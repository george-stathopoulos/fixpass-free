<?php
/**
 * Temporary support access: the support account, the single-use login link, expiry and cleanup.
 *
 * @package Fixpass
 */

defined( 'ABSPATH' ) || exit;

/**
 * One site has at most one access at a time. It owns a temporary administrator account that is
 * deleted when access expires or is ended. The login link works once: opening it shows a
 * confirmation page, and only pressing the button (a POST) uses it up, so email scanners that
 * open links automatically can't burn it.
 */
final class Fixpass_Access {

	/**
	 * User meta that marks a support account. Its value is the ID of the site the account was
	 * made for: on multisite, users are shared by the whole network, and a support account is
	 * only ever a support account on its own site.
	 */
	const META = 'fixpass_support';

	/**
	 * Multisite: network option that lists the sites with support access (site ID => end time),
	 * so the network admin can see and end them without visiting every site.
	 */
	const NETWORK_SITES = 'fixpass_network_sites';

	/**
	 * Multisite: network option, whether site administrators may give support access (default on).
	 */
	const ALLOW_SITE_ADMINS = 'fixpass_allow_site_admins';

	/**
	 * Option that holds the current access.
	 */
	const OPTION = 'fixpass_access';

	/**
	 * Option that remembers how the last access ended, for the owner's screen.
	 */
	const ENDED = 'fixpass_ended';

	/**
	 * Cron hook that ends access at its end time.
	 */
	const EXPIRE = 'fixpass_expire';

	const LOGIN_ACTION  = 'fixpass';
	const DEFAULT_HOURS = 72;

