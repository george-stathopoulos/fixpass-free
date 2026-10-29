<?php
/**
 * Plugin bootstrap.
 *
 * @package Fixpass
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin together. Cleaning up after access ends is done by Fixpass_Access.
 */
final class Fixpass_Plugin {

	/**
	 * Transient set on activation, so a short notice shows once.
	 */
	const NOTICE = 'fixpass_activation_notice';

	/**
	 * Register hooks.
	 */
	public static function init() {
		Fixpass_Access::init();
		Fixpass_Admin::init();
		if ( is_multisite() ) {
			Fixpass_Network::init();
		}

		add_action( 'admin_notices', array( __CLASS__, 'activation_notice' ) );
	}

	/**
	 * Right after activation, say once where Fixpass is. A notice rather than a redirect, so
	 * activating Fixpass never takes you away from the page you were on.
	 */
	public static function activation_notice() {
		if ( ! get_transient( self::NOTICE ) || ! Fixpass_Access::can_give() ) {
			return;
		}
		delete_transient( self::NOTICE );
		echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses(
			sprintf(
				/* translators: %s: link to the Fixpass page */
				__( 'Fixpass is ready. When you need help with your site, %s.', 'fixpass' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=' . Fixpass_Admin::SLUG ) ) . '">' . esc_html__( 'open Fixpass to give your support team temporary access', 'fixpass' ) . '</a>'
			),
			array(
				'a' => array( 'href' => true ),
			)
		) . '</p></div>';
	}

	/**
	 * Activation.
	 *
	 * @param bool $network_wide Activated for the whole network.
	 */
	public static function activate( $network_wide = false ) {
		if ( ! $network_wide ) {
			set_transient( self::NOTICE, 1, 10 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Deactivation: end any access, because its account depends on this plugin to expire. When
	 * the network deactivates Fixpass, access ends on every site.
	 *
	 * @param bool $network_wide Deactivated for the whole network.
	 */
	public static function deactivate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			Fixpass_Access::revoke_everywhere( 'deactivated' );
			return;
		}
		Fixpass_Access::revoke( 'deactivated' );
	}
}
