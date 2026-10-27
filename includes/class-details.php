<?php
/**
 * The support details: what the owner copies, and what support sees on their dashboard.
 *
 * @package Fixpass
 */

defined( 'ABSPATH' ) || exit;

/**
 * Captures the context of the site automatically and builds it as sections of label => value
 * rows. The same sections are shown on the support dashboard and turned into HTML (pastes
 * formatted into an email) and plain text (pastes anywhere) for the owner's "Copy support
 * details" button. Nothing here makes a network request.
 */
final class Fixpass_Details {

	/**
	 * Everything captured about the site: configuration, health, plugins and themes.
	 *
	 * @return array[] Sections: [ title, rows ], where rows is label => value or a list.
	 */
	public static function site_sections() {
		$ext      = self::extensions();
		$sections = array( array( __( 'Site', 'fixpass' ), self::environment() ) );
		$health   = self::site_health();
		if ( $health ) {
			$sections[] = array( __( 'Site Health', 'fixpass' ), $health );
		}
		return array_merge(
			$sections,
			array(
				array( __( 'Active plugins', 'fixpass' ), $ext['active_plugins'] ),
				array( __( 'Inactive plugins', 'fixpass' ), $ext['inactive_plugins'] ),
				array( __( 'Must-use plugins and drop-ins', 'fixpass' ), $ext['special'] ),
				array( __( 'Active theme', 'fixpass' ), $ext['active_theme'] ),
				array( __( 'Other installed themes', 'fixpass' ), $ext['other_themes'] ),
			)
		);
	}

	/**
	 * The owner's description of the problem, if they wrote one.
	 *
	 * @param array $grant The access.
	 * @return array[] Zero or one section.
	 */
	public static function problem_sections( array $grant ) {
		$note = trim( (string) ( $grant['note'] ?? '' ) );
		return '' === $note ? array() : array( array( __( 'The problem, in the site owner’s words', 'fixpass' ), array( $note ) ) );
	}

	/**
	 * The message the owner copies: login link, access times, the problem and the site context.
	 *
	 * @param array  $grant The access.
	 * @param string $url   The single-use login link.
	 * @return array { subject, html, text }
	 */
	public static function message( array $grant, $url ) {
		$site   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		$access   = array(
			array(
				__( 'Access', 'fixpass' ),
				array(
					__( 'Secure login link (works once, no password needed)', 'fixpass' ) => $url,
					__( 'Granted', 'fixpass' )      => wp_date( $format, (int) $grant['created_at'] ),
					__( 'Expires', 'fixpass' )      => wp_date( $format, (int) $grant['expires_at'] ),
					__( 'Access level', 'fixpass' ) => __( 'Administrator (cannot manage users or install themes)', 'fixpass' ),
					__( 'Details captured', 'fixpass' ) => wp_date( $format ),
				),
			),
		);
		$sections = array_merge( $access, self::problem_sections( $grant ), self::site_sections() );
		/* translators: %s: site name */
		$intro = sprintf( __( 'Support access details for %s. Please share this with your support team.', 'fixpass' ), $site );
		$after = __( 'After logging in, the Support session page shows these details.', 'fixpass' );
		return array(
			/* translators: %s: site name */
			'subject' => sprintf( __( 'Support access for %s', 'fixpass' ), $site ),
			'html'    => self::html( $intro, $url, $sections, $after ),
			'text'    => self::text( $intro, $sections, $after ),
		);
	}

	/**
	 * HTML for a list of sections, without inline styling (the support dashboard).
	 *
	 * @param array[] $sections Sections.
	 * @return string
	 */
	public static function render( array $sections ) {
		$out = '';
		foreach ( $sections as $section ) {
			$out .= '<h3>' . esc_html( $section[0] ) . '</h3>';
			$rows = $section[1];
			if ( ! $rows ) {
				$out .= '<p>' . esc_html__( 'None', 'fixpass' ) . '</p>';
			} elseif ( self::is_list( $rows ) ) {
				$out .= '<ul class="fixpass-list">';
				foreach ( $rows as $item ) {
					$out .= '<li>' . esc_html( $item ) . '</li>';
				}
				$out .= '</ul>';
			} else {
				$out .= '<table class="widefat striped fixpass-table"><tbody>';
				foreach ( $rows as $label => $value ) {
					$out .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . self::cell( $value ) . '</td></tr>';
				}
				$out .= '</tbody></table>';
			}
		}
		return $out;
	}

