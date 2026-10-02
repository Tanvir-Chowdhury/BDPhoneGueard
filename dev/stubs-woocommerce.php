<?php
/**
 * Stand-in for the Store API exception the block checkout turns into an
 * error response. Separate file because namespaced stubs cannot share a
 * file with unbraced global-namespace code.
 */

namespace Automattic\WooCommerce\StoreApi\Exceptions;

class RouteException extends \Exception {

	public function __construct( $error_code = '', $message = '', $http_status_code = 400, $additional_data = array() ) {
		parent::__construct( $message, 0 );
	}
}
