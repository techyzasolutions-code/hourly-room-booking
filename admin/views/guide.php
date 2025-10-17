<?php
/**
 * Plugin Guide Page
 * Displays shortcodes and cron jobs
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Hourly Room Booking - User Guide', 'hourly-room-booking'); ?></h1>
    
    <div class="hrb-guide-container">
        <style>
        .hrb-guide-container {
            max-width: 800px;
            margin: 20px 0;
        }
        
        .hrb-guide-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .hrb-guide-section h2 {
            background: #f8f9fa;
            margin: 0;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            color: #2c3e50;
            font-size: 18px;
        }
        
        .hrb-guide-content {
            padding: 20px;
        }
        
        .hrb-code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 10px 0;
            overflow-x: auto;
        }
        
        .hrb-command {
            background: #1a202c;
            color: #68d391;
            padding: 10px 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin: 5px 0;
            border-left: 4px solid #68d391;
        }
        
        .hrb-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        
        .hrb-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .hrb-table th,
        .hrb-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        
        .hrb-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .hrb-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #28a745;
            color: white;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        </style>

        <!-- Shortcodes Section -->
        <div class="hrb-guide-section">
            <h2>🎨 Frontend Shortcodes</h2>
            <div class="hrb-guide-content">
                <h3>Available Shortcodes</h3>
                
                <div class="hrb-code-block">
[room_booking_search] - Display room search form
[room_list] - Display list of available rooms
[room_booking_form] - Display booking form
[room_booking_page_form] - Display full page booking form
[room_booking_filter] - Display room filter options
[room_calendar] - Display room availability calendar
                </div>
                
                <h3>Usage Examples with Parameters</h3>
                
                <h4>Room Booking Form</h4>
                <div class="hrb-command">
[room_booking_form room_id="1" show_room_info="true" redirect_url="/success/"]
                </div>
                <p><strong>Parameters:</strong></p>
                <ul>
                    <li><code>room_id</code> - Required. Room ID to display booking form for</li>
                    <li><code>show_room_info</code> - Show room information (true/false)</li>
                    <li><code>redirect_url</code> - URL to redirect after successful booking</li>
                </ul>
                
                <h4>Room Booking Page Form</h4>
                <div class="hrb-command">
[room_booking_page_form room_id="1" show_room_info="true" redirect_url="/success/"]
                </div>
                <p><strong>Parameters:</strong> Same as room_booking_form</p>
                
                <h4>Room Search</h4>
                <div class="hrb-command">
[room_booking_search show_filters="true" rooms_per_page="6" columns="3" show_price="true" show_capacity="true" show_amenities="true" show_view_button="true"]
                </div>
                <p><strong>Parameters:</strong></p>
                <ul>
                    <li><code>show_filters</code> - Show search filters (true/false)</li>
                    <li><code>rooms_per_page</code> - Number of rooms to display per page</li>
                    <li><code>columns</code> - Number of columns in grid layout (1-4)</li>
                    <li><code>show_price</code> - Show room prices (true/false)</li>
                    <li><code>show_capacity</code> - Show room capacity (true/false)</li>
                    <li><code>show_amenities</code> - Show room amenities (true/false)</li>
                    <li><code>show_view_button</code> - Show "View Details" button (true/false)</li>
                </ul>
                
                <h5>Room Search Examples:</h5>
                <div class="hrb-command">
<!-- Basic search with all features -->
[room_booking_search]

<!-- 4-column layout with all details -->
[room_booking_search columns="4" show_price="true" show_capacity="true" show_amenities="true" show_view_button="true"]

<!-- Simple booking-only layout -->
[room_booking_search columns="2" show_price="true" show_capacity="false" show_amenities="false" show_view_button="false"]

<!-- Compact single column -->
[room_booking_search columns="1" show_price="true" show_capacity="true" show_amenities="false" show_view_button="true"]
                </div>
                
                <h4>Room List</h4>
                <div class="hrb-command">
[room_list columns="3" show_price="true" show_capacity="true" show_amenities="true" limit="6"]
                </div>
                <p><strong>Parameters:</strong></p>
                <ul>
                    <li><code>columns</code> - Number of columns in grid layout</li>
                    <li><code>show_price</code> - Show room prices (true/false)</li>
                    <li><code>show_capacity</code> - Show room capacity (true/false)</li>
                    <li><code>show_amenities</code> - Show room amenities (true/false)</li>
                    <li><code>limit</code> - Maximum number of rooms to display (-1 for all)</li>
                </ul>
                
                <h4>Room Filter</h4>
                <div class="hrb-command">
[room_booking_filter redirect_url="/search-results/" show_title="true" title="Find Your Perfect Room" subtitle="Search and book rooms"]
                </div>
                <p><strong>Parameters:</strong></p>
                <ul>
                    <li><code>redirect_url</code> - URL to redirect after search</li>
                    <li><code>show_title</code> - Show title section (true/false)</li>
                    <li><code>title</code> - Custom title text</li>
                    <li><code>subtitle</code> - Custom subtitle text</li>
                </ul>
                
            </div>
        </div>

        <!-- Cron Jobs Section -->
        <div class="hrb-guide-section">
            <h2>⏰ Cron Jobs & Scheduled Tasks</h2>
            <div class="hrb-guide-content">
                <div class="hrb-warning">
                    <strong>⚠️ Important:</strong> These cron jobs are essential for the plugin to function properly.
                </div>
                
                <h3>🔧 Manual Cron Job Setup</h3>
                <p>Add these commands to your server's crontab (run <code>crontab -e</code> to edit):</p>
                
                <div class="hrb-code-block">
# Hourly Room Booking Plugin Cron Jobs
# Run every 5 minutes to check for pending tasks
*/5 * * * * /usr/bin/php /path/to/your/wordpress/wp-cron.php

