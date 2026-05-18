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
        
        add_action('wp_ajax_hrb_get_room_price_for_duration', array($this, 'get_room_price_for_duration'));
        add_action('wp_ajax_nopriv_hrb_get_room_price_for_duration', array($this, 'get_room_price_for_duration'));
        
        add_action('wp_ajax_hrb_get_room_price_range', array($this, 'get_room_price_range'));
        add_action('wp_ajax_nopriv_hrb_get_room_price_range', array($this, 'get_room_price_range'));
        
        add_action('wp_ajax_hrb_get_locked_dates', array($this, 'get_locked_dates'));
        add_action('wp_ajax_nopriv_hrb_get_locked_dates', array($this, 'get_locked_dates'));
    }
    
    /**
     * Check room availability
     */
    public function check_availability() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $booking_data = array(
            'room_id' => intval($_POST['room_id']),
            'booking_date' => sanitize_text_field($_POST['booking_date']),
            'start_time' => sanitize_text_field($_POST['start_time']),
            'end_time' => sanitize_text_field($_POST['end_time']),
            'extra_people' => intval($_POST['extra_people']),
            'extras' => isset($_POST['extras']) ? $_POST['extras'] : array(),
            'payment_method' => isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : 'onsite'
        );
        
        $booking_manager = HRB_Booking_Manager::getInstance();
        $pricing = $booking_manager->calculate_booking_price($booking_data);
        
        wp_send_json_success($pricing);
    }
    
    /**
     * Search available rooms
     */
    public function search_rooms() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
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

        // Get all rooms instead of filtering by availability
        $rooms = $room_manager->get_all_rooms();
        
        // Get currency symbol from settings
        $settings = HRB_Settings::getInstance();
        $currency_symbol = $settings->get('hrb_currency_symbol', '€');

        $results = array();
        global $wpdb;
        
        foreach ($rooms as $room) {
            $amenities = $room_manager->get_room_amenities($room->id);
            
            // Check availability if date/time filters are provided
            $is_available = true;
            if (!empty($filters['date'])) {
                // Check if there are any available time slots for this room on this date
                // Use default duration of 2 hours to check availability
                $check_duration = 2;
                $all_slots = $this->generate_available_time_slots($room->id, $filters['date'], $check_duration, 0);
                
                // Check if there's at least one available slot
                $has_available_slot = false;
                foreach ($all_slots as $slot) {
                    if (!empty($slot['available'])) {
                        $has_available_slot = true;
                        break;
                    }
                }
                
                // If no time slots are available (due to master lock or other reasons), mark as unavailable
                if (!$has_available_slot) {
                    $is_available = false;
                } else {
                    // If time filters are provided, check specific time slot
                    if (!empty($filters['start_time']) && !empty($filters['end_time'])) {
                        $is_available = $room_manager->is_room_available($room->id, $filters['date'], $filters['start_time'], $filters['end_time']);
                    }
                }
            }
            
            // Check if we have duration filter
            $duration = isset($filters['duration']) ? intval($filters['duration']) : 0;
            
            if ($duration > 0) {
                // Show specific price for selected duration
                $specific_price = $room_manager->get_room_price_for_duration($room, $duration);
                $formatted_price = $specific_price > 0 ? hrb_format_amount($specific_price) . ' ' . sprintf(__('for %d hours', 'hourly-room-booking'), $duration) : 'N/A';
                $price_for_sorting = $specific_price;
            } else {
                // Show price range (default)
                $price_range = $room_manager->get_room_price_range($room);
                $formatted_price = $price_range['formatted'];
                $price_for_sorting = $price_range['min'];
            }

            $results[] = array(
                'id' => $room->id,
                'name' => $room->name,
                'description' => $room->description,
                'capacity' => $room->capacity,
                'price' => $price_for_sorting, // Use for sorting
                'formatted_price' => $formatted_price, // Use formatted price
                'amenities' => $amenities,
                'images' => $room_manager->get_room_images($room->id),
                'external_link' => $room->external_link ?? '',
                'is_available' => $is_available // Add availability status
            );
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Get room price for specific duration
     */
    public function get_room_price_for_duration() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
        }
        
        $room_id = intval($_POST['room_id']);
        $duration = intval($_POST['duration']);
        
        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($room_id);
        
        if (!$room) {
            wp_send_json_error(__('Room not found', 'hourly-room-booking'));
        }
        
        $specific_price = $room_manager->get_room_price_for_duration($room, $duration);
        
        if ($specific_price > 0) {
            $formatted_price = hrb_format_amount($specific_price);
            $pricing_label = get_option('hrb_pricing_label', '');
            
            if (!empty($pricing_label)) {
                $formatted_price = $pricing_label . ' ' . $formatted_price;
            }
            
            $formatted_price_with_duration = $formatted_price . ' ' . sprintf(__('for %d hours', 'hourly-room-booking'), $duration);
            
            wp_send_json_success(array(
                'formatted_price' => $formatted_price_with_duration
            ));
        } else {
            wp_send_json_success(array(
                'formatted_price' => 'N/A'
            ));
        }
    }
    
    /**
     * Get room price range
     */
    public function get_room_price_range() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
        }
        
        $room_id = intval($_POST['room_id']);
        
        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($room_id);
        
        if (!$room) {
            wp_send_json_error(__('Room not found', 'hourly-room-booking'));
        }
        
        $price_range = $room_manager->get_room_price_range($room);
        
        wp_send_json_success(array(
            'formatted_price' => $price_range['formatted']
        ));
    }
    
    /**
     * Load more rooms for pagination
     */
    public function load_more_rooms() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
        }

        $page = intval($_POST['page']) ?: 1;
        $per_page = intval($_POST['per_page']) ?: 6;
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
        $columns = isset($_POST['columns']) ? intval($_POST['columns']) : 3;
        $show_price = isset($_POST['show_price']) ? $_POST['show_price'] === 'true' : true;
        $show_capacity = isset($_POST['show_capacity']) ? $_POST['show_capacity'] === 'true' : true;
        $show_amenities = isset($_POST['show_amenities']) ? $_POST['show_amenities'] === 'true' : true;
        $show_view_button = isset($_POST['show_view_button']) ? $_POST['show_view_button'] === 'true' : true;
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

                </div>

                    <div class="hrb-room-content">
                        <h3 class="hrb-room-title"><?php echo esc_html($room->name); ?></h3>

                        <?php if ($show_price): ?>
                            <div class="hrb-room-price" data-room-id="<?php echo $room->id; ?>">
                                <?php 
                                $room_manager = HRB_Room_Manager::getInstance();
                                
                                if ($duration > 0) {
                                    // Show specific price for selected duration
                                    $specific_price = $room_manager->get_room_price_for_duration($room, $duration);
                                    
                                    if ($specific_price > 0) {
                                        $formatted_price = hrb_format_amount($specific_price);
                                        $pricing_label = get_option('hrb_pricing_label', '');
                                        
                                        if (!empty($pricing_label)) {
                                            $formatted_price = $pricing_label . ' ' . $formatted_price;
                                        }
                                        
                                        // Include duration in the price text (same as regular search results)
                                        $formatted_price_with_duration = $formatted_price . ' ' . sprintf(__('for %d hours', 'hourly-room-booking'), $duration);
                                        echo '<span class="hrb-price">' . $formatted_price_with_duration . '</span>';
                                    } else {
                                        echo '<span class="hrb-price">N/A</span>';
                                    }
                                } else {
                                    // Show price range (default)
                                    $price_range = $room_manager->get_room_price_range($room);
                                    echo '<span class="hrb-price">' . $price_range['formatted'] . '</span>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>

                    <?php if (!empty($room->description)): ?>
                        <p class="hrb-room-description">
                            <?php echo esc_html(wp_trim_words($room->description, 20)); ?>
                        </p>
                    <?php endif; ?>

                        <div class="hrb-room-details">
                            <?php if ($show_capacity): ?>
                                <div class="hrb-room-detail">
                                    <i class="hrb-icon-people"></i>
                                    <span><?php printf(__('Up to %d people', 'hourly-room-booking'), $room->capacity); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($show_amenities): ?>
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
                            <?php endif; ?>
                        </div>

                    <div class="hrb-room-actions">
                        <?php if ($show_view_button): ?>
                            <a href="#" class="hrb-btn hrb-btn-primary hrb-view-room" data-room-id="<?php echo $room->id; ?>" data-external-link="<?php echo esc_attr($room->external_link ?? ''); ?>">
                                <?php _e('View Details', 'hourly-room-booking'); ?>
                            </a>
                        <?php endif; ?>
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_calendar_nonce')) {
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
        // Start output buffering to prevent any output before JSON response
        ob_start();
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            ob_end_clean();
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        // Check if this is an anonymous booking
        $is_anonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] === '1';

        // Validate and sanitize input using the new validator
        $validator = HRB_Input_Validator::getInstance();
        
        $booking_data = $validator->validate_booking_data($_POST);
        if (is_wp_error($booking_data)) {
            ob_end_clean();
            wp_send_json_error($booking_data->get_error_message());
        }
        
        // Add anonymous flag to booking data
        $booking_data['is_anonymous'] = $is_anonymous;
        
        // For anonymous bookings, skip customer data validation
        if ($is_anonymous) {
            // Validate that first_name is provided for anonymous bookings (same as admin)
            $provided_name = sanitize_text_field($_POST['first_name'] ?? '');
            
            if (empty($provided_name)) {
                ob_end_clean();
                wp_send_json_error(__('Name is required for anonymous bookings.', 'hourly-room-booking'));
            }
            
            // Create minimal customer data for anonymous bookings
            $customer_data = array(
                'first_name' => 'Anonymous',
                'last_name' => 'User',
                'email' => 'anonymous@example.com', // Placeholder email
                'phone' => '0000000000', // Placeholder phone
                'company' => '',
                'address' => '',
                'city' => '',
                'postal_code' => '',
                'country' => 'DE'
            );
            
            // Store the actual booking name in booking data
            $booking_data['first_name'] = $provided_name;
            $booking_data['last_name'] = sanitize_text_field($_POST['last_name'] ?? '');
        } else {
            $customer_data = $validator->validate_customer_data($_POST);
            if (is_wp_error($customer_data)) {
                ob_end_clean();
                wp_send_json_error($customer_data->get_error_message());
            }
            
            // Store customer name in booking data for regular bookings too
            $booking_data['first_name'] = $customer_data['first_name'];
            $booking_data['last_name'] = $customer_data['last_name'];
        }
        
        // OTP verification has been removed - skip this check
        
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Create or get customer - improved logic for logged-in users
            $customer = null;
            $current_user_id = is_user_logged_in() ? get_current_user_id() : null;

            if ($is_anonymous) {
                // For anonymous bookings, use the single anonymous customer
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE email = %s",
                    'anonymous@example.com'
                ));
            } else {
                // ALWAYS check by email FIRST (from form input) to ensure we use the correct customer record
                // This ensures that if user changes email in form, we find/update the correct customer
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE email = %s",
                    $customer_data['email']
                ));
                
                // If no customer found by email, and user is logged in, check by wp_user_id as fallback
                // This handles cases where user changes email but we still want to link to their account
                if (!$customer && $current_user_id) {
                    $customer = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE wp_user_id = %d",
                        $current_user_id
                    ));
                }
            }

            if ($customer) {
                $customer_id = $customer->id;

                // IMPORTANT: Always use form input data (customer_data from $_POST), NOT the cached customer record
                // This ensures the booking uses the details entered in the form, not old cached data
                // customer_data from validator contains: first_name, last_name, email, phone, company (5 fields)
                $update_data = array(
                    'first_name' => $customer_data['first_name'],
                    'last_name' => $customer_data['last_name'],
                    'email' => $customer_data['email'],
                    'phone' => $customer_data['phone'],
                    'company' => $customer_data['company']
                );

                // If this is a logged-in user and the customer record doesn't have wp_user_id, add it
                if ($current_user_id && empty($customer->wp_user_id)) {
                    $update_data['wp_user_id'] = $current_user_id;
                }

                // Build format array to match the fields we're updating
                // Only update the fields from form input: first_name, last_name, email, phone, company
                $update_format = array('%s', '%s', '%s', '%s', '%s'); // 5 string fields
                if (isset($update_data['wp_user_id'])) {
                    $update_format[] = '%d'; // Add wp_user_id format if present
                }

                // Update customer info with form input data (this overwrites old cached data)
                $update_result = $wpdb->update(
                    $wpdb->prefix . 'hrb_customers',
                    $update_data,
                    array('id' => $customer_id),
                    $update_format,
                    array('%d')
                );
                
                
            } else {
                // Create new customer
                if ($is_anonymous) {
                    // Create the single anonymous customer
                    $new_customer_data = $customer_data;
                } else {
                    $new_customer_data = array_merge($customer_data, array('country' => 'DE'));

                    // Add wp_user_id for logged-in users
                    if ($current_user_id) {
                        $new_customer_data['wp_user_id'] = $current_user_id;
                    }
                }

                $insert_format = array('%s', '%s', '%s', '%s', '%s', '%s');
                if ($current_user_id && !$is_anonymous) {
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

     
            // Get the created booking
            $booking = $booking_manager->get_booking($booking_id);

            // Clean output buffer before sending JSON response
            ob_end_clean();
            
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
            ob_end_clean();
            wp_send_json_error('Booking failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Send OTP verification
     */
    public function send_otp() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $type = sanitize_text_field($_POST['type']); // email or sms
        
        if (empty($email)) {
            wp_send_json_error(__('Email is required', 'hourly-room-booking'));
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_booking_form_nonce')) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_room_details_nonce')) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }

        $room_id = intval($_POST['room_id']);
        $date = sanitize_text_field($_POST['date']);
        $duration = floatval($_POST['duration']);
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        // Check if is_admin parameter is passed (can be 'true' string or boolean true)
        $is_admin_param = isset($_POST['is_admin']) && ($_POST['is_admin'] === 'true' || $_POST['is_admin'] === true || $_POST['is_admin'] === '1' || $_POST['is_admin'] === 1);

        if (empty($room_id) || empty($date) || empty($duration)) {
            wp_send_json_error(__('Missing required parameters', 'hourly-room-booking'));
        }

        // Validate duration (2-12 hours)
        if ($duration < 2 || $duration > 12) {
            wp_send_json_error(__('Invalid duration. Must be between 2-12 hours', 'hourly-room-booking'));
        }

        $room_manager = HRB_Room_Manager::getInstance();
        $available_slots = $this->generate_available_time_slots($room_id, $date, $duration, $booking_id, $is_admin_param);

        wp_send_json_success(array(
            'slots' => $available_slots,
            'message' => sprintf(__('%d time slots available', 'hourly-room-booking'), count($available_slots))
        ));
    }

    /**
     * Generate available time slots for a given date and duration
     */
    public function generate_available_time_slots($room_id, $date, $duration, $booking_id = 0, $is_admin_param = false) {
        $available_slots = array();
        global $wpdb;

        // Query room locks for the selected room and date
        $room_locks = $wpdb->get_results($wpdb->prepare(
            "SELECT start_datetime, end_datetime 
             FROM {$wpdb->prefix}hrb_room_locks 
             WHERE room_id = %d AND start_datetime <= %s AND end_datetime >= %s",
            $room_id, $date . ' 23:59:59', $date . ' 00:00:00'
        ));

        // Query master locks for the date
        $master_locks = $wpdb->get_results($wpdb->prepare(
            "SELECT start_datetime, end_datetime 
             FROM {$wpdb->prefix}hrb_master_locks 
             WHERE start_datetime <= %s AND end_datetime >= %s",
            $date . ' 23:59:59', $date . ' 00:00:00'
        ));


        // Get booking time range from settings
        $booking_start_time = get_option('hrb_booking_start_time', '08:00');
        $booking_end_time = get_option('hrb_booking_end_time', '20:00');
        
        
        // Parse start and end times
        $start_hour = intval(substr($booking_start_time, 0, 2));
        $end_hour = intval(substr($booking_end_time, 0, 2));

        // Parse end time properly to handle minutes
        $end_hour = intval(substr($booking_end_time, 0, 2));
        $end_minute = intval(substr($booking_end_time, 3, 2));
        $booking_end_minutes = $end_hour * 60 + $end_minute;
        
        // Get plugin timezone setting
        $plugin_timezone = get_option('hrb_timezone', 'Europe/Berlin');
        
        // Set timezone for calculations
        $original_timezone = date_default_timezone_get();
        date_default_timezone_set($plugin_timezone);
        
        // Check if the selected date is today (using plugin timezone)
        $today = date('Y-m-d');
        $current_time = date('H:i');
        $is_today = ($date === $today);
        
        
        
        for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
            // Check both :00 and :30 minute slots
            foreach (['00', '30'] as $minute) {
                $start_minutes = ($hour * 60) + intval($minute);
                // If start is at or beyond end, skip
                if ($start_minutes >= $booking_end_minutes) {
                    continue;
                }

                $start_time = sprintf('%02d:%s:00', $hour, $minute);

                // Check if this time slot has already passed (for today only)
                $slot_time = sprintf('%02d:%s', $hour, $minute);
                $is_past_time = $is_today && ($slot_time < $current_time);
                
                
                $slot_end_minutes = $start_minutes + ($duration * 60);

                // Allow crossing midnight when booking end time is 24:00
                $allow_cross_midnight = ($booking_end_minutes === 1440);

                // Check if slot ends after the configured end time (only block if not allowed to cross)
                if (!$allow_cross_midnight && $slot_end_minutes > $booking_end_minutes) {
                    continue;
                }

                // Build end time string (support crossing midnight)
                if ($slot_end_minutes >= 1440) {
                    $end_minutes_overflow = $slot_end_minutes - 1440;
                    $end_hour_slot = intdiv($end_minutes_overflow, 60);
                    $end_minute_slot = $end_minutes_overflow % 60;
                    $end_time = sprintf('%02d:%02d:00', $end_hour_slot, $end_minute_slot);
                    $end_time_display = sprintf('%02d:%02d', $end_hour_slot, $end_minute_slot);
                } else {
                    $end_hour_slot = intdiv($slot_end_minutes, 60);
                    $end_minute_slot = $slot_end_minutes % 60;
                    $end_time = sprintf('%02d:%02d:00', $end_hour_slot, $end_minute_slot);
                    $end_time_display = sprintf('%02d:%02d', $end_hour_slot, $end_minute_slot);
                }

                $start_time_display = sprintf('%02d:%02d', intdiv($start_minutes, 60), $start_minutes % 60);

                // Check if this slot is locked
                $is_locked = false;
                $lock_type = null; // 'master' or 'room'

                // Check master locks with proper datetime overlap
                foreach ($master_locks as $lock) {
                    $slot_start_datetime = $date . ' ' . $start_time;
                    $slot_end_datetime = $date . ' ' . $end_time;
                    
                    // Simple datetime overlap check
                    if ($slot_start_datetime < $lock->end_datetime && $slot_end_datetime > $lock->start_datetime) {
                        $is_locked = true;
                        $lock_type = 'master';
                        break;
                    }
                }
                
                // Check room-specific locks (only if not already locked by master)
                if (!$is_locked) {
                    foreach ($room_locks as $lock) {
                        $slot_start_datetime = $date . ' ' . $start_time;
                        $slot_end_datetime = $date . ' ' . $end_time;
                        
                        // Simple datetime overlap check
                        if ($slot_start_datetime < $lock->end_datetime && $slot_end_datetime > $lock->start_datetime) {
                            $is_locked = true;
                            $lock_type = 'room';
                            break;
                        }
                    }
                }

                // Check if request is from admin (admin can book locked rooms)
                // Must have both: is_admin parameter AND admin capabilities (security check)
                $has_admin_cap = current_user_can('hrb_manage_bookings') || current_user_can('manage_options');
                $is_admin_request = $is_admin_param && $has_admin_cap;
                
                // Check if this slot is available - use cooldown-aware checking
                // Exclude current booking from conflict check if editing
                $exclude_booking_id = ($booking_id > 0) ? $booking_id : 0;
                $is_available = $this->is_slot_available_with_cooldown_excluding_booking($room_id, $date, $start_time, $end_time, $exclude_booking_id);
                
                // If slot is locked, mark as unavailable for frontend, but allow admin to see and book it
                // For admin, locked slots are still available (they can override locks)
                // Frontend users cannot bypass locks even if they try to pass is_admin parameter
                if ($is_locked && !$is_admin_request) {
                    $is_available = false;
                }
                // Note: For admin, even if locked, $is_available remains true (if no real conflicts)
                
                // If it's a past time slot, mark it as unavailable
                if ($is_past_time) {
                    $is_available = false;
                }
                
                // Check if this is the current booking's time slot
                $is_current_booking_slot = false;
                if ($booking_id > 0) {
                    global $wpdb;
                    $current_booking = $wpdb->get_row($wpdb->prepare(
                        "SELECT start_time, end_time FROM {$wpdb->prefix}hrb_bookings WHERE id = %d",
                        $booking_id
                    ));
                    
                    if ($current_booking && 
                        $current_booking->start_time === $start_time && 
                        $current_booking->end_time === $end_time) {
                        $is_current_booking_slot = true;
                    }
                }
                
                $slot_data = array(
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'display_start' => $start_time_display,
                    'display_end' => $end_time_display,
                    'label' => sprintf('%s - %s', $start_time_display, $end_time_display),
                    'available' => $is_available,
                    'is_current_booking' => $is_current_booking_slot,
                    'is_locked' => $is_locked,
                    'lock_type' => $lock_type // 'master' or 'room' or null
                );
                
                // Add all slots (both available and unavailable)
                $available_slots[] = $slot_data;
            }
        }

        // Restore original timezone
        date_default_timezone_set($original_timezone);
        
        return $available_slots;
    }

    /**
     * Check if a time slot is available including cooldown periods
     */
    private function is_slot_available_with_cooldown($room_id, $date, $start_time, $end_time, $exclude_booking_id = 0) {
        global $wpdb;

        // Get cooldown minutes from settings
        $cooldown_minutes = intval(get_option('hrb_cooldown_minutes', 30));

        // Build date range to fetch potentially overlapping bookings (previous, current, and next day for cross-midnight)
        $date_obj = new DateTime($date);
        $prev_day = clone $date_obj;
        $prev_day->modify('-1 day');
        $next_day = clone $date_obj;
        $next_day->modify('+1 day');
        $date_str = $date_obj->format('Y-m-d');
        $prev_day_str = $prev_day->format('Y-m-d');
        $next_day_str = $next_day->format('Y-m-d');

        // Fetch bookings for previous, current, and next day (to account for cross-midnight bookings)
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT id, booking_date, start_time, end_time 
             FROM {$wpdb->prefix}hrb_bookings
             WHERE room_id = %d
             AND booking_date IN (%s, %s, %s)
             AND status NOT IN ('cancelled', 'no_show')",
            $room_id,
            $prev_day_str,
            $date_str,
            $next_day_str
        ));

        // Prepare slot start/end as datetimes
        $slot_start_dt = new DateTime("{$date_str} {$start_time}");
        $slot_end_dt = new DateTime("{$date_str} {$end_time}");
        if ($slot_end_dt <= $slot_start_dt) {
            // crosses midnight
            $slot_end_dt->modify('+1 day');
        }

        foreach ($bookings as $booking) {
            if ($exclude_booking_id > 0 && intval($booking->id) === intval($exclude_booking_id)) {
                continue;
            }

            $booking_start_dt = new DateTime("{$booking->booking_date} {$booking->start_time}");
            $booking_end_dt = new DateTime("{$booking->booking_date} {$booking->end_time}");
            if ($booking_end_dt <= $booking_start_dt) {
                // booking crosses midnight
                $booking_end_dt->modify('+1 day');
            }

            // Direct overlap: existing_start < new_end AND existing_end > new_start
            if ($booking_start_dt < $slot_end_dt && $booking_end_dt > $slot_start_dt) {
                return false;
            }

            // Cooldown after existing booking
            $booking_end_with_cooldown = clone $booking_end_dt;
            $booking_end_with_cooldown->modify("+{$cooldown_minutes} minutes");
            if ($slot_start_dt >= $booking_end_dt && $slot_start_dt < $booking_end_with_cooldown) {
                return false;
            }

            // Cooldown before existing booking
            $booking_start_with_cooldown = clone $booking_start_dt;
            $booking_start_with_cooldown->modify("-{$cooldown_minutes} minutes");
            if ($slot_end_dt > $booking_start_with_cooldown && $slot_end_dt <= $booking_start_dt) {
                return false;
            }
        }

        return true;
    }
    
    /**
     * wrapper function for backward compatibility
     */
    private function is_slot_available_with_cooldown_excluding_booking($room_id, $date, $start_time, $end_time, $exclude_booking_id = 0) {
        return $this->is_slot_available_with_cooldown($room_id, $date, $start_time, $end_time, $exclude_booking_id);
    }

    /**
     * Get locked dates for a room
     */
    public function get_locked_dates() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $room_id = intval($_POST['room_id']);
        global $wpdb;
        
        // Get room locks
        $room_locks = $wpdb->get_results($wpdb->prepare(
            "SELECT start_datetime, end_datetime 
             FROM {$wpdb->prefix}hrb_room_locks 
             WHERE room_id = %d",
            $room_id
        ));
        
        // Get master locks
        $master_locks = $wpdb->get_results(
            "SELECT start_datetime, end_datetime 
             FROM {$wpdb->prefix}hrb_master_locks"
        );
        
        // Format response
        $disabled_dates = array();
        
        // Process room locks
        foreach ($room_locks as $lock) {
            $start = strtotime($lock->start_datetime);
            $end = strtotime($lock->end_datetime);
            
            // Extract date and time components
            $start_date = date('Y-m-d', $start);
            $end_date = date('Y-m-d', $end);
            $start_time = date('H:i:s', $start);
            $end_time = date('H:i:s', $end);
            
            // If all-day lock, add entire date range
            if ($start_time === '00:00:00' && $end_time === '23:59:59') {
                for ($date = $start; $date <= $end; $date += 86400) {
                    $disabled_dates[] = date('Y-m-d', $date);
                }
            } else {
                // Partial day lock - only disable if start_time is 00:00 and end_time is 23:59
                // Otherwise, date remains selectable but time slots will be unavailable
                if ($start_time === '00:00:00' && $end_time === '23:59:59') {
                    for ($date = $start; $date <= $end; $date += 86400) {
                        $disabled_dates[] = date('Y-m-d', $date);
                    }
                }
            }
        }
        
        // Process master locks (always disable entire dates)
        foreach ($master_locks as $lock) {
            $start = strtotime($lock->start_datetime);
            $end = strtotime($lock->end_datetime);
            
            for ($date = $start; $date <= $end; $date += 86400) {
                $disabled_dates[] = date('Y-m-d', $date);
            }
        }
        
        wp_send_json_success(array('disabled_dates' => array_unique($disabled_dates)));
    }

    /**
     * Send verification code
     */
    public function send_verification_code() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }

        $booking_date = sanitize_text_field($_POST['booking_date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

        if (empty($booking_date) || empty($start_time) || empty($end_time)) {
            wp_send_json_error(__('Missing required parameters', 'hourly-room-booking'));
        }

        // Check if this is an admin request (admin can book inactive extras and bypass locks)
        $is_admin_request = current_user_can('manage_options') || current_user_can('hrb_manage_bookings');
        $include_inactive = $is_admin_request;
        $allow_locked = $is_admin_request;

        // Use the new stock management system
        $stock_manager = HRB_Extra_Stock_Manager::getInstance();
        $available_extras = $stock_manager->get_available_extras($booking_date, $start_time, $end_time, $include_inactive, $booking_id, $allow_locked);

        // If we're editing a booking, mark which extras are already selected
        if ($booking_id > 0) {
            $extras_manager = HRB_Extras::getInstance();
            $selected_extras = $extras_manager->get_booking_extras($booking_id);
            $selected_extra_ids = array_column($selected_extras, 'id');
            
            // Add is_selected flag to each extra
            foreach ($available_extras as &$extra) {
                $extra['is_selected'] = in_array($extra['id'], $selected_extra_ids);
            }
        }

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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('hrb_manage_bookings')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }
        
        // Get settings from POST data
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        
        if (empty($settings)) {
            wp_send_json_error(__('No settings provided', 'hourly-room-booking'));
            return;
        }
        
        // Use the settings class to save settings
        $settings_manager = HRB_Settings::getInstance();
        
        foreach ($settings as $key => $value) {
            $settings_manager->set($key, $value);
        }
        
        // Clear settings cache to ensure fresh data on next load
        $settings_manager->clear_cache();
        
        wp_send_json_success(__('Settings saved successfully', 'hourly-room-booking'));
    }
}