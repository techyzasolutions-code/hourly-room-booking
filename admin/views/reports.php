<?php
/**
 * Reports and Analytics View
 * Comprehensive analytics dashboard with charts, statistics, and reports
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get managers
$booking_manager = HRB_Booking_Manager::getInstance();
$payment_manager = HRB_Payment_Manager::getInstance();
$room_manager = HRB_Room_Manager::getInstance();

// Handle date range - default to current month for better data visibility
$date_range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : 'this_month';
$custom_start = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
$custom_end = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

// Set dates based on selected range
switch ($date_range) {
    case '7_days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        break;
    case '30_days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
        break;
    case '90_days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $end_date = date('Y-m-d');
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('last month'));
        $end_date = date('Y-m-t', strtotime('last month'));
        break;
    case 'this_year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'custom':
        if (!empty($custom_start) && !empty($custom_end)) {
            $start_date = $custom_start;
            $end_date = $custom_end;
        } else {
            // Fallback to current month if custom dates are empty
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
        }
        break;
    default:
        // Default to current month
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
}

// Get real analytics data from database
global $wpdb;

// Get basic booking statistics
$booking_stats = $wpdb->get_row($wpdb->prepare("
    SELECT
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        AVG(total_hours) as avg_duration,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_booking_value
    FROM {$wpdb->prefix}hrb_bookings
    WHERE booking_date BETWEEN %s AND %s
", $start_date, $end_date));

// Get unique customers count
$unique_customers = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(DISTINCT customer_id)
    FROM {$wpdb->prefix}hrb_bookings
    WHERE booking_date BETWEEN %s AND %s
", $start_date, $end_date));

// Get peak booking hours
$peak_hours_raw = $wpdb->get_results($wpdb->prepare("
    SELECT
        HOUR(start_time) as hour,
        COUNT(*) as booking_count
    FROM {$wpdb->prefix}hrb_bookings
    WHERE booking_date BETWEEN %s AND %s AND status NOT IN ('cancelled')
    GROUP BY HOUR(start_time)
    ORDER BY booking_count DESC
    LIMIT 6
", $start_date, $end_date));

// Format peak hours
$peak_hours = [];
$total_peak_bookings = array_sum(array_column($peak_hours_raw, 'booking_count'));
foreach ($peak_hours_raw as $hour_data) {
    $start_hour = sprintf('%02d:00', $hour_data->hour);
    $end_hour = sprintf('%02d:00', $hour_data->hour + 1);
    $percentage = $total_peak_bookings > 0 ? ($hour_data->booking_count / $total_peak_bookings) * 100 : 0;
    $peak_hours[] = (object)[
        'time_slot' => $start_hour . '-' . $end_hour,
        'booking_count' => $hour_data->booking_count,
        'percentage' => $percentage
    ];
}

// Get daily booking data for chart
$daily_bookings = $wpdb->get_results($wpdb->prepare("
    SELECT
        DATE(booking_date) as date,
        COUNT(*) as count
    FROM {$wpdb->prefix}hrb_bookings
    WHERE booking_date BETWEEN %s AND %s
    GROUP BY DATE(booking_date)
    ORDER BY date
", $start_date, $end_date));

// Format chart data
$booking_labels = [];
$booking_data = [];
foreach ($daily_bookings as $day) {
    $booking_labels[] = date('D', strtotime($day->date));
    $booking_data[] = (int)$day->count;
}

// Calculate rates
$cancellation_rate = $booking_stats->total_bookings > 0 ?
    ($booking_stats->cancelled_bookings / $booking_stats->total_bookings) * 100 : 0;

// Calculate occupancy rate
$occupancy_rate = 0;
if ($booking_stats->total_bookings > 0) {
    // Get total booked time slots (confirmed bookings)
    $total_booked_slots = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}hrb_bookings
        WHERE booking_date BETWEEN %s AND %s
        AND status IN ('confirmed', 'completed')
    ", $start_date, $end_date));
    
    // Get total possible time slots (assuming 8-hour workday, 7 days a week)
    $days_in_period = (strtotime($end_date) - strtotime($start_date)) / (24 * 60 * 60) + 1;
    $hours_per_day = 8; // Assuming 8-hour workday
    $total_rooms = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hrb_rooms WHERE is_active = 1");
    $total_possible_slots = $days_in_period * $hours_per_day * $total_rooms;
    
    // Calculate occupancy rate
    if ($total_possible_slots > 0) {
        $occupancy_rate = ($total_booked_slots / $total_possible_slots) * 100;
    }
}

$analytics_data = [
    'total_bookings' => (int)($booking_stats->total_bookings ?? 0),
    'bookings_growth' => 0, // Would need historical comparison
    'occupancy_rate' => $occupancy_rate,
    'occupancy_growth' => 0,
    'unique_customers' => (int)($unique_customers ?? 0),
    'customers_growth' => 0, // Would need historical comparison
    'avg_booking_value' => (float)($booking_stats->avg_booking_value ?? 0),
    'repeat_customer_rate' => 0, // Would need repeat customer analysis
    'avg_booking_duration' => (float)($booking_stats->avg_duration ?? 0),
    'cancellation_rate' => $cancellation_rate,
    'peak_hours' => $peak_hours,
    'booking_labels' => $booking_labels,
    'booking_data' => $booking_data,
    'status_labels' => ['Confirmed', 'Pending', 'Cancelled', 'Completed'],
    'status_data' => [
        (int)($booking_stats->confirmed_bookings ?? 0),
        (int)($booking_stats->pending_bookings ?? 0),
        (int)($booking_stats->cancelled_bookings ?? 0),
        (int)($booking_stats->completed_bookings ?? 0)
    ]
];

// Get daily revenue data
$daily_revenue = $wpdb->get_results($wpdb->prepare("
    SELECT
        DATE(booking_date) as date,
        SUM(total_amount) as revenue
    FROM {$wpdb->prefix}hrb_bookings
    WHERE booking_date BETWEEN %s AND %s AND status NOT IN ('cancelled')
    GROUP BY DATE(booking_date)
    ORDER BY date
", $start_date, $end_date));

// Format revenue chart data
$revenue_labels = [];
$revenue_data_values = [];
foreach ($daily_revenue as $day) {
    $revenue_labels[] = date('D', strtotime($day->date));
    $revenue_data_values[] = (float)$day->revenue;
}

$revenue_data = [
    'total_revenue' => (float)($booking_stats->total_revenue ?? 0),
    'revenue_growth' => 0, // Would need historical comparison
    'revenue_labels' => $revenue_labels,
    'revenue_data' => $revenue_data_values
];

// Get room performance data
$room_performance = $wpdb->get_results($wpdb->prepare("
    SELECT
        r.name as room_name, r.id as id,
        COUNT(b.id) as booking_count,
        SUM(CASE WHEN b.status NOT IN ('cancelled') THEN b.total_amount ELSE 0 END) as total_revenue,
        AVG(CASE WHEN b.status NOT IN ('cancelled') THEN b.total_hours ELSE NULL END) as avg_duration
    FROM {$wpdb->prefix}hrb_rooms r
    LEFT JOIN {$wpdb->prefix}hrb_bookings b ON r.id = b.room_id
        AND b.booking_date BETWEEN %s AND %s
    WHERE r.is_active = 1
    GROUP BY r.id, r.name
    ORDER BY booking_count DESC
", $start_date, $end_date));

// Calculate occupancy rates for each room
foreach ($room_performance as $room) {
    
    // Get booked time slots for this room (confirmed bookings only)
    $room_booked_slots = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}hrb_bookings
        WHERE room_id = %d
        AND booking_date BETWEEN %s AND %s
        AND status IN ('confirmed', 'completed')
    ", $room->id, $start_date, $end_date));
    
    // Get total possible time slots for this room (8 hours per day)
    $days_in_period = (strtotime($end_date) - strtotime($start_date)) / (24 * 60 * 60) + 1;
    $hours_per_day = 8; // Assuming 8-hour workday
    $room_possible_slots = $days_in_period * $hours_per_day;
    
    // Calculate occupancy rate for this room
    if ($room_possible_slots > 0) {
        $room->occupancy_rate = ($room_booked_slots / $room_possible_slots) * 100;
    } else {
        $room->occupancy_rate = 0;
    }
}

// Get currency symbol from settings
$currency_symbol = hrb_get_currency_symbol();
?>

<div class="hrb-admin-page">
    <div class="hrb-page-header">
        <div class="hrb-page-title">
            <h1><?php _e('Reports & Analytics', 'hourly-room-booking'); ?></h1>
            <p class="description"><?php _e('Comprehensive analytics and reports for your room booking business.', 'hourly-room-booking'); ?></p>
        </div>
        <div class="hrb-page-actions">
            <!-- <button type="button" class="button" onclick="exportReport()">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export Report', 'hourly-room-booking'); ?>
            </button> -->
            <button type="button" class="button button-primary" onclick="printReport()">
                <span class="dashicons dashicons-printer"></span>
                <?php _e('Print Report', 'hourly-room-booking'); ?>
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="hrb-date-range-filter">
        <form method="get" class="hrb-filter-form">
            <input type="hidden" name="page" value="hrb-reports">

            <div class="hrb-filter-group">
                <label for="date-range"><?php _e('Date Range:', 'hourly-room-booking'); ?></label>
                <select name="range" id="date-range" onchange="toggleCustomDates(this.value)">
                    <option value="this_month" <?php selected($date_range, 'this_month'); ?>><?php _e('This Month', 'hourly-room-booking'); ?></option>
                    <option value="7_days" <?php selected($date_range, '7_days'); ?>><?php _e('Last 7 Days', 'hourly-room-booking'); ?></option>
                    <option value="30_days" <?php selected($date_range, '30_days'); ?>><?php _e('Last 30 Days', 'hourly-room-booking'); ?></option>
                    <option value="90_days" <?php selected($date_range, '90_days'); ?>><?php _e('Last 90 Days', 'hourly-room-booking'); ?></option>
                    <option value="last_month" <?php selected($date_range, 'last_month'); ?>><?php _e('Last Month', 'hourly-room-booking'); ?></option>
                    <option value="this_year" <?php selected($date_range, 'this_year'); ?>><?php _e('This Year', 'hourly-room-booking'); ?></option>
                    <option value="custom" <?php selected($date_range, 'custom'); ?>><?php _e('Custom Range', 'hourly-room-booking'); ?></option>
                </select>
            </div>

            <div id="custom-dates" class="hrb-custom-dates" style="display: <?php echo $date_range === 'custom' ? 'flex' : 'none'; ?>;">
                <div class="hrb-filter-group">
                    <label for="start-date"><?php _e('From:', 'hourly-room-booking'); ?></label>
                    <input type="date" name="start_date" id="start-date" value="<?php echo esc_attr($start_date); ?>">
                </div>
                <div class="hrb-filter-group">
                    <label for="end-date"><?php _e('To:', 'hourly-room-booking'); ?></label>
                    <input type="date" name="end_date" id="end-date" value="<?php echo esc_attr($end_date); ?>">
                </div>
            </div>

            <div class="hrb-filter-actions">
                <button type="submit" class="button"><?php _e('Apply Filter', 'hourly-room-booking'); ?></button>
            </div>
        </form>
    </div>

    <script>
    // Auto-update date fields when predefined range is selected
    document.getElementById('date-range').addEventListener('change', function() {
        const range = this.value;
        const startDateField = document.getElementById('start-date');
        const endDateField = document.getElementById('end-date');

        let startDate = '';
        let endDate = '';

        const today = new Date();

        switch(range) {
            case '7_days':
                startDate = new Date(today.getTime() - (7 * 24 * 60 * 60 * 1000));
                endDate = today;
                break;
            case '30_days':
                startDate = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
                endDate = today;
                break;
            case '90_days':
                startDate = new Date(today.getTime() - (90 * 24 * 60 * 60 * 1000));
                endDate = today;
                break;
            case 'this_month':
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                break;
            case 'last_month':
                startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'this_year':
                startDate = new Date(today.getFullYear(), 0, 1);
                endDate = new Date(today.getFullYear(), 11, 31);
                break;
        }

        if (startDate && endDate && range !== 'custom') {
            startDateField.value = startDate.toISOString().split('T')[0];
            endDateField.value = endDate.toISOString().split('T')[0];
        }

        toggleCustomDates(range);
    });

    // Initialize date fields on page load
    document.addEventListener('DOMContentLoaded', function() {
        const currentRange = document.getElementById('date-range').value;
        if (currentRange !== 'custom') {
            // Update the date fields to match the selected range
            document.getElementById('date-range').dispatchEvent(new Event('change'));
        }
    });
    </script>

    <!-- Overview Statistics -->
    <div class="hrb-overview-stats">
        <div class="hrb-stats-grid">
            <div class="hrb-stat-card">
                <div class="hrb-stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="hrb-stat-content">
                    <div class="hrb-stat-number"><?php echo intval($analytics_data['total_bookings'] ?? 0); ?></div>
                    <div class="hrb-stat-label"><?php _e('Total Bookings', 'hourly-room-booking'); ?></div>
                    <!-- <div class="hrb-stat-change positive">
                        +<?php echo intval($analytics_data['bookings_growth'] ?? 0); ?>% <?php _e('vs previous period', 'hourly-room-booking'); ?>
                    </div> -->
                </div>
            </div>

            <div class="hrb-stat-card">
                <div class="hrb-stat-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="hrb-stat-content">
                    <div class="hrb-stat-number"><?php echo esc_html($currency_symbol); ?><?php echo number_format($revenue_data['total_revenue'] ?? 0, 2); ?></div>
                    <div class="hrb-stat-label"><?php _e('Total Revenue', 'hourly-room-booking'); ?></div>
                    <!-- <div class="hrb-stat-change positive">
                        +<?php echo number_format($revenue_data['revenue_growth'] ?? 0, 1); ?>% <?php _e('vs previous period', 'hourly-room-booking'); ?>
                    </div> -->
                </div>
            </div>

            <div class="hrb-stat-card">
                <div class="hrb-stat-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="hrb-stat-content">
                    <div class="hrb-stat-number"><?php echo number_format($analytics_data['occupancy_rate'] ?? 0, 1); ?>%</div>
                    <div class="hrb-stat-label"><?php _e('Occupancy Rate', 'hourly-room-booking'); ?></div>
                    <!-- <div class="hrb-stat-change <?php echo ($analytics_data['occupancy_growth'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo ($analytics_data['occupancy_growth'] ?? 0) >= 0 ? '+' : ''; ?><?php echo number_format($analytics_data['occupancy_growth'] ?? 0, 1); ?>% <?php _e('vs previous period', 'hourly-room-booking'); ?>
                    </div> -->
                </div>
            </div>

            <div class="hrb-stat-card">
                <div class="hrb-stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="hrb-stat-content">
                    <div class="hrb-stat-number"><?php echo intval($analytics_data['unique_customers'] ?? 0); ?></div>
                    <div class="hrb-stat-label"><?php _e('Unique Customers', 'hourly-room-booking'); ?></div>
                    <!-- <div class="hrb-stat-change positive">
                        +<?php echo intval($analytics_data['customers_growth'] ?? 0); ?>% <?php _e('vs previous period', 'hourly-room-booking'); ?>
                    </div> -->
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="hrb-charts-section">
        <div class="hrb-charts-grid">
            <!-- Bookings Trend Chart -->
            <div class="hrb-chart-card">
                <div class="hrb-chart-header">
                    <h3><?php _e('Bookings Trend', 'hourly-room-booking'); ?></h3>
                    <div class="hrb-chart-controls">
                        <select id="bookings-chart-type">
                            <option value="daily"><?php _e('Daily', 'hourly-room-booking'); ?></option>
                            <option value="weekly"><?php _e('Weekly', 'hourly-room-booking'); ?></option>
                            <option value="monthly"><?php _e('Monthly', 'hourly-room-booking'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="hrb-chart-container">
                    <canvas id="bookings-trend-chart"></canvas>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="hrb-chart-card">
                <div class="hrb-chart-header">
                    <h3><?php _e('Revenue Analysis', 'hourly-room-booking'); ?></h3>
                    <div class="hrb-chart-controls">
                        <select id="revenue-chart-type">
                            <option value="daily"><?php _e('Daily', 'hourly-room-booking'); ?></option>
                            <option value="weekly"><?php _e('Weekly', 'hourly-room-booking'); ?></option>
                            <option value="monthly"><?php _e('Monthly', 'hourly-room-booking'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="hrb-chart-container">
                    <canvas id="revenue-chart"></canvas>
                </div>
            </div>
        </div>

        <div class="hrb-charts-grid">
            <!-- Room Performance Chart -->
            <div class="hrb-chart-card">
                <div class="hrb-chart-header">
                    <h3><?php _e('Room Performance', 'hourly-room-booking'); ?></h3>
                </div>
                <div class="hrb-chart-container">
                    <canvas id="room-performance-chart"></canvas>
                </div>
            </div>

            <!-- Booking Status Distribution -->
            <div class="hrb-chart-card">
                <div class="hrb-chart-header">
                    <h3><?php _e('Booking Status Distribution', 'hourly-room-booking'); ?></h3>
                </div>
                <div class="hrb-chart-container">
                    <canvas id="status-distribution-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Analytics Tables -->
    <div class="hrb-analytics-tables">
        <div class="hrb-tables-grid">
            <!-- Top Performing Rooms -->
            <div class="hrb-analytics-table">
                <div class="hrb-table-header">
                    <h3><?php _e('Top Performing Rooms', 'hourly-room-booking'); ?></h3>
                </div>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Room', 'hourly-room-booking'); ?></th>
                            <th><?php _e('Bookings', 'hourly-room-booking'); ?></th>
                            <th><?php _e('Revenue', 'hourly-room-booking'); ?></th>
                            <th><?php _e('Occupancy', 'hourly-room-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($room_performance)): ?>
                            <?php foreach (array_slice($room_performance, 0, 5) as $room): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($room->room_name); ?></strong></td>
                                    <td><?php echo intval($room->booking_count); ?></td>
                                    <td><?php echo esc_html($currency_symbol); ?><?php echo number_format($room->total_revenue, 2); ?></td>
                                    <td><?php echo number_format($room->occupancy_rate, 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4"><?php _e('No data available for selected period.', 'hourly-room-booking'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Peak Booking Hours -->
            <div class="hrb-analytics-table">
                <div class="hrb-table-header">
                    <h3><?php _e('Peak Booking Hours', 'hourly-room-booking'); ?></h3>
                </div>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time Slot', 'hourly-room-booking'); ?></th>
                            <th><?php _e('Bookings', 'hourly-room-booking'); ?></th>
                            <th><?php _e('Percentage', 'hourly-room-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $peak_hours = $analytics_data['peak_hours'] ?? [];
                        if (!empty($peak_hours)):
                        ?>
                            <?php foreach (array_slice($peak_hours, 0, 6) as $hour): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($hour->time_slot); ?></strong></td>
                                    <td><?php echo intval($hour->booking_count); ?></td>
                                    <td><?php echo number_format($hour->percentage, 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3"><?php _e('No data available for selected period.', 'hourly-room-booking'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Customer Analytics -->
    <div class="hrb-customer-analytics">
        <div class="hrb-section-header">
            <h3><?php _e('Customer Analytics', 'hourly-room-booking'); ?></h3>
        </div>

        <div class="hrb-customer-stats-grid">
            <div class="hrb-customer-stat-card">
                <div class="hrb-stat-number"><?php echo esc_html($currency_symbol); ?><?php echo number_format($analytics_data['avg_booking_value'] ?? 0, 2); ?></div>
                <div class="hrb-stat-label"><?php _e('Average Booking Value', 'hourly-room-booking'); ?></div>
            </div>

            <div class="hrb-customer-stat-card">
                <div class="hrb-stat-number"><?php echo number_format($analytics_data['repeat_customer_rate'] ?? 0, 1); ?>%</div>
                <div class="hrb-stat-label"><?php _e('Repeat Customer Rate', 'hourly-room-booking'); ?></div>
            </div>

            <div class="hrb-customer-stat-card">
                <div class="hrb-stat-number"><?php echo number_format($analytics_data['avg_booking_duration'] ?? 0, 1); ?>h</div>
                <div class="hrb-stat-label"><?php _e('Average Booking Duration', 'hourly-room-booking'); ?></div>
            </div>

            <div class="hrb-customer-stat-card">
                <div class="hrb-stat-number"><?php echo number_format($analytics_data['cancellation_rate'] ?? 0, 1); ?>%</div>
                <div class="hrb-stat-label"><?php _e('Cancellation Rate', 'hourly-room-booking'); ?></div>
            </div>
        </div>
    </div>
</div>

<style>
/* HRB Reports - Modern Professional Styling */
.hrb-admin-page {
    max-width: 1400px;
    background: #f8f9fa;
    min-height: 100vh;
    
    padding: 24px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Page Header */
.hrb-page-header {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white ;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 32px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 32px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.hrb-page-title h1 {
    color: #fff;
    font-size: 32px;
    font-weight: 800;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* .hrb-page-title h1::before {
    content: '';
    width: 4px;
    height: 32px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    border-radius: 2px;
} */

.hrb-page-title .description {
    color: #fff;
    font-size: 1.1rem;
    margin: 0;
    font-weight: 500;
}

.hrb-page-actions {
    display: flex;
    gap: 12px;
}

.hrb-page-actions .button {
    border-radius: 12px;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.hrb-page-actions .button-primary {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.hrb-page-actions .button-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

.hrb-page-actions .button:not(.button-primary) {
    background: white;
    color: #475569;
    border-color: #e2e8f0;
}

.hrb-page-actions .button:not(.button-primary):hover {
    border-color: #3b82f6;
    color: #3b82f6;
    transform: translateY(-1px);
}

/* Date Range Filter */
.hrb-date-range-filter {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 32px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.hrb-filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: end;
}

.hrb-filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.hrb-filter-group label {
    font-weight: 700;
    font-size: 13px;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hrb-filter-group select,
.hrb-filter-group input {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 14px;
    transition: all 0.2s ease;
    background: white;
}

.hrb-filter-group select:focus,
.hrb-filter-group input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

.hrb-custom-dates {
    display: flex;
    gap: 20px;
}

.hrb-filter-actions .button {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.hrb-filter-actions .button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

/* Overview Stats */
.hrb-overview-stats {
    margin-bottom: 40px;
}

.hrb-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
}

.hrb-stat-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    /* padding: 28px; */
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

.hrb-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
    border-radius: 16px 16px 0 0;
}

.hrb-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: #3b82f6;
}

.hrb-stat-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.hrb-stat-content {
    flex: 1;
}

.hrb-stat-number {
    font-size: 32px;
    font-weight: 900;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 4px;
}

.hrb-stat-label {
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hrb-stat-change {
    font-size: 12px;
    font-weight: 700;
    margin-top: 8px;
    padding: 4px 8px;
    border-radius: 12px;
    display: inline-block;
}

.hrb-stat-change.positive {
    color: #059669;
    background: #d1fae5;
}

.hrb-stat-change.negative {
    color: #dc2626;
    background: #fee2e2;
}

/* Charts Section */
.hrb-charts-section {
    margin-bottom: 40px;
}

.hrb-charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 24px;
    margin-bottom: 24px;
}

.hrb-chart-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.hrb-chart-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.hrb-chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f1f5f9;
}

.hrb-chart-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.hrb-chart-header h3::before {
    content: '';
    width: 8px;
    height: 8px;
    background: #3b82f6;
    border-radius: 50%;
}

.hrb-chart-container {
    height: 320px;
    position: relative;
}

/* Analytics Tables */
.hrb-analytics-tables {
    margin-bottom: 40px;
}

.hrb-tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 24px;
}

.hrb-analytics-table {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.hrb-analytics-table:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.hrb-table-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
}

.hrb-table-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.hrb-table-header h3::before {
    content: '';
    width: 6px;
    height: 6px;
    background: #3b82f6;
    border-radius: 50%;
}

.hrb-analytics-table table {
    margin: 0;
    border: none;
    width: 100%;
    border-collapse: collapse;
}

.hrb-analytics-table th {
    background: #f8fafc;
    padding: 16px 24px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}

.hrb-analytics-table td {
    padding: 16px 24px;
    border-bottom: 1px solid #f1f5f9;
    color: #374151;
    font-weight: 500;
}

.hrb-analytics-table tr:hover {
    background: #f8fafc;
}

/* Customer Analytics */
.hrb-customer-analytics {
    margin-bottom: 40px;
}

.hrb-section-header {
    margin-bottom: 24px;
    padding: 20px 0;
    border-bottom: 2px solid #e2e8f0;
}

.hrb-section-header h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 800;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}

.hrb-section-header h3::before {
    content: '';
    width: 4px;
    height: 24px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 2px;
}

.hrb-customer-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.hrb-customer-stat-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.hrb-customer-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #10b981, #059669);
    border-radius: 16px 16px 0 0;
}

