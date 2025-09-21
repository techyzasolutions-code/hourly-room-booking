<?php
/**
 * Calendar functionality for the Hourly Room Booking plugin
 *
 * @package HourlyRoomBooking
 * @subpackage Calendar
 * @since 1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('Direct script access denied.');
}

/**
 * HRB_Calendar Class
 *
 * Handles calendar functionality for room booking system including
 * FullCalendar.js integration, booking display, and availability checking.
 *
 * @since 1.0.0
 */
class HRB_Calendar {

    /**
     * Single instance of the class
     *
     * @since 1.0.0
     * @var HRB_Calendar|null
     */
    private static ?HRB_Calendar $instance = null;

    /**
     * Database instance
     *
     * @since 1.0.0
     * @var HRB_Database
     */
    private HRB_Database $db;

    /**
     * Room manager instance
     *
     * @since 1.0.0
     * @var HRB_Room_Manager
     */
    private HRB_Room_Manager $room_manager;

    /**
     * Booking manager instance
     *
     * @since 1.0.0
     * @var HRB_Booking_Manager
     */
    private HRB_Booking_Manager $booking_manager;

    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return HRB_Calendar
     */
    public static function getInstance(): HRB_Calendar {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->db = HRB_Database::getInstance();
        $this->room_manager = HRB_Room_Manager::getInstance();
        $this->booking_manager = HRB_Booking_Manager::getInstance();

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_calendar_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_calendar_assets']);
    }

    /**
     * Enqueue FullCalendar assets for frontend
     *
     * @since 1.0.0
     */
    public function enqueue_calendar_assets(): void {
        // FullCalendar CSS
        wp_enqueue_style(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
            [],
            '6.1.10'
        );

        // FullCalendar JS
        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
            [],
            '6.1.10',
            true
        );

        // Custom calendar JS
        wp_enqueue_script(
            'hrb-calendar',
            HRB_ASSETS_URL . 'js/calendar.js',
            ['fullcalendar', 'jquery'],
            HRB_VERSION,
            true
        );

