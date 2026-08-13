<?php
/**
 * Schema-driven command base.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Commands;

use Nought\DolPress\Contracts\CommandInterface;
use Nought\DolPress\Rendering\RenderContext;

abstract class AbstractCommand implements CommandInterface {
	abstract public function code(): string;

	/**
	 * @return array<string, mixed>
	 */
	abstract public function definition(): array;

	public function group(): string {
		return (string) ( $this->definition()['group'] ?? 'presentation' );
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @param list<string>         $flags
	 * @return array{ok: bool, arguments: array<string, mixed>, diagnostics: list<array<string, mixed>>}
	 */
	public function validate( array $arguments, array $flags, RenderContext $context ): array {
		$schema        = $this->definition()['schema'] ?? array();
		$positional    = $schema['positional'] ?? array();
		$named         = $schema['named'] ?? array();
		$allowed_flags = $schema['flags'] ?? array();
		$diagnostics   = array();
		$out           = array();
		$index         = 0;

		foreach ( $flags as $flag ) {
			if ( ! in_array( $flag, $allowed_flags, true ) ) {
				$diagnostics[] = $this->diag( 'W_BAD_FLAG', sprintf( 'Unknown flag +%s on $%s$.', $flag, $this->code() ), 'warning' );
			}
		}

		foreach ( $positional as $field ) {
			$key = '_' . $index;
			if ( array_key_exists( $key, $arguments ) ) {
				$checked = $this->coerce( $arguments[ $key ], $field );
				if ( $checked['ok'] ) {
					$out[ $field['name'] ] = $checked['value'];
				} else {
					$diagnostics[] = $this->diag( 'E_BAD_VALUE', $checked['message'] );
				}
			} elseif ( ! empty( $field['required'] ) ) {
				$diagnostics[] = $this->diag( 'E_MISSING', sprintf( 'Missing required argument %s.', $field['name'] ) );
			} elseif ( array_key_exists( 'default', $field ) ) {
				$out[ $field['name'] ] = $field['default'];
			}
			++$index;
		}

		foreach ( $named as $field ) {
			$name = strtoupper( (string) $field['name'] );
			if ( array_key_exists( $name, $arguments ) ) {
				$checked = $this->coerce( $arguments[ $name ], $field );
				if ( $checked['ok'] ) {
					$out[ $name ] = $checked['value'];
				} else {
					$diagnostics[] = $this->diag( 'E_BAD_VALUE', $checked['message'] );
				}
			} elseif ( ! empty( $field['required'] ) && ! array_key_exists( $name, $out ) ) {
				$diagnostics[] = $this->diag( 'E_MISSING', sprintf( 'Missing required argument %s.', $name ) );
			} elseif ( array_key_exists( 'default', $field ) && ! array_key_exists( $name, $out ) ) {
				$out[ $name ] = $field['default'];
			}
		}

		foreach ( $arguments as $key => $value ) {
			if ( str_starts_with( (string) $key, '_' ) ) {
				continue;
			}
			$known = false;
			foreach ( $named as $field ) {
				if ( strtoupper( (string) $field['name'] ) === strtoupper( (string) $key ) ) {
					$known = true;
					break;
				}
			}
			if ( ! $known && ! isset( $out[ strtoupper( (string) $key ) ] ) ) {
				$diagnostics[] = $this->diag( 'W_UNKNOWN_ARG', sprintf( 'Unknown argument %s on $%s$.', $key, $this->code() ), 'warning' );
			}
		}

		$has_error = false;
		foreach ( $diagnostics as $item ) {
			if ( 'error' === $item['severity'] ) {
				$has_error = true;
				break;
			}
		}

		return array(
			'ok'          => ! $has_error,
			'arguments'   => $out,
			'diagnostics' => $diagnostics,
		);
	}

	/**
	 * @param array<string, mixed> $field
	 * @return array{ok: bool, value: mixed, message: string}
	 */
	private function coerce( mixed $value, array $field ): array {
		$type = (string) ( $field['type'] ?? 'string' );
		$name = (string) ( $field['name'] ?? 'value' );

		if ( 'int' === $type ) {
			if ( is_numeric( $value ) ) {
				$int = (int) $value;
				$min = $field['min'] ?? null;
				$max = $field['max'] ?? null;
				if ( is_int( $min ) && $int < $min ) {
					$int = $min;
				}
				if ( is_int( $max ) && $int > $max ) {
					$int = $max;
				}
				return array(
					'ok'      => true,
					'value'   => $int,
					'message' => '',
				);
			}
			return array(
				'ok'      => false,
				'value'   => null,
				'message' => sprintf( '%s must be an integer.', $name ),
			);
		}

		if ( 'bool' === $type ) {
			if ( is_bool( $value ) ) {
				return array(
					'ok'      => true,
					'value'   => $value,
					'message' => '',
				);
			}
			if ( is_string( $value ) && in_array( strtoupper( $value ), array( 'TRUE', 'FALSE', '1', '0' ), true ) ) {
				return array(
					'ok'      => true,
					'value'   => in_array( strtoupper( $value ), array( 'TRUE', '1' ), true ),
					'message' => '',
				);
			}
			return array(
				'ok'      => false,
				'value'   => null,
				'message' => sprintf( '%s must be a boolean.', $name ),
			);
		}

		if ( 'enum' === $type ) {
			$choices = $field['choices'] ?? array();
			$str     = is_string( $value ) || is_int( $value ) ? (string) $value : '';
			foreach ( $choices as $choice ) {
				if ( strcasecmp( (string) $choice, $str ) === 0 ) {
					return array(
						'ok'      => true,
						'value'   => $choice,
						'message' => '',
					);
				}
			}
			return array(
				'ok'      => false,
				'value'   => null,
				'message' => sprintf( '%s must be one of: %s.', $name, implode( ', ', $choices ) ),
			);
		}

		if ( 'url' === $type ) {
			$str = is_string( $value ) ? trim( $value ) : '';
			if ( '' === $str ) {
				return array(
					'ok'      => true,
					'value'   => '',
					'message' => '',
				);
			}
			$parts  = wp_parse_url( $str );
			$scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
			if ( ! in_array( $scheme, array( 'http', 'https', 'mailto' ), true ) ) {
				return array(
					'ok'      => false,
					'value'   => null,
					'message' => sprintf( '%s uses a disallowed URL protocol.', $name ),
				);
			}
			return array(
				'ok'      => true,
				'value'   => esc_url_raw( $str ),
				'message' => '',
			);
		}

		$string = is_scalar( $value ) ? (string) $value : '';
		$max    = $field['max_length'] ?? 5000;
		if ( strlen( $string ) > $max ) {
			$string = substr( $string, 0, $max );
		}

		return array(
			'ok'      => true,
			'value'   => $string,
			'message' => '',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function diag( string $code, string $message, string $severity = 'error' ): array {
		return array(
			'severity' => $severity,
			'code'     => $code,
			'message'  => $message,
		);
	}

	/**
	 * @param array<string, scalar|null|bool> $attrs
	 */
	protected function el( string $tag, string $inner, array $attrs = array() ): string {
		$html = '<' . $tag;
		foreach ( $attrs as $name => $value ) {
			if ( null === $value || false === $value || '' === $value ) {
				continue;
			}
			if ( true === $value ) {
				$html .= ' ' . $name;
				continue;
			}
			$html .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}
		return $html . '>' . $inner . '</' . $tag . '>';
	}
}
