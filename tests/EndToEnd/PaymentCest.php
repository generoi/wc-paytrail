<?php

use \Tests\Support\EndToEndTester;
use \PHPUnit\Framework\Assert;

class PaymentCest
{
    /**
     * Test that manual invoice capture is used
     */
    public function shouldUseManualInvoiceCapture(EndToEndTester $I) {
        $this->setup();

        $order = $this->createOrder();

        $this->setPaytrailOption('mode', 'bypass');
        $this->setPaytrailOption('invoice_capture', 'status_wc-completed');
        $this->setPaytrailOption('invoice_capture_initial_status', 'wc-on-hold');

        // Visit "Pay for order" page
        $payUrl = $this->getPayForOrderUrl($order);
        $I->amOnPage($payUrl);

        // Select Walley
        $I->selectOption('input[name=wc_paytrail_ppa_preselected_method]', 'walleyb2c');
        
        // Click "Proceed to Pay"
        $I->click('button#place_order');

        // Wait until we are on Walley page
        $I->waitForElement('.collector-checkout-iframe');

        // Check that we have recorded manual invoice capture
        $order = $this->reloadOrder($order);
        Assert::assertEquals('created', $order->get_meta('_paytrail_ppa_invoice_manual_capture'));

        // Fake finalized payment callback
        $callbackUrl = $this->getCallbackUrl('pending', 'walleyb2c', $order);
        $I->amOnPage($callbackUrl);

        // Check that we were redirected to "Thank you for your page" and order status is "On hold"
        $I->waitForText('Thank you. Your order has been received.', 10);
        $order = $this->reloadOrder($order);
        Assert::assertEquals('pending', $order->get_meta('_paytrail_ppa_invoice_manual_capture'));
        Assert::assertEquals('on-hold', $order->get_status());

        // Check that we respect initial status setting
        $this->setPaytrailOption('invoice_capture_initial_status', 'wc-processing');
        $order->update_meta_data('_paytrail_ppa_invoice_manual_capture', 'created');
        $order->save();
        $callbackUrl = $this->getCallbackUrl('pending', 'walleyb2c', $order);
        $I->amOnPage($callbackUrl);
        $I->waitForText('Thank you. Your order has been received.', 10);
        $order = $this->reloadOrder($order);
        Assert::assertEquals('pending', $order->get_meta('_paytrail_ppa_invoice_manual_capture'));
        Assert::assertEquals('processing', $order->get_status());
    }

    /**
     * Test that manual invoice capture is NOT used
     */
    public function shouldNotUseManualInvoiceCapture(EndToEndTester $I) {
        $this->setup();

        $order = $this->createOrder();

        $this->setPaytrailOption('mode', 'bypass');
        $this->setPaytrailOption('invoice_capture', '');
        $this->setPaytrailOption('invoice_capture_initial_status', 'wc-processing');

        // Visit "Pay for order" page
        $payUrl = $this->getPayForOrderUrl($order);
        $I->amOnPage($payUrl);

        // Select Walley
        $I->selectOption('input[name=wc_paytrail_ppa_preselected_method]', 'walleyb2c');
        
        // Click "Proceed to Pay"
        $I->click('button#place_order');

        // Wait until we are on Walley page
        $I->waitForElement('.collector-checkout-iframe');

        // Check that we haven't recorded manual invoice capture
        $order = $this->reloadOrder($order);
        Assert::assertEmpty($order->get_meta('_paytrail_ppa_invoice_manual_capture'));

        // Fake finalized payment callback
        $callbackUrl = $this->getCallbackUrl('ok', 'walleyb2c', $order);
        $I->amOnPage($callbackUrl);

        // Check that we were redirected to "Thank you for your page" and order status is "Processing"
        $I->waitForText('Thank you. Your order has been received.', 10);
        $order = $this->reloadOrder($order);
        Assert::assertEmpty($order->get_meta('_paytrail_ppa_invoice_manual_capture'));
        Assert::assertEquals('processing', $order->get_status());
        Assert::assertNotEmpty($order->get_date_paid());
    }

