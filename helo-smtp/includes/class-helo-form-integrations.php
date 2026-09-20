<?php
/**
 * Auto-injection of the Turnstile widget into third-party form builders.
 *
 * Each supported plugin gets a pair of hooks: one renders the widget into the
 * plugin's form, the other verifies the token on submit. The widget and the
 * server-side check are shared from Helo_Turnstile, so each integration is
 * mostly a matter of knowing that plugin's hook names and how it reports a
 * field error.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Form_Integrations {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'wire' ), 25 );
	}

	public static function wire() {
		self::wire_wpforms();
		self::wire_forminator();
		self::wire_fluent();
		self::wire_formidable();
		self::wire_jetpack();
		self::wire_gravity();
		self::wire_kadence();
		self::wire_sureforms();
		self::wire_elementor();
	}

	/* ------------------------------------------------------------- helpers */

	/**
	 * Capture the widget markup for hooks that must RETURN a string.
	 *
	 * Helo_Turnstile::field() echoes the widget div and enqueues the render
	 * script; the enqueue side effect is wanted, only the echoed div (and any
	 * submit-button style) should land in the returned markup.
	 *
	 * @param string $button_id CSS selector for the submit button.
	 * @param string $action    Form action label.
	 * @param string $unique_id Widget id suffix.
	 * @return string
	 */
	private static function field_markup( $button_id, $action, $unique_id ) {
		if ( ! Helo_Turnstile::enabled() ) {
			return '';
		}

		ob_start();
		Helo_Turnstile::field( $button_id, $action, $unique_id );
		$html   = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/* -------------------------------------------------------------- WPForms */

	public static function wire_wpforms() {
		if ( ! Helo_Settings::get( 'turnstile_wpforms' ) || ! function_exists( 'wpforms' ) ) {
			return;
		}

		add_action( 'wpforms_display_submit_before', array( __CLASS__, 'wpforms_field' ), 10, 1 );
		add_action( 'wpforms_process_before', array( __CLASS__, 'wpforms_check' ), 10, 2 );
	}

	public static function wpforms_field( $form_data ) {
		Helo_Turnstile::field( '.wpforms-submit', 'form-wpforms', '-wpf-' . $form_data['id'] );
	}

	public static function wpforms_check( $entry, $form_data ) {
		$check = Helo_Turnstile::check( '', 'form-wpforms' );
		if ( ! $check['success'] ) {
			wpforms()->process->errors[ $form_data['id'] ]['header'] = Helo_Turnstile::failed_message();
		}
	}

	/* ------------------------------------------------------------ Forminator */

	public static function wire_forminator() {
		if ( ! Helo_Settings::get( 'turnstile_forminator' ) || ! defined( 'FORMINATOR_VERSION' ) ) {
			return;
		}

		add_filter( 'forminator_render_form_submit_markup', array( __CLASS__, 'forminator_field' ), 10, 4 );
		add_filter( 'forminator_custom_form_submit_errors', array( __CLASS__, 'forminator_check' ), 10, 3 );
	}

	public static function forminator_field( $html, $form_id, $post_id, $nonce ) {
		return self::field_markup( '.forminator-button-submit', 'form-forminator', '-fmn-' . $form_id ) . $html;
	}

	/**
	 * The token in Forminator's field data arrives in several shapes; normalise
	 * to the cf-turnstile-response value. Pure — tested.
	 *
	 * @param array $field_data_array Forminator's submitted field data.
	 * @return string
	 */
	public static function forminator_token( $field_data_array ) {
		$posted = array();

		if ( ! is_array( $field_data_array ) ) {
			return '';
		}

		foreach ( $field_data_array as $key => $val ) {
			if ( is_string( $key ) && ! is_array( $val ) && ! is_object( $val ) ) {
				$posted[ $key ] = $val;
				continue;
			}
			if ( is_array( $val ) && isset( $val['name'] ) ) {
				$posted[ $val['name'] ] = isset( $val['value'] ) ? $val['value'] : '';
				continue;
			}
			if ( is_object( $val ) && isset( $val->name ) ) {
				$posted[ $val->name ] = isset( $val->value ) ? $val->value : '';
			}
		}

		$token = isset( $posted['cf-turnstile-response'] ) && ! is_array( $posted['cf-turnstile-response'] )
			? sanitize_text_field( $posted['cf-turnstile-response'] )
			: '';

		if ( '' === $token && ! empty( $_POST['cf-turnstile-response'] ) && ! is_array( $_POST['cf-turnstile-response'] ) ) {
			$token = sanitize_text_field( $_POST['cf-turnstile-response'] );
		}

		return $token;
	}

	public static function forminator_check( $submit_errors, $form_id, $field_data_array ) {
		// Forminator may re-run this hook several times per submit (fields, file
		// upload, payment intents); only the first pass may verify, so a
		// single-use token is never spent more than once.
		static $verified = array();

		$token  = self::forminator_token( $field_data_array );
		$memo   = $form_id . '|' . $token;
		// ponytail: relies on Helo's 120s _verify cache + Cloudflare single-use;
		// add a consume-on-read transient only if replay beyond 120s ever shows.
		if ( '' !== $token && isset( $verified[ $memo ] ) ) {
			return $submit_errors;
		}

		$check = Helo_Turnstile::check( $token, 'form-forminator' );
		if ( $check['success'] ) {
			$verified[ $memo ] = true;
		} else {
			$submit_errors[]['submit'] = Helo_Turnstile::failed_message();
		}

		return $submit_errors;
	}

	/* ------------------------------------------------------------- Fluent */

	public static function wire_fluent() {
		if ( ! Helo_Settings::get( 'turnstile_fluent' ) || ! defined( 'FLUENTFORM_VERSION' ) ) {
			return;
		}

		if ( has_action( 'fluentform/render_item_submit_button' ) ) {
			add_action( 'fluentform/render_item_submit_button', array( __CLASS__, 'fluent_field' ), 10, 2 );
		} else {
			add_action( 'fluentform_render_item_submit_button', array( __CLASS__, 'fluent_field' ), 10, 2 );
		}
		add_action( 'fluentform/before_insert_submission', array( __CLASS__, 'fluent_check' ), 10, 3 );
	}

	public static function fluent_field( $item, $form ) {
		Helo_Turnstile::field( '.fluentform .ff-btn-submit', 'form-fluent', '-fl-' . $form->id );
	}

	public static function fluent_check( $insert_data, $data, $form ) {
		$token = isset( $data['cf-turnstile-response'] ) ? sanitize_text_field( $data['cf-turnstile-response'] ) : '';

		$check = Helo_Turnstile::check( $token, 'form-fluent' );
		if ( ! $check['success'] ) {
			wp_die( Helo_Turnstile::failed_message(), 'helo-smtp' );
		}
	}

	/* ----------------------------------------------------------- Formidable */

	public static function wire_formidable() {
		if ( ! Helo_Settings::get( 'turnstile_formidable' ) || ! defined( 'FRM_VERSION' ) ) {
			return;
		}

		add_action( 'frm_submit_button_html', array( __CLASS__, 'formidable_field' ), 10, 2 );
		add_action( 'frm_validate_entry', array( __CLASS__, 'formidable_check' ), 10, 2 );
	}

	public static function formidable_field( $button, $args ) {
		return self::field_markup( '.frm_button_submit', 'form-formidable', '-fmd-' . $args['form']->id ) . $button;
	}

	public static function formidable_check( $errors, $values ) {
		$check = Helo_Turnstile::check( '', 'form-formidable' );
		if ( ! $check['success'] ) {
			$errors['cfturnstile_error'] = Helo_Turnstile::failed_message();
		}

		return $errors;
	}

	/* -------------------------------------------------------------- Jetpack */

	public static function wire_jetpack() {
		if ( ! Helo_Settings::get( 'turnstile_jetpack' ) || ! defined( 'JETPACK__VERSION' ) ) {
			return;
		}

		add_filter( 'jetpack_contact_form_html', array( __CLASS__, 'jetpack_field' ), 10, 1 );
		add_filter( 'jetpack_contact_form_is_spam', array( __CLASS__, 'jetpack_check' ), 10, 1 );
	}

	public static function jetpack_field( $html ) {
		$markup = self::field_markup( '.wp-block-jetpack-contact-form button', 'form-jetpack', '-jetpack-' . wp_rand() );
		if ( '' === $markup ) {
			return $html;
		}

		// Splice before the submit button if we can find one, else before </form>.
		$pos = strpos( $html, 'type="submit"' );
		if ( false === $pos ) {
			$pos = strripos( $html, '</form>' );
		}
		if ( false !== $pos ) {
			$html = substr_replace( $html, $markup, $pos, 0 );
		}

		return $html;
	}

	public static function jetpack_check( $default ) {
		$check = Helo_Turnstile::check( '', 'form-jetpack' );
		if ( ! $check['success'] ) {
			return new WP_Error( 'captcha_failed', Helo_Turnstile::failed_message() );
		}

		return false;
	}

	/* -------------------------------------------------------------- Gravity */

	public static function wire_gravity() {
		if ( ! Helo_Settings::get( 'turnstile_gravity' ) || ! class_exists( 'GFAPI' ) ) {
			return;
		}

		add_action( 'gform_submit_button', array( __CLASS__, 'gravity_field' ), 10, 2 );
		add_filter( 'gform_validation', array( __CLASS__, 'gravity_check' ), 10, 4 );
	}

	public static function gravity_field( $button, $form ) {
		return self::field_markup( '.gform_button', 'form-gravity', '-gf-' . $form['id'] ) . $button;
	}

	/**
	 * True when a Gravity form has pagination (a field of type 'page'). Pure.
	 *
	 * @param array $form Gravity form array.
	 * @return bool
	 */
	public static function gravity_has_pages( $form ) {
		if ( ! is_array( $form ) || ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( is_array( $field ) && isset( $field['type'] ) && 'page' === $field['type'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gravity's multi-page forms POST between pages without a token. A page
	 * advance must be allowed, but a forged target page on a single-page form
	 * must still fail closed. Pure — tested.
	 *
	 * @param array  $form   Gravity form array.
	 * @param string $token  The posted turnstile response (or '').
	 * @param string $target The posted target page number (or '').
	 * @return bool True to skip the Turnstile check (legitimate page nav).
	 */
	public static function gravity_page_nav( $form, $token = '', $target = '' ) {
		if ( '' !== $token ) {
			return false; // Has a token: always verify.
		}
		if ( '' === $target || intval( $target ) === 0 ) {
			return false; // Not a page nav.
		}

		return self::gravity_has_pages( $form );
	}

	public static function gravity_check( $validation_result ) {
		$form  = $validation_result['form'];
		$id    = isset( $form['id'] ) ? (int) $form['id'] : 0;
		$token = ! empty( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( $_POST['cf-turnstile-response'] ) : '';
		$target = isset( $_POST[ 'gform_target_page_number_' . $id ] ) ? sanitize_text_field( $_POST[ 'gform_target_page_number_' . $id ] ) : '';

		if ( self::gravity_page_nav( $form, $token, $target ) ) {
			return $validation_result;
		}

		$check = Helo_Turnstile::check( '', 'form-gravity' );
		if ( ! $check['success'] ) {
			$validation_result['is_valid'] = false;
			add_filter( 'gform_validation_message_' . $id, array( __CLASS__, 'gravity_message' ), 10, 2 );
		}

		return $validation_result;
	}

	public static function gravity_message( $message, $form ) {
		return Helo_Turnstile::failed_message();
	}

	/* -------------------------------------------------------------- Kadence */

	public static function wire_kadence() {
		if ( ! Helo_Settings::get( 'turnstile_kadence' ) || ! defined( 'KADENCE_BLOCKS_VERSION' ) ) {
			return;
		}

		add_filter( 'render_block', array( __CLASS__, 'kadence_field' ), 10, 2 );
		add_action( 'kadence_blocks_form_verify_nonce', array( __CLASS__, 'kadence_check' ), 10, 1 );
	}

	public static function kadence_field( $block_content, $block ) {
		if ( is_admin() || empty( $block_content ) || ! is_array( $block ) || empty( $block['blockName'] ) ) {
			return $block_content;
		}
		if ( 'kadence/advanced-form' !== $block['blockName'] && 'kadence/form' !== $block['blockName'] ) {
			return $block_content;
		}
		if ( false !== strpos( $block_content, 'cf-turnstile' ) ) {
			return $block_content; // Already injected.
		}

		$markup = self::field_markup(
			'.kb-adv-form-submit-button, .kb-submit-field .kb-button, .kb-form-submit .kb-button',
			'form-kadence',
			'-kt-' . wp_rand()
		);
		if ( '' === $markup ) {
			return $block_content;
		}

		$pos = strripos( $block_content, '</form>' );
		if ( false !== $pos ) {
			$block_content = substr_replace( $block_content, $markup, $pos, 0 );
		}

		return $block_content;
	}

	public static function kadence_check( $nonce ) {
		$check = Helo_Turnstile::check( '', 'form-kadence' );
		if ( ! $check['success'] ) {
			wp_die( Helo_Turnstile::failed_message(), 'helo-smtp' );
		}

		return $nonce;
	}

	/* ------------------------------------------------------------ SureForms */

	public static function wire_sureforms() {
		if ( ! Helo_Settings::get( 'turnstile_sureforms' ) || ! defined( 'SRFM_VERSION' ) ) {
			return;
		}

		add_action( 'srfm_before_submit_button', array( __CLASS__, 'sureforms_field' ), 10, 1 );
		add_filter( 'srfm_additional_restriction_check', array( __CLASS__, 'sureforms_check' ), 10, 3 );
		add_filter( 'srfm_additional_restriction_message', array( __CLASS__, 'sureforms_message' ), 10, 3 );
	}

	public static function sureforms_field( $id ) {
		Helo_Turnstile::field( '.srfm-submit-container .srfm-submit-button', 'form-sureforms', '-sf-' . $id );
		echo '<div class="srfm-validation-error" id="captcha-error" style="display: none; margin-bottom: 20px;">'
			. esc_html( Helo_Turnstile::failed_message() ) . '</div>';
	}

	/** @var bool True when the current request's SureForms submission failed. */
	private static $sureforms_failed = false;

	public static function sureforms_check( $restricted, $form_id, $form_data ) {
		$token = isset( $form_data['cf-turnstile-response'] ) ? sanitize_text_field( $form_data['cf-turnstile-response'] ) : '';

		$check = Helo_Turnstile::check( $token, 'form-sureforms' );
		if ( ! $check['success'] ) {
			self::$sureforms_failed = true;
			return true; // Restricted (until the token verifies).
		}

		return $restricted;
	}

	public static function sureforms_message( $message, $form_id, $form_data ) {
		return self::$sureforms_failed ? Helo_Turnstile::failed_message() : $message;
	}

	/* ------------------------------------------------------------- Elementor */

	/** Gate: Elementor Pro (not the free builder alone). */
	protected static function elementor_active() {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}

	public static function wire_elementor() {
		if ( ! Helo_Settings::get( 'turnstile_elementor' ) || ! self::elementor_active() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'elementor_enqueue' ), 99 );
		add_action( 'elementor_pro/forms/validation', array( __CLASS__, 'elementor_check' ), 10, 2 );

		// Elementor 4 atomic-style forms post to their own admin-ajax action and do not
		// run elementor_pro/forms/validation; reject a missing/invalid token there too.
		add_action( 'wp_ajax_elementor_pro_atomic_forms_send_form', array( __CLASS__, 'elementor_atomic_check' ), 5 );
		add_action( 'wp_ajax_nopriv_elementor_pro_atomic_forms_send_form', array( __CLASS__, 'elementor_atomic_check' ), 5 );
	}

	public static function elementor_enqueue() {
		if ( ! Helo_Turnstile::enabled() ) {
			return;
		}

		// Ensure Cloudflare's API script is loaded so window.turnstile exists and
		// the render queue drains; the JS then injects into .elementor-form.
		Helo_Turnstile::require_api();

		wp_enqueue_script(
			'helo-elementor',
			HELO_URL . 'js/helo-elementor.js',
			array( 'jquery' ),
			HELO_VERSION,
			true
		);
		wp_localize_script(
			'helo-elementor',
			'heloElementorSettings',
			array(
				'sitekey'       => Helo_Settings::get( 'turnstile_site_key' ),
				'enabled'       => true,
				'theme'         => Helo_Settings::get( 'turnstile_theme' ),
				'appearance'    => Helo_Settings::get( 'turnstile_appearance' ),
				'position'      => 'before',
				'disableSubmit' => false,
			)
		);
	}

	public static function elementor_check( $record, $ajax_handler ) {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			$ajax_handler->add_error_message( Helo_Turnstile::failed_message() );
			$ajax_handler->is_success = false;
			return;
		}

		$check = Helo_Turnstile::check( '', 'form-elementor' );
		if ( ! $check['success'] ) {
			$ajax_handler->add_error_message( Helo_Turnstile::failed_message() );
			$ajax_handler->add_error( '', '' );
			$ajax_handler->is_success = false;
		}
	}

	public static function elementor_atomic_check() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			wp_send_json_error( array( 'message' => Helo_Turnstile::failed_message() ) );
		}

		$check = Helo_Turnstile::check( '', 'elementor-atomic-form' );
		if ( empty( $check['success'] ) ) {
			wp_send_json_error( array( 'message' => Helo_Turnstile::failed_message() ) );
		}
	}
}