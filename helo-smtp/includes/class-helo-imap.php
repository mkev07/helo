<?php
/**
 * Files a copy of every sent message into the mailbox's Sent folder.
 *
 * SMTP has no concept of folders, so nothing about sending puts a message in
 * Sent. Desktop mail clients do it themselves: they send over SMTP, then open
 * a separate IMAP connection and APPEND a copy. This does the same.
 *
 * Written against raw sockets rather than PHP's imap extension, which is
 * frequently missing and was unbundled from core in PHP 8.4.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Imap {

	const TIMEOUT        = 10;
	const FOLDER_CACHE   = 'helo_sent_folder';
	const FOLDER_CACHE_TTL = DAY_IN_SECONDS;

	/** @var resource|null */
	private $stream = null;

	/** @var int Sequence for command tags. */
	private $sequence = 0;

	/** @var string Last error, safe to show an administrator. */
	private $error = '';

	public static function init() {
		// Runs after the logger, both on the same hook.
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'save_sent_copy' ), 20 );
		add_action( 'admin_post_helo_test_imap', array( __CLASS__, 'handle_test' ) );
	}

	public function error() {
		return $this->error;
	}

	/* ------------------------------------------------------------- settings */

	/**
	 * IMAP connection details, falling back to the SMTP ones.
	 *
	 * On a normal mailbox the IMAP and SMTP logins are the same account, so
	 * there is nothing extra to enter and no second password to store.
	 *
	 * @return array
	 */
	public static function config() {
		$settings = Helo_Settings::all();

		return array(
			'host'       => '' !== $settings['imap_host'] ? $settings['imap_host'] : $settings['host'],
			'port'       => (int) $settings['imap_port'],
			'encryption' => $settings['imap_encryption'],
			'username'   => $settings['username'],
			'password'   => $settings['password'],
			'folder'     => $settings['imap_folder'],
		);
	}

	public static function is_enabled() {
		$config = self::config();

		return (bool) Helo_Settings::get( 'copy_to_sent' ) && '' !== $config['host'] && '' !== $config['username'];
	}

	/* ---------------------------------------------------------------- filing */

	/**
	 * Append the message WordPress just sent to the Sent folder.
	 *
	 * Deliberately silent: a broken IMAP login must never look like a failed
	 * send, because the email did go out. Problems go to the PHP error log,
	 * and the Test button in settings is the place to diagnose them.
	 */
	public static function save_sent_copy() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$raw = self::sent_message();

		if ( '' === $raw ) {
			return;
		}

		$config = self::config();
		$imap   = new self();

		if ( ! $imap->connect( $config['host'], $config['port'], $config['encryption'], $config['username'], $config['password'] ) ) {
			error_log( '[Helo] Sent copy skipped: ' . $imap->error() );

			return;
		}

		$folder = '' !== $config['folder'] ? $config['folder'] : $imap->resolve_sent_folder();

		if ( '' === $folder ) {
			error_log( '[Helo] Sent copy skipped: no Sent folder found. Set one explicitly in settings.' );
			$imap->disconnect();

			return;
		}

		if ( ! $imap->append( $folder, $raw ) ) {
			error_log( '[Helo] Sent copy failed: ' . $imap->error() );
		}

		$imap->disconnect();
	}

	/**
	 * The exact bytes PHPMailer put on the wire.
	 *
	 * WordPress keeps one PHPMailer instance in a global and reuses it, so
	 * after a send it still holds the assembled message.
	 *
	 * @return string
	 */
	private static function sent_message() {
		global $phpmailer;

		if ( ! is_object( $phpmailer ) || ! method_exists( $phpmailer, 'getSentMIMEMessage' ) ) {
			return '';
		}

		try {
			return (string) $phpmailer->getSentMIMEMessage();
		} catch ( Exception $e ) {
			return '';
		} catch ( Error $e ) {
			return '';
		}
	}

	/* --------------------------------------------------------------- session */

	/**
	 * @param string $host       Server hostname.
	 * @param int    $port       Port.
	 * @param string $encryption 'ssl' for implicit TLS, 'tls' for STARTTLS.
	 * @param string $username   Mailbox login.
	 * @param string $password   Mailbox password.
	 * @return bool
	 */
	public function connect( $host, $port, $encryption, $username, $password ) {
		$transport = ( 'ssl' === $encryption ) ? 'ssl://' : 'tcp://';

		$this->stream = @stream_socket_client(
			$transport . $host . ':' . (int) $port,
			$errno,
			$errstr,
			self::TIMEOUT,
			STREAM_CLIENT_CONNECT
		);

		if ( ! $this->stream ) {
			$this->error = sprintf( 'could not connect to %s:%d — %s', $host, $port, $errstr ? $errstr : 'no response' );

			return false;
		}

		stream_set_timeout( $this->stream, self::TIMEOUT );

		$greeting = fgets( $this->stream );

		if ( ! $greeting || 0 !== strpos( $greeting, '* OK' ) ) {
			$this->error = 'server did not greet as IMAP: ' . trim( (string) $greeting );

			return $this->fail();
		}

		if ( 'tls' === $encryption && ! $this->start_tls() ) {
			return $this->fail();
		}

		// Never include the command itself in an error — it carries the password.
		$result = $this->command( sprintf( 'LOGIN %s %s', self::quote( $username ), self::quote( $password ) ) );

		if ( empty( $result['ok'] ) ) {
			$this->error = 'login rejected: ' . $result['message'];

			return $this->fail();
		}

		return true;
	}

	private function start_tls() {
		$result = $this->command( 'STARTTLS' );

		if ( empty( $result['ok'] ) ) {
			$this->error = 'STARTTLS refused: ' . $result['message'];

			return false;
		}

		if ( ! @stream_socket_enable_crypto( $this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT ) ) {
			$this->error = 'STARTTLS negotiation failed';

			return false;
		}

		return true;
	}

	public function disconnect() {
		if ( $this->stream ) {
			$this->write( "HBYE LOGOUT\r\n" );
			@fclose( $this->stream );
			$this->stream = null;
		}
	}

	private function fail() {
		$this->disconnect();

		return false;
	}

	/* --------------------------------------------------------------- folders */

	/**
	 * Find the Sent folder, preferring the server's own SPECIAL-USE flag.
	 *
	 * Naming is not consistent across hosts: DirectAdmin boxes often expose
	 * "INBOX.Sent" with a dot separator, others use a plain "Sent". Asking the
	 * server beats guessing.
	 *
	 * @return string Folder name, empty when nothing plausible was found.
	 */
	public function resolve_sent_folder() {
		$cached = get_transient( self::FOLDER_CACHE );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$result = $this->command( 'LIST "" "*"' );

		if ( empty( $result['ok'] ) ) {
			return '';
		}

		$candidates = array();
		$found      = '';

		foreach ( $result['lines'] as $line ) {
			if ( ! preg_match( '/^\* LIST \(([^)]*)\) (?:"[^"]*"|NIL) (.+)$/i', $line, $matches ) ) {
				continue;
			}

			$flags = strtolower( $matches[1] );
			$name  = trim( $matches[2] );

			if ( '"' === substr( $name, 0, 1 ) ) {
				$name = stripcslashes( substr( $name, 1, -1 ) );
			}

			if ( false !== strpos( $flags, '\\sent' ) ) {
				$found = $name;
				break;
			}

			$candidates[ strtolower( $name ) ] = $name;
		}

		if ( '' === $found ) {
			$guesses = array( 'inbox.sent', 'sent', 'inbox.sent items', 'sent items', 'inbox.sent messages', 'sent messages' );

			foreach ( $guesses as $guess ) {
				if ( isset( $candidates[ $guess ] ) ) {
					$found = $candidates[ $guess ];
					break;
				}
			}
		}

		if ( '' !== $found ) {
			set_transient( self::FOLDER_CACHE, $found, self::FOLDER_CACHE_TTL );
		}

		return $found;
	}

	/* ---------------------------------------------------------------- append */

	/**
	 * @param string $folder  Target mailbox.
	 * @param string $message Raw RFC 5322 message.
	 * @return bool
	 */
	public function append( $folder, $message ) {
		// IMAP literals are counted in octets and the protocol is CRLF.
		$message = preg_replace( '/\r\n|\r|\n/', "\r\n", $message );
		$date    = gmdate( 'd-M-Y H:i:s +0000' );

		$tag = $this->next_tag();

		$sent = $this->write(
			sprintf(
				"%s APPEND %s (\\Seen) \"%s\" {%d}\r\n",
				$tag,
				self::quote( $folder ),
				$date,
				strlen( $message )
			)
		);

		if ( ! $sent ) {
			$this->error = 'connection lost before the append';

			return false;
		}

		$result = $this->read( $tag );

		if ( empty( $result['continue'] ) ) {
			$this->error = sprintf( 'server refused an append to "%s": %s', $folder, $result['message'] );

			return false;
		}

		if ( ! $this->write( $message . "\r\n" ) ) {
			$this->error = 'connection lost while sending the message';

			return false;
		}

		$result = $this->read( $tag );

		if ( empty( $result['ok'] ) ) {
			$this->error = 'append failed: ' . $result['message'];

			return false;
		}

		return true;
	}

	/* -------------------------------------------------------------- protocol */

	private function next_tag() {
		return sprintf( 'H%03d', ++$this->sequence );
	}

	/**
	 * Write to the socket without letting a dropped connection raise a notice.
	 *
	 * @param string $data Bytes to send.
	 * @return bool True when everything was written.
	 */
	private function write( $data ) {
		if ( ! $this->stream ) {
			return false;
		}

		$sent = @fwrite( $this->stream, $data );

		return false !== $sent && strlen( $data ) === $sent;
	}

	/**
	 * @param string $command Command without its tag.
	 * @return array{ok: bool, message: string, lines: string[], continue?: bool}
	 */
	private function command( $command ) {
		$tag = $this->next_tag();

		if ( ! $this->write( $tag . ' ' . $command . "\r\n" ) ) {
			return array(
				'ok'      => false,
				'message' => 'connection lost while sending',
				'lines'   => array(),
			);
		}

		return $this->read( $tag );
	}

	/**
	 * Read until the tagged completion, a continuation, or the socket dies.
	 *
	 * @param string $tag Tag to wait for.
	 * @return array{ok: bool, message: string, lines: string[], continue?: bool}
	 */
	private function read( $tag ) {
		$lines = array();

		while ( true ) {
			$line = fgets( $this->stream );

			if ( false === $line ) {
				$meta = stream_get_meta_data( $this->stream );

				return array(
					'ok'      => false,
					'message' => ! empty( $meta['timed_out'] ) ? 'timed out waiting for the server' : 'connection closed',
					'lines'   => $lines,
				);
			}

			$line = rtrim( $line, "\r\n" );

			if ( 0 === strpos( $line, $tag . ' ' ) ) {
				$response = substr( $line, strlen( $tag ) + 1 );
				$status   = strtoupper( (string) strtok( $response, ' ' ) );

				return array(
					'ok'      => 'OK' === $status,
					'message' => $response,
					'lines'   => $lines,
				);
			}

			if ( '+' === substr( $line, 0, 1 ) ) {
				return array(
					'ok'       => true,
					'continue' => true,
					'message'  => $line,
					'lines'    => $lines,
				);
			}

			$lines[] = $line;
		}
	}

	/**
	 * @param string $value Value to send as an IMAP quoted string.
	 * @return string
	 */
	private static function quote( $value ) {
		return '"' . addcslashes( (string) $value, '"\\' ) . '"';
	}

	/* ------------------------------------------------------------------ test */

	public static function test_url() {
		return wp_nonce_url(
			add_query_arg( array( 'action' => 'helo_test_imap' ), admin_url( 'admin-post.php' ) ),
			'helo_test_imap'
		);
	}

	public static function handle_test() {
		check_admin_referer( 'helo_test_imap' );

		if ( ! current_user_can( Helo_Settings::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'helo-smtp' ) );
		}

		delete_transient( self::FOLDER_CACHE );

		$config = self::config();
		$imap   = new self();

		if ( '' === $config['host'] || '' === $config['username'] ) {
			$message = __( 'Set a mail server host and username first.', 'helo-smtp' );
			$type    = 'error';
		} elseif ( ! $imap->connect( $config['host'], $config['port'], $config['encryption'], $config['username'], $config['password'] ) ) {
			/* translators: %s: error detail from the IMAP server. */
			$message = sprintf( __( 'IMAP connection failed — %s', 'helo-smtp' ), $imap->error() );
			$type    = 'error';
		} else {
			$folder = '' !== $config['folder'] ? $config['folder'] : $imap->resolve_sent_folder();

			if ( '' === $folder ) {
				$message = __( 'Connected, but no Sent folder was found. Name one explicitly below.', 'helo-smtp' );
				$type    = 'error';
			} else {
				/* translators: %s: IMAP folder name. */
				$message = sprintf( __( 'Connected. Copies will be filed in “%s”. Send a test email to confirm end to end.', 'helo-smtp' ), $folder );
				$type    = 'success';
			}

			$imap->disconnect();
		}

		set_transient(
			Helo_Admin::NOTICE_TRANSIENT,
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( Helo_Admin::url() );
		exit;
	}
}
