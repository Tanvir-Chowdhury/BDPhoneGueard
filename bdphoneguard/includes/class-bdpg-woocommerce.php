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
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'validate_store_api_order' ), 10, 2 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'normalize_store_api_phone_input' ), 10, 3 );
		add_action( 'woocommerce_after_save_address_validation', array( __CLASS__, 'validate_address' ), 10, 2 );
		add_action( 'woocommerce_save_account_details_errors', array( __CLASS__, 'validate_account_details' ), 10, 1 );
	}

	/**
	 * Block checkout: WooCommerce's own address validation runs before any
	 * hook can help, and it rejects Bangla digits with a generic English
	 * error. Normalize parseable numbers in the request up front so the
	 * schema check passes and our prefix/junk rules decide the outcome.
	 * Unparseable values are left untouched for Woo to report.
	 *
	 * @param mixed           $result  Null (request continues) by default.
	 * @param WP_REST_Server  $server  Server object.
	 * @param WP_REST_Request $request Request being dispatched.
	 * @return mixed Always null; the request proceeds unchanged otherwise.
	 */
	public static function normalize_store_api_phone_input( $result, $server, $request ) {
		unset( $result, $server );

		if ( ! bdpg_is_feature_active( 'checkout' ) || ! method_exists( $request, 'get_route' ) || ! method_exists( $request, 'set_param' ) ) {
			return null;
		}

		if ( false === strpos( (string) $request->get_route(), '/wc/store/v1' ) ) {
			return null;
		}

		foreach ( array( 'billing_address', 'shipping_address' ) as $param ) {
			$address = $request->get_param( $param );

			if ( ! is_array( $address ) || empty( $address['phone'] ) || ! is_string( $address['phone'] ) ) {
				continue;
			}

			$parsed = BD_Phone_Guard_Phone::parse( $address['phone'] );

			if ( is_array( $parsed ) ) {
				$address['phone'] = $parsed['digits'];
				$request->set_param( $param, $address );
			}
		}

		return null;
	}

	/**
	 * Block checkout (Store API): validate and normalize the phone on the
	 * order being placed. Throwing a RouteException blocks the order and
	 * shows the message in the checkout UI.
	 *
	 * @param WC_Order        $order   Order being created from the request.
	 * @param WP_REST_Request $request Store API request.
	 */
	public static function validate_store_api_order( $order, $request ) {
		if ( ! bdpg_is_feature_active( 'checkout' ) || ! class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			return;
		}

		$addresses = array(
			'billing'  => $request->get_param( 'billing_address' ),
			'shipping' => $request->get_param( 'shipping_address' ),
		);

		foreach ( $addresses as $type => $address ) {
			if ( ! is_array( $address ) || empty( $address['phone'] ) ) {
				continue;
			}

			$result = bdpg_validate_phone( $address['phone'] );

			if ( is_wp_error( $result ) ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
					'bdpg_' . $type . '_phone',
					esc_html( $result->get_error_message() ),
					400
				);
			}

			$normalized = bdpg_normalize_phone( $address['phone'] );

			if ( ! is_wp_error( $normalized ) ) {
				if ( 'billing' === $type ) {
					$order->set_billing_phone( $normalized );
				} else {
					$order->set_shipping_phone( $normalized );
				}
			}
		}
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
