<?php
/**
 * Plugin option handling.
 *
 * @package BDPhoneGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores and sanitizes the plugin settings.
 */
class BD_Phone_Guard_Options {

	const OPTION_KEY = 'bdpg_options';

	/**
	 * Option defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'output_format'   => 'national',
			'error_language'  => 'auto',
			'reject_repeated' => 1,
			'enable_checkout' => 1,
			'enable_account'  => 1,
		);
	}

	/**
	 * Current options merged over the defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Sanitize callback for register_setting().
	 *
	 * @param array|mixed $input Raw form input.
	 * @return array Clean option values.
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		$formats         = array( 'national', 'e164', 'dashed', 'off' );
		$submitted_value = isset( $input['output_format'] ) ? $input['output_format'] : '';

		$output['output_format'] = in_array( $submitted_value, $formats, true ) ? $submitted_value : $defaults['output_format'];

		$languages       = array( 'auto', 'bn', 'en' );
		$submitted_value = isset( $input['error_language'] ) ? $input['error_language'] : '';

		$output['error_language'] = in_array( $submitted_value, $languages, true ) ? $submitted_value : $defaults['error_language'];

		foreach ( array( 'reject_repeated', 'enable_checkout', 'enable_account' ) as $flag ) {
			$output[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		return $output;
	}
}

/**
 * Returns the current plugin options merged over the defaults.
 *
 * @return array
 */
function bdpg_get_options() {
	return BD_Phone_Guard_Options::get();
}

/**
 * Whether a feature is switched on in the settings.
 *
 * @param string $feature 'checkout' or 'account'.
 * @return bool
 */
function bdpg_is_feature_active( $feature ) {
	$options = bdpg_get_options();

	return ( 'checkout' === $feature ) ? ! empty( $options['enable_checkout'] ) : ! empty( $options['enable_account'] );
}
