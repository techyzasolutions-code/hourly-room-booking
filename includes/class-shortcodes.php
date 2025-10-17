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
        add_shortcode('room_booking_page_form', array($this, 'room_booking_page_form_shortcode'));
        add_shortcode('room_booking_search', array($this, 'room_booking_search_shortcode'));
        add_shortcode('room_booking_filter', array($this, 'room_booking_filter_shortcode'));
        add_shortcode('room_calendar', array($this, 'room_calendar_shortcode'));
        add_shortcode('room_list', array($this, 'room_list_shortcode'));
        add_shortcode('booking_button', array($this, 'booking_button_shortcode'));
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
     * Room booking page form shortcode (exact duplicate of room_booking_form)
     * [room_booking_page_form room_id="1"]
     */
    public function room_booking_page_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'room_id' => '',
            'show_room_info' => 'true',
            'redirect_url' => ''
        ), $atts, 'room_booking_page_form');
        
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
        include HRB_PLUGIN_DIR . 'templates/booking-form-page.php';
        return ob_get_clean();
    }
    
    /**
     * Room booking filter only shortcode (for homepage)
     * [room_booking_filter redirect_url="/search-results/"]
     */
    public function room_booking_filter_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect_url' => '/search-results/',
            'show_title' => 'true',
            'title' => __('Find Your Perfect Room', 'hourly-room-booking'),
            'subtitle' => __('Search and book rooms by date, time, and duration', 'hourly-room-booking')
        ), $atts, 'room_booking_filter');
        
        ob_start();
        include HRB_PLUGIN_DIR . 'templates/booking-filter.php';
        return ob_get_clean();
    }
    
    /**
     * Room search and booking form shortcode
     * [room_booking_search]
     */
    public function room_booking_search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_filters' => 'true',
            'rooms_per_page' => '6',
            'columns' => '3',
            'show_price' => 'true',
            'show_capacity' => 'true',
            'show_amenities' => 'true',
            'show_view_button' => 'true'
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
        
        // Load FullCalendar with all plugins bundled
        wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', array('jquery'), '6.1.10', true);
        wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css', array(), '6.1.10');
        
        // Load additional plugins for dayGridMonth, timeGridWeek, timeGridDay
        wp_enqueue_script('fullcalendar-daygrid', 'https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.10/index.global.min.js', array('fullcalendar'), '6.1.10', true);
        wp_enqueue_script('fullcalendar-timegrid', 'https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@6.1.10/index.global.min.js', array('fullcalendar'), '6.1.10', true);
        
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
    
    /**
     * Booking button shortcode
     * [booking_button room_id="1" text="Book Now" style="primary" size="medium"]
     */
    public function booking_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'room_id' => '',
            'text' => 'Book Now',
            'style' => 'primary',
            'size' => 'medium',
            'class' => ''
        ), $atts, 'booking_button');
        
        $room_id = intval($atts['room_id']);
        
        if (empty($room_id)) {
            return '<p>' . __('Error: Room ID is required', 'hourly-room-booking') . '</p>';
        }
        
        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($room_id);
        
        if (!$room) {
            return '<p>' . __('Error: Room not found', 'hourly-room-booking') . '</p>';
        }
        
        $button_text = esc_html($atts['text']);
        $button_style = esc_attr($atts['style']);
        $button_size = esc_attr($atts['size']);
        $button_class = esc_attr($atts['class']);
        
        // Generate unique ID for the button
        $button_id = 'hrb-booking-btn-' . $room_id . '-' . uniqid();
        
        ob_start();
        ?>
        <button type="button" 
                id="<?php echo $button_id; ?>" 
                class="hrb-booking-button hrb-btn-<?php echo $button_style; ?> hrb-btn-<?php echo $button_size; ?> <?php echo $button_class; ?>" 
                data-room-id="<?php echo $room_id; ?>"
                data-room-name="<?php echo esc_attr($room->name); ?>">
            <?php echo $button_text; ?>
        </button>
        
        <style>
        .hrb-booking-button {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            line-height: 1.4;
        }
        
        .hrb-booking-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            text-decoration: none;
        }
        
        .hrb-booking-button:active {
            transform: translateY(0);
        }
        
        /* Button Styles */
        .hrb-btn-primary {
            background: var(--hrb-primary);
            color: white;
        }
        
        .hrb-btn-primary:hover {
            background: var(--hrb-primary-dark);
            color: white;
        }
        
        .hrb-btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }
        
        .hrb-btn-secondary:hover {
            background: linear-gradient(135deg, #4b5563, #374151);
            color: white;
        }
        
        .hrb-btn-success {
            background: var(--hrb-success);
            color: white;
        }
        
        .hrb-btn-success:hover {
            background: var(--hrb-success-dark);
            color: white;
        }
        
        .hrb-btn-outline {
            background: transparent;
            color: #3b82f6;
            border: 2px solid #3b82f6;
        }
        
        .hrb-btn-outline:hover {
            background: #3b82f6;
            color: white;
        }
        
        /* Button Sizes */
        .hrb-btn-small {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .hrb-btn-medium {
            padding: 12px 24px;
            font-size: 16px;
        }
        
        .hrb-btn-large {
            padding: 16px 32px;
            font-size: 18px;
        }
        
        .hrb-btn-block {
            width: 100%;
            display: block;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#<?php echo $button_id; ?>').on('click', function(e) {
                e.preventDefault();
                const roomId = $(this).data('room-id');
                const roomName = $(this).data('room-name');
                
                // Show booking form in modal
                showBookingModal(roomId, roomName);
            });
            
            function showBookingModal(roomId, roomName) {
                // Create modal overlay
                const modalHtml = `
                    <div class="hrb-modal-overlay" id="hrb-booking-modal">
                        <div class="hrb-modal-content">
                            <div class="hrb-modal-header">
                                <h3><?php _e('Book', 'hourly-room-booking'); ?> ${roomName}</h3>
                                <button class="hrb-modal-close">&times;</button>
                            </div>
                            <div class="hrb-modal-body">
                                <div class="hrb-loading-message">
                                    <div class="hrb-loading-spinner"></div>
                                    <p><?php _e('Loading booking form...', 'hourly-room-booking'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                $('body').append(modalHtml);
                
                // Load booking form via AJAX
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'hrb_get_booking_form',
                        room_id: roomId,
                        nonce: '<?php echo wp_create_nonce('hrb_booking_form_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#hrb-booking-modal .hrb-modal-body').html(response.data.html);
                        } else {
                            $('#hrb-booking-modal .hrb-modal-body').html('<p class="hrb-error">' + response.data + '</p>');
                        }
                    },
                    error: function() {
                        $('#hrb-booking-modal .hrb-modal-body').html('<p class="hrb-error"><?php _e('Failed to load booking form', 'hourly-room-booking'); ?></p>');
                    }
                });
            }
            
            // Handle modal close
            $(document).on('click', '.hrb-modal-close, .hrb-modal-overlay', function(e) {
                if (e.target === this) {
                    $(this).closest('.hrb-modal-overlay').remove();
                }
            });
            
            // Close modal on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.hrb-modal-overlay').remove();
                }
            });
        });
        </script>
        
        <style>
        /* Modal Styles */
        .hrb-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .hrb-modal-content {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .hrb-modal-header {
            background: #f8fafc;
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .hrb-modal-header h3 {
            margin: 0;
            color: #1f2937;
            font-size: 20px;
            font-weight: 600;
        }
        
        .hrb-modal-close {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            font-size: 18px;
            color: #374151;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .hrb-modal-close:hover {
            background: #e5e7eb;
            border-color: #9ca3af;
        }
        
        .hrb-modal-body {
            padding: 24px;
            max-height: calc(90vh - 80px);
            overflow-y: auto;
        }
        
        .hrb-loading-message {
            text-align: center;
            padding: 40px;
            color: #6b7280;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .hrb-loading-spinner {
            width: 35px;
            height: 35px;
            border: 2px solid #e5e7eb;
            border-radius: 50%;
            border-top-color: #3b82f6;
            animation: hrb-spin 1s linear infinite;
            margin: 0 auto 16px;
        }
        
        @keyframes hrb-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .hrb-error {
            color: #dc2626;
            background: #fef2f2;
            border: 1px solid #fecaca;
            padding: 16px;
            border-radius: 6px;
            text-align: center;
        }
        
        @media screen and (max-width: 768px) {
            .hrb-modal-content {
                margin: 10px;
                max-width: calc(100% - 20px);
                border-radius: 15px;
            }
            
            .hrb-modal-header {
                padding: 20px 25px;
            }
            
            .hrb-modal-header h3 {
                font-size: 18px;
            }
            
            .hrb-modal-body {
                padding: 25px;
            }
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
}

?>