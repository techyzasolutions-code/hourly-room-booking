<?php
/**
 * Booking Reminder Email Template
 * 
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Template variables are passed from the notification manager
$booking = $template_data['booking'] ?? null;
$customer = $template_data['customer'] ?? null;
$room = $template_data['room'] ?? null;
$company_name = $template_data['company_name'] ?? get_option('hrb_company_name', get_bloginfo('name'));
$company_email = $template_data['company_email'] ?? get_option('hrb_company_email', get_option('admin_email'));
$company_phone = $template_data['company_phone'] ?? get_option('hrb_company_phone', '');
$company_address = $template_data['company_address'] ?? get_option('hrb_company_address', '');

if (!$booking || !$customer || !$room) {
    return;
}

$booking_datetime = $booking->booking_date . ' ' . $booking->start_time;
$formatted_datetime = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking_datetime));
$hours_until = round((strtotime($booking_datetime) - time()) / 3600, 1);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Booking Reminder', 'hourly-room-booking'); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .content {
            background: #fff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        .booking-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .booking-details h3 {
            margin-top: 0;
            color: #495057;
        }
        .booking-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .booking-details td {
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .booking-details td:first-child {
            font-weight: bold;
            width: 40%;
        }
        .highlight {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            font-size: 14px;
            color: #6c757d;
        }
        .button {
            display: inline-block;
            background: #007cba;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php _e('Booking Reminder', 'hourly-room-booking'); ?></h1>
        <p><?php echo esc_html($company_name); ?></p>
    </div>

    <div class="content">
        <h2><?php _e('Your booking is coming up soon!', 'hourly-room-booking'); ?></h2>
        
        <p><?php printf(__('Hello %s,', 'hourly-room-booking'), esc_html($customer->first_name)); ?></p>
        
        <p><?php printf(__('This is a friendly reminder that you have a booking scheduled for %s (%s hours from now).', 'hourly-room-booking'), $formatted_datetime, $hours_until); ?></p>

        <div class="booking-details">
            <h3><?php _e('Booking Details', 'hourly-room-booking'); ?></h3>
            <table>
                <tr>
                    <td><?php _e('Booking Reference:', 'hourly-room-booking'); ?></td>
                    <td><strong><?php echo esc_html($booking->booking_reference); ?></strong></td>
                </tr>
                <tr>
                    <td><?php _e('Room:', 'hourly-room-booking'); ?></td>
                    <td><?php echo esc_html($room->name); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Date & Time:', 'hourly-room-booking'); ?></td>
                    <td><?php echo $formatted_datetime; ?></td>
                </tr>
                <tr>
                    <td><?php _e('Duration:', 'hourly-room-booking'); ?></td>
                    <td><?php echo esc_html($booking->total_hours); ?> <?php _e('hours', 'hourly-room-booking'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Total Amount:', 'hourly-room-booking'); ?></td>
                    <td><strong><?php echo HRB_Currency_Manager::getInstance()->format_amount($booking->total_amount); ?></strong></td>
                </tr>
            </table>
        </div>

        <div class="highlight">
            <h3><?php _e('Important Reminders:', 'hourly-room-booking'); ?></h3>
            <ul>
                <li><?php _e('Please arrive on time for your booking', 'hourly-room-booking'); ?></li>
                <li><?php _e('Bring a valid ID for verification', 'hourly-room-booking'); ?></li>
                <li><?php _e('Contact us if you need to make any changes', 'hourly-room-booking'); ?></li>
            </ul>
        </div>

        <?php if ($company_phone): ?>
        <p><?php printf(__('If you have any questions, please contact us at %s or reply to this email.', 'hourly-room-booking'), $company_phone); ?></p>
        <?php else: ?>
        <p><?php _e('If you have any questions, please reply to this email.', 'hourly-room-booking'); ?></p>
        <?php endif; ?>

        <p><?php _e('We look forward to seeing you soon!', 'hourly-room-booking'); ?></p>
        
        <p><?php _e('Best regards,', 'hourly-room-booking'); ?><br>
        <?php echo esc_html($company_name); ?></p>
    </div>

    <div class="footer">
        <p><?php echo esc_html($company_name); ?></p>
        <?php if ($company_address): ?>
        <p><?php echo esc_html($company_address); ?></p>
        <?php endif; ?>
        <p><?php printf(__('Email: %s', 'hourly-room-booking'), $company_email); ?></p>
        <?php if ($company_phone): ?>
        <p><?php printf(__('Phone: %s', 'hourly-room-booking'), $company_phone); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
