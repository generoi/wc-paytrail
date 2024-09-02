<?php if ( $add_card_url ) { ?>
	<div class="wc-paytrail-ppa-token-add-new">
		<a href="<?php echo esc_url( $add_card_url ); ?>" class="button" id="wc-paytrail-ppa-add-card-link">
			<span class="dashicons dashicons-plus"></span>
			<?php echo esc_html( __( 'Add payment card', 'wc-paytrail' ) ); ?>
		</a>
	</div>
<?php } ?>

<?php if ( isset( $change_payment_method ) && $change_payment_method && isset( $has_other_subs ) && $has_other_subs ) { ?> 
	<div class="wc-paytrail-change-token-notice">
		<?php _e( 'Please note that payment method will be updated to all of your subscriptions.', 'wc-paytrail' ); ?>
	</div>
<?php } ?>
