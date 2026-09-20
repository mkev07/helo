<?php
/**
 * Dashboard: at-a-glance status and shortcuts to each screen.
 *
 * @package Helo
 * @var array $settings   Resolved plugin settings.
 * @var array $stats      Recent log counts.
 * @var int   $mail_count Total logged messages.
 */

defined( 'ABSPATH' ) || exit;

$helo_last      = $stats['last'];
$helo_turnstile = Helo_Turnstile::enabled() ? 'ok' : 'muted';
?>

<div class="helo-summary helo-dash-summary">
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
				<a href="<?php echo esc_url( Helo_Admin::logs_url() ); ?>" style="color:var(--helo-err);text-decoration:none">
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

<div class="helo-dash-grid">
	<?php
	$helo_cards = array(
		array( 'mail', 'dashicons-email', __( 'Mail', 'helo-smtp' ), __( 'SMTP server, sender, copy-to-Sent, and a test button.', 'helo-smtp' ) ),
		array( 'logs', 'dashicons-list-view', __( 'Email log', 'helo-smtp' ), sprintf( __( 'Every message sent — %s logged.', 'helo-smtp' ), number_format_i18n( $mail_count ) ) ),
		array( 'security', 'dashicons-shield', __( 'Bot protection', 'helo-smtp' ), __( 'Cloudflare Turnstile keys, and which forms they guard.', 'helo-smtp' ) ),
		array( 'analytics', 'dashicons-chart-area', __( 'Security log', 'helo-smtp' ), __( 'Verified vs blocked, per form, and why.', 'helo-smtp' ) ),
	);

	foreach ( $helo_cards as $helo_card ) :
		$helo_method = $helo_card[0] . '_url';
		?>
		<div class="helo-card helo-dash-card">
			<div class="helo-card__head helo-card__head--split">
				<div>
					<h2><span class="dashicons <?php echo esc_attr( $helo_card[1] ); ?>"></span><?php echo esc_html( $helo_card[2] ); ?></h2>
					<p><?php echo esc_html( $helo_card[3] ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( Helo_Admin::{$helo_method}() ); ?>"><?php esc_html_e( 'Open', 'helo-smtp' ); ?></a>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<div class="helo-card helo-quicklinks">
	<div class="helo-card__head">
		<h2><?php esc_html_e( 'Quick links', 'helo-smtp' ); ?></h2>
		<p><?php esc_html_e( 'Jump to the tools behind Helo.', 'helo-smtp' ); ?></p>
	</div>

	<div class="helo-row">
		<a class="button" href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Cloudflare Turnstile ↗', 'helo-smtp' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Plugins screen', 'helo-smtp' ); ?></a>
		<?php if ( Helo_Updater::uri() ) : ?>
			<a class="button" href="<?php echo esc_url( Helo_Updater::check_url() ); ?>"><?php esc_html_e( 'Check for updates', 'helo-smtp' ); ?></a>
		<?php endif; ?>
	</div>
</div>