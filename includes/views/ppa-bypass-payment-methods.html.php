<?php do_action( 'wc_paytrail_ppa_before_payment_methods', $self ); ?>

<input type="hidden" name="wc_paytrail_ppa_preselected_method" value="0" />

<?php if ( $terms && $display_terms === 'above' ) { ?>
	<div class="wc-paytrail-ppa-terms">
		<?php echo $terms; ?>
	</div>
<?php } ?>

<div class="wc-paytrail-ppa-methods-container wc-paytrail-classic">
	<div class="wc-paytrail-ppa-methods">
		<?php foreach ( $groups as $group ) { ?>
			<?php if ( ! empty( $group->providers ) ) { ?>
				<div class="wc-paytrail-ppa-method-group <?php echo ( $group->id === 'applepay' ? 'wc-paytrail-hide-ap' : '' ); ?>" id="wc-paytrail-ppa-method-group-<?php echo esc_attr( $group->id ); ?>">
					<div class="wc-paytrail-ppa-method-group-title"><?php echo esc_html( $group->name ); ?></div>
					
					<?php foreach ( $group->providers as $provider ) { ?>
						<div class="wc-paytrail-ppa-method" id="wc-paytrail-ppa-method-<?php echo $provider->id; ?>">
							<?php do_action( 'wc_paytrail_ppa_before_payment_method', $provider ); ?>
							<div class="wc-paytrail-ppa-method-icon-container">
								<img src="<?php echo esc_attr( $provider->svg ); ?>" class="wc-paytrail-ppa-method-icon" />
							</div>
							<input type="radio" name="wc_paytrail_ppa_preselected_method" value="<?php echo $provider->id; ?>" id="wc-paytrail-ppa-method-radio-<?php echo $provider->id; ?>" />
							<?php do_action( 'wc_paytrail_ppa_after_payment_method', $provider ); ?>
						</div>
					<?php } ?>
				</div>
			<?php } ?>
		<?php } ?>
	</div>
</div>

<?php if ( $terms && $display_terms === 'below' ) { ?>
	<div class="wc-paytrail-ppa-terms">
		<?php echo $terms; ?>
	</div>
<?php } ?>

<?php do_action( 'wc_paytrail_ppa_after_payment_methods', $self ); ?>
