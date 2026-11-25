<?php
/**
 * Request helpers: client IP, user agent, IP matching.
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small helpers about the current HTTP request.
 */
class OrderWarden_Request {

	/**
	 * Client IP address. Uses REMOTE_ADDR unless proxy headers are enabled.
	 *
	 * @return string
	 */
	public static function ip() {
		$candidates = array();

		if ( OrderWarden_Settings::is_on( 'use_proxy_headers' ) ) {
			$candidates[] = 'HTTP_CF_CONNECTING_IP';
			$candidates[] = 'HTTP_X_REAL_IP';
			$candidates[] = 'HTTP_X_FORWARDED_FOR';
		}
		$candidates[] = 'REMOTE_ADDR';

		foreach ( $candidates as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$raw = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			// X-Forwarded-For may hold a list; the first entry is the client.
			$first = trim( explode( ',', $raw )[0] );
			if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
				return $first;
			}
		}
		return '0.0.0.0';
	}

	/**
	 * User agent string (sanitized, trimmed).
	 *
	 * @return string
	 */
	public static function user_agent() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}
		return substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 );
	}

	/**
	 * Mask an IP for display and storage: 203.0.113.45 -> 203.0.113.x
	 *
	 * @param string $ip IP address.
	 * @return string
	 */
	public static function mask_ip( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = 'x';
			return implode( '.', $parts );
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $ip );
			return implode( ':', array_slice( $parts, 0, 3 ) ) . ':x';
		}
		return 'unknown';
	}

	/**
	 * Keyed hash of an IP, used for counters without storing the raw IP.
	 *
	 * @param string $ip IP address.
	 * @return string
	 */
	public static function ip_hash( $ip ) {
		return hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
	}

	/**
	 * Validate a single IP or CIDR range.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_valid_ip_or_cidr( $value ) {
		if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return true;
		}
		if ( false === strpos( $value, '/' ) ) {
			return false;
		}
		list( $subnet, $bits ) = explode( '/', $value, 2 );
		if ( ! ctype_digit( $bits ) || ! filter_var( $subnet, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		$max = filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ? 32 : 128;
		return (int) $bits >= 0 && (int) $bits <= $max;
	}

	/**
	 * Whether an IP is inside an IP or CIDR range.
	 *
	 * @param string $ip    IP to test.
	 * @param string $range IP or CIDR.
	 * @return bool
	 */
	public static function ip_in_range( $ip, $range ) {
		if ( false === strpos( $range, '/' ) ) {
			return $ip === $range;
		}
		list( $subnet, $bits ) = explode( '/', $range, 2 );
		$ip_bin     = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input returns false.
		$subnet_bin = @inet_pton( $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
			return false;
		}
		$bits  = (int) $bits;
		$bytes = intdiv( $bits, 8 );
		$rest  = $bits % 8;
		if ( $bytes && substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
			return false;
		}
		if ( $rest ) {
			$mask = chr( ( 0xff << ( 8 - $rest ) ) & 0xff );
			return ( $ip_bin[ $bytes ] & $mask ) === ( $subnet_bin[ $bytes ] & $mask );
		}
		return true;
	}

	/**
	 * Whether the current IP is on the allowlist.
	 *
	 * @return bool
	 */
	public static function is_allowlisted() {
		$list = (string) OrderWarden_Settings::get( 'ip_allowlist' );
		if ( '' === $list ) {
			return false;
		}
		$ip = self::ip();
		foreach ( explode( "\n", $list ) as $range ) {
			$range = trim( $range );
			if ( '' !== $range && self::ip_in_range( $ip, $range ) ) {
				return true;
			}
		}
		return false;
	}
}
