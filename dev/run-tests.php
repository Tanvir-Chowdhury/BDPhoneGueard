<?php
/**
 * Test suite for BD Phone Guard's core logic. Run with: php dev/run-tests.php
 *
 * Requires PHP 7.2+ and the shims from dev/stubs.php. No WordPress needed.
 */

require __DIR__ . '/stubs.php';
require __DIR__ . '/stubs-woocommerce.php';

$bdpg_plugin_root = dirname( __DIR__ ) . '/bd-phone-guard';

// Every class file is loaded so the suite also catches parse errors in code
// the tests themselves do not execute.
require $bdpg_plugin_root . '/includes/class-bdpg-options.php';
require $bdpg_plugin_root . '/includes/class-bdpg-phone.php';
require $bdpg_plugin_root . '/includes/class-bdpg-settings.php';
require $bdpg_plugin_root . '/includes/class-bdpg-shortcode.php';
require $bdpg_plugin_root . '/includes/class-bdpg-woocommerce.php';
require $bdpg_plugin_root . '/includes/class-bdpg-plugin.php';

$GLOBALS['bdpg_test_options'] = array();
$GLOBALS['bdpg_notices']      = array();

$bdpg_count    = 0;
$bdpg_failures = 0;

function bdpg_test( $name, $callback ) {
	global $bdpg_count, $bdpg_failures;

	$bdpg_count++;

	try {
		$callback();
		echo "PASS  $name\n";
	} catch ( Throwable $e ) {
		$bdpg_failures++;
		echo "FAIL  $name — " . $e->getMessage() . "\n";
	}
}

function bdpg_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new Exception( $message );
	}
}

