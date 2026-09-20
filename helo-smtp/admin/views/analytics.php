<?php
/**
 * Security log screen: how the bot protection is performing.
 *
 * @package Helo
 * @var array $analytics Normalised counter snapshot from Helo_Analytics::snapshot().
 * @var array $log       Raw debug log, newest first, from Helo_Analytics::log().
 */

defined( 'ABSPATH' ) || exit;

$helo_total         = (int) $analytics['total'];
$helo_verified      = (int) $analytics['verified'];
$helo_blocked       = (int) $analytics['blocked'];
$helo_retries       = (int) $analytics['retries'];
$helo_updated       = $analytics['updated'] ? mysql2date( 'U', $analytics['updated'] ) : 0;
$helo_has_analytics = $helo_total > 0;
$helo_has_log       = ! empty( $log );

$helo_reset_confirm = "return confirm('" . esc_js( esc_html__( 'Reset all Turnstile security analytics? This cannot be undone.', 'helo-smtp' ) ) . "');";
$helo_reset_log_confirm = "return confirm('" . esc_js( esc_html__( 'Clear the Turnstile debug log?', 'helo-smtp' ) ) . "');";
?>

<div class="helo-card">
	<div class="helo-card__head">
		<h2><?php esc_html_e( 'Bot protection — how it is going', 'helo-smtp' ); ?></h2>
		<p>
			<?php esc_html_e( 'How often Cloudflare Turnstile lets a human through versus blocks a request, split by form.', 'helo-smtp' ); ?>
			<?php if ( $helo_updated ) : ?>
				<small><?php printf( esc_html__( 'Last check: %s', 'helo-smtp' ), esc_html( human_time_diff( $helo_updated, current_time( 'timestamp' ) ) . ' ' . esc_html__( 'ago', 'helo-smtp' ) ) ); ?></small>
			<?php endif; ?>
		</p>
	</div>

	<?php if ( ! $helo_has_analytics ) : ?>
		<div class="helo-field">
			<p class="helo-hint"><?php esc_html_e( 'No verification data yet. Once a form protected by Helo is submitted, the totals appear here.', 'helo-smtp' ); ?></p>
		</div>
	<?php else : ?>

	<div class="helo-summary">
		<div class="helo-summary__cell">
			<span class="helo-summary__label"><?php esc_html_e( 'Checks', 'helo-smtp' ); ?></span>
			<div class="helo-summary__value"><?php echo esc_html( number_format_i18n( $helo_total ) ); ?></div>
		</div>
		<div class="helo-summary__cell">
			<span class="helo-summary__label"><?php esc_html_e( 'Verified', 'helo-smtp' ); ?></span>
			<div class="helo-summary__value helo-summary__value--ok"><?php echo esc_html( number_format_i18n( $helo_verified ) ); ?>
				<small><?php echo esc_html( Helo_Analytics::percent( $helo_verified, $helo_total ) ); ?></small>
			</div>
		</div>
		<div class="helo-summary__cell">
			<span class="helo-summary__label"><?php esc_html_e( 'Blocked', 'helo-smtp' ); ?></span>
			<div class="helo-summary__value"><?php echo esc_html( number_format_i18n( $helo_blocked ) ); ?>
				<small><?php echo esc_html( Helo_Analytics::percent( $helo_blocked, $helo_total ) ); ?></small>
			</div>
		</div>
		<div class="helo-summary__cell">
			<span class="helo-summary__label"><?php esc_html_e( 'Retries', 'helo-smtp' ); ?></span>
			<div class="helo-summary__value"><?php echo esc_html( number_format_i18n( $helo_retries ) ); ?></div>
		</div>
	</div>

	<h3><?php esc_html_e( 'Blocked reasons', 'helo-smtp' ); ?></h3>
	<?php if ( ! empty( $analytics['errors'] ) ) : ?>
		<div class="helo-row">
			<?php foreach ( $analytics['errors'] as $helo_code => $helo_count ) : ?>
				<span class="helo-badge helo-badge--err"><?php echo esc_html( $helo_code ); ?> &times; <?php echo esc_html( number_format_i18n( $helo_count ) ); ?></span>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="helo-hint"><?php esc_html_e( 'No blocked reasons recorded.', 'helo-smtp' ); ?></p>
	<?php endif; ?>

	<h3><?php esc_html_e( 'By form', 'helo-smtp' ); ?></h3>
	<?php if ( ! empty( $analytics['forms'] ) ) : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Form', 'helo-smtp' ); ?></th>
					<th><?php esc_html_e( 'Checks', 'helo-smtp' ); ?></th>
					<th><?php esc_html_e( 'Verified', 'helo-smtp' ); ?></th>
					<th><?php esc_html_e( 'Blocked', 'helo-smtp' ); ?></th>
					<th><?php esc_html_e( 'Success', 'helo-smtp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $analytics['forms'] as $helo_form ) : ?>
					<tr>
						<td><?php echo esc_html( $helo_form['label'] ? (string) $helo_form['label'] : esc_html__( 'Unknown', 'helo-smtp' ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $helo_form['total'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $helo_form['verified'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $helo_form['blocked'] ) ); ?></td>
						<td><?php echo esc_html( Helo_Analytics::percent( $helo_form['verified'], $helo_form['total'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="helo-hint"><?php esc_html_e( 'No per-form breakdown yet.', 'helo-smtp' ); ?></p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="helo_turnstile_reset_analytics">
		<?php wp_nonce_field( 'helo_turnstile_reset_analytics' ); ?>
		<?php submit_button( __( 'Reset analytics', 'helo-smtp' ), 'secondary', 'submit', false, array( 'onclick' => $helo_reset_confirm ) ); ?>
	</form>
	<?php endif; ?>
</div>

<div class="helo-card">
	<div class="helo-card__head">
		<h2><?php esc_html_e( 'Debug log', 'helo-smtp' ); ?></h2>
		<p><?php esc_html_e( 'Per-verification record including the visitor IP and page URL. Turned on in Bot protection; off by default because it stores identifying data.', 'helo-smtp' ); ?></p>
	</div>

	<?php if ( ! Helo_Settings::get( 'turnstile_debug_log' ) ) : ?>
		<p class="helo-hint"><?php esc_html_e( 'The debug log is disabled. Enable “Turnstile debug log” on the Bot protection screen to start recording entries.', 'helo-smtp' ); ?></p>
	<?php elseif ( ! $helo_has_log ) : ?>
		<p class="helo-hint"><?php esc_html_e( 'No events logged yet.', 'helo-smtp' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'helo-smtp' ); ?></th>
					<th><?php esc_html_e( 'Outcome', 'helo-smtp' ); ?></th>
					<th><?php esc_html_e( 'Response', 'helo-smtp' ); ?></th>
					<th><?php esc_html_e( 'Info', 'helo-smtp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log as $helo_item ) : ?>
					<tr>
						<td><?php echo esc_html( $helo_item['date'] ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), mysql2date( 'U', $helo_item['date'] ) ) : '' ); ?></td>
						<td>
							<?php if ( $helo_item['success'] ) : ?>
								<span class="helo-badge helo-badge--ok"><?php esc_html_e( 'Verified', 'helo-smtp' ); ?></span>
							<?php else : ?>
								<span class="helo-badge helo-badge--err"><?php esc_html_e( 'Blocked', 'helo-smtp' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $helo_item['success'] ? esc_html__( 'Success', 'helo-smtp' ) : (string) $helo_item['error'] ); ?></td>
						<td>
							<?php if ( $helo_item['ip'] ) : ?>
								<strong><?php esc_html_e( 'IP:', 'helo-smtp' ); ?></strong> <?php echo esc_html( $helo_item['ip'] ); ?>
							<?php endif; ?>
							<?php if ( $helo_item['page'] ) : ?>
								<br><strong><?php esc_html_e( 'URL:', 'helo-smtp' ); ?></strong> <?php echo esc_html( $helo_item['page'] ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="helo_turnstile_reset_log">
			<?php wp_nonce_field( 'helo_turnstile_reset_log' ); ?>
			<?php submit_button( __( 'Clear debug log', 'helo-smtp' ), 'secondary', 'submit', false, array( 'onclick' => $helo_reset_log_confirm ) ); ?>
		</form>
	<?php endif; ?>
</div>