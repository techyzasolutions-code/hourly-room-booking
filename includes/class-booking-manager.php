<?php
/**
 * Booking Manager Class
 * Handles all booking-related operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Booking_Manager {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize hooks
        add_action('wp_loaded', array($this, 'schedule_events'));
        add_action('hrb_cleanup_expired_bookings', array($this, 'cleanup_expired_bookings'));
        add_action('hrb_send_booking_reminders', array($this, 'send_booking_reminders'));
    }
    
    /**
     * Schedule recurring events
     */
    public function schedule_events() {
        if (!wp_next_scheduled('hrb_cleanup_expired_bookings')) {
            wp_schedule_event(time(), 'daily', 'hrb_cleanup_expired_bookings');
        }
        
        if (!wp_next_scheduled('hrb_send_booking_reminders')) {
            wp_schedule_event(time(), 'hourly', 'hrb_send_booking_reminders');
        }
    }
    
    /**
     * Create a new booking
     */
    public function create_booking($data) {
        global $wpdb;
        // Validate required fields
        $required_fields = array('room_id', 'customer_id', 'booking_date', 'start_time', 'end_time');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Field %s is required', 'hourly-room-booking'), $field));
            }
        }
        
        // Validate booking rules
        $validation = $this->validate_booking_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Check for conflicts
        if (HRB_Database::check_booking_conflict($data['room_id'], $data['booking_date'], $data['start_time'], $data['end_time'])) {
            return new WP_Error('booking_conflict', __('Selected time slot is not available', 'hourly-room-booking'));
        }
        
        // Calculate pricing
        $pricing = $this->calculate_booking_price($data);
        
        // Prepare booking data
        $booking_data = array(
            'booking_reference' => HRB_Database::generate_booking_reference(),
            'room_id' => intval($data['room_id']),
            'customer_id' => intval($data['customer_id']),
            'booking_date' => sanitize_text_field($data['booking_date']),
            'start_time' => sanitize_text_field($data['start_time']),
            'end_time' => sanitize_text_field($data['end_time']),
            'total_hours' => $this->calculate_duration($data['start_time'], $data['end_time']),
            'base_price' => $pricing['base_price'],
            'extra_people' => isset($data['extra_people']) ? intval($data['extra_people']) : 0,
            'extra_people_price' => $pricing['extra_people_cost'],
            'extras_price' => $pricing['extras_cost'],
            'tax_amount' => $pricing['tax_amount'],
            'paypal_fee' => $pricing['paypal_fee'],
            'total_amount' => $pricing['total_amount'],
            'status' => isset($data['status']) ? $data['status'] : 'pending',
            'payment_status' => isset($data['payment_status']) ? $data['payment_status'] : 'pending',
            'payment_method' => isset($data['payment_method']) ? sanitize_text_field($data['payment_method']) : null,
            'special_requests' => isset($data['special_requests']) ? sanitize_textarea_field($data['special_requests']) : null,
            'admin_notes' => isset($data['admin_notes']) ? sanitize_textarea_field($data['admin_notes']) : null,
            'created_by_admin' => isset($data['created_by_admin']) ? intval($data['created_by_admin']) : 0,
            'cooldown_override' => isset($data['cooldown_override']) ? intval($data['cooldown_override']) : 0
        );
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Insert booking
            $result = $wpdb->insert(
                $wpdb->prefix . 'hrb_bookings',
                $booking_data,
                array('%s', '%d', '%d', '%s', '%s', '%s', '%f', '%f', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d')
            );
           
            if ($result === false) {
                $wpdb_error = $wpdb->last_error;
                throw new Exception(__('Failed to create booking', 'hourly-room-booking') . ': ' . $wpdb_error);
            }
            
            $booking_id = $wpdb->insert_id;

            // Create payment record (except for PayPal which handles its own payment records)
            $payment_method = $booking_data['payment_method'] ?: 'onsite';
            if ($payment_method !== 'paypal') {
                $payment_manager = HRB_Payment_Manager::getInstance();
                $currency = HRB_Currency_Manager::getInstance()->get_currency_code();

                $payment_id = $payment_manager->create_payment(
                    $booking_id,
                    $booking_data['total_amount'],
                    $payment_method,
                    $currency
                );

                if (is_wp_error($payment_id)) {
                    throw new Exception($payment_id->get_error_message());
                }
            }

            // Create invoice if booking is confirmed
            if ($booking_data['status'] === 'confirmed') {
                $invoice_id = $this->create_invoice($booking_id);
                if (is_wp_error($invoice_id)) {
                    throw new Exception($invoice_id->get_error_message());
                }
            }

            $wpdb->query('COMMIT');
            
            return $booking_id;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('booking_creation_failed', $e->getMessage());
        }
    }
    
    /**
     * Validate booking data
     */
    public function validate_booking_data($data, $allow_past_dates = false) {
        // Validate room exists and is active
        $room = HRB_Room_Manager::getInstance()->get_room($data['room_id']);
        if (!$room || !$room->is_active) {
            return new WP_Error('invalid_room', __('Selected room is not available', 'hourly-room-booking'));
        }
        
        // Validate date (not in the past, within booking window)
        $booking_date = strtotime($data['booking_date']);
        $today = strtotime('today');
        
        if (!$allow_past_dates && $booking_date < $today) {
            return new WP_Error('past_date', __('Cannot book for past dates', 'hourly-room-booking'));
        }
        
        $max_advance_days = get_option('hrb_booking_advance_days', 30);
        $max_booking_date = strtotime("+{$max_advance_days} days");
        
        if ($booking_date > $max_booking_date) {
            return new WP_Error('too_far_advance',
                sprintf(__('Cannot book more than %d days in advance', 'hourly-room-booking'), $max_advance_days));
        }

        // Validate booking duration (2-12 hours as per requirements)
        $duration = $this->calculate_duration($data['start_time'], $data['end_time']);
        if ($duration < 2) {
            return new WP_Error('minimum_duration', __('Minimum booking duration is 2 hours', 'hourly-room-booking'));
        }
        if ($duration > 12) {
            return new WP_Error('maximum_duration', __('Maximum booking duration is 12 hours', 'hourly-room-booking'));
        }

        // Validate payment method based on duration (4+ hours must use PayPal)
        if ($duration >= 4 && isset($data['payment_method']) && $data['payment_method'] !== 'paypal') {
            return new WP_Error('payment_method_required', __('Bookings of 4 hours or more require PayPal payment', 'hourly-room-booking'));
        }

        // Validate time slots (already calculated duration above)
        
        if ($duration < 2) {
            return new WP_Error('min_duration', __('Minimum booking duration is 2 hours', 'hourly-room-booking'));
        }
        
        if ($duration > 12) {
            return new WP_Error('max_duration', __('Maximum booking duration is 12 hours', 'hourly-room-booking'));
        }
        
        // Validate business hours
        $business_start = get_option('hrb_booking_start_time', '08:00');
        $business_end = get_option('hrb_booking_end_time', '20:00');
        
        if ($data['start_time'] < $business_start || $data['end_time'] > $business_end) {
            return new WP_Error('outside_business_hours', 
                sprintf(__('Bookings must be between %s and %s', 'hourly-room-booking'), $business_start, $business_end));
        }
        
        return true;
    }
    
    /**
     * Calculate booking duration in hours
     */
    private function calculate_duration($start_time, $end_time) {
        $start = strtotime($start_time);
        $end = strtotime($end_time);
        return ($end - $start) / 3600;
    }
    
    /**
     * Save extras for a booking
     */
    public function save_booking_extras($booking_id, $extras_data, $booking_date, $start_time, $end_time) {
        global $wpdb;
        
        // First, remove all existing extras for this booking
        $wpdb->delete(
            $wpdb->prefix . 'hrb_booking_extras',
            ['booking_id' => $booking_id],
            ['%d']
        );
        
        if (empty($extras_data)) {
            return true;
        }
        
        $stock_manager = HRB_Extra_Stock_Manager::getInstance();
        $extras_manager = HRB_Extras::getInstance();
        
        foreach ($extras_data as $index => $extra) {
            // Handle both formats: direct ID or array with ID
            $extra_id = is_array($extra) ? intval($extra['id']) : intval($extra);
            
            if ($extra_id > 0) {
                // Check availability first
                $availability = $stock_manager->check_availability(
                    $extra_id,
                    $booking_date,
                    $start_time,
                    $end_time,
                    1
                );
                
                if (!$availability['available']) {
                    return new WP_Error('extra_unavailable', sprintf(__('Sorry, extra item is no longer available: %s', 'hourly-room-booking'), $availability['reason']));
                }
                
                // Save to booking_extras table for pricing and stock management
                $extra = $extras_manager->get_extra($extra_id);
                if ($extra) {
                    // Insert new extra (since we cleared all existing ones)
                    $result = $wpdb->insert(
                        $wpdb->prefix . 'hrb_booking_extras',
                        [
                            'booking_id' => $booking_id,
                            'extra_id' => $extra_id,
                            'quantity' => 1,
                            'unit_price' => $extra->price,
                            'total_price' => $extra->price,
                            'booking_date' => $booking_date,
                            'start_time' => $start_time,
                            'end_time' => $end_time
                        ],
                        ['%d', '%d', '%d', '%f', '%f', '%s', '%s', '%s']
                    );
                    
                    if ($result === false) {
                        return new WP_Error('extras_save_failed', __('Failed to save extras pricing', 'hourly-room-booking'));
                    }
                }
            }
        }
        
        return true;
    }

    /**
     * Calculate booking price
     */
    public function calculate_booking_price($data) {
        $room = HRB_Room_Manager::getInstance()->get_room($data['room_id']);
        $duration = $this->calculate_duration($data['start_time'], $data['end_time']);

        // Calculate base price using room-specific or global pricing
        $base_price = $this->calculate_base_price($room, $duration);

        // Add extra people cost (€15 per person, max 10 extra people)
        $extra_people_cost = 0;
        if (isset($data['extra_people']) && $data['extra_people'] > 0) {
            $extra_people_cost = min($data['extra_people'], 10) * 15.00;
        }

        // Add extras cost
        $extras_cost = 0;
        if (isset($data['extras']) && is_array($data['extras'])) {
            $extras_manager = HRB_Extras::getInstance();
            foreach ($data['extras'] as $extra) {
                if (is_array($extra) && isset($extra['price'])) {
                    // Old format with price field
                    $extras_cost += floatval($extra['price']);
                } elseif (is_numeric($extra)) {
                    // New format with just extra ID
                    $extra_obj = $extras_manager->get_extra($extra);
                    if ($extra_obj) {
                        $extras_cost += floatval($extra_obj->price);
                    }
                }
            }
        }

        // Calculate subtotal before fees
        $subtotal = $base_price + $extra_people_cost + $extras_cost;

        // Calculate VAT (19% in Germany)
        $tax_rate = floatval(get_option('hrb_tax_rate', 19)) / 100; // Convert percentage to decimal
        $tax_amount = $subtotal * $tax_rate;

        // Calculate PayPal fee (3% on subtotal) - only if PayPal is selected
        $paypal_fee = 0;
        if (isset($data['payment_method']) && $data['payment_method'] === 'paypal') {
            $paypal_fee = $subtotal * 0.03;
        }

        // Total amount includes everything
        $total_amount = $subtotal + $tax_amount + $paypal_fee;

        return [
            'base_price' => round($base_price, 2),
            'extra_people_cost' => round($extra_people_cost, 2),
            'extras_cost' => round($extras_cost, 2),
            'subtotal' => round($subtotal, 2),
            'tax_rate' => round($tax_rate * 100, 2), // Return as percentage
            'tax_amount' => round($tax_amount, 2),
            'paypal_fee' => round($paypal_fee, 2),
            'total_amount' => round($total_amount, 2)
        ];
    }
    
    /**
     * Calculate base price using room-specific or global pricing
     */
    public function calculate_base_price($room, $duration) {
        // Check if room has specific pricing for this duration
        $room_price = 0;
        $use_room_price = false;
        
        if ($duration == 2 && $room->price_2_hours > 0) {
            $room_price = floatval($room->price_2_hours);
            $use_room_price = true;
        } elseif ($duration == 3 && $room->price_3_hours > 0) {
            $room_price = floatval($room->price_3_hours);
            $use_room_price = true;
        } elseif ($duration == 4 && $room->price_4_hours > 0) {
            $room_price = floatval($room->price_4_hours);
            $use_room_price = true;
        } elseif ($duration > 4) {
            // For durations > 4 hours, use 4-hour price + extra hours
            if ($room->price_4_hours > 0) {
                $room_price = floatval($room->price_4_hours);
                $use_room_price = true;
                
                // Add extra hours using room-specific or global extra hour price
                $extra_hours = $duration - 4;
                $extra_hour_price = $room->price_extra_hour > 0 ? 
                    floatval($room->price_extra_hour) : 
                    floatval(get_option('hrb_price_extra_hour', 0));
                
                if ($extra_hour_price > 0) {
                    $room_price += $extra_hours * $extra_hour_price;
                }
            }
        }
        
        // If room has specific pricing, use it
        if ($use_room_price) {
            return $room_price;
        }
        
        // Fallback to global pricing
        $global_price = 0;
        if ($duration == 2) {
            $global_price = floatval(get_option('hrb_price_2_hours', 0));
        } elseif ($duration == 3) {
            $global_price = floatval(get_option('hrb_price_3_hours', 0));
        } elseif ($duration == 4) {
            $global_price = floatval(get_option('hrb_price_4_hours', 0));
        } elseif ($duration > 4) {
            $global_price = floatval(get_option('hrb_price_4_hours', 0));
            $extra_hours = $duration - 4;
            $extra_hour_price = floatval(get_option('hrb_price_extra_hour', 0));
            if ($extra_hour_price > 0) {
                $global_price += $extra_hours * $extra_hour_price;
            }
        }
        
        // If global pricing is available, use it
        if ($global_price > 0) {
            return $global_price;
        }
        
        // No pricing found - return 0
        return 0;
    }
    
    /**
     * Get booking by ID
     */
    public function get_booking($booking_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, r.name as room_name, c.first_name, c.last_name, c.email, c.phone, c.company,
                    p.status as actual_payment_status, p.transaction_id, p.processed_at
             FROM {$wpdb->prefix}hrb_bookings b
             JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
             JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
             LEFT JOIN {$wpdb->prefix}hrb_payments p ON b.id = p.booking_id
             WHERE b.id = %d",
            $booking_id
        ));
    }
    
    /**
     * Get booking by reference
     */
    public function get_booking_by_reference($reference) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, r.name as room_name, c.first_name, c.last_name, c.email, c.phone, c.company
             FROM {$wpdb->prefix}hrb_bookings b
             JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
             JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
             WHERE b.booking_reference = %s",
            $reference
        ));
    }
    
    /**
     * Update booking
     */
    public function update_booking($booking_id, $data, $send_notification = true) {
        global $wpdb;
        
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        // Validate updates
        if (isset($data['booking_date']) || isset($data['start_time']) || isset($data['end_time']) || isset($data['room_id'])) {
            $check_data = array(
                'room_id' => isset($data['room_id']) ? $data['room_id'] : $booking->room_id,
                'booking_date' => isset($data['booking_date']) ? $data['booking_date'] : $booking->booking_date,
                'start_time' => isset($data['start_time']) ? $data['start_time'] : $booking->start_time,
                'end_time' => isset($data['end_time']) ? $data['end_time'] : $booking->end_time
            );
            
            // Check for conflicts (excluding current booking)
            if (HRB_Database::check_booking_conflict($check_data['room_id'], $check_data['booking_date'], $check_data['start_time'], $check_data['end_time'], $booking_id)) {
                return new WP_Error('booking_conflict', __('Updated time slot conflicts with existing booking', 'hourly-room-booking'));
            }
            
            // Recalculate pricing if any pricing-related field changed
            $pricing_fields = ['booking_date', 'start_time', 'end_time', 'room_id', 'extra_people', 'extras', 'payment_method'];
            $should_recalculate = false;
            
            foreach ($pricing_fields as $field) {
                if (isset($data[$field])) {
                    $should_recalculate = true;
                    break;
                }
            }
            
            if ($should_recalculate) {
                // Get current extras from booking_extras table
                $current_extras = array();
                if (isset($data['extras'])) {
                    $current_extras = $data['extras'];
                } else {
                    // Get existing extras from booking_extras table
                    $existing_extras = $wpdb->get_results($wpdb->prepare(
                        "SELECT extra_id FROM {$wpdb->prefix}hrb_booking_extras WHERE booking_id = %d",
                        $booking_id
                    ));
                    $current_extras = array_column($existing_extras, 'extra_id');
                }
                
                // Prepare complete data for pricing calculation
                $pricing_data = array_merge($check_data, array(
                    'extra_people' => isset($data['extra_people']) ? $data['extra_people'] : $booking->extra_people,
                    'extras' => $current_extras,
                    'payment_method' => isset($data['payment_method']) ? $data['payment_method'] : $booking->payment_method
                ));
                
                $pricing = $this->calculate_booking_price($pricing_data);
                $data = array_merge($data, array(
                    'total_hours' => $this->calculate_duration($check_data['start_time'], $check_data['end_time']),
                    'base_price' => $pricing['base_price'],
                    'extra_people_price' => $pricing['extra_people_cost'],
                    'extras_price' => $pricing['extras_cost'],
                    'tax_amount' => $pricing['tax_amount'],
                    'paypal_fee' => $pricing['paypal_fee'],
                    'total_amount' => $pricing['total_amount']
                ));
            }
        }
        
        // Remove extras from data since it's not a column in bookings table
        unset($data['extras']);
        
        // Generate format array dynamically for the data being updated
        $format = array();
        foreach ($data as $key => $value) {
            if (is_int($value)) {
                $format[] = '%d';
            } elseif (is_float($value)) {
                $format[] = '%f';
            } else {
                $format[] = '%s';
            }
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_bookings',
            $data,
            array('id' => $booking_id),
            $format,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update booking', 'hourly-room-booking'));
        }
        
        // Auto-cancel payment status for all payment methods when booking is cancelled
        if (isset($data['status']) && $data['status'] === 'cancelled' && $booking->payment_status === 'pending') {
            $wpdb->update(
                $wpdb->prefix . 'hrb_payments',
                array('status' => 'cancelled'),
                array('booking_id' => $booking_id),
                array('%s'),
                array('%d')
            );
        }
        
        // Send notification if status changed and notifications are enabled
        if ($send_notification && isset($data['status']) && $data['status'] !== $booking->status) {
            $this->send_booking_notification($booking_id, 'booking_modified');
        }
        
        return true;
    }
    
    /**
     * Cancel booking
     */
    public function cancel_booking($booking_id, $reason = '') {
        global $wpdb;
        
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        if ($booking->status === 'cancelled') {
            return new WP_Error('already_cancelled', __('Booking is already cancelled', 'hourly-room-booking'));
        }
        
        // Check cancellation policy
        $cancellation_hours = get_option('hrb_cancellation_hours', 24);
        $booking_datetime = strtotime($booking->booking_date . ' ' . $booking->start_time);
        $min_cancellation_time = $booking_datetime - ($cancellation_hours * 3600);
        
        if (time() > $min_cancellation_time && !current_user_can('manage_options')) {
            return new WP_Error('cancellation_too_late', 
                sprintf(__('Bookings can only be cancelled %d hours in advance', 'hourly-room-booking'), $cancellation_hours));
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_bookings',
            array(
                'status' => 'cancelled',
                'admin_notes' => $booking->admin_notes . "\nCancelled: " . current_time('Y-m-d H:i:s') . " - " . $reason
            ),
            array('id' => $booking_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('cancellation_failed', __('Failed to cancel booking', 'hourly-room-booking'));
        }
        
        // Cancel extra bookings to free up stock
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'hrb_booking_extras',
            ['booking_id' => $booking_id],
            ['%d']
        );
        
        // Handle refunds if payment was made
        if ($booking->payment_status === 'completed') {
            // Process refund logic here
            $this->process_refund($booking_id);
        }
        
        // Auto-cancel payment status for all payment methods when booking is cancelled
        if ($booking->payment_status === 'pending') {
            $update_result = $wpdb->update(
                $wpdb->prefix . 'hrb_payments',
                array('status' => 'cancelled'),
                array('booking_id' => $booking_id),
                array('%s'),
                array('%d')
            );
            
        }
        
        // Send notification
        $this->send_booking_notification($booking_id, 'booking_cancelled');
        
        return true;
    }
    
    /**
     * Delete booking
     */
    public function delete_booking($booking_id) {
        global $wpdb;
        
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete booking extras
            $wpdb->delete(
                $wpdb->prefix . 'hrb_booking_extras',
                ['booking_id' => $booking_id],
                ['%d']
            );
            
            // Delete payments
            $wpdb->delete(
                $wpdb->prefix . 'hrb_payments',
                ['booking_id' => $booking_id],
                ['%d']
            );
            
            // Delete booking
            $result = $wpdb->delete(
                $wpdb->prefix . 'hrb_bookings',
                ['id' => $booking_id],
                ['%d']
            );
            
            if ($result === false) {
                throw new Exception('Failed to delete booking');
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return true;
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            return new WP_Error('delete_failed', __('Failed to delete booking', 'hourly-room-booking'));
        }
    }
    
    /**
     * Get bookings with filters
     */
    public function get_bookings($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $params = array();
        
        // Date range filter
        if (!empty($filters['start_date'])) {
            $where_conditions[] = 'b.booking_date >= %s';
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_conditions[] = 'b.booking_date <= %s';
            $params[] = $filters['end_date'];
        }
        
        // Room filter
        if (!empty($filters['room_id'])) {
            $where_conditions[] = 'b.room_id = %d';
            $params[] = intval($filters['room_id']);
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $where_conditions[] = 'b.status = %s';
            $params[] = $filters['status'];
        }
        
        // Payment status filter
        if (!empty($filters['payment_status'])) {
            $where_conditions[] = 'b.payment_status = %s';
            $params[] = $filters['payment_status'];
        }
        
        // Customer search
        if (!empty($filters['customer_search'])) {
            $search = '%' . $wpdb->esc_like($filters['customer_search']) . '%';
            $where_conditions[] = '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        // Pagination
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
        $offset = isset($filters['offset']) ? intval($filters['offset']) : 0;
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT b.*, r.name as room_name, c.first_name, c.last_name, c.email, c.phone
                FROM {$wpdb->prefix}hrb_bookings b
                JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
                JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
                WHERE $where_clause
                ORDER BY b.booking_date DESC, b.start_time DESC
                LIMIT %d OFFSET %d";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Create invoice for booking
     */
    public function create_invoice($booking_id) {
        global $wpdb;
        
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        // Generate invoice number
        $invoice_counter = get_option('hrb_invoice_counter', 1);
        $invoice_number = date('Y') . '-' . str_pad($invoice_counter, 6, '0', STR_PAD_LEFT);
        
        $invoice_data = array(
            'invoice_number' => $invoice_number,
            'booking_id' => $booking_id,
            'customer_id' => $booking->customer_id,
            'issue_date' => current_time('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+14 days')),
            'subtotal' => $booking->base_price,
            'tax_rate' => floatval(get_option('hrb_tax_rate', 19)),
            'tax_amount' => $booking->tax_amount,
            'total_amount' => $booking->total_amount,
            'status' => 'sent'
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'hrb_invoices',
            $invoice_data,
            array('%s', '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('invoice_creation_failed', __('Failed to create invoice', 'hourly-room-booking'));
        }
        
        // Update invoice counter
        update_option('hrb_invoice_counter', $invoice_counter + 1);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Send booking notification
     */
    public function send_booking_notification($booking_id, $event) {
        $notification_manager = HRB_Notification_Manager::getInstance();
        return $notification_manager->send_notification($booking_id, $event);
    }
    
    /**
     * Process refund
     */
    public function process_refund($booking_id) {
        // Refund processing logic will be implemented in payment handler
        $payment_handler = HRB_Payment_Handler::getInstance();
        return $payment_handler->process_refund($booking_id);
    }
    
    /**
     * Clean up expired bookings
     */
    public function cleanup_expired_bookings() {
        global $wpdb;
        
        
        // Get expired pending bookings before cancelling them
        $expired_bookings = $wpdb->get_results(
            "SELECT id, payment_method, payment_status 
             FROM {$wpdb->prefix}hrb_bookings 
             WHERE status = 'pending' 
             AND CONCAT(booking_date, ' ', start_time) < NOW() - INTERVAL 1 HOUR"
        );
        
        // Mark expired pending bookings as cancelled
        $wpdb->query(
            "UPDATE {$wpdb->prefix}hrb_bookings 
             SET status = 'cancelled', 
                 admin_notes = CONCAT(COALESCE(admin_notes, ''), '\nAuto-cancelled: expired') 
             WHERE status = 'pending' 
             AND CONCAT(booking_date, ' ', start_time) < NOW() - INTERVAL 1 HOUR"
        );
        
        // Auto-cancel payment status for onsite payments when bookings are auto-cancelled
        foreach ($expired_bookings as $booking) {
            if ($booking->payment_method === 'onsite' && $booking->payment_status === 'pending') {
                $wpdb->update(
                    $wpdb->prefix . 'hrb_payments',
                    array('status' => 'cancelled'),
                    array('booking_id' => $booking->id),
                    array('%s'),
                    array('%d')
                );
            }
        }
        
        // Mark confirmed bookings as completed when their time has passed
        // This is the primary logic: confirmed bookings become completed when time passes
        $completed_count = $wpdb->query(
            "UPDATE {$wpdb->prefix}hrb_bookings 
             SET status = 'completed' 
             WHERE status = 'confirmed' 
             AND CONCAT(booking_date, ' ', end_time) < NOW()"
        );
        
        
        // Mark no-show bookings (this should be a separate manual process or different logic)
        // For now, we'll keep this as a separate query that can be run manually
        // or triggered by admin action, not automatically
    }
    
    /**
     * Send booking reminders
     */
    public function send_booking_reminders() {
        global $wpdb;
        
        
        // Get bookings starting in 1 hour (expanded window: 45-75 minutes)
        $upcoming_bookings = $wpdb->get_results(
            "SELECT b.*, c.email, c.phone 
             FROM {$wpdb->prefix}hrb_bookings b
             JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
             WHERE b.status = 'confirmed'
             AND CONCAT(b.booking_date, ' ', b.start_time) BETWEEN NOW() + INTERVAL 45 MINUTE AND NOW() + INTERVAL 75 MINUTE"
        );
        
        
        $reminders_sent = 0;
        $reminders_skipped = 0;
        
        foreach ($upcoming_bookings as $booking) {
            // Check if reminder already sent
            $reminder_sent = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_notification_logs 
                 WHERE booking_id = %d AND event = 'booking_reminder' AND status IN ('sent', 'delivered')",
                $booking->id
            ));
            
            if ($reminder_sent == 0) {
                $result = $this->send_booking_notification($booking->id, 'booking_reminder');
                $reminders_sent++;
            } else {
                $reminders_skipped++;
            }
        }
        
        
        return array(
            'total_found' => count($upcoming_bookings),
            'reminders_sent' => $reminders_sent,
            'reminders_skipped' => $reminders_skipped
        );
    }
    
    /**
     * Test booking reminders manually (for debugging)
     */
    public function test_booking_reminders() {
        global $wpdb;
        
        echo "<h3>🧪 Manual Booking Reminder Test</h3>\n";
        
        // Check if email notifications are enabled
        $email_enabled = get_option('hrb_email_notifications', 1);
        echo "✅ Email notifications enabled: " . ($email_enabled ? 'YES' : 'NO') . "\n";
        
        // Check company email
        $company_email = get_option('hrb_company_email', get_option('admin_email'));
        echo "✅ Company email: {$company_email}\n";
        
        // Get all confirmed bookings
        $all_bookings = $wpdb->get_results(
            "SELECT b.id, b.booking_reference, b.booking_date, b.start_time, b.status, c.email 
             FROM {$wpdb->prefix}hrb_bookings b
             JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
             WHERE b.status = 'confirmed'
             ORDER BY b.booking_date DESC, b.start_time DESC
             LIMIT 10"
        );
        
        echo "✅ Total confirmed bookings: " . count($all_bookings) . "\n";
        
        if (count($all_bookings) > 0) {
            echo "<h4>📋 Recent Bookings:</h4>\n";
            foreach ($all_bookings as $booking) {
                $booking_time = strtotime($booking->booking_date . ' ' . $booking->start_time);
                $time_until = $booking_time - time();
                $hours_until = round($time_until / 3600, 1);
                
                echo "- Booking #{$booking->id}: {$booking->booking_reference} on {$booking->booking_date} at {$booking->start_time} (Email: {$booking->email}) - {$hours_until} hours from now\n";
            }
        }
        
        // Test the reminder query
        $upcoming_bookings = $wpdb->get_results(
            "SELECT b.*, c.email, c.phone 
             FROM {$wpdb->prefix}hrb_bookings b
             JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
             WHERE b.status = 'confirmed'
             AND CONCAT(b.booking_date, ' ', b.start_time) BETWEEN NOW() + INTERVAL 45 MINUTE AND NOW() + INTERVAL 75 MINUTE"
        );
        
        echo "<h4>🎯 Bookings in Reminder Window (45-75 minutes):</h4>\n";
        echo "Found: " . count($upcoming_bookings) . " bookings\n";
        
        if (count($upcoming_bookings) > 0) {
            foreach ($upcoming_bookings as $booking) {
                echo "- Booking #{$booking->id}: {$booking->booking_reference} starts at {$booking->booking_date} {$booking->start_time} (Email: {$booking->email})\n";
            }
        } else {
            echo "⚠️ No bookings found in the reminder window\n";
            echo "💡 Try creating a test booking for 1 hour from now\n";
        }
        
        // Check recent notification logs
        $recent_logs = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}hrb_notification_logs 
             WHERE event = 'booking_reminder' 
             ORDER BY created_at DESC 
             LIMIT 5"
        );
        
        echo "<h4>📋 Recent Reminder Logs:</h4>\n";
        echo "Found: " . count($recent_logs) . " reminder logs\n";
        
        foreach ($recent_logs as $log) {
            echo "- {$log->created_at}: {$log->type} to {$log->recipient} - Status: {$log->status}\n";
            if ($log->error_message) {
                echo "  Error: {$log->error_message}\n";
            }
        }
        
        // Manual reminder test
        if (count($upcoming_bookings) > 0) {
            echo "<h4>🚀 Manual Reminder Test:</h4>\n";
            $result = $this->send_booking_reminders();
            echo "✅ Manual test completed:\n";
            echo "- Total found: {$result['total_found']}\n";
            echo "- Reminders sent: {$result['reminders_sent']}\n";
            echo "- Reminders skipped: {$result['reminders_skipped']}\n";
        } else {
            echo "<h4>⚠️ Cannot test - No bookings in reminder window</h4>\n";
            echo "Create a test booking for 1 hour from now to test reminders\n";
        }
        
        return $result ?? array();
    }

    /**
     * Get booking count for a specific room and date
     *
     * @since 1.0.0
     * @param int $room_id Room ID
     * @param string $date Date in Y-m-d format
     * @return int Number of bookings
     */
    public function get_room_bookings_count(int $room_id, string $date = ''): int {
        global $wpdb;

        if (empty($date)) {
            $date = date('Y-m-d');
        }

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}hrb_bookings
            WHERE room_id = %d
            AND booking_date = %s
            AND status NOT IN ('cancelled', 'no_show')
        ", $room_id, $date));

        return intval($count);
    }

    /**
     * Get bookings for admin view with filters
     *
     * @since 1.0.0
     * @param array $filters Filter parameters
     * @param int $limit Number of bookings to retrieve
     * @param int $offset Offset for pagination
     * @return array Bookings data
     */
    public function get_bookings_admin(array $filters = [], int $limit = 20, int $offset = 0): array {
        global $wpdb;

        $where_conditions = ['1=1'];
        $where_values = [];

        // Status filter
        if (!empty($filters['status'])) {
            $where_conditions[] = 'b.status = %s';
            $where_values[] = $filters['status'];
        }

        // Room filter
        if (!empty($filters['room_id'])) {
            $where_conditions[] = 'b.room_id = %d';
            $where_values[] = $filters['room_id'];
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'b.booking_date >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'b.booking_date <= %s';
            $where_values[] = $filters['date_to'];
        }

        // Recent only filter (last 2 days)
        if (!empty($filters['recent_only'])) {
            $two_days_ago = date('Y-m-d', strtotime('-2 days'));
            $where_conditions[] = 'b.booking_date >= %s';
            $where_values[] = $two_days_ago;
        }

        // Old only filter (older than 2 days)
        if (!empty($filters['old_only'])) {
            $two_days_ago = date('Y-m-d', strtotime('-2 days'));
            $where_conditions[] = 'b.booking_date < %s';
            $where_values[] = $two_days_ago;
        }

        // Search filter
        if (!empty($filters['search'])) {
            $where_conditions[] = '(CONCAT(c.first_name, " ", c.last_name) LIKE %s OR c.email LIKE %s OR b.booking_reference LIKE %s OR r.name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

        // Sorting
        $order_by = 'b.created_at DESC'; // Default sorting
        if (!empty($filters['orderby'])) {
            $order = !empty($filters['order']) && strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';
            
            switch ($filters['orderby']) {
                case 'datetime':
                    $order_by = "b.booking_date {$order}, b.start_time {$order}";
                    break;
                case 'amount':
                    $order_by = "b.total_amount {$order}";
                    break;
                case 'status':
                    $order_by = "b.status {$order}";
                    break;
                case 'customer':
                    $order_by = "customer_name {$order}";
                    break;
                case 'room':
                    $order_by = "r.name {$order}";
                    break;
                case 'created':
                default:
                    $order_by = "b.created_at {$order}";
                    break;
            }
        }

        // Add limit and offset values
        $where_values[] = $limit;
        $where_values[] = $offset;

        $query = "
            SELECT
                b.id,
                b.booking_reference,
                b.customer_id,
                b.room_id,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.status,
                b.payment_method,
                COALESCE(p.status, b.payment_status) as payment_status,
                p.status as actual_payment_status,
                p.transaction_id,
                p.processed_at,
                b.total_amount,
                b.extra_people,
                b.created_at,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                r.name as room_name
            FROM {$wpdb->prefix}hrb_bookings b
            LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
            LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            LEFT JOIN {$wpdb->prefix}hrb_payments p ON b.id = p.booking_id
            {$where_clause}
            ORDER BY {$order_by}
            LIMIT %d OFFSET %d
        ";

        return $wpdb->get_results($wpdb->prepare($query, $where_values), ARRAY_A) ?: [];
    }

    /**
     * Get bookings count for admin view with filters
     *
     * @since 1.0.0
     * @param array $filters Filter parameters
     * @return int Total count
     */
    public function get_bookings_count_admin(array $filters = []): int {
        global $wpdb;

        $where_conditions = ['1=1'];
        $where_values = [];

        // Status filter
        if (!empty($filters['status'])) {
            $where_conditions[] = 'b.status = %s';
            $where_values[] = $filters['status'];
        }

        // Room filter
        if (!empty($filters['room_id'])) {
            $where_conditions[] = 'b.room_id = %d';
            $where_values[] = $filters['room_id'];
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'b.booking_date >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'b.booking_date <= %s';
            $where_values[] = $filters['date_to'];
        }

        // Recent only filter (last 2 days)
        if (!empty($filters['recent_only'])) {
            $two_days_ago = date('Y-m-d', strtotime('-2 days'));
            $where_conditions[] = 'b.booking_date >= %s';
            $where_values[] = $two_days_ago;
        }

        // Old only filter (older than 2 days)
        if (!empty($filters['old_only'])) {
            $two_days_ago = date('Y-m-d', strtotime('-2 days'));
            $where_conditions[] = 'b.booking_date < %s';
            $where_values[] = $two_days_ago;
        }

        // Search filter
        if (!empty($filters['search'])) {
            $where_conditions[] = '(CONCAT(c.first_name, " ", c.last_name) LIKE %s OR c.email LIKE %s OR b.booking_reference LIKE %s OR r.name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

        $query = "
            SELECT COUNT(*)
            FROM {$wpdb->prefix}hrb_bookings b
            LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
            LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            {$where_clause}
        ";

        if (empty($where_values)) {
            $count = $wpdb->get_var($query);
        } else {
            $count = $wpdb->get_var($wpdb->prepare($query, $where_values));
        }

        return intval($count);
    }

    /**
     * Update booking status
     *
     * @since 1.0.0
     * @param int $booking_id Booking ID
     * @param string $status New status
     * @return bool Success status
     */
    public function update_booking_status(int $booking_id, string $status): bool {
        global $wpdb;

        $allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
        if (!in_array($status, $allowed_statuses)) {
            return false;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_bookings',
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $booking_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            // Auto-cancel payment status for all payment methods when booking is cancelled
            if ($status === 'cancelled') {
                $booking = $this->get_booking($booking_id);
                if ($booking && $booking->payment_status === 'pending') {
                    $wpdb->update(
                        $wpdb->prefix . 'hrb_payments',
                        array('status' => 'cancelled'),
                        array('booking_id' => $booking_id),
                        array('%s'),
                        array('%d')
                    );
                }
            }
            
            // Send notification based on status change
            $notification_types = [
                'confirmed' => 'booking_confirmation',
                'cancelled' => 'booking_cancellation',
                'completed' => 'booking_completion'
            ];

            if (isset($notification_types[$status])) {
                $this->send_booking_notification($booking_id, $notification_types[$status]);
            }

            return true;
        }

        return false;
    }
    
    /**
     * Remove all extras for a booking
     */
    public function remove_booking_extras($booking_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'hrb_booking_extras',
            ['booking_id' => $booking_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Fix existing bookings with missing extra_people_price
     */
    public function fix_extra_people_pricing() {
        global $wpdb;
        
        // Get bookings that have extra_people > 0 but extra_people_price = 0
        $bookings_to_fix = $wpdb->get_results(
            "SELECT id, extra_people FROM {$wpdb->prefix}hrb_bookings 
             WHERE extra_people > 0 AND extra_people_price = 0"
        );
        
        foreach ($bookings_to_fix as $booking) {
            $extra_people_price = $booking->extra_people * 15.00; // €15 per person
            
            $wpdb->update(
                $wpdb->prefix . 'hrb_bookings',
                array('extra_people_price' => $extra_people_price),
                array('id' => $booking->id),
                array('%f'),
                array('%d')
            );
        }
        
        return count($bookings_to_fix);
    }
    
    /**
     * Mark bookings as no-show (manual process)
     * This should be called manually by admin when they know customer didn't show up
     */
    public function mark_no_show_bookings() {
        global $wpdb;
        
        // Mark confirmed bookings as no-show if they are past their end time
        // This is a separate method that can be called manually or on a different schedule
        $result = $wpdb->query(
            "UPDATE {$wpdb->prefix}hrb_bookings 
             SET status = 'no_show' 
             WHERE status = 'confirmed' 
             AND CONCAT(booking_date, ' ', end_time) < NOW() - INTERVAL 2 HOUR"
        );
        
        return $result;
    }
}
?>