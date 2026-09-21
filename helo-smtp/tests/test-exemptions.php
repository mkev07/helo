<?php
/**
 * Standalone check for the exemption matching.
 *
 * This is the code that decides who skips the bot check, so a wrong answer is
 * a bypass rather than a cosmetic bug. The CIDR maths in particular has to be
 * exact at the boundaries and must never match across address families.
 *
 * Run with: php tests/test-exemptions.php
 *
 * @package Helo
 */

define( 'ABSPATH', __DIR__ );

function sanitize_text_field( $v ) {
	return trim( strip_tags( (string) $v ) );
}
function wp_unslash( $v ) {
	return $v;
}
function is_user_logged_in() {
	return false;
}
function apply_filters( $tag, $value ) {
	return $value;
}

require_once dirname( __DIR__ ) . '/includes/class-helo-exemptions.php';

/* ------------------------------------------------------------ exact match */

assert( Helo_Exemptions::ip_matches( '203.0.113.7', '203.0.113.7' ), 'identical IPv4 should match' );
assert( ! Helo_Exemptions::ip_matches( '203.0.113.7', '203.0.113.8' ), 'different IPv4 should not match' );

// inet_pton canonicalises, so shorthand and longhand IPv6 are the same address.
assert( Helo_Exemptions::ip_matches( '2001:db8::1', '2001:0db8:0000:0000:0000:0000:0000:0001' ), 'IPv6 shorthand should match longhand' );

// Garbage must never match, rather than matching everything.
assert( ! Helo_Exemptions::ip_matches( 'not-an-ip', 'not-an-ip' ), 'invalid input should not match itself' );
assert( ! Helo_Exemptions::ip_matches( '', '' ), 'empty should not match' );

/* ------------------------------------------------------------------ CIDR */

assert( Helo_Exemptions::ip_in_cidr( '198.51.100.23', '198.51.100.0/24' ), 'address inside /24' );
assert( ! Helo_Exemptions::ip_in_cidr( '198.51.101.23', '198.51.100.0/24' ), 'address outside /24' );

// Boundaries: first and last address of the range are both inside it.
assert( Helo_Exemptions::ip_in_cidr( '198.51.100.0', '198.51.100.0/24' ), 'network address is inside' );
assert( Helo_Exemptions::ip_in_cidr( '198.51.100.255', '198.51.100.0/24' ), 'broadcast address is inside' );
assert( ! Helo_Exemptions::ip_in_cidr( '198.51.99.255', '198.51.100.0/24' ), 'one below the range is outside' );
assert( ! Helo_Exemptions::ip_in_cidr( '198.51.101.0', '198.51.100.0/24' ), 'one above the range is outside' );

// A prefix that is not a whole number of bytes exercises the mask maths.
assert( Helo_Exemptions::ip_in_cidr( '192.168.1.130', '192.168.1.128/25' ), 'inside a /25' );
assert( ! Helo_Exemptions::ip_in_cidr( '192.168.1.127', '192.168.1.128/25' ), 'just below a /25' );
assert( Helo_Exemptions::ip_in_cidr( '10.0.0.5', '10.0.0.4/30' ), 'inside a /30' );
assert( ! Helo_Exemptions::ip_in_cidr( '10.0.0.8', '10.0.0.4/30' ), 'outside a /30' );

// /32 is a single host; /0 is everything.
assert( Helo_Exemptions::ip_in_cidr( '203.0.113.7', '203.0.113.7/32' ), '/32 matches itself' );
assert( ! Helo_Exemptions::ip_in_cidr( '203.0.113.8', '203.0.113.7/32' ), '/32 matches nothing else' );
assert( Helo_Exemptions::ip_in_cidr( '8.8.8.8', '0.0.0.0/0' ), '/0 matches everything' );

// IPv6.
assert( Helo_Exemptions::ip_in_cidr( '2001:db8:1234::1', '2001:db8::/32' ), 'inside an IPv6 /32' );
assert( ! Helo_Exemptions::ip_in_cidr( '2001:db9::1', '2001:db8::/32' ), 'outside an IPv6 /32' );

// Families must never cross: a v4 address is not inside a v6 range.
assert( ! Helo_Exemptions::ip_in_cidr( '203.0.113.7', '2001:db8::/32' ), 'v4 is not inside a v6 range' );
assert( ! Helo_Exemptions::ip_in_cidr( '2001:db8::1', '198.51.100.0/24' ), 'v6 is not inside a v4 range' );

