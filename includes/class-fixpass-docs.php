<?php
/**
 * In-plugin documentation.
 *
 * @package Fixpass
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Documentation page: how Fixpass works, what it captures and stores, what support can and
 * can't do, multisite, troubleshooting and the hooks it offers. Plain pages, one per tab, no
 * scripts.
 */
final class Fixpass_Docs {

	const SLUG = 'fixpass-docs';

	/**
	 * The tabs, in order.
	 *
	 * @return array Key => label.
	 */
	private static function tabs() {
		$tabs = array(
			'start'    => __( 'Getting started', 'fixpass' ),
			'context'  => __( 'Automatic context capture', 'fixpass' ),
			'access'   => __( 'Support access', 'fixpass' ),
			'copy'     => __( 'Copy support details', 'fixpass' ),
			'support'  => __( 'For support teams', 'fixpass' ),
			'cleanup'  => __( 'Automatic cleanup', 'fixpass' ),
			'security' => __( 'Security and privacy', 'fixpass' ),
		);
		if ( is_multisite() ) {
			$tabs['multisite'] = __( 'Multisite', 'fixpass' );
		}
		$tabs['faq']       = __( 'Troubleshooting', 'fixpass' );
		$tabs['developer'] = __( 'For developers', 'fixpass' );
		return $tabs;
	}

