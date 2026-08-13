<?php
/**
 * Command schema and threat-model tests.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Tests\Unit;

use Nought\DolPress\Commands\ButtonCommand;
use Nought\DolPress\Commands\CommandRegistry;
use Nought\DolPress\Commands\LinkCommand;
use Nought\DolPress\Rendering\RenderContext;
use Nought\DolPress\Security\ThreatModel;
use Nought\DolPress\Support\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class CommandsAndSecurityTest extends TestCase {
	public function test_registry_contains_mvp_commands(): void {
		$registry = CommandRegistry::create_default( new SettingsRepository() );
		foreach ( array( 'TX', 'CR', 'FG', 'BG', 'UL', 'IV', 'HL', 'LK', 'BT', 'TR', 'IM', 'HR', 'WS', 'WG', 'WT', 'WA', 'WN', 'WL', 'WP', 'WC', 'WX', 'WM', 'WB' ) as $code ) {
			$this->assertTrue( $registry->has( $code ), $code );
			$def = $registry->get( $code )?->definition();
			$this->assertIsArray( $def );
			$this->assertArrayHasKey( 'schema', $def );
			$this->assertArrayHasKey( 'label', $def );
		}
	}

	public function test_button_rejects_arbitrary_action(): void {
		$command = new ButtonCommand();
		$context = new RenderContext( 0, false, false, 0 );
		$result  = $command->validate( array( 'TEXT' => 'Go', 'ACTION' => 'javascript:alert(1)' ), array(), $context );
		$this->assertFalse( $result['ok'] );
	}

	public function test_link_requires_url(): void {
		$command = new LinkCommand();
		$context = new RenderContext( 0, false, false, 0 );
		$result  = $command->validate( array( '_0' => 'Nought' ), array(), $context );
		$this->assertFalse( $result['ok'] );
	}

	public function test_threat_model_blocks_eval_paths(): void {
		$model = ThreatModel::document();
		$this->assertTrue( $model['controls']['no_eval'] );
		$this->assertTrue( $model['controls']['kses_policy'] );
		$this->assertSame( 'security@noughtdigital.com', $model['disclosure']['contact'] );
	}
}
