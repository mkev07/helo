<?php
/**
 * Dashboard: is mail going out, and is the site being protected?
 *
 * @package Helo
 * @var array $settings   Resolved plugin settings.
 * @var array $stats      Mail log stats.
 * @var int   $mail_count Total logged emails.
 * @var array $analytics  Turnstile counter snapshot.
 * @var array $summary    Integration summary.
 */

defined( 'ABSPATH' ) || exit;

$helo_smtp_on  = Helo_Settings::is_configured();
$helo_last     = $stats['last'];
$helo_bot_on   = Helo_Turnstile::enabled();
$helo_checks   = (int) $analytics['total'];
$helo_pass     = Helo_Analytics::percent_value( $analytics['verified'], $helo_checks );
$helo_copy_on  = Helo_Settings::get( 'copy_to_sent' );
?>

<div class="helo-stack">

	<div class="helo-stats">
		<div class="helo-stat">
			<span class="helo-stat__label"><?php esc_html_e( 'Mail server', 'helo-smtp' ); ?></span>
			<div class="helo-stat__value helo-stat__value--sm">
				<?php if ( $helo_smtp_on ) : ?>
					<?php echo esc_html( $settings['host'] ); ?><small>:<?php echo esc_html( $settings['port'] ); ?></small>
				<?php else : ?>
					<small><?php esc_html_e( 'PHP mail() — not configured', 'helo-smtp' ); ?></small>
				<?php endif; ?>
			</div>
		</div>
		<div class="helo-stat">
			<span class="helo-stat__label"><?php esc_html_e( 'Sent, last 7 days', 'helo-smtp' ); ?></span>
			<div class="helo-stat__value"><?php echo esc_html( number_format_i18n( $stats['sent'] ) ); ?></div>
		</div>
		<div class="helo-stat">
			<span class="helo-stat__label"><?php esc_html_e( 'Failed, last 7 days', 'helo-smtp' ); ?></span>
			<div class="helo-stat__value<?php echo $stats['failed'] ? ' helo-stat__value--err' : ''; ?>">
				<?php if ( $stats['failed'] ) : ?>
					<a class="helo-danger" href="<?php echo esc_url( Helo_Admin::logs_url() ); ?>"><?php echo esc_html( number_format_i18n( $stats['failed'] ) ); ?></a>
				<?php else : ?>
					0
				<?php endif; ?>
			</div>
		</div>
		<div class="helo-stat">
			<span class="helo-stat__label"><?php esc_html_e( 'Bot checks', 'helo-smtp' ); ?></span>
			<div class="helo-stat__value">
				<?php echo esc_html( number_format_i18n( $helo_checks ) ); ?>
				<?php if ( $helo_checks ) : ?>
					<small><?php echo esc_html( Helo_Analytics::percent( $analytics['verified'], $helo_checks ) ); ?> <?php esc_html_e( 'passed', 'helo-smtp' ); ?></small>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="helo-grid helo-grid--2">

		<!-- ------------------------------------------------------ delivery -->
		<div class="helo-card">
			<div class="helo-card__head">
				<div>
					<h2><?php esc_html_e( 'Mail delivery', 'helo-smtp' ); ?></h2>
					<p><?php esc_html_e( 'Where outgoing email is handed off, and what happened to it.', 'helo-smtp' ); ?></p>
				</div>
				<?php if ( $helo_smtp_on ) : ?>
					<span class="helo-badge helo-badge--ok"><?php esc_html_e( 'SMTP', 'helo-smtp' ); ?></span>
				<?php else : ?>
					<span class="helo-badge helo-badge--warn"><?php esc_html_e( 'Unconfigured', 'helo-smtp' ); ?></span>
				<?php endif; ?>
			</div>

			<div class="helo-card__body">
				<?php if ( ! $helo_smtp_on ) : ?>
					<div class="helo-alert helo-alert--warn">
						<span class="helo-alert__icon" aria-hidden="true">&#9888;</span>
						<div><?php esc_html_e( 'Mail is still going through PHP’s mail(), which most hosts block or send straight to spam.', 'helo-smtp' ); ?></div>
					</div>
				<?php elseif ( $helo_last && 'sent' !== $helo_last->status ) : ?>
					<div class="helo-alert helo-alert--err">
						<span class="helo-alert__icon" aria-hidden="true">&#10007;</span>
						<div>
							<strong><?php esc_html_e( 'The last email failed.', 'helo-smtp' ); ?></strong>
							<a href="<?php echo esc_url( Helo_Admin::logs_url( '', 1, $helo_last->id ) ); ?>"><?php esc_html_e( 'See why', 'helo-smtp' ); ?></a>
						</div>
					</div>
				<?php else : ?>
					<div class="helo-alert helo-alert--ok">
						<span class="helo-alert__icon" aria-hidden="true">&#10003;</span>
						<div>
							<?php if ( $helo_last ) : ?>
								<?php
								printf(
									/* translators: %s: human readable time difference. */
									esc_html__( 'Last email went out %s ago.', 'helo-smtp' ),
									esc_html( human_time_diff( mysql2date( 'U', $helo_last->created_at ), current_time( 'timestamp' ) ) )
								);
								?>
							<?php else : ?>
								<?php esc_html_e( 'SMTP is configured. Nothing has been sent yet.', 'helo-smtp' ); ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<dl class="helo-link-list" style="margin-top:16px">
					<div>
						<span class="helo-muted"><?php esc_html_e( 'Logged', 'helo-smtp' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( $mail_count ) ); ?></strong>
					</div>
					<div>
						<span class="helo-muted"><?php esc_html_e( 'Copy to Sent', 'helo-smtp' ); ?></span>
						<strong><?php echo $helo_copy_on ? esc_html__( 'On', 'helo-smtp' ) : esc_html__( 'Off', 'helo-smtp' ); ?></strong>
					</div>
					<div>
						<span class="helo-muted"><?php esc_html_e( 'Retention', 'helo-smtp' ); ?></span>
						<strong>
							<?php
							echo $settings['log_days']
								? esc_html( sprintf( /* translators: %s: number of days. */ __( '%s days', 'helo-smtp' ), number_format_i18n( $settings['log_days'] ) ) )
								: esc_html__( 'Forever', 'helo-smtp' );
							?>
						</strong>
					</div>
				</dl>
			</div>

			<div class="helo-card__foot">
				<a class="button" href="<?php echo esc_url( Helo_Admin::mail_url() ); ?>"><?php esc_html_e( 'Mail settings', 'helo-smtp' ); ?></a>
				<a class="button" href="<?php echo esc_url( Helo_Admin::logs_url() ); ?>"><?php esc_html_e( 'Open email log', 'helo-smtp' ); ?></a>
			</div>
		</div>

		<!-- ------------------------------------------------------ protection -->
		<div class="helo-card">
			<div class="helo-card__head">
				<div>
					<h2><?php esc_html_e( 'Bot protection', 'helo-smtp' ); ?></h2>
					<p><?php esc_html_e( 'Cloudflare Turnstile on your forms, and how it is doing.', 'helo-smtp' ); ?></p>
				</div>
				<?php if ( $helo_bot_on ) : ?>
					<span class="helo-badge helo-badge--ok"><?php esc_html_e( 'Active', 'helo-smtp' ); ?></span>
				<?php else : ?>
					<span class="helo-badge helo-badge--muted"><?php esc_html_e( 'Off', 'helo-smtp' ); ?></span>
				<?php endif; ?>
			</div>

			<div class="helo-card__body">
				<?php if ( ! $helo_bot_on ) : ?>
					<div class="helo-alert helo-alert--info">
						<span class="helo-alert__icon" aria-hidden="true">&#9432;</span>
						<div><?php esc_html_e( 'Turnstile is off. Add a free Cloudflare site key and pick which forms to guard.', 'helo-smtp' ); ?></div>
					</div>
				<?php elseif ( ! $summary['on'] ) : ?>
					<div class="helo-alert helo-alert--warn">
						<span class="helo-alert__icon" aria-hidden="true">&#9888;</span>
						<div><?php esc_html_e( 'Keys are set but no forms are selected, so nothing is being checked.', 'helo-smtp' ); ?></div>
					</div>
				<?php else : ?>
					<div class="helo-alert helo-alert--ok">
						<span class="helo-alert__icon" aria-hidden="true">&#10003;</span>
						<div>
							<?php
							printf(
								/* translators: %s: number of protected forms. */
								esc_html( _n( '%s form is protected.', '%s forms are protected.', $summary['on'], 'helo-smtp' ) ),
								esc_html( number_format_i18n( $summary['on'] ) )
							);
							?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $helo_checks ) : ?>
					<div style="margin-top:18px">
						<div class="helo-row" style="justify-content:space-between">
							<span class="helo-muted"><?php esc_html_e( 'Pass rate', 'helo-smtp' ); ?></span>
							<strong><?php echo esc_html( Helo_Analytics::percent( $analytics['verified'], $helo_checks ) ); ?></strong>
						</div>
						<div class="helo-meter">
							<span class="helo-meter__fill" style="width:<?php echo esc_attr( $helo_pass ); ?>%"></span>
						</div>
						<p class="helo-hint">
							<?php
							printf(
								/* translators: 1: blocked count, 2: total count. */
								esc_html__( '%1$s of %2$s checks were blocked.', 'helo-smtp' ),
								esc_html( number_format_i18n( $analytics['blocked'] ) ),
								esc_html( number_format_i18n( $helo_checks ) )
							);
							?>
						</p>
					</div>
				<?php endif; ?>
			</div>

			<div class="helo-card__foot">
				<a class="button" href="<?php echo esc_url( Helo_Admin::security_url() ); ?>"><?php esc_html_e( 'Bot protection', 'helo-smtp' ); ?></a>
				<a class="button" href="<?php echo esc_url( Helo_Admin::analytics_url() ); ?>"><?php esc_html_e( 'Security log', 'helo-smtp' ); ?></a>
			</div>
		</div>
	</div>
</div>
