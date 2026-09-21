<?php
/**
 * Bot protection: Turnstile keys, what they guard, and how they behave.
 *
 * @package Helo
 * @var array $settings     Resolved plugin settings.
 * @var array $integrations Registry rows grouped for display.
 * @var array $summary      {on:int,total:int,missing:int}
 */

defined( 'ABSPATH' ) || exit;

$helo_locked = Helo_Turnstile::keys_locked();
$helo_has_keys = '' !== Helo_Turnstile::site_key() && '' !== Helo_Turnstile::secret_key();
$helo_live   = Helo_Turnstile::enabled();
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="helo-stack">
	<input type="hidden" name="action" value="helo_save">
	<input type="hidden" name="return_slug" value="<?php echo esc_attr( Helo_Admin::SUB_SECURITY ); ?>">
	<?php wp_nonce_field( 'helo_save' ); ?>

	<?php if ( $settings['turnstile_enable'] && ! $helo_has_keys ) : ?>
		<div class="helo-alert helo-alert--warn">
			<span class="helo-alert__icon" aria-hidden="true">&#9888;</span>
			<div>
				<strong><?php esc_html_e( 'Turnstile is switched on but has no keys.', 'helo-smtp' ); ?></strong>
				<?php esc_html_e( 'Nothing is being checked until both a site key and a secret key are saved.', 'helo-smtp' ); ?>
			</div>
		</div>
	<?php elseif ( $helo_live && 0 === $summary['on'] ) : ?>
		<div class="helo-alert helo-alert--warn">
			<span class="helo-alert__icon" aria-hidden="true">&#9888;</span>
			<div>
				<strong><?php esc_html_e( 'No forms are protected yet.', 'helo-smtp' ); ?></strong>
				<?php esc_html_e( 'The keys are valid, but nothing below is switched on, so Turnstile never runs.', 'helo-smtp' ); ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- ------------------------------------------------------------- keys -->
	<div class="helo-card">
		<div class="helo-card__head">
			<div>
				<h2><?php esc_html_e( 'Cloudflare Turnstile', 'helo-smtp' ); ?></h2>
				<p>
					<?php esc_html_e( 'A CAPTCHA that keeps the bot check on the visitor’s device, so no visitor cookies or data reach your server. Keys are free from', 'helo-smtp' ); ?>
					<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">dash.cloudflare.com</a>.
				</p>
			</div>
			<?php if ( $helo_live ) : ?>
				<span class="helo-badge helo-badge--ok"><?php esc_html_e( 'Active', 'helo-smtp' ); ?></span>
			<?php else : ?>
				<span class="helo-badge helo-badge--muted"><?php esc_html_e( 'Inactive', 'helo-smtp' ); ?></span>
			<?php endif; ?>
		</div>

		<div class="helo-field">
			<span class="helo-field__label"><?php esc_html_e( 'Protection', 'helo-smtp' ); ?></span>
			<div class="helo-field__control">
				<label class="helo-switch">
					<input name="turnstile_enable" type="checkbox" value="1" <?php checked( $settings['turnstile_enable'] ); ?>>
					<span class="helo-switch__text">
						<strong><?php esc_html_e( 'Run Turnstile on the forms selected below', 'helo-smtp' ); ?></strong>
						<small><?php esc_html_e( 'The master switch. Off means no widget is rendered and no token is checked, whatever else is set.', 'helo-smtp' ); ?></small>
					</span>
				</label>
			</div>
		</div>

		<div class="helo-field">
			<label class="helo-field__label" for="helo-site-key"><?php esc_html_e( 'Site key', 'helo-smtp' ); ?></label>
			<div class="helo-field__control">
				<?php if ( defined( 'HELO_TURNSTILE_SITE_KEY' ) && HELO_TURNSTILE_SITE_KEY ) : ?>
					<span class="helo-badge helo-badge--ok"><?php esc_html_e( 'Set in wp-config.php', 'helo-smtp' ); ?></span>
				<?php else : ?>
					<input name="turnstile_site_key" id="helo-site-key" type="text" class="helo-input--mono"
						value="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>" placeholder="0x4AAAAAAA…">
					<p class="helo-hint"><?php esc_html_e( 'Public. It is embedded in your pages, so it is not a secret.', 'helo-smtp' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="helo-field">
			<label class="helo-field__label" for="helo-secret-key"><?php esc_html_e( 'Secret key', 'helo-smtp' ); ?></label>
			<div class="helo-field__control">
				<?php if ( defined( 'HELO_TURNSTILE_SECRET_KEY' ) && HELO_TURNSTILE_SECRET_KEY ) : ?>
					<span class="helo-badge helo-badge--ok"><?php esc_html_e( 'Set in wp-config.php', 'helo-smtp' ); ?></span>
				<?php else : ?>
					<input name="turnstile_secret_key" id="helo-secret-key" type="password" autocomplete="new-password" class="helo-input--mono"
						value="<?php echo esc_attr( $settings['turnstile_secret_key'] ); ?>">
					<p class="helo-hint">
						<?php esc_html_e( 'Sent only from your server to Cloudflare. Keep it out of the database entirely with', 'helo-smtp' ); ?>
						<code>define( 'HELO_TURNSTILE_SECRET_KEY', '…' );</code>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $helo_locked ) : ?>
			<div class="helo-field">
				<span class="helo-field__label"><?php esc_html_e( 'Note', 'helo-smtp' ); ?></span>
				<div class="helo-field__control">
					<div class="helo-alert helo-alert--info">
						<span class="helo-alert__icon" aria-hidden="true">&#9432;</span>
						<div><?php esc_html_e( 'A constant in wp-config.php wins over anything stored here, so staging and production can hold different keys from the same database.', 'helo-smtp' ); ?></div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- ------------------------------------------------------- what to guard -->
	<div class="helo-card">
		<div class="helo-card__head">
			<div>
				<h2><?php esc_html_e( 'Protected forms', 'helo-smtp' ); ?></h2>
				<p><?php esc_html_e( 'Each one requires a passing check before it will submit. Anything not installed is shown dimmed — you can still switch it on ahead of installing the plugin.', 'helo-smtp' ); ?></p>
			</div>
			<span class="helo-badge helo-badge--accent helo-badge--bare">
				<?php
				printf(
					/* translators: 1: number switched on, 2: number available. */
					esc_html__( '%1$s of %2$s on', 'helo-smtp' ),
					esc_html( number_format_i18n( $summary['on'] ) ),
					esc_html( number_format_i18n( $summary['total'] ) )
				);
				?>
			</span>
		</div>

		<?php foreach ( $integrations as $helo_group ) : ?>
			<?php if ( empty( $helo_group['items'] ) ) { continue; } ?>
			<div class="helo-group">
				<div class="helo-group__head">
					<h3><?php echo esc_html( $helo_group['label'] ); ?></h3>
					<p><?php echo esc_html( $helo_group['blurb'] ); ?></p>
				</div>

				<div class="helo-integrations">
					<?php foreach ( $helo_group['items'] as $helo_item ) : ?>
						<label class="helo-int<?php echo $helo_item['detected'] ? '' : ' helo-int--absent'; ?>">
							<span class="helo-switch">
								<input type="checkbox" name="<?php echo esc_attr( $helo_item['setting'] ); ?>" value="1" <?php checked( $helo_item['on'] ); ?>>
							</span>
							<span class="helo-int__body">
								<span class="helo-int__name">
									<?php echo esc_html( $helo_item['label'] ); ?>
									<?php if ( ! empty( $helo_item['strict'] ) ) : ?>
										<span class="helo-tag helo-tag--on"><?php esc_html_e( 'Strict', 'helo-smtp' ); ?></span>
									<?php elseif ( ! $helo_item['detected'] ) : ?>
										<span class="helo-tag helo-tag--off"><?php esc_html_e( 'Not installed', 'helo-smtp' ); ?></span>
									<?php endif; ?>
								</span>
								<?php if ( ! empty( $helo_item['note'] ) ) : ?>
									<span class="helo-int__note"><?php echo esc_html( $helo_item['note'] ); ?></span>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<?php if ( $summary['missing'] ) : ?>
			<div class="helo-card__foot">
				<p>
					<?php
					printf(
						/* translators: %s: number of integrations. */
						esc_html( _n( '%s form is switched on but its plugin is not installed — harmless, it simply never runs.', '%s forms are switched on but their plugins are not installed — harmless, they simply never run.', $summary['missing'], 'helo-smtp' ) ),
						esc_html( number_format_i18n( $summary['missing'] ) )
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	</div>

	<div class="helo-grid helo-grid--2">
		<!-- ---------------------------------------------------- appearance -->
		<div class="helo-card">
			<div class="helo-card__head">
				<div>
					<h2><?php esc_html_e( 'Widget', 'helo-smtp' ); ?></h2>
					<p><?php esc_html_e( 'How the challenge looks where it appears.', 'helo-smtp' ); ?></p>
				</div>
			</div>

			<div class="helo-field">
				<label class="helo-field__label" for="helo-ts-theme"><?php esc_html_e( 'Theme', 'helo-smtp' ); ?></label>
				<div class="helo-field__control">
					<div class="helo-row">
						<select name="turnstile_theme" id="helo-ts-theme" style="max-width:150px">
							<option value="auto" <?php selected( $settings['turnstile_theme'], 'auto' ); ?>><?php esc_html_e( 'Auto', 'helo-smtp' ); ?></option>
							<option value="light" <?php selected( $settings['turnstile_theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'helo-smtp' ); ?></option>
							<option value="dark" <?php selected( $settings['turnstile_theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'helo-smtp' ); ?></option>
						</select>
						<select name="turnstile_size" style="max-width:160px">
							<option value="normal" <?php selected( $settings['turnstile_size'], 'normal' ); ?>><?php esc_html_e( 'Normal size', 'helo-smtp' ); ?></option>
							<option value="flexible" <?php selected( $settings['turnstile_size'], 'flexible' ); ?>><?php esc_html_e( 'Flexible width', 'helo-smtp' ); ?></option>
							<option value="compact" <?php selected( $settings['turnstile_size'], 'compact' ); ?>><?php esc_html_e( 'Compact', 'helo-smtp' ); ?></option>
						</select>
					</div>
				</div>
			</div>

			<div class="helo-field">
				<label class="helo-field__label" for="helo-ts-appearance"><?php esc_html_e( 'Show it', 'helo-smtp' ); ?></label>
				<div class="helo-field__control">
					<select name="turnstile_appearance" id="helo-ts-appearance">
						<option value="always" <?php selected( $settings['turnstile_appearance'], 'always' ); ?>><?php esc_html_e( 'Always', 'helo-smtp' ); ?></option>
						<option value="interaction-only" <?php selected( $settings['turnstile_appearance'], 'interaction-only' ); ?>><?php esc_html_e( 'Only when a visitor must act', 'helo-smtp' ); ?></option>
					</select>
					<p class="helo-hint"><?php esc_html_e( 'Most visitors pass silently, so “only when needed” keeps forms clean at the cost of no visible reassurance.', 'helo-smtp' ); ?></p>
				</div>
			</div>

			<div class="helo-field">
				<label class="helo-field__label" for="helo-ts-language"><?php esc_html_e( 'Language', 'helo-smtp' ); ?></label>
				<div class="helo-field__control">
					<input name="turnstile_language" id="helo-ts-language" type="text" style="max-width:150px"
						value="<?php echo esc_attr( $settings['turnstile_language'] ); ?>" placeholder="auto">
					<p class="helo-hint"><?php esc_html_e( '“auto” follows the visitor’s browser. Otherwise a code such as en, fr or pt-BR.', 'helo-smtp' ); ?></p>
				</div>
			</div>

			<div class="helo-field">
				<label class="helo-field__label" for="helo-ts-label"><?php esc_html_e( 'Label', 'helo-smtp' ); ?></label>
				<div class="helo-field__control">
					<input name="turnstile_label" id="helo-ts-label" type="text"
						value="<?php echo esc_attr( $settings['turnstile_label'] ); ?>" placeholder="<?php esc_attr_e( 'No label', 'helo-smtp' ); ?>">
					<p class="helo-hint"><?php esc_html_e( 'Optional text printed just above the widget.', 'helo-smtp' ); ?></p>
				</div>
			</div>

			<div class="helo-field">
				<span class="helo-field__label"><?php esc_html_e( 'Submit button', 'helo-smtp' ); ?></span>
				<div class="helo-field__control">
					<label class="helo-switch">
						<input name="turnstile_hold_submit" type="checkbox" value="1" <?php checked( $settings['turnstile_hold_submit'] ); ?>>
						<span class="helo-switch__text">
							<strong><?php esc_html_e( 'Hold the button until the check passes', 'helo-smtp' ); ?></strong>
							<small><?php esc_html_e( 'Prevents the “nothing happened” double-click. Switch off if a theme’s button stops responding.', 'helo-smtp' ); ?></small>
						</span>
					</label>
				</div>
			</div>

			<div class="helo-field">
				<label class="helo-field__label" for="helo-ts-message"><?php esc_html_e( 'Failure message', 'helo-smtp' ); ?></label>
				<div class="helo-field__control">
					<input name="turnstile_message" id="helo-ts-message" type="text" class="helo-input--wide"
						value="<?php echo esc_attr( $settings['turnstile_message'] ); ?>"
						placeholder="<?php esc_attr_e( 'Failed to verify you are human. Please try again.', 'helo-smtp' ); ?>">
				</div>
			</div>
		</div>

		<!-- ------------------------------------------------------ exemptions -->
		<div class="helo-card">
			<div class="helo-card__head">
				<div>
					<h2><?php esc_html_e( 'Exemptions', 'helo-smtp' ); ?></h2>
					<p><?php esc_html_e( 'Visitors who skip the check entirely. Comments are never exempt.', 'helo-smtp' ); ?></p>
				</div>
			</div>

			<div class="helo-field">
				<span class="helo-field__label"><?php esc_html_e( 'Signed in', 'helo-smtp' ); ?></span>
				<div class="helo-field__control">
					<label class="helo-switch">
						<input name="turnstile_skip_users" type="checkbox" value="1" <?php checked( $settings['turnstile_skip_users'] ); ?>>
						<span class="helo-switch__text">
							<strong><?php esc_html_e( 'Logged-in users skip the check', 'helo-smtp' ); ?></strong>
							<small><?php esc_html_e( 'Stops staff solving a challenge all day. Comments still require one.', 'helo-smtp' ); ?></small>
						</span>
					</label>
				</div>
			</div>

			<div class="helo-field helo-field--stacked">
				<label class="helo-field__label" for="helo-skip-ips">
					<?php esc_html_e( 'IP addresses', 'helo-smtp' ); ?>
					<small><?php esc_html_e( 'One per line. Ranges in CIDR form are fine.', 'helo-smtp' ); ?></small>
				</label>
				<div class="helo-field__control">
					<textarea name="turnstile_skip_ips" id="helo-skip-ips" rows="4" class="helo-input--wide" placeholder="203.0.113.7&#10;198.51.100.0/24&#10;2001:db8::/32"><?php echo esc_textarea( $settings['turnstile_skip_ips'] ); ?></textarea>
					<p class="helo-hint"><?php esc_html_e( 'An IP allowlist is only as trustworthy as the proxy in front of your site — the address is read from headers a client can set unless something upstream overwrites them. Behind Cloudflare it is reliable.', 'helo-smtp' ); ?></p>
				</div>
			</div>

			<div class="helo-field helo-field--stacked">
				<label class="helo-field__label" for="helo-skip-agents">
					<?php esc_html_e( 'User agents', 'helo-smtp' ); ?>
					<small><?php esc_html_e( 'One substring per line, matched anywhere in the header.', 'helo-smtp' ); ?></small>
				</label>
				<div class="helo-field__control">
					<textarea name="turnstile_skip_agents" id="helo-skip-agents" rows="3" class="helo-input--wide" placeholder="UptimeRobot&#10;Pingdom"><?php echo esc_textarea( $settings['turnstile_skip_agents'] ); ?></textarea>
					<p class="helo-hint"><?php esc_html_e( 'Useful for uptime monitors. A user agent is trivially forged, so treat this as convenience, not security.', 'helo-smtp' ); ?></p>
				</div>
			</div>
		</div>
	</div>

	<!-- --------------------------------------------------------- failsafe -->
	<div class="helo-card">
		<div class="helo-card__head">
			<div>
				<h2><?php esc_html_e( 'If Cloudflare cannot be reached', 'helo-smtp' ); ?></h2>
				<p><?php esc_html_e( 'During an outage no token can be verified. This decides what happens to people trying to submit in the meantime — a rejected key or a bot is never treated as an outage.', 'helo-smtp' ); ?></p>
			</div>
		</div>

		<div class="helo-card__body">
			<div class="helo-choice">
				<label>
					<input type="radio" name="turnstile_failsafe" value="allow" <?php checked( $settings['turnstile_failsafe'], 'allow' ); ?>>
					<span class="helo-choice__text">
						<strong><?php esc_html_e( 'Let submissions through', 'helo-smtp' ); ?></strong>
						<small><?php esc_html_e( 'Forms keep working and nobody is locked out of logging in. Spam can get through for the length of the outage.', 'helo-smtp' ); ?></small>
					</span>
				</label>
				<label>
					<input type="radio" name="turnstile_failsafe" value="block" <?php checked( $settings['turnstile_failsafe'], 'block' ); ?>>
					<span class="helo-choice__text">
						<strong><?php esc_html_e( 'Block submissions', 'helo-smtp' ); ?></strong>
						<small><?php esc_html_e( 'Nothing unverified gets in. Your login and contact forms stop accepting anyone until Cloudflare returns.', 'helo-smtp' ); ?></small>
					</span>
				</label>
			</div>
		</div>
	</div>

	<!-- ---------------------------------------------------------- logging -->
	<div class="helo-card">
		<div class="helo-card__head">
			<div>
				<h2><?php esc_html_e( 'Recording', 'helo-smtp' ); ?></h2>
				<p><?php esc_html_e( 'What the Security log collects.', 'helo-smtp' ); ?></p>
			</div>
			<a class="button" href="<?php echo esc_url( Helo_Admin::analytics_url() ); ?>"><?php esc_html_e( 'View security log', 'helo-smtp' ); ?></a>
		</div>

		<div class="helo-field">
			<span class="helo-field__label"><?php esc_html_e( 'Totals', 'helo-smtp' ); ?></span>
			<div class="helo-field__control">
				<label class="helo-switch">
					<input name="turnstile_analytics" type="checkbox" value="1" <?php checked( $settings['turnstile_analytics'] ); ?>>
					<span class="helo-switch__text">
						<strong><?php esc_html_e( 'Count verified and blocked checks', 'helo-smtp' ); ?></strong>
						<small><?php esc_html_e( 'Counters only — no IP addresses and no page URLs.', 'helo-smtp' ); ?></small>
					</span>
				</label>
			</div>
		</div>

		<div class="helo-field">
			<span class="helo-field__label"><?php esc_html_e( 'Debug log', 'helo-smtp' ); ?></span>
			<div class="helo-field__control">
				<label class="helo-switch">
					<input name="turnstile_debug_log" type="checkbox" value="1" <?php checked( $settings['turnstile_debug_log'] ); ?>>
					<span class="helo-switch__text">
						<strong><?php esc_html_e( 'Record the last 50 checks with IP and page', 'helo-smtp' ); ?></strong>
						<small><?php esc_html_e( 'Stores identifying data. Leave off unless you are diagnosing something.', 'helo-smtp' ); ?></small>
					</span>
				</label>
			</div>
		</div>

		<div class="helo-card__foot">
			<p><?php esc_html_e( 'One save applies everything on this screen.', 'helo-smtp' ); ?></p>
			<?php submit_button( __( 'Save bot protection', 'helo-smtp' ), 'primary', 'submit', false ); ?>
		</div>
	</div>
</form>
