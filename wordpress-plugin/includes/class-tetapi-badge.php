<?php
/**
 * Verified-entity badge: [tetapi_badge] shortcode + a matching widget.
 * Pulls the public by-slug payload (no auth) and caches it briefly so the
 * badge doesn't hit the API on every page view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tetapi_Badge {

	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	public function __construct() {
		add_shortcode( 'tetapi_badge', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
	}

	public function enqueue_assets() {
		wp_register_style( 'tetapi-badge', TETAPI_PLUGIN_URL . 'assets/badge.css', array(), TETAPI_VERSION );
	}

	public function register_widget() {
		register_widget( 'Tetapi_Badge_Widget' );
	}

	public static function shortcode( $atts ) {
		return self::render( $atts );
	}

	/**
	 * @return string Escaped HTML, safe to echo directly.
	 */
	public static function render( $atts ) {
		$entity_slug = get_option( 'tetapi_entity_slug', '' );
		if ( ! $entity_slug ) {
			return '';
		}

		wp_enqueue_style( 'tetapi-badge' );

		$profile = self::get_profile( $entity_slug );
		if ( ! $profile ) {
			return '';
		}

		$trust_level = isset( $profile['trust_level'] ) ? $profile['trust_level'] : 'none';
		$name        = isset( $profile['name'] ) ? $profile['name'] : $entity_slug;
		$legal       = isset( $profile['legal_entity'] ) ? $profile['legal_entity'] : null;
		$profile_url = 'https://app.tetapi.dev/e/' . rawurlencode( $entity_slug );

		ob_start();
		?>
		<a class="tetapi-badge tetapi-badge--<?php echo esc_attr( $trust_level ); ?>" href="<?php echo esc_url( $profile_url ); ?>" target="_blank" rel="noopener noreferrer">
			<span class="tetapi-badge__icon" aria-hidden="true">✓</span>
			<span class="tetapi-badge__label">
				<?php
				/* translators: %s: entity name */
				printf( esc_html__( 'Verified by TETA+PI: %s', 'tetapi' ), esc_html( $name ) );
				?>
			</span>
			<?php if ( $legal && ! empty( $legal['name'] ) ) : ?>
				<span class="tetapi-badge__legal">
					<?php
					/* translators: %s: legal entity name */
					printf( esc_html__( '(%s)', 'tetapi' ), esc_html( $legal['name'] ) );
					?>
				</span>
			<?php endif; ?>
		</a>
		<?php
		return ob_get_clean();
	}

	private static function get_profile( $slug ) {
		$cache_key = 'tetapi_badge_' . md5( $slug );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$profile = Tetapi_Api::get_public_profile( $slug );
		if ( is_wp_error( $profile ) ) {
			return null;
		}

		set_transient( $cache_key, $profile, self::CACHE_TTL );
		return $profile;
	}
}

class Tetapi_Badge_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'tetapi_badge_widget',
			__( 'TETA+PI Badge', 'tetapi' ),
			array( 'description' => __( 'Displays your TETA+PI verified-entity badge.', 'tetapi' ) )
		);
	}

	public function widget( $args, $instance ) {
		$output = Tetapi_Badge::render( array() );
		if ( ! $output ) {
			return;
		}
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-provided wrapper markup.
		echo wp_kses_post( $output );
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-provided wrapper markup.
	}

	public function form( $instance ) {
		echo '<p>' . esc_html__( 'No settings — connect your entity under Settings > TETA+PI.', 'tetapi' ) . '</p>';
	}
}
