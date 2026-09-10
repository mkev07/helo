<?php
/**
 * Email log: schema, recording, querying and retention.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Logger {

	const DB_VERSION = '1';
	const DB_OPTION  = 'helo_db_version';
	const CRON_HOOK  = 'helo_purge_logs';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );

		// Both hooks receive the full wp_mail() argument set, so one write per email is enough.
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'record_success' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'record_failure' ) );

		add_action( self::CRON_HOOK, array( __CLASS__, 'purge' ) );
	}

	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'helo_mail_log';
	}

	/* ------------------------------------------------------------ lifecycle */

	public static function install() {
		global $wpdb;

		self::adopt_legacy_data();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				to_email text NOT NULL,
				subject text NOT NULL,
				body longtext NOT NULL,
				headers text NOT NULL,
				attachments text NOT NULL,
				content_type varchar(64) NOT NULL DEFAULT 'text/plain',
				status varchar(20) NOT NULL DEFAULT 'sent',
				error text NOT NULL,
				PRIMARY KEY  (id),
				KEY created_at (created_at),
				KEY status (status)
			) {$charset};"
		);

		update_option( self::DB_OPTION, self::DB_VERSION, false );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Take over the settings and log from the plugin's previous name,
	 * "Simple SMTP + Mail Log", so a rename does not cost you the stored
	 * password or the history.
	 *
	 * ponytail: one-shot migration, delete once every install is on Helo.
	 */
	private static function adopt_legacy_data() {
		global $wpdb;

		$legacy_settings = get_option( 'ssml_settings' );

		if ( false !== $legacy_settings && false === get_option( Helo_Settings::OPTION ) ) {
			// The password is keyed to the site's auth salt, which is unchanged,
			// so it stays decryptable after the move.
			add_option( Helo_Settings::OPTION, $legacy_settings );
		}

		delete_option( 'ssml_settings' );
		delete_option( 'ssml_db_version' );
		wp_clear_scheduled_hook( 'ssml_purge_logs' );

		$legacy_table = $wpdb->prefix . 'ssml_mail_log';
		$table        = self::table();

		$has_legacy = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy_table ) ) );
		$has_new    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

		if ( $has_legacy && ! $has_new ) {
			$wpdb->query( "RENAME TABLE `{$legacy_table}` TO `{$table}`" );
		}
	}

	/** Creates or updates the table for sites that were upgraded without re-activating. */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/* --------------------------------------------------------------- record */

	/**
	 * @param array $mail_data wp_mail() arguments, as passed by WordPress.
	 */
	public static function record_success( $mail_data ) {
		self::insert( (array) $mail_data, 'sent' );
	}

	/**
	 * @param WP_Error $error Error whose data carries the wp_mail() arguments.
	 */
	public static function record_failure( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}

		self::insert( (array) $error->get_error_data(), 'failed', $error->get_error_message() );
	}

	private static function insert( array $mail, $status, $error = '' ) {
		if ( ! Helo_Settings::get( 'logging' ) ) {
			return;
		}

		global $wpdb;

		$wpdb->insert(
			self::table(),
			array(
				'created_at'   => current_time( 'mysql' ),
				'to_email'     => self::flatten( isset( $mail['to'] ) ? $mail['to'] : '' ),
				'subject'      => (string) ( isset( $mail['subject'] ) ? $mail['subject'] : '' ),
				'body'         => (string) ( isset( $mail['message'] ) ? $mail['message'] : '' ),
				'headers'      => self::flatten( isset( $mail['headers'] ) ? $mail['headers'] : '' ),
				'attachments'  => self::flatten( isset( $mail['attachments'] ) ? $mail['attachments'] : '' ),
				'content_type' => Helo_Mailer::content_type(),
				'status'       => $status,
				'error'        => (string) $error,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	private static function flatten( $value ) {
		return is_array( $value ) ? implode( "\n", array_map( 'strval', $value ) ) : (string) $value;
	}

	/* ---------------------------------------------------------------- query */

	/**
	 * @param array $args search, paged, per_page.
	 * @return array{rows: array, total: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'paged'    => 1,
				'per_page' => 25,
			)
		);

		$table  = self::table();
		$where  = 'WHERE 1=1';
		$params = array();

		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where  .= ' AND ( to_email LIKE %s OR subject LIKE %s )';
			$params  = array( $like, $like );
		}

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) )
			: $wpdb->get_var( $count_sql ) );

		$offset = ( max( 1, (int) $args['paged'] ) - 1 ) * (int) $args['per_page'];

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, created_at, to_email, subject, status, error FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( (int) $args['per_page'], $offset ) )
			)
		);

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Headline numbers for the settings screen.
	 *
	 * @param int $days Window for the sent/failed counts.
	 * @return array{total: int, sent: int, failed: int, last: object|null}
	 */
	public static function stats( $days = 7 ) {
		global $wpdb;

		$table  = self::table();
		$since  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );

		$counts = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, SUM( status = 'failed' ) AS failed FROM {$table} WHERE created_at >= %s",
				$since
			)
		);

		return array(
			'total'  => $counts ? (int) $counts->total : 0,
			'failed' => $counts ? (int) $counts->failed : 0,
			'sent'   => $counts ? (int) $counts->total - (int) $counts->failed : 0,
			'last'   => $wpdb->get_row( "SELECT id, created_at, status, subject FROM {$table} ORDER BY id DESC LIMIT 1" ),
		);
	}

	/** Total rows in the log, for the tab counter. */
	public static function count() {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
	}

	/**
	 * @param int $id Log entry id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	/**
	 * @param int $id Log entry id.
	 * @return int Rows deleted.
	 */
	public static function delete( $id ) {
		global $wpdb;

		return (int) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/** Daily cron: drop entries older than the configured retention. */
	public static function purge() {
		$days = (int) Helo_Settings::get( 'log_days' );

		if ( $days < 1 ) {
			return; // 0 means keep forever.
		}

		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );

		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE created_at < %s', $cutoff ) );
	}
}
