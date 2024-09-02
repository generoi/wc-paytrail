<?php

/**
 * Prevent direct access to the script.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Paytrail_Ppa extends WC_Payment_Gateway {
	public $supports = [
		'products',
		'refunds',
		'tokenization',
		'subscriptions', 
		'subscription_cancellation', 
		'subscription_suspension', 
		'subscription_reactivation',
		'subscription_amount_changes',
		'subscription_payment_method_change',
		'subscription_payment_method_change_customer',
		'subscription_date_changes',
		'multiple_subscriptions',
	];

	private $providers = false;
	public $merchant_id;
	public $merchant_key;
	public $paytrail_language;
	public $mode;
	public $display_terms;
	public $enable_apple_pay;
	public $transaction_settlement_prefix;
	public $transaction_settlement_enable;
	public $polylang_fix;
	public $paytrail_api_url;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id = 'paytrail_ppa';
		#$this->icon = WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/logo.png';
		$this->has_fields = true;
		$this->method_title = __( 'Paytrail', 'wc-paytrail' );
		$this->method_description = '';

		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->merchant_id = $this->get_option( 'merchant_id' );
		$this->merchant_key = html_entity_decode( $this->get_option( 'merchant_key' ) );
		$this->order_button_text = apply_filters( 'wc_paytrail_order_button_text', __( 'Proceed to pay', 'wc-paytrail' ) );
		$this->paytrail_language = $this->get_option( 'language', 'auto' );
		$this->mode = $this->get_option( 'mode' );
		$this->display_terms = $this->get_option( 'display_terms' );
		$this->enable_apple_pay = $this->get_option( 'enable_apple_pay' ) === 'yes';
		$this->transaction_settlement_prefix = $this->get_option( 'transaction_settlement_prefix', '10' );
		$this->transaction_settlement_enable = $this->get_option( 'transaction_settlement_enable', 'no' ) === 'yes';
		$this->polylang_fix = $this->get_option( 'polylang_fix', 'no' ) === 'yes';

		$this->paytrail_api_url = 'https://services.paytrail.com';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ], 10 );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'generate_ap_ver_file' ], 20 );

		add_action( 'woocommerce_api_wc_gateway_paytrail_ppa', [ $this, 'router' ], 10 );

		add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'receipt_page' ] );

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
	}

	/**
	 * Define payment gateway settings
	 */
	public function init_form_fields() {
		// Instructions for activating Apple Pay
		$apple_pay_url = 'https://support.paytrail.com/hc/fi/articles/4412478752017--Uusi-Paytrail-Apple-Pay-';

		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable Paytrail', 'wc-paytrail' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Paytrail', 'wc-paytrail' ),
				'default' => 'no'
			),
			'merchant_id' => array(
				'title' => __( 'Merchant ID', 'wc-paytrail' ),
				'type' => 'text',
				'default' => '', # TEST: 375917
			),
			'merchant_key' => array(
				'title' => __( 'Merchant key', 'wc-paytrail' ),
				'type' => 'text',
				'default' => '', # TEST: SAIPPUAKAUPPIAS
			),
			'mode' => array(
				'title' => __( 'Payment mode', 'wc-paytrail' ),
				'type' => 'select',
				'default' => 'default',
				'options' => array(
					'default' => __( 'Payment page', 'wc-paytrail' ),
					'bypass' => __( 'Payment page bypass', 'wc-paytrail' ),
				)
			),
			'language' => array(
				'title' => __( 'Language', 'wc-paytrail' ),
				'type' => 'select',
				'default' => 'auto',
				'options' => array(
					'auto' => __( 'Site language (automatic)', 'wc-paytrail' ),
					'FI' => __( 'Finnish', 'wc-paytrail' ),
					'SV' => __( 'Swedish', 'wc-paytrail' ),
					'EN' => __( 'English', 'wc-paytrail' )
				)
			),
			'title' => array(
				'title' => __( 'Title', 'wc-paytrail' ),
				'type' => 'text',
				'default' => __( 'Paytrail', 'wc-paytrail' ),
				'description' => __( 'This controls the title which the user sees during checkout.', 'wc-paytrail' ),
				'desc_tip'      => true,
			),
			'description' => array(
				'title' => __( 'Description', 'wc-paytrail' ),
				'type' => 'textarea',
				'default' => '',
				'description' => __( 'This controls the description which the user sees during checkout.', 'wc-paytrail' ),
				'desc_tip' => true,
			),
			'display_terms' => array(
				'title' => __( 'Display terms', 'wc-paytrail' ),
				'type' => 'select',
				'default' => false,
				'options' => array(
					'' => __( 'Do not display', 'wc-paytrail' ),
					'above' => __( 'Above payment methods or description', 'wc-paytrail' ),
					'below' => __( 'Below payment methods or description', 'wc-paytrail' ),
				)
			),
			'apple_pay_title' => [
				'title' => __( 'Apple Pay', 'wc-paytrail' ),
				'type' => 'title',
			],
			'enable_apple_pay' => [
				'title' => __( 'Enable Apple Pay', 'wc-paytrail' ),
				'type' => 'checkbox',
				'default' => 'no',
				'description' => sprintf( __( 'Requires domain validation with Paytrail. <a href="%s" target="_blank">Please see the instructions &raquo;</a>', 'wc-paytrail' ), $apple_pay_url )
			],
			'apple_pay_verification_file' => [
				'title' => __( 'Verification File', 'wc-paytrail' ),
				'type' => 'wc_paytrail_apple_pay_file',
			],
			'transaction_settlement_title' => [
				'title' => __( 'Transaction-specific settlements', 'wc-paytrail' ),
				'type' => 'title',
			],
			'transaction_settlement_enable' => [
				'title' => __( 'Enable transaction-specific settlements', 'wc-paytrail' ),
				'type' => 'checkbox',
				'default' => 'no',
				'description' => __( "Requires activation from Paytrail as well. To activate transaction-specific settlements please contact Paytrail's customer service.", 'wc-paytrail' ),
			],
			'transaction_settlement_prefix' => [
				'title' => __( 'Bank reference prefix', 'wc-paytrail' ),
				'type' => 'text',
				'description' => __( 'Prefix is prepended to the reference calculated from the order number. It should be at least two numbers to conform to the reference number requirements. For example, if prefix is 10 and order number 123, the final reference would be 101239 (prefix + order number + checksum).', 'wc-paytrail' ),
				'default' => '10',
				'class' => 'paytrail-ppa-settlement-field'
			],
		);

		// Polylang compatibility mode
		if ( function_exists( 'pll_current_language' ) ) {
			$this->form_fields['polylang_fix_title'] = [
				'title' => __( 'Polylang', 'wc-paytrail' ),
				'type' => 'title',
			];

			$this->form_fields['polylang_fix'] = [
				'title' => __( 'Enable compatibility mode', 'wc-paytrail' ),
				'type' => 'checkbox',
				'default' => 'no',
				'description' => __( 'Check this if <em>Order received</em> page is in wrong language or it returns <em>404 Not Found</em> error.', 'wc-paytrail' ),
			];
		}
	}

	/**
	 * Generate Apple Pay verification file
	 */
	public function generate_ap_ver_file() {
		if ( $this->get_option( 'enable_apple_pay' ) === 'yes' && isset( $_POST['wc_paytrail_ap_ver_file_generate'] ) && $_POST['wc_paytrail_ap_ver_file_generate'] ) {
			Markup_Paytrail::generate_ap_ver_file();
		}
	}

	/**
	 * Output html for Apple Pay verification file
	 */
	public function generate_wc_paytrail_apple_pay_file_html( $id, $value ) {
		$status = Markup_Paytrail::ap_ver_file_status();
		$ap_ver_file_url = Markup_Paytrail::ap_ver_file_url();

		ob_start();

		include 'views/apple-pay-file.html.php';

		return ob_get_clean();

	}

	/**
	 * Get merchant ID
	 */
	private function get_merchant_id( $order_id = null ) {
		return intval( apply_filters( 'wc_paytrail_ppa_merchant_id', $this->merchant_id, $order_id ) );
	}

	/**
	 * Get merchant key
	 */
	private function get_merchant_key( $order_id = null ) {
		return apply_filters( 'wc_paytrail_ppa_merchant_key', $this->merchant_key, $order_id );
	}

	/**
	 * Fields for preselecting payment method on checkout.
	 */
	public function payment_fields() {
		$cart_total = $this->get_cart_total();
		$display_terms = $this->display_terms;
		$terms = $this->get_terms( $cart_total );

		if ( is_add_payment_method_page() ) {
			// Do not show any fields if we are on add payment method page
			// Adding card will be done in add_payment_method() when user submits the page 
			return;
		} else if ( $this->cart_contains_subscription() ) {
			$add_card_url = false;
			if ( is_user_logged_in() ) {
				$args = [
					'add_card' => '1'
				];
	
				$change_payment_method = filter_input( INPUT_GET, 'change_payment_method' );
				$has_other_subs = false;
				if ( $change_payment_method ) {
					$args['change_payment_method'] = '1';
	
					if ( function_exists( 'wcs_get_users_subscriptions' ) && get_current_user_id() ) {
						$has_other_subs = count( wcs_get_users_subscriptions( get_current_user_id() ) ) > 1;
					}
				}
	
				$add_card_url = $this->get_api_url( false, $args );
			}

			ob_start();

			if ( count( $this->get_tokens() ) > 0 ) {
				$this->saved_payment_methods();
			}

			include 'views/ppa-token-payment.html.php';

			$card_payment = ob_get_clean();

			$description = $this->get_description();

			ob_start();

			include 'views/ppa-description.html.php';

			$output = ob_get_clean();

			echo $output;
		} else if ( 'bypass' === $this->mode && is_checkout() ) {
			$groups = $this->get_grouped_providers( $cart_total );

			$self = $this;

			ob_start();

			include 'views/ppa-bypass-payment-methods.html.php';

			$output = ob_get_clean();

			echo $output;
		} else {
			$description = $this->get_description();

			ob_start();

			include 'views/ppa-description.html.php';

			$output = ob_get_clean();

			echo $output;
		}
	}

	/**
	 * Get cart total
	 */
	public function get_cart_total() {
		$order_id = absint( get_query_var( 'order-pay' ) );

		// Gets order total from "pay for order" page
		if ( 0 < $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order ) {
				return intval( round( $order->get_total( 'edit' ) * 100 ) );
			}
		} else if ( WC()->cart && is_callable( array( WC()->cart, 'get_total' ) ) ) {
			return intval( round( WC()->cart->get_total( 'edit' ) * 100 ) );
		}

		return 0;
	}

	/**
	 * Get terms
	 */
	public function get_terms( $cart_total ) {
		if ( ! $this->display_terms ) {
			return false;
		}

		$providers = $this->get_providers( $cart_total );

		if ( $providers && is_object( $this->providers ) && isset( $this->providers->terms ) ) {
			return $this->providers->terms;
		}

		return false;
	}

	/**
	 * Get plugin version
	 */
	private function get_platform_name() {
		return 'woocommerce-bitbot';
	}

	/**
	 * Get payment methods for bypass
	 */
	public function get_providers( $amount ) {
		if ( $this->providers ) {
			return $this->providers;
		}

		// Make request
		$url = sprintf( 'merchants/grouped-payment-providers?amount=%s&language=%s', $amount, $this->get_language() );
		$response = $this->request( $url, 'GET', '', [], [], null, false );

		if ( ! is_wp_error( $response ) ) {
			$response_code = (string) wp_remote_retrieve_response_code( $response );

			if ( $response_code === '200' ) {
				$body = wp_remote_retrieve_body( $response );
				$body_obj = json_decode( $body );

				$this->providers = $body_obj;

				return $this->providers;
			}
		}

		return false;
	}

	/**
	 * Get grouped and sorted providers
	 */
	public function get_grouped_providers( $amount ) {
		$providers = $this->get_providers( $amount );

		if ( ! $providers ) {
			return false;
		}

		// Key groups by ID
		$keyed_groups = [];
		foreach ( $providers->groups as $group ) {
			$keyed_groups[$group->id] = $group;
		}
		$groups = $keyed_groups;

		// Rewrite credit card IDs since it's 'creditcard' for
		// Visa, Visa Electron and MC and cannot be be differentiated
		// in frontend otherwise
		if ( isset( $groups['creditcard'] ) ) {
			foreach ( $groups['creditcard']->providers as $key => $provider ) {
				if ( $provider->id === 'creditcard' ) {
					$groups['creditcard']->providers[$key]->id = sprintf( '%s:%s', $provider->id, $key );
				}
			}
		}

		// Add Apple Pay
		if ( $this->enable_apple_pay ) {
			$ap_method = (object) [
				'id' => 'apple_pay',
				'name' => 'Apple Pay',
				'group' => 'applepay',
				'icon' => sprintf( '%sassets/images/apple-pay.svg', WC_PAYTRAIL_PLUGIN_URL ),
				'svg' => sprintf( '%sassets/images/apple-pay.svg', WC_PAYTRAIL_PLUGIN_URL ),
			];

			$groups['applepay'] = (object) [
				'id' => 'applepay',
				'name' => __( 'Apple Pay', 'wc-paytrail' ),
				'providers' => [ $ap_method ],
			];
		}

		// Sort providers
		$groups = $this->sort_groups( $groups );

		// Deprecated hook notification
		apply_filters_deprecated( 'wc_paytrail_bypass_methods', [[]], '2.5.0', 'wc_paytrail_grouped_providers' );

		return array_values( apply_filters( 'wc_paytrail_grouped_providers', $groups, $this ) );
	}

	/**
	 * Sort payment method groups
	 */
	private function sort_groups( $groups ) {
		// Set initial weight for each group
		$weight = 5;
		foreach ( $groups as $key => $group ) {
			$groups[$key]->weight = $weight;
			$weight += 5;
		}

		// Set Apple Pay just before mobile methods or after bank methods
		if ( isset( $groups['applepay'] ) ) {
			// We need to take into account that bank or mobile won't be available for all merchants
			if ( isset( $groups['mobile'] ) ) {
				$groups['applepay']->weight = $groups['mobile']->weight - 1;
			} else if ( isset( $groups['bank'] ) ) {
				$groups['applepay']->weight = $groups['bank']->weight + 1;
			} else {
				// No bank or mobile methods, just set weight to 1 since there will be
				// only credit cards and/or invoicing methods
				$groups['applepay']->weight = 1;
			}
		}

		uasort( $groups, function( $a, $b ) {
			return $a->weight <=> $b->weight;
		} );

		return $groups;
	}

	/**
	 * Process payment by redirecting to payment page
	 *
	 * @param int $order_id
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$change_payment_method = filter_input( INPUT_GET, 'change_payment_method' );

		if ( $change_payment_method ) {
			return $this->process_payment_method_change( $order );
		}

		if ( $this->order_contains_subscription( $order_id ) || $this->is_token_payment() ) {
			return $this->process_token_payment( $order );
		}

		if ( 'bypass' === $this->mode ) {
			$bypass_method = $this->save_preselected_payment_method( $order );

			if ( ! empty( $bypass_method ) ) {
				return [
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url( true )
				];
			}
		}

		try {
			$payment = $this->create_payment( $order );
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			return [
				'result' => 'failure',
				'redirect' => '',
			];
		}

		$order->add_order_note( __( 'Customer was redirected to Paytrail.', 'wc-paytrail' ) );

		return [
			'result' => 'success',
			'redirect' => $payment->href,
		];
	}

	/**
	 * Process token payment
	 * 
	 * @param WC_Order $order
	 * @param string|int $token_id
	 * 
	 * @return array|void
	 */
	private function process_token_payment( $order ) {
		$token_id = false;
		if ( isset( $_POST["wc-{$this->id}-payment-token"] ) ) {
			$token_id = absint( $_POST["wc-{$this->id}-payment-token"] );
		}

		if ( empty( $token_id ) || $token_id === 'new' ) {
			return $this->process_pay_tokenize( $order );
		}

		/**
		 * @var WC_Payment_Token_CC $token
		 */
		$token = WC_Payment_Tokens::get( $token_id );

		if ( ! $token ) {
			wc_add_notice( sprintf( __( 'Invalid token (%s). Please try again or add another payment card.', 'wc-paytrail' ), __( 'token not found', 'wc-paytrail' ) ), 'error' );

			return [
				'result' => 'failure',
				'redirect' => '',
			];
		}

		// Ensure tokens belong to the current user
		if ( $token->get_user_id() !== get_current_user_id() ) {
			wc_add_notice( sprintf( __( 'Invalid token (%s). Please try again or add another payment card.', 'wc-paytrail' ), __( 'user does not match', 'wc-paytrail' ) ), 'error' );

			return [
				'result' => 'failure',
				'redirect' => '',
			];
		}

		// Add token to order and subscription
		$order->add_payment_token( $token );
		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );
			foreach ( $subscriptions as $subscription ) {
				$subscription->add_payment_token( $token );
			}
		}

		try {
			$result = $this->charge_token( $order, $token->get_token(), 'cit' );
		} catch( \Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			return [
				'result' => 'failure',
				'redirect' => '',
			];
		}

		if ( is_array( $result ) ) {
			if ( $result['result'] === 'success' ) {
				$txn_id = $result['body']->transactionId;
				$order->payment_complete( $txn_id );
				$order->add_order_note( sprintf( __( 'Paytrail token payment completed with %s ending in %s. Transaction ID: %s', 'wc-paytrail' ), ucfirst( $token->get_card_type() ), $token->get_last4(), $txn_id ) );
	
				// Set selected token as default
				WC_Payment_Tokens::set_users_default( get_current_user_id(), $token->get_id() );
	
				WC()->cart->empty_cart();
	
				return [
					'result' => 'success',
					'redirect' => $this->get_return_url( $order )
				];
			} else if ( $result['result'] === '3ds' ) {
				return [
					'result' => 'success',
					'redirect' => $result['redirect'],
				];
			} else if ( $result['result'] === 'failed' ) {
				$error_msg = $result['error'];

				$order->add_order_note( sprintf( __( 'Error processing token payment with %s %s: %s', 'wc-paytrail' ), ucfirst( $token->get_card_type() ), $token->get_last4(), $error_msg ) );
		
				// Show more detailed error msg for admin
				if ( current_user_can( 'manage_woocommerce' ) ) {
					wc_add_notice( sprintf( __( 'Unable to complete the payment. Please try again or choose another payment method. (%s)', 'wc-paytrail' ), $error_msg ), 'error' );
				} else {
					wc_add_notice( __( 'Unable to complete the payment. Please try again or choose another payment method. (2)', 'wc-paytrail' ), 'error' );
				}
			}
		}

		return [
			'result' => 'failure',
			'redirect' => '',
		];
	}

	/**
	 * Process Pay & Tokenize
	 */
	private function process_pay_tokenize( $order ) {
		try {
			$payment = $this->create_payment( $order, 'tokenization/pay-and-add-card' );
		} catch ( Exception $e ) {
			wc_add_notice( __( 'Unable to complete the payment. Please try again or choose another payment method. (2)', 'wc-paytrail' ), 'error' );

			return [
				'result' => 'failure',
				'redirect' => '',
			];
		}

		if ( $payment && isset( $payment->redirectUrl ) ) {
			return [
				'result' => 'success',
				'redirect' => $payment->redirectUrl,
			];
		}

		wc_add_notice( __( 'Unknown error in Pay & Tokenize', 'wc-paytrail' ), 'error' );

		return [
			'result' => 'failure',
			'redirect' => '',
		];
	}

	/**
	 * Process payment method change
	 */
	private function process_payment_method_change( $order ) {
		$token_id = filter_input( INPUT_POST, "wc-{$this->id}-payment-token" );

		if ( empty( $token_id ) || $token_id === 'new' ) {
			wc_add_notice( __( 'Please add payment card before proceeding.', 'wc-paytrail' ), 'error' );
			return;
		}

		$token = WC_Payment_Tokens::get( $token_id );

		if ( ! $token ) {
			wc_add_notice( sprintf( __( 'Invalid token (%s). Please try again or add another payment card.', 'wc-paytrail' ), __( 'token not found', 'wc-paytrail' ) ), 'error' );
			return;
		}

		// Ensure tokens belong to the current user
		if ( $token->get_user_id() !== get_current_user_id() ) {
			wc_add_notice( sprintf( __( 'Invalid token (%s). Please try again or add another payment card.', 'wc-paytrail' ), __( 'user does not match', 'wc-paytrail' ) ), 'error' );
			return;
		}

		// Set user default token to this one
		WC_Payment_Tokens::set_users_default( get_current_user_id(), $token->get_id() );

		return [
			'result' => 'success',
			'redirect' => $this->get_return_url( $order )
		];
	}

	/**
	 * Create payment via Paytrail Payment API
	 * 
	 * @param WC_Order $order
	 * 
	 * @return object
	 */
	private function create_payment( $order, $endpoint = 'payments' ) {
		$body = $this->payment_args( $order );

		// Store reference for future uses
		$order->update_meta_data( '_paytrail_ppa_reference', $body['reference'] );

		// Store information that transaction-specific settlement was used
		if ( $this->transaction_settlement_enable ) {
			$order->update_meta_data( '_paytrail_ppa_transaction_settlement', true );
		}

		$response = $this->request( $endpoint, 'POST', json_encode( $body ), [], [], $order->get_id(), true );

		$error_msg = false;

		// Check response
		if ( ! is_wp_error( $response ) ) {
			$response_code = (string) wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$body_obj = json_decode( $body );

			if ( in_array( $response_code, [ '200', '201' ], true ) ) {
				// Validate that request originated from Paytrail (signature is valid)
				$headers_obj = wp_remote_retrieve_headers( $response );
				$headers = $headers_obj->getAll();
				
				$response_hmac = $this->calculate_hmac( $headers, $body, $order->get_id() );
				if ( $response_hmac === $headers['signature'] ) {
					$order->save();

					return $body_obj;
				}
			} else if ( in_array( $response_code, [ '400', '500', '401' ], true ) ) {
				if ( is_object( $body_obj ) && isset( $body_obj->status, $body_obj->message ) && $body_obj->status === 'error' ) {
					$error_msg = $body_obj->message;

					if ( isset( $body_obj->meta ) && is_array( $body_obj->meta ) ) {
						$error_msg = sprintf( '%s %s', $error_msg, implode( '. ', $body_obj->meta ) );
					}
				} else {
					$error_msg = __( 'unknown error', 'wc-paytrail' );
				}
			} else {
				$error_msg = sprintf( __( 'invalid response code %s', 'wc-paytrail' ), $response_code );
			}
		} else {
			$error_msg = $response->get_error_message();
		}

		if ( $error_msg ) {
			$order->add_order_note( sprintf( __( 'Error processing payment: %s', 'wc-paytrail' ), $error_msg ) );
		}

		// Show more detailed error msg for admin
		if ( current_user_can( 'manage_woocommerce' ) ) {
			throw new Exception( sprintf( __( 'Unable to complete the payment. Please try again or choose another payment method. (%s)', 'wc-paytrail' ), $error_msg ) );
		} else {
			throw new Exception( __( 'Unable to complete the payment. Please try again or choose another payment method. (2)', 'wc-paytrail' ) );
		}
	}

	/**
	 * Save preselected payment method if bypass is enabled.
	 * 
	 * @param WC_Order $order
	 * 
	 * @return string
	 */
	public function save_preselected_payment_method( $order ) {
		$method = false;

		if ( isset( $_POST['wc_paytrail_ppa_preselected_method'] ) ) {
			$method = $_POST['wc_paytrail_ppa_preselected_method'];
		}

		// Special processing for credit cards
		if ( strpos( $method, 'creditcard' ) !== false ) {
			$method_ids = explode( ':', $method );
			$method = reset( $method_ids );
		}

		$order->update_meta_data( '_wc_paytrail_ppa_preselected_method', $method );
		$order->save();

		return $method;
	}

	/**
	 * Calculate hmac for request
	 */
	private function calculate_hmac( $params, $body = '', $order_id = null ) {
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
	
		return hash_hmac( 'sha256', join( "\n", $hmac_payload ), $this->get_merchant_key( $order_id ) );
	}

	/**
	 * Payment request body
	 * 
	 * @param WC_Order $order
	 * @param WC_Payment_Token_CC $token
	 * 
	 * @return array
	 */
	private function payment_args( $order, $token = false ) {
		$body = [];

		$reference = strval( $order->get_order_number() );

		// Calculate bank reference for transaction-specific settlements
		if ( $this->transaction_settlement_enable ) {
			$reference = $this->calculate_reference( $reference );
		}

		$body['stamp'] = sprintf( '%d-%s', $order->get_id(), uniqid() );
		$body['reference'] = $reference;
		$body['amount'] = intval( round( $order->get_total() * 100 ) );
		$body['currency'] = 'EUR';
		$body['language'] = $this->get_language();
		$body['orderId'] = strval( $order->get_id() );
		$body['items'] = $this->get_item_args( $order );
		$body['customer'] = array(
			'email' => $order->get_billing_email(),
			'firstName' => $order->get_billing_first_name(),
			'lastName' => $order->get_billing_last_name(),
			'phone' => $order->get_billing_phone(),
			'vatId' => '',
		);
		$body['deliveryAddress'] = array(
			'streetAddress' => substr( trim( sprintf( '%s %s', $order->get_shipping_address_1(), $order->get_shipping_address_2() ) ), 0, 50 ),
			'postalCode' => $order->get_shipping_postcode(),
			'city' => substr( $order->get_shipping_city(), 0, 30 ),
			'county' => $order->get_shipping_state(),
			'country' => $order->get_shipping_country(),
		);
		$body['invoicingAddress'] = array(
			'streetAddress' => substr( trim( sprintf( '%s %s', $order->get_billing_address_1(), $order->get_billing_address_2() ) ), 0, 50 ),
			'postalCode' => $order->get_billing_postcode(),
			'city' => substr( $order->get_billing_city(), 0, 30 ),
			'county' => $order->get_billing_state(),
			'country' => $order->get_billing_country(),
		);
		$body['redirectUrls'] = array(
			'success' => $this->get_api_url( $order ),
			'cancel' => $this->get_api_url( $order ),
		);
		$body['callbackUrls'] = array(
			'success' => $this->get_api_url( $order ),
			'cancel' => $this->get_api_url( $order ),
		);
		$body['callbackDelay'] = 10; // Avoid simultaneous requests from both customer and Paytrail server to the thank you page which would lead to duplicate payment confirmations

		// Add token
		if ( $token ) {
			$body['token'] = $token;
		}

		// Allow other plugins to alter this data
		$body = apply_filters( 'wc_paytrail_ppa_payment_args', $body, $order );

		return $body;
	}

	/**
	 * Calculate bank reference
	 */
	private function calculate_reference( $base ) {
		$base = sprintf( '%s%s', $this->transaction_settlement_prefix, $base );
		$base = trim( str_replace( ' ', '', $base ) );
		$base = str_split( $base );
		$reversed_base = array_reverse( $base );

		$weights = array( 7, 3, 1, 7, 3, 1, 7, 3, 1, 7, 3, 1, 7, 3, 1, 7, 3, 1, 7 );

		$sum = 0;
		for ( $i = 0; $i < count( $reversed_base ); $i++ ) {
			$coefficient = array_shift( $weights );
			$sum += intval( $reversed_base[$i] ) * $coefficient;
		}

		$checksum = ( $sum % 10 == 0 ) ? 0 : ( 10 - $sum % 10 );

		$reference = sprintf( '%s%d', implode( '', $base ), $checksum );

		return $reference;
	}

	/**
	 * Get payment page language
	 */
	private function get_language() {
		// auto_wpml and auto_polylang are for legacy support, not anymore in this
		// plugin
		if ( in_array( $this->paytrail_language, [ 'auto', 'auto_wpml', 'auto_polylang' ], true ) ) {
			$locale = strtolower( get_locale() );

			switch ( $locale ) {
				case 'fi':
				case 'fi_fi':
					return 'FI';
				case 'sv':
				case 'sv_se':
					return 'SV';
				default:
					return 'EN';
			}
		}

		if ( in_array( $this->paytrail_language, [ 'FI', 'SV', 'EN' ], true ) ) {
			return $this->paytrail_language;
		}

		// Fallback
		return 'FI';
	}

	/**
	 * Get order items
	 * 
	 * @param WC_Order $order
	 */
	private function get_item_args( $order ) {
		$line_items = [];
		$round = false;

		// Add product line items
		foreach ( $order->get_items() as $item ) {
			/**
			 * @var WC_Order_Item_Product $item
			 */
			$product = false;
			if ( is_callable( [ $item, 'get_product' ] ) ) {
				$product = $item->get_product();
			}

			// Calculate tax rate for the item
			$total_without_tax = $order->get_item_total( $item, false, false );
			$tax = $this->get_item_tax( $item );
			$tax_percent = $total_without_tax ? ( $tax / $total_without_tax ) * 100 : 0;

			$title = $item->get_name();
			$title = substr( $title, 0, 1000 ); // API allows max 1000 chars

			$sku = ( false !== $product ) ? $product->get_sku() : '';
			if ( function_exists( 'mb_strcut' ) ) {
				$sku = mb_strcut( $sku, 0, 100 ); // API allows max 100 chars
			} else {
				$sku = substr( $sku, 0, 100 ); // API allows max 100 chars
			}

			$line_items[] = [
				'unitPrice' => intval( round( $order->get_item_total( $item, true, $round ) * 100 ) ),
				'units' => intval( $item->get_quantity() ),
				'vatPercentage' => round( floatval( $tax_percent ), 1 ),
				'productCode' => $sku,
				'description' => $title,
				'stamp' => $this->item_stamp( $item->get_id(), $order ),
			];
		}

		// Add fees
		foreach ( $order->get_fees() as $item ) {
			$total_without_tax = $order->get_item_total( $item, false, false );
			$tax = $this->get_item_tax( $item );
			$tax_percent = $total_without_tax ? ( $tax / $total_without_tax ) * 100 : 0;

			$line_items[] = [
				'unitPrice' => intval( round( $order->get_line_total( $item, true, $round ) * 100 ) ),
				'units' => 1,
				'vatPercentage' => round( floatval( $tax_percent ), 1 ),
				'productCode' => '',
				'description' => $item->get_name(),
				'stamp' => $this->item_stamp( $item->get_id(), $order ),
			];
		}

		// Add shipping line items
		foreach ( $order->get_items( [ 'shipping' ] ) as $item ) {
			$total_without_tax = $order->get_item_total( $item, false, false );
			$tax = $this->get_item_tax( $item );
			$tax_percent = $total_without_tax ? ( $tax / $total_without_tax ) * 100 : 0;

			$line_items[] = [
				'unitPrice' => intval( round( $order->get_line_total( $item, true, $round ) * 100 ) ),
				'units' => 1,
				'vatPercentage' => round( floatval( $tax_percent ), 1 ),
				'productCode' => '',
				'description' => $item['name'],
				'stamp' => $this->item_stamp( $item->get_id(), $order ),
			];
		}

		// YITH gift cards
		$yith_gift_cards = $order->get_meta( '_ywgc_applied_gift_cards', true, 'edit' );
		if ( $yith_gift_cards && is_array( $yith_gift_cards ) ) {
			foreach ( $yith_gift_cards as $code => $amount ) {
				$line_items[] = [
					'unitPrice' => absint( intval( round( ( $amount * 100 ) ) ) ) * -1,
					'units' => 1,
					'vatPercentage' => 0,
					'productCode' => '',
					'description' => sprintf( __( 'Gift card: %s', 'wc-paytrail' ), $code ),
				];
			}
		}

		// Smart Coupon giftcards
		foreach ( $order->get_items( [ 'coupon' ] ) as $item ) {
			if ( empty( $item['name'] ) ) {
				continue;
			}

			$coupon = new WC_Coupon( $item['name'] );

			if ( 'smart_coupon' != $coupon->get_discount_type() ) {
				continue;
			}

			$line_items[] = [
				'unitPrice' => absint( intval( round( ( $item['discount_amount'] * 100 ) ) ) ) * -1,
				'units' => 1,
				'vatPercentage' => 0,
				'productCode' => '',
				'description' => $item->get_name(),
			];
		}

		// PW WooCommerce Gift Cards
		// https://wordpress.org/plugins/pw-woocommerce-gift-cards/
		if ( class_exists( 'PW_Gift_Card' ) ) {
			foreach ( $order->get_items( [ 'pw_gift_card' ] ) as $item ) {
				$line_items[] = [
					'unitPrice' => absint( intval( round( ( $item->get_amount() * 100 ) ) ) ) * -1,
					'units' => 1,
					'vatPercentage' => 0,
					'productCode' => '',
					'description' => sprintf( __( 'Gift card: %s', 'wc-paytrail' ), $item->get_card_number() ),
				];
			}
		}

		// Support for WooCommerce Gift Cards
		// https://woocommerce.com/products/gift-cards/
		foreach ( $order->get_items( 'gift_card' ) as $item ) {
			$line_items[] = [
				'unitPrice' => absint( intval( round( ( $item->get_amount() * 100 ) ) ) ) * -1,
				'units' => 1,
				'vatPercentage' => 0,
				'productCode' => '',
				'description' => sprintf( __( 'Gift card: %s', 'wc-paytrail' ), $item->get_name() ),
			];
		}

		// Allow other plugins to modify line items
		$line_items = apply_filters( 'wc_paytrail_ppa_line_item_args', $line_items, $order, $round );

		// Rounding row
		// Add rounding row in case totals do not match exactly
		$diff = $this->totals_diff( $line_items, $order );
		if ( $diff !== 0 ) {
			$line_items[] = [
				'unitPrice' => $diff * -1,
				'units' => 1,
				'vatPercentage' => $this->get_rounding_tax_pct( $line_items, $diff ),
				'productCode' => 'rounding-row',
				'description' => __( 'Rounding', 'wc-paytrail' ),
			];
		}

		return $line_items;
	}

	/**
	 * Get item tax
	 * 
	 * @param WC_Order_Item_Product $item
	 * 
	 * @return float
	 */
	private function get_item_tax( $item ) {
		if ( $item->get_quantity() ) {
			// Preferred method: get tax amount from raw data which is not rounded
			if ( is_callable( [ $item, 'get_taxes' ] ) ) {
				$taxes = $item->get_taxes();

				if ( is_array( $taxes ) && isset( $taxes['total'] ) && is_array( $taxes['total'] ) ) {
					$sanitized_taxes = array_map( 'floatval', $taxes['total'] );

					return array_sum( $sanitized_taxes ) / $item->get_quantity();
				}
			}

			// Fallback method: get tax amount from total tax which might be
			// rounded depending on WooCommerce settings
			if ( is_callable( [ $item, 'get_total_tax' ] ) ) {
				return $item->get_total_tax() / $item->get_quantity();
			}
		}

		return 0;
	}

	/**
	 * Get tax percentage for rounding item
	 * 
	 * Small amounts (< 0.1 €) are likely rounding errors but larger
	 * amounts are likely line items by unsupported 3rd party plugins
	 * (e.g. gift card plugins)
	 * 
	 * For small amounts we use same VAT rate as other line items but for
	 * larger amounts use 0 so the merchant can make final judgment from
	 * the settlement report
	 */
	private function get_rounding_tax_pct( $items, $diff ) {
		if ( $diff < 10 ) {
			// Get VAT totals
			$totals = [];
			foreach ( $items as $item ) {
				if ( $item['unitPrice'] > 0 ) {
					$rate_key = strval( $item['vatPercentage'] );
			
					if ( ! isset($totals[$rate_key] ) ) {
						$totals[$rate_key] = 0;
					}
			
					$totals[$rate_key] += $item['unitPrice'] * $item['units'];
				}
			}
		
			// Sort from lowest to highest
			asort( $totals, SORT_NUMERIC );

			if ( ! empty( $totals ) ) {
				// Use VAT rate with highest order total as the rounding
				// item VAT rate
				end( $totals );
				return round( floatval( key( $totals ) ), 1 );
			}			
		}

		return 0;
	}

	/**
	 * Calculate item stamp
	 * 
	 * @param WC_Order_Item $item
	 * @param WC_Order $order
	 * 
	 * @return string
	 */
	private function item_stamp( $item_id, $order ) {
		// Stamp is item ID + order ID + order key to avoid
		// collisions since stamp needs to be unique
		return sprintf( '%d-%d-%s', $item_id, $order->get_id(), $order->get_order_key() );
	}

	/**
	 * Payment return URL
	 * 
	 * @param WC_Order $order
	 * 
	 * @return string
	 */
	public function get_api_url( $order = false, $extra_args = [] ) {
		$url = WC()->api_request_url( 'WC_Gateway_Paytrail_Ppa' );

		if ( $order ) {
			$url = add_query_arg( 'order_id', $order->get_id(), $url );
		}

		$url = add_query_arg( $extra_args, $url );

		// Polylang compatibility fix
		if ( function_exists( 'pll_current_language' ) && isset( $this->polylang_fix ) && $this->polylang_fix ) {
			$lang_code = pll_current_language();
			$url = str_replace( '/wc-api/WC_Gateway_Paytrail_Ppa', "/{$lang_code}/wc-api/WC_Gateway_Paytrail_Ppa", $url );
		}

		return $url;
	}

	/**
	 * Calculate line item total
	 */
	private function get_line_item_total( $items ) {
		$total = 0;
		foreach ( $items as $item ) {
			$total += $item['unitPrice'] * $item['units'];
		}

		return $total;
	}

	/**
	 * Returns difference between line item totals and order total
	 */
	private function totals_diff( $items, $order ) {
		$line_item_total = $this->get_line_item_total( $items );
		$order_total = intval( round( $order->get_total() * 100 ) );

		return $line_item_total - $order_total;
	}

	/**
	 * Receipt page is shown when payment page bypass is enabled.
	 * 
	 * It will show form which will submit directly to Paytrail
	 */
	public function receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );

		try {
			$payment = $this->create_payment( $order );
		} catch ( Exception $e ) {
			wc_print_notice( $e->getMessage(), 'error' );

			return;
		}

		// Find preselected payment method
		$bypass_method = $order->get_meta( '_wc_paytrail_ppa_preselected_method', true, 'edit' );

		$bypass_provider = false;
		$template = 'views/ppa-redirect-form.html.php';

		if ( $bypass_method === 'apple_pay' && isset( $payment->customProviders->applepay ) ) {
			$bypass_provider = $payment->customProviders->applepay;

			$template = 'views/ppa-redirect-form-apple-pay.html.php';
		} else {
			foreach ( $payment->providers as $key => $provider ) {
				if ( $provider->id === $bypass_method ) {
					$bypass_provider = $provider;
					break;
				}
			}
		}

		$order->add_order_note( sprintf( __( 'Customer was redirected to Paytrail. Preselected payment method: %s', 'wc-paytrail' ), $this->get_provider_title( $bypass_method ) ) );

		ob_start();

		include $template;

		$output = ob_get_clean();

		echo $output;

		/**
		 * Fix for WooCommerce bug which removes existing line items
		 * and create new ones which causes inconsistencies with order
		 * item IDs. This is caused by incorrectly updating the order
		 * from cart after it has already been processed.
		 * 
		 * We could empty cart as an alternative solution but that would
		 * not allow customer to navigate back with browser and retain
		 * the cart
		 * 
		 * Source of bug:
		 * woocommerce/src/StoreApi/Utilities/OrderController.php:107 / update_line_items_from_cart
		 * This should not be run here but it is due to funky WooCommerce
		 * logic but we can avoid it by marking the order as not needing payment
		 * 
		 * This is present at least in WooCommerce 8.6.1
		 */
		add_filter( 'woocommerce_order_needs_payment', '__return_false', 100, 0 );
	}

	/**
	 * Redirect to Paytrail add card / tokenization form
	 */
	private function redirect_to_tokenization_form( $context ) {
		$fields = $this->token_fields( $context );

		$extra_args = [
			'redirection' => 0,
		];

		$response = $this->request( 'tokenization/addcard-form', 'POST', json_encode( $fields ), $extra_args, [], null, false, true );

		if ( ! is_wp_error( $response ) ) {
			$response_code = wp_remote_retrieve_response_code( $response );

			if ( $response_code === 302 ) {
				$headers = wp_remote_retrieve_headers( $response );

				if ( isset( $headers['Location'] ) && ! empty( $headers['Location'] ) ) {
					wp_redirect( $headers['Location'] );
					exit;
				}
			} else {
				$this->log( sprintf( __( 'Failed to redirect to tokenization form: invalid response code %d', 'wc-paytrail' ), $response_code ), 'alert' );
			}
		} else {
			$this->log( sprintf( __( 'Failed to redirect to tokenization form: %s', 'wc-paytrail' ), $response->get_error_message() ), 'alert' );
		}

		wc_add_notice( __( 'Something went wrong. Please try again.', 'wc-paytrail' ), 'error' );

		switch ( $context ) {
			case 'my-account':
				$redirect = wc_get_account_endpoint_url( 'payment-methods' );
				break;
			case 'checkout':
				$redirect = wc_get_checkout_url();
				break;
			case 'add-payment-method':
				$redirect = wc_get_account_endpoint_url( 'add-payment-method' );
				break;
			default:
				$redirect = get_site_url();
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Get fields for add card form
	 */
	private function token_fields( $context ) {
		$success_url = $this->get_api_url( false, [ 'tokenization' => '1', 'success' => '1', 'context' => $context ] );
		$cancel_url = $this->get_api_url( false, [ 'tokenization' => '1', 'cancel' => '1', 'context' => $context ] );

		$datetime = new \DateTime();

		$fields = [
			'checkout-account' => $this->get_merchant_id(),
			'checkout-algorithm' => 'sha256',
			'checkout-method' => 'POST',
			'checkout-nonce' => uniqid( true ),
			'checkout-timestamp' => $datetime->format('Y-m-d\TH:i:s.u\Z'),
			'checkout-redirect-success-url' => $success_url,
			'checkout-redirect-cancel-url' => $cancel_url,
			'language' => $this->get_language(),
		];

		$fields['signature'] = $this->calculate_hmac( $fields, '' );

		return $fields;
	}

	/**
	 * Route API request to correct function
	 */
	public function router() {
		// Add card
		$add_card = filter_input( INPUT_GET, 'add_card' );
		if ( ! empty( $add_card ) ) {
			$this->add_card();
			return;
		}

		// Complete tokenization
		$tokenization = filter_input( INPUT_GET, 'tokenization' );
		if ( ! empty( $tokenization ) ) {
			$this->complete_tokenization();
			return;
		}

		// Complete payment (default)
		$this->complete_payment();
	}

	/**
	 * Complete payment after returning from the offsite payment provider's website.
	 *
	 * After visitor has completed or cancelled the payment, he will
	 * be redirected to this page from Paytrail. The request will be validated
	 * and visitor redirected to the thank you page.
	 *
	 * @return void
	 */
	public function complete_payment() {
		@ob_clean();
		header( 'HTTP/1.1 200 OK' );

		$stamp = filter_input( INPUT_GET, 'checkout-stamp' );

		$order_id = $this->get_order_id_by_stamp( $stamp );

		if ( ! $order_id ) {
			$this->log( sprintf( __( 'Order not found by stamp %s', 'wc-paytrail' ), $stamp ), 'alert' );
		}

		$this->validate_request( $_GET, '', true, $order_id );

		$order = false;

		if ( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order && ! $order->has_status( [ 'processing', 'completed' ] ) && $order->get_meta( '_wc_paytrail_payment_completed', true ) !== 'yes' ) {
				$status = filter_input( INPUT_GET, 'checkout-status' );
				$txn_id = filter_input( INPUT_GET, 'checkout-transaction-id' );
				$provider = filter_input( INPUT_GET, 'checkout-provider' );
				$provider_title = $this->get_provider_title( $provider );

				if ( $status === 'ok' ) {
					$order->update_meta_data( '_wc_paytrail_payment_completed', 'yes' );
					$order->update_meta_data( '_wc_paytrail_version', WC_PAYTRAIL_VERSION );

					$order->payment_complete( $txn_id );
					$order->add_order_note( sprintf( __( 'Paytrail payment completed with %s. Transaction ID: %s', 'wc-paytrail' ), $provider_title, $txn_id ) );

					WC()->cart->empty_cart();
				} else if ( $status === 'pending' || $status === 'delayed' ) {
					$order->update_status( 'on-hold', __( 'Awaiting payment', 'wc-paytrail' ) );
					$order->add_order_note( sprintf( __( 'Paytrail payment PENDING with %s. Transaction ID: %s', 'wc-paytrail' ), $provider_title, $txn_id ) );

					wc_maybe_reduce_stock_levels( $order_id );

					WC()->cart->empty_cart();
				} else {
					$order->update_status( 'failed' );
				}
			}

			// Add token if this was a Pay & Tokenize payment
			$card_token = filter_input( INPUT_GET, 'checkout-card-token' );
			if ( $order && $card_token ) {
				$token = new WC_Payment_Token_CC();
				$token->set_token( $card_token );
				$token->set_gateway_id( $this->id );
				$token->set_last4( filter_input( INPUT_GET, 'partial_pan' ) );
				$token->set_expiry_year( filter_input( INPUT_GET, 'expire_year' ) );
				$token->set_expiry_month( filter_input( INPUT_GET, 'expire_month' ) );
				$token->set_card_type( strtolower( filter_input( INPUT_GET, 'type' ) ) );
				$token->set_user_id( $order->get_user_id( 'edit' ) );
				$token->save();
	
				WC_Payment_Tokens::set_users_default( $order->get_user_id( 'edit' ), $token->get_id() );

				// Add token to order and subscription
				$order->add_payment_token( $token );
				if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
					$subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );
					foreach ( $subscriptions as $subscription ) {
						$subscription->add_payment_token( $token );
					}
				}

				$order->add_order_note( sprintf( __( 'Added card ending in %s to the user account.', 'wc-paytrail' ), $token->get_last4() ) );
			}
		}

		// Dont provide order object for return URL if the request originated from Paytrail server.
		// Google Analytics plugin uses Javascript for conversion tracking which Paytrail server doesn't
		// support so we want to redirect Paytrail server to anonymous (non-order) return page. Otherwise
		// conversion tracking wont work correctly.
		if ( $this->is_paytrail_request() ) {
			$url = $this->get_return_url();
		} else {
			$url = $this->get_return_url( $order );
		}

		wp_redirect( $url );
		exit;
	}

	/**
	 * Complete tokenization
	 */
	public function complete_tokenization() {
		$tokenization_id = filter_input( INPUT_GET, 'checkout-tokenization-id' );
		$status = filter_input( INPUT_GET, 'checkout-status' );

		// Validate request
		$this->validate_request( $_GET, '' );

		if ( $status === 'ok' && $tokenization_id && ( $token = $this->save_token( $tokenization_id ) ) ) {
			wc_add_notice( sprintf( __( 'Card ending with %s was added successfully.', 'wc-paytrail' ), $token->get_last4() ), 'success' );
		} else {
			wc_add_notice( __( 'Adding card failed. Please try again.', 'wc-paytrail' ), 'error' );
		}

		switch ( filter_input( INPUT_GET, 'context' ) ) {
			case 'add-payment-method':
			case 'my-account':
				$redirect = wc_get_account_endpoint_url( 'payment-methods' );
				break;
			case 'checkout':
				$redirect = wc_get_checkout_url();
				break;
			default:
				$redirect = get_site_url();
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Add card via the link in the checkout
	 */
	public function add_card() {
		$context = 'checkout';
		if ( filter_input( INPUT_GET, 'change_payment_method' ) ) {
			$context = 'my-account';
		}

		if ( filter_input( INPUT_GET, 'add_payment_method' ) ) {
			$context = 'add-payment-method';
		}

		$this->redirect_to_tokenization_form( $context );		
	}

	/**
	 * Save token
	 */
	private function save_token( $tokenization_id ) {
		// Get token from Paytrail
		$data = $this->get_token( $tokenization_id );

		if ( $data ) {
			$token = new WC_Payment_Token_CC();
			$token->set_token( $data->token );
			$token->set_gateway_id( $this->id );
			$token->set_last4( $data->card->partial_pan );
			$token->set_expiry_year( $data->card->expire_year );
			$token->set_expiry_month( $data->card->expire_month );
			$token->set_card_type( strtolower( $data->card->type ) );
			$token->set_user_id( get_current_user_id() );
			$token->save();

			WC_Payment_Tokens::set_users_default( get_current_user_id(), $token->get_id() );

			return $token;
		}

		return false;
	}

	/**
	 * Get token from Paytrail with tokenization ID
	 */
	private function get_token( $tokenization_id ) {
		$response = $this->request( "tokenization/{$tokenization_id}", 'POST', '', [], [ 'checkout-tokenization-id' => $tokenization_id ] );

		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( $code === 200 ) {
				return json_decode( $body );
			}
		}

		return false;
	}

	/**
	 * Charge token
	 * 
	 * @param WC_Order $order
	 * 
	 * @return array
	 */
	private function charge_token( $order, $token, $mit_cit ) {
		$body = $this->payment_args( $order, $token );

		// Store reference for future uses (not used by this plugin at the moment)
		$order->update_meta_data( '_paytrail_ppa_reference', $body['reference'] );

		// Store information that transaction-specific settlement was used
		if ( $this->transaction_settlement_enable ) {
			$order->update_meta_data( '_paytrail_ppa_transaction_settlement', true );
		}

		$response = $this->request( "payments/token/{$mit_cit}/charge", 'POST', json_encode( $body ), [], [], $order->get_id(), true );

		$error_msg = false;

		// Check response
		if ( ! is_wp_error( $response ) ) {
			$response_code = (string) wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$body = json_decode( $body );

			if ( $response_code === '201' ) {
				return [
					'result' => 'success',
					'body' => $body
				];
			} else if ( $response_code === '403' && is_object( $body ) && isset( $body->threeDSecureUrl ) ) {
				return [
					'result' => '3ds',
					'redirect' => $body->threeDSecureUrl,
				];
			} else if ( in_array( $response_code, [ '400', '500', '401' ], true ) ) {
				if ( is_object( $body ) && isset( $body->status, $body->message ) && $body->status === 'error' ) {
					$error_msg = $body->message;

					if ( isset( $body->meta ) && is_array( $body->meta ) ) {
						$error_msg = sprintf( '%s %s', $error_msg, implode( '. ', $body->meta ) );
					}
				} else {
					$error_msg = sprintf( __( 'unknown error (response code: %s)', 'wc-paytrail' ), $response_code );
				}
			} else {
				$error_msg = sprintf( __( 'invalid response code %s', 'wc-paytrail' ), $response_code );
			}
		} else {
			$error_msg = $response->get_error_message();
		}

		return [
			'result' => 'failed',
			'error' => $error_msg,
		];
	}

	/**
	 * Create token / add payment method from "My Account" page
	 */
	public function add_payment_method() {
		$this->redirect_to_tokenization_form( 'my-account' );
	}

	/**
	 * Make request to API
	 */
	private function request( $endpoint, $method, $body = '', $extra_args = [], $extra_headers = [], $order_id = null, $validate_response = true, $remove_headers = false ) {
		$datetime = new \DateTime();
		$args = [
			'body' => $body,
			'method' => $method,
			'headers' => [
				'checkout-account' => $this->get_merchant_id( $order_id ),
				'checkout-algorithm' => 'sha256',
				'checkout-method' => $method,
				'checkout-nonce' => uniqid( true ),
				'checkout-timestamp' => $datetime->format('Y-m-d\TH:i:s.u\Z'),
				'platform-name' => $this->get_platform_name(),
				'content-type' => 'application/json; charset=utf-8',
			],
			'timeout' => 30,
		];

		$args = array_merge( $args, $extra_args );
		$args['headers'] = array_merge( $args['headers'], $extra_headers );

		$args['headers']['signature'] = $this->calculate_hmac( $args['headers'], $args['body'], $order_id );

		// For add cart form request headers are not used and everything will be in
		// body
		if ( $remove_headers ) {
			unset( $args['headers'] );
			$args['headers'] = [
				'content-type' => 'application/json; charset=utf-8',
			];
		}

		$url = sprintf( '%s/%s', $this->paytrail_api_url, $endpoint );

		$response = wp_remote_request( $url, $args );

		// Validate that response originated from Paytrail (signature is valid)
		if ( ! is_wp_error( $response ) && $validate_response ) {
			$headers_obj = wp_remote_retrieve_headers( $response );
			$headers = $headers_obj->getAll();
			$body = wp_remote_retrieve_body( $response );

			// This will kill script execution if validation fails
			$this->validate_request( $headers, $body, true, $order_id );
		}

		return $response;
	}

	/**
	 * Validate request
	 */
	private function validate_request( $params, $body, $die_on_error = true, $order_id = null ) {
		$signature = isset( $params['signature'] ) ? $params['signature'] : false;
		$calculated_signature = $this->calculate_hmac( $params, $body, $order_id );

		if ( $signature && $signature === $calculated_signature ) {
			return true;
		}

		if ( $die_on_error ) {
			wp_die( __( 'Signature validation failed.', 'wc-paytrail' ) );
			exit;
		}

		return false;
	}

	/**
	 * Get order ID by stamp
	 * 
	 * Stamp is formatted {order_id}-{unique_id} so we can get
	 * order ID from the first part
	 */
	public function get_order_id_by_stamp( $stamp ) {
		$parts = explode( '-', $stamp );

		if ( count( $parts ) === 2 ) {
			return intval( $parts[0] ) ;
		}

		$this->log( sprintf( __( 'Order ID could not be retviered from stamp %s', 'wc-paytrail' ), $stamp ), 'alert' );

		return false;
	}

	/**
	 * Check if request originated from Paytrail
	 */
	public function is_paytrail_request() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) && strpos( strtolower( $_SERVER['HTTP_USER_AGENT'] ), 'payment registration' ) !== false;
	}

	/**
	 * Scheduled subscription payment
	 * 
	 * @param float $amount_to_charge
	 * @param WC_Order $order
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order ) {
		// Check that order has customer ID
		$customer_id = $order->get_customer_id();
		if ( ! $customer_id ) {
			$msg = sprintf( __( 'Paytrail token payment failed: %s', 'wc-paytrail' ), __( 'customer not set for the order', 'wc-paytrail' ) );
			$this->fail_scheduled_payment( $order, $msg );

			return false;
		}

		/**
		 * @var WC_Payment_Token_CC $token
		 */
		$token = WC_Payment_Tokens::get_customer_default_token( $order->get_customer_id() );
		if ( ! $token ) {
			$msg = sprintf( __( 'Paytrail token payment failed: %s', 'wc-paytrail' ), __( 'default token not set for the customer', 'wc-paytrail' ) );
			$this->fail_scheduled_payment( $order, $msg );

			return false;
		}

		// Ensure token is for this gateway
		if ( $token->get_gateway_id() !== $this->id ) {
			$msg = sprintf( __( 'Paytrail token payment failed: %s', 'wc-paytrail' ), __( 'customer default token is not for Paytrail gateway', 'wc-paytrail' ) );
			$this->fail_scheduled_payment( $order, $msg );

			return false;
		}

		// Attempt payment
		try {
			$result = $this->charge_token( $order, $token->get_token(), 'mit' );
		} catch( \Exception $e ) {
			$this->fail_scheduled_payment( $order, $e->getMessage() );

			return false;
		}

		if ( is_array( $result ) ) {
			if ( $result['result'] === 'success' ) {
				$txn_id = $result['body']->transactionId;
				$order->payment_complete( $txn_id );
				$order->add_order_note( sprintf( __( 'Scheduled Paytrail token payment completed with %s ending in %s. Transaction ID: %s', 'wc-paytrail' ), ucfirst( $token->get_card_type() ), $token->get_last4(), $txn_id ) );
	
				return true;
			} else if ( $result['result'] === '3ds' ) {
				$msg = sprintf( __( 'Paytrail token payment failed with %s %s: %s', 'wc-paytrail' ), ucfirst( $token->get_card_type() ), $token->get_last4(), __( 'token payment requires 3D Secure authentication', 'wc-paytrail' ) );
				$this->fail_scheduled_payment( $order, $msg );

				return false;
			} else if ( $result['result'] === 'failed' ) {
				$error = $result['error'];
				$msg = sprintf( __( 'Paytrail token payment failed with %s %s: %s', 'wc-paytrail' ), ucfirst( $token->get_card_type() ), $token->get_last4(), $error );

				$this->fail_scheduled_payment( $order, $msg );
	
				return false;
			}
		}

		$msg = __( 'Unknown error charging token', 'wc-paytrail' );
		$this->fail_scheduled_payment( $order, $msg );

		return false;
	}

	/**
	 * Fail scheduled subscription payment
	 * 
	 * @param WC_Order $order
	 * @param string $msg
	 * 
	 * @return void
	 */
	private function fail_scheduled_payment( $order, $msg ) {
		// If status is already failed, we will add order note because in that
		// case update_status wont add order note
		if ( $order->has_status( [ 'failed' ] ) ) {
			$order->add_order_note( $msg );
		}

		$order->update_status( 'failed', $msg );
	}

	/**
	 * Process refund.
	 *
	 * @param  int $order_id
	 * @param  float $amount
	 * @param  string $reason
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		try {
			$body = $this->get_refund_args( $order, $amount, $reason );
		} catch ( \Exception $e ) {
			throw new Exception( sprintf( __( 'Error refunding Paytrail payment: %s', 'wc-paytrail' ), $e->getMessage() ) );
		}

		$txn_id = $order->get_transaction_id( 'edit' );

		$headers = [
			'checkout-transaction-id' => $txn_id,
		];

		$response = $this->request( "payments/{$txn_id}/refund", 'POST', json_encode( $body ), [], $headers, $order->get_id(), false );

		// Check response
		if ( ! is_wp_error( $response ) ) {
			$response_code = (string) wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$body_obj = json_decode( $body );

			if ( $response_code === '201' ) {
				if ( $body_obj->status === 'ok' ) {
					$order->add_order_note( sprintf( __( 'Paytrail payment refunded. Refund amount: %s.', 'wc-paytrail' ), wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ) );

					return true;
				} else if ( $body_obj->status === 'pending' ) {
					$order->add_order_note( sprintf( __( 'Paytrail payment refund pending. Refund amount: %s.', 'wc-paytrail' ), wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ) );

					return true;
				}
			}

			$error = sprintf( '%s - %s', $response_code, $body );
		} else {
			$error = $response->get_error_message();
		}

		throw new Exception( sprintf( __( 'Error refunding Paytrail payment: %s', 'wc-paytrail' ), $error ) );
	}

	/**
	 * Get refund body
	 * 
	 * @param WC_Order $order
	 * @param float $amount
	 * @param string $reason
	 * 
	 * @return array
	 */
	private function get_refund_args( $order, $amount, $reason ) {
		// Get all refunds
		$refunds = $order->get_refunds();

		// Refunds should be ordered by date but just to safeguard
		// against regression bugs sort them by ID
		usort( $refunds, function( $a, $b ) {
			return $b->get_id() <=> $a->get_id();
		} );

		// Get the latest refund (the one we are processing here)
		$refund = reset( $refunds );

		// Check that amount matches as an additional safety check
		if ( round( $refund->get_amount(), 2 ) <> round( $amount, 2 ) ) {
			throw new Exception( sprintf( __( 'refund totals does not match (%.2f vs %.2f)', 'wc-paytrail' ), $refund->get_amount(), $amount ) );
		}

		if ( ! $refund ) {
			throw new Exception( __( 'refund object not found', 'wc-paytrail' ) );
		}

		$amount_cents = intval( round( ( $amount * 100 ) ) );
		$body = [
			'amount' => $amount_cents,
			'email' => $order->get_billing_email(),
			'callbackUrls' => [
				'success' => site_url(), // not implemented as of yet
				'cancel' => site_url(), // not implemented as of yet
			],
		];

		// Since 2.6.0 - refund using item stamps
		$version = $order->get_meta( '_wc_paytrail_version' );
		if ( $version && version_compare( $version, '2.6.0' ) >= 0 ) {
			$items = [];
			foreach ( $refund->get_items( ['line_item', 'fee', 'shipping' ] ) as $item ) {
				$item_id = $item->get_meta( '_refunded_item_id', true, 'edit' );

				$items[] = [
					'amount' => absint( intval( round( $refund->get_line_total( $item, true, false ) * 100 ) ) ),
					'stamp' => $this->item_stamp( $item_id, $order ),
				];
			}

			$body['items'] = $items;

			// Main body amount is not used when items are present
			unset( $body['amount'] );

			// Check that sum of items matches the amount
			$item_total = 0;
			foreach ( $items as $item ) {
				$item_total += $item['amount'];
			}

			if ( $item_total <> $amount_cents ) {
				throw new Exception( sprintf( __( 'item total does not match refund total (%.2f vs %.2f)', 'wc-paytrail' ), ( $amount_cents / 100 ), ( $item_total / 100 ) ) );
			}
		}

		$refund->update_meta_data( '_wc_paytrail_refund_body', $body );
		$refund->save();

		return $body;
	}

	/**
	 * Logger
	 */
	private function log( $msg, $level = 'debug' ) {
		$logger = wc_get_logger();
		$context = array( 'source' => 'wc-paytrail-ppa' );

		call_user_func( array( $logger, $level ), $msg, $context );
	}

	/** 
	 * Check if we are processing subscription order 
	 */ 
	private function order_contains_subscription( $order_id ) { 
		if ( $this->subscriptions_enabled() ) { 
			return wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ); 
		} 

		return false; 
	}

	/**
	 * Check if we are processing token payment (only with Blocks based checkout)
	 */
	private function is_token_payment() {
		return isset( $_POST['issavedtoken'] ) && $_POST['issavedtoken'] && isset( $_POST['wc-paytrail_ppa-payment-token'] ) && isset( $_POST['payment_method'] ) && $_POST['payment_method'] === 'paytrail_ppa';
	}

	/**
	 * Check if we are processing subscription payment
	 */
	public function cart_contains_subscription() {
		if ( $this->subscriptions_enabled() ) {
			return WC_Subscriptions_Cart::cart_contains_subscription() || $this->cart_contains_renewal() || filter_input( INPUT_GET, 'change_payment_method' );
		}

		return false;
	}

	/**
	 * Check if cart contains renewal
	 */
	private function cart_contains_renewal() {
		if ( function_exists( 'wcs_cart_contains_renewal' ) ) {
			return wcs_cart_contains_renewal();
		}

		return false;
	}

	/**
	 * Check if subscriptions are enabled
	 */
	private function subscriptions_enabled() {
		return class_exists( 'WC_Subscriptions' );
	}

	/**
	 * Gets saved payment method HTML from a token.
	 *
	 * @param WC_Payment_Token_CC $token Payment Token.
	 * @return string Generated payment method HTML
	 */
	public function get_saved_payment_method_option_html( $token ) {
		$gateway_id = $this->id;
		$token_img = $this->get_token_img( $token );
		$bullets = implode( '&nbsp;', array_fill( 0, 3, '&#8226;&#8226;&#8226;&#8226;' ) );
		$last4 = $token->get_last4();
		$expiry = sprintf( '%s/%s', $token->get_expiry_month(), substr( $token->get_expiry_year(), -2 ) );

		ob_start();

		include 'views/saved-payment-method-option.html.php';

		$html = ob_get_clean();

		return apply_filters( 'woocommerce_payment_gateway_get_saved_payment_method_option_html', $html, $token, $this );
	}

	/**
	 * Get icon for token
	 */
	private function get_token_img( $token ) {
		$img = false;

		switch ( strtolower( $token->get_card_type() ) ) {
			case 'visa':
				$img = 'visa.svg';
				break;
			case 'mastercard':
				$img = 'mastercard.svg';
				break;
			case 'amex':
				$img = 'amex.svg';
				break;
			default:
				$img = 'default.svg';
		}

		return sprintf( '%sassets/images/tokens/%s', WC_PAYTRAIL_PLUGIN_URL, $img ); 
	}

	/**
	 * Displays a radio button for entering a new payment method (new CC details) instead of using a saved method.
	 * Only displayed when a gateway supports tokenization.
	 *
	 * @since 2.6.0
	 */
	public function get_new_payment_method_option_html() {
		// New payment method is rendered separately
		return apply_filters( 'woocommerce_payment_gateway_get_new_payment_method_option_html', '', $this );
	}

	/**
	 * Provider labels
	 */
	public function get_provider_title( $code ) {
		if ( empty( $code ) ) {
			return __( 'N/A', 'wc-paytrail' );
		}

		$providers = [
			'apple_pay' => 'Apple Pay',
			'osuuspankki' => 'OP',
			'nordea' => 'Nordea',
			'handelsbanken' => 'Handelsbanken',
			'pop' => 'POP Pankki',
			'saastopankki' => 'Säästöpankki',
			'omasp' => 'Oma SP',
			'aktia' => 'Aktia',
			'spankki' => 'S-Pankki',
			'danske' => 'Danske',
			'creditcard' => 'Visa / Mastercard',
			'amex' => 'American Express',
			'collectorb2c' => 'Collector',
			'collectorb2b' => 'Collector B2B',
			'pivo' => 'Pivo',
			'mobilepay' => 'MobilePay',
			'siirto' => 'Siirto',
			'oplasku' => 'OP Lasku',
			'jousto' => 'Jousto',
			'alandsbanken' => 'Ålandsbanken',
			'nordea-business' => 'Nordea B2B',
			'danske-business' => 'Danske B2B',
		];

		if ( isset( $providers[$code] ) ) {
			return $providers[$code];
		}

		return $code;
	}
}
