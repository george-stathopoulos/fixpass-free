<?php
/**
 * Admin screens: guided setup for the site owner, and the support dashboard.
 *
 * @package Fixpass
 */

defined( 'ABSPATH' ) || exit;

/**
 * One menu item. The site owner sees a guided setup (get started, give access, copy the
 * details). Support, once logged in, sees the Support session dashboard.
 */
final class Fixpass_Admin {

	const SLUG = 'fixpass';

	/**
	 * Option that remembers the last forms that were sent.
	 */
	const SENT = 'fixpass_sent';

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Documentation page hook suffix.
	 *
	 * @var string
	 */
	private static $docs_hook = '';

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
		add_action( 'wp_ajax_fixpass_link_state', array( __CLASS__, 'ajax_link_state' ) );
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
		// On multisite the network admin can keep giving access to super admins only.
		$cap        = $support ? 'read' : ( Fixpass_Access::can_give() ? 'manage_options' : 'manage_network_options' );
		self::$hook = add_menu_page(
			$support ? __( 'Support session', 'fixpass' ) : __( 'Fixpass', 'fixpass' ),
			$support ? __( 'Support session', 'fixpass' ) : __( 'Fixpass', 'fixpass' ),
			$cap,
			self::SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-sos',
			$support ? 3 : 80
		);
		add_submenu_page( self::SLUG, __( 'Fixpass', 'fixpass' ), $support ? __( 'Session', 'fixpass' ) : __( 'Setup', 'fixpass' ), $cap, self::SLUG, array( __CLASS__, 'render' ) );
		self::$docs_hook = add_submenu_page( self::SLUG, __( 'Fixpass documentation', 'fixpass' ), __( 'Documentation', 'fixpass' ), $cap, Fixpass_Docs::SLUG, array( 'Fixpass_Docs', 'render' ) );
		add_action( 'load-' . self::$hook, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Enqueue the page's styles and script.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( $hook !== self::$hook && $hook !== self::$docs_hook ) {
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
		if ( ! Fixpass_Access::can_give() || Fixpass_Access::is_support_user( get_current_user_id() ) ) {
			return;
		}

		// A form that is sent again (the page was reloaded and the browser re-sent it) does nothing the
		// second time. Otherwise reloading would replace the link you just copied.
		if ( in_array( $do, array( 'give', 'link', 'link_only' ), true ) && self::already_sent() ) {
			self::$result = array( 'repeat' => true );
			return;
		}

		if ( 'give' === $do ) {
			$hours        = isset( $_POST['hours'] ) ? absint( $_POST['hours'] ) : Fixpass_Access::DEFAULT_HOURS;
			$note         = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
			$out          = Fixpass_Access::create( $hours, ! empty( $_POST['cleanup'] ), $note );
			self::$result = is_wp_error( $out ) ? array( 'error' => $out ) : self::copy_result( $out['grant'], $out['url'] );
		} elseif ( 'link' === $do ) {
			$grant = Fixpass_Access::active();
			$url   = Fixpass_Access::new_link();
			if ( ! $grant || is_wp_error( $url ) ) {
				self::$result = array( 'error' => is_wp_error( $url ) ? $url : new WP_Error( 'fixpass_inactive', __( 'Support access is not active.', 'fixpass' ) ) );
			} else {
				self::$result = self::copy_result( $grant, $url );
			}
		} elseif ( 'link_only' === $do ) {
			$url          = Fixpass_Access::new_link();
			self::$result = is_wp_error( $url ) ? array( 'error' => $url ) : array(
				'url'       => $url,
				'only_link' => true,
			);
		} elseif ( 'end' === $do ) {
			Fixpass_Access::revoke( 'revoked' );
			wp_safe_redirect( self::url() );
			exit;
		}
	}

	/**
	 * AJAX: has the login link on the owner's screen been replaced or used? Lets the copy
	 * buttons warn instead of handing out a link that no longer works.
	 */
	public static function ajax_link_state() {
		check_ajax_referer( 'fixpass_link_state', 'nonce' );
		if ( ! Fixpass_Access::can_give() || Fixpass_Access::is_support_user( get_current_user_id() ) ) {
			wp_send_json_error( null, 403 );
		}
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		wp_send_json_success( array( 'state' => Fixpass_Access::link_state( $token ) ) );
	}

