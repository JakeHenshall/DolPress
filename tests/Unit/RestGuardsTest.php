<?php
/**
 * REST boundary guard tests: source cap, rate limit, macro payload sanitisation.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Nought\DolPress\Rest\DocumentController;
use Nought\DolPress\Rest\PreviewController;
use Nought\DolPress\Support\SettingsRepository;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class RestGuardsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function controller(): PreviewController {
		return new PreviewController( new SettingsRepository(), new \Nought\DolPress\Rendering\Renderer(
			new \Nought\DolPress\Parser\Parser(),
			\Nought\DolPress\Commands\CommandRegistry::create_default( new SettingsRepository() ),
			new SettingsRepository()
		) );
	}

	public function test_enforce_source_cap_rejects_oversized_source(): void {
		$method  = new \ReflectionMethod( PreviewController::class, 'enforce_source_cap' );

		$this->assertSame( 'ok', $method->invoke( $this->controller(), 'ok' ) );

		$big = str_repeat( 'a', 102401 ); // Default cap is 102400 bytes.
		$this->assertNull( $method->invoke( $this->controller(), $big ) );

		$exact = str_repeat( 'a', 102400 );
		$this->assertSame( 102400, strlen( (string) $method->invoke( $this->controller(), $exact ) ) );
	}

	public function test_rate_limit_blocks_after_threshold(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		// In-memory transient store so the counter actually accumulates.
		$store = array();
		Functions\when( 'get_transient' )->alias(
			static function ( string $key ) use ( &$store ): mixed {
				return $store[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			static function ( string $key, mixed $value, int $ttl = 0 ) use ( &$store ): bool {
				unset( $ttl );
				$store[ $key ] = $value;
				return true;
			}
		);

		$method = new \ReflectionMethod( PreviewController::class, 'allow_request' );
		$controller = $this->controller();

		for ( $i = 0; $i < 30; $i++ ) {
			$this->assertTrue( $method->invoke( $controller, 'parse' ), "request $i should pass" );
		}
		$this->assertFalse( $method->invoke( $controller, 'parse' ), 'request over threshold should be blocked' );
		// Separate bucket is independently limited.
		$this->assertTrue( $method->invoke( $controller, 'preview' ), 'other bucket unaffected' );
	}

	public function test_rate_limit_window_reset(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 8 );
		Functions\when( 'get_transient' )->justReturn( array( 'start' => time() - 3600, 'n' => 999 ) );
		Functions\when( 'set_transient' )->justReturn( true );

		$method = new \ReflectionMethod( PreviewController::class, 'allow_request' );

		$this->assertTrue( $method->invoke( $this->controller(), 'preview' ), 'expired window resets the counter' );
	}

	public function test_macro_payload_is_sanitised_to_scalar_leaves(): void {
		Functions\when( 'sanitize_key' )->alias( static fn( string $key ): string => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '' );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $value ): string => trim( strip_tags( $value ) ) );

		$method = new \ReflectionMethod( DocumentController::class, 'sanitise_payload' );
		$controller = new DocumentController( new SettingsRepository(), new \Nought\DolPress\Support\BinStore(), new \Nought\DolPress\Commands\ActionRegistry( new SettingsRepository() ) );

		$out = $method->invoke( $controller, array( 'target' => 'intro', 'count' => 3, 'flag' => true ) );
		$this->assertSame(
			array(
				'target' => 'intro',
				'count'  => 3,
				'flag'   => true,
			),
			$out
		);

		// Nested arrays collapse to their single scalar leaf.
		$out = $method->invoke( $controller, array( 'deep' => array( 'only' => 'x' ) ) );
		$this->assertSame( array( 'deep' => 'x' ), $out );

		// Objects are dropped, not passed through.
		$out = $method->invoke( $controller, array( 'bad' => new \stdClass(), 'good' => 'y' ) );
		$this->assertSame( array( 'good' => 'y' ), $out );

		// Non-array payload is rejected outright.
		$this->assertNull( $method->invoke( $controller, 'string-payload' ) );
	}

	public function test_macro_payload_key_and_size_limits(): void {
		Functions\when( 'sanitize_key' )->alias( static fn( string $key ): string => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '' );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $value ): string => trim( strip_tags( $value ) ) );

		$method     = new \ReflectionMethod( DocumentController::class, 'sanitise_payload' );
		$controller = new DocumentController( new SettingsRepository(), new \Nought\DolPress\Support\BinStore(), new \Nought\DolPress\Commands\ActionRegistry( new SettingsRepository() ) );

		// Over the key budget -> rejected.
		$flood = array_fill_keys( range( 1, 25 ), 'x' );
		$this->assertNull( $method->invoke( $controller, $flood ) );

		// Long strings are truncated to the entry cap.
		$out    = $method->invoke( $controller, array( 'blob' => str_repeat( 'a', 5000 ) ) );
		$length = is_array( $out ) && isset( $out['blob'] ) ? strlen( (string) $out['blob'] ) : 0;
		$this->assertSame( 1000, $length );
	}
}
