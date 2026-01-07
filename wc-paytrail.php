<?php

/*
Plugin Name: WooCommerce Paytrail
Plugin URI:  https://markup.fi
Description: Paytrail payment gateway integration for WooCommerce.
Version:     2.7.0
Author:      Lauri Karisola / Markup.fi
Author URI:  https://markup.fi
Text Domain: wc-paytrail
Domain Path: /languages
WC requires at least: 8.0.0
WC tested up to: 10.0.0
*/

/**
 * Prevent direct access to the script.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version
 */
if ( ! defined( 'WC_PAYTRAIL_VERSION' ) ) {
	define( 'WC_PAYTRAIL_VERSION', '2.7.0' );
}

/**
 * Plugin URL
 */
if ( ! defined( 'WC_PAYTRAIL_PLUGIN_URL' ) ) {
	define( 'WC_PAYTRAIL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Plugin path
 */
if ( ! defined( 'WC_PAYTRAIL_PATH' ) ) {
	define( 'WC_PAYTRAIL_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * Plugin update checker
 */
require_once 'plugin-update-checker/plugin-update-checker.php';
$update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://markup.fi/products/woocommerce-paytrail/metadata.json',
	__FILE__,
	'wc-paytrail',
	24
);

class Markup_Paytrail {
	/**
	 * Constructor
	 */
	public function __construct() {
		// Load textdomain
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ], 10, 0 );

		// Load payment gateway classes
		add_action( 'plugins_loaded', [ $this, 'load_gateways' ], 10, 0 );

		// HPOS compatibility
		add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_support' ], 10, 0 );

		// Blocks compatibility
		add_action( 'woocommerce_blocks_loaded', [ $this, 'register_blocks_support' ], 10, 0 );

		// Load payment gateways
		add_filter( 'woocommerce_payment_gateways', [ $this, 'register_gateways' ], 10, 1 );

		// Load stylesheets and scripts
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 10, 0 );

		// Load admin stylesheets and scripts
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 10, 0 );

		// Add settings link to the plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_settings_link' ], 10, 1 );

		// AJAX page for checking API credentials (new API)
		add_action( 'wp_ajax_paytrail_ppa_check_api_credentials', [ $this, 'ppa_ajax_check_api_credentials' ] );

		// Admin notice about wrong decimal setting
		add_action( 'admin_notices', [ $this, 'notice_decimal_setting' ], 10, 0 );

		// Admin notice about conflicting plugins
		add_action( 'admin_notices', [ $this, 'notice_conflicting_plugins' ], 10, 0 );

		// Display settlement reference in the order details
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_settlement_ref' ], 10, 1 );

		// Capture invoice through user action
		add_action( 'wp_ajax_paytrail_capture_invoice', [ $this, 'ajax_capture_invoice' ] );

		// Capture invoice based on status change
		add_action( 'woocommerce_order_status_changed', [ $this, 'maybe_capture_invoice' ], 10, 4 );

		// Display invoice status
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_invoice_status' ], 10, 1 );

		// Display provider in admin
		add_filter( 'woocommerce_gateway_title', [ $this, 'display_provider_admin' ], 10, 2 );

		// Display provider in order item totals
		add_filter( 'woocommerce_get_order_item_totals', [ $this, 'display_provider_order_item_totals' ], 10, 2 );

		// Put Paytrail as the first payment method in the list
		register_activation_hook( __FILE__, [ __CLASS__, 'reorder_payment_gateways' ] );
	}

	/**
	 * Load textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wc-paytrail', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		load_plugin_textdomain( 'plugin-update-checker', false, dirname( plugin_basename( __FILE__ ) ) . '/plugin-update-checker/languages/' );
	}

	/**
	 * Load payment gateway classes
	 */
	public function load_gateways() {
		if ( defined( 'WC_VERSION' ) ) {
			include_once( plugin_dir_path( __FILE__ ) . 'includes/class-wc-gateway-paytrail-ppa.php' );
		}
	}

	/**
	 * Declare HPOS support
	 */
	public function declare_hpos_support() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	/**
	 * Register blocks support
	 */
	public function register_blocks_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) && class_exists( 'Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry' ) ) {
			require_once 'includes/class-wc-paytrail-blocks.php';

			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Gateway_Paytrail_Blocks_Support );
				}
			);
		}
	}

	/**
	 * Register Paytrail as WooCommerce payment gateway
	 *
	 * @param array $methods
	 * @return array $methods
	 */
	public function register_gateways( $methods ) {
		$methods[] = 'WC_Gateway_Paytrail_Ppa';

		return $methods;
	}

	/**
	 * Load stylesheets and scripts
	 */
	public function enqueue_scripts() {
		$assets_version = WC_PAYTRAIL_VERSION;

		wp_enqueue_style( 'wc-paytrail-css', WC_PAYTRAIL_PLUGIN_URL . 'assets/css/wc-paytrail.css', [], $assets_version );
		wp_enqueue_script( 'wc-paytrail-js', WC_PAYTRAIL_PLUGIN_URL . 'assets/js/wc-paytrail.js', [ 'jquery' ], $assets_version );

		if ( $this->apple_pay_enabled() && is_checkout() ) {
			// We will use local file instead of fetching this from Paytrail server.
			// This is because some strict content security policies block all outside
			// scripts
			wp_enqueue_script( 'wc-paytrail-apple-pay-js', WC_PAYTRAIL_PLUGIN_URL . 'assets/js/paytrail.js', [], $assets_version );
		}
	}

	/**
	 * Check if Apple Pay is enabled in the settings
	 */
	private function apple_pay_enabled() {
		$options = get_option( 'woocommerce_paytrail_ppa_settings', [] );

		return is_array( $options ) && isset( $options['enable_apple_pay'] ) && $options['enable_apple_pay'] === 'yes';
	}

	/**
	 * Load admin stylesheets and scripts
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style( 'wc-paytrail-css', WC_PAYTRAIL_PLUGIN_URL . 'assets/css/wc-paytrail.css', [], WC_PAYTRAIL_VERSION );
		wp_enqueue_style( 'wc-paytrail-admin-css', WC_PAYTRAIL_PLUGIN_URL . 'assets/css/wc-paytrail-admin.css', [], WC_PAYTRAIL_VERSION );
		wp_enqueue_script( 'wc-paytrail-admin-ppa-js', WC_PAYTRAIL_PLUGIN_URL . 'assets/js/wc-paytrail-ppa-admin.js', [ 'jquery' ], WC_PAYTRAIL_VERSION );
	
		wp_localize_script( 'wc-paytrail-admin-ppa-js', 'wc_paytrail_settings', [
			'check_credentials' => __( 'Check API credentials', 'wc-paytrail' ),
			'check_credentials_success' => __( 'Valid credentials', 'wc-paytrail' ),
		] );
	}

	/**
	 * Add settings link to the plugins page
	 */
	public function add_settings_link( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paytrail_ppa' );
		$link = '<a href="' . $url . '">' . __( 'Settings' ) . '</a>';
	
		return array_merge( [ $link ], $links );
	}

	/**
	 * AJAX page for checking API credentials
	 *
	 * Check is done by trying to create a payment URL via payment API
	 *
	 * URL: /wp-admin/admin-ajax.php?action=paytrail_ppa_check_api_credentials
	 */
	public function ppa_ajax_check_api_credentials() {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Access denied', 'wc-paytrail' ) );
		}

		$merchant_id = trim( $_REQUEST['merchant_id'] );
		$merchant_key = trim( $_REQUEST['merchant_secret'] );
		$transaction_settlements = $_REQUEST['transaction_settlements'] === 'true';

		$datetime = new \DateTime();

		$body = $this->ppa_dummy_args( $transaction_settlements );

		$args = array(
			'body' => json_encode( $body ),
			'headers' => array(
				'checkout-account' => $merchant_id,
				'checkout-algorithm' => 'sha256',
				'checkout-method' => 'POST',
				'checkout-nonce' => uniqid( true ),
				'checkout-timestamp' => $datetime->format('Y-m-d\TH:i:s.u\Z'),
				'platform-name' => 'woocommerce-bitbot',
				'content-type' => 'application/json; charset=utf-8',
			),
			'timeout' => 15
		);

		$args['headers']['signature'] = $this->ppa_dummy_calculate_hmac( $merchant_key, $args['headers'], $args['body'] );

		$url = 'https://services.paytrail.com/payments';

		$response = wp_remote_post( $url, $args );

		// Check response
		$status = 'error';
		$error = __( 'Merchant ID or secret is invalid.', 'wc-paytrail' );
		if ( ! is_wp_error( $response ) ) {
			$response_code = (string) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );
			$data = json_decode( $body );

			if ( $response_code === '201' ) {
				$status = 'ok';
				$error = false;
			} else if ( $response_code === '400' && is_object( $data ) && isset( $data->message ) && $data->message == "Given reference did not pass Finnish reference number validation." ) {
				$error = __( 'Transaction-specific settlements are enabled at Paytrail. Please enable transaction-specific settlements below as well in order to use the plugin.', 'wc-paytrail' );
			} else if ( $response_code === '400' && is_object( $data ) && isset( $data->status, $data->message ) && $data->status === 'error' ) {
				$error = $data->message;

				if ( isset( $data->meta ) && is_array( $data->meta ) ) {
					$error = sprintf( '%s %s', $error, implode( '. ', $data->meta ) );
				}
			} else if ( $response_code === '401' ) {
				// use default error message
			} else {
				$error = sprintf( __( 'Invalid response code: %s', 'wc-paytrail' ), $response_code );
			}
		} else {
			$error = $response->get_error_message();
		}

		$data = array(
			'status' => $status,
			'error' => $error,
		);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);

		wp_die();
	}

	/**
	 * Provides dummy payment arguments for testing credentials
	 *
	 * @return array
	 */
	private function ppa_dummy_args( $transaction_settlements ) {
		$reference = 'TEST12345';

		if ( $transaction_settlements ) {
			$reference = '10003';
		}

		$body = array();

		$body['stamp'] = uniqid( true );
		$body['reference'] = $reference;
		$body['amount'] = 100;
		$body['currency'] = 'EUR';
		$body['language'] = 'FI';
		$body['orderId'] = $body['reference'];
		$body['items'] = array( array(
			'unitPrice' => 100,
			'units' => 1,
			'vatPercentage' => 24,
			'productCode' => '',
			'deliveryDate' => date( 'Y-m-d' ),
			'description' => 'Test item',
		) );
		$body['customer'] = array(
			'email' => 'test@example.com',
			'firstName' => 'Testi',
			'lastName' => 'Asiakas',
			'phone' => '0401234567',
			'vatId' => '',
		);
		$body['deliveryAddress'] = array(
			'streetAddress' => 'Testikatu 1',
			'postalCode' => '00100',
			'city' => 'Helsinki',
			'county' => '',
			'country' => 'FI',
		);
		$body['invoicingAddress'] = $body['deliveryAddress'];
		$body['redirectUrls'] = array(
			'success' => WC()->api_request_url( 'WC_Gateway_Paytrail_Ppa' ),
			'cancel' => WC()->api_request_url( 'WC_Gateway_Paytrail_Ppa' ),
		);
		$body['callbackUrls'] = array(
			'success' => WC()->api_request_url( 'WC_Gateway_Paytrail_Ppa' ),
			'cancel' => WC()->api_request_url( 'WC_Gateway_Paytrail_Ppa' ),
		);

		return $body;
	}

	/**
	 * Calculate hmac for request
	 */
	private function ppa_dummy_calculate_hmac( $merchant_key, $params, $body = '' ) {
		// Keep only checkout- params, more relevant for response validation. Filter query
		// string parameters the same way - the signature includes only checkout- values.
		$included_keys = array_filter( array_keys( $params ), function ( $key ) {
			return preg_match( '/^checkout-/', $key );
		});

		// Keys must be sorted alphabetically
		sort( $included_keys, SORT_STRING );

		$hmac_payload = array_map(
			function ( $key ) use ( $params ) {
				return implode( ':', array( $key, $params[$key] ) );
			},
			$included_keys
		);

		array_push( $hmac_payload, $body );

		return hash_hmac( 'sha256', implode( "\n", $hmac_payload ), $merchant_key );
	}

	/**
	 * Admin notice about wrong decimal setting
	 */
	public function notice_decimal_setting() {
		if ( ! function_exists( 'wc_get_price_decimals' ) ) {
			return;
		}

		$decimals = wc_get_price_decimals();

		if ( $decimals < 2 ) {
			$url = admin_url( 'admin.php?page=wc-settings' );
	
			$notice = sprintf( __( 'Paytrail requires at least two decimals (cents) to be able to calculate taxes correctly. Please check <a href="%s"><em>WooCommerce > Settings > General > Currency options > Number of decimals</em></a>', 'wc-paytrail' ), $url );
		
			$this->render_admin_notice( $notice );
		}
	}

	/**
	 * Admin notice about other active Checkout / Paytrail plugins
	 */
	public function notice_conflicting_plugins() {
		if ( class_exists( '\OpMerchantServices\WooCommercePaymentGateway\Plugin' ) ) {
			$notice = __( '<em>Checkout Finland for WooCommerce</em> / <em>OP Payment Service for WooCommerce</em> plugin is activated which interferes with <em>WooCommerce Paytrail</em> plugin. Please deactivate or remove the conflicting plugin.', 'wc-paytrail' );
		
			$this->render_admin_notice( $notice );
		}

		if ( class_exists( '\Paytrail\WooCommercePaymentGateway\Plugin' ) ) {
			$notice = __( '<em>Paytrail for WooCommerce</em> plugin is activated which interferes with <em>WooCommerce Paytrail</em> plugin. Please deactivate or remove the conflicting plugin.', 'wc-paytrail' );
		
			$this->render_admin_notice( $notice );
		}
	}

	/**
	 * Render admin notice
	 */
	private function render_admin_notice( $notice ) {
		?>
		<div class="notice notice-error">
			<p><?php echo $notice; ?></p>
		</div>
		<?php
	}

	/**
	 * Display settlement reference in the admin order details
	 * 
	 * @param WC_Order $order
	 * 
	 * @return void
	 */
	public function display_settlement_ref( $order ) {
		if ( $order ) {
			$transaction_settlement = $order->get_meta( '_paytrail_ppa_transaction_settlement', true );
			$reference = $order->get_meta( '_paytrail_ppa_reference', true );

			$formatted_ref = strrev( implode( ' ', str_split( strrev( $reference ), 5 ) ) );

			if ( $transaction_settlement && $reference ) {
				include 'includes/views/admin-info.html.php';
			}
		}
	}

	/**
	 * Generate and verify Apple Pay verification file
	 * 
	 */
	public static function generate_ap_ver_file() {
		// Reset errors
		delete_option( 'wc_paytrail_ap_file_error' );

		$error = false;

		$result_file = self::add_ap_ver_file();
		if ( $result_file === true ) {
			$result_access = self::verify_ap_ver_file();

			if ( ! is_wp_error( $result_access ) ) {
				return true;
			} else {
				$error = $result_access->get_error_message();
			}
		} else {
			$error = $result_file->get_error_message();
		}

		update_option( 'wc_paytrail_ap_file_error', $error );

		return false;
	}

	/**
	 * Get Apple Pay verification file from Paytrail
	 */
	public static function get_ap_ver_file() {
		$response = wp_remote_get( 'https://pay.paytrail.com/.well-known/apple-developer-merchantid-domain-association', [
			'timeout' => 10
		] );

		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( $code === 200 ) {
				return $body;
			} else {
				return new WP_Error( 'wc_paytrail_ap_file_response_error', sprintf( __( 'Failed to fetch verification file from Paytrail. Response code: %s', 'wc-paytrail' ), $code ) );
			}
		}

		// Return error object
		return $response;
	}

	/**
	 * Add Apple Pay verification file
	 */
	public static function add_ap_ver_file() {
		$dest_file = sprintf( '%s.well-known/apple-developer-merchantid-domain-association', ABSPATH );
		$well_known_dir = sprintf( '%s.well-known', ABSPATH );

		// Create well known folder if it doesn't exist
		if ( ! is_dir( $well_known_dir ) ) {
			mkdir( $well_known_dir, 0755, false );
		}

		// Check that directory was created successfully
		if ( ! is_dir( $well_known_dir ) ) {
			return new WP_Error( 'wc_paytrail_ap_file_dir_error', __( 'Could not create .well-known directory', 'wc-paytrail' ) );
		}

		// Get file
		$contents = self::get_ap_ver_file();
		if ( is_wp_error( $contents ) ) {
			return $contents;
		}

		// Save file
		$result = file_put_contents( $dest_file, $contents );

		if ( ! $result ) {
			return new WP_Error( 'wc_paytrail_ap_file_put_error', __( 'Failed to save verification file', 'wc-paytrail' ) );
		}

		// Double check that file was created
		if ( ! file_exists( $dest_file ) ) {
			return new WP_Error( 'wc_paytrail_ap_file_copy_error', __( 'Failed to find verification file', 'wc-paytrail' ) );
		}

		return true;
	}

	/**
	 * Verify Apple Pay verification file is accessible over internet
	 */
	public static function verify_ap_ver_file() {
		// Reset verification file data
		delete_option( 'wc_paytrail_ap_file_site_url' );

		$response = wp_remote_get( self::ap_ver_file_url() );

		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			$body = strval( wp_remote_retrieve_body( $response ) );

			if ( $code === 200 ) {
				if ( ! empty( $body ) ) {
					update_option( 'wc_paytrail_ap_file_site_url', get_site_url() );

					return true;
				} else {
					return new WP_Error( 'wc_paytrail_ap_file_invalid', __( 'Verification file exists but it is empty', 'wc-paytrail' ) );
				}
			} else {
				return new WP_Error( 'wc_paytrail_ap_file_invalid', sprintf( __( 'Invalid response code: %s', 'wc-paytrail' ), $code ) );
			}
		}

		return new WP_Error( 'wc_paytrail_ap_file_invalid', sprintf( __( 'Verification file could not be accessed: %s', 'wc-paytrail' ), $response->get_error_message() ) );
	}

	/**
	 * Check Apple Pay verification file exists and is for the URL
	 */
	public static function ap_ver_file_status() {
		$filepath = sprintf( '%s.well-known/apple-developer-merchantid-domain-association', ABSPATH );

		// Check that there were no errors in the generation phase
		$error = get_option( 'wc_paytrail_ap_file_error', false );
		if ( ! empty( $error ) ) {
			return new WP_Error( 'wc_paytrail_ap_file_invalid', $error );
		}

		// Check that the file exists
		if ( ! file_exists( $filepath ) ) {
			return new WP_Error( 'wc_paytrail_ap_file_invalid', __( 'File does not exist', 'wc-paytrail' ) );
		}

		// Check that the file was generated for the URL
		$url = get_option( 'wc_paytrail_ap_file_site_url', false );
		if ( ! empty( $url ) && $url != get_site_url() ) {
			return new WP_Error( 'wc_paytrail_ap_file_invalid', __( 'File exists but was generated for another domain', 'wc-paytrail' ) );
		}

		return true;
	}

	/**
	 * Apple Pay verification file URL
	 */
	public static function ap_ver_file_url() {
		return sprintf( '%s/.well-known/apple-developer-merchantid-domain-association', rtrim( get_site_url(), '/' ) );
	}

	/**
	 * Display provider in admin
	 */
	public function display_provider_admin( $title, $id ) {
		if ( $id !== 'paytrail_ppa' || ! is_admin() ) {
			return $title;
		}

		$is_order_hpos = isset( $_GET['page'], $_GET['action'], $_GET['id'] ) && $_GET['page'] === 'wc-orders' && $_GET['action'] === 'edit';
		$is_order_non_hpos = isset( $_GET['post'], $_GET['action'] ) && $_GET['action'] === 'edit';

		if ( $is_order_hpos || $is_order_non_hpos ) {
			global $theorder;

			if ( $theorder && is_a( $theorder, 'WC_Order' ) && is_callable( [ $theorder, 'get_meta' ] ) ) {
				$provider = $theorder->get_meta( '_wc_paytrail_provider_title', true );

				if ( $provider ) {
					$title = sprintf( '%s (%s)', $title, $provider );
				}
			}
		}

		return $title;
	}

	/**
	 * Display provider in order item totals
	 * 
	 * Payment method in order item totals is displayed in emails,
	 * My Account pages and other places where the order details
	 * can be viewed
	 */
	public function display_provider_order_item_totals( $rows, $order ) {
		if (
			is_array( $rows ) &&
			isset( $rows['payment_method'] ) &&
			is_a( $order, 'WC_Order' ) &&
			is_callable( [ $order, 'get_payment_method' ] ) &&
			is_callable( [ $order, 'get_meta' ] ) &&
			$order->get_payment_method() === 'paytrail_ppa'
		) {
			$provider = $order->get_meta( '_wc_paytrail_provider_title', true );

			if ( $provider ) {
				$rows['payment_method']['value'] = sprintf( '%s (%s)', $rows['payment_method']['value'], $provider );
			}
		}

		return $rows;
	}

	/**
	 * Display invoice status
	 */
	public function display_invoice_status( $order, $echo = true ) {
		// Ensure this is paid with Paytrail
		$txn_id = $order->get_meta( '_wc_paytrail_transaction_id' );
		if ( empty( $txn_id ) ) {
			return;
		}

		// Check that the order has invoice provider and manual activations
		// were enabled
		$gateway = self::get_gateway();
		if ( ! $gateway->is_capturable( $order ) ) {
			return;
		}

		$provider_title = $order->get_meta( '_wc_paytrail_provider_title' );
		$status = $order->get_meta( '_paytrail_ppa_invoice_manual_capture' );

		$capture_url = add_query_arg( [
			'action' => 'paytrail_capture_invoice',
			'nonce' => wp_create_nonce( 'paytrail_capture_invoice' ),
		], admin_url( 'admin-ajax.php' ) );

		ob_start();

		include 'includes/views/invoice-status.html.php';

		$output = ob_get_clean();

		if ( $echo ) {
			echo $output;
		} else {
			return $output;
		}
	}

	/**
	 * Capture invoice through AJAX request
	 */
	public function ajax_capture_invoice() {
		$nonce = $_GET['nonce'];
		if ( ! wp_verify_nonce( $nonce, 'paytrail_capture_invoice' ) ) {
			wp_send_json( [
				'status' => 'error',
				'msg' => __( 'Permission denied', 'wc-paytrail' ),
				'error_code' => 'invalid_nonce'
			], 403 );
			die;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json( [
				'status' => 'error',
				'msg' => __( 'Permission denied', 'wc-paytrail' ),
				'error_code' => 'unauthorized'
			], 403 );
			die;
		}

		$order = wc_get_order( absint( $_POST['order_id'] ) );

		if ( ! $order ) {
			wp_send_json( [
				'status' => 'error',
				'msg' => __( 'Order not found', 'wc-paytrail' ),
			], 422 );
		}

		$gateway = self::get_gateway();
		$result = $gateway->process_capture_invoice( $order );

		if ( $result === true ) {
			$output = $this->display_invoice_status( $order, false );

			wp_send_json( [
				'status' => 'ok',
				'msg' => __( 'Invoice captured successfully.', 'wc-paytrail' ),
				'html' => $output,
			], 200 );
		}

		wp_send_json( [
			'status' => 'error',
			'msg' => $result->get_error_message(),
		], 422 );
	}

	/**
	 * Maybe capture invoice
	 * 
	 * @param int $order_id
	 * @param string $from
	 * @param string $to
	 * @param WC_Order $order
	 * 
	 * @return void
	 */
	public function maybe_capture_invoice( $order_id, $from, $to, $order ) {
		// Ensure this is paid with Paytrail
		$txn_id = $order->get_meta( '_wc_paytrail_transaction_id' );
		if ( empty( $txn_id ) ) {
			return;
		}
		
		// Check that we have status-based trigger
		$settings = get_option( 'woocommerce_paytrail_ppa_settings', [] );
		$trigger = isset( $settings['invoice_capture'] ) ? $settings['invoice_capture'] : '';
		if ( strpos( $trigger, 'status_' ) === false ) {
			return;
		}

		// Check that order is capturable (invoice provider + enabled manual captures)
		$gateway = self::get_gateway();
		if ( ! $gateway->is_capturable( $order ) ) {
			return;
		}

		// Check that we haven't captured the order already
		if ( $order->get_meta( '_paytrail_ppa_invoice_manual_capture' ) === 'captured' ) {
			return;
		}

		// Check for status and capture
		if ( strpos( $trigger, 'status_' ) === 0 ) {
			$trigger_status = \Automattic\WooCommerce\Utilities\OrderUtil::remove_status_prefix( substr( $trigger, 7 ) );
			$to_status = \Automattic\WooCommerce\Utilities\OrderUtil::remove_status_prefix( $to );

			if ( $trigger_status === $to_status ) {
				$gateway->process_capture_invoice( $order );
			}
		}
	}

	/**
	 * Get gateway
	 * 
	 * @return WC_Gateway_Paytrail_Ppa
	 */
	public static function get_gateway() {
		$gateway_controller = WC_Payment_Gateways::instance();
		$gateways = $gateway_controller->payment_gateways();

		return isset( $gateways['paytrail_ppa'] ) ? $gateways['paytrail_ppa'] : false;
	}

	/**
	 * New gateways are on bottom of the list by default. We want Paytrail to
	 * be first when it's activated
	 */
	public static function reorder_payment_gateways() {
		$ordering = (array) get_option( 'woocommerce_gateway_order', [] );
		$id = 'paytrail_ppa';

		if ( ! isset( $ordering[$id] ) || ! is_numeric( $ordering[$id] ) ) {
			$is_empty = empty( $ordering ) || ( count( $ordering ) === 1 && $ordering[0] === false );
			$ordering[$id] = $is_empty ? 0 : ( min( $ordering ) - 1 );

			update_option( 'woocommerce_gateway_order', $ordering );
		}
	}
}

new Markup_Paytrail();
