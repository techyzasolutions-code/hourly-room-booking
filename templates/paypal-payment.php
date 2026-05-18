<?php
/**
 * PayPal Payment Page Template
 * Displays payment page for existing bookings when payment method is changed to PayPal
 */

if (!defined('ABSPATH')) {
    exit;
}

$booking = $GLOBALS['hrb_booking'] ?? null;

if (!$booking) {
    wp_redirect(home_url());
    exit;
}

// Get room details
global $wpdb;
$room = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}hrb_rooms WHERE id = %d",
    $booking->room_id
));

if (!$room) {
    wp_redirect(home_url());
    exit;
}

// Check if token parameter is in URL
$payment_token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : null;
$is_additional_payment = false;

// Find the payment record if token is provided
$payment_record = null;
$additional_amount = null;
$payment_error = null;
$payment_error_type = null; // 'link_error' or 'payment_completed'

// Check if token is provided and validate it first
if (!empty($payment_token)) {
    // Get payment record by token (secure - token cannot be easily guessed)
    $payment_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hrb_payments 
         WHERE payment_token = %s AND booking_id = %d
         LIMIT 1",
        $payment_token,
        $booking->id
    ));
    
    if ($payment_record) {
        // Token is valid - check payment status
        if ($payment_record->status === 'completed') {
            // Payment already completed with valid token
            $payment_error = __('Payment has already been completed for this booking. Please contact support if you have any questions.', 'hourly-room-booking');
            $payment_error_type = 'payment_completed';
        } else {
            // Payment is pending - check if this is an additional payment
            $is_additional_payment = (!empty($payment_record->transaction_id) && strpos($payment_record->transaction_id, 'ADD_') === 0);
            
            // Get amount from database, not from URL (security)
            if ($is_additional_payment) {
                $additional_amount = floatval($payment_record->amount);
            }
        }
    } else {
        // Invalid payment token - show error message
        $payment_error = __('Invalid payment link. Please use the payment link from your email or contact support.', 'hourly-room-booking');
        $payment_error_type = 'link_error';
    }
} else {
    // No token provided - check if payment is already completed (for backward compatibility)
    $completed_payment = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}hrb_payments 
         WHERE booking_id = %d AND payment_method = 'paypal' AND status = 'completed'
         AND (transaction_id IS NULL OR transaction_id NOT LIKE 'ADD_%%')
         LIMIT 1",
        $booking->id
    ));
    
    if ($completed_payment) {
        // Payment already completed (no token in URL)
        $payment_error = __('Payment has already been completed for this booking. Please contact support if you have any questions.', 'hourly-room-booking');
        $payment_error_type = 'payment_completed';
    }
}

// Get booking extras - filter by admin-added for additional payments
if ($is_additional_payment && $payment_record) {
    // For additional payments, only show extras added by admin
    $extras = $wpdb->get_results($wpdb->prepare(
        "SELECT be.*, e.name, e.price FROM {$wpdb->prefix}hrb_booking_extras be 
         LEFT JOIN {$wpdb->prefix}hrb_extras e ON be.extra_id = e.id 
         WHERE be.booking_id = %d 
         AND (be.added_by_admin = 1 OR be.added_by_admin = '1' OR be.added_by_admin = true)",
        $booking->id
    ));
} else {
    // For full payment, show all extras
    $extras = $wpdb->get_results($wpdb->prepare(
        "SELECT be.*, e.name, e.price FROM {$wpdb->prefix}hrb_booking_extras be 
         LEFT JOIN {$wpdb->prefix}hrb_extras e ON be.extra_id = e.id 
         WHERE be.booking_id = %d",
        $booking->id
    ));
}

// Calculate pricing
if ($is_additional_payment && $payment_record) {
    // For additional payments, use the payment record amount
    $pricing = array(
        'base_price' => 0,
        'extras_price' => 0,
        'extra_people_price' => 0,
        'tax_amount' => 0,
        'paypal_fee' => 0,
        'total_amount' => $additional_amount
    );
} else {
    // For full payment, use booking total
    $pricing = array(
        'base_price' => $booking->base_price,
        'extras_price' => $booking->extras_price,
        'extra_people_price' => $booking->extra_people_price,
        'tax_amount' => $booking->tax_amount,
        'paypal_fee' => $booking->paypal_fee,
        'total_amount' => $booking->total_amount
    );
}