    /**
     * Test "delayed" callback
     */
    public function shouldDelayedOrder(EndToEndTester $I) {
        $this->setup(true);

        $order = $this->createOrder();

        $callbackUrl = $this->getCallbackUrl('delayed', 'osuuspankki', $order);
        $I->amOnPage($callbackUrl);

        // Check that we were redirected to "Thank you for your page" and order status is "On hold"
        $I->waitForText('Thank you. Your order has been received.', 10);
        $order = $this->reloadOrder($order);
        Assert::assertEquals('on-hold', $order->get_status());

        // Check that order note is correct
        $this->assertOrderNote($I, $order, 'Paytrail payment DELAYED with OP');
        $this->assertOrderNoteCount($I, $order, 1);
    }

    /**
     * Test "pending -> fail" workflow
     */
    public function shouldPendingFailOrder(EndToEndTester $I) {
        $this->setup(true);

        $order = $this->createOrder();
        $order->update_meta_data( '_paytrail_ppa_invoice_manual_capture', 'created' );
        $order->save();
        Assert::assertEquals('pending', $order->get_status());

        // Order should be failed even for processing orders when using Walley
        $this->setPaytrailOption('invoice_capture', 'manual');
        $this->setPaytrailOption('invoice_capture_initial_status', 'wc-processing');

        $callbackUrl = $this->getCallbackUrl('pending', 'walleyb2c', $order);
        $I->amOnPage($callbackUrl);
        $order = $this->reloadOrder($order);
        Assert::assertEquals('processing', $order->get_status());
        $this->assertOrderNote($I, $order, 'Pending Walley B2C invoice capture. Transaction ID');
        $this->assertOrderNoteCount($I, $order, 1);

        // Call failure
        $callbackUrl = $this->getCallbackUrl('fail', 'walleyb2c', $order);
        $I->amOnPage($callbackUrl);
        $order = $this->reloadOrder($order);
        Assert::assertEquals('failed', $order->get_status());
        Assert::assertEquals('failed', $order->get_meta('_paytrail_ppa_invoice_manual_capture'));
        $this->assertOrderNote($I, $order, 'Paytrail payment FAILED with Walley B2C. Transaction ID');
        $this->assertOrderNoteCount($I, $order, 2);
    }

    /**
     * Test "pending -> ok" workflow
     */
    public function shouldPendingOkOrder(EndToEndTester $I) {
        $this->setup(true);

        $order = $this->createOrder();
        Assert::assertEquals('pending', $order->get_status());

        $callbackUrl = $this->getCallbackUrl('pending', 'osuuspankki', $order);
        $I->amOnPage($callbackUrl);

        // Check that we initially have "On hold" status
        $order = $this->reloadOrder($order);
        Assert::assertEquals('on-hold', $order->get_status());
        $this->assertOrderNote($I, $order, 'Paytrail payment PENDING with OP');
        $this->assertOrderNoteCount($I, $order, 1);

        // Make callback with "ok" status
        $callbackUrl = $this->getCallbackUrl('ok', 'osuuspankki', $order);
        $I->amOnPage($callbackUrl);
        $order = $this->reloadOrder($order);
        Assert::assertEquals('processing', $order->get_status());
        $this->assertOrderNote($I, $order, 'Paytrail payment completed with OP');
        $this->assertOrderNote($I, $order, 'Payment complete.');
        $this->assertOrderNoteCount($I, $order, 3);
    }

    /**
     * Test "ok -> fail" workflow (should not do anything)
     */
    public function shouldOkFailOrder(EndToEndTester $I) {
        $this->setup(true);

        $order = $this->createOrder();
        Assert::assertEquals('pending', $order->get_status());

        $callbackUrl = $this->getCallbackUrl('ok', 'osuuspankki', $order);
        $I->amOnPage($callbackUrl);

        // Check that we initially have "Processing" status
        $order = $this->reloadOrder($order);
        Assert::assertEquals('processing', $order->get_status());

        // Make callback with "fail" status
        $callbackUrl = $this->getCallbackUrl('fail', 'osuuspankki', $order);
        $I->amOnPage($callbackUrl);

        // In this instance we cannot fail the order because we have a valid
        // payment already. Sometimes Paytrail might do fail callback even
        // for paid orders if there were multiple payment attempts and first
        // attempt resulted in a delayed fail. We cannot trust that callbacks
        // are always in the correct order
        $order = $this->reloadOrder($order);
        Assert::assertEquals('processing', $order->get_status());
    }

