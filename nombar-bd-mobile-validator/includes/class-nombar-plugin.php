<?php
/**
 * Plugin bootstrap.
 *
 * @package Nombar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's parts together.
 */
final class Nombar_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Nombar_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return Nombar_Plugin
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
		add_action( 'init', array( 'Nombar_Shortcode', 'init' ) );
		add_action( 'init', array( 'Nombar_Settings', 'init' ) );

		Nombar_WooCommerce::init();

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Registers the public script and stylesheet once. The script is enqueued
	 * by the shortcode, and on WooCommerce checkout and account pages.
	 */
	public function register_assets() {
		wp_register_style( 'nombar-public', NOMBAR_URL . 'assets/css/nombar-public.css', array(), NOMBAR_VERSION );
		wp_register_script( 'nombar-public', NOMBAR_URL . 'assets/js/nombar-public.js', array(), NOMBAR_VERSION, true );

		wp_localize_script(
			'nombar-public',
			'NOMBAR_PUBLIC',
			array(
				'format'         => nombar_get_options()['output_format'],
				'rejectRepeated' => (bool) nombar_get_options()['reject_repeated'],
				'i18n'           => Nombar_Phone::js_messages(),
			)
		);

		if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_account_page() ) ) {
			wp_enqueue_style( 'nombar-public' );
			wp_enqueue_script( 'nombar-public' );
		}
	}

	/**
	 * Singletons cannot be cloned.
	 */
	private function __clone() {}
}
