/*
 * BD Phone Guard - live validation for Bangladeshi mobile numbers.
 * Server-side validation stays the authority; this is instant feedback.
 */
( function () {
	'use strict';

	var cfg = window.BDPG_PUBLIC || {};
	var i18n = cfg.i18n || {};
	var BN_DIGITS = '\u09E6\u09E7\u09E8\u09E9\u09EA\u09EB\u09EC\u09ED\u09EE\u09EF';

	function toEnglishDigits( value ) {
		return String( value ).replace( /[\u09E6-\u09EF]/g, function ( ch ) {
			return String( BN_DIGITS.indexOf( ch ) );
		} );
	}

	function cleanValue( value ) {
		var cleaned = toEnglishDigits( value );
		cleaned = cleaned.replace( /[\u200B-\u200F\u202A-\u202E\u00AD\uFEFF]/g, '' );
		return cleaned.replace( /[^0-9]/g, '' );
	}

	function applyFormat( digits ) {
		var format = cfg.format || 'national';

		if ( format === 'e164' ) {
			return '+880' + digits.slice( 1 );
		}

		if ( format === 'dashed' ) {
			return digits.slice( 0, 5 ) + '-' + digits.slice( 5 );
		}

		return digits;
	}

	/*
	 * Mirrors BD_Phone_Guard_Phone::parse() in PHP. Keep both in sync.
	 */
	function check( rawValue ) {
		var digits = cleanValue( rawValue );

		if ( ! digits ) {
			return { valid: false, code: 'empty', formatted: '' };
		}

		if ( digits.slice( 0, 2 ) === '00' ) {
			digits = digits.slice( 2 );
		}

		if ( digits.slice( 0, 3 ) === '880' ) {
			digits = digits.slice( 3 );

			if ( digits && digits.charAt( 0 ) !== '0' ) {
				digits = '0' + digits;
			}
		}

		if ( digits.length === 10 && digits.charAt( 0 ) === '1' ) {
			digits = '0' + digits;
		}

		if ( digits.length !== 11 || digits.slice( 0, 2 ) !== '01' ) {
			return { valid: false, code: 'length', formatted: '' };
		}

		if ( '3456789'.indexOf( digits.charAt( 2 ) ) === -1 ) {
			return { valid: false, code: 'prefix', formatted: '' };
		}

		if ( cfg.rejectRepeated && /^(\d)\1{7}$/.test( digits.slice( 3 ) ) ) {
			return { valid: false, code: 'junk', formatted: '' };
		}

		return { valid: true, code: '', formatted: applyFormat( digits ) };
	}

	function messageFor( result ) {
		if ( result.valid ) {
			return ( i18n.validNumber ? i18n.validNumber + ' ' : '' ) + result.formatted;
		}

		return i18n[ result.code ] || i18n.invalid || '';
	}

	function attach( input, message ) {
		if ( input.getAttribute( 'data-bdpg-bound' ) ) {
			return;
		}

		input.setAttribute( 'data-bdpg-bound', '1' );

		function update() {
			var result = check( input.value );

			message.textContent = messageFor( result );
			message.className = 'bdpg-phone-message ' + ( result.valid ? 'bdpg-is-valid' : 'bdpg-is-invalid' );

			if ( result.valid ) {
				input.setAttribute( 'data-bdpg-valid', '1' );
			} else {
				input.removeAttribute( 'data-bdpg-valid' );
			}
		}

		input.addEventListener( 'input', update );

		input.addEventListener( 'blur', function () {
			var result = check( input.value );

			if ( result.valid && cfg.format && cfg.format !== 'off' ) {
				input.value = result.formatted;
			}

			update();
		} );

		update();
	}

	function ensureMessage( input ) {
		var message = document.createElement( 'p' );

		message.className = 'bdpg-phone-message';
		message.setAttribute( 'role', 'status' );
		message.setAttribute( 'aria-live', 'polite' );

		input.parentNode.insertBefore( message, input.nextSibling );

		return message;
	}

	function init() {
		var inputs = document.querySelectorAll(
			'.bdpg-phone-input, input[name="billing_phone"], input[name="shipping_phone"], input[name="account_phone"]'
		);

		Array.prototype.forEach.call( inputs, function ( input ) {
			var message = input.parentNode.querySelector( '.bdpg-phone-message' );

			if ( ! message ) {
				message = ensureMessage( input );
			}

			attach( input, message );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
