<?php
/**
 * Standalone check for Helo_Analytics: counter accumulation, per-form
 * breakdown, block-reason tracking, and the debug log.
 *
 * Run with: php tests/test-analytics.php
 *
 * @package Helo
 */

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['options'] = array();

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['options'][ $name ] = $value;

	return true;
}
function delete_option( $name ) {
	unset( $GLOBALS['options'][ $name ] );
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function wp_unslash( $value ) {
	return $value;
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
function current_time( $type ) {
	return 'timestamp' === $type ? time() : gmdate( 'Y-m-d H:i:s' );
}
function absint( $value ) {
	return abs( (int) $value );
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, $decimals );
}
function sanitize_email( $value ) {
	return (string) filter_var( $value, FILTER_VALIDATE_EMAIL );
}
function check_admin_referer( $action ) {
	return true;
}
function wp_die( ...$args ) {}
function wp_safe_redirect( $url ) {
	return true;
}
function admin_url( $path = '' ) {
	return 'http://example.test/wp-admin/' . ltrim( $path, '/' );
}
function add_query_arg( $key, $value = '', $url = '' ) {
	return (string) $url . '?page=helo';
}
function current_user_can( $cap ) {
	return true;
}

require_once dirname( __DIR__ ) . '/includes/class-helo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-helo-turnstile.php';
require_once dirname( __DIR__ ) . '/includes/class-helo-analytics.php';

// Settings: counters on, debug log on so record() fills both.
$GLOBALS['options'][ Helo_Settings::OPTION ] = array_merge(
	Helo_Settings::defaults(),
	array( 'turnstile_analytics' => 1, 'turnstile_debug_log' => 1 )
);
$cache = new ReflectionProperty( 'Helo_Settings', 'cache' );
$cache->setAccessible( true );
$cache->setValue( null, null );

$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$_SERVER['REQUEST_URI'] = '/contact';

// 1. Failed check increments blocked + per-form + error reason.
Helo_Analytics::record( null, array( 'success' => false, 'error_code' => 'invalid-input-response' ), 'contact-form' );
$snap = Helo_Analytics::snapshot();
assert( 1 === $snap['total'], 'total should be 1' );
assert( 0 === $snap['verified'], 'no verified yet' );
assert( 1 === $snap['blocked'], 'one blocked' );
assert( 1 === $snap['errors']['invalid-input-response'], 'reason counted' );
assert( 1 === $snap['forms']['contact-form']['blocked'], 'form blocked counted' );
assert( 1 === $snap['forms']['contact-form']['total'], 'form total counted' );

// 2. Verified check increments verified, not blocked.
Helo_Analytics::record( (object) array( 'success' => true ), array( 'success' => true ), 'contact-form' );
$snap = Helo_Analytics::snapshot();
assert( 2 === $snap['total'], 'total should be 2' );
assert( 1 === $snap['verified'], 'one verified' );
assert( 1 === $snap['blocked'], 'still one blocked' );
assert( 1 === $snap['forms']['contact-form']['verified'], 'form verified counted' );

// 3. A retry error tags the retries counter.
Helo_Analytics::record( null, array( 'success' => false, 'error_code' => 'timeout-or-duplicate' ), 'contact-form' );
$snap = Helo_Analytics::snapshot();
assert( 1 === $snap['retries'], 'retry counted' );
assert( 2 === $snap['blocked'], 'retry also counts as blocked' );

// 4. Different forms are tracked separately.
Helo_Analytics::record( null, array( 'success' => false, 'error_code' => 'missing-input-response' ), 'wordpress-comment' );
$snap = Helo_Analytics::snapshot();
assert( 2 === count( $snap['forms'] ), 'two forms tracked' );
assert( 1 === $snap['forms']['wordpress-comment']['blocked'], 'comment form blocked counted' );

// 5. Debug log records verifying AND blocked entries with ip/page.
$log = Helo_Analytics::log();
assert( 4 === count( $log ), 'four log entries' );
$last = reset( $log );
assert( '203.0.113.9' === $last['ip'], 'ip recorded' );
assert( '/contact' === $last['page'], 'page recorded' );

// 6. Clear resets counters but not the log.
Helo_Analytics::clear();
$snap = Helo_Analytics::snapshot();
assert( 0 === $snap['total'], 'counters reset' );
assert( '' === $snap['updated'], 'updated cleared' );
assert( 4 === count( Helo_Analytics::log() ), 'log untouched by analytics reset' );

// 7. Clearing the log empties it.
Helo_Analytics::clear_log();
assert( 0 === count( Helo_Analytics::log() ), 'log cleared' );

// 8. Percent formatting guards divide-by-zero.
assert( '0%' === Helo_Analytics::percent( 5, 0 ), 'percent guards zero' );
assert( '50.0%' === Helo_Analytics::percent( 5, 10 ), 'percent computes' );

echo "ok\n";