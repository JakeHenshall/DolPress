<?php
/**
 * PSR-4 autoloader so the plugin runs without Composer on production sites.
 *
 * @package DolPress
 */

declare(strict_types=1);

namespace Nought\DolPress;

final class Autoloader {
	private const PREFIX = 'Nought\\DolPress\\';

	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	public static function load( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, strlen( self::PREFIX ) ) );
		$bundles  = array(
			'Parser/Node'                  => 'Parser/Ast.php',
			'Parser/DocumentNode'          => 'Parser/Ast.php',
			'Parser/TextNode'              => 'Parser/Ast.php',
			'Parser/CommandNode'           => 'Parser/Ast.php',
			'Commands/TextCommand'         => 'Commands/PresentationCommands.php',
			'Commands/BreakCommand'        => 'Commands/PresentationCommands.php',
			'Commands/ColourCommand'       => 'Commands/PresentationCommands.php',
			'Commands/ToggleCommand'       => 'Commands/PresentationCommands.php',
			'Commands/LinkCommand'         => 'Commands/PresentationCommands.php',
			'Commands/ButtonCommand'       => 'Commands/PresentationCommands.php',
			'Commands/TreeCommand'         => 'Commands/PresentationCommands.php',
			'Commands/ImageCommand'        => 'Commands/PresentationCommands.php',
			'Commands/RuleCommand'         => 'Commands/PresentationCommands.php',
			'Commands/SiteCommand'         => 'Commands/WordPressCommands.php',
			'Commands/LogoCommand'         => 'Commands/WordPressCommands.php',
			'Commands/CurrentPostCommand'  => 'Commands/WordPressCommands.php',
			'Commands/AuthorCommand'       => 'Commands/WordPressCommands.php',
			'Commands/TaxonomyCommand'     => 'Commands/WordPressCommands.php',
			'Commands/MenuCommand'         => 'Commands/WordPressCommands.php',
			'Commands/BreadcrumbCommand'   => 'Commands/WordPressCommands.php',
			'Commands/SelectedPostCommand' => 'Commands/WordPressCommands.php',
			'Commands/LoopCommand'         => 'Commands/WordPressCommands.php',
			'Commands/CommentsCommand'     => 'Commands/WordPressCommands.php',
			'Commands/MetaCommand'         => 'Commands/WordPressCommands.php',
			'Commands/LayoutCommand'       => 'Commands/LayoutCommands.php',
			'Commands/AnchorCommand'       => 'Commands/LayoutCommands.php',
			'Commands/IndentCommand'       => 'Commands/LayoutCommands.php',
			'Commands/DataCommand'         => 'Commands/WidgetCommands.php',
			'Commands/CheckBoxCommand'     => 'Commands/WidgetCommands.php',
			'Commands/ListCommand'         => 'Commands/WidgetCommands.php',
			'Commands/MenuValCommand'      => 'Commands/WidgetCommands.php',
			'Commands/HexCommand'          => 'Commands/WidgetCommands.php',
			'Commands/SpriteCommand'       => 'Commands/SpriteCommands.php',
			'Commands/SongCommand'         => 'Commands/SpriteCommands.php',
			'Commands/HtmlCodeCommand'     => 'Commands/SpriteCommands.php',
		);

		$file = DOLPRESS_PATH . 'includes/' . ( $bundles[ str_replace( '\\', '/', $relative ) ] ?? $relative . '.php' );

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
}