function bdpg_assert_same( $expected, $actual, $message = '' ) {
	if ( $expected !== $actual ) {
		throw new Exception( ( $message ? $message . ': ' : '' ) . 'expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

function bdpg_set_options( array $overrides = array() ) {
	$GLOBALS['bdpg_test_options'] = array(
		'bdpg_options' => array_merge( BD_Phone_Guard_Options::defaults(), $overrides ),
	);
}

bdpg_set_options();

/*
 * Every accepted spelling must normalize to the same national number.
 */
$bdpg_valid = array(
	'01712345678',
	'+8801712345678',
	'8801712345678',
	'008801712345678',
	'(+88) 01712 345678',
	'০১৭১২৩৪৫৬৭৮',
	'৮৮০১৭১২৩৪৫৬৭৮',
	'০1712345678',
	'01712-345678',
	'017 12 34 56 78',
	'+880 1712-345678',
	"017\xE2\x80\x8B12345678",
	'1712345678',
	'tel:01712345678',
);

foreach ( $bdpg_valid as $input ) {
	bdpg_test( "accepts: " . $input, function () use ( $input ) {
		$parsed = BD_Phone_Guard_Phone::parse( $input );

		bdpg_assert( ! is_wp_error( $parsed ), 'should be valid, got error: ' . ( is_wp_error( $parsed ) ? $parsed->get_error_message() : '?' ) );
		bdpg_assert_same( '01712345678', $parsed['digits'] );
		bdpg_assert_same( '017', $parsed['prefix'] );
		bdpg_assert_same( 'Grameenphone', $parsed['operator'] );
		bdpg_assert_same( '+8801712345678', $parsed['e164'] );
	} );
}

/*
 * Rejections, mapped to the failure code they must produce.
 */
$bdpg_invalid = array(
	''             => 'empty',
	'   '          => 'empty',
	'abc'          => 'empty',
	'0171234567'   => 'length',
	'017123456789' => 'length',
	'12345'        => 'length',
	'01111111111'  => 'prefix',
	'01234567890'  => 'prefix',
	'02012345678'  => 'prefix',
	'01700000000'  => 'junk',
	'01877777777'  => 'junk',
	'01555555555'  => 'junk',
);

foreach ( $bdpg_invalid as $input => $expected_code ) {
	bdpg_test( "rejects: " . ( '' === $input ? '(empty string)' : $input ) . " [$expected_code]", function () use ( $input, $expected_code ) {
		$result = bdpg_validate_phone( $input );

		bdpg_assert( is_wp_error( $result ), 'should be invalid' );
		bdpg_assert_same( 'bdpg_' . $expected_code, $result->get_error_code() );
		bdpg_assert( '' !== $result->get_error_message(), 'must carry a customer-facing message' );
	} );
}

bdpg_test( 'formats: e164', function () {
	bdpg_assert_same( '+8801712345678', bdpg_normalize_phone( '01712345678', 'e164' ) );
} );

bdpg_test( 'formats: dashed', function () {
	bdpg_assert_same( '01712-345678', bdpg_normalize_phone( '8801712345678', 'dashed' ) );
} );

bdpg_test( 'formats: off returns the input untouched', function () {
	bdpg_assert_same( '01712345678', bdpg_normalize_phone( ' 01712345678 ', 'off' ), 'off trims but does not reformat' );
} );

bdpg_test( 'formats: default comes from the settings', function () {
	bdpg_set_options( array( 'output_format' => 'e164' ) );
	bdpg_assert_same( '+8801712345678', bdpg_normalize_phone( '০১৭১২৩৪৫৬৭৮' ) );
	bdpg_set_options();
} );

bdpg_test( 'operators: prefix map', function () {
	bdpg_assert_same( 'Banglalink', BD_Phone_Guard_Phone::parse( '01912345678' )['operator'] );
	bdpg_assert_same( 'Teletalk', BD_Phone_Guard_Phone::parse( '01512345678' )['operator'] );
	bdpg_assert_same( 'Robi', BD_Phone_Guard_Phone::parse( '01812345678' )['operator'] );
} );

bdpg_test( 'junk check can be switched off', function () {
	bdpg_set_options( array( 'reject_repeated' => 0 ) );
	bdpg_assert( true === bdpg_validate_phone( '01700000000' ), '01700000000 should pass when the check is off' );
	bdpg_set_options();
} );

bdpg_test( 'messages: bangla when the site language is Bangla', function () {
	$GLOBALS['bdpg_test_locale'] = 'bn_BD';
	$message                     = BD_Phone_Guard_Phone::message( BD_Phone_Guard_Phone::ERROR_PREFIX );
	$GLOBALS['bdpg_test_locale'] = 'en_US';

	bdpg_assert( false === strpos( $message, 'This does not look like' ), 'expected a Bangla message' );
} );

bdpg_test( 'messages: bangla when forced by the setting', function () {
	bdpg_set_options( array( 'error_language' => 'bn' ) );
	$message = BD_Phone_Guard_Phone::message( BD_Phone_Guard_Phone::ERROR_LENGTH );
	bdpg_set_options();

	bdpg_assert( false === strpos( $message, 'Please enter a full' ), 'expected a Bangla message' );
} );

bdpg_test( 'options: sanitize keeps known values', function () {
	$clean = BD_Phone_Guard_Options::sanitize( array(
		'output_format'   => 'e164',
		'error_language'  => 'bn',
		'reject_repeated' => '1',
		'enable_checkout' => 0,
		'enable_account'  => 'anything',
	) );

	bdpg_assert_same( 'e164', $clean['output_format'] );
	bdpg_assert_same( 'bn', $clean['error_language'] );
	bdpg_assert_same( 1, $clean['reject_repeated'] );
	bdpg_assert_same( 0, $clean['enable_checkout'] );
	bdpg_assert_same( 1, $clean['enable_account'] );
} );

bdpg_test( 'options: sanitize rejects unknown values', function () {
	$clean = BD_Phone_Guard_Options::sanitize( array( 'output_format' => '<script>', 'error_language' => 'xx' ) );

	bdpg_assert_same( 'national', $clean['output_format'] );
	bdpg_assert_same( 'auto', $clean['error_language'] );
} );

bdpg_test( 'options: sanitize survives garbage input', function () {
	$clean = BD_Phone_Guard_Options::sanitize( 'not an array' );

	bdpg_assert_same( 'national', $clean['output_format'], 'format falls back to the default' );
	bdpg_assert_same( 'auto', $clean['error_language'], 'language falls back to the default' );
	bdpg_assert_same( 0, $clean['reject_repeated'], 'absent checkboxes mean off' );
	bdpg_assert_same( 0, $clean['enable_checkout'], 'absent checkboxes mean off' );
	bdpg_assert_same( 0, $clean['enable_account'], 'absent checkboxes mean off' );
} );

bdpg_test( 'woocommerce: junk number blocks checkout', function () {
	bdpg_set_options();
	$errors = new BDPG_Fake_Checkout_Errors();

	BD_Phone_Guard_WooCommerce::validate_checkout(
		array( 'billing_phone' => '01700000000', 'shipping_phone' => '' ),
		$errors
	);

	bdpg_assert( $errors->has( 'billing_phone' ), 'expected a billing_phone error' );
} );

bdpg_test( 'woocommerce: valid number passes checkout', function () {
	$errors = new BDPG_Fake_Checkout_Errors();

	BD_Phone_Guard_WooCommerce::validate_checkout(
		array( 'billing_phone' => '০১৭১২৩৪৫৬৭৮', 'shipping_phone' => '01912345678' ),
		$errors
	);

	bdpg_assert_same( array(), $errors->codes );
} );

bdpg_test( 'woocommerce: checkout normalization', function () {
	bdpg_assert_same( '01712345678', BD_Phone_Guard_WooCommerce::normalize_checkout_phone( '+880 1712-345678' ) );
	bdpg_assert_same( 'total junk', BD_Phone_Guard_WooCommerce::normalize_checkout_phone( 'total junk' ), 'invalid input must pass through untouched' );
} );

bdpg_test( 'woocommerce: address form error notice', function () {
	$_POST['billing_phone']     = '01234567890';
	$GLOBALS['bdpg_notices']    = array();

	BD_Phone_Guard_WooCommerce::validate_address( 1, 'billing' );

	bdpg_assert( 1 === count( $GLOBALS['bdpg_notices'] ), 'expected exactly one error notice' );
	unset( $_POST['billing_phone'] );
} );

bdpg_test( 'woocommerce: account details error', function () {
	$_POST['account_phone'] = '01700000000';
	$errors                 = new BDPG_Fake_Checkout_Errors();

	BD_Phone_Guard_WooCommerce::validate_account_details( $errors );

	bdpg_assert( $errors->has( 'account_phone' ), 'expected an account_phone error' );
	unset( $_POST['account_phone'] );
} );

bdpg_test( 'woocommerce: integration can be disabled', function () {
	bdpg_set_options( array( 'enable_checkout' => 0 ) );
	$errors = new BDPG_Fake_Checkout_Errors();

	BD_Phone_Guard_WooCommerce::validate_checkout(
		array( 'billing_phone' => '01700000000' ),
		$errors
	);

	bdpg_assert_same( array(), $errors->codes );
	bdpg_set_options();
} );

bdpg_test( 'woocommerce: block checkout junk number throws a store API error', function () {
	bdpg_set_options();
	$order   = new BDPG_Fake_Order();
	$request = new BDPG_Fake_Request( array( 'billing_address' => array( 'phone' => '01700000000' ) ) );

	$thrown = false;

	try {
		BD_Phone_Guard_WooCommerce::validate_store_api_order( $order, $request );
	} catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $e ) {
		$thrown = true;
		bdpg_assert( false !== strpos( $e->getMessage(), 'does not look real' ), 'expected the customer-facing message' );
	}

	bdpg_assert( $thrown, 'expected a RouteException for a junk number' );
} );

bdpg_test( 'woocommerce: block checkout valid number normalizes the order', function () {
	$order   = new BDPG_Fake_Order();
	$request = new BDPG_Fake_Request( array(
		'billing_address'  => array( 'phone' => '০১৭১২৩৪৫৬৭৮' ),
		'shipping_address' => array( 'phone' => '+8801812345678' ),
	) );

	BD_Phone_Guard_WooCommerce::validate_store_api_order( $order, $request );

	bdpg_assert_same( '01712345678', $order->billing_phone );
	bdpg_assert_same( '01812345678', $order->shipping_phone );
} );

bdpg_test( 'woocommerce: block checkout integration can be disabled', function () {
	bdpg_set_options( array( 'enable_checkout' => 0 ) );
	$order   = new BDPG_Fake_Order();
	$request = new BDPG_Fake_Request( array( 'billing_address' => array( 'phone' => '01700000000' ) ) );

	BD_Phone_Guard_WooCommerce::validate_store_api_order( $order, $request );

	bdpg_assert_same( '', $order->billing_phone, 'disabled integration must not touch the order' );
	bdpg_set_options();
} );

bdpg_test( 'woocommerce: store api phone input is normalized before validation', function () {
	bdpg_set_options();
	$request = new BDPG_Fake_Request( array(
		'billing_address'  => array( 'phone' => '০১৭১২৩৪৫৬৭৮' ),
		'shipping_address' => array( 'phone' => '+880 1812-345678' ),
	) );

	BD_Phone_Guard_WooCommerce::normalize_store_api_phone_input( null, null, $request );

	bdpg_assert_same( '01712345678', $request->get_param( 'billing_address' )['phone'] );
	bdpg_assert_same( '01812345678', $request->get_param( 'shipping_address' )['phone'] );
} );

bdpg_test( 'woocommerce: store api unparseable phone is left for Woo to report', function () {
	$request = new BDPG_Fake_Request( array(
		'billing_address' => array( 'phone' => '12345' ),
	) );

	BD_Phone_Guard_WooCommerce::normalize_store_api_phone_input( null, null, $request );

	bdpg_assert_same( '12345', $request->get_param( 'billing_address' )['phone'] );
} );

bdpg_test( 'woocommerce: non-store-api routes are ignored', function () {
	$request = new BDPG_Fake_Request( array( 'billing_address' => array( 'phone' => '০১৭১২৩৪৫৬৭৮' ) ), '/wp/v2/users' );

	BD_Phone_Guard_WooCommerce::normalize_store_api_phone_input( null, null, $request );

	bdpg_assert_same( '০১৭১২৩৪৫৬৭৮', $request->get_param( 'billing_address' )['phone'] );
} );

bdpg_test( 'shortcode: renders a bound field', function () {
	$html = BD_Phone_Guard_Shortcode::render( array(
		'name'        => 'phone',
		'label'       => 'Mobile number',
		'placeholder' => '01XXXXXXXXX',
		'required'    => 'yes',
	) );

	bdpg_assert( false !== strpos( $html, 'name="phone"' ), 'missing name attribute' );
	bdpg_assert( false !== strpos( $html, 'bdpg-phone-input' ), 'missing input class' );
	bdpg_assert( false !== strpos( $html, 'required' ), 'missing required attribute' );
	bdpg_assert( false !== strpos( $html, 'Mobile number' ), 'missing label' );
	bdpg_assert( false === strpos( $html, 'name=""' ), 'must not render an empty name' );
} );

bdpg_test( 'shortcode: checker mode has no name attribute', function () {
	$html = BD_Phone_Guard_Shortcode::render( array() );

	bdpg_assert( false === strpos( $html, 'name=' ), 'must not render a name attribute when none was asked for' );
} );

echo "\n{$bdpg_count} tests, {$bdpg_failures} failures\n";

exit( $bdpg_failures ? 1 : 0 );
