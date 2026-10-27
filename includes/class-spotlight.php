<?php
/**
 * Spotlight: capture the exact spot of a problem, with its context.
 *
 * @package Fixpass
 */

defined( 'ABSPATH' ) || exit;

/**
 * "Spotlight a problem" in the admin bar, on the front end and in wp-admin. The site owner clicks
 * the broken part of a page (or drags a box around it), up to 5 spots, with a note each. Each
 * spot keeps its page, the screen size and browser, and the JavaScript errors and failed
 * requests the page had. Support opens the page with the spot highlighted. Nothing is recorded
 * until the owner picks a spot, and nothing leaves the site unless the owner copies it.
 */
final class Fixpass_Spotlight {

	const OPTION = 'fixpass_spots';
	const NS     = 'fixpass/v1';
	const KEEP   = 30; // Days a spot is kept if it is never sent.
	const MAX    = 5;

	/**
	 * The toolbar icon: a spotlight casting its beam (static markup, nothing from users).
	 */
	const ICON = '<svg class="fxp-ab-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 3l6 3.5-2.5 4.3-6-3.5z" fill="currentColor"/><path d="M13.6 9.2L4 21h10.5l2.6-9.8z" fill="currentColor" opacity=".45"/><circle cx="18.6" cy="3.6" r="1.2" fill="currentColor"/></svg>';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'collector' ), 1 );
		add_action( 'admin_head', array( __CLASS__, 'collector' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Whether the current person can spotlight a problem: an administrator (not the support
	 * account), while no support access is active.
	 *
	 * @return bool
	 */
	public static function available() {
		/**
		 * Filters whether "Spotlight a problem" is offered in the toolbar.
		 *
		 * @param bool $on On.
		 */
		return (bool) apply_filters( 'fixpass_spotlight', true )
			&& is_user_logged_in()
			&& Fixpass_Access::can_give()
			&& ! Fixpass_Access::is_support_user( get_current_user_id() )
			&& ! Fixpass_Access::active();
	}

	/**
	 * Admin bar button.
	 *
	 * @param WP_Admin_Bar $bar Bar.
	 */
	public static function admin_bar( $bar ) {
		if ( ! self::available() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'     => 'fixpass-spotlight',
				'parent' => 'top-secondary',
				'title'  => self::ICON . '<span class="ab-label">' . esc_html__( 'Spotlight a problem', 'fixpass' ) . '</span>',
				'href'   => '#fixpass-spotlight',
				'meta'   => array( 'title' => __( 'Click the part of this page that has the problem, and send it to your support', 'fixpass' ) ),
			)
		);
	}

	/**
	 * Load the picking overlay (for those who can spotlight) or the highlighter (a link with
	 * ?fxp_spot=, opened by support).
	 */
	public static function assets() {
		$spot = isset( $_GET['fxp_spot'] ) ? sanitize_key( wp_unslash( $_GET['fxp_spot'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: highlights a spot.
		if ( ! self::available() && '' === $spot ) {
			return;
		}
		wp_enqueue_script( 'fixpass-spotlight', FIXPASS_URL . 'assets/spotlight.js', array(), FIXPASS_VERSION, true );
		wp_enqueue_style( 'fixpass-spotlight', FIXPASS_URL . 'assets/spotlight.css', array(), FIXPASS_VERSION );
		wp_localize_script(
			'fixpass-spotlight',
			'fixpassSpotlight',
			array(
				'can'   => self::available(),
				'pin'   => $spot,
				'api'   => esc_url_raw( rest_url( self::NS . '/' ) ),
				'nonce' => self::available() ? wp_create_nonce( 'wp_rest' ) : '',
				'back'  => self::available() ? admin_url( 'admin.php?page=' . Fixpass_Admin::SLUG . '&step=access&spots=' ) : '',
				'area'  => is_admin() ? 'admin' : 'front',
				'i18n'  => array(
					'pick'         => __( 'Click the part of the page that has the problem, or drag a box around it. Links are paused while Spotlight is on. Press Esc to cancel.', 'fixpass' ),
					'cancel'       => __( 'Cancel', 'fixpass' ),
					'guideHint'    => __( 'Click here, then click the problem on this page', 'fixpass' ),
					'guideStop'    => __( 'Hide this hint', 'fixpass' ),
					'introTitle'   => __( 'How Spotlight works', 'fixpass' ),
					'intro1'       => __( 'Click the part of the page that has the problem, or drag a box around an area.', 'fixpass' ),
					'intro2'       => __( 'Add a short note if you like. You can mark up to 5 spots, all on this page.', 'fixpass' ),
					'intro3'       => __( 'Click Continue to go back to Fixpass: your spots go with the support details.', 'fixpass' ),
					'introPause'   => __( 'While Spotlight is on, the page is paused: links, buttons and menus don’t work, so you can’t go to another page. Press Esc or Cancel to stop.', 'fixpass' ),
					'introPrivacy' => __( 'Nothing on your screen is recorded. Support opens the page itself, with your spots highlighted.', 'fixpass' ),
					'introHide'    => __( 'Don’t show this again', 'fixpass' ),
					'introStart'   => __( 'Start', 'fixpass' ),
					'title'        => __( 'Spotlight this spot', 'fixpass' ),
					'what'         => __( 'Support gets the page, the spot, your screen size and browser, and any errors on this page.', 'fixpass' ),
					'next'         => __( 'Continue', 'fixpass' ),
					'another'      => __( 'Add another spot', 'fixpass' ),
					'label'        => __( 'What’s wrong here? (optional)', 'fixpass' ),
					'labelHint'    => __( 'For example: wrong price, button does nothing', 'fixpass' ),
					/* translators: %d: number of spots */
					'count'        => __( 'Spots: %d of 5', 'fixpass' ),
					'working'      => __( 'Preparing…', 'fixpass' ),
					'failed'       => __( 'Something went wrong. Try again.', 'fixpass' ),
					'here'         => __( 'The reported spot', 'fixpass' ),
					'notFound'     => __( 'The exact element isn’t on the page any more; the box shows where it was.', 'fixpass' ),
					'pinGone'      => __( 'This highlight link has expired.', 'fixpass' ),
					'close'        => __( 'Close', 'fixpass' ),
				),
			)
		);
	}

	/**
	 * Early in the page: remember JavaScript errors and failed requests, so a spot picked later on
	 * this page includes them. Nothing is sent unless the person picks a spot.
	 */
	public static function collector() {
		if ( ! self::available() ) {
			return;
		}
		wp_print_inline_script_tag(
			'(function(){var w=window,l=w.fixpassLog={errors:[],requests:[]};function add(a,x){if(a.length<20){a.push(x);}}' .
			'w.addEventListener("error",function(e){if(e&&e.message){add(l.errors,{message:String(e.message).slice(0,300),source:String(e.filename||"").slice(0,300),line:e.lineno||0});}else if(e&&e.target&&(e.target.src||e.target.href)){add(l.requests,{url:String(e.target.src||e.target.href).slice(0,300),status:0,method:"GET"});}},true);' .
			'w.addEventListener("unhandledrejection",function(e){add(l.errors,{message:String(e&&e.reason&&(e.reason.message||e.reason)||"Unhandled promise rejection").slice(0,300),source:"",line:0});});' .
			'if(w.fetch){var f=w.fetch;w.fetch=function(i,o){var u=String(i&&i.url||i),m=(o&&o.method)||(i&&i.method)||"GET";return f.apply(this,arguments).then(function(r){if(!r.ok){add(l.requests,{url:u.slice(0,300),status:r.status,method:m});}return r;},function(e){add(l.requests,{url:u.slice(0,300),status:0,method:m});throw e;});};}' .
			'var X=w.XMLHttpRequest&&w.XMLHttpRequest.prototype;if(X){var op=X.open,se=X.send;X.open=function(m,u){this._fxp=[m,String(u)];return op.apply(this,arguments);};X.send=function(){var x=this;x.addEventListener("loadend",function(){if(x._fxp&&(x.status===0||x.status>=400)){add(l.requests,{url:x._fxp[1].slice(0,300),status:x.status,method:x._fxp[0]});}});return se.apply(this,arguments);};}})();'
		);
	}

	/**
	 * REST routes.
	 */
	public static function routes() {
		register_rest_route(
			self::NS,
			'/spots',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create' ),
				'permission_callback' => array( __CLASS__, 'available' ),
			)
		);
		register_rest_route(
			self::NS,
			'/spots/(?P<id>[a-z0-9]{32})',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'highlight_data' ),
				// Public on purpose: support opens the highlight link without an account until they
				// log in. The random 32-character ID is the key, and only the spot's position is returned.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Save picked spots (from the overlay).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error { id, ids }
	 */
	public static function create( WP_REST_Request $request ) {
		$params = (array) $request->get_json_params();
		$spots  = isset( $params['spots'] ) && is_array( $params['spots'] ) ? array_slice( $params['spots'], 0, self::MAX ) : array();
		if ( ! $spots ) {
			return new WP_Error( 'fixpass_spot', __( 'Pick a spot first.', 'fixpass' ), array( 'status' => 400 ) );
		}
		$ids = array();
		foreach ( $spots as $spot ) {
			$ids[] = self::store( (array) $spot );
		}
		return array(
			'id'  => $ids[0],
			'ids' => $ids,
		);
	}

	/**
	 * The position of a spot, to highlight it (nothing else).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function highlight_data( WP_REST_Request $request ) {
		$spot = self::all()[ (string) $request['id'] ] ?? null;
		if ( ! $spot ) {
			return new WP_Error( 'fixpass_spot', __( 'This highlight link has expired.', 'fixpass' ), array( 'status' => 404 ) );
		}
		return array(
			'selector' => $spot['selector'],
			'kind'     => $spot['kind'],
			'box'      => $spot['box'],
			'path'     => $spot['path'],
			'rect'     => $spot['rect'],
			'viewport' => $spot['viewport'],
		);
	}

	/**
	 * Keep one spot.
	 *
	 * @param array $raw Spot from the browser.
	 * @return string Spot ID.
	 */
	private static function store( array $raw ) {
		$spot       = self::clean( $raw );
		$spot['id'] = strtolower( wp_generate_password( 32, false ) );
		$spots      = array_filter(
			self::all(),
			static function ( $s ) {
				return (int) $s['created_at'] > time() - self::KEEP * DAY_IN_SECONDS;
			}
		);

		$spot['created_at'] = time();
		$spots[ $spot['id'] ] = $spot;
		update_option( self::OPTION, array_slice( $spots, -50, null, true ), false );
		return $spot['id'];
	}

	/**
	 * A stored spot, for the support details and the support dashboard.
	 *
	 * @param string $id Spot ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		return self::all()[ sanitize_key( (string) $id ) ] ?? null;
	}

	/**
	 * Spots by ID, in the order given. Unknown IDs are skipped.
	 *
	 * @param string[] $ids IDs.
	 * @return array[]
	 */
	public static function find( array $ids ) {
		$out = array();
		foreach ( array_slice( $ids, 0, self::MAX ) as $id ) {
			$spot = self::get( $id );
			if ( $spot ) {
				$out[] = $spot;
			}
		}
		return $out;
	}

	/**
	 * The link that opens a spot's page with the spot highlighted.
	 *
	 * @param array $spot Spot.
	 * @return string
	 */
	public static function highlight_url( array $spot ) {
		return add_query_arg( 'fxp_spot', $spot['id'], $spot['url'] );
	}

	/**
	 * All spots by ID.
	 *
	 * @return array
	 */
	private static function all() {
		$spots = get_option( self::OPTION, array() );
		return is_array( $spots ) ? $spots : array();
	}

	/**
	 * Clean a spot from the browser.
	 *
	 * @param array $in Raw.
	 * @return array
	 */
	private static function clean( array $in ) {
		$num  = static function ( $v ) {
			return (int) round( (float) $v );
		};
		$rect = (array) ( $in['rect'] ?? array() );
		$view = (array) ( $in['viewport'] ?? array() );
		$list = static function ( $items, array $keys ) {
			$out = array();
			foreach ( array_slice( (array) $items, 0, 20 ) as $item ) {
				$row = array();
				foreach ( $keys as $k => $type ) {
					$v         = ( (array) $item )[ $k ] ?? '';
					$row[ $k ] = 'int' === $type ? (int) $v : substr( Fixpass_Redactor::text( sanitize_text_field( (string) $v ) ), 0, 300 );
				}
				$out[] = $row;
			}
			return $out;
		};
		$kind = 'area' === ( $in['kind'] ?? '' ) ? 'area' : 'element';
		return array(
			'url'      => esc_url_raw( (string) ( $in['url'] ?? '' ) ),
			'title'    => substr( sanitize_text_field( (string) ( $in['title'] ?? '' ) ), 0, 200 ),
			'selector' => substr( sanitize_text_field( (string) ( $in['selector'] ?? '' ) ), 0, 500 ),
			// element: the element itself · area: a box drawn on the element.
			'kind'     => $kind,
			'box'      => 'area' === $kind && is_array( $in['box'] ?? null ) ? self::clean_box( $in['box'] ) : null,
			// How to find the element again, step by step from <body> (see spotlight.js).
			'path'     => self::clean_path( $in['path'] ?? array() ),
			'text'     => substr( Fixpass_Redactor::text( sanitize_text_field( (string) ( $in['text'] ?? '' ) ) ), 0, 200 ),
			// The owner's own words for this spot ("this button", "wrong price").
			'label'    => substr( sanitize_text_field( (string) ( $in['label'] ?? '' ) ), 0, 80 ),
			'rect'     => array(
				'x' => $num( $rect['x'] ?? 0 ),
				'y' => $num( $rect['y'] ?? 0 ),
				'w' => max( 0, $num( $rect['w'] ?? 0 ) ),
				'h' => max( 0, $num( $rect['h'] ?? 0 ) ),
			),
			'viewport' => array(
				'w'   => max( 0, $num( $view['w'] ?? 0 ) ),
				'h'   => max( 0, $num( $view['h'] ?? 0 ) ),
				'dpr' => round( max( 0, min( 8, (float) ( $view['dpr'] ?? 1 ) ) ), 2 ),
			),
			'browser'  => substr( sanitize_text_field( (string) ( $in['browser'] ?? '' ) ), 0, 300 ),
			'area'     => 'admin' === ( $in['area'] ?? '' ) ? 'admin' : 'front',
			'errors'   => $list(
				$in['errors'] ?? array(),
				array(
					'message' => 'text',
					'source'  => 'text',
					'line'    => 'int',
				)
			),
			'requests' => $list(
				$in['requests'] ?? array(),
				array(
					'url'    => 'text',
					'status' => 'int',
					'method' => 'text',
				)
			),
		);
	}

	/**
	 * Steps to an element: { id } or { t: tag, c: classes, i: position }.
	 *
	 * @param mixed $path Raw.
	 * @return array[]
	 */
	private static function clean_path( $path ) {
		$out = array();
		foreach ( array_slice( (array) $path, 0, 10 ) as $step ) {
			$step = (array) $step;
			if ( ! empty( $step['id'] ) && preg_match( '/^[A-Za-z][\w-]{0,99}$/', (string) $step['id'] ) ) {
				$out[] = array( 'id' => (string) $step['id'] );
				continue;
			}
			if ( empty( $step['t'] ) || ! preg_match( '/^[a-z][a-z0-9-]{0,30}$/', (string) $step['t'] ) ) {
				return array();
			}
			$out[] = array(
				't' => (string) $step['t'],
				'c' => array_values(
					array_filter(
						array_slice( (array) ( $step['c'] ?? array() ), 0, 2 ),
						static function ( $c ) {
							return is_string( $c ) && preg_match( '/^[A-Za-z][\w-]{0,99}$/', $c );
						}
					)
				),
				'i' => max( 0, min( 500, (int) ( $step['i'] ?? 0 ) ) ),
			);
		}
		return $out;
	}

	/**
	 * An area box: pixel offsets from an element.
	 *
	 * @param array $box Raw { x, y, w, h, px }.
	 * @return array
	 */
	private static function clean_box( array $box ) {
		$out = array( 'px' => true );
		foreach ( array( 'x', 'y', 'w', 'h' ) as $k ) {
			$out[ $k ] = max( -20000, min( 20000, (int) ( $box[ $k ] ?? 0 ) ) );
		}
		return $out;
	}
}
