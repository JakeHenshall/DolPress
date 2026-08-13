<?php
/**
 * Settings sanitisation without WordPress side effects.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Support;

final class SettingsSanitizer {
	public const MODES = array( 'source', 'rendered', 'split' );

	public const INVALID_COMMAND_BEHAVIOURS = array( 'hide', 'escape' );

	public const FALLBACKS = array( 'escaped', 'snapshot' );

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled_post_types'     => PostTypePolicy::DEFAULT_ENABLED,
			'default_mode'           => 'source',
			'strict_diagnostics'     => false,
			'public_invalid_command' => 'hide',
			'allowed_meta_keys'      => array(),
			'max_loop_count'         => 10,
			'max_command_count'      => 200,
			'max_source_bytes'       => 102400,
			'max_token_count'        => 20000,
			'max_nesting_depth'      => 8,
			'max_media_count'        => 40,
			'max_query_count'        => 10,
			'max_render_ms'          => 1500,
			'cache_enabled'          => true,
			'fallback_behaviour'     => 'escaped',
			'uninstall_delete_data'  => false,
			'global_disable'         => false,
		);
	}

	/**
	 * @param mixed $input
	 * @return array<string, mixed>
	 */
	public static function sanitise( $input, ?callable $is_public_post_type = null ): array {
		$defaults = self::defaults();
		$raw      = is_array( $input ) ? $input : array();

		$types = $raw['enabled_post_types'] ?? $defaults['enabled_post_types'];
		if ( ! is_array( $types ) ) {
			$types = $defaults['enabled_post_types'];
		}

		$mode = isset( $raw['default_mode'] ) && is_string( $raw['default_mode'] )
			? strtolower( $raw['default_mode'] )
			: $defaults['default_mode'];

		$invalid = isset( $raw['public_invalid_command'] ) && is_string( $raw['public_invalid_command'] )
			? strtolower( $raw['public_invalid_command'] )
			: $defaults['public_invalid_command'];

		$fallback = isset( $raw['fallback_behaviour'] ) && is_string( $raw['fallback_behaviour'] )
			? strtolower( $raw['fallback_behaviour'] )
			: $defaults['fallback_behaviour'];

		$meta_keys = $raw['allowed_meta_keys'] ?? array();
		if ( is_string( $meta_keys ) ) {
			$split     = preg_split( '/[\s,]+/', $meta_keys );
			$meta_keys = is_array( $split ) ? $split : array();
		}

		return array(
			'enabled_post_types'     => PostTypePolicy::sanitise_enabled( array_values( $types ), $is_public_post_type ),
			'default_mode'           => in_array( $mode, self::MODES, true ) ? $mode : $defaults['default_mode'],
			'strict_diagnostics'     => self::to_bool( $raw['strict_diagnostics'] ?? false ),
			'public_invalid_command' => in_array( $invalid, self::INVALID_COMMAND_BEHAVIOURS, true ) ? $invalid : $defaults['public_invalid_command'],
			'allowed_meta_keys'      => self::sanitise_meta_keys( is_array( $meta_keys ) ? $meta_keys : array() ),
			'max_loop_count'         => self::clamp_int( $raw['max_loop_count'] ?? $defaults['max_loop_count'], 1, 20, 10 ),
			'max_command_count'      => self::clamp_int( $raw['max_command_count'] ?? $defaults['max_command_count'], 10, 1000, 200 ),
			'max_source_bytes'       => self::clamp_int( $raw['max_source_bytes'] ?? $defaults['max_source_bytes'], 1024, 1048576, 102400 ),
			'max_token_count'        => self::clamp_int( $raw['max_token_count'] ?? $defaults['max_token_count'], 100, 100000, 20000 ),
			'max_nesting_depth'      => self::clamp_int( $raw['max_nesting_depth'] ?? $defaults['max_nesting_depth'], 1, 16, 8 ),
			'max_media_count'        => self::clamp_int( $raw['max_media_count'] ?? $defaults['max_media_count'], 1, 100, 40 ),
			'max_query_count'        => self::clamp_int( $raw['max_query_count'] ?? $defaults['max_query_count'], 1, 20, 10 ),
			'max_render_ms'          => self::clamp_int( $raw['max_render_ms'] ?? $defaults['max_render_ms'], 100, 10000, 1500 ),
			'cache_enabled'          => self::to_bool( $raw['cache_enabled'] ?? true ),
			'fallback_behaviour'     => in_array( $fallback, self::FALLBACKS, true ) ? $fallback : $defaults['fallback_behaviour'],
			'uninstall_delete_data'  => self::to_bool( $raw['uninstall_delete_data'] ?? false ),
			'global_disable'         => self::to_bool( $raw['global_disable'] ?? false ),
		);
	}

	/**
	 * @param list<mixed> $keys
	 * @return list<string>
	 */
	public static function sanitise_meta_keys( array $keys ): array {
		$clean = array();

		foreach ( $keys as $key ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			$slug = trim( $key );
			if ( '' === $slug || str_starts_with( $slug, '_' ) ) {
				continue;
			}

			if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $slug ) ) {
				continue;
			}

			$clean[] = $slug;
		}

		return array_values( array_unique( $clean ) );
	}

	public static function to_bool( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 0 !== (int) $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return false;
	}

	public static function clamp_int( mixed $value, int $min, int $max, int $default ): int {
		if ( is_string( $value ) && is_numeric( $value ) ) {
			$value = (int) $value;
		}

		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			return $default;
		}

		$int = (int) $value;

		return max( $min, min( $max, $int ) );
	}
}