.hrb-customer-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: #10b981;
}

.hrb-customer-stat-card .hrb-stat-number {
    font-size: 28px;
    font-weight: 800;
    color: #10b981;
    margin-bottom: 8px;
}

.hrb-customer-stat-card .hrb-stat-label {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .hrb-charts-grid {
        grid-template-columns: 1fr;
    }

    .hrb-tables-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .hrb-admin-page {
        padding: 16px;
        margin: -20px -10px -20px -2px;
    }

    .hrb-page-header {
        flex-direction: column;
        gap: 20px;
        padding: 24px;
    }

    .hrb-filter-form {
        flex-direction: column;
        align-items: stretch;
    }

    .hrb-custom-dates {
        flex-direction: column;
    }

    .hrb-stats-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .hrb-stat-card {
        padding: 20px;
    }

    .hrb-stat-number {
        font-size: 28px;
    }

    .hrb-customer-stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 16px;
    }
}

/* Print Styles */
@media print {
    .hrb-page-actions,
    .hrb-date-range-filter {
        display: none;
    }

    .hrb-admin-page {
        background: white;
        margin: 0;
        padding: 20px;
    }

    .hrb-stat-card,
    .hrb-chart-card,
    .hrb-analytics-table {
        box-shadow: none;
        border: 1px solid #000;
        break-inside: avoid;
    }

    .hrb-page-header {
        background: white;
        box-shadow: none;
    }
}
</style>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

