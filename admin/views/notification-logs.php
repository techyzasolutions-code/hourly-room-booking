<?php
/**
 * Email Logs View
 * Displays all email notification logs with filters, pagination, and delete functionality
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Email Logs', 'hourly-room-booking'); ?></h1>
    <hr class="wp-header-end">
    
    <?php
    // Check if WordPress cron is disabled
    $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
    $wp_cron_path = ABSPATH . 'wp-cron.php';
    $cron_command = '*/5 * * * * /usr/bin/php ' . $wp_cron_path;
    
    if ($cron_disabled) {
        echo '<div class="notice notice-warning"><p><strong>' . __('Warning:', 'hourly-room-booking') . '</strong> ' . __('WordPress cron is disabled. Reminders will not be sent automatically. Please set up a system cron job on your server using the following command:', 'hourly-room-booking') . '</p>';
        echo '<p style="margin: 10px 0;"><code style="background: #f0f0f1; padding: 8px 12px; border-radius: 4px; display: inline-block; font-size: 13px;">' . esc_html($cron_command) . '</code></p>';
        echo '<p style="margin-top: 10px;"><strong>' . __('Cron Timing Explained:', 'hourly-room-booking') . '</strong></p>';
        echo '<p style="margin: 5px 0;">' . __('The timing <code>*/5 * * * *</code> means the cron job runs every 5 minutes. The format is: <code>minute hour day month weekday</code>', 'hourly-room-booking') . '</p>';
        echo '<p style="margin: 10px 0;"><strong>' . __('To change the timing, modify the first part:', 'hourly-room-booking') . '</strong></p>';
        echo '<ul style="margin-left: 20px; margin-top: 5px;">';
        echo '<li><code>*/5 * * * *</code> - ' . __('Every 5 minutes (recommended)', 'hourly-room-booking') . '</li>';
        echo '<li><code>*/15 * * * *</code> - ' . __('Every 15 minutes', 'hourly-room-booking') . '</li>';
        echo '<li><code>0 * * * *</code> - ' . __('Every hour (at minute 0)', 'hourly-room-booking') . '</li>';
        echo '<li><code>*/10 * * * *</code> - ' . __('Every 10 minutes', 'hourly-room-booking') . '</li>';
        echo '</ul></div>';
    } else {
        echo '<div class="notice notice-info"><p><strong>' . __('Note:', 'hourly-room-booking') . '</strong> ' . __('For more reliable delivery, consider setting up a system cron job on your server using the following command:', 'hourly-room-booking') . '</p>';
        echo '<p style="margin: 10px 0;"><code style="background: #f0f0f1; padding: 8px 12px; border-radius: 4px; display: inline-block; font-size: 13px;">' . esc_html($cron_command) . '</code></p>';
        echo '<p style="margin-top: 10px;"><strong>' . __('Cron Timing Explained:', 'hourly-room-booking') . '</strong></p>';
        echo '<p style="margin: 5px 0;">' . __('The timing <code>*/5 * * * *</code> means the cron job runs every 5 minutes. The format is: <code>minute hour day month weekday</code>', 'hourly-room-booking') . '</p>';
        echo '<p style="margin: 10px 0;"><strong>' . __('To change the timing, modify the first part:', 'hourly-room-booking') . '</strong></p>';
        echo '<ul style="margin-left: 20px; margin-top: 5px;">';
        echo '<li><code>*/5 * * * *</code> - ' . __('Every 5 minutes (recommended)', 'hourly-room-booking') . '</li>';
        echo '<li><code>*/15 * * * *</code> - ' . __('Every 15 minutes', 'hourly-room-booking') . '</li>';
        echo '<li><code>0 * * * *</code> - ' . __('Every hour (at minute 0)', 'hourly-room-booking') . '</li>';
        echo '<li><code>*/10 * * * *</code> - ' . __('Every 10 minutes', 'hourly-room-booking') . '</li>';
        echo '</ul></div>';
    }
    ?>
    
    <!-- Overall Statistics -->
    <div class="hrb-dashboard-section">
        <div class="hrb-section-header">
            <h2><?php _e('Overall Statistics', 'hourly-room-booking'); ?></h2>
        </div>
        <div class="hrb-chart-container">
            <div class="hrb-stats-grid">
                <div class="hrb-stat-card">
                    <div class="hrb-stat-content">
                        <div class="hrb-stat-number"><?php echo $stats ? intval($stats->total_logs) : 0; ?></div>
                        <div class="hrb-stat-label"><?php _e('Total Logs', 'hourly-room-booking'); ?></div>
                    </div>
                </div>
                <div class="hrb-stat-card">
                    <div class="hrb-stat-content">
                        <div class="hrb-stat-number" style="color: #00a32a;"><?php echo $stats ? intval($stats->sent_logs) : 0; ?></div>
                        <div class="hrb-stat-label"><?php _e('Sent', 'hourly-room-booking'); ?></div>
                    </div>
                </div>
                <div class="hrb-stat-card">
                    <div class="hrb-stat-content">
                        <div class="hrb-stat-number" style="color: #d63638;"><?php echo $stats ? intval($stats->failed_logs) : 0; ?></div>
                        <div class="hrb-stat-label"><?php _e('Failed', 'hourly-room-booking'); ?></div>
                    </div>
                </div>
                <div class="hrb-stat-card">
                    <div class="hrb-stat-content">
                        <div class="hrb-stat-number" style="color: #dba617;"><?php echo $stats ? intval($stats->pending_logs) : 0; ?></div>
                        <div class="hrb-stat-label"><?php _e('Pending', 'hourly-room-booking'); ?></div>
                    </div>
                </div>
                <div class="hrb-stat-card">
                    <div class="hrb-stat-content">
                        <div class="hrb-stat-number"><?php echo $stats ? intval($stats->email_logs) : 0; ?></div>
                        <div class="hrb-stat-label"><?php _e('Email Logs', 'hourly-room-booking'); ?></div>
                    </div>
                </div>
                <div class="hrb-stat-card">
                    <div class="hrb-stat-content">
                        <div class="hrb-stat-number"><?php echo $stats ? intval($stats->sms_logs) : 0; ?></div>
                        <div class="hrb-stat-label"><?php _e('SMS Logs', 'hourly-room-booking'); ?></div>
                    </div>
                </div>
                <div class="hrb-stat-card">
                    <div class="hrb-stat-content">
                        <div class="hrb-stat-number"><?php echo $stats ? intval($stats->whatsapp_logs) : 0; ?></div>
                        <div class="hrb-stat-label"><?php _e('WhatsApp Logs', 'hourly-room-booking'); ?></div>
                    </div>
                </div>
                <div class="hrb-stat-card">
                    <div class="hrb-stat-content">
                        <div class="hrb-stat-number" style="font-size: 18px; line-height: 1.4;"><?php echo $next_reminders ? date('Y-m-d H:i:s', $next_reminders) : __('Not scheduled', 'hourly-room-booking'); ?></div>
                        <div class="hrb-stat-label"><?php _e('Next Reminder Check', 'hourly-room-booking'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div style="padding: 20px; border-top: 1px solid #e1e5e9; margin-top: 20px;">
            <button class="button button-primary" onclick="testReminders()"><?php _e('Test Reminder System', 'hourly-room-booking'); ?></button>
            <div id="test-results" style="margin-top: 15px;"></div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="hrb-dashboard-section">
        <div class="hrb-section-header">
            <h2><?php _e('Filters', 'hourly-room-booking'); ?></h2>
        </div>
        <div style="padding: 20px;">
        <form method="GET" action="<?php echo admin_url('admin.php'); ?>">
            <input type="hidden" name="page" value="hrb-reminder-logs">
            <div class="hrb-filters-grid">
                <div class="hrb-filter-item">
                    <label for="filter-type"><?php _e('Type', 'hourly-room-booking'); ?></label>
                    <select name="type" id="filter-type">
                        <option value=""><?php _e('All Types', 'hourly-room-booking'); ?></option>
                        <option value="email" <?php selected($filters['type'], 'email'); ?>><?php _e('Email', 'hourly-room-booking'); ?></option>
                        <option value="sms" <?php selected($filters['type'], 'sms'); ?>><?php _e('SMS', 'hourly-room-booking'); ?></option>
                        <option value="whatsapp" <?php selected($filters['type'], 'whatsapp'); ?>><?php _e('WhatsApp', 'hourly-room-booking'); ?></option>
                    </select>
                </div>
                <div class="hrb-filter-item">
                    <label for="filter-status"><?php _e('Status', 'hourly-room-booking'); ?></label>
                    <select name="status" id="filter-status">
                        <option value=""><?php _e('All Statuses', 'hourly-room-booking'); ?></option>
                        <option value="sent" <?php selected($filters['status'], 'sent'); ?>><?php _e('Sent', 'hourly-room-booking'); ?></option>
                        <option value="failed" <?php selected($filters['status'], 'failed'); ?>><?php _e('Failed', 'hourly-room-booking'); ?></option>
                        <option value="pending" <?php selected($filters['status'], 'pending'); ?>><?php _e('Pending', 'hourly-room-booking'); ?></option>
                    </select>
                </div>
                <div class="hrb-filter-item">
                    <label for="filter-event"><?php _e('Event', 'hourly-room-booking'); ?></label>
                    <select name="event" id="filter-event">
                        <option value=""><?php _e('All Events', 'hourly-room-booking'); ?></option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo esc_attr($event); ?>" <?php selected($filters['event'], $event); ?>>
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $event))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="hrb-filter-item">
                    <label for="filter-date-from"><?php _e('Date From', 'hourly-room-booking'); ?></label>
                    <input type="date" name="date_from" id="filter-date-from" value="<?php echo esc_attr($filters['date_from']); ?>">
                </div>
                <div class="hrb-filter-item">
                    <label for="filter-date-to"><?php _e('Date To', 'hourly-room-booking'); ?></label>
                    <input type="date" name="date_to" id="filter-date-to" value="<?php echo esc_attr($filters['date_to']); ?>">
                </div>
                <div class="hrb-filter-item">
                    <label for="filter-search"><?php _e('Search', 'hourly-room-booking'); ?></label>
                    <input type="search" name="s" id="filter-search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php _e('Search by recipient, subject, or booking ID', 'hourly-room-booking'); ?>">
                </div>
                <div class="hrb-filter-item hrb-filter-buttons" style="display: flex; gap: 10px; align-items: end;flex-direction: row;">
                    <button type="submit" class="button button-primary" style="flex: 1;"><?php _e('Filter', 'hourly-room-booking'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=hrb-reminder-logs'); ?>" class="button" style="flex: 1; text-align: center;"><?php _e('Clear', 'hourly-room-booking'); ?></a>
                </div>
            </div>
        </form>
        </div>
    </div>
    
    <!-- Delete Old Logs Section -->
    <?php if (current_user_can('hrb_manage_bookings')): ?>
    <div class="hrb-dashboard-section">
        <div class="hrb-section-header">
            <h2><?php _e('Delete Old Logs', 'hourly-room-booking'); ?></h2>
        </div>
        <div style="padding: 20px;">
        <form method="POST" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to delete old logs? This action cannot be undone.', 'hourly-room-booking')); ?>');">
            <?php wp_nonce_field('hrb_delete_logs', 'hrb_nonce'); ?>
            <input type="hidden" name="action" value="delete_logs">
            <p>
                <label><?php _e('Delete logs older than:', 'hourly-room-booking'); ?> </label>
                <select name="delete_days">
                    <option value="30"><?php _e('30 days', 'hourly-room-booking'); ?></option>
                    <option value="60"><?php _e('60 days', 'hourly-room-booking'); ?></option>
                    <option value="90"><?php _e('90 days', 'hourly-room-booking'); ?></option>
                    <option value="180"><?php _e('180 days', 'hourly-room-booking'); ?></option>
                    <option value="365"><?php _e('1 year', 'hourly-room-booking'); ?></option>
                </select>
                <button type="submit" class="button button-secondary"><?php _e('Delete', 'hourly-room-booking'); ?></button>
            </p>
        </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Logs Table -->
    <div class="hrb-dashboard-section">
        <div class="hrb-section-header">
            <h2><?php _e('Notification Logs', 'hourly-room-booking'); ?> <span class="count">(<?php echo $total_logs; ?>)</span></h2>
        </div>
        <div class="hrb-table-container">
            <?php if (empty($logs)): ?>
                <p style="padding: 20px;"><?php _e('No logs found.', 'hourly-room-booking'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><input type="checkbox" id="select-all-logs"></th>
                    <th><?php _e('Date', 'hourly-room-booking'); ?></th>
                    <th><?php _e('Type', 'hourly-room-booking'); ?></th>
                    <th><?php _e('Event', 'hourly-room-booking'); ?></th>
                    <th><?php _e('Booking ID', 'hourly-room-booking'); ?></th>
                    <th><?php _e('Recipient', 'hourly-room-booking'); ?></th>
                    <th><?php _e('Subject', 'hourly-room-booking'); ?></th>
                    <th><?php _e('Status', 'hourly-room-booking'); ?></th>
                    <th><?php _e('Sent At', 'hourly-room-booking'); ?></th>
                    <th><?php _e('Error', 'hourly-room-booking'); ?></th>
                    <?php if (current_user_can('hrb_manage_bookings')): ?>
                        <th><?php _e('Actions', 'hourly-room-booking'); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php $status_color = $log->status === 'sent' ? 'green' : ($log->status === 'failed' ? 'red' : 'orange'); ?>
                    <tr>
                        <?php if (current_user_can('hrb_manage_bookings')): ?>
                            <td><input type="checkbox" name="log_ids[]" value="<?php echo esc_attr($log->id); ?>" class="log-checkbox"></td>
                        <?php else: ?>
                            <td></td>
                        <?php endif; ?>
                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at)); ?></td>
                        <td><?php echo esc_html(ucfirst($log->type)); ?></td>
                        <td><?php echo esc_html(ucwords(str_replace('_', ' ', $log->event))); ?></td>
                        <td><?php echo $log->booking_id > 0 ? '<a href="' . admin_url('admin.php?page=hrb-bookings&action=view&id=' . $log->booking_id) . '">#' . $log->booking_id . '</a>' : '-'; ?></td>
                        <td><?php echo esc_html($log->recipient); ?></td>
                        <td><?php echo esc_html($log->subject ?? '-'); ?></td>
                        <td style="color: <?php echo $status_color; ?>; font-weight: bold;"><?php echo esc_html(ucfirst($log->status)); ?></td>
                        <td><?php echo $log->sent_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->sent_at)) : '-'; ?></td>
                        <td><?php echo esc_html($log->error_message ?? ''); ?></td>
                        <?php if (current_user_can('hrb_manage_bookings')): ?>
                            <td>
                                <?php if ($log->type === 'email' && $log->booking_id > 0): ?>
                                    <button class="button button-small hrb-resend-log" data-log-id="<?php echo esc_attr($log->id); ?>" title="<?php esc_attr_e('Resend', 'hourly-room-booking'); ?>" style="margin-right: 5px;">
                                        <span class="dashicons dashicons-update"></span>
                                    </button>
                                <?php endif; ?>
                                <button class="button button-small hrb-delete-log" data-log-id="<?php echo esc_attr($log->id); ?>" title="<?php esc_attr_e('Delete', 'hourly-room-booking'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $page
                    ));
                    ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- CSS for Enhanced Filter Grid -->
