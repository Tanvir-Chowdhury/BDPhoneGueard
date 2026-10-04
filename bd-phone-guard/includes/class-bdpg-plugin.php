<?php
/**
 * Plugin bootstrap.
 *
 * @package BDPhoneGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's parts together.
 */
final class BD_Phone_Guard_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var BD_Phone_Guard_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return BD_Phone_Guard_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers all hooks.
	 */
	private function __construct() {
		// Translations load automatically from translate.wordpress.org for
		// plugins hosted on WordPress.org; the .pot file ships for GlotPress.
		add_action( 'init', array( 'BD_Phone_Guard_Shortcode', 'init' ) );
		add_action( 'init', array( 'BD_Phone_Guard_Settings', 'init' ) );

		BD_Phone_Guard_WooCommerce::init();

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Registers the public script and stylesheet once. The script is enqueued
	 * by the shortcode, and on WooCommerce checkout and account pages.
	 */
	public function register_assets() {
		wp_register_style( 'bdpg-public', BDPG_URL . 'assets/css/bdpg-public.css', array(), BDPG_VERSION );
		wp_register_script( 'bdpg-public', BDPG_URL . 'assets/js/bdpg-public.js', array(), BDPG_VERSION, true );

		wp_localize_script(
			'bdpg-public',
			'BDPG_PUBLIC',
			array(
				'format'         => bdpg_get_options()['output_format'],
				'rejectRepeated' => (bool) bdpg_get_options()['reject_repeated'],
				'i18n'           => BD_Phone_Guard_Phone::js_messages(),
			)
		);

		if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_account_page() ) ) {
			wp_enqueue_style( 'bdpg-public' );
			wp_enqueue_script( 'bdpg-public' );
		}
	}

	/**
	 * Singletons cannot be cloned.
	 */
	private function __clone() {}
}