	/**
	 * True while revoke() deletes accounts, so their deletion doesn't start another revoke.
	 *
	 * @var bool
	 */
	private static $ending = false;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'login_form_' . self::LOGIN_ACTION, array( __CLASS__, 'login_screen' ) );
		add_action( 'init', array( __CLASS__, 'expire_due' ), 1 );
		add_action( self::EXPIRE, array( __CLASS__, 'cron_expire' ) );
		add_filter( 'authenticate', array( __CLASS__, 'block_password_login' ), 100, 2 );
		add_filter( 'determine_current_user', array( __CLASS__, 'enforce_expiry' ), 100 );
		add_filter( 'auth_cookie_expiration', array( __CLASS__, 'cookie_expiration' ), 100, 2 );
		add_filter( 'user_has_cap', array( __CLASS__, 'limit_caps' ), 100, 4 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'protect' ), 100, 4 );
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 10, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'on_logout' ) );
		add_action( 'delete_user', array( __CLASS__, 'on_user_deleted' ) );
		add_action( 'wpmu_delete_user', array( __CLASS__, 'on_user_deleted' ) );
		add_action( 'remove_user_from_blog', array( __CLASS__, 'on_user_deleted' ) );
	}

	/**
	 * Lengths the owner can pick.
	 *
	 * @return array Hours => label.
	 */
	public static function durations() {
		return array(
			24  => __( '1 day', 'fixpass' ),
			48  => __( '2 days', 'fixpass' ),
			72  => __( '3 days', 'fixpass' ),
			120 => __( '5 days', 'fixpass' ),
			168 => __( '1 week', 'fixpass' ),
		);
	}

	/**
	 * The current access, even if it ran out but wasn't cleaned up yet.
	 *
	 * @return array|null
	 */
	public static function get() {
		$grant = get_option( self::OPTION );
		return is_array( $grant ) && ! empty( $grant['user_id'] ) ? $grant : null;
	}

	/**
	 * The access, if it is usable now.
	 *
	 * @return array|null
	 */
	public static function active() {
		$grant = self::get();
		return $grant && (int) $grant['expires_at'] > time() ? $grant : null;
	}

	/**
	 * Whether a user is a temporary support account.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function is_support_user( $user_id ) {
		return $user_id && (int) get_user_meta( (int) $user_id, self::META, true ) === get_current_blog_id();
	}

	/**
	 * Whether the current person may give support access on this site. On a single site, any
	 * administrator. On multisite, a super admin always; site administrators too, unless the
	 * network admin turned that off.
	 *
	 * @return bool
	 */
	public static function can_give() {
		if ( ! is_multisite() || is_super_admin() ) {
			return current_user_can( 'manage_options' );
		}
		return current_user_can( 'manage_options' ) && (bool) get_site_option( self::ALLOW_SITE_ADMINS, 1 );
	}

	/**
	 * Whether "Delete Fixpass when access ends" is offered. Plugin files are shared by every site
	 * on a network, so there it only switches Fixpass off for this site and removes its data; and
	 * when the network activated Fixpass, it can't be switched off for one site at all.
	 *
	 * @return bool
	 */
	public static function can_self_remove() {
		if ( ! is_multisite() ) {
			return true;
		}
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return ! is_plugin_active_for_network( plugin_basename( FIXPASS_FILE ) );
	}

	/**
	 * Multisite: note that this site has (or no longer has) support access, for the network list.
	 *
	 * @param int $expires_at End time, or 0 when access ended.
	 */
	private static function register_site( $expires_at ) {
		if ( ! is_multisite() ) {
			return;
		}
		$sites = (array) get_site_option( self::NETWORK_SITES, array() );
		if ( $expires_at ) {
			$sites[ get_current_blog_id() ] = (int) $expires_at;
		} else {
			unset( $sites[ get_current_blog_id() ] );
		}
		update_site_option( self::NETWORK_SITES, $sites );
	}

	/**
	 * End the access on every site of a network (when Fixpass is deactivated for the network).
	 *
	 * @param string $reason Reason.
	 */
	public static function revoke_everywhere( $reason = 'deactivated' ) {
		if ( ! is_multisite() ) {
			self::revoke( $reason );
			return;
		}
		foreach ( get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::revoke( $reason );
			restore_current_blog();
		}
		delete_site_option( self::NETWORK_SITES );
	}

	/**
	 * Whether the current user is the support account of the active access.
	 *
	 * @return bool
	 */
	public static function is_support_session() {
		$grant = self::active();
		return $grant && get_current_user_id() === (int) $grant['user_id'];
	}

	/**
	 * Where the access stands, for the owner: waiting (nobody logged in yet), active (support
	 * is logged in), logged_out, or ended.
	 *
	 * @return string
	 */
	public static function status() {
		$grant = self::active();
		if ( ! $grant ) {
			return 'ended';
		}
		if ( 'active' === $grant['status'] ) {
			// Logged in means a live session; one that timed out counts as logged out.
			return WP_Session_Tokens::get_instance( (int) $grant['user_id'] )->get_all() ? 'active' : 'logged_out';
		}
		return (string) $grant['status'];
	}

	/**
	 * Create the temporary account and its first login link.
	 *
	 * @param int    $hours   Access length (one of durations()).
	 * @param bool   $cleanup Delete Fixpass once access ends.
	 * @param string $note    The owner's description of the problem, for support.
	 * @return array|WP_Error { url, grant }
	 */
	public static function create( $hours, $cleanup, $note = '' ) {
		$hours = isset( self::durations()[ (int) $hours ] ) ? (int) $hours : self::DEFAULT_HOURS;

		// Two clicks landing at the same instant must not make two accounts. add_option() is atomic
		// (option names are unique), so only one request gets the lock.
		$held = (int) get_option( 'fixpass_creating', 0 );
		if ( $held && $held < time() - 30 ) {
			delete_option( 'fixpass_creating' ); // Left behind by a request that died.
		}
		if ( ! add_option( 'fixpass_creating', time(), '', 'no' ) ) {
			return new WP_Error( 'fixpass_busy', __( 'Support access is already being created. Wait a moment, then reload the page.', 'fixpass' ) );
		}
		try {
			if ( self::active() ) {
				return new WP_Error( 'fixpass_active', __( 'Support access is already active. End it first to start again.', 'fixpass' ) );
			}
			self::revoke( 'replaced' ); // Clear anything left over from an access that ran out.
			$user_id = self::create_user();
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			$expires = time() + $hours * HOUR_IN_SECONDS;
			update_option(
				self::OPTION,
				array(
					'user_id'    => $user_id,
					'token_hash' => '',
					'created_at' => time(),
					'expires_at' => $expires,
					'created_by' => get_current_user_id(),
					'cleanup'    => (bool) $cleanup && self::can_self_remove(),
					'status'     => 'waiting',
					'note'       => Fixpass_Redactor::text( mb_substr( sanitize_textarea_field( (string) $note ), 0, 1000 ) ),
				),
				true
			);
			self::register_site( $expires );
			delete_option( self::ENDED );
			wp_clear_scheduled_hook( Fixpass_Plugin::SELF_REMOVE );
			wp_schedule_single_event( $expires + MINUTE_IN_SECONDS, self::EXPIRE );
			return array(
				'url'   => self::new_link(),
				'grant' => self::get(),
			);
		} finally {
			delete_option( 'fixpass_creating' );
		}
	}

	/**
	 * Create the support account: an administrator nobody can log in to with a password.
	 *
	 * @return int|WP_Error User ID.
	 */
	private static function create_user() {
		$suffix  = strtolower( wp_generate_password( 6, false ) );
		$host    = wp_parse_url( home_url(), PHP_URL_HOST );
		$host    = $host && false !== strpos( $host, '.' ) ? $host : 'example.invalid';
		$user_id = wp_insert_user(
			array(
				'user_login'   => 'support-' . $suffix,
				'user_pass'    => wp_generate_password( 64, true, true ),
				'user_email'   => 'fixpass-' . $suffix . '@' . $host,
				'display_name' => __( 'Support (temporary access)', 'fixpass' ),
				'first_name'   => __( 'Support', 'fixpass' ),
				'role'         => 'administrator',
				'description'  => __( 'Temporary support account created by Fixpass. It is deleted automatically when access ends.', 'fixpass' ),
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		update_user_meta( $user_id, self::META, get_current_blog_id() );
		update_user_meta( $user_id, 'show_admin_bar_front', 'true' );
		return (int) $user_id;
	}

	/**
	 * Issue a fresh single-use login link. An earlier unused link stops working.
	 *
	 * @return string|WP_Error URL.
	 */
	public static function new_link() {
		$grant = self::active();
		if ( ! $grant ) {
			return new WP_Error( 'fixpass_inactive', __( 'Support access is not active.', 'fixpass' ) );
		}
		if ( ! empty( $grant['token_hash'] ) ) {
			delete_option( self::used_option( $grant['token_hash'] ) ); // The old link is replaced, so it needs no marker.
		}
		$secret              = bin2hex( random_bytes( 32 ) ); // 256 bits; only its hash is stored.
		$grant['token_hash'] = hash( 'sha256', $secret );
		update_option( self::OPTION, $grant, true );
		return add_query_arg(
			array(
				'action' => self::LOGIN_ACTION,
				'token'  => $secret,
			),
			wp_login_url()
		);
	}

	/**
	 * End the access now: delete the support account and its sessions, and (if the owner asked)
	 * schedule Fixpass's own removal.
	 *
	 * @param string $reason revoked | expired | support_ended | deactivated | replaced.
	 */
	public static function revoke( $reason = 'revoked' ) {
		$grant = self::get();
		$users = self::support_users();
		if ( $grant ) {
			$users[] = (int) $grant['user_id'];
		}
		$users = array_unique( array_filter( array_map( 'intval', $users ) ) );
		if ( ! $users && ! $grant ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$reassign     = $grant && (int) $grant['created_by'] && get_userdata( (int) $grant['created_by'] ) ? (int) $grant['created_by'] : null;
		self::$ending = true;
		foreach ( $users as $user_id ) {
			if ( ! get_userdata( $user_id ) ) {
				continue;
			}
			WP_Session_Tokens::get_instance( $user_id )->destroy_all();
			if ( is_multisite() ) {
				require_once ABSPATH . 'wp-admin/includes/ms.php';
				// Reassign content first: wpmu_delete_user() would otherwise delete it.
				if ( $reassign ) {
					remove_user_from_blog( $user_id, get_current_blog_id(), $reassign );
				}
				wpmu_delete_user( $user_id );
			} else {
				wp_delete_user( $user_id, $reassign );
			}
		}
		self::$ending = false;

		if ( ! $grant ) {
			return;
		}
		if ( ! empty( $grant['token_hash'] ) ) {
			delete_option( self::used_option( $grant['token_hash'] ) );
		}
		delete_option( self::OPTION );
		wp_clear_scheduled_hook( self::EXPIRE );
		self::register_site( 0 );
		if ( 'replaced' === $reason ) {
			return; // A new access is being created.
		}
		update_option(
			self::ENDED,
			array(
				'time'   => time(),
				'reason' => $reason,
			),
			false
		);
		// The owner asked for Fixpass to remove itself once this access ended.
		if ( ! empty( $grant['cleanup'] ) && 'deactivated' !== $reason && ! wp_next_scheduled( Fixpass_Plugin::SELF_REMOVE ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, Fixpass_Plugin::SELF_REMOVE );
		}
	}

	/**
	 * Every support account on the site (normally one).
	 *
	 * @return int[]
	 */
	private static function support_users() {
		return array_map(
			'intval',
			get_users(
				array(
					'meta_key'   => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => get_current_blog_id(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'fields'     => 'ID',
					'blog_id'    => get_current_blog_id(),
				)
			)
		);
	}

	/**
	 * Every page load: end an access that ran out.
	 */
	public static function expire_due() {
		$grant = self::get();
		if ( $grant && (int) $grant['expires_at'] <= time() ) {
			self::revoke( 'expired' );
		}
	}

	/**
	 * Cron, at the end time (or when an account is found with no access behind it, for example
	 * after a restore): end the access and delete any support account left behind.
	 */
	public static function cron_expire() {
		if ( self::get() ) {
			self::expire_due();
		} else {
			self::revoke( 'expired' );
		}
	}

	/**
	 * Option that marks a login link as used. add_option() is atomic, so it also guards against
	 * two requests using the same link at once.
	 *
	 * @param string $hash Hash of the link's secret.
	 * @return string
	 */
	private static function used_option( $hash ) {
		return 'fixpass_used_' . substr( (string) $hash, 0, 32 );
	}

	/**
	 * Validate a login link's secret.
	 *
	 * @param string $token Secret from the link.
	 * @return array|WP_Error The access.
	 */
	private static function grant_for_token( $token ) {
		$invalid = new WP_Error( 'fixpass_link', __( 'This support login link is not valid. It may have been used already, replaced by a newer link, or access may have ended. Ask the site owner for a new link.', 'fixpass' ) );
		$grant   = self::get();
		if ( ! preg_match( '/^[a-f0-9]{64}$/', (string) $token ) || ! $grant || '' === (string) $grant['token_hash'] || ! hash_equals( (string) $grant['token_hash'], hash( 'sha256', $token ) ) ) {
			return $invalid;
		}
		if ( false !== get_option( self::used_option( $grant['token_hash'] ) ) ) {
			return $invalid;
		}
		if ( ! self::active() || ! get_userdata( (int) $grant['user_id'] ) ) {
			return new WP_Error( 'fixpass_expired', __( 'Support access to this site has ended.', 'fixpass' ) );
		}
		return $grant;
	}

	/**
	 * Login link screen: GET shows a confirmation button, POST uses up the link and logs in.
	 */
	public static function login_screen() {
		$token = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the single-use secret is the credential.
		$error = null;
		$grant = self::grant_for_token( $token );

		if ( is_wp_error( $grant ) ) {
			$error = $grant;
		} elseif ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			if ( ! isset( $_POST['fixpass_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fixpass_nonce'] ) ), 'fixpass_login' ) ) {
				$error = new WP_Error( 'fixpass_nonce', __( 'This page expired. Open the link again.', 'fixpass' ) );
			} else {
				self::consume_and_login( $grant );
			}
		}

		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		wp_enqueue_style( 'fixpass-login', FIXPASS_URL . 'assets/login.css', array( 'login' ), FIXPASS_VERSION );
		login_header( __( 'Support login', 'fixpass' ), '', $error );

		if ( ! $error ) {
			echo '<form method="post" class="fixpass-login" action="' . esc_url( add_query_arg( 'action', self::LOGIN_ACTION, wp_login_url() ) ) . '">';
			echo '<p class="fixpass-login__badge">' . esc_html__( 'Temporary support access', 'fixpass' ) . '</p>';
			echo '<h2 class="fixpass-login__title">' . esc_html(
				sprintf(
					/* translators: %s: site name */
					__( 'Log in to %s', 'fixpass' ),
					wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
				)
			) . '</h2>';
			echo '<dl class="fixpass-login__facts">';
			echo '<dt>' . esc_html__( 'You will be', 'fixpass' ) . '</dt><dd>' . esc_html__( 'Support, a temporary administrator', 'fixpass' ) . '</dd>';
			echo '<dt>' . esc_html__( 'Access ends', 'fixpass' ) . '</dt><dd>' . esc_html(
				sprintf(
					/* translators: 1: date and time, 2: time left */
					__( '%1$s (in %2$s)', 'fixpass' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $grant['expires_at'] ),
					human_time_diff( time(), (int) $grant['expires_at'] )
				)
			) . '</dd></dl>';
			echo '<p class="fixpass-login__note"><strong>' . esc_html__( 'This link works once.', 'fixpass' ) . '</strong> ' . esc_html__( 'After you log in it can’t be used again. The site owner can end access at any time.', 'fixpass' ) . '</p>';
			echo '<input type="hidden" name="token" value="' . esc_attr( $token ) . '">';
			wp_nonce_field( 'fixpass_login', 'fixpass_nonce' );
			echo '<button type="submit" class="button button-primary button-large fixpass-login__button">' . esc_html__( 'Log in as support', 'fixpass' ) . '</button>';
			echo '</form>';
		}
		login_footer();
		exit;
	}

	/**
	 * Mark the link used and log in.
	 *
	 * @param array $grant The access.
	 */
	private static function consume_and_login( array $grant ) {
		// Only one request can create this option, so only one can use the link.
		if ( ! add_option( self::used_option( $grant['token_hash'] ), time(), '', 'no' ) ) {
			wp_die( esc_html__( 'This support login link was already used.', 'fixpass' ), '', array( 'response' => 403 ) );
		}
		$user = get_userdata( (int) $grant['user_id'] );
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, false, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's login hook, fired as wp_signon() does.
		wp_safe_redirect( admin_url( 'admin.php?page=' . Fixpass_Admin::SLUG ) );
		exit;
	}

	/**
	 * Record the new status of the access.
	 *
	 * @param string $status waiting | active | logged_out.
	 */
	private static function set_status( $status ) {
		$grant = self::get();
		if ( $grant ) {
			$grant['status'] = $status;
			update_option( self::OPTION, $grant, true );
		}
	}

	/**
	 * Tell the site owner that support logged in.
	 *
	 * @param string  $login User login.
	 * @param WP_User $user  User.
	 */
	public static function on_login( $login, $user ) {
		if ( ! self::is_support_user( $user->ID ) ) {
			return;
		}
		self::set_status( 'active' );
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		wp_mail(
			get_option( 'admin_email' ),
			/* translators: %s: site name */
			sprintf( __( '[%s] Support logged in to your site', 'fixpass' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			sprintf(
				/* translators: 1: IP address, 2: URL */
				__( "Support just logged in using the link you created (IP %1\$s).\n\nYou can end access at any time: %2\$s", 'fixpass' ),
				$ip,
				admin_url( 'admin.php?page=' . Fixpass_Admin::SLUG )
			)
		);
	}

	/**
	 * Support logged out.
	 *
	 * @param int $user_id User.
	 */
	public static function on_logout( $user_id = 0 ) {
		if ( self::is_support_user( (int) $user_id ) ) {
			self::set_status( 'logged_out' );
		}
	}

	/**
	 * The support account was deleted (for example on the Users screen): the access ends too.
	 *
	 * @param int $user_id User being deleted or removed from this site.
	 */
	public static function on_user_deleted( $user_id ) {
		if ( self::$ending || ! self::is_support_user( (int) $user_id ) ) {
			return;
		}
		$grant = self::get();
		if ( ! $grant ) {
			return;
		}
		delete_option( self::OPTION );
		wp_clear_scheduled_hook( self::EXPIRE );
		self::register_site( 0 );
		update_option(
			self::ENDED,
			array(
				'time'   => time(),
				'reason' => 'deleted',
			),
			false
		);
	}

	/**
	 * Support users can only log in with a link, never a password.
	 *
	 * @param WP_User|WP_Error|null $user     User.
	 * @param string                $username Username.
	 * @return WP_User|WP_Error|null
	 */
	public static function block_password_login( $user, $username ) {
		if ( $user instanceof WP_User && self::is_support_user( $user->ID ) && '' !== (string) $username ) {
			return new WP_Error( 'fixpass_password', __( 'Temporary support accounts can only log in with a support login link.', 'fixpass' ) );
		}
		return $user;
	}

	/**
	 * Treat an expired support account as logged out, even before cleanup deletes it.
	 *
	 * @param int|false $user_id User.
	 * @return int|false
	 */
	public static function enforce_expiry( $user_id ) {
		if ( ! $user_id || ! self::is_support_user( $user_id ) ) {
			return $user_id;
		}
		$grant = self::active();
		if ( ! $grant || (int) $grant['user_id'] !== (int) $user_id ) {
			wp_schedule_single_event( time(), self::EXPIRE ); // Duplicates within 10 minutes are ignored by WordPress.
			return false;
		}
		return $user_id;
	}

	/**
	 * Login cookies for support never outlive the access.
	 *
	 * @param int $length  Seconds.
	 * @param int $user_id User.
	 * @return int
	 */
	public static function cookie_expiration( $length, $user_id ) {
		$grant = self::get();
		if ( $grant && self::is_support_user( $user_id ) ) {
			return max( 60, min( (int) $length, (int) $grant['expires_at'] - time() ) );
		}
		return $length;
	}

	/**
	 * Whatever happens, support never manages users (an account they created would outlive their
	 * access) or installs and deletes themes (which can break the site for visitors).
	 *
	 * @param array   $allcaps All caps.
	 * @param array   $caps    Required caps.
	 * @param array   $args    Args.
	 * @param WP_User $user    User.
	 * @return array
	 */
	public static function limit_caps( $allcaps, $caps, $args, $user ) {
		if ( ! $user instanceof WP_User || ! $user->ID || ! self::is_support_user( $user->ID ) ) {
			return $allcaps;
		}
		foreach ( array( 'create_users', 'promote_users', 'add_users', 'edit_users', 'delete_users', 'remove_users', 'list_users', 'manage_network_users', 'install_themes', 'delete_themes' ) as $cap ) {
			$allcaps[ $cap ] = false;
		}
		return $allcaps;
	}

	/**
	 * Support can't deactivate or delete Fixpass, or edit other users.
	 *
	 * @param string[] $caps    Primitive caps.
	 * @param string   $cap     Meta cap.
	 * @param int      $user_id User.
	 * @param array    $args    Args.
	 * @return string[]
	 */
	public static function protect( $caps, $cap, $user_id, $args ) {
		if ( ! self::is_support_user( $user_id ) ) {
			return $caps;
		}
		$own = plugin_basename( FIXPASS_FILE );
		if ( in_array( $cap, array( 'deactivate_plugin', 'delete_plugin' ), true ) && isset( $args[0] ) && $own === $args[0] ) {
			return array( 'do_not_allow' );
		}
		if ( in_array( $cap, array( 'delete_plugins', 'deactivate_plugins' ), true ) && isset( $_REQUEST['checked'] ) && in_array( $own, (array) wp_unslash( $_REQUEST['checked'] ), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared, never stored or output.
			return array( 'do_not_allow' );
		}
		if ( in_array( $cap, array( 'edit_user', 'delete_user', 'promote_user', 'remove_user' ), true ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}
}
