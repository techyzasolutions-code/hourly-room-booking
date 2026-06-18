<?php
/**
 * Payment Manager Class
 * Handles payment data management for admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Payment_Manager {

    private static $instance = null;

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Constructor can be empty for now
    }

    /**
     * Get payments with filtering and pagination
     */
    public function get_payments($filters = [], $limit = 20, $offset = 0) {
        global $wpdb;

        $where_conditions = ['1=1'];
        $where_values = [];

        // Status filter
        if (!empty($filters['status'])) {
            $where_conditions[] = 'p.status = %s';
            $where_values[] = $filters['status'];
        }

        // Payment method filter
        if (!empty($filters['payment_method'])) {
            $where_conditions[] = 'p.payment_method = %s';
            $where_values[] = $filters['payment_method'];
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'DATE(p.created_at) >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'DATE(p.created_at) <= %s';
            $where_values[] = $filters['date_to'];
        }

        // Search filter
        if (!empty($filters['search'])) {
            $where_conditions[] = '(p.transaction_id LIKE %s OR p.gateway_transaction_id LIKE %s OR CONCAT(c.first_name, " ", c.last_name) LIKE %s OR c.email LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Get total count
        $count_query = "
            SELECT COUNT(*)
            FROM {$wpdb->prefix}hrb_payments p
            LEFT JOIN {$wpdb->prefix}hrb_bookings b ON p.booking_id = b.id
            LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
            LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            WHERE {$where_clause}
        ";

        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }

        $total = $wpdb->get_var($count_query);

        // Get payments data
        $query = "
            SELECT
                p.id,
                p.booking_id,
                p.transaction_id,
                p.gateway_transaction_id,
                p.amount,
                p.fees,
                p.refunded_amount,
                p.payment_method,
                p.status,
                p.created_at,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.is_anonymous,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                c.email as customer_email,
                r.name as room_name
            FROM {$wpdb->prefix}hrb_payments p
            LEFT JOIN {$wpdb->prefix}hrb_bookings b ON p.booking_id = b.id
            LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
            LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            WHERE {$where_clause}
            ORDER BY p.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $query_values = array_merge($where_values, [$limit, $offset]);
        $payments = $wpdb->get_results($wpdb->prepare($query, $query_values));

        return [
            'payments' => $payments,
            'total' => intval($total)
        ];
    }

    /**
     * Get payment statistics
     */
    public function get_payment_statistics($filters = []) {
        global $wpdb;

        $where_conditions = ['1=1'];
        $where_values = [];

        // Apply same filters as get_payments if needed
        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $filters['status'];
        }

        if (!empty($filters['payment_method'])) {
            $where_conditions[] = 'payment_method = %s';
            $where_values[] = $filters['payment_method'];
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'DATE(created_at) >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'DATE(created_at) <= %s';
            $where_values[] = $filters['date_to'];
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Total revenue
        $total_revenue_query = "
            SELECT COALESCE(SUM(amount), 0)
            FROM {$wpdb->prefix}hrb_payments
            WHERE status = 'completed' AND {$where_clause}
        ";

        if (!empty($where_values)) {
            $total_revenue = $wpdb->get_var($wpdb->prepare($total_revenue_query, $where_values));
        } else {
            $total_revenue = $wpdb->get_var($total_revenue_query);
        }

        // Monthly revenue (current month)
        $monthly_revenue_query = "
            SELECT COALESCE(SUM(amount), 0)
            FROM {$wpdb->prefix}hrb_payments
            WHERE status = 'completed'
            AND YEAR(created_at) = YEAR(CURDATE())
            AND MONTH(created_at) = MONTH(CURDATE())
        ";
        $monthly_revenue = $wpdb->get_var($monthly_revenue_query);

        // Total transactions
        $total_transactions_query = "
            SELECT COUNT(*)
            FROM {$wpdb->prefix}hrb_payments
            WHERE {$where_clause}
        ";

        if (!empty($where_values)) {
            $total_transactions = $wpdb->get_var($wpdb->prepare($total_transactions_query, $where_values));
        } else {
            $total_transactions = $wpdb->get_var($total_transactions_query);
        }

        // Pending amount (sum of payments still awaiting collection/completion)
        $pending_amount_query = "
            SELECT COALESCE(SUM(amount), 0)
            FROM {$wpdb->prefix}hrb_payments
            WHERE status = 'pending' AND {$where_clause}
        ";

        if (!empty($where_values)) {
            $pending_amount = $wpdb->get_var($wpdb->prepare($pending_amount_query, $where_values));
        } else {
            $pending_amount = $wpdb->get_var($pending_amount_query);
        }

        return [
            'total_revenue' => floatval($total_revenue),
            'monthly_revenue' => floatval($monthly_revenue),
            'total_transactions' => intval($total_transactions),
            'pending_amount' => floatval($pending_amount)
        ];
    }

    /**
     * Get payment details by ID
     */
    public function get_payment($payment_id) {
        global $wpdb;

        $query = "
            SELECT
                p.*,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.is_anonymous,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                r.name as room_name
            FROM {$wpdb->prefix}hrb_payments p
            LEFT JOIN {$wpdb->prefix}hrb_bookings b ON p.booking_id = b.id
            LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
            LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            WHERE p.id = %d
        ";

        return $wpdb->get_row($wpdb->prepare($query, $payment_id));
    }

    /**
     * Process refund
     */
    public function process_refund($payment_id, $refund_amount, $reason = '', $notify_customer = true) {
        global $wpdb;

        $payment = $this->get_payment($payment_id);
        if (!$payment) {
            return new WP_Error('payment_not_found', __('Payment not found', 'hourly-room-booking'));
        }

        if ($payment->status !== 'completed') {
            return new WP_Error('invalid_status', __('Can only refund completed payments', 'hourly-room-booking'));
        }

        $available_amount = $payment->amount - $payment->refunded_amount;
        if ($refund_amount > $available_amount) {
            return new WP_Error('invalid_amount', __('Refund amount exceeds available amount', 'hourly-room-booking'));
        }

        // Update refunded amount
        $new_refunded_amount = $payment->refunded_amount + $refund_amount;
        $new_status = ($new_refunded_amount >= $payment->amount) ? 'refunded' : 'partially_refunded';

        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_payments',
            [
                'refunded_amount' => $new_refunded_amount,
                'status' => $new_status,
                'refund_reason' => $reason
            ],
            ['id' => $payment_id],
            ['%f', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to process refund', 'hourly-room-booking'));
        }

        // Note: Integrate with payment gateway for actual refund processing
        // For now, this is just updating the database

        // Send notification email if requested
        if ($notify_customer && !empty($payment->customer_email)) {
            // Send refund notification email
        }

        return true;
    }

    /**
     * Cancel payment
     */
    public function cancel_payment($payment_id) {
        global $wpdb;

        $payment = $this->get_payment($payment_id);
        if (!$payment) {
            return new WP_Error('payment_not_found', __('Payment not found', 'hourly-room-booking'));
        }

        if ($payment->status !== 'pending') {
            return new WP_Error('invalid_status', __('Can only cancel pending payments', 'hourly-room-booking'));
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_payments',
            ['status' => 'cancelled'],
            ['id' => $payment_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to cancel payment', 'hourly-room-booking'));
        }

        return true;
    }

    /**
     * Get refund information for a payment
     */
    public function get_refund_info($payment_id) {
        $payment = $this->get_payment($payment_id);
        if (!$payment) {
            return new WP_Error('payment_not_found', __('Payment not found', 'hourly-room-booking'));
        }

        $available_refund = $payment->amount - $payment->refunded_amount;

        return [
            'payment_id' => $payment->id,
            'total_amount' => $payment->amount,
            'refunded_amount' => $payment->refunded_amount,
            'available_refund' => $available_refund,
            'can_refund' => $payment->status === 'completed' && $available_refund > 0
        ];
    }

    /**
     * Create a payment record for a booking (centralized method)
     */
    public function create_payment($booking_id, $amount, $payment_method, $currency = 'EUR', $additional_data = array()) {
        global $wpdb;

        // Default values
        $defaults = array(
            'booking_id' => $booking_id,
            'transaction_id' => 'TXN_' . time() . '_' . $booking_id,
            'gateway_transaction_id' => '',
            'payment_method' => $payment_method,
            'amount' => $amount,
            'fees' => 0.00,
            'refunded_amount' => 0.00,
            'currency' => $currency,
            'status' => 'pending',
            'refund_reason' => null,
            'gateway_response' => '',
            'processed_at' => null,
            'created_at' => current_time('mysql')
        );

        // Merge with additional data (for PayPal, etc.)
        $payment_data = wp_parse_args($additional_data, $defaults);

        // Generate format array dynamically
        $format = array();
        foreach ($payment_data as $key => $value) {
            if (is_int($value)) {
                $format[] = '%d';
            } elseif (is_float($value)) {
                $format[] = '%f';
            } else {
                $format[] = '%s';
            }
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'hrb_payments',
            $payment_data,
            $format
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create payment record', 'hourly-room-booking'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Update payment status
     */
    public function update_payment_status($payment_id, $status, $gateway_transaction_id = null, $gateway_response = null) {
        global $wpdb;

        $update_data = [
            'status' => $status
        ];
        $update_format = ['%s'];

        if ($status === 'completed') {
            $update_data['processed_at'] = current_time('mysql');
            $update_format[] = '%s';
        }

        if ($gateway_transaction_id) {
            $update_data['gateway_transaction_id'] = $gateway_transaction_id;
            $update_format[] = '%s';
        }

        if ($gateway_response) {
            $update_data['gateway_response'] = $gateway_response;
            $update_format[] = '%s';
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_payments',
            $update_data,
            ['id' => $payment_id],
            $update_format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get payment by booking ID
     */
    public function get_payment_by_booking($booking_id) {
        global $wpdb;

        // Exclude the standalone cancellation-fee charge (CANCELFEE_*): it is a
        // separate charge, not the booking's own payment, so it must not drive
        // the booking's payment status.
        $query = "
            SELECT p.*
            FROM {$wpdb->prefix}hrb_payments p
            WHERE p.booking_id = %d
            AND (p.transaction_id NOT LIKE 'CANCELFEE%' OR p.transaction_id IS NULL)
            ORDER BY p.created_at DESC
            LIMIT 1
        ";

        return $wpdb->get_row($wpdb->prepare($query, $booking_id));
    }

    /**
     * Migrate existing bookings to create payment records
     */
    public function migrate_existing_bookings() {
        global $wpdb;

        // Find bookings without payment records
        $bookings = $wpdb->get_results("
            SELECT b.id, b.total_amount, b.payment_method, b.payment_status, b.created_at
            FROM {$wpdb->prefix}hrb_bookings b
            LEFT JOIN {$wpdb->prefix}hrb_payments p ON b.id = p.booking_id
            WHERE p.id IS NULL
        ");

        $created_count = 0;
        $currency = HRB_Currency_Manager::getInstance()->get_currency_code();

        foreach ($bookings as $booking) {
            $payment_method = $booking->payment_method ?: 'onsite';
            $payment_status = 'completed'; // Assume existing bookings are completed

            // Generate transaction ID for existing booking
            $transaction_id = 'TXN_MIGRATED_' . $booking->id . '_' . time();

            $result = $wpdb->insert(
                $wpdb->prefix . 'hrb_payments',
                [
                    'booking_id' => $booking->id,
                    'transaction_id' => $transaction_id,
                    'payment_method' => $payment_method,
                    'amount' => $booking->total_amount,
                    'currency' => $currency,
                    'status' => $payment_status,
                    'processed_at' => $booking->created_at,
                    'created_at' => $booking->created_at
                ],
                ['%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
            );

            if ($result !== false) {
                $created_count++;
            }
        }

        return $created_count;
    }

    /**
     * Export payments data
     */
    public function export_payments($filters = []) {
        $payments_data = $this->get_payments($filters, 10000, 0); // Get all payments for export
        $payments = $payments_data['payments'];

        $csv_data = [];
        $csv_data[] = [
            'ID',
            'Transaction ID',
            'Gateway Transaction ID',
            'Booking ID',
            'Customer',
            'Email',
            'Room',
            'Amount',
            'Fees',
            'Refunded Amount',
            'Payment Method',
            'Status',
            'Created At'
        ];

        foreach ($payments as $payment) {
            $csv_data[] = [
                $payment->id,
                $payment->transaction_id,
                $payment->gateway_transaction_id,
                $payment->booking_id,
                (isset($payment->is_anonymous) && $payment->is_anonymous) ? 'Anonymous' : ($payment->customer_name ?? 'N/A'),
                (isset($payment->is_anonymous) && $payment->is_anonymous) ? 'No contact information' : ($payment->customer_email ?? 'N/A'),
                $payment->room_name,
                $payment->amount,
                $payment->fees,
                $payment->refunded_amount,
                $payment->payment_method,
                $payment->status,
                $payment->created_at
            ];
        }

        return $csv_data;
    }
}