<?php
/**
 * Fires only on actual uninstall (not deactivation) — removes all plugin
 * options so nothing is left orphaned in wp_options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$tetapi_options = array(
	'tetapi_api_key',
	'tetapi_api_key_enc',
	'tetapi_entity_id',
	'tetapi_entity_slug',
	'tetapi_entity_name',
	'tetapi_domain',
	'tetapi_domain_token',
	'tetapi_domain_status',
	'tetapi_domain_method',
	'tetapi_domain_verified_at',
	'tetapi_license_key',
);

foreach ( $tetapi_options as $tetapi_option ) {
	delete_option( $tetapi_option );
}

delete_transient( 'tetapi_businesses' );
