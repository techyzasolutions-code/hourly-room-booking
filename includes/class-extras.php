<?php
/**
 * Extras Manager Class
 * Handles all extras-related operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Extras {

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
     * Get all extras
     */
    public function get_extras($active_only ='all') {
        global $wpdb;

        $extras_table = $wpdb->prefix . 'hrb_extras';

        $where_clause = '';
        if ($active_only == 'active') {
            $where_clause = ' WHERE is_active = 1';
        }elseif($active_only == 'inactive'){
            $where_clause = ' WHERE is_active = 0';
        }elseif($active_only == 'all'){
            $where_clause = '';
        }

        $query = "SELECT * FROM {$extras_table}{$where_clause} ORDER BY sort_order ASC, name ASC";

        return $wpdb->get_results($query);
    }

    /**
     * Get extra by ID
     */
    public function get_extra($id) {
        global $wpdb;

        $extras_table = $wpdb->prefix . 'hrb_extras';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$extras_table} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Create new extra
     */
    public function create_extra($data) {
        global $wpdb;

        $extras_table = $wpdb->prefix . 'hrb_extras';

        $insert_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'price' => floatval($data['price']),
            'stock_quantity' => intval($data['stock_quantity'] ?? 0),
            'track_stock' => isset($data['track_stock']) ? 1 : 0,
            'image_url' => esc_url_raw($data['image_url'] ?? ''),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'sort_order' => intval($data['sort_order'] ?? 0),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $result = $wpdb->insert(
            $extras_table,
            $insert_data,
            [
                '%s', // name
                '%s', // description
                '%f', // price
                '%d', // stock_quantity
                '%d', // track_stock
                '%s', // image_url
                '%d', // is_active
                '%d', // sort_order
                '%s', // created_at
                '%s'  // updated_at
            ]
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update extra
     */
    public function update_extra($id, $data) {
        global $wpdb;

        $extras_table = $wpdb->prefix . 'hrb_extras';

        $update_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'price' => floatval($data['price']),
            'stock_quantity' => intval($data['stock_quantity'] ?? 0),
            'track_stock' => isset($data['track_stock']) ? 1 : 0,
            'image_url' => esc_url_raw($data['image_url'] ?? ''),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'sort_order' => intval($data['sort_order'] ?? 0),
            'updated_at' => current_time('mysql')
        ];

        $result = $wpdb->update(
            $extras_table,
            $update_data,
            ['id' => $id],
            [
                '%s', // name
                '%s', // description
                '%f', // price
                '%d', // stock_quantity
                '%d', // track_stock
                '%s', // image_url
                '%d', // is_active
                '%d', // sort_order
                '%s'  // updated_at
            ],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete extra
     */
    public function delete_extra($id) {
        global $wpdb;

        $extras_table = $wpdb->prefix . 'hrb_extras';

        // First check if extra is being used in any bookings
        $booking_extras_table = $wpdb->prefix . 'hrb_booking_extras';
        $usage_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$booking_extras_table} WHERE extra_id = %d",
                $id
            )
        );

        if ($usage_count > 0) {
            return new WP_Error('extra_in_use', 'Cannot delete extra as it is being used in existing bookings.');
        }

        $result = $wpdb->delete(
            $extras_table,
            ['id' => $id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Activate/Deactivate extra
     */
    public function toggle_extra_status($id) {
        global $wpdb;

        $extras_table = $wpdb->prefix . 'hrb_extras';

        // Get current status
        $current_status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT is_active FROM {$extras_table} WHERE id = %d",
                $id
            )
        );

        if ($current_status === null) {
            return false;
        }

        $new_status = $current_status ? 0 : 1;

        $result = $wpdb->update(
            $extras_table,
            [
                'is_active' => $new_status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get extras for a specific booking
     */
    public function get_booking_extras($booking_id) {
        global $wpdb;

        $extras_table = $wpdb->prefix . 'hrb_extras';
        $booking_extras_table = $wpdb->prefix . 'hrb_booking_extras';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, be.quantity, be.unit_price, be.total_price
                 FROM {$extras_table} e
                 INNER JOIN {$booking_extras_table} be ON e.id = be.extra_id
                 WHERE be.booking_id = %d
                 ORDER BY e.name ASC",
                $booking_id
            )
        );
    }

    /**
     * Add extras to booking
     */
    public function add_booking_extras($booking_id, $extras_data) {
        global $wpdb;

        $booking_extras_table = $wpdb->prefix . 'hrb_booking_extras';

        // First remove existing extras for this booking
        $wpdb->delete(
            $booking_extras_table,
            ['booking_id' => $booking_id],
            ['%d']
        );

        $total_extras_price = 0;

        foreach ($extras_data as $extra_data) {
            $extra_id = intval($extra_data['extra_id']);
            $quantity = intval($extra_data['quantity']);
            $unit_price = floatval($extra_data['unit_price']);
            $total_price = $quantity * $unit_price;

            $result = $wpdb->insert(
                $booking_extras_table,
                [
                    'booking_id' => $booking_id,
                    'extra_id' => $extra_id,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'total_price' => $total_price,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%d', '%f', '%f', '%s']
            );

            if ($result !== false) {
                $total_extras_price += $total_price;
            }
        }

        return $total_extras_price;
    }

    /**
     * Calculate total extras price for booking
     */
    public function calculate_extras_total($extras_data) {
        $total = 0;

        foreach ($extras_data as $extra_data) {
            $quantity = intval($extra_data['quantity']);
            $unit_price = floatval($extra_data['unit_price']);
            $total += $quantity * $unit_price;
        }

        return $total;
    }

    /**
     * Get extras statistics for admin dashboard
     */
    public function get_extras_stats() {
        global $wpdb;

        $extras_table = $wpdb->prefix . 'hrb_extras';
        $booking_extras_table = $wpdb->prefix . 'hrb_booking_extras';

        $stats = [];

        // Total active extras
        $stats['total_active'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$extras_table} WHERE is_active = 1"
        );

        // Total inactive extras
        $stats['total_inactive'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$extras_table} WHERE is_active = 0"
        );

        // Most popular extra this month
        $stats['popular_extra'] = $wpdb->get_row(
            "SELECT e.name, SUM(be.quantity) as total_quantity
             FROM {$extras_table} e
             INNER JOIN {$booking_extras_table} be ON e.id = be.extra_id
             WHERE be.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
             GROUP BY e.id, e.name
             ORDER BY total_quantity DESC
             LIMIT 1"
        );

        // Total extras revenue this month
        $stats['monthly_revenue'] = $wpdb->get_var(
            "SELECT SUM(be.total_price)
             FROM {$booking_extras_table} be
             WHERE be.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
        ) ?: 0;

        return $stats;
    }

    /**
     * Update sort order for extras
     */
    public function update_sort_order($extra_ids) {
        global $wpdb;

        $extras_table = $wpdb->prefix . 'hrb_extras';

        foreach ($extra_ids as $sort_order => $extra_id) {
            $wpdb->update(
                $extras_table,
                [
                    'sort_order' => $sort_order,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => intval($extra_id)],
                ['%d', '%s'],
                ['%d']
            );
        }

        return true;
    }

    /**
     * Check if extra has sufficient stock for the given date and time
     */
    public function check_stock_availability($extra_id, $booking_date, $start_time, $end_time, $quantity = 1) {
        // Use the new stock management system
        $stock_manager = HRB_Extra_Stock_Manager::getInstance();
        $availability = $stock_manager->check_availability($extra_id, $booking_date, $start_time, $end_time, $quantity);
        return $availability['available'];
    }

    /**
     * Get booked quantity for an extra on a specific date and time
     */
    public function get_booked_quantity($extra_id, $booking_date, $start_time, $end_time) {
        global $wpdb;

        $booking_extras_table = $wpdb->prefix . 'hrb_booking_extras';
        $bookings_table = $wpdb->prefix . 'hrb_bookings';

        $query = $wpdb->prepare("
            SELECT COALESCE(SUM(be.quantity), 0) as total_booked
            FROM {$booking_extras_table} be
            INNER JOIN {$bookings_table} b ON be.booking_id = b.id
            WHERE be.extra_id = %d
            AND b.booking_date = %s
            AND b.status IN ('confirmed', 'pending')
            AND (
                (b.start_time < %s AND b.end_time > %s) OR
                (b.start_time < %s AND b.end_time > %s) OR
                (b.start_time >= %s AND b.end_time <= %s)
            )
        ", $extra_id, $booking_date, $end_time, $start_time, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time);

        return intval($wpdb->get_var($query));
    }

    /**
     * Get available extras for a specific date and time
     */
    public function get_available_extras($booking_date, $start_time, $end_time) {
        // Use the new stock management system
        $stock_manager = HRB_Extra_Stock_Manager::getInstance();
        return $stock_manager->get_available_extras($booking_date, $start_time, $end_time);
    }

    /**
     * Get available quantity for a specific extra on a date/time
     */
    public function get_available_quantity($extra_id, $booking_date, $start_time, $end_time) {
        // Use the new stock management system
        $stock_manager = HRB_Extra_Stock_Manager::getInstance();
        $availability = $stock_manager->check_availability($extra_id, $booking_date, $start_time, $end_time, 1);
        return $availability['available_quantity'];
    }

    /**
     * Reduce stock when a booking is confirmed (for future use)
     */
    public function update_stock_on_booking($booking_id, $extras_data) {
        // This method can be called when a booking is confirmed
        // to handle any additional stock management logic if needed
        // For now, stock is managed through the booking_extras table
        return true;
    }
}