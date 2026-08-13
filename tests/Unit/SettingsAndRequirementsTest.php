<?php
/**
 * Activation and settings unit tests.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Tests\Unit;

use Nought\DolPress\Support\PostTypePolicy;
use Nought\DolPress\Support\Requirements;
use Nought\DolPress\Support\SettingsSanitizer;
use PHPUnit\Framework\TestCase;

final class SettingsAndRequirementsTest extends TestCase {
	public function test_php_too_old_fails_activation(): void {
		$result = Requirements::check( '7.4.0', '6.8' );
		$this->assertFalse( $result['ok'] );
		$this->assertNotEmpty( $result['messages'] );
	}

	public function test_supported_versions_pass(): void {
		$result = Requirements::check( '8.2.0', '6.7.2' );
		$this->assertTrue( $result['ok'] );
	}

	public function test_wordpress_too_old_fails(): void {
		$result = Requirements::check( '8.1.0', '6.0' );
		$this->assertFalse( $result['ok'] );
	}

	public function test_blocked_post_types_cannot_be_enabled(): void {
		$clean = SettingsSanitizer::sanitise(
			array(
				'enabled_post_types' => array( 'post', 'wp_template', 'wp_navigation', 'wp_block', 'page' ),
			)
		);
		$this->assertSame( array( 'post', 'page' ), $clean['enabled_post_types'] );
	}

	public function test_private_meta_keys_rejected(): void {
		$keys = SettingsSanitizer::sanitise_meta_keys( array( '_edit_lock', 'subtitle', 'bad key', '' ) );
		$this->assertSame( array( 'subtitle' ), $keys );
	}

	public function test_limits_are_clamped(): void {
		$clean = SettingsSanitizer::sanitise(
			array(
				'max_loop_count'     => 999,
				'max_command_count'  => 1,
				'strict_diagnostics' => 'yes',
				'cache_enabled'      => '0',
			)
		);
		$this->assertSame( 20, $clean['max_loop_count'] );
		$this->assertSame( 10, $clean['max_command_count'] );
		$this->assertTrue( $clean['strict_diagnostics'] );
		$this->assertFalse( $clean['cache_enabled'] );
	}

	public function test_invalid_mode_falls_back(): void {
		$clean = SettingsSanitizer::sanitise( array( 'default_mode' => 'rainbow' ) );
		$this->assertSame( 'source', $clean['default_mode'] );
	}

	public function test_custom_public_types_require_callback(): void {
		$clean = PostTypePolicy::sanitise_enabled(
			array( 'project', 'post' ),
			static fn( string $type ): bool => 'project' === $type
		);
		$this->assertSame( array( 'project', 'post' ), $clean );

		$denied = PostTypePolicy::sanitise_enabled(
			array( 'secret', 'post' ),
			static fn(): bool => false
		);
		$this->assertSame( array( 'post' ), $denied );
	}
}
