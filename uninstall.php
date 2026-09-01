<?php
/**
 * Alireza Smart Search — Uninstall Script
 *
 * This file is executed automatically by WordPress when the plugin is deleted
 * from the Plugins screen (not just deactivated). It removes every trace of
 * the plugin from the database so the site is left in a clean state.
 *
 * @package Alireza_Ajax_Search
 * @since   2.1.0
 */

// WordPress sets this constant before running uninstall.php.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// =========================================================================
// Helper: delete all alireza transients from a single database.
// =========================================================================
/**
 * Purge every transient whose option_name starts with the plugin prefix.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return void
 */
function _alireza_delete_transients() {
	global $wpdb;

	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '_transient_alireza_%'
		    OR option_name LIKE '_transient_timeout_alireza_%'
		    OR option_name LIKE '_transient_alz_%'
		    OR option_name LIKE '_transient_timeout_alz_%'"
	);
}

// =========================================================================
// Single-site cleanup
// =========================================================================
if ( ! is_multisite() ) {

	// 1. Remove the plugin's main settings option.
	delete_option( 'alireza_search_settings' );

	// 2. Remove all search-result transients.
	_alireza_delete_transients();

} else {
	// =========================================================================
	// Multisite cleanup — iterate over every sub-site.
	// =========================================================================

	$blog_ids = get_sites( array(
		'fields'     => 'ids',
		'number'     => 0,   // 0 = no limit
		'spam'       => 0,
		'deleted'    => 0,
		'archived'   => 0,
	) );

	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( (int) $blog_id );

		delete_option( 'alireza_search_settings' );
		_alireza_delete_transients();

		restore_current_blog();
	}
}
