<?php
/**
 * Booking Success Page Template
 * Displays confirmation after successful booking
 */

if (!defined('ABSPATH')) {
    exit;
}

// Set page title using JavaScript since this is a template fragment
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set title based on current language
    <?php if (get_locale() === 'de_DE'): ?>
    document.title = 'Buchung bestätigt - <?php echo esc_js(get_bloginfo('name')); ?>';
    <?php else: ?>
    document.title = 'Booking Confirmed - <?php echo esc_js(get_bloginfo('name')); ?>';
    <?php endif; ?>
});

// Also try setting it immediately and with a timeout
<?php if (get_locale() === 'de_DE'): ?>
document.title = 'Buchung bestätigt - <?php echo esc_js(get_bloginfo('name')); ?>';
setTimeout(function() { document.title = 'Buchung bestätigt - <?php echo esc_js(get_bloginfo('name')); ?>'; }, 100);
<?php else: ?>
document.title = 'Booking Confirmed - <?php echo esc_js(get_bloginfo('name')); ?>';
setTimeout(function() { document.title = 'Booking Confirmed - <?php echo esc_js(get_bloginfo('name')); ?>'; }, 100);
<?php endif; ?>
</script>
<?php

// Handle PayPal return URL parameters
$paypal_order_id = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
$paypal_payer_id = isset($_GET['PayerID']) ? sanitize_text_field($_GET['PayerID']) : '';

// Get booking reference from URL
$booking_ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';

// If this is a PayPal return, we need to capture the payment
if ($paypal_order_id && $paypal_payer_id) {
    // Find the booking by PayPal order ID (stored in booking meta or payments table)
    global $wpdb;
    
    // First, try to find by PayPal order ID in payments table (stored in gateway_transaction_id)
    $payment = $wpdb->get_row($wpdb->prepare(
        "SELECT p.*, b.booking_reference
         FROM {$wpdb->prefix}hrb_payments p
         JOIN {$wpdb->prefix}hrb_bookings b ON p.booking_id = b.id
         WHERE p.gateway_transaction_id = %s AND p.payment_method = 'paypal'",
        $paypal_order_id
    ));
    
    if ($payment) {
        $booking_ref = $payment->booking_reference;

        // Only capture if payment is still pending (avoid duplicate captures)
        if ($payment->status === 'pending') {
            // Capture the PayPal payment
            $payment_handler = HRB_Payment_Handler::getInstance();
            $capture_result = $payment_handler->capture_paypal_payment_ajax($paypal_order_id, $payment->booking_id);

            if (is_wp_error($capture_result)) {
                // Handle capture error
                wp_redirect(site_url('/booking-cancelled/?error=' . urlencode($capture_result->get_error_message())));
                exit;
            }
        }
        // If payment is already completed, just proceed to show success page
    }
}

