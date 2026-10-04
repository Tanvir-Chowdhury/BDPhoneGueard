<?php
/**
 * Core parser, validator and formatter for Bangladeshi mobile numbers.
 *
 * @package BDPhoneGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accepts anything customers actually type: 01712345678, +8801712345678,
 * 8801712345678, 00 880 1712-345678, "017 12 34 56 78", ০১৭১২৩৪৫৬৭৮, and
 * numbers wrapped in invisible characters copied out of messaging apps.
 */
class BD_Phone_Guard_Phone {

	const ERROR_EMPTY   = 'empty';
	const ERROR_LENGTH  = 'length';
	const ERROR_PREFIX  = 'prefix';
	const ERROR_JUNK    = 'junk';
	const ERROR_INVALID = 'invalid';

	/**
	 * Mobile prefixes allocated by BTRC, mapped to the operating brand.
	 *
	 * @return array prefix => operator name.
	 */
	public static function operator_prefixes() {
		$prefixes = array(
			'013' => 'Grameenphone',
			'014' => 'Banglalink',
			'015' => 'Teletalk',
			'016' => 'Airtel',
			'017' => 'Grameenphone',
			'018' => 'Robi',
			'019' => 'Banglalink',
		);

		/**
		 * Filters the accepted operator prefixes.
		 *
		 * @param array $prefixes prefix => operator name.
		 */
		return apply_filters( 'bdpg_operator_prefixes', $prefixes );
	}

	/**
	 * Reduces any raw input to ASCII digits only.
	 *
	 * @param string $raw Untouched user input.
	 * @return string Digits only. Empty string when the input holds no digits.
	 */
	public static function clean( $raw ) {
		$value = (string) $raw;

		// Invisible characters picked up when copying numbers from chat apps or PDFs.
		$stripped = preg_replace( '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{00AD}\x{FEFF}]/u', '', $value );

		if ( null !== $stripped ) {
			$value = $stripped;
		}

		// Bangla digits (U+09E6 to U+09EF) to their ASCII equivalents.
		$value = str_replace(
			array( '০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯' ),
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			$value
		);

		// Everything that is not a digit is noise: spaces, dashes, brackets, plus signs, letters.
		return (string) preg_replace( '/[^0-9]/', '', $value );
	}

	/**
	 * Parses and validates any raw input.
	 *
	 * @param string $raw Untouched user input.
	 * @return array|WP_Error Array with digits, prefix, operator, national and e164 keys.
	 */
	public static function parse( $raw ) {
		$digits = self::clean( $raw );

		if ( '' === $digits ) {
			return self::error( self::ERROR_EMPTY );
		}

		// International call prefix, e.g. 00 880 1712 345678.
		if ( '00' === substr( $digits, 0, 2 ) ) {
			$digits = substr( $digits, 2 );
		}

		// Country code: 8801712345678 or 88001712345678.
		if ( '880' === substr( $digits, 0, 3 ) ) {
			$digits = substr( $digits, 3 );

			if ( '' !== $digits && '0' !== substr( $digits, 0, 1 ) ) {
				$digits = '0' . $digits;
			}
		}

		// Written without the trunk zero: 1712345678.
		if ( 10 === strlen( $digits ) && '1' === substr( $digits, 0, 1 ) ) {
			$digits = '0' . $digits;
		}

		if ( 11 !== strlen( $digits ) ) {
			return self::error( self::ERROR_LENGTH );
		}

		$prefix = substr( $digits, 0, 3 );

		if ( ! array_key_exists( $prefix, self::operator_prefixes() ) ) {
			return self::error( self::ERROR_PREFIX );
		}

		if ( self::looks_junk( $digits ) ) {
			return self::error( self::ERROR_JUNK );
		}

		$prefixes = self::operator_prefixes();

		return array(
			'digits'   => $digits,
			'prefix'   => $prefix,
			'operator' => $prefixes[ $prefix ],
			'national' => $digits,
			'e164'     => '+880' . substr( $digits, 1 ),
		);
	}

	/**
	 * Heuristic for obviously fake numbers, e.g. 01700000000 or 01877777777:
	 * the eight digits after the operator prefix are all identical.
	 *
	 * @param string $digits A normalized 11-digit national number.
	 * @return bool
	 */
	public static function looks_junk( $digits ) {
		if ( ! self::junk_check_enabled() ) {
			return false;
		}

		return 1 === preg_match( '/^(\d)\1{7}$/', substr( $digits, 3 ) );
	}

	/**
	 * Whether the fake-number heuristic is switched on (setting + filter).
	 *
	 * @return bool
	 */
	protected static function junk_check_enabled() {
		$options = bdpg_get_options();

		/**
		 * Filters whether obviously fake numbers (all identical digits) are rejected.
		 *
		 * @param bool $enabled
		 */
		return (bool) apply_filters( 'bdpg_reject_repeated_digits', ! empty( $options['reject_repeated'] ) );
	}

	/**
	 * Builds a customer-ready WP_Error for a failure code.
	 *
	 * @param string $code One of the ERROR_* constants.
	 * @return WP_Error
	 */
	protected static function error( $code ) {
		return new WP_Error( 'bdpg_' . $code, self::message( $code ), array( 'status' => 400 ) );
	}