<script>
// Date range toggle
function toggleCustomDates(value) {
    const customDates = document.getElementById('custom-dates');
    customDates.style.display = value === 'custom' ? 'flex' : 'none';
}

// Export functionality
function exportReport() {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('action', 'export_report');
    urlParams.set('nonce', '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>');

    window.location.href = ajaxurl + '?' + urlParams.toString();
}

// Print functionality
function printReport() {
    window.print();
}

// Chart data
const chartData = {
    bookings: {
        labels: <?php echo json_encode($analytics_data['booking_labels'] ?? []); ?>,
        data: <?php echo json_encode($analytics_data['booking_data'] ?? []); ?>
    },
    revenue: {
        labels: <?php echo json_encode($revenue_data['revenue_labels'] ?? []); ?>,
        data: <?php echo json_encode($revenue_data['revenue_data'] ?? []); ?>
    },
    rooms: {
        labels: <?php echo json_encode(array_column($room_performance ?? [], 'room_name')); ?>,
        data: <?php echo json_encode(array_column($room_performance ?? [], 'booking_count')); ?>
    },
    status: {
        labels: <?php echo json_encode($analytics_data['status_labels'] ?? []); ?>,
        data: <?php echo json_encode($analytics_data['status_data'] ?? []); ?>
    }
};