// Get booking details if reference provided
$booking = null;
if ($booking_ref) {
    global $wpdb;
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

<div class="hrb-booking-success-container">
    <style>
    /* Enhanced Success Page Variables */
    :root {
        --hrb-success-primary: #10b981;
        --hrb-success-secondary: #059669;
        --hrb-success-light: #d1fae5;
        --hrb-success-dark: #047857;
        --hrb-text: #1f2937;
        --hrb-text-light: #6b7280;
        --hrb-border: #e5e7eb;
        --hrb-background: #ffffff;
        --hrb-background-light: #f8fafc;
        --hrb-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
        --hrb-shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
        --hrb-shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
        --hrb-radius: 12px;
        --hrb-radius-lg: 16px;
        --hrb-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .hrb-booking-success-container {
        max-width: 900px;
        margin: 60px auto;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: var(--hrb-background);
        border-radius: var(--hrb-radius-lg);
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

    .hrb-success-header {
        text-align: center;
        margin-bottom: 0;
        padding: 60px 40px;
        background: linear-gradient(135deg, var(--hrb-success-primary) 0%, var(--hrb-success-secondary) 100%);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .hrb-success-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="success-grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23success-grain)"/></svg>');
        opacity: 0.3;
        pointer-events: none;
    }

    .hrb-success-icon {
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

    .hrb-success-title {
        font-size: 2.5rem;
        margin: 0 0 15px 0;
        font-weight: 700;
        position: relative;
        z-index: 1;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        letter-spacing: -0.02em;
    }

    .hrb-success-subtitle {
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
        border-radius: var(--hrb-radius);
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
        background: rgba(16, 185, 129, 0.02);
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

    .hrb-total-amount {
        background: linear-gradient(135deg, var(--hrb-success-light), rgba(16, 185, 129, 0.1));
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--hrb-success-dark);
        border: 2px solid var(--hrb-success-light);
        border-radius: var(--hrb-radius);
        padding: 16px 20px;
        margin: 20px -20px -20px -20px;
        text-align: center;
    }

    .hrb-next-steps {
        background: linear-gradient(135deg, var(--hrb-success-light), rgba(16, 185, 129, 0.05));
        border: 2px solid var(--hrb-success-light);
        border-radius: var(--hrb-radius);
        padding: 30px;
        margin: 40px;
        box-shadow: var(--hrb-shadow);
        position: relative;
        overflow: hidden;
    }

    .hrb-next-steps::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="steps-grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(16,185,129,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(16,185,129,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(16,185,129,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(16,185,129,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(16,185,129,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23steps-grain)"/></svg>');
        opacity: 0.3;
        pointer-events: none;
    }

    .hrb-next-steps h3 {
        color: var(--hrb-success-dark);
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 1.3rem;
        font-weight: 700;
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .hrb-next-steps h3::before {
        content: '✨';
        font-size: 1.2em;
    }

    .hrb-next-steps ul {
        color: var(--hrb-success-dark);
        margin: 0;
        padding-left: 0;
        list-style: none;
        position: relative;
        z-index: 1;
    }

    .hrb-next-steps li {
        margin-bottom: 12px;
        padding-left: 30px;
        position: relative;
        font-weight: 500;
        line-height: 1.6;
    }

    .hrb-next-steps li::before {
        content: '✓';
        position: absolute;
        left: 0;
        top: 0;
        width: 20px;
        height: 20px;
        background: var(--hrb-success-primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
    }

    .hrb-actions {
        text-align: center;
        margin: 50px 40px 40px;
        padding: 30px 0;
        border-top: 1px solid var(--hrb-border);
    }
    
    /* Print styles */
    @media print {
        body * { visibility: hidden !important; }
        .hrb-booking-success-container, .hrb-booking-success-container * { visibility: visible !important; }
        .hrb-booking-success-container { position: static; box-shadow: none; margin: 0; max-width: none; }
        .hrb-actions { display: none !important; }
        .hrb-success-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .hrb-booking-details, .hrb-next-steps { margin: 20px; box-shadow: none; }
    }

    .hrb-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 16px 32px;
        margin: 0 12px;
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
        background: linear-gradient(135deg, var(--hrb-success-primary), var(--hrb-success-secondary));
        color: white;
        border: 2px solid transparent;
    }

    .hrb-btn-primary:hover {
        background: linear-gradient(135deg, var(--hrb-success-secondary), var(--hrb-success-dark));
        color: white;
        text-decoration: none;
        transform: translateY(-3px);
        box-shadow: var(--hrb-shadow-xl);
    }

    .hrb-btn-secondary {
        background: var(--hrb-background);
        color: var(--hrb-text);
        border: 2px solid var(--hrb-border);
    }

    .hrb-btn-secondary:hover {
        background: var(--hrb-text);
        color: white;
        text-decoration: none;
        transform: translateY(-3px);
        box-shadow: var(--hrb-shadow-xl);
    }

    @media (max-width: 600px) {
        .hrb-booking-success-container {
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

    <div class="hrb-success-header">
        <span class="hrb-success-icon">✓</span>
        <h1 class="hrb-success-title"><?php _e('Booking Confirmed!', 'hourly-room-booking'); ?></h1>
        <p class="hrb-success-subtitle"><?php _e('Your room booking has been successfully created.', 'hourly-room-booking'); ?></p>
        
        <?php if ($booking): ?>
        <div style="margin-top: 30px;">
            <button type="button" class="hrb-btn hrb-btn-primary" onclick="window.print()" style="background: rgba(255, 255, 255, 0.2); border: 2px solid rgba(255, 255, 255, 0.3); color: white; backdrop-filter: blur(10px);">
                🖨️ <?php _e('Print Booking Details', 'hourly-room-booking'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($booking): ?>
        <div class="hrb-booking-details">
            <h3><?php _e('Booking Details', 'hourly-room-booking'); ?></h3>
            <?php if ($booking && $booking->is_anonymous): ?>
            <div style="background: linear-gradient(135deg, #fef3c7, #fde68a); border: 2px solid #f59e0b; border-radius: 12px; padding: 20px; margin: 20px 0; text-align: center;">
            <div style="color: #92400e; font-weight: 500;">
                    <?php _e('✅ Deine anonyme Buchung war erfolgreich!', 'hourly-room-booking'); ?>
                </div>
                <div style="font-size: 1.5rem; font-weight: 700; color: #92400e; margin-bottom: 10px;">
                    <?php _e('Buchungs-ID:', 'hourly-room-booking'); ?> #<?php echo esc_html($booking->booking_reference); ?>
                </div>
                <div style="color: #92400e; font-weight: 500;">
                    <?php _e('Bitte notiere oder speichere diese ID. Sie dient als einziger Nachweis deiner Buchung und wird nicht per E-Mail versendet.', 'hourly-room-booking'); ?>
                </div>
            </div>
        <?php endif; ?>
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
                <span class="hrb-detail-value"><?php echo esc_html(date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date))); ?></span>
            </div>

            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Time', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value"><?php echo esc_html(date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->start_time)) . ' - ' . date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->end_time))); ?></span>
            </div>

            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Duration', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value"><?php echo esc_html($booking->total_hours); ?> <?php _e('hours', 'hourly-room-booking'); ?></span>
            </div>

            <?php if (!$booking->is_anonymous): ?>
                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Customer', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?></span>
                </div>

                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Email', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo esc_html($booking->email); ?></span>
                </div>
            <?php endif; ?>

            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Payment Method', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value"><?php echo esc_html($booking->payment_method === 'paypal' ? 'PayPal' : __('On-site Payment', 'hourly-room-booking')); ?></span>
            </div>

            <?php
            // Check if there are any extras for this booking
            $extras_manager = HRB_Extras::getInstance();
            $booking_extras = $extras_manager->get_booking_extras($booking->id);
            if (!empty($booking_extras)): ?>
            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Extras', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value">
                    <?php
                    $extras_list = [];
                    foreach ($booking_extras as $extra) {
                        $extras_list[] = $extra->name . ' (' . hrb_format_amount($extra->total_price) . ')';
                    }
                    echo implode(', ', $extras_list);
                    ?>
                </span>
            </div>
            <?php elseif ($booking->extras_price > 0): ?>
            <div class="hrb-detail-row">
                <span class="hrb-detail-label"><?php _e('Extras', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value"><?php echo hrb_format_amount($booking->extras_price); ?></span>
            </div>
            <?php endif; ?>

            <div class="hrb-detail-row hrb-total-amount">
                <span class="hrb-detail-label"><?php _e('Total Amount', 'hourly-room-booking'); ?>:</span>
                <span class="hrb-detail-value"><?php echo hrb_format_amount($booking->total_amount); ?></span>
            </div>
        </div>

        <div class="hrb-next-steps">
            <h3><?php _e('What happens next?', 'hourly-room-booking'); ?></h3>
            <ul>
                <?php if (!$booking->is_anonymous): ?>
                <li><?php _e('You will receive a confirmation email shortly with all the booking details.', 'hourly-room-booking'); ?></li>
                <?php endif; ?>
                <?php if ($booking->payment_method === 'onsite'): ?>
                    <li><?php _e('Only cash payments are accepted on site when you arrive for your booking.', 'hourly-room-booking'); ?></li>
                <?php endif; ?>
                
                <?php if (!$booking->is_anonymous): ?>
                <li><?php _e('If you need to cancel or modify your booking, please contact us as soon as possible.', 'hourly-room-booking'); ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="hrb-next-steps">
            <h3><?php _e('KURZFRISTIGE STORNIERUNGEN', 'hourly-room-booking'); ?></h3>
            <p>Werden Zimmer (ohne Zusatzleistung - maximal 3 Stunden) innerhalb der letzten 24h vor Buchungsbeginn storniert, so wird eine Stornogebühr von 15 Euro fällig.</p>
        </div>
        
    <?php else: ?>
        <div class="hrb-booking-details">
            <h3><?php _e('Booking Information', 'hourly-room-booking'); ?></h3>
            <p><?php _e('Your booking has been confirmed. You should receive a confirmation email shortly.', 'hourly-room-booking'); ?></p>
        </div>
    <?php endif; ?>

    <div class="hrb-actions">
        <?php if ($booking): ?>
            <a href="<?php echo site_url('/booking-details/?ref=' . $booking->booking_reference); ?>" class="hrb-btn hrb-btn-primary">
                <?php _e('View Full Details', 'hourly-room-booking'); ?>
            </a>
            <button type="button" class="hrb-btn hrb-btn-secondary" onclick="window.print()">
            🖨️ <?php _e('Print', 'hourly-room-booking'); ?>
            </button>
        <?php endif; ?>
        <a href="<?php echo home_url(); ?>" class="hrb-btn hrb-btn-secondary">
            <?php _e('Back to Home', 'hourly-room-booking'); ?>
        </a>
    </div>
</div>