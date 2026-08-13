<?php
/**
 * Authoritative DolPress parser with error recovery.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Parser;

require_once __DIR__ . '/Ast.php';

use Nought\DolPress\Contracts\ParserInterface;
use Nought\DolPress\Support\SettingsRepository;

final class Parser implements ParserInterface {
	public const KNOWN_CODES = array(
		'TX',
		'CR',
		'FG',
		'BG',
		'UL',
		'IV',
		'HL',
		'LK',
		'BT',
		'TR',
		'IM',
		'HR',
		'WS',
		'WG',
		'WT',
		'WA',
		'WN',
		'WL',
		'WP',
		'WC',
		'WX',
		'WM',
		'WB',
	);

	public const PAIRED = array( 'TR' );

	public function __construct( private readonly ?SettingsRepository $settings = null ) {}

	public function parse( string $source ): ParseResult {
		$lexer       = new Lexer( $this->settings );
		$lexed       = $lexer->tokenize( $source );
		$tokens      = $lexed['tokens'];
		$diagnostics = $lexed['diagnostics'];

		$children = array();
		$stack    = array();
		$max_nest = (int) ( $this->settings?->get( 'max_nesting_depth', 8 ) ?? 8 );
		$max_cmds = (int) ( $this->settings?->get( 'max_command_count', 200 ) ?? 200 );
		$commands = 0;

		foreach ( $tokens as $token ) {
			if ( Token::EOF === $token->kind ) {
				break;
			}

			if ( Token::TEXT === $token->kind ) {
				if ( '' !== $token->value ) {
					$this->append( $children, $stack, new TextNode( $token->value, $token->offset, $token->length, $token->line, $token->column ) );
				}
				continue;
			}

			if ( Token::LITERAL_DOLLAR === $token->kind ) {
				$this->append( $children, $stack, new TextNode( '$', $token->offset, $token->length, $token->line, $token->column ) );
				continue;
			}

			if ( Token::ERROR === $token->kind ) {
				$this->append(
					$children,
					$stack,
					new CommandNode( '??', array(), array(), array(), true, true, $token->value, $token->offset, $token->length, $token->line, $token->column )
				);
				continue;
			}

			if ( Token::COMMAND_END === $token->kind ) {
				$code = strtoupper( $token->value );
				if ( array() === $stack || end( $stack )['code'] !== $code ) {
					$diagnostics[] = new Diagnostic(
						'error',
						'E_UNMATCHED_CLOSE',
						sprintf( 'Closing $/%s$ does not match an open command.', $code ),
						$token->line,
						$token->column,
						$token->offset,
						$token->length
					);
					continue;
				}

				$open   = array_pop( $stack );
				$node   = $open['node'];
				$inner  = $open['children'];
				$length = $token->end_offset() - $node->offset;
				$closed = new CommandNode(
					$node->code,
					$node->flags,
					$node->arguments,
					$inner,
					$node->unknown,
					$node->malformed,
					substr( $source, $node->offset, $length ),
					$node->offset,
					$length,
					$node->line,
					$node->column
				);
				$this->append( $children, $stack, $closed );
				continue;
			}

			if ( Token::COMMAND_START !== $token->kind ) {
				continue;
			}

			++$commands;
			if ( $commands > $max_cmds ) {
				$diagnostics[] = new Diagnostic(
					'error',
					'E_COMMAND_LIMIT',
					sprintf( 'Command limit of %d reached.', $max_cmds ),
					$token->line,
					$token->column,
					$token->offset,
					$token->length
				);
				continue;
			}

			$payload = json_decode( $token->value, true );
			if ( ! is_array( $payload ) ) {
				continue;
			}

			$code      = strtoupper( (string) ( $payload['code'] ?? '' ) );
			$unknown   = ! in_array( $code, self::KNOWN_CODES, true );
			$malformed = false;
			$flags     = is_array( $payload['flags'] ?? null ) ? $payload['flags'] : array();
			$arguments = is_array( $payload['arguments'] ?? null ) ? $payload['arguments'] : array();
			$raw       = (string) ( $payload['raw'] ?? $token->value );

			if ( $unknown ) {
				$diagnostics[] = new Diagnostic(
					'warning',
					'W_UNKNOWN_COMMAND',
					sprintf( 'Unknown command $%s$.', $code ),
					$token->line,
					$token->column,
					$token->offset,
					$token->length,
					'Unknown commands are kept in the document but never executed.'
				);
			}

			$node = new CommandNode(
				$code,
				array_values( array_map( 'strval', $flags ) ),
				$arguments,
				array(),
				$unknown,
				$malformed,
				$raw,
				$token->offset,
				$token->length,
				$token->line,
				$token->column
			);

			if ( in_array( $code, self::PAIRED, true ) ) {
				if ( count( $stack ) >= $max_nest ) {
					$diagnostics[] = new Diagnostic(
						'error',
						'E_NESTING_LIMIT',
						sprintf( 'Nesting exceeds the maximum depth of %d.', $max_nest ),
						$token->line,
						$token->column,
						$token->offset,
						$token->length
					);
					$this->append( $children, $stack, $node );
					continue;
				}

				$stack[] = array(
					'code'     => $code,
					'node'     => $node,
					'children' => array(),
				);
				continue;
			}

			$this->append( $children, $stack, $node );
		}

		while ( array() !== $stack ) {
			$open          = array_pop( $stack );
			$diagnostics[] = new Diagnostic(
				'error',
				'E_UNCLOSED',
				sprintf( 'Command $%s$ was not closed.', $open['code'] ),
				$open['node']->line,
				$open['node']->column,
				$open['node']->offset,
				$open['node']->length,
				sprintf( 'Add $/%s$ to close the command.', $open['code'] )
			);
			$node          = $open['node'];
			$closed        = new CommandNode(
				$node->code,
				$node->flags,
				$node->arguments,
				$open['children'],
				$node->unknown,
				true,
				$node->raw,
				$node->offset,
				$node->length,
				$node->line,
				$node->column
			);
			$this->append( $children, $stack, $closed );
		}

		$document = new DocumentNode( $children, 0, strlen( $source ) );

		return new ParseResult( $document, $diagnostics, $source );
	}

	/**
	 * @param list<Node> $children
	 * @param list<array<string, mixed>> $stack
	 */
	private function append( array &$children, array &$stack, Node $node ): void {
		if ( array() === $stack ) {
			$children[] = $node;
			return;
		}

		$index                         = count( $stack ) - 1;
		$stack[ $index ]['children'][] = $node;
	}
}
