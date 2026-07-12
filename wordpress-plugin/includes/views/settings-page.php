<?php
/**
 * Settings > TETA+PI view. Variables provided by Tetapi_Settings::render_page():
 * $api_key, $businesses, $entity_id, $entity_slug, $domain_status, $domain, $status_msg.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_messages = array(
	'started'        => __( 'Verification started — add the DNS record or wait a moment for the well-known file, then click Check.', 'tetapi' ),
	'verified'       => __( 'Domain ownership verified.', 'tetapi' ),
	'not_yet'        => __( 'Not verified yet — the DNS record or well-known file was not found. Try again in a minute.', 'tetapi' ),
	'start_failed'   => __( 'Could not start verification. Check your API key and entity.', 'tetapi' ),
	'check_failed'   => __( 'Could not check verification right now. Try again shortly.', 'tetapi' ),
	'missing_entity' => __( 'Connect your API key and choose an entity first.', 'tetapi' ),
);
?>
<div class="wrap tetapi-settings">
	<h1><?php esc_html_e( 'TETA+PI', 'tetapi' ); ?></h1>

	<?php if ( $status_msg && isset( $status_messages[ $status_msg ] ) ) : ?>
		<div class="notice <?php echo 'verified' === $status_msg ? 'notice-success' : 'notice-info'; ?>">
			<p><?php echo esc_html( $status_messages[ $status_msg ] ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( '1. Connect your entity', 'tetapi' ); ?></h2>
	<form method="post" action="options.php">
		<?php settings_fields( Tetapi_Settings::OPTION_GROUP ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="tetapi_api_key"><?php esc_html_e( 'Personal API Key', 'tetapi' ); ?></label></th>
				<td>
					<input type="password" id="tetapi_api_key" name="tetapi_api_key" class="regular-text" placeholder="<?php echo $api_key ? esc_attr__( 'Connected — leave blank to keep', 'tetapi' ) : 'pk_live_…'; ?>" autocomplete="off" />
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to app.tetapi.dev account settings */
							esc_html__( 'Find your key under Account > API Key at %s.', 'tetapi' ),
							'<a href="https://app.tetapi.dev/settings" target="_blank" rel="noopener noreferrer">app.tetapi.dev</a>'
						);
						?>
					</p>
				</td>
			</tr>
			<?php if ( $api_key ) : ?>
			<tr>
				<th scope="row"><label for="tetapi_entity_id"><?php esc_html_e( 'Entity', 'tetapi' ); ?></label></th>
				<td>
					<?php if ( empty( $businesses ) ) : ?>
						<p class="description"><?php esc_html_e( 'No entities found for this API key yet — create one at app.tetapi.dev first.', 'tetapi' ); ?></p>
					<?php else : ?>
						<select id="tetapi_entity_id" name="tetapi_entity_id">
							<option value=""><?php esc_html_e( '— choose —', 'tetapi' ); ?></option>
							<?php foreach ( $businesses as $business ) : ?>
								<option value="<?php echo esc_attr( $business['id'] ); ?>" <?php selected( $entity_id, $business['id'] ); ?>>
									<?php echo esc_html( $business['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php submit_button( __( 'Save', 'tetapi' ) ); ?>
	</form>

	<?php if ( $entity_id && $entity_slug ) : ?>

		<h2><?php esc_html_e( '2. Domain ownership', 'tetapi' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: site domain */
				esc_html__( 'Status for %s:', 'tetapi' ),
				esc_html( $domain ? $domain : wp_parse_url( home_url(), PHP_URL_HOST ) )
			);
			?>
			<strong>
			<?php
			if ( 'verified' === $domain_status ) {
				esc_html_e( 'Verified', 'tetapi' );
			} elseif ( 'pending' === $domain_status ) {
				esc_html_e( 'Pending', 'tetapi' );
			} else {
				esc_html_e( 'Not verified', 'tetapi' );
			}
			?>
			</strong>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
			<?php wp_nonce_field( 'tetapi_domain_action' ); ?>
			<input type="hidden" name="action" value="tetapi_domain_start" />
			<?php submit_button( __( 'Start verification', 'tetapi' ), 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
			<?php wp_nonce_field( 'tetapi_domain_action' ); ?>
			<input type="hidden" name="action" value="tetapi_domain_check" />
			<?php submit_button( __( 'Check now', 'tetapi' ), 'secondary', 'submit', false ); ?>
		</form>

		<h2><?php esc_html_e( '3. Badge', 'tetapi' ); ?></h2>
		<p><?php esc_html_e( 'Add the badge anywhere with the shortcode, or use the "TETA+PI Badge" widget.', 'tetapi' ); ?></p>
		<code>[tetapi_badge]</code>
		<div class="tetapi-badge-preview">
			<?php echo Tetapi_Badge::render( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside render(). ?>
		</div>

	<?php endif; ?>

	<h2><?php esc_html_e( '4. Premium', 'tetapi' ); ?></h2>
	<form method="post" action="options.php">
		<?php settings_fields( Tetapi_Settings::OPTION_GROUP ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="tetapi_license_key"><?php esc_html_e( 'License Key', 'tetapi' ); ?></label></th>
				<td>
					<input type="text" id="tetapi_license_key" name="tetapi_license_key" class="regular-text" value="<?php echo esc_attr( get_option( 'tetapi_license_key', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Enter your $25 Premium Pack license key', 'tetapi' ); ?>" />
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save License Key', 'tetapi' ) ); ?>
	</form>

	<?php Tetapi_Premium::render_locked_sections(); ?>

</div>
