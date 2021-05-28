<?php
/*
	Plugin Name: Island Pay Sand Dollar WooCommerce Payment Gateway
	Plugin URI: https://github.com/islandpay/wc-gw-islandpay-sd
	Description: Island Pay Sand Dollar WooCommerce Payment Gateway provides your customers a way to pay using the Sand Dollar mobile app using QR Codes.
	Version: 1.0.0
	Author: Island Pay
	Author URI: https://github.com/islandpay
	License:           GPL-3.0
 	License URI:       https://opensource.org/licenses/GPL-3.0
 	GitHub Plugin URI: https://github.com/islandpay/wc-gw-islandpay-sd
*/

if (!defined('ABSPATH'))
	exit;

/**
 * Required functions
 */

load_plugin_textdomain('wc-gw-islandpay-sd', false, trailingslashit(dirname(plugin_basename(__FILE__))));

add_action('plugins_loaded', 'wc_islandpaysd_init', 0);

/**
 * Initialize the gateway.
 *
 */
function wc_islandpaysd_init()
{
	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}

	require_once(plugin_basename('includes/class-wc-gw-islandpay-sd.php'));
	add_filter('woocommerce_payment_gateways', 'wc_islandpaysd_add_gateway');
}

/**
 * Add the gateway to WooCommerce
 *
 * @param $methods
 *
 * @return array
 */
function wc_islandpaysd_add_gateway($methods)
{
	$methods[] = 'WC_Gateway_IslandPaySD';

	return $methods;
}
