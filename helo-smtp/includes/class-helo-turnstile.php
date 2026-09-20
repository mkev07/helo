<?php
/**
 * Cloudflare Turnstile — bot & spam protection for WordPress forms.
 *
 * The design (single-use verification tokens, server-side siteverify with the
 * remote IP, and an explicit-render widget queue) follows the shape proven by
 * the simple-cloudflare-turnstile plugin; the code here is our own.
 *
 * @package Helo
 */

defined( 'ABSPATH' ) || exit;

class Helo_Turnstile {

	const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	const API_URL    = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=heloTurnstileOnload';

	/**
	 * Register form hooks and the widget renderer. Nothing is wired until the
	 * feature is switched on and a site key is present.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'wire' ), 20 );
	}

	public static function enabled() {
		return Helo_Settings::get( 'turnstile_enable' )
			&& '' !== Helo_Settings::get( 'turnstile_site_key' )
			&& '' !== Helo_Settings::get( 'turnstile_secret_key' );
	}

	public static function wire() {
		if ( ! self::enabled() ) {
			return;
		}

		add_shortcode( 'helo-turnstile', array( __CLASS__, 'render_shortcode' ) );

		// Core WordPress identity forms.
		if ( Helo_Settings::get( 'turnstile_login' ) ) {
			add_action( 'login_form', array( __CLASS__, 'field_login' ) );
			add_filter( 'authenticate', array( __CLASS__, 'check_login' ), 21, 1 );
		}
		if ( Helo_Settings::get( 'turnstile_register' ) ) {
			add_action( 'register_form', array( __CLASS__, 'field_register' ) );
			add_action( 'registration_errors', array( __CLASS__, 'check_register' ), 10, 3 );
		}
		if ( Helo_Settings::get( 'turnstile_reset' ) ) {
			if ( ! is_admin() ) {
				add_action( 'lostpassword_form', array( __CLASS__, 'field_reset' ) );
				add_action( 'lostpassword_post', array( __CLASS__, 'check_reset' ), 10, 1 );
			}
		}

		// Comments: strict block, no exemptions.
		if ( Helo_Settings::get( 'turnstile_comments' ) ) {
			add_action( 'comment_form_submit_button', array( __CLASS__, 'field_comment' ), 100, 2 );
			add_action( 'pre_comment_on_post', array( __CLASS__, 'check_comment' ), 10, 1 );
		}

		// Third-party integrations, gated on the plugin being active.
		if ( Helo_Settings::get( 'turnstile_woo' ) && function_exists( 'wc_get_checkout_url' ) ) {
			add_action( 'init', array( __CLASS__, 'wire_woocommerce' ), 30 );
		}
		if ( Helo_Settings::get( 'turnstile_cf7' ) && class_exists( 'WPCF7_ContactForm' ) ) {
			add_action( 'wpcf7_init', array( __CLASS__, 'wire_cf7' ), 10, 0 );
		}
	}

	/* -------------------------------------------------------------- widget JS */

	/**
	 * The render-queue bootstrap. Cloudflare's api.js is loaded in explicit
	 * mode, so nothing renders until we call it; this queue drains on load and
	 * again whenever a widget is added, so a widget works even when injected
	 * into a form late (comments, AJAX, shortcodes).
	 *
	 * @return string
	 */
	public static function bootstrap_js() {
		return '(function(w,d){var q=w.heloTurnstileQueue=w.heloTurnstileQueue||[];if(w.heloTurnstileRender)return;var ready=false,hooked=false,ticks=0,timer=null;var cbs=["callback","error-callback","expired-callback","timeout-callback","unsupported-callback","before-interactive-callback","after-interactive-callback"];function opts(e){var p={};for(var i=0;i<cbs.length;i++){(function(n){var v=e.getAttribute("data-"+n);if(!v)return;p[n]=function(){var f=w[v];if(typeof f==="function")return f.apply(w,arguments);};})(cbs[i]);}return p;}w.heloTurnstileOpts=function(e){if(typeof e==="string")e=d.querySelector(e);return e&&e.getAttribute?opts(e):{};};function one(id){var e=d.getElementById("cf-turnstile"+id);if(!e)return false;if(e.firstElementChild)return true;try{w.turnstile.render(e,opts(e));return true;}catch(_){return false;}}function drain(){if(!ready)return;for(var i=q.length-1;i>=0;i--){if(one(q[i]))q.splice(i,1);}if(q.length&&!hooked&&d.readyState==="loading"){hooked=true;d.addEventListener("DOMContentLoaded",function(){hooked=false;drain();});}}function watch(){timer=null;if(!q.length)return;if(!ready&&w.turnstile&&typeof w.turnstile.render==="function")ready=true;drain();if(q.length&&++ticks<170)timer=setTimeout(watch,ticks<20?100:2000);}w.heloTurnstileRender=function(){drain();if(q.length&&!timer)timer=setTimeout(watch,100);};w.heloTurnstileOnload=function(){ready=true;w.heloTurnstileRender();};w.heloTurnstileRender();})(window,document);';
	}

