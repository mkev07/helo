<?php
/**
 * Dev-only harness: renders the real admin views against stubbed WordPress
 * functions so the styling can be eyeballed without an install.
 * Not part of the plugin. Run: php preview/render.php
 */

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

// Read the real header so the preview never shows a stale version.
preg_match( '/^ \* Version:\s+(\S+)/m', file_get_contents( dirname( __DIR__ ) . '/helo-smtp/helo-smtp.php' ), $v );
define( 'HELO_VERSION', $v[1] );
define( 'HELO_FILE', dirname( __DIR__ ) . '/helo-smtp/helo-smtp.php' );
define( 'HELO_PATH', dirname( __DIR__ ) . '/helo-smtp/' );
define( 'HELO_URL', '../helo-smtp/' );

$GLOBALS['options'] = array(
	'helo_settings' => array(
		'host'       => 'mail.example.com',
		'port'       => 587,
		'encryption' => 'tls',
		'auth'       => 1,
		'username'   => 'postmaster@example.com',
		'password'   => 'stored',
		'from_email' => 'hello@example.com',
		'from_name'  => 'Northwind',
		'force_from' => 1,
		'logging'    => 1,
		'log_days'   => 30,
		'debug'      => 0,
		'copy_to_sent'    => 1,
		'imap_port'       => 993,
		'imap_encryption' => 'ssl',
	),
);

// --- WordPress stubs ------------------------------------------------------

