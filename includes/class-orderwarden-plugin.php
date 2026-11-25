<?php
/**
 * Plugin bootstrap.
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires everything together.
 */
class OrderWarden_Plugin {

	const CRON_HOOK = 'orderwarden_daily';

	/**
	 * Singleton.
	 *
	 * @var OrderWarden_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return OrderWarden_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'missing_woocommerce_notice' ) );
			return;
		}

		if ( get_option( 'orderwarden_db_version' ) !== ORDERWARDEN_DB_VERSION ) {
			OrderWarden_Logger::install();
		}

		OrderWarden_Guard::init();
		OrderWarden_Velocity::init();
		OrderWarden_Cleaner::init();
		add_action( self::CRON_HOOK, array( 'OrderWarden_Logger', 'purge' ) );

		if ( is_admin() ) {
			OrderWarden_Admin::init();
		}
	}

	/**
	 * Notice when WooCommerce is not active.
	 *
	 * @return void
	 */
	public static function missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'OrderWarden needs WooCommerce to be installed and active.', 'orderwarden' ) . '</p></div>';
	}

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public static function activate() {
		OrderWarden_Logger::install();
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( OrderWarden_Cleaner::HOOK, array(), OrderWarden_Cleaner::GROUP );
		}
	}
}