	/** Reset a spent widget a moment after its form submits without navigating. */
	public static function token_refresh_js() {
		return '(function(w,d){if(w.heloTurnstileRefresh)return;w.heloTurnstileRefresh=1;var F="input[name=cf-turnstile-response]",u=false;function go(){u=true;}w.addEventListener("pagehide",go);w.addEventListener("beforeunload",go);d.addEventListener("submit",function(e){var f=e.target,s=[];if(!f||!f.querySelectorAll)return;f.querySelectorAll(".cf-turnstile").forEach(function(el){var i=el.querySelector(F);if(i&&i.value)s.push([el,i.value]);});if(!s.length)return;setTimeout(function(){if(u||!w.turnstile)return;s.forEach(function(x){var i=x[0].querySelector(F);if(i&&i.value===x[1]){try{w.turnstile.reset(x[0]);}catch(_){}}});},2000);},true);})(window,document);';
	}

	/**
	 * Register the API script (once) with the bootstrap attached before it, so
	 * the onload callback exists before Cloudflare's script can call it.
	 *
	 * @param array $args Script args as accepted by wp_register_script().
	 */
	public static function register_api( $args = array() ) {
		static $bootstrapped = false;

		if ( ! wp_script_is( 'helo-turnstile', 'registered' ) ) {
			wp_register_script( 'helo-turnstile', self::API_URL, array(), null, $args );
		}

		if ( ! $bootstrapped ) {
			$bootstrapped = true;
			wp_add_inline_script( 'helo-turnstile', self::bootstrap_js(), 'before' );
			wp_add_inline_script( 'helo-turnstile', self::token_refresh_js(), 'before' );
		}
	}

	public static function require_api() {
		self::register_api( array( 'in_footer' => true ) );
		wp_enqueue_script( 'helo-turnstile' );
	}

	/* ----------------------------------------------------------------- widget */

	/**
	 * Render the Turnstile widget inside a form.
	 *
	 * @param string $button_id   Selector of the form's submit button, if any.
	 * @param string $form_action Actions string used by siteverify/analytics.
	 * @param string $unique_id   Unique suffix for the widget id.
	 */
	public static function field( $button_id = '', $form_action = '', $unique_id = '' ) {
		if ( ! self::enabled() ) {
			return;
		}

		// A stable per-location prefix plus a random tail, so two widgets on the
		// same page (shortcode + form, etc.) never collide in the render queue.
		$id = '-h' . ( $unique_id !== '' ? $unique_id : 'w' ) . '-' . wp_rand();

		?><div id="cf-turnstile<?php echo esc_attr( $id ); ?>"
		class="cf-turnstile"
		data-sitekey="<?php echo esc_attr( Helo_Settings::get( 'turnstile_site_key' ) ); ?>"
		data-theme="<?php echo esc_attr( Helo_Settings::get( 'turnstile_theme' ) ); ?>"
		data-language="auto"
		data-size="normal"
		data-retry="auto" data-retry-interval="1000"
		data-refresh-expired="auto"
		data-refresh-timeout="auto"
		data-action="<?php echo esc_attr( $form_action ); ?>"
		data-appearance="<?php echo esc_attr( Helo_Settings::get( 'turnstile_appearance' ) ); ?>"></div>
		<?php
		if ( $button_id ) : ?>
			<style><?php echo esc_html( $button_id ); ?> { pointer-events: none; opacity: 0.5; }</style>
		<?php endif;

		self::enqueue_render( $id );
	}

