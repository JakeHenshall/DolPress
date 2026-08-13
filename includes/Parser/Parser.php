<?php
/**
 * Authoritative DolPress parser with error recovery.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Parser;

require_once __DIR__ . '/Ast.php';

use Nought\DolPress\Contracts\CommandRegistryInterface;
use Nought\DolPress\Contracts\ParserInterface;
use Nought\DolPress\Support\SettingsRepository;

final class Parser implements ParserInterface {
	/**
	 * @var list<string>
	 */
	private array $known;

	/**
	 * @var list<string>
	 */
	private array $paired;

	public function __construct(
		private readonly ?SettingsRepository $settings = null,
		private readonly ?CommandRegistryInterface $commands = null
	) {
		$this->known  = $commands ? $commands->codes() : Codes::CORE;
		$this->paired = $commands ? $commands->paired() : Codes::PAIRED;
	}

	public function refresh_known(): void {
		if ( $this->commands ) {
			$this->known  = $this->commands->codes();
			$this->paired = $this->commands->paired();
		}
	}

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

		$token_count = count( $tokens );
		for ( $ti = 0; $ti < $token_count; $ti++ ) {
			$token = $tokens[ $ti ];
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
			$unknown   = ! in_array( $code, $this->known, true );
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

			if ( in_array( $code, $this->paired, true ) && ! $this->is_indent_scoped_tree( $code, $tokens, $ti ) ) {
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
			$open = array_pop( $stack );
			if ( 'TR' === $open['code'] ) {
				$this->append( $children, $stack, $open['node'] );
				foreach ( $open['children'] as $inner ) {
					$this->append( $children, $stack, $inner );
				}
				continue;
			}

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

		$children = ( new TreeNormalizer( $max_nest ) )->normalize( $children, $diagnostics );
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

	/**
	 * Native DolDoc trees are widgets followed by $ID,+n$, not $/TR$ pairs.
	 *
	 * @param list<Token> $tokens
	 */
	private function is_indent_scoped_tree( string $code, array $tokens, int $index ): bool {
		if ( 'TR' !== $code ) {
			return false;
		}

		$count = count( $tokens );
		for ( $i = $index + 1; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( Token::TEXT === $token->kind ) {
				if ( '' === trim( $token->value ) ) {
					continue;
				}

				return false;
			}

			if ( Token::COMMAND_START !== $token->kind ) {
				return false;
			}

			$payload = json_decode( $token->value, true );
			if ( ! is_array( $payload ) || 'ID' !== strtoupper( (string) ( $payload['code'] ?? '' ) ) ) {
				return false;
			}

			$arguments = is_array( $payload['arguments'] ?? null ) ? $payload['arguments'] : array();

			return TreeNormalizer::delta_from_arguments( $arguments ) > 0;
		}

		return false;
	}
}