        // Localize script
        wp_localize_script('hrb-calendar', 'hrbCalendar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hrb_calendar_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'hourly-room-booking'),
                'error' => __('Error loading calendar', 'hourly-room-booking'),
                'booked' => __('Booked', 'hourly-room-booking'),
                'available' => __('Available', 'hourly-room-booking'),
                'cooldown' => __('Cooldown Period', 'hourly-room-booking'),
                'selectTime' => __('Select Time', 'hourly-room-booking'),
                'bookNow' => __('Book Now', 'hourly-room-booking'),
            ]
        ]);
    }

    /**
     * Enqueue FullCalendar assets for admin
     *
     * @since 1.0.0
     */
    public function enqueue_admin_calendar_assets(): void {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'hrb-') === false) {
            return;
        }

        $this->enqueue_calendar_assets();

        // Admin-specific calendar JS
        // wp_enqueue_script(
        //     'hrb-admin-calendar',
        //     HRB_ASSETS_URL . 'js/admin-calendar.js',
        //     ['hrb-calendar'],
        //     HRB_VERSION,
        //     true
        // );

        wp_localize_script('hrb-admin-calendar', 'hrbAdminCalendar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hrb_admin_calendar_nonce'),
            'canEdit' => current_user_can('manage_options'),
            'strings' => [
                'editBooking' => __('Edit Booking', 'hourly-room-booking'),
                'deleteBooking' => __('Delete Booking', 'hourly-room-booking'),
                'confirmDelete' => __('Are you sure you want to delete this booking?', 'hourly-room-booking'),
            ]
        ]);
    }

    /**
     * Get calendar events for a specific room and date range
     *
     * @since 1.0.0
     * @param int $room_id Room ID (0 for all rooms)
     * @param string $start_date Start date (Y-m-d format)
     * @param string $end_date End date (Y-m-d format)
     * @return array Calendar events
     */
    public function get_calendar_events(int $room_id = 0, string $start_date = '', string $end_date = ''): array {
        global $wpdb;

        // Default date range if not provided
        if (empty($start_date)) {
            $start_date = date('Y-m-d');
        }
        if (empty($end_date)) {
            $end_date = date('Y-m-d', strtotime('+1 month'));
        }

        $room_condition = $room_id > 0 ? $wpdb->prepare('AND b.room_id = %d', $room_id) : '';

        $query = $wpdb->prepare("
            SELECT
                b.id,
                b.room_id,
                b.customer_id,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.status,
                b.total_amount,
                b.extra_people,
                r.name as room_name,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                c.email as customer_email,
                c.phone as customer_phone
            FROM {$wpdb->prefix}hrb_bookings b
            LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
            WHERE b.booking_date BETWEEN %s AND %s
            {$room_condition}
            AND b.status NOT IN ('cancelled', 'no_show')
            ORDER BY b.booking_date, b.start_time
        ", $start_date, $end_date);

        $bookings = $wpdb->get_results($query, ARRAY_A);
        $events = [];

        foreach ($bookings as $booking) {
            $start_datetime = $booking['booking_date'] . 'T' . $booking['start_time'];
            $end_datetime = $booking['booking_date'] . 'T' . $booking['end_time'];

            // Calculate cooldown end time (30 minutes after booking end)
            $cooldown_end = date('H:i:s', strtotime($booking['end_time'] . ' +30 minutes'));
            $cooldown_end_datetime = $booking['booking_date'] . 'T' . $cooldown_end;

            // Main booking event
            $events[] = [
                'id' => 'booking_' . $booking['id'],
                'title' => sprintf(
                    '%s - %s',
                    $booking['room_name'],
                    $booking['customer_name']
                ),
                'start' => $start_datetime,
                'end' => $end_datetime,
                'backgroundColor' => $this->get_status_color($booking['status']),
                'borderColor' => $this->get_status_color($booking['status']),
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'bookingId' => $booking['id'],
                    'roomId' => $booking['room_id'],
                    'roomName' => $booking['room_name'],
                    'customerName' => $booking['customer_name'],
                    'customerEmail' => $booking['customer_email'],
                    'customerPhone' => $booking['customer_phone'],
                    'status' => $booking['status'],
                    'totalAmount' => $booking['total_amount'],
                    'extraPeople' => $booking['extra_people'],
                    'type' => 'booking'
                ]
            ];

            // Cooldown period event
            if (strtotime($cooldown_end) <= strtotime('23:59:59')) {
                $events[] = [
                    'id' => 'cooldown_' . $booking['id'],
                    'title' => sprintf(
                        __('Cooldown - %s', 'hourly-room-booking'),
                        $booking['room_name']
                    ),
                    'start' => $end_datetime,
                    'end' => $cooldown_end_datetime,
                    'backgroundColor' => '#ffc107',
                    'borderColor' => '#ffc107',
                    'textColor' => '#000000',
                    'display' => 'background',
                    'extendedProps' => [
                        'type' => 'cooldown',
                        'roomId' => $booking['room_id'],
                        'parentBookingId' => $booking['id']
                    ]
                ];
            }
        }

        return $events;
    }

    /**
     * Get room availability for a specific date and time range
     *
     * @since 1.0.0
     * @param int $room_id Room ID
     * @param string $date Date (Y-m-d format)
     * @param string $start_time Start time (H:i:s format)
     * @param string $end_time End time (H:i:s format)
     * @return bool True if available, false if not
     */
    public function check_room_availability(int $room_id, string $date, string $start_time, string $end_time): bool {
        return $this->room_manager->check_availability($room_id, $date, $start_time, $end_time);
    }

    /**
     * Get available time slots for a room on a specific date
     *
     * @since 1.0.0
     * @param int $room_id Room ID
     * @param string $date Date (Y-m-d format)
     * @return array Available time slots
     */
    public function get_available_time_slots(int $room_id, string $date): array {
        $slots = [];
        $start_hour = 8; // 8 AM
        $end_hour = 20;  // 8 PM

        for ($hour = $start_hour; $hour < $end_hour; $hour++) {
            $start_time = sprintf('%02d:00:00', $hour);
            $end_time = sprintf('%02d:00:00', $hour + 2); // Minimum 2 hours

            if ($this->check_room_availability($room_id, $date, $start_time, $end_time)) {
                $slots[] = [
                    'time' => $start_time,
                    'display' => sprintf('%02d:00', $hour),
                    'available' => true
                ];
            }
        }

        return $slots;
    }

    /**
     * Render calendar HTML
     *
     * @since 1.0.0
     * @param array $args Calendar arguments
     * @return string Calendar HTML
     */
    public function render_calendar(array $args = []): string {
        $defaults = [
            'room_id' => 0,
            'view' => 'month',
            'height' => 'auto',
            'selectable' => false,
            'editable' => false,
            'show_room_filter' => true,
            'id' => 'hrb-calendar-' . uniqid()
        ];

        $args = wp_parse_args($args, $defaults);

        ob_start();
        ?>
        <div class="hrb-calendar-wrapper">
            <?php if ($args['show_room_filter']): ?>
                <div class="hrb-calendar-filters">
                    <select id="hrb-room-filter" class="hrb-room-filter">
                        <option value="0"><?php _e('All Rooms', 'hourly-room-booking'); ?></option>
                        <?php
                        $rooms = $this->room_manager->get_all_rooms();
                        foreach ($rooms as $room):
                        ?>
                            <option value="<?php echo esc_attr($room['id']); ?>" <?php selected($args['room_id'], $room['id']); ?>>
                                <?php echo esc_html($room['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div id="<?php echo esc_attr($args['id']); ?>" class="hrb-calendar"
                 data-room-id="<?php echo esc_attr($args['room_id']); ?>"
                 data-view="<?php echo esc_attr($args['view']); ?>"
                 data-height="<?php echo esc_attr($args['height']); ?>"
                 data-selectable="<?php echo $args['selectable'] ? 'true' : 'false'; ?>"
                 data-editable="<?php echo $args['editable'] ? 'true' : 'false'; ?>">
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize calendar when DOM is ready
            if (typeof hrbInitializeCalendar === 'function') {
                hrbInitializeCalendar('<?php echo esc_js($args['id']); ?>');
            }
        });
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Get status color for calendar events
     *
     * @since 1.0.0
     * @param string $status Booking status
     * @return string Color code
     */
    private function get_status_color(string $status): string {
        $colors = [
            'pending' => '#ffc107',    // Yellow
            'confirmed' => '#28a745',  // Green
            'completed' => '#17a2b8',  // Blue
            'cancelled' => '#dc3545',  // Red
            'no_show' => '#6c757d'     // Gray
        ];

        return $colors[$status] ?? '#007bff';
    }

    /**
     * Get calendar configuration for JavaScript
     *
     * @since 1.0.0
     * @param array $args Configuration arguments
     * @return array Calendar configuration
     */
    public function get_calendar_config(array $args = []): array {
        $defaults = [
            'room_id' => 0,
            'view' => 'month',
            'selectable' => false,
            'editable' => false,
            'height' => 'auto'
        ];

        $args = wp_parse_args($args, $defaults);

        return [
            'initialView' => $args['view'] === 'month' ? 'dayGridMonth' : 'timeGridWeek',
            'height' => $args['height'],
            'selectable' => $args['selectable'],
            'editable' => $args['editable'],
            'events' => [
                'url' => admin_url('admin-ajax.php'),
                'method' => 'POST',
                'extraParams' => [
                    'action' => 'hrb_get_calendar_events',
                    'room_id' => $args['room_id'],
                    'nonce' => wp_create_nonce('hrb_calendar_nonce')
                ]
            ],
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay'
            ],
            'locale' => determine_locale() === 'de_DE' ? 'de' : 'en',
            'firstDay' => 1, // Monday
            'slotMinTime' => '08:00:00',
            'slotMaxTime' => '20:00:00',
            'allDaySlot' => false,
            'slotDuration' => '01:00:00',
            'businessHours' => [
                'daysOfWeek' => [1, 2, 3, 4, 5, 6, 0], // Monday - Sunday
                'startTime' => '08:00',
                'endTime' => '20:00'
            ]
        ];
    }

    /**
     * AJAX handler for getting calendar events
     *
     * @since 1.0.0
     */
    public function ajax_get_calendar_events(): void {
        check_ajax_referer('hrb_calendar_nonce', 'nonce');

        $room_id = intval($_POST['room_id'] ?? 0);
        $start_date = sanitize_text_field($_POST['start'] ?? '');
        $end_date = sanitize_text_field($_POST['end'] ?? '');

        // Convert from ISO format if needed
        if ($start_date) {
            $start_date = date('Y-m-d', strtotime($start_date));
        }
        if ($end_date) {
            $end_date = date('Y-m-d', strtotime($end_date));
        }

        $events = $this->get_calendar_events($room_id, $start_date, $end_date);

        wp_send_json_success($events);
    }

    /**
     * AJAX handler for getting available time slots
     *
     * @since 1.0.0
     */
    public function ajax_get_time_slots(): void {
        check_ajax_referer('hrb_calendar_nonce', 'nonce');

        $room_id = intval($_POST['room_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? '');

        if (!$room_id || !$date) {
            wp_send_json_error(__('Invalid parameters', 'hourly-room-booking'));
            return;
        }

        $slots = $this->get_available_time_slots($room_id, $date);

        wp_send_json_success($slots);
    }
}

// Initialize AJAX handlers
// Note: hrb_get_calendar_events is handled by HRB_Admin class for admin calendar
add_action('wp_ajax_hrb_get_time_slots', [HRB_Calendar::getInstance(), 'ajax_get_time_slots']);
add_action('wp_ajax_nopriv_hrb_get_time_slots', [HRB_Calendar::getInstance(), 'ajax_get_time_slots']);