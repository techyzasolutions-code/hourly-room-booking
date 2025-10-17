<?php
/**
 * Customer Manager Class
 * Handles customer-related operations
 */


if (!defined('ABSPATH')) {
    exit;
}

class HRB_Customer_Manager {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Get customer by ID
     */
    public function get_customer($customer_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE id = %d",
            $customer_id
        ));
    }
    
    /**
     * Get customer by email
     */
    public function get_customer_by_email($email) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE email = %s",
            $email
        ));
    }
    
    /**
     * Create customer
     */
    public function create_customer($data) {
        global $wpdb;
        
        $defaults = array(
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'company' => '',
            'address' => '',
            'city' => '',
            'postal_code' => '',
            'country' => 'DE',
            'date_of_birth' => null,
            'is_verified' => 0
        );
        
        $customer_data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($customer_data['first_name']) || empty($customer_data['email'])) {
            return new WP_Error('missing_fields', __('Required fields are missing', 'hourly-room-booking'));
        }
        
        // Check if email already exists
        $existing = $this->get_customer_by_email($customer_data['email']);
        if ($existing) {
            return new WP_Error('email_exists', __('Customer with this email already exists', 'hourly-room-booking'));
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'hrb_customers',
            $customer_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create customer', 'hourly-room-booking'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update customer
     */
    public function update_customer($customer_id, $data) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_customers',
            $data,
            array('id' => $customer_id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update customer', 'hourly-room-booking'));
        }
        
        return true;
    }
    
    /**
     * Get customer bookings
     */
    public function get_customer_bookings($customer_id, $limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, r.name as room_name 
             FROM {$wpdb->prefix}hrb_bookings b
             JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
             WHERE b.customer_id = %d 
             ORDER BY b.booking_date DESC, b.start_time DESC 
             LIMIT %d",
            $customer_id, $limit
        ));
    }
    
    /**
     * Get customer statistics
     */
    public function get_customer_stats($customer_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(total_amount) as total_spent,
                AVG(total_amount) as avg_booking_value,
                MIN(booking_date) as first_booking_date,
                MAX(booking_date) as last_booking_date
             FROM {$wpdb->prefix}hrb_bookings 
             WHERE customer_id = %d",
            $customer_id
        ));
    }
    
    /**
     * Get all customers with filters
     */
    public function get_customers($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $params = array();
        
        // Search filter
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_conditions[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        // Verification filter
        if (isset($filters['verified'])) {
            $where_conditions[] = 'is_verified = %d';
            $params[] = intval($filters['verified']);
        }
        
        // Date range filter
        if (!empty($filters['start_date'])) {
            $where_conditions[] = 'created_at >= %s';
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_conditions[] = 'created_at <= %s';
            $params[] = $filters['end_date'];
        }
        
        // Pagination
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
        $offset = isset($filters['offset']) ? intval($filters['offset']) : 0;
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT c.*, 
                       COUNT(b.id) as total_bookings,
                       SUM(b.total_amount) as total_spent,
                       MAX(b.created_at) as last_booking
                FROM {$wpdb->prefix}hrb_customers c
                LEFT JOIN {$wpdb->prefix}hrb_bookings b ON c.id = b.customer_id
                WHERE $where_clause
                GROUP BY c.id
                ORDER BY c.created_at DESC
                LIMIT %d OFFSET %d";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Delete customer
     */
    public function delete_customer($customer_id, $force = false) {
        global $wpdb;
        
        $customer = $this->get_customer($customer_id);
        if (!$customer) {
            return new WP_Error('customer_not_found', __('Customer not found', 'hourly-room-booking'));
        }
        
        // Check for existing bookings
        $booking_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_bookings 
             WHERE customer_id = %d AND status NOT IN ('cancelled')",
            $customer_id
        ));
        
        if ($booking_count > 0 && !$force) {
            return new WP_Error('customer_has_bookings', __('Cannot delete customer with active bookings', 'hourly-room-booking'));
        }
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'hrb_customers',
            array('id' => $customer_id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete customer', 'hourly-room-booking'));
        }
        
        return true;
    }
}
?>