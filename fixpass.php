<?php
/**
 * Plugin Name:       Fixpass
 * Description:       Give your support team safe, temporary access to your site with a single-use login link, and everything they need to start fixing.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            George Stathopoulos
 * Author URI:        https://profiles.wordpress.org/mindanticipation/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fixpass
 *
 * @package Fixpass
 */

defined( 'ABSPATH' ) || exit;

define( 'FIXPASS_VERSION', '1.0.0' );
define( 'FIXPASS_FILE', __FILE__ );
define( 'FIXPASS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FIXPASS_URL', plugin_dir_url( __FILE__ ) );

require_once FIXPASS_DIR . 'includes/class-redactor.php';
require_once FIXPASS_DIR . 'includes/class-access.php';
require_once FIXPASS_DIR . 'includes/class-details.php';
require_once FIXPASS_DIR . 'includes/class-docs.php';
require_once FIXPASS_DIR . 'includes/class-network.php';
require_once FIXPASS_DIR . 'includes/class-admin.php';
require_once FIXPASS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Fixpass_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Fixpass_Plugin', 'deactivate' ) );

Fixpass_Plugin::init();
