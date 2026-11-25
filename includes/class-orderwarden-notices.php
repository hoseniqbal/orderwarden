<?php
/**
 * Review request notice.
 *
 * Asks for a review only after OrderWarden has proven itself: at least
 * 7 days after install and at least 10 blocked attempts. Shown only on
 * OrderWarden's own screen, the Dashboard and the Plugins screen, to users
 * who manage the store. "Maybe later" hides it for 7 days; "I already did"
 * and "Write a review" hide it for good.
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review notice.
 */
class OrderWarden_Notices {

	const OPTION        = 'orderwarden_review';
	const INSTALLED     = 'orderwarden_installed';
	const MIN_DAYS      = 7;
	const MIN_BLOCKED   = 10;
	const SNOOZE_DAYS   = 7;
	const REVIEW_URL    = 'https://wordpress.org/support/plugin/orderwarden/reviews/#new-post';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show' ) );
		add_action( 'admin_post_orderwarden_review', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Install time, recorded the first time it is needed.
	 *
	 * @return int
	 */
	public static function installed_at() {
		$time = (int) get_option( self::INSTALLED );
		if ( ! $time ) {
			$time = time();
			update_option( self::INSTALLED, $time, false );
		}
		return $time;
	}

	/**
	 * Whether the notice should show now.
	 *
	 * @return bool
	 */
	private static function should_show() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$ids    = array( 'dashboard', 'plugins', 'toplevel_page_orderwarden' );
		if ( ! $screen || ! in_array( $screen->id, $ids, true ) ) {
			return false;
		}

		$state = get_option( self::OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		if ( ! empty( $state['done'] ) ) {
			return false;
		}
		if ( ! empty( $state['snooze_until'] ) && time() < (int) $state['snooze_until'] ) {
			return false;
		}

		if ( ( time() - self::installed_at() ) < self::MIN_DAYS * DAY_IN_SECONDS ) {
			return false;
		}

		$lifetime = OrderWarden_Logger::lifetime();
		return isset( $lifetime['blocked'] ) && (int) $lifetime['blocked'] >= self::MIN_BLOCKED;
	}

	/**
	 * Action link for one of the buttons.
	 *
	 * @param string $choice rate | later | done.
	 * @return string
	 */
	private static function link( $choice ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'orderwarden_review',
					'choice' => $choice,
				),
				admin_url( 'admin-post.php' )
			),
			'orderwarden_review'
		);
	}

	/**
	 * Render the notice.
	 *
	 * @return void
	 */
	public static function maybe_show() {
		if ( ! self::should_show() ) {
			return;
		}
		$lifetime = OrderWarden_Logger::lifetime();
		$blocked  = (int) $lifetime['blocked'];
		?>
		<div class="notice notice-info orderwarden-review">
			<p>
				<strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of blocked attempts */
							_n( 'OrderWarden has stopped %s bot order on your store.', 'OrderWarden has stopped %s bot orders on your store.', $blocked, 'orderwarden' ),
							number_format_i18n( $blocked )
						)
					);
					?>
				</strong>
				<?php esc_html_e( 'If it has saved you some trouble, a short review on WordPress.org helps other store owners find it. It takes a minute.', 'orderwarden' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::link( 'rate' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Write a review', 'orderwarden' ); ?></a>
				<a class="button" href="<?php echo esc_url( self::link( 'later' ) ); ?>"><?php esc_html_e( 'Maybe later', 'orderwarden' ); ?></a>
				<a class="button-link" href="<?php echo esc_url( self::link( 'done' ) ); ?>" style="margin-left:8px"><?php esc_html_e( 'I already did', 'orderwarden' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle a button click.
	 *
	 * @return void
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'orderwarden' ) );
		}
		check_admin_referer( 'orderwarden_review' );

		$choice = isset( $_GET['choice'] ) ? sanitize_key( wp_unslash( $_GET['choice'] ) ) : '';
		$state  = get_option( self::OPTION, array() );
		$state  = is_array( $state ) ? $state : array();

		if ( 'later' === $choice ) {
			$state['snooze_until'] = time() + self::SNOOZE_DAYS * DAY_IN_SECONDS;
		} else {
			$state['done'] = time();
		}
		update_option( self::OPTION, $state, false );

		if ( 'rate' === $choice ) {
			wp_redirect( self::REVIEW_URL ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- fixed wordpress.org URL.
			exit;
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
