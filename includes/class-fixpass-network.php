<?php
/**
 * Multisite: the network admin overview.
 *
 * @package Fixpass
 */

defined( 'ABSPATH' ) || exit;

/**
 * On a network, each site gives and ends its own support access. This page is for super admins:
 * it lists the sites that have support access right now, ends access on any of them, and sets
 * whether site administrators may give access at all.
 */
final class Fixpass_Network {

	const SLUG = 'fixpass-network';

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'network_admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Network admin menu.
	 */
	public static function menu() {
		self::$hook = add_menu_page( __( 'Fixpass', 'fixpass' ), __( 'Fixpass', 'fixpass' ), 'manage_network_options', self::SLUG, array( __CLASS__, 'render' ), 'dashicons-sos', 80 );
		add_action( 'load-' . self::$hook, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Styles and script for the page.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_style( 'fixpass-admin', FIXPASS_URL . 'assets/admin.css', array(), FIXPASS_VERSION );
		wp_enqueue_script( 'fixpass-admin', FIXPASS_URL . 'assets/admin.js', array(), FIXPASS_VERSION, true );
	}

	/**
	 * Address of this page.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	private static function url( array $args = array() ) {
		return add_query_arg( $args, network_admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	/**
	 * Handle a button press, before anything is output.
	 */
	public static function handle() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		check_admin_referer( 'fixpass_network', 'fixpass_nonce' );
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'fixpass' ), '', array( 'response' => 403 ) );
		}
		$do = isset( $_POST['fixpass_do'] ) ? sanitize_key( wp_unslash( $_POST['fixpass_do'] ) ) : '';

		if ( 'settings' === $do ) {
			update_site_option( Fixpass_Access::ALLOW_SITE_ADMINS, empty( $_POST['allow_site_admins'] ) ? 0 : 1 );
			wp_safe_redirect( self::url( array( 'saved' => 1 ) ) );
			exit;
		}
		if ( 'end' === $do ) {
			$site_id = isset( $_POST['site'] ) ? absint( $_POST['site'] ) : 0;
			if ( $site_id && get_site( $site_id ) ) {
				switch_to_blog( $site_id );
				Fixpass_Access::revoke( 'revoked' );
				restore_current_blog();
			}
			wp_safe_redirect( self::url( array( 'ended' => 1 ) ) );
			exit;
		}
	}

	/**
	 * The page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		echo '<div class="wrap fixpass"><h1>' . esc_html__( 'Fixpass', 'fixpass' ) . '</h1>';
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Settings saved.', 'fixpass' ) . '</p></div>';
		}
		if ( isset( $_GET['ended'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Support access ended and the support account was deleted.', 'fixpass' ) . '</p></div>';
		}

		echo '<div class="fixpass-card"><h2>' . esc_html__( 'Who can give support access', 'fixpass' ) . '</h2>';
		echo '<p>' . esc_html__( 'Each site gives and ends its own support access, from Fixpass in that site’s admin. Support gets an administrator account on that one site only.', 'fixpass' ) . '</p>';
		echo '<form method="post" action="' . esc_url( self::url() ) . '">';
		wp_nonce_field( 'fixpass_network', 'fixpass_nonce' );
		echo '<input type="hidden" name="fixpass_do" value="settings">';
		echo '<p><label><input type="checkbox" name="allow_site_admins" value="1"' . checked( (bool) get_site_option( Fixpass_Access::ALLOW_SITE_ADMINS, 1 ), true, false ) . '> ' . esc_html__( 'Let site administrators give support access on their own site', 'fixpass' ) . '</label><br><span class="description">' . esc_html__( 'Turn this off to keep it to super admins. Access that is already on is not affected.', 'fixpass' ) . '</span></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'fixpass' ) . '</button></p></form></div>';

		echo '<div class="fixpass-card"><h2>' . esc_html__( 'Sites with support access now', 'fixpass' ) . '</h2>';
		self::render_sites();
		echo '</div></div>';
	}

	/**
	 * The sites that have support access. The list is checked against each site before it is
	 * shown, and sites whose access has gone are dropped from it.
	 */
	private static function render_sites() {
		$registry = (array) get_site_option( Fixpass_Access::NETWORK_SITES, array() );
		$rows     = array();
		foreach ( array_keys( $registry ) as $site_id ) {
			if ( ! get_site( (int) $site_id ) ) {
				unset( $registry[ $site_id ] );
				continue;
			}
			switch_to_blog( (int) $site_id );
			$grant = Fixpass_Access::active();
			if ( $grant ) {
				$rows[] = array(
					'id'      => (int) $site_id,
					'name'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					'url'     => home_url( '/' ),
					'status'  => Fixpass_Access::status(),
					'expires' => (int) $grant['expires_at'],
				);
			} else {
				unset( $registry[ $site_id ] );
			}
			restore_current_blog();
		}
		update_site_option( Fixpass_Access::NETWORK_SITES, $registry );

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No site has support access right now.', 'fixpass' ) . '</p>';
			return;
		}
		$labels = array(
			'waiting'    => __( 'Waiting for support to log in', 'fixpass' ),
			'active'     => __( 'Support is logged in', 'fixpass' ),
			'logged_out' => __( 'Support logged out', 'fixpass' ),
		);
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Site', 'fixpass' ) . '</th><th>' . esc_html__( 'Status', 'fixpass' ) . '</th><th>' . esc_html__( 'Ends', 'fixpass' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td><a href="' . esc_url( $row['url'] ) . '">' . esc_html( $row['name'] ) . '</a></td>';
			echo '<td>' . esc_html( $labels[ $row['status'] ] ?? $row['status'] ) . '</td>';
			echo '<td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['expires'] ) ) . '</td>';
			echo '<td><form method="post" action="' . esc_url( self::url() ) . '" data-fixpass-confirm="' . esc_attr__( 'End support access on this site now? The support account will be deleted.', 'fixpass' ) . '">';
			wp_nonce_field( 'fixpass_network', 'fixpass_nonce' );
			echo '<input type="hidden" name="fixpass_do" value="end"><input type="hidden" name="site" value="' . esc_attr( $row['id'] ) . '">';
			echo '<button type="submit" class="button button-secondary fixpass-danger">' . esc_html__( 'End access', 'fixpass' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table>';
	}
}
