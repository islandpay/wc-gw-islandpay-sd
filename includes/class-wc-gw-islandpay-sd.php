<?php

/**
 * Island Pay Payment Gateway for Sand Dollar
 *
 * Provides an Island Pay Payment Gateway for Sand Dollar.
 *
 * @author        Island Pay
 */
class WC_Gateway_IslandPaySD extends WC_Payment_Gateway
{
    public $version = '1.0.1';

    public function __construct()
    {
        $this->id                 = 'islandpaysd';  // Unique ID for your gateway
        $this->logger_id           = 'wc-gw-islandpay-sd';
        $this->method_title       = __('Island Pay Sand Dollar', 'wc-gw-islandpay-sd'); // Title of the payment method shown on the admin page.
        $this->method_description = __('Island Pay Payments using Sand Dollar', 'wc-gw-islandpay-sd'); // Description for the payment method shown on the admin page.
        $this->icon               = $this->plugin_url() . '/assets/images/logo.png'; // Show an image next to the gateway’s name on the frontend
        $this->has_fields         = true;  // true if you want payment fields to show on the checkout (if doing a direct integration).

        // Setup available countries.
        $this->available_countries = array('BS');

        // Setup available currency codes.
        $this->available_currencies = array('BSD');

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        $this->title = $this->settings['title'];  // Displayed on the 'choose payment method' screen

        // Setup default merchant data.
        $this->url                    = 'https://www.islandpay.com';
        $this->api_endpoint_pro       = 'https://snapper.islandpay.com/api/merchant/ecomm';
        $this->api_endpoint_sandbox   = 'https://conch.islandpay.com/api/merchant/ecomm';
        $this->response_url           = add_query_arg('wc-api', 'WC_Gateway_IslandPaySD', home_url('/'));

        // Debug mode.
        $this->debug = false;
        if (isset($this->settings['debug']) && 'yes' == $this->settings['debug']) {
            $this->debug = true;
        }
        // Allow subscriptions.
        $this->allow_subscriptions = false;
        if (isset($this->settings['allow_subscriptions']) && 'yes' == $this->settings['allow_subscriptions']) {
            $this->allow_subscriptions = true;
        }

        if (class_exists('WC_Logger')) {
            $this->logger = new WC_Logger();
        } else {
            $this->logger = WC()->logger();
        }

        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'api_callback'));
        //add_action('valid-islandpay-request', array($this, 'successful_request'));

        /* 1.6.6 */
        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
        /* 2.0.0 */
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_action('woocommerce_receipt_islandpaysd', array($this, 'receipt_page'));

        if (!$this->gateway_available_and_enabled()) {
            $this->enabled = false; // 'yes' if enabled
        }
    }

    /**
     * Log a message to the WC_Logger, if debug mode is on.
     *
     * @param string $message
     * @return void
     */
    private function log($message)
    {
        if ($this->debug) {
            $this->logger->add($this->logger_id, $message);
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     */
    function init_form_fields()
    {
        $label = __('Enable Logging', 'wc-gw-islandpay-sd');
        $description = __('Enable the logging of errors.', 'wc-gw-islandpay-sd');

        if (defined('WC_LOG_DIR')) {
            $log_url = add_query_arg('tab', 'logs', add_query_arg('page', 'wc-status', admin_url('admin.php')));
            $log_key = 'wc-gw-islandpay-sd-' . sanitize_file_name(wp_hash('wc-gw-islandpay-sd'));
            $log_url = add_query_arg('log_file', $log_key, $log_url);

            $label .= ' | ' . sprintf(__('%1$sView Log%2$s', 'wc-gw-islandpay-sd'), '<a href="' . esc_url($log_url) . '">', '</a>');
        }

        $this->form_fields = array(
            'enabled'                 => array(
                'title'   => __('Enable/Disable', 'wc-gw-islandpay-sd'),
                'label'   => __('Enable Island Pay Sand Dollar', 'wc-gw-islandpay-sd'),
                'type'    => 'checkbox',
                'default' => 'yes'
            ),
            'title'                   => array(
                'title'       => __('Title', 'wc-gw-islandpay-sd'),
                'type'        => 'text',
                'description' => __('This is the title which the user sees during checkout.', 'wc-gw-islandpay-sd'),
                'default'     => __('Sand Dollar', 'wc-gw-islandpay-sd')
            ),
            'description'             => array(
                'title'       => __('Description', 'wc-gw-islandpay-sd'),
                'type'        => 'text',
                'description' => __('Optional: This is the description which the user sees during checkout.', 'wc-gw-islandpay-sd'),
                'default'     => __('Pay using your mobile phone with Sand Dollar.', 'wc-gw-islandpay-sd')
            ),
            'merchant_account_id'                => array(
                'title'       => __('Merchant Account ID', 'wc-gw-islandpay-sd'),
                'type'        => 'text',
                'description' => __('Enter your Merchant Account ID here.', 'wc-gw-islandpay-sd'),
                'default'     => ''
            ),
            'device_id'                => array(
                'title'       => __('Device ID', 'wc-gw-islandpay-sd'),
                'type'        => 'text',
                'description' => __('Enter your Device ID here.', 'wc-gw-islandpay-sd'),
                'default'     => ''
            ),
            'pin'                => array(
                'title'       => __('PIN', 'wc-gw-islandpay-sd'),
                'type'        => 'text',
                'description' => __('Enter your PIN here.', 'wc-gw-islandpay-sd'),
                'default'     => ''
            ),
            'testmode' => array(
                'title'       => __('Test Mode', 'wc-gw-islandpay-sd'),
                'label'       => __('Use Sandbox', 'wc-gw-islandpay-sd'),
                'type'        => 'checkbox',
                'default'     => 'no',
                'description' => 'Test mode enables you to test payments using the sandbox environment before going live.',
            ),
            'allow_subscriptions' => array(
                'title'       => __('Allow Subscriptions', 'wc-gw-islandpay'),
                'type'        => 'checkbox',
                'default'     => 'no'
            ),
            'debug' => array(
                'title'       => __('Debug Log', 'wc-gw-islandpay-sd'),
                'label'       => $label,
                'description' => $description,
                'type'        => 'checkbox',
                'default'     => 'no'
            )
        );
    }

    /**
     * Get the API endpoint
     */
    function api_endpoint()
    {
        if ($this->settings['testmode'] == 'no')
            return $this->api_endpoint_pro;
        else
            return $this->api_endpoint_sandbox;
    }

    /**
     * Get the plugin URL
     */
    function plugin_url()
    {
        if (isset($this->plugin_url)) {
            return $this->plugin_url;
        }

        if (is_ssl()) {
            return $this->plugin_url = str_replace('http://', 'https://', WP_PLUGIN_URL) . "/" . plugin_basename(dirname(dirname(__FILE__)));
        } else {
            return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename(dirname(dirname(__FILE__)));
        }
    }

    /**
     * gateway_available_and_enabled()
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     */
    function gateway_available_and_enabled()
    {
        $is_available  = false;
        $user_currency = get_option('woocommerce_currency');

        $is_available_currency = in_array($user_currency, $this->available_currencies);

        if (
            $is_available_currency && $this->enabled == 'yes' && $this->settings['merchant_account_id'] != '' &&
            $this->settings['device_id'] != '' && $this->settings['pin'] != ''
        ) {
            $is_available = true;
        }

        return $is_available;
    }

    /**
     * Admin Panel Options
     */
    public function admin_options()
    {
        print '<h3>' . __('Island Pay Sand Dollar', 'wc-gw-islandpay-sd') . '</h3>';
        print "<p>";
        printf(__('IslandPay WooCommerce Payment Gateway for Sand Dollar provides your customers a way to pay using the Sand Dollar mobile app using QR Codes.', 'wc-gw-islandpay-sd'), '<a href="https://www.islandpay.com/">', '</a>');
        print '<br>';
        print __('Please reach out to IslandPay to request your account details <a href="http://islandpay.com/contact/" target="_blank">here</a>.', 'wc-gw-islandpay-sd');
        print "</p>";

        if ('BSD' == get_option('woocommerce_currency')) {
            print '<table class="form-table">';
            print $this->generate_settings_html();
            print '</table>';
        } else {
            // Determine the settings URL where currency is adjusted.
            $url = admin_url('admin.php?page=wc-settings&tab=general');
            // Older settings screen.s
            if (isset($_GET['page']) && 'woocommerce' == $_GET['page']) {
                $url = admin_url('admin.php?page=woocommerce&tab=catalog');
            }
            print '<div class="inline error"><p><strong>' . _e('Gateway Disabled', 'wc-gw-islandpay-sd') . '</strong>';
            print sprintf(__('Choose Bahamian Dollars as your store currency in %1$sGeneral Settings%2$s to enable the Island Pay Gateway.', 'wc-gw-islandpay-sd'), '<a href="' . esc_url($url) . '">', '</a>');
            print '</p></div>';
        }
    }

    /**
     * Show the description if set.
     */
    function payment_fields()
    {
        if (isset($this->settings['description']) && ('' != $this->settings['description'])) {
            print wpautop(wptexturize($this->settings['description']));
        }
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     *
     * @return array
     */
    function process_payment($order_id)
    {

        $order = new WC_Order($order_id);

        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    /**
     * Receipt page.
     *
     * Display text and a button to direct the user to Sand Dollar.
     *
     * @param $order_id
     */
    function receipt_page($order_id)
    {
        $order               = new WC_Order($order_id);
        $amount              = $order->get_total();

        $order_code = get_post_meta($order_id, 'islandpay_transaction', true);
        $order_sd_addr = get_post_meta($order_id, 'islandpay_transaction_sd', true);
        if (empty($order_code) || empty($order_sd_addr)) {

            $orderip          = $this->create_order($amount);
            if ($orderip == null) {
                echo '<p>Error! Please contact support (1).</p>';
                return;
            }
            $order_code     = $orderip->code;
            $order_sd_addr     = $orderip->sandDollarCode;
            if ($order_code == '') {
                echo '<p>Error! Please contact support (1).</p>';
                return;
            }
            update_post_meta($order_id, 'islandpay_transaction', $order_code);
            update_post_meta($order_id, 'islandpay_transaction_sd', $order_sd_addr);
        }

        $qr_image_base64     = $this->generate_qr_code($order_code);
        if ($qr_image_base64 == '') {
            echo '<p>Error! Please contact support (2).</p>';
            return;
        }

        $check_order_url = $this->response_url; // http://yoursite.com/?wc-api=CALLBACK

        // both http://yoursite.com/?wc-api=CALLBACK or http://yoursite.com/wc-api/CALLBACK/ is valid
        if (strrpos($check_order_url, '?') === false) {
            $subs_order_url = $check_order_url . '?oid=' . $order_id;
        } else {
            $subs_order_url = $check_order_url . '&oid=' . $order_id;
        }
        if (strrpos($check_order_url, '?') === false) {
            $check_order_url .= '?check_status=1&oid=' . $order_id;
        } else {
            $check_order_url .= '&check_status=1&oid=' . $order_id;
        }

        $order_received_url         = $order->get_checkout_order_received_url();
        $order_checkout_payment_url = $order->get_checkout_payment_url(true);

        print '
		<div class="islandpay-wrapper">
			<style type="text/css">
				#islandpay-widget {
                    width: 90%;
                    max-width: 400px;
                    padding: 30px 20px;
                    background-color: #13395e;
                    box-sizing: content-box;
				}
                
				#islandpay-widget > div.pay-button {
                    display: inline-block;
                    width: 50%;
                    margin-left: auto;
                    margin-right: auto;
                    border: 1px solid white;
                    border-radius: 10px;
                    padding: 10px;
                    cursor: pointer;
                }

				#islandpay-widget > div.pay-button > span {
                    color: white !important;
                    display: inline-block;
                    margin-bottom: 0.5em;
                }

				#islandpay-widget > div.pay-button > img {
                    display: inline-block;
                    width: auto;
                }

				#islandpay-widget > div.scan-text {
                    box-sizing: content-box;
                    margin-top: 1em;
                    margin-bottom: 1em;
                }
                  
				#islandpay-widget > div.scan-text > span {
                    color: white !important;
                }

				#islandpay-widget > div.islandpay-logo {
                    width: auto;
                    height: 100px;
                    margin-top: 1em;
                    margin-left: auto;
                    margin-right: auto;
                    padding: 10px 0;
                    box-sizing: content-box;
                }
                
				#islandpay-widget > div.islandpay-logo > img {
                    border:none;
                    height: 100px;
                    margin-top: 0;
                    margin-bottom: 0;
                    margin-left: auto;
                    margin-right: auto;
                    padding: 0;
                    background:transparent;
                    box-sizing: content-box;
                    box-shadow: none;
                    display:block;
                }

                #islandpay-widget > div.subscription, 
				#islandpay-widget > div.subscription > div.subscription-buttons {
                    width: 100%;
                    margin-top: 0;
                    margin-bottom: 0;
                    margin-left: auto;
                    margin-right: auto;
                    padding: 0;
                    background:transparent;
                    box-sizing: content-box;
                    box-shadow: none;
                    display:block;
                }
				#islandpay-widget > div.subscription > div.subscription-buttons > div.pay-button {
                    display: inline-block;
                    width: 50%;
                    margin-left: auto;
                    margin-right: auto;
                    border: 1px solid white;
                    border-radius: 10px;
                    padding: 10px;
                    cursor: pointer;
                }
				#islandpay-widget > div.subscription > div.subscription-buttons > div.pay-button-active {
                    display: inline-block;
                    width: 50%;
                    margin-left: auto;
                    margin-right: auto;
                    border: 1px solid #13395e;
                    border-radius: 10px;
                    padding: 10px;
                    cursor: pointer;
                    background-color: white;
                }
				#islandpay-widget > div.pay-button > span {
                    color: white !important;
                    display: inline-block;
                    margin-bottom: 0.5em;
                }
				#islandpay-widget > div.pay-button-active > span {
                    color: #13395e !important;
                    display: inline-block;
                    margin-bottom: 0.5em;
                }
			</style>
            <script type="text/javascript">
                function setSubscriptionNone() {
                    jQuery.get("' . $subs_order_url . '&subscription=true&recurrency=0", function(r) {
                        var subNone = document.getElementById("subscription-none");
                        var subDaily = document.getElementById("subscription-daily");
                        var subWeekly = document.getElementById("subscription-weekly");
                        var subMonthly = document.getElementById("subscription-monthly");
                        subNone.classList.remove("pay-button-active");
                        subDaily.classList.remove("pay-button-active");
                        subWeekly.classList.remove("pay-button-active");
                        subMonthly.classList.remove("pay-button-active");
                        subNone.classList.add("pay-button-active");
                    },"json");
                }
                function setSubscriptionDaily() {
                    jQuery.get("' . $subs_order_url . '&subscription=true&recurrency=10", function(r) {
                        var subNone = document.getElementById("subscription-none");
                        var subDaily = document.getElementById("subscription-daily");
                        var subWeekly = document.getElementById("subscription-weekly");
                        var subMonthly = document.getElementById("subscription-monthly");
                        subNone.classList.remove("pay-button-active");
                        subDaily.classList.remove("pay-button-active");
                        subWeekly.classList.remove("pay-button-active");
                        subMonthly.classList.remove("pay-button-active");
                        subDaily.classList.add("pay-button-active");
                    },"json");
                }
                function setSubscriptionWeekly() {
                    jQuery.get("' . $subs_order_url . '&subscription=true&recurrency=20", function(r) {
                        var subNone = document.getElementById("subscription-none");
                        var subDaily = document.getElementById("subscription-daily");
                        var subWeekly = document.getElementById("subscription-weekly");
                        var subMonthly = document.getElementById("subscription-monthly");
                        subNone.classList.remove("pay-button-active");
                        subDaily.classList.remove("pay-button-active");
                        subWeekly.classList.remove("pay-button-active");
                        subMonthly.classList.remove("pay-button-active");
                        subWeekly.classList.add("pay-button-active");
                    },"json");
                }
                function setSubscriptionMonthly() {
                    jQuery.get("' . $subs_order_url . '&subscription=true&recurrency=40", function(r) {
                        var subNone = document.getElementById("subscription-none");
                        var subDaily = document.getElementById("subscription-daily");
                        var subWeekly = document.getElementById("subscription-weekly");
                        var subMonthly = document.getElementById("subscription-monthly");
                        subNone.classList.remove("pay-button-active");
                        subDaily.classList.remove("pay-button-active");
                        subWeekly.classList.remove("pay-button-active");
                        subMonthly.classList.remove("pay-button-active");
                        subMonthly.classList.add("pay-button-active");
                    },"json");
                }
            </script>
            <div id="islandpay-widget" style="margin:0 auto;text-align: center; border:none">';

        if ($this->allow_subscriptions) {
        print '
                <div class="subscription">
                    Do you want to subscribe this order?
                    <div class="subscription-buttons">
                        <div id="subscription-none" class="pay-button pay-button-active" onclick="setSubscriptionNone()">
                            <span>None</span>
                        </div>
                        <div id="subscription-daily" class="pay-button" onclick="setSubscriptionDaily()">
                            <span>Daily</span>
                        </div>
                        <div id="subscription-weekly" class="pay-button" onclick="setSubscriptionWeekly()">
                            <span>Weekly</span>
                        </div>
                        <div id="subscription-monthly" class="pay-button" onclick="setSubscriptionMonthly()">
                            <span>Monthly</span>
                        </div>
                    </div>
                </div>';
        }
    
        print '
                <div class="pay-button" onclick="window.location = \'nzia:qr/' . $order_sd_addr . '\'">
                    <span>Pay with</span>
                    <img src="' . $this->plugin_url() . "/assets/images/logo_text.png" . '" />
                </div>
                <div class="scan-text">
                    <span>or scan QR Code</span>
                </div>
				<div class="islandpay-code">
				  <img class="islandpay" src="' . $qr_image_base64 . '" />
				</div>
                <div class="islandpay-logo">
                    <img src="' . $this->plugin_url() . "/assets/images/logo.png" . '" />
                </div>
			</div>
		</div>';

        $polling_script = '';
        if (in_array($order->get_status(), array('pending', 'failed'))) {
            $polling_script = '
			<script type="text/javascript">
				var islandpayPollCount = 0;
				function pollIslandpayPayment() {
					islandpayPollCount++;
					jQuery.getJSON("' . $check_order_url . '&count=" + islandpayPollCount).then(
					function(r) { // success
					    if (r.status == "processing" || r.status == "completed") {
					        window.location.replace("' . $order_received_url . '");
					    } else if (r.continue_polling) {
					            setTimeout(pollIslandpayPayment, 1000);
					    } else {
					        window.location.replace("' . $order_checkout_payment_url . '");
					    }
					},
					function(r) { // fail
					    // invalid response, try again after a few seconds
					    setTimeout(pollIslandpayPayment, 3000);
					});
				}
				pollIslandpayPayment();
			</script>';
        }
        print $polling_script;
    }

    /**
     * Generate the QR code image using the order code
     **/
    public function generate_qr_code($order_code)
    {
        $api_url = $this->api_endpoint() . '/qrcodeimage/' . $order_code . '/sd';

        $args = array(
            'timeout' => 60,
            'method'  => 'GET'
        );

        $res = wp_remote_request($api_url, $args);

        if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) == 200) {
            $img = wp_remote_retrieve_body($res);
            return $img;
        } else {
            if (is_wp_error($res)) {
                $this->log($res->get_error_code() . ' - ' . $res->get_error_message());
            }
        }

        return '';
    }

    /**
     * Create an order on Island Pay
     *
     */
    public function create_order($amount)
    {
        $api_url = $this->api_endpoint() . '/orders';

        $headers = array(
            'Content-Type'    => 'application/json',
        );

        $merchant_account_id = $this->settings['merchant_account_id'];
        $device_id           = $this->settings['device_id'];
        $pin                 = $this->settings['pin'];

        $body = array(
            'merchant_account_id' => $merchant_account_id,
            'device_id'           => $device_id,
            'pin'                 => $pin,
            'amount'              => $amount,
        );

        $args = array(
            'headers' => $headers,
            'body'    => json_encode($body),
            'timeout' => 60,
            'method'  => 'POST'
        );

        $res = wp_remote_post($api_url, $args);

        if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) == 200) {
            $order = json_decode(wp_remote_retrieve_body($res));
            return $order;
        } else {
            if (is_wp_error($res)) {
                $this->log($res->get_error_code() . ' - ' . $res->get_error_message());
            }
        }
        return null;
    }

    function poll_islandpay_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $order_code = get_post_meta($order_id, 'islandpay_transaction', true);
        if (empty($order_code)) {
            status_header(400);
            exit('Missing or incorrect order_id');
        }

        $api_url = $this->api_endpoint() . '/orders/' . $order_code;

        $args = array(
            'timeout' => 60,
            'method'  => 'GET'
        );

        $res = wp_remote_request($api_url, $args);
        
        if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) == 200) {
            $islandpay_order = json_decode(wp_remote_retrieve_body($res));
            $this->successful_request($order_id, $order, $islandpay_order);
        } else {
            if (is_wp_error($res)) {
                $this->log($res->get_error_code() . ' - ' . $res->get_error_message());
            }
        }
    }

    function exit_with_json_result($order_status, $continue_polling)
    {
        print wp_json_encode(
            array(
                'status'           => $order_status,
                'continue_polling' => $continue_polling
            )
        );
        exit;
    }

    /**
     * API Callback
     */
    function api_callback()
    {
        if (isset($_GET['check_status']) && $_GET['check_status']) {
            // FROM JS
            $order_id = (int) $_GET['oid'];
            $order    = wc_get_order($order_id);
            // if the order is not pending or failed then we can stop polling
            if (!in_array($order->get_status(), array('pending', 'failed'))) {
                $this->check_subscription($order_id, $order);
                $this->exit_with_json_result($order->get_status(), false);
            } else {
                // poll Island Pay servers directly every 5 seconds
                $last_poll_time = get_post_meta($order_id, 'islandpay_poll_request', true);
                if ($last_poll_time === '' || (time() - $last_poll_time >= 1)) {
                    update_post_meta($order_id, 'islandpay_poll_request', time());
                    $this->poll_islandpay_payment($order_id);
                }
            }
            $this->exit_with_json_result($order->get_status(), true);
        } elseif (isset($_GET['subscription']) && $_GET['recurrency']) {
            $order_id = (int) $_GET['oid'];
            $order = wc_get_order($order_id);
            $order_code = get_post_meta($order_id, 'islandpay_transaction', true);
            if (empty($order_code)) {
                status_header(400);
                print wp_json_encode(
                    array(
                        'status'           => 'can\'t find order',
                    )
                );
                exit;
            }

            $recurrency = (int) $_GET['recurrency'];
            update_post_meta($order_id, 'islandpay_subscription', $recurrency);

            print wp_json_encode(
                array(
                    'status'              => 'success'
                )
            );
            exit;
        } else {
            // FROM ISLAND PAY FOR NOTIFICATIONS
            $_POST = stripslashes_deep($_POST);
            $this->validate_callback_request();
            //do_action('valid-islandpay-request', json_decode($_POST['payload'], true));
            //$islandpay_order = json_decode($_POST['payload'], true);
            //$order = ??;
            //$this->successful_request($order, $islandpay_order);
        }
    }


    /**
     * Validate callback request
     *
     */
    function validate_callback_request()
    {
        if (!isset($_POST['payload'])) {
            status_header(400);
            exit('payload missing');
        }

        // if (!(isset($_GET['token']) && $_GET['token'] === $this->settings['merchant_callback_token'])) {
        //     status_header(400);
        //     exit('Missing or incorrect token');
        // }
    }

    /**
     * Check Subscription
     */
    function check_subscription($order_id, $order)
    {
        $order_code = get_post_meta($order_id, 'islandpay_transaction', true);
        $recur = get_post_meta($order_id, 'islandpay_subscription', true);

        if (!empty($recur)) {
            $recurrency = intval($recur);

            if ($recurrency > 0) {

                $headers = array(
                    'Content-Type'    => 'application/json',
                );

                $api_url = $this->api_endpoint() . '/orders/' . $order_code . '/subscribe';

                $merchant_account_id = $this->settings['merchant_account_id'];
                $device_id           = $this->settings['device_id'];
                $pin                 = $this->settings['pin'];

                $body = array(
                    'merchant_account_id' => $merchant_account_id,
                    'device_id'           => $device_id,
                    'pin'                 => $pin,
                    'recurrency'          => $recurrency,
                    'active'              => true
                );

                $args = array(
                    'headers' => $headers,
                    'body'    => json_encode($body),
                    'timeout' => 60,
                    'method'  => 'POST'
                );

                wp_remote_request($api_url, $args);

                if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) == 204) {
                    $subscription_result = json_decode(wp_remote_retrieve_body($res));
                    $success = $subscription_result->status;
                } else {
                    $success = false;
                }
            }
        }
    }

    /**
     * Successful Payment
     *
     * @param $payload
     */
    function successful_request($order_id, $order, $islandpay_order)
    {
        // Island Pay services uses word 'finalished' for a finalized order
        if ('finalished' === strtolower($islandpay_order->status))
        {
            if (!in_array($order->get_status(), array('completed', 'processing')))
            {
                $order->add_order_note(__("Sand Dollar payment completed\nOrder Code: {$islandpay_order->code}", 'wc-gw-islandpay-sd'));
                $order->payment_complete();
                delete_post_meta($order->get_order_number(), 'islandpay_poll_request');
            }
        }
    }
}
