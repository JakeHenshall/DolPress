<?php
/**
 * Bounded lexer for DolPress v0.1 source.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Parser;

use Nought\DolPress\Support\SettingsRepository;

final class Lexer {
	private string $source;
	private int $length;
	private int $index       = 0;
	private int $line        = 1;
	private int $column      = 1;
	private int $token_count = 0;
	private int $max_tokens;

	/**
	 * @var list<Diagnostic>
	 */
	private array $diagnostics = array();

	public function __construct( private readonly ?SettingsRepository $settings = null ) {
		$this->max_tokens = (int) ( $this->settings?->get( 'max_token_count', 20000 ) ?? 20000 );
	}

	/**
	 * @return array{tokens: list<Token>, diagnostics: list<Diagnostic>}
	 */
	public function tokenize( string $source ): array {
		$this->source      = $source;
		$this->length      = strlen( $source );
		$this->index       = 0;
		$this->line        = 1;
		$this->column      = 1;
		$this->token_count = 0;
		$this->diagnostics = array();

		$max_bytes = (int) ( $this->settings?->get( 'max_source_bytes', 102400 ) ?? 102400 );
		if ( $this->length > $max_bytes ) {
			$this->diagnostics[] = new Diagnostic(
				'error',
				'E_SOURCE_TOO_LARGE',
				sprintf( 'Source exceeds the maximum of %d bytes.', $max_bytes ),
				1,
				1,
				0,
				$this->length,
				'Reduce the document size or raise the limit in DolPress settings.'
			);
			$this->source        = substr( $source, 0, $max_bytes );
			$this->length        = strlen( $this->source );
		}

		$tokens = array();
		while ( $this->index < $this->length ) {
			if ( $this->token_count >= $this->max_tokens ) {
				$this->diagnostics[] = new Diagnostic(
					'error',
					'E_TOKEN_LIMIT',
					sprintf( 'Token limit of %d reached; remaining source was skipped.', $this->max_tokens ),
					$this->line,
					$this->column,
					$this->index,
					$this->length - $this->index
				);
				break;
			}

			$char = $this->source[ $this->index ];
			if ( '$' === $char ) {
				$tokens[] = $this->read_dollar_or_command();
				continue;
			}

			$tokens[] = $this->read_text();
		}

		$tokens[] = new Token( Token::EOF, '', $this->index, 0, $this->line, $this->column );

		return array(
			'tokens'      => $tokens,
			'diagnostics' => $this->diagnostics,
		);
	}

	private function read_text(): Token {
		$start        = $this->index;
		$start_line   = $this->line;
		$start_column = $this->column;
		$buffer       = '';

		while ( $this->index < $this->length ) {
			$char = $this->source[ $this->index ];
			if ( '$' === $char ) {
				break;
			}
			$buffer .= $char;
			$this->advance();
		}

		return $this->emit( Token::TEXT, $buffer, $start, $this->index - $start, $start_line, $start_column );
	}

	private function read_dollar_or_command(): Token {
		$start        = $this->index;
		$start_line   = $this->line;
		$start_column = $this->column;

		if ( $this->peek( 1 ) === '$' ) {
			$this->advance();
			$this->advance();
			return $this->emit( Token::LITERAL_DOLLAR, '$', $start, 2, $start_line, $start_column );
		}

		$this->advance();

		if ( $this->index < $this->length && '/' === $this->source[ $this->index ] ) {
			$this->advance();
			$code = $this->read_code();
			if ( 2 !== strlen( $code ) ) {
				$this->diagnostics[] = $this->error_at( 'E_CLOSE_CODE', 'Closing command is missing a two-character code.', $start, $start_line, $start_column );
				$this->consume_until_dollar();
				return $this->emit( Token::ERROR, substr( $this->source, $start, $this->index - $start ), $start, $this->index - $start, $start_line, $start_column );
			}
			$this->skip_spaces();
			if ( $this->index < $this->length && '$' === $this->source[ $this->index ] ) {
				$this->advance();
			} else {
				$this->diagnostics[] = $this->error_at( 'E_UNTERMINATED', 'Command is missing a closing dollar.', $start, $start_line, $start_column );
			}

			return $this->emit( Token::COMMAND_END, $code, $start, $this->index - $start, $start_line, $start_column );
		}

		$code = $this->read_code();
		if ( 2 !== strlen( $code ) ) {
			$this->diagnostics[] = $this->error_at(
				'E_BAD_CODE',
				'Commands must start with a two-character alphabetic code.',
				$start,
				$start_line,
				$start_column,
				'Use a command such as $CR$ or $TX,"text"$.'
			);
			$this->consume_until_dollar();
			return $this->emit( Token::ERROR, substr( $this->source, $start, $this->index - $start ), $start, $this->index - $start, $start_line, $start_column );
		}

		return $this->read_command_body( $code, $start, $start_line, $start_column );
	}

	private function read_command_body( string $code, int $start, int $start_line, int $start_column ): Token {
		$flags     = array();
		$arguments = array();

		while ( $this->index < $this->length && '+' === $this->source[ $this->index ] ) {
			$this->advance();
			$flag = $this->read_ident();
			if ( '' === $flag ) {
				$this->diagnostics[] = $this->error_at( 'E_BAD_FLAG', 'Flag names must be alphanumeric.', $this->index, $this->line, $this->column );
				break;
			}
			$flags[] = strtoupper( $flag );
		}

		$this->skip_spaces();

		if ( $this->index < $this->length && ',' === $this->source[ $this->index ] ) {
			$this->advance();
			$this->skip_spaces();
			$arguments = $this->read_arguments( $start, $start_line, $start_column );
		}

		if ( $this->index < $this->length && '$' === $this->source[ $this->index ] ) {
			$this->advance();
		} else {
			$this->diagnostics[] = $this->error_at( 'E_UNTERMINATED', 'Command is missing a closing dollar.', $start, $start_line, $start_column );
			$this->consume_until_dollar();
		}

		$raw = substr( $this->source, $start, $this->index - $start );

		$encoded = function_exists( 'wp_json_encode' )
			? wp_json_encode(
				array(
					'code'      => strtoupper( $code ),
					'flags'     => $flags,
					'arguments' => $arguments,
					'raw'       => $raw,
				)
			)
			: json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				array(
					'code'      => strtoupper( $code ),
					'flags'     => $flags,
					'arguments' => $arguments,
					'raw'       => $raw,
				),
				JSON_UNESCAPED_UNICODE
			);

		return $this->emit(
			Token::COMMAND_START,
			is_string( $encoded ) ? $encoded : $raw,
			$start,
			$this->index - $start,
			$start_line,
			$start_column
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function read_arguments( int $start, int $start_line, int $start_column ): array {
		$arguments = array();

		while ( $this->index < $this->length && '$' !== $this->source[ $this->index ] ) {
			$this->skip_spaces();
			if ( $this->index >= $this->length || '$' === $this->source[ $this->index ] ) {
				break;
			}

			if ( ',' === $this->source[ $this->index ] ) {
				$this->advance();
				continue;
			}

			$argument = $this->read_argument();
			if ( null !== $argument ) {
				$arguments[] = $argument;
			} else {
				$this->diagnostics[] = $this->error_at( 'E_BAD_ARGUMENT', 'Could not parse command argument.', $this->index, $this->line, $this->column );
				$this->skip_until_comma_or_end();
			}
		}

		return $arguments;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function read_argument(): ?array {
		$this->skip_spaces();
		if ( $this->index >= $this->length ) {
			return null;
		}

		$char = $this->source[ $this->index ];

		if ( '"' === $char ) {
			return array(
				'name'  => '',
				'value' => $this->read_string(),
				'kind'  => 'string',
			);
		}

		if ( $this->is_digit( $char ) || ( '-' === $char && $this->is_digit( $this->peek( 1 ) ) ) ) {
			return array(
				'name'  => '',
				'value' => $this->read_number(),
				'kind'  => 'number',
			);
		}

		$ident = $this->read_ident();
		if ( '' === $ident ) {
			return null;
		}

		$this->skip_spaces();
		if ( $this->index < $this->length && '=' === $this->source[ $this->index ] ) {
			$this->advance();
			$this->skip_spaces();
			$value = $this->read_value();
			return array(
				'name'  => strtoupper( $ident ),
				'value' => $value['value'],
				'kind'  => $value['kind'],
			);
		}

		$upper = strtoupper( $ident );
		if ( in_array( $upper, array( 'TRUE', 'FALSE' ), true ) ) {
			return array(
				'name'  => '',
				'value' => 'TRUE' === $upper,
				'kind'  => 'boolean',
			);
		}

		return array(
			'name'  => '',
			'value' => $ident,
			'kind'  => 'ident',
		);
	}

	/**
	 * @return array{value: mixed, kind: string}
	 */
	private function read_value(): array {
		if ( $this->index >= $this->length ) {
			return array(
				'value' => '',
				'kind'  => 'ident',
			);
		}

		$char = $this->source[ $this->index ];
		if ( '"' === $char ) {
			return array(
				'value' => $this->read_string(),
				'kind'  => 'string',
			);
		}

		if ( $this->is_digit( $char ) || ( '-' === $char && $this->is_digit( $this->peek( 1 ) ) ) ) {
			return array(
				'value' => $this->read_number(),
				'kind'  => 'number',
			);
		}

		$ident = $this->read_ident();
		$upper = strtoupper( $ident );
		if ( in_array( $upper, array( 'TRUE', 'FALSE' ), true ) ) {
			return array(
				'value' => 'TRUE' === $upper,
				'kind'  => 'boolean',
			);
		}

		return array(
			'value' => $ident,
			'kind'  => 'ident',
		);
	}

	private function read_string(): string {
		$this->advance();
		$buffer = '';

		while ( $this->index < $this->length ) {
			$char = $this->source[ $this->index ];
			if ( '"' === $char ) {
				$this->advance();
				return $buffer;
			}

			if ( '\\' === $char ) {
				$next = $this->peek( 1 );
				$this->advance();
				$this->advance();
				$buffer .= match ( $next ) {
					'n' => "\n",
					't' => "\t",
					'"' => '"',
					'\\' => '\\',
					'$' => '$',
					default => $next,
				};
				continue;
			}

			$buffer .= $char;
			$this->advance();
		}

		$this->diagnostics[] = $this->error_at( 'E_UNTERMINATED_STRING', 'String is missing a closing quote.', $this->index, $this->line, $this->column );
		return $buffer;
	}

	private function read_number(): int|float {
		$start = $this->index;
		if ( '-' === $this->source[ $this->index ] ) {
			$this->advance();
		}

		while ( $this->index < $this->length && $this->is_digit( $this->source[ $this->index ] ) ) {
			$this->advance();
		}

		if ( $this->index < $this->length && '.' === $this->source[ $this->index ] && $this->is_digit( $this->peek( 1 ) ) ) {
			$this->advance();
			while ( $this->index < $this->length && $this->is_digit( $this->source[ $this->index ] ) ) {
				$this->advance();
			}
			return (float) substr( $this->source, $start, $this->index - $start );
		}

		return (int) substr( $this->source, $start, $this->index - $start );
	}

	private function read_code(): string {
		$buffer = '';
		for ( $i = 0; $i < 2; $i++ ) {
			if ( $this->index >= $this->length ) {
				break;
			}
			$char = $this->source[ $this->index ];
			if ( ! preg_match( '/[A-Za-z]/', $char ) ) {
				break;
			}
			$buffer .= $char;
			$this->advance();
		}

		return $buffer;
	}

	private function read_ident(): string {
		$buffer = '';
		while ( $this->index < $this->length && preg_match( '/[A-Za-z0-9_]/', $this->source[ $this->index ] ) ) {
			$buffer .= $this->source[ $this->index ];
			$this->advance();
		}

		return $buffer;
	}

	private function consume_until_dollar(): void {
		while ( $this->index < $this->length && '$' !== $this->source[ $this->index ] ) {
			$this->advance();
		}
		if ( $this->index < $this->length && '$' === $this->source[ $this->index ] ) {
			$this->advance();
		}
	}

	private function skip_until_comma_or_end(): void {
		while ( $this->index < $this->length && ! in_array( $this->source[ $this->index ], array( ',', '$' ), true ) ) {
			$this->advance();
		}
	}

	private function skip_spaces(): void {
		while ( $this->index < $this->length && in_array( $this->source[ $this->index ], array( ' ', "\t" ), true ) ) {
			$this->advance();
		}
	}

	private function peek( int $ahead ): string {
		$pos = $this->index + $ahead;
		return $pos < $this->length ? $this->source[ $pos ] : '';
	}

	private function advance(): void {
		if ( $this->index >= $this->length ) {
			return;
		}

		$char = $this->source[ $this->index ];
		if ( "\n" === $char ) {
			++$this->line;
			$this->column = 1;
		} else {
			++$this->column;
		}
		++$this->index;
	}

	private function is_digit( string $char ): bool {
		return 1 === strlen( $char ) && $char >= '0' && $char <= '9';
	}

	private function emit( string $kind, string $value, int $offset, int $length, int $line, int $column ): Token {
		++$this->token_count;
		return new Token( $kind, $value, $offset, $length, $line, $column );
	}

	private function error_at( string $code, string $message, int $offset, int $line, int $column, ?string $help = null ): Diagnostic {
		return new Diagnostic( 'error', $code, $message, $line, $column, $offset, 1, $help );
	}
}
