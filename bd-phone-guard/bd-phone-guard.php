<?php
/**
 * Plugin Name:       BD Phone Guard
 * Description:       Validates and cleans up Bangladeshi mobile numbers at checkout: Bangla digits, +880 formats, spaces and dashes all become one consistent number your courier and SMS tools can use.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            tanvir11744
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bd-phone-guard
 * Domain Path:       /languages
 *
 * @package BDPhoneGuard
 */

defined( 'ABSPATH' ) || exit;

define( 'BDPG_VERSION', '1.0.0' );
define( 'BDPG_FILE', __FILE__ );
define( 'BDPG_DIR', plugin_dir_path( __FILE__ ) );
define( 'BDPG_URL', plugin_dir_url( __FILE__ ) );

require_once BDPG_DIR . 'includes/class-bdpg-phone.php';
require_once BDPG_DIR . 'includes/class-bdpg-options.php';
require_once BDPG_DIR . 'includes/class-bdpg-settings.php';
require_once BDPG_DIR . 'includes/class-bdpg-shortcode.php';
require_once BDPG_DIR . 'includes/class-bdpg-woocommerce.php';
require_once BDPG_DIR . 'includes/class-bdpg-plugin.php';

BD_Phone_Guard_Plugin::instance();