    /**
     * Test that invoice status is displayed in admin
     */
    public function shouldDisplayInvoiceStatus(EndToEndTester $I) {
        $this->setup(true);
        
        $order = $this->createOrder();
        $order->update_meta_data( '_paytrail_ppa_invoice_manual_capture', 'pending' );
        $order->update_meta_data( '_wc_paytrail_provider_id', 'walleyb2c' );
        $order->update_meta_data( '_wc_paytrail_provider_title', 'Walley B2C' );
        $order->update_meta_data( '_wc_paytrail_transaction_id', 'test-' . uniqid() );
        $order->update_status( 'on-hold' );
        $order->save();

        // Log in
        $I->loginAsAdmin();
        $I->amOnAdminPage('/');
        $I->see('Dashboard');

        // Check that invoice status is displayed
        $orderUrl = sprintf('/wp-admin/admin.php?page=wc-orders&action=edit&id=%d', $order->get_id());
        $I->amOnPage($orderUrl);
        $I->see('Walley B2C status:');
        $I->see('Pending capture', '.wc-paytrail-invoice-status');

        // Failed
        $order->update_meta_data( '_paytrail_ppa_invoice_manual_capture', 'failed' );
        $order->save();
        $I->amOnPage($orderUrl);
        $I->see('Failed', '.wc-paytrail-invoice-status');

        // Captured
        $order->update_meta_data( '_paytrail_ppa_invoice_manual_capture', 'captured' );
        $order->save();
        $I->amOnPage($orderUrl);
        $I->see('Captured', '.wc-paytrail-invoice-status');

        // Change provider and ensure we only show status for
        // correct methods
        $order->update_meta_data( '_wc_paytrail_provider_id', 'osuuspankki' );
        $order->save();
        $I->amOnPage($orderUrl);
        $I->dontSee('Captured', '.wc-paytrail-invoice-status');
    }

    /**
     * Test that invoice capture works on status change
     */
    public function shouldCaptureInvoiceOnStatus(EndToEndTester $I) {
        $this->setup(true);

        $order = $this->createOrder();

        $this->setPaytrailOption('mode', 'bypass');
        $this->setPaytrailOption('invoice_capture', 'status_wc-completed');
        $this->setPaytrailOption('invoice_capture_initial_status', 'wc-processing');

        $order->update_meta_data('_paytrail_ppa_invoice_manual_capture', 'created');
        $order->save();

        // Fake finalized payment callback
        $callbackUrl = $this->getCallbackUrl('pending', 'walleyb2c', $order);
        $I->amOnPage($callbackUrl);

        // Check that we were redirected to "Thank you for your page" and order status is "Processing"
        $I->waitForText('Thank you. Your order has been received.', 10);
        $order = $this->reloadOrder($order);
        Assert::assertEquals('pending', $order->get_meta('_paytrail_ppa_invoice_manual_capture'));
        Assert::assertEquals('processing', $order->get_status());

        // Log in to admin and check we have indicator for pending capture
        $I->loginAsAdmin();
        $I->amOnAdminPage('/');
        $I->see('Dashboard');
        $I->amOnPage(sprintf('/wp-admin/admin.php?page=wc-orders&action=edit&id=%d', $order->get_id()));
        $I->see('Walley B2C status:');
        $I->see('Pending capture', '.wc-paytrail-invoice-status');
        $I->dontSee('Failed to capture Walley B2C invoice: Airplane Mode is enabled (airplane_mode_enabled)', '.order_notes .note_content');

        // Change status to "Completed"
        $I->selectOption('select#order_status', 'wc-completed');
        $I->click('button.save_order');

        // Since we don't have real invoice to be captured, the capture
        // will fail. We will check that the capture action was triggered
        // from order notes
        $I->amOnPage(sprintf('/wp-admin/admin.php?page=wc-orders&action=edit&id=%d', $order->get_id()));
        $I->see('Failed to capture Walley B2C invoice: Airplane Mode is enabled (airplane_mode_enabled)', '.order_notes .note_content');
        $I->see('Pending capture', '.wc-paytrail-invoice-status'); // This needs to stay as pending since it failed
    }

