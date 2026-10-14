<?php
/**
 * Removes the plugin's options on uninstall.
 *
 * @package Nombar
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	$nombar_site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

	foreach ( $nombar_site_ids as $nombar_site_id ) {
		switch_to_blog( $nombar_site_id );
		delete_option( 'nombar_options' );
		restore_current_blog();
	}
} else {
	delete_option( 'nombar_options' );
}
