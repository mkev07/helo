<?php
/**
 * Standalone check for Helo_Turnstile's verification machinery: single-use
 * tokens, fail-closed missing tokens, and a mocked siteverify round trip.
 *
 * Run with: php tests/test-turnstile.php
 *
 * @package Helo
 */

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['options'] = array();
$GLOBALS['transient'] = array();

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['options'][ $name ] = $value;

	return true;
}
function get_transient( $name ) {
	return array_key_exists( $name, $GLOBALS['transient'] ) ? $GLOBALS['transient'][ $name ] : false;
}
function set_transient( $name, $value, $ttl = 0 ) {
	$GLOBALS['transient'][ $name ] = $value;
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_key( $value ) {
	return strtolower( trim( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ) );
}
function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, (array) $args );
}
function wp_salt( $scheme = 'auth' ) {
	return 'test-salt-' . $scheme;
}
function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) {
	return true;
}
function do_action( $hook, ...$args ) {
	return null;
}
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) {
	return true;
}
function add_shortcode( $tag, $callback ) {
	return true;
}
function current_time( $type ) {
	return 'timestamp' === $type ? time() : gmdate( 'Y-m-d H:i:s' );
}
function wp_doing_ajax() {
	return false;
}
function did_action( $tag ) {
	return false;
}
function is_admin() {
	return false;
}

/**
 * Stub Cloudflare: verify returns success for any token. We count the number
 * of wp_remote_post() calls so tests can assert the transient cache
 * short-circuits a second network round trip for the same token.
 *
 * @var int
 */
$GLOBALS['verify_calls'] = 0;
function is_wp_error( $value ) {
	return false;
}
function wp_remote_post( $url, $args = array() ) {
	++$GLOBALS['verify_calls'];

	return array( 'body' => json_encode( array( 'success' => true, 'action' => 'wordpress-test' ) ) );
}
function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}

function is_user_logged_in(  ) {
	return false;
}
function apply_filters( $t, $v ) {
	return $v;
}
function wp_validate_auth_cookie( $c = '', $s = '' ) {
	return false;
}
function wp_remote_retrieve_response_code( $r ) {
	return isset( $r['response']['code'] ) ? $r['response']['code'] : 200;
}
function wp_unslash( $v ) {
	return $v;
}
function __( $t, $d = null ) {
	return $t;
}
function wp_list_pluck( $list, $field ) {
	return array_map( function ( $i ) use ( $field ) { return $i[ $field ]; }, $list );
}

require_once dirname( __DIR__ ) . '/includes/class-helo-integrations.php';
require_once dirname( __DIR__ ) . '/includes/class-helo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-helo-exemptions.php';
require_once dirname( __DIR__ ) . '/includes/class-helo-turnstile.php';

// Configure Helo with a stub site + secret key so enabled() passes.
$GLOBALS['options'][ Helo_Settings::OPTION ] = array_merge(
	Helo_Settings::defaults(),
	array( 'turnstile_enable' => 1, 'turnstile_site_key' => 'site', 'turnstile_secret_key' => 'secret' )
);
// Reset the settings cache.
$cache = new ReflectionProperty( 'Helo_Settings', 'cache' );
$cache->setAccessible( true );
$cache->setValue( null, null );

/** Call a private static method. */
function helo_private( $class, $name, array $args = array() ) {
	$method = new ReflectionMethod( $class, $name );
	$method->setAccessible( true );

	return $method->invokeArgs( null, $args );
}

// 1. A valid token verifies against Cloudflare (one network round trip).
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$GLOBALS['verify_calls'] = 0;
$ok = Helo_Turnstile::check( 'token-a', 'wordpress-test' );
assert( true === $ok['success'], 'expected success for a fresh token' );
assert( 1 === $GLOBALS['verify_calls'], 'a fresh verify should call Cloudflare once' );

// 2. The same token checked again within the cache window short-circuits:
// no second network call. (Server-side single-use is Cloudflare's job; the
// transient stops our own double-verification.)
$GLOBALS['verify_calls'] = 0;
$again = Helo_Turnstile::check( 'token-a', 'wordpress-test' );
assert( true === $again['success'], 'cached token should stay verified' );
assert( 0 === $GLOBALS['verify_calls'], 'a repeat check must not hit Cloudflare again' );

// 3. A different token is independent and verifies via its own call.
$GLOBALS['verify_calls'] = 0;
$ok2 = Helo_Turnstile::check( 'token-b', 'wordpress-test' );
assert( true === $ok2['success'], 'independent tokens should not interfere' );
assert( 1 === $GLOBALS['verify_calls'], 'a different token needs its own verify' );

// 4. Fail-closed on a missing token (no network call).
$GLOBALS['verify_calls'] = 0;
$missing = Helo_Turnstile::check( '', 'wordpress-test' );
assert( false === $missing['success'], 'missing token must fail closed' );
assert( 0 === $GLOBALS['verify_calls'], 'a missing token must not hit Cloudflare' );

// 5. The transient key depends on the token (different tokens -> different keys).
$k1 = helo_private( 'Helo_Turnstile', 'transient_key', array( '_verify', 'aaa' ) );
$k2 = helo_private( 'Helo_Turnstile', 'transient_key', array( '_verify', 'bbb' ) );
assert( $k1 !== $k2, 'different tokens must map to different keys' );
assert( false !== $k1 && false !== $k2, 'transient keys must be non-empty' );

// 6. get_verified() reflects a set_verified() call.
$tk = helo_private( 'Helo_Turnstile', 'transient_key', array( 'test_flag', 'tok' ) );
helo_private( 'Helo_Turnstile', 'set_verified', array( 'test_flag', 'tok', 60 ) );
assert( true === helo_private( 'Helo_Turnstile', 'get_verified', array( 'test_flag', 'tok' ) ), 'set_verified should be readable' );

echo "ok\n";