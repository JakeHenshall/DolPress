<?php
/**
 * Persisted plugin settings.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Support;

final class SettingsRepository {
	public const OPTION_KEY = 'dolpress_settings';

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_KEY, array() ) : array();

		return SettingsSanitizer::sanitise( $stored, array( $this, 'is_public_post_type' ) );
	}

	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->all();

		return $all[ $key ] ?? $default;
	}

	public function ensure_defaults(): void {
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}

		$existing = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $existing ) ) {
			add_option( self::OPTION_KEY, SettingsSanitizer::defaults(), '', false );
		}
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function sanitise_from_request( $input ): array {
		return SettingsSanitizer::sanitise( $input, array( $this, 'is_public_post_type' ) );
	}

	public function is_enabled_post_type( string $type ): bool {
		$enabled = $this->get( 'enabled_post_types', PostTypePolicy::DEFAULT_ENABLED );

		return is_array( $enabled ) && in_array( $type, $enabled, true );
	}

	public function is_globally_disabled(): bool {
		return (bool) $this->get( 'global_disable', false );
	}

	public function is_public_post_type( string $type ): bool {
		if ( in_array( $type, PostTypePolicy::DEFAULT_ENABLED, true ) ) {
			return true;
		}

		if ( ! function_exists( 'get_post_type_object' ) ) {
			return false;
		}

		$object = get_post_type_object( $type );

		return $object instanceof \WP_Post_Type && $object->public && ! PostTypePolicy::is_blocked( $type );
	}

	/**
	 * @return list<string>
	 */
	public function allowed_meta_keys(): array {
		$keys = $this->get( 'allowed_meta_keys', array() );

		return is_array( $keys ) ? array_values( $keys ) : array();
	}

	public function is_meta_key_allowed( string $key ): bool {
		return in_array( $key, $this->allowed_meta_keys(), true );
	}

	/**
	 * @return list<string>
	 */
	public function allowed_macros(): array {
		$names = $this->get( 'allowed_macros', array() );

		return is_array( $names ) ? array_values( $names ) : array();
	}

	public function html_code_enabled(): bool {
		return (bool) $this->get( 'html_code_enabled', false );
	}
}