<style>
/* Filter Grid - Custom styles for notification logs page */
.hrb-filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    align-items: end;
    margin-bottom: 15px;
}
.hrb-filter-item {
    display: flex;
    flex-direction: column;
}
.hrb-filter-item label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 13px;
    color: #23282d;
}
.hrb-filter-item select,
.hrb-filter-item input[type="date"],
.hrb-filter-item input[type="search"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.hrb-filter-item select:focus,
.hrb-filter-item input[type="date"]:focus,
.hrb-filter-item input[type="search"]:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}
.hrb-filter-item .button {
    white-space: nowrap;
}
@media (max-width: 1200px) {
    .hrb-filters-grid {
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }
}
@media (max-width: 782px) {
    .hrb-filters-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- JavaScript -->
<script>
jQuery(document).ready(function($) {
    // Select all checkbox
    $("#select-all-logs").on("change", function() {
        $(".log-checkbox").prop("checked", $(this).prop("checked"));
    });
    
    // Resend notification
    $(".hrb-resend-log").on("click", function() {
        var $button = $(this);
        var logId = $button.data("log-id");
        
        // Disable button and show loading
        $button.prop("disabled", true);
        var originalHtml = $button.html();
        $button.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');
        
        $.post(ajaxurl, {
            action: "hrb_resend_notification",
            log_id: logId,
            nonce: "<?php echo wp_create_nonce('hrb_admin_nonce'); ?>"
        }, function(response) {
            // Re-enable button
            $button.prop("disabled", false);
            $button.html(originalHtml);
            
            if (response.success) {
                // Show success message
                $button.closest("tr").find("td").css("background-color", "#d4edda");
                setTimeout(function() {
                    $button.closest("tr").find("td").css("background-color", "");
                }, 2000);
                
                // Show success notice
                var notice = $('<div class="notice notice-success is-dismissible"><p>' + (response.data.message || "<?php echo esc_js(__('Notification resent successfully.', 'hourly-room-booking')); ?>") + '</p></div>');
                $(".wrap h1").after(notice);
                setTimeout(function() {
                    notice.fadeOut(function() {
                        notice.remove();
                    });
                }, 3000);
            } else {
                // Show error message
                alert(response.data || "<?php echo esc_js(__('Error resending notification', 'hourly-room-booking')); ?>");
            }
        }).fail(function() {
            // Re-enable button on error
            $button.prop("disabled", false);
            $button.html(originalHtml);
            alert("<?php echo esc_js(__('An error occurred while resending the notification', 'hourly-room-booking')); ?>");
        });
    });
    
    // Delete single log
    $(".hrb-delete-log").on("click", function() {
        var logId = $(this).data("log-id");
        if (confirm("<?php echo esc_js(__('Are you sure you want to delete this log entry?', 'hourly-room-booking')); ?>")) {
            $.post(ajaxurl, {
                action: "hrb_delete_notification_log",
                log_id: logId,
                nonce: "<?php echo wp_create_nonce('hrb_admin_nonce'); ?>"
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || "Error deleting log");
                }
            });
        }
    });
});

function testReminders() {
    document.getElementById("test-results").innerHTML = "Testing...";
    jQuery.post(ajaxurl, {
        action: "hrb_test_reminders",
        nonce: "<?php echo wp_create_nonce('hrb_admin_nonce'); ?>"
    }, function(response) {
        if (response.success) {
            document.getElementById("test-results").innerHTML = "<div class=\"notice notice-success\"><p>" + response.data.message + "</p></div>";
        } else {
            document.getElementById("test-results").innerHTML = "<div class=\"notice notice-error\"><p>Error: " + response.data + "</p></div>";
        }
    });
}
</script>

