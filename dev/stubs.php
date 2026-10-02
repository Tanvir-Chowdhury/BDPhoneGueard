<?php
/**
 * Minimal WordPress shims so the plugin's classes run under plain PHP.
 * Used only by dev/run-tests.php — never shipped with the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

class WP_Error {

	private $bdpg_code;
	private $bdpg_message;
	private $bdpg_data;

	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->bdpg_code    = $code;
		$this->bdpg_message = $message;
		$this->bdpg_data    = $data;
	}

	public function get_error_code() {
		return $this->bdpg_code;
	}

	public function get_error_message() {
		return $this->bdpg_message;
	}

	public function get_error_data() {
		return $this->bdpg_data;
	}
}

/**
 * Stand-in for WooCommerce's checkout error bag, which only needs add().
 */
class BDPG_Fake_Checkout_Errors {

	public $codes = array();

	public function add( $code, $message ) {
		$this->codes[ $code ] = $message;
	}

	public function has( $code ) {
		return isset( $this->codes[ $code ] );
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html( $text ) {
	return $text;
}

function esc_attr( $text ) {
	return $text;
}

function esc_url( $url ) {
	return $url;
}

function get_locale() {
	return isset( $GLOBALS['bdpg_test_locale'] ) ? $GLOBALS['bdpg_test_locale'] : 'en_US';
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}

function get_option( $name, $default = false ) {
	if ( isset( $GLOBALS['bdpg_test_options'][ $name ] ) ) {
		return $GLOBALS['bdpg_test_options'][ $name ];
	}

	return $default;
}

function apply_filters( $tag, $value ) {
	return $value;
}

function add_filter( ...$args ) {}

function add_action( ...$args ) {}

function sanitize_text_field( $str ) {
	return trim( preg_replace( '/<[^>]*>/', '', (string) $str ) );
}

function wp_unslash( $value ) {
	return $value;
}

function wc_add_notice( $message, $type = 'notice', $args = array() ) {
	$GLOBALS['bdpg_notices'][] = $message;
}

function shortcode_atts( $defaults, $atts, $tag = '' ) {
	$atts = (array) $atts;
	$out  = array();

	foreach ( $defaults as $key => $value ) {
		$out[ $key ] = isset( $atts[ $key ] ) ? $atts[ $key ] : $value;
	}

	return $out;
}

function add_shortcode( $tag, $callback ) {}

function wp_enqueue_style( ...$args ) {}

function wp_enqueue_script( ...$args ) {}

function wp_register_style( ...$args ) {}

function wp_register_script( ...$args ) {}

function wp_localize_script( ...$args ) {}

function wp_unique_id( $prefix = '' ) {
	static $count = 0;
	$count++;

	return $prefix . $count;
}

function register_setting( ...$args ) {}

function add_settings_section( ...$args ) {}

function add_settings_field( ...$args ) {}

function settings_fields( ...$args ) {}

function do_settings_sections( ...$args ) {}

function submit_button( ...$args ) {}

function checked( $checked, $current = true, $echo = true ) {
	$result = ( (string) $checked === (string) $current ) ? ' checked=\'checked\'' : '';

	if ( $echo ) {
		echo $result;
	}

	return $result;
}

function selected( $selected, $current = true, $echo = true ) {
	return checked( $selected, $current, $echo );
}

function current_user_can( ...$args ) {
	return true;
}

function get_admin_page_title() {
	return 'BD Phone Guard';
}

function admin_url( $path = '' ) {
	return 'http://example.org/wp-admin/' . $path;
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function load_plugin_textdomain( ...$args ) {
	return true;
}

function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/' ) . '/';
}

function plugin_dir_url( $file ) {
	return 'http://example.org/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function is_checkout() {
	return false;
}

function is_account_page() {
	return false;
}