// Malformed entries fail closed.
assert( ! Helo_Exemptions::ip_in_cidr( '198.51.100.1', '198.51.100.0/' ), 'missing prefix' );
assert( ! Helo_Exemptions::ip_in_cidr( '198.51.100.1', '198.51.100.0/abc' ), 'non-numeric prefix' );
assert( ! Helo_Exemptions::ip_in_cidr( '198.51.100.1', '198.51.100.0/33' ), 'prefix beyond the address size' );
assert( ! Helo_Exemptions::ip_in_cidr( '198.51.100.1', 'garbage/24' ), 'unparseable network' );

/* ------------------------------------------------------------------ lists */

$list = "  203.0.113.7  \n\n# a comment\n198.51.100.0/24\n2001:db8::/32\n";

assert( Helo_Exemptions::ip_listed( '203.0.113.7', $list ), 'exact entry, surrounded by whitespace' );
assert( Helo_Exemptions::ip_listed( '198.51.100.99', $list ), 'CIDR entry' );
assert( Helo_Exemptions::ip_listed( '2001:db8:abcd::9', $list ), 'IPv6 CIDR entry' );
assert( ! Helo_Exemptions::ip_listed( '8.8.8.8', $list ), 'address not on the list' );
assert( ! Helo_Exemptions::ip_listed( '', $list ), 'no address means no match' );
assert( ! Helo_Exemptions::ip_listed( '203.0.113.7', '' ), 'empty list means no match' );
assert( ! Helo_Exemptions::ip_listed( '203.0.113.7', "   \n  \n" ), 'whitespace-only list means no match' );

// A comment line must not be treated as an entry.
assert( ! Helo_Exemptions::ip_listed( '203.0.113.7', "# 203.0.113.7\n" ), 'commented entry is ignored' );

/* ----------------------------------------------------------- user agents */

$agents = "UptimeRobot\n# ignored\nPingdom\n";

assert( Helo_Exemptions::agent_listed( 'Mozilla/5.0 (compatible; UptimeRobot/2.0)', $agents ), 'substring anywhere matches' );
assert( Helo_Exemptions::agent_listed( 'pingdom bot', $agents ), 'matching is case-insensitive' );
assert( ! Helo_Exemptions::agent_listed( 'Mozilla/5.0 Chrome/120', $agents ), 'ordinary browser does not match' );
assert( ! Helo_Exemptions::agent_listed( '', $agents ), 'no agent means no match' );
assert( ! Helo_Exemptions::agent_listed( 'UptimeRobot', '' ), 'empty list means no match' );

/* -------------------------------------------------------------- remote ip */

$_SERVER = array();
assert( '' === Helo_Exemptions::remote_ip(), 'no headers means no IP' );

$_SERVER = array( 'REMOTE_ADDR' => '203.0.113.7' );
assert( '203.0.113.7' === Helo_Exemptions::remote_ip(), 'falls back to REMOTE_ADDR' );

// Cloudflare rewrites its own header, so it wins.
$_SERVER = array( 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.4' );
assert( '198.51.100.4' === Helo_Exemptions::remote_ip(), 'CF-Connecting-IP wins' );

// The forwarded chain is client-prefixed; take the first routable hop, not a
// private one an attacker padded the header with.
$_SERVER = array( 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '10.0.0.9, 198.51.100.5, 172.16.0.1' );
assert( '198.51.100.5' === Helo_Exemptions::remote_ip(), 'first public address in the chain wins' );

// Junk in a header must not become the IP.
$_SERVER = array( 'REMOTE_ADDR' => '203.0.113.7', 'HTTP_CF_CONNECTING_IP' => 'not-an-ip' );
assert( '203.0.113.7' === Helo_Exemptions::remote_ip(), 'invalid header is ignored' );

$_SERVER = array();

/* ------------------------------------------------------------ the decision */

$GLOBALS['helo_settings'] = array(
	'turnstile_skip_users'  => 0,
	'turnstile_skip_ips'    => '198.51.100.0/24',
	'turnstile_skip_agents' => '',
);

class Helo_Settings {
	public static function get( $key ) {
		return isset( $GLOBALS['helo_settings'][ $key ] ) ? $GLOBALS['helo_settings'][ $key ] : '';
	}
}

$_SERVER = array( 'REMOTE_ADDR' => '198.51.100.20' );
assert( Helo_Exemptions::applies( 'wordpress-login' ), 'an allowlisted IP is exempt' );

// Comments are the one surface that is never exempt, whatever the rules say.
assert( ! Helo_Exemptions::applies( 'wordpress-comment' ), 'comments are never exempt' );

$_SERVER = array( 'REMOTE_ADDR' => '8.8.8.8' );
assert( ! Helo_Exemptions::applies( 'wordpress-login' ), 'an address off the list is not exempt' );

echo "ok\n";
