<?php
/**
 * Test suite for Nombar BD Mobile Validator's core logic. Run with: php dev/run-tests.php
 *
 * Requires PHP 7.2+ and the shims from dev/stubs.php. No WordPress needed.
 */

require __DIR__ . '/stubs.php';
require __DIR__ . '/stubs-woocommerce.php';

$nombar_plugin_root = dirname( __DIR__ ) . '/nombar-bd-mobile-validator';

// Every class file is loaded so the suite also catches parse errors in code
// the tests themselves do not execute.
require $nombar_plugin_root . '/includes/class-nombar-options.php';
require $nombar_plugin_root . '/includes/class-nombar-phone.php';
require $nombar_plugin_root . '/includes/class-nombar-settings.php';
require $nombar_plugin_root . '/includes/class-nombar-shortcode.php';
require $nombar_plugin_root . '/includes/class-nombar-woocommerce.php';
require $nombar_plugin_root . '/includes/class-nombar-plugin.php';

$GLOBALS['nombar_test_options'] = array();
$GLOBALS['nombar_notices']      = array();

$nombar_count    = 0;
$nombar_failures = 0;

function nombar_test( $name, $callback ) {
	global $nombar_count, $nombar_failures;

	$nombar_count++;

	try {
		$callback();
		echo "PASS  $name\n";
	} catch ( Throwable $e ) {
		$nombar_failures++;
		echo "FAIL  $name — " . $e->getMessage() . "\n";
	}
}

function nombar_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new Exception( $message );
	}
}

