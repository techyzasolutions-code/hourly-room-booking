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
        
        // PayPal order creation for existing bookings
        add_action('wp_ajax_hrb_create_paypal_order_for_existing_booking', array($this, 'create_paypal_order_for_existing_booking'));
        add_action('wp_ajax_nopriv_hrb_create_paypal_order_for_existing_booking', array($this, 'create_paypal_order_for_existing_booking'));
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $booking_data = json_decode(stripslashes($_POST['booking_data']), true);
        
        if (!$booking_data) {
            wp_send_json_error(__('Invalid booking data', 'hourly-room-booking'));
        }
        
        // Check if this is an anonymous booking
        $is_anonymous = isset($booking_data['is_anonymous']) && $booking_data['is_anonymous'] === '1';
        
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
        
        // Handle anonymous bookings
        if ($is_anonymous) {
            $customer_data = array(
                'first_name' => 'Anonymous',
                'last_name' => 'User',
                'email' => 'anonymous@example.com',
                'phone' => '0000000000',
                'company' => '',
                'country' => 'DE'
            );
        } else {
            $customer_data = array(
                'first_name' => sanitize_text_field($booking_data['first_name']),
                'last_name' => sanitize_text_field($booking_data['last_name']),
                'email' => sanitize_email($booking_data['email']),
                'phone' => sanitize_text_field($booking_data['phone']),
                'company' => isset($booking_data['company']) ? sanitize_text_field($booking_data['company']) : '',
                'country' => 'DE'
            );
        }
        
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
        $booking_data['is_anonymous'] = $is_anonymous;
        
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
        // Store the PayPal fee in the payment record for accurate tracking
        $payment_manager = HRB_Payment_Manager::getInstance();
        $payment_id = $payment_manager->create_payment(
            $temp_booking_id,
            $pricing['total_amount'],
            'paypal',
            HRB_Currency_Manager::getInstance()->get_currency_code(),
            array(
                'gateway_transaction_id' => $order['id'],
                'status' => 'pending',
                'fees' => $pricing['paypal_fee'] // Store PayPal fee in payment record
            )
        );
        
        wp_send_json_success(array(
            'order_id' => $order['id'],
            'approval_url' => $this->get_approval_url($order['links'])
        ));
    }
    
    /**
     * Create PayPal order for existing booking
     */
    public function create_paypal_order_for_existing_booking() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        if (!$booking_id) {
            wp_send_json_error(__('Invalid booking ID', 'hourly-room-booking'));
        }
        
        // Get booking details
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            wp_send_json_error(__('Booking not found', 'hourly-room-booking'));
        }
        
        // Get room details
        global $wpdb;
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_rooms WHERE id = %d",
            $booking->room_id
        ));
        
        if (!$room) {
            wp_send_json_error(__('Room not found', 'hourly-room-booking'));
        }
        
        // Check if this is an additional payment (payment_token parameter in request)
        $payment_token = isset($_POST['payment_token']) ? sanitize_text_field($_POST['payment_token']) : null;
        $is_additional_payment = false;
        
        // Get payment record by token if provided (secure - token cannot be easily guessed)
        $payment_record = null;
        $payment_amount = $booking->total_amount;
        
        if (!empty($payment_token)) {
            // Get payment record by token and verify it belongs to this booking
            $payment_record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hrb_payments 
                 WHERE payment_token = %s AND booking_id = %d AND status = 'pending'
                 LIMIT 1",
                $payment_token,
                $booking_id
            ));
            
            if (!$payment_record) {
                wp_send_json_error(__('Invalid payment link. Please use the payment link from your email or contact support.', 'hourly-room-booking'));
            }
            
            // Check if this is an additional payment (has ADD_ prefix in transaction_id)
            $is_additional_payment = (!empty($payment_record->transaction_id) && strpos($payment_record->transaction_id, 'ADD_') === 0);
            
            // Get amount from database, not from request (security)
            $payment_amount = floatval($payment_record->amount);
            
            // Check if payment is already completed (prevent multiple payments)
            $completed_payment = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}hrb_payments 
                 WHERE booking_id = %d AND payment_method = 'paypal' AND status = 'completed'
                 AND (transaction_id IS NULL OR transaction_id NOT LIKE 'ADD_%%')
                 LIMIT 1",
                $booking_id
            ));
            
            if ($completed_payment && !$is_additional_payment) {
                wp_send_json_error(__('Payment has already been completed for this booking. Please contact support if you have any questions.', 'hourly-room-booking'));
            }
        } else {
            // No token provided - check if there's already a completed payment
            $completed_payment = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}hrb_payments 
                 WHERE booking_id = %d AND payment_method = 'paypal' AND status = 'completed'
                 AND (transaction_id IS NULL OR transaction_id NOT LIKE 'ADD_%%')
                 LIMIT 1",
                $booking_id
            ));
            
            if ($completed_payment) {
                wp_send_json_error(__('Payment has already been completed for this booking. Please contact support if you have any questions.', 'hourly-room-booking'));
            }
        }
        
        $access_token = $this->get_paypal_access_token();
        if (is_wp_error($access_token)) {
            wp_send_json_error($access_token->get_error_message());
        }
        
        $api_url = $this->get_paypal_api_url();
        
        // Prepare order description
        if ($is_additional_payment) {
            $description = sprintf(
                __('Additional payment for booking: %s on %s', 'hourly-room-booking'),
                $room->name,
                date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date))
            );
        } else {
            $description = sprintf(
                __('Room booking: %s on %s', 'hourly-room-booking'),
                $room->name,
                date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date))
            );
        }
        
        // Prepare order data
        $order_data = array(
            'intent' => 'CAPTURE',
            'purchase_units' => array(
                array(
                    'reference_id' => 'hrb_booking_' . $booking_id . ($is_additional_payment ? '_additional' : ''),
                    'amount' => array(
                        'currency_code' => HRB_Currency_Manager::getInstance()->get_paypal_currency(),
                        'value' => number_format($payment_amount, 2, '.', '')
                    ),
                    'description' => $description
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
        
        // Check if there's an existing pending payment record for this booking
        global $wpdb;
        
        if ($payment_record) {
            // Use the payment record found by token (for both initial and additional payments)
            $existing_payment = $payment_record;
        } else {
            // No token provided - find existing pending payment
            $existing_payment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hrb_payments 
                 WHERE booking_id = %d 
                 AND payment_method = 'paypal' 
                 AND status = 'pending'
                 AND (transaction_id IS NULL OR transaction_id NOT LIKE 'ADD_%%')
                 ORDER BY id DESC
                 LIMIT 1",
                $booking_id
            ));
        }
        
        if ($existing_payment) {
            // Update existing payment record with new PayPal order ID
            $update_result = $wpdb->update(
                $wpdb->prefix . 'hrb_payments',
                array(
                    'gateway_transaction_id' => $order['id'],
                    'gateway_response' => json_encode($order)
                ),
                array('id' => $existing_payment->id),
                array('%s', '%s'),
                array('%d')
            );
            
            if ($update_result === false) {
                wp_send_json_error(__('Failed to update payment record', 'hourly-room-booking'));
            }
        } else {
            // Record pending PayPal payment for existing booking (only if no existing payment)
            // Use the correct payment amount (additional or full)
            $payment_manager = HRB_Payment_Manager::getInstance();
            // Generate unique payment token for additional payments
            $payment_token = null;
            if ($is_additional_payment) {
                $payment_token = wp_generate_password(32, false);
            }
            
            // Calculate PayPal fee: if payment_amount includes fee, extract it
            // PayPal fee is typically 3% of base amount, so: amount = base + fee, fee = amount - base
            // where base = amount / 1.03
            $base_amount = $payment_amount / 1.03;
            $paypal_fee = $payment_amount - $base_amount;
            
            $payment_id = $payment_manager->create_payment(
                $booking_id,
                $payment_amount, // Use calculated payment amount (additional or full)
                'paypal',
                HRB_Currency_Manager::getInstance()->get_currency_code(),
                array(
                    'gateway_transaction_id' => $order['id'],
                    'status' => 'pending',
                    'transaction_id' => $is_additional_payment ? ('ADD_' . time() . '_' . $booking_id) : null,
                    'is_additional_payment' => $is_additional_payment ? 1 : 0,
                    'payment_token' => $payment_token,
                    'fees' => $paypal_fee // Store PayPal fee
                )
            );
            
            if (is_wp_error($payment_id)) {
                wp_send_json_error(__('Failed to create payment record', 'hourly-room-booking'));
            }
        }
        
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }

        $order_id = sanitize_text_field($_POST['order_id']);
        $booking_id = intval($_POST['booking_id']);

        if (empty($order_id) || empty($booking_id)) {
            wp_send_json_error(__('Missing required parameters', 'hourly-room-booking'));
        }

        // SECURITY: Verify this PayPal order was issued by us for this booking.
        // The pending payment record was created by create_paypal_order() with
        // gateway_transaction_id = $order['id']. Without this check, an attacker
        // could submit an arbitrary order_id (e.g. one created for a cheap
        // booking) to mark a different (higher-value) booking as paid.
        global $wpdb;
        $expected_payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_payments
             WHERE booking_id = %d
               AND payment_method = 'paypal'
               AND status = 'pending'
               AND gateway_transaction_id = %s
             ORDER BY id DESC LIMIT 1",
            $booking_id,
            $order_id
        ));

        if (!$expected_payment) {
            // Retry of an already-captured order? Return idempotent success.
            $already_completed = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}hrb_payments
                 WHERE booking_id = %d
                   AND payment_method = 'paypal'
                   AND status = 'completed'
                   AND gateway_transaction_id = %s
                 LIMIT 1",
                $booking_id,
                $order_id
            ));
            if ($already_completed) {
                wp_send_json_success(array(
                    'message' => __('Payment Already Completed', 'hourly-room-booking'),
                    'transaction_id' => $order_id
                ));
                return;
            }
            wp_send_json_error(__('Payment record not found for this order. Capture rejected.', 'hourly-room-booking'));
        }

        $expected_amount = floatval($expected_payment->amount);
        $expected_currency = strtoupper((string) $expected_payment->currency);

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
            // SECURITY: defense-in-depth — confirm the captured amount and
            // currency match what we issued the order for. If they don't,
            // refuse to mark the booking as paid; the captured funds must
            // be reviewed/refunded manually by an admin.
            $captured_amount   = floatval($capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value']);
            $captured_currency = strtoupper((string) $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code']);

            if (abs($captured_amount - $expected_amount) > 0.01 || $captured_currency !== $expected_currency) {
                // Flag this pending payment as failed/disputed so admins can spot it.
                $wpdb->update(
                    $wpdb->prefix . 'hrb_payments',
                    array(
                        'status'           => 'failed',
                        'transaction_id'   => $capture_result['id'],
                        'amount'           => $captured_amount,
                        'currency'         => $captured_currency,
                        'gateway_response' => json_encode($capture_result),
                        'processed_at'     => current_time('mysql'),
                    ),
                    array('id' => $expected_payment->id),
                    array('%s', '%s', '%f', '%s', '%s', '%s'),
                    array('%d')
                );

                wp_send_json_error(sprintf(
                    __('Payment amount mismatch (expected %1$s %2$s, captured %3$s %4$s). The booking has not been marked as paid. Please contact support to arrange a refund.', 'hourly-room-booking'),
                    number_format($expected_amount, 2),
                    $expected_currency,
                    number_format($captured_amount, 2),
                    $captured_currency
                ));
            }

            // Check if this payment has already been captured to prevent duplicates
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
                    'message' => __('Payment Already Completed', 'hourly-room-booking'),
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
                // Get existing payment to preserve is_additional_payment flag
                $existing_payment = $wpdb->get_row($wpdb->prepare(
                    "SELECT is_additional_payment FROM {$wpdb->prefix}hrb_payments WHERE id = %d",
                    $payment_id
                ));
                
                // Extract amount and calculate fees
                $captured_amount = floatval($capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value']);
                
                // Try to extract fees from PayPal response (seller_receivable_breakdown)
                $paypal_fee = 0;
                if (isset($capture_result['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown'])) {
                    $breakdown = $capture_result['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown'];
                    if (isset($breakdown['paypal_fee']['value'])) {
                        $paypal_fee = floatval($breakdown['paypal_fee']['value']);
                    } elseif (isset($breakdown['platform_fees'][0]['amount']['value'])) {
                        $paypal_fee = floatval($breakdown['platform_fees'][0]['amount']['value']);
                    }
                }
                
                // If fees not in response, calculate from stored payment record or estimate
                if ($paypal_fee == 0) {
                    // Get the original payment to see if fees were stored
                    $original_payment = $wpdb->get_row($wpdb->prepare(
                        "SELECT fees, amount FROM {$wpdb->prefix}hrb_payments WHERE id = %d",
                        $payment_id
                    ));
                    if ($original_payment && $original_payment->fees > 0) {
                        $paypal_fee = floatval($original_payment->fees);
                    } else {
                        // Estimate: PayPal fee is typically 3% of the amount (excluding fee)
                        // So if amount = base + fee, then fee = amount - base, where base = amount / 1.03
                        $base_amount = $captured_amount / 1.03;
                        $paypal_fee = $captured_amount - $base_amount;
                    }
                }
                
                $update_data = array(
                    'transaction_id' => $capture_result['id'],
                    'amount' => $captured_amount,
                    'currency' => $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'],
                    'status' => 'completed',
                    'fees' => $paypal_fee, // Store PayPal fee
                    'gateway_response' => json_encode($capture_result),
                    'processed_at' => current_time('mysql')
                );
                
                $format = array('%s', '%f', '%s', '%s', '%f', '%s', '%s');
                
                // Preserve is_additional_payment flag if it exists
                if ($existing_payment && isset($existing_payment->is_additional_payment)) {
                    $update_data['is_additional_payment'] = $existing_payment->is_additional_payment;
                    $format[] = '%d';
                }
                
                // Update existing payment
                $wpdb->update(
                    $wpdb->prefix . 'hrb_payments',
                    $update_data,
                    array('id' => $payment_id),
                    $format,
                    array('%d')
                );
            } else {
                // Fallback: create new payment if none found
                // Calculate fees same way as above
                $captured_amount = floatval($capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value']);
                $paypal_fee = 0;
                if (isset($capture_result['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown'])) {
                    $breakdown = $capture_result['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown'];
                    if (isset($breakdown['paypal_fee']['value'])) {
                        $paypal_fee = floatval($breakdown['paypal_fee']['value']);
                    } elseif (isset($breakdown['platform_fees'][0]['amount']['value'])) {
                        $paypal_fee = floatval($breakdown['platform_fees'][0]['amount']['value']);
                    }
                }
                if ($paypal_fee == 0) {
                    // Estimate: PayPal fee is typically 3% of the amount (excluding fee)
                    $base_amount = $captured_amount / 1.03;
                    $paypal_fee = $captured_amount - $base_amount;
                }
                
                $payment_manager = HRB_Payment_Manager::getInstance();
                $payment_id = $payment_manager->create_payment(
                    $booking_id,
                    $captured_amount,
                    'paypal',
                    $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'],
                    array(
                        'gateway_transaction_id' => $capture_result['id'],
                        'status' => 'completed',
                        'fees' => $paypal_fee, // Store PayPal fee
                        'gateway_response' => json_encode($capture_result),
                        'processed_at' => current_time('mysql'),
                        'is_additional_payment' => 0 // Default to 0 for new payments (not additional)
                    )
                );
            }
            
            if ($payment_id && !is_wp_error($payment_id)) {
                // Get current booking to preserve is_anonymous field
                $booking_manager = HRB_Booking_Manager::getInstance();
                $current_booking = $booking_manager->get_booking($booking_id);
                
                // Check if this is an additional payment
                $is_additional_payment = false;
                if ($existing_payment && isset($existing_payment->is_additional_payment)) {
                    $is_additional_payment = ($existing_payment->is_additional_payment == 1);
                } else {
                    // Check the payment record directly
                    $payment_check = $wpdb->get_row($wpdb->prepare(
                        "SELECT is_additional_payment FROM {$wpdb->prefix}hrb_payments WHERE id = %d",
                        $payment_id
                    ));
                    if ($payment_check && isset($payment_check->is_additional_payment)) {
                        $is_additional_payment = ($payment_check->is_additional_payment == 1);
                    }
                }
               
                // Update booking status - both confirmed and paid, preserving is_anonymous
                $update_data = array(
                    'status' => 'confirmed',
                    'payment_status' => 'completed',
                    'payment_method' => 'paypal'
                );
                
                // Preserve is_anonymous field if it exists
                if (isset($current_booking->is_anonymous)) {
                    $update_data['is_anonymous'] = $current_booking->is_anonymous;
                }
                
                $booking_manager->update_booking($booking_id, $update_data, false); // Don't send notification during payment processing
                
                // Update booking table's total_amount and paypal_fee from payment records (source of truth)
                // This ensures consistency when additional services are added later
                $completed_payments_total = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}hrb_payments 
                    WHERE booking_id = %d AND status IN ('completed', 'paid')",
                    $booking_id
                ));
                $pending_payments_total = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}hrb_payments 
                    WHERE booking_id = %d AND status = 'pending'",
                    $booking_id
                ));
                $total_amount_from_payments = $completed_payments_total + $pending_payments_total;
                
                // PayPal fee is sum of all fees in payment records
                $total_fees_from_payments = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(fees), 0) FROM {$wpdb->prefix}hrb_payments 
                    WHERE booking_id = %d",
                    $booking_id
                ));
                
                // Update booking with accurate totals from payment records
                $booking_manager->update_booking($booking_id, [
                    'total_amount' => $total_amount_from_payments,
                    'paypal_fee' => $total_fees_from_payments
                ], false);
                
                // For additional payments: regenerate invoice (which sends updated invoice email)
                // Skip payment_confirmation email since updated invoice email is already sent
                // For original payments: send both payment_confirmation and booking_confirmation
                if ($is_additional_payment) {
                    // Regenerate invoice with updated total (this will send updated invoice email automatically)
                    $invoice_generator = HRB_Invoice_Generator::getInstance();
                    $invoice_result = $invoice_generator->regenerate_invoice($booking_id);
                    
                    if (is_wp_error($invoice_result)) {
                        // Continue anyway - invoice regeneration failure shouldn't block payment completion
                    }
                    
                    // Don't send payment_confirmation email - updated invoice email is sufficient
                    // The updated invoice email already confirms the payment
                } else {
                    // For original payments, send both notifications
                    $booking_manager->send_booking_notification($booking_id, 'payment_confirmation');
                    $booking_manager->send_booking_notification($booking_id, 'booking_confirmation');
                }
            }
            
            wp_send_json_success(array(
                'message' => __('Payment completed successfully', 'hourly-room-booking'),
                'transaction_id' => $capture_result['id']
            ));
        } else {
            // Payment failed - update both booking and payment status to failed
            $booking_manager = HRB_Booking_Manager::getInstance();
            $current_booking = $booking_manager->get_booking($booking_id);
            
            $update_data = array(
                'status' => 'failed',
                'payment_status' => 'failed',
                'payment_method' => 'paypal'
            );
            
            // Preserve is_anonymous field if it exists
            if (isset($current_booking->is_anonymous)) {
                $update_data['is_anonymous'] = $current_booking->is_anonymous;
            }
            
            $booking_manager->update_booking($booking_id, $update_data, false); // Don't send notification during payment processing
            
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
        
        // Note: booking_confirmation email is already sent by create_booking method
        // No need to send duplicate email here
        
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_die('Security check failed');
        }

        // Check admin permissions
        if (!current_user_can('hrb_manage_payments')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'hourly-room-booking')]);
        }

        $payment_id = intval($_POST['payment_id']);
        $payment_manager = HRB_Payment_Manager::getInstance();
        $booking_manager = HRB_Booking_Manager::getInstance();
        
        // Get payment record to find booking_id and old status
        $payment = $payment_manager->get_payment($payment_id);
        if (!$payment) {
            wp_send_json_error(['message' => __('Payment not found', 'hourly-room-booking')]);
        }

        // Cancellation-fee charges are standalone: mark only this payment row as
        // collected. Do NOT touch the booking — it must stay cancelled — and do
        // not sync the booking payment status, re-confirm it, generate an
        // invoice, or send a payment-confirmation email.
        if (strpos((string) $payment->transaction_id, 'CANCELFEE_') === 0) {
            $result = $payment_manager->update_payment_status($payment_id, 'completed');
            if ($result) {
                wp_send_json_success(['message' => __('Cancellation fee marked as paid', 'hourly-room-booking')]);
            } else {
                wp_send_json_error(['message' => __('Failed to update payment status', 'hourly-room-booking')]);
            }
            return;
        }

        $booking_id = $payment->booking_id;
        $old_payment_status = $payment->status;
        
        // Update payment status
        $result = $payment_manager->update_payment_status($payment_id, 'completed');
        
        if ($result) {
            // Sync payment status to booking table
            $booking_manager->update_booking($booking_id, array(
                'payment_status' => 'completed'
            ), false); // Don't send notification during update
            
            // Get updated booking
            $booking = $booking_manager->get_booking($booking_id);
            
            if ($booking) {
                // Normalize payment status values for comparison
                $new_payment_status_normalized = 'completed';
                $old_payment_status_normalized = strtolower(trim($old_payment_status));
                
                // If payment status changed to paid/completed, generate invoice and send payment confirmation email
                $paid_statuses = ['paid', 'completed'];
                if (in_array($new_payment_status_normalized, $paid_statuses) && 
                    !in_array($old_payment_status_normalized, $paid_statuses)) {
                    
                    // Ensure booking status is confirmed (required for invoice and email)
                    if ($booking->status !== 'confirmed') {
                        $booking_manager->update_booking($booking_id, array('status' => 'confirmed'), false);
                        $booking = $booking_manager->get_booking($booking_id);
                    }
                    
                    // Generate invoice if it doesn't exist
                    $invoice_generator = HRB_Invoice_Generator::getInstance();
                    $existing_invoice = $invoice_generator->get_invoice_by_booking($booking_id);
                    
                    if (!$existing_invoice) {
                        $invoice_id = $booking_manager->create_invoice($booking_id);
                        if (!is_wp_error($invoice_id)) {
                            // Generate PDF for the invoice
                            $invoice_generator->generate_invoice_pdf($invoice_id);
                        }
                    } else {
                        // Ensure PDF exists
                        if (empty($existing_invoice->pdf_file_path)) {
                            $invoice_generator->generate_invoice_pdf($existing_invoice->id);
                        }
                    }
                    
                    // Send payment confirmation email
                    $booking_manager->send_booking_notification($booking_id, 'payment_confirmation');
                }
            }
            
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_die('Security check failed');
        }

        // Check admin permissions
        if (!current_user_can('hrb_manage_payments')) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_die('Security check failed');
        }

        // Check admin permissions
        if (!current_user_can('hrb_manage_payments')) {
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
        $html .= '<tr><th>' . __('Customer', 'hourly-room-booking') . '</th><td>' . hrb_display_customer_info($payment, 'name_email') . '</td></tr>';
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_die('Security check failed');
        }

        // Check admin permissions
        if (!current_user_can('hrb_manage_payments')) {
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
        if (!current_user_can('hrb_manage_payments')) {
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
        global $wpdb;

        // SECURITY: same guard as the public AJAX endpoint — verify the order
        // was created by us for this booking before contacting PayPal.
        $expected_payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_payments
             WHERE booking_id = %d
               AND payment_method = 'paypal'
               AND status = 'pending'
               AND gateway_transaction_id = %s
             ORDER BY id DESC LIMIT 1",
            $booking_id,
            $order_id
        ));

        if (!$expected_payment) {
            // Idempotent: already captured?
            $already_completed = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}hrb_payments
                 WHERE booking_id = %d
                   AND payment_method = 'paypal'
                   AND status = 'completed'
                   AND gateway_transaction_id = %s
                 LIMIT 1",
                $booking_id,
                $order_id
            ));
            if ($already_completed) {
                return true;
            }
            return new WP_Error('hrb_payment_not_found', __('Payment record not found for this order. Capture rejected.', 'hourly-room-booking'));
        }

        $expected_amount   = floatval($expected_payment->amount);
        $expected_currency = strtoupper((string) $expected_payment->currency);

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
            // SECURITY: defense-in-depth amount/currency check.
            $captured_amount   = floatval($capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value']);
            $captured_currency = strtoupper((string) $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code']);

            if (abs($captured_amount - $expected_amount) > 0.01 || $captured_currency !== $expected_currency) {
                $wpdb->update(
                    $wpdb->prefix . 'hrb_payments',
                    array(
                        'status'           => 'failed',
                        'transaction_id'   => $capture_result['id'],
                        'amount'           => $captured_amount,
                        'currency'         => $captured_currency,
                        'gateway_response' => json_encode($capture_result),
                        'processed_at'     => current_time('mysql'),
                    ),
                    array('id' => $expected_payment->id),
                    array('%s', '%s', '%f', '%s', '%s', '%s'),
                    array('%d')
                );
                return new WP_Error('hrb_payment_amount_mismatch', sprintf(
                    __('Payment amount mismatch (expected %1$s %2$s, captured %3$s %4$s). Please contact support.', 'hourly-room-booking'),
                    number_format($expected_amount, 2),
                    $expected_currency,
                    number_format($captured_amount, 2),
                    $captured_currency
                ));
            }

            // Check if this payment has already been captured to prevent duplicates
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
                // Check if this is an additional payment
                $is_additional_payment = false;
                $payment_check = $wpdb->get_row($wpdb->prepare(
                    "SELECT is_additional_payment FROM {$wpdb->prefix}hrb_payments WHERE id = %d",
                    $payment_id
                ));
                if ($payment_check && isset($payment_check->is_additional_payment)) {
                    $is_additional_payment = ($payment_check->is_additional_payment == 1);
                }
                
                // Update booking status - both confirmed and paid
                $booking_manager = HRB_Booking_Manager::getInstance();
                $booking_manager->update_booking($booking_id, array(
                    'status' => 'confirmed',
                    'payment_status' => 'completed',
                    'payment_method' => 'paypal'
                ), false); // Don't send notification during payment processing
                
                // For additional payments: regenerate invoice (which sends updated invoice email)
                // Skip payment_confirmation email since updated invoice email is already sent
                // For original payments: send both payment_confirmation and booking_confirmation
                if ($is_additional_payment) {
                    // Regenerate invoice with updated total (this will send updated invoice email automatically)
                    $invoice_generator = HRB_Invoice_Generator::getInstance();
                    $invoice_result = $invoice_generator->regenerate_invoice($booking_id);
                    
                    if (is_wp_error($invoice_result)) {
                        // Continue anyway - invoice regeneration failure shouldn't block payment completion
                    }
                    
                    // Don't send payment_confirmation email - updated invoice email is sufficient
                    // The updated invoice email already confirms the payment
                } else {
                    // For original payments, send both notifications
                    $booking_manager->send_booking_notification($booking_id, 'payment_confirmation');
                    $booking_manager->send_booking_notification($booking_id, 'booking_confirmation');
                }
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