# Daily cleanup and maintenance (runs at 2 AM)
0 2 * * * /usr/bin/php /path/to/your/wordpress/wp-cron.php

# Weekly report generation (runs every Monday at 3 AM)
0 3 * * 1 /usr/bin/php /path/to/your/wordpress/wp-cron.php
                </div>
                
                <h3>📋 WordPress Cron Events</h3>
                <table class="hrb-table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Frequency</th>
                            <th>Purpose</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>hrb_cleanup_expired_bookings</code></td>
                            <td>Daily</td>
                            <td>Clean up expired and cancelled bookings</td>
                            <td><span class="hrb-badge">Active</span></td>
                        </tr>
                        <tr>
                            <td><code>hrb_send_booking_reminders</code></td>
                            <td>Hourly</td>
                            <td>Send booking reminder emails</td>
                            <td><span class="hrb-badge">Active</span></td>
                        </tr>
                        <tr>
                            <td><code>hrb_update_room_availability</code></td>
                            <td>Every 15 minutes</td>
                            <td>Update room availability status</td>
                            <td><span class="hrb-badge">Active</span></td>
                        </tr>
                        <tr>
                            <td><code>hrb_generate_daily_reports</code></td>
                            <td>Daily at 1 AM</td>
                            <td>Generate daily booking reports</td>
                            <td><span class="hrb-badge">Active</span></td>
                        </tr>
                        <tr>
                            <td><code>hrb_sync_payment_status</code></td>
                            <td>Every 30 minutes</td>
                            <td>Sync payment status with PayPal</td>
                            <td><span class="hrb-badge">Active</span></td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>🛠️ Manual Cron Commands</h3>
                <p>You can manually trigger these events using WP-CLI:</p>
                
                <div class="hrb-command">
# Clean up expired bookings
wp eval "do_action('hrb_cleanup_expired_bookings');"
                </div>
                
                <div class="hrb-command">
# Send booking reminders
wp eval "do_action('hrb_send_booking_reminders');"
                </div>
                
                <div class="hrb-command">
# Update room availability
wp eval "do_action('hrb_update_room_availability');"
                </div>
                
                <div class="hrb-command">
# Generate daily reports
wp eval "do_action('hrb_generate_daily_reports');"
                </div>
                
                <div class="hrb-command">
# Sync payment status
wp eval "do_action('hrb_sync_payment_status');"
                </div>
            </div>
        </div>
    </div>
</div>
