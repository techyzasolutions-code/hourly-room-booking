<?php

/**
 * Payments Management View
 * Handles payment transactions, refunds, and payment method management
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get payment manager and stats
$payment_manager = HRB_Payment_Manager::getInstance();

// Handle filters and pagination
$filters = [
    'status' => sanitize_text_field($_GET['status'] ?? ''),
    'payment_method' => sanitize_text_field($_GET['payment_method'] ?? ''),
    'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
    'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
    'search' => sanitize_text_field($_GET['s'] ?? ''),
];

$per_page = 20;
$current_page = max(1, intval($_GET['paged'] ?? 1));
$offset = ($current_page - 1) * $per_page;

// Get payments data
$payments_data = $payment_manager->get_payments($filters, $per_page, $offset);
$payments = $payments_data['payments'];
$total_count = $payments_data['total'];
$total_pages = ceil($total_count / $per_page);

// Get payment statistics
$payment_stats = $payment_manager->get_payment_statistics($filters);

// Get currency symbol from settings
$currency_symbol = hrb_get_currency_symbol();
?>

<div class="hrb-admin-page">
    <div class="hrb-page-header">
        <div class="hrb-page-title">
            <h1><?php _e('Payment Management', 'hourly-room-booking'); ?></h1>
            <p class="description"><?php _e('Manage payment transactions, process refunds, and view payment analytics.', 'hourly-room-booking'); ?></p>
        </div>
        <div class="hrb-page-actions">
            <button type="button" class="button" onclick="exportPayments()">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export Payments', 'hourly-room-booking'); ?>
            </button>
        </div>
    </div>

    <!-- Payment Statistics -->
    <div class="hrb-stats-grid">
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
                <div class="hrb-stat-number"><?php echo $currency_symbol . number_format($payment_stats['total_revenue'] ?? 0, 2); ?></div>
                <div class="hrb-stat-label"><?php _e('Total Revenue', 'hourly-room-booking'); ?></div>
            </div>

        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php echo $currency_symbol . number_format($payment_stats['monthly_revenue'] ?? 0, 2); ?></div>
            <div class="hrb-stat-label"><?php _e('This Month', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php echo intval($payment_stats['total_transactions'] ?? 0); ?></div>
            <div class="hrb-stat-label"><?php _e('Total Transactions', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php echo $currency_symbol . number_format($payment_stats['pending_refunds'] ?? 0, 2); ?></div>
            <div class="hrb-stat-label"><?php _e('Pending Refunds', 'hourly-room-booking'); ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="hrb-filters">
        <form method="get" class="hrb-filter-form">
            <input type="hidden" name="page" value="hrb-payments">

            <div class="hrb-filter-group">
                <label for="filter-status"><?php _e('Status:', 'hourly-room-booking'); ?></label>
                <select name="status" id="filter-status">
                    <option value=""><?php _e('All Statuses', 'hourly-room-booking'); ?></option>
                    <option value="completed" <?php selected($filters['status'], 'completed'); ?>><?php _e('Completed', 'hourly-room-booking'); ?></option>
                    <option value="pending" <?php selected($filters['status'], 'pending'); ?>><?php _e('Pending', 'hourly-room-booking'); ?></option>
                    <option value="failed" <?php selected($filters['status'], 'failed'); ?>><?php _e('Failed', 'hourly-room-booking'); ?></option>
                    <option value="refunded" <?php selected($filters['status'], 'refunded'); ?>><?php _e('Refunded', 'hourly-room-booking'); ?></option>
                    <option value="partially_refunded" <?php selected($filters['status'], 'partially_refunded'); ?>><?php _e('Partially Refunded', 'hourly-room-booking'); ?></option>
                </select>
            </div>

            <div class="hrb-filter-group">
                <label for="filter-payment-method"><?php _e('Payment Method:', 'hourly-room-booking'); ?></label>
                <select name="payment_method" id="filter-payment-method">
                    <option value=""><?php _e('All Methods', 'hourly-room-booking'); ?></option>
                    <option value="paypal" <?php selected($filters['payment_method'], 'paypal'); ?>><?php _e('PayPal', 'hourly-room-booking'); ?></option>
                    <option value="onsite" <?php selected($filters['payment_method'], 'onsite'); ?>><?php _e('On-site Payment', 'hourly-room-booking'); ?></option>
                </select>
            </div>

            <div class="hrb-filter-group">
                <label for="filter-date-from"><?php _e('From:', 'hourly-room-booking'); ?></label>
                <input type="date" name="date_from" id="filter-date-from" value="<?php echo esc_attr($filters['date_from']); ?>">
            </div>

            <div class="hrb-filter-group">
                <label for="filter-date-to"><?php _e('To:', 'hourly-room-booking'); ?></label>
                <input type="date" name="date_to" id="filter-date-to" value="<?php echo esc_attr($filters['date_to']); ?>">
            </div>

            <div class="hrb-filter-group">
                <input type="search" name="s" placeholder="<?php _e('Search transactions...', 'hourly-room-booking'); ?>" value="<?php echo esc_attr($filters['search']); ?>">
            </div>

            <div class="hrb-filter-actions">
                <button type="submit" class="button"><?php _e('Filter', 'hourly-room-booking'); ?></button>
                <a href="<?php echo admin_url('admin.php?page=hrb-payments'); ?>" class="button"><?php _e('Clear', 'hourly-room-booking'); ?></a>
            </div>
        </form>
    </div>

    <!-- Payments Table -->
    <div class="hrb-table-container">
        <table class="wp-list-table widefat striped payments">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </th>
                    <th scope="col" class="manage-column column-transaction"><?php _e('Transaction', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="manage-column column-booking"><?php _e('Booking', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="manage-column column-customer"><?php _e('Customer', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="manage-column column-amount"><?php _e('Amount', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="manage-column column-method"><?php _e('Method', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="manage-column column-status"><?php _e('Status', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="manage-column column-date"><?php _e('Date', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'hourly-room-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payments)): ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr data-payment-id="<?php echo $payment->id; ?>">
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="payment[]" value="<?php echo $payment->id; ?>">
                            </th>
                            <td class="column-transaction">
                                <strong>
                                    <a href="#" onclick="viewPaymentDetails(<?php echo $payment->id; ?>)">
                                        <?php echo esc_html($payment->transaction_id ?: '#' . $payment->id); ?>
                                    </a>
                                </strong>
                                <?php if ($payment->gateway_transaction_id): ?>
                                    <div class="gateway-id">
                                        <small><?php echo esc_html($payment->gateway_transaction_id); ?></small>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="column-booking">
                                <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=edit&booking_id=' . $payment->booking_id); ?>">
                                    #<?php echo $payment->booking_id; ?>
                                </a>
                                <div class="booking-details">
                                    <small><?php echo esc_html($payment->room_name ?? 'N/A'); ?></small><br>
                                    <small><?php echo date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($payment->booking_date ?? 'now')); ?></small>
                                </div>
                            </td>
                            <td class="column-customer">
                                <?php echo hrb_display_customer_info($payment, 'name_email'); ?>
                            </td>
                            <td class="column-amount">
                                <strong><?php echo $currency_symbol . number_format($payment->amount, 2); ?></strong>
                                <?php if ($payment->fees > 0): ?>
                                    <div class="fees">
                                        <small><?php printf(__('Fees: %s%s', 'hourly-room-booking'), $currency_symbol, number_format($payment->fees, 2)); ?></small>
                                    </div>
                                <?php endif; ?>
                                <?php if ($payment->refunded_amount > 0): ?>
                                    <div class="refunded">
                                        <small><?php printf(__('Refunded: %s%s', 'hourly-room-booking'), $currency_symbol, number_format($payment->refunded_amount, 2)); ?></small>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="column-method">
                                <span class="payment-method payment-method-<?php echo $payment->payment_method; ?>">
                                    <?php echo esc_html(hrb_get_payment_method_label($payment->payment_method)); ?>
                                </span>
                            </td>
                            <td class="column-status">
                                <span class="hrb-status status-<?php echo $payment->status; ?>">
                                    <?php echo esc_html(hrb_get_payment_status_label($payment->status)); ?>
                                </span>
                            </td>
                            <td class="column-date">
                                <?php echo date_i18n(get_option('hrb_date_format', 'd.m.Y') . ' ' . get_option('hrb_time_format', 'H:i'), strtotime($payment->created_at)); ?>
                            </td>
                            <td class="column-actions">
                                <div class="hrb-actions">
                                    <button type="button" class="button button-small" onclick="viewPaymentDetails(<?php echo $payment->id; ?>)" title="<?php _e('View Payment Details', 'hourly-room-booking'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>

                                    <?php if ($payment->status === 'completed' && $payment->refunded_amount < $payment->amount): ?>
                                        <button type="button" class="button button-small hrb-refund-btn" onclick="processRefund(<?php echo $payment->id; ?>)" title="<?php _e('Process Refund', 'hourly-room-booking'); ?>">
                                            <span class="dashicons dashicons-undo"></span>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($payment->status === 'pending'): ?>
                                        <button type="button" class="button button-small hrb-complete-btn" onclick="markPaymentCompleted(<?php echo $payment->id; ?>)" title="<?php _e('Mark as Completed', 'hourly-room-booking'); ?>">
                                            <span class="dashicons dashicons-yes"></span>
                                        </button>
                                        <button type="button" class="button button-small hrb-cancel-btn" onclick="cancelPayment(<?php echo $payment->id; ?>)" title="<?php _e('Cancel Payment', 'hourly-room-booking'); ?>">
                                            <span class="dashicons dashicons-no"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="no-items"><?php _e('No payments found.', 'hourly-room-booking'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="hrb-pagination">
            <?php
            $page_links = paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo; ' . __('Previous', 'hourly-room-booking'),
                'next_text' => __('Next', 'hourly-room-booking') . ' &raquo;',
                'current' => $current_page,
                'total' => $total_pages,
                'type' => 'plain'
            ]);

            if ($page_links) {
                echo $page_links;
            }
            ?>
        </div>
    <?php endif; ?>
</div>

<!-- Payment Details Modal -->
<div id="payment-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h2 id="payment-modal-title"><?php _e('Payment Details', 'hourly-room-booking'); ?></h2>
            <span class="hrb-modal-close" onclick="closePaymentModal()">&times;</span>
        </div>
        <div class="hrb-modal-body" id="payment-modal-body">
            <!-- Content loaded via AJAX -->
        </div>
        <div class="hrb-modal-footer">
            <button type="button" class="button" onclick="closePaymentModal()"><?php _e('Close', 'hourly-room-booking'); ?></button>
        </div>
    </div>
</div>

<!-- Refund Modal -->
<div id="refund-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h2><?php _e('Process Refund', 'hourly-room-booking'); ?></h2>
            <span class="hrb-modal-close" onclick="closeRefundModal()">&times;</span>
        </div>
        <form id="refund-form">
            <div class="hrb-modal-body">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="refund-amount"><?php printf(__('Refund Amount (%s)', 'hourly-room-booking'), $currency_symbol); ?></label>
                        </th>
                        <td>
                            <input type="number" name="refund_amount" id="refund-amount" step="0.01" min="0" class="regular-text" required>
                            <p class="description" id="refund-available"></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="refund-reason"><?php _e('Reason', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="refund_reason" id="refund-reason" rows="3" class="large-text" required placeholder="<?php _e('Please provide a reason for the refund...', 'hourly-room-booking'); ?>"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="notify-customer"><?php _e('Notify Customer', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="notify_customer" id="notify-customer" checked>
                                <?php _e('Send email notification to customer', 'hourly-room-booking'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="payment_id" id="refund-payment-id">
                <input type="hidden" name="action" value="hrb_process_refund">
                <?php wp_nonce_field('hrb_admin_action', '_wpnonce'); ?>
            </div>
            <div class="hrb-modal-footer">
                <button type="button" class="button" onclick="closeRefundModal()"><?php _e('Cancel', 'hourly-room-booking'); ?></button>
                <button type="submit" class="button button-primary"><?php _e('Process Refund', 'hourly-room-booking'); ?></button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Modern Professional Payments Management Styling */
    .hrb-admin-page {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 24px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .hrb-page-header {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        color: white;
        padding: 32px;
        border-radius: 6px;
        margin-bottom: 32px;
        box-shadow: 0 8px 32px rgba(220, 38, 38, 0.15);
        position: relative;
        overflow: hidden;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .hrb-page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -30px;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        backdrop-filter: blur(20px);
    }

    .hrb-page-title h1 {
        margin: 0 0 8px 0;
        font-size: 2.5rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        color: white;
    }

    .hrb-page-title .description {
        margin: 0;
        color: rgba(255, 255, 255, 0.9);
        font-size: 1.1rem;
        font-weight: 400;
    }

    .hrb-page-actions {
        z-index: 2;
    }

    .hrb-page-actions .button {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
        font-weight: 600;
        padding: 12px 24px;
        border-radius: 8px;
        backdrop-filter: blur(10px);
        transition: all 0.3s ease;
        text-shadow: none;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    }

    .hrb-page-actions .button:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
        box-shadow: 0 6px 24px rgba(0, 0, 0, 0.15);
    }

    .hrb-page-actions .dashicons {
        margin-right: 8px;
    }

    /* Modern Stats Grid */
    .hrb-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .hrb-stat-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 18px 20px;
        text-align: center;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .hrb-stat-card::before {
        content: '';
        position: absolute;
        top: -40px;
        right: -40px;
        width: 140px;
        height: 140px;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.10) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .hrb-stat-card:nth-child(2)::before {
        background: radial-gradient(circle, rgba(59, 130, 246, 0.10) 0%, transparent 70%);
    }

    .hrb-stat-card:nth-child(3)::before {
        background: radial-gradient(circle, rgba(245, 158, 11, 0.10) 0%, transparent 70%);
    }

    .hrb-stat-card:nth-child(4)::before {
        background: radial-gradient(circle, rgba(220, 38, 38, 0.10) 0%, transparent 70%);
    }

    .hrb-stat-card:hover {
        transform: translateY(-2px);
        border-color: #dc2626;
        box-shadow: 0 6px 16px -8px rgba(220, 38, 38, 0.22);
    }

    .hrb-stat-number {
        font-size: 26px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
        margin-bottom: 4px;
        letter-spacing: -0.5px;
        position: relative;
        z-index: 1;
    }

    .hrb-stat-label {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        position: relative;
        z-index: 1;
    }

    /* Modern Filters */
    .hrb-filters {
        background: white;
        padding: 24px;
        border-radius: 4px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid rgba(0, 0, 0, 0.05);
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
        font-weight: 600;
        font-size: 0.9rem;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .hrb-filter-group select,
    .hrb-filter-group input {
        padding: 8px 12px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 0.9rem;
        background: white;
        transition: border-color 0.2s ease;
    }

    .hrb-filter-group select:focus,
    .hrb-filter-group input:focus {
        border-color: #dc2626;
        outline: none;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    }

    .hrb-filter-actions {
        display: flex;
        gap: 12px;
    }

    .hrb-filter-actions .button {
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 500;
    }

    /* Modern Table Container */
    .hrb-table-container {
        background: white;
        border-radius: 4px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .wp-list-table {
        border: none;
    }

    .wp-list-table thead th {
        background: #f8f9fa;
        color: #374151;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        padding: 20px 16px;
        border-bottom: 2px solid #e5e7eb;
    }

    .wp-list-table tbody td {
        padding: 20px 16px;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }

    .wp-list-table tbody tr {
        transition: background-color 0.2s ease;
    }

    .wp-list-table tbody tr:hover {
        background: #f8f9fa;
    }

    .column-transaction {
        width: 15%;
    }

    .column-booking {
        width: 12%;
    }

    .column-customer {
        width: 18%;
    }

    .column-amount {
        width: 12%;
    }

    .column-method {
        width: 10%;
    }

    .column-status {
        width: 12%;
    }

    .column-date {
        width: 12%;
    }

    .column-actions {
        width: 9%;
    }

    /* Enhanced Actions - Matching Bookings Page Style */
    .hrb-actions {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .hrb-actions .button {
        padding: 8px;
        border-radius: 8px;
        border: none;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        min-width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .hrb-actions .button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .hrb-actions .button-small {
        background: linear-gradient(135deg, #b91c1c, #dc2626);
        color: white;
    }

    .hrb-actions .hrb-complete-btn {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .hrb-actions .hrb-refund-btn {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .hrb-actions .hrb-cancel-btn {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .gateway-id {
        color: #6b7280;
        font-size: 0.8rem;
        margin-top: 4px;
        font-weight: 500;
    }

    .booking-details {
        font-size: 0.8rem;
        color: #6b7280;
        margin-top: 4px;
        line-height: 1.4;
    }

    .fees,
    .refunded {
        font-size: 0.8rem;
        color: #6b7280;
        margin-top: 4px;
        font-weight: 500;
    }

    .payment-method {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: inline-block;
    }

    .payment-method-paypal {
        background: linear-gradient(135deg, #0070ba, #005eb8);
        color: white;
    }

    .payment-method-stripe {
        background: linear-gradient(135deg, #635bff, #5a52d5);
        color: white;
    }

    .payment-method-bank_transfer {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .payment-method-cash,
    .payment-method-onsite {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
    }

    .hrb-status {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: inline-block;
    }

    .status-completed {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #065f46;
    }

    .status-pending {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
    }

    .status-failed {
        background: linear-gradient(135deg, #fecaca, #fca5a5);
        color: #7f1d1d;
    }

    .status-refunded {
        background: linear-gradient(135deg, #fed7aa, #fdba74);
        color: #9a3412;
    }

    .status-partially_refunded {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
    }

    .status-cancelled {
        background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        color: #374151;
    }

    .row-actions {
        font-size: 0.8rem;
    }

    .row-actions a {
        font-weight: 500;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .row-actions .submitdelete {
        color: #dc2626;
    }

    .row-actions .submitdelete:hover {
        color: #b91c1c;
    }

    /* Modern Pagination */
    .hrb-pagination {
        margin-top: 32px;
        text-align: center;
    }

    .hrb-pagination .page-numbers {
        display: inline-block;
        padding: 8px 12px;
        margin: 0 4px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .hrb-pagination .page-numbers:hover,
    .hrb-pagination .page-numbers.current {
        background: #dc2626;
        border-color: #dc2626;
        color: white;
    }

    /* Modern Modal */
    .hrb-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .hrb-modal-content {
        background: white;
        border-radius: 6px;
        width: 90%;
        max-width: 600px;
        max-height: 90%;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .hrb-modal-header {
        padding: 24px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8f9fa;
        border-radius: 6px 16px 0 0;
    }

    .hrb-modal-header h2 {
        margin: 0;
        color: #1f2937;
        font-weight: 600;
        font-size: 1.5rem;
    }

    .hrb-modal-close {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        cursor: pointer;
        color: #6b7280;
        background: white;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }

    .hrb-modal-close:hover {
        background: #f3f4f6;
        color: #374151;
        transform: scale(1.05);
    }

    .hrb-modal-body {
        padding: 24px;
    }

    .hrb-modal-body .form-table th {
        padding: 16px 0;
        font-weight: 600;
        color: #374151;
    }

    .hrb-modal-body .form-table td {
        padding: 16px 0;
    }

    .hrb-modal-body input,
    .hrb-modal-body textarea,
    .hrb-modal-body select {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 8px 12px;
        transition: border-color 0.2s ease;
    }

    .hrb-modal-body input:focus,
    .hrb-modal-body textarea:focus,
    .hrb-modal-body select:focus {
        border-color: #dc2626;
        outline: none;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    }

    .hrb-modal-footer {
        padding: 24px;
        border-top: 1px solid #e5e7eb;
        text-align: right;
        background: #f8f9fa;
        border-radius: 0 0 16px 16px;
    }

    .hrb-modal-footer .button {
        margin-left: 12px;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
    }

    .hrb-modal-footer .button-primary {
        background: #dc2626;
        border-color: #dc2626;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .hrb-admin-page {
            padding: 16px;
            margin: -10px;
        }

        .hrb-page-header {
            flex-direction: column;
            gap: 16px;
            padding: 24px;
        }

        .hrb-page-title h1 {
            font-size: 2rem;
        }

        .hrb-stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .hrb-stat-card {
            padding: 20px;
        }

        .hrb-stat-number {
            font-size: 2rem;
        }

        .hrb-filter-form {
            flex-direction: column;
            align-items: stretch;
        }

        .hrb-filter-actions {
            justify-content: center;
            margin-top: 16px;
        }

        .hrb-modal-content {
            width: 95%;
            margin: 20px;
        }
    }

    /* Print Styles */
    @media print {
        .hrb-admin-page {
            background: white;
            margin: 0;
            padding: 0;
        }

        .hrb-page-header {
            background: white !important;
            color: black !important;
            box-shadow: none;
        }

        .hrb-page-actions,
        .hrb-filters,
        .column-actions,
        .check-column {
            display: none;
        }
    }
</style>

<script>
    function viewPaymentDetails(paymentId) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hrb_get_payment_details',
                payment_id: paymentId,
                nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    document.getElementById('payment-modal-body').innerHTML = response.data.html;
                    document.getElementById('payment-modal').style.display = 'flex';
                }
            }
        });
    }

    function closePaymentModal() {
        document.getElementById('payment-modal').style.display = 'none';
    }

    function processRefund(paymentId) {
        // Get payment details for refund modal
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hrb_get_payment_refund_info',
                payment_id: paymentId,
                nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    document.getElementById('refund-payment-id').value = paymentId;
                    document.getElementById('refund-amount').max = data.available_refund;
                    document.getElementById('refund-amount').value = data.available_refund;
                    document.getElementById('refund-available').textContent =
                        '<?php _e('Available for refund:', 'hourly-room-booking'); ?> <?php echo $currency_symbol; ?>' + data.available_refund;
                    document.getElementById('refund-modal').style.display = 'flex';
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to get refund information'));
                }
            },
            error: function(xhr, status, error) {
                /* removed debug console.error for AJAX Error */
                alert('Failed to load refund information. Please try again.');
            }
        });
    }

    function closeRefundModal() {
        document.getElementById('refund-modal').style.display = 'none';
        document.getElementById('refund-form').reset();
    }

    function markPaymentCompleted(paymentId) {
        // Use custom alert dialog with warning type
        window.hrbShowAlertDialog(
            <?php echo json_encode(__('Mark this payment as completed? This should only be done after receiving the cash payment.', 'hourly-room-booking')); ?>,
            {
                warningMessage: <?php echo json_encode(__('This action should only be done after receiving the actual cash payment.', 'hourly-room-booking')); ?>,
                title: <?php echo json_encode(__('Mark Payment as Completed', 'hourly-room-booking')); ?>,
                confirmText: <?php echo json_encode(__('Mark Completed', 'hourly-room-booking')); ?>,
                cancelText: <?php echo json_encode(__('Cancel', 'hourly-room-booking')); ?>,
                type: 'warning'
            },
            function() {
                // User confirmed - proceed with marking as completed
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hrb_mark_payment_completed',
                        payment_id: paymentId,
                        nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php _e('Failed to mark payment as completed', 'hourly-room-booking'); ?>');
                        }
                    }
                });
            }
        );
    }

    function cancelPayment(paymentId) {
        // Use custom alert dialog with danger type
        window.hrbShowAlertDialog(
            <?php echo json_encode(__('Are you sure you want to cancel this payment?', 'hourly-room-booking')); ?>,
            {
                warningMessage: <?php echo json_encode(__('This action cannot be undone.', 'hourly-room-booking')); ?>,
                title: <?php echo json_encode(__('Cancel Payment', 'hourly-room-booking')); ?>,
                confirmText: <?php echo json_encode(__('Cancel Payment', 'hourly-room-booking')); ?>,
                cancelText: <?php echo json_encode(__('Keep Payment', 'hourly-room-booking')); ?>,
                type: 'danger'
            },
            function() {
                // User confirmed - proceed with cancellation
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hrb_cancel_payment',
                        payment_id: paymentId,
                        nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php _e('Failed to cancel payment', 'hourly-room-booking'); ?>');
                        }
                    }
                });
            }
        );
    }

    function exportPayments() {
        const params = new URLSearchParams(window.location.search);
        params.set('action', 'export_payments');
        params.set('nonce', '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>');
        window.location.href = ajaxurl + '?' + params.toString();
    }

    // Handle refund form submission
    document.getElementById('refund-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    closeRefundModal();
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('Failed to process refund', 'hourly-room-booking'); ?>');
                }
            },
            error: function(xhr, status, error) {
                /* removed debug console.error for Refund AJAX Error */
                alert('<?php _e('Failed to process refund. Please try again.', 'hourly-room-booking'); ?>');
            }
        });
    });

    // Close modals when clicking outside
    document.getElementById('payment-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePaymentModal();
        }
    });

    document.getElementById('refund-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRefundModal();
        }
    });

    // Select all functionality
    document.getElementById('cb-select-all').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="payment[]"]');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
</script>