<?php
/**
 * Plugin bootstrap and automatic cleanup.
 *
 * @package Fixpass
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin together, and cleans up after access ends.
 */
final class Fixpass_Plugin {

	/**
	 * Cron hook: deactivate and delete Fixpass after access ended, when the owner asked.
	 */
	const SELF_REMOVE = 'fixpass_self_remove';

	/**
	 * Transient set on activation, so the next admin page opens Fixpass.
	 */
	const REDIRECT = 'fixpass_activation_redirect';

	/**
	 * Register hooks.
	 */
	public static function init() {
		Fixpass_Access::init();
		Fixpass_Spotlight::init();
		Fixpass_Admin::init();
		if ( is_multisite() ) {
			Fixpass_Network::init();
		}

		add_action( self::SELF_REMOVE, array( __CLASS__, 'self_remove' ) );
		add_action( 'admin_init', array( __CLASS__, 'activation_redirect' ) );
	}

	/**
	 * Right after activation, open Fixpass's page. Not for bulk activation, AJAX or WP-CLI.
	 */
	public static function activation_redirect() {
		if ( ! get_transient( self::REDIRECT ) ) {
			return;
		}
		delete_transient( self::REDIRECT );
		if ( isset( $_GET['activate-multi'] ) || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! Fixpass_Access::can_give() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checks whether this was a bulk activation.
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . Fixpass_Admin::SLUG ) );
		exit;
	}

	/**
	 * After access ended, when the owner ticked "Delete Fixpass when access ends".
	 *
	 * On a single site: deactivate, then delete the plugin (which runs uninstall.php). Hosts that
	 * need FTP details to change files can't delete it here, so there it stays installed but
	 * deactivated.
	 *
	 * On a network the plugin's files belong to every site, so they are never deleted from here:
	 * Fixpass is deactivated for this site only, and what it stored for this site is removed.
	 */
	public static function self_remove() {
		if ( Fixpass_Access::active() || ! Fixpass_Access::can_self_remove() ) {
			return; // New access was created in the meantime, or the network activated Fixpass.
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = plugin_basename( FIXPASS_FILE );
		deactivate_plugins( $file, true );
		if ( is_multisite() ) {
			self::delete_site_data();
			return;
		}
		// Never delete a source checkout (a developer's copy): only real installs are deleted.
		if ( file_exists( FIXPASS_DIR . '.git' ) ) {
			return;
		}
		delete_plugins( array( $file ) );
	}

	/**
	 * Remove what Fixpass stored for the current site (its options and scheduled tasks).
	 */
	private static function delete_site_data() {
		global $wpdb;
		$used = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s', $wpdb->esc_like( 'fixpass_used_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $used as $option ) {
			delete_option( $option );
		}
		foreach ( array( Fixpass_Access::OPTION, Fixpass_Access::ENDED, Fixpass_Spotlight::OPTION, 'fixpass_creating' ) as $option ) {
			delete_option( $option );
		}
		foreach ( array( Fixpass_Access::EXPIRE, self::SELF_REMOVE ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Activation.
	 *
	 * @param bool $network_wide Activated for the whole network.
	 */
	public static function activate( $network_wide = false ) {
		if ( ! $network_wide ) {
			set_transient( self::REDIRECT, 1, 30 );
		}
	}

	/**
	 * Deactivation: end any access (its accounts depend on this plugin to expire) and stop
	 * scheduled jobs. When the network deactivates Fixpass, access ends on every site.
	 *
	 * @param bool $network_wide Deactivated for the whole network.
	 */
	public static function deactivate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			Fixpass_Access::revoke_everywhere( 'deactivated' );
			return;
		}
		Fixpass_Access::revoke( 'deactivated' );
		wp_clear_scheduled_hook( self::SELF_REMOVE );
	}
}
