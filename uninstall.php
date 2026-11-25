<?php
/**
 * Uninstall: removes plugin data when "Delete all OrderWarden data" is enabled.
 *
 * @package OrderWarden
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove data for the current site when the user opted in.
 *
 * @return void
 */
function orderwarden_uninstall_current_site() {
	global $wpdb;

	$settings = get_option( 'orderwarden_settings' );
	if ( ! is_array( $settings ) || ! isset( $settings['delete_data'] ) || 'yes' !== $settings['delete_data'] ) {
		return;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing our own table on uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'orderwarden_events' ) );

	foreach ( array( 'orderwarden_settings', 'orderwarden_secret', 'orderwarden_stats', 'orderwarden_armed', 'orderwarden_cleanup', 'orderwarden_db_version' ) as $option ) {
		delete_option( $option );
	}
	delete_transient( 'orderwarden_alert_sent' );
}

if ( is_multisite() ) {
	$orderwarden_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $orderwarden_site_ids as $orderwarden_site_id ) {
		switch_to_blog( $orderwarden_site_id );
		orderwarden_uninstall_current_site();
		restore_current_blog();
	}
} else {
	orderwarden_uninstall_current_site();
}
