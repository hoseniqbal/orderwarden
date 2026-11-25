<?php
/**
 * Checkout proof tokens.
 *
 * A token is issued to the shopper's browser (fetched by JavaScript after the
 * page loads, so page caching never serves a stale one) and must come back with
 * the checkout request. Scripts that post straight to the checkout endpoints
 * never load the page and so carry no valid token.
 *
 * Format: {issued}.{random}.{ua}.{signature}
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Issues and verifies tokens.
 */
class OrderWarden_Token {

	const SECRET_OPTION = 'orderwarden_secret';
	const FIELD         = 'orderwarden_token';
	const HEADER        = 'X-OrderWarden-Token';
	const MIN_AGE       = 1;            // Seconds. A human cannot load a page and pay in under a second.
	const MAX_AGE       = 21600;        // Six hours.

	/**
	 * Secret key used to sign tokens.
	 *
	 * @return string
	 */
	private static function key() {
		$secret = get_option( self::SECRET_OPTION );
		if ( ! is_string( $secret ) || strlen( $secret ) < 32 ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( self::SECRET_OPTION, $secret, false );
		}
		return wp_salt( 'nonce' ) . $secret;
	}

	/**
	 * Short fingerprint of the user agent, so a token cannot be farmed by one
	 * client and replayed by another.
	 *
	 * @param string $ua User agent.
	 * @return string
	 */
	private static function ua_fingerprint( $ua ) {
		return substr( hash( 'sha256', $ua ), 0, 10 );
	}

	/**
	 * Create a new token.
	 *
	 * @param int|null $now Unix time (for tests).
	 * @return string
	 */
	public static function issue( $now = null ) {
		$issued = (string) ( null === $now ? time() : (int) $now );
		$random = bin2hex( random_bytes( 8 ) );
		$ua     = self::ua_fingerprint( OrderWarden_Request::user_agent() );
		$data   = $issued . '.' . $random . '.' . $ua;
		return $data . '.' . hash_hmac( 'sha256', $data, self::key() );
	}

	/**
	 * Verify a token.
	 *
	 * @param string   $token Token string.
	 * @param int|null $now   Unix time (for tests).
	 * @return string 'valid' or a failure reason.
	 */
	public static function verify( $token, $now = null ) {
		$now = null === $now ? time() : (int) $now;

		if ( ! is_string( $token ) || '' === $token ) {
			return 'missing';
		}
		$parts = explode( '.', $token );
		if ( 4 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return 'malformed';
		}
		list( $issued, $random, $ua, $signature ) = $parts;

		$expected = hash_hmac( 'sha256', $issued . '.' . $random . '.' . $ua, self::key() );
		if ( ! hash_equals( $expected, $signature ) ) {
			return 'bad_signature';
		}
		$age = $now - (int) $issued;
		if ( $age > self::MAX_AGE ) {
			return 'expired';
		}
		if ( $age < self::MIN_AGE ) {
			return 'too_fast';
		}
		if ( ! hash_equals( self::ua_fingerprint( OrderWarden_Request::user_agent() ), $ua ) ) {
			return 'agent_mismatch';
		}
		return 'valid';
	}

	/**
	 * Read the token sent with the current request (header first, then POST field).
	 *
	 * @param WP_REST_Request|null $request Optional REST request.
	 * @return string
	 */
	public static function from_request( $request = null ) {
		if ( $request instanceof WP_REST_Request ) {
			$header = $request->get_header( 'x_orderwarden_token' );
			if ( is_string( $header ) && '' !== $header ) {
				return sanitize_text_field( $header );
			}
		}
		if ( ! empty( $_SERVER['HTTP_X_ORDERWARDEN_TOKEN'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ORDERWARDEN_TOKEN'] ) );
		}
		// WooCommerce has already verified its own nonce for the checkout and pay forms
		// before our hooks run; this field only carries our signed token.
		if ( ! empty( $_POST[ self::FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		return '';
	}
}
