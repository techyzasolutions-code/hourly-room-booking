<?php
/**
 * Booking Cancelled Page Template
 * Displays cancellation confirmation and information
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get booking reference from URL or PayPal token
$booking_ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
$paypal_token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';

// Get booking details if reference provided
$booking = null;
$cancellation_processed = false;
$is_paypal_cancellation = false;

// Handle PayPal cancellation
if ($paypal_token && !$booking_ref) {
    global $wpdb;
    
    // Find booking by PayPal token in payments table
    $payment = $wpdb->get_row($wpdb->prepare(
        "SELECT p.*, b.booking_reference 
         FROM {$wpdb->prefix}hrb_payments p
         JOIN {$wpdb->prefix}hrb_bookings b ON p.booking_id = b.id
         WHERE p.gateway_transaction_id = %s AND p.payment_method = 'paypal'",
        $paypal_token
    ));
    
    if ($payment) {
        $booking_ref = $payment->booking_reference;
        $is_paypal_cancellation = true;
        
        // Update booking status to cancelled for PayPal cancellation
        $wpdb->update(
            $wpdb->prefix . 'hrb_bookings',
            array(
                'status' => 'cancelled',
                'updated_at' => current_time('mysql')
            ),
            array('booking_reference' => $booking_ref),
            array('%s', '%s'),
            array('%s')
        );
        
        // Update payment status to cancelled
        $wpdb->update(
            $wpdb->prefix . 'hrb_payments',
            array(
                'status' => 'cancelled',
                'gateway_response' => 'PayPal payment cancelled by user'
            ),
            array('id' => $payment->id),
            array('%s', '%s'),
            array('%d')
        );
        
        $cancellation_processed = true;
    }
}

if ($booking_ref) {
    global $wpdb;

    // If this is a manual cancellation request, process it
    if ($action === 'cancel' && wp_verify_nonce($nonce, 'cancel_booking')) {
        // Update booking status to cancelled
        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_bookings',
            array(
                'status' => 'cancelled',
                'updated_at' => current_time('mysql')
            ),
            array('booking_reference' => $booking_ref),
            array('%s', '%s'),
            array('%s')
        );

        if ($result !== false) {
            $cancellation_processed = true;

            // Send cancellation notification (you can implement this later)
            // HRB_Notification_Manager::getInstance()->send_cancellation_notification($booking_ref);
        }
    }

    // Get booking details
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, r.name as room_name, c.first_name, c.last_name, c.email, c.phone
         FROM {$wpdb->prefix}hrb_bookings b
         JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
         JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
         WHERE b.booking_reference = %s",
        $booking_ref
    ));
}
?>

<div class="hrb-booking-cancelled-container">
    <style>
    /* Enhanced Booking Cancelled Variables */
    :root {
        --hrb-error: #ef4444;
        --hrb-error-dark: #dc2626;
        --hrb-warning: #f59e0b;
        --hrb-warning-dark: #d97706;
        --hrb-primary: #6366f1;
        --hrb-secondary: #8b5cf6;
        --hrb-success: #10b981;
        --hrb-text: #1f2937;
        --hrb-text-light: #6b7280;
        --hrb-text-muted: #9ca3af;
        --hrb-border: #e5e7eb;
        --hrb-border-light: #f3f4f6;
        --hrb-background: #ffffff;
        --hrb-background-light: #f8fafc;
        --hrb-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
        --hrb-shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07), 0 2px 4px rgba(0, 0, 0, 0.06);
        --hrb-shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
        --hrb-shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
        --hrb-radius: 8px;
        --hrb-radius-lg: 12px;
        --hrb-radius-xl: 16px;
        --hrb-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .hrb-booking-cancelled-container {
        max-width: 900px;
        margin: 60px auto;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: var(--hrb-background);
        border-radius: var(--hrb-radius-xl);
        box-shadow: var(--hrb-shadow-xl);
        overflow: hidden;
        animation: slideInUp 0.6s ease-out;
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .hrb-cancelled-header {
        text-align: center;
        margin-bottom: 0;
        padding: 60px 40px;
        background: linear-gradient(135deg, var(--hrb-error) 0%, var(--hrb-error-dark) 100%);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .hrb-cancelled-header.success {
        background: linear-gradient(135deg, var(--hrb-warning) 0%, var(--hrb-warning-dark) 100%);
        color: white;
    }

    .hrb-cancelled-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="cancelled-grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23cancelled-grain)"/></svg>');
        opacity: 0.3;
        pointer-events: none;
    }

    .hrb-cancelled-icon {
        font-size: 64px;
        margin-bottom: 20px;
        display: block;
        position: relative;
        z-index: 1;
        animation: bounceIn 0.8s ease-out;
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px auto;
        border: 3px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    @keyframes bounceIn {
        0% {
            opacity: 0;
            transform: scale(0.3);
        }
        50% {
            opacity: 1;
            transform: scale(1.1);
        }
        100% {
            opacity: 1;
            transform: scale(1);
        }
    }

    .hrb-cancelled-title {
        font-size: 2.5rem;
        margin: 0 0 15px 0;
        font-weight: 700;
        position: relative;
        z-index: 1;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        letter-spacing: -0.02em;
    }

    .hrb-cancelled-subtitle {
        font-size: 1.2rem;
        opacity: 0.95;
        margin: 0;
        position: relative;
        z-index: 1;
        font-weight: 400;
        line-height: 1.5;
    }

    .hrb-booking-details {
        background: var(--hrb-background-light);
        border: 1px solid var(--hrb-border);
        border-radius: var(--hrb-radius-lg);
        padding: 40px;
        margin: 40px;
        box-shadow: var(--hrb-shadow);
        transition: var(--hrb-transition);
    }

    .hrb-booking-details:hover {
        box-shadow: var(--hrb-shadow-lg);
        transform: translateY(-2px);
    }

    .hrb-booking-details h3 {
        color: var(--hrb-text);
        margin-top: 0;
        margin-bottom: 30px;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--hrb-border-light);
    }

    .hrb-booking-details h3::before {
        content: '📋';
        font-size: 1.2em;
    }

    .hrb-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 0;
        border-bottom: 1px solid var(--hrb-border);
        transition: var(--hrb-transition);
    }

    .hrb-detail-row:hover {
        background: rgba(239, 68, 68, 0.02);
        margin: 0 -20px;
        padding: 16px 20px;
        border-radius: var(--hrb-radius);
    }

    .hrb-detail-row:last-child {
        border-bottom: none;
    }

    .hrb-detail-label {
        font-weight: 600;
        color: var(--hrb-text-light);
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .hrb-detail-value {
        color: var(--hrb-text);
        font-weight: 600;
        font-size: 1rem;
    }

    .hrb-status-cancelled {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #991b1b;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        box-shadow: var(--hrb-shadow);
        border: 2px solid var(--hrb-error);
    }

    .hrb-alert {
        padding: 20px;
        margin: 20px 0;
        border: 1px solid transparent;
        border-radius: 8px;
    }

    .hrb-alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    .hrb-alert-info {
        color: #0c5460;
        background-color: #d1ecf1;
        border-color: #bee5eb;
    }

    .hrb-alert-warning {
        color: #856404;
        background-color: #fff3cd;
        border-color: #ffeaa7;
    }

    .hrb-alert-error {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .hrb-next-steps {
        background: #e9ecef;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        margin: 40px;
    }

    .hrb-next-steps h3 {
        color: #495057;
        margin-top: 0;
        margin-bottom: 15px;
    }

    .hrb-next-steps ul {
        color: #495057;
        margin: 0;
        padding-left: 20px;
    }

    .hrb-next-steps li {
        margin-bottom: 8px;
    }

    .hrb-actions {
        text-align: center;
        margin: 50px 40px 40px;
        padding: 40px;
        background: var(--hrb-background-light);
        border-radius: var(--hrb-radius-lg);
        border: 1px solid var(--hrb-border);
        box-shadow: var(--hrb-shadow);
    }

    .hrb-actions h3 {
        color: var(--hrb-text);
        margin-top: 0;
        margin-bottom: 30px;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }

    .hrb-actions h3::before {
        content: '⚡';
        font-size: 1.2em;
    }

    .hrb-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 16px 32px;
        margin: 0 12px 12px 0;
        text-decoration: none;
        border-radius: var(--hrb-radius);
        font-weight: 700;
        font-size: 16px;
        transition: var(--hrb-transition);
        box-shadow: var(--hrb-shadow);
        position: relative;
        overflow: hidden;
        min-width: 160px;
        letter-spacing: 0.025em;
        border: 2px solid transparent;
        cursor: pointer;
    }

    .hrb-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .hrb-btn:hover::before {
        left: 100%;
    }

    .hrb-btn-primary {
        background: linear-gradient(135deg, var(--hrb-primary), var(--hrb-secondary));
        color: white;
    }

    .hrb-btn-primary:hover {
        background: linear-gradient(135deg, var(--hrb-secondary), #7c3aed);
        color: white;
        text-decoration: none;
        transform: translateY(-3px);
        box-shadow: var(--hrb-shadow-xl);
    }

    .hrb-btn-secondary {
        background: var(--hrb-background);
        color: var(--hrb-text);
        border-color: var(--hrb-border);
    }

    .hrb-btn-secondary:hover {
        background: var(--hrb-text);
        color: white;
        text-decoration: none;
        transform: translateY(-3px);
        box-shadow: var(--hrb-shadow-xl);
    }

    .hrb-btn-success {
        background: linear-gradient(135deg, var(--hrb-success), #059669);
        color: white;
    }

    .hrb-btn-success:hover {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        text-decoration: none;
        transform: translateY(-3px);
        box-shadow: var(--hrb-shadow-xl);
    }

    @media (max-width: 600px) {
        .hrb-booking-cancelled-container {
            padding: 15px;
            margin: 20px auto;
        }

        .hrb-detail-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .hrb-detail-value {
            margin-top: 5px;
        }

        .hrb-btn {
            display: block;
            margin: 10px 0;
        }
    }
    </style>

    <?php if ($cancellation_processed): ?>
        <!-- Cancellation Processed Successfully -->
        <div class="hrb-cancelled-header success">
            <span class="hrb-cancelled-icon"></span>
            <h1 class="hrb-cancelled-title"><?php _e('Booking Cancelled', 'hourly-room-booking'); ?></h1>
            <p class="hrb-cancelled-subtitle">
                <?php if ($is_paypal_cancellation): ?>
                    <?php _e('Your PayPal payment was cancelled and booking has been cancelled.', 'hourly-room-booking'); ?>
                <?php else: ?>
                    <?php _e('Your booking has been successfully cancelled.', 'hourly-room-booking'); ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="hrb-alert hrb-alert-success">
            <strong><?php _e('Cancellation Confirmed', 'hourly-room-booking'); ?></strong><br>
            <?php if ($is_paypal_cancellation): ?>
                <?php _e('Your PayPal payment was cancelled and the booking has been automatically cancelled. No charges were made to your account.', 'hourly-room-booking'); ?>
            <?php else: ?>
                <?php _e('Your booking has been cancelled. You will receive a confirmation email shortly.', 'hourly-room-booking'); ?>
            <?php endif; ?>
        </div>

    <?php elseif ($booking && $booking->status === 'cancelled'): ?>
        <!-- Booking Already Cancelled -->
        <div class="hrb-cancelled-header">
            <span class="hrb-cancelled-icon">❌</span>
            <h1 class="hrb-cancelled-title"><?php _e('Booking Cancelled', 'hourly-room-booking'); ?></h1>
            <p class="hrb-cancelled-subtitle"><?php _e('This booking has been cancelled.', 'hourly-room-booking'); ?></p>
        </div>

        <div class="hrb-alert hrb-alert-info">
            <strong><?php _e('Already Cancelled', 'hourly-room-booking'); ?></strong><br>
            <?php _e('This booking was previously cancelled.', 'hourly-room-booking'); ?>
        </div>

    <?php elseif (!$booking): ?>
        <!-- Booking Not Found -->
        <div class="hrb-cancelled-header">
            <span class="hrb-cancelled-icon">🔍</span>
            <h1 class="hrb-cancelled-title"><?php _e('Booking Not Found', 'hourly-room-booking'); ?></h1>
            <p class="hrb-cancelled-subtitle">
                <?php if ($paypal_token): ?>
                    <?php _e('The PayPal payment session could not be found or has expired.', 'hourly-room-booking'); ?>
                <?php else: ?>
                    <?php _e('The booking reference could not be found.', 'hourly-room-booking'); ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="hrb-alert hrb-alert-error">
            <strong><?php _e('Invalid Reference', 'hourly-room-booking'); ?></strong><br>
            <?php if ($paypal_token): ?>
                <?php _e('The PayPal payment session could not be found. This may happen if the session has expired or the payment was already processed. Please check your email for booking confirmations or contact us for assistance.', 'hourly-room-booking'); ?>
            <?php else: ?>
                <?php _e('The booking reference you provided could not be found. Please check the reference number and try again.', 'hourly-room-booking'); ?>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- Cancellation Failed or Other Error -->
        <div class="hrb-cancelled-header">
            <span class="hrb-cancelled-icon">�</span>
            <h1 class="hrb-cancelled-title"><?php _e('Cancellation Issue', 'hourly-room-booking'); ?></h1>
            <p class="hrb-cancelled-subtitle"><?php _e('There was an issue processing your cancellation.', 'hourly-room-booking'); ?></p>
        </div>

        <div class="hrb-alert hrb-alert-warning">
            <strong><?php _e('Cancellation Not Processed', 'hourly-room-booking'); ?></strong><br>
            <?php _e('Your booking cancellation could not be processed automatically. Please contact us directly to cancel your booking.', 'hourly-room-booking'); ?>
        </div>
    <?php endif; ?>

    <?php if ($booking): ?>
        <div class="hrb-booking-details">
            <h3><?php _e('Booking Information', 'hourly-room-booking'); ?></h3>

            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Booking Reference', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value"><?php echo esc_html($booking->booking_reference); ?></span>
            </div>

            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Room', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value"><?php echo esc_html($booking->room_name); ?></span>
            </div>

            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Date', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value"><?php echo esc_html(date('F j, Y', strtotime($booking->booking_date))); ?></span>
            </div>

            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Time', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value"><?php echo esc_html($booking->start_time . ' - ' . $booking->end_time); ?></span>
            </div>

            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Customer', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value"><?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?></span>
            </div>

            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Status', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value">
                    <span class="hrb-status-cancelled"><?php _e('Cancelled', 'hourly-room-booking'); ?></span>
                </span>
            </div>

            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Total Amount', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value"><?php echo number_format($booking->total_amount, 2) . ' �'; ?></span>
            </div>
        </div>

        <?php if ($cancellation_processed || $booking->status === 'cancelled'): ?>
            <div class="hrb-next-steps">
                <h3><?php _e('What happens next?', 'hourly-room-booking'); ?></h3>
                <ul>
                    <li><?php _e('You will receive a cancellation confirmation email within a few minutes.', 'hourly-room-booking'); ?></li>
                    <?php if ($booking->payment_method === 'paypal'): ?>
                        <li><?php _e('If you have already paid, refunds will be processed within 3-5 business days.', 'hourly-room-booking'); ?></li>
                    <?php elseif ($booking->payment_method === 'onsite'): ?>
                        <li><?php _e('Since you selected on-site payment, no refund processing is required.', 'hourly-room-booking'); ?></li>
                    <?php endif; ?>
                    <li><?php _e('You are welcome to make a new booking at any time.', 'hourly-room-booking'); ?></li>
                    <li><?php _e('If you have any questions, please contact us using the information below.', 'hourly-room-booking'); ?></li>
                </ul>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- Contact Information -->
    <div class="hrb-next-steps">
        <h3><?php _e('Need Help?', 'hourly-room-booking'); ?></h3>
        <p><?php _e('If you have any questions or need assistance, please contact us:', 'hourly-room-booking'); ?></p>
        <ul>
            <li><strong><?php _e('Email', 'hourly-room-booking'); ?>:</strong> <a href="mailto:<?php echo get_option('admin_email'); ?>"><?php echo get_option('admin_email'); ?></a></li>
            <?php $phone = get_option('hrb_company_phone'); if ($phone): ?>
            <li><strong><?php _e('Phone', 'hourly-room-booking'); ?>:</strong> <?php echo esc_html($phone); ?></li>
            <?php endif; ?>
            <li><strong><?php _e('Reference', 'hourly-room-booking'); ?>:</strong> <?php echo esc_html($booking_ref); ?></li>
        </ul>
    </div>

    <div class="hrb-actions">
        <?php if ($booking && ($cancellation_processed || $booking->status === 'cancelled')): ?>
            <a href="<?php echo home_url(); ?>" class="hrb-btn hrb-btn-success">
                <?php _e('Make New Booking', 'hourly-room-booking'); ?>
            </a>
        <?php endif; ?>

        <a href="mailto:<?php echo get_option('admin_email'); ?>?subject=<?php echo urlencode('Booking Inquiry - ' . $booking_ref); ?>" class="hrb-btn hrb-btn-primary">
            <?php _e('Contact Support', 'hourly-room-booking'); ?>
        </a>

        <a href="<?php echo home_url(); ?>" class="hrb-btn hrb-btn-secondary">
            <?php _e('Back to Home', 'hourly-room-booking'); ?>
        </a>
    </div>
</div>