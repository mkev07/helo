<?php
/**
 * Standalone check for the IMAP client's protocol handling.
 *
 * Drives the real class against a socket pair standing in for a server, so the
 * literal byte count, the CRLF normalising and the LIST parsing are exercised
 * for real rather than asserted about in comments. An off-by-one in the
 * {octet} count corrupts every appended message and is invisible until a
 * server rejects it.
 *
 * Run with: php tests/test-imap.php
 *
 * @package Helo
 */

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['transients'] = array();
$GLOBALS['keep_alive']  = array();

function get_transient( $name ) {
	return isset( $GLOBALS['transients'][ $name ] ) ? $GLOBALS['transients'][ $name ] : false;
}
function set_transient( $name, $value, $ttl = 0 ) {
	$GLOBALS['transients'][ $name ] = $value;

	return true;
}
function delete_transient( $name ) {
	unset( $GLOBALS['transients'][ $name ] );

	return true;
}
function add_action() {}

require_once dirname( __DIR__ ) . '/includes/class-helo-imap.php';

/**
 * A Helo_Imap wired to one end of a socket pair, plus the other end for the
 * test to play server with.
 *
 * @param string $scripted Bytes the fake server has waiting.
 * @return array{0: Helo_Imap, 1: resource}
 */
function fake_server( $scripted ) {
	$pair = stream_socket_pair( STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0 );

	fwrite( $pair[1], $scripted );

	$GLOBALS['keep_alive'][] = $pair[1];

	$imap   = new Helo_Imap();
	$stream = new ReflectionProperty( 'Helo_Imap', 'stream' );
	$stream->setAccessible( true );
	$stream->setValue( $imap, $pair[0] );

	return array( $imap, $pair[1] );
}

/**
 * Everything the client wrote to the server.
 *
 * @param resource $server Server end of the pair.
 * @return string
 */
function drain( $server ) {
	stream_set_blocking( $server, false );

	$out = '';
	while ( true ) {
		$chunk = fread( $server, 8192 );
		if ( false === $chunk || '' === $chunk ) {
			break;
		}
		$out .= $chunk;
	}

	return $out;
}

/* -------------------------------------------------------- folder discovery */

// 1. The server's own \Sent flag wins, whatever the folder is called.
list( $imap, ) = fake_server(
	"* LIST (\\HasNoChildren) \".\" INBOX\r\n"
	. "* LIST (\\HasNoChildren \\Drafts) \".\" INBOX.Drafts\r\n"
	. "* LIST (\\HasNoChildren \\Sent) \".\" INBOX.Verzonden\r\n"
	. "H001 OK LIST completed\r\n"
);
assert( 'INBOX.Verzonden' === $imap->resolve_sent_folder(), 'SPECIAL-USE \\Sent flag should win' );

// 2. Without SPECIAL-USE, fall back to a known name. DirectAdmin hosts like
//    MXroute expose "INBOX.Sent" with a dot separator.
$GLOBALS['transients'] = array();
list( $imap, ) = fake_server(
	"* LIST (\\HasNoChildren) \".\" INBOX\r\n"
	. "* LIST (\\HasNoChildren) \".\" INBOX.Sent\r\n"
	. "* LIST (\\HasNoChildren) \".\" INBOX.Trash\r\n"
	. "H001 OK LIST completed\r\n"
);
assert( 'INBOX.Sent' === $imap->resolve_sent_folder(), 'should fall back to INBOX.Sent' );

// 3. A quoted folder name is unquoted.
$GLOBALS['transients'] = array();
list( $imap, ) = fake_server(
	"* LIST (\\HasNoChildren) \"/\" \"Sent Items\"\r\n"
	. "H001 OK LIST completed\r\n"
);
assert( 'Sent Items' === $imap->resolve_sent_folder(), 'quoted names should be unquoted' );

// 4. Nothing plausible means nothing, rather than a wrong guess.
$GLOBALS['transients'] = array();
list( $imap, ) = fake_server( "* LIST (\\HasNoChildren) \".\" INBOX\r\nH001 OK LIST completed\r\n" );
assert( '' === $imap->resolve_sent_folder(), 'no Sent folder should return empty' );

/* ------------------------------------------------------------------ append */

// 5. The declared literal size must equal the bytes that follow it, and the
//    message must be normalised to CRLF first.
list( $imap, $server ) = fake_server( "+ Ready for literal data\r\nH001 OK [APPENDUID 1 2] APPEND completed\r\n" );

$message = "Subject: Mixed endings\nFrom: a@example.com\r\nTo: b@example.com\n\nBody line one\nBody line two\n";
assert( true === $imap->append( 'INBOX.Sent', $message ), 'append should succeed' );

$wrote = drain( $server );

assert( 1 === preg_match( '/^H001 APPEND "INBOX\.Sent" \(\\\\Seen\) "[^"]+" \{(\d+)\}\r\n/', $wrote, $m ), 'malformed APPEND command: ' . $wrote );

$declared = (int) $m[1];
$payload  = substr( $wrote, strlen( $m[0] ) );
$payload  = substr( $payload, 0, -2 ); // Trailing CRLF terminating the literal.

assert( $declared === strlen( $payload ), sprintf( 'declared %d octets but sent %d', $declared, strlen( $payload ) ) );
assert( $payload === preg_replace( '/\r\n|\r|\n/', "\r\n", $message ), 'payload should be the CRLF-normalised message' );
assert( false === strpos( preg_replace( '/\r\n/', '', $payload ), "\n" ), 'no bare newlines should survive' );

// 6. A refused append is reported, not silently treated as success.
list( $imap, ) = fake_server( "H001 NO [TRYCREATE] Mailbox does not exist\r\n" );
assert( false === $imap->append( 'INBOX.Nope', "Subject: x\r\n\r\nbody\r\n" ), 'refused append should fail' );
assert( false !== stripos( $imap->error(), 'Mailbox does not exist' ), 'error should carry the server reason' );

// 7. A failure after the literal is also caught.
list( $imap, ) = fake_server( "+ go ahead\r\nH001 NO Over quota\r\n" );
assert( false === $imap->append( 'INBOX.Sent', "Subject: x\r\n\r\nbody\r\n" ), 'post-literal failure should fail' );
assert( false !== stripos( $imap->error(), 'Over quota' ), 'error should carry the server reason' );

/* ------------------------------------------------------------------ quoting */

// 8. Quotes and backslashes in a password must not break out of the string.
$quote = new ReflectionMethod( 'Helo_Imap', 'quote' );
$quote->setAccessible( true );
assert( '"a\\"b"' === $quote->invoke( null, 'a"b' ), 'double quotes should be escaped' );
assert( '"a\\\\b"' === $quote->invoke( null, 'a\\b' ), 'backslashes should be escaped' );

echo "ok\n";
