<?php
/**
 * Uninstall handler. Deletes plugin data only when the setting is enabled.
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
	foreach ( array( 'attachment', 'bin', 'comment', 'menu', 'meta', 'option', 'post', 'term', 'user' ) as $dolpress_dependency_type ) {
		delete_option( 'dolpress_cache_version_' . $dolpress_dependency_type );
	}
	// Per-meta-key invalidation counters.
	wp_load_alloptions();
	$dolpress_alloptions = wp_cache_get( 'alloptions', 'options' );
	if ( is_array( $dolpress_alloptions ) ) {
		foreach ( array_keys( $dolpress_alloptions ) as $dolpress_option_name ) {
			if ( is_string( $dolpress_option_name ) && str_starts_with( $dolpress_option_name, 'dolpress_cache_version_meta:' ) ) {
				delete_option( $dolpress_option_name );
			}
			unset( $dolpress_alloptions[ $dolpress_option_name ] );
		}
	}
	delete_metadata( 'user', 0, 'dolpress_editor_mode', '', true );
	delete_post_meta_by_key( '_dolpress_render_cache' );
	delete_post_meta_by_key( '_dolpress_render_hash' );
	delete_post_meta_by_key( '_dolpress_snapshot' );
	delete_post_meta_by_key( '_dolpress_bins' );
}
