<?php
/**
 * Admin screen: menu, tab routing, form handlers.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Admin {

	const SLUG           = 'helo';
	const NOTICE_TRANSIENT = 'helo_notice';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( HELO_FILE ), array( __CLASS__, 'action_links' ) );

		add_action( 'admin_post_helo_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_helo_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_helo_resend', array( __CLASS__, 'handle_resend' ) );
		add_action( 'admin_post_helo_delete', array( __CLASS__, 'handle_delete' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Helo — SMTP & Mail Log', 'helo-smtp' ),
			__( 'Helo', 'helo-smtp' ),
			Helo_Settings::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render' ),
			self::menu_icon(),
			80
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
		if ( 'toplevel_page_' . self::SLUG !== $hook_suffix ) {
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
			sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'helo-smtp' ) )
		);

		return $links;
	}

	/* ------------------------------------------------------------------ urls */

	/**
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => self::SLUG ), $args ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * A log URL that keeps the current search and page, so selecting a message
	 * does not throw away where you were in the list.
	 *
	 * @param string $search Current search term.
	 * @param int    $paged  Current page.
	 * @param int    $log_id Message to select, 0 for none.
	 * @return string
	 */
	public static function log_url( $search = '', $paged = 1, $log_id = 0 ) {
		$args = array( 'tab' => 'logs' );

		if ( '' !== $search ) {
			// add_query_arg() does not encode values.
			$args['s'] = rawurlencode( $search );
		}

		if ( $paged > 1 ) {
			$args['paged'] = (int) $paged;
		}

		if ( $log_id ) {
			$args['log'] = (int) $log_id;
		}

		return self::url( $args );
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

	public static function render() {
		if ( ! current_user_can( Helo_Settings::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'helo-smtp' ) );
		}

		$tab = ( isset( $_GET['tab'] ) && 'logs' === $_GET['tab'] ) ? 'logs' : 'settings';

		echo '<div class="wrap helo-app">';

		self::print_header();
		self::print_notice();
		self::print_tabs( $tab );

		if ( 'logs' === $tab ) {
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

			self::view( 'logs', $results + compact( 'search', 'paged', 'per_page', 'log' ) );
		} else {
			self::view(
				'settings',
				array(
					'settings' => Helo_Settings::all(),
					'stats'    => Helo_Logger::stats(),
				)
			);
		}

		echo '</div>';
	}

	private static function print_header() {
		?>
		<div class="helo-head">
			<div>
				<h1>Helo</h1>
				<p><?php esc_html_e( 'SMTP delivery, and a record of every email this site sends.', 'helo-smtp' ); ?></p>
			</div>
			<div class="helo-row">
				<?php if ( Helo_Settings::is_configured() ) : ?>
					<span class="helo-badge helo-badge--ok"><?php esc_html_e( 'SMTP active', 'helo-smtp' ); ?></span>
				<?php else : ?>
					<span class="helo-badge helo-badge--muted"><?php esc_html_e( 'Using PHP mail()', 'helo-smtp' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string $current Active tab.
	 */
	private static function print_tabs( $current ) {
		$count = Helo_Logger::count();
		?>
		<nav class="helo-tabs">
			<a href="<?php echo esc_url( self::url() ); ?>" <?php echo 'settings' === $current ? 'aria-current="page"' : ''; ?>>
				<?php esc_html_e( 'Settings', 'helo-smtp' ); ?>
			</a>
			<a href="<?php echo esc_url( self::url( array( 'tab' => 'logs' ) ) ); ?>" <?php echo 'logs' === $current ? 'aria-current="page"' : ''; ?>>
				<?php esc_html_e( 'Email log', 'helo-smtp' ); ?>
				<?php if ( $count ) : ?>
					<span class="helo-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
				<?php endif; ?>
			</a>
		</nav>
		<?php
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

	public static function handle_save() {
		self::guard( 'helo_save' );

		Helo_Settings::save( wp_unslash( $_POST ) );

		self::flash( 'success', __( 'Settings saved.', 'helo-smtp' ) );
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function handle_test() {
		self::guard( 'helo_test' );

		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';

		if ( ! is_email( $to ) ) {
			self::flash( 'error', __( 'That is not a valid email address.', 'helo-smtp' ) );
			wp_safe_redirect( self::url() );
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

		wp_safe_redirect( self::url() );
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

		wp_safe_redirect( self::url( array( 'tab' => 'logs' ) ) );
		exit;
	}

	public static function handle_delete() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		self::guard( 'helo_delete_' . $id );

		Helo_Logger::delete( $id );

		self::flash( 'success', __( 'Log entry deleted.', 'helo-smtp' ) );
		wp_safe_redirect( self::url( array( 'tab' => 'logs' ) ) );
		exit;
	}
}
