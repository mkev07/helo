<?php
/**
 * Plugin settings: storage, defaults, sanitising and password encryption.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Settings {

	const OPTION    = 'helo_settings';
	const CAPABILITY = 'manage_options';

	/**
	 * Sentinel posted back by the settings form when the password field was
	 * left untouched, so a saved password is never wiped by a blank field.
	 */
	const UNCHANGED = '__helo_unchanged__';

	/** @var array|null Per-request cache of the resolved settings. */
	private static $cache = null;

	public static function defaults() {
		return array(
			'host'       => '',
			'port'       => 587,
			'encryption' => 'tls',
			'auth'       => 1,
			'username'   => '',
			'password'   => '',
			'from_email' => '',
			'from_name'  => '',
			'force_from' => 1,
			'logging'    => 1,
			'log_days'   => 30,
			'debug'      => 0,

			// Copy of each sent message, filed over IMAP.
			'copy_to_sent'    => 0,
			'imap_host'       => '',
			'imap_port'       => 993,
			'imap_encryption' => 'ssl',
			'imap_folder'     => '',
		);
	}

	/**
	 * All settings, with the password decrypted (or taken from the constant).
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );

		$settings['password'] = self::password_is_locked()
			? HELO_SMTP_PASSWORD
			: self::decrypt( $settings['password'] );

		self::$cache = $settings;

		return self::$cache;
	}

	/**
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( $key ) {
		$settings = self::all();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/** True when the password comes from wp-config.php rather than the database. */
	public static function password_is_locked() {
		return defined( 'HELO_SMTP_PASSWORD' );
	}

	/** True when a host has been configured; otherwise WordPress keeps using PHP mail(). */
	public static function is_configured() {
		return '' !== self::get( 'host' );
	}

	/**
	 * Sanitise a raw $_POST payload and persist it.
	 *
	 * @param array $input Raw, unslashed form input.
	 */
	public static function save( array $input ) {
		$stored = (array) get_option( self::OPTION, array() );

		$clean = array(
			'host'       => sanitize_text_field( isset( $input['host'] ) ? $input['host'] : '' ),
			'port'       => max( 1, min( 65535, (int) ( isset( $input['port'] ) ? $input['port'] : 587 ) ) ),
			'encryption' => in_array( isset( $input['encryption'] ) ? $input['encryption'] : '', array( 'tls', 'ssl', 'none' ), true ) ? $input['encryption'] : 'tls',
			'auth'       => empty( $input['auth'] ) ? 0 : 1,
			'username'   => sanitize_text_field( isset( $input['username'] ) ? $input['username'] : '' ),
			'from_email' => sanitize_email( isset( $input['from_email'] ) ? $input['from_email'] : '' ),
			'from_name'  => sanitize_text_field( isset( $input['from_name'] ) ? $input['from_name'] : '' ),
			'force_from' => empty( $input['force_from'] ) ? 0 : 1,
			'logging'    => empty( $input['logging'] ) ? 0 : 1,
			'log_days'   => max( 0, (int) ( isset( $input['log_days'] ) ? $input['log_days'] : 30 ) ),
			'debug'      => empty( $input['debug'] ) ? 0 : 1,

			'copy_to_sent'    => empty( $input['copy_to_sent'] ) ? 0 : 1,
			'imap_host'       => sanitize_text_field( isset( $input['imap_host'] ) ? $input['imap_host'] : '' ),
			'imap_port'       => max( 1, min( 65535, (int) ( isset( $input['imap_port'] ) ? $input['imap_port'] : 993 ) ) ),
			'imap_encryption' => in_array( isset( $input['imap_encryption'] ) ? $input['imap_encryption'] : '', array( 'ssl', 'tls' ), true ) ? $input['imap_encryption'] : 'ssl',
			'imap_folder'     => sanitize_text_field( isset( $input['imap_folder'] ) ? $input['imap_folder'] : '' ),
		);

		$submitted = isset( $input['password'] ) ? (string) $input['password'] : '';

		if ( self::UNCHANGED === $submitted ) {
			$clean['password'] = isset( $stored['password'] ) ? $stored['password'] : '';
		} else {
			$clean['password'] = self::encrypt( $submitted );
		}

		update_option( self::OPTION, $clean );
		self::$cache = null;

		// A changed host or folder invalidates the discovered Sent folder.
		delete_transient( Helo_Imap::FOLDER_CACHE );
	}

	/* ------------------------------------------------------------------
	 * Password encryption at rest.
	 *
	 * The key is derived from the site's auth salt, so a stolen database
	 * dump alone does not hand over the mailbox password. Rotating the
	 * salts invalidates the stored password and it has to be re-entered.
	 * ------------------------------------------------------------------ */

	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	private static function encrypt( $value ) {
		if ( '' === $value || ! function_exists( 'openssl_encrypt' ) ) {
			return $value;
		}

		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $value, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );

		return false === $cipher ? $value : base64_encode( $iv . $cipher );
	}

	private static function decrypt( $value ) {
		if ( '' === $value || ! function_exists( 'openssl_decrypt' ) ) {
			return $value;
		}

		$raw = base64_decode( $value, true );
		if ( false === $raw || strlen( $raw ) <= 16 ) {
			return $value; // Not encrypted by us (e.g. upgraded from a plain value).
		}

		$plain = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );

		return false === $plain ? $value : $plain;
	}
}
