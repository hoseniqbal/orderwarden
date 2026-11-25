<?php
/**
 * Admin screens: WooCommerce > OrderWarden.
 *
 * @package OrderWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overview, Settings, Cleanup and Checklist tabs.
 */
class OrderWarden_Admin {

	const PAGE = 'orderwarden';
	const CAP  = 'manage_woocommerce';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_orderwarden_cleanup_start', array( __CLASS__, 'handle_cleanup_start' ) );
		add_action( 'admin_post_orderwarden_cleanup_cancel', array( __CLASS__, 'handle_cleanup_cancel' ) );
		add_filter( 'option_page_capability_orderwarden', array( __CLASS__, 'settings_capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ORDERWARDEN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Let shop managers save the settings.
	 *
	 * @return string
	 */
	public static function settings_capability() {
		return self::CAP;
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Dashboard', 'orderwarden' ) . '</a>' );
		return $links;
	}

	/**
	 * Page URL.
	 *
	 * @param string $tab Tab.
	 * @return string
	 */
	public static function url( $tab = 'overview' ) {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Menu entry under WooCommerce.
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'OrderWarden', 'orderwarden' ),
			__( 'OrderWarden', 'orderwarden' ),
			self::CAP,
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Admin CSS on our page only.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'orderwarden-admin', ORDERWARDEN_URL . 'assets/css/admin.css', array(), ORDERWARDEN_VERSION );
	}

	/**
	 * Register the settings option.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'orderwarden',
			OrderWarden_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'OrderWarden_Settings', 'sanitize' ),
				'default'           => OrderWarden_Settings::defaults(),
			)
		);
	}

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tabs = array( 'overview', 'settings', 'cleanup', 'checklist' );
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switch only.
		return in_array( $tab, $tabs, true ) ? $tab : 'overview';
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tab  = self::current_tab();
		$tabs = array(
			'overview'  => __( 'Overview', 'orderwarden' ),
			'settings'  => __( 'Settings', 'orderwarden' ),
			'cleanup'   => __( 'Clean up failed orders', 'orderwarden' ),
			'checklist' => __( 'Checklist', 'orderwarden' ),
		);
		?>
		<div class="wrap orderwarden">
			<h1><?php esc_html_e( 'OrderWarden', 'orderwarden' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			switch ( $tab ) {
				case 'settings':
					self::render_settings();
					break;
				case 'cleanup':
					self::render_cleanup();
					break;
				case 'checklist':
					self::render_checklist();
					break;
				default:
					self::render_overview();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Tags allowed inside translated sentences that embed form fields.
	 *
	 * @return array
	 */
	private static function allowed_html() {
		return array(
			'strong' => array(),
			'input'  => array(
				'type'  => true,
				'min'   => true,
				'max'   => true,
				'class' => true,
				'name'  => true,
				'value' => true,
			),
		);
	}

	/**
	 * Human label for a reason code.
	 *
	 * @param string $reason Reason.
	 * @return string
	 */
	private static function reason_label( $reason ) {
		$labels = array(
			'missing'        => __( 'No token (request did not come from your checkout page)', 'orderwarden' ),
			'malformed'      => __( 'Broken token', 'orderwarden' ),
			'bad_signature'  => __( 'Forged token', 'orderwarden' ),
			'expired'        => __( 'Expired token', 'orderwarden' ),
			'too_fast'       => __( 'Submitted faster than a human could', 'orderwarden' ),
			'agent_mismatch' => __( 'Token reused by a different browser', 'orderwarden' ),
			'velocity'       => __( 'Too many failed payments from this address', 'orderwarden' ),
		);
		return isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
	}

	/**
	 * Human label for a channel.
	 *
	 * @param string $channel Channel.
	 * @return string
	 */
	private static function channel_label( $channel ) {
		$labels = array(
			'classic'   => __( 'Classic checkout & express buttons', 'orderwarden' ),
			'store_api' => __( 'Block checkout (Store API)', 'orderwarden' ),
			'pay'       => __( 'Pay for order page', 'orderwarden' ),
		);
		return isset( $labels[ $channel ] ) ? $labels[ $channel ] : $channel;
	}

