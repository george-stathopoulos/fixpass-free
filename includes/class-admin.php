<?php
/**
 * Admin screens: guided setup for the site owner, and the support dashboard.
 *
 * @package Fixpass
 */

defined( 'ABSPATH' ) || exit;

/**
 * One menu item. The site owner sees a guided setup (choose, spotlight the problem, give access,
 * copy the details). Support, once logged in, sees the Support session dashboard.
 */
final class Fixpass_Admin {

	const SLUG = 'fixpass';

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * What the last form submission produced.
	 *
	 * @var array|null
	 */
	private static $result = null;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'support_notice' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );
		add_filter( 'plugin_action_links_' . plugin_basename( FIXPASS_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'manage_users_columns', array( __CLASS__, 'users_column' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'users_column_value' ), 10, 3 );
	}

	/**
	 * Menu: the guided setup for the site owner; the session dashboard for support.
	 */
	public static function menu() {
		$support = Fixpass_Access::is_support_user( get_current_user_id() );
		self::$hook = add_menu_page(
			$support ? __( 'Support session', 'fixpass' ) : __( 'Fixpass', 'fixpass' ),
			$support ? __( 'Support session', 'fixpass' ) : __( 'Fixpass', 'fixpass' ),
			$support ? 'read' : 'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-sos',
			$support ? 3 : 80
		);
		add_action( 'load-' . self::$hook, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Enqueue the page's styles and script.
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
	 * Address of the Fixpass page.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	private static function url( array $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	/**
	 * Handle a button press, before anything is output.
	 */
	public static function handle() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		check_admin_referer( 'fixpass_action', 'fixpass_nonce' );
		$do = isset( $_POST['fixpass_do'] ) ? sanitize_key( wp_unslash( $_POST['fixpass_do'] ) ) : '';

		// Support ends their own access.
		if ( Fixpass_Access::is_support_session() ) {
			if ( 'support_end' === $do ) {
				Fixpass_Access::revoke( 'support_ended' );
				wp_safe_redirect( home_url( '/' ) );
				exit;
			}
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || Fixpass_Access::is_support_user( get_current_user_id() ) ) {
			return;
		}

		if ( 'give' === $do ) {
			$ids   = self::posted_spot_ids();
			$hours = isset( $_POST['hours'] ) ? absint( $_POST['hours'] ) : Fixpass_Access::DEFAULT_HOURS;
			$out   = Fixpass_Access::create( $hours, ! empty( $_POST['cleanup'] ), wp_list_pluck( Fixpass_Spotlight::find( $ids ), 'id' ) );
			self::$result = is_wp_error( $out ) ? array( 'error' => $out ) : self::copy_result( $out['grant'], $out['url'] );
		} elseif ( 'link' === $do ) {
			$grant = Fixpass_Access::active();
			$url   = Fixpass_Access::new_link();
			self::$result = is_wp_error( $url ) || ! $grant ? array( 'error' => is_wp_error( $url ) ? $url : new WP_Error( 'fixpass_inactive', __( 'Support access is not active.', 'fixpass' ) ) ) : self::copy_result( $grant, $url );
		} elseif ( 'end' === $do ) {
			Fixpass_Access::revoke( 'revoked' );
			wp_safe_redirect( self::url() );
			exit;
		}
	}

	/**
	 * The details to copy, for a grant and its login link.
	 *
	 * @param array  $grant The access.
	 * @param string $url   Login link.
	 * @return array
	 */
	private static function copy_result( array $grant, $url ) {
		return array(
			'message' => Fixpass_Details::message( $grant, $url, Fixpass_Spotlight::find( (array) $grant['spots'] ) ),
			'url'     => $url,
		);
	}

	/**
	 * Spot IDs from a comma-separated form field or address.
	 *
	 * @return string[]
	 */
	private static function posted_spot_ids() {
		$raw = '';
		if ( isset( $_POST['spots'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in handle().
			$raw = sanitize_text_field( wp_unslash( $_POST['spots'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_GET['spots'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: shows the spots picked.
			$raw = sanitize_text_field( wp_unslash( $_GET['spots'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		return array_slice( array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) ), 0, Fixpass_Spotlight::MAX );
	}

	/**
	 * The page.
	 */
	public static function render() {
		echo '<div class="wrap fixpass"><h1>';
		echo esc_html( Fixpass_Access::is_support_user( get_current_user_id() ) ? __( 'Support session', 'fixpass' ) : __( 'Fixpass', 'fixpass' ) );
		echo '</h1>';
		if ( Fixpass_Access::is_support_user( get_current_user_id() ) ) {
			self::render_support();
		} elseif ( current_user_can( 'manage_options' ) ) {
			self::render_owner();
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------ Site owner */

	/**
	 * The site owner's screen.
	 */
	private static function render_owner() {
		if ( isset( self::$result['error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( self::$result['error']->get_error_message() ) . '</p></div>';
		}
		if ( isset( self::$result['message'] ) ) {
			self::render_copy( self::$result );
			return;
		}
		if ( Fixpass_Access::active() ) {
			self::render_status();
			return;
		}
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'start'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: picks the screen.
		if ( 'spots' === $step ) {
			self::render_spots_step();
		} elseif ( 'access' === $step ) {
			self::render_access_step();
		} else {
			self::render_start();
		}
	}

	/**
	 * The four steps, with the current one marked.
	 *
	 * @param int $current 1-4.
	 */
	private static function steps( $current ) {
		$labels = array(
			1 => __( 'Choose', 'fixpass' ),
			2 => __( 'Spotlight the problem', 'fixpass' ),
			3 => __( 'Give access', 'fixpass' ),
			4 => __( 'Copy the details', 'fixpass' ),
		);
		echo '<ol class="fixpass-steps">';
		foreach ( $labels as $n => $label ) {
			$class = $n === $current ? ' class="is-current"' : ( $n < $current ? ' class="is-done"' : '' );
			echo '<li' . $class . '><span>' . (int) $n . '</span> ' . esc_html( $label ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed class attribute.
		}
		echo '</ol>';
	}

	/**
	 * Step 1: choose how to start.
	 */
	private static function render_start() {
		self::steps( 1 );
		$ended = get_option( Fixpass_Access::ENDED );
		if ( is_array( $ended ) && ! empty( $ended['time'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html(
				sprintf(
					/* translators: %s: date and time */
					__( 'Support access ended on %s. The support account was deleted.', 'fixpass' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $ended['time'] )
				)
			) . '</p></div>';
		}
		echo '<p class="fixpass-lead">' . esc_html__( 'Need help with your site? Give your support team a temporary, single-use login link instead of a password. Access ends by itself, and the support account is deleted.', 'fixpass' ) . '</p>';
		echo '<div class="fixpass-cards">';
		echo '<div class="fixpass-card fixpass-card--main"><h2>' . esc_html__( 'Show the problem, then give access', 'fixpass' ) . '</h2>';
		echo '<p>' . esc_html__( 'Recommended. Point at the broken part of your site. Fixpass captures the page, your screen size, your browser and any errors on the page.', 'fixpass' ) . '</p>';
		echo '<a class="button button-primary button-hero" href="' . esc_url( self::url( array( 'step' => 'spots' ) ) ) . '">' . esc_html__( 'Start', 'fixpass' ) . '</a></div>';
		echo '<div class="fixpass-card"><h2>' . esc_html__( 'Just give access', 'fixpass' ) . '</h2>';
		echo '<p>' . esc_html__( 'Skip pointing at the problem. You can still describe it to your support team yourself.', 'fixpass' ) . '</p>';
		echo '<a class="button button-secondary button-hero" href="' . esc_url( self::url( array( 'step' => 'access' ) ) ) . '">' . esc_html__( 'Continue', 'fixpass' ) . '</a></div>';
		echo '</div>';
		self::render_privacy_note();
	}

	/**
	 * Step 2: point at the problem.
	 */
	private static function render_spots_step() {
		self::steps( 2 );
		echo '<div class="fixpass-card"><h2>' . esc_html__( 'Point at the problem', 'fixpass' ) . '</h2><ol>';
		echo '<li>' . esc_html__( 'Open your site. The Spotlight button glows in the toolbar.', 'fixpass' ) . '</li>';
		echo '<li>' . esc_html__( 'Go to the page that has the problem, click Spotlight a problem, then click the broken part or drag a box around it.', 'fixpass' ) . '</li>';
		echo '<li>' . esc_html__( 'Add a short note, then click Continue. You come back here.', 'fixpass' ) . '</li></ol>';
		echo '<p><a class="button button-primary button-hero" href="' . esc_url( add_query_arg( 'fxp_guide', '1', home_url( '/' ) ) ) . '">' . esc_html__( 'Open my site', 'fixpass' ) . '</a> ';
		echo '<a class="button button-link" href="' . esc_url( self::url( array( 'step' => 'access' ) ) ) . '">' . esc_html__( 'Skip this step', 'fixpass' ) . '</a></p></div>';
		self::render_privacy_note();
	}

	/**
	 * Step 3: choose how long, and give access.
	 */
	private static function render_access_step() {
		self::steps( 3 );
		$spots = Fixpass_Spotlight::find( self::posted_spot_ids() );
		if ( $spots ) {
			echo '<div class="fixpass-card"><h2>' . esc_html__( 'Your spots', 'fixpass' ) . '</h2><ul class="fixpass-list">';
			foreach ( $spots as $spot ) {
				$text = ( '' !== $spot['label'] ? $spot['label'] : __( 'Spot', 'fixpass' ) ) . ' · ' . ( '' !== $spot['title'] ? $spot['title'] : $spot['url'] );
				if ( $spot['errors'] || $spot['requests'] ) {
					$text .= ' · ' . sprintf(
						/* translators: 1: number of errors, 2: number of failed requests */
						__( '%1$d errors, %2$d failed requests captured', 'fixpass' ),
						count( $spot['errors'] ),
						count( $spot['requests'] )
					);
				}
				echo '<li>' . esc_html( $text ) . '</li>';
			}
			echo '</ul></div>';
		}
		echo '<form method="post" class="fixpass-card" action="' . esc_url( self::url() ) . '">';
		wp_nonce_field( 'fixpass_action', 'fixpass_nonce' );
		echo '<input type="hidden" name="fixpass_do" value="give">';
		echo '<input type="hidden" name="spots" value="' . esc_attr( implode( ',', wp_list_pluck( $spots, 'id' ) ) ) . '">';
		echo '<h2>' . esc_html__( 'Give access', 'fixpass' ) . '</h2>';
		echo '<p><label for="fixpass-hours"><strong>' . esc_html__( 'How long should access last?', 'fixpass' ) . '</strong></label><br><select id="fixpass-hours" name="hours">';
		foreach ( Fixpass_Access::durations() as $hours => $label ) {
			echo '<option value="' . esc_attr( $hours ) . '"' . selected( Fixpass_Access::DEFAULT_HOURS, $hours, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label><input type="checkbox" name="cleanup" value="1"> ' . esc_html__( 'Delete Fixpass when access ends', 'fixpass' ) . '</label><br><span class="description">' . esc_html__( 'Fixpass deactivates and deletes itself, with everything it stored. Without this, Fixpass stays installed and only the support account is deleted.', 'fixpass' ) . '</span></p>';
		echo '<ul class="fixpass-list"><li>' . esc_html__( 'Support gets an administrator account with a login link instead of a password.', 'fixpass' ) . '</li>';
		echo '<li>' . esc_html__( 'The link works once. You can make a new one at any time.', 'fixpass' ) . '</li>';
		echo '<li>' . esc_html__( 'Support cannot manage users or install themes, and cannot remove Fixpass.', 'fixpass' ) . '</li>';
		echo '<li>' . esc_html__( 'When access ends, the support account is deleted automatically.', 'fixpass' ) . '</li></ul>';
		echo '<p><button type="submit" class="button button-primary button-hero">' . esc_html__( 'Give access', 'fixpass' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Step 4: copy the details for support.
	 *
	 * @param array $result { message, url }.
	 */
	private static function render_copy( array $result ) {
		self::steps( 4 );
		$grant = Fixpass_Access::active();
		echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Support access is on. Copy the details and send them to your support team.', 'fixpass' ) . '</p></div>';
		echo '<div class="fixpass-card"><h2>' . esc_html__( 'Copy support details', 'fixpass' ) . '</h2>';
		echo '<p>' . esc_html__( 'The login link, your spots, site information, and your plugins and themes, ready to paste into an email or ticket. Passwords and keys are never included.', 'fixpass' ) . '</p>';
		echo '<p><button type="button" class="button button-primary button-hero" data-fixpass-copy data-copied="' . esc_attr__( 'Copied!', 'fixpass' ) . '" data-failed="' . esc_attr__( 'Copy failed: select the text below and copy it yourself.', 'fixpass' ) . '">' . esc_html__( 'Copy support details', 'fixpass' ) . '</button> <span class="fixpass-copy-status" role="status"></span></p>';
		if ( $grant ) {
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: %s: date and time */
					__( 'Access ends on %s.', 'fixpass' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $grant['expires_at'] )
				)
			) . '</p>';
		}
		echo '<h3>' . esc_html__( 'Preview', 'fixpass' ) . '</h3>';
		echo '<div id="fixpass-html" class="fixpass-preview">';
		add_filter( 'safe_style_css', array( __CLASS__, 'allow_email_css' ) );
		echo wp_kses( $result['message']['html'], self::email_tags() );
		remove_filter( 'safe_style_css', array( __CLASS__, 'allow_email_css' ) );
		echo '</div>';
		echo '<textarea id="fixpass-text" class="fixpass-text" readonly rows="6" aria-label="' . esc_attr__( 'Plain text version', 'fixpass' ) . '">' . esc_textarea( $result['message']['text'] ) . '</textarea>';
		echo '</div>';
		echo '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Back to access status', 'fixpass' ) . '</a></p>';
	}

	/**
	 * The status screen while access is on.
	 */
	private static function render_status() {
		$grant  = Fixpass_Access::active();
		$status = Fixpass_Access::status();
		$labels = array(
			'waiting'    => __( 'Waiting for support to log in', 'fixpass' ),
			'active'     => __( 'Support is logged in', 'fixpass' ),
			'logged_out' => __( 'Support logged out', 'fixpass' ),
		);
		echo '<div class="fixpass-card"><h2>' . esc_html__( 'Support access is on', 'fixpass' ) . '</h2>';
		echo '<p class="fixpass-status fixpass-status--' . esc_attr( $status ) . '"><strong>' . esc_html( $labels[ $status ] ?? $status ) . '</strong></p>';
		echo '<p>' . esc_html(
			sprintf(
				/* translators: 1: date and time, 2: time left */
				__( 'Access ends on %1$s (in %2$s). Then the support account is deleted automatically.', 'fixpass' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $grant['expires_at'] ),
				human_time_diff( time(), (int) $grant['expires_at'] )
			)
		) . '</p>';
		if ( ! empty( $grant['cleanup'] ) ) {
			echo '<p>' . esc_html__( 'Fixpass will also delete itself when access ends.', 'fixpass' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( self::url() ) . '" class="fixpass-inline">';
		wp_nonce_field( 'fixpass_action', 'fixpass_nonce' );
		echo '<input type="hidden" name="fixpass_do" value="link">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Copy support details with a new link', 'fixpass' ) . '</button> ';
		echo '</form> ';
		echo '<form method="post" action="' . esc_url( self::url() ) . '" class="fixpass-inline" data-fixpass-confirm="' . esc_attr__( 'End support access now? The support account will be deleted.', 'fixpass' ) . '">';
		wp_nonce_field( 'fixpass_action', 'fixpass_nonce' );
		echo '<input type="hidden" name="fixpass_do" value="end">';
		echo '<button type="submit" class="button button-secondary fixpass-danger">' . esc_html__( 'End access now', 'fixpass' ) . '</button>';
		echo '</form>';
		echo '<p class="description">' . esc_html__( 'A new link replaces the old one. The old link stops working.', 'fixpass' ) . '</p></div>';
	}

	/**
	 * What Fixpass captures and shares.
	 */
	private static function render_privacy_note() {
		echo '<p class="description">' . esc_html__( 'Fixpass sends nothing to any outside service. The details only leave your site when you copy them and send them yourself. Nothing on your screen is recorded.', 'fixpass' ) . '</p>';
	}

	/**
	 * Tags the copied message uses.
	 *
	 * @return array
	 */
	private static function email_tags() {
		$style = array( 'style' => true );
		return array(
			'div'   => array(),
			'p'     => $style,
			'h2'    => $style,
			'a'     => array(
				'href'  => true,
				'style' => true,
			),
			'ul'    => $style,
			'li'    => $style,
			'table' => array(
				'role'  => true,
				'style' => true,
			),
			'tr'    => array(),
			'th'    => $style,
			'td'    => $style,
		);
	}

	/**
	 * CSS properties the copied message uses inline, so it keeps its look in an email.
	 *
	 * @param string[] $props Allowed properties.
	 * @return string[]
	 */
	public static function allow_email_css( $props ) {
		return array_merge( $props, array( 'display', 'font', 'border-radius', 'text-decoration', 'word-break', 'white-space', 'vertical-align', 'text-align', 'border-collapse', 'padding-left', 'margin-top' ) );
	}

	/* ------------------------------------------------------------------ Support dashboard */

	/**
	 * The support dashboard: the owner's spots, site details and ways to end the session.
	 */
	private static function render_support() {
		$grant = Fixpass_Access::active();
		if ( ! $grant || ! Fixpass_Access::is_support_session() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Support access has ended.', 'fixpass' ) . '</p></div>';
			return;
		}
		echo '<div class="fixpass-card"><h2>' . esc_html(
			sprintf(
				/* translators: %s: site name */
				__( 'You are logged in to %s', 'fixpass' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			)
		) . '</h2><p>' . esc_html(
			sprintf(
				/* translators: 1: date and time, 2: time left */
				__( 'Access ends on %1$s (in %2$s). The login link works once: if you log out, ask the site owner for a new link.', 'fixpass' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $grant['expires_at'] ),
				human_time_diff( time(), (int) $grant['expires_at'] )
			)
		) . '</p></div>';

		$spots = Fixpass_Spotlight::find( (array) $grant['spots'] );
		echo '<div class="fixpass-card"><h2>' . esc_html__( 'The problem, as the site owner showed it', 'fixpass' ) . '</h2>';
		if ( $spots ) {
			echo '<p>' . esc_html__( 'Each link opens the page with the spot highlighted.', 'fixpass' ) . '</p>';
			echo wp_kses( Fixpass_Details::render( Fixpass_Details::spot_sections( $spots ) ), self::dashboard_tags() );
		} else {
			echo '<p>' . esc_html__( 'The site owner didn’t point at a spot. Ask them what is wrong.', 'fixpass' ) . '</p>';
		}
		echo '</div>';

		echo '<div class="fixpass-card"><h2>' . esc_html__( 'Site details', 'fixpass' ) . '</h2>';
		echo wp_kses( Fixpass_Details::render( Fixpass_Details::site_sections() ), self::dashboard_tags() );
		echo '</div>';

		echo '<form method="post" class="fixpass-card" action="' . esc_url( self::url() ) . '" data-fixpass-confirm="' . esc_attr__( 'End access now? Your account will be deleted and you will be logged out.', 'fixpass' ) . '">';
		wp_nonce_field( 'fixpass_action', 'fixpass_nonce' );
		echo '<input type="hidden" name="fixpass_do" value="support_end">';
		echo '<h2>' . esc_html__( 'Finished?', 'fixpass' ) . '</h2><p>' . esc_html__( 'End access now. Your account is deleted and you are logged out.', 'fixpass' ) . '</p>';
		echo '<button type="submit" class="button button-secondary fixpass-danger">' . esc_html__( 'End access', 'fixpass' ) . '</button></form>';
	}

	/**
	 * Tags used by the dashboard's detail tables.
	 *
	 * @return array
	 */
	private static function dashboard_tags() {
		return array(
			'h3'    => array(),
			'p'     => array(),
			'ul'    => array( 'class' => true ),
			'li'    => array(),
			'a'     => array( 'href' => true ),
			'table' => array( 'class' => true ),
			'tbody' => array(),
			'tr'    => array(),
			'th'    => array( 'scope' => true ),
			'td'    => array(),
		);
	}

	/* ------------------------------------------------------------------ Everywhere else */

	/**
	 * Tell support, on every admin screen, that they're in a temporary session.
	 */
	public static function support_notice() {
		$grant = Fixpass_Access::is_support_session() ? Fixpass_Access::active() : null;
		if ( ! $grant ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>';
		echo esc_html(
			sprintf(
				/* translators: %s: site name */
				__( 'You are logged in to %s with Fixpass.', 'fixpass' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			)
		);
		echo '</strong> ';
		echo esc_html(
			sprintf(
				/* translators: %s: time left */
				__( 'Access ends in %s.', 'fixpass' ),
				human_time_diff( time(), (int) $grant['expires_at'] )
			)
		);
		echo ' <a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Session details', 'fixpass' ) . '</a></p></div>';
	}

	/**
	 * Admin bar item for support.
	 *
	 * @param WP_Admin_Bar $bar Bar.
	 */
	public static function admin_bar( $bar ) {
		$grant = Fixpass_Access::is_support_session() ? Fixpass_Access::active() : null;
		if ( ! $grant ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'fixpass-session',
				'title' => esc_html(
					sprintf(
						/* translators: %s: time left */
						__( 'Support session · %s left', 'fixpass' ),
						human_time_diff( time(), (int) $grant['expires_at'] )
					)
				),
				'href'  => self::url(),
			)
		);
	}

	/**
	 * Users screen: a column that labels the support account, so other admins know what it is.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function users_column( $columns ) {
		$columns['fixpass'] = __( 'Fixpass', 'fixpass' );
		return $columns;
	}

	/**
	 * The column's value for a user.
	 *
	 * @param string $value   Value.
	 * @param string $column  Column.
	 * @param int    $user_id User.
	 * @return string
	 */
	public static function users_column_value( $value, $column, $user_id ) {
		if ( 'fixpass' !== $column || ! Fixpass_Access::is_support_user( (int) $user_id ) ) {
			return $value;
		}
		$grant = Fixpass_Access::active();
		if ( ! $grant ) {
			return esc_html__( 'Temporary support account (access ended)', 'fixpass' );
		}
		return '<strong>' . esc_html(
			sprintf(
				/* translators: %s: date and time */
				__( 'Temporary support account: expires %s', 'fixpass' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $grant['expires_at'] )
			)
		) . '</strong>';
	}

	/**
	 * Plugins screen link.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Open', 'fixpass' ) . '</a>' );
		return $links;
	}
}