// PayPal Client ID
$paypal_client_id = get_option('hrb_paypal_client_id');
$paypal_env = get_option('hrb_paypal_sandbox', 1) ? 'sandbox' : 'production';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (get_locale() === 'de_DE'): ?>
    document.title = 'PayPal-Zahlung - <?php echo esc_js(get_bloginfo('name')); ?>';
    <?php else: ?>
    document.title = 'PayPal Payment - <?php echo esc_js(get_bloginfo('name')); ?>';
    <?php endif; ?>
});

<?php if (get_locale() === 'de_DE'): ?>
document.title = 'PayPal-Zahlung - <?php echo esc_js(get_bloginfo('name')); ?>';
setTimeout(function() { document.title = 'PayPal-Zahlung - <?php echo esc_js(get_bloginfo('name')); ?>'; }, 100);
<?php else: ?>
document.title = 'PayPal Payment - <?php echo esc_js(get_bloginfo('name')); ?>';
setTimeout(function() { document.title = 'PayPal Payment - <?php echo esc_js(get_bloginfo('name')); ?>'; }, 100);
<?php endif; ?>
</script>

<div class="hrb-paypal-payment-container">
    <style>
    /* Enhanced PayPal Payment Page Variables */
    :root {
        --hrb-paypal-primary: #0070ba;
        --hrb-paypal-secondary: #005ea6;
        --hrb-paypal-light: #e7f3ff;
        --hrb-paypal-dark: #004d8f;
        --hrb-text: #1f2937;
        --hrb-text-light: #6b7280;
        --hrb-text-muted: #9ca3af;
        --hrb-border: #e5e7eb;
        --hrb-border-light: #f3f4f6;
        --hrb-background: #ffffff;
        --hrb-background-light: #f8fafc;
        --hrb-background-dark: #f1f5f9;
        --hrb-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
        --hrb-shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07), 0 2px 4px rgba(0, 0, 0, 0.06);
        --hrb-shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
        --hrb-shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
        --hrb-radius: 8px;
        --hrb-radius-lg: 12px;
        --hrb-radius-xl: 16px;
        --hrb-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .hrb-paypal-payment-container {
        max-width: 1000px;
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

    .hrb-payment-header {
        text-align: center;
        margin-bottom: 0;
        padding: 60px 40px;
        background: linear-gradient(135deg, var(--hrb-paypal-primary) 0%, var(--hrb-paypal-secondary) 100%);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .hrb-payment-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="paypal-grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23paypal-grain)"/></svg>');
        opacity: 0.3;
        pointer-events: none;
    }

    .hrb-payment-icon {
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

    .hrb-payment-title {
        font-size: 2.5rem;
        margin: 0 0 15px 0;
        font-weight: 700;
        position: relative;
        z-index: 1;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        letter-spacing: -0.02em;
    }

    .hrb-payment-subtitle {
        font-size: 1.2rem;
        margin: 0;
        opacity: 0.95;
        position: relative;
        z-index: 1;
        font-weight: 400;
    }

    .hrb-payment-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        padding: 40px;
        background: var(--hrb-background-light);
    }

    .hrb-booking-summary-card {
        background: var(--hrb-background);
        border-radius: var(--hrb-radius-lg);
        padding: 30px;
        box-shadow: var(--hrb-shadow-md);
        transition: var(--hrb-transition);
        border: 1px solid var(--hrb-border-light);
    }

    .hrb-booking-summary-card:hover {
        box-shadow: var(--hrb-shadow-lg);
        transform: translateY(-2px);
    }

    .hrb-summary-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0 0 25px 0;
        color: var(--hrb-text);
        border-bottom: 3px solid var(--hrb-paypal-primary);
        padding-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .hrb-summary-title::before {
        content: '📋';
        font-size: 1.5rem;
    }

    .hrb-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid var(--hrb-border-light);
        transition: var(--hrb-transition);
    }

    .hrb-summary-row:hover {
        background: var(--hrb-background-light);
        margin: 0 -15px;
        padding: 15px;
        border-radius: var(--hrb-radius);
    }

    .hrb-summary-row:last-child {
        border-bottom: none;
        font-weight: 700;
        font-size: 1.5rem;
        color: var(--hrb-paypal-primary);
        margin-top: 15px;
        padding-top: 20px;
        border-top: 2px solid var(--hrb-paypal-primary);
    }

    .hrb-summary-label {
        color: var(--hrb-text-light);
        font-weight: 500;
        flex-shrink: 0;
        margin-right: 15px;
        font-size: 15px;
    }

    .hrb-summary-value {
        color: var(--hrb-text);
        font-weight: 600;
        text-align: right;
        word-break: break-word;
    }

    .hrb-summary-row:last-child .hrb-summary-value {
        color: var(--hrb-paypal-primary);
        font-size: 1.5rem;
    }

    .hrb-payment-section-card {
        background: var(--hrb-background);
        border-radius: var(--hrb-radius-lg);
        padding: 40px;
        box-shadow: var(--hrb-shadow-md);
        transition: var(--hrb-transition);
        border: 1px solid var(--hrb-border-light);
        /* display: flex; */
        flex-direction: column;
        justify-content: center;
    }

    .hrb-payment-section-card:hover {
        box-shadow: var(--hrb-shadow-lg);
        transform: translateY(-2px);
    }

    .hrb-payment-section-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0 0 15px 0;
        color: var(--hrb-text);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .hrb-payment-section-title::before {
        /* content: '💳';
        font-size: 1.5rem; */
    }

    .hrb-payment-section-description {
        color: var(--hrb-text-light);
        margin: 0 0 30px 0;
        line-height: 1.6;
        font-size: 1rem;
    }

    .hrb-paypal-button-wrapper {
        margin-top: 20px;
        text-align: center;
    }

    .hrb-paypal-button {
        width: 100%;
        padding: 10px;
        background: #dc3545;
        color: white;
        border: 2px solid #dc3545;
        border-radius: var(--hrb-radius-lg);
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--hrb-transition);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        position: relative;
        overflow: hidden;
    }

    .hrb-paypal-button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .hrb-paypal-button:hover::before {
        left: 100%;
    }

    .hrb-paypal-button:hover {
        background: #c82333;
        border-color: #c82333;
        box-shadow: 0 6px 16px rgba(220, 53, 69, 0.4);
        transform: translateY(-2px);
    }

    .hrb-paypal-button:active {
        transform: translateY(0);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
    }

    .hrb-paypal-button:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    .hrb-paypal-button-icon {
        /* width: 60px; */
        height: 55px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
        border-radius: 8px;
        padding: 8px;
        flex-shrink: 0;
    }

    .hrb-paypal-button-icon img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .hrb-paypal-button-text {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
        flex: 1;
    }

    .hrb-paypal-button-text strong {
        font-size: 1.2rem;
        font-weight: 700;
        display: block;
        line-height: 1.2;
    }

    .hrb-paypal-button-text p {
        font-size: 0.9rem;
        font-weight: 400;
        margin: 4px 0 0 0;
        opacity: 0.95;
    }

    .hrb-paypal-button-loader {
        font-size: 1.5rem;
        animation: spin 1s linear infinite;
        display: none;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .hrb-paypal-button.loading .hrb-paypal-button-icon,
    .hrb-paypal-button.loading .hrb-paypal-button-text {
        opacity: 0.7;
    }

    .hrb-payment-info {
        margin-top: 20px;
        padding: 15px;
        background: var(--hrb-paypal-light);
        border-left: 4px solid var(--hrb-paypal-primary);
        border-radius: var(--hrb-radius);
        font-size: 0.9rem;
        color: var(--hrb-text);
        line-height: 1.6;
    }

    .hrb-payment-info strong {
        color: var(--hrb-paypal-primary);
        display: block;
        margin-bottom: 5px;
    }

    .hrb-payment-error {
        padding: 20px;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: var(--hrb-radius);
        margin-top: 20px;
        color: #856404;
    }

    .hrb-payment-error strong {
        display: block;
        margin-bottom: 8px;
        font-size: 1.1rem;
    }

    @media (max-width: 768px) {
        .hrb-paypal-payment-container {
            margin: 20px;
            border-radius: var(--hrb-radius-lg);
        }

        .hrb-payment-header {
            padding: 40px 20px;
        }

        .hrb-payment-title {
            font-size: 2rem;
        }

        .hrb-payment-subtitle {
            font-size: 1rem;
        }

        .hrb-payment-content {
            grid-template-columns: 1fr;
            gap: 20px;
            padding: 20px;
        }

        .hrb-booking-summary-card,
        .hrb-payment-section-card {
            padding: 25px;
        }
    }

    @media (max-width: 480px) {
        .hrb-payment-title {
            font-size: 1.75rem;
        }

        .hrb-summary-title,
        .hrb-payment-section-title {
            font-size: 1.25rem;
        }

        .hrb-summary-row:last-child {
            font-size: 1.25rem;
        }
    }
    </style>

    <div class="hrb-payment-header">
        <div class="hrb-payment-icon">💳</div>
        <h1 class="hrb-payment-title"><?php echo $is_additional_payment ? __('Additional Payment', 'hourly-room-booking') : __('PayPal Payment', 'hourly-room-booking'); ?></h1>
        <p class="hrb-payment-subtitle"><?php echo $is_additional_payment ? __('Please complete the additional payment for the services added to your booking', 'hourly-room-booking') : __('Please complete your payment to confirm your booking', 'hourly-room-booking'); ?></p>
    </div>

    <?php if ($payment_error): ?>
    <div class="hrb-payment-content" style="grid-template-columns: 1fr;">
        <?php if ($payment_error_type === 'payment_completed'): ?>
        <!-- Payment Already Completed Message -->
        <div class="hrb-booking-summary-card" style="background: #d1ecf1; border: 2px solid #0c5460;">
            <h3 class="hrb-summary-title" style="color: #0c5460; border-bottom-color: #0c5460;">
                <?php _e('Payment Already Completed', 'hourly-room-booking'); ?>
            </h3>
            <div style="padding: 20px 0; color: #0c5460; font-size: 1.1em;">
                <p style="margin: 0 0 15px 0;"><?php echo esc_html($payment_error); ?></p>
                <p style="margin: 0; font-size: 0.9em; opacity: 0.9;">
                    <?php _e('Your payment has been successfully processed. If you have any questions, please contact support.', 'hourly-room-booking'); ?>
                </p>
            </div>
        </div>
        <?php else: ?>
        <!-- Payment Link Error Message -->
        <div class="hrb-booking-summary-card" style="background: #fff3cd; border: 2px solid #ffc107;">
            <h3 class="hrb-summary-title" style="color: #856404; border-bottom-color: #ffc107;">
                <?php _e('Payment Link Error', 'hourly-room-booking'); ?>
            </h3>
            <div style="padding: 20px 0; color: #856404; font-size: 1.1em;">
                <p style="margin: 0 0 15px 0;"><?php echo esc_html($payment_error); ?></p>
                <p style="margin: 0; font-size: 0.9em;">
                    <?php _e('If you received this link via email, please use that exact link. If the problem persists, please contact support.', 'hourly-room-booking'); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="hrb-payment-content">
        <div class="hrb-booking-summary-card">
            <h3 class="hrb-summary-title"><?php _e('Booking Summary', 'hourly-room-booking'); ?></h3>
            
            <div class="hrb-summary-row">
                <span class="hrb-summary-label"><?php _e('Booking Reference:', 'hourly-room-booking'); ?></span>
                <span class="hrb-summary-value"><strong><?php echo esc_html($booking->booking_reference); ?></strong></span>
            </div>
            
            <div class="hrb-summary-row">
                <span class="hrb-summary-label"><?php _e('Room:', 'hourly-room-booking'); ?></span>
                <span class="hrb-summary-value"><?php echo esc_html($room->name); ?></span>
            </div>
            
            <div class="hrb-summary-row">
                <span class="hrb-summary-label"><?php _e('Date:', 'hourly-room-booking'); ?></span>
                <span class="hrb-summary-value"><?php echo date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date)); ?></span>
            </div>
            
            <div class="hrb-summary-row">
                <span class="hrb-summary-label"><?php _e('Time:', 'hourly-room-booking'); ?></span>
                <span class="hrb-summary-value"><?php echo date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->start_time)); ?> - <?php echo date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->end_time)); ?></span>
            </div>
            
            <div class="hrb-summary-row">
                <span class="hrb-summary-label"><?php _e('Duration:', 'hourly-room-booking'); ?></span>
                <span class="hrb-summary-value"><?php echo esc_html($booking->total_hours); ?> <?php _e('hours', 'hourly-room-booking'); ?></span>
            </div>
            
            <?php if (!empty($extras)): ?>
                <div class="hrb-summary-row">
                    <span class="hrb-summary-label"><?php _e('Extras:', 'hourly-room-booking'); ?></span>
                    <span class="hrb-summary-value">
                        <?php foreach ($extras as $extra): ?>
                            <?php echo esc_html($extra->name); ?> (<?php echo esc_html($extra->quantity); ?>x)<br>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <?php if (!$is_additional_payment): ?>
            <div class="hrb-summary-row">
                <span class="hrb-summary-label"><?php _e('Base Price:', 'hourly-room-booking'); ?></span>
                <span class="hrb-summary-value"><?php echo hrb_format_amount($pricing['base_price']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($is_additional_payment && $payment_record): ?>
                <!-- Additional Payment - Show only the additional amount -->
                <div class="hrb-summary-row" style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ffc107;">
                    <span class="hrb-summary-label" style="color: #856404; font-weight: bold;"><?php _e('Additional Payment Required:', 'hourly-room-booking'); ?></span>
                    <span class="hrb-summary-value" style="color: #856404; font-weight: bold; font-size: 1.2em;"><?php echo hrb_format_amount($additional_amount); ?></span>
                </div>
                <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #0070ba; font-size: 0.9em; color: #004085;">
                    <strong><?php _e('Note:', 'hourly-room-booking'); ?></strong> <?php _e('This payment is for additional services added to your booking after the original payment was completed.', 'hourly-room-booking'); ?>
                </div>
            <?php else: ?>
                <!-- Full Payment - Show full breakdown -->
                <?php if ($pricing['extras_price'] > 0): ?>
                    <div class="hrb-summary-row">
                        <span class="hrb-summary-label"><?php _e('Extras:', 'hourly-room-booking'); ?></span>
                        <span class="hrb-summary-value"><?php echo hrb_format_amount($pricing['extras_price']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($pricing['extra_people_price'] > 0): ?>
                    <div class="hrb-summary-row">
                        <span class="hrb-summary-label"><?php _e('Extra People:', 'hourly-room-booking'); ?></span>
                        <span class="hrb-summary-value"><?php echo hrb_format_amount($pricing['extra_people_price']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($pricing['tax_amount'] > 0): ?>
                    <div class="hrb-summary-row">
                        <span class="hrb-summary-label"><?php _e('Tax:', 'hourly-room-booking'); ?></span>
                        <span class="hrb-summary-value"><?php echo hrb_format_amount($pricing['tax_amount']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($pricing['paypal_fee'] > 0): ?>
                    <div class="hrb-summary-row">
                        <span class="hrb-summary-label"><?php _e('PayPal Fee:', 'hourly-room-booking'); ?></span>
                        <span class="hrb-summary-value"><?php echo hrb_format_amount($pricing['paypal_fee']); ?></span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="hrb-summary-row">
                <span class="hrb-summary-label"><?php echo $is_additional_payment ? __('Amount to Pay:', 'hourly-room-booking') : __('Total Amount:', 'hourly-room-booking'); ?></span>
                <span class="hrb-summary-value"><?php echo hrb_format_amount($pricing['total_amount']); ?></span>
            </div>
        </div>

        <div class="hrb-payment-section-card">
            <h3 class="hrb-payment-section-title"><?php echo $is_additional_payment ? __('Complete Additional Payment', 'hourly-room-booking') : __('Complete Your Payment', 'hourly-room-booking'); ?></h3>
            <p class="hrb-payment-section-description"><?php echo $is_additional_payment ? __('Click the button below to complete the additional payment for the services added to your booking. This payment is separate from your original booking payment.', 'hourly-room-booking') : __('Click the button below to complete your payment via PayPal. Your booking will be confirmed once payment is processed.', 'hourly-room-booking'); ?></p>
            
            <?php if (!empty($paypal_client_id)): ?>
                <div class="hrb-paypal-button-wrapper">
                    <button type="button" id="hrb-paypal-pay-button" class="hrb-paypal-button">
                        <span class="hrb-paypal-button-icon">
                            <img src="<?php echo HRB_PLUGIN_URL; ?>assets/images/payment-methods/paypal.png" alt="PayPal">
                        </span>
                        <span class="hrb-paypal-button-text">
                            <strong><?php _e('PayPal', 'hourly-room-booking'); ?></strong>
                            <p><?php _e('Secure online payment with PayPal (+3% fee)', 'hourly-room-booking'); ?></p>
                        </span>
                        <span class="hrb-paypal-button-loader" style="display: none;">⏳</span>
                    </button>
                </div>
                
                
            <?php else: ?>
                <div class="hrb-payment-error">
                    <strong><?php _e('PayPal Configuration Error', 'hourly-room-booking'); ?></strong>
                    <?php _e('PayPal is not configured. Please contact the administrator.', 'hourly-room-booking'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($paypal_client_id) && !$payment_error): ?>
<script>
jQuery(document).ready(function($) {
    $('#hrb-paypal-pay-button').on('click', function() {
        var $button = $(this);
        
        // Disable button and show loading
        $button.prop('disabled', true);
        $button.addClass('loading');
        $button.find('.hrb-paypal-button-icon').hide();
        $button.find('.hrb-paypal-button-text strong').text('<?php _e('Processing...', 'hourly-room-booking'); ?>');
        $button.find('.hrb-paypal-button-text p').text('');
        $button.find('.hrb-paypal-button-loader').show();
        
        // Create PayPal order
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'hrb_create_paypal_order_for_existing_booking',
                booking_id: <?php echo intval($booking->id); ?>,
                <?php if ($is_additional_payment && $payment_record): ?>
                payment_token: '<?php echo esc_js($payment_record->payment_token); ?>',
                <?php endif; ?>
                nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.approval_url) {
                    // Show redirect message
                    $button.find('.hrb-paypal-button-text strong').text('<?php _e('Redirecting to PayPal...', 'hourly-room-booking'); ?>');
                    
                    // Redirect to PayPal for payment
                    setTimeout(function() {
                        window.location.href = response.data.approval_url;
                    }, 1000);
                } else {
                    // Re-enable button and show error
                    $button.prop('disabled', false);
                    $button.removeClass('loading');
                    $button.find('.hrb-paypal-button-icon').show();
                    $button.find('.hrb-paypal-button-text strong').text('<?php _e('PayPal', 'hourly-room-booking'); ?>');
                    $button.find('.hrb-paypal-button-text p').text('<?php _e('Secure online payment with PayPal (+3% fee)', 'hourly-room-booking'); ?>');
                    $button.find('.hrb-paypal-button-loader').hide();
                    alert(response.data || '<?php _e('Failed to create PayPal order. Please try again.', 'hourly-room-booking'); ?>');
                }
            },
            error: function(xhr, status, error) {
                // Re-enable button and show error
                $button.prop('disabled', false);
                $button.removeClass('loading');
                $button.find('.hrb-paypal-button-icon').show();
                $button.find('.hrb-paypal-button-text strong').text('<?php _e('PayPal', 'hourly-room-booking'); ?>');
                $button.find('.hrb-paypal-button-text p').text('<?php _e('Secure online payment with PayPal (+3% fee)', 'hourly-room-booking'); ?>');
                $button.find('.hrb-paypal-button-loader').hide();
                alert('<?php _e('Network error occurred. Please try again.', 'hourly-room-booking'); ?>');
            }
        });
    });
});
</script>
<?php endif; ?>