    /**
     * Test that invoice capture works on status change
     * immediately after checkout if capture status
     * is "Processing" and initial order status is set to
     * "Processing" as well
     */
    public function shouldCaptureInvoiceOnCheckoutStatus(EndToEndTester $I) {
        $this->setup(true);

        $order = $this->createOrder();

        $this->setPaytrailOption('mode', 'bypass');
        $this->setPaytrailOption('invoice_capture', 'status_wc-processing');
        $this->setPaytrailOption('invoice_capture_initial_status', 'wc-processing');

        $order->update_meta_data('_paytrail_ppa_invoice_manual_capture', 'created');
        $order->save();

        // Fake finalized payment callback
        $callbackUrl = $this->getCallbackUrl('pending', 'walleyb2c', $order);
        $I->amOnPage($callbackUrl);

        // Check that we were redirected to "Thank you for your page" and order status is "Processing"
        $I->waitForText('Thank you. Your order has been received.', 10);
        $order = $this->reloadOrder($order);
        Assert::assertEquals('processing', $order->get_status());

        // Log in to admin and check we have note about failed capture
        $I->loginAsAdmin();
        $I->amOnAdminPage('/');
        $I->see('Dashboard');
        $I->amOnPage(sprintf('/wp-admin/admin.php?page=wc-orders&action=edit&id=%d', $order->get_id()));
        $I->see('Failed to capture Walley B2C invoice: Airplane Mode is enabled', '.order_notes .note_content');
        $I->see('Pending Walley B2C invoice capture. Transaction ID', '.order_notes .note_content');
        $I->see('Pending capture', '.wc-paytrail-invoice-status'); // This needs to stay as pending since it failed
        $this->assertOrderNoteCount($I, $order, 2);
    }

    /**
     * Test that invoice capture works via admin AJAX function
     */
    public function shouldCaptureInvoiceViaAdmin(EndToEndTester $I) {
        $this->setup(true);

        $order = $this->createOrder();

        $this->setPaytrailOption('mode', 'bypass');
        $this->setPaytrailOption('invoice_capture', 'manual');
        $this->setPaytrailOption('invoice_capture_initial_status', 'wc-processing');

        $order->update_meta_data('_paytrail_ppa_invoice_manual_capture', 'created');
        $order->save();

        // Fake finalized payment callback
        $callbackUrl = $this->getCallbackUrl('pending', 'walleyb2c', $order);
        $I->amOnPage($callbackUrl);

        // Check that we were redirected to "Thank you for your page" and order status is "Processing"
        $I->waitForText('Thank you. Your order has been received.', 10);
        $order = $this->reloadOrder($order);
        Assert::assertEquals('pending', $order->get_meta('_paytrail_ppa_invoice_manual_capture'));
        Assert::assertEquals('processing', $order->get_status());

        // Log in to admin and check we have indicator for pending capture
        $I->loginAsAdmin();
        $I->amOnAdminPage('/');
        $I->see('Dashboard');
        $I->amOnPage(sprintf('/wp-admin/admin.php?page=wc-orders&action=edit&id=%d', $order->get_id()));
        $I->see('Walley B2C status:');
        $I->see('Pending capture', '.wc-paytrail-invoice-status');
        $I->dontSee('Failed to capture Walley B2C invoice: Airplane Mode is enabled (airplane_mode_enabled)', '.order_notes .note_content');

        // Check that AJAX call works
        $I->click('a#wc-paytrail-capture-order');
        $I->waitForText('Airplane Mode is enabled', 5, '.wc-paytrail-error');
        $this->assertOrderNote($I, $order, 'Failed to capture Walley B2C invoice: Airplane Mode is enabled (airplane_mode_enabled)');
    }

