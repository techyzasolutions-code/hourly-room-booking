<?php
/**
 * AJAX Handler Class
 * Handles all AJAX requests
 */

if (!defined('ABSPATH')) {
    exit;
}
class HRB_Ajax_Handler {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Booking form AJAX actions
        add_action('wp_ajax_hrb_check_availability', array($this, 'check_availability'));
        add_action('wp_ajax_nopriv_hrb_check_availability', array($this, 'check_availability'));
        
        add_action('wp_ajax_hrb_calculate_price', array($this, 'calculate_price'));
        add_action('wp_ajax_nopriv_hrb_calculate_price', array($this, 'calculate_price'));
        
        add_action('wp_ajax_hrb_search_rooms', array($this, 'search_rooms'));
        add_action('wp_ajax_nopriv_hrb_search_rooms', array($this, 'search_rooms'));
        
        add_action('wp_ajax_hrb_submit_booking', array($this, 'submit_booking'));
        add_action('wp_ajax_nopriv_hrb_submit_booking', array($this, 'submit_booking'));
        
        add_action('wp_ajax_hrb_send_otp', array($this, 'send_otp'));
        add_action('wp_ajax_nopriv_hrb_send_otp', array($this, 'send_otp'));
        
        add_action('wp_ajax_hrb_verify_otp', array($this, 'verify_otp'));
        add_action('wp_ajax_nopriv_hrb_verify_otp', array($this, 'verify_otp'));
        
        add_action('wp_ajax_hrb_check_verification_status', array($this, 'check_verification_status'));
        add_action('wp_ajax_nopriv_hrb_check_verification_status', array($this, 'check_verification_status'));
        
        add_action('wp_ajax_hrb_clear_verification', array($this, 'clear_verification'));
        add_action('wp_ajax_nopriv_hrb_clear_verification', array($this, 'clear_verification'));
        
        // Note: hrb_get_calendar_events is handled by HRB_Admin and HRB_Calendar classes

        add_action('wp_ajax_hrb_get_booking_form', array($this, 'get_booking_form'));
        add_action('wp_ajax_nopriv_hrb_get_booking_form', array($this, 'get_booking_form'));
        
        // Admin AJAX actions
        add_action('wp_ajax_hrb_save_settings', array($this, 'save_settings'));

        add_action('wp_ajax_hrb_get_room_details_modal', array($this, 'get_room_details_modal'));
        add_action('wp_ajax_nopriv_hrb_get_room_details_modal', array($this, 'get_room_details_modal'));

        add_action('wp_ajax_hrb_get_available_time_slots', array($this, 'get_available_time_slots'));
        add_action('wp_ajax_nopriv_hrb_get_available_time_slots', array($this, 'get_available_time_slots'));
        
        add_action('wp_ajax_hrb_get_room_pricing', array($this, 'get_room_pricing'));
        add_action('wp_ajax_hrb_get_room_pricing_data', array($this, 'get_room_pricing_data'));

        add_action('wp_ajax_hrb_get_available_extras', array($this, 'get_available_extras'));
        add_action('wp_ajax_nopriv_hrb_get_available_extras', array($this, 'get_available_extras'));

        // Verification handlers
        add_action('wp_ajax_hrb_send_verification_code', array($this, 'send_verification_code'));
        add_action('wp_ajax_nopriv_hrb_send_verification_code', array($this, 'send_verification_code'));

        add_action('wp_ajax_hrb_verify_code', array($this, 'verify_code'));
        add_action('wp_ajax_nopriv_hrb_verify_code', array($this, 'verify_code'));

        add_action('wp_ajax_hrb_check_verification_status', array($this, 'check_verification_status'));
        add_action('wp_ajax_nopriv_hrb_check_verification_status', array($this, 'check_verification_status'));

        add_action('wp_ajax_hrb_load_more_rooms', array($this, 'load_more_rooms'));
        add_action('wp_ajax_nopriv_hrb_load_more_rooms', array($this, 'load_more_rooms'));