	/**
	 * Customer-facing message for a failure code, in the configured language.
	 *
	 * @param string $code One of the ERROR_* constants.
	 * @return string
	 */
	public static function message( $code ) {
		$options = bdpg_get_options();
		$lang    = $options['error_language'];

		if ( 'auto' === $lang ) {
			$lang = ( 0 === strpos( get_locale(), 'bn' ) ) ? 'bn' : 'en';
		}

		if ( 'bn' === $lang ) {
			$messages = array(
				self::ERROR_EMPTY   => 'অনুগ্রহ করে আপনার মোবাইল নম্বর লিখুন।',
				self::ERROR_LENGTH  => 'সম্পূর্ণ ১১ সংখ্যার বাংলাদেশি মোবাইল নম্বর লিখুন, যেমন ০১৭১২৩৪৫৬৭৮।',
				self::ERROR_PREFIX  => 'এটি বাংলাদেশি মোবাইল নম্বর বলে মনে হচ্ছে না। সঠিক নম্বর ০১৩, ০১৪, ০১৫, ০১৬, ০১৭, ০১৮ বা ০১৯ দিয়ে শুরু হয়।',
				self::ERROR_JUNK    => 'এই নম্বরটি ভুয়া বলে মনে হচ্ছে। অনুগ্রহ করে আপনার সঠিক মোবাইল নম্বর লিখুন।',
				self::ERROR_INVALID => 'অনুগ্রহ করে সঠিক বাংলাদেশি মোবাইল নম্বর লিখুন, যেমন ০১৭১২৩৪৫৬৭৮।',
			);
			$domain   = 'bn';
		} else {
			$messages = array(
				self::ERROR_EMPTY   => __( 'Please enter your mobile number.', 'bd-phone-guard' ),
				self::ERROR_LENGTH  => __( 'Please enter a full 11-digit Bangladeshi mobile number, for example 01712345678.', 'bd-phone-guard' ),
				self::ERROR_PREFIX  => __( 'This does not look like a Bangladeshi mobile number. Valid numbers start with 013, 014, 015, 016, 017, 018 or 019.', 'bd-phone-guard' ),
				self::ERROR_JUNK    => __( 'This phone number does not look real. Please check it and enter your correct mobile number.', 'bd-phone-guard' ),
				self::ERROR_INVALID => __( 'Please enter a valid Bangladeshi mobile number, for example 01712345678.', 'bd-phone-guard' ),
			);
			$domain   = 'en';
		}

		$message = isset( $messages[ $code ] ) ? $messages[ $code ] : $messages[ self::ERROR_INVALID ];

		/**
		 * Filters the error message shown to the customer.
		 *
		 * @param string $message The message text.
		 * @param string $code    The failure code.
		 * @param string $domain  Language the message was built for: bn or en.
		 */
		return apply_filters( 'bdpg_error_message', $message, $code, $domain );
	}

	/**
	 * Client-side copies of the messages, passed to the public script.
	 *
	 * @return array
	 */
	public static function js_messages() {
		return array(
			'empty'       => self::message( self::ERROR_EMPTY ),
			'length'      => self::message( self::ERROR_LENGTH ),
			'prefix'      => self::message( self::ERROR_PREFIX ),
			'junk'        => self::message( self::ERROR_JUNK ),
			'invalid'     => self::message( self::ERROR_INVALID ),
			'validNumber' => __( 'Valid mobile number:', 'bd-phone-guard' ),
		);
	}

	/**
	 * Formats a normalized 11-digit national number.
	 *
	 * @param string $digits Normalized number, e.g. 01712345678.
	 * @param string $format national|e164|dashed.
	 * @return string
	 */
	public static function format( $digits, $format ) {
		$digits = (string) $digits;

		switch ( $format ) {
			case 'e164':
				return '+880' . substr( $digits, 1 );
			case 'dashed':
				return substr( $digits, 0, 5 ) . '-' . substr( $digits, 5 );
			case 'national':
			default:
				return $digits;
		}
	}
}

/**
 * Validates a Bangladeshi mobile number.
 *
 * @param string $raw Any user input.
 * @return true|WP_Error True when valid, WP_Error with a customer-ready message otherwise.
 */
function bdpg_validate_phone( $raw ) {
	$parsed = BD_Phone_Guard_Phone::parse( $raw );

	return is_wp_error( $parsed ) ? $parsed : true;
}

/**
 * Normalizes a Bangladeshi mobile number to the configured output format.
 *
 * @param string      $raw    Any user input.
 * @param string|null $format Optional: national|e164|dashed. Defaults to the plugin setting.
 *                            Pass 'off' to get the untouched input back after validation.
 * @return string|WP_Error The formatted number, or WP_Error when the number is invalid.
 */
function bdpg_normalize_phone( $raw, $format = null ) {
	$parsed = BD_Phone_Guard_Phone::parse( $raw );

	if ( is_wp_error( $parsed ) ) {
		return $parsed;
	}

	if ( null === $format ) {
		$options = bdpg_get_options();
		$format  = $options['output_format'];
	}

	if ( 'off' === $format ) {
		return trim( (string) $raw );
	}

	return BD_Phone_Guard_Phone::format( $parsed['digits'], $format );
}
