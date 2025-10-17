<?php
/**
 * Admin Dashboard View
 *
 * @package HourlyRoomBooking
 * @subpackage Admin/Views
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct script access denied.');
}

// Get dashboard data
$dashboard_stats = HRB_Admin::getInstance()->get_dashboard_stats();
$recent_bookings = HRB_Admin::getInstance()->get_recent_bookings(5);
$booking_manager = HRB_Booking_Manager::getInstance();
$room_manager = HRB_Room_Manager::getInstance();

// Current date range (today and this month)
$today = date('Y-m-d');
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
?>

<div class="wrap hrb-admin-dashboard">
    <h1 class="wp-heading-inline">
        <?php _e('Room Booking Dashboard', 'hourly-room-booking'); ?>
    </h1>

    
    <!-- Dashboard Statistics -->
    <div class="hrb-dashboard-stats">
        <div class="hrb-stats-grid">
            <!-- Today's Bookings -->
            <div class="hrb-stat-card hrb-stat-today">
                <div class="hrb-stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="hrb-stat-content">
                    <div class="hrb-stat-number">
                        <?php echo esc_html($dashboard_stats['today_bookings'] ?? 0); ?>
                    </div>
                    <div class="hrb-stat-label">
                        <?php _e("Today's Bookings", 'hourly-room-booking'); ?>
                    </div>
                </div>
            </div>

            <!-- This Month's Revenue -->
            <div class="hrb-stat-card hrb-stat-revenue">
                <div class="hrb-stat-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="hrb-stat-content">
                    <div class="hrb-stat-number">
                        <?php echo hrb_format_amount($dashboard_stats['month_revenue'] ?? 0); ?>
                    </div>
                    <div class="hrb-stat-label">
                        <?php _e('This Month Revenue', 'hourly-room-booking'); ?>
                    </div>
                </div>
            </div>

            <!-- Total Rooms -->
            <div class="hrb-stat-card hrb-stat-rooms">
                <div class="hrb-stat-icon">
                    <span class="dashicons dashicons-admin-home"></span>
                </div>
                <div class="hrb-stat-content">
                    <div class="hrb-stat-number">
                        <?php echo esc_html($dashboard_stats['total_rooms'] ?? 0); ?>
                    </div>
                    <div class="hrb-stat-label">
                        <?php _e('Active Rooms', 'hourly-room-booking'); ?>
                    </div>
                </div>
            </div>

            <!-- Pending Payments -->
            <div class="hrb-stat-card hrb-stat-pending">
                <div class="hrb-stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="hrb-stat-content">
                    <div class="hrb-stat-number">
                        <?php echo esc_html($dashboard_stats['pending_payments'] ?? 0); ?>
                    </div>
                    <div class="hrb-stat-label">
                        <?php _e('Pending Payments', 'hourly-room-booking'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Content Grid -->
    <div class="hrb-dashboard-content">
        <div class="hrb-dashboard-main">
            <!-- Recent Bookings -->
            <div class="hrb-dashboard-section">
                <div class="hrb-section-header">
                    <h2><?php _e('Recent Bookings', 'hourly-room-booking'); ?></h2>
                    <a href="<?php echo admin_url('admin.php?page=hrb-bookings'); ?>" class="button">
                        <?php _e('View All', 'hourly-room-booking'); ?>
                    </a>
                </div>

                <div class="hrb-table-container">
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Booking ID', 'hourly-room-booking'); ?></th>
                                <th><?php _e('Customer', 'hourly-room-booking'); ?></th>
                                <th><?php _e('Room', 'hourly-room-booking'); ?></th>
                                <th><?php _e('Date & Time', 'hourly-room-booking'); ?></th>
                                <th><?php _e('Status', 'hourly-room-booking'); ?></th>
                                <th><?php _e('Amount', 'hourly-room-booking'); ?></th>
                                <th><?php _e('Actions', 'hourly-room-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_bookings)): ?>
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo esc_html($booking['booking_reference']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="hrb-customer-info">
                                                <strong><?php echo esc_html($booking['customer_name']); ?></strong><br>
                                                <small><?php echo esc_html($booking['customer_email']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo esc_html($booking['room_name']); ?>
                                        </td>
                                        <td>
                                            <div class="hrb-datetime">
                                                <strong><?php echo date('d.m.Y', strtotime($booking['booking_date'])); ?></strong><br>
                                                <small><?php echo date('H:i', strtotime($booking['start_time'])) . ' - ' . date('H:i', strtotime($booking['end_time'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo HRB_Admin::getInstance()->get_status_badge($booking['status']); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo hrb_format_amount($booking['total_amount']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="hrb-actions">
                                                <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=view&id=' . $booking['id']); ?>"
                                                   class="button button-small" title="<?php _e('View Details', 'hourly-room-booking'); ?>">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </a>
                                                <?php if ($booking['status'] === 'pending1'): ?>
                                                    <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=confirm&id=' . $booking['id']); ?>"
                                                       class="button button-primary button-small" title="<?php _e('Confirm', 'hourly-room-booking'); ?>">
                                                        <span class="dashicons dashicons-yes"></span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="hrb-no-data">
                                        <?php _e('No recent bookings found.', 'hourly-room-booking'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Stats Chart -->
            <div class="hrb-dashboard-section">
                <div class="hrb-section-header">
                    <h2><?php _e('Booking Trends (Last 30 Days)', 'hourly-room-booking'); ?></h2>
                </div>
                <div class="hrb-chart-container">
                    <canvas id="hrbBookingChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <div class="hrb-dashboard-sidebar">
            <!-- Quick Actions -->
            <div class="hrb-dashboard-section hrb-quick-actions">
                <h3><?php _e('Quick Actions', 'hourly-room-booking'); ?></h3>
                <div class="hrb-action-buttons">
                    <?php if (current_user_can('hrb_manage_bookings')): ?>
                    <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=add'); ?>"
                       class="button button-primary button-large hrb-action-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('New Booking', 'hourly-room-booking'); ?>
                    </a>
                    <?php endif; ?>

                    <?php if (current_user_can('hrb_manage_rooms')): ?>
                    <a href="<?php echo admin_url('admin.php?page=hrb-rooms&action=add'); ?>"
                       class="button button-secondary button-large hrb-action-btn">
                        <span class="dashicons dashicons-admin-home"></span>
                        <?php _e('Add Room', 'hourly-room-booking'); ?>
                    </a>
                    <?php endif; ?>

                    <a href="<?php echo admin_url('admin.php?page=hrb-calendar'); ?>"
                       class="button button-secondary button-large hrb-action-btn">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php _e('View Calendar', 'hourly-room-booking'); ?>
                    </a>

                    <a href="<?php echo admin_url('admin.php?page=hrb-reports'); ?>"
                       class="button button-secondary button-large hrb-action-btn">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php _e('View Reports', 'hourly-room-booking'); ?>
                    </a>
                </div>
            </div>

            <!-- Room Status Overview -->
            <div class="hrb-dashboard-section hrb-room-status">
                <h3><?php _e('Room Status', 'hourly-room-booking'); ?></h3>
                <div class="hrb-room-list">
                    <?php
                    $rooms = $room_manager->get_all_rooms(true);
                    if (!empty($rooms)):
                        foreach ($rooms as $room):
                            $room_bookings_today = $booking_manager->get_room_bookings_count($room->id, $today);
                            $is_available = $room_manager->is_room_available($room->id, $today, date('H:i:s'), date('H:i:s', strtotime('+2 hours')));
                    ?>
                        <div class="hrb-room-item">
                            <div class="hrb-room-info">
                                <strong><?php echo esc_html($room->name); ?></strong>
                                <div class="hrb-room-stats">
                                    <span class="hrb-room-bookings"><?php echo $room_bookings_today; ?> <?php _e('bookings today', 'hourly-room-booking'); ?></span>
                                </div>
                            </div>
                            <div class="hrb-room-status">
                                <?php if ($is_available): ?>
                                    <span class="hrb-status-available"><?php _e('Available', 'hourly-room-booking'); ?></span>
                                <?php else: ?>
                                    <span class="hrb-status-occupied"><?php _e('Occupied', 'hourly-room-booking'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php
                        endforeach;
                    else:
                    ?>
                        <p class="hrb-no-data"><?php _e('No rooms configured yet.', 'hourly-room-booking'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Status -->
            <div class="hrb-dashboard-section hrb-system-status">
                <h3><?php _e('System Status', 'hourly-room-booking'); ?></h3>
                <div class="hrb-status-list">
                    <div class="hrb-status-item">
                        <span class="hrb-status-label"><?php _e('Database', 'hourly-room-booking'); ?></span>
                        <span class="hrb-status-value hrb-status-ok"><?php _e('OK', 'hourly-room-booking'); ?></span>
                    </div>
                    <div class="hrb-status-item">
                        <span class="hrb-status-label"><?php _e('PayPal Integration', 'hourly-room-booking'); ?></span>
                        <span class="hrb-status-value <?php echo !empty(get_option('hrb_paypal_client_id')) ? 'hrb-status-ok' : 'hrb-status-warning'; ?>">
                            <?php echo !empty(get_option('hrb_paypal_client_id')) ? __('Active', 'hourly-room-booking') : __('Not Configured', 'hourly-room-booking'); ?>
                        </span>
                    </div>
                    <div class="hrb-status-item">
                        <span class="hrb-status-label"><?php _e('Email Notifications', 'hourly-room-booking'); ?></span>
                        <span class="hrb-status-value <?php echo get_option('hrb_email_notifications') ? 'hrb-status-ok' : 'hrb-status-disabled'; ?>">
                            <?php echo get_option('hrb_email_notifications') ? __('Enabled', 'hourly-room-booking') : __('Disabled', 'hourly-room-booking'); ?>
                        </span>
                    </div>
                    <div class="hrb-status-item">
                        <span class="hrb-status-label"><?php _e('SMS Notifications', 'hourly-room-booking'); ?></span>
                        <span class="hrb-status-value <?php echo (get_option('hrb_sms_notifications') && !empty(get_option('hrb_twilio_sid'))) ? 'hrb-status-ok' : 'hrb-status-disabled'; ?>">
                            <?php echo (get_option('hrb_sms_notifications') && !empty(get_option('hrb_twilio_sid'))) ? __('Enabled', 'hourly-room-booking') : __('Disabled', 'hourly-room-booking'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Integration -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get booking data for chart
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'hrb_get_booking_chart_data',
            nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                initBookingChart(response.data);
            }
        }
    });

    function initBookingChart(data) {
        const ctx = document.getElementById('hrbBookingChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: '<?php _e('Bookings', 'hourly-room-booking'); ?>',
                    data: data.bookings,
                    borderColor: '#007cba',
                    backgroundColor: 'rgba(0, 124, 186, 0.1)',
                    tension: 0.1
                }, {
                    label: '<?php _e('Revenue', 'hourly-room-booking'); ?>',
                    data: data.revenue,
                    borderColor: '#00a32a',
                    backgroundColor: 'rgba(0, 163, 42, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: '<?php _e('Bookings', 'hourly-room-booking'); ?>'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: '<?php _e('Revenue (�)', 'hourly-room-booking'); ?>'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    }
});
</script>

<style>
/* HRB Admin Dashboard - Modern Professional Styling */
.hrb-admin-dashboard {
    background: #f8f9fa;
    min-height: calc(100vh - 32px);
    margin: 20px -20px -20px -2px;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.hrb-admin-dashboard .wp-heading-inline {
    color: #1e293b;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.hrb-admin-dashboard .wp-heading-inline::before {
    content: '';
    width: 4px;
    height: 32px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    border-radius: 2px;
}

/* Dashboard Stats Grid */
.hrb-dashboard-stats {
    margin-bottom: 32px;
}

.hrb-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.hrb-stat-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    /* padding: 24px; */
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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

.hrb-stat-today::before { background: linear-gradient(90deg, #10b981, #059669); }
.hrb-stat-revenue::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
.hrb-stat-rooms::before { background: linear-gradient(90deg, #8b5cf6, #7c3aed); }
.hrb-stat-pending::before { background: linear-gradient(90deg, #ef4444, #dc2626); }

.hrb-stat-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.hrb-stat-today .hrb-stat-icon { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
.hrb-stat-revenue .hrb-stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
.hrb-stat-rooms .hrb-stat-icon { background: linear-gradient(135deg, #8b5cf6, #7c3aed); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3); }
.hrb-stat-pending .hrb-stat-icon { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }

.hrb-stat-content {
    flex: 1;
}

.hrb-stat-number {
    font-size: 32px;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 4px;
}

.hrb-stat-label {
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Dashboard Content */
.hrb-dashboard-content {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 32px;
    align-items: start;
}

.hrb-dashboard-main {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.hrb-dashboard-section {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.hrb-section-header {
    padding: 24px;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.hrb-section-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.hrb-section-header h2::before {
    content: '';
    width: 8px;
    height: 8px;
    background: #3b82f6;
    border-radius: 50%;
}

/* Recent Bookings Table */
.hrb-table-container {
    overflow-x: auto;
}

.hrb-recent-bookings table {
    width: 100%;
    border-collapse: collapse;
}

.hrb-recent-bookings th {
    background: #f8fafc;
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
}

.hrb-recent-bookings td {
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.hrb-recent-bookings tr:hover {
    background: #f8fafc;
}

.hrb-actions {
    display: flex;
    gap: 8px;
}

.hrb-actions .button {
    border-radius: 8px;
    transition: all 0.2s ease;
}

.hrb-no-data {
    text-align: center;
    padding: 48px 24px;
    color: #64748b;
    font-style: italic;
}

/* Chart Container */
.hrb-chart-container {
    padding: 24px;
    min-height: 300px;
}

/* Dashboard Sidebar */
.hrb-dashboard-sidebar {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.hrb-dashboard-sidebar .hrb-dashboard-section h3 {
    margin: 0 0 16px 0;
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    padding: 20px 24px 0;
}

/* Quick Actions */
.hrb-action-buttons {
    padding: 20px 24px 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.hrb-action-btn {
    border-radius: 12px;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    font-weight: 600;
    font-size: 14px;
}

.hrb-action-btn.button-primary {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.hrb-action-btn.button-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

.hrb-action-btn.button-secondary {
    background: white;
    color: #475569;
    border-color: #e2e8f0;
}

.hrb-action-btn.button-secondary:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    transform: translateY(-1px);
}

/* Room Status */
.hrb-room-list {
    padding: 20px 24px 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.hrb-room-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.2s ease;
}

.hrb-room-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.hrb-room-info strong {
    color: #1e293b;
    font-weight: 600;
    font-size: 14px;
}

.hrb-room-stats {
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}

.hrb-status-available {
    background: #d1fae5;
    color: #059669;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hrb-status-occupied {
    background: #fee2e2;
    color: #dc2626;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* System Status */
.hrb-status-list {
    padding: 20px 24px 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.hrb-status-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 8px;
}

.hrb-status-label {
    font-weight: 600;
    color: #475569;
    font-size: 13px;
}

.hrb-status-value {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hrb-status-ok {
    background: #d1fae5;
    color: #059669;
}

.hrb-status-warning {
    background: #fef3c7;
    color: #d97706;
}

.hrb-status-disabled {
    background: #f3f4f6;
    color: #6b7280;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .hrb-dashboard-content {
        grid-template-columns: 1fr;
        gap: 24px;
    }

    .hrb-stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    }
}

@media (max-width: 768px) {
    .hrb-admin-dashboard {
        padding: 16px;
        margin: -20px -10px -20px -2px;
    }

    .hrb-stats-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .hrb-stat-card {
        padding: 20px;
    }

    .hrb-stat-number {
        font-size: 24px;
    }

    .hrb-dashboard-content {
        gap: 20px;
    }
}
</style>