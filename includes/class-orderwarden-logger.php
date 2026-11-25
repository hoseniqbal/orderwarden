<?php
/**
 * Event log stored in a small custom table.
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes and reads checkout protection events.
 */
class OrderWarden_Logger {

	const STATS_OPTION = 'orderwarden_stats';

	/**
	 * Most events written per minute; a large attack is counted, not logged row by row.
	 */
	const MAX_ROWS_PER_MINUTE = 300;

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'orderwarden_events';
	}

	/**
	 * Create or update the table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			event varchar(20) NOT NULL,
			reason varchar(32) NOT NULL DEFAULT '',
			channel varchar(20) NOT NULL DEFAULT '',
			ip_masked varchar(64) NOT NULL DEFAULT '',
			ip_hash char(64) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY event_created (event, created_at)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'orderwarden_db_version', ORDERWARDEN_DB_VERSION, false );
	}

	/**
	 * Record an event.
	 *
	 * @param string $event   blocked | would_block | verified | velocity_block.
	 * @param string $reason  Short reason code.
	 * @param string $channel classic | store_api | pay.
	 * @return void
	 */
	public static function log( $event, $reason = '', $channel = '' ) {
		global $wpdb;

		self::bump_stat( $event );

		$minute_key = 'orderwarden_rows_' . gmdate( 'YmdHi' );
		$rows       = (int) get_transient( $minute_key );
		if ( $rows >= self::MAX_ROWS_PER_MINUTE ) {
			return;
		}
		set_transient( $minute_key, $rows + 1, 2 * MINUTE_IN_SECONDS );

		$ip = OrderWarden_Request::ip();
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
			self::table(),
			array(
				'created_at' => current_time( 'mysql', true ),
				'event'      => substr( sanitize_key( $event ), 0, 20 ),
				'reason'     => substr( sanitize_key( $reason ), 0, 32 ),
				'channel'    => substr( sanitize_key( $channel ), 0, 20 ),
				'ip_masked'  => OrderWarden_Request::mask_ip( $ip ),
				'ip_hash'    => OrderWarden_Request::ip_hash( $ip ),
				'user_agent' => OrderWarden_Request::user_agent(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Increment the lifetime counters.
	 *
	 * @param string $event Event name.
	 * @return void
	 */
	private static function bump_stat( $event ) {
		$stats = get_option( self::STATS_OPTION, array() );
		$stats = is_array( $stats ) ? $stats : array();
		if ( empty( $stats['since'] ) ) {
			$stats['since'] = time();
		}
		$stats[ $event ] = isset( $stats[ $event ] ) ? (int) $stats[ $event ] + 1 : 1;
		update_option( self::STATS_OPTION, $stats, false );
	}

	/**
	 * Lifetime counters.
	 *
	 * @return array
	 */
	public static function lifetime() {
		$stats = get_option( self::STATS_OPTION, array() );
		return is_array( $stats ) ? $stats : array();
	}

	/**
	 * Count events of a type in the last N hours.
	 *
	 * @param string $event Event name.
	 * @param int    $hours Hours back.
	 * @return int
	 */
	public static function count_since( $event, $hours ) {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - ( (int) $hours * HOUR_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, live counts.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event = %s AND created_at >= %s', self::table(), $event, $since ) );
	}

	/**
	 * Most recent events.
	 *
	 * @param int $limit Rows.
	 * @return array
	 */
	public static function recent( $limit = 20 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, live list.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE event <> %s ORDER BY id DESC LIMIT %d', self::table(), 'verified', (int) $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete events older than the retention period.
	 *
	 * @return void
	 */
	public static function purge() {
		global $wpdb;
		$days  = (int) OrderWarden_Settings::get( 'log_retention_days' );
		$until = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table cleanup.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', self::table(), $until ) );
	}
}
