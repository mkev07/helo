<?php
/**
 * Hands PHPMailer the SMTP configuration and owns the sender address.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Mailer {

	/**
	 * Content type of the message currently being sent, read off PHPMailer
	 * just before delivery so the log knows how to render the body.
	 *
	 * @var string
	 */
	private static $content_type = 'text/plain';

	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure' ) );
		add_filter( 'wp_mail_from', array( __CLASS__, 'from_email' ), 99 );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ), 99 );
	}

	public static function content_type() {
		return self::$content_type;
	}

	/**
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer instance, by reference.
	 */
	public static function configure( $phpmailer ) {
		self::$content_type = $phpmailer->ContentType ? $phpmailer->ContentType : 'text/plain';

		if ( ! Helo_Settings::is_configured() ) {
			return; // No host set: leave WordPress on the server's own mailer.
		}

		$settings = Helo_Settings::all();

		$phpmailer->isSMTP();
		$phpmailer->Host        = $settings['host'];
		$phpmailer->Port        = (int) $settings['port'];
		$phpmailer->SMTPAuth    = (bool) $settings['auth'];
		$phpmailer->SMTPSecure  = 'none' === $settings['encryption'] ? '' : $settings['encryption'];
		$phpmailer->SMTPAutoTLS = 'none' !== $settings['encryption'];

		if ( $settings['auth'] ) {
			$phpmailer->Username = $settings['username'];
			$phpmailer->Password = $settings['password'];
		}

		if ( $settings['force_from'] && $settings['from_email'] ) {
			$phpmailer->setFrom( $settings['from_email'], $settings['from_name'], false );
		}

		if ( $settings['debug'] ) {
			$phpmailer->SMTPDebug   = 2;
			$phpmailer->Debugoutput = function ( $message ) {
				error_log( '[Helo] ' . trim( $message ) );
			};
		}
	}

	/**
	 * @param string $email Address chosen by WordPress or another plugin.
	 * @return string
	 */
	public static function from_email( $email ) {
		$configured = Helo_Settings::get( 'from_email' );

		return ( Helo_Settings::get( 'force_from' ) && $configured ) ? $configured : $email;
	}

	/**
	 * @param string $name Name chosen by WordPress or another plugin.
	 * @return string
	 */
	public static function from_name( $name ) {
		$configured = Helo_Settings::get( 'from_name' );

		return ( Helo_Settings::get( 'force_from' ) && $configured ) ? $configured : $name;
	}

	/**
	 * Send a test message and report back whatever PHPMailer complained about.
	 *
	 * @param string $to   Recipient.
	 * @param bool   $html Send as HTML.
	 * @return array{sent: bool, errors: string[]}
	 */
	public static function send_test( $to, $html = true ) {
		$errors = array();

		$collect = function ( $error ) use ( &$errors ) {
			$errors[] = $error->get_error_message();
		};
		add_action( 'wp_mail_failed', $collect );

		$site = get_bloginfo( 'name' );
		$when = current_time( 'mysql' );

		$body = $html
			? '<p>' . esc_html__( 'This is a test email.', 'helo-smtp' ) . '</p>'
				. '<p><strong>' . esc_html( $site ) . '</strong><br>' . esc_html( $when ) . '</p>'
			: sprintf( "This is a test email.\n\n%s\n%s\n", $site, $when );

		$sent = wp_mail(
			$to,
			sprintf(
				/* translators: %s: site name. */
				__( 'SMTP test from %s', 'helo-smtp' ),
				$site
			),
			$body,
			$html ? array( 'Content-Type: text/html; charset=UTF-8' ) : array()
		);

		remove_action( 'wp_mail_failed', $collect );

		return array(
			'sent'   => (bool) $sent,
			'errors' => $errors,
		);
	}

	/**
	 * Re-send a logged email.
	 *
	 * @param object $log Row from the log table.
	 * @return bool
	 */
	public static function resend( $log ) {
		$headers = array_filter( array_map( 'trim', explode( "\n", $log->headers ) ) );

		if ( false !== stripos( $log->content_type, 'html' ) ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		// Attachments were logged as paths; only re-attach the files still on disk.
		$attachments = array_filter(
			array_filter( array_map( 'trim', explode( "\n", $log->attachments ) ) ),
			'is_readable'
		);

		$recipients = array_filter( array_map( 'trim', preg_split( '/[,\n]/', $log->to_email ) ) );

		return wp_mail( $recipients, $log->subject, $log->body, $headers, $attachments );
	}
}
