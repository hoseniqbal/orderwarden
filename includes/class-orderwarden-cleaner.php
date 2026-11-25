<?php
/**
 * Bulk cleaner for failed orders left behind by card-testing attacks.
 *
 * Runs in the background with Action Scheduler (bundled with WooCommerce), one
 * batch at a time, so thousands of orders can be removed without timeouts.
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds and removes failed orders in batches.
 */
class OrderWarden_Cleaner {

	const STATE_OPTION = 'orderwarden_cleanup';
	const HOOK         = 'orderwarden_cleanup_batch';
	const GROUP        = 'orderwarden';
	const BATCH        = 50;

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
	}

	/**
	 * Query args for the orders a job will remove.
	 *
	 * @param array $job Job settings.
	 * @return array
	 */
	private static function query_args( array $job ) {
		$args = array(
			'status'  => array( 'failed' ),
			'orderby' => 'date',
			'order'   => 'ASC',
			'type'    => 'shop_order',
		);
		if ( ! empty( $job['guest_only'] ) ) {
			$args['customer_id'] = 0;
		}
		if ( ! empty( $job['older_than_days'] ) ) {
			$args['date_created'] = '<' . ( time() - ( (int) $job['older_than_days'] * DAY_IN_SECONDS ) );
		}
		return $args;
	}

	/**
	 * Count matching orders.
	 *
	 * @param array $job Job settings.
	 * @return int
	 */
	public static function count( array $job ) {
		$args             = self::query_args( $job );
		$args['limit']    = 1;
		$args['paginate'] = true;
		$args['return']   = 'ids';
		$result           = wc_get_orders( $args );
		return is_object( $result ) && isset( $result->total ) ? (int) $result->total : 0;
	}

	/**
	 * Current job state.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::STATE_OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Start a cleanup job.
	 *
	 * @param bool $guest_only      Only orders without a customer account.
	 * @param int  $older_than_days Only orders older than this many days (0 = any age).
	 * @param bool $permanent       Delete permanently instead of moving to Trash.
	 * @return void
	 */
	public static function start( $guest_only, $older_than_days, $permanent ) {
		$job = array(
			'running'         => true,
			'guest_only'      => (bool) $guest_only,
			'older_than_days' => max( 0, (int) $older_than_days ),
			'permanent'       => (bool) $permanent,
			'started'         => time(),
			'done'            => 0,
			'finished'        => 0,
		);
		$job['total'] = self::count( $job );
		update_option( self::STATE_OPTION, $job, false );
		self::schedule();
	}

	/**
	 * Stop a running job.
	 *
	 * @return void
	 */
	public static function cancel() {
		$job            = self::state();
		$job['running'] = false;
		update_option( self::STATE_OPTION, $job, false );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
		}
	}

	/**
	 * Queue the next batch.
	 *
	 * @return void
	 */
	private static function schedule() {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array(), self::GROUP );
		} else {
			wp_schedule_single_event( time(), self::HOOK );
		}
	}

	/**
	 * Remove one batch, then queue the next while orders remain.
	 *
	 * @return void
	 */
	public static function run_batch() {
		$job = self::state();
		if ( empty( $job['running'] ) ) {
			return;
		}

		$args           = self::query_args( $job );
		$args['limit']  = self::BATCH;
		$args['return'] = 'objects';
		$orders         = wc_get_orders( $args );

		$removed = 0;
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order || ! $order->has_status( 'failed' ) ) {
				continue;
			}
			if ( ! empty( $job['guest_only'] ) && 0 !== (int) $order->get_customer_id() ) {
				continue;
			}
			if ( $order->delete( ! empty( $job['permanent'] ) ) ) {
				++$removed;
			}
		}
		$job['done'] += $removed;

		// Stop when nothing is left, or when a batch made no progress (avoids an endless loop).
		if ( count( $orders ) < self::BATCH || 0 === $removed ) {
			$job['running']  = false;
			$job['finished'] = time();
			update_option( self::STATE_OPTION, $job, false );
			return;
		}

		update_option( self::STATE_OPTION, $job, false );
		self::schedule();
	}
}
