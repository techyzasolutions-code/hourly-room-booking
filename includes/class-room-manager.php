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
            'hourly_price' => 0.00,
            'price_2_hours' => 0.00,
            'price_3_hours' => 0.00,
            'price_4_hours' => 0.00,
            'price_extra_hour' => 0.00,
            'images' => '',
            'amenities' => '',
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
        
        if ($room_data['hourly_price'] < 0) {
            return new WP_Error('invalid_price', __('Hourly price cannot be negative', 'hourly-room-booking'));
        }
        
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
            array('%s', '%s', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%d', '%d')
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
        
        if (isset($data['hourly_price']) && $data['hourly_price'] < 0) {
            return new WP_Error('invalid_price', __('Hourly price cannot be negative', 'hourly-room-booking'));
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

        if (!current_user_can('manage_options')) {
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
        $business_start = HRB_Database::get_setting('business_hours_start', '08:00');
        $business_end = HRB_Database::get_setting('business_hours_end', '20:00');
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
     * Get rooms with search filters
     */
    public function search_rooms($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('is_active = 1');
        $params = array();
        
        // Date and time filter
        if (!empty($filters['date']) && !empty($filters['start_time'])) {
            $end_time = !empty($filters['end_time']) ? $filters['end_time'] : date('H:i', strtotime($filters['start_time'] . ' +1 hour'));

            // More comprehensive overlap check: two time ranges overlap if one starts before the other ends
            $where_conditions[] = "id NOT IN (
                SELECT DISTINCT room_id FROM {$wpdb->prefix}hrb_bookings
                WHERE booking_date = %s
                AND status NOT IN ('cancelled', 'rejected')
                AND NOT (end_time <= %s OR start_time >= %s)
            )";

            $params[] = $filters['date'];
            $params[] = $filters['start_time'];
            $params[] = $end_time;
        }
        
        // Capacity filter
        if (!empty($filters['min_capacity'])) {
            $where_conditions[] = 'capacity >= %d';
            $params[] = intval($filters['min_capacity']);
        }
        
        // Price range filter
        if (!empty($filters['max_price'])) {
            $where_conditions[] = 'hourly_price <= %f';
            $params[] = floatval($filters['max_price']);
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
}
?>