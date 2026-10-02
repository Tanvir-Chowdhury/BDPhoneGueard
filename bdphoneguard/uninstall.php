<?php
/**
 * Removes the plugin's options on uninstall.
 *
 * @package BDPhoneGuard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	$bdpg_site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

	foreach ( $bdpg_site_ids as $bdpg_site_id ) {
		switch_to_blog( $bdpg_site_id );
		delete_option( 'bdpg_options' );
		restore_current_blog();
	}
} else {
	delete_option( 'bdpg_options' );
}
