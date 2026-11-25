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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'menu_style' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
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
		$icon = 'data:image/svg+xml;base64,' . base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon.
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1.5 2.8 4.2v5.2c0 4.4 3 7.7 7.2 8.9 4.2-1.2 7.2-4.5 7.2-8.9V4.2L10 1.5Zm-1 11.3L5.7 9.5l1.2-1.2L9 10.4l4.2-4.3 1.2 1.2L9 12.8Z"/></svg>'
		);
		add_menu_page(
			__( 'OrderWarden', 'orderwarden' ),
			__( 'OrderWarden', 'orderwarden' ),
			self::CAP,
			self::PAGE,
			array( __CLASS__, 'render' ),
			$icon,
			56
		);

		add_submenu_page( self::PAGE, __( 'Overview', 'orderwarden' ), __( 'Overview', 'orderwarden' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ) );
		foreach ( self::free_tabs() as $key => $label ) {
			if ( 'overview' === $key ) {
				continue;
			}
			add_submenu_page( self::PAGE, $label, $label, self::CAP, self::menu_slug( $key ) );
		}
		foreach ( self::pro_features() as $key => $feature ) {
			add_submenu_page(
				self::PAGE,
				$feature['title'],
				esc_html( $feature['menu'] ) . ' <span class="orderwarden-menu-pro">' . esc_html__( 'Pro', 'orderwarden' ) . '</span>',
				self::CAP,
				self::menu_slug( 'pro-' . $key )
			);
		}
	}

	/**
	 * Submenu slug that links straight to a tab.
	 *
	 * @param string $tab Tab.
	 * @return string
	 */
	private static function menu_slug( $tab ) {
		return 'admin.php?page=' . self::PAGE . '&tab=' . $tab;
	}

	/**
	 * Highlight the submenu item of the open tab.
	 *
	 * @param string|null $submenu_file Current submenu file.
	 * @return string|null
	 */
	public static function submenu_file( $submenu_file ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- menu highlight only.
		if ( self::PAGE !== $page ) {
			return $submenu_file;
		}
		$tab = self::current_tab();
		return 'overview' === $tab ? self::PAGE : self::menu_slug( $tab );
	}

	/**
	 * Small style for the Pro label in the admin menu, on every admin screen.
	 *
	 * @return void
	 */
	public static function menu_style() {
		wp_register_style( 'orderwarden-menu', false, array(), ORDERWARDEN_VERSION );
		wp_enqueue_style( 'orderwarden-menu' );
		wp_add_inline_style( 'orderwarden-menu', '#adminmenu .orderwarden-menu-pro{display:inline-block;margin-left:4px;padding:0 6px;border-radius:9px;background:#136f4d;color:#fff;font-size:10px;font-weight:600;line-height:17px;vertical-align:1px}' );
	}

	/**
	 * Free sections.
	 *
	 * @return array
	 */
	private static function free_tabs() {
		return array(
			'overview'  => __( 'Overview', 'orderwarden' ),
			'settings'  => __( 'Settings', 'orderwarden' ),
			'cleanup'   => __( 'Failed orders', 'orderwarden' ),
			'checklist' => __( 'Checklist', 'orderwarden' ),
		);
	}

	/**
	 * Whether OrderWarden Pro is installed and active.
	 *
	 * @return bool
	 */
	private static function pro_installed() {
		return (bool) apply_filters( 'orderwarden_pro_active', class_exists( 'OWP_Plugin' ) );
	}

	/**
	 * Link to buy Pro. Empty until the add-on is released, which shows "Coming soon".
	 *
	 * @return string
	 */
	public static function pro_url() {
		return (string) apply_filters( 'orderwarden_pro_url', 'https://checkout.freemius.com/plugin/46327/plan/81643/' );
	}

	/**
	 * Features of the paid add-on. None of this code ships in the free plugin.
	 *
	 * @return array
	 */
	private static function pro_features() {
		return array(
			'bans'    => array(
				'menu'   => __( 'Auto bans', 'orderwarden' ),
				'title'  => __( 'Automatic bans', 'orderwarden' ),
				'intro'  => __( 'Addresses and emails that keep failing payments are banned for a while, then released on their own.', 'orderwarden' ),
				'points' => array(
					__( 'Ban by IP range, email and billing address, not only a single IP', 'orderwarden' ),
					__( 'Bans lift by themselves after the time you choose', 'orderwarden' ),
					__( 'A list of active bans you can clear with one click', 'orderwarden' ),
				),
			),
			'alerts'  => array(
				'menu'   => __( 'Instant alerts', 'orderwarden' ),
				'title'  => __( 'Alerts where you already are', 'orderwarden' ),
				'intro'  => __( 'Hear about an attack the minute it starts, on the app you check most.', 'orderwarden' ),
				'points' => array(
					__( 'Slack, Discord, Telegram or any webhook, as well as email', 'orderwarden' ),
					__( 'One message per attack, not one per blocked order', 'orderwarden' ),
					__( 'A short note when the attack is over', 'orderwarden' ),
				),
			),
			'report'  => array(
				'menu'   => __( 'Weekly report', 'orderwarden' ),
				'title'  => __( 'Weekly report', 'orderwarden' ),
				'intro'  => __( 'One email a week that shows what OrderWarden did for your store.', 'orderwarden' ),
				'points' => array(
					__( 'Blocked attempts by day and by checkout', 'orderwarden' ),
					__( 'Roughly how much you saved in gateway fees', 'orderwarden' ),
					__( 'Sent to you, your team or your client', 'orderwarden' ),
				),
			),
			'history' => array(
				'menu'   => __( 'Attack history', 'orderwarden' ),
				'title'  => __( 'Full attack history', 'orderwarden' ),
				'intro'  => __( 'Every blocked attempt, kept as long as you need it.', 'orderwarden' ),
				'points' => array(
					__( 'Filter by date, reason, checkout and IP', 'orderwarden' ),
					__( 'Export to CSV for your records or your payment provider', 'orderwarden' ),
					__( 'A daily chart that shows when attacks start and stop', 'orderwarden' ),
				),
			),
			'agency'  => array(
				'menu'   => __( 'Many stores', 'orderwarden' ),
				'title'  => __( 'Made for many stores', 'orderwarden' ),
				'intro'  => __( 'For agencies and owners with more than one shop.', 'orderwarden' ),
				'points' => array(
					__( 'Licences for 5 or 25 stores at a lower price per store', 'orderwarden' ),
					__( 'Copy your settings from one store to the others in one step', 'orderwarden' ),
					__( 'Priority email support from the developer', 'orderwarden' ),
				),
			),
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
		$file = ORDERWARDEN_PATH . 'assets/css/admin.css';
		wp_enqueue_style( 'orderwarden-admin', ORDERWARDEN_URL . 'assets/css/admin.css', array(), ORDERWARDEN_VERSION . '.' . (int) filemtime( $file ) );
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
		$tabs = array_keys( self::free_tabs() );
		foreach ( array_keys( self::pro_features() ) as $key ) {
			$tabs[] = 'pro-' . $key;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switch only.
		return in_array( $tab, $tabs, true ) ? $tab : 'overview';
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
				'type'       => true,
				'min'        => true,
				'max'        => true,
				'class'      => true,
				'name'       => true,
				'value'      => true,
				'id'         => true,
				'aria-label' => true,
			),
		);
	}

	/**
	 * Shield mark used in the header (original artwork).
	 *
	 * @return void
	 */
	private static function logo() {
		?>
		<svg width="34" height="34" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
			<path d="M16 2.5 4.5 6.8v8.4c0 7.1 4.9 12.4 11.5 14.3 6.6-1.9 11.5-7.2 11.5-14.3V6.8L16 2.5Z" fill="#136f4d"/>
			<path d="M10.5 16.2l3.6 3.6 7.4-7.6" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
		<?php
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
		$tabs   = self::free_tabs();
		$is_pro = 0 === strpos( $tab, 'pro-' );
		$first  = 'pro-' . key( self::pro_features() );
		?>
		<div class="wrap orderwarden">
			<div class="ow-hero">
				<div class="orderwarden-header">
					<span class="ow-logo"><?php self::logo(); ?></span>
					<div class="ow-title">
						<h1><?php esc_html_e( 'OrderWarden', 'orderwarden' ); ?></h1>
						<p class="ow-tagline"><?php esc_html_e( 'Stops card testing and fake orders before they reach your payment gateway.', 'orderwarden' ); ?></p>
					</div>
					<span class="orderwarden-version"><?php echo esc_html( 'v' . ORDERWARDEN_VERSION ); ?></span>
				</div>

				<nav class="orderwarden-tabs" aria-label="<?php esc_attr_e( 'OrderWarden sections', 'orderwarden' ); ?>">
					<?php foreach ( $tabs as $key => $label ) : ?>
						<a href="<?php echo esc_url( self::url( $key ) ); ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
					<span class="ow-tab-gap" aria-hidden="true"></span>
					<?php if ( self::pro_installed() ) : ?>
						<a class="ow-tab-pro" href="<?php echo esc_url( admin_url( 'admin.php?page=orderwarden-pro-bans' ) ); ?>"><?php esc_html_e( 'Pro tools', 'orderwarden' ); ?></a>
					<?php else : ?>
						<a class="ow-tab-pro" href="<?php echo esc_url( self::url( $first ) ); ?>"<?php echo $is_pro ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Pro features', 'orderwarden' ); ?></a>
					<?php endif; ?>
				</nav>
			</div>
			<hr class="wp-header-end">

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
					if ( $is_pro ) {
						self::render_pro( substr( $tab, 4 ) );
					} else {
						self::render_overview();
					}
			}
			?>
		</div>
		<?php
	}

	/**
	 * Human label for a reason code.
	 *
	 * @param string $reason Reason.
	 * @return string
	 */
	private static function reason_label( $reason ) {
		$labels = array(
			'missing'        => __( 'Sent without visiting your checkout', 'orderwarden' ),
			'malformed'      => __( 'Broken checkout pass', 'orderwarden' ),
			'bad_signature'  => __( 'Forged checkout pass', 'orderwarden' ),
			'expired'        => __( 'Expired checkout pass', 'orderwarden' ),
			'too_fast'       => __( 'Faster than a person can type', 'orderwarden' ),
			'agent_mismatch' => __( 'Pass copied to another browser', 'orderwarden' ),
			'velocity'       => __( 'Too many failed payments', 'orderwarden' ),
			'banned_range'   => __( 'Banned IP range (Pro)', 'orderwarden' ),
			'banned_email'   => __( 'Banned email (Pro)', 'orderwarden' ),
			'banned_address' => __( 'Banned billing address (Pro)', 'orderwarden' ),
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
			'classic'   => __( 'Classic checkout', 'orderwarden' ),
			'store_api' => __( 'Block checkout', 'orderwarden' ),
			'pay'       => __( 'Pay for order', 'orderwarden' ),
		);
		return isset( $labels[ $channel ] ) ? $labels[ $channel ] : $channel;
	}

	/**
	 * Overall state for the banner.
	 *
	 * @return array{state:string,title:string,text:string}
	 */
	private static function overall_state() {
		$mode = OrderWarden_Settings::get( 'mode' );
		if ( 'off' === $mode ) {
			return array(
				'state' => 'off',
				'title' => __( 'Protection is switched off', 'orderwarden' ),
				'text'  => __( 'Bots can place orders on your store. Turn protection back on in Settings.', 'orderwarden' ),
			);
		}
		if ( 'learning' === $mode ) {
			return array(
				'state' => 'learning',
				'title' => __( 'Learning mode: nothing is being blocked', 'orderwarden' ),
				'text'  => __( 'OrderWarden only records what it would block. Switch to Automatic in Settings when you are ready.', 'orderwarden' ),
			);
		}
		$armed = OrderWarden_Guard::armed();
		if ( empty( $armed ) && 'auto' === $mode ) {
			return array(
				'state' => 'learning',
				'title' => __( 'One step left: place a test order', 'orderwarden' ),
				'text'  => __( 'Place one normal order on your store. As soon as OrderWarden sees it go through, protection switches on for that checkout.', 'orderwarden' ),
			);
		}
		return array(
			'state' => 'on',
			'title' => __( 'Your checkout is protected', 'orderwarden' ),
			'text'  => __( 'Orders sent by bots are stopped before WooCommerce creates them or contacts your payment gateway. Real customers see nothing.', 'orderwarden' ),
		);
	}

	/**
	 * Status banner, shown on every tab.
	 *
	 * @return void
	 */
	private static function render_banner() {
		$state = self::overall_state();
		$mode  = OrderWarden_Settings::get( 'mode' );
		?>
		<section class="orderwarden-banner is-<?php echo esc_attr( $state['state'] ); ?>">
			<div>
				<h2><?php echo esc_html( $state['title'] ); ?></h2>
				<p><?php echo esc_html( $state['text'] ); ?></p>
			</div>
			<ul class="orderwarden-channels" aria-label="<?php esc_attr_e( 'Checkout protection', 'orderwarden' ); ?>">
				<?php
				foreach ( array( 'classic', 'store_api', 'pay' ) as $channel ) :
					if ( 'off' === $mode ) {
						$dot   = 'is-off';
						$label = __( 'off', 'orderwarden' );
					} elseif ( OrderWarden_Guard::is_enforcing( $channel ) ) {
						$dot   = 'is-on';
						$label = __( 'protected', 'orderwarden' );
					} else {
						$dot   = '';
						$label = __( 'learning', 'orderwarden' );
					}
					?>
					<li>
						<span class="orderwarden-dot <?php echo esc_attr( $dot ); ?>" aria-hidden="true"></span>
						<?php
						/* translators: 1: checkout type, 2: protection state */
						echo esc_html( sprintf( __( '%1$s: %2$s', 'orderwarden' ), self::channel_label( $channel ), $label ) );
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	/**
	 * Overview tab.
	 *
	 * @return void
	 */
	private static function render_overview() {
		$lifetime = OrderWarden_Logger::lifetime();
		$recent   = OrderWarden_Logger::recent( 20 );
		$failed   = OrderWarden_Cleaner::count( array( 'guest_only' => false ) );
		$figures  = array(
			__( 'Blocked today', 'orderwarden' )             => OrderWarden_Logger::count_since( 'blocked', 24 ),
			__( 'Blocked this week', 'orderwarden' )         => OrderWarden_Logger::count_since( 'blocked', 24 * 7 ),
			__( 'Blocked since install', 'orderwarden' )     => isset( $lifetime['blocked'] ) ? (int) $lifetime['blocked'] : 0,
			__( 'Real checkouts today', 'orderwarden' )      => OrderWarden_Logger::count_since( 'verified', 24 ),
		);

		self::render_banner();
		?>
		<section class="orderwarden-panel">
			<dl class="orderwarden-figures">
				<?php foreach ( $figures as $label => $value ) : ?>
					<div>
						<dt><?php echo esc_html( $label ); ?></dt>
						<dd><?php echo esc_html( number_format_i18n( $value ) ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
			<?php if ( $failed > 0 ) : ?>
				<div class="orderwarden-callout">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of failed orders */
								_n( 'Your store has %s failed order. Attacks usually leave many behind.', 'Your store has %s failed orders. Attacks usually leave many behind.', $failed, 'orderwarden' ),
								number_format_i18n( $failed )
							)
						);
						?>
					</p>
					<a class="orderwarden-link" href="<?php echo esc_url( self::url( 'cleanup' ) ); ?>"><?php esc_html_e( 'Clean up failed orders', 'orderwarden' ); ?></a>
				</div>
			<?php endif; ?>
		</section>

		<section class="orderwarden-panel">
			<div class="orderwarden-panel-head">
				<h2><?php esc_html_e( 'Recent blocked attempts', 'orderwarden' ); ?></h2>
				<p><?php esc_html_e( 'IP addresses are shortened for privacy.', 'orderwarden' ); ?></p>
			</div>
			<?php if ( empty( $recent ) ) : ?>
				<div class="orderwarden-empty">
					<strong><?php esc_html_e( 'No attempts so far', 'orderwarden' ); ?></strong>
					<?php esc_html_e( 'When a bot tries your checkout, it shows up here with the reason it was stopped.', 'orderwarden' ); ?>
				</div>
			<?php else : ?>
				<table class="orderwarden-table">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'When', 'orderwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Result', 'orderwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Why', 'orderwarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Where', 'orderwarden' ); ?></th>
						<th scope="col" class="is-ip"><?php esc_html_e( 'IP', 'orderwarden' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $recent as $row ) : ?>
						<tr>
							<td class="is-time">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: human time difference, e.g. 5 mins */
										__( '%s ago', 'orderwarden' ),
										human_time_diff( strtotime( $row['created_at'] . ' UTC' ) )
									)
								);
								?>
							</td>
							<td>
								<?php if ( 'blocked' === $row['event'] ) : ?>
									<span class="orderwarden-tag is-blocked"><?php esc_html_e( 'Blocked', 'orderwarden' ); ?></span>
								<?php else : ?>
									<span class="orderwarden-tag is-would"><?php esc_html_e( 'Would block', 'orderwarden' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( self::reason_label( $row['reason'] ) ); ?></td>
							<td><?php echo esc_html( self::channel_label( $row['channel'] ) ); ?></td>
							<td class="is-ip"><code><?php echo esc_html( $row['ip_masked'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<?php if ( ! self::pro_installed() ) : ?>
		<div class="orderwarden-footnote">
			<span><?php esc_html_e( 'OrderWarden Pro adds automatic bans, Slack, Discord and Telegram alerts, and a weekly report.', 'orderwarden' ); ?></span>
			<a class="button ow-btn-gold" href="<?php echo esc_url( self::url( 'pro-bans' ) ); ?>"><?php esc_html_e( 'See Pro features', 'orderwarden' ); ?></a>
		</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * A yes/no switch.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Visible label.
	 * @return void
	 */
	private static function field_switch( $key, $label ) {
		$name = OrderWarden_Settings::OPTION . '[' . $key . ']';
		?>
		<label class="orderwarden-switch">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="yes" <?php checked( OrderWarden_Settings::get( $key ), 'yes' ); ?>>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * A number input for use inside a sentence.
	 *
	 * @param string $key   Setting key.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @param string $label Accessible label.
	 * @return string
	 */
	private static function field_number( $key, $min, $max, $label ) {
		return sprintf(
			'<input type="number" min="%1$d" max="%2$d" name="%3$s" value="%4$s" aria-label="%5$s">',
			(int) $min,
			(int) $max,
			esc_attr( OrderWarden_Settings::OPTION . '[' . $key . ']' ),
			esc_attr( OrderWarden_Settings::get( $key ) ),
			esc_attr( $label )
		);
	}

	/**
	 * Settings tab.
	 *
	 * @return void
	 */
	private static function render_settings() {
		$s    = OrderWarden_Settings::all();
		$name = OrderWarden_Settings::OPTION;
		settings_errors();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'orderwarden' ); ?>

			<section class="orderwarden-panel">
				<div class="orderwarden-panel-head"><h2><?php esc_html_e( 'Protection', 'orderwarden' ); ?></h2></div>

				<div class="orderwarden-row">
					<div class="orderwarden-row-label">
						<label for="ow-mode"><?php esc_html_e( 'Mode', 'orderwarden' ); ?></label>
						<p><?php esc_html_e( 'How OrderWarden treats checkout requests.', 'orderwarden' ); ?></p>
					</div>
					<div class="orderwarden-row-control">
						<select id="ow-mode" name="<?php echo esc_attr( $name ); ?>[mode]">
							<option value="auto" <?php selected( $s['mode'], 'auto' ); ?>><?php esc_html_e( 'Automatic (recommended)', 'orderwarden' ); ?></option>
							<option value="protect" <?php selected( $s['mode'], 'protect' ); ?>><?php esc_html_e( 'Always protect', 'orderwarden' ); ?></option>
							<option value="learning" <?php selected( $s['mode'], 'learning' ); ?>><?php esc_html_e( 'Learning only, never block', 'orderwarden' ); ?></option>
							<option value="off" <?php selected( $s['mode'], 'off' ); ?>><?php esc_html_e( 'Off', 'orderwarden' ); ?></option>
						</select>
						<p class="orderwarden-help"><?php esc_html_e( 'Automatic waits until it has seen one real order on each checkout before it blocks anything there, so a theme or cache problem can never turn customers away.', 'orderwarden' ); ?></p>
					</div>
				</div>

				<div class="orderwarden-row">
					<div class="orderwarden-row-label">
						<?php esc_html_e( 'Failed payment limit', 'orderwarden' ); ?>
						<p><?php esc_html_e( 'Catches bots that open your checkout in a real browser.', 'orderwarden' ); ?></p>
					</div>
					<div class="orderwarden-row-control">
						<?php self::field_switch( 'velocity_enabled', __( 'Turn away an address after repeated failed payments', 'orderwarden' ) ); ?>
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: 1: number of failed payments, 2: minutes window, 3: minutes blocked */
									esc_html__( 'After %1$s failed payments within %2$s minutes, block that address for %3$s minutes.', 'orderwarden' ),
									self::field_number( 'velocity_limit', 1, 50, __( 'Failed payments', 'orderwarden' ) ),
									self::field_number( 'velocity_window', 1, 1440, __( 'Within minutes', 'orderwarden' ) ),
									self::field_number( 'velocity_block', 1, 10080, __( 'Block for minutes', 'orderwarden' ) )
								),
								self::allowed_html()
							);
							?>
						</p>
					</div>
				</div>

				<div class="orderwarden-row">
					<div class="orderwarden-row-label">
						<?php esc_html_e( 'Returning customers', 'orderwarden' ); ?>
						<p><?php esc_html_e( 'People you already know.', 'orderwarden' ); ?></p>
					</div>
					<div class="orderwarden-row-control">
						<?php self::field_switch( 'trust_customers', __( 'Never block logged-in customers who have a paid order', 'orderwarden' ) ); ?>
					</div>
				</div>
			</section>

			<section class="orderwarden-panel">
				<div class="orderwarden-panel-head"><h2><?php esc_html_e( 'Alerts', 'orderwarden' ); ?></h2></div>

				<div class="orderwarden-row">
					<div class="orderwarden-row-label">
						<?php esc_html_e( 'Attack email', 'orderwarden' ); ?>
						<p><?php esc_html_e( 'At most one email per hour.', 'orderwarden' ); ?></p>
					</div>
					<div class="orderwarden-row-control">
						<?php self::field_switch( 'alert_enabled', __( 'Email me when an attack starts', 'orderwarden' ) ); ?>
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: number of blocked attempts */
									esc_html__( 'Send it after %s blocked attempts within 10 minutes.', 'orderwarden' ),
									self::field_number( 'alert_threshold', 5, 10000, __( 'Blocked attempts', 'orderwarden' ) )
								),
								self::allowed_html()
							);
							?>
						</p>
						<input type="email" aria-label="<?php esc_attr_e( 'Alert email address', 'orderwarden' ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" name="<?php echo esc_attr( $name ); ?>[alert_email]" value="<?php echo esc_attr( $s['alert_email'] ); ?>">
						<p class="orderwarden-help"><?php esc_html_e( 'Leave empty to use the site admin email.', 'orderwarden' ); ?></p>
					</div>
				</div>
			</section>

			<section class="orderwarden-panel">
				<div class="orderwarden-panel-head"><h2><?php esc_html_e( 'Advanced', 'orderwarden' ); ?></h2></div>

				<div class="orderwarden-row">
					<div class="orderwarden-row-label">
						<?php esc_html_e( 'Cloudflare or proxy', 'orderwarden' ); ?>
						<p><?php esc_html_e( 'Where visitor IP addresses come from.', 'orderwarden' ); ?></p>
					</div>
					<div class="orderwarden-row-control">
						<?php self::field_switch( 'use_proxy_headers', __( 'My site is behind Cloudflare or another proxy', 'orderwarden' ) ); ?>
						<p class="orderwarden-help"><?php esc_html_e( 'Only turn this on if a proxy really sits in front of your site. Otherwise visitors could fake their address.', 'orderwarden' ); ?></p>
					</div>
				</div>

				<div class="orderwarden-row">
					<div class="orderwarden-row-label">
						<label for="ow-allow"><?php esc_html_e( 'Never block these IPs', 'orderwarden' ); ?></label>
						<p><?php esc_html_e( 'For your office, staff or a headless checkout.', 'orderwarden' ); ?></p>
					</div>
					<div class="orderwarden-row-control">
						<textarea id="ow-allow" rows="4" name="<?php echo esc_attr( $name ); ?>[ip_allowlist]" placeholder="203.0.113.10"><?php echo esc_textarea( $s['ip_allowlist'] ); ?></textarea>
						<p class="orderwarden-help"><?php esc_html_e( 'One address or range per line, for example 203.0.113.10 or 203.0.113.0/24.', 'orderwarden' ); ?></p>
					</div>
				</div>

				<div class="orderwarden-row">
					<div class="orderwarden-row-label">
						<?php esc_html_e( 'Activity log', 'orderwarden' ); ?>
					</div>
					<div class="orderwarden-row-control">
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: number of days */
									esc_html__( 'Keep blocked attempts for %s days.', 'orderwarden' ),
									self::field_number( 'log_retention_days', 1, 365, __( 'Days', 'orderwarden' ) )
								),
								self::allowed_html()
							);
							?>
						</p>
					</div>
				</div>

				<div class="orderwarden-row">
					<div class="orderwarden-row-label">
						<?php esc_html_e( 'When you delete the plugin', 'orderwarden' ); ?>
					</div>
					<div class="orderwarden-row-control">
						<?php self::field_switch( 'delete_data', __( 'Also delete its settings and activity log', 'orderwarden' ) ); ?>
					</div>
				</div>

				<div class="orderwarden-actions">
					<?php submit_button( __( 'Save settings', 'orderwarden' ), 'orderwarden-btn', 'submit', false ); ?>
				</div>
			</section>
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

		if ( ! empty( $state['running'] ) ) :
			$total   = max( 1, (int) $state['total'] );
			$percent = min( 100, (int) round( ( (int) $state['done'] / $total ) * 100 ) );
			?>
			<meta http-equiv="refresh" content="5">
			<section class="orderwarden-panel">
				<div class="orderwarden-panel-head"><h2><?php esc_html_e( 'Cleaning up failed orders', 'orderwarden' ); ?></h2></div>
				<div class="orderwarden-panel-body">
					<div class="orderwarden-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $percent ); ?>"><span style="width: <?php echo esc_attr( $percent ); ?>%"></span></div>
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: orders removed so far, 2: total orders */
								__( '%1$s of about %2$s orders removed. This page updates by itself, and you can leave it.', 'orderwarden' ),
								number_format_i18n( (int) $state['done'] ),
								number_format_i18n( (int) $state['total'] )
							)
						);
						?>
					</p>
				</div>
				<form class="orderwarden-actions" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="orderwarden_cleanup_cancel">
					<?php wp_nonce_field( 'orderwarden_cleanup_cancel' ); ?>
					<?php submit_button( __( 'Stop cleanup', 'orderwarden' ), 'orderwarden-btn-secondary', 'submit', false ); ?>
				</form>
			</section>
			<?php
			return;
		endif;

		if ( ! empty( $state['finished'] ) ) :
			?>
			<div class="notice notice-success inline"><p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: number of orders */
						_n( 'Cleanup finished. %s order removed.', 'Cleanup finished. %s orders removed.', (int) $state['done'], 'orderwarden' ),
						number_format_i18n( (int) $state['done'] )
					)
				);
				?>
			</p></div>
			<?php
		endif;
		?>
		<section class="orderwarden-panel">
			<div class="orderwarden-panel-head">
				<h2><?php esc_html_e( 'Failed orders', 'orderwarden' ); ?></h2>
				<p><?php esc_html_e( 'Runs in the background, so large numbers are fine.', 'orderwarden' ); ?></p>
			</div>
			<dl class="orderwarden-figures">
				<div>
					<dt><?php esc_html_e( 'All failed orders', 'orderwarden' ); ?></dt>
					<dd><?php echo esc_html( number_format_i18n( $all ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'From guests', 'orderwarden' ); ?></dt>
					<dd><?php echo esc_html( number_format_i18n( $guest ) ); ?></dd>
				</div>
			</dl>

			<?php if ( 0 === $all ) : ?>
				<div class="orderwarden-empty">
					<strong><?php esc_html_e( 'Nothing to clean up', 'orderwarden' ); ?></strong>
					<?php esc_html_e( 'Your store has no failed orders right now.', 'orderwarden' ); ?>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="orderwarden_cleanup_start">
					<?php wp_nonce_field( 'orderwarden_cleanup_start' ); ?>

					<div class="orderwarden-row">
						<div class="orderwarden-row-label">
							<?php esc_html_e( 'Which orders', 'orderwarden' ); ?>
							<p><?php esc_html_e( 'A failed order can also be a real customer whose card was declined. Guest orders are the safe place to start.', 'orderwarden' ); ?></p>
						</div>
						<div class="orderwarden-row-control">
							<label class="orderwarden-switch">
								<input type="checkbox" name="guest_only" value="1" checked>
								<span><?php esc_html_e( 'Only orders from guests, not from customer accounts', 'orderwarden' ); ?></span>
							</label>
							<p>
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %s: number of days */
										esc_html__( 'Only orders older than %s days. Use 0 for any age.', 'orderwarden' ),
										'<input type="number" min="0" max="3650" name="older_than_days" value="0" aria-label="' . esc_attr__( 'Days', 'orderwarden' ) . '">'
									),
									self::allowed_html()
								);
								?>
							</p>
						</div>
					</div>

					<div class="orderwarden-row">
						<div class="orderwarden-row-label">
							<?php esc_html_e( 'What happens to them', 'orderwarden' ); ?>
						</div>
						<div class="orderwarden-row-control">
							<label class="orderwarden-choice"><input type="radio" name="permanent" value="0" checked> <span><?php esc_html_e( 'Move to Trash. You can restore them for 30 days.', 'orderwarden' ); ?></span></label>
							<label class="orderwarden-choice"><input type="radio" name="permanent" value="1"> <span><?php esc_html_e( 'Delete permanently. This cannot be undone.', 'orderwarden' ); ?></span></label>
						</div>
					</div>

					<div class="orderwarden-actions">
						<label class="orderwarden-choice"><input type="checkbox" name="confirm" value="1" required> <span><?php esc_html_e( 'I understand these failed orders will be removed.', 'orderwarden' ); ?></span></label>
						<?php submit_button( __( 'Start cleanup', 'orderwarden' ), 'orderwarden-btn', 'submit', false ); ?>
					</div>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Small check icon for the checklist.
	 *
	 * @return void
	 */
	private static function check_icon() {
		echo '<svg width="18" height="18" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="9" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M6 10.4l2.6 2.6L14 7.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
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

		$gateway_items = array();
		foreach ( self::gateway_tips() as $prefix => $info ) {
			foreach ( $enabled as $id ) {
				if ( 0 === strpos( $id, $prefix ) ) {
					$gateway_items[] = $info;
					break;
				}
			}
		}

		$general = array(
			array(
				'title' => __( 'Cloudflare, free plan', 'orderwarden' ),
				'tip'   => __( 'Turn on Security > Bots > Bot Fight Mode, and add one rate limiting rule for your checkout page and for /?wc-ajax=checkout.', 'orderwarden' ),
			),
			array(
				'title' => __( 'WooCommerce rate limit', 'orderwarden' ),
				'tip'   => __( 'If you use the block checkout, turn on WooCommerce > Settings > Advanced > Features > Rate limiting Checkout block and Store API.', 'orderwarden' ),
			),
			array(
				'title' => __( 'Guest checkout', 'orderwarden' ),
				'tip'   => __( 'If it suits your store, turn off guest checkout in WooCommerce > Settings > Accounts & Privacy so bots must create an account first.', 'orderwarden' ),
			),
			array(
				'title' => __( 'Updates', 'orderwarden' ),
				'tip'   => __( 'Keep WooCommerce and your payment plugin up to date. Card testing often targets old gateway versions.', 'orderwarden' ),
			),
		);
		?>
		<section class="orderwarden-panel">
			<div class="orderwarden-panel-head">
				<h2><?php esc_html_e( 'Your payment gateways', 'orderwarden' ); ?></h2>
				<p><?php esc_html_e( 'Free settings that add a second layer at the gateway.', 'orderwarden' ); ?></p>
			</div>
			<?php if ( empty( $gateway_items ) ) : ?>
				<div class="orderwarden-empty">
					<strong><?php esc_html_e( 'No tips for your gateways yet', 'orderwarden' ); ?></strong>
					<?php esc_html_e( 'Ask your payment provider whether it offers velocity limits and CVC checks, and turn them on.', 'orderwarden' ); ?>
				</div>
			<?php else : ?>
				<ul class="orderwarden-checklist">
					<?php foreach ( $gateway_items as $item ) : ?>
						<li><?php self::check_icon(); ?><div><strong><?php echo esc_html( $item['title'] ); ?></strong><p><?php echo esc_html( $item['tip'] ); ?></p></div></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<section class="orderwarden-panel">
			<div class="orderwarden-panel-head">
				<h2><?php esc_html_e( 'For every store', 'orderwarden' ); ?></h2>
			</div>
			<ul class="orderwarden-checklist">
				<?php foreach ( $general as $item ) : ?>
					<li><?php self::check_icon(); ?><div><strong><?php echo esc_html( $item['title'] ); ?></strong><p><?php echo esc_html( $item['tip'] ); ?></p></div></li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}


	/**
	 * Small line icon for a Pro feature (original artwork).
	 *
	 * @param string $key Feature key.
	 * @return void
	 */
	private static function pro_icon( $key ) {
		$paths = array(
			'bans'    => '<path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.4 7.5 9.5 4.3-1.1 7.5-4.9 7.5-9.5V6L12 3Z"/><path d="m8.5 8.5 7 7m0-7-7 7"/>',
			'alerts'  => '<path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16Z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
			'report'  => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4m8-4v4M8 15v-3m4 3V10m4 5v-2"/>',
			'history' => '<path d="M4 12a8 8 0 1 0 2.4-5.7L4 8.5"/><path d="M4 4v4.5h4.5M12 8v4.5l3 2"/>',
			'agency'  => '<rect x="3" y="4" width="8" height="7" rx="1.5"/><rect x="13" y="4" width="8" height="7" rx="1.5"/><rect x="3" y="13" width="8" height="7" rx="1.5"/><rect x="13" y="13" width="8" height="7" rx="1.5"/>',
		);
		$path = isset( $paths[ $key ] ) ? $paths[ $key ] : $paths['bans'];
		echo '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . wp_kses( $path, array( 'path' => array( 'd' => true ), 'rect' => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true ) ) ) . '</svg>';
	}

	/**
	 * Plans shown on the Pro pages. Prices are set in Freemius; keep these in sync.
	 *
	 * @return array
	 */
	private static function pro_plans() {
		return array(
			1  => array(
				'name'  => __( 'Single store', 'orderwarden' ),
				'for'   => __( 'For one shop', 'orderwarden' ),
				'price' => 49,
			),
			5  => array(
				'name'  => __( '5 stores', 'orderwarden' ),
				'for'   => __( 'For growing brands', 'orderwarden' ),
				'price' => 189,
			),
			25 => array(
				'name'  => __( '25 stores', 'orderwarden' ),
				'for'   => __( 'For agencies', 'orderwarden' ),
				'price' => 379,
			),
		);
	}

	/**
	 * Page for one Pro feature, with plans. Nothing here is locked code;
	 * the feature itself ships only in the paid add-on.
	 *
	 * @param string $current Feature key.
	 * @return void
	 */
	private static function render_pro( $current ) {
		$features = self::pro_features();
		if ( ! isset( $features[ $current ] ) ) {
			$current = key( $features );
		}
		$feature = $features[ $current ];
		$buy     = self::pro_url();
		?>
		<nav class="ow-feature-nav" aria-label="<?php esc_attr_e( 'Pro features', 'orderwarden' ); ?>">
			<?php foreach ( $features as $key => $item ) : ?>
				<a href="<?php echo esc_url( self::url( 'pro-' . $key ) ); ?>"<?php echo $key === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item['menu'] ); ?></a>
			<?php endforeach; ?>
		</nav>

		<section class="ow-sale">
			<div class="ow-sale-main">
				<span class="ow-sale-icon"><?php self::pro_icon( $current ); ?></span>
				<h2><?php echo esc_html( $feature['title'] ); ?></h2>
				<p><?php echo esc_html( $feature['intro'] ); ?></p>
				<ul class="ow-points">
					<?php foreach ( $feature['points'] as $point ) : ?>
						<li><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="ow-sale-aside">
				<span class="ow-pro-badge"><?php esc_html_e( 'Pro', 'orderwarden' ); ?></span>
				<h3 style="margin-top:14px"><?php esc_html_e( 'Part of OrderWarden Pro', 'orderwarden' ); ?></h3>
				<p><?php esc_html_e( 'Pro is a separate add-on. Everything you use today stays free, with no limits, and Pro adds every feature listed on these pages.', 'orderwarden' ); ?></p>
				<?php if ( '' === $buy ) : ?>
					<p><strong><?php esc_html_e( 'Coming soon.', 'orderwarden' ); ?></strong></p>
				<?php else : ?>
					<a class="button ow-btn-gold" href="#ow-plans"><?php esc_html_e( 'See plans', 'orderwarden' ); ?></a>
				<?php endif; ?>
			</div>
		</section>

		<?php if ( '' !== $buy ) : ?>
			<div id="ow-plans" class="ow-pricing">
				<?php foreach ( self::pro_plans() as $sites => $plan ) : ?>
					<div class="ow-plan<?php echo 5 === $sites ? ' is-featured' : ''; ?>">
						<?php if ( 5 === $sites ) : ?>
							<span class="ow-plan-flag"><?php esc_html_e( 'Best value', 'orderwarden' ); ?></span>
						<?php endif; ?>
						<h3><?php echo esc_html( $plan['name'] ); ?></h3>
						<p class="ow-plan-for"><?php echo esc_html( $plan['for'] ); ?></p>
						<div class="ow-price"><strong><?php echo esc_html( '$' . $plan['price'] ); ?></strong><span><?php esc_html_e( '/ year', 'orderwarden' ); ?></span></div>
						<p class="ow-per">
							<?php
							/* translators: %s: price per store per year */
							echo esc_html( 1 === $sites ? __( 'Everything in Pro', 'orderwarden' ) : sprintf( __( 'Only $%s per store', 'orderwarden' ), number_format_i18n( $plan['price'] / $sites, 2 ) ) );
							?>
						</p>
						<ul class="ow-points">
							<li><?php esc_html_e( 'Automatic bans', 'orderwarden' ); ?></li>
							<li><?php esc_html_e( 'Slack, Discord, Telegram alerts', 'orderwarden' ); ?></li>
							<li><?php esc_html_e( 'Weekly report and full history', 'orderwarden' ); ?></li>
							<li><?php echo esc_html( 1 === $sites ? __( 'Email support', 'orderwarden' ) : __( 'Priority email support', 'orderwarden' ) ); ?></li>
						</ul>
						<a class="button <?php echo 5 === $sites ? 'ow-btn-gold' : 'orderwarden-btn'; ?>" href="<?php echo esc_url( trailingslashit( $buy ) . 'licenses/' . $sites . '/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Pro', 'orderwarden' ); ?></a>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="ow-guarantee"><?php esc_html_e( '14-day money back guarantee. Secure checkout by Freemius.', 'orderwarden' ); ?></p>
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
}
