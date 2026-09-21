<?php
/**
 * Security log: how the bot protection is performing.
 *
 * @package Helo
 * @var array $analytics Normalised counter snapshot from Helo_Analytics::snapshot().
 * @var array $log       Raw debug log, newest first, from Helo_Analytics::log().
 */

defined( 'ABSPATH' ) || exit;

$helo_total    = (int) $analytics['total'];
$helo_verified = (int) $analytics['verified'];
$helo_blocked  = (int) $analytics['blocked'];
$helo_retries  = (int) $analytics['retries'];
$helo_updated  = $analytics['updated'] ? mysql2date( 'U', $analytics['updated'] ) : 0;
$helo_pass     = Helo_Analytics::percent_value( $helo_verified, $helo_total );

$helo_confirm     = "return confirm('" . esc_js( __( 'Reset all Turnstile security analytics? This cannot be undone.', 'helo-smtp' ) ) . "');";
$helo_confirm_log = "return confirm('" . esc_js( __( 'Clear the Turnstile debug log?', 'helo-smtp' ) ) . "');";
?>

<div class="helo-stack">

	<?php if ( ! $helo_total ) : ?>

		<div class="helo-card">
			<div class="helo-empty">
				<span class="dashicons dashicons-shield"></span>
				<h3><?php esc_html_e( 'Nothing checked yet', 'helo-smtp' ); ?></h3>
				<p><?php esc_html_e( 'Totals appear here the first time somebody submits a form that Turnstile guards.', 'helo-smtp' ); ?></p>
				<p class="helo-empty__actions">
					<a class="button" href="<?php echo esc_url( Helo_Admin::security_url() ); ?>"><?php esc_html_e( 'Set up bot protection', 'helo-smtp' ); ?></a>
				</p>
			</div>
		</div>

	<?php else : ?>

		<div class="helo-stats">
			<div class="helo-stat">
				<span class="helo-stat__label"><?php esc_html_e( 'Checks', 'helo-smtp' ); ?></span>
				<div class="helo-stat__value"><?php echo esc_html( number_format_i18n( $helo_total ) ); ?></div>
				<?php if ( $helo_updated ) : ?>
					<div class="helo-meter helo-meter--sm" style="visibility:hidden"></div>
				<?php endif; ?>
			</div>
			<div class="helo-stat">
				<span class="helo-stat__label"><?php esc_html_e( 'Verified', 'helo-smtp' ); ?></span>
				<div class="helo-stat__value helo-stat__value--ok">
					<?php echo esc_html( number_format_i18n( $helo_verified ) ); ?>
					<small><?php echo esc_html( Helo_Analytics::percent( $helo_verified, $helo_total ) ); ?></small>
				</div>
				<div class="helo-meter helo-meter--sm">
					<span class="helo-meter__fill" style="width:<?php echo esc_attr( $helo_pass ); ?>%"></span>
				</div>
			</div>
			<div class="helo-stat">
				<span class="helo-stat__label"><?php esc_html_e( 'Blocked', 'helo-smtp' ); ?></span>
				<div class="helo-stat__value<?php echo $helo_blocked ? ' helo-stat__value--err' : ''; ?>">
					<?php echo esc_html( number_format_i18n( $helo_blocked ) ); ?>
					<small><?php echo esc_html( Helo_Analytics::percent( $helo_blocked, $helo_total ) ); ?></small>
				</div>
				<div class="helo-meter helo-meter--sm">
					<span class="helo-meter__fill helo-meter__fill--err" style="width:<?php echo esc_attr( 100 - $helo_pass ); ?>%"></span>
				</div>
			</div>
			<div class="helo-stat">
				<span class="helo-stat__label"><?php esc_html_e( 'Retries', 'helo-smtp' ); ?></span>
				<div class="helo-stat__value"><?php echo esc_html( number_format_i18n( $helo_retries ) ); ?></div>
				<div class="helo-meter helo-meter--sm" style="visibility:hidden"></div>
			</div>
		</div>

		<!-- ------------------------------------------------------- by form -->
		<div class="helo-card">
			<div class="helo-card__head">
				<div>
					<h2><?php esc_html_e( 'By form', 'helo-smtp' ); ?></h2>
					<p><?php esc_html_e( 'A low pass rate usually means bots are finding that form, not that visitors are struggling.', 'helo-smtp' ); ?></p>
				</div>
				<?php if ( $helo_updated ) : ?>
					<span class="helo-badge helo-badge--muted helo-badge--bare">
						<?php
						printf(
							/* translators: %s: human readable time difference. */
							esc_html__( 'Last check %s ago', 'helo-smtp' ),
							esc_html( human_time_diff( $helo_updated, current_time( 'timestamp' ) ) )
						);
						?>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( empty( $analytics['forms'] ) ) : ?>
				<div class="helo-card__body">
					<p class="helo-muted"><?php esc_html_e( 'No per-form breakdown yet.', 'helo-smtp' ); ?></p>
				</div>
			<?php else : ?>
				<table class="helo-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Form', 'helo-smtp' ); ?></th>
							<th scope="col" class="helo-table__num" style="width:110px"><?php esc_html_e( 'Checks', 'helo-smtp' ); ?></th>
							<th scope="col" class="helo-table__num" style="width:110px"><?php esc_html_e( 'Blocked', 'helo-smtp' ); ?></th>
							<th scope="col" style="width:200px"><?php esc_html_e( 'Pass rate', 'helo-smtp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $analytics['forms'] as $helo_form ) : ?>
							<?php $helo_rate = Helo_Analytics::percent_value( $helo_form['verified'], $helo_form['total'] ); ?>
							<tr>
								<td>
									<span class="helo-table__name"><?php echo esc_html( $helo_form['label'] ? $helo_form['label'] : __( 'Unknown', 'helo-smtp' ) ); ?></span>
									<?php if ( $helo_form['last_checked'] ) : ?>
										<span class="helo-table__sub"><?php echo esc_html( mysql2date( 'j M, H:i', $helo_form['last_checked'] ) ); ?></span>
									<?php endif; ?>
								</td>
								<td class="helo-table__num"><?php echo esc_html( number_format_i18n( $helo_form['total'] ) ); ?></td>
								<td class="helo-table__num"><?php echo esc_html( number_format_i18n( $helo_form['blocked'] ) ); ?></td>
								<td>
									<div class="helo-row" style="gap:8px;flex-wrap:nowrap">
										<div class="helo-meter" style="flex:1;margin:0">
											<span class="helo-meter__fill<?php echo $helo_rate < 70 ? ' helo-meter__fill--err' : ''; ?>" style="width:<?php echo esc_attr( $helo_rate ); ?>%"></span>
										</div>
										<span class="helo-mono helo-nowrap"><?php echo esc_html( Helo_Analytics::percent( $helo_form['verified'], $helo_form['total'] ) ); ?></span>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<div class="helo-card__foot">
				<p><?php esc_html_e( 'Counters only — no IP addresses or page URLs are stored here.', 'helo-smtp' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="helo_turnstile_reset_analytics">
					<?php wp_nonce_field( 'helo_turnstile_reset_analytics' ); ?>
					<?php submit_button( __( 'Reset totals', 'helo-smtp' ), 'secondary', 'submit', false, array( 'onclick' => $helo_confirm ) ); ?>
				</form>
			</div>
		</div>

		<!-- -------------------------------------------------- why they were blocked -->
		<?php if ( ! empty( $analytics['errors'] ) ) : ?>
			<div class="helo-card">
				<div class="helo-card__head">
					<div>
						<h2><?php esc_html_e( 'Why checks were blocked', 'helo-smtp' ); ?></h2>
						<p><?php esc_html_e( 'Cloudflare’s reason for each rejection, most common first.', 'helo-smtp' ); ?></p>
					</div>
				</div>

				<table class="helo-table">
					<thead>
						<tr>
							<th scope="col" style="width:230px"><?php esc_html_e( 'Reason', 'helo-smtp' ); ?></th>
							<th scope="col"><?php esc_html_e( 'What it means', 'helo-smtp' ); ?></th>
							<th scope="col" class="helo-table__num" style="width:100px"><?php esc_html_e( 'Count', 'helo-smtp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $analytics['errors'] as $helo_code => $helo_count ) : ?>
							<tr>
								<td><span class="helo-mono"><?php echo esc_html( $helo_code ); ?></span></td>
								<td class="helo-muted"><?php echo esc_html( Helo_Turnstile::explain( $helo_code ) ); ?></td>
								<td class="helo-table__num"><?php echo esc_html( number_format_i18n( $helo_count ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	<?php endif; ?>

	<!-- ------------------------------------------------------------ debug -->
	<div class="helo-card">
		<div class="helo-card__head">
			<div>
				<h2><?php esc_html_e( 'Debug log', 'helo-smtp' ); ?></h2>
				<p><?php esc_html_e( 'The last 50 checks with the visitor IP and the page they were on.', 'helo-smtp' ); ?></p>
			</div>
			<?php if ( Helo_Settings::get( 'turnstile_debug_log' ) ) : ?>
				<span class="helo-badge helo-badge--warn"><?php esc_html_e( 'Recording', 'helo-smtp' ); ?></span>
			<?php else : ?>
				<span class="helo-badge helo-badge--muted"><?php esc_html_e( 'Off', 'helo-smtp' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( ! Helo_Settings::get( 'turnstile_debug_log' ) ) : ?>
			<div class="helo-card__body">
				<div class="helo-alert helo-alert--info">
					<span class="helo-alert__icon" aria-hidden="true">&#9432;</span>
					<div>
						<?php esc_html_e( 'The debug log is off, which is the right default — it stores identifying data. Switch it on under Recording when you need to trace a specific block.', 'helo-smtp' ); ?>
						<a href="<?php echo esc_url( Helo_Admin::security_url() ); ?>"><?php esc_html_e( 'Bot protection settings', 'helo-smtp' ); ?></a>
					</div>
				</div>
			</div>
		<?php elseif ( empty( $log ) ) : ?>
			<div class="helo-card__body">
				<p class="helo-muted"><?php esc_html_e( 'Recording is on, but nothing has been checked yet.', 'helo-smtp' ); ?></p>
			</div>
		<?php else : ?>
			<table class="helo-table">
				<thead>
					<tr>
						<th scope="col" style="width:160px"><?php esc_html_e( 'When', 'helo-smtp' ); ?></th>
						<th scope="col" style="width:110px"><?php esc_html_e( 'Outcome', 'helo-smtp' ); ?></th>
						<th scope="col" style="width:150px"><?php esc_html_e( 'IP', 'helo-smtp' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Page', 'helo-smtp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $helo_item ) : ?>
						<tr>
							<td class="helo-mono helo-nowrap"><?php echo esc_html( $helo_item['date'] ? mysql2date( 'j M H:i:s', $helo_item['date'] ) : '' ); ?></td>
							<td>
								<?php if ( ! empty( $helo_item['success'] ) ) : ?>
									<span class="helo-badge helo-badge--ok"><?php esc_html_e( 'Verified', 'helo-smtp' ); ?></span>
								<?php else : ?>
									<span class="helo-badge helo-badge--err" title="<?php echo esc_attr( Helo_Turnstile::explain( $helo_item['error'] ) ); ?>"><?php esc_html_e( 'Blocked', 'helo-smtp' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="helo-mono"><?php echo esc_html( $helo_item['ip'] ); ?></td>
							<td>
								<span class="helo-mono"><?php echo esc_html( $helo_item['page'] ); ?></span>
								<?php if ( empty( $helo_item['success'] ) && ! empty( $helo_item['error'] ) ) : ?>
									<span class="helo-table__sub"><?php echo esc_html( $helo_item['error'] ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="helo-card__foot">
				<p><?php esc_html_e( 'Oldest entries drop off automatically once there are more than 50.', 'helo-smtp' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="helo_turnstile_reset_log">
					<?php wp_nonce_field( 'helo_turnstile_reset_log' ); ?>
					<?php submit_button( __( 'Clear log', 'helo-smtp' ), 'secondary', 'submit', false, array( 'onclick' => $helo_confirm_log ) ); ?>
				</form>
			</div>
		<?php endif; ?>
	</div>
</div>
