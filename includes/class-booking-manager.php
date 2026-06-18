<?php
/**
 * Booking Manager Class
 * Handles all booking-related operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Booking_Manager {

    /**
     * Flat cancellation fee (in the store currency) charged when a cash/onsite
     * booking is cancelled within the cancellation window. PayPal/online
     * bookings are never charged this fee (and are not refunded).
     */
    const CANCELLATION_FEE = 15.00;

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
        add_action('hrb_cleanup_incomplete_payments', array($this, 'cleanup_incomplete_payments'));
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
        
        // Schedule cleanup of incomplete PayPal payments every 5 minutes
        if (!wp_next_scheduled('hrb_cleanup_incomplete_payments')) {
            wp_schedule_event(time(), 'hrb_five_minutes', 'hrb_cleanup_incomplete_payments');
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
        // Allow inactive rooms ONLY if booking is created by admin AND user is actually an admin
        // This prevents frontend manipulation by checking user capabilities
        $allow_inactive_rooms = false;
        if (isset($data['created_by_admin']) && ($data['created_by_admin'] == 1 || $data['created_by_admin'] === '1')) {
            // Double-check: verify user is actually an admin (prevents frontend manipulation)
            if (current_user_can('manage_options') || current_user_can('hrb_manage_bookings')) {
                $allow_inactive_rooms = true;
            }
        }
        $validation = $this->validate_booking_data($data, false, $allow_inactive_rooms);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Conflict check moved INSIDE the transaction below so that the room
        // row lock (SELECT ... FOR UPDATE) actually holds across the
        // check-then-insert critical section. Doing it here, outside the
        // transaction, left a race window where two concurrent requests
        // could both pass the check and both insert.

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
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : (isset($data['payment_method']) && in_array($data['payment_method'], ['onsite', 'cash']) ? 'confirmed' : 'pending'),
            'payment_status' => isset($data['payment_status']) ? $data['payment_status'] : 'pending',
            'payment_method' => isset($data['payment_method']) ? sanitize_text_field($data['payment_method']) : null,
            'special_requests' => isset($data['special_requests']) ? sanitize_textarea_field($data['special_requests']) : null,
            'admin_notes' => isset($data['admin_notes']) ? sanitize_textarea_field($data['admin_notes']) : null,
            'created_by_admin' => isset($data['created_by_admin']) ? intval($data['created_by_admin']) : 0,
            'cooldown_override' => isset($data['cooldown_override']) ? intval($data['cooldown_override']) : 0,
            'is_anonymous' => isset($data['is_anonymous']) ? intval($data['is_anonymous']) : 0,
            'first_name' => isset($data['first_name']) ? sanitize_text_field($data['first_name']) : null,
            'last_name' => isset($data['last_name']) ? sanitize_text_field($data['last_name']) : null
        );
        
        
        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Atomic conflict check: lock the room row, then verify the slot
            // is still free. Any concurrent booking attempt for the same room
            // will block here until this transaction commits or rolls back.
            if (HRB_Database::check_booking_conflict(
                $booking_data['room_id'],
                $booking_data['booking_date'],
                $booking_data['start_time'],
                $booking_data['end_time'],
                null,
                true // acquire_room_lock
            )) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('booking_conflict', __('Selected time slot is not available', 'hourly-room-booking'));
            }

            // Insert booking
            $result = $wpdb->insert(
                $wpdb->prefix . 'hrb_bookings',
                $booking_data,
                array('%s', '%d', '%d', '%s', '%s', '%s', '%f', '%f', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s')
            );
            if ($result === false) {
                $wpdb_error = $wpdb->last_error;
                throw new Exception(__('Failed to create booking', 'hourly-room-booking') . ': ' . $wpdb_error);
            }
            
            $booking_id = $wpdb->insert_id;

            // Create payment record
            $payment_method = $booking_data['payment_method'] ?: 'onsite';
            $payment_manager = HRB_Payment_Manager::getInstance();
            $currency = HRB_Currency_Manager::getInstance()->get_currency_code();
            
            if ($payment_method === 'paypal') {
                // For PayPal, create payment record with token to prevent multiple payments
                // Generate unique payment token for security
                $payment_token = wp_generate_password(32, false);
                
                $payment_id = $payment_manager->create_payment(
                    $booking_id,
                    $booking_data['total_amount'],
                    $payment_method,
                    $currency,
                    array(
                        'status' => 'pending',
                        'payment_token' => $payment_token,
                        'fees' => $booking_data['paypal_fee'] ?? 0
                    )
                );

                if (is_wp_error($payment_id)) {
                    throw new Exception($payment_id->get_error_message());
                }
            } else {
                // For other payment methods, create payment record normally
                $payment_id = $payment_manager->create_payment(
                    $booking_id,
                    $booking_data['total_amount'],
                    $payment_method,
                    $currency,
                    array('status' => $booking_data['payment_status'])
                );

                if (is_wp_error($payment_id)) {
                    throw new Exception($payment_id->get_error_message());
                }
            }

            // Create invoice based on payment method and status
            $should_create_invoice = false;
            
            if ($booking_data['status'] === 'confirmed') {
                // For PayPal payments, create invoice immediately
                if ($payment_method === 'paypal') {
                    $should_create_invoice = true;
                }
                // For cash/onsite payments, only create invoice when status is 'paid'
                elseif (in_array($payment_method, ['onsite', 'cash']) && $booking_data['payment_status'] === 'paid') {
                    $should_create_invoice = true;
                }
                // For other payment methods, create invoice immediately
                elseif (!in_array($payment_method, ['onsite', 'cash', 'paypal'])) {
                    $should_create_invoice = true;
                }
            }
            
            if ($should_create_invoice) {
                $invoice_id = $this->create_invoice($booking_id);
                if (is_wp_error($invoice_id)) {
                    throw new Exception($invoice_id->get_error_message());
                }
            }

            $wpdb->query('COMMIT');
            
            // Send appropriate notification based on payment method
            if ($payment_method === 'paypal') {
                // Generate payment link with token for admin-created bookings
                $payment_link = home_url('/paypal-payment/?ref=' . urlencode($booking_data['booking_reference']));
                
                // If booking was created by admin, include payment token in link
                if (!empty($booking_data['created_by_admin'])) {
                    global $wpdb;
                    // Get the payment token we just created
                    $payment_record = $wpdb->get_row($wpdb->prepare(
                        "SELECT payment_token FROM {$wpdb->prefix}hrb_payments 
                        WHERE booking_id = %d AND payment_method = 'paypal' AND status = 'pending'
                        ORDER BY id DESC LIMIT 1",
                        $booking_id
                    ));
                    
                    if ($payment_record && !empty($payment_record->payment_token)) {
                        $payment_link .= '&token=' . urlencode($payment_record->payment_token);
                    }
                }
                
                $custom_data = array(
                    // Only include the payment link when booking was created by an admin
                    'payment_link' => !empty($booking_data['created_by_admin']) ? $payment_link : ''
                );

                $this->send_booking_notification(
                    $booking_id,
                    'online_payment_pending',
                    $custom_data
                );
            } else {
                // For other payment methods, send booking confirmation
                $this->send_booking_notification($booking_id, 'booking_confirmation');
            }
            
            return $booking_id;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('booking_creation_failed', $e->getMessage());
        }
    }
    
    /**
     * Validate booking data
     */
    public function validate_booking_data($data, $allow_past_dates = false, $allow_inactive_rooms = false) {
        // Validate room exists and is active (unless admin allows inactive rooms)
        $room = HRB_Room_Manager::getInstance()->get_room($data['room_id']);
        if (!$room) {
            return new WP_Error('invalid_room', __('Selected room is not available', 'hourly-room-booking'));
        }
        // Only check is_active if not allowing inactive rooms
        if (!$allow_inactive_rooms && !$room->is_active) {
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
        
        // Validate business hours (allow cross-midnight when end time < start time and end limit is 24:00)
        $business_start = get_option('hrb_booking_start_time', '08:00');
        $business_end = get_option('hrb_booking_end_time', '20:00');
        $allow_cross_midnight = ($business_end === '24:00' || $business_end === '24:00:00');

        $start_minutes = $this->time_to_minutes($data['start_time']);
        $end_minutes   = $this->time_to_minutes($data['end_time']);
        $business_start_minutes = $this->time_to_minutes($business_start);
        $business_end_minutes   = $this->time_to_minutes($business_end);

        // Start must be within window
        if ($start_minutes < $business_start_minutes) {
            return new WP_Error('outside_business_hours', 
                sprintf(__('Bookings must be between %s and %s', 'hourly-room-booking'), $business_start, $business_end));
        }

        // Compute slot end minutes relative to start (handle cross-midnight)
        $slot_end_minutes = $end_minutes;
        if ($end_minutes <= $start_minutes) {
            $slot_end_minutes += 24 * 60; // next day
        }

        // Business end boundary
        // If end is 24:00 and slot crosses midnight, allow up to next-day 24:00 (i.e., +24h window)
        $max_end_minutes = $allow_cross_midnight ? ($business_end_minutes + 24 * 60) : $business_end_minutes;

        if (!$allow_cross_midnight && $slot_end_minutes > $business_end_minutes) {
            return new WP_Error('outside_business_hours', 
                sprintf(__('Bookings must be between %s and %s', 'hourly-room-booking'), $business_start, $business_end));
        }
        if ($allow_cross_midnight && $slot_end_minutes > $max_end_minutes) {
            return new WP_Error('outside_business_hours', 
                sprintf(__('Bookings must be between %s and %s', 'hourly-room-booking'), $business_start, $business_end));
        }
        
        return true;
    }
    
    /**
     * Calculate booking duration in hours
     */
    private function calculate_duration($start_time, $end_time) {
        $start_minutes = $this->time_to_minutes($start_time);
        $end_minutes   = $this->time_to_minutes($end_time);
        if ($end_minutes <= $start_minutes) {
            $end_minutes += 24 * 60; // cross-midnight
        }
        return ($end_minutes - $start_minutes) / 60;
    }

    private function time_to_minutes($time) {
        $parts = explode(':', $time);
        $h = intval($parts[0]);
        $m = isset($parts[1]) ? intval($parts[1]) : 0;
        return $h * 60 + $m;
    }
    
    /**
     * Save extras for a booking
     */
    public function save_booking_extras($booking_id, $extras_data, $booking_date, $start_time, $end_time, $is_admin_edit = false) {
        global $wpdb;
        
        // Get original extras with their added_by_admin flags and user IDs before deletion (to preserve admin-added status)
        $original_extras_map = [];
        if ($is_admin_edit) {
            $original_extras_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT extra_id, added_by_admin, added_by_user_id FROM {$wpdb->prefix}hrb_booking_extras WHERE booking_id = %d",
                $booking_id
            ));
            foreach ($original_extras_rows as $row) {
                $original_extras_map[$row->extra_id] = [
                    'added_by_admin' => intval($row->added_by_admin),
                    'added_by_user_id' => intval($row->added_by_user_id ?? 0)
                ];
            }
        }
        
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
        
        // Check if this is an admin booking (allow inactive extras)
        $is_admin_booking = current_user_can('manage_options') || current_user_can('hrb_manage_bookings');
        
        foreach ($extras_data as $index => $extra) {
            // Handle both formats: direct ID or array with ID
            $extra_id = is_array($extra) ? intval($extra['id']) : intval($extra);
            
            if ($extra_id > 0) {
                // Get extra to check if it's active
                $extra_obj = $extras_manager->get_extra($extra_id);
                if (!$extra_obj) {
                    return new WP_Error('extra_not_found', sprintf(__('Extra not found: ID %d', 'hourly-room-booking'), $extra_id));
                }
                
                // For admin bookings, skip active check but still validate stock/availability
                // For frontend bookings, check availability (includes active status)
                if ($is_admin_booking && !$extra_obj->is_active) {
                    // Admin can book inactive extras - skip availability check but still validate locks
                    // Just verify the extra exists and we can proceed
                    $availability = ['available' => true, 'available_quantity' => 999];
                } else {
                    // Check availability first (includes active check for frontend)
                    $availability = $stock_manager->check_availability(
                        $extra_id,
                        $booking_date,
                        $start_time,
                        $end_time,
                        1,
                        $booking_id  // Exclude current booking from availability check
                    );
                    
                    if (!$availability['available']) {
                        return new WP_Error('extra_unavailable', sprintf(__('Sorry, extra item is no longer available: %s', 'hourly-room-booking'), $availability['reason']));
                    }
                }
                
                // Preserve added_by_admin flag and user ID if it was already set, otherwise check if it's new
                $added_by_admin = 0;
                $added_by_user_id = null;
                
                if (isset($original_extras_map[$extra_id])) {
                    // Preserve the existing flag and user ID (was already admin-added)
                    $added_by_admin = $original_extras_map[$extra_id]['added_by_admin'];
                    $added_by_user_id = $original_extras_map[$extra_id]['added_by_user_id'] > 0 ? $original_extras_map[$extra_id]['added_by_user_id'] : null;
                } else {
                    // New extra - mark as admin-added if this is an admin edit
                    if ($is_admin_edit) {
                        $added_by_admin = 1;
                        // Store the current user ID (admin or staff)
                        $added_by_user_id = get_current_user_id();
                    }
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
                            'end_time' => $end_time,
                            'added_by_admin' => $added_by_admin,
                            'added_by_user_id' => $added_by_user_id
                        ],
                        ['%d', '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%d', '%d']
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
     * Track booking modifications (hours or extra people increases)
     */
    public function track_booking_modification($booking_id, $modification_type, $original_value, $new_value, $additional_amount, $added_by_user_id = null) {
        global $wpdb;
        
        // Only track if there's an increase
        if ($new_value <= $original_value) {
            return false;
        }
        
        // Don't track if additional amount is negative
        if ($additional_amount < 0) {
            return false;
        }
        
        // Get current user if not provided and we're in admin context
        if ($added_by_user_id === null || $added_by_user_id == 0) {
            if (is_admin() && (current_user_can('manage_options') || current_user_can('hrb_manage_bookings'))) {
                $added_by_user_id = get_current_user_id();
            } else {
                // Not in admin context or user doesn't have capabilities
                return false;
            }
        }
        
        // Verify user has admin capabilities
        if ($added_by_user_id > 0) {
            $user = get_userdata($added_by_user_id);
            if (!$user || (!user_can($added_by_user_id, 'manage_options') && !user_can($added_by_user_id, 'hrb_manage_bookings'))) {
                return false;
            }
        } else {
            return false;
        }
        
        $modifications_table = $wpdb->prefix . 'hrb_booking_modifications';
        
        // Check if modification already exists for this booking and type
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$modifications_table} 
             WHERE booking_id = %d AND modification_type = %s 
             ORDER BY id DESC LIMIT 1",
            $booking_id,
            $modification_type
        ));
        
        if ($existing) {
            // Update existing modification
            $update_result = $wpdb->update(
                $modifications_table,
                [
                    'original_value' => $original_value,
                    'new_value' => $new_value,
                    'additional_amount' => $additional_amount,
                    'added_by_user_id' => $added_by_user_id
                ],
                ['id' => $existing->id],
                ['%f', '%f', '%f', '%d'],
                ['%d']
            );
            
            if ($update_result === false) {
                return false;
            }
        } else {
            // Insert new modification
            $insert_result = $wpdb->insert(
                $modifications_table,
                [
                    'booking_id' => $booking_id,
                    'modification_type' => $modification_type,
                    'original_value' => $original_value,
                    'new_value' => $new_value,
                    'additional_amount' => $additional_amount,
                    'added_by_user_id' => $added_by_user_id
                ],
                ['%d', '%s', '%f', '%f', '%f', '%d']
            );
            
            if ($insert_result === false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get booking modifications
     */
    public function get_booking_modifications($booking_id) {
        global $wpdb;
        
        $modifications_table = $wpdb->prefix . 'hrb_booking_modifications';
        $users_table = $wpdb->users;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT bm.*, u.user_login as added_by_username, u.display_name as added_by_display_name
             FROM {$modifications_table} bm
             LEFT JOIN {$users_table} u ON bm.added_by_user_id = u.ID
             WHERE bm.booking_id = %d
             ORDER BY bm.created_at DESC",
            $booking_id
        ));
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

        // Calculate VAT (based on admin setting)
        $tax_rate = floatval(get_option('hrb_tax_rate', 19)) / 100; // Convert percentage to decimal
        $tax_amount = $tax_rate > 0 ? $subtotal * $tax_rate : 0; // Only calculate tax if rate > 0

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
            "SELECT b.*, r.name as room_name, 
                    CASE 
                        WHEN b.is_anonymous = 1 THEN CASE WHEN b.first_name IS NOT NULL AND b.first_name != '' AND b.first_name != '0' THEN b.first_name ELSE '' END
                        ELSE CASE WHEN b.first_name IS NOT NULL AND b.first_name != '' AND b.first_name != '0' THEN b.first_name ELSE c.first_name END
                    END as first_name,
                    CASE 
                        WHEN b.is_anonymous = 1 THEN CASE WHEN b.last_name IS NOT NULL AND b.last_name != '' AND b.last_name != '0' THEN b.last_name ELSE '' END
                        ELSE CASE WHEN b.last_name IS NOT NULL AND b.last_name != '' AND b.last_name != '0' THEN b.last_name ELSE c.last_name END
                    END as last_name,
                    c.email, c.phone, c.company,
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
            "SELECT b.*, r.name as room_name, 
                    CASE 
                        WHEN b.is_anonymous = 1 THEN CASE WHEN b.first_name IS NOT NULL AND b.first_name != '' AND b.first_name != '0' THEN b.first_name ELSE '' END
                        ELSE CASE WHEN b.first_name IS NOT NULL AND b.first_name != '' AND b.first_name != '0' THEN b.first_name ELSE c.first_name END
                    END as first_name,
                    CASE 
                        WHEN b.is_anonymous = 1 THEN CASE WHEN b.last_name IS NOT NULL AND b.last_name != '' AND b.last_name != '0' THEN b.last_name ELSE '' END
                        ELSE CASE WHEN b.last_name IS NOT NULL AND b.last_name != '' AND b.last_name != '0' THEN b.last_name ELSE c.last_name END
                    END as last_name,
                    c.email, c.phone, c.company
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
    public function update_booking($booking_id, $data, $send_notification = true, $is_new_booking = false) {
        global $wpdb;
        
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        // If room/date/time is changing, we need an atomic conflict check
        // against the destination slot. Use the same locked check as
        // create_booking() so two concurrent moves can't land on the
        // same slot.
        $slot_change = isset($data['booking_date']) || isset($data['start_time']) || isset($data['end_time']) || isset($data['room_id']);

        if ($slot_change) {
            $check_data = array(
                'room_id' => isset($data['room_id']) ? $data['room_id'] : $booking->room_id,
                'booking_date' => isset($data['booking_date']) ? $data['booking_date'] : $booking->booking_date,
                'start_time' => isset($data['start_time']) ? $data['start_time'] : $booking->start_time,
                'end_time' => isset($data['end_time']) ? $data['end_time'] : $booking->end_time
            );

            // Wrap the locked check + the subsequent UPDATE in a transaction
            // so the room-row lock acquired by check_booking_conflict() is
            // held until we commit. The transaction is committed at the end
            // of this block after the wpdb->update() call.
            $wpdb->query('START TRANSACTION');

            if (HRB_Database::check_booking_conflict(
                $check_data['room_id'],
                $check_data['booking_date'],
                $check_data['start_time'],
                $check_data['end_time'],
                $booking_id,
                true // acquire_room_lock
            )) {
                $wpdb->query('ROLLBACK');
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
            if ($slot_change) {
                $wpdb->query('ROLLBACK');
            }
            return new WP_Error('update_failed', __('Failed to update booking', 'hourly-room-booking'));
        }

        // Release the room-row lock acquired by the conflict check above.
        if ($slot_change) {
            $wpdb->query('COMMIT');
        }

        // Auto-cancel payment status for all payment methods when booking is cancelled.
        // Never cancel the standalone cancellation-fee charge (CANCELFEE_*): it
        // stays pending until the admin marks it collected on-site.
        if (isset($data['status']) && $data['status'] === 'cancelled' && $booking->payment_status === 'pending') {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}hrb_payments SET status = 'cancelled'
                 WHERE booking_id = %d AND (transaction_id NOT LIKE %s OR transaction_id IS NULL)",
                $booking_id,
                $wpdb->esc_like('CANCELFEE_') . '%'
            ));
        }

        // Apply the cancellation fee on a transition into 'cancelled'
        // (cash/onsite, within window). Idempotent. Runs after the payment
        // auto-cancel above so the fee row is not cancelled with the rest.
        if (isset($data['status']) && $data['status'] === 'cancelled' && $booking->status !== 'cancelled') {
            $this->maybe_apply_cancellation_fee($booking_id);
        }

        // Send notification if booking was modified and notifications are enabled (but not for new bookings)
        if ($send_notification && !$is_new_booking) {
            // Check if any significant booking data changed (not just status)
            $significant_fields = ['status', 'booking_date', 'start_time', 'end_time', 'room_id', 
                                   'base_price', 'extra_people_price', 'extras_price', 'total_amount',
                                   'extra_people', 'payment_method'];
            $has_changes = false;
            
            // Check if status changed
            if (isset($data['status']) && $data['status'] !== $booking->status) {
                $has_changes = true;
            }
            
            // Check if any other significant field changed
            if (!$has_changes) {
                foreach ($significant_fields as $field) {
                    if (isset($data[$field])) {
                        $old_value = isset($booking->$field) ? $booking->$field : null;
                        $new_value = $data[$field];
                        
                        // Compare values (handle numeric comparisons)
                        if (is_numeric($old_value) && is_numeric($new_value)) {
                            if (floatval($old_value) != floatval($new_value)) {
                                $has_changes = true;
                                break;
                            }
                        } elseif ($old_value != $new_value) {
                            $has_changes = true;
                            break;
                        }
                    }
                }
            }
            
            // Also check if extras were added/removed (by comparing extras_price)
            if (!$has_changes && isset($data['extras_price'])) {
                $old_extras_price = isset($booking->extras_price) ? floatval($booking->extras_price) : 0;
                $new_extras_price = floatval($data['extras_price']);
                if ($old_extras_price != $new_extras_price) {
                    $has_changes = true;
                }
            }
            
            if ($has_changes) {
                $this->send_booking_notification($booking_id, 'booking_modified');
            }
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
        
        if (time() > $min_cancellation_time && !current_user_can('hrb_manage_bookings')) {
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

        // Auto-cancel payment status for all payment methods when booking is cancelled.
        // Never cancel the standalone cancellation-fee charge (CANCELFEE_*).
        if ($booking->payment_status === 'pending') {
            $update_result = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}hrb_payments SET status = 'cancelled'
                 WHERE booking_id = %d AND (transaction_id NOT LIKE %s OR transaction_id IS NULL)",
                $booking_id,
                $wpdb->esc_like('CANCELFEE_') . '%'
            ));

        }
        
        // Apply the cancellation fee (cash/onsite, within window) BEFORE the
        // notification so the email can reflect it.
        $this->maybe_apply_cancellation_fee($booking_id);

        // Send notification
        $this->send_booking_notification($booking_id, 'booking_cancelled');

        return true;
    }

    /**
     * Apply the flat cancellation fee when applicable.
     *
     * Rules:
     *  - Only cash/onsite bookings are charged (PayPal/online are excluded:
     *    no refund, no fee).
     *  - Only when the cancellation happens within the cancellation window
     *    (hrb_cancellation_hours, default 24h) before the booking start.
     *  - Idempotent: never charges twice for the same booking.
     *
     * The fee is stored on the booking (cancellation_fee) and recorded as a
     * pending, labelled row in the payments table (payable on-site).
     *
     * @param int $booking_id
     * @return bool True when a fee was applied, false otherwise.
     */
    private function maybe_apply_cancellation_fee($booking_id) {
        global $wpdb;

        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return false;
        }

        // Only cash/onsite payment methods (exclude PayPal/online).
        $method = strtolower(trim($booking->payment_method ?? ''));
        if (!in_array($method, ['onsite', 'cash'], true)) {
            return false;
        }

        // Idempotency: do not charge twice.
        if (isset($booking->cancellation_fee) && floatval($booking->cancellation_fee) > 0) {
            return false;
        }

        // Only within the cancellation window before the booking start.
        $hours = intval(get_option('hrb_cancellation_hours', 24));
        $booking_datetime = strtotime($booking->booking_date . ' ' . $booking->start_time);
        if ($booking_datetime && time() < ($booking_datetime - $hours * 3600)) {
            // Cancelled early enough — no fee.
            return false;
        }

        $fee = self::CANCELLATION_FEE;

        // Record the fee on the booking.
        $wpdb->update(
            $wpdb->prefix . 'hrb_bookings',
            array('cancellation_fee' => $fee),
            array('id' => $booking_id),
            array('%f'),
            array('%d')
        );

        // Record a labelled, pending payment row (payable on-site). The
        // CANCELFEE_ transaction prefix lets the payments view label it.
        $wpdb->insert(
            $wpdb->prefix . 'hrb_payments',
            array(
                'booking_id'           => $booking_id,
                'transaction_id'       => 'CANCELFEE_' . $booking->booking_reference,
                'payment_method'       => $booking->payment_method,
                'amount'               => $fee,
                'currency'             => 'EUR',
                'status'               => 'pending',
                'is_additional_payment'=> 1,
                'gateway_response'     => 'Cancellation Fee',
                'created_at'           => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s')
        );

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
            
            // Delete booking modifications
            $wpdb->delete(
                $wpdb->prefix . 'hrb_booking_modifications',
                ['booking_id' => $booking_id],
                ['%d']
            );
            
            // Delete payments
            $wpdb->delete(
                $wpdb->prefix . 'hrb_payments',
                ['booking_id' => $booking_id],
                ['%d']
            );
            
            // Delete invoices
            $wpdb->delete(
                $wpdb->prefix . 'hrb_invoices',
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
            $where_conditions[] = '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s OR b.first_name LIKE %s OR b.last_name LIKE %s)';
            $params[] = $search;
            $params[] = $search;
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
        
        $tax_rate = floatval(get_option('hrb_tax_rate', 19));
        
        $invoice_data = array(
            'invoice_number' => $invoice_number,
            'booking_id' => $booking_id,
            'customer_id' => $booking->customer_id,
            'issue_date' => current_time('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+14 days')),
            'subtotal' => $booking->base_price,
            'tax_rate' => $tax_rate,
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
            return new WP_Error('invoice_creation_failed', __('Failed to create invoice', 'hourly-room-booking') . ': ' . $wpdb->last_error);
        }
        
        $invoice_id = $wpdb->insert_id;
        
        // Update invoice counter
        update_option('hrb_invoice_counter', $invoice_counter + 1);
        
        return $invoice_id;
    }
    
    /**
     * Send booking notification
     */
    public function send_booking_notification($booking_id, $event, $custom_data = array()) {
        $notification_manager = HRB_Notification_Manager::getInstance();
        return $notification_manager->send_notification($booking_id, $event, $custom_data);
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
        // Filter out anonymous bookings and bookings without email
        $upcoming_bookings = $wpdb->get_results(
            "SELECT b.*, c.email, c.phone, c.first_name, c.last_name
             FROM {$wpdb->prefix}hrb_bookings b
             LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
             WHERE b.status = 'confirmed'
             AND b.is_anonymous = 0
             AND c.email IS NOT NULL
             AND c.email != ''
             AND CONCAT(b.booking_date, ' ', b.start_time) BETWEEN NOW() + INTERVAL 45 MINUTE AND NOW() + INTERVAL 75 MINUTE"
        );
        
        $reminders_sent = 0;
        $reminders_skipped = 0;
        $reminders_failed = 0;
        
        foreach ($upcoming_bookings as $booking) {
            // Validate email before proceeding
            if (empty($booking->email) || !is_email($booking->email)) {
                $reminders_skipped++;
                continue;
            }
            
            // Check if reminder already sent
            $reminder_sent = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_notification_logs 
                 WHERE booking_id = %d AND event = 'booking_reminder' AND status IN ('sent', 'delivered')",
                $booking->id
            ));
            
            if ($reminder_sent == 0) {
                $result = $this->send_booking_notification($booking->id, 'booking_reminder');
                
                if (is_wp_error($result)) {
                    $reminders_failed++;
                } else {
                    $reminders_sent++;
                }
            } else {
                $reminders_skipped++;
            }
        }
        
        return array(
            'total_found' => count($upcoming_bookings),
            'reminders_sent' => $reminders_sent,
            'reminders_skipped' => $reminders_skipped,
            'reminders_failed' => $reminders_failed
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

        // Display rule for the payment-status column (see the two CASE expressions below).
        // A booking is shown as completed in the list ONLY when it is actually fully paid.
        // The latest payment row (MAX id) can be a completed PayPal capture while a
        // later-added extra still leaves a pending balance; that previously made the list
        // show completed even though money was still owed, while the calendar and detail
        // views correctly showed pending. The CASE expressions downgrade such a completed
        // status back to pending ONLY when there are completed payments that do not cover
        // the booking total (a genuine balance-due / partial-payment case). Bookings an
        // admin manually marked completed with no completed payment record
        // (completed_paid = 0) are intentionally left untouched.
        // NOTE: keep this comment OUTSIDE the double-quoted SQL string below -- a double
        // quote inside that string would terminate it and cause a PHP parse error.
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
                b.is_anonymous,
                b.payment_method,
                CASE
                    WHEN COALESCE(p.status, b.payment_status) IN ('completed', 'paid')
                         AND b.total_amount > 0
                         AND COALESCE(pay.completed_paid, 0) > 0
                         AND COALESCE(pay.completed_paid, 0) + 0.01 < b.total_amount
                    THEN 'pending'
                    ELSE COALESCE(p.status, b.payment_status)
                END as payment_status,
                CASE
                    WHEN COALESCE(p.status, b.payment_status) IN ('completed', 'paid')
                         AND b.total_amount > 0
                         AND COALESCE(pay.completed_paid, 0) > 0
                         AND COALESCE(pay.completed_paid, 0) + 0.01 < b.total_amount
                    THEN 'pending'
                    ELSE p.status
                END as actual_payment_status,
                p.transaction_id,
                p.processed_at,
                b.total_amount,
                b.cancellation_fee,
                b.extra_people,
                b.created_at,
                CASE 
                    WHEN b.is_anonymous = 1 THEN CONCAT(
                        CASE WHEN b.first_name IS NOT NULL AND b.first_name != '' AND b.first_name != '0' THEN b.first_name ELSE '' END, 
                        CASE WHEN b.last_name IS NOT NULL AND b.last_name != '' AND b.last_name != '0' THEN CONCAT(' ', b.last_name) ELSE '' END
                    )
                    ELSE CONCAT(c.first_name, ' ', c.last_name)
                END as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                r.name as room_name
            FROM {$wpdb->prefix}hrb_bookings b
            LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
            LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            LEFT JOIN (
                SELECT booking_id, status, transaction_id, processed_at
                FROM {$wpdb->prefix}hrb_payments
                WHERE id IN (
                    SELECT MAX(id) 
                    FROM {$wpdb->prefix}hrb_payments 
                    GROUP BY booking_id
                )
            ) p ON b.id = p.booking_id
            LEFT JOIN (
                SELECT booking_id, COALESCE(SUM(amount), 0) AS completed_paid
                FROM {$wpdb->prefix}hrb_payments
                WHERE status IN ('completed', 'paid')
                GROUP BY booking_id
            ) pay ON b.id = pay.booking_id
            {$where_clause}
            GROUP BY b.id
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
            // Auto-cancel payment status for all payment methods when booking is cancelled.
            // Never cancel the standalone cancellation-fee charge (CANCELFEE_*).
            if ($status === 'cancelled') {
                $booking = $this->get_booking($booking_id);
                if ($booking && $booking->payment_status === 'pending') {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}hrb_payments SET status = 'cancelled'
                         WHERE booking_id = %d AND (transaction_id NOT LIKE %s OR transaction_id IS NULL)",
                        $booking_id,
                        $wpdb->esc_like('CANCELFEE_') . '%'
                    ));
                }

                // Apply the cancellation fee (cash/onsite, within window). Idempotent.
                $this->maybe_apply_cancellation_fee($booking_id);
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
    
    /**
     * Cleanup incomplete PayPal payments after 15 minutes
     * This prevents time slots from being blocked by abandoned payment processes
     */
    public function cleanup_incomplete_payments() {
        global $wpdb;
        
        // Find bookings with PayPal payment method that are still pending after 15 minutes
        $incomplete_bookings = $wpdb->get_results($wpdb->prepare("
            SELECT b.id, b.booking_reference, b.customer_id, b.room_id, b.booking_date, b.start_time, b.end_time
            FROM {$wpdb->prefix}hrb_bookings b
            LEFT JOIN {$wpdb->prefix}hrb_payments p ON b.id = p.booking_id
            WHERE b.payment_method = 'paypal' 
            AND b.status = 'pending' 
            AND b.payment_status = 'pending'
            AND b.created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            AND (p.id IS NULL OR p.status = 'pending')
        "));
        
        $cancelled_count = 0;
        
        foreach ($incomplete_bookings as $booking) {
            // Cancel the booking
            $result = $wpdb->update(
                $wpdb->prefix . 'hrb_bookings',
                array(
                    'status' => 'cancelled',
                    'payment_status' => 'cancelled'
                ),
                array('id' => $booking->id),
                array('%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $cancelled_count++;
                
                // Send payment timeout cancellation email
                $this->send_booking_notification($booking->id, 'payment_timeout_cancellation');
                
            }
        }
        
        return $cancelled_count;
    }
}
?>