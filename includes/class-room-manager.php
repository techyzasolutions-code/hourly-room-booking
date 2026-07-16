<?php
/**
 * Room Manager Class
 * Handles all room-related operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Room_Manager {
    
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
     * Get all active rooms
     */
    public function get_all_rooms($active_only='all') {
        global $wpdb;
        
        $where = '';
        if ($active_only == 'active') {
            $where = ' WHERE is_active = 1';
        }elseif($active_only == 'inactive'){
            $where = ' WHERE is_active = 0';
        }elseif($active_only == 'all'){
            $where = '';
        }
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}hrb_rooms$where ORDER BY sort_order ASC, name ASC"
        );
    }
    
    /**
     * Get room by ID
     */
    public function get_room($room_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_rooms WHERE id = %d",
            $room_id
        ));
    }
    
    /**
     * Create new room
     */
    public function create_room($data) {
        global $wpdb;
        
        $defaults = array(
            'name' => '',
            'description' => '',
            'capacity' => 1,
            'price_2_hours' => 0.00,
            'price_3_hours' => 0.00,
            'price_4_hours' => 0.00,
            'price_extra_hour' => 0.00,
            'images' => '',
            'amenities' => '',
            'color' => '#3498db',
            'is_active' => 1,
            'sort_order' => 0
        );
        
        // Use validated data directly, no need for wp_parse_args since validator provides all fields
        $room_data = $data;
        
        // Validate required fields
        if (empty($room_data['name'])) {
            return new WP_Error('missing_name', __('Room name is required', 'hourly-room-booking'));
        }
        
        if ($room_data['capacity'] < 1) {
            return new WP_Error('invalid_capacity', __('Room capacity must be at least 1', 'hourly-room-booking'));
        }
        
        // if ($room_data['hourly_price'] < 0) {
        //     return new WP_Error('invalid_price', __('Hourly price cannot be negative', 'hourly-room-booking'));
        // }
        
        // Handle amenities array
        if (is_array($room_data['amenities'])) {
            $room_data['amenities'] = json_encode($room_data['amenities']);
        }
        
        // Handle images array
        if (is_array($room_data['images'])) {
            $room_data['images'] = json_encode($room_data['images']);
        }
        
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'hrb_rooms',
            $room_data,
            array('%s', '%s', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%d', '%d')
        );
        
        if ($result === false) {
            global $wpdb;
            return new WP_Error('db_error', __('Failed to create room: ' . $wpdb->last_error, 'hourly-room-booking'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update room
     */
    public function update_room($room_id, $data) {
        global $wpdb;
        
        $room = $this->get_room($room_id);
        if (!$room) {
            return new WP_Error('room_not_found', __('Room not found', 'hourly-room-booking'));
        }
        
        // Handle amenities array
        if (isset($data['amenities']) && is_array($data['amenities'])) {
            $data['amenities'] = json_encode($data['amenities']);
        }
        
        // Handle images array
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = json_encode($data['images']);
        }
        
        // Validate data
        if (isset($data['capacity']) && $data['capacity'] < 1) {
            return new WP_Error('invalid_capacity', __('Room capacity must be at least 1', 'hourly-room-booking'));
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_rooms',
            $data,
            array('id' => $room_id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update room', 'hourly-room-booking'));
        }
        
        return true;
    }
    
    /**
     * Delete room (soft delete by setting inactive)
     */
    public function delete_room($room_id, $force = false) {
        global $wpdb;
        
        $room = $this->get_room($room_id);
        if (!$room) {
            return new WP_Error('room_not_found', __('Room not found', 'hourly-room-booking'));
        }
        
        // Check for existing bookings
        $booking_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_bookings 
             WHERE room_id = %d AND status NOT IN ('cancelled')",
            $room_id
        ));
        
        if ($booking_count > 0 && !$force) {
            return new WP_Error('room_has_bookings', __('Cannot delete room with existing bookings', 'hourly-room-booking'));
        }
        
        if ($force) {
            // Hard delete
            $result = $wpdb->delete(
                $wpdb->prefix . 'hrb_rooms',
                array('id' => $room_id),
                array('%d')
            );
        } else {
            // Soft delete
            $result = $wpdb->update(
                $wpdb->prefix . 'hrb_rooms',
                array('is_active' => 0),
                array('id' => $room_id),
                array('%d'),
                array('%d')
            );
        }
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete room: ' . $wpdb->last_error, 'hourly-room-booking'));
        }
        
        return true;
    }

    /**
     * Toggle room status (active/inactive)
     */
    public function toggle_room_status($room_id) {
        global $wpdb;

        if (!current_user_can('hrb_manage_rooms')) {
            return new WP_Error('permission_denied', __('Permission denied', 'hourly-room-booking'));
        }

        $room = $this->get_room($room_id);
        if (!$room) {
            return new WP_Error('room_not_found', __('Room not found', 'hourly-room-booking'));
        }

        $new_status = $room->is_active ? 0 : 1;

        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_rooms',
            array('is_active' => $new_status),
            array('id' => $room_id),
            array('%d'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update room status', 'hourly-room-booking'));
        }

        return true;
    }

    /**
     * Get room availability for a specific date
     */
    public function get_room_availability($room_id, $date) {
        global $wpdb;
        
        $room = $this->get_room($room_id);
        if (!$room || !$room->is_active) {
            return array();
        }
        
        // Get business hours and time slots
        $business_start = get_option('hrb_booking_start_time', '08:00');
        $business_end = get_option('hrb_booking_end_time', '20:00');
        $time_slots = HRB_Database::get_setting('booking_time_slots', array());
        
        // Check for room-specific availability exceptions
        $exception = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_room_availability 
             WHERE room_id = %d AND exception_date = %s AND is_active = 1",
            $room_id, $date
        ));
        
        if ($exception && $exception->type == 'closed') {
            return array(); // Room is closed
        }
        
        // Get existing bookings for the date
        $existing_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT start_time, end_time FROM {$wpdb->prefix}hrb_bookings 
             WHERE room_id = %d AND booking_date = %s AND status NOT IN ('cancelled')",
            $room_id, $date
        ));
        
        // Generate available slots
        $available_slots = array();
        
        if (empty($time_slots)) {
            // Generate hourly slots if none configured
            $current_time = strtotime($business_start);
            $end_time = strtotime($business_end);
            
            while ($current_time < $end_time) {
                $time_slots[] = date('H:i', $current_time);
                $current_time += 3600; // Add 1 hour
            }
        }
        
        foreach ($time_slots as $slot) {
            $slot_start = $slot;
            $slot_end = date('H:i', strtotime($slot . ' +1 hour'));
            
            // Check if slot is within business hours
            if ($slot_start < $business_start || $slot_start >= $business_end) {
                continue;
            }
            
            // Check for conflicts with existing bookings
            $is_available = true;
            foreach ($existing_bookings as $booking) {
                if ($this->time_slots_overlap($slot_start, $slot_end, $booking->start_time, $booking->end_time)) {
                    $is_available = false;
                    break;
                }
            }
            
            if ($is_available) {
                $available_slots[] = array(
                    'time' => $slot_start,
                    'display' => $this->format_time_slot($slot_start),
                    'price' => $this->get_slot_price($room, $date, $slot_start)
                );
            }
        }
        
        return $available_slots;
    }
    
    /**
     * Check if two time slots overlap
     */
    private function time_slots_overlap($start1, $end1, $start2, $end2) {
        return (strtotime($start1) < strtotime($end2) && strtotime($end1) > strtotime($start2));
    }
    
    /**
     * Format time slot for display
     */
    private function format_time_slot($time) {
        $start_time = date('H:i', strtotime($time));
        $end_time = date('H:i', strtotime($time . ' +1 hour'));
        return $start_time . ' - ' . $end_time;
    }
    
    /**
     * Get price for a specific slot based on date and time
     */
    public function get_slot_price($room, $date, $time) {
        if (is_numeric($room)) {
            $room = $this->get_room($room);
        }
        
        if (!$room) {
            return 0;
        }
        
        return floatval($room->hourly_price);
    }
    
    /**
     * Get price range for room (2-4 hours)
     */
    public function get_room_price_range($room) {
        if (is_numeric($room)) {
            $room = $this->get_room($room);
        }
        
        if (!$room) {
            return ['min' => 0, 'max' => 0, 'formatted' => 'N/A'];
        }
        
        $prices = [];
        
        // Get room-specific prices first, fallback to global for missing ones
        $room_2h = floatval($room->price_2_hours);
        $room_3h = floatval($room->price_3_hours);
        $room_4h = floatval($room->price_4_hours);
        
        $global_2h = floatval(get_option('hrb_price_2_hours', 0));
        $global_3h = floatval(get_option('hrb_price_3_hours', 0));
        $global_4h = floatval(get_option('hrb_price_4_hours', 0));
        
        // Use room-specific price if available, otherwise use global
        if ($room_2h > 0) {
            $prices[] = $room_2h;
        } elseif ($global_2h > 0) {
            $prices[] = $global_2h;
        }
        
        if ($room_3h > 0) {
            $prices[] = $room_3h;
        } elseif ($global_3h > 0) {
            $prices[] = $global_3h;
        }
        
        if ($room_4h > 0) {
            $prices[] = $room_4h;
        } elseif ($global_4h > 0) {
            $prices[] = $global_4h;
        }
        
        // If no prices found, return 0
        if (empty($prices)) {
            return ['min' => 0, 'max' => 0, 'formatted' => 'N/A'];
        }
        
        $min_price = min($prices);
        $max_price = max($prices);
        
        $currency_manager = HRB_Currency_Manager::getInstance();
        $pricing_label = get_option('hrb_pricing_label', '');
        
        $formatted = hrb_format_amount($min_price) . ' - ' . hrb_format_amount($max_price);
        
        // Add pricing label if set
        if (!empty($pricing_label)) {
            $formatted = $pricing_label . ' ' . $formatted;
        }
        
        return [
            'min' => $min_price,
            'max' => $max_price,
            'formatted' => $formatted
        ];
    }
    
    /**
     * Get rooms with search filters
     */
    public function search_rooms($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('is_active = 1');
        $params = array();
        
        // Date and time filter - use proper availability checking with cooldown
        if (!empty($filters['date']) && !empty($filters['start_time'])) {
            $end_time = !empty($filters['end_time']) ? $filters['end_time'] : date('H:i', strtotime($filters['start_time'] . ' +1 hour'));
            
            // Get all rooms first, then filter by availability using the same logic as search-form.php
            $all_rooms = $this->get_all_rooms();
            $available_rooms = array();
            
            foreach ($all_rooms as $room) {
                if ($this->is_room_available($room->id, $filters['date'], $filters['start_time'], $end_time)) {
                    $available_rooms[] = $room->id;
                }
            }
            
            if (!empty($available_rooms)) {
                $room_ids = implode(',', array_map('intval', $available_rooms));
                $where_conditions[] = "id IN ($room_ids)";
            } else {
                // No rooms available, return empty result
                $where_conditions[] = "1 = 0";
            }
        }
        
        // Capacity filter
        if (!empty($filters['min_capacity'])) {
            $where_conditions[] = 'capacity >= %d';
            $params[] = intval($filters['min_capacity']);
        }
        
        // Price range filter - check if any of the 2-4 hour prices are within range
        if (!empty($filters['max_price'])) {
            $max_price = floatval($filters['max_price']);
            $where_conditions[] = '(price_2_hours <= %f OR price_3_hours <= %f OR price_4_hours <= %f OR hourly_price * 2 <= %f)';
            $params[] = $max_price;
            $params[] = $max_price;
            $params[] = $max_price;
            $params[] = $max_price;
        }
        
        // Amenities filter
        if (!empty($filters['amenities']) && is_array($filters['amenities'])) {
            foreach ($filters['amenities'] as $amenity) {
                $where_conditions[] = 'amenities LIKE %s';
                $params[] = '%' . $wpdb->esc_like($amenity) . '%';
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT * FROM {$wpdb->prefix}hrb_rooms WHERE $where_clause ORDER BY sort_order ASC, name ASC";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_results($sql);
        }
    }
    
    /**
     * Get room price for specific duration
     */
    public function get_room_price_for_duration($room, $duration_hours) {
        if (is_numeric($room)) {
            $room = $this->get_room($room);
        }
        
        if (!$room) {
            return 0;
        }
        
        // Check if room has specific pricing for this duration
        $room_price = 0;
        $use_room_price = false;
        
        if ($duration_hours == 2 && $room->price_2_hours > 0) {
            $room_price = floatval($room->price_2_hours);
            $use_room_price = true;
        } elseif ($duration_hours == 3 && $room->price_3_hours > 0) {
            $room_price = floatval($room->price_3_hours);
            $use_room_price = true;
        } elseif ($duration_hours == 4 && $room->price_4_hours > 0) {
            $room_price = floatval($room->price_4_hours);
            $use_room_price = true;
        } elseif ($duration_hours > 4) {
            // For durations > 4 hours, use 4-hour price + extra hours
            if ($room->price_4_hours > 0) {
                $room_price = floatval($room->price_4_hours);
                $use_room_price = true;
                
                // Add extra hours using room-specific or global extra hour price
                $extra_hours = $duration_hours - 4;
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
        if ($duration_hours == 2) {
            $global_price = floatval(get_option('hrb_price_2_hours', 0));
        } elseif ($duration_hours == 3) {
            $global_price = floatval(get_option('hrb_price_3_hours', 0));
        } elseif ($duration_hours == 4) {
            $global_price = floatval(get_option('hrb_price_4_hours', 0));
        } elseif ($duration_hours > 4) {
            $global_price = floatval(get_option('hrb_price_4_hours', 0));
            $extra_hours = $duration_hours - 4;
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
     * Get room amenities as array
     */
    public function get_room_amenities($room_id) {
        $room = $this->get_room($room_id);
        if (!$room || empty($room->amenities)) {
            return array();
        }
        
        $amenities = json_decode($room->amenities, true);
        return is_array($amenities) ? $amenities : array();
    }
    
    /**
     * Get room images as array
     */
    public function get_room_images($room_id) {
        $room = $this->get_room($room_id);
        if (!$room || empty($room->images)) {
            return array();
        }
        
        $images = json_decode($room->images, true);
        return is_array($images) ? $images : array();
    }
    
    /**
     * Update room sort order
     */
    public function update_sort_order($room_orders) {
        global $wpdb;
        
        if (!is_array($room_orders)) {
            return false;
        }
        
        foreach ($room_orders as $room_id => $order) {
            $wpdb->update(
                $wpdb->prefix . 'hrb_rooms',
                array('sort_order' => intval($order)),
                array('id' => intval($room_id)),
                array('%d'),
                array('%d')
            );
        }
        
        return true;
    }
    
    /**
     * Get room booking statistics
     */
    public function get_room_stats($room_id, $start_date = null, $end_date = null) {
        global $wpdb;
        
        $date_condition = '';
        $params = array($room_id);
        
        if ($start_date && $end_date) {
            $date_condition = 'AND booking_date BETWEEN %s AND %s';
            $params[] = $start_date;
            $params[] = $end_date;
        }
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show_bookings,
                AVG(total_amount) as avg_booking_value,
                SUM(total_amount) as total_revenue,
                AVG(total_hours) as avg_duration
            FROM {$wpdb->prefix}hrb_bookings 
            WHERE room_id = %d $date_condition",
            $params
        ));
        
        return $stats;
    }
    
    /**
     * Check if room is available for booking
     */
    public function is_room_available($room_id, $date, $start_time, $end_time = null) {
        if (!$end_time) {
            $end_time = date('H:i', strtotime($start_time . ' +1 hour'));
        }
        
        $room = $this->get_room($room_id);
        if (!$room || !$room->is_active) {
            return false;
        }
        
        // Check for conflicts
        return !HRB_Database::check_booking_conflict($room_id, $date, $start_time, $end_time);
    }

    /**
     * Whether a booking [start,end] falls inside the room's general bookable window.
     * available_from/available_to of '00:00:00'/'00:00:00' means fully bookable (24h).
     * An end time of 00:00 counts as midnight (end of day). Cross-midnight bookings are
     * only allowed when the room is fully bookable.
     */
    public function is_time_within_availability($room, $start_time, $end_time) {
        $from = (is_object($room) && isset($room->available_from)) ? $room->available_from : '00:00:00';
        $to   = (is_object($room) && isset($room->available_to)) ? $room->available_to : '00:00:00';
        $from_min = (intval(substr($from, 0, 2)) * 60) + intval(substr($from, 3, 2));
        $to_min   = ($to === '00:00:00' || $to === '00:00') ? 1440 : ((intval(substr($to, 0, 2)) * 60) + intval(substr($to, 3, 2)));
        if ($from_min <= 0 && $to_min >= 1440) {
            return true; // fully bookable
        }
        $s = (intval(substr($start_time, 0, 2)) * 60) + intval(substr($start_time, 3, 2));
        $e = (intval(substr($end_time, 0, 2)) * 60) + intval(substr($end_time, 3, 2));
        if ($e === 0) { $e = 1440; }
        if ($e <= $s) { return false; } // cross-midnight not allowed within a restricted window
        return ($s >= $from_min && $e <= $to_min);
    }
}
?>