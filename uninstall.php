<?php
/**
 * Uninstall handler. Deletes plugin options only when the setting is enabled.
 *
 * @package DolPress
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$dolpress_settings = get_option( 'dolpress_settings', array() );

if ( is_array( $dolpress_settings ) && ! empty( $dolpress_settings['uninstall_delete_data'] ) ) {
	delete_option( 'dolpress_settings' );
	delete_option( 'dolpress_cache_index' );
	delete_metadata( 'user', 0, 'dolpress_editor_mode', '', true );
	delete_post_meta_by_key( '_dolpress_render_cache' );
	delete_post_meta_by_key( '_dolpress_render_hash' );
	delete_post_meta_by_key( '_dolpress_snapshot' );
}
