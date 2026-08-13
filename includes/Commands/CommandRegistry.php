<?php
/**
 * Command registry and registration hook.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress\Commands;

require_once __DIR__ . '/PresentationCommands.php';
require_once __DIR__ . '/WordPressCommands.php';

use Nought\DolPress\Contracts\CommandInterface;
use Nought\DolPress\Contracts\CommandRegistryInterface;
use Nought\DolPress\Support\SettingsRepository;

final class CommandRegistry implements CommandRegistryInterface {
	/**
	 * @var array<string, CommandInterface>
	 */
	private array $commands = array();

	public static function create_default( SettingsRepository $settings ): self {
		$registry = new self();
		$registry->add( new TextCommand() );
		$registry->add( new BreakCommand() );
		$registry->add( new ColourCommand( 'FG' ) );
		$registry->add( new ColourCommand( 'BG' ) );
		$registry->add( new ToggleCommand( 'UL', 'Underline', 'dolpress-ul' ) );
		$registry->add( new ToggleCommand( 'IV', 'Invert', 'dolpress-iv' ) );
		$registry->add( new ToggleCommand( 'HL', 'Highlight', 'dolpress-hl' ) );
		$registry->add( new LinkCommand() );
		$registry->add( new ButtonCommand() );
		$registry->add( new TreeCommand() );
		$registry->add( new ImageCommand() );
		$registry->add( new RuleCommand() );
		$registry->add( new SiteCommand() );
		$registry->add( new LogoCommand() );
		$registry->add( new CurrentPostCommand() );
		$registry->add( new AuthorCommand() );
		$registry->add( new TaxonomyCommand() );
		$registry->add( new MenuCommand() );
		$registry->add( new BreadcrumbCommand() );
		$registry->add( new SelectedPostCommand() );
		$registry->add( new LoopCommand( $settings ) );
		$registry->add( new CommentsCommand() );
		$registry->add( new MetaCommand( $settings ) );

		return $registry;
	}

	public function register(): void {
		do_action( 'dolpress/register_commands', $this );
	}

	public function add( CommandInterface $command ): void {
		$this->commands[ strtoupper( $command->code() ) ] = $command;
	}

	public function get( string $code ): ?CommandInterface {
		return $this->commands[ strtoupper( $code ) ] ?? null;
	}

	public function has( string $code ): bool {
		return isset( $this->commands[ strtoupper( $code ) ] );
	}

	public function schemas(): array {
		$out = array();
		foreach ( $this->commands as $command ) {
			$out[] = $command->definition();
		}

		return $out;
	}
}
