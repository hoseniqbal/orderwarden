<?php
/**
 * Plugin Name:       OrderWarden – Stop Card Testing & Fake Orders for WooCommerce
 * Plugin URI:        https://github.com/hoseniqbal/orderwarden
 * Description:       Stops card-testing bots and fake orders at the WooCommerce checkout without CAPTCHA, and cleans up thousands of failed orders in one click. Works with every payment gateway.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Iqbal Hossain
 * Author URI:        https://github.com/hoseniqbal
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       orderwarden
 * WC requires at least: 7.0
 * WC tested up to:   10.8
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ORDERWARDEN_VERSION', '1.0.0' );
define( 'ORDERWARDEN_DB_VERSION', '1' );
define( 'ORDERWARDEN_FILE', __FILE__ );
define( 'ORDERWARDEN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ORDERWARDEN_URL', plugin_dir_url( __FILE__ ) );

require_once ORDERWARDEN_PATH . 'includes/class-orderwarden-settings.php';
require_once ORDERWARDEN_PATH . 'includes/class-orderwarden-request.php';
require_once ORDERWARDEN_PATH . 'includes/class-orderwarden-token.php';
require_once ORDERWARDEN_PATH . 'includes/class-orderwarden-logger.php';
require_once ORDERWARDEN_PATH . 'includes/class-orderwarden-alerts.php';
require_once ORDERWARDEN_PATH . 'includes/class-orderwarden-velocity.php';
require_once ORDERWARDEN_PATH . 'includes/class-orderwarden-guard.php';
require_once ORDERWARDEN_PATH . 'includes/class-orderwarden-cleaner.php';
require_once ORDERWARDEN_PATH . 'includes/class-orderwarden-admin.php';
require_once ORDERWARDEN_PATH . 'includes/class-orderwarden-plugin.php';

register_activation_hook( __FILE__, array( 'OrderWarden_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OrderWarden_Plugin', 'deactivate' ) );

/**
 * Declare compatibility with WooCommerce HPOS and Cart/Checkout Blocks.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'OrderWarden_Plugin', 'instance' ) );
