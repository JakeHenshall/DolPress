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
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $key ): string => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? ''
		);
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

	public function test_public_meta_change_bumps_per_key_counter(): void {
		Functions\when( 'get_option' )->justReturn( 4 );
		Functions\expect( 'update_option' )
			->once()
			->with( 'dolpress_cache_version_meta:subtitle', 5, false )
			->andReturn( true );

		$cache = new Cache( new SettingsRepository() );
		$cache->on_post_meta( 1, 42, 'subtitle', 'New value' );
		$this->addToAssertionCount( 1 );
	}

	public function test_unrelated_meta_key_does_not_flush_cached_documents(): void {
		// analytics_counter gets its own per-key counter; no global meta bump.
		Functions\when( 'get_option' )->justReturn( 4 );
		Functions\expect( 'update_option' )
			->once()
			->with( 'dolpress_cache_version_meta:analytics_counter', 5, false )
			->andReturn( true );

		$cache = new Cache( new SettingsRepository() );
		$cache->on_post_meta( 1, 42, 'analytics_counter', '7' );
		$this->addToAssertionCount( 1 );
	}

	public function test_private_meta_change_is_ignored(): void {
		Functions\expect( 'update_option' )->never();

		$cache = new Cache( new SettingsRepository() );
		$cache->on_post_meta( 1, 42, '_some_plugin_private', 'x' );
		$this->addToAssertionCount( 1 );
	}

	public function test_cache_entries_carry_schema_version(): void {
		Functions\when( 'get_option' )->justReturn( 1 );
		Functions\when( 'wp_json_encode' )->alias( static fn( mixed $data ): string => (string) json_encode( $data ) );

		$captured = null;
		Functions\expect( 'update_post_meta' )
			->twice()
			->andReturnUsing(
				static function ( int $post_id, string $meta_key, mixed $value ) use ( &$captured ): bool {
					if ( Cache::META_KEY === $meta_key ) {
						$captured = $value;
					}

					return true;
				}
			);

		$cache   = new Cache( new SettingsRepository() );
		$context = new \Nought\DolPress\Rendering\RenderContext( 42, false, false, 0 );
		$cache->put( 42, '$TX$', $context, '<div></div>', array( 'meta' => array( 'subtitle' ) ) );

		$this->assertIsArray( $captured );
		$this->assertSame( 'meta-keys', $captured['v'] ?? null );
	}

	public function test_flush_all_still_invalidates_keyed_documents(): void {
		// version('meta:subtitle') must include the global meta counter so a
		// legacy flush_all() bump still invalidates per-key cached documents.
		$versions = array(
			'dolpress_cache_version_meta'          => 3,
			'dolpress_cache_version_meta:subtitle' => 5,
		);
		Functions\when( 'get_option' )->alias(
			static fn( string $name, mixed $default = false ): mixed => $versions[ $name ] ?? ( is_int( $default ) ? $default : 1 )
		);

		$method = new \ReflectionMethod( Cache::class, 'version' );
		$cache  = new Cache( new SettingsRepository() );

		$this->assertSame( 8, $method->invoke( $cache, 'meta:subtitle' ) );
		$this->assertSame( 3, $method->invoke( $cache, 'meta' ) );
		$this->assertSame( 1, $method->invoke( $cache, 'post' ) );
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
