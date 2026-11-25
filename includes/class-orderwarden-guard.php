<?php
/**
 * Checkout guard: decides whether a checkout request may continue.
 *
 * Channels covered:
 * - classic:   the classic checkout form (?wc-ajax=checkout) and express
 *              payment buttons that call WC_Checkout::process_checkout().
 * - store_api: the block checkout (POST /wc/store/v1/checkout and
 *              /wc/store/v1/checkout/{order_id}).
 * - pay:       the classic "Pay for order" page (order-pay endpoint).
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into every checkout entry point.
 */
class OrderWarden_Guard {

	const ARMED_OPTION = 'orderwarden_armed';

	/**
	 * Per-request cache for the trusted-customer lookup.
	 *
	 * @var bool|null
	 */
	private static $trusted = null;

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'guard_classic' ), 1 );
		add_action( 'woocommerce_before_pay_action', array( __CLASS__, 'guard_pay' ), 1 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'guard_store_api' ), 10, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Message shown to a blocked request. Deliberately says nothing about why.
	 *
	 * @return string
	 */
	public static function blocked_message() {
		return __( 'Sorry, we could not process your order. Please refresh the page and try again.', 'orderwarden' );
	}

	/**
	 * Classic checkout and express buttons.
	 *
	 * @return void
	 */
	public static function guard_classic() {
		if ( true !== self::check( 'classic', OrderWarden_Token::from_request() ) ) {
			wc_add_notice( self::blocked_message(), 'error' );
		}
	}

	/**
	 * Classic "Pay for order" page.
	 *
	 * @return void
	 */
	public static function guard_pay() {
		if ( true !== self::check( 'pay', OrderWarden_Token::from_request() ) ) {
			wc_add_notice( self::blocked_message(), 'error' );
		}
	}

	/**
	 * Block checkout via the Store API.
	 *
	 * @param mixed           $result  Response so far.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function guard_store_api( $result, $server, $request ) {
		if ( null !== $result || ! $request instanceof WP_REST_Request ) {
			return $result;
		}
		if ( 'POST' !== strtoupper( $request->get_method() ) ) {
			return $result;
		}
		if ( ! preg_match( '#^/wc/store(/v\d+)?/checkout(/\d+)?/?$#', $request->get_route() ) ) {
			return $result;
		}
		if ( true !== self::check( 'store_api', OrderWarden_Token::from_request( $request ) ) ) {
			return new WP_Error( 'orderwarden_blocked', self::blocked_message(), array( 'status' => 403 ) );
		}
		return $result;
	}

	/**
	 * Core decision.
	 *
	 * @param string $channel Channel name.
	 * @param string $token   Token sent with the request.
	 * @return true|string True to allow, or the reason it was blocked.
	 */
	public static function check( $channel, $token ) {
		$mode = OrderWarden_Settings::get( 'mode' );
		if ( 'off' === $mode ) {
			return true;
		}

		/**
		 * Let other code exempt a request (for example a custom headless checkout).
		 *
		 * @param bool   $skip    Whether to skip all checks.
		 * @param string $channel Channel name.
		 */
		if ( apply_filters( 'orderwarden_skip_check', false, $channel ) ) {
			return true;
		}

		$status = OrderWarden_Token::verify( $token );

		// A valid token proves the token script works on this checkout, whoever placed
		// the order (including a shop manager placing a test order), so arm the channel.
		if ( 'valid' === $status ) {
			self::arm( $channel );
		}

		if ( current_user_can( 'manage_woocommerce' ) || OrderWarden_Request::is_allowlisted() ) {
			return true;
		}

		$enforce = self::is_enforcing( $channel );

		// Repeat payment failures from this address.
		if ( OrderWarden_Velocity::is_blocked( OrderWarden_Request::ip() ) ) {
			if ( 'learning' === $mode ) {
				OrderWarden_Logger::log( 'would_block', 'velocity', $channel );
				return true;
			}
			self::record_block( 'velocity', $channel );
			return 'velocity';
		}

		if ( 'valid' === $status ) {
			OrderWarden_Logger::log( 'verified', '', $channel );
			return true;
		}

		if ( self::is_trusted_customer() ) {
			return true;
		}

		if ( ! $enforce ) {
			OrderWarden_Logger::log( 'would_block', $status, $channel );
			return true;
		}

		self::record_block( $status, $channel );
		return $status;
	}

	/**
	 * Log a block and fire alerts.
	 *
	 * @param string $reason  Reason.
	 * @param string $channel Channel.
	 * @return void
	 */
	private static function record_block( $reason, $channel ) {
		OrderWarden_Logger::log( 'blocked', $reason, $channel );
		OrderWarden_Alerts::on_block();

		/**
		 * Fires when OrderWarden turns a checkout request away.
		 *
		 * @param string $reason  Reason code.
		 * @param string $channel Channel name.
		 */
		do_action( 'orderwarden_blocked', $reason, $channel );
	}

	/**
	 * Whether a channel is currently enforcing (blocking) or only learning.
	 *
	 * @param string $channel Channel.
	 * @return bool
	 */
	public static function is_enforcing( $channel ) {
		$mode = OrderWarden_Settings::get( 'mode' );
		if ( 'protect' === $mode ) {
			return true;
		}
		if ( 'auto' === $mode ) {
			$armed = self::armed();
			return ! empty( $armed[ $channel ] );
		}
		return false;
	}

	/**
	 * Channels that have seen a genuine, token-carrying checkout.
	 *
	 * @return array channel => timestamp.
	 */
	public static function armed() {
		$armed = get_option( self::ARMED_OPTION, array() );
		return is_array( $armed ) ? $armed : array();
	}

	/**
	 * Mark a channel as working, so auto mode can start protecting it.
	 *
	 * @param string $channel Channel.
	 * @return void
	 */
	private static function arm( $channel ) {
		$armed = self::armed();
		if ( empty( $armed[ $channel ] ) ) {
			$armed[ $channel ] = time();
			update_option( self::ARMED_OPTION, $armed, false );
		}
	}

	/**
	 * Logged-in customer with at least one paid order.
	 *
	 * @return bool
	 */
	private static function is_trusted_customer() {
		if ( null !== self::$trusted ) {
			return self::$trusted;
		}
		self::$trusted = false;
		if ( OrderWarden_Settings::is_on( 'trust_customers' ) && is_user_logged_in() && function_exists( 'wc_get_orders' ) ) {
			$orders        = wc_get_orders(
				array(
					'customer_id' => get_current_user_id(),
					'status'      => wc_get_is_paid_statuses(),
					'limit'       => 1,
					'return'      => 'ids',
				)
			);
			self::$trusted = ! empty( $orders );
		}
		return self::$trusted;
	}

	/**
	 * REST route that hands a fresh token to the browser.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'orderwarden/v1',
			'/token',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_token' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Token endpoint callback.
	 *
	 * @return WP_REST_Response
	 */
	public static function rest_token() {
		$response = rest_ensure_response(
			array(
				'token' => OrderWarden_Token::issue(),
				'ttl'   => OrderWarden_Token::MAX_AGE,
			)
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	/**
	 * Load the small token script on store pages.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( 'off' === OrderWarden_Settings::get( 'mode' ) || ! function_exists( 'is_woocommerce' ) ) {
			return;
		}
		$load = is_woocommerce() || is_cart() || is_checkout() || is_account_page();

		/**
		 * Load the token script on every front-end page, for stores that show
		 * express payment buttons outside WooCommerce pages.
		 *
		 * @param bool $load Whether to load.
		 */
		if ( ! apply_filters( 'orderwarden_load_script', $load ) ) {
			return;
		}

		wp_enqueue_script(
			'orderwarden-token',
			ORDERWARDEN_URL . 'assets/js/orderwarden-token.js',
			array(),
			ORDERWARDEN_VERSION,
			false // In the head, so it is in place before checkout scripts send requests.
		);
		wp_localize_script(
			'orderwarden-token',
			'orderwardenData',
			array(
				'tokenUrl' => esc_url_raw( rest_url( 'orderwarden/v1/token' ) ),
				'field'    => OrderWarden_Token::FIELD,
				'header'   => OrderWarden_Token::HEADER,
				'refresh'  => 20 * MINUTE_IN_SECONDS * 1000,
			)
		);
	}
}