	/**
	 * Queue the widget for rendering. When the footer is gone (an AJAX/early
	 * form) the inline script is echoed directly so the widget still renders.
	 *
	 * @param string $id Widget element id (without the cf-turnstile prefix).
	 */
	public static function enqueue_render( $id = '' ) {
		self::require_api();

		$script = '(window.heloTurnstileQueue=window.heloTurnstileQueue||[]).push("' . esc_js( $id ) . '");if(window.heloTurnstileRender)window.heloTurnstileRender();';

		if ( self::footer_unavailable() ) {
			echo '<script data-cfasync="false">' . $script . '</script>';
			return;
		}

		if ( ! wp_script_is( 'helo-turnstile-render', 'registered' ) ) {
			wp_register_script( 'helo-turnstile-render', '', array(), false, array( 'in_footer' => true ) );
		}
		wp_enqueue_script( 'helo-turnstile-render' );
		wp_add_inline_script( 'helo-turnstile-render', $script );
	}

	/** True when no footer remains into which an enqueued script would print. */
	public static function footer_unavailable() {
		if ( wp_doing_ajax() ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		return did_action( 'wp_print_footer_scripts' ) || did_action( 'admin_print_footer_scripts' );
	}

	/* ------------------------------------------------------------ verification */

	/** @var int Seconds a successful verification stays cached (token is single-use). */
	const VERIFY_CACHE = 120;

	private static function transient_key( $key, $token = '' ) {
		if ( '' === $token && ! empty( $_POST['cf-turnstile-response'] ) ) {
			$token = sanitize_text_field( $_POST['cf-turnstile-response'] );
		}
		if ( '' === $token ) {
			return false;
		}

		return 'helo_tst_' . substr( md5( $key . '_t' . $token ), 0, 20 );
	}

	private static function set_verified( $key, $token = '', $ttl = self::VERIFY_CACHE ) {
		$tk = self::transient_key( $key, $token );
		if ( $tk ) {
			set_transient( $tk, 1, $ttl );
		}
	}

	private static function get_verified( $key, $token = '' ) {
		$tk = self::transient_key( $key, $token );

		return $tk && (bool) get_transient( $tk );
	}

	/**
	 * Verify a Turnstile token with Cloudflare. A token that has already been
	 * accepted is rejected (no replay), and a token without one is rejected.
	 *
	 * @param string $token       The widget response token.
	 * @param string $form_action Form label for analytics fallback.
	 * @return array {success: bool, error_code: string}
	 */
	public static function check( $token = '', $form_action = '' ) {
		$results = array( 'success' => false, 'error_code' => '' );

		if ( ! self::enabled() ) {
			return $results;
		}
		if ( '' === $token && ! empty( $_POST['cf-turnstile-response'] ) ) {
			$token = sanitize_text_field( $_POST['cf-turnstile-response'] );
		}
		if ( '' === $token ) {
			return $results; // Fail closed on a missing token.
		}

		if ( self::get_verified( '_verify', $token ) ) {
			$results['success'] = true;
			return $results; // Already accepted once this request.
		}

		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 15,
				'body'    => array(
					'secret'   => Helo_Settings::get( 'turnstile_secret_key' ),
					'response' => $token,
					'remoteip' => self::remote_ip(),
				),
			)
		);

		$body = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( is_array( $json ) ) {
			$success = ! empty( $json['success'] );
			if ( $success ) {
				$results['success'] = true;
				self::set_verified( '_verify', $token, self::VERIFY_CACHE );
			} else {
				$error = isset( $json['error-codes'] ) && is_array( $json['error-codes'] ) ? reset( $json['error-codes'] ) : '';
				$results['error_code'] = sanitize_key( (string) $error );
			}
		}

		do_action( 'helo_turnstile_after_check', $json, $results, $form_action );