    /**
     * Test concurrency handling
     */
    public function shouldHandleConcurrency(EndToEndTester $I) {
        $this->setup(true);

        $order = $this->createOrder();

        $callbackUrl = $this->getCallbackUrl('ok', 'osuuspankki', $order);

        // Add drag to simulate slow environments
        activate_plugin('order-complete-drag/order-complete-drag.php');

        $this->asyncRequest(site_url($callbackUrl));
        $this->asyncRequest(site_url($callbackUrl));
        $this->asyncRequest(site_url($callbackUrl));
        $this->asyncRequest(site_url($callbackUrl));
        $this->asyncRequest(site_url($callbackUrl));

        // Wait for all async requests to be completed
        $I->wait(1);

        // Check that we actually got 5 callbacks
        // This will be counted in order-complete-drag plugin
        $counter = get_option( 'wc_paytrail_api_request_counter', 0 );
        Assert::assertEquals(5, $counter);

        $order = $this->reloadOrder($order);
        Assert::assertEquals('processing', $order->get_status());
        Assert::assertEquals('yes', $order->get_meta('_wc_paytrail_payment_completed'));

        // There should be only two notes
        $this->assertOrderNoteCount($I, $order, 2);
        $this->assertOrderNote($I, $order, 'Paytrail payment completed with OP. Transaction ID');

        // Disable drag
        deactivate_plugins(['order-complete-drag/order-complete-drag.php']);
    }

    /**
     * Test duplicate pending callbacks
     */
    public function shouldHandleDoublePendingCallbacks(EndToEndTester $I) {
        $this->setup(true);

        $order = $this->createOrder();

        $callbackUrl = $this->getCallbackUrl('pending', 'walleyb2c', $order);

        $I->amOnPage($callbackUrl);
        $I->amOnPage($callbackUrl);

        $order = $this->reloadOrder($order);
        Assert::assertEquals('on-hold', $order->get_status());

        // There should be only two notes
        $this->assertOrderNoteCount($I, $order, 1);
        $this->assertOrderNote($I, $order, 'Paytrail payment PENDING with Walley B2C. Transaction ID');
    }

    /**
     * Test duplicate delayed callbacks
     */
    public function shouldHandleDoubleDelayedCallbacks(EndToEndTester $I) {
        $this->setup(true);

        $order = $this->createOrder();

        $callbackUrl = $this->getCallbackUrl('delayed', 'walleyb2c', $order);

        $I->amOnPage($callbackUrl);
        $I->amOnPage($callbackUrl);

        $order = $this->reloadOrder($order);
        Assert::assertEquals('on-hold', $order->get_status());

        // There should be only two notes
        $this->assertOrderNoteCount($I, $order, 1);
        $this->assertOrderNote($I, $order, 'Paytrail payment DELAYED with Walley B2C. Transaction ID');
    }

    /**
     * Test duplicate failed callbacks
     */
    public function shouldHandleDoubleFailedCallbacks(EndToEndTester $I) {
        $this->setup(true);

        $this->setPaytrailOption('invoice_capture', 'manual');
        $this->setPaytrailOption('invoice_capture_initial_status', 'wc-on-hold');

        $order = $this->createOrder();
        $order->update_meta_data( '_paytrail_ppa_invoice_manual_capture', 'created' );
        $order->save();

        // Mark the order as pending first
        $callbackUrl = $this->getCallbackUrl('pending', 'walleyb2c', $order);
        $I->amOnPage($callbackUrl);
        $order = $this->reloadOrder($order);
        Assert::assertEquals('on-hold', $order->get_status());
        Assert::assertEquals('pending', $order->get_meta('_paytrail_ppa_invoice_manual_capture'));

        $callbackUrl = $this->getCallbackUrl('fail', 'walleyb2c', $order);

        $I->amOnPage($callbackUrl);
        $I->amOnPage($callbackUrl);

        $order = $this->reloadOrder($order);
        Assert::assertEquals('failed', $order->get_status());

        // There should be only two notes
        $this->assertOrderNoteCount($I, $order, 2);
        $this->assertOrderNote($I, $order, 'Paytrail payment FAILED with Walley B2C. Transaction ID:');
        $this->assertOrderNote($I, $order, 'Pending Walley B2C invoice capture. Transaction ID:');
    }

