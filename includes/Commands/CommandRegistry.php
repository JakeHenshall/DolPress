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
require_once __DIR__ . '/LayoutCommands.php';
require_once __DIR__ . '/WidgetCommands.php';
require_once __DIR__ . '/MacroCommand.php';
require_once __DIR__ . '/SpriteCommands.php';

use Nought\DolPress\Contracts\CommandInterface;
use Nought\DolPress\Contracts\CommandRegistryInterface;
use Nought\DolPress\Parser\Codes;
use Nought\DolPress\Sprites\Decoder;
use Nought\DolPress\Support\BinStore;
use Nought\DolPress\Support\SettingsRepository;

final class CommandRegistry implements CommandRegistryInterface {
	/**
	 * @var array<string, CommandInterface>
	 */
	private array $commands = array();

	public static function create_default( SettingsRepository $settings ): self {
		$registry = new self();
		$actions  = new ActionRegistry( $settings );
		$bins     = new BinStore();
		$decoder  = new Decoder();

		$int_arg = static fn( string $name, int $min, int $max, int $default ): array => array(
			'name'    => $name,
			'type'    => 'int',
			'min'     => $min,
			'max'     => $max,
			'default' => $default,
		);

		$registry->add( new TextCommand() );
		$registry->add( new BreakCommand() );
		$registry->add( new ColourCommand( 'FG' ) );
		$registry->add( new ColourCommand( 'BG' ) );
		$registry->add( new ColourCommand( 'FD' ) );
		$registry->add( new ColourCommand( 'BD' ) );
		$registry->add( new ToggleCommand( 'UL', 'Underline', 'dolpress-ul' ) );
		$registry->add( new ToggleCommand( 'IV', 'Invert', 'dolpress-iv' ) );
		$registry->add( new ToggleCommand( 'HL', 'Highlight', 'dolpress-hl' ) );
		$registry->add( new ToggleCommand( 'WW', 'Word wrap', 'dolpress-ww' ) );
		$registry->add( new ToggleCommand( 'BK', 'Blink', 'dolpress-bk' ) );
		$registry->add( new LinkCommand() );
		$registry->add( new ButtonCommand( $actions ) );
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
		$registry->add( new LayoutCommand( 'SR', 'Soft break', 'Soft wrap break.', array(), array(), array(), array( '$SR$' ), '<wbr class="dolpress-sr" />' ) );
		$registry->add( new LayoutCommand( 'TB', 'Tab', 'Tab stop.', array(), array(), array(), array( '$TB$' ), '<span class="dolpress-tb">    </span>' ) );
		$registry->add( new LayoutCommand( 'PB', 'Page break', 'Page break.', array(), array(), array(), array( '$PB$' ), '<hr class="dolpress-pb" />' ) );
		$registry->add( new LayoutCommand( 'PL', 'Page length', 'Page length in lines.', array(), array( $int_arg( 'N', 1, 200, 60 ) ), array( $int_arg( 'N', 1, 200, 60 ) ), array( '$PL,60$' ) ) );
		$registry->add( new LayoutCommand( 'LM', 'Left margin', 'Left margin in characters.', array(), array( $int_arg( 'N', 0, 80, 0 ) ), array( $int_arg( 'N', 0, 80, 0 ) ), array( '$LM,4$' ) ) );
		$registry->add( new LayoutCommand( 'RM', 'Right margin', 'Right margin in characters.', array(), array( $int_arg( 'N', 0, 80, 0 ) ), array( $int_arg( 'N', 0, 80, 0 ) ), array( '$RM,4$' ) ) );
		$registry->add( new LayoutCommand( 'HD', 'Header', 'Header band.', array(), array(), array(), array( '$HD$' ), '<header class="dolpress-hd"></header>' ) );
		$registry->add( new LayoutCommand( 'FO', 'Footer', 'Footer band.', array(), array(), array(), array( '$FO$' ), '<footer class="dolpress-fo"></footer>' ) );
		$registry->add( new IndentCommand() );
		$registry->add( new LayoutCommand( 'SX', 'Shift X', 'Pixel X shift.', array(), array( $int_arg( 'N', -7, 7, 0 ) ), array( $int_arg( 'N', -7, 7, 0 ) ), array( '$SX,2$' ) ) );
		$registry->add( new LayoutCommand( 'SY', 'Shift Y', 'Pixel Y shift.', array(), array( $int_arg( 'N', -7, 7, 0 ) ), array( $int_arg( 'N', -7, 7, 0 ) ), array( '$SY,2$' ) ) );
		$registry->add( new LayoutCommand( 'CM', 'Cursor movement', 'Cursor position hint.', array( 'LE', 'RE' ), array(), array(), array( '$CM$' ) ) );
		$registry->add( new AnchorCommand() );
		$registry->add( new LayoutCommand( 'MK', 'Marker', 'Invisible marker.', array(), array(), array(), array( '$MK$' ), '<span class="dolpress-mk"></span>' ) );
		$registry->add( new LayoutCommand( 'CU', 'Cursor', 'Editor cursor marker.', array(), array(), array(), array( '$CU$' ) ) );
		$registry->add( new LayoutCommand( 'PT', 'Prompt', 'Prompt region marker.', array(), array(), array(), array( '$PT$' ) ) );
		$registry->add( new LayoutCommand( 'CL', 'Clear', 'Clear marker. Not a terminal.', array( 'H' ), array(), array(), array( '$CL$' ) ) );
		$registry->add( new DataCommand( $settings ) );
		$registry->add( new CheckBoxCommand( $settings ) );
		$registry->add( new ListCommand( $settings ) );
		$registry->add( new MenuValCommand( $actions ) );
		$registry->add( new HexCommand( $settings, $bins ) );
		$registry->add( new MacroCommand( $actions ) );
		$registry->add( new SpriteCommand( $bins, $decoder ) );
		$registry->add( new SongCommand() );
		$registry->add( new HtmlCodeCommand( $settings ) );

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

	/**
	 * @return list<string>
	 */
	public function codes(): array {
		$codes = array_keys( $this->commands );
		sort( $codes );

		return $codes;
	}

	/**
	 * @return list<string>
	 */
	public function paired(): array {
		$paired = array();
		foreach ( $this->commands as $command ) {
			$def = $command->definition();
			if ( ! empty( $def['paired'] ) ) {
				$paired[] = strtoupper( $command->code() );
			}
		}

		return array() === $paired ? Codes::PAIRED : $paired;
	}

	public function schemas(): array {
		$out = array();
		foreach ( $this->commands as $command ) {
			$out[] = $command->definition();
		}

		return $out;
	}
}
