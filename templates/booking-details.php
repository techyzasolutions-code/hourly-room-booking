<?php
/**
 * Booking Details Page Template
 * Displays detailed booking information with management options
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get booking reference from URL
$booking_ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';

// Get booking details if reference provided
$booking = null;
if ($booking_ref) {
    global $wpdb;
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, r.name as room_name, r.description as room_description, c.first_name, c.last_name, c.email, c.phone, c.company
         FROM {$wpdb->prefix}hrb_bookings b
         JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
         JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
         WHERE b.booking_reference = %s",
        $booking_ref
    ));
}
?>

<div class="hrb-booking-details-container">
    <style>
    /* Enhanced Booking Details Variables */
    :root {
        --hrb-primary: #6366f1;
        --hrb-primary-dark: #4f46e5;
        --hrb-secondary: #8b5cf6;
        --hrb-accent: #06b6d4;
        --hrb-success: #10b981;
        --hrb-warning: #f59e0b;
        --hrb-error: #ef4444;
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

    .hrb-booking-details-container {
        max-width: 1100px;
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

    .hrb-details-header {
        text-align: center;
        margin-bottom: 0;
        padding: 60px 40px;
        background: linear-gradient(135deg, var(--hrb-primary) 0%, var(--hrb-secondary) 100%);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .hrb-details-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="details-grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23details-grain)"/></svg>');
        opacity: 0.3;
        pointer-events: none;
    }

    .hrb-details-icon {
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

    .hrb-details-title {
        font-size: 2.5rem;
        margin: 0 0 15px 0;
        font-weight: 700;
        position: relative;
        z-index: 1;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        letter-spacing: -0.02em;
    }

    .hrb-details-subtitle {
        font-size: 1.2rem;
        opacity: 0.95;
        margin: 0;
        position: relative;
        z-index: 1;
        font-weight: 400;
        line-height: 1.5;
    }

    .hrb-booking-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin: 40px;
        padding: 0;
    }

    .hrb-info-card {
        background: var(--hrb-background-light);
        border: 1px solid var(--hrb-border);
        border-radius: var(--hrb-radius-lg);
        padding: 30px;
        box-shadow: var(--hrb-shadow);
        transition: var(--hrb-transition);
        position: relative;
        overflow: hidden;
    }

    .hrb-info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--hrb-primary), var(--hrb-secondary));
    }

    .hrb-info-card:hover {
        box-shadow: var(--hrb-shadow-lg);
        transform: translateY(-2px);
    }

    .hrb-info-card h3 {
        color: var(--hrb-text);
        margin-top: 0;
        margin-bottom: 25px;
        font-size: 1.4rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--hrb-border-light);
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
        background: rgba(99, 102, 241, 0.02);
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

    .hrb-status-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        box-shadow: var(--hrb-shadow);
        border: 2px solid transparent;
    }

    .hrb-status-pending {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
        border-color: #f59e0b;
    }

    .hrb-status-confirmed {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #047857;
        border-color: var(--hrb-success);
    }

    .hrb-status-cancelled {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #991b1b;
        border-color: var(--hrb-error);
    }

    .hrb-payment-pending {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
        border-color: var(--hrb-warning);
    }

    .hrb-payment-paid {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #047857;
        border-color: var(--hrb-success);
    }

    .hrb-full-width-card {
        grid-column: 1 / -1;
    }

    .hrb-total-amount {
        background: #e9ecef;
        font-size: 1.2rem;
        font-weight: 600;
        color: #495057;
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
        background: linear-gradient(135deg, var(--hrb-primary), var(--hrb-primary-dark));
        color: white;
    }

    .hrb-btn-primary:hover {
        background: linear-gradient(135deg, var(--hrb-primary-dark), var(--hrb-secondary));
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

    .hrb-btn-danger {
        background: linear-gradient(135deg, var(--hrb-error), #dc2626);
        color: white;
    }

    .hrb-btn-danger:hover {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
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

    .hrb-alert {
        padding: 15px;
        margin: 20px 0;
        border: 1px solid transparent;
        border-radius: 4px;
    }

    .hrb-alert-info {
        color: #0c5460;
        background-color: #d1ecf1;
        border-color: #bee5eb;
    }

    .hrb-alert-error {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    @media (max-width: 768px) {
        .hrb-booking-details-container {
            padding: 15px;
            margin: 20px auto;
        }

        .hrb-booking-info-grid {
            grid-template-columns: 1fr;
            gap: 20px;
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

    <div class="hrb-details-header">
        <span class="hrb-details-icon">=�</span>
        <h1 class="hrb-details-title"><?php _e('Booking Details', 'hourly-room-booking'); ?></h1>
        <p class="hrb-details-subtitle"><?php _e('Complete information about your room booking', 'hourly-room-booking'); ?></p>
    </div>

    <?php if ($booking): ?>
        <div class="hrb-booking-info-grid">
            <!-- Booking Information -->
            <div class="hrb-info-card">
                <h3><?php _e('Booking Information', 'hourly-room-booking'); ?></h3>

                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Reference', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo esc_html($booking->booking_reference); ?></span>
                </div>

                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Status', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value">
                        <span class="hrb-status-badge hrb-status-<?php echo esc_attr($booking->status); ?>">
                            <?php echo esc_html(ucfirst($booking->status)); ?>
                        </span>
                    </span>
                </div>

                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Payment Status', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value">
                        <span class="hrb-status-badge hrb-payment-<?php echo esc_attr($booking->payment_status); ?>">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $booking->payment_status))); ?>
                        </span>
                    </span>
                </div>

                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Created', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo esc_html(date('F j, Y \a\t g:i A', strtotime($booking->created_at))); ?></span>
                </div>
            </div>

            <!-- Room Information -->
            <div class="hrb-info-card">
                <h3><?php _e('Room Information', 'hourly-room-booking'); ?></h3>

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
                    <span class="hrb-detail-label"><?php _e('Duration', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo esc_html($booking->total_hours . ' hours'); ?></span>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="hrb-info-card">
                <h3><?php _e('Customer Information', 'hourly-room-booking'); ?></h3>

                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Name', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?></span>
                </div>

                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Email', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo esc_html($booking->email); ?></span>
                </div>

                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Phone', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo esc_html($booking->phone); ?></span>
                </div>

                <?php if (!empty($booking->company)): ?>
                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Company', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo esc_html($booking->company); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Payment Information -->
            <div class="hrb-info-card">
                <h3><?php _e('Payment Information', 'hourly-room-booking'); ?></h3>

                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Payment Method', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo esc_html(ucfirst(str_replace('_', ' ', $booking->payment_method))); ?></span>
                </div>

                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Base Price', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo hrb_format_amount($booking->base_price); ?></span>
                </div>

                <?php if ($booking->extra_people > 0): ?>
                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Extra People', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php
                        echo $booking->extra_people . ' × ' . hrb_format_amount($booking->extra_people_price / $booking->extra_people) . ' = ' . hrb_format_amount($booking->extra_people_price);
                    ?></span>
                </div>
                <?php endif; ?>

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


                <div class="hrb-detail-row">
                    <span class="hrb-detail-label"><?php _e('Tax', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo get_option('hrb_currency_symbol', '€') . number_format($booking->tax_amount, 2); ?></span>
                </div>

                <div class="hrb-detail-row hrb-total-amount">
                    <span class="hrb-detail-label"><?php _e('Total Amount', 'hourly-room-booking'); ?>:</span>
                    <span class="hrb-detail-value"><?php echo get_option('hrb_currency_symbol', '€') . number_format($booking->total_amount, 2); ?></span>
                </div>
            </div>

            <!-- Special Requests -->
            <?php if (!empty($booking->special_requests)): ?>
            <div class="hrb-info-card hrb-full-width-card">
                <h3><?php _e('Special Requests', 'hourly-room-booking'); ?></h3>
                <p><?php echo esc_html($booking->special_requests); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="hrb-actions">
            <h3><?php _e('Booking Actions', 'hourly-room-booking'); ?></h3>

            <?php if ($booking->status === 'pending'): ?>
                <div class="hrb-alert hrb-alert-info">
                    <strong><?php _e('Pending Booking', 'hourly-room-booking'); ?></strong><br>
                    <?php _e('Your booking is currently pending confirmation. You will receive an email once it is confirmed.', 'hourly-room-booking'); ?>
                </div>
            <?php elseif ($booking->status === 'confirmed'): ?>
                <div class="hrb-alert hrb-alert-info">
                    <strong><?php _e('Confirmed Booking', 'hourly-room-booking'); ?></strong><br>
                    <?php if ($booking->payment_method === 'onsite'): ?>
                        <?php _e('Your booking is confirmed. Please bring payment when you arrive.', 'hourly-room-booking'); ?>
                    <?php else: ?>
                        <?php _e('Your booking is confirmed. We look forward to seeing you!', 'hourly-room-booking'); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($booking->status !== 'cancelled'): ?>
                <a href="mailto:<?php echo get_option('admin_email'); ?>?subject=<?php echo urlencode('Booking Modification Request - ' . $booking->booking_reference); ?>" class="hrb-btn hrb-btn-primary">
                    <?php _e('Request Modification', 'hourly-room-booking'); ?>
                </a>

                <button onclick="confirmCancellation('<?php echo esc_js($booking->booking_reference); ?>')" class="hrb-btn hrb-btn-danger">
                    <?php _e('Cancel Booking', 'hourly-room-booking'); ?>
                </button>
            <?php endif; ?>

            <a href="<?php echo home_url(); ?>" class="hrb-btn hrb-btn-secondary">
                <?php _e('Back to Home', 'hourly-room-booking'); ?>
            </a>
        </div>

        <script>
        function confirmCancellation(bookingRef) {
            if (confirm('<?php echo esc_js(__('Are you sure you want to cancel this booking? This action cannot be undone.', 'hourly-room-booking')); ?>')) {
                window.location.href = '<?php echo site_url('/booking-cancelled/'); ?>?ref=' + bookingRef + '&action=cancel&nonce=<?php echo wp_create_nonce('cancel_booking'); ?>';
            }
        }
        </script>

    <?php else: ?>
        <div class="hrb-alert hrb-alert-error">
            <h3><?php _e('Booking Not Found', 'hourly-room-booking'); ?></h3>
            <p><?php _e('The booking reference you provided could not be found. Please check the reference number and try again.', 'hourly-room-booking'); ?></p>
        </div>

        <div class="hrb-actions">
            <a href="<?php echo home_url(); ?>" class="hrb-btn hrb-btn-primary">
                <?php _e('Back to Home', 'hourly-room-booking'); ?>
            </a>
        </div>
    <?php endif; ?>
</div>