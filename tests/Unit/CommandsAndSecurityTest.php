<?php
/**
 * Command schema and threat-model tests.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Tests\Unit;

use Nought\DolPress\Commands\ActionRegistry;
use Nought\DolPress\Commands\ButtonCommand;
use Nought\DolPress\Commands\CommandRegistry;
use Nought\DolPress\Commands\HtmlCodeCommand;
use Nought\DolPress\Commands\LinkCommand;
use Nought\DolPress\Commands\MacroCommand;
use Nought\DolPress\Parser\CommandNode;
use Nought\DolPress\Parser\Codes;
use Nought\DolPress\Parser\Parser;
use Nought\DolPress\Rendering\RenderContext;
use Nought\DolPress\Security\ThreatModel;
use Nought\DolPress\Sprites\Decoder;
use Nought\DolPress\Support\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class CommandsAndSecurityTest extends TestCase {
	public function test_registry_contains_core_commands(): void {
		$registry = CommandRegistry::create_default( new SettingsRepository() );
		foreach ( Codes::CORE as $code ) {
			$this->assertTrue( $registry->has( $code ), $code );
			$def = $registry->get( $code )?->definition();
			$this->assertIsArray( $def );
			$this->assertArrayHasKey( 'schema', $def );
			$this->assertArrayHasKey( 'label', $def );
		}
	}

	public function test_button_rejects_arbitrary_action(): void {
		$command = new ButtonCommand( new ActionRegistry( new SettingsRepository() ) );
		$context = new RenderContext( 0, false, false, 0 );
		$result  = $command->validate( array( 'TEXT' => 'Go', 'ACTION' => 'javascript:alert(1)' ), array(), $context );
		$this->assertFalse( $result['ok'] );
	}

	public function test_link_rejects_address_eval(): void {
		$command = new LinkCommand();
		$context = new RenderContext( 0, false, false, 0 );
		$result  = $command->validate( array( '_0' => 'Nought', 'A' => 'AD:Foo' ), array(), $context );
		$this->assertFalse( $result['ok'] );
	}

	public function test_macro_rejects_unknown_hook(): void {
		$command = new MacroCommand( new ActionRegistry( new SettingsRepository() ) );
		$context = new RenderContext( 0, false, false, 0 );
		$result  = $command->validate( array( 'TEXT' => 'Run', 'ACTION' => 'eval_php' ), array(), $context );
		$this->assertFalse( $result['ok'] );
	}

	public function test_html_code_off_by_default(): void {
		$command = new HtmlCodeCommand( new SettingsRepository() );
		$html    = $command->render( array( 'HTML' => '<strong>x</strong>' ), array(), new RenderContext( 0, false, false, 0 ) );
		$this->assertSame( '', $html );
	}

	public function test_indent_tree_attaches_body(): void {
		$parser = new Parser();
		$result = $parser->parse( '$TR,"Branch"$$ID,2$Hi$ID,-2$' );
		$tree   = $result->document->children[0];
		$this->assertInstanceOf( CommandNode::class, $tree );
		$this->assertSame( 'TR', $tree->code );
		$this->assertCount( 3, $tree->children );
	}

	public function test_indent_tree_allows_newlines_between_widget_and_id(): void {
		$parser = new Parser();
		$result = $parser->parse( "\$TR,\"Branch\"\$\n\$ID,2\$\nHi\n\$ID,-2\$" );
		$tree   = $result->document->children[0];
		$codes  = array_map( static fn( $item ) => $item->code, $result->diagnostics );
		$this->assertInstanceOf( CommandNode::class, $tree );
		$this->assertSame( 'TR', $tree->code );
		$this->assertNotEmpty( $tree->children );
		$this->assertNotContains( 'E_NESTING_LIMIT', $codes );
	}

	public function test_sibling_indent_trees_do_not_hit_nesting_limit(): void {
		$block  = "\$TR,\"Branch\"\$\n\$ID,2\$\n\n\$ID,-2\$\n";
		$parser = new Parser();
		$result = $parser->parse( str_repeat( $block, 9 ) );
		$codes  = array_map( static fn( $item ) => $item->code, $result->diagnostics );
		$trees  = array_values(
			array_filter(
				$result->document->children,
				static fn( $node ) => $node instanceof CommandNode && 'TR' === $node->code
			)
		);
		$this->assertNotContains( 'E_NESTING_LIMIT', $codes );
		$this->assertCount( 9, $trees );
	}

	public function test_nested_indent_trees_respect_max_depth(): void {
		$source = '';
		for ( $i = 0; $i < 9; $i++ ) {
			$source .= '$TR,"B"$$ID,2$';
		}
		$source .= 'x' . str_repeat( '$ID,-2$', 9 );
		$parser = new Parser();
		$result = $parser->parse( $source );
		$codes  = array_map( static fn( $item ) => $item->code, $result->diagnostics );
		$this->assertContains( 'E_NESTING_LIMIT', $codes );
	}

	public function test_sprite_decoder_line_svg(): void {
		$svg = ( new Decoder() )->to_svg( Decoder::encode_line( 0, 0, 10, 10, 4, 1 ) );
		$this->assertStringContainsString( '<svg', $svg );
		$this->assertStringContainsString( '<line', $svg );
	}

	public function test_sprite_decoder_json_ops(): void {
		$svg = ( new Decoder() )->to_svg( '{"ops":[{"t":"circle","x":8,"y":8,"r":4}]}' );
		$this->assertStringContainsString( '<circle', $svg );
	}

	public function test_threat_model_blocks_eval_paths(): void {
		$model = ThreatModel::document();
		$this->assertTrue( $model['controls']['no_eval'] );
		$this->assertTrue( $model['controls']['kses_policy'] );
		$this->assertTrue( $model['controls']['action_allowlist'] );
		$this->assertSame( 'security@noughtdigital.com', $model['disclosure']['contact'] );
	}
}
