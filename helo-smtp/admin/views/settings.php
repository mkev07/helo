<?php
/**
 * Settings tab.
 *
 * @package Helo
 * @var array $settings Resolved plugin settings.
 * @var array $stats    Recent log counts.
 */

defined( 'ABSPATH' ) || exit;

$helo_locked      = Helo_Settings::password_is_locked();
$helo_last        = $stats['last'];
$helo_encryptions = array(
	'tls'  => __( 'TLS / STARTTLS — usually port 587', 'helo-smtp' ),
	'ssl'  => __( 'SSL — usually port 465', 'helo-smtp' ),
	'none' => __( 'None — not recommended', 'helo-smtp' ),
);
?>

<div class="helo-summary">
	<div class="helo-summary__cell">
		<span class="helo-summary__label"><?php esc_html_e( 'Mail server', 'helo-smtp' ); ?></span>
		<div class="helo-summary__value">
			<?php if ( '' !== $settings['host'] ) : ?>
				<?php echo esc_html( $settings['host'] ); ?>
				<small>:<?php echo esc_html( $settings['port'] ); ?></small>
			<?php else : ?>
				<small><?php esc_html_e( 'Not configured', 'helo-smtp' ); ?></small>
			<?php endif; ?>
		</div>
	</div>
	<div class="helo-summary__cell">
		<span class="helo-summary__label"><?php esc_html_e( 'Sent, last 7 days', 'helo-smtp' ); ?></span>
		<div class="helo-summary__value"><?php echo esc_html( number_format_i18n( $stats['sent'] ) ); ?></div>
	</div>
	<div class="helo-summary__cell">
		<span class="helo-summary__label"><?php esc_html_e( 'Failed, last 7 days', 'helo-smtp' ); ?></span>
		<div class="helo-summary__value">
			<?php if ( $stats['failed'] ) : ?>
				<a href="<?php echo esc_url( Helo_Admin::url( array( 'tab' => 'logs' ) ) ); ?>" style="color:var(--helo-err);text-decoration:none">
					<?php echo esc_html( number_format_i18n( $stats['failed'] ) ); ?>
				</a>
			<?php else : ?>
				0
			<?php endif; ?>
		</div>
	</div>
	<div class="helo-summary__cell">
		<span class="helo-summary__label"><?php esc_html_e( 'Last email', 'helo-smtp' ); ?></span>
		<div class="helo-summary__value">
			<?php if ( $helo_last ) : ?>
				<span class="helo-badge helo-badge--<?php echo 'sent' === $helo_last->status ? 'ok' : 'err'; ?>">
					<?php echo esc_html( human_time_diff( mysql2date( 'U', $helo_last->created_at ), current_time( 'timestamp' ) ) ); ?>
					<?php esc_html_e( 'ago', 'helo-smtp' ); ?>
				</span>
			<?php else : ?>
				<small><?php esc_html_e( 'Nothing yet', 'helo-smtp' ); ?></small>
			<?php endif; ?>
		</div>
	</div>
