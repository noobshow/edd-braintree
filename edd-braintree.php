<?php

/*
Plugin Name: Braintree for Easy Digital Downloads
Plugin URI: https://omnipay.io/downloads/braintree-easy-digital-downloads/
Description: Accept Credit Card and PayPal payments in your Easy Digital Downloads store via Braintree
Version: 1.0.1
Author: Agbonghama Collins (W3Guy LLC)
Author URI: https://omnipay.io/downloads/braintree-easy-digital-downloads/
Text Domain: edd-braintree
Domain Path: /languages
*/

namespace OmnipayWP\EDD\Braintree;

use Omnipay\Omnipay;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

require __DIR__ . '/vendor/autoload.php';


class Braintree {

	private $merchant_id;
	private $private_key;
	private $public_key;

	public function __construct() {

		add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ) );
		add_filter( 'edd_settings_sections_gateways', array( $this, 'settings_section' ) );
		add_filter( 'edd_settings_gateways', array( $this, 'settings_page' ) );

		// gateway ID is "eddBraintree"
		add_action( 'edd_eddBraintree_cc_form', array( $this, 'payment_form' ) );
		add_action( 'edd_gateway_eddBraintree', array( $this, 'process_payment' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

		$this->merchant_id = edd_get_option( 'eddBraintree_merchant_id', '' );
		$this->private_key = edd_get_option( 'eddBraintree_private_key', '' );
		$this->public_key  = edd_get_option( 'eddBraintree_public_key', '' );
		$this->merchant_id = edd_get_option( 'eddBraintree_merchant_id', '' );

		$basename = plugin_basename( __FILE__ );
		$prefix   = is_network_admin() ? 'network_admin_' : '';
		add_filter( "{$prefix}plugin_action_links_$basename", array( $this, 'action_links' ), 10, 4 );
	}

	/**
	 * Show row meta on the plugin screen.
	 *
	 * @param    mixed $links Plugin Row Meta
	 * @param    mixed $file Plugin Base file
	 *
	 * @return    array
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( $file == plugin_basename( __FILE__ ) ) {
			$row_meta = array(
				'upgradetopro' => '<a href="https://omnipay.io/downloads/braintree-easy-digital-downloads/" target="__blank" title="' . esc_attr( __( 'Upgrade to PRO', 'edd-braintree' ) ) . '"><span style="color:#f18500">' . __( 'Upgrade to PRO', 'edd-braintree' ) . '</span></a>',
			);

			return array_merge( $links, $row_meta );
		}

		return (array) $links;
	}

	/**
	 * Action links
	 *
	 * @param $actions
	 * @param $plugin_file
	 * @param $plugin_data
	 * @param $context
	 *
	 * @return array
	 */
	public function action_links( $actions, $plugin_file, $plugin_data, $context ) {
		$custom_actions = array(
			'upgradetopro' => '<a href="https://omnipay.io/downloads/braintree-easy-digital-downloads/" target="__blank" title="' . esc_attr( __( 'Upgrade to PRO', 'edd-braintree' ) ) . '"><span style="color:#f18500">' . __( 'Upgrade to PRO', 'edd-braintree' ) . '</span></a>',
		);

		// add the links to the front of the actions list
		return array_merge( $custom_actions, $actions );
	}


	/**
	 * This function adds the payment gateway to EDD settings.
	 *
	 * @param array $gateways
	 *
	 * @return array
	 */
	public function register_gateway( $gateways ) {

		$gateways['eddBraintree'] = array(
			'admin_label'    => 'Braintree',
			'checkout_label' => apply_filters( 'edd_braintree_label', __( 'Credit Card', 'edd-braintree' ) ),
		);

		return $gateways;
	}

	public function settings_section( $sections ) {
		$sections['braintree'] = __( 'Braintree', 'edd-braintree' );

		return $sections;
	}

	public function settings_page( $settings ) {

		$gateway_settings = array(
			'braintree' => array(
				array(
					'id'   => 'eddBraintree_settings',
					'name' => '<strong>' . __( 'Braintree Settings', 'edd-braintree' ) . '</strong>
					<div id="message" class="error notice"><p>'
					          . sprintf(
						          __(
							          'Be PCI compliant with "Dropin-UI" and "Hosted Fields" checkout style with and get access to support from WordPress & EDD experts. <strong><a target="_blank" href="%s">Upgrade to PRO Now</a></strong>.',
							          'edd-2checkout'
						          ),
						          'https://omnipay.io/downloads/braintree-easy-digital-downloads/?utm_source=wp-dashboard&utm_medium=edd-braintree-lite'
					          ) .
					          '</p></div>',
					'desc' => __( 'Configure Braintree payment gateway settings', 'edd-braintree' ),
					'type' => 'header',
				),
				array(
					'id'   => 'eddBraintree_merchant_id',
					'name' => __( 'Merchant ID', 'edd-braintree' ),
					'desc' => __( 'Enter your merchant ID.', 'edd-braintree' ),
					'type' => 'text',
					'size' => 'regular',
				),
				array(
					'id'   => 'eddBraintree_public_key',
					'name' => __( 'Public Key', 'edd-braintree' ),
					'desc' => __( 'Enter your public key.', 'edd-braintree' ),
					'type' => 'text',
					'size' => 'regular',
				),
				array(
					'id'   => 'eddBraintree_private_key',
					'name' => __( 'Private Key', 'edd-braintree' ),
					'desc' => __( 'Enter your private key.', 'edd-braintree' ),
					'type' => 'text',
					'size' => 'regular',
				),
			),
		);

		return array_merge( $settings, $gateway_settings );
	}

	/**
	 * Gateway payment form.
	 */
	public function payment_form() {
		edd_get_cc_form();
	}

	/**
	 * Process transaction via Braintree.
	 *
	 * @param array $purchase_data
	 */
	public function process_payment( $purchase_data ) {
		if ( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'edd-gateway' ) ) {
			wp_die( __( 'Nonce verification has failed', 'edd' ), __( 'Error', 'edd' ), array( 'response' => 403 ) );
		}

		// make sure we don't have any left over errors present
		edd_clear_errors();

		$payment = array(
			'price'        => $purchase_data['price'],
			'date'         => $purchase_data['date'],
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => edd_get_currency(),
			'downloads'    => $purchase_data['downloads'],
			'cart_details' => $purchase_data['cart_details'],
			'user_info'    => $purchase_data['user_info'],
			'gateway'      => 'Braintree',
			'status'       => 'pending',
		);

		// record the pending payment
		$payment_id = edd_insert_payment( $payment );

		$payment_data = array(
			'amount'   => $purchase_data['price'],
			'options'  => array( 'submitForSettlement' => apply_filters( 'edd_2checkout_submit_for_settlement', true ) ),
			'customer' => array(
				'firstName' => $purchase_data['user_info']['first_name'],
				'lastName'  => $purchase_data['user_info']['last_name'],
				'email'     => $purchase_data['user_email'],
			),
			'billing'  => array(
				'firstName'         => $purchase_data['user_info']['first_name'],
				'lastName'          => $purchase_data['user_info']['last_name'],
				'streetAddress'     => $purchase_data['card_info']['card_address'],
				'extendedAddress'   => $purchase_data['card_info']['card_address_2'],
				'locality'          => $purchase_data['card_info']['card_city'],
				'region'            => $purchase_data['card_info']['card_state'],
				'postalCode'        => $purchase_data['card_info']['card_zip'],
				'countryCodeAlpha2' => $purchase_data['card_info']['card_country'],
			),
		);

		$result = $this->process_raw_cc_payment( $payment_data, $payment_id, $purchase_data );

		// if $result returns void ($result isn't set to a value) or it return false, execute the code below.
		if ( ! isset( $result ) || ! $result ) {
			edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
		}
	}


	/**
	 * Process raw credit card payment.
	 *
	 * @param array $payment_data
	 * @param int $payment_id
	 * @param array $purchase_data
	 *
	 * @return bool|void
	 */
	public function process_raw_cc_payment( $payment_data, $payment_id, $purchase_data ) {

		$cc_data = $this->validate_cc_data( $purchase_data['card_info'] );

		$payment_data['creditCard']['cardholderName'] = $cc_data['card_name'];
		$payment_data['creditCard']['number']         = $cc_data['card_number'];
		$payment_data['creditCard']['cvv']            = $cc_data['card_cvc'];
		// format MM/YY
		// pad month number with 0, if month isn't two digit
		// edd by default return 1-9 for january to september but braintree require 2-digit month.
		$month                                        = str_pad( $cc_data['card_exp_month'], 2, 0, STR_PAD_LEFT );
		$year                                         = $cc_data['card_exp_year'];
		$payment_data['creditCard']['expirationDate'] = "$month/$year";


		\Braintree_Configuration::environment( edd_is_test_mode() ? 'sandbox' : 'production' );
		\Braintree_Configuration::merchantId( $this->merchant_id );
		\Braintree_Configuration::publicKey( $this->public_key );
		\Braintree_Configuration::privateKey( $this->private_key );

		$result = \Braintree_Transaction::sale( $payment_data );

		if ( $result->success ) {
			edd_update_payment_status( $payment_id, 'complete' );
			edd_set_payment_transaction_id( $payment_id, $result->transaction->id );
			edd_send_to_success_page();

		} else if ( $result->transaction ) {
			$error = sprintf( __( 'Transaction Failed. %s (%)', 'edd-braintree' ), $result->transaction->processorResponseText, $result->transaction->processorResponseCode );
			edd_set_error( 'braintree_error', $error );

			return false;

		} else {

			$exclude = array( 81725 ); //Credit card must include number, paymentMethodNonce, or venmoSdkPaymentMethodCode.
			foreach ( ( $result->errors->deepAll() ) as $error ) {
				if ( ! in_array( $error->code, $exclude ) ) {
					edd_set_error( 'braintree_error', $error->message );
				}
			}

			return false;
		}

	}


	/**
	 * Validate credit card information.
	 *
	 * @param array $card_info
	 *
	 * @return array
	 */
	public function validate_cc_data( $card_info ) {

		$cc_description = array(
			'card_number'    => __( 'credit card number', 'edd-braintree' ),
			'card_exp_month' => __( 'expiration month', 'edd-braintree' ),
			'card_exp_year'  => __( 'expiration year', 'edd-braintree' ),
			'card_name'      => __( 'card holder name', 'edd-braintree' ),
			'card_cvc'       => __( 'security code', 'edd-braintree' ),
		);

		foreach ( $card_info as $key => $value ) {
			// only validate cc data in $cc_Description array above.
			if ( in_array( $key, array_keys( $cc_description ) ) ) {
				if ( ! isset( $card_info[ $key ] ) || empty( $card_info[ $key ] ) ) {
					edd_set_error( 'error_' . $key, sprintf( __( 'You must enter a valid %s.', 'edd-braintree' ), $cc_description[ $key ] ) );
				}
			} else {
				continue;
			}
		}

		return array_map( 'sanitize_text_field', $card_info );
	}

	/**
	 * Singleton poop.
	 * @return Braintree
	 */
	public static function get_instance() {
		static $instance;
		if ( ! isset( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}
}

add_action( 'plugins_loaded', 'OmnipayWP\EDD\Braintree\load_plugin' );

function load_plugin() {
	Braintree::get_instance();
}