	/**
	 * HTML, styled inline so it survives being pasted into an email.
	 *
	 * @param string  $intro    First line.
	 * @param string  $url      Login link.
	 * @param array[] $sections Sections.
	 * @param string  $after    Last line.
	 * @return string
	 */
	private static function html( $intro, $url, array $sections, $after ) {
		$h2  = 'style="font:600 15px/1.3 Arial,sans-serif;margin:20px 0 6px;color:#1a1a1a"';
		$p   = 'style="font:14px/1.5 Arial,sans-serif;margin:0 0 8px;color:#1a1a1a"';
		$td  = 'style="font:13px/1.5 Arial,sans-serif;padding:2px 12px 2px 0;vertical-align:top;color:#1a1a1a;word-break:break-word"';
		$th  = 'style="font:13px/1.5 Arial,sans-serif;padding:2px 12px 2px 0;vertical-align:top;color:#666;text-align:left;white-space:nowrap"';
		$out = '<div><p ' . $p . '>' . esc_html( $intro ) . '</p>';
		$out .= '<p ' . $p . '><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:9px 18px;background:#2271b1;color:#fff;border-radius:4px;text-decoration:none;font:600 14px Arial,sans-serif">' . esc_html__( 'Log in to the site', 'fixpass' ) . '</a></p>';
		foreach ( $sections as $section ) {
			$out .= '<h2 ' . $h2 . '>' . esc_html( $section[0] ) . '</h2>';
			$rows = $section[1];
			if ( ! $rows ) {
				$out .= '<p ' . $p . '>' . esc_html__( 'None', 'fixpass' ) . '</p>';
			} elseif ( self::is_list( $rows ) ) {
				$out .= '<ul style="margin:0;padding-left:18px">';
				foreach ( $rows as $item ) {
					$out .= '<li style="font:13px/1.5 Arial,sans-serif;color:#1a1a1a">' . esc_html( $item ) . '</li>';
				}
				$out .= '</ul>';
			} else {
				$out .= '<table role="presentation" style="border-collapse:collapse">';
				foreach ( $rows as $label => $value ) {
					$out .= '<tr><th ' . $th . '>' . esc_html( $label ) . '</th><td ' . $td . '>' . self::cell( $value ) . '</td></tr>';
				}
				$out .= '</table>';
			}
		}
		$out .= '<p style="font:12px/1.5 Arial,sans-serif;color:#888;margin-top:20px">' . esc_html( $after ) . '</p>';
		return $out . '</div>';
	}

	/**
	 * Plain text.
	 *
	 * @param string  $intro    First line.
	 * @param array[] $sections Sections.
	 * @param string  $after    Last line.
	 * @return string
	 */
	private static function text( $intro, array $sections, $after ) {
		$lines = array( $intro );
		foreach ( $sections as $section ) {
			$lines[] = '';
			$lines[] = '== ' . $section[0] . ' ==';
			$rows    = $section[1];
			if ( ! $rows ) {
				$lines[] = '- ' . __( 'None', 'fixpass' );
			} elseif ( self::is_list( $rows ) ) {
				foreach ( $rows as $item ) {
					$lines[] = '- ' . $item;
				}
			} else {
				foreach ( $rows as $label => $value ) {
					$lines[] = $label . ': ' . ( '' === (string) $value ? __( 'not available', 'fixpass' ) : $value );
				}
			}
		}
		$lines[] = '';
		$lines[] = $after;
		return implode( "\n", $lines );
	}

	/**
	 * Whether rows are a plain list (not label => value).
	 *
	 * @param array $rows Rows.
	 * @return bool
	 */
	private static function is_list( array $rows ) {
		return array_keys( $rows ) === range( 0, count( $rows ) - 1 );
	}

	/**
	 * A table cell: addresses become links.
	 *
	 * @param mixed $value Value.
	 * @return string Escaped HTML.
	 */
	private static function cell( $value ) {
		$value = '' === (string) $value ? __( 'not available', 'fixpass' ) : (string) $value;
		return 0 === strpos( $value, 'http' ) ? '<a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>' : esc_html( $value );
	}

