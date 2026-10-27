<?php
/**
 * Uninstall: delete support accounts and remove everything Fixpass stored.
 *
 * On multisite, deleting the plugin removes its files for the whole network, so every site is
 * cleaned.
 *
 * @package Fixpass
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Clean the current site.
 */
function fixpass_uninstall_site() {
	global $wpdb;

	// Any remaining support account for this site (deactivation normally removed it already).
	$users = get_users(
		array(
			'meta_key'   => 'fixpass_support', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => get_current_blog_id(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'     => 'ID',
			'blog_id'    => get_current_blog_id(),
		)
	);
	if ( $users ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $users as $user_id ) {
			if ( is_multisite() ) {
				require_once ABSPATH . 'wp-admin/includes/ms.php';
				wpmu_delete_user( (int) $user_id );
			} else {
				wp_delete_user( (int) $user_id );
			}
		}
	}

	// Single-use markers are stored as options named fixpass_used_{hash}.
	$used = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s', $wpdb->esc_like( 'fixpass_used_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	foreach ( (array) $used as $option ) {
		delete_option( $option );
	}

	foreach ( array( 'fixpass_access', 'fixpass_ended', 'fixpass_creating' ) as $option ) {
		delete_option( $option );
	}

	foreach ( array( 'fixpass_expire', 'fixpass_self_remove' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $fixpass_site_id ) {
		switch_to_blog( (int) $fixpass_site_id );
		fixpass_uninstall_site();
		restore_current_blog();
	}
	delete_site_option( 'fixpass_network_sites' );
	delete_site_option( 'fixpass_allow_site_admins' );
} else {
	fixpass_uninstall_site();
}
