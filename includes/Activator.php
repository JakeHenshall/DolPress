<?php
/**
 * Activation guards.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress;

use Nought\DolPress\Support\Requirements;
use Nought\DolPress\Support\SettingsRepository;

final class Activator {
	public static function activate(): void {
		$php_version = PHP_VERSION;
		$wp_version  = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '0';
		$result      = Requirements::check( $php_version, $wp_version );

		if ( ! $result['ok'] ) {
			if ( function_exists( 'deactivate_plugins' ) ) {
				deactivate_plugins( DOLPRESS_BASENAME );
			}

			$message = implode( ' ', $result['messages'] );
			if ( function_exists( 'wp_die' ) ) {
				wp_die(
					esc_html( $message ),
					esc_html__( 'DolPress activation failed', 'dolpress' ),
					array( 'back_link' => true )
				);
			}

			throw new \RuntimeException( esc_html( $message ) );
		}

		$repository = new SettingsRepository();
		$repository->ensure_defaults();
	}
}
