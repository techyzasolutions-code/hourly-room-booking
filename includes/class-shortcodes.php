<?php
/**
 * Shortcodes Class
 * Handles all plugin shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Shortcodes {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('room_booking_form', array($this, 'room_booking_form_shortcode'));
        add_shortcode('room_booking_search', array($this, 'room_booking_search_shortcode'));
        add_shortcode('room_calendar', array($this, 'room_calendar_shortcode'));
        add_shortcode('room_list', array($this, 'room_list_shortcode'));
    }
    
    /**
     * Room booking form shortcode
     * [room_booking_form room_id="1"]
     */
    public function room_booking_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'room_id' => '',
            'show_room_info' => 'true',
            'redirect_url' => ''
        ), $atts, 'room_booking_form');
        
        $room_id = intval($atts['room_id']);
        
        if (empty($room_id)) {
            return '<p>' . __('Error: Room ID is required', 'hourly-room-booking') . '</p>';
        }
        
        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($room_id);
        
        if (!$room || !$room->is_active) {
            return '<p>' . __('Error: Room not available', 'hourly-room-booking') . '</p>';
        }
        
        ob_start();
        include HRB_PLUGIN_DIR . 'templates/booking-form.php';
        return ob_get_clean();
    }
    
    /**
     * Room search and booking form shortcode
     * [room_booking_search]
     */
    public function room_booking_search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_filters' => 'true',
            'rooms_per_page' => '6'
        ), $atts, 'room_booking_search');
        
        ob_start();
        include HRB_PLUGIN_DIR . 'templates/search-form.php';
        return ob_get_clean();
    }
    
    /**
     * Room calendar shortcode
     * [room_calendar room_id="1"]
     */
    public function room_calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'room_id' => '',
            'view' => 'month',
            'height' => '600'
        ), $atts, 'room_calendar');
        
        $room_id = intval($atts['room_id']);
        
        if (empty($room_id)) {
            return '<p>' . __('Error: Room ID is required', 'hourly-room-booking') . '</p>';
        }
        
        wp_enqueue_script('fullcalendar', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js', array('jquery'), '3.10.2', true);
        wp_enqueue_style('fullcalendar', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css', array(), '3.10.2');
        
        ob_start();
        include HRB_PLUGIN_DIR . 'templates/room-calendar.php';
        return ob_get_clean();
    }
    
    /**
     * Room list shortcode
     * [room_list]
     */
    public function room_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'columns' => '3',
            'show_price' => 'true',
            'show_capacity' => 'true',
            'show_amenities' => 'true',
            'limit' => '-1'
        ), $atts, 'room_list');
        
        $room_manager = HRB_Room_Manager::getInstance();
        $rooms = $room_manager->get_all_rooms();
        
        if (intval($atts['limit']) > 0) {
            $rooms = array_slice($rooms, 0, intval($atts['limit']));
        }
        
        ob_start();
        include HRB_PLUGIN_DIR . 'templates/room-list.php';
        return ob_get_clean();
    }
}

?>