function add_action() {}
function add_filter() {}
function add_menu_page() {}
function plugin_basename() { return 'helo-smtp/helo-smtp.php'; }
function current_user_can() { return true; }
function wp_die( $m ) { exit( $m ); }
function wp_salt( $s = 'auth' ) { return 'preview-salt'; }
function get_option( $n, $d = false ) { return $GLOBALS['options'][ $n ] ?? $d; }
function update_option( $n, $v, $a = null ) { $GLOBALS['options'][ $n ] = $v; return true; }
function get_transient( $n ) { return $GLOBALS['transient'] ?? false; }
function set_transient( $n, $v, $t = 0 ) { $GLOBALS['transient'] = $v; }
function delete_transient( $n ) { unset( $GLOBALS['transient'] ); }
function wp_next_scheduled( $h ) { return false; }
function get_site_transient( $n ) { return false; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_email( $v ) { return (string) filter_var( $v, FILTER_VALIDATE_EMAIL ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $v ) { return $v; }
function esc_js( $v ) { return addslashes( (string) $v ); }
function esc_textarea( $v ) { return esc_html( $v ); }
function wp_kses( $v ) { return $v; }
function wp_kses_post( $v ) { return $v; }
function __( $t, $d = null ) { return $t; }
function _n( $s, $p, $n, $d = null ) { return 1 === $n ? $s : $p; }
function esc_html__( $t, $d = null ) { return esc_html( $t ); }
function esc_attr__( $t, $d = null ) { return esc_attr( $t ); }
function esc_html_e( $t, $d = null ) { echo esc_html( $t ); }
function esc_attr_e( $t, $d = null ) { echo esc_attr( $t ); }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function human_time_diff( $from, $to ) { return round( ( $to - $from ) / 60 ) . ' mins'; }
function current_time( $type ) { return 'timestamp' === $type ? time() : gmdate( 'Y-m-d H:i:s' ); }
function mysql2date( $format, $date ) { return 'U' === $format ? strtotime( $date ) : gmdate( $format, strtotime( $date ) ); }
function admin_url( $p = '' ) { return '#' . $p; }
function add_query_arg( ...$a ) {
	$args = is_array( $a[0] ) ? $a[0] : array( $a[0] => $a[1] );
	$url  = is_array( $a[0] ) ? ( $a[1] ?? '' ) : ( $a[2] ?? '' );
	return $url . '?' . http_build_query( $args );
}
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="stub">'; }
function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=stub'; }
function checked( $a, $b = true, $e = true ) { $r = $a == $b ? ' checked' : ''; if ( $e ) { echo $r; } return $r; }
function selected( $a, $b = true, $e = true ) { $r = $a == $b ? ' selected' : ''; if ( $e ) { echo $r; } return $r; }
function submit_button( $text = 'Save Changes', $type = 'primary', $name = 'submit', $wrap = true ) {
	$html = '<button type="submit" class="button button-' . esc_attr( $type ) . '">' . esc_html( $text ) . '</button>';
	echo $wrap ? "<p class=\"submit\">{$html}</p>" : $html;
}
function wp_get_current_user() { return (object) array( 'user_email' => 'jane@example.com' ); }
function paginate_links( $args ) {
	$out = '<a class="page-numbers" href="#">&larr;</a>';
	for ( $i = 1; $i <= min( 4, $args['total'] ); $i++ ) {
		$out .= $i === $args['current']
			? '<span class="page-numbers current">' . $i . '</span>'
			: '<a class="page-numbers" href="#">' . $i . '</a>';
	}

	return $out . '<span class="page-numbers dots">&hellip;</span><a class="page-numbers" href="#">&rarr;</a>';
}

require_once HELO_PATH . 'includes/class-helo-settings.php';
require_once HELO_PATH . 'includes/class-helo-imap.php';

// --- Fixture data ---------------------------------------------------------

class Helo_Logger {
	public static $empty = false;
	public static function rows() {
		if ( self::$empty ) { return array(); }
		$fixtures = array(
			array( '-4 minutes', 'jane@example.com', 'Your order #1482 is confirmed', 'sent', '' ),
			array( '-38 minutes', 'accounts@clientsite.com', 'New enquiry from the contact form', 'sent', '' ),
			array( '-2 hours', 'noreply@bounce.example', 'Password reset requested', 'failed', 'SMTP Error: Could not authenticate. 535 5.7.8 Username and Password not accepted.' ),
			array( '-5 hours', 'team@example.com, billing@example.com', 'Weekly summary — 12 new leads', 'sent', '' ),
			array( '-1 day', 'jane@example.com', '', 'sent', '' ),
		);

		$rows = array();
		foreach ( $fixtures as $i => $f ) {
			$rows[] = (object) array(
				'id'         => 120 - $i,
				'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( $f[0] ) ),
				'to_email'   => $f[1],
				'subject'    => $f[2],
				'status'     => $f[3],
				'error'      => $f[4],
			);
		}

		return $rows;
	}

	public static function count() { return self::$empty ? 0 : 137; }
	public static function query( $a = array() ) { return array( 'rows' => self::rows(), 'total' => self::$empty ? 0 : 137 ); }
	public static function stats( $d = 7 ) {
		if ( self::$empty ) { return array( 'total' => 0, 'sent' => 0, 'failed' => 0, 'last' => null ); }
		$rows = self::rows();

		return array( 'total' => 96, 'sent' => 94, 'failed' => 2, 'last' => $rows[0] );
	}
	public static function get( $id ) {
		$row = null;
		foreach ( self::rows() as $candidate ) {
			if ( (int) $candidate->id === (int) $id ) {
				$row = $candidate;
			}
		}
		if ( ! $row ) {
			return null;
		}

		if ( 'failed' === $row->status ) {
			$row->body         = "Someone requested a password reset for your account.\n\nIf this was you, follow the link below. If not, you can ignore this email.\n\nhttps://example.com/wp-login.php?action=rp&key=…\n";
			$row->headers      = '';
			$row->attachments  = '';
			$row->content_type = 'text/plain';

			return $row;
		}

		$row->body = "<html><body style=\"margin:0;font-family:Helvetica,Arial,sans-serif;background:#f4f4f5;padding:32px\">\n"
			. "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\"><tr><td align=\"center\">\n"
			. "<table width=\"560\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#fff;border-radius:12px;overflow:hidden\">\n"
			. "<tr><td style=\"background:#111827;padding:24px 32px;color:#fff;font-size:18px;font-weight:600\">Northwind</td></tr>\n"
			. "<tr><td style=\"padding:32px\">\n"
			. "<h1 style=\"margin:0 0 12px;font-size:20px;color:#111827\">Your order is confirmed</h1>\n"
			. "<p style=\"margin:0 0 16px;color:#4b5563;line-height:1.6\">Thanks Jane — order <strong>#1482</strong> is on its way. We&rsquo;ll email again the moment it ships.</p>\n"
			. "<a href=\"#\" style=\"display:inline-block;background:#2271b1;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:600\">Track your order</a>\n"
			. "</td></tr>\n"
			. "<tr><td style=\"padding:20px 32px;background:#f9fafb;color:#6b7280;font-size:12px\">You received this because you placed an order at example.com</td></tr>\n"
			. "</table></td></tr></table></body></html>";
		$row->headers      = "Content-Type: text/html; charset=UTF-8\nReply-To: support@example.com";
		$row->attachments  = "/var/www/html/wp-content/uploads/2026/09/invoice-1482.pdf";
		$row->content_type = 'text/html';

		return $row;
	}
}

class Helo_Updater {
	public static function uri() { return 'https://raw.githubusercontent.com/x/y/main/update.json'; }
	public static function check_url() { return '#check'; }
}

require_once HELO_PATH . 'admin/class-helo-admin.php';

// --- Render ---------------------------------------------------------------

$screens = array(
	'settings' => array(),
	'logs'     => array( 'tab' => 'logs' ),
	'single'   => array( 'tab' => 'logs', 'log' => 118 ),
	'empty'    => array( 'tab' => 'logs' ),
	'fresh'    => array(),
);

foreach ( $screens as $name => $query ) {
	$_GET = $query;

	Helo_Logger::$empty = in_array( $name, array( 'empty', 'fresh' ), true );
	if ( 'fresh' === $name ) {
		$GLOBALS['options']['helo_settings']['host'] = '';
		Helo_Settings::save( array( 'password' => Helo_Settings::UNCHANGED ) );
	}

	if ( 'settings' === $name ) {
		$GLOBALS['transient'] = array( 'type' => 'success', 'message' => 'Test email accepted by the mail server. Check the inbox, and the spam folder.' );
	}

	ob_start();
	Helo_Admin::render();
	$body = ob_get_clean();

	// Inlined, because the preview pane serves these as standalone snapshots.
	$css = file_get_contents( __DIR__ . '/wp-base.css' )
		. file_get_contents( HELO_PATH . 'admin/css/admin.css' );

	file_put_contents(
		__DIR__ . "/{$name}.html",
		'<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $name . '</title>'
		. '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@wordpress/dashicons@0.1.0/build-style/dashicons.css">'
		. '<style>' . $css . '</style>'
		. '</head><body><div id="wpbody-content">' . $body . '</div></body></html>'
	);
}

echo "rendered: " . implode( ', ', array_keys( $screens ) ) . "\n";