	/**
	 * Address of a tab.
	 *
	 * @param string $tab Tab.
	 * @return string
	 */
	public static function url( $tab = 'start' ) {
		return add_query_arg( 'tab', $tab, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	/**
	 * The page.
	 */
	public static function render() {
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'start'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: picks the tab.
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'start';

		echo '<div class="wrap fixpass"><h1>' . esc_html__( 'Fixpass documentation', 'fixpass' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Documentation sections', 'fixpass' ) . '">';
		foreach ( $tabs as $key => $label ) {
			echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( $key ) ) . '"' . ( $key === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed strings.
		}
		echo '</nav><div class="fixpass-card fixpass-docs">';
		call_user_func( array( __CLASS__, 'tab_' . $tab ) );
		echo '</div></div>';
	}

	/**
	 * Output a heading.
	 *
	 * @param string $text Text.
	 */
	private static function h( $text ) {
		echo '<h2>' . esc_html( $text ) . '</h2>';
	}

	/**
	 * Output a sub-heading.
	 *
	 * @param string $text Text.
	 */
	private static function h3( $text ) {
		echo '<h3>' . esc_html( $text ) . '</h3>';
	}

	/**
	 * Output a paragraph.
	 *
	 * @param string $text Text.
	 */
	private static function p( $text ) {
		echo '<p>' . esc_html( $text ) . '</p>';
	}

	/**
	 * Output a bulleted or numbered list.
	 *
	 * @param string[] $items   Items.
	 * @param bool     $ordered Numbered.
	 */
	private static function ul( array $items, $ordered = false ) {
		$tag = $ordered ? 'ol' : 'ul';
		echo '<' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag.
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed tag.
	}

	/**
	 * Output a table of two columns.
	 *
	 * @param array $rows Label => text.
	 */
	private static function table( array $rows ) {
		echo '<table class="widefat striped fixpass-table"><tbody>';
		foreach ( $rows as $label => $text ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $text ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* ---------------------------------------------------------------- Tabs */

	/**
	 * Getting started.
	 */
	private static function tab_start() {
		self::h( __( 'Getting started', 'fixpass' ) );
		self::p( __( 'Fixpass lets you give your support team temporary access to your site without sharing a password, and gives them everything they need to start work. It runs entirely on your site: no account, no outside service.', 'fixpass' ) );
		self::h3( __( 'The three steps', 'fixpass' ) );
		self::ul(
			array(
				__( 'Get started. Open Fixpass in the admin menu. It shows what your support team will get automatically. Click Start.', 'fixpass' ),
				__( 'Give access. Describe what is wrong in a sentence or two (optional), choose how long access lasts (1 day to 1 week; 3 days is the default), and click “Give access”. Fixpass creates a temporary support account and a single-use login link.', 'fixpass' ),
				__( 'Send it to support. Click “Copy full message” and paste it into an email or support ticket, or click “Copy login link” to send only the link. You can open “Review exactly what the full message contains” first. That is all support needs to log in and start work.', 'fixpass' ),
			),
			true
		);
		self::h3( __( 'After you have sent the details', 'fixpass' ) );
		self::p( __( 'The Fixpass page shows whether support has logged in. You get an email when they do. At any time you can end access now, or make a new login link if the first one was used. When the time is up, access ends by itself and the support account is deleted.', 'fixpass' ) );
		self::h3( __( 'Who should use it', 'fixpass' ) );
		self::p( __( 'Anyone who administers a WordPress site and wants help from a developer, an agency, a host, or a plugin or theme vendor. You need to be an administrator to give access.', 'fixpass' ) );
	}

	/**
	 * Automatic context capture.
	 */
	private static function tab_context() {
		self::h( __( 'Automatic context capture', 'fixpass' ) );
		self::p( __( 'The usual back-and-forth of “which WordPress version?”, “which plugins?” and “what PHP are you on?” is skipped. Fixpass reads this from your site by itself, at the moment you copy the details, so it is always current. You don’t have to find or collect anything.', 'fixpass' ) );
		self::h3( __( 'What is captured', 'fixpass' ) );
		self::table(
			array(
				__( 'Site', 'fixpass' )             => __( 'Site address; WordPress, PHP and database versions; PHP extensions; web server; environment type; multisite; HTTPS; language and time zone; permalinks; memory, execution time and upload limits; object cache; scheduled tasks that are overdue; and whether WP_DEBUG, WP_DEBUG_LOG, DISABLE_WP_CRON and DISALLOW_FILE_MODS are on.', 'fixpass' ),
				__( 'Site Health', 'fixpass' )      => __( 'How many critical issues, recommended improvements and passed tests WordPress’s own Site Health last found. Fixpass runs no tests: it only reads the result WordPress stored, and leaves the section out if the tests have never run.', 'fixpass' ),
				__( 'Plugins', 'fixpass' )          => __( 'Name and version of every installed plugin, whether it is active, and whether an update is waiting.', 'fixpass' ),
				__( 'Must-use plugins and drop-ins', 'fixpass' ) => __( 'Must-use plugins and drop-ins such as object-cache.php and advanced-cache.php, which often explain odd behaviour.', 'fixpass' ),
				__( 'Themes', 'fixpass' )           => __( 'The active theme and the other installed themes, with versions and waiting updates.', 'fixpass' ),
				__( 'Your description', 'fixpass' ) => __( 'What you wrote in “What’s wrong?”, up to 1,000 characters.', 'fixpass' ),
				__( 'Access', 'fixpass' )           => __( 'When access was given and when it ends, and when the details were captured.', 'fixpass' ),
			)
		);
		self::h3( __( 'What is never captured', 'fixpass' ) );
		self::p( __( 'Passwords, keys and salts, the contents of wp-config.php or any other file, the debug log, users and their email addresses, posts, pages, orders, form entries or any customer data. Fixpass also reads no browser data from you or your visitors.', 'fixpass' ) );
		self::h3( __( 'Where it is shown', 'fixpass' ) );
		self::p( __( 'It goes into the details you copy, and support sees the same information, read live, on the Support session page after logging in. Nothing is saved on your site except the access itself and your description, which are deleted when access ends.', 'fixpass' ) );
		self::p( __( 'Secrets in your description (such as text that looks like a password, key or token) are replaced with [redacted] and email addresses are shortened, as a safety net. Still, don’t write passwords in it.', 'fixpass' ) );
	}

	/**
	 * Support access.
	 */
	private static function tab_access() {
		self::h( __( 'Temporary support access and single-use links', 'fixpass' ) );
		self::h3( __( 'What “Give access” does', 'fixpass' ) );
		self::ul(
			array(
				__( 'Creates one temporary user, named “support-” followed by six random characters, with the Administrator role.', 'fixpass' ),
				__( 'Gives it a long random password that nobody knows. Password login is switched off for it.', 'fixpass' ),
				__( 'Creates a login link that works once.', 'fixpass' ),
				__( 'Schedules the end of access.', 'fixpass' ),
			)
		);
		self::h3( __( 'How long', 'fixpass' ) );
		self::p( __( 'Choose 1 day, 2 days, 3 days (the default), 5 days or 1 week. Access cannot be extended: if support needs more time, end access and give it again. Only one access can be on at a time.', 'fixpass' ) );
		self::h3( __( 'What support can and cannot do', 'fixpass' ) );
		self::table(
			array(
				__( 'Can', 'fixpass' )    => __( 'Everything an administrator can do, except the items below. On a single site this includes installing and updating plugins.', 'fixpass' ),
				__( 'Cannot', 'fixpass' ) => __( 'Create, edit, promote or delete users or see the users list; install or delete themes; deactivate or delete Fixpass. On multisite, support is a site administrator, never a super admin, so network-level actions are not available either.', 'fixpass' ),
			)
		);
		self::h3( __( 'Single-use login links', 'fixpass' ) );
		self::ul(
			array(
				__( 'The link looks like your login page with a long random code. The code is 256 bits long; your site keeps only a one-way hash of it.', 'fixpass' ),
				__( 'Opening the link shows a page with a “Log in as support” button. Only pressing that button uses the link up. Email security scanners that open links automatically cannot use it.', 'fixpass' ),
				__( 'After it is used, it never works again. A link also stops working when access ends or when you make a new link.', 'fixpass' ),
				__( 'If support needs to log in again, click “Copy new login link” (just the link) or “Copy full message with a new link” on the Fixpass page. The old link stops working.', 'fixpass' ),
			)
		);
		self::h3( __( 'Staying in control', 'fixpass' ) );
		self::ul(
			array(
				__( 'You get an email at the site’s administration email address when support logs in.', 'fixpass' ),
				__( 'The Fixpass page shows if support is waiting, logged in or logged out.', 'fixpass' ),
				__( 'The support account is labelled “Temporary support account” on the Users screen.', 'fixpass' ),
				__( 'Click “End access now” at any time. Support is logged out at once and the account is deleted.', 'fixpass' ),
			)
		);
	}

	/**
	 * Copy support details.
	 */
	private static function tab_copy() {
		self::h( __( 'Copy support details', 'fixpass' ) );
		self::p( __( 'After you give access, Fixpass offers two things to copy. Pick whichever suits what you have already sent.', 'fixpass' ) );
		self::table(
			array(
				__( 'Copy full message', 'fixpass' ) => __( 'The login link plus everything support would otherwise ask you for. It is formatted for email, and also copied as plain text so it pastes well into a ticket system.', 'fixpass' ),
				__( 'Copy login link', 'fixpass' )   => __( 'Only the single-use link, as plain text. Use this when support already has the details, or when you want to send the link and the details separately.', 'fixpass' ),
			)
		);
		self::h3( __( 'Review before you send', 'fixpass' ) );
		self::p( __( 'Under the buttons, “Review exactly what the full message contains” opens a preview of the message, as support will receive it. It lists its sections, so you can see at a glance what goes with it. It is closed by default, because the message is long.', 'fixpass' ) );
		self::h3( __( 'What the full message includes', 'fixpass' ) );
		self::ul(
			array(
				__( 'A button-style link to log in, which works once.', 'fixpass' ),
				__( 'When access was given, when it ends, and when the details were captured.', 'fixpass' ),
				__( 'Your description of the problem, if you wrote one.', 'fixpass' ),
				__( 'Everything described under “Automatic context capture”: the site, Site Health, plugins, must-use plugins and drop-ins, and themes.', 'fixpass' ),
			)
		);
		self::h3( __( 'Later, from the status screen', 'fixpass' ) );
		self::p( __( 'Login links are not stored on your site: the link is only shown right after it is made. While access is on, the Fixpass page shows three actions. Each of the first two makes a new single-use link, and the previous link stops working.', 'fixpass' ) );
		self::table(
			array(
				__( 'Copy full message with a new link', 'fixpass' ) => __( 'Makes a new link and shows the same screen as after giving access, with the full message and a preview. The details are captured again, so they are current.', 'fixpass' ),
				__( 'Copy new login link', 'fixpass' ) => __( 'Makes a new link and shows only a “Copy login link” button. Use it when support already has the details and just needs another way in, for example because the first link was used.', 'fixpass' ),
				__( 'End access now', 'fixpass' )      => __( 'Ends access at once and deletes the support account.', 'fixpass' ),
			)
		);
		self::h3( __( 'If you reload the page', 'fixpass' ) );
		self::p( __( 'Reloading the copy screen makes your browser offer to send the form again. If you do, Fixpass recognises it, changes nothing, and tells you so: the link you copied before keeps working. To get a new link, use the buttons.', 'fixpass' ) );
		self::h3( __( 'If the link on the screen is out of date', 'fixpass' ) );
		self::p( __( 'Only the newest link works. If you leave the copy screen open, then make a new link (here or in another tab), or support uses the link, the page notices when you come back to it. It shows a warning and turns the copy buttons off, so you never send a link that no longer works. Click “Make a new link” in the warning to get a fresh one.', 'fixpass' ) );
		self::h3( __( 'If copying fails', 'fixpass' ) );
		self::p( __( 'Some browsers block copying to the clipboard on pages that are not secure (HTTP). Fixpass then tries a simpler copy; if that fails too, it asks you to select the text and copy it yourself. For the full message, open the review section and copy the text from the preview.', 'fixpass' ) );
	}

	/**
	 * For support teams.
	 */
	private static function tab_support() {
		self::h( __( 'For support teams: the Support session page', 'fixpass' ) );
		self::p( __( 'If you were sent a Fixpass login link, this is what to expect.', 'fixpass' ) );
		self::ul(
			array(
				__( 'Open the link. It shows a page with a “Log in as support” button. Press it; the link is then used up.', 'fixpass' ),
				__( 'You land on the Support session page. It shows when access ends, the problem as the site owner described it, and the site details.', 'fixpass' ),
				__( 'Site details are shown as sections you can open and close: Site, Site Health, Active plugins, Inactive plugins, Must-use plugins and drop-ins, and themes. Each shows how many items it holds. The Site section starts open. Everything is read live from the site, and lists every plugin and theme with its version and whether an update is waiting.', 'fixpass' ),
				__( 'A banner and a toolbar item remind you how long you have left.', 'fixpass' ),
			)
		);
		self::h3( __( 'Good to know', 'fixpass' ) );
		self::ul(
			array(
				__( 'The link works once. If you log out or your session ends, ask the owner for a new link.', 'fixpass' ),
				__( 'Your session never outlasts the access. When time runs out, you are logged out and the account is deleted.', 'fixpass' ),
				__( 'You cannot manage users or install and delete themes, and you cannot deactivate or delete Fixpass.', 'fixpass' ),
				__( 'When you are finished, click “End access” on the Support session page. Your account is deleted and you are logged out.', 'fixpass' ),
			)
		);
	}

	/**
	 * Automatic cleanup.
	 */
	private static function tab_cleanup() {
		self::h( __( 'Automatic cleanup', 'fixpass' ) );
		self::p( __( 'Nothing is left behind when access ends. Fixpass does not rely on you remembering to switch anything off.', 'fixpass' ) );
		self::h3( __( 'When access ends', 'fixpass' ) );
		self::p( __( 'Access ends when the time is up, when you click “End access now”, when support clicks “End access”, when you delete the support user on the Users screen, or when Fixpass is deactivated.', 'fixpass' ) );
		self::h3( __( 'What is removed, every time', 'fixpass' ) );
		self::ul(
			array(
				__( 'The support account, and its login sessions. Anything it created is given to the administrator who gave access.', 'fixpass' ),
				__( 'The login link and the record that it was used.', 'fixpass' ),
				__( 'Your description of the problem.', 'fixpass' ),
				__( 'The scheduled task that ends access.', 'fixpass' ),
			)
		);
		self::h3( __( 'Even if the scheduled task does not run', 'fixpass' ) );
		self::p( __( 'WordPress runs scheduled tasks when someone visits. Fixpass does not depend on that for safety: the support account is treated as logged out the moment its time is up, and Fixpass checks the end time on every page load and removes the account as soon as it can.', 'fixpass' ) );
		self::h3( __( 'Fixpass itself', 'fixpass' ) );
		self::p( __( 'Fixpass stays installed after access ends, ready for next time. It does nothing until you give access again. When you no longer need it, deactivate and delete it on the Plugins screen: that removes any support account and everything Fixpass stored. See the “For developers” tab for the full list.', 'fixpass' ) );
	}

	/**
	 * Security and privacy.
	 */
	private static function tab_security() {
		self::h( __( 'Security and privacy', 'fixpass' ) );
		self::h3( __( 'Security', 'fixpass' ) );
		self::ul(
			array(
				__( 'There is no password to leak. The support account’s password is random and password login is blocked for it.', 'fixpass' ),
				__( 'The login link is a random 256-bit code; only its SHA-256 hash is stored. It cannot be guessed, is used once, and dies with the access.', 'fixpass' ),
				__( 'Using the link takes a button press with a one-time check, so automatic link scanners cannot use it.', 'fixpass' ),
				__( 'Guessing is not a realistic attack: the code has 2^256 possibilities, and a wrong code reveals nothing.', 'fixpass' ),
				__( 'Support’s login cookie never outlasts the access.', 'fixpass' ),
				__( 'Support cannot manage users, install or delete themes, or remove Fixpass, so they cannot leave a back door.', 'fixpass' ),
				__( 'Only administrators can give access or see the Fixpass setup. Every action is protected by a security check.', 'fixpass' ),
			)
		);
		self::p( __( 'Support is still an administrator: they can change plugins, content and settings. Give access only to people you trust, for as short a time as needed.', 'fixpass' ) );
		self::h3( __( 'Privacy', 'fixpass' ) );
		self::ul(
			array(
				__( 'Fixpass does not send data to any outside service and does not load scripts, fonts or images from outside your site.', 'fixpass' ),
				__( 'The details only leave your site when you copy them and send them yourself.', 'fixpass' ),
				__( 'Fixpass sends one email: to the site’s administration email address, when support logs in. It includes the IP address support logged in from, so you can recognise it.', 'fixpass' ),
				__( 'Fixpass shows nothing and sets no cookies for visitors. The only cookie it causes is support’s own login cookie.', 'fixpass' ),
				__( 'Your description of the problem is deleted when access ends.', 'fixpass' ),
			)
		);
		self::p( __( 'If you are subject to privacy rules, treat support like any other provider with access to your site. Fixpass does not give support a list of users, but an administrator can open pages that show user data.', 'fixpass' ) );
	}

	/**
	 * Multisite.
	 */
	private static function tab_multisite() {
		self::h( __( 'Multisite', 'fixpass' ) );
		self::p( __( 'Fixpass works on WordPress multisite networks. The rule is simple: every site gives and ends its own support access, and support never gets anything beyond the one site.', 'fixpass' ) );
		self::h3( __( 'How it works on a network', 'fixpass' ) );
		self::ul(
			array(
				__( 'Each site has its own access, description, login link and end time. Access on one site never affects another.', 'fixpass' ),
				__( 'The support account is made a member of that one site only, as an Administrator. It is never a super admin. On any other site of the network it has no role and no access.', 'fixpass' ),
				__( 'Because plugins are shared by the whole network, support cannot install or delete plugins on a network (only super admins can), and cannot manage users or install themes.', 'fixpass' ),
				__( 'The login link opens on the site that created it, and the email about a login goes to that site’s administration email address.', 'fixpass' ),
			)
		);
		self::h3( __( 'Activating Fixpass', 'fixpass' ) );
		self::table(
			array(
				__( 'Network activate', 'fixpass' )     => __( 'Fixpass is available on every site, including sites created later. Super admins also get the Fixpass overview in the Network Admin.', 'fixpass' ),
				__( 'Activate on one site', 'fixpass' ) => __( 'Only that site can use it. The other sites don’t see Fixpass at all.', 'fixpass' ),
			)
		);
		self::h3( __( 'Network Admin: Fixpass', 'fixpass' ) );
		self::ul(
			array(
				__( 'See every site that has support access now, whether support is waiting or logged in, and when it ends.', 'fixpass' ),
				__( 'End access on any site with one click. The support account on that site is deleted.', 'fixpass' ),
				__( 'Choose whether site administrators may give support access on their own site. It is on by default. When it is off, only super admins can give access, and site administrators do not see the Fixpass menu. Access that is already on is not affected.', 'fixpass' ),
			)
		);
		self::h3( __( 'Deactivating and uninstalling', 'fixpass' ) );
		self::ul(
			array(
				__( 'Network deactivating ends access on every site and deletes the support accounts.', 'fixpass' ),
				__( 'Deactivating on a single site ends access on that site only.', 'fixpass' ),
				__( 'Deleting Fixpass from the network removes support accounts and stored data from every site.', 'fixpass' ),
			)
		);
	}

	/**
	 * Troubleshooting.
	 */
	private static function tab_faq() {
		self::h( __( 'Troubleshooting', 'fixpass' ) );
		$faq = array(
			__( 'Support says the link does not work.', 'fixpass' ) => __( 'Links work once. The link may have been opened and used already, replaced by a newer one, or access may have ended. On the Fixpass page, click “Copy full message with a new link” and send the new details. If the page says access has ended, give access again.', 'fixpass' ),
			__( 'The Site Health section is missing.', 'fixpass' ) => __( 'Fixpass only reads the result WordPress stored. Open Tools → Site Health once so WordPress runs its tests, then copy the details again.', 'fixpass' ),
			__( 'Copying failed.', 'fixpass' )        => __( 'Your browser may block the clipboard on pages that are not HTTPS. For the full message, open “Review exactly what the full message contains” and copy the text from the preview. If the “Copy login link” button fails, allow clipboard access for the page and press it again, or open the site admin over HTTPS.', 'fixpass' ),
			__( 'I did not get the “support logged in” email.', 'fixpass' ) => __( 'The email is sent to the site’s administration email address (Settings → General). If your site cannot send email, the Fixpass page still shows when support is logged in.', 'fixpass' ),
			__( 'The support account is still on the Users screen.', 'fixpass' ) => __( 'It is deleted the moment access ends. If access just ended, reload. If it stays, delete it yourself: that is safe. If scheduled tasks are disabled on your site, load any page of the site once and Fixpass will remove it.', 'fixpass' ),
			__( 'I cannot start access.', 'fixpass' ) => __( 'Only one access can be on at a time. End the current one first. If you see “already being created”, wait a few seconds and reload.', 'fixpass' ),
			__( 'I do not see Fixpass in the menu (multisite).', 'fixpass' ) => __( 'The network admin may have limited giving support access to super admins. Ask a super admin, or ask them to allow site administrators in Network Admin → Fixpass.', 'fixpass' ),
			__( 'Support cannot install a plugin or theme.', 'fixpass' ) => __( 'Support can never install themes. On multisite they cannot install plugins either. Install it yourself, or ask support to tell you what they need.', 'fixpass' ),
		);
		foreach ( $faq as $question => $answer ) {
			echo '<h3>' . esc_html( $question ) . '</h3><p>' . esc_html( $answer ) . '</p>';
		}
	}

	/**
	 * For developers.
	 */
	private static function tab_developer() {
		self::h( __( 'For developers', 'fixpass' ) );
		self::h3( __( 'What Fixpass stores', 'fixpass' ) );
		self::table(
			array(
				'fixpass_access'                 => __( 'Option, per site: the current access (account ID, hash of the login link, times, status, your description). Removed when access ends.', 'fixpass' ),
				'fixpass_ended'                  => __( 'Option, per site: when and how the last access ended, to show on the setup screen. Removed when new access is given.', 'fixpass' ),
				'fixpass_used_{hash}'            => __( 'Option, per site: marks a login link as used. Removed when access ends.', 'fixpass' ),
				'fixpass_activation_notice'      => __( 'Transient, 10 minutes: set when Fixpass is activated, so a one-time notice shows where to find it.', 'fixpass' ),
				'admin-ajax: fixpass_link_state' => __( 'Owner screens only (administrators, with a nonce): tells the copy buttons whether the link on the screen still works. It receives the link’s secret and answers current, used, replaced or ended. Nothing is stored.', 'fixpass' ),
				'fixpass_sent'                   => __( 'Option, per site: the IDs of the last few forms that were sent, so reloading a page can’t make a second link. Nothing sensitive.', 'fixpass' ),
				'fixpass_creating'               => __( 'Option, per site: a short lock while access is being created.', 'fixpass' ),
				'fixpass_support (user meta)'    => __( 'On the support account: the ID of the site it is for. The account is deleted when access ends.', 'fixpass' ),
				'fixpass_expire'                 => __( 'Scheduled task: ends access at its end time. Cleared when access ends.', 'fixpass' ),
				'fixpass_network_sites, fixpass_allow_site_admins' => __( 'Multisite only, network options: the sites with access now, and whether site administrators may give access.', 'fixpass' ),
			)
		);
		self::p( __( 'Fixpass creates no database tables and adds no files outside its own folder. There is nothing to configure; on multisite the only setting is the network option below.', 'fixpass' ) );
		self::h3( __( 'Capabilities', 'fixpass' ) );
		self::p( __( 'The owner screens need manage_options (and, on multisite, the network setting that lets site administrators give access). The support account is an Administrator with these capabilities removed: create_users, promote_users, add_users, edit_users, delete_users, remove_users, list_users, manage_network_users, install_themes, delete_themes. It also cannot deactivate or delete Fixpass.', 'fixpass' ) );
		self::h3( __( 'Login link', 'fixpass' ) );
		self::p( __( 'The link is wp-login.php?action=fixpass&token=… The token is 64 hexadecimal characters. A request without a valid token shows an error; a valid one shows a confirmation form, and only its POST (with a nonce) logs in.', 'fixpass' ) );
	}
}