    /**
     * Test tampered callback
     */
    public function shouldPreventTampering(EndToEndTester $I) {
        $this->setup(true);

        $order = $this->createOrder();

        # Let's try to switch "fail" status to "ok" but keep signature
        # as-is
        $callbackUrl = $this->getCallbackUrl('fail', 'osuuspankki', $order, [
            'checkout-status' => 'ok'
        ]);
        $I->amOnPage($callbackUrl);
        $I->waitForText('Signature validation failed');

        $order = $this->reloadOrder($order);
        Assert::assertEquals('pending', $order->get_status());
    }

    /**
     * Test that we can proceed to Paytrail and that the preselected
     * payment method is used
     */
    public function shouldProceedToPaytrail(EndToEndTester $I) {
        $this->setup();

        $order = $this->createOrder();

        // Enable bypass
        $this->setPaytrailOption('mode', 'bypass');
        $payUrl = $this->getPayForOrderUrl($order);

        $I->amOnPage($payUrl);
        $I->see('Bank payment methods');

        // Select Visa
        $I->selectOption('input[name=wc_paytrail_ppa_preselected_method]', 'creditcard:0');

        // Click "Proceed to Pay"
        $I->click('button#place_order');
        
        // Assert that we have been redirected to the Visa payment page
        $I->waitForElementVisible('#purchase-info-row', 10);
        $currentUrl = $I->grabFullUrl();
        Assert::assertStringContainsString('v1-hub-staging.sph-test-solinor.com', $currentUrl);
        $I->see('Please fill in the information on your card');
        Assert::assertEquals('pending', $order->get_status());
    }

    /**
     * Test that order is completed with OP
     */
    public function shouldCompleteOrder(EndToEndTester $I) {
        $this->setup();

        $order = $this->createOrder();

        // Enable bypass
        $this->setPaytrailOption('mode', 'bypass');
        $payUrl = $this->getPayForOrderUrl($order);

        // Change preselected method to OP and ensure the order is directly
        // marked as processing (OP doesn't require confirmation)
        $I->amOnPage($payUrl);
        $I->see('Bank payment methods');
        $I->selectOption('input[name=wc_paytrail_ppa_preselected_method]', 'osuuspankki');
        $I->click('button#place_order');
        $I->waitForText('Thank you. Your order has been received.', 10);
        $order = $this->reloadOrder($order);
        Assert::assertEquals('processing', $order->get_status());

        // Check that the plugin is saving transaction data
        Assert::assertEquals('osuuspankki', $order->get_meta('_wc_paytrail_provider_id'));
        Assert::assertNotEmpty($order->get_meta('_wc_paytrail_transaction_id'));
    }

    /**
     * Test that payment page bypass is used when enabled
     */
    public function shouldUseBypass(EndToEndTester $I) {
        $this->setup();

        $order = $this->createOrder();
        $payUrl = $this->getPayForOrderUrl($order);

        // Bypass enabled
        $this->setPaytrailOption( 'mode', 'bypass' );
        $I->amOnPage( $payUrl );
        $I->see('Pay for order');
        $I->see('Paytrail');
        $I->see('Bank payment methods');

        // Disable bypass
        $this->setPaytrailOption( 'mode', 'default' );
        $I->amOnPage( $payUrl );
        $I->see('Pay for order');
        $I->see('Paytrail');
        $I->dontSee('Bank payment methods');
    }

    /**
     * @assert
     * 
     * Check that order has certain note
     */
    protected function assertOrderNote($I, $order, $noteContent) {
        $notes = $I->grabEntriesFromDatabase('wp_comments', [
            'comment_post_ID' => $order->get_id(),
            'comment_type' => 'order_note'
        ]);

        $has = false;
        foreach ($notes as $note) {
            if (strpos($note['comment_content'], $noteContent) !== false) {
                $has = true;
                break;
            }
        }

        Assert::assertTrue($has, sprintf('Order does not have note with content: %s', $noteContent));
    }

    /**
     * @assert
     * 
     * Check that order has certain note count
     */
    protected function assertOrderNoteCount($I, $order, $count) {
        $notes = $I->grabEntriesFromDatabase('wp_comments', [
            'comment_post_ID' => $order->get_id(),
            'comment_type' => 'order_note'
        ]);

        Assert::assertCount($count, $notes);
    }