</div>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="helo_save">
	<?php wp_nonce_field( 'helo_save' ); ?>

	<div class="helo-card">
		<div class="helo-card__head">
			<h2><?php esc_html_e( 'Mail server', 'helo-smtp' ); ?></h2>
			<p><?php esc_html_e( 'Where outgoing mail is handed off. Leave the host empty to keep using the server’s built-in PHP mailer.', 'helo-smtp' ); ?></p>
		</div>

		<div class="helo-field">
			<label class="helo-field__label" for="helo-host"><?php esc_html_e( 'SMTP host', 'helo-smtp' ); ?></label>
			<div class="helo-field__control">
				<input name="host" id="helo-host" type="text" placeholder="smtp.example.com" value="<?php echo esc_attr( $settings['host'] ); ?>">
			</div>
		</div>

		<div class="helo-field">
			<label class="helo-field__label" for="helo-encryption"><?php esc_html_e( 'Encryption', 'helo-smtp' ); ?></label>
			<div class="helo-field__control">
				<div class="helo-row">
					<select name="encryption" id="helo-encryption" style="max-width:280px">
						<?php foreach ( $helo_encryptions as $helo_value => $helo_label ) : ?>
							<option value="<?php echo esc_attr( $helo_value ); ?>" <?php selected( $settings['encryption'], $helo_value ); ?>>
								<?php echo esc_html( $helo_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<label for="helo-port" class="screen-reader-text"><?php esc_html_e( 'Port', 'helo-smtp' ); ?></label>
					<input name="port" id="helo-port" type="number" min="1" max="65535" value="<?php echo esc_attr( $settings['port'] ); ?>">
				</div>
				<p class="helo-hint"><?php esc_html_e( 'Protocol and port. Change the port only if your provider asks for something unusual.', 'helo-smtp' ); ?></p>
			</div>
		</div>

		<div class="helo-field">
			<span class="helo-field__label"><?php esc_html_e( 'Authentication', 'helo-smtp' ); ?></span>
			<div class="helo-field__control">
				<label class="helo-switch">
					<input name="auth" type="checkbox" value="1" <?php checked( $settings['auth'] ); ?>>
					<span><?php esc_html_e( 'This server requires a username and password', 'helo-smtp' ); ?></span>
				</label>
			</div>
		</div>

		<div class="helo-field">
			<label class="helo-field__label" for="helo-username"><?php esc_html_e( 'Username', 'helo-smtp' ); ?></label>
			<div class="helo-field__control">
				<input name="username" id="helo-username" type="text" autocomplete="off" value="<?php echo esc_attr( $settings['username'] ); ?>">
			</div>
		</div>

		<div class="helo-field">
			<label class="helo-field__label" for="helo-password"><?php esc_html_e( 'Password', 'helo-smtp' ); ?></label>
			<div class="helo-field__control">
				<?php if ( $helo_locked ) : ?>
					<span class="helo-badge helo-badge--ok"><?php esc_html_e( 'Set in wp-config.php', 'helo-smtp' ); ?></span>
					<p class="helo-hint"><?php esc_html_e( 'The HELO_SMTP_PASSWORD constant takes priority over anything stored here.', 'helo-smtp' ); ?></p>
				<?php else : ?>
					<input name="password" id="helo-password" type="password" autocomplete="new-password"
						value="<?php echo $settings['password'] ? esc_attr( Helo_Settings::UNCHANGED ) : ''; ?>">
					<p class="helo-hint">
						<?php esc_html_e( 'Stored encrypted. Safer still: keep it out of the database entirely with', 'helo-smtp' ); ?>
						<code>define( 'HELO_SMTP_PASSWORD', '…' );</code>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="helo-card">
		<div class="helo-card__head">
			<h2><?php esc_html_e( 'Sender', 'helo-smtp' ); ?></h2>
			<p><?php esc_html_e( 'What recipients see in the From line.', 'helo-smtp' ); ?></p>
		</div>

		<div class="helo-field">
			<label class="helo-field__label" for="helo-from-email"><?php esc_html_e( 'From address', 'helo-smtp' ); ?></label>
			<div class="helo-field__control">
				<input name="from_email" id="helo-from-email" type="email" value="<?php echo esc_attr( $settings['from_email'] ); ?>">
			</div>
		</div>

		<div class="helo-field">
			<label class="helo-field__label" for="helo-from-name"><?php esc_html_e( 'From name', 'helo-smtp' ); ?></label>
			<div class="helo-field__control">
				<input name="from_name" id="helo-from-name" type="text" value="<?php echo esc_attr( $settings['from_name'] ); ?>">
			</div>
		</div>

		<div class="helo-field">
			<span class="helo-field__label"><?php esc_html_e( 'Force sender', 'helo-smtp' ); ?></span>
			<div class="helo-field__control">
				<label class="helo-switch">
					<input name="force_from" type="checkbox" value="1" <?php checked( $settings['force_from'] ); ?>>
					<span><?php esc_html_e( 'Override the From address chosen by other plugins', 'helo-smtp' ); ?></span>
				</label>
				<p class="helo-hint"><?php esc_html_e( 'Recommended. Most providers reject mail sent from an address they do not own.', 'helo-smtp' ); ?></p>
			</div>
		</div>
	</div>

	<div class="helo-card">
		<div class="helo-card__head">
			<h2><?php esc_html_e( 'Log & diagnostics', 'helo-smtp' ); ?></h2>
			<p><?php esc_html_e( 'What gets recorded, and for how long.', 'helo-smtp' ); ?></p>
		</div>

		<div class="helo-field">
			<span class="helo-field__label"><?php esc_html_e( 'Email log', 'helo-smtp' ); ?></span>
			<div class="helo-field__control">
				<label class="helo-switch">
					<input name="logging" type="checkbox" value="1" <?php checked( $settings['logging'] ); ?>>
					<span><?php esc_html_e( 'Record every outgoing email', 'helo-smtp' ); ?></span>
				</label>
			</div>
		</div>

		<div class="helo-field">
			<label class="helo-field__label" for="helo-log-days"><?php esc_html_e( 'Retention', 'helo-smtp' ); ?></label>
			<div class="helo-field__control">
				<div class="helo-row">
					<input name="log_days" id="helo-log-days" type="number" min="0" value="<?php echo esc_attr( $settings['log_days'] ); ?>">
					<span><?php esc_html_e( 'days', 'helo-smtp' ); ?></span>
				</div>
				<p class="helo-hint"><?php esc_html_e( '0 keeps them forever. Message bodies add up, so 30 days is usually plenty.', 'helo-smtp' ); ?></p>
			</div>
		</div>

		<div class="helo-field">
			<span class="helo-field__label"><?php esc_html_e( 'Debug', 'helo-smtp' ); ?></span>
			<div class="helo-field__control">
				<label class="helo-switch">
					<input name="debug" type="checkbox" value="1" <?php checked( $settings['debug'] ); ?>>
					<span><?php esc_html_e( 'Write the raw SMTP conversation to the PHP error log', 'helo-smtp' ); ?></span>
				</label>
				<p class="helo-hint"><?php esc_html_e( 'Turn this off once delivery works — it is noisy.', 'helo-smtp' ); ?></p>
			</div>
		</div>

		<div class="helo-card__foot">
			<p>
				<?php
				printf(
					/* translators: %s: version number. */
					esc_html__( 'Version %s', 'helo-smtp' ),
					esc_html( HELO_VERSION )
				);
				?>
				<?php if ( Helo_Updater::uri() ) : ?>
					&middot; <a href="<?php echo esc_url( Helo_Updater::check_url() ); ?>"><?php esc_html_e( 'Check for updates', 'helo-smtp' ); ?></a>
				<?php endif; ?>
			</p>
			<?php submit_button( __( 'Save changes', 'helo-smtp' ), 'primary', 'submit', false ); ?>
		</div>
	</div>
</form>

<div class="helo-card">
	<div class="helo-card__head">
		<h2><?php esc_html_e( 'Send a test email', 'helo-smtp' ); ?></h2>
		<p><?php esc_html_e( 'Save your settings first — the test uses whatever is currently stored, and reports the server’s exact error if it fails.', 'helo-smtp' ); ?></p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="helo_test">
		<?php wp_nonce_field( 'helo_test' ); ?>

		<div class="helo-field">
			<label class="helo-field__label" for="helo-test-to"><?php esc_html_e( 'Send to', 'helo-smtp' ); ?></label>
			<div class="helo-field__control">
				<div class="helo-row">
					<input type="email" name="to" id="helo-test-to" required style="max-width:300px" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
					<label class="helo-switch">
						<input type="checkbox" name="html" value="1" checked>
						<span><?php esc_html_e( 'HTML', 'helo-smtp' ); ?></span>
					</label>
					<?php submit_button( __( 'Send test', 'helo-smtp' ), 'secondary', 'submit', false ); ?>
				</div>
			</div>
		</div>
	</form>
</div>
