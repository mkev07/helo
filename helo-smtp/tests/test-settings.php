<?php
/**
 * Standalone check for the two bits of Helo_Settings that can lose credentials:
 * password encryption round-tripping, and the "field left untouched" sentinel.
 *
 * Run with: php tests/test-settings.php
 *
 * @package Helo
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['options'] = array();

function wp_salt( $scheme = 'auth' ) {
	return 'test-salt-' . $scheme;
}
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['options'][ $name ] = $value;

	return true;
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_email( $value ) {
	return (string) filter_var( $value, FILTER_VALIDATE_EMAIL );
}
function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, (array) $args );
}

require_once dirname( __DIR__ ) . '/includes/class-helo-settings.php';

/**
 * @param string $name Private static method on Helo_Settings.
 * @param array  $args Arguments.
 * @return mixed
 */
function call_private( $name, array $args ) {
	$method = new ReflectionMethod( 'Helo_Settings', $name );
	$method->setAccessible( true );

	return $method->invoke( null, $args[0] );
}

/** Force Helo_Settings to re-read the option store. */
function reset_cache() {
	$cache = new ReflectionProperty( 'Helo_Settings', 'cache' );
	$cache->setAccessible( true );
	$cache->setValue( null, null );
}

// 1. A password survives the encrypt/decrypt round trip.
$secret = 'p@ss word — with ünicode';
$cipher = call_private( 'encrypt', array( $secret ) );
assert( $cipher !== $secret, 'password should not be stored in the clear' );
assert( call_private( 'decrypt', array( $cipher ) ) === $secret, 'round trip failed' );

// 2. Two encryptions of the same value differ (random IV) but both decrypt back.
assert( call_private( 'encrypt', array( $secret ) ) !== $cipher, 'IV should be random' );

// 3. An empty password stays empty rather than becoming ciphertext.
assert( call_private( 'encrypt', array( '' ) ) === '', 'empty password should stay empty' );

// 4. A legacy plain-text value decrypts to itself instead of turning to mush.
assert( call_private( 'decrypt', array( 'plain-text-password' ) ) === 'plain-text-password', 'plain value mangled' );

// 5. Saving with the sentinel keeps the stored password.
Helo_Settings::save(
	array(
		'host'     => 'smtp.example.com',
		'password' => $secret,
	)
);
reset_cache();
$stored = $GLOBALS['options'][ Helo_Settings::OPTION ]['password'];
assert( Helo_Settings::get( 'password' ) === $secret, 'saved password not readable' );

Helo_Settings::save(
	array(
		'host'     => 'smtp.example.com',
		'password' => Helo_Settings::UNCHANGED,
	)
);
reset_cache();
assert( $GLOBALS['options'][ Helo_Settings::OPTION ]['password'] === $stored, 'sentinel should leave the password alone' );
assert( Helo_Settings::get( 'password' ) === $secret, 'password lost after an unrelated save' );

// 6. An explicitly blank password clears it.
Helo_Settings::save(
	array(
		'host'     => 'smtp.example.com',
		'password' => '',
	)
);
reset_cache();
assert( Helo_Settings::get( 'password' ) === '', 'blank password should clear' );

// 7. Junk input is clamped rather than stored.
Helo_Settings::save(
	array(
		'port'       => 99999,
		'encryption' => 'carrier-pigeon',
		'log_days'   => -5,
		'password'   => Helo_Settings::UNCHANGED,
	)
);
reset_cache();
assert( 65535 === Helo_Settings::get( 'port' ), 'port not clamped' );
assert( 'tls' === Helo_Settings::get( 'encryption' ), 'encryption not validated' );
assert( 0 === Helo_Settings::get( 'log_days' ), 'log_days not clamped' );

echo "ok\n";
