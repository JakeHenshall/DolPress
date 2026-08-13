<?php
/**
 * Integration tests require a WordPress test suite.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class WordPressLifecycleTest extends TestCase {
	public function test_wordpress_suite_is_optional(): void {
		if ( ! getenv( 'WP_TESTS_DIR' ) ) {
			$this->markTestSkipped( 'Set WP_TESTS_DIR to run WordPress integration tests.' );
		}

		$this->assertTrue( defined( 'ABSPATH' ) );
	}
}
