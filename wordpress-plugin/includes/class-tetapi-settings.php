<?php
/**
 * Settings > TETA+PI admin page: connect via pk_live_ API key, pick the
 * entity, view domain verification status, preview the badge, and (premium)
 * enter a license key.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tetapi_Settings {

	const OPTION_GROUP = 'tetapi_settings';
	const PAGE_SLUG    = 'tetapi';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public static function page_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
	}

	public static function get_api_key() {
		$stored = get_option( 'tetapi_api_key_enc', '' );
		return $stored ? self::decrypt( $stored ) : '';
	}

	private static function encrypt( $value ) {
		if ( ! $value || ! function_exists( 'openssl_encrypt' ) ) {
			return $value;
		}
		$key = wp_salt( 'auth' );
		$iv  = substr( hash( 'sha256', $key ), 0, 16 );
		return base64_encode( openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv ) ); // phpcs:ignore
	}

	private static function decrypt( $value ) {
		if ( ! $value || ! function_exists( 'openssl_decrypt' ) ) {
			return $value;
		}
		$key = wp_salt( 'auth' );
		$iv  = substr( hash( 'sha256', $key ), 0, 16 );
		$out = openssl_decrypt( base64_decode( $value ), 'AES-256-CBC', $key, 0, $iv ); // phpcs:ignore
		return false === $out ? '' : $out;
	}

	public function add_menu() {
		add_options_page(
			__( 'TETA+PI', 'tetapi' ),
			__( 'TETA+PI', 'tetapi' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'tetapi-admin', TETAPI_PLUGIN_URL . 'assets/admin.css', array(), TETAPI_VERSION );
	}

	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			'tetapi_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'tetapi_entity_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_entity_id' ),
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'tetapi_license_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
	}

	/**
	 * The API key field is a write-only "virtual" option: we never echo the
	 * real value back into a rendered input, we only ever store it encrypted.
	 */
	public function sanitize_api_key( $value ) {
		$value = sanitize_text_field( $value );

		if ( '' === $value ) {
			// Blank means "keep the existing key" — the field is intentionally
			// never pre-filled with the real value.
			return get_option( 'tetapi_api_key', '' );
		}

		update_option( 'tetapi_api_key_enc', self::encrypt( $value ) );
		delete_transient( 'tetapi_businesses' );

		return substr( $value, 0, 8 ) . str_repeat( '•', 12 );
	}

	public function sanitize_entity_id( $value ) {
		$value = sanitize_text_field( $value );
		if ( '' === $value ) {
			return get_option( 'tetapi_entity_id', '' );
		}

		foreach ( $this->get_businesses() as $business ) {
			if ( isset( $business['id'] ) && $business['id'] === $value ) {
				update_option( 'tetapi_entity_slug', sanitize_text_field( $business['slug'] ) );
				update_option( 'tetapi_entity_name', sanitize_text_field( $business['name'] ) );
				// Entity changed — any prior domain verification no longer applies.
				update_option( 'tetapi_domain_status', 'none' );
				break;
			}
		}

		return $value;
	}

	private function get_businesses() {
		$api_key = self::get_api_key();
		if ( ! $api_key ) {
			return array();
		}

		$cached = get_transient( 'tetapi_businesses' );
		if ( false !== $cached ) {
			return $cached;
		}

		$result = Tetapi_Api::get_businesses( $api_key );
		if ( is_wp_error( $result ) ) {
			return array();
		}

		set_transient( 'tetapi_businesses', $result, 5 * MINUTE_IN_SECONDS );
		return $result;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$api_key       = self::get_api_key();
		$businesses    = $this->get_businesses();
		$entity_id     = get_option( 'tetapi_entity_id', '' );
		$entity_slug   = get_option( 'tetapi_entity_slug', '' );
		$domain_status = get_option( 'tetapi_domain_status', 'none' );
		$domain        = get_option( 'tetapi_domain', '' );
		$status_msg    = isset( $_GET['tetapi_status'] ) ? sanitize_key( wp_unslash( $_GET['tetapi_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		require TETAPI_PLUGIN_DIR . 'includes/views/settings-page.php';
	}
}
