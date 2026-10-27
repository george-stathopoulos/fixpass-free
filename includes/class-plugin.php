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
		if ( isset( $_GET['activate-multi'] ) || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checks whether this was a bulk activation.
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . Fixpass_Admin::SLUG ) );
		exit;
	}

	/**
	 * After access ended, when the owner ticked "Delete Fixpass when access ends": deactivate,
	 * then delete the plugin (which runs uninstall.php). Hosts that need FTP details to change
	 * files can't delete it here, so there it stays installed but deactivated.
	 */
	public static function self_remove() {
		if ( Fixpass_Access::active() ) {
			return; // New access was created in the meantime.
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = plugin_basename( FIXPASS_FILE );
		deactivate_plugins( $file, true );
		delete_plugins( array( $file ) );
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		set_transient( self::REDIRECT, 1, 30 );
	}

	/**
	 * Deactivation: end any access (its accounts depend on this plugin to expire) and stop
	 * scheduled jobs.
	 */
	public static function deactivate() {
		Fixpass_Access::revoke( 'deactivated' );
		wp_clear_scheduled_hook( self::SELF_REMOVE );
	}
}
