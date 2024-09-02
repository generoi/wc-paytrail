<?php if ( $bypass_provider ) { ?>
	<div id="paytrail-ppa-apple-pay-info"><?php esc_html_e( 'Click the button below to complete your purchase with Apple Pay:', 'wc-paytrail' ); ?></div>

	<div id="paytrail-ppa-apple-pay-container">
		<div id="paytrail-ppa-apple-pay-button">
			<?php foreach ( $bypass_provider->parameters as $key => $field ) { ?>
				<input type="hidden" name="<?php echo esc_attr( $field->name ); ?>" value="<?php echo esc_attr( $field->value ); ?>" />
			<?php } ?>
		</div>
	</div>

	<script type="text/javascript">
		jQuery(document).ready(function($) {
			const applePayButton = checkoutFinland.applePayButton;

			if (applePayButton.canMakePayment()) {
				applePayButton.mount('#paytrail-ppa-apple-pay-button', function(redirectUrl) {
					setTimeout(function() {
						window.location.replace(redirectUrl);
					}, 1500);
				});
			}
		});
	</script>

	<style>
		/* Reorder elements so that Apple Pay button is the first to show */
		body.woocommerce-order-pay div[data-block-name="woocommerce/classic-shortcode"],
		body.woocommerce-order-pay div.woocommerce {
			display: flex;
			flex-flow: column;
		}

		/* Blocks based checkout */
		body.woocommerce-order-pay div[data-block-name="woocommerce/classic-shortcode"] > * { order: 1; }
		body.woocommerce-order-pay div[data-block-name="woocommerce/classic-shortcode"] ul.order_details { order: 3; }
		body.woocommerce-order-pay div[data-block-name="woocommerce/classic-shortcode"] #paytrail-ppa-apple-pay-container { order: 1; }

		/* Classic checkout */
		body.woocommerce-order-pay div.woocommerce > * { order: 1; }
		body.woocommerce-order-pay div.woocommerce ul.order_details { order: 3; }
		body.woocommerce-order-pay div.woocommerce #paytrail-ppa-apple-pay-container { order: 1; }
	</style>
<?php } else if ( isset( $payment->href ) && ! empty( $payment->href ) ) { ?>
	<?php
		// If for some reason bypass provider is not available just redirect to Paytrail
		// payment wall
	?>
	<script>window.location.replace( "<?php echo $payment->href; ?>" );</script>
<?php } else { ?>
	<p><?php _e( 'Error occurred. Please try again shortly.', 'wc-paytrail' ); ?></p>
<?php } ?>
