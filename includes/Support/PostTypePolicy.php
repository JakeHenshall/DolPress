<?php
/**
 * Internal and public post-type policy.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Support;

final class PostTypePolicy {
	public const DEFAULT_ENABLED = array( 'post', 'page' );

	public const BLOCKED = array(
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_block',
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		'wp_navigation',
		'wp_font_family',
		'wp_font_face',
		'wp_pattern',
		'wp_autosave',
		'wp_registered_pattern',
	);

	/**
	 * @param array<mixed> $types
	 * @return list<string>
	 */
	public static function sanitise_enabled( array $types, ?callable $is_public = null ): array {
		$clean = array();

		foreach ( $types as $type ) {
			if ( ! is_string( $type ) ) {
				continue;
			}

			$slug = strtolower( trim( $type ) );
			if ( '' === $slug || ! preg_match( '/^[a-z0-9_-]{1,20}$/', $slug ) ) {
				continue;
			}

			if ( in_array( $slug, self::BLOCKED, true ) ) {
				continue;
			}

			if ( in_array( $slug, self::DEFAULT_ENABLED, true ) ) {
				$clean[] = $slug;
				continue;
			}

			if ( null !== $is_public && ! $is_public( $slug ) ) {
				continue;
			}

			$clean[] = $slug;
		}

		$clean = array_values( array_unique( $clean ) );

		return $clean;
	}

	public static function is_blocked( string $type ): bool {
		return in_array( $type, self::BLOCKED, true );
	}
}
