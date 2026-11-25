<?php
/**
 * Email alert when an attack is under way.
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends at most one alert per hour when blocks spike.
 */
class OrderWarden_Alerts {

	const WINDOW = 600; // Ten minutes.

	/**
	 * Called every time a request is blocked.
	 *
	 * @return void
	 */
	public static function on_block() {
		if ( ! OrderWarden_Settings::is_on( 'alert_enabled' ) ) {
			return;
		}

		$bucket = 'orderwarden_burst_' . (int) floor( time() / self::WINDOW );
		$count  = (int) get_transient( $bucket ) + 1;
		set_transient( $bucket, $count, 2 * self::WINDOW );

		$threshold = (int) OrderWarden_Settings::get( 'alert_threshold' );
		if ( $count < $threshold || get_transient( 'orderwarden_alert_sent' ) ) {
			return;
		}
		set_transient( 'orderwarden_alert_sent', 1, HOUR_IN_SECONDS );
		self::send( $count );
	}

	/**
	 * Send the alert email.
	 *
	 * @param int $count Blocks in the current window.
	 * @return void
	 */
	private static function send( $count ) {
		$to = OrderWarden_Settings::get( 'alert_email' );
		if ( empty( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		/* translators: %s: site name */
		$subject = sprintf( __( '[%s] OrderWarden is blocking a card-testing attack', 'orderwarden' ), $site );

		$lines   = array();
		$lines[] = sprintf(
			/* translators: %d: number of blocked checkout attempts */
			__( 'OrderWarden has blocked %d checkout attempts in the last 10 minutes. This usually means a bot is testing stolen cards on your store.', 'orderwarden' ),
			(int) $count
		);
		$lines[] = '';
		$lines[] = __( 'The attempts were stopped before WooCommerce created an order or contacted your payment gateway. You do not need to do anything right now.', 'orderwarden' );
		$lines[] = '';
		$lines[] = __( 'Good next steps:', 'orderwarden' );
		$lines[] = '- ' . __( 'Check the OrderWarden dashboard for details.', 'orderwarden' );
		$lines[] = '- ' . __( 'Turn on the protections in your payment gateway listed on the OrderWarden Checklist tab.', 'orderwarden' );
		$lines[] = '';
		$lines[] = admin_url( 'admin.php?page=orderwarden' );
		$lines[] = '';
		$lines[] = __( 'You will get at most one of these emails per hour.', 'orderwarden' );

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}
}