function nombar_assert_same( $expected, $actual, $message = '' ) {
	if ( $expected !== $actual ) {
		throw new Exception( ( $message ? $message . ': ' : '' ) . 'expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

function nombar_set_options( array $overrides = array() ) {
	$GLOBALS['nombar_test_options'] = array(
		'nombar_options' => array_merge( Nombar_Options::defaults(), $overrides ),
	);
}

nombar_set_options();

/*
 * Every accepted spelling must normalize to the same national number.
 */
$nombar_valid = array(
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

foreach ( $nombar_valid as $input ) {
	nombar_test( "accepts: " . $input, function () use ( $input ) {
		$parsed = Nombar_Phone::parse( $input );

		nombar_assert( ! is_wp_error( $parsed ), 'should be valid, got error: ' . ( is_wp_error( $parsed ) ? $parsed->get_error_message() : '?' ) );
		nombar_assert_same( '01712345678', $parsed['digits'] );
		nombar_assert_same( '017', $parsed['prefix'] );
		nombar_assert_same( 'Grameenphone', $parsed['operator'] );
		nombar_assert_same( '+8801712345678', $parsed['e164'] );
	} );
}

/*
 * Rejections, mapped to the failure code they must produce.
 */
$nombar_invalid = array(
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

foreach ( $nombar_invalid as $input => $expected_code ) {
	nombar_test( "rejects: " . ( '' === $input ? '(empty string)' : $input ) . " [$expected_code]", function () use ( $input, $expected_code ) {
		$result = nombar_validate_phone( $input );

		nombar_assert( is_wp_error( $result ), 'should be invalid' );
		nombar_assert_same( 'nombar_' . $expected_code, $result->get_error_code() );
		nombar_assert( '' !== $result->get_error_message(), 'must carry a customer-facing message' );
	} );
}

nombar_test( 'formats: e164', function () {
	nombar_assert_same( '+8801712345678', nombar_normalize_phone( '01712345678', 'e164' ) );
} );

nombar_test( 'formats: dashed', function () {
	nombar_assert_same( '01712-345678', nombar_normalize_phone( '8801712345678', 'dashed' ) );
} );

nombar_test( 'formats: off returns the input untouched', function () {
	nombar_assert_same( '01712345678', nombar_normalize_phone( ' 01712345678 ', 'off' ), 'off trims but does not reformat' );
} );

nombar_test( 'formats: default comes from the settings', function () {
	nombar_set_options( array( 'output_format' => 'e164' ) );
	nombar_assert_same( '+8801712345678', nombar_normalize_phone( '০১৭১২৩৪৫৬৭৮' ) );
	nombar_set_options();
} );

nombar_test( 'operators: prefix map', function () {
	nombar_assert_same( 'Banglalink', Nombar_Phone::parse( '01912345678' )['operator'] );
	nombar_assert_same( 'Teletalk', Nombar_Phone::parse( '01512345678' )['operator'] );
	nombar_assert_same( 'Robi', Nombar_Phone::parse( '01812345678' )['operator'] );
} );

nombar_test( 'junk check can be switched off', function () {
	nombar_set_options( array( 'reject_repeated' => 0 ) );
	nombar_assert( true === nombar_validate_phone( '01700000000' ), '01700000000 should pass when the check is off' );
	nombar_set_options();
} );

nombar_test( 'messages: bangla when the site language is Bangla', function () {
	$GLOBALS['nombar_test_locale'] = 'bn_BD';
	$message                     = Nombar_Phone::message( Nombar_Phone::ERROR_PREFIX );
	$GLOBALS['nombar_test_locale'] = 'en_US';

	nombar_assert( false === strpos( $message, 'This does not look like' ), 'expected a Bangla message' );
} );

nombar_test( 'messages: bangla when forced by the setting', function () {
	nombar_set_options( array( 'error_language' => 'bn' ) );
	$message = Nombar_Phone::message( Nombar_Phone::ERROR_LENGTH );
	nombar_set_options();

	nombar_assert( false === strpos( $message, 'Please enter a full' ), 'expected a Bangla message' );
} );

nombar_test( 'options: sanitize keeps known values', function () {
	$clean = Nombar_Options::sanitize( array(
		'output_format'   => 'e164',
		'error_language'  => 'bn',
		'reject_repeated' => '1',
		'enable_checkout' => 0,
		'enable_account'  => 'anything',
	) );

	nombar_assert_same( 'e164', $clean['output_format'] );
	nombar_assert_same( 'bn', $clean['error_language'] );
	nombar_assert_same( 1, $clean['reject_repeated'] );
	nombar_assert_same( 0, $clean['enable_checkout'] );
	nombar_assert_same( 1, $clean['enable_account'] );
} );

nombar_test( 'options: sanitize rejects unknown values', function () {
	$clean = Nombar_Options::sanitize( array( 'output_format' => '<script>', 'error_language' => 'xx' ) );

	nombar_assert_same( 'national', $clean['output_format'] );
	nombar_assert_same( 'auto', $clean['error_language'] );
} );

nombar_test( 'options: sanitize survives garbage input', function () {
	$clean = Nombar_Options::sanitize( 'not an array' );

	nombar_assert_same( 'national', $clean['output_format'], 'format falls back to the default' );
	nombar_assert_same( 'auto', $clean['error_language'], 'language falls back to the default' );
	nombar_assert_same( 0, $clean['reject_repeated'], 'absent checkboxes mean off' );
	nombar_assert_same( 0, $clean['enable_checkout'], 'absent checkboxes mean off' );
	nombar_assert_same( 0, $clean['enable_account'], 'absent checkboxes mean off' );
} );

nombar_test( 'woocommerce: junk number blocks checkout', function () {
	nombar_set_options();
	$errors = new NOMBAR_Fake_Checkout_Errors();

	Nombar_WooCommerce::validate_checkout(
		array( 'billing_phone' => '01700000000', 'shipping_phone' => '' ),
		$errors
	);

	nombar_assert( $errors->has( 'billing_phone' ), 'expected a billing_phone error' );
} );

nombar_test( 'woocommerce: valid number passes checkout', function () {
	$errors = new NOMBAR_Fake_Checkout_Errors();

	Nombar_WooCommerce::validate_checkout(
		array( 'billing_phone' => '০১৭১২৩৪৫৬৭৮', 'shipping_phone' => '01912345678' ),
		$errors
	);

	nombar_assert_same( array(), $errors->codes );
} );

nombar_test( 'woocommerce: checkout normalization', function () {
	nombar_assert_same( '01712345678', Nombar_WooCommerce::normalize_checkout_phone( '+880 1712-345678' ) );
	nombar_assert_same( 'total junk', Nombar_WooCommerce::normalize_checkout_phone( 'total junk' ), 'invalid input must pass through untouched' );
} );

nombar_test( 'woocommerce: address form error notice', function () {
	$_POST['billing_phone']     = '01234567890';
	$GLOBALS['nombar_notices']    = array();

	Nombar_WooCommerce::validate_address( 1, 'billing' );

	nombar_assert( 1 === count( $GLOBALS['nombar_notices'] ), 'expected exactly one error notice' );
	unset( $_POST['billing_phone'] );
} );

nombar_test( 'woocommerce: account details error', function () {
	$_POST['account_phone'] = '01700000000';
	$errors                 = new NOMBAR_Fake_Checkout_Errors();

	Nombar_WooCommerce::validate_account_details( $errors );

	nombar_assert( $errors->has( 'account_phone' ), 'expected an account_phone error' );
	unset( $_POST['account_phone'] );
} );

nombar_test( 'woocommerce: integration can be disabled', function () {
	nombar_set_options( array( 'enable_checkout' => 0 ) );
	$errors = new NOMBAR_Fake_Checkout_Errors();

	Nombar_WooCommerce::validate_checkout(
		array( 'billing_phone' => '01700000000' ),
		$errors
	);

	nombar_assert_same( array(), $errors->codes );
	nombar_set_options();
} );

nombar_test( 'woocommerce: block checkout junk number throws a store API error', function () {
	nombar_set_options();
	$order   = new NOMBAR_Fake_Order();
	$request = new NOMBAR_Fake_Request( array( 'billing_address' => array( 'phone' => '01700000000' ) ) );

	$thrown = false;

	try {
		Nombar_WooCommerce::validate_store_api_order( $order, $request );
	} catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $e ) {
		$thrown = true;
		nombar_assert( false !== strpos( $e->getMessage(), 'does not look real' ), 'expected the customer-facing message' );
	}

	nombar_assert( $thrown, 'expected a RouteException for a junk number' );
} );

nombar_test( 'woocommerce: block checkout valid number normalizes the order', function () {
	$order   = new NOMBAR_Fake_Order();
	$request = new NOMBAR_Fake_Request( array(
		'billing_address'  => array( 'phone' => '০১৭১২৩৪৫৬৭৮' ),
		'shipping_address' => array( 'phone' => '+8801812345678' ),
	) );

	Nombar_WooCommerce::validate_store_api_order( $order, $request );

	nombar_assert_same( '01712345678', $order->billing_phone );
	nombar_assert_same( '01812345678', $order->shipping_phone );
} );

nombar_test( 'woocommerce: block checkout integration can be disabled', function () {
	nombar_set_options( array( 'enable_checkout' => 0 ) );
	$order   = new NOMBAR_Fake_Order();
	$request = new NOMBAR_Fake_Request( array( 'billing_address' => array( 'phone' => '01700000000' ) ) );

	Nombar_WooCommerce::validate_store_api_order( $order, $request );

	nombar_assert_same( '', $order->billing_phone, 'disabled integration must not touch the order' );
	nombar_set_options();
} );

nombar_test( 'woocommerce: store api phone input is normalized before validation', function () {
	nombar_set_options();
	$request = new NOMBAR_Fake_Request( array(
		'billing_address'  => array( 'phone' => '০১৭১২৩৪৫৬৭৮' ),
		'shipping_address' => array( 'phone' => '+880 1812-345678' ),
	) );

	Nombar_WooCommerce::normalize_store_api_phone_input( null, null, $request );

	nombar_assert_same( '01712345678', $request->get_param( 'billing_address' )['phone'] );
	nombar_assert_same( '01812345678', $request->get_param( 'shipping_address' )['phone'] );
} );

nombar_test( 'woocommerce: store api unparseable phone is left for Woo to report', function () {
	$request = new NOMBAR_Fake_Request( array(
		'billing_address' => array( 'phone' => '12345' ),
	) );

	Nombar_WooCommerce::normalize_store_api_phone_input( null, null, $request );

	nombar_assert_same( '12345', $request->get_param( 'billing_address' )['phone'] );
} );

nombar_test( 'woocommerce: non-store-api routes are ignored', function () {
	$request = new NOMBAR_Fake_Request( array( 'billing_address' => array( 'phone' => '০১৭১২৩৪৫৬৭৮' ) ), '/wp/v2/users' );

	Nombar_WooCommerce::normalize_store_api_phone_input( null, null, $request );

	nombar_assert_same( '০১৭১২৩৪৫৬৭৮', $request->get_param( 'billing_address' )['phone'] );
} );

nombar_test( 'shortcode: renders a bound field', function () {
	$html = Nombar_Shortcode::render( array(
		'name'        => 'phone',
		'label'       => 'Mobile number',
		'placeholder' => '01XXXXXXXXX',
		'required'    => 'yes',
	) );

	nombar_assert( false !== strpos( $html, 'name="phone"' ), 'missing name attribute' );
	nombar_assert( false !== strpos( $html, 'nombar-phone-input' ), 'missing input class' );
	nombar_assert( false !== strpos( $html, 'required' ), 'missing required attribute' );
	nombar_assert( false !== strpos( $html, 'Mobile number' ), 'missing label' );
	nombar_assert( false === strpos( $html, 'name=""' ), 'must not render an empty name' );
} );

nombar_test( 'shortcode: checker mode has no name attribute', function () {
	$html = Nombar_Shortcode::render( array() );

	nombar_assert( false === strpos( $html, 'name=' ), 'must not render a name attribute when none was asked for' );
} );

echo "\n{$nombar_count} tests, {$nombar_failures} failures\n";

exit( $nombar_failures ? 1 : 0 );
