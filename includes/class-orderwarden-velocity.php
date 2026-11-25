<?php
/**
 * Failed-payment velocity limit.
 *
 * Bots that drive a real browser get a valid token. They still give themselves
 * away by failing payment after payment from the same address, so after N failed
 * orders in a short window that address is turned away for a while.
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts failed payments per IP and blocks repeat offenders.
 */
class OrderWarden_Velocity {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'on_failed_order' ), 10, 2 );
	}

	/**
	 * Transient key for the failure list of an IP.
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	private static function fail_key( $ip ) {
		return 'orderwarden_vf_' . substr( OrderWarden_Request::ip_hash( $ip ), 0, 32 );
	}

	/**
	 * Transient key for the block flag of an IP.
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	private static function block_key( $ip ) {
		return 'orderwarden_vb_' . substr( OrderWarden_Request::ip_hash( $ip ), 0, 32 );
	}

	/**
	 * Record a failed order.
	 *
	 * @param int           $order_id Order ID.
	 * @param WC_Order|null $order    Order.
	 * @return void
	 */
	public static function on_failed_order( $order_id, $order = null ) {
		if ( ! OrderWarden_Settings::is_on( 'velocity_enabled' ) ) {
			return;
		}
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$ip = $order->get_customer_ip_address();
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return;
		}

		$window = (int) OrderWarden_Settings::get( 'velocity_window' ) * MINUTE_IN_SECONDS;
		$limit  = (int) OrderWarden_Settings::get( 'velocity_limit' );
		$now    = time();

		$fails   = get_transient( self::fail_key( $ip ) );
		$fails   = is_array( $fails ) ? $fails : array();
		$fails   = array_values(
			array_filter(
				$fails,
				static function ( $t ) use ( $now, $window ) {
					return ( $now - (int) $t ) < $window;
				}
			)
		);
		$fails[] = $now;
		set_transient( self::fail_key( $ip ), $fails, $window );

		if ( count( $fails ) >= $limit ) {
			$block = (int) OrderWarden_Settings::get( 'velocity_block' ) * MINUTE_IN_SECONDS;
			set_transient( self::block_key( $ip ), $now + $block, $block );
		}
	}

	/**
	 * Whether an IP is currently blocked.
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_blocked( $ip ) {
		if ( ! OrderWarden_Settings::is_on( 'velocity_enabled' ) ) {
			return false;
		}
		$until = (int) get_transient( self::block_key( $ip ) );
		return $until > time();
	}
}
