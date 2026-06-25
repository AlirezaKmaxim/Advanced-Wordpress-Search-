<?php
/**
 * HamSeda Ajax Search — Uninstall Script
 *
 * This file is executed automatically by WordPress when the plugin is deleted
 * from the Plugins screen (not just deactivated). It removes every trace of
 * the plugin from the database so the site is left in a clean state.
 *
 * Cleanup checklist:
 *  ✓ Plugin options (hamseda_search_settings)
 *  ✓ All transients created by the search-query layer
 *  ✓ Per-site options on WordPress Multisite installations
 *
 * @package HamSeda_Ajax_Search
 * @since   2.0.9
 */

// WordPress sets this constant before running uninstall.php.
// If it is not set the file has been accessed directly — abort immediately.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// =========================================================================
// Helper: delete all hamseda transients from a single database.
// =========================================================================
/**
 * Purge every transient whose option_name starts with the plugin prefix.
 *
 * WordPress stores transients in the options table as:
 *   _transient_<key>
 *   _transient_timeout_<key>
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return void
 */
function _hamseda_delete_transients() {
	global $wpdb;

	// Delete the transient values themselves.
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '_transient_hamseda_%'
		    OR option_name LIKE '_transient_timeout_hamseda_%'"
	);
}

// =========================================================================
// Single-site cleanup
// =========================================================================
if ( ! is_multisite() ) {

	// 1. Remove the plugin's main settings option.
	delete_option( 'hamseda_search_settings' );

	// 2. Remove all search-result transients.
	_hamseda_delete_transients();

} else {
	// =========================================================================
	// Multisite cleanup — iterate over every sub-site.
	// =========================================================================

	// Retrieve all blog IDs in this network.
	$blog_ids = get_sites( array(
		'fields'     => 'ids',
		'number'     => 0,   // 0 = no limit
		'spam'       => 0,
		'deleted'    => 0,
		'archived'   => 0,
	) );

	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( (int) $blog_id );

		// 1. Remove the plugin's main settings option for this sub-site.
		delete_option( 'hamseda_search_settings' );

		// 2. Remove all search-result transients for this sub-site.
		_hamseda_delete_transients();

		restore_current_blog();
	}

	// 3. Remove any network-wide options if they exist.
	//    (The plugin currently stores nothing at network level, but this
	//    placeholder makes future additions safe to clean up.)
	// delete_site_option( 'hamseda_search_network_settings' );
}
