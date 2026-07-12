<?php
/**
 * Domain Ownership verification: serves /.well-known/tetapi-verify.txt via a
 * rewrite rule, and drives the start/check calls against the API (same
 * mechanism described in docs/verification-rework.md §2).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tetapi_Domain {

	const QUERY_VAR = 'tetapi_verify_file';

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_verify_file' ) );
		add_action( 'admin_post_tetapi_domain_start', array( $this, 'handle_start' ) );
		add_action( 'admin_post_tetapi_domain_check', array( $this, 'handle_check' ) );
	}

	public static function register_rewrite_rule() {
		add_rewrite_rule(
			'^\.well-known/tetapi-verify\.txt$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	public static function activate() {
		self::register_rewrite_rule();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_serve_verify_file() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$token = get_option( 'tetapi_domain_token', '' );

		status_header( $token ? 200 : 404 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $token );
		exit;
	}

	private function current_domain() {
		return (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	public function handle_start() {
		$this->guard();

		$api_key   = Tetapi_Settings::get_api_key();
		$entity_id = get_option( 'tetapi_entity_id', '' );
		$domain    = $this->current_domain();

		if ( ! $api_key || ! $entity_id ) {
			$this->redirect_back( 'missing_entity' );
		}

		$result = Tetapi_Api::start_domain_verification( $api_key, $entity_id, $domain );

		if ( is_wp_error( $result ) ) {
			$this->redirect_back( 'start_failed' );
		}

		update_option( 'tetapi_domain_token', isset( $result['token'] ) ? sanitize_text_field( $result['token'] ) : '' );
		update_option( 'tetapi_domain', $domain );
		update_option( 'tetapi_domain_status', 'pending' );

		$this->redirect_back( 'started' );
	}

	public function handle_check() {
		$this->guard();

		$api_key   = Tetapi_Settings::get_api_key();
		$entity_id = get_option( 'tetapi_entity_id', '' );
		$domain    = get_option( 'tetapi_domain', $this->current_domain() );

		if ( ! $api_key || ! $entity_id ) {
			$this->redirect_back( 'missing_entity' );
		}

		$result = Tetapi_Api::check_domain_verification( $api_key, $entity_id, $domain );

		if ( is_wp_error( $result ) ) {
			$this->redirect_back( 'check_failed' );
		}

		if ( ! empty( $result['verified'] ) ) {
			update_option( 'tetapi_domain_status', 'verified' );
			update_option( 'tetapi_domain_method', isset( $result['method'] ) ? sanitize_text_field( $result['method'] ) : '' );
			update_option( 'tetapi_domain_verified_at', current_time( 'mysql' ) );
			$this->redirect_back( 'verified' );
		}

		$this->redirect_back( 'not_yet' );
	}

	private function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'tetapi' ) );
		}
		check_admin_referer( 'tetapi_domain_action' );
	}

	private function redirect_back( $status ) {
		wp_safe_redirect( add_query_arg( array( 'tetapi_status' => $status ), Tetapi_Settings::page_url() ) );
		exit;
	}
}