// Initialize charts when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
});

function initializeCharts() {
    // Bookings Trend Chart
    const bookingsCtx = document.getElementById('bookings-trend-chart');
    if (bookingsCtx) {
        new Chart(bookingsCtx, {
            type: 'line',
            data: {
                labels: chartData.bookings.labels,
                datasets: [{
                    label: '<?php _e('Bookings', 'hourly-room-booking'); ?>',
                    data: chartData.bookings.data,
                    borderColor: '#0073aa',
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Revenue Chart
    const revenueCtx = document.getElementById('revenue-chart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: chartData.revenue.labels,
                datasets: [{
                    label: '<?php printf(__('Revenue (%s)', 'hourly-room-booking'), esc_js($currency_symbol)); ?>',
                    data: chartData.revenue.data,
                    backgroundColor: '#28a745',
                    borderColor: '#28a745',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Room Performance Chart
    const roomCtx = document.getElementById('room-performance-chart');
    if (roomCtx) {
        new Chart(roomCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.rooms.labels,
                datasets: [{
                    data: chartData.rooms.data,
                    backgroundColor: [
                        '#0073aa',
                        '#28a745',
                        '#ffc107',
                        '#dc3545',
                        '#6c757d',
                        '#17a2b8'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Status Distribution Chart
    const statusCtx = document.getElementById('status-distribution-chart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: chartData.status.labels,
                datasets: [{
                    data: chartData.status.data,
                    backgroundColor: [
                        '#28a745', // confirmed
                        '#ffc107', // pending
                        '#dc3545', // cancelled
                        '#6c757d'  // completed
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Chart type change handlers
document.getElementById('bookings-chart-type')?.addEventListener('change', function() {
    // Reload chart with new data type
    // This would trigger an AJAX call to get data for the selected period
});

document.getElementById('revenue-chart-type')?.addEventListener('change', function() {
    // Reload chart with new data type
    // This would trigger an AJAX call to get data for the selected period
});
</script>