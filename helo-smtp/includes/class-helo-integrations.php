<?php
/**
 * Registry of everything Turnstile can protect.
 *
 * One declarative table drives three things that used to drift apart: the
 * settings defaults, the sanitiser, and the admin screen. Adding a form plugin
 * means adding a row here and a wiring method, not editing four files.
 *
 * Detection is advisory — it decides how the admin screen groups and labels a
 * row, never whether the hooks are attached. A wrong marker therefore dims a
 * card; it cannot silently switch protection off.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Integrations {

	const GROUP_CORE       = 'core';
	const GROUP_FORMS      = 'forms';
	const GROUP_COMMERCE   = 'commerce';
	const GROUP_MEMBERSHIP = 'membership';

	/**
	 * Group labels and blurbs, in display order.
	 *
	 * @return array<string,array{label:string,blurb:string}>
	 */
	public static function groups() {
		return array(
			self::GROUP_CORE     => array(
				'label' => __( 'WordPress', 'helo-smtp' ),
				'blurb' => __( 'The forms every site has.', 'helo-smtp' ),
			),
			self::GROUP_FORMS    => array(
				'label' => __( 'Form builders', 'helo-smtp' ),
				'blurb' => __( 'Contact and lead forms.', 'helo-smtp' ),
			),
			self::GROUP_COMMERCE => array(
				'label' => __( 'E-commerce', 'helo-smtp' ),
				'blurb' => __( 'Checkout and customer accounts.', 'helo-smtp' ),
			),
		);
	}

	/**
	 * Every protectable surface.
	 *
	 * detect: true for things WordPress always has, otherwise one of
	 * class / function / constant naming a marker the plugin defines.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		return array(
			'login'      => array(
				'label'   => __( 'Login', 'helo-smtp' ),
				'note'    => __( 'wp-login.php', 'helo-smtp' ),
				'group'   => self::GROUP_CORE,
				'setting' => 'turnstile_login',
				'detect'  => true,
			),
			'register'   => array(
				'label'   => __( 'Registration', 'helo-smtp' ),
				'note'    => __( 'New account sign-ups', 'helo-smtp' ),
				'group'   => self::GROUP_CORE,
				'setting' => 'turnstile_register',
				'detect'  => true,
			),
			'reset'      => array(
				'label'   => __( 'Lost password', 'helo-smtp' ),
				'note'    => __( 'Password reset requests', 'helo-smtp' ),
				'group'   => self::GROUP_CORE,
				'setting' => 'turnstile_reset',
				'detect'  => true,
			),
			'comments'   => array(
				'label'   => __( 'Comments', 'helo-smtp' ),
				'note'    => __( 'No exemptions, not even logged-in users', 'helo-smtp' ),
				'group'   => self::GROUP_CORE,
				'setting' => 'turnstile_comments',
				'detect'  => true,
				'strict'  => true,
			),

			'cf7'        => array(
				'label'   => __( 'Contact Form 7', 'helo-smtp' ),
				'note'    => __( 'Adds a [cf7_simple_turnstile] tag', 'helo-smtp' ),
				'group'   => self::GROUP_FORMS,
				'setting' => 'turnstile_cf7',
				'detect'  => array( 'class' => 'WPCF7_ContactForm' ),
			),
			'wpforms'    => array(
				'label'   => __( 'WPForms', 'helo-smtp' ),
				'group'   => self::GROUP_FORMS,
				'setting' => 'turnstile_wpforms',
				'detect'  => array( 'function' => 'wpforms' ),
			),
			'forminator' => array(
				'label'   => __( 'Forminator', 'helo-smtp' ),
				'group'   => self::GROUP_FORMS,
				'setting' => 'turnstile_forminator',
				'detect'  => array( 'constant' => 'FORMINATOR_VERSION' ),
			),
			'fluent'     => array(
				'label'   => __( 'Fluent Forms', 'helo-smtp' ),
				'group'   => self::GROUP_FORMS,
				'setting' => 'turnstile_fluent',
				'detect'  => array( 'constant' => 'FLUENTFORM_VERSION' ),
			),
			'formidable' => array(
				'label'   => __( 'Formidable Forms', 'helo-smtp' ),
				'group'   => self::GROUP_FORMS,
				'setting' => 'turnstile_formidable',
				'detect'  => array( 'constant' => 'FRM_VERSION' ),
			),
			'gravity'    => array(
				'label'   => __( 'Gravity Forms', 'helo-smtp' ),
				'note'    => __( 'Multi-page forms keep working', 'helo-smtp' ),
				'group'   => self::GROUP_FORMS,
				'setting' => 'turnstile_gravity',
				'detect'  => array( 'class' => 'GFAPI' ),
			),
			'jetpack'    => array(
				'label'   => __( 'Jetpack forms', 'helo-smtp' ),
				'group'   => self::GROUP_FORMS,
				'setting' => 'turnstile_jetpack',
				'detect'  => array( 'constant' => 'JETPACK__VERSION' ),
			),
			'kadence'    => array(
				'label'   => __( 'Kadence Blocks', 'helo-smtp' ),
				'group'   => self::GROUP_FORMS,
				'setting' => 'turnstile_kadence',
				'detect'  => array( 'constant' => 'KADENCE_BLOCKS_VERSION' ),
			),
			'sureforms'  => array(
				'label'   => __( 'SureForms', 'helo-smtp' ),
				'group'   => self::GROUP_FORMS,
				'setting' => 'turnstile_sureforms',
				'detect'  => array( 'constant' => 'SRFM_VERSION' ),
			),
			'elementor'  => array(
				'label'   => __( 'Elementor Pro forms', 'helo-smtp' ),
				'note'    => __( 'Requires Elementor Pro', 'helo-smtp' ),
				'group'   => self::GROUP_FORMS,
				'setting' => 'turnstile_elementor',
				'detect'  => array( 'constant' => 'ELEMENTOR_PRO_VERSION' ),
			),

			'woo'        => array(
				'label'   => __( 'WooCommerce', 'helo-smtp' ),
				'note'    => __( 'Login, registration, reset and checkout', 'helo-smtp' ),
				'group'   => self::GROUP_COMMERCE,
				'setting' => 'turnstile_woo',
				'detect'  => array( 'class' => 'WooCommerce' ),
			),
		);
	}

	/**
	 * @param string $slug Integration key.
	 * @return array|null
	 */
	public static function get( $slug ) {
		$all = self::all();

		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/**
	 * Is the plugin behind this integration present?
	 *
	 * Pure apart from the marker lookups, so it is testable by passing a spec
	 * directly.
	 *
	 * @param array|bool $detect Detection spec from the registry.
	 * @return bool
	 */
	public static function detect( $detect ) {
		if ( true === $detect ) {
			return true;
		}

		if ( ! is_array( $detect ) ) {
			return false;
		}

		if ( isset( $detect['class'] ) ) {
			return class_exists( $detect['class'] );
		}

		if ( isset( $detect['function'] ) ) {
			return function_exists( $detect['function'] );
		}

		if ( isset( $detect['constant'] ) ) {
			return defined( $detect['constant'] );
		}

		return false;
	}

	/**
	 * @param string $slug Integration key.
	 * @return bool
	 */
	public static function is_detected( $slug ) {
		$item = self::get( $slug );

		return $item ? self::detect( $item['detect'] ) : false;
	}

	/**
	 * Registry rows enriched with runtime state, grouped for the admin screen.
	 *
	 * @param array $settings Resolved settings.
	 * @return array<string,array{label:string,blurb:string,items:array}>
	 */
	public static function grouped( array $settings ) {
		$groups = array();

		foreach ( self::groups() as $key => $group ) {
			$groups[ $key ] = $group + array( 'items' => array() );
		}

		foreach ( self::all() as $slug => $item ) {
			$item['slug']     = $slug;
			$item['detected'] = self::detect( $item['detect'] );
			$item['on']       = ! empty( $settings[ $item['setting'] ] );

			$groups[ $item['group'] ]['items'][ $slug ] = $item;
		}

		return $groups;
	}

	/**
	 * Setting keys for every integration, for defaults and sanitising.
	 *
	 * @return string[]
	 */
	public static function setting_keys() {
		return wp_list_pluck( self::all(), 'setting' );
	}

	/**
	 * How many surfaces are switched on, and how many of those are detected.
	 *
	 * @param array $settings Resolved settings.
	 * @return array{on:int,total:int,missing:int}
	 */
	public static function summary( array $settings ) {
		$on      = 0;
		$missing = 0;
		$all     = self::all();

		foreach ( $all as $item ) {
			if ( empty( $settings[ $item['setting'] ] ) ) {
				continue;
			}

			$on++;

			if ( ! self::detect( $item['detect'] ) ) {
				$missing++;
			}
		}

		return array(
			'on'      => $on,
			'total'   => count( $all ),
			'missing' => $missing,
		);
	}
}
