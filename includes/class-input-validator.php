<?php
/**
 * Input Validator Class
 * Provides comprehensive input validation and sanitization
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Input_Validator {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Validate and sanitize booking data
     */
    public function validate_booking_data(array $data): array|WP_Error {
        $errors = new WP_Error();
        $sanitized = [];
        
        // Room ID validation
        if (empty($data['room_id'])) {
            $errors->add('room_id_required', __('Room ID is required', 'hourly-room-booking'));
        } else {
            $sanitized['room_id'] = absint($data['room_id']);
            if ($sanitized['room_id'] <= 0) {
                $errors->add('room_id_invalid', __('Invalid room ID', 'hourly-room-booking'));
            }
        }
        
        // Booking date validation
        if (empty($data['booking_date'])) {
            $errors->add('booking_date_required', __('Booking date is required', 'hourly-room-booking'));
        } else {
            $sanitized['booking_date'] = sanitize_text_field($data['booking_date']);
            if (!$this->is_valid_date($sanitized['booking_date'])) {
                $errors->add('booking_date_invalid', __('Invalid booking date format', 'hourly-room-booking'));
            }
        }
        
        // Start time validation
        if (empty($data['start_time'])) {
            $errors->add('start_time_required', __('Start time is required', 'hourly-room-booking'));
        } else {
            $sanitized['start_time'] = sanitize_text_field($data['start_time']);
            if (!$this->is_valid_time($sanitized['start_time'])) {
                $errors->add('start_time_invalid', __('Invalid start time format', 'hourly-room-booking'));
            } else {
                // Normalize time format to H:i:s
                $sanitized['start_time'] = $this->normalize_time($sanitized['start_time']);
            }
        }
        
        // End time validation
        if (empty($data['end_time'])) {
            $errors->add('end_time_required', __('End time is required', 'hourly-room-booking'));
        } else {
            $sanitized['end_time'] = sanitize_text_field($data['end_time']);
            if (!$this->is_valid_time($sanitized['end_time'])) {
                $errors->add('end_time_invalid', __('Invalid end time format', 'hourly-room-booking'));
            } else {
                // Normalize time format to H:i:s
                $sanitized['end_time'] = $this->normalize_time($sanitized['end_time']);
            }
        }
        
        // Validate time logic
        if (!empty($sanitized['start_time']) && !empty($sanitized['end_time'])) {
            if (strtotime($sanitized['start_time']) >= strtotime($sanitized['end_time'])) {
                $errors->add('time_logic_invalid', __('End time must be after start time', 'hourly-room-booking'));
            }
        }
        
        // Extra people validation
        $sanitized['extra_people'] = isset($data['extra_people']) ? absint($data['extra_people']) : 0;
        if ($sanitized['extra_people'] > 10) {
            $errors->add('extra_people_limit', __('Maximum 10 extra people allowed', 'hourly-room-booking'));
        }
        
        // Payment method validation
        if (empty($data['payment_method'])) {
            $errors->add('payment_method_required', __('Payment method is required', 'hourly-room-booking'));
        } else {
            $sanitized['payment_method'] = sanitize_text_field($data['payment_method']);
            $allowed_methods = ['paypal', 'onsite', 'cash', 'card'];
            if (!in_array($sanitized['payment_method'], $allowed_methods)) {
                $errors->add('payment_method_invalid', __('Invalid payment method', 'hourly-room-booking'));
            }
        }
        
        // Special requests sanitization
        $sanitized['special_requests'] = isset($data['special_requests']) ? sanitize_textarea_field($data['special_requests']) : '';
        
        // Extras validation
        $sanitized['extras'] = [];
        if (isset($data['extras']) && is_array($data['extras'])) {
            foreach ($data['extras'] as $extra) {
                // Handle both formats: array of IDs or array of objects
                if (is_array($extra) && isset($extra['id']) && isset($extra['price'])) {
                    // Object format with id, price, quantity
                    $sanitized['extras'][] = [
                        'id' => absint($extra['id']),
                        'price' => floatval($extra['price']),
                        'quantity' => isset($extra['quantity']) ? absint($extra['quantity']) : 1
                    ];
                } elseif (is_numeric($extra)) {
                    // Simple ID format - just the extra ID
                    $sanitized['extras'][] = absint($extra);
                }
            }
        }
        
        if (!empty($errors->get_error_messages())) {
            return $errors;
        }
        
        return $sanitized;
    }
    
    /**
     * Validate and sanitize customer data
     */
    public function validate_customer_data(array $data): array|WP_Error {
        $errors = new WP_Error();
        $sanitized = [];
        
        // First name validation
        if (empty($data['first_name'])) {
            $errors->add('first_name_required', __('First name is required', 'hourly-room-booking'));
        } else {
            $sanitized['first_name'] = sanitize_text_field($data['first_name']);
            if (strlen($sanitized['first_name']) < 2) {
                $errors->add('first_name_too_short', __('First name must be at least 2 characters', 'hourly-room-booking'));
            }
            if (strlen($sanitized['first_name']) > 50) {
                $errors->add('first_name_too_long', __('First name must be less than 50 characters', 'hourly-room-booking'));
            }
        }
        
        // Last name validation
        if (empty($data['last_name'])) {
            $errors->add('last_name_required', __('Last name is required', 'hourly-room-booking'));
        } else {
            $sanitized['last_name'] = sanitize_text_field($data['last_name']);
            if (strlen($sanitized['last_name']) < 2) {
                $errors->add('last_name_too_short', __('Last name must be at least 2 characters', 'hourly-room-booking'));
            }
            if (strlen($sanitized['last_name']) > 50) {
                $errors->add('last_name_too_long', __('Last name must be less than 50 characters', 'hourly-room-booking'));
            }
        }
        
        // Email validation
        if (empty($data['email'])) {
            $errors->add('email_required', __('Email is required', 'hourly-room-booking'));
        } else {
            $sanitized['email'] = sanitize_email($data['email']);
            if (!is_email($sanitized['email'])) {
                $errors->add('email_invalid', __('Invalid email address', 'hourly-room-booking'));
            }
        }
        
        // Phone validation
        if (empty($data['phone'])) {
            $errors->add('phone_required', __('Phone number is required', 'hourly-room-booking'));
        } else {
            $sanitized['phone'] = sanitize_text_field($data['phone']);
            if (!$this->is_valid_phone($sanitized['phone'])) {
                $errors->add('phone_invalid', __('Invalid phone number format', 'hourly-room-booking'));
            }
        }
        
        // Company validation (optional)
        $sanitized['company'] = isset($data['company']) ? sanitize_text_field($data['company']) : '';
        if (strlen($sanitized['company']) > 100) {
            $errors->add('company_too_long', __('Company name must be less than 100 characters', 'hourly-room-booking'));
        }
        
        if (!empty($errors->get_error_messages())) {
            return $errors;
        }
        
        return $sanitized;
    }
    
    /**
     * Validate room data
     */
    public function validate_room_data(array $data): array|WP_Error {
        $errors = new WP_Error();
        $sanitized = [];
        
        // Name validation
        if (empty($data['name'])) {
            $errors->add('name_required', __('Room name is required', 'hourly-room-booking'));
        } else {
            $sanitized['name'] = sanitize_text_field($data['name']);
            if (strlen($sanitized['name']) < 3) {
                $errors->add('name_too_short', __('Room name must be at least 3 characters', 'hourly-room-booking'));
            }
            if (strlen($sanitized['name']) > 100) {
                $errors->add('name_too_long', __('Room name must be less than 100 characters', 'hourly-room-booking'));
            }
        }
        
        // Description validation
        $sanitized['description'] = isset($data['description']) ? sanitize_textarea_field($data['description']) : '';
        if (strlen($sanitized['description']) > 500) {
            $errors->add('description_too_long', __('Description must be less than 500 characters', 'hourly-room-booking'));
        }
        
        // Capacity validation
        if (empty($data['capacity'])) {
            $errors->add('capacity_required', __('Room capacity is required', 'hourly-room-booking'));
        } else {
            $sanitized['capacity'] = absint($data['capacity']);
            if ($sanitized['capacity'] < 1 || $sanitized['capacity'] > 100) {
                $errors->add('capacity_invalid', __('Room capacity must be between 1 and 100', 'hourly-room-booking'));
            }
        }
        
        // Price validation (removed - using duration-based pricing)
        // hourly_price field is no longer used in room forms
        
        
        // 2 Hours price validation
        $sanitized['price_2_hours'] = isset($data['price_2_hours']) ? floatval($data['price_2_hours']) : 0;
        if ($sanitized['price_2_hours'] < 0 || $sanitized['price_2_hours'] > 1000) {
            $errors->add('price_2_hours_invalid', __('2 Hours price must be between 0 and 1000', 'hourly-room-booking'));
        }
        
        // 3 Hours price validation
        $sanitized['price_3_hours'] = isset($data['price_3_hours']) ? floatval($data['price_3_hours']) : 0;
        if ($sanitized['price_3_hours'] < 0 || $sanitized['price_3_hours'] > 1000) {
            $errors->add('price_3_hours_invalid', __('3 Hours price must be between 0 and 1000', 'hourly-room-booking'));
        }
        
        // 4 Hours price validation
        $sanitized['price_4_hours'] = isset($data['price_4_hours']) ? floatval($data['price_4_hours']) : 0;
        if ($sanitized['price_4_hours'] < 0 || $sanitized['price_4_hours'] > 1000) {
            $errors->add('price_4_hours_invalid', __('4 Hours price must be between 0 and 1000', 'hourly-room-booking'));
        }
        
        // Extra hour price validation
        $sanitized['price_extra_hour'] = isset($data['price_extra_hour']) ? floatval($data['price_extra_hour']) : 0;
        if ($sanitized['price_extra_hour'] < 0 || $sanitized['price_extra_hour'] > 1000) {
            $errors->add('price_extra_hour_invalid', __('Extra hour price must be between 0 and 1000', 'hourly-room-booking'));
        }
        
        // Amenities validation
        $sanitized['amenities'] = [];
        if (isset($data['amenities']) && is_array($data['amenities'])) {
            foreach ($data['amenities'] as $amenity) {
                $amenity = sanitize_text_field($amenity);
                if (!empty($amenity) && strlen($amenity) <= 50) {
                    $sanitized['amenities'][] = $amenity;
                }
            }
        }
        
        // Images validation
        $sanitized['images'] = [];
        if (isset($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $image) {
                $image = esc_url_raw($image);
                if (!empty($image) && $this->is_valid_image_url($image)) {
                    $sanitized['images'][] = $image;
                }
            }
        }
        
        if (!empty($errors->get_error_messages())) {
            return $errors;
        }
        
        return $sanitized;
    }
    
    /**
     * Validate date format (Y-m-d)
     */
    private function is_valid_date(string $date): bool {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Validate time format (H:i or H:i:s)
     */
    private function is_valid_time(string $time): bool {
        // Try H:i format first
        $t1 = DateTime::createFromFormat('H:i', $time);
        if ($t1 && $t1->format('H:i') === $time) {
            return true;
        }
        
        // Try H:i:s format
        $t2 = DateTime::createFromFormat('H:i:s', $time);
        if ($t2 && $t2->format('H:i:s') === $time) {
            return true;
        }
        
        // Try G:i format (single digit hour)
        $t3 = DateTime::createFromFormat('G:i', $time);
        if ($t3 && $t3->format('G:i') === $time) {
            return true;
        }
        
        // Try G:i:s format (single digit hour with seconds)
        $t4 = DateTime::createFromFormat('G:i:s', $time);
        if ($t4 && $t4->format('G:i:s') === $time) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Normalize time format to H:i:s
     */
    private function normalize_time(string $time): string {
        // Try H:i format first
        $t1 = DateTime::createFromFormat('H:i', $time);
        if ($t1) {
            return $t1->format('H:i:s');
        }
        
        // Try H:i:s format
        $t2 = DateTime::createFromFormat('H:i:s', $time);
        if ($t2) {
            return $t2->format('H:i:s');
        }
        
        // Try G:i format (single digit hour)
        $t3 = DateTime::createFromFormat('G:i', $time);
        if ($t3) {
            return $t3->format('H:i:s');
        }
        
        // Try G:i:s format (single digit hour with seconds)
        $t4 = DateTime::createFromFormat('G:i:s', $time);
        if ($t4) {
            return $t4->format('H:i:s');
        }
        
        // Fallback to original time
        return $time;
    }
    
    /**
     * Validate phone number (basic validation)
     */
    private function is_valid_phone(string $phone): bool {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        // Check if it's between 7 and 15 digits (international standard)
        return strlen($cleaned) >= 7 && strlen($cleaned) <= 15;
    }
    
    /**
     * Validate image URL
     */
    private function is_valid_image_url(string $url): bool {
        $parsed = wp_parse_url($url);
        if (!$parsed || !isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
            return false;
        }
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $path_info = pathinfo($parsed['path']);
        $extension = strtolower($path_info['extension'] ?? '');
        
        return in_array($extension, $allowed_extensions);
    }
    
    /**
     * Sanitize search query
     */
    public function sanitize_search_query(string $query): string {
        $sanitized = sanitize_text_field($query);
        // Remove potentially dangerous characters
        $sanitized = preg_replace('/[<>"\']/', '', $sanitized);
        return trim($sanitized);
    }
    
    /**
     * Validate admin action
     */
    public function validate_admin_action(string $action): bool {
        $allowed_actions = [
            'create_room', 'update_room', 'delete_room', 'toggle_room_status',
            'create_booking', 'update_booking', 'cancel_booking', 'confirm_booking',
            'create_customer', 'update_customer', 'delete_customer',
            'create_extra', 'update_extra', 'delete_extra',
            'mark_payment_completed', 'cancel_payment', 'process_refund'
        ];
        
        return in_array($action, $allowed_actions);
    }
    
    /**
     * Validate pagination parameters
     */
    public function validate_pagination(array $data): array {
        $sanitized = [];
        
        $sanitized['page'] = isset($data['page']) ? max(1, absint($data['page'])) : 1;
        $sanitized['per_page'] = isset($data['per_page']) ? max(1, min(100, absint($data['per_page']))) : 20;
        $sanitized['offset'] = ($sanitized['page'] - 1) * $sanitized['per_page'];
        
        return $sanitized;
    }
    
    /**
     * Validate date range
     */
    public function validate_date_range(array $data): array|WP_Error {
        $errors = new WP_Error();
        $sanitized = [];
        
        if (isset($data['date_from'])) {
            $sanitized['date_from'] = sanitize_text_field($data['date_from']);
            if (!$this->is_valid_date($sanitized['date_from'])) {
                $errors->add('date_from_invalid', __('Invalid start date format', 'hourly-room-booking'));
            }
        }
        
        if (isset($data['date_to'])) {
            $sanitized['date_to'] = sanitize_text_field($data['date_to']);
            if (!$this->is_valid_date($sanitized['date_to'])) {
                $errors->add('date_to_invalid', __('Invalid end date format', 'hourly-room-booking'));
            }
        }
        
        // Validate date range logic
        if (!empty($sanitized['date_from']) && !empty($sanitized['date_to'])) {
            if (strtotime($sanitized['date_from']) > strtotime($sanitized['date_to'])) {
                $errors->add('date_range_invalid', __('Start date must be before end date', 'hourly-room-booking'));
            }
        }
        
        if (!empty($errors->get_error_messages())) {
            return $errors;
        }
        
        return $sanitized;
    }
}
