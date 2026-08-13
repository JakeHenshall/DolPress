<?php
/**
 * Plugin composition root.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress;

use Nought\DolPress\Admin\EditorReplacement;
use Nought\DolPress\Admin\EditorScreen;
use Nought\DolPress\Admin\SafeMode;
use Nought\DolPress\Admin\SettingsPage;
use Nought\DolPress\Commands\ActionRegistry;
use Nought\DolPress\Commands\CommandRegistry;
use Nought\DolPress\Contracts\CommandRegistryInterface;
use Nought\DolPress\Contracts\ParserInterface;
use Nought\DolPress\Contracts\RendererInterface;
use Nought\DolPress\Parser\Parser;
use Nought\DolPress\Rendering\Cache;
use Nought\DolPress\Rendering\Frontend;
use Nought\DolPress\Rendering\Renderer;
use Nought\DolPress\Rest\CommandSchemaController;
use Nought\DolPress\Rest\DocumentController;
use Nought\DolPress\Rest\PreviewController;
use Nought\DolPress\Support\BinStore;
use Nought\DolPress\Support\SettingsRepository;

final class Plugin {
	private static ?self $instance = null;

	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly ParserInterface $parser,
		private readonly RendererInterface $renderer,
		private readonly CommandRegistryInterface $commands,
		private readonly SafeMode $safe_mode,
		private readonly EditorReplacement $editor_replacement,
		private readonly EditorScreen $editor_screen,
		private readonly SettingsPage $settings_page,
		private readonly Frontend $frontend,
		private readonly Cache $cache,
		private readonly PreviewController $preview_controller,
		private readonly CommandSchemaController $schema_controller,
		private readonly DocumentController $document_controller
	) {}

	public static function boot(): self {
		if ( self::$instance instanceof self ) {
			return self::$instance;
		}

		$settings  = new SettingsRepository();
		$commands  = CommandRegistry::create_default( $settings );
		$parser    = new Parser( $settings, $commands );
		$cache     = new Cache( $settings );
		$renderer  = new Renderer( $parser, $commands, $settings );
		$safe_mode = new SafeMode( $settings );
		$frontend  = new Frontend( $settings, $renderer, $safe_mode, $cache );
		$bins      = new BinStore();
		$actions   = new ActionRegistry( $settings );

		self::$instance = new self(
			$settings,
			$parser,
			$renderer,
			$commands,
			$safe_mode,
			new EditorReplacement( $settings, $safe_mode ),
			new EditorScreen( $settings, $safe_mode, $commands ),
			new SettingsPage( $settings ),
			$frontend,
			$cache,
			new PreviewController( $settings, $renderer ),
			new CommandSchemaController( $commands ),
			new DocumentController( $settings, $bins, $actions )
		);

		return self::$instance;
	}

	public static function instance(): ?self {
		return self::$instance;
	}

	public function register(): void {
		load_plugin_textdomain( 'dolpress', false, dirname( DOLPRESS_BASENAME ) . '/languages' );

		$this->settings_page->register();
		$this->safe_mode->register();
		$this->editor_replacement->register();
		$this->editor_screen->register();
		$this->frontend->register();
		$this->cache->register();
		$this->preview_controller->register();
		$this->schema_controller->register();
		$this->document_controller->register();
		$this->commands->register();
		$this->parser->refresh_known();
	}

	public function settings(): SettingsRepository {
		return $this->settings;
	}

	public function parser(): ParserInterface {
		return $this->parser;
	}

	public function renderer(): RendererInterface {
		return $this->renderer;
	}

	public function commands(): CommandRegistryInterface {
		return $this->commands;
	}

	public function safe_mode(): SafeMode {
		return $this->safe_mode;
	}
}
