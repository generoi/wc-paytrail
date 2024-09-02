jQuery(document).ready(function($) {
	/**
	 * Display Apple Pay in payment methods
	 */
	function wcPaytrailInitApplePay() {
		if ( $( '.wc-paytrail-classic #wc-paytrail-ppa-method-apple_pay' ).length > 0 && typeof checkoutFinland !== 'undefined' ) {
			const applePayButton = checkoutFinland.applePayButton;
			
			if (applePayButton.canMakePayment()) {
				$( '#wc-paytrail-ppa-method-group-applepay' ).removeClass( 'wc-paytrail-hide-ap' );
			}
		}
	}
	wcPaytrailInitApplePay();

	$( document.body ).on( 'updated_checkout', function() {
		wcPaytrailInitApplePay();
	} );

	/**
	 * When clicking Paytrail card token, add active class and check radio button
	 */
	$( 'form.checkout, form#order_review' ).on( 'click', '#payment .payment_methods .payment_method_paytrail_ppa li.woocommerce-SavedPaymentMethods-token', function( e ) {
		$( '#payment .payment_methods .payment_method_paytrail_ppa li.woocommerce-SavedPaymentMethods-token' ).removeClass( 'selected' );
		$( this ).addClass( 'selected' );
		$( 'input[type="radio"]', this ).prop( 'checked', true ).change();
	} );

	/**
	 * When clicking Paytrail PPA payment method, add active class and check radio button
	 */
	$( 'form.checkout, form#order_review' ).on( 'click', '.wc-paytrail-classic .wc-paytrail-ppa-method', function( e ) {
		$( '.wc-paytrail-ppa-method.selected').removeClass( 'selected' );
		$( this ).addClass( 'selected' );
		$( 'input[type="radio"]', this ).prop( 'checked', true ).change();
	} );
});