        add_action('wp_ajax_hrb_get_calendar_events', array($this, 'get_calendar_events'));
        add_action('wp_ajax_nopriv_hrb_get_calendar_events', array($this, 'get_calendar_events'));
    }
    
    /**
     * Check room availability
     */
    public function check_availability() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $room_id = intval($_POST['room_id']);
        $date = sanitize_text_field($_POST['date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);
        
        if (empty($room_id) || empty($date) || empty($start_time) || empty($end_time)) {
            wp_send_json_error(__('Missing required parameters', 'hourly-room-booking'));
        }
        
        $room_manager = HRB_Room_Manager::getInstance();
        $is_available = $room_manager->is_room_available($room_id, $date, $start_time, $end_time);
        
        if ($is_available) {
            wp_send_json_success(array(
                'available' => true,
                'message' => __('Time slot is available', 'hourly-room-booking')
            ));
        } else {
            wp_send_json_error(__('Selected time slot is not available', 'hourly-room-booking'));
        }
    }
    
    /**
     * Calculate booking price
     */
    public function calculate_price() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $booking_data = array(
            'room_id' => intval($_POST['room_id']),
            'booking_date' => sanitize_text_field($_POST['booking_date']),
            'start_time' => sanitize_text_field($_POST['start_time']),
            'end_time' => sanitize_text_field($_POST['end_time']),
            'extra_people' => intval($_POST['extra_people']),
            'extras' => isset($_POST['extras']) ? $_POST['extras'] : array(),
            'payment_method' => sanitize_text_field($_POST['payment_method'])
        );
        
        $booking_manager = HRB_Booking_Manager::getInstance();
        $pricing = $booking_manager->calculate_booking_price($booking_data);
        
        wp_send_json_success($pricing);
    }
    
    /**
     * Search available rooms
     */
    public function search_rooms() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $filters = array(
            'date' => sanitize_text_field($_POST['date']),
            'start_time' => sanitize_text_field($_POST['start_time']),
            'end_time' => sanitize_text_field($_POST['end_time']),
            'min_capacity' => intval($_POST['min_capacity']),
            'max_price' => floatval($_POST['max_price']),
            'amenities' => isset($_POST['amenities']) ? array_map('sanitize_text_field', $_POST['amenities']) : array()
        );
        
        $room_manager = HRB_Room_Manager::getInstance();

        $rooms = $room_manager->search_rooms($filters);
        
        // Get currency symbol from settings
        $settings = HRB_Settings::getInstance();
        $currency_symbol = $settings->get('hrb_currency_symbol', '€');

        $results = array();
        foreach ($rooms as $room) {
            // Get price range using the new system
            $price_range = $room_manager->get_room_price_range($room);
            $amenities = $room_manager->get_room_amenities($room->id);

            $results[] = array(
                'id' => $room->id,
                'name' => $room->name,
                'description' => $room->description,
                'capacity' => $room->capacity,
                'price' => $price_range['min'], // Use min price for sorting
                'formatted_price' => $price_range['formatted'], // Use formatted price range
                'amenities' => $amenities,
                'images' => $room_manager->get_room_images($room->id),
                'external_link' => $room->external_link ?? ''
            );
        }
        
        wp_send_json_success($results);
    }

    /**
     * Load more rooms for pagination
     */
    public function load_more_rooms() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
        }

        $page = intval($_POST['page']) ?: 1;
        $per_page = intval($_POST['per_page']) ?: 6;
        $offset = ($page - 1) * $per_page;

        // Get room manager
        $room_manager = HRB_Room_Manager::getInstance();
        $all_rooms = $room_manager->get_all_rooms();

        // Filter active rooms only
        $rooms = array_filter($all_rooms, function($room) {
            return $room->is_active;
        });

        // Get rooms for this page
        $rooms_for_page = array_slice($rooms, $offset, $per_page);

        if (empty($rooms_for_page)) {
            wp_send_json_success(array(
                'rooms_html' => '',
                'has_more' => false
            ));
            return;
        }

        // Get currency symbol
        $settings = HRB_Settings::getInstance();
        $currency_symbol = $settings->get('hrb_currency_symbol', '€');

        // Generate HTML for rooms
        ob_start();
        foreach ($rooms_for_page as $room):
            $amenities = $room_manager->get_room_amenities($room->id);
            $images = $room_manager->get_room_images($room->id);
        ?>
            <div class="hrb-room-card">
                <div class="hrb-room-image">
                    <?php if (!empty($images)): ?>
                        <img src="<?php echo esc_url($images[0]); ?>" alt="<?php echo esc_attr($room->name); ?>">
                    <?php else: ?>
                        <div class="hrb-room-placeholder">
                            <i class="hrb-icon-room"></i>
                        </div>
                    <?php endif; ?>

                    <div class="hrb-room-price">
                        <?php 
                        $room_manager = HRB_Room_Manager::getInstance();
                        $price_range = $room_manager->get_room_price_range($room);
                        ?>
                        <span class="hrb-price">
                            <?php echo $price_range['formatted']; ?>
                        </span>
                    </div>
                </div>

                <div class="hrb-room-content">
                    <h3 class="hrb-room-title"><?php echo esc_html($room->name); ?></h3>

                    <?php if (!empty($room->description)): ?>
                        <p class="hrb-room-description">
                            <?php echo esc_html(wp_trim_words($room->description, 20)); ?>
                        </p>
                    <?php endif; ?>

                    <div class="hrb-room-details">
                        <div class="hrb-room-detail">
                            <i class="hrb-icon-people"></i>
                            <span><?php printf(__('Up to %d people', 'hourly-room-booking'), $room->capacity); ?></span>
                        </div>

                        <?php if (!empty($amenities)): ?>
                            <div class="hrb-room-detail">
                                <i class="hrb-icon-amenities"></i>
                                <span>
                                    <?php echo implode(', ', array_slice($amenities, 0, 3)); ?>
                                    <?php if (count($amenities) > 3): ?>
                                        <span class="hrb-more-amenities">+<?php echo count($amenities) - 3; ?> <?php _e('more', 'hourly-room-booking'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="hrb-room-actions">
                        <a href="#" class="hrb-btn hrb-btn-primary hrb-view-room" data-room-id="<?php echo $room->id; ?>" data-external-link="<?php echo esc_attr($room->external_link ?? ''); ?>">
                            <?php _e('View Details', 'hourly-room-booking'); ?>
                        </a>
                        <a href="#" class="hrb-btn hrb-btn-secondary hrb-book-room" data-room-id="<?php echo $room->id; ?>">
                            <?php _e('Book Now', 'hourly-room-booking'); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php
        endforeach;
        $rooms_html = ob_get_clean();

        // Check if there are more rooms
        $total_rooms = count($rooms);
        $loaded_rooms = $page * $per_page;
        $has_more = $loaded_rooms < $total_rooms;

        wp_send_json_success(array(
            'rooms_html' => $rooms_html,
            'has_more' => $has_more,
            'loaded_count' => $loaded_rooms,
            'total_count' => $total_rooms
        ));
    }

    /**
     * Get calendar events for room calendar
     */
    public function get_calendar_events() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_calendar_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
        }

        $room_id = intval($_POST['room_id']);
        $start_date = sanitize_text_field($_POST['start']);
        $end_date = sanitize_text_field($_POST['end']);

        if (empty($room_id)) {
            wp_send_json_error(__('Room ID is required', 'hourly-room-booking'));
        }

        global $wpdb;

        // Get bookings for this room in the date range
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.first_name, c.last_name, r.name as room_name
             FROM {$wpdb->prefix}hrb_bookings b
             LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
             LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
             WHERE b.room_id = %d
             AND b.booking_date BETWEEN %s AND %s
             AND b.status IN ('confirmed', 'pending')
             ORDER BY b.booking_date, b.start_time",
            $room_id,
            $start_date,
            $end_date
        ));

        $events = array();

        foreach ($bookings as $booking) {
            $start_datetime = $booking->booking_date . 'T' . $booking->start_time;
            $end_datetime = $booking->booking_date . 'T' . $booking->end_time;

            $status_text = ($booking->status === 'confirmed') ? __('Confirmed', 'hourly-room-booking') : __('Pending', 'hourly-room-booking');
            $customer_name = trim($booking->first_name . ' ' . $booking->last_name) ?: __('Unknown', 'hourly-room-booking');
            
            // Use full customer name for calendar display
            $title = $customer_name;

            $events[] = array(
                'id' => 'booking-' . $booking->id,
                'title' => $title,
                'start' => $start_datetime,
                'end' => $end_datetime,
                'status' => 'booking-' . $booking->status,
                'statusText' => $status_text,
                'bookingId' => $booking->id,
                'customerName' => $customer_name,
                'roomName' => $booking->room_name,
                'allDay' => false,
                'className' => 'hrb-booking-' . $booking->status
            );
        }

        wp_send_json_success(array(
            'events' => $events,
            'room_id' => $room_id,
            'start' => $start_date,
            'end' => $end_date
        ));
    }

    /**
     * Submit booking
     */
    public function submit_booking() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        

        // Validate and sanitize input using the new validator
        $validator = HRB_Input_Validator::getInstance();
        
        $booking_data = $validator->validate_booking_data($_POST);
        if (is_wp_error($booking_data)) {
            wp_send_json_error($booking_data->get_error_message());
        }
        
        $customer_data = $validator->validate_customer_data($_POST);
        if (is_wp_error($customer_data)) {
            wp_send_json_error($customer_data->get_error_message());
        }
        
        // OTP verification has been removed - skip this check
        
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Create or get customer - improved logic for logged-in users
            $customer = null;
            $current_user_id = is_user_logged_in() ? get_current_user_id() : null;

            if ($current_user_id) {
                // For logged-in users, first check by wp_user_id
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE wp_user_id = %d",
                    $current_user_id
                ));
            }

            // If no customer found by user ID, check by email (for both logged-in and guest users)
            if (!$customer) {
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE email = %s",
                    $customer_data['email']
                ));
            }

            if ($customer) {
                $customer_id = $customer->id;

                // Prepare update data
                $update_data = $customer_data;

                // If this is a logged-in user and the customer record doesn't have wp_user_id, add it
                if ($current_user_id && empty($customer->wp_user_id)) {
                    $update_data['wp_user_id'] = $current_user_id;
                }

                // Update customer info
                $update_format = array('%s', '%s', '%s', '%s', '%s');
                if (isset($update_data['wp_user_id'])) {
                    $update_format[] = '%d';
                }

                $wpdb->update(
                    $wpdb->prefix . 'hrb_customers',
                    $update_data,
                    array('id' => $customer_id),
                    $update_format,
                    array('%d')
                );

            } else {
                // Create new customer
                $new_customer_data = array_merge($customer_data, array('country' => 'DE'));

                // Add wp_user_id for logged-in users
                if ($current_user_id) {
                    $new_customer_data['wp_user_id'] = $current_user_id;
                }

                $insert_format = array('%s', '%s', '%s', '%s', '%s', '%s');
                if ($current_user_id) {
                    $insert_format[] = '%d';
                }

                $wpdb->insert(
                    $wpdb->prefix . 'hrb_customers',
                    $new_customer_data,
                    $insert_format
                );
                $customer_id = $wpdb->insert_id;

            }
            
            if (!$customer_id) {
                throw new Exception(__('Failed to create customer record', 'hourly-room-booking'));
            }
            
            // Note: Extras stock validation will be done after booking creation to avoid race conditions

            // Create booking
            $booking_data['customer_id'] = $customer_id;
            $booking_manager = HRB_Booking_Manager::getInstance();
            $booking_id = $booking_manager->create_booking($booking_data);
            
            if (is_wp_error($booking_id)) {
                throw new Exception($booking_id->get_error_message());
            }
            
            // Save extras with stock management
            if (!empty($booking_data['extras'])) {
                $extras_result = $booking_manager->save_booking_extras(
                    $booking_id,
                    $booking_data['extras'],
                    $booking_data['booking_date'],
                    $booking_data['start_time'],
                    $booking_data['end_time']
                );
                
                if (is_wp_error($extras_result)) {
                    $wpdb->query('ROLLBACK');
                    throw new Exception($extras_result->get_error_message());
                }
            }
            
            // Process payment based on payment method
            $payment_handler = HRB_Payment_Handler::getInstance();
            
            if ($booking_data['payment_method'] === 'onsite') {
                // For on-site payment: booking=confirmed, payment=pending
                $payment_handler->process_onsite_payment($booking_id);
            }
            // For PayPal, payment processing happens in the PayPal flow
            
            $wpdb->query('COMMIT');

            // Save booking-specific user meta for logged-in users
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();

                // Save booking-specific meta fields for future auto-fill
                update_user_meta($user_id, 'hrb_booking_first_name', $customer_data['first_name']);
                update_user_meta($user_id, 'hrb_booking_last_name', $customer_data['last_name']);
                update_user_meta($user_id, 'hrb_booking_email', $customer_data['email']);
                update_user_meta($user_id, 'hrb_booking_phone', $customer_data['phone']);

                // Only save company if it's not empty
                if (!empty($customer_data['company'])) {
                    update_user_meta($user_id, 'hrb_booking_company', $customer_data['company']);
                }

            }

            // Get the created booking
            $booking = $booking_manager->get_booking($booking_id);

            wp_send_json_success(array(
                'booking_id' => $booking_id,
                'booking_reference' => $booking->booking_reference,
                'total_amount' => $booking->total_amount,
                'payment_method' => $booking_data['payment_method'],
                'booking_url' => site_url('/booking-details/?ref=' . $booking->booking_reference),
                'message' => __('Booking created successfully', 'hourly-room-booking')
            ));
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('HRB Booking Error: ' . $e->getMessage());
            wp_send_json_error('Booking failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Send OTP verification
     */
    public function send_otp() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $type = sanitize_text_field($_POST['type']); // email or sms
        
        if (empty($email) || empty($phone)) {
            wp_send_json_error(__('Email and phone are required', 'hourly-room-booking'));
        }
        
        $notification_manager = HRB_Notification_Manager::getInstance();
        $result = $notification_manager->send_otp_verification($email, $phone, $type);
        
        if ($result) {
            wp_send_json_success(__('Verification code sent successfully', 'hourly-room-booking'));
        } else {
            wp_send_json_error(__('Failed to send verification code', 'hourly-room-booking'));
        }
    }
    
    /**
     * Verify OTP code
     */
    public function verify_otp() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $otp_code = sanitize_text_field($_POST['otp_code']);
        
        if (empty($email) || empty($phone) || empty($otp_code)) {
            wp_send_json_error(__('All fields are required', 'hourly-room-booking'));
        }
        
        $notification_manager = HRB_Notification_Manager::getInstance();
        $is_valid = $notification_manager->verify_otp($email, $phone, $otp_code);
        
        if ($is_valid) {
            // Set session verification for this email and IP
            $session_key = 'hrb_verified_email_' . md5($email . $_SERVER['REMOTE_ADDR']);
            $_SESSION[$session_key] = true;
            
            // Set transient for IP-based verification (24 hours)
            $ip_key = 'hrb_verified_email_ip_' . md5($email . $_SERVER['REMOTE_ADDR']);
            set_transient($ip_key, true, 24 * HOUR_IN_SECONDS);
            
            wp_send_json_success(__('Verification successful', 'hourly-room-booking'));
        } else {
            wp_send_json_error(__('Invalid or expired verification code', 'hourly-room-booking'));
        }
    }
    
    
    /**
     * Get color for booking status
     */
    private function get_status_color($status) {
        $colors = array(
            'pending' => '#ffc107',
            'confirmed' => '#28a745',
            'completed' => '#6c757d',
            'cancelled' => '#dc3545',
            'no_show' => '#fd7e14'
        );

        return isset($colors[$status]) ? $colors[$status] : '#007bff';
    }

    /**
     * Get booking form for modal
     */
    public function get_booking_form() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_booking_form_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
        }

        $room_id = intval($_POST['room_id']);

        if (empty($room_id)) {
            wp_send_json_error(__('Room ID is required', 'hourly-room-booking'));
        }

        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($room_id);

        if (!$room || !$room->is_active) {
            wp_send_json_error(__('Room not available', 'hourly-room-booking'));
        }

        // Get pre-fill parameters from search form
        $prefill_date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $prefill_time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $prefill_duration = isset($_POST['duration']) ? sanitize_text_field($_POST['duration']) : '';

        // Debug logging
        error_log('HRB AJAX: Received pre-fill values - Date: ' . $prefill_date . ', Time: ' . $prefill_time . ', Duration: ' . $prefill_duration);

        // Set up variables for the booking form template
        $atts = array(
            'room_id' => $room_id,
            'show_room_info' => 'false' // Don't show room info in modal
        );

        // Set pre-fill values as global variables for the template
        // The template expects these variables to be available
        $GLOBALS['prefill_date'] = $prefill_date;
        $GLOBALS['prefill_time'] = $prefill_time;
        $GLOBALS['prefill_duration'] = $prefill_duration;

        // Get available extras
        $extras_manager = HRB_Extras::getInstance();
        $available_extras = $extras_manager->get_extras(true);

        // Get settings for pricing
        $settings = HRB_Settings::getInstance();
        $extra_people_price = $settings->get('hrb_extra_person_price', 15);

        // Capture the booking form output
        ob_start();
        include HRB_PLUGIN_DIR . 'templates/booking-form.php';
        $form_html = ob_get_clean();

        wp_send_json_success(array('html' => $form_html));
    }

    /**
     * Get room details for modal
     */
    public function get_room_details_modal() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_room_details_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
        }

        $room_id = intval($_POST['room_id']);

        if (empty($room_id)) {
            wp_send_json_error(__('Room ID is required', 'hourly-room-booking'));
        }

        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($room_id);

        if (!$room || !$room->is_active) {
            wp_send_json_error(__('Room not available', 'hourly-room-booking'));
        }

        // Get room amenities and images
        $amenities = $room_manager->get_room_amenities($room_id);
        $images = $room_manager->get_room_images($room_id);

        // Get currency symbol from settings
        $settings = HRB_Settings::getInstance();
        $currency_symbol = $settings->get('hrb_currency_symbol', '€');
        
        // Get room price range
        $price_range = $room_manager->get_room_price_range($room);

        // Build room details HTML
        ob_start();
        ?>
        <div class="hrb-room-details-modal">
            <div class="hrb-room-gallery">
                <?php if (!empty($images)): ?>
                    <div class="hrb-room-main-image">
                        <img src="<?php echo esc_url($images[0]); ?>" alt="<?php echo esc_attr($room->name); ?>">
                    </div>
                    <?php if (count($images) > 1): ?>
                        <div class="hrb-room-thumbnails">
                            <?php foreach (array_slice($images, 1, 4) as $image): ?>
                                <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($room->name); ?>" class="hrb-thumbnail">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="hrb-room-placeholder">
                        <i class="hrb-icon-room"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="hrb-room-info">
                <h2><?php echo esc_html($room->name); ?></h2>

                <?php if (!empty($room->description)): ?>
                    <div class="hrb-room-description">
                        <p><?php echo esc_html($room->description); ?></p>
                    </div>
                <?php endif; ?>

                <div class="hrb-room-specs">
                    <div class="hrb-spec-item">
                        <strong><?php _e('Capacity:', 'hourly-room-booking'); ?></strong>
                        <span><?php printf(__('%d people', 'hourly-room-booking'), $room->capacity); ?></span>
                    </div>
                    <div class="hrb-spec-item">
                        <strong><?php _e('Price Range:', 'hourly-room-booking'); ?></strong>
                        <span><?php echo $price_range['formatted']; ?></span>
                    </div>
                </div>

                <?php if (!empty($amenities)): ?>
                    <div class="hrb-room-amenities">
                        <h4><?php _e('Amenities', 'hourly-room-booking'); ?></h4>
                        <div class="hrb-amenities-list">
                            <?php foreach ($amenities as $amenity): ?>
                                <span class="hrb-amenity-tag"><?php echo esc_html($amenity); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="hrb-room-actions">
                    <button class="hrb-btn hrb-btn-primary hrb-book-this-room" data-room-id="<?php echo $room_id; ?>">
                        <?php _e('Book This Room', 'hourly-room-booking'); ?>
                    </button>
                </div>
            </div>
        </div>

        <style>
        .hrb-room-details-modal {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .hrb-room-gallery {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .hrb-room-main-image img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 8px;
        }

        .hrb-room-thumbnails {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .hrb-thumbnail {
            width: 100%;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }

        .hrb-thumbnail:hover {
            opacity: 0.8;
        }

        .hrb-room-placeholder {
            width: 100%;
            height: 300px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #999;
            border-radius: 8px;
        }

        .hrb-room-info h2 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 28px;
        }

        .hrb-room-description {
            margin-bottom: 20px;
            color: #666;
            line-height: 1.6;
        }

        .hrb-room-specs {
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .hrb-spec-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .hrb-spec-item:last-child {
            margin-bottom: 0;
        }

        .hrb-room-amenities h4 {
            margin: 0 0 15px 0;
            color: #333;
        }

        .hrb-amenities-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 25px;
        }

        .hrb-amenity-tag {
            background: #e9ecef;
            color: #495057;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        .hrb-room-actions {
            margin-top: 20px;
        }

        .hrb-book-this-room {
            width: 100%;
            padding: 15px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .hrb-book-this-room:hover {
            background: #005a87;
        }

        @media (max-width: 768px) {
            .hrb-room-details-modal {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .hrb-room-main-image img {
                height: 200px;
            }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Handle thumbnail clicks
            $('.hrb-thumbnail').on('click', function() {
                const newSrc = $(this).attr('src');
                $('.hrb-room-main-image img').attr('src', newSrc);
            });

            // Handle book this room button
            $('.hrb-book-this-room').on('click', function() {
                const roomId = $(this).data('room-id');

                // Close current modal
                $('.hrb-modal-overlay').remove();

                // Show booking modal
                showBookingModal(roomId);
            });
        });
        </script>
        <?php
        $details_html = ob_get_clean();

        wp_send_json_success(array('html' => $details_html));
    }

    /**
     * Get available time slots for a room and date
     */
    public function get_available_time_slots() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }

        $room_id = intval($_POST['room_id']);
        $date = sanitize_text_field($_POST['date']);
        $duration = floatval($_POST['duration']);

        if (empty($room_id) || empty($date) || empty($duration)) {
            wp_send_json_error(__('Missing required parameters', 'hourly-room-booking'));
        }

        // Validate duration (2-12 hours)
        if ($duration < 2 || $duration > 12) {
            wp_send_json_error(__('Invalid duration. Must be between 2-12 hours', 'hourly-room-booking'));
        }

        $room_manager = HRB_Room_Manager::getInstance();
        $available_slots = $this->generate_available_time_slots($room_id, $date, $duration);

        wp_send_json_success(array(
            'slots' => $available_slots,
            'message' => sprintf(__('%d time slots available', 'hourly-room-booking'), count($available_slots))
        ));
    }

    /**
     * Generate available time slots for a given date and duration
     */
    private function generate_available_time_slots($room_id, $date, $duration) {
        $available_slots = array();

        // Get booking time range from settings
        $booking_start_time = get_option('hrb_booking_start_time', '08:00');
        $booking_end_time = get_option('hrb_booking_end_time', '20:00');
        
        // Parse start and end times
        $start_hour = intval(substr($booking_start_time, 0, 2));
        $end_hour = intval(substr($booking_end_time, 0, 2));

        for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
            // Check both :00 and :30 minute slots
            foreach (['00', '30'] as $minute) {
                $start_time = sprintf('%02d:%s:00', $hour, $minute);
                $end_time_timestamp = strtotime($start_time) + ($duration * 3600);
                $end_time = date('H:i:s', $end_time_timestamp);

                // Check if booking ends within the allowed time range
                $end_time_formatted = date('H:i', $end_time_timestamp);
                $booking_end_hour = intval(substr($booking_end_time, 0, 2));
                $booking_start_hour = intval(substr($booking_start_time, 0, 2));
                
                // Check if end time is after booking end time or before booking start time
                if ($end_time_formatted > $booking_end_time || $start_time < $booking_start_time . ':00') {
                    continue;
                }

                // Check if this slot is available (including cooldown periods)
                $is_available = $this->is_slot_available_with_cooldown($room_id, $date, $start_time, $end_time);
                
                $slot_data = array(
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'display_start' => date('H:i', strtotime($start_time)),
                    'display_end' => date('H:i', strtotime($end_time)),
                    'label' => sprintf('%s - %s',
                        date('H:i', strtotime($start_time)),
                        date('H:i', strtotime($end_time))
                    ),
                    'available' => $is_available
                );
                
                // Add all slots (both available and unavailable)
                $available_slots[] = $slot_data;
            }
        }

        return $available_slots;
    }

    /**
     * Check if a time slot is available including cooldown periods
     */
    private function is_slot_available_with_cooldown($room_id, $date, $start_time, $end_time) {
        global $wpdb;

        // Get cooldown minutes from settings
        $cooldown_minutes = intval(get_option('hrb_cooldown_minutes', 30));

        // Check for direct time conflicts first
        $direct_conflict = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_bookings
             WHERE room_id = %d
             AND booking_date = %s
             AND status NOT IN ('cancelled', 'no_show')
             AND (
                 (start_time < %s AND end_time > %s) OR
                 (start_time < %s AND end_time > %s) OR
                 (start_time >= %s AND start_time < %s)
             )",
            $room_id, $date, $end_time, $start_time, $start_time, $end_time, $start_time, $end_time
        ));

        if ($direct_conflict > 0) {
            return false;
        }

        // Check for cooldown conflicts after existing bookings end
        $cooldown_conflict_after = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_bookings
             WHERE room_id = %d
             AND booking_date = %s
             AND status NOT IN ('cancelled', 'no_show')
             AND TIME_TO_SEC(%s) >= TIME_TO_SEC(end_time)
             AND TIME_TO_SEC(%s) < TIME_TO_SEC(end_time) + (%d * 60)",
            $room_id, $date, $start_time, $start_time, $cooldown_minutes
        ));

        // Check for cooldown conflicts before existing bookings start
        // New booking ends too close to existing booking start
        $cooldown_conflict_before = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_bookings
             WHERE room_id = %d
             AND booking_date = %s
             AND status NOT IN ('cancelled', 'no_show')
             AND TIME_TO_SEC(%s) > TIME_TO_SEC(start_time) - (%d * 60)
             AND TIME_TO_SEC(%s) <= TIME_TO_SEC(start_time)",
            $room_id, $date, $end_time, $cooldown_minutes, $end_time
        ));

        // Debug: Log cooldown conflicts
        if ($cooldown_conflict_after > 0 || $cooldown_conflict_before > 0) {
            error_log("COOLDOWN CONFLICT - Start: $start_time, After: $cooldown_conflict_after, Before: $cooldown_conflict_before");
        }

        return ($cooldown_conflict_after == 0 && $cooldown_conflict_before == 0);
    }

    /**
     * Send verification code
     */
    public function send_verification_code() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }

        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $type = sanitize_text_field($_POST['type']); // email or sms

        if (empty($email)) {
            wp_send_json_error(__('Email is required', 'hourly-room-booking'));
        }

        if ($type === 'sms' && empty($phone)) {
            wp_send_json_error(__('Phone number is required for SMS verification', 'hourly-room-booking'));
        }

        // Check if customer exists and create if needed
        global $wpdb;
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE email = %s",
            $email
        ));

        // Check if email is verified for current session (not globally)
        $session_key = 'hrb_verified_email_' . md5($email . $_SERVER['REMOTE_ADDR']);
        $is_session_verified = isset($_SESSION[$session_key]) && $_SESSION[$session_key] === true;
        
        // Check if email was verified recently (within 24 hours) for this IP
        $ip_key = 'hrb_verified_email_ip_' . md5($email . $_SERVER['REMOTE_ADDR']);
        $is_ip_verified = get_transient($ip_key) === true;
        
        // Only skip OTP if verified for current session/IP
        if ($is_session_verified || $is_ip_verified) {
            wp_send_json_success(__('Email is already verified for this session', 'hourly-room-booking'));
        }

        // If customer doesn't exist, create a new record for verification tracking
        if (!$customer) {
            $current_user_id = is_user_logged_in() ? get_current_user_id() : null;

            // Prepare customer data
            $customer_data = array(
                'first_name' => '',
                'last_name' => '',
                'email' => $email,
                'phone' => $phone,
                'company' => '',
                'country' => 'DE',
                'is_verified' => 0
            );

            // Add wp_user_id for logged-in users
            if ($current_user_id) {
                $customer_data['wp_user_id'] = $current_user_id;
            }

            // Set format array based on whether wp_user_id is included
            $format_array = array('%s', '%s', '%s', '%s', '%s', '%s', '%d');
            if ($current_user_id) {
                $format_array[] = '%d';
            }

            $wpdb->insert(
                $wpdb->prefix . 'hrb_customers',
                $customer_data,
                $format_array
            );

        }

        $notification_manager = HRB_Notification_Manager::getInstance();
        $result = $notification_manager->send_otp_verification($email, $phone, $type);

        if ($result) {
            $message = $type === 'sms' ?
                __('SMS verification code sent successfully', 'hourly-room-booking') :
                __('Email verification code sent successfully', 'hourly-room-booking');
            wp_send_json_success($message);
        } else {
            wp_send_json_error(__('Failed to send verification code', 'hourly-room-booking'));
        }
    }

    /**
     * Verify code
     */
    public function verify_code() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }

        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $code = sanitize_text_field($_POST['code']);

        if (empty($email) || empty($code)) {
            wp_send_json_error(__('Email and verification code are required', 'hourly-room-booking'));
        }

        $notification_manager = HRB_Notification_Manager::getInstance();
        $is_valid = $notification_manager->verify_otp($email, $phone, $code);

        if ($is_valid) {
            // Update customer verification status in database
            global $wpdb;

            // Find customer by email or phone and update verification status
            $customer = null;
            if (!empty($email)) {
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE email = %s",
                    $email
                ));
            } elseif (!empty($phone)) {
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE phone = %s",
                    $phone
                ));
            }

            if ($customer) {
                $wpdb->update(
                    $wpdb->prefix . 'hrb_customers',
                    array('is_verified' => 1),
                    array('id' => $customer->id),
                    array('%d'),
                    array('%d')
                );
            }

            // Set session verification for this email and IP
            $session_key = 'hrb_verified_email_' . md5($email . $_SERVER['REMOTE_ADDR']);
            $_SESSION[$session_key] = true;
            
            // Set transient for IP-based verification (24 hours)
            $ip_key = 'hrb_verified_email_ip_' . md5($email . $_SERVER['REMOTE_ADDR']);
            set_transient($ip_key, true, 24 * HOUR_IN_SECONDS);

            wp_send_json_success(__('Verification successful', 'hourly-room-booking'));
        } else {
            wp_send_json_error(__('Invalid or expired verification code', 'hourly-room-booking'));
        }
    }

    /**
     * Check if customer is already verified
     */
    public function check_verification_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }

        $email = sanitize_email($_POST['email']);

        if (empty($email)) {
            wp_send_json_error(__('Email is required', 'hourly-room-booking'));
        }

        // Check if email is verified in current session
        $session_key = 'hrb_verified_email_' . md5($email . $_SERVER['REMOTE_ADDR']);
        $is_session_verified = isset($_SESSION[$session_key]) && $_SESSION[$session_key] === true;
        
        // Check if email was verified recently (within 24 hours) for this IP
        $ip_key = 'hrb_verified_email_ip_' . md5($email . $_SERVER['REMOTE_ADDR']);
        $is_ip_verified = get_transient($ip_key) === true;

        if ($is_session_verified || $is_ip_verified) {
            wp_send_json_success(array(
                'is_verified' => true,
                'message' => __('Email is verified for this session! You can proceed with booking.', 'hourly-room-booking')
            ));
        } else {
            wp_send_json_success(array(
                'is_verified' => false,
                'message' => __('Email verification required', 'hourly-room-booking')
            ));
        }
    }

    /**
     * Clear email verification for current session
     */
    public function clear_verification() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }

        $email = sanitize_email($_POST['email']);

        if (empty($email)) {
            wp_send_json_error(__('Email is required', 'hourly-room-booking'));
        }

        // Clear session verification
        $session_key = 'hrb_verified_email_' . md5($email . $_SERVER['REMOTE_ADDR']);
        unset($_SESSION[$session_key]);
        
        // Clear IP-based verification
        $ip_key = 'hrb_verified_email_ip_' . md5($email . $_SERVER['REMOTE_ADDR']);
        delete_transient($ip_key);

        wp_send_json_success(__('Email verification cleared. Please verify your email again.', 'hourly-room-booking'));
    }

    /**
     * Get available extras based on stock for a specific date and time
     */
    public function get_available_extras() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }

        $booking_date = sanitize_text_field($_POST['booking_date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);

        if (empty($booking_date) || empty($start_time) || empty($end_time)) {
            wp_send_json_error(__('Missing required parameters', 'hourly-room-booking'));
        }

        // Use the new stock management system
        $stock_manager = HRB_Extra_Stock_Manager::getInstance();
        $available_extras = $stock_manager->get_available_extras($booking_date, $start_time, $end_time);

        wp_send_json_success(array(
            'extras' => $available_extras,
            'message' => sprintf(__('%d extras available', 'hourly-room-booking'), count($available_extras))
        ));
    }
    
    /**
     * Get room pricing for admin booking form
     */
    public function get_room_pricing() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
            return;
        }
        
        $room_id = intval($_POST['room_id']);
        $duration = intval($_POST['duration']);
        
        if (!$room_id || !$duration) {
            wp_send_json_error(__('Invalid room ID or duration', 'hourly-room-booking'));
            return;
        }
        
        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($room_id);
        
        if (!$room) {
            wp_send_json_error(__('Room not found', 'hourly-room-booking'));
            return;
        }
        
        // Calculate base price for the duration
        $booking_manager = HRB_Booking_Manager::getInstance();
        $base_price = $booking_manager->calculate_base_price($room, $duration);
        
        wp_send_json_success(array(
            'base_price' => $base_price,
            'room_name' => $room->name,
            'duration' => $duration
        ));
    }
    
    /**
     * Get room pricing data for admin duration dropdown
     */
    public function get_room_pricing_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
            return;
        }
        
        $room_id = intval($_POST['room_id']);
        
        if (!$room_id) {
            wp_send_json_error(__('Invalid room ID', 'hourly-room-booking'));
            return;
        }
        
        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($room_id);
        
        if (!$room) {
            wp_send_json_error(__('Room not found', 'hourly-room-booking'));
            return;
        }
        
        wp_send_json_success(array(
            'price_2_hours' => floatval($room->price_2_hours),
            'price_3_hours' => floatval($room->price_3_hours),
            'price_4_hours' => floatval($room->price_4_hours),
            'price_extra_hour' => floatval($room->price_extra_hour),
            'room_name' => $room->name
        ));
    }
    
    /**
     * Save plugin settings
     */
    public function save_settings() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }
        
        // Get settings from POST data
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        // Debug: Log received settings
        error_log('HRB: Received settings: ' . print_r($settings, true));
        
        if (empty($settings)) {
            wp_send_json_error(__('No settings provided', 'hourly-room-booking'));
            return;
        }
        
        // Use the settings class to save settings
        $settings_manager = HRB_Settings::getInstance();
        
        foreach ($settings as $key => $value) {
            error_log('HRB: Saving setting ' . $key . ' = ' . $value);
            $settings_manager->set($key, $value);
        }
        
        wp_send_json_success(__('Settings saved successfully', 'hourly-room-booking'));
    }
}