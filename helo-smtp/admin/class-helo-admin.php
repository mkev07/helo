<?php
/**
 * Admin: submenu pages, routing, form handlers.
 *
 * Helo is organised as one parent menu with several submenu screens:
 *   Helo (dashboard) - Mail - Email log - Bot protection - Security log.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Admin {

	const SLUG      = 'helo';
	const SUB_MAIL       = 'helo-mail';
	const SUB_LOGS       = 'helo-logs';
	const SUB_SECURITY   = 'helo-security';
	const SUB_ANALYTICS  = 'helo-analytics';
	const NOTICE_TRANSIENT = 'helo_notice';

	/** Map each screen's slug to the admin-post action it saves, for redirects. */
	const RETURN_MAP = array(
		self::SUB_MAIL      => 'mail_url',
		self::SUB_SECURITY  => 'security_url',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( HELO_FILE ), array( __CLASS__, 'action_links' ) );

		add_action( 'admin_post_helo_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_helo_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_helo_resend', array( __CLASS__, 'handle_resend' ) );
		add_action( 'admin_post_helo_delete', array( __CLASS__, 'handle_delete' ) );
		// Turnstile analytics reset handlers are registered in Helo_Analytics::init().
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Helo — SMTP, Mail Log & Bot Protection', 'helo-smtp' ),
			__( 'Helo', 'helo-smtp' ),
			Helo_Settings::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_dashboard' ),
			self::menu_icon(),
			80
		);

		// Dashboard as a first child carrying the parent slug: WordPress uses
		// this to auto-highlight the parent + this child, avoiding a duplicate
		// auto-added parent flyout link.
		add_submenu_page(
			self::SLUG,
			__( 'Dashboard', 'helo-smtp' ),
			__( 'Dashboard', 'helo-smtp' ),
			Helo_Settings::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_dashboard' )
		);
		add_submenu_page(
			self::SLUG,
			__( 'Mail', 'helo-smtp' ),
			__( 'Mail', 'helo-smtp' ),
			Helo_Settings::CAPABILITY,
			self::SUB_MAIL,
			array( __CLASS__, 'render_mail' )
		);
		add_submenu_page(
			self::SLUG,
			__( 'Email log', 'helo-smtp' ),
			__( 'Email log', 'helo-smtp' ),
			Helo_Settings::CAPABILITY,
			self::SUB_LOGS,
			array( __CLASS__, 'render_logs' )
		);
		add_submenu_page(
			self::SLUG,
			__( 'Bot protection', 'helo-smtp' ),
			__( 'Bot protection', 'helo-smtp' ),
			Helo_Settings::CAPABILITY,
			self::SUB_SECURITY,
			array( __CLASS__, 'render_security' )
		);
		add_submenu_page(
			self::SLUG,
			__( 'Security log', 'helo-smtp' ),
			__( 'Security log', 'helo-smtp' ),
			Helo_Settings::CAPABILITY,
			self::SUB_ANALYTICS,
			array( __CLASS__, 'render_analytics' )
		);
	}

	/**
	 * Menu icon as a base64 data URI, which is what add_menu_page() wants.
	 *
	 * The file is filled white on purpose: WordPress dims menu icons to 60%
	 * opacity when idle and lifts them to full on hover or when current, so a
	 * white source lands on the same grey and white as the built-in dashicons.
	 *
	 * @return string
	 */
	private static function menu_icon() {
		$svg = file_get_contents( HELO_PATH . 'admin/img/menu-icon.svg' );

		return $svg
			? 'data:image/svg+xml;base64,' . base64_encode( $svg )
			: 'dashicons-email-alt';
	}

	/**
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		// Load our CSS on the dashboard (toplevel_page_helo) and every submenu
		// (helo_page_helo-*). A prefix match keeps this working without listing
		// every hook.
		if ( 0 !== strpos( $hook_suffix, 'helo' ) ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'helo-admin', HELO_URL . 'admin/css/admin.css', array(), HELO_VERSION );
	}

	/**
	 * @param string[] $links Existing plugin row links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::mail_url() ), esc_html__( 'Settings', 'helo-smtp' ) ),
			sprintf( '<a href="%s">%s</a>', esc_url( Helo_Updater::check_url() ), esc_html__( 'Check for updates', 'helo-smtp' ) )
		);

		return $links;
	}

	/* ------------------------------------------------------------------ urls */

	/** Dashboard (parent) URL. */
	public static function url( array $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => self::SLUG ), $args ),
			admin_url( 'admin.php' )
		);
	}

	public static function mail_url() {
		return add_query_arg( 'page', self::SUB_MAIL, admin_url( 'admin.php' ) );
	}

	public static function logs_url( $search = '', $paged = 1, $log_id = 0 ) {
		$args = array( 'page' => self::SUB_LOGS );

		if ( '' !== $search ) {
			$args['s'] = rawurlencode( $search );
		}

		if ( $paged > 1 ) {
			$args['paged'] = (int) $paged;
		}

		if ( $log_id ) {
			$args['log'] = (int) $log_id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	public static function security_url() {
		return add_query_arg( 'page', self::SUB_SECURITY, admin_url( 'admin.php' ) );
	}

	public static function analytics_url() {
		return add_query_arg( 'page', self::SUB_ANALYTICS, admin_url( 'admin.php' ) );
	}

	/**
	 * A nonced admin-post.php URL for a per-row action.
	 *
	 * @param string $action admin_post action name.
	 * @param int    $id     Log entry id.
	 * @return string
	 */
	public static function action_url( $action, $id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => $action,
					'id'     => (int) $id,
				),
				admin_url( 'admin-post.php' )
			),
			$action . '_' . (int) $id
		);
	}

	/* --------------------------------------------------------------- render */

	/** Cap-check + page scaffold shared by every screen. */
	private static function page_top( $title, $subtitle = '', array $badges = array() ) {
		if ( ! current_user_can( Helo_Settings::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'helo-smtp' ) );
		}

		echo '<div class="wrap helo-app">';

		if ( '' === $title ) {
			return;
		}
		?>
		<div class="helo-head">
			<div>
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( '' !== $subtitle ) : ?>
					<p><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $badges ) ) : ?>
				<div class="helo-row"><?php foreach ( $badges as $helo_b ) : ?><span class="helo-badge helo-badge--<?php echo esc_attr( $helo_b[0] ); ?>"><?php echo esc_html( $helo_b[1] ); ?></span><?php endforeach; ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function page_bottom() {
		echo '</div>';
	}

	public static function render_dashboard() {
		self::page_top(
			__( 'Helo', 'helo-smtp' ),
			__( 'SMTP delivery, a record of every email, and bot protection — in one place.', 'helo-smtp' ),
			array( array( Helo_Settings::is_configured() ? 'ok' : 'muted', Helo_Settings::is_configured() ? __( 'SMTP active', 'helo-smtp' ) : __( 'Using PHP mail()', 'helo-smtp' ) ) )
		);
		self::print_notice();

		Helo_Admin::view(
			'dashboard',
			array(
				'settings' => Helo_Settings::all(),
				'stats'    => Helo_Logger::stats(),
				'mail_count' => Helo_Logger::count(),
			)
		);

		self::page_bottom();
	}

	public static function render_mail() {
		self::page_top(
			__( 'Mail', 'helo-smtp' ),
			__( 'How outbound mail is handed off, and who signs it.', 'helo-smtp' )
		);
		self::print_notice();

		Helo_Admin::view(
			'mail',
			array(
				'settings' => Helo_Settings::all(),
				'stats'    => Helo_Logger::stats(),
			)
		);

		self::page_bottom();
	}

	public static function render_logs() {
		self::page_top( __( 'Email log', 'helo-smtp' ), __( 'Every message this site sent, past and present.', 'helo-smtp' ) );
		self::print_notice();

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$per_page = 25;
		$results  = Helo_Logger::query( compact( 'search', 'paged', 'per_page' ) );

		// Like a mail client, open the newest message when none is picked.
		$log_id = isset( $_GET['log'] ) ? (int) $_GET['log'] : 0;
		if ( ! $log_id && ! empty( $results['rows'] ) ) {
			$log_id = (int) $results['rows'][0]->id;
		}

		$log = $log_id ? Helo_Logger::get( $log_id ) : null;

		Helo_Admin::view( 'logs', $results + compact( 'search', 'paged', 'per_page', 'log' ) );

		self::page_bottom();
	}

	public static function render_security() {
		self::page_top(
			__( 'Bot protection', 'helo-smtp' ),
			__( 'Cloudflare Turnstile keys and which forms they guard.', 'helo-smtp' )
		);
		self::print_notice();

		Helo_Admin::view(
			'security',
			array(
				'settings' => Helo_Settings::all(),
			)
		);

		self::page_bottom();
	}

	public static function render_analytics() {
		self::page_top(
			__( 'Security log', 'helo-smtp' ),
			__( 'How the bot protection is doing — verified vs blocked.', 'helo-smtp' )
		);
		self::print_notice();

		Helo_Admin::view(
			'analytics',
			array(
				'analytics' => Helo_Analytics::snapshot(),
				'log'       => Helo_Analytics::log(),
			)
		);

		self::page_bottom();
	}

	/**
	 * @param string $name View file name, without extension.
	 * @param array  $vars Variables exposed to the view.
	 */
	private static function view( $name, array $vars = array() ) {
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Controlled, internal view data.
		extract( $vars, EXTR_SKIP );

		require HELO_PATH . 'admin/views/' . $name . '.php';
	}

	/* --------------------------------------------------------------- notices */

	/**
	 * @param string $type    'success' or 'error'.
	 * @param string $message Message text.
	 */
	private static function flash( $type, $message ) {
		set_transient( self::NOTICE_TRANSIENT, compact( 'type', 'message' ), MINUTE_IN_SECONDS );
	}

	private static function print_notice() {
		$notice = get_transient( self::NOTICE_TRANSIENT );

		if ( ! $notice ) {
			return;
		}

		delete_transient( self::NOTICE_TRANSIENT );

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			'error' === $notice['type'] ? 'error' : 'success',
			wp_kses( nl2br( esc_html( $notice['message'] ) ), array( 'br' => array() ) )
		);
	}

	/* -------------------------------------------------------------- handlers */

	private static function guard( $nonce_action ) {
		check_admin_referer( $nonce_action );

		if ( ! current_user_can( Helo_Settings::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'helo-smtp' ) );
		}
	}

	/** Redirect after a settings save, honouring the screen the form came from. */
	private static function save_redirect() {
		$slug = isset( $_POST['return_slug'] ) ? sanitize_key( wp_unslash( $_POST['return_slug'] ) ) : '';

		$url = self::SUB_SECURITY === $slug ? self::security_url() : self::mail_url();
		wp_safe_redirect( $url );
		exit;
	}

	public static function handle_save() {
		self::guard( 'helo_save' );

		Helo_Settings::save( wp_unslash( $_POST ) );

		self::flash( 'success', __( 'Settings saved.', 'helo-smtp' ) );
		self::save_redirect();
	}

	public static function handle_test() {
		self::guard( 'helo_test' );

		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';

		if ( ! is_email( $to ) ) {
			self::flash( 'error', __( 'That is not a valid email address.', 'helo-smtp' ) );
			wp_safe_redirect( self::mail_url() );
			exit;
		}

		$result = Helo_Mailer::send_test( $to, ! empty( $_POST['html'] ) );

		if ( $result['sent'] ) {
			self::flash( 'success', __( 'Test email accepted by the mail server. Check the inbox, and the spam folder.', 'helo-smtp' ) );
		} else {
			self::flash(
				'error',
				__( 'Sending failed.', 'helo-smtp' ) . "\n" . (
					$result['errors']
						? implode( "\n", $result['errors'] )
						: __( 'No error was reported. Enable Debug and check the PHP error log.', 'helo-smtp' )
				)
			);
		}

		wp_safe_redirect( self::mail_url() );
		exit;
	}

	public static function handle_resend() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		self::guard( 'helo_resend_' . $id );

		$log = Helo_Logger::get( $id );

		if ( ! $log ) {
			self::flash( 'error', __( 'That log entry no longer exists.', 'helo-smtp' ) );
		} else {
			$sent = Helo_Mailer::resend( $log );

			self::flash(
				$sent ? 'success' : 'error',
				$sent
					? __( 'Email resent.', 'helo-smtp' )
					: __( 'Resend failed. The newest log entry has the error.', 'helo-smtp' )
			);
		}

		wp_safe_redirect( self::logs_url() );
		exit;
	}

	public static function handle_delete() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		self::guard( 'helo_delete_' . $id );

		Helo_Logger::delete( $id );

		self::flash( 'success', __( 'Log entry deleted.', 'helo-smtp' ) );
		wp_safe_redirect( self::logs_url() );
		exit;
	}
}