<?php
/**
 * Email log: message list on the left, reading pane on the right.
 *
 * @package Helo
 * @var array       $rows     Log rows.
 * @var int         $total    Total matching rows.
 * @var string      $search   Current search term.
 * @var int         $paged    Current page.
 * @var int         $per_page Rows per page.
 * @var object|null $log      Selected message, if any.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="helo-toolbar">
	<form method="get" class="helo-search">
		<input type="hidden" name="page" value="<?php echo esc_attr( Helo_Admin::SLUG ); ?>">
		<input type="hidden" name="tab" value="logs">
		<label for="helo-search" class="screen-reader-text"><?php esc_html_e( 'Search emails', 'helo-smtp' ); ?></label>
		<input type="search" name="s" id="helo-search" value="<?php echo esc_attr( $search ); ?>"
			placeholder="<?php esc_attr_e( 'Search recipient or subject…', 'helo-smtp' ); ?>">
		<?php submit_button( __( 'Search', 'helo-smtp' ), 'secondary', '', false ); ?>
		<?php if ( '' !== $search ) : ?>
			<a class="button-link" href="<?php echo esc_url( Helo_Admin::log_url() ); ?>">
				<?php esc_html_e( 'Clear', 'helo-smtp' ); ?>
			</a>
		<?php endif; ?>
	</form>

	<span class="helo-result-count">
		<?php
		printf(
			/* translators: %s: number of emails. */
			esc_html( _n( '%s email', '%s emails', $total, 'helo-smtp' ) ),
			esc_html( number_format_i18n( $total ) )
		);
		?>
	</span>
</div>

<?php if ( empty( $rows ) ) : ?>

	<div class="helo-card">
		<div class="helo-empty">
			<span class="dashicons dashicons-email-alt"></span>
			<h3>
				<?php
				echo '' !== $search
					? esc_html__( 'No emails match that search', 'helo-smtp' )
					: esc_html__( 'Nothing logged yet', 'helo-smtp' );
				?>
			</h3>
			<p>
				<?php
				echo '' !== $search
					? esc_html__( 'Try a different recipient or subject.', 'helo-smtp' )
					: esc_html__( 'Messages appear here as soon as the site sends one. Send a test to try it.', 'helo-smtp' );
				?>
			</p>
		</div>
	</div>

<?php else : ?>

	<div class="helo-card helo-split">
		<div class="helo-split__list">
			<?php foreach ( $rows as $helo_row ) : ?>
				<?php
				$helo_selected = $log && (int) $log->id === (int) $helo_row->id;
				$helo_failed   = 'sent' !== $helo_row->status;
				?>
				<a class="helo-msg" href="<?php echo esc_url( Helo_Admin::log_url( $search, $paged, $helo_row->id ) ); ?>"
					<?php echo $helo_selected ? 'aria-current="true"' : ''; ?>>
					<span class="helo-msg__top">
						<span class="helo-msg__subject">
							<span class="helo-dot helo-dot--<?php echo $helo_failed ? 'err' : 'ok'; ?>"
								title="<?php echo esc_attr( $helo_failed ? $helo_row->error : __( 'Sent', 'helo-smtp' ) ); ?>"></span>
							<?php echo esc_html( '' !== $helo_row->subject ? $helo_row->subject : __( '(no subject)', 'helo-smtp' ) ); ?>
						</span>
						<span class="helo-msg__time"><?php echo esc_html( mysql2date( 'j M H:i', $helo_row->created_at ) ); ?></span>
					</span>
					<span class="helo-msg__to"><?php echo esc_html( $helo_row->to_email ); ?></span>
				</a>
			<?php endforeach; ?>

			<?php
			$helo_pages = (int) ceil( $total / max( 1, $per_page ) );

			if ( $helo_pages > 1 ) :
				?>
				<div class="helo-pagination helo-pagination--list">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								// paginate_links() substitutes %#%, so the page slot is built by hand.
								'base'      => Helo_Admin::url(
									array_filter(
										array(
											'tab'   => 'logs',
											's'     => '' !== $search ? rawurlencode( $search ) : null,
											'paged' => '%#%',
										)
									)
								),
								'format'    => '',
								'current'   => $paged,
								'total'     => $helo_pages,
								'prev_text' => '&larr;',
								'next_text' => '&rarr;',
								'mid_size'  => 1,
								'end_size'  => 1,
							)
						)
					);
					?>
				</div>
				<?php
			endif;
			?>
		</div>

		<div class="helo-split__pane">
			<?php require HELO_PATH . 'admin/views/message.php'; ?>
		</div>
	</div>

<?php endif; ?>