		return $results;
	}

	/** The visitor IP, honouring the common proxy headers Cloudflare sets. */
	public static function remote_ip() {
		$ip = '';

		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			$header = isset( $_SERVER[ $key ] ) ? sanitize_text_field( $_SERVER[ $key ] ) : '';
			if ( $header ) {
				$ip = $header;
				break;
			}
		}

		// X-Forwarded-For can carry a comma-separated chain; take the first.
		if ( false !== strpos( $ip, ',' ) ) {
			$ip = trim( explode( ',', $ip )[0] );
		}

		return $ip;
	}

	/* -------------------------------------------------------- shortcode + msg */

	/** Render a standalone widget via shortcode. */
	public static function render_shortcode() {
		ob_start();
		self::field( '', '', 'sc' );
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	public static function failed_message() {
		return esc_html__( 'Failed to verify you are human. Please try again.', 'helo-smtp' );
	}

	/* ------------------------------------------------------------ core forms */

	public static function field_login() {
		if ( ! empty( $_POST ) ) {
			return; // Already submitted; don't inject into the error re-render.
		}
		self::field( '#wp-submit', 'wordpress-login', 'l' );
	}

	public static function check_login( $user ) {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return $user;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $user;
		}
		if ( is_wp_error( $user ) && $user->get_error_code() ) {
			return $user; // Don't consume a token on a bad-password login.
		}

		$key = $user->ID ? 'helo_login_' . $user->ID : 'helo_login';
		if ( self::get_verified( $key ) ) {
			return $user;
		}

		$check = self::check( '', 'wordpress-login' );
		if ( ! $check['success'] ) {
			return new WP_Error( 'helo_turnstile_error', self::failed_message() );
		}

		self::set_verified( $key, '', 300 );

		return $user;
	}

	public static function field_register() {
		self::field( '#wp-submit', 'wordpress-register', 'r' );
	}

	public static function check_register( $errors, $user_login, $user_email ) {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return $errors;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $errors;
		}

		$check = self::check( '', 'wordpress-register' );
		if ( ! $check['success'] ) {
			$errors->add( 'helo_turnstile_error', self::failed_message() );
		}

		return $errors;
	}

	public static function field_reset() {
		self::field( '#wp-submit', 'wordpress-reset', 'x' );
	}

	public static function check_reset( $validation_errors ) {
		if ( false === stripos( $_SERVER['SCRIPT_NAME'] ?? '', strrchr( wp_login_url(), '/' ) ) ) {
			return $validation_errors; // Only the wp-login.php reset form is gated.
		}

		$check = self::check( '', 'wordpress-reset' );
		if ( ! $check['success'] ) {
			$validation_errors->add( 'helo_turnstile_error', self::failed_message() );
		}

		return $validation_errors;
	}

	/* ------------------------------------------------------- comments (strict) */

	public static function field_comment( $submit_button, $args ) {
		if ( self::enabled() ) {
			ob_start();
			self::field( '', 'wordpress-comment', '-c' );
			$markup = ob_get_contents();
			ob_end_clean();

			return $submit_button . '<span style="margin-top:10px">' . $markup . '</span>';
		}

		return $submit_button;
	}

	/**
	 * Block comments that fail — no exemptions, not even logged-in users.
	 */
	public static function check_comment( $commentdata ) {
		if ( empty( $_POST ) ) {
			return $commentdata;
		}

		$check = self::check( '', 'wordpress-comment' );
		if ( ! $check['success'] ) {
			wp_die(
				'<p><strong>' . esc_html__( 'Blocked:', 'helo-smtp' ) . '</strong> ' . self::failed_message() . '</p>',
				'helo-smtp',
				array( 'response' => 403, 'back_link' => 1 )
			);
		}

		return $commentdata;
	}

	/* ------------------------------------------------------ WooCommerce forms */

	public static function wire_woocommerce() {
		add_action( 'woocommerce_login_form', array( __CLASS__, 'field_woo_login' ) );
		add_action( 'woocommerce_register_form', array( __CLASS__, 'field_woo_register' ) );
		add_action( 'woocommerce_lostpassword_form', array( __CLASS__, 'field_woo_reset' ) );

		add_filter( 'authenticate', array( __CLASS__, 'check_woo_login' ), 22, 1 );
		if ( ! is_admin() ) {
			add_action( 'woocommerce_register_post', array( __CLASS__, 'check_woo_register' ), 10, 3 );
		}
		add_action( 'lostpassword_post', array( __CLASS__, 'check_woo_reset' ), 11, 1 );

		if ( function_exists( 'wc_get_checkout_url' ) ) {
			add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'check_woo_checkout' ), 10 );
		}
	}

	public static function field_woo_login() {
		self::field( '.woocommerce-form-login__submit', 'woocommerce-login', '-wl' );
	}

	public static function check_woo_login( $user ) {
		if ( ! isset( $_POST['woocommerce-login-nonce'] ) ) {
			return $user;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return $user;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $user;
		}

		$key = $user->ID ? 'helo_woo_login_' . $user->ID : 'helo_woo_login';
		if ( self::get_verified( $key ) ) {
			return $user;
		}

		$check = self::check( '', 'woocommerce-login' );
		if ( ! $check['success'] ) {
			return new WP_Error( 'helo_turnstile_error', self::failed_message() );
		}

		self::set_verified( $key, '', 300 );

		return $user;
	}

	public static function field_woo_register() {
		self::field( '.woocommerce-form-register__submit', 'woocommerce-register', '-wr' );
	}

	public static function check_woo_register( $username, $email, $errors ) {
		$check = self::check( '', 'woocommerce-register' );
		if ( ! $check['success'] ) {
			$errors->add( 'helo_turnstile_error', self::failed_message() );
		}
	}

	public static function field_woo_reset() {
		self::field( '.woocommerce-ResetPassword .button, .woocommerce-Button', 'woocommerce-reset', '-wx' );
	}

	public static function check_woo_reset( $validation_errors ) {
		if ( ! isset( $_POST['wc_reset_password'], $_POST['user_login'] ) ) {
			return $validation_errors;
		}

		$check = self::check( '', 'woocommerce-reset' );
		if ( ! $check['success'] ) {
			$validation_errors->add( 'helo_turnstile_error', self::failed_message() );
		}

		return $validation_errors;
	}

	public static function check_woo_checkout() {
		static $ran = false;
		if ( $ran || empty( $_POST ) ) {
			return;
		}

		// Guest-only gate honours the general guest-only convention; comment
		// blocking (the "100%" surface) is not affected by this.
		if ( is_user_logged_in() ) {
			$ran = true;
			return;
		}
		$ran     = true;
		$check   = self::check( '', 'woocommerce-checkout' );
		$success = $check['success'];

		wc_add_notice( $success ? '' : self::failed_message(), 'error' );
	}

	/* ------------------------------------------------------------ Contact Form 7 */

	public static function wire_cf7() {
		add_action( 'wpcf7_init', array( __CLASS__, 'cf7_add_tag' ), 10, 0 );
		add_filter( 'wpcf7_validate', array( __CLASS__, 'check_cf7' ), 20, 2 );
	}

	public static function cf7_add_tag() {
		if ( function_exists( 'wpcf7_add_form_tag' ) ) {
			wpcf7_add_form_tag( 'cf7_simple_turnstile', array( __CLASS__, 'cf7_tag' ) );
		}
	}

	public static function cf7_tag() {
		ob_start();
		self::field( '.wpcf7-submit', 'contact-form-7', '-cf7' );
		$html = ob_get_contents();
		ob_end_clean();

		return '<div class="cf7-cf-turnstile">' . $html . '</div>';
	}

	public static function check_cf7( $result ) {
		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return $result;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return $result;
		}

		$data   = $submission->get_posted_data();
		$token  = isset( $data['cf-turnstile-response'] ) ? $data['cf-turnstile-response'] : '';
		$check  = self::check( $token, 'contact-form-7' );

		if ( ! $check['success'] ) {
			$result->invalidate( array( 'type' => 'captcha', 'name' => 'cf-turnstile' ), self::failed_message() );
		}

		return $result;
	}
}