<?php
/**
 * Plugin Name:       Nombar BD Mobile Validator
 * Description:       Validates and cleans up Bangladeshi mobile numbers at checkout: Bangla digits, +880 formats, spaces and dashes all become one consistent number your courier and SMS tools can use.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            tanvir11744
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nombar-bd-mobile-validator
 * Domain Path:       /languages
 *
 * @package Nombar
 */

defined( 'ABSPATH' ) || exit;

define( 'NOMBAR_VERSION', '1.0.0' );
define( 'NOMBAR_FILE', __FILE__ );
define( 'NOMBAR_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOMBAR_URL', plugin_dir_url( __FILE__ ) );

require_once NOMBAR_DIR . 'includes/class-nombar-phone.php';
require_once NOMBAR_DIR . 'includes/class-nombar-options.php';
require_once NOMBAR_DIR . 'includes/class-nombar-settings.php';
require_once NOMBAR_DIR . 'includes/class-nombar-shortcode.php';
require_once NOMBAR_DIR . 'includes/class-nombar-woocommerce.php';
require_once NOMBAR_DIR . 'includes/class-nombar-plugin.php';

Nombar_Plugin::instance();
