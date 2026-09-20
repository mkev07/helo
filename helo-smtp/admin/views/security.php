<?php
/**
 * Bot protection screen: Cloudflare Turnstile keys, which forms, analytics toggles.
 *
 * @package Helo
 * @var array $settings Resolved plugin settings.
 */

defined( 'ABSPATH' ) || exit;
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="helo_save">
	<input type="hidden" name="return_slug" value="<?php echo esc_attr( Helo_Admin::SUB_SECURITY ); ?>">
	<?php wp_nonce_field( 'helo_save' ); ?>

<div class="helo-card">
	<div class="helo-card__head">
		<h2><?php esc_html_e( 'Cloudflare Turnstile', 'helo-smtp' ); ?></h2>
		<p>
			<?php esc_html_e( 'A privacy-friendly CAPTCHA that keeps the bot check on the visitor’s device, so no visitor cookies or data reach your server. Get a free site key and secret at', 'helo-smtp' ); ?>
			<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">dash.cloudflare.com</a>.
		</p>
	</div>

	<div class="helo-field">
		<span class="helo-field__label"><?php esc_html_e( 'Enable Turnstile', 'helo-smtp' ); ?></span>
		<div class="helo-field__control">
			<label class="helo-switch">
				<input name="turnstile_enable" type="checkbox" value="1" <?php checked( $settings['turnstile_enable'] ); ?>>
				<span><?php esc_html_e( 'Turn on bot protection through Cloudflare Turnstile', 'helo-smtp' ); ?></span>
			</label>
		</div>
	</div>

	<div class="helo-field">
		<label class="helo-field__label" for="helo-turnstile-site-key"><?php esc_html_e( 'Site key', 'helo-smtp' ); ?></label>
		<div class="helo-field__control">
			<input name="turnstile_site_key" id="helo-turnstile-site-key" type="text" value="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>">
			<p class="helo-hint"><?php esc_html_e( 'The public key, shown to visitors and safe to embed in your page.', 'helo-smtp' ); ?></p>
		</div>
	</div>

	<div class="helo-field">
		<label class="helo-field__label" for="helo-turnstile-secret-key"><?php esc_html_e( 'Secret key', 'helo-smtp' ); ?></label>
		<div class="helo-field__control">
			<input name="turnstile_secret_key" id="helo-turnstile-secret-key" type="password" autocomplete="new-password" value="<?php echo esc_attr( $settings['turnstile_secret_key'] ); ?>">
			<p class="helo-hint"><?php esc_html_e( 'Sent only from your server to Cloudflare to verify a token. Never shown to visitors.', 'helo-smtp' ); ?></p>
		</div>
	</div>

	<div class="helo-field">
		<span class="helo-field__label"><?php esc_html_e( 'Appearance', 'helo-smtp' ); ?></span>
		<div class="helo-field__control">
			<div class="helo-row">
				<select name="turnstile_theme" style="max-width:200px">
					<option value="auto" <?php selected( $settings['turnstile_theme'], 'auto' ); ?>><?php esc_html_e( 'Auto theme', 'helo-smtp' ); ?></option>
					<option value="light" <?php selected( $settings['turnstile_theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'helo-smtp' ); ?></option>
					<option value="dark" <?php selected( $settings['turnstile_theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'helo-smtp' ); ?></option>
				</select>
				<select name="turnstile_appearance" style="max-width:260px">
					<option value="always" <?php selected( $settings['turnstile_appearance'], 'always' ); ?>><?php esc_html_e( 'Always visible', 'helo-smtp' ); ?></option>
					<option value="interaction-only" <?php selected( $settings['turnstile_appearance'], 'interaction-only' ); ?>><?php esc_html_e( 'Shown only when needed', 'helo-smtp' ); ?></option>
				</select>
			</div>
		</div>
	</div>

	<div class="helo-card__foot">
		<p>
			<strong><?php esc_html_e( 'Comments are blocked with no exceptions', 'helo-smtp' ); ?></strong>
			<?php esc_html_e( 'when the feature is on and keys are set — no logged-in or role exemptions.', 'helo-smtp' ); ?>
		</p>
	</div>
</div>

<div class="helo-card">
		<div class="helo-card__head">
			<h2><?php esc_html_e( 'Protect these forms', 'helo-smtp' ); ?></h2>
			<p><?php esc_html_e( 'Which forms require a passing Turnstile check before they submit.', 'helo-smtp' ); ?></p>
		</div>

		<div class="helo-field">
			<span class="helo-field__label"><?php esc_html_e( 'Forms', 'helo-smtp' ); ?></span>
			<div class="helo-field__control helo-form-protection">
				<label class="helo-switch"><input type="checkbox" name="turnstile_login" value="1" <?php checked( $settings['turnstile_login'] ); ?>><span><?php esc_html_e( 'Login', 'helo-smtp' ); ?></span></label>
				<label class="helo-switch"><input type="checkbox" name="turnstile_register" value="1" <?php checked( $settings['turnstile_register'] ); ?>><span><?php esc_html_e( 'Registration', 'helo-smtp' ); ?></span></label>
				<label class="helo-switch"><input type="checkbox" name="turnstile_reset" value="1" <?php checked( $settings['turnstile_reset'] ); ?>><span><?php esc_html_e( 'Lost password', 'helo-smtp' ); ?></span></label>
				<label class="helo-switch"><input type="checkbox" name="turnstile_comments" value="1" <?php checked( $settings['turnstile_comments'] ); ?>><span><?php esc_html_e( 'Comments', 'helo-smtp' ); ?></span></label>
				<label class="helo-switch"><input type="checkbox" name="turnstile_woo" value="1" <?php checked( $settings['turnstile_woo'] ); ?>><span><?php esc_html_e( 'WooCommerce', 'helo-smtp' ); ?></span></label>
				<label class="helo-switch"><input type="checkbox" name="turnstile_cf7" value="1" <?php checked( $settings['turnstile_cf7'] ); ?>><span><?php esc_html_e( 'Contact Form 7', 'helo-smtp' ); ?></span></label>
			</div>
		</div>

		<div class="helo-card__foot">
			<?php submit_button( __( 'Save protection settings', 'helo-smtp' ), 'primary', 'submit', false ); ?>
		</div>
	</div>

	<div class="helo-card">
		<div class="helo-card__head">
			<h2><?php esc_html_e( 'Security analytics', 'helo-smtp' ); ?></h2>
			<p><?php esc_html_e( 'What the Security log records (shown on the Security log screen).', 'helo-smtp' ); ?></p>
		</div>

		<div class="helo-field">
			<span class="helo-field__label"><?php esc_html_e( 'Counters', 'helo-smtp' ); ?></span>
			<div class="helo-field__control">
				<label class="helo-switch">
					<input name="turnstile_analytics" type="checkbox" value="1" <?php checked( $settings['turnstile_analytics'] ); ?>>
					<span><?php esc_html_e( 'Record verified / blocked totals (no IPs or page URLs stored)', 'helo-smtp' ); ?></span>
				</label>
			</div>
		</div>

		<div class="helo-field">
			<span class="helo-field__label"><?php esc_html_e( 'Debug log', 'helo-smtp' ); ?></span>
			<div class="helo-field__control">
				<label class="helo-switch">
					<input name="turnstile_debug_log" type="checkbox" value="1" <?php checked( $settings['turnstile_debug_log'] ); ?>>
					<span><?php esc_html_e( 'Log each check with IP and page URL (last 50) — for troubleshooting', 'helo-smtp' ); ?></span>
				</label>
				<p class="helo-hint"><?php esc_html_e( 'Stores identifying data. Leave off unless you are diagnosing a problem.', 'helo-smtp' ); ?></p>
			</div>
		</div>

		<div class="helo-card__foot">
			<p><?php esc_html_e( 'Save once to apply the keys, the forms they protect, and the analytics settings.', 'helo-smtp' ); ?></p>
			<?php submit_button( __( 'Save bot protection', 'helo-smtp' ), 'primary', 'submit', false ); ?>
		</div>
	</div>
</form>

<div class="helo-card helo-quicklinks">
	<div class="helo-card__head">
		<h2><?php esc_html_e( 'Helpful links', 'helo-smtp' ); ?></h2>
		<p><?php esc_html_e( 'Jump straight to where the action happens.', 'helo-smtp' ); ?></p>
	</div>

	<div class="helo-row">
		<a class="button" href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create Cloudflare keys ↗', 'helo-smtp' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>"><?php esc_html_e( 'WordPress general settings', 'helo-smtp' ); ?></a>
	</div>
</div>