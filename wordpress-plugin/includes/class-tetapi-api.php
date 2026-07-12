<?php
/**
 * Thin HTTP client for api.tetapi.dev — no external HTTP libraries, WP core only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tetapi_Api {

	/**
	 * List the businesses owned by the account behind this API key.
	 *
	 * @return array|WP_Error List of business objects on success.
	 */
	public static function get_businesses( $api_key ) {
		$response = self::request( 'GET', '/businesses', $api_key );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return $response;
	}

	/**
	 * Public entity payload — no auth required.
	 *
	 * @return array|WP_Error
	 */
	public static function get_public_profile( $slug ) {
		return self::request( 'GET', '/businesses/by-slug/' . rawurlencode( $slug ) . '/public', null );
	}

	/**
	 * Domain ownership, step 1 — request a token + instructions.
	 *
	 * @return array|WP_Error
	 */
	public static function start_domain_verification( $api_key, $entity_id, $domain ) {
		return self::request(
			'POST',
			'/businesses/' . rawurlencode( $entity_id ) . '/verify/domain/start',
			$api_key,
			array( 'domain' => $domain )
		);
	}

	/**
	 * Domain ownership, step 2 — check the DNS TXT record / well-known file.
	 *
	 * @return array|WP_Error
	 */
	public static function check_domain_verification( $api_key, $entity_id, $domain ) {
		return self::request(
			'POST',
			'/businesses/' . rawurlencode( $entity_id ) . '/verify/domain/check',
			$api_key,
			array( 'domain' => $domain )
		);
	}

	/**
	 * @param string      $method  GET|POST
	 * @param string      $path    Path relative to TETAPI_API_BASE.
	 * @param string|null $api_key Bearer token (pk_live_... or none for public endpoints).
	 * @param array|null  $body    JSON body for POST requests.
	 *
	 * @return array|WP_Error Decoded JSON body on success.
	 */
	private static function request( $method, $path, $api_key = null, $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 10,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);

		if ( $api_key ) {
			$args['headers']['Authorization'] = 'Bearer ' . $api_key;
		}

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$url = TETAPI_API_BASE . $path;

		$response = ( 'GET' === $method )
			? wp_remote_get( $url, $args )
			: wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) && isset( $data['detail'] ) ? $data['detail'] : __( 'Unexpected error from TETA+PI API.', 'tetapi' );
			return new WP_Error( 'tetapi_api_error', $message, array( 'status' => $code ) );
		}

		return is_array( $data ) ? $data : array();
	}
}
