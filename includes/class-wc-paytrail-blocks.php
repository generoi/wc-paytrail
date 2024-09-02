<?php

/**
 * Paytrail blocks integration
 */
final class WC_Gateway_Paytrail_Blocks_Support extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_Paytrail_Ppa
	 */
	private $gateway = false;

	/**
	 * Payment method ID.
	 *
	 * @var string
	 */
	protected $name = 'paytrail_ppa';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_paytrail_ppa_settings', [] );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->get_setting( 'enabled' ) === 'yes';
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path = 'assets/js/build/blocks.js';
		$script_asset_path = WC_PAYTRAIL_PATH . 'assets/js/build/blocks.asset.php';
		$script_asset = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => [],
				'version' => WC_PAYTRAIL_VERSION
			);
		$script_url = WC_PAYTRAIL_PLUGIN_URL . $script_path;

		wp_register_script(
			'wc-paytrail-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		return [ 'wc-paytrail-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$mode = $this->get_setting( 'mode' );
		$display_terms = $this->get_setting( 'display_terms', '' );
		$gateway = $this->get_gateway();

		$providers = false;
		if ( $mode === 'bypass' ) {
			$providers = $this->get_providers();
		}

		$terms = false;
		if ( in_array( $display_terms, [ 'above', 'below'], true ) ) {
			$terms = $this->get_terms();
		}

		return [
			'title' => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'mode' => $mode,
			'display_terms' => $display_terms,
			'terms' => $terms,
			'providers' => $providers,
			'supports' => array_filter( $gateway->supports, [ $gateway, 'supports' ] ),
			'contains_subscription' => $gateway->cart_contains_subscription(),
			'order_button_text' => $gateway->order_button_text,
		];
	}

	/**
	 * Get gateway object
	 * 
	 * @return WC_Gateway_Paytrail_Ppa
	 */
	private function get_gateway() {
		if ( $this->gateway ) {
			return $this->gateway;
		}

		$gateways = WC()->payment_gateways->payment_gateways();
		
		if ( isset( $gateways['paytrail_ppa'] ) ) {
			$this->gateway = $gateways['paytrail_ppa'];
		} else {
			error_log( "Paytrail PPA gateway not loaded correctly." );
		}

		return $this->gateway;
	}

	/**
	 * Get terms
	 */
	private function get_terms() {
		$gateway = $this->get_gateway();

		return $gateway->get_terms( $gateway->get_cart_total() );
	}

	/**
	 * Get payment method providers
	 */
	private function get_providers() {
		$gateway = $this->get_gateway();

		return $gateway->get_grouped_providers( $gateway->get_cart_total() );
	}
}
