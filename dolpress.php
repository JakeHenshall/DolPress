<?php
/**
 * Plugin Name: DolPress
 * Plugin URI: https://noughtdigital.com
 * Description: A DolDoc-inspired document editor for WordPress by Nought Digital.
 * Version: 0.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Nought Digital
 * Author URI: https://noughtdigital.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dolpress
 * Domain Path: /languages
 *
 * @package DolPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DOLPRESS_VERSION', '0.1.0' );
define( 'DOLPRESS_FILE', __FILE__ );
define( 'DOLPRESS_PATH', plugin_dir_path( __FILE__ ) );
define( 'DOLPRESS_URL', plugin_dir_url( __FILE__ ) );
define( 'DOLPRESS_BASENAME', plugin_basename( __FILE__ ) );
define( 'DOLPRESS_MIN_PHP', '8.1' );
define( 'DOLPRESS_MIN_WP', '6.7' );
define( 'DOLPRESS_GRAMMAR_VERSION', '0.1' );

require_once DOLPRESS_PATH . 'includes/Autoloader.php';

Nought\DolPress\Autoloader::register();

require_once DOLPRESS_PATH . 'includes/functions.php';

register_activation_hook(
	DOLPRESS_FILE,
	static function (): void {
		Nought\DolPress\Activator::activate();
	}
);

register_deactivation_hook(
	DOLPRESS_FILE,
	static function (): void {
		Nought\DolPress\Deactivator::deactivate();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		$plugin = Nought\DolPress\Plugin::boot();
		$plugin->register();
	},
	1
);
