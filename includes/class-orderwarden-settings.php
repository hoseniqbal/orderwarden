<?php
/**
 * Settings storage and defaults.
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and sanitizes the plugin settings.
 */
class OrderWarden_Settings {

	const OPTION = 'orderwarden_settings';

	/**
	 * Cached settings for the current request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// auto = learn until a verified checkout is seen on a channel, then protect it.
			'mode'                => 'auto',
			'velocity_enabled'    => 'yes',
			'velocity_limit'      => 3,
			'velocity_window'     => 10,
			'velocity_block'      => 60,
			'trust_customers'     => 'yes',
			'use_proxy_headers'   => 'no',
			'ip_allowlist'        => '',
			'alert_enabled'       => 'yes',
			'alert_email'         => '',
			'alert_threshold'     => 20,
			'log_retention_days'  => 30,
			'delete_data'         => 'no',
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Whether a yes/no setting is on.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function is_on( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * Clear the request cache (after saving).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Sanitize callback for register_setting().
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		$modes       = array( 'auto', 'learning', 'protect', 'off' );
		$out['mode'] = ( isset( $input['mode'] ) && in_array( $input['mode'], $modes, true ) ) ? $input['mode'] : $defaults['mode'];

		foreach ( array( 'velocity_enabled', 'trust_customers', 'use_proxy_headers', 'alert_enabled', 'delete_data' ) as $key ) {
			$out[ $key ] = ( isset( $input[ $key ] ) && 'yes' === $input[ $key ] ) ? 'yes' : 'no';
		}

		$ranges = array(
			'velocity_limit'     => array( 1, 50 ),
			'velocity_window'    => array( 1, 1440 ),
			'velocity_block'     => array( 1, 10080 ),
			'alert_threshold'    => array( 5, 10000 ),
			'log_retention_days' => array( 1, 365 ),
		);
		foreach ( $ranges as $key => $range ) {
			$value       = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $defaults[ $key ];
			$out[ $key ] = min( $range[1], max( $range[0], $value ) );
		}

		$email              = isset( $input['alert_email'] ) ? sanitize_email( wp_unslash( $input['alert_email'] ) ) : '';
		$out['alert_email'] = is_email( $email ) ? $email : '';

		$lines = isset( $input['ip_allowlist'] ) ? explode( "\n", sanitize_textarea_field( wp_unslash( $input['ip_allowlist'] ) ) ) : array();
		$clean = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' !== $line && OrderWarden_Request::is_valid_ip_or_cidr( $line ) ) {
				$clean[] = $line;
			}
		}
		$out['ip_allowlist'] = implode( "\n", array_unique( $clean ) );

		self::flush();
		return $out;
	}
}
