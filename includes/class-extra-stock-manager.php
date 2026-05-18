<?php
/**
 * Extra Stock Manager Class
 * Handles time-based stock management for extras
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Extra_Stock_Manager {
    
    private static $instance = null;
    private $wpdb;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Check if an extra is available for a specific time slot
     * 
     * @param int $extra_id Extra ID
     * @param string $booking_date Date in Y-m-d format
     * @param string $start_time Start time in H:i:s format
     * @param string $end_time End time in H:i:s format
     * @param int $quantity Required quantity
     * @param int $exclude_booking_id Optional booking ID to exclude from availability check (for editing)
     * @return array Availability status and available quantity
     */
    public function check_availability(int $extra_id, string $booking_date, string $start_time, string $end_time, int $quantity = 1, int $exclude_booking_id = 0, bool $bypass_locks = false): array {
        // Get extra details
        $extra = $this->get_extra_details($extra_id);
        if (!$extra) {
            return [
                'available' => false,
                'available_quantity' => 0,
                'reason' => 'Extra not found'
            ];
        }
        
        // Check for extra locks (unless bypassing locks for admin)
        $booking_start_datetime = $booking_date . ' ' . $start_time;
        $booking_end_datetime = $booking_date . ' ' . $end_time;
        
        if (!$bypass_locks) {
            $extra_locked = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}hrb_extra_locks 
                 WHERE extra_id = %d AND start_datetime <= %s AND end_datetime >= %s",
                $extra_id, $booking_end_datetime, $booking_start_datetime
            ));
            
            if ($extra_locked > 0) {
                return [
                    'available' => false,
                    'available_quantity' => 0,
                    'reason' => 'Extra is locked for this time period'
                ];
            }
            
            // Check for master extra locks
            $master_locked = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}hrb_master_extra_locks 
                 WHERE start_datetime <= %s AND end_datetime >= %s",
                $booking_end_datetime, $booking_start_datetime
            ));
            
            if ($master_locked > 0) {
                return [
                    'available' => false,
                    'available_quantity' => 0,
                    'reason' => 'All extras are locked for this time period'
                ];
            }
        }
        
        // If stock tracking is disabled, always available
        if (!$extra->track_stock) {
            return [
                'available' => true,
                'available_quantity' => 999,
                'reason' => 'Unlimited stock'
            ];
        }
        
        // Get total stock quantity
        $total_stock = intval($extra->stock_quantity);
        if ($total_stock <= 0) {
            return [
                'available' => false,
                'available_quantity' => 0,
                'reason' => 'Out of stock'
            ];
        }
        
        // Get currently booked quantity for this time slot
        $booked_quantity = $this->get_booked_quantity($extra_id, $booking_date, $start_time, $end_time, $exclude_booking_id);
        
        // Calculate available quantity
        $available_quantity = $total_stock - $booked_quantity;
        
        return [
            'available' => $available_quantity >= $quantity,
            'available_quantity' => max(0, $available_quantity),
            'total_stock' => $total_stock,
            'booked_quantity' => $booked_quantity,
            'reason' => $available_quantity >= $quantity ? 'Available' : 'Insufficient stock'
        ];
    }
    
    /**
     * Get available extras for a specific time slot
     * 
     * @param string $booking_date Date in Y-m-d format
     * @param string $start_time Start time in H:i:s format
     * @param string $end_time End time in H:i:s format
     * @param bool $include_inactive Whether to include inactive extras (for admin bookings)
     * @param int $exclude_booking_id Optional booking ID to exclude from availability check (for editing)
     * @return array Available extras with availability info
     */
    public function get_available_extras(string $booking_date, string $start_time, string $end_time, bool $include_inactive = false, int $exclude_booking_id = 0, bool $allow_locked = false): array {
        $extras_table = $this->wpdb->prefix . 'hrb_extras';
        
        // Build query - include inactive if requested (for admin)
        $where_clause = $include_inactive ? '1=1' : 'is_active = 1';
        $extras = $this->wpdb->get_results("
            SELECT * FROM {$extras_table} 
            WHERE {$where_clause} 
            ORDER BY sort_order ASC, name ASC
        ");
        
        $available_extras = [];
        
        foreach ($extras as $extra) {
            $availability = $this->check_availability(
                $extra->id, 
                $booking_date, 
                $start_time, 
                $end_time, 
                1,
                $exclude_booking_id,
                $allow_locked
            );
            
            // Include extra if:
            // For frontend: active + available (passes all checks: stock, locks, timing)
            // For admin: available (passes locks/timing checks) AND (active OR include_inactive is true)
            // Note: For admin with inactive extras, we still check locks (timing validation) but may allow even if stock issues
            if ($include_inactive && !$extra->is_active) {
                // Admin booking inactive extra: still check locks (timing validation) but skip stock validation
                // If locked, don't include it (timing conflict still applies)
                if ($availability['available']) {
                    // Available means no locks - include it (stock check skipped for inactive extras from admin)
                    $should_include = true;
                } else {
                    // Locked or other issues - don't include (timing validation still applies)
                    $should_include = false;
                }
            } else {
                // Normal case: active extra or frontend booking - must pass all validations (active + available)
                $should_include = $extra->is_active && $availability['available'];
            }
            
            if ($should_include) {
                $available_extras[] = [
                    'id' => $extra->id,
                    'name' => $extra->name,
                    'description' => $extra->description,
                    'price' => floatval($extra->price),
                    'image_url' => $extra->image_url,
                    'track_stock' => (bool) $extra->track_stock,
                    'available_quantity' => $availability['available_quantity'],
                    'total_stock' => $availability['total_stock'],
                    'sort_order' => intval($extra->sort_order),
                    'is_active' => (bool) $extra->is_active
                ];
            }
        }
        
        return $available_extras;
    }
    
    /**
     * Book an extra for a specific time slot
     * 
     * @param int $extra_id Extra ID
     * @param int $booking_id Booking ID
     * @param string $booking_date Date in Y-m-d format
     * @param string $start_time Start time in H:i:s format
     * @param string $end_time End time in H:i:s format
     * @param int $quantity Quantity to book
     * @return bool|WP_Error Success status or error
     */
    public function book_extra(int $extra_id, int $booking_id, string $booking_date, string $start_time, string $end_time, int $quantity = 1) {
        // This method is now handled by the main booking process
        // The booking_extras table is updated directly with time slot information
        return true;
    }
    
    /**
     * Cancel an extra booking
     * 
     * @param int $booking_id Booking ID
     * @return bool Success status
     */
    public function cancel_extra_booking(int $booking_id): bool {
        // This method is now handled by the main booking process
        // The booking_extras table records are deleted when booking is cancelled
        return true;
    }
    
    /**
     * Cancel all extra bookings for a booking
     * This should be called when a booking is cancelled or deleted
     * 
     * @param int $booking_id Booking ID
     * @return bool Success status
     */
    public function cancel_all_extra_bookings(int $booking_id): bool {
        return $this->cancel_extra_booking($booking_id);
    }
    
    /**
     * Get booked quantity for a specific time slot
     * 
     * @param int $extra_id Extra ID
     * @param string $booking_date Date in Y-m-d format
     * @param string $start_time Start time in H:i:s format
     * @param string $end_time End time in H:i:s format
     * @param int $exclude_booking_id Optional booking ID to exclude from count (for editing)
     * @return int Booked quantity
     */
    private function get_booked_quantity(int $extra_id, string $booking_date, string $start_time, string $end_time, int $exclude_booking_id = 0): int {
        $booking_extras_table = $this->wpdb->prefix . 'hrb_booking_extras';
        $bookings_table = $this->wpdb->prefix . 'hrb_bookings';
        
        // Check for overlapping time slots, only count confirmed/pending bookings
        // Exclude the specified booking ID if provided (for editing bookings)
        $exclude_condition = $exclude_booking_id > 0 ? 'AND be.booking_id != %d' : '';
        $params = [$extra_id, $booking_date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time];
        if ($exclude_booking_id > 0) {
            $params[] = $exclude_booking_id;
        }
        
        $booked_quantity = $this->wpdb->get_var($this->wpdb->prepare("
            SELECT COALESCE(SUM(be.quantity), 0) 
            FROM {$booking_extras_table} be
            INNER JOIN {$bookings_table} b ON be.booking_id = b.id
            WHERE be.extra_id = %d 
            AND be.booking_date = %s 
            AND b.status IN ('confirmed', 'pending')
            AND (
                (be.start_time < %s AND be.end_time > %s) OR  -- Overlaps with start
                (be.start_time < %s AND be.end_time > %s) OR  -- Overlaps with end
                (be.start_time >= %s AND be.end_time <= %s)   -- Completely within
            )
            {$exclude_condition}
        ", $params));
        
        return intval($booked_quantity);
    }
    
    /**
     * Get extra details
     * 
     * @param int $extra_id Extra ID
     * @return object|null Extra details or null if not found
     */
    private function get_extra_details(int $extra_id): ?object {
        $extras_table = $this->wpdb->prefix . 'hrb_extras';
        
        return $this->wpdb->get_row($this->wpdb->prepare("
            SELECT * FROM {$extras_table} WHERE id = %d
        ", $extra_id));
    }
    
    /**
     * Get extra booking statistics
     * 
     * @param int $extra_id Extra ID (optional)
     * @param string $start_date Start date (optional)
     * @param string $end_date End date (optional)
     * @return array Statistics
     */
    public function get_extra_statistics(int $extra_id = null, string $start_date = null, string $end_date = null): array {
        $extra_bookings_table = $this->wpdb->prefix . 'hrb_extra_bookings';
        $extras_table = $this->wpdb->prefix . 'hrb_extras';
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if ($extra_id) {
            $where_conditions[] = 'eb.extra_id = %d';
            $where_values[] = $extra_id;
        }
        
        if ($start_date) {
            $where_conditions[] = 'eb.booking_date >= %s';
            $where_values[] = $start_date;
        }
        
        if ($end_date) {
            $where_conditions[] = 'eb.booking_date <= %s';
            $where_values[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $stats = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT 
                e.id as extra_id,
                e.name as extra_name,
                e.stock_quantity,
                e.track_stock,
                COUNT(eb.id) as total_bookings,
                SUM(eb.quantity) as total_quantity_booked,
                AVG(eb.quantity) as avg_quantity_per_booking
            FROM {$extras_table} e
            LEFT JOIN {$extra_bookings_table} eb ON e.id = eb.extra_id
            WHERE {$where_clause}
            GROUP BY e.id, e.name, e.stock_quantity, e.track_stock
            ORDER BY e.name
        ", $where_values));
        
        return $stats;
    }
    
    /**
     * Get extra utilization for a specific date range
     * 
     * @param int $extra_id Extra ID
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Utilization data
     */
    public function get_extra_utilization(int $extra_id, string $start_date, string $end_date): array {
        $extra_bookings_table = $this->wpdb->prefix . 'hrb_extra_bookings';
        
        $utilization = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT 
                booking_date,
                SUM(quantity) as daily_usage,
                COUNT(DISTINCT booking_id) as unique_bookings
            FROM {$extra_bookings_table}
            WHERE extra_id = %d 
            AND booking_date BETWEEN %s AND %s
            GROUP BY booking_date
            ORDER BY booking_date
        ", $extra_id, $start_date, $end_date));
        
        return $utilization;
    }
}
