<?php
/**
 * Premium ($25 pack) stub — no license-server calls, no payment code. Exists
 * so the free-tier settings page can show what premium unlocks without
 * shipping any of it yet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tetapi_Premium {

	/**
	 * Always false in this release — license validation is not implemented.
	 * Kept as a single choke point so a future task can wire up real
	 * validation without touching call sites.
	 */
	public static function is_licensed() {
		return false;
	}

	public static function render_locked_sections() {
		$sections = array(
			__( 'Badge style pack', 'tetapi' )           => __( '5 additional badge layouts + color/theme picker.', 'tetapi' ),
			__( 'Auto-insert placement', 'tetapi' )       => __( 'Show the badge in header/footer/post-end automatically, no shortcode needed.', 'tetapi' ),
			__( 'Multi-entity sites', 'tetapi' )          => __( 'Connect more than one TETA+PI entity to this site.', 'tetapi' ),
			__( 'WooCommerce integration', 'tetapi' )     => __( 'Show the badge on product, checkout, and order-email pages.', 'tetapi' ),
			__( 'Verification nudges', 'tetapi' )         => __( 'Dashboard reminders to complete email/registry verification.', 'tetapi' ),
			__( 'Priority badge refresh', 'tetapi' )      => __( 'Near-live trust level instead of the 15-minute cache.', 'tetapi' ),
		);
		?>
		<ul class="tetapi-premium-list">
			<?php foreach ( $sections as $title => $description ) : ?>
				<li class="tetapi-premium-list__item">
					<span class="tetapi-premium-list__lock" aria-hidden="true">🔒</span>
					<strong><?php echo esc_html( $title ); ?></strong>
					<span class="tetapi-premium-list__desc"><?php echo esc_html( $description ); ?></span>
					<span class="tetapi-premium-list__badge"><?php esc_html_e( 'Coming with Premium', 'tetapi' ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}
