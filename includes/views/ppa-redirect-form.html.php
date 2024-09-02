<?php if ( $bypass_provider ) { ?>
	<form id="wc-paytrail-ppa-payment-form" method="POST" action="<?php echo $bypass_provider->url; ?>">
		<?php foreach ( $bypass_provider->parameters as $key => $field ) { ?>
			<input type="hidden" name="<?php echo esc_attr( $field->name ); ?>" value="<?php echo esc_attr( $field->value ); ?>" />
		<?php } ?>

		<p><?php esc_html_e( 'Redirecting...', 'wc-paytrail' ); ?></p>
		<p><?php esc_html_e( 'If nothing happens in a few seconds, please click the button below.', 'wc-paytrail' ); ?></p>

		<p><input type="submit" value="<?php esc_html_e( 'Submit', 'wc-paytrail' ); ?>" /></p>
	</form>
	<script>document.getElementById( "wc-paytrail-ppa-payment-form" ).submit();</script>
<?php } else if ( isset( $payment->href ) && ! empty( $payment->href ) ) { ?>
	<?php
		// If for some reason bypass provider is not available just redirect to Paytrail
		// payment wall
	?>
	<script>window.location.replace( "<?php echo $payment->href; ?>" );</script>
<?php } else { ?>
	<p><?php _e( 'Error occurred. Please try again shortly.', 'wc-paytrail' ); ?></p>
<?php } ?>
