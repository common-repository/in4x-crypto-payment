<?php

/*
  Plugin Name: IN4X Crypto Payment
  Description: Extends WooCommerce with an IN4X Crypto Payment.
  Version: 1.0.4
  Author: IN4X Global
  Author URI: https://www.in4xglobal.com/


  Copyright 2021  IN4X Global Ltd.

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('IN4X_VERSION', '1.0.4');
// define IN4X auth header
define('IN4X_AUTHHEADER', 'X-Authorization');
define('IN4X_BASE_URL', 'www.in4xglobal.com');
define('IN4X_TEST_URL', 'dev.in4xglobal.com');

define('IN4X_WIDGET_JS', '/app/widget/in4x-widget.js');
define('IN4X_WIDGET_CSS', '/app/widget/in4x-widget.css');

define('IN4X_LOGO', '/api/img/nx-logo.svg');
define('IN4X_DASH_URL', '/app/merchant-e-commerce-integration');
define('IN4X_API_PAYMENT_CREATE', '/api/partner/payment/create');
define('IN4X_API_CLIENT_CREATE', '/api/partner/client/create');
// WC Order statuses:
define('IN4X_WCO_STATUS_PENDING', 'wc-pending');
define('IN4X_WCO_STATUS_PROCESSING', 'wc-processing');
define('IN4X_WCO_STATUS_HOLD', 'wc-on-hold');
define('IN4X_WCO_STATUS_COMPLETED', 'wc-completed');
define('IN4X_WCO_STATUS_CANCELLED', 'wc-cancelled');
define('IN4X_WCO_STATUS_REFUNDED', 'wc-refunded');
define('IN4X_WCO_STATUS_FAILED', 'wc-failed');

add_action('plugins_loaded', 'IN4X_init_Payment', 20);

function IN4X_init_Payment()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * @property string $id ID of the plugin
     * @property string $method_title Label description of the payment method
     * @property string $method_description Label description of the payment method
     * @property string $icon URL of the icon of the payment (NX logo)
     * @property string $base_url URL of the server (governed by $this->testing plugin option)
     * @property string $public_api_key Public API key for merchant
     * @property string $private_api_key Private API key for merchant (used to verify backend)
     * @property boolean $testing Flag for test/dev mode
     */
    class WC_NX_Payment extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'in4x';
            $this->has_fields = false;
            $this->method_title = 'IN4X Crypto Payment';
            $this->method_description = 'IN4X Crypto Payment';
            $this->init_settings();
            // Load settings
            $this->enabled = $this->get_option('enabled');
            $this->testing = $this->get_option('testing');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->public_api_key = $this->get_option('public_api_key');
            $this->private_api_key = $this->get_option('private_api_key');
            $this->base_url = 'https://' . ($this->testing === 'yes' ? IN4X_TEST_URL : IN4X_BASE_URL);
            $this->icon = apply_filters('woocommerce_nx_icon', $this->base_url . IN4X_LOGO);
            $this->init_form_fields();
            // Widget
            wp_register_style('nx-style', plugins_url('nx-style.css', __FILE__), array(), null);
            wp_enqueue_style('nx-style');
            wp_register_style('nx-widget-style', $this->base_url . IN4X_WIDGET_CSS, array(), null);
            wp_enqueue_style('nx-widget-style');
            wp_enqueue_script('nx-widget', $this->base_url . IN4X_WIDGET_JS, array('jquery'), null);
            wp_enqueue_script('nx-script', plugins_url('nx-script.js', __FILE__), array('jquery'), null);
            wp_enqueue_script('nx-gtag', "https://www.googletagmanager.com/gtag/js?id=G-C4D46910L2", array(), null);
            wp_enqueue_script('nx-ga', plugins_url('nx-gtag.js', __FILE__), array('jquery', 'nx-gtag'), null);
            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_nx_payment', array($this, 'check_ipn_response'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Plugin On/Off',
                    'type' => 'checkbox',
                    'label' => 'On/Off plugin',
                    'default' => 'yes'
                ),
                'testing' => array(
                    'title' => 'Test Mode On/Off',
                    'type' => 'checkbox',
                    'label' => 'On/Off test mode',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'The title that appears on the checkout page',
                    'default' => 'IN4X Crypto Payment',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'The description that appears during the payment method selection process',
                    'default' => 'Pay through IN4X Crypto Payment',
                ),
                'listen_url' => array(
                    'title' => 'Response server URL',
                    'type' => 'text',
                    'description' => 'Copy this url to "Processing Listen URL" field on <a target="_blank" href="' . $this->base_url . IN4X_DASH_URL . '">IN4X Merchant Dashboard</a>',
                    'default' => get_site_url() . '/wc-api/nx_payment/'
                ),
                'public_api_key' => array(
                    'title' => 'Public API Key',
                    'type' => 'text',
                    'description' => 'IN4X Merchant API Key',
                    'default' => '',
                ),
                'private_api_key' => array(
                    'title' => 'Secret API Key',
                    'type' => 'text',
                    'description' => 'IN4X Merchant Secret.',
                    'default' => '',
                )
            );
            return true;
        }

        function process_payment($order_id)
        {
            $order = NULL;
            try {
                $order = new WC_Order($order_id);
                $cid = $this->create_client($order);
                $pid = $this->create_payment($order, $cid);
                $order->add_order_note(__('Payment Request: PaymentID = ' . $pid . ' ClientID = ' . $cid, 'IN4X'));
            } catch (Exception $e) {
                if (!empty($order) && get_class($order) === 'WC_Order') {
                    $order->update_status(WCO_STATUS_FAILED, __('Unable to create payment'));
                }
                wc_add_notice(__('Payment error: ', 'IN4X') . $e->getMessage(), 'error');
                return;
            }
            return array(
                'result' => !empty($pid) ? 'success' : 'failed',
                'redirect' => add_query_arg('pid', $pid, add_query_arg('order', $order_id, add_query_arg('key', $order->get_order_key(), get_permalink(wc_get_page_id('pay')))))
            );
        }

        public function receipt_page($order_id)
        {
            echo '<p class="nx-message" role="alert">Ready to process your payment using <img src="'. $this->icon .'" style="vertical-align: middle;margin-left: 10px;" /></p>';
            echo '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout nx-error-wrapper" style="display:none;"><div class="woocommerce-error nx-error"></div></div>';
            echo '<div class="nx-pay wc-proceed-to-checkout">'
                . '<a class="nx-pay-button checkout-button button alt wc-forward" '
                . 'data-api-key="' . $this->public_api_key . '" ' . ($this->testing === 'yes'? 'data-override-url="' . IN4X_TEST_URL . '"' : '') . '>Pay now</a>'
                . '</div>';
        }

        /**
         * When we have a payment`s response
         */
        function check_ipn_response()
        {

            $data = file_get_contents('php://input');
            //Client doesn't know how to call us
            if (empty($data)) {
                wp_die('Access denied!');
            }

            $request = json_decode($data, TRUE);
            $requestHeaders = getallheaders();

            // Check if server is just polling us
            $this->status_check($request);

            if (!isset($requestHeaders[IN4X_AUTHHEADER])) {
                wp_die('Access denied!');
            }
            // Get the Private api key from db
            $private_api_key_client = $this->get_option('private_api_key');
            if ($private_api_key_client !== $requestHeaders[IN4X_AUTHHEADER]) {
                wp_die('Access denied!');
            }
            // Get orderId and order status
            $response_order_id = $request['orderId'] ? $request['orderId'] : '';
            $response_order_status = $request['status'] ? (
            in_array($request['status'], array(
                IN4X_WCO_STATUS_COMPLETED, 
                IN4X_WCO_STATUS_CANCELLED, 
                IN4X_WCO_STATUS_FAILED, 
                IN4X_WCO_STATUS_HOLD, 
                IN4X_WCO_STATUS_REFUNDED, 
                IN4X_WCO_STATUS_PROCESSING, 
                IN4X_WCO_STATUS_PENDING
            )) ?
                $request['status'] : IN4X_WCO_STATUS_FAILED) : '';
            $response_order_message = $request['message'] ? $request['message'] : 'Order Status: ' . $response_order_status;
            $response_order_amount = $request['amount'] ? $request['amount'] : '';
            if ($response_order_status !== '' && $response_order_amount !== '') {
                $order = new WC_Order($response_order_id);
                if (floatval($order->get_total()) === floatval($response_order_amount)) {
                    $status = 'success';
                    $order->update_status($response_order_status);
                    $order->add_order_note($response_order_message);
                } else {
                    $status = 'failed';
                    $order->update_status('failed');
                    $order->add_order_note($response_order_message);
                }
                $response = array('status' => $status, 'order_id' => $response_order_id, 'order_status' => $response_order_status);
                die(json_encode($response));
            } else {
                wp_die('IPN request failed!');
            }
        }

        /**
         * Performs a quick status check, and exits if the request indicates a status check
         * @param $request array Request JSON Decoded array
         */
        function status_check($request)
        {
            if (!empty($request) && is_array($request) && array_key_exists('contest', $request) && $request['contest'] = true) {
                $message = 'Active';
                if (array_key_exists('api', $request) && $this->public_api_key !== $request['api']) {
                    $message = 'API key mismatch';
                }
                if (array_key_exists('secret', $request) && $this->private_api_key !== $request['secret']) {
                    $message = 'API key mismatch';
                }
                echo json_encode(['contest' => true, 'status' => $message, 'version' => IN4X_VERSION]);
                exit();
            }
        }


        /**
         * Creates a client for an in4x payment order (or retries existing if previously created)
         * @param WC_Order $order An order
         * @return string|false Client ID
         */
        function create_client($order)
        {
            if (empty($order) || get_class($order) !== 'WC_Order') {
                return false;
            }
            $data = array(
                'merchantCustomerId' => $order->get_customer_id(),
                'emailAddress' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'firstname' => $order->get_billing_first_name(),
                'lastname' => $order->get_billing_last_name(),
                'address' => $order->get_billing_address_1(),
                'city' => $order->get_billing_city(),
                'country' => $order->get_billing_country(),
            );
            $res = $this->request_api(IN4X_API_CLIENT_CREATE, $data);
            return !empty($res) && array_key_exists('client', $res) && array_key_exists('referenceId', $res['client']) ? $res['client']['referenceId'] : false;
        }

        /**
         * Creates a payment request to IN4X Global from a WC_Order object
         * This function updates the order status to either wc-pending or wp-on-hold depending on the response (or lack of it)
         * @param WC_Order $order An order
         * @param string $client_id An optional client ID for the payment
         * @return string|false Payment ID
         */
        function create_payment($order, $client_id = FALSE)
        {
            if (empty($order) || get_class($order) !== 'WC_Order') {
                return false;
            }
            $items = $order->get_items();
            $description = 'Payment for Order: (' . $order->get_id() . ')' . PHP_EOL;
            if (is_array($items) && class_exists('WC_Order_Item_Product')) {
                foreach ($items as $i) {
                    $p = new WC_Order_Item_Product($i);
                    $description .= ' - ' . $p->get_name() . ' (' . $p->get_quantity() . ')' . PHP_EOL;
                }
            }
            $data = array(
                'merchantOrderId' => $order->get_id(),
                'orderDescription' => $description,
                'orderCurrency' => strtoupper($order->get_currency()),
                'orderAmount' => $order->get_total(),
            );
            if (!empty($client_id)) {
                $data['clientId'] = $client_id;
            }

            $res = $this->request_api(IN4X_API_PAYMENT_CREATE, $data);
            $pid = !empty($res) && array_key_exists('payment', $res) && array_key_exists('referenceId', $res['payment']) ? $res['payment']['referenceId'] : false;
            if (!empty($pid)) {
                $order->update_status(IN4X_WCO_STATUS_PENDING, 'Awaiting payment');
            } else {
                $order->update_status(IN4X_WCO_STATUS_FAILED, 'Payment creation failed');
            }
            return $pid;
        }

        /**
         * Submits a POST to IN4X Partner API
         * @param string $endpoint An API endpoints (one of API_*** constants
         * @param array $data POST body
         * @return array|false
         * @throws Exception API errors
         */
        function request_api($endpoint, $data)
        {
            $payload = wp_json_encode($data);
            $args = array(
                'method'      => 'POST',
                'body'        => $payload,
                'timeout'     => 60,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(
                    'Content-Type'     => 'application/json',
                    'X-Requested-With' => 'xmlhttprequest',
                    'Authorization'    => 'Basic ' . $this->public_api_key
                ),
                'data_format' => 'body',
                'cookies'     => array()
            );
            $result = wp_remote_post($this->base_url . $endpoint, $args);
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            if ($result === FALSE) {
                throw new Exception('Received malformed response from IN4X API Server');
            }
            $r = json_decode(wp_remote_retrieve_body($result), TRUE);
            $e = NULL;
            if (is_array($r)) {
                // Singular error:
                if (array_key_exists('error', $r)) {
                    $e = $r; //Response is an error
                }
                // Nested Errors (multi error): (validation)
                if (array_key_exists('errors', $r) && is_array($r['errors'])) {
                    $e = $r['errors'][0]; //report only 1st
                }
            }
            if(!empty($e)) {
                $code = '';
                if(array_key_exists('code', $e)) {
                    $code = ' (' . $e['code'] . ')';
                }
                throw new Exception($e['error'] . $code);
            }
            return $r; //Treat as success
        }
    }

}

add_filter('woocommerce_payment_gateways', 'add_WC_NX_Payment_Gateway');

function add_WC_NX_Payment_Gateway($methods)
{
    $methods[] = 'WC_NX_Payment';
    return $methods;
}

?>