<div class="wc-paytrail-desc-container wc-paytrail-classic">
	<?php if ( $terms && $display_terms === 'above' ) { ?>
		<div class="wc-paytrail-ppa-terms wc-paytrail-ppa-terms-above">
			<?php echo $terms; ?>
		</div>
	<?php } ?>

	<?php if ( $description ) { ?>
		<div class="wc-paytrail-ppa-description">
			<?php echo wpautop( wptexturize( $description ) ); ?>
		</div>
	<?php } ?>

	<?php if ( isset( $card_payment ) && $card_payment ) { ?>
		<?php echo $card_payment; ?>
	<?php } ?>

	<?php if ( $terms && $display_terms === 'below' ) { ?>
		<div class="wc-paytrail-ppa-terms wc-paytrail-ppa-terms-below">
			<?php echo $terms; ?>
		</div>
	<?php } ?>
</div>

