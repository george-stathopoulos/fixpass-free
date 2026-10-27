<?php
/**
 * Uninstall: delete support accounts and remove everything Fixpass stored.
 *
 * @package Fixpass
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Any remaining support accounts (deactivation normally removed them already).
$fixpass_users = get_users(
	array(
		'meta_key' => 'fixpass_support', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'fields'   => 'ID',
	)
);
if ( $fixpass_users ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $fixpass_users as $fixpass_user ) {
		wp_delete_user( (int) $fixpass_user );
	}
}

// Single-use markers are stored as options named fixpass_used_{hash}.
global $wpdb;
$fixpass_used = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s', $wpdb->esc_like( 'fixpass_used_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
foreach ( (array) $fixpass_used as $fixpass_option ) {
	delete_option( $fixpass_option );
}

foreach ( array( 'fixpass_access', 'fixpass_ended', 'fixpass_spots', 'fixpass_creating' ) as $fixpass_option ) {
	delete_option( $fixpass_option );
}

foreach ( array( 'fixpass_expire', 'fixpass_self_remove' ) as $fixpass_hook ) {
	wp_clear_scheduled_hook( $fixpass_hook );
}
