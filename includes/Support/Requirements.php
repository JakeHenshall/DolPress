<?php
/**
 * Environment requirement checks, independent of WordPress globals where possible.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Support;

final class Requirements {
	/**
	 * @return array{ok: bool, messages: list<string>}
	 */
	public static function check( string $php_version, string $wp_version, string $min_php = DOLPRESS_MIN_PHP, string $min_wp = DOLPRESS_MIN_WP ): array {
		$messages = array();

		if ( version_compare( $php_version, $min_php, '<' ) ) {
			$messages[] = sprintf(
				'DolPress requires PHP %s or newer. This site is running PHP %s.',
				$min_php,
				$php_version
			);
		}

		$normalized_wp = self::normalize_wp_version( $wp_version );
		if ( version_compare( $normalized_wp, $min_wp, '<' ) ) {
			$messages[] = sprintf(
				'DolPress requires WordPress %s or newer. This site is running WordPress %s.',
				$min_wp,
				$wp_version
			);
		}

		return array(
			'ok'       => array() === $messages,
			'messages' => $messages,
		);
	}

	public static function normalize_wp_version( string $version ): string {
		if ( preg_match( '/^\d+\.\d+(?:\.\d+)?/', $version, $matches ) ) {
			return $matches[0];
		}

		return '0';
	}
}