	/**
	 * Versions and configuration that matter for support.
	 *
	 * @return array Label => value.
	 */
	private static function environment() {
		global $wpdb, $wp_version;
		$yes  = __( 'yes', 'fixpass' );
		$no   = __( 'no', 'fixpass' );
		$flag = static function ( $name ) use ( $yes, $no ) {
			return defined( $name ) && constant( $name ) ? $yes : $no;
		};

		$overdue = 0;
		$cron    = _get_cron_array();
		if ( is_array( $cron ) ) {
			foreach ( $cron as $time => $hooks ) {
				if ( $time < time() - 15 * MINUTE_IN_SECONDS ) {
					$overdue += count( $hooks );
				}
			}
		}

		return array(
			__( 'Site address', 'fixpass' )           => home_url( '/' ),
			__( 'WordPress version', 'fixpass' )      => (string) $wp_version,
			__( 'PHP version', 'fixpass' )            => PHP_VERSION,
			__( 'PHP extensions', 'fixpass' )         => implode( ', ', array_intersect( array( 'curl', 'dom', 'exif', 'fileinfo', 'gd', 'imagick', 'intl', 'mbstring', 'mysqli', 'openssl', 'sodium', 'xml', 'zip' ), get_loaded_extensions() ) ),
			__( 'Database', 'fixpass' )               => method_exists( $wpdb, 'db_server_info' ) ? (string) $wpdb->db_server_info() : (string) $wpdb->db_version(),
			__( 'Web server', 'fixpass' )             => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
			__( 'Environment type', 'fixpass' )       => wp_get_environment_type(),
			__( 'Multisite', 'fixpass' )              => is_multisite() ? $yes : $no,
			__( 'HTTPS', 'fixpass' )                  => is_ssl() || 0 === strpos( home_url(), 'https://' ) ? $yes : $no,
			__( 'Language', 'fixpass' )               => get_locale(),
			__( 'Time zone', 'fixpass' )              => wp_timezone_string(),
			__( 'Permalinks', 'fixpass' )             => (string) get_option( 'permalink_structure' ),
			__( 'PHP memory limit', 'fixpass' )       => (string) ini_get( 'memory_limit' ),
			__( 'WordPress memory limit', 'fixpass' ) => WP_MEMORY_LIMIT,
			__( 'Max execution time', 'fixpass' )     => (string) ini_get( 'max_execution_time' ),
			__( 'Upload size limit', 'fixpass' )      => size_format( wp_max_upload_size() ),
			__( 'Object cache', 'fixpass' )           => wp_using_ext_object_cache() ? $yes : $no,
			__( 'Overdue scheduled tasks', 'fixpass' ) => (string) $overdue,
			'WP_DEBUG'                                => $flag( 'WP_DEBUG' ),
			'WP_DEBUG_LOG'                            => $flag( 'WP_DEBUG_LOG' ),
			'DISABLE_WP_CRON'                         => $flag( 'DISABLE_WP_CRON' ),
			'DISALLOW_FILE_MODS'                      => $flag( 'DISALLOW_FILE_MODS' ),
		);
	}

	/**
	 * The result of WordPress's own Site Health tests, as WordPress last stored it. Nothing is
	 * run here, so the section is left out if the tests have never run.
	 *
	 * @return array Label => value.
	 */
	private static function site_health() {
		$stored = get_transient( 'health-check-site-status-result' );
		$result = is_string( $stored ) ? json_decode( $stored, true ) : null;
		if ( ! is_array( $result ) ) {
			return array();
		}
		return array(
			__( 'Critical issues', 'fixpass' )          => (string) (int) ( $result['critical'] ?? 0 ),
			__( 'Recommended improvements', 'fixpass' ) => (string) (int) ( $result['recommended'] ?? 0 ),
			__( 'Passed tests', 'fixpass' )             => (string) (int) ( $result['good'] ?? 0 ),
		);
	}

	/**
	 * Installed plugins and themes, with versions and available updates.
	 *
	 * @return array
	 */
	private static function extensions() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$out = array(
			'active_plugins'   => array(),
			'inactive_plugins' => array(),
			'special'          => array(),
			'active_theme'     => array(),
			'other_themes'     => array(),
		);
		// Cached update information only: this doesn't contact wordpress.org.
		$plugin_updates = (array) get_site_transient( 'update_plugins' );
		$theme_updates  = (array) get_site_transient( 'update_themes' );
		$update         = __( 'update available', 'fixpass' );
		foreach ( get_plugins() as $file => $plugin ) {
			$line = trim( $plugin['Name'] . ' ' . $plugin['Version'] ) . ( isset( $plugin_updates['response'][ $file ] ) ? ' (' . $update . ')' : '' );
			$out[ is_plugin_active( $file ) ? 'active_plugins' : 'inactive_plugins' ][] = $line;
		}
		foreach ( get_mu_plugins() as $plugin ) {
			$out['special'][] = trim( __( 'Must-use:', 'fixpass' ) . ' ' . $plugin['Name'] . ' ' . $plugin['Version'] );
		}
		foreach ( array_keys( get_dropins() ) as $file ) {
			$out['special'][] = __( 'Drop-in:', 'fixpass' ) . ' ' . $file;
		}
		$current = get_stylesheet();
		foreach ( wp_get_themes() as $slug => $theme ) {
			$line = trim( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ) . ( isset( $theme_updates['response'][ $slug ] ) ? ' (' . $update . ')' : '' );
			$out[ $slug === $current ? 'active_theme' : 'other_themes' ][] = $line;
		}
		return $out;
	}
}