    /**
     * @helper
     * 
     * Reload order
     */
    protected function reloadOrder($order) {
        $cache = wc_get_container()->get(\Automattic\WooCommerce\Caches\OrderCache::class);
        $cache->remove($order->get_id());

        return wc_get_order($order);
    }

    /**
     * @helper
     * 
     * Async GET request using wp_remote_get
     */
    protected function asyncRequest($url) {
        wp_remote_get($url, [
            'timeout' => 0.01,
            'blocking' => false,
        ]);
    }

    /**
     * @helper
     * 
     * Get Paytrail callback URL to fake order success / pending / failure
     */
    protected function getCallbackUrl($status, $paymentMethod, $order, $extraParams = []) {
        $baseUrl = '/wc-api/WC_Gateway_Paytrail_Ppa/';

        $params = [
            'checkout-account' => '375917',
            'checkout-algorithm' => 'sha256',
            'checkout-amount' => '1',
            'checkout-stamp' => sprintf('%s-%s', $order->get_id(), uniqid()),
            'checkout-reference' => $order->get_id(),
            'checkout-status' => $status,
            'checkout-provider' => $paymentMethod,
            'checkout-transaction-id' => sprintf('test-txn-id-%s', uniqid()),
        ];
        $params['signature'] = $this->calculateHmac($params);
        $params = array_merge($params, $extraParams);

        return sprintf('%s?%s', $baseUrl, http_build_query($params));   
    }

	/**
     * @helper
     * 
	 * Calculate hmac for request
	 */
	private function calculateHmac( $params, $body = '' ) {
		// Keep only checkout- params, more relevant for response validation. Filter query
		// string parameters the same way - the signature includes only checkout- values.
		$included_keys = array_filter( array_keys( $params ), function ( $key ) {
			return preg_match( '/^checkout-/', $key );
		});
	
		// Keys must be sorted alphabetically
		sort( $included_keys, SORT_STRING );
	
		$hmac_payload = array_map(
			function ( $key ) use ( $params ) {
				return join( ':', array( $key, $params[$key] ) );
			},
			$included_keys
		);

		array_push( $hmac_payload, $body );
	
		return hash_hmac( 'sha256', join( "\n", $hmac_payload ), 'SAIPPUAKAUPPIAS' );
	}

    /**
     * @helper
     * 
     * Set Paytrail option
     * 
     * @return void
     */
    protected function setPaytrailOption( $option, $value ) {
        $options = get_option( 'woocommerce_paytrail_ppa_settings', [] );
        $options[$option] = $value;
        update_option( 'woocommerce_paytrail_ppa_settings', $options );
    }

    /**
     * @helper
     * 
     * Get pay for order URL
     * 
     * @return string
     */
    protected function getPayForOrderUrl( $order ) {
        return sprintf( '/checkout/order-pay/%s/?pay_for_order=true&key=%s', $order->get_id(), $order->get_order_key() );
    }

    /**
     * @helper
     * 
     * Create order
     * 
     * @return WC_Order
     */
    protected function createOrder() {
        $order = wc_create_order();
        $order->add_product(wc_get_product(13), 1);
        $order->calculate_totals();
        $order->set_payment_method('paytrail_ppa');
        $order->set_status( 'pending' );
        $order->set_billing_address( [
            'first_name' => 'Matti',
            'last_name' => 'Meikäläinen',
            'address_1' => 'Testikatu 1',
            'postcode' => '00100',
            'country' => 'FI',
            'phone' => '0401234567',
            'email' => 'test@example.com',
        ] );
        $order->set_shipping_address( $order->get_address( 'billing' ) );
        $order->save();

        return $order;
    }

    /**
     * @helper
     * 
     * Setup
     */
    protected function setup($enableAirplaneMode = false) {
        // Do not show admin email confirmation
        update_option( 'admin_email_lifespan', time() + YEAR_IN_SECONDS );

        // Clear comments / order notes cache
        wp_cache_flush();

        // Enable airplane mode to avoid external requests
        if ($enableAirplaneMode) {
            $airplaneMode = Airplane_Mode_Core::getInstance();
            $airplaneMode->enable();
        }
    }
}
