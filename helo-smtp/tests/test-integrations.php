<?php
/**
 * Standalone checks for the pure predicates in Helo_Form_Integrations:
 * Gravity's pagination detection and page-nav guard, and Forminator's field
 * token normalizer. These are the only parts of the integrations class testable
 * without a live plugin install.
 *
 * Run with: php tests/test-integrations.php
 *
 * @package Helo
 */

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['options'] = array();
$GLOBALS['_post']   = array();

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
function sanitize_key( $value ) {
	return strtolower( trim( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ) );
}
function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, (array) $args );
}
function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) {
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) {
	return true;
}
function has_action( $hook ) {
	return false;
}

require_once dirname( __DIR__ ) . '/includes/class-helo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-helo-form-integrations.php';

// --- Gravity pagination detection -----------------------------------------

// 1. A form with a 'page' field is multi-page.
assert( true === Helo_Form_Integrations::gravity_has_pages( array(
	'id'     => 1,
	'fields' => array(
		array( 'type' => 'email' ),
		array( 'type' => 'page' ),
	),
) ), 'page field should register as paginated' );

// 2. Without a page field it is not.
assert( false === Helo_Form_Integrations::gravity_has_pages( array(
	'id'     => 1,
	'fields' => array( array( 'type' => 'email' ) ),
) ), 'no page field should not be paginated' );

// 3. Missing / empty fields array is safe.
assert( false === Helo_Form_Integrations::gravity_has_pages( array( 'id' => 1 ) ), 'missing fields should be safe' );
assert( false === Helo_Form_Integrations::gravity_has_pages( array() ), 'empty form should be safe' );

// --- Gravity page-nav guard ------------------------------------------------

$paginated   = array( 'id' => 1, 'fields' => array( array( 'type' => 'page' ), array( 'type' => 'email' ) ) );
$single_page = array( 'id' => 2, 'fields' => array( array( 'type' => 'email' ) ) );

// 4. Paginated form, advancing pages without a token: skip the check.
assert( true === Helo_Form_Integrations::gravity_page_nav( $paginated, '', '2' ), 'paginated page advance should skip' );

// 5. Same form WITH a token: always verify.
assert( false === Helo_Form_Integrations::gravity_page_nav( $paginated, 'tok-123', '2' ), 'a token must always verify' );

// 6. Single-page form, forged target page, no token: fail closed (no skip).
assert( false === Helo_Form_Integrations::gravity_page_nav( $single_page, '', '2' ), 'single-page form cannot skip via forged target' );

// 7. No posted target at all (a final / single submit): no skip.
assert( false === Helo_Form_Integrations::gravity_page_nav( $paginated, '', '' ), 'no target page should not skip' );

// --- Forminator token normalizer -------------------------------------------

// 8. Associative-shaped field data.
$assoc = array( 'cf-turnstile-response' => 'tok-a', 'email' => 'a@b.c' );
assert( 'tok-a' === Helo_Form_Integrations::forminator_token( $assoc ), 'assoc shape token read' );

// 9. Array-of-array shape.
$list = array(
	array( 'name' => 'email', 'value' => 'a@b.c' ),
	array( 'name' => 'cf-turnstile-response', 'value' => 'tok-b' ),
);
assert( 'tok-b' === Helo_Form_Integrations::forminator_token( $list ), 'list-of-arrays shape token read' );

// 10. Array-of-objects shape.
$objs = array(
	(object) array( 'name' => 'cf-turnstile-response', 'value' => 'tok-c' ),
	(object) array( 'name' => 'email', 'value' => 'a@b.c' ),
);
assert( 'tok-c' === Helo_Form_Integrations::forminator_token( $objs ), 'list-of-objects shape token read' );

// 11. Missing token returns ''.
assert( '' === Helo_Form_Integrations::forminator_token( array( 'email' => 'a@b.c' ) ), 'no token returns empty' );
assert( '' === Helo_Form_Integrations::forminator_token( array() ), 'empty data returns empty' );
assert( '' === Helo_Form_Integrations::forminator_token( 'garbage' ), 'garbage input is safe' );

echo "ok\n";