	/**
	 * Whether this exact form was already handled. Each form carries a random ID; the last few are
	 * remembered.
	 *
	 * @return bool
	 */
	private static function already_sent() {
		$id = isset( $_POST['fixpass_rid'] ) ? substr( sanitize_key( wp_unslash( $_POST['fixpass_rid'] ) ), 0, 20 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in handle().
		if ( '' === $id ) {
			return false;
		}
		$seen = (array) get_option( self::SENT, array() );
		if ( in_array( $id, $seen, true ) ) {
			return true;
		}
		$seen[] = $id;
		update_option( self::SENT, array_slice( $seen, -10 ), false );
		return false;
	}

	/**
	 * The hidden field that tells forms apart. See already_sent().
	 */
	private static function rid_field() {
		echo '<input type="hidden" name="fixpass_rid" value="' . esc_attr( strtolower( wp_generate_password( 20, false ) ) ) . '">';
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
			'message' => Fixpass_Details::message( $grant, $url ),
			'url'     => $url,
		);
	}

	/**
	 * The page.
	 */
	public static function render() {
		$support = Fixpass_Access::is_support_user( get_current_user_id() );
		echo '<div class="wrap fixpass"><h1>';
		echo esc_html( $support ? __( 'Support session', 'fixpass' ) : __( 'Fixpass', 'fixpass' ) );
		echo '</h1>';
		if ( $support ) {
			self::render_support();
		} elseif ( Fixpass_Access::can_give() ) {
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
		if ( ! empty( self::$result['repeat'] ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'This page was sent again (for example by reloading it), so nothing was changed. The link you copied before still works. To get a new link, use the buttons below.', 'fixpass' ) . '</p></div>';
		}
		if ( isset( self::$result['url'] ) ) {
			self::render_copy( self::$result );
			return;
		}
		if ( Fixpass_Access::active() ) {
			self::render_status();
			return;
		}
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'start'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: picks the screen.
		if ( 'access' === $step ) {
			self::render_access_step();
		} else {
			self::render_start();
		}
	}

	/**
	 * The three steps, with the current one marked.
	 *
	 * @param int $current 1-3.
	 */
	private static function steps( $current ) {
		$labels = array(
			1 => __( 'Get started', 'fixpass' ),
			2 => __( 'Give access', 'fixpass' ),
			3 => __( 'Copy the details', 'fixpass' ),
		);
		echo '<ol class="fixpass-steps">';
		foreach ( $labels as $n => $label ) {
			$class = $n === $current ? 'is-current' : ( $n < $current ? 'is-done' : '' );
			echo '<li class="' . esc_attr( $class ) . '"><span>' . (int) $n . '</span> ' . esc_html( $label ) . '</li>';
		}
		echo '</ol>';
	}

	/**
	 * Step 1: what is about to happen, and what Fixpass captures.
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
		echo '<div class="fixpass-card"><h2>' . esc_html__( 'What your support team gets, automatically', 'fixpass' ) . '</h2>';
		echo '<ul class="fixpass-list">';
		echo '<li>' . esc_html__( 'WordPress, PHP and database versions, and how your site is set up.', 'fixpass' ) . '</li>';
		echo '<li>' . esc_html__( 'Your plugins and themes, with versions and waiting updates.', 'fixpass' ) . '</li>';
		echo '<li>' . esc_html__( 'The result of WordPress’s own Site Health tests, when they have run.', 'fixpass' ) . '</li>';
		echo '<li>' . esc_html__( 'What you tell them is wrong.', 'fixpass' ) . '</li></ul>';
		echo '<p class="description">' . esc_html__( 'Passwords, keys, users, content and customer data are never included. Nothing is sent anywhere: you copy the details and send them yourself.', 'fixpass' ) . '</p>';
		echo '<p><a class="button button-primary button-hero" href="' . esc_url( self::url( array( 'step' => 'access' ) ) ) . '">' . esc_html__( 'Start', 'fixpass' ) . '</a></p></div>';
		echo '<p><a href="' . esc_url( Fixpass_Docs::url() ) . '">' . esc_html__( 'Read the documentation', 'fixpass' ) . '</a></p>';
	}

	/**
	 * Step 2: describe the problem, choose how long, and give access.
	 */
	private static function render_access_step() {
		self::steps( 2 );
		echo '<form method="post" class="fixpass-card" action="' . esc_url( self::url() ) . '">';
		wp_nonce_field( 'fixpass_action', 'fixpass_nonce' );
		echo '<input type="hidden" name="fixpass_do" value="give">';
		self::rid_field();
		echo '<h2>' . esc_html__( 'Give access', 'fixpass' ) . '</h2>';
		echo '<p><label for="fixpass-note"><strong>' . esc_html__( 'What’s wrong? (optional)', 'fixpass' ) . '</strong></label><br>';
		echo '<textarea id="fixpass-note" name="note" rows="4" maxlength="1000" class="large-text" placeholder="' . esc_attr__( 'For example: the checkout page shows an error after I click Pay.', 'fixpass' ) . '"></textarea><br>';
		echo '<span class="description">' . esc_html__( 'Don’t put passwords here.', 'fixpass' ) . '</span></p>';
		echo '<p><label for="fixpass-hours"><strong>' . esc_html__( 'How long should access last?', 'fixpass' ) . '</strong></label><br><select id="fixpass-hours" name="hours">';
		foreach ( Fixpass_Access::durations() as $hours => $label ) {
			echo '<option value="' . esc_attr( $hours ) . '"' . selected( Fixpass_Access::DEFAULT_HOURS, $hours, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		if ( Fixpass_Access::can_self_remove() ) {
			$label = is_multisite() ? __( 'Switch Fixpass off for this site when access ends', 'fixpass' ) : __( 'Delete Fixpass when access ends', 'fixpass' );
			$help  = is_multisite()
				? __( 'Fixpass deactivates itself on this site and removes what it stored here. The plugin files stay, because other sites on the network may use them.', 'fixpass' )
				: __( 'Fixpass deactivates and deletes itself, with everything it stored. Without this, Fixpass stays installed and only the support account is deleted.', 'fixpass' );
			echo '<p><label><input type="checkbox" name="cleanup" value="1"> ' . esc_html( $label ) . '</label><br><span class="description">' . esc_html( $help ) . '</span></p>';
		}
		echo '<ul class="fixpass-list"><li>' . esc_html__( 'Support gets an administrator account with a login link instead of a password.', 'fixpass' ) . '</li>';
		echo '<li>' . esc_html__( 'The link works once. You can make a new one at any time.', 'fixpass' ) . '</li>';
		echo '<li>' . esc_html__( 'Support cannot manage users or install themes, and cannot remove Fixpass.', 'fixpass' ) . '</li>';
		echo '<li>' . esc_html__( 'When access ends, the support account is deleted automatically.', 'fixpass' ) . '</li></ul>';
		echo '<p><button type="submit" class="button button-primary button-hero">' . esc_html__( 'Give access', 'fixpass' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Step 3: copy the details for support.
	 *
	 * @param array $result { message, url }.
	 */
	private static function render_copy( array $result ) {
		self::steps( 3 );
		$grant = Fixpass_Access::active();
		echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Support access is on. Send your support team what they need to log in.', 'fixpass' ) . '</p></div>';
		echo '<div class="fixpass-card"><h2>' . esc_html__( 'Send this to your support team', 'fixpass' ) . '</h2>';
		if ( $grant ) {
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: %s: date and time */
					__( 'Access ends on %s. The link works once.', 'fixpass' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $grant['expires_at'] )
				)
			) . '</p>';
		}
		$copied    = esc_attr__( 'Copied!', 'fixpass' );
		$failed    = esc_attr__( 'Copy failed: select the text and copy it yourself.', 'fixpass' );
		$only_link = ! empty( $result['only_link'] );

		echo '<div class="notice notice-warning inline fixpass-stale" hidden><p><span class="fixpass-stale__text"></span> <a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Make a new link', 'fixpass' ) . '</a></p></div>';
		printf(
			'<div class="fixpass-actions" data-ajax="%1$s" data-nonce="%2$s" data-used="%3$s" data-replaced="%4$s" data-ended="%5$s">',
			esc_url( admin_url( 'admin-ajax.php' ) ),
			esc_attr( wp_create_nonce( 'fixpass_link_state' ) ),
			esc_attr__( 'Support has already used this link. If they need to log in again, make a new link.', 'fixpass' ),
			esc_attr__( 'This link was replaced by a newer one and no longer works.', 'fixpass' ),
			esc_attr__( 'Support access has ended.', 'fixpass' )
		);
		if ( ! $only_link ) {
			echo '<button type="button" class="button button-primary button-hero" data-fixpass-copy="message" data-copied="' . $copied . '" data-failed="' . $failed . '">' . esc_html__( 'Copy full message', 'fixpass' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		}
		echo '<button type="button" class="button button-hero ' . ( $only_link ? 'button-primary' : 'button-secondary' ) . '" data-fixpass-copy="link" data-copied="' . $copied . '" data-failed="' . $failed . '">' . esc_html__( 'Copy login link', 'fixpass' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		echo '</div>';
		echo '<p class="description">' . esc_html(
			$only_link
				? __( 'A new login link is ready. The previous link no longer works.', 'fixpass' )
				: __( 'Full message: the login link plus everything support would otherwise ask you for. Login link: only the single-use link, for when support already has the details.', 'fixpass' )
		) . '</p>';
		// Off-screen, outside the disclosure, so they can still be selected for copying.
		echo '<input type="text" id="fixpass-link" class="fixpass-text" readonly value="' . esc_attr( $result['url'] ) . '" aria-label="' . esc_attr__( 'Login link', 'fixpass' ) . '" tabindex="-1">';
		echo '<p class="fixpass-copy-status" role="status"></p>';

		if ( ! $only_link ) {
			echo '<details class="fixpass-disclosure"><summary>' . esc_html__( 'Review exactly what the full message contains', 'fixpass' ) . '</summary>';
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: %s: list of section names, for example "Access, Site, Active plugins" */
					__( 'This is the message that is copied, section by section: %s. Nothing else is included, and passwords, keys, users and content never are.', 'fixpass' ),
					implode( ', ', $result['message']['titles'] )
				)
			) . '</p>';
			echo '<div id="fixpass-html" class="fixpass-preview">';
			add_filter( 'safe_style_css', array( __CLASS__, 'allow_email_css' ) );
			echo wp_kses( $result['message']['html'], self::email_tags() );
			remove_filter( 'safe_style_css', array( __CLASS__, 'allow_email_css' ) );
			echo '</div></details>';
			echo '<textarea id="fixpass-text" class="fixpass-text" readonly rows="6" tabindex="-1" aria-label="' . esc_attr__( 'Plain text version', 'fixpass' ) . '">' . esc_textarea( $result['message']['text'] ) . '</textarea>';
		}
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
			echo '<p>' . esc_html( is_multisite() ? __( 'Fixpass will also switch itself off for this site when access ends.', 'fixpass' ) : __( 'Fixpass will also delete itself when access ends.', 'fixpass' ) ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( self::url() ) . '" class="fixpass-inline">';
		wp_nonce_field( 'fixpass_action', 'fixpass_nonce' );
		echo '<input type="hidden" name="fixpass_do" value="link">';
		self::rid_field();
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Copy full message with a new link', 'fixpass' ) . '</button>';
		echo '</form> ';
		echo '<form method="post" action="' . esc_url( self::url() ) . '" class="fixpass-inline">';
		wp_nonce_field( 'fixpass_action', 'fixpass_nonce' );
		echo '<input type="hidden" name="fixpass_do" value="link_only">';
		self::rid_field();
		echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Copy new login link', 'fixpass' ) . '</button>';
		echo '</form> ';
		echo '<form method="post" action="' . esc_url( self::url() ) . '" class="fixpass-inline" data-fixpass-confirm="' . esc_attr__( 'End support access now? The support account will be deleted.', 'fixpass' ) . '">';
		wp_nonce_field( 'fixpass_action', 'fixpass_nonce' );
		echo '<input type="hidden" name="fixpass_do" value="end">';
		echo '<button type="submit" class="button button-secondary fixpass-danger">' . esc_html__( 'End access now', 'fixpass' ) . '</button>';
		echo '</form>';
		echo '<p class="description">' . esc_html__( 'A new link replaces the old one. The old link stops working. “Copy full message” includes the site details; “Copy new login link” is just the link.', 'fixpass' ) . '</p></div>';
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
	 * The support dashboard: the problem, the site details and a way to end the session.
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

		echo '<div class="fixpass-card"><h2>' . esc_html__( 'The problem', 'fixpass' ) . '</h2>';
		$problem = Fixpass_Details::problem_sections( $grant );
		if ( $problem ) {
			echo '<p class="fixpass-note">' . esc_html( $grant['note'] ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'The site owner didn’t describe the problem. Ask them what is wrong.', 'fixpass' ) . '</p>';
		}
		echo '</div>';

		echo '<div class="fixpass-card"><h2>' . esc_html__( 'Site details, captured automatically', 'fixpass' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Open a section to see it. These are read live from the site.', 'fixpass' ) . '</p>';
		echo wp_kses( Fixpass_Details::render( Fixpass_Details::site_sections(), true ), self::dashboard_tags() );
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
			'h3'      => array(),
			'p'       => array(),
			'ul'      => array( 'class' => true ),
			'li'      => array(),
			'a'       => array( 'href' => true ),
			'table'   => array( 'class' => true ),
			'tbody'   => array(),
			'tr'      => array(),
			'details' => array(
				'class' => true,
				'open'  => true,
			),
			'summary' => array(),
			'span'    => array( 'class' => true ),
			'th'      => array( 'scope' => true ),
			'td'      => array(),
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
		if ( Fixpass_Access::get() ) {
			$columns['fixpass'] = __( 'Fixpass', 'fixpass' );
		}
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
	 * Plugins screen links.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Open', 'fixpass' ) . '</a>',
			'<a href="' . esc_url( Fixpass_Docs::url() ) . '">' . esc_html__( 'Documentation', 'fixpass' ) . '</a>'
		);
		return $links;
	}
}
