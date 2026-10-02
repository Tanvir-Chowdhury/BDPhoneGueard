<?php
/**
 * WooCommerce integration.
 *
 * @package BDPhoneGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates and normalizes phone fields at checkout and on My Account pages.
 */
class BD_Phone_Guard_WooCommerce {

	/**
	 * Registers hooks. The hooks only fire when WooCommerce is active, so no
	 * hard dependency is needed and the plugin works standalone as well.
	 */
	public static function init() {
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_checkout' ), 10, 2 );
		add_filter( 'woocommerce_process_checkout_field_billing_phone', array( __CLASS__, 'normalize_checkout_phone' ) );
		add_filter( 'woocommerce_process_checkout_field_shipping_phone', array( __CLASS__, 'normalize_checkout_phone' ) );
		add_action( 'woocommerce_after_save_address_validation', array( __CLASS__, 'validate_address' ), 10, 2 );
		add_action( 'woocommerce_save_account_details_errors', array( __CLASS__, 'validate_account_details' ), 10, 1 );
	}

	/**
	 * Checkout validation: blocks the order and explains the problem.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Checkout errors.
	 */
	public static function validate_checkout( $data, $errors ) {
		if ( ! bdpg_is_feature_active( 'checkout' ) ) {
			return;
		}

		foreach ( array( 'billing_phone', 'shipping_phone' ) as $field ) {
			if ( empty( $data[ $field ] ) ) {
				continue;
			}

			$result = bdpg_validate_phone( $data[ $field ] );

			if ( is_wp_error( $result ) ) {
				$errors->add( $field, $result->get_error_message() );
			}
		}
	}

	/**
	 * Stores checkout phone fields in the configured format.
	 *
	 * Invalid values are left untouched here; validate_checkout() already
	 * reported them to the customer.
	 *
	 * @param string $value Submitted phone number.
	 * @return string
	 */
	public static function normalize_checkout_phone( $value ) {
		if ( ! bdpg_is_feature_active( 'checkout' ) ) {
			return $value;
		}

		$normalized = bdpg_normalize_phone( $value );

		return is_wp_error( $normalized ) ? $value : $normalized;
	}

	/**
	 * My Account → Addresses validation.
	 *
	 * @param int    $user_id      User being saved.
	 * @param string $address_type 'billing' or 'shipping'.
	 */
	public static function validate_address( $user_id, $address_type ) {
		unset( $user_id );

		if ( ! bdpg_is_feature_active( 'account' ) ) {
			return;
		}

		$field = $address_type . '_phone';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the nonce for this form before this hook fires.
		$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';

		if ( '' === $value ) {
			return;
		}

		$result = bdpg_validate_phone( $value );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
		}
	}

	/**
	 * My Account → Account details validation.
	 *
	 * @param WP_Error $errors Account details errors.
	 */
	public static function validate_account_details( $errors ) {
		if ( ! bdpg_is_feature_active( 'account' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the nonce for this form before this hook fires.
		$value = isset( $_POST['account_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['account_phone'] ) ) : '';

		if ( '' === $value ) {
			return;
		}

		$result = bdpg_validate_phone( $value );

		if ( is_wp_error( $result ) ) {
			$errors->add( 'account_phone', $result->get_error_message() );
		}
	}
}
