<?php
/**
 * Reading pane. Included by logs.php inside the split layout.
 *
 * @package Helo
 * @var object|null $log Selected message.
 */

defined( 'ABSPATH' ) || exit;

if ( ! $log ) {
	?>
	<div class="helo-empty">
		<span class="dashicons dashicons-email-alt"></span>
		<h3><?php esc_html_e( 'Nothing selected', 'helo-smtp' ); ?></h3>
		<p><?php esc_html_e( 'Pick a message from the list to read it.', 'helo-smtp' ); ?></p>
	</div>
	<?php
	return;
}

$helo_is_html = false !== stripos( $log->content_type, 'html' );
?>

<div class="helo-reader__head">
	<div class="helo-reader__title">
		<h2><?php echo esc_html( '' !== $log->subject ? $log->subject : __( '(no subject)', 'helo-smtp' ) ); ?></h2>
		<p><?php echo esc_html( mysql2date( 'j F Y, H:i:s', $log->created_at ) ); ?></p>
	</div>
	<div class="helo-row">
		<?php if ( 'sent' === $log->status ) : ?>
			<span class="helo-badge helo-badge--ok"><?php esc_html_e( 'Sent', 'helo-smtp' ); ?></span>
		<?php else : ?>
			<span class="helo-badge helo-badge--err"><?php esc_html_e( 'Failed', 'helo-smtp' ); ?></span>
		<?php endif; ?>
		<a class="button" href="<?php echo esc_url( Helo_Admin::action_url( 'helo_resend', $log->id ) ); ?>"
			onclick="return confirm('<?php echo esc_js( __( 'Send this email again?', 'helo-smtp' ) ); ?>')">
			<?php esc_html_e( 'Resend', 'helo-smtp' ); ?>
		</a>
		<a class="button" href="<?php echo esc_url( Helo_Admin::action_url( 'helo_delete', $log->id ) ); ?>"
			onclick="return confirm('<?php echo esc_js( __( 'Delete this log entry?', 'helo-smtp' ) ); ?>')">
			<?php esc_html_e( 'Delete', 'helo-smtp' ); ?>
		</a>
	</div>
</div>

<dl class="helo-meta">
	<dt><?php esc_html_e( 'To', 'helo-smtp' ); ?></dt>
	<dd><?php echo nl2br( esc_html( $log->to_email ) ); ?></dd>

	<dt><?php esc_html_e( 'Content type', 'helo-smtp' ); ?></dt>
	<dd><span class="helo-mono"><?php echo esc_html( $log->content_type ); ?></span></dd>

	<?php if ( '' !== trim( $log->headers ) ) : ?>
		<dt><?php esc_html_e( 'Headers', 'helo-smtp' ); ?></dt>
		<dd><pre><?php echo esc_html( $log->headers ); ?></pre></dd>
	<?php endif; ?>

	<?php if ( '' !== trim( $log->attachments ) ) : ?>
		<dt><?php esc_html_e( 'Attachments', 'helo-smtp' ); ?></dt>
		<dd><pre><?php echo esc_html( $log->attachments ); ?></pre></dd>
	<?php endif; ?>

	<?php if ( '' !== $log->error ) : ?>
		<dt><?php esc_html_e( 'Error', 'helo-smtp' ); ?></dt>
		<dd><div class="helo-error-box"><?php echo esc_html( $log->error ); ?></div></dd>
	<?php endif; ?>
</dl>

<?php /* Radios drive the width switcher below with CSS alone — no script. */ ?>
<input type="radio" name="helo-width" id="helo-width-desktop" class="helo-vis" checked>
<input type="radio" name="helo-width" id="helo-width-mobile" class="helo-vis">

<div class="helo-preview">
	<div class="helo-preview__bar">
		<span><?php esc_html_e( 'Preview', 'helo-smtp' ); ?></span>
		<?php if ( $helo_is_html ) : ?>
			<div class="helo-seg">
				<label for="helo-width-desktop"><?php esc_html_e( 'Desktop', 'helo-smtp' ); ?></label>
				<label for="helo-width-mobile"><?php esc_html_e( 'Mobile', 'helo-smtp' ); ?></label>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $helo_is_html ) : ?>
		<div class="helo-preview__stage">
			<?php /* sandbox="" blocks scripts and same-origin access, so a hostile body cannot touch wp-admin. */ ?>
			<iframe class="helo-preview__frame" title="<?php esc_attr_e( 'Email preview', 'helo-smtp' ); ?>"
				sandbox="" srcdoc="<?php echo esc_attr( $log->body ); ?>"></iframe>
		</div>

		<details class="helo-source">
			<summary><?php esc_html_e( 'HTML source', 'helo-smtp' ); ?></summary>
			<pre><?php echo esc_html( $log->body ); ?></pre>
		</details>
	<?php else : ?>
		<pre class="helo-plain"><?php echo esc_html( $log->body ); ?></pre>
	<?php endif; ?>
</div>
