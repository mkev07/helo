<?php
/**
 * Security analytics for the Turnstile gate: how often forms are verified
 * versus blocked, per form, and why.
 *
 * Two tiers, matching upstream's split:
 *  - Counters (privacy-safe): total / verified / blocked / retries, a per-form
 *    breakdown, and block-reason counts. No IPs, no page URLs.
 *  - Optional debug log (sensitive; off by default): date, success, error, IP
 *    and page URL for each verification, last 50.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Analytics {

	const OPTION        = 'helo_turnstile_analytics';
	const LOG_OPTION    = 'helo_turnstile_debug_log';
	const MAX_FORMS     = 40;
	const MAX_ERRORS    = 20;
	const MAX_LOG_ITEMS = 50;

	public static function init() {
		add_action( 'helo_turnstile_after_check', array( __CLASS__, 'record' ), 10, 3 );
		add_action( 'admin_post_helo_turnstile_reset_analytics', array( __CLASS__, 'handle_reset' ) );
		add_action( 'admin_post_helo_turnstile_reset_log', array( __CLASS__, 'handle_reset_log' ) );
	}

	/* ---------------------------------------------------------------- record */

	/**
	 * Hook fired from Helo_Turnstile::check() after a siteverify round trip.
	 *
	 * @param mixed $response     Decoded Cloudflare JSON response (or null).
	 * @param array $results      {success: bool, error_code: string}
	 * @param string $form_action Form label.
	 */
	public static function record( $response, $results, $form_action = '' ) {
		self::record_counters( $results, $form_action );

		if ( Helo_Settings::get( 'turnstile_debug_log' ) ) {
			self::record_log( $results );
		}
	}

	private static function record_counters( $results, $form_action ) {
		if ( ! Helo_Settings::get( 'turnstile_analytics' ) ) {
			return;
		}

		$analytics = self::read();

		$now        = current_time( 'mysql' );
		$success    = ! empty( $results['success'] );
		$error_code = self::normalize_error( isset( $results['error_code'] ) ? $results['error_code'] : '' );
		$is_retry   = ( 'timeout-or-duplicate' === $error_code );

		$analytics['updated'] = $now;
		$analytics['total']   = absint( $analytics['total'] ) + 1;
		$analytics['verified'] = absint( $analytics['verified'] ) + ( $success ? 1 : 0 );
		$analytics['blocked']  = absint( $analytics['blocked'] ) + ( $success ? 0 : 1 );
		$analytics['retries']  = absint( $analytics['retries'] ) + ( $is_retry ? 1 : 0 );

		$form_key = sanitize_key( self::normalize_label( $form_action ) );
		if ( '' === $form_key ) {
			$form_key = 'unknown';
		}
		$form = wp_parse_args(
			isset( $analytics['forms'][ $form_key ] ) && is_array( $analytics['forms'][ $form_key ] ) ? $analytics['forms'][ $form_key ] : array(),
			array( 'label' => '', 'total' => 0, 'verified' => 0, 'blocked' => 0, 'retries' => 0, 'last_checked' => '' )
		);
		$form['label']        = self::normalize_label( $form_action );
		$form['last_checked'] = $now;
		$form['total']        = absint( $form['total'] ) + 1;
		$form['verified']     = absint( $form['verified'] ) + ( $success ? 1 : 0 );
		$form['blocked']      = absint( $form['blocked'] ) + ( $success ? 0 : 1 );
		$form['retries']      = absint( $form['retries'] ) + ( $is_retry ? 1 : 0 );
		$analytics['forms'][ $form_key ] = $form;

		if ( ! $success && $error_code ) {
			$analytics['errors'][ $error_code ] = ( isset( $analytics['errors'][ $error_code ] ) ? absint( $analytics['errors'][ $error_code ] ) : 0 ) + 1;
		}

		self::trim( $analytics );
		update_option( self::OPTION, $analytics, false );
	}

	private static function record_log( $results ) {
		$log   = get_option( self::LOG_OPTION );
		$log   = is_array( $log ) ? $log : array();
		$error = isset( $results['error_code'] ) ? $results['error_code'] : '';

		$log[] = array(
			'date'    => current_time( 'mysql' ),
			'success' => ! empty( $results['success'] ),
			'error'   => $error,
			'ip'      => self::clip( Helo_Turnstile::remote_ip(), 100 ),
			'page'    => self::clip( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', 250 ),
		);

		if ( count( $log ) > self::MAX_LOG_ITEMS ) {
			$log = array_slice( $log, -self::MAX_LOG_ITEMS );
		}
		update_option( self::LOG_OPTION, $log, false );
	}

	/* ----------------------------------------------------------------- help */

	private static function read() {
		$analytics = get_option( self::OPTION );
		$analytics = is_array( $analytics ) ? $analytics : array();

		$analytics = wp_parse_args(
			$analytics,
			array( 'started' => '', 'updated' => '', 'total' => 0, 'verified' => 0, 'blocked' => 0, 'retries' => 0, 'forms' => array(), 'errors' => array() )
		);
		if ( ! is_array( $analytics['forms'] ) ) {
			$analytics['forms'] = array();
		}
		if ( ! is_array( $analytics['errors'] ) ) {
			$analytics['errors'] = array();
		}

		return $analytics;
	}

	/** Normalised snapshot for the admin tab (sorts, clamps, sanitises). */
	public static function snapshot() {
		$analytics         = self::read();
		$analytics['total'] = absint( $analytics['total'] );
		$analytics['verified'] = absint( $analytics['verified'] );
		$analytics['blocked']  = absint( $analytics['blocked'] );
		$analytics['retries']  = absint( $analytics['retries'] );

		foreach ( $analytics['forms'] as $key => $form ) {
			if ( ! is_array( $form ) ) {
				unset( $analytics['forms'][ $key ] );
				continue;
			}
			$analytics['forms'][ $key ] = wp_parse_args(
				$form,
				array( 'label' => '', 'total' => 0, 'verified' => 0, 'blocked' => 0, 'retries' => 0, 'last_checked' => '' )
			);
		}
		uasort( $analytics['forms'], array( __CLASS__, 'sort_forms' ) );
		if ( count( $analytics['forms'] ) > self::MAX_FORMS ) {
			$analytics['forms'] = array_slice( $analytics['forms'], 0, self::MAX_FORMS, true );
		}

		foreach ( $analytics['errors'] as $key => $count ) {
			$code = sanitize_key( (string) $key );
			if ( '' === $code ) {
				unset( $analytics['errors'][ $key ] );
			}
		}
		arsort( $analytics['errors'] );
		if ( count( $analytics['errors'] ) > self::MAX_ERRORS ) {
			$analytics['errors'] = array_slice( $analytics['errors'], 0, self::MAX_ERRORS, true );
		}

		return $analytics;
	}

	/** The raw debug log, newest first, ready for the admin view. */
	public static function log() {
		$log = get_option( self::LOG_OPTION );
		$log = is_array( $log ) ? $log : array();
		uasort( $log, array( __CLASS__, 'sort_log_newest' ) );

		return $log;
	}

	public static function clear() {
		delete_option( self::OPTION );
	}

	public static function clear_log() {
		delete_option( self::LOG_OPTION );
	}

	private static function trim( $analytics ) {
		foreach ( $analytics['errors'] as $key => $count ) {
			$code = sanitize_key( (string) $key );
			if ( '' === $code ) {
				unset( $analytics['errors'][ $key ] );
			}
		}
		arsort( $analytics['errors'] );
		if ( count( $analytics['errors'] ) > self::MAX_ERRORS ) {
			$analytics['errors'] = array_slice( $analytics['errors'], 0, self::MAX_ERRORS, true );
		}

		uasort( $analytics['forms'], array( __CLASS__, 'sort_forms' ) );
		if ( count( $analytics['forms'] ) > self::MAX_FORMS ) {
			$analytics['forms'] = array_slice( $analytics['forms'], 0, self::MAX_FORMS, true );
		}
	}

	public static function sort_forms( $a, $b ) {
		$a_total = isset( $a['total'] ) ? absint( $a['total'] ) : 0;
		$b_total = isset( $b['total'] ) ? absint( $b['total'] ) : 0;
		if ( $a_total === $b_total ) {
			return 0;
		}

		return ( $a_total < $b_total ) ? 1 : -1;
	}

	public static function sort_log_newest( $a, $b ) {
		$a_time = isset( $a['date'] ) ? (string) $a['date'] : '';
		$b_time = isset( $b['date'] ) ? (string) $b['date'] : '';
		if ( $a_time === $b_time ) {
			return 0;
		}

		return ( $a_time < $b_time ) ? 1 : -1;
	}

	/* ---------------------------------------------------------------- reset */

	private static function guard( $action ) {
		check_admin_referer( $action );
		if ( ! current_user_can( Helo_Settings::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'helo-smtp' ) );
		}
	}

	public static function handle_reset() {
		self::guard( 'helo_turnstile_reset_analytics' );
		self::clear();
		wp_safe_redirect( add_query_arg( 'tab', 'security', admin_url( 'admin.php?page=helo' ) ) );
		exit;
	}

	public static function handle_reset_log() {
		self::guard( 'helo_turnstile_reset_log' );
		self::clear_log();
		wp_safe_redirect( add_query_arg( 'tab', 'security', admin_url( 'admin.php?page=helo' ) ) );
		exit;
	}

	/* -------------------------------------------------------------- formatng */

	public static function percent( $part, $total ) {
		$total = absint( $total );
		if ( ! $total ) {
			return '0%';
		}

		return number_format_i18n( ( absint( $part ) / $total ) * 100, 1 ) . '%';
	}

	public static function percent_value( $part, $total ) {
		$total = absint( $total );
		if ( ! $total ) {
			return 0;
		}

		return min( 100, max( 0, ( absint( $part ) / $total ) * 100 ) );
	}

	private static function normalize_label( $label ) {
		return self::clip( sanitize_text_field( (string) $label ), 80 );
	}

	private static function normalize_error( $error ) {
		return substr( sanitize_key( (string) $error ), 0, 80 );
	}

	private static function clip( $value, $max ) {
		$value = sanitize_text_field( (string) $value );
		if ( strlen( $value ) > $max ) {
			$value = substr( $value, 0, $max );
		}

		return $value;
	}
}