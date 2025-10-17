<?php
/**
 * Payment Handler Class
 * Handles PayPal and on-site payment processing
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Payment_Handler {
    
    private static $instance = null;
    private $paypal_client_id;
    private $paypal_client_secret;
    private $paypal_environment;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->paypal_client_id = get_option('hrb_paypal_client_id', '');
        $this->paypal_client_secret = get_option('hrb_paypal_client_secret', '');
        $this->paypal_environment = get_option('hrb_paypal_sandbox', 1) ? 'sandbox' : 'live';
        
        // Initialize hooks
        add_action('wp_ajax_hrb_create_paypal_order', array($this, 'create_paypal_order'));
        add_action('wp_ajax_nopriv_hrb_create_paypal_order', array($this, 'create_paypal_order'));
        add_action('wp_ajax_hrb_capture_paypal_payment', array($this, 'capture_paypal_payment'));
        add_action('wp_ajax_nopriv_hrb_capture_paypal_payment', array($this, 'capture_paypal_payment'));

        // Admin payment management actions
        add_action('wp_ajax_hrb_mark_payment_completed', array($this, 'mark_payment_completed'));
        add_action('wp_ajax_hrb_cancel_payment', array($this, 'cancel_payment_ajax'));
        add_action('wp_ajax_hrb_get_payment_details', array($this, 'get_payment_details'));
        add_action('wp_ajax_hrb_get_payment_refund_info', array($this, 'get_payment_refund_info'));
        add_action('wp_ajax_hrb_process_refund', array($this, 'process_refund_ajax'));
    }
    
    /**
     * Get PayPal API base URL
     */
    private function get_paypal_api_url() {
        return $this->paypal_environment === 'sandbox' 
            ? 'https://api.sandbox.paypal.com' 
            : 'https://api.paypal.com';
    }
    
    /**
     * Get PayPal access token
     */
    private function get_paypal_access_token() {
        if (empty($this->paypal_client_id) || empty($this->paypal_client_secret)) {
            return new WP_Error('paypal_config', __('PayPal configuration is missing', 'hourly-room-booking'));
        }
        
        $api_url = $this->get_paypal_api_url();
        $credentials = base64_encode($this->paypal_client_id . ':' . $this->paypal_client_secret);
        
        $response = wp_remote_post($api_url . '/v1/oauth2/token', array(
            'headers' => array(
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US',
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => 'grant_type=client_credentials',
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['access_token'])) {
            return new WP_Error('paypal_auth', __('Failed to get PayPal access token', 'hourly-room-booking'));
        }
        
        return $data['access_token'];
    }
    
    /**
     * Create PayPal order
     */
    public function create_paypal_order() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $booking_data = json_decode(stripslashes($_POST['booking_data']), true);
        
        if (!$booking_data) {
            wp_send_json_error(__('Invalid booking data', 'hourly-room-booking'));
        }
        
        // Calculate total amount including PayPal fee
        $booking_manager = HRB_Booking_Manager::getInstance();
        $pricing = $booking_manager->calculate_booking_price($booking_data);
        
        $access_token = $this->get_paypal_access_token();
        if (is_wp_error($access_token)) {
            wp_send_json_error($access_token->get_error_message());
        }
        
        $api_url = $this->get_paypal_api_url();
        
        // Prepare order data
        $order_data = array(
            'intent' => 'CAPTURE',
            'purchase_units' => array(
                array(
                    'reference_id' => 'hrb_booking_' . time(),
                    'amount' => array(
                        'currency_code' => HRB_Currency_Manager::getInstance()->get_paypal_currency(),
                        'value' => number_format($pricing['total_amount'], 2, '.', '')
                    ),
                    'description' => sprintf(
                        __('Room booking: %s on %s', 'hourly-room-booking'),
                        $booking_data['room_name'] ?? 'Room',
                        $booking_data['booking_date']
                    )
                )
            ),
            'application_context' => array(
                'brand_name' => get_option('hrb_company_name', get_bloginfo('name')),
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => site_url('/booking-success/'),
                'cancel_url' => site_url('/booking-cancelled/?token=' . uniqid())
            )
        );
        
        $response = wp_remote_post($api_url . '/v2/checkout/orders', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
                'PayPal-Request-Id' => uniqid()
            ),
            'body' => json_encode($order_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $order = json_decode($body, true);
        
        if (!isset($order['id'])) {
            wp_send_json_error(__('Failed to create PayPal order', 'hourly-room-booking'));
        }
        
        // Create customer first
        global $wpdb;
        $customer_data = array(
            'first_name' => sanitize_text_field($booking_data['first_name']),
            'last_name' => sanitize_text_field($booking_data['last_name']),
            'email' => sanitize_email($booking_data['email']),
            'phone' => sanitize_text_field($booking_data['phone']),
            'company' => sanitize_text_field($booking_data['company']),
            'country' => 'DE'
        );
        
        // Check if customer exists
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE email = %s",
            $customer_data['email']
        ));
        
        if ($customer) {
            $customer_id = $customer->id;
            // Update customer info
            $wpdb->update(
                $wpdb->prefix . 'hrb_customers',
                $customer_data,
                array('id' => $customer_id),
                array('%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Create new customer
            $wpdb->insert(
                $wpdb->prefix . 'hrb_customers',
                $customer_data,
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
            $customer_id = $wpdb->insert_id;
        }
        
        if (!$customer_id) {
            wp_send_json_error(__('Failed to create customer record', 'hourly-room-booking'));
        }
        
        // Create a temporary booking record to store the PayPal order ID
        $booking_data['customer_id'] = $customer_id;
        $booking_manager = HRB_Booking_Manager::getInstance();
        $temp_booking_id = $booking_manager->create_booking($booking_data);
        
        if (is_wp_error($temp_booking_id)) {
            wp_send_json_error($temp_booking_id->get_error_message());
        }
        
        // Save extras for PayPal bookings
        if (!empty($booking_data['extras'])) {
            $extras_result = $booking_manager->save_booking_extras(
                $temp_booking_id,
                $booking_data['extras'],
                $booking_data['booking_date'],
                $booking_data['start_time'],
                $booking_data['end_time']
            );
            
            if (is_wp_error($extras_result)) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error($extras_result->get_error_message());
            }
        }
        
        // Record pending PayPal payment using centralized method
        $payment_manager = HRB_Payment_Manager::getInstance();
        $payment_id = $payment_manager->create_payment(
            $temp_booking_id,
            $pricing['total_amount'],
            'paypal',
            HRB_Currency_Manager::getInstance()->get_currency_code(),
            array(
                'gateway_transaction_id' => $order['id'],
                'status' => 'pending'
            )
        );
        
        wp_send_json_success(array(
            'order_id' => $order['id'],
            'approval_url' => $this->get_approval_url($order['links'])
        ));
    }
    
    /**
     * Capture PayPal payment
     */
    public function capture_paypal_payment() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }

        $order_id = sanitize_text_field($_POST['order_id']);
        $booking_id = intval($_POST['booking_id']);

        if (empty($order_id) || empty($booking_id)) {
            wp_send_json_error(__('Missing required parameters', 'hourly-room-booking'));
        }

        $access_token = $this->get_paypal_access_token();
        if (is_wp_error($access_token)) {
            wp_send_json_error($access_token->get_error_message());
        }

        $api_url = $this->get_paypal_api_url();

        $response = wp_remote_post($api_url . '/v2/checkout/orders/' . $order_id . '/capture', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
                'PayPal-Request-Id' => uniqid()
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $capture_result = json_decode($body, true);

        if ($capture_result['status'] === 'COMPLETED') {
            // Check if this payment has already been captured to prevent duplicates
            global $wpdb;
            $existing_completed = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}hrb_payments
                 WHERE booking_id = %d AND payment_method = 'paypal' AND status = 'completed'
                 AND transaction_id = %s",
                $booking_id,
                $capture_result['id']
            ));

            if ($existing_completed) {
                // Payment already captured, just return success
                wp_send_json_success(array(
                    'message' => __('Payment already completed', 'hourly-room-booking'),
                    'transaction_id' => $capture_result['id']
                ));
                return;
            }

            // Update existing pending payment instead of creating new one
            $payment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}hrb_payments
                 WHERE booking_id = %d AND payment_method = 'paypal' AND status = 'pending'
                 ORDER BY id DESC LIMIT 1",
                $booking_id
            ));
            
            if ($payment_id) {
                // Update existing payment
                $wpdb->update(
                    $wpdb->prefix . 'hrb_payments',
                    array(
                        'transaction_id' => $capture_result['id'],
                        'amount' => $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                        'currency' => $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'],
                        'status' => 'completed',
                        'gateway_response' => json_encode($capture_result),
                        'processed_at' => current_time('mysql')
                    ),
                    array('id' => $payment_id),
                    array('%s', '%f', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
            } else {
                // Fallback: create new payment if none found
                $payment_manager = HRB_Payment_Manager::getInstance();
                $payment_id = $payment_manager->create_payment(
                    $booking_id,
                    $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                    'paypal',
                    $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'],
                    array(
                        'gateway_transaction_id' => $capture_result['id'],
                        'status' => 'completed',
                        'gateway_response' => json_encode($capture_result),
                        'processed_at' => current_time('mysql')
                    )
                );
            }
            
            if ($payment_id && !is_wp_error($payment_id)) {
                // Update booking status - both confirmed and paid
                $booking_manager = HRB_Booking_Manager::getInstance();
                $booking_manager->update_booking($booking_id, array(
                    'status' => 'confirmed',
                    'payment_status' => 'completed',
                    'payment_method' => 'paypal'
                ), false); // Don't send notification during payment processing
                
                // Send confirmation notification
                $booking_manager->send_booking_notification($booking_id, 'payment_confirmation');
            }
            
            wp_send_json_success(array(
                'message' => __('Payment completed successfully', 'hourly-room-booking'),
                'transaction_id' => $capture_result['id']
            ));
        } else {
            // Payment failed - update both booking and payment status to failed
            $booking_manager = HRB_Booking_Manager::getInstance();
            $booking_manager->update_booking($booking_id, array(
                'status' => 'failed',
                'payment_status' => 'failed',
                'payment_method' => 'paypal'
            ), false); // Don't send notification during payment processing
            
            // Update payment record to failed
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'hrb_payments',
                array(
                    'status' => 'failed',
                    'gateway_response' => json_encode($capture_result)
                ),
                array('booking_id' => $booking_id, 'payment_method' => 'paypal'),
                array('%s', '%s'),
                array('%d', '%s')
            );
            
            wp_send_json_error(__('Payment capture failed', 'hourly-room-booking'));
        }
    }
    
    /**
     * Get approval URL from PayPal links
     */
    private function get_approval_url($links) {
        foreach ($links as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }
        return '';
    }
    
    /**
     * Record payment in database
     */
    public function record_payment($booking_id, $payment_data) {
        global $wpdb;
        
        $defaults = array(
            'booking_id' => $booking_id,
            'transaction_id' => '',
            'gateway_transaction_id' => '',
            'payment_method' => '',
            'amount' => 0.00,
            'currency' => HRB_Currency_Manager::getInstance()->get_currency_code(),
            'status' => 'pending',
            'gateway_response' => '',
            'processed_at' => current_time('mysql')
        );
        
        $payment_data = wp_parse_args($payment_data, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'hrb_payments',
            $payment_data,
            array('%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('payment_record_failed', __('Failed to record payment', 'hourly-room-booking'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Process on-site payment
     */
    public function process_onsite_payment($booking_id) {
        global $wpdb;
        
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        // For bookings 4+ hours, only PayPal is allowed
        if ($booking->total_hours >= 4) {
            return new WP_Error('payment_method_not_allowed', __('For bookings 4+ hours, only PayPal payment is allowed', 'hourly-room-booking'));
        }
        
        // Check if payment already exists for this booking
        global $wpdb;
        $existing_payment = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}hrb_payments 
             WHERE booking_id = %d AND payment_method = 'onsite' 
             ORDER BY id DESC LIMIT 1",
            $booking_id
        ));
        
        if ($existing_payment) {
            // Payment already exists, return existing ID
            $payment_id = $existing_payment;
        } else {
            // Record pending on-site payment using centralized method
            $payment_manager = HRB_Payment_Manager::getInstance();
            $payment_id = $payment_manager->create_payment(
                $booking_id,
                $booking->total_amount,
                'onsite',
                HRB_Currency_Manager::getInstance()->get_currency_code(),
                array(
                    'status' => 'pending'
                )
            );
        }
        
        if (is_wp_error($payment_id)) {
            return $payment_id;
        }
        
        // Update booking status
        $booking_manager->update_booking($booking_id, array(
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'onsite'
        ), false); // Don't send notification during payment processing
        
        // Send confirmation notification after status update
        $booking_manager->send_booking_notification($booking_id, 'booking_confirmation');
        
        return $payment_id;
    }
    
    /**
     * Mark on-site payment as completed
     */
    public function complete_onsite_payment($booking_id, $notes = '') {
        global $wpdb;
        
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_payments 
             WHERE booking_id = %d AND payment_method = 'onsite' 
             ORDER BY id DESC LIMIT 1",
            $booking_id
        ));
        
        if (!$payment) {
            return new WP_Error('payment_not_found', __('On-site payment record not found', 'hourly-room-booking'));
        }
        
        // Update payment status
        $wpdb->update(
            $wpdb->prefix . 'hrb_payments',
            array(
                'status' => 'completed',
                'processed_at' => current_time('mysql'),
                'gateway_response' => 'On-site payment completed' . ($notes ? ' - ' . $notes : '')
            ),
            array('id' => $payment->id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        // Update booking payment status
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking_manager->update_booking($booking_id, array(
            'payment_status' => 'completed'
        ), false); // Don't send notification during payment processing
        
        // Send payment confirmation
        $booking_manager->send_booking_notification($booking_id, 'payment_confirmation');
        
        return true;
    }
    
    /**
     * Process refund
     */
    public function process_refund($booking_id) {
        global $wpdb;
        
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        // Get payment record
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_payments 
             WHERE booking_id = %d AND status = 'completed' 
             ORDER BY id DESC LIMIT 1",
            $booking_id
        ));
        
        if (!$payment) {
            return new WP_Error('payment_not_found', __('No completed payment found for refund', 'hourly-room-booking'));
        }
        
        // Check refund policy - no refunds for 4+ hour bookings
        if ($booking->total_hours >= 4) {
            return new WP_Error('no_refunds', __('No refunds allowed for bookings 4+ hours', 'hourly-room-booking'));
        }
        
        if ($payment->payment_method === 'paypal') {
            return $this->process_paypal_refund($payment);
        } else {
            // For on-site payments, just mark as refunded
            return $this->process_onsite_refund($payment);
        }
    }
    
    /**
     * Process PayPal refund
     */
    private function process_paypal_refund($payment) {
        global $wpdb;
        
        $access_token = $this->get_paypal_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        $api_url = $this->get_paypal_api_url();
        $capture_id = $payment->transaction_id;
        
        $refund_data = array(
            'amount' => array(
                'value' => number_format($payment->amount, 2, '.', ''),
                'currency_code' => $payment->currency
            ),
            'note_to_payer' => __('Booking cancellation refund', 'hourly-room-booking')
        );
        
        $response = wp_remote_post($api_url . '/v2/payments/captures/' . $capture_id . '/refund', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
                'PayPal-Request-Id' => uniqid()
            ),
            'body' => json_encode($refund_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $refund_result = json_decode($body, true);
        
        if ($refund_result['status'] === 'COMPLETED') {
            // Update payment status
            $wpdb->update(
                $wpdb->prefix . 'hrb_payments',
                array(
                    'status' => 'refunded',
                    'gateway_response' => json_encode($refund_result)
                ),
                array('id' => $payment->id),
                array('%s', '%s'),
                array('%d')
            );
            
            // Update booking payment status
            $booking_manager = HRB_Booking_Manager::getInstance();
            $booking_manager->update_booking($payment->booking_id, array(
                'payment_status' => 'refunded'
            ), false); // Don't send notification during refund processing
            
            return true;
        } else {
            return new WP_Error('refund_failed', __('PayPal refund failed', 'hourly-room-booking'));
        }
    }
    
    /**
     * Process on-site refund
     */
    private function process_onsite_refund($payment) {
        global $wpdb;
        
        // Update payment status
        $wpdb->update(
            $wpdb->prefix . 'hrb_payments',
            array(
                'status' => 'refunded',
                'gateway_response' => 'On-site payment refunded - ' . current_time('Y-m-d H:i:s')
            ),
            array('id' => $payment->id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Update booking payment status
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking_manager->update_booking($payment->booking_id, array(
            'payment_status' => 'refunded'
        ));
        
        return true;
    }
    
    /**
     * Get payment statistics
     */
    public function get_payment_stats($start_date = null, $end_date = null) {
        global $wpdb;
        
        $date_condition = '';
        $params = array();
        
        if ($start_date && $end_date) {
            $date_condition = 'WHERE p.processed_at BETWEEN %s AND %s';
            $params[] = $start_date;
            $params[] = $end_date;
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN p.payment_method = 'paypal' THEN 1 ELSE 0 END) as paypal_payments,
                    SUM(CASE WHEN p.payment_method = 'onsite' THEN 1 ELSE 0 END) as onsite_payments,
                    SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN p.payment_method = 'paypal' AND p.status = 'completed' THEN p.amount * 0.03 ELSE 0 END) as paypal_fees,
                    AVG(CASE WHEN p.status = 'completed' THEN p.amount ELSE NULL END) as avg_payment_amount
                FROM {$wpdb->prefix}hrb_payments p
                $date_condition";
        
        if (!empty($params)) {
            return $wpdb->get_row($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_row($sql);
        }
    }
    
    /**
     * Get payment by ID
     */
    public function get_payment($payment_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_payments WHERE id = %d",
            $payment_id
        ));
    }
    
    /**
     * Get payments for booking
     */
    public function get_booking_payments($booking_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_payments
             WHERE booking_id = %d ORDER BY created_at DESC",
            $booking_id
        ));
    }

    /**
     * AJAX handler to mark payment as completed
     */
    public function mark_payment_completed() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_die('Security check failed');
        }

        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'hourly-room-booking')]);
        }

        $payment_id = intval($_POST['payment_id']);
        $payment_manager = HRB_Payment_Manager::getInstance();

        $result = $payment_manager->update_payment_status($payment_id, 'completed');

        if ($result) {
            wp_send_json_success(['message' => __('Payment marked as completed', 'hourly-room-booking')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update payment status', 'hourly-room-booking')]);
        }
    }

    /**
     * AJAX handler to cancel payment
     */
    public function cancel_payment_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_die('Security check failed');
        }

        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'hourly-room-booking')]);
        }

        $payment_id = intval($_POST['payment_id']);
        $payment_manager = HRB_Payment_Manager::getInstance();

        $result = $payment_manager->cancel_payment($payment_id);

        if (!is_wp_error($result)) {
            wp_send_json_success(['message' => __('Payment cancelled', 'hourly-room-booking')]);
        } else {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
    }

    /**
     * AJAX handler to get payment details
     */
    public function get_payment_details() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_die('Security check failed');
        }

        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'hourly-room-booking')]);
        }

        $payment_id = intval($_POST['payment_id']);
        $payment_manager = HRB_Payment_Manager::getInstance();
        $payment = $payment_manager->get_payment($payment_id);

        if (!$payment) {
            wp_send_json_error(['message' => __('Payment not found', 'hourly-room-booking')]);
        }

        $currency_symbol = hrb_get_currency_symbol();

        $html = '<div class="payment-details">';
        $html .= '<table class="form-table">';
        $html .= '<tr><th>' . __('Transaction ID', 'hourly-room-booking') . '</th><td>' . esc_html($payment->transaction_id) . '</td></tr>';
        $html .= '<tr><th>' . __('Booking ID', 'hourly-room-booking') . '</th><td>#' . $payment->booking_id . '</td></tr>';
        $html .= '<tr><th>' . __('Customer', 'hourly-room-booking') . '</th><td>' . esc_html($payment->customer_name) . '</td></tr>';
        $html .= '<tr><th>' . __('Room', 'hourly-room-booking') . '</th><td>' . esc_html($payment->room_name) . '</td></tr>';
        $html .= '<tr><th>' . __('Amount', 'hourly-room-booking') . '</th><td>' . $currency_symbol . number_format($payment->amount, 2) . '</td></tr>';
        // Get admin instance for translation methods
        $admin = HRB_Admin::getInstance();
        
        // Translate payment method using global function
        $payment_method_text = hrb_get_payment_method_label($payment->payment_method);
        
        $html .= '<tr><th>' . __('Payment Method', 'hourly-room-booking') . '</th><td>' . $payment_method_text . '</td></tr>';
        $html .= '<tr><th>' . __('Status', 'hourly-room-booking') . '</th><td>' . $admin->get_payment_status_badge($payment->status) . '</td></tr>';
        $html .= '<tr><th>' . __('Created', 'hourly-room-booking') . '</th><td>' . date_i18n(get_option('hrb_date_format', 'd.m.Y') . ' ' . get_option('hrb_time_format', 'H:i'), strtotime($payment->created_at)) . '</td></tr>';
        if ($payment->processed_at) {
            $html .= '<tr><th>' . __('Processed', 'hourly-room-booking') . '</th><td>' . date_i18n(get_option('hrb_date_format', 'd.m.Y') . ' ' . get_option('hrb_time_format', 'H:i'), strtotime($payment->processed_at)) . '</td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';

        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX handler to get payment refund info
     */
    public function get_payment_refund_info() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_die('Security check failed');
        }

        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'hourly-room-booking')]);
        }

        $payment_id = intval($_POST['payment_id']);
        $payment_manager = HRB_Payment_Manager::getInstance();

        $refund_info = $payment_manager->get_refund_info($payment_id);

        if (is_wp_error($refund_info)) {
            wp_send_json_error(['message' => $refund_info->get_error_message()]);
        }

        wp_send_json_success($refund_info);
    }

    /**
     * AJAX handler to process refund
     */
    public function process_refund_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'hrb_admin_action')) {
            wp_die('Security check failed');
        }

        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'hourly-room-booking')]);
        }

        $payment_id = intval($_POST['payment_id']);
        $refund_amount = floatval($_POST['refund_amount']);
        $refund_reason = sanitize_textarea_field($_POST['refund_reason']);
        $notify_customer = isset($_POST['notify_customer']);

        $payment_manager = HRB_Payment_Manager::getInstance();

        $result = $payment_manager->process_refund($payment_id, $refund_amount, $refund_reason, $notify_customer);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => __('Refund processed successfully', 'hourly-room-booking')]);
        }
    }

    /**
     * Capture PayPal payment (for success page)
     */
    public function capture_paypal_payment_ajax($order_id, $booking_id) {
        $access_token = $this->get_paypal_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        $api_url = $this->get_paypal_api_url();
        
        $response = wp_remote_post($api_url . '/v2/checkout/orders/' . $order_id . '/capture', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
                'PayPal-Request-Id' => uniqid()
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $capture_result = json_decode($body, true);
        
        if ($capture_result['status'] === 'COMPLETED') {
            // Check if this payment has already been captured to prevent duplicates
            global $wpdb;
            $existing_completed = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}hrb_payments
                 WHERE booking_id = %d AND payment_method = 'paypal' AND status = 'completed'
                 AND transaction_id = %s",
                $booking_id,
                $capture_result['id']
            ));

            if ($existing_completed) {
                // Payment already captured, just return success
                return true;
            }

            // Update existing pending payment instead of creating new one
            $payment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}hrb_payments
                 WHERE booking_id = %d AND payment_method = 'paypal' AND status = 'pending'
                 ORDER BY id DESC LIMIT 1",
                $booking_id
            ));

            if ($payment_id) {
                // Update existing payment
                $wpdb->update(
                    $wpdb->prefix . 'hrb_payments',
                    array(
                        'transaction_id' => $capture_result['id'],
                        'amount' => $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                        'currency' => $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'],
                        'status' => 'completed',
                        'gateway_response' => json_encode($capture_result),
                        'processed_at' => current_time('mysql')
                    ),
                    array('id' => $payment_id),
                    array('%s', '%f', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
            } else {
                // Fallback: create new payment if none found
                $payment_manager = HRB_Payment_Manager::getInstance();
                $payment_id = $payment_manager->create_payment(
                    $booking_id,
                    $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                    'paypal',
                    $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'],
                    array(
                        'gateway_transaction_id' => $capture_result['id'],
                        'status' => 'completed',
                        'gateway_response' => json_encode($capture_result),
                        'processed_at' => current_time('mysql')
                    )
                );
            }
            
            if ($payment_id && !is_wp_error($payment_id)) {
                // Update booking status - both confirmed and paid
                $booking_manager = HRB_Booking_Manager::getInstance();
                $booking_manager->update_booking($booking_id, array(
                    'status' => 'confirmed',
                    'payment_status' => 'completed',
                    'payment_method' => 'paypal'
                ), false); // Don't send notification during payment processing
                
                // Send confirmation notification
                $booking_manager->send_booking_notification($booking_id, 'payment_confirmation');
            }
            
            return true;
        } else {
            // Payment failed - update both booking and payment status to failed
            $booking_manager = HRB_Booking_Manager::getInstance();
            $booking_manager->update_booking($booking_id, array(
                'status' => 'failed',
                'payment_status' => 'failed',
                'payment_method' => 'paypal'
            ), false); // Don't send notification during payment processing
            
            // Update payment record to failed
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'hrb_payments',
                array(
                    'status' => 'failed',
                    'gateway_response' => json_encode($capture_result)
                ),
                array('booking_id' => $booking_id, 'payment_method' => 'paypal'),
                array('%s', '%s'),
                array('%d', '%s')
            );
            
            return new WP_Error('payment_capture_failed', __('Payment capture failed', 'hourly-room-booking'));
        }
    }
}
?>