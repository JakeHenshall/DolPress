<?php
/**
 * PHPUnit bootstrap.
 *
 * @package DolPress
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'DOLPRESS_VERSION' ) ) {
	define( 'DOLPRESS_VERSION', '0.1.0' );
	define( 'DOLPRESS_FILE', dirname( __DIR__ ) . '/dolpress.php' );
	define( 'DOLPRESS_PATH', dirname( __DIR__ ) . '/' );
	define( 'DOLPRESS_URL', 'https://example.test/wp-content/plugins/dolpress/' );
	define( 'DOLPRESS_BASENAME', 'dolpress/dolpress.php' );
	define( 'DOLPRESS_MIN_PHP', '8.1' );
	define( 'DOLPRESS_MIN_WP', '6.7' );
	define( 'DOLPRESS_GRAMMAR_VERSION', '0.2' );
}

require_once DOLPRESS_PATH . 'includes/Autoloader.php';
Nought\DolPress\Autoloader::register();