	/**
	 * Overview tab.
	 *
	 * @return void
	 */
	private static function render_overview() {
		$mode     = OrderWarden_Settings::get( 'mode' );
		$armed    = OrderWarden_Guard::armed();
		$lifetime = OrderWarden_Logger::lifetime();
		$recent   = OrderWarden_Logger::recent( 20 );
		$failed   = OrderWarden_Cleaner::count( array( 'guest_only' => false ) );
		?>
		<?php if ( 'auto' === $mode && empty( $armed ) ) : ?>
			<div class="notice notice-info inline"><p>
				<strong><?php esc_html_e( 'OrderWarden is learning.', 'orderwarden' ); ?></strong>
				<?php esc_html_e( 'It starts blocking on each checkout type as soon as it sees one real checkout there. Place a test order in a normal browser to switch protection on now.', 'orderwarden' ); ?>
			</p></div>
		<?php elseif ( 'learning' === $mode ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Learning mode: OrderWarden is only logging what it would block. Nothing is being blocked.', 'orderwarden' ); ?></p></div>
		<?php elseif ( 'off' === $mode ) : ?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'Protection is switched off.', 'orderwarden' ); ?></p></div>
		<?php endif; ?>

		<div class="orderwarden-cards">
			<div class="orderwarden-card"><span class="num"><?php echo esc_html( number_format_i18n( OrderWarden_Logger::count_since( 'blocked', 24 ) ) ); ?></span><span class="label"><?php esc_html_e( 'Blocked, last 24 hours', 'orderwarden' ); ?></span></div>
			<div class="orderwarden-card"><span class="num"><?php echo esc_html( number_format_i18n( OrderWarden_Logger::count_since( 'blocked', 24 * 7 ) ) ); ?></span><span class="label"><?php esc_html_e( 'Blocked, last 7 days', 'orderwarden' ); ?></span></div>
			<div class="orderwarden-card"><span class="num"><?php echo esc_html( number_format_i18n( isset( $lifetime['blocked'] ) ? (int) $lifetime['blocked'] : 0 ) ); ?></span><span class="label"><?php esc_html_e( 'Blocked since install', 'orderwarden' ); ?></span></div>
			<div class="orderwarden-card"><span class="num"><?php echo esc_html( number_format_i18n( OrderWarden_Logger::count_since( 'verified', 24 ) ) ); ?></span><span class="label"><?php esc_html_e( 'Genuine checkouts, last 24 hours', 'orderwarden' ); ?></span></div>
		</div>

		<h2><?php esc_html_e( 'Protection status', 'orderwarden' ); ?></h2>
		<table class="widefat striped orderwarden-status">
			<tbody>
			<?php foreach ( array( 'classic', 'store_api', 'pay' ) as $channel ) : ?>
				<tr>
					<td><?php echo esc_html( self::channel_label( $channel ) ); ?></td>
					<td>
						<?php if ( OrderWarden_Guard::is_enforcing( $channel ) ) : ?>
							<span class="orderwarden-pill on"><?php esc_html_e( 'Protected', 'orderwarden' ); ?></span>
						<?php elseif ( 'off' === $mode ) : ?>
							<span class="orderwarden-pill off"><?php esc_html_e( 'Off', 'orderwarden' ); ?></span>
						<?php else : ?>
							<span class="orderwarden-pill learn"><?php esc_html_e( 'Learning', 'orderwarden' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $failed > 0 ) : ?>
			<p class="orderwarden-cleanup-hint">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: number of failed orders */
						esc_html__( 'Your store has %s failed orders.', 'orderwarden' ),
						'<strong>' . esc_html( number_format_i18n( $failed ) ) . '</strong>'
					),
					self::allowed_html()
				);
				?>
				<a href="<?php echo esc_url( self::url( 'cleanup' ) ); ?>"><?php esc_html_e( 'Clean them up', 'orderwarden' ); ?> &rarr;</a>
			</p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Recent blocked attempts', 'orderwarden' ); ?></h2>
		<?php if ( empty( $recent ) ) : ?>
			<p><?php esc_html_e( 'Nothing yet. That is good news.', 'orderwarden' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Time', 'orderwarden' ); ?></th>
					<th><?php esc_html_e( 'Result', 'orderwarden' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'orderwarden' ); ?></th>
					<th><?php esc_html_e( 'Where', 'orderwarden' ); ?></th>
					<th><?php esc_html_e( 'IP', 'orderwarden' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $recent as $row ) : ?>
					<tr>
						<td><?php echo esc_html( get_date_from_gmt( $row['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
						<td><?php echo 'blocked' === $row['event'] ? esc_html__( 'Blocked', 'orderwarden' ) : esc_html__( 'Would block (learning)', 'orderwarden' ); ?></td>
						<td><?php echo esc_html( self::reason_label( $row['reason'] ) ); ?></td>
						<td><?php echo esc_html( self::channel_label( $row['channel'] ) ); ?></td>
						<td><code><?php echo esc_html( $row['ip_masked'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Settings tab.
	 *
	 * @return void
	 */
	private static function render_settings() {
		$s    = OrderWarden_Settings::all();
		$name = OrderWarden_Settings::OPTION;
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'orderwarden' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ow-mode"><?php esc_html_e( 'Protection mode', 'orderwarden' ); ?></label></th>
					<td>
						<select id="ow-mode" name="<?php echo esc_attr( $name ); ?>[mode]">
							<option value="auto" <?php selected( $s['mode'], 'auto' ); ?>><?php esc_html_e( 'Automatic (recommended)', 'orderwarden' ); ?></option>
							<option value="protect" <?php selected( $s['mode'], 'protect' ); ?>><?php esc_html_e( 'Always protect', 'orderwarden' ); ?></option>
							<option value="learning" <?php selected( $s['mode'], 'learning' ); ?>><?php esc_html_e( 'Learning only (log, never block)', 'orderwarden' ); ?></option>
							<option value="off" <?php selected( $s['mode'], 'off' ); ?>><?php esc_html_e( 'Off', 'orderwarden' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Automatic waits until it has seen one genuine checkout of each type before it blocks anything, so real customers are never turned away by a theme or caching problem.', 'orderwarden' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Failed payment limit', 'orderwarden' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[velocity_enabled]" value="yes" <?php checked( $s['velocity_enabled'], 'yes' ); ?>> <?php esc_html_e( 'Block an address after repeated failed payments', 'orderwarden' ); ?></label>
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: 1: number input, 2: minutes input, 3: minutes input */
									esc_html__( 'Block for %3$s minutes after %1$s failed payments within %2$s minutes.', 'orderwarden' ),
									'<input type="number" min="1" max="50" class="small-text" name="' . esc_attr( $name ) . '[velocity_limit]" value="' . esc_attr( $s['velocity_limit'] ) . '">',
									'<input type="number" min="1" max="1440" class="small-text" name="' . esc_attr( $name ) . '[velocity_window]" value="' . esc_attr( $s['velocity_window'] ) . '">',
									'<input type="number" min="1" max="10080" class="small-text" name="' . esc_attr( $name ) . '[velocity_block]" value="' . esc_attr( $s['velocity_block'] ) . '">'
								),
								self::allowed_html()
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Returning customers', 'orderwarden' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[trust_customers]" value="yes" <?php checked( $s['trust_customers'], 'yes' ); ?>> <?php esc_html_e( 'Never block logged-in customers who already have a paid order', 'orderwarden' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Proxy / Cloudflare', 'orderwarden' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[use_proxy_headers]" value="yes" <?php checked( $s['use_proxy_headers'], 'yes' ); ?>> <?php esc_html_e( 'My site is behind Cloudflare or another proxy', 'orderwarden' ); ?></label>
						<p class="description"><?php esc_html_e( 'Reads the visitor IP from CF-Connecting-IP / X-Forwarded-For. Only turn this on if a proxy really sits in front of your site, otherwise visitors can fake their IP.', 'orderwarden' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ow-allow"><?php esc_html_e( 'Never block these IPs', 'orderwarden' ); ?></label></th>
					<td>
						<textarea id="ow-allow" rows="4" class="large-text code" name="<?php echo esc_attr( $name ); ?>[ip_allowlist]"><?php echo esc_textarea( $s['ip_allowlist'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One IP or CIDR range per line, for example 203.0.113.10 or 203.0.113.0/24.', 'orderwarden' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Attack alerts', 'orderwarden' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[alert_enabled]" value="yes" <?php checked( $s['alert_enabled'], 'yes' ); ?>> <?php esc_html_e( 'Email me when an attack starts', 'orderwarden' ); ?></label>
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: number input */
									esc_html__( 'Send an alert after %s blocked attempts within 10 minutes (at most one email per hour).', 'orderwarden' ),
									'<input type="number" min="5" max="10000" class="small-text" name="' . esc_attr( $name ) . '[alert_threshold]" value="' . esc_attr( $s['alert_threshold'] ) . '">'
								),
								self::allowed_html()
							);
							?>
						</p>
						<input type="email" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" name="<?php echo esc_attr( $name ); ?>[alert_email]" value="<?php echo esc_attr( $s['alert_email'] ); ?>">
						<p class="description"><?php esc_html_e( 'Leave empty to use the site admin email.', 'orderwarden' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ow-retention"><?php esc_html_e( 'Keep logs for', 'orderwarden' ); ?></label></th>
					<td><input id="ow-retention" type="number" min="1" max="365" class="small-text" name="<?php echo esc_attr( $name ); ?>[log_retention_days]" value="<?php echo esc_attr( $s['log_retention_days'] ); ?>"> <?php esc_html_e( 'days', 'orderwarden' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Uninstall', 'orderwarden' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_data]" value="yes" <?php checked( $s['delete_data'], 'yes' ); ?>> <?php esc_html_e( 'Delete all OrderWarden data when the plugin is deleted', 'orderwarden' ); ?></label></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Cleanup tab.
	 *
	 * @return void
	 */
	private static function render_cleanup() {
		$state = OrderWarden_Cleaner::state();
		$all   = OrderWarden_Cleaner::count( array( 'guest_only' => false ) );
		$guest = OrderWarden_Cleaner::count( array( 'guest_only' => true ) );
		?>
		<p><?php esc_html_e( 'Card-testing attacks leave hundreds or thousands of failed orders behind. Remove them here in the background, without timeouts. Failed orders can also belong to real customers whose card was declined, so start with guest orders.', 'orderwarden' ); ?></p>

		<?php if ( ! empty( $state['running'] ) ) : ?>
			<div class="notice notice-info inline"><p>
				<?php
				printf(
					/* translators: 1: orders removed so far, 2: total orders */
					esc_html__( 'Cleaning up: %1$s of about %2$s orders removed. This page refreshes on its own.', 'orderwarden' ),
					esc_html( number_format_i18n( (int) $state['done'] ) ),
					esc_html( number_format_i18n( (int) $state['total'] ) )
				);
				?>
			</p></div>
			<meta http-equiv="refresh" content="5">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="orderwarden_cleanup_cancel">
				<?php wp_nonce_field( 'orderwarden_cleanup_cancel' ); ?>
				<?php submit_button( __( 'Stop', 'orderwarden' ), 'secondary' ); ?>
			</form>
			<?php
			return;
		endif;
		?>

		<?php if ( ! empty( $state['finished'] ) ) : ?>
			<div class="notice notice-success inline"><p>
				<?php
				printf(
					/* translators: %s: number of orders */
					esc_html__( 'Last cleanup finished: %s orders removed.', 'orderwarden' ),
					esc_html( number_format_i18n( (int) $state['done'] ) )
				);
				?>
			</p></div>
		<?php endif; ?>

		<table class="widefat striped orderwarden-status">
			<tbody>
				<tr><td><?php esc_html_e( 'Failed orders (all)', 'orderwarden' ); ?></td><td><strong><?php echo esc_html( number_format_i18n( $all ) ); ?></strong></td></tr>
				<tr><td><?php esc_html_e( 'Failed guest orders (no customer account)', 'orderwarden' ); ?></td><td><strong><?php echo esc_html( number_format_i18n( $guest ) ); ?></strong></td></tr>
			</tbody>
		</table>

		<?php if ( $all > 0 ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="orderwarden-cleanup-form">
			<input type="hidden" name="action" value="orderwarden_cleanup_start">
			<?php wp_nonce_field( 'orderwarden_cleanup_start' ); ?>
			<p><label><input type="checkbox" name="guest_only" value="1" checked> <?php esc_html_e( 'Only guest orders', 'orderwarden' ); ?></label></p>
			<p>
				<label>
					<?php esc_html_e( 'Only orders older than', 'orderwarden' ); ?>
					<input type="number" min="0" max="3650" class="small-text" name="older_than_days" value="0">
					<?php esc_html_e( 'days (0 = any age)', 'orderwarden' ); ?>
				</label>
			</p>
			<fieldset>
				<p><label><input type="radio" name="permanent" value="0" checked> <?php esc_html_e( 'Move to Trash (can be restored)', 'orderwarden' ); ?></label></p>
				<p><label><input type="radio" name="permanent" value="1"> <?php esc_html_e( 'Delete permanently (cannot be undone)', 'orderwarden' ); ?></label></p>
			</fieldset>
			<p><label><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'I understand these failed orders will be removed.', 'orderwarden' ); ?></label></p>
			<?php submit_button( __( 'Start cleanup', 'orderwarden' ), 'primary' ); ?>
		</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Start cleanup (admin-post).
	 *
	 * @return void
	 */
	public static function handle_cleanup_start() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'orderwarden' ) );
		}
		check_admin_referer( 'orderwarden_cleanup_start' );

		if ( ! empty( $_POST['confirm'] ) ) {
			$guest_only = ! empty( $_POST['guest_only'] );
			$days       = isset( $_POST['older_than_days'] ) ? absint( $_POST['older_than_days'] ) : 0;
			$permanent  = isset( $_POST['permanent'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['permanent'] ) );
			OrderWarden_Cleaner::start( $guest_only, min( 3650, $days ), $permanent );
		}
		wp_safe_redirect( self::url( 'cleanup' ) );
		exit;
	}

	/**
	 * Cancel cleanup (admin-post).
	 *
	 * @return void
	 */
	public static function handle_cleanup_cancel() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'orderwarden' ) );
		}
		check_admin_referer( 'orderwarden_cleanup_cancel' );
		OrderWarden_Cleaner::cancel();
		wp_safe_redirect( self::url( 'cleanup' ) );
		exit;
	}

	/**
	 * Gateway-specific tips, keyed by gateway ID prefix.
	 *
	 * @return array
	 */
	private static function gateway_tips() {
		return array(
			'stripe'               => array(
				'title' => 'Stripe',
				'tip'   => __( 'In your Stripe Dashboard open Radar > Rules and turn on "Block if CVC verification fails" and "Block if postal code verification fails". Radar is included with Stripe.', 'orderwarden' ),
			),
			'woocommerce_payments' => array(
				'title' => 'WooPayments',
				'tip'   => __( 'WooPayments has card-testing protection built in. Also review Payments > Settings > Fraud protection and pick at least the Basic level.', 'orderwarden' ),
			),
			'ppcp'                 => array(
				'title' => 'PayPal Payments',
				'tip'   => __( 'If you are under attack, temporarily disable "Advanced Card Processing" in the PayPal settings and update the PayPal Payments plugin to its latest version.', 'orderwarden' ),
			),
			'authorize'            => array(
				'title' => 'Authorize.net',
				'tip'   => __( 'In the Authorize.net Merchant Interface go to Tools > Fraud Detection Suite and set the Velocity Filter and Hourly Velocity Filter to Decline.', 'orderwarden' ),
			),
			'square'               => array(
				'title' => 'Square',
				'tip'   => __( 'Make sure your Square account requires CVV and postal code for card payments, and contact Square support if you see an attack.', 'orderwarden' ),
			),
			'braintree'            => array(
				'title' => 'Braintree',
				'tip'   => __( 'In the Braintree Control Panel enable Advanced Fraud Protection and the CVV and AVS rules.', 'orderwarden' ),
			),
		);
	}

	/**
	 * Checklist tab.
	 *
	 * @return void
	 */
	private static function render_checklist() {
		$enabled = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
				if ( isset( $gateway->enabled ) && 'yes' === $gateway->enabled ) {
					$enabled[] = (string) $id;
				}
			}
		}
		?>
		<p><?php esc_html_e( 'OrderWarden stops bots at your checkout. These free settings add a second layer at your payment gateway and in front of your site.', 'orderwarden' ); ?></p>

		<h2><?php esc_html_e( 'Your payment gateways', 'orderwarden' ); ?></h2>
		<?php
		$shown = 0;
		foreach ( self::gateway_tips() as $prefix => $info ) {
			$match = false;
			foreach ( $enabled as $id ) {
				if ( 0 === strpos( $id, $prefix ) ) {
					$match = true;
					break;
				}
			}
			if ( ! $match ) {
				continue;
			}
			++$shown;
			echo '<div class="orderwarden-tip"><h3>' . esc_html( $info['title'] ) . '</h3><p>' . esc_html( $info['tip'] ) . '</p></div>';
		}
		if ( 0 === $shown ) {
			echo '<p>' . esc_html__( 'No gateway-specific tips for your enabled gateways. Ask your gateway whether it offers velocity limits or CVC checks.', 'orderwarden' ) . '</p>';
		}
		?>

		<h2><?php esc_html_e( 'Everyone', 'orderwarden' ); ?></h2>
		<ol class="orderwarden-list">
			<li><?php esc_html_e( 'Cloudflare (free plan): turn on Security > Bots > Bot Fight Mode, and add one rate-limiting rule for your checkout URL and for /?wc-ajax=checkout.', 'orderwarden' ); ?></li>
			<li><?php esc_html_e( 'If you use the block checkout, WooCommerce has its own limit: WooCommerce > Settings > Advanced > Features > "Rate limiting Checkout block and Store API".', 'orderwarden' ); ?></li>
			<li><?php esc_html_e( 'If it suits your store, turn off guest checkout in WooCommerce > Settings > Accounts & Privacy so bots must create an account first.', 'orderwarden' ); ?></li>
			<li><?php esc_html_e( 'Keep WooCommerce and your payment plugin up to date.', 'orderwarden' ); ?></li>
		</ol>
		<?php
	}
}
