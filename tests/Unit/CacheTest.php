<?php
/**
 * Render cache invalidation tests.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Nought\DolPress\Rendering\Cache;
use Nought\DolPress\Support\SettingsRepository;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class CacheTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_internal_cache_meta_does_not_invalidate_itself(): void {
		Functions\expect( 'update_option' )->never();
		$cache = new Cache( new SettingsRepository() );
		$cache->on_post_meta( 1, 42, Cache::META_KEY, array() );
		$this->addToAssertionCount( 1 );
	}

	public function test_public_meta_change_bumps_dependency_generation(): void {
		Functions\when( 'get_option' )->justReturn( 4 );
		Functions\expect( 'update_option' )
			->once()
			->with( 'dolpress_cache_version_meta', 5, false )
			->andReturn( true );

		$cache = new Cache( new SettingsRepository() );
		$cache->on_post_meta( 1, 42, 'subtitle', 'New value' );
		$this->addToAssertionCount( 1 );
	}

	public function test_post_invalidation_is_constant_time(): void {
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'wp_is_post_autosave' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( 1 );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\expect( 'delete_post_meta' )->twice()->andReturn( true );
		Functions\expect( 'delete_post_meta_by_key' )->never();
		Functions\expect( 'update_option' )
			->once()
			->with( 'dolpress_cache_version_post', 2, false )
			->andReturn( true );

		$cache = new Cache( new SettingsRepository() );
		$cache->invalidate_post( 42 );
		$this->addToAssertionCount( 1 );
	}

	public function test_autosaves_do_not_invalidate_public_render_caches(): void {
		Functions\when( 'wp_is_post_revision' )->justReturn( true );
		Functions\expect( 'delete_post_meta' )->never();
		Functions\expect( 'update_option' )->never();

		$cache = new Cache( new SettingsRepository() );
		$cache->invalidate_post( 42 );
		$this->addToAssertionCount( 1 );
	}
}
