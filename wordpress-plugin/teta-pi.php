<?php
/**
 * Plugin Name:       TETA+PI
 * Plugin URI:        https://tetapi.dev
 * Description:       Connect this site to a TETA+PI verified entity, prove domain ownership, and display a trust badge.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            TetaPi GmbH
 * Author URI:        https://tetapi.dev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        tetapi
 * Domain Path:        /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'TETAPI_VERSION', '1.0.0' );
define( 'TETAPI_PLUGIN_FILE', __FILE__ );
define( 'TETAPI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TETAPI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TETAPI_API_BASE', 'https://api.tetapi.dev/api/v1' );

require_once TETAPI_PLUGIN_DIR . 'includes/class-tetapi-api.php';
require_once TETAPI_PLUGIN_DIR . 'includes/class-tetapi-domain.php';
require_once TETAPI_PLUGIN_DIR . 'includes/class-tetapi-badge.php';
require_once TETAPI_PLUGIN_DIR . 'includes/class-tetapi-premium.php';
require_once TETAPI_PLUGIN_DIR . 'includes/class-tetapi-settings.php';
require_once TETAPI_PLUGIN_DIR . 'includes/class-tetapi-plugin.php';

Tetapi_Plugin::instance();
