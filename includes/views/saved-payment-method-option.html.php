<li class="woocommerce-SavedPaymentMethods-token <?php echo ( $token->is_default() ? 'selected' : '' ); ?>">
	<input
		id="wc-<?php echo esc_attr( $gateway_id ); ?>-payment-token-<?php echo esc_attr( $token->get_id() ); ?>"
		type="radio"
		name="wc-<?php echo esc_attr( $gateway_id ); ?>-payment-token"
		value="<?php echo esc_attr( $token->get_id() ); ?>"
		style="width:auto;"
		class="woocommerce-SavedPaymentMethods-tokenInput"
		<?php checked( $token->is_default(), true ); ?>
	/>

	<img src="<?php echo esc_url( $token_img ); ?>" class="wc-<?php echo esc_attr( $gateway_id ); ?>-payment-token-img" /> 

	<div class="wc-<?php echo esc_attr( $gateway_id ); ?>-payment-token-digits">
		<span class="wc-<?php echo esc_attr( $gateway_id ); ?>-payment-token-bullets"><?php echo esc_html( $bullets ); ?></span>
		<span class="wc-<?php echo esc_attr( $gateway_id ); ?>-payment-token-last4"><?php echo esc_html( $last4 ); ?></span>
	</div>
	<div class="wc-<?php echo esc_attr( $gateway_id ); ?>-payment-token-expiry"><?php echo esc_html( $expiry ); ?></div>
</li>
