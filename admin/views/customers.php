<?php

/**
 * Customers Management View
 * Displays the admin interface for managing customers
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Search and filtering
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

// Build query
$where_conditions = ['1=1'];
$query_params = [];

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)";
    $search_term = '%' . $wpdb->esc_like($search) . '%';
    $query_params[] = $search_term;
    $query_params[] = $search_term;
    $query_params[] = $search_term;
    $query_params[] = $search_term;
}

if ($status_filter === 'verified') {
    $where_conditions[] = "is_verified = 1";
} elseif ($status_filter === 'unverified') {
    $where_conditions[] = "is_verified = 0";
} elseif ($status_filter === 'linked') {
    $where_conditions[] = "wp_user_id IS NOT NULL AND wp_user_id > 0";
} elseif ($status_filter === 'guest') {
    $where_conditions[] = "(wp_user_id IS NULL OR wp_user_id = 0)";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_customers WHERE {$where_clause}";
if (!empty($query_params)) {
    $total_customers = $wpdb->get_var($wpdb->prepare($total_query, $query_params));
} else {
    $total_customers = $wpdb->get_var($total_query);
}

$total_pages = ceil($total_customers / $per_page);

// Get customers with booking statistics
$customers_query = "
    SELECT c.*,
           COUNT(b.id) as booking_count,
           SUM(CASE WHEN b.status = 'completed' THEN b.total_amount ELSE 0 END) as total_spent,
           MAX(b.created_at) as last_booking_date
    FROM {$wpdb->prefix}hrb_customers c
    LEFT JOIN {$wpdb->prefix}hrb_bookings b ON c.id = b.customer_id
    WHERE {$where_clause}
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT %d OFFSET %d
";

$final_params = array_merge($query_params, [$per_page, $offset]);
$customers = $wpdb->get_results($wpdb->prepare($customers_query, $final_params));

// Quick stats
$stats = [
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hrb_customers"),
    'verified' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hrb_customers WHERE is_verified = 1"),
    'linked_users' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hrb_customers WHERE wp_user_id IS NOT NULL AND wp_user_id > 0"),
    'guest_customers' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hrb_customers WHERE wp_user_id IS NULL OR wp_user_id = 0"),
    'this_month' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hrb_customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"),
    'avg_bookings' => $wpdb->get_var("SELECT AVG(booking_count) FROM (SELECT COUNT(b.id) as booking_count FROM {$wpdb->prefix}hrb_customers c LEFT JOIN {$wpdb->prefix}hrb_bookings b ON c.id = b.customer_id GROUP BY c.id) as subq")
];
?>

<div class="hrb-admin-page">
    <div class="hrb-page-header">
        <div class="hrb-page-title">
            <h1><?php _e('Manage Customers', 'hourly-room-booking'); ?></h1>
            <p class="description"><?php _e('View and manage customer accounts and their booking history.', 'hourly-room-booking'); ?></p>
        </div>
        <div class="hrb-page-actions">
            <!-- <button type="button" class="button button-primary" onclick="showAddCustomerModal()">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Add Customer', 'hourly-room-booking'); ?>
            </button> -->
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="hrb-stats-grid" style="margin-bottom: 20px;">
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
                <div class="hrb-stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="hrb-stat-label"><?php _e('Total Customers', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
                <div class="hrb-stat-number"><?php echo number_format($stats['linked_users']); ?></div>
                <div class="hrb-stat-label"><?php _e('Linked Users', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
                <div class="hrb-stat-number"><?php echo number_format($stats['guest_customers']); ?></div>
                <div class="hrb-stat-label"><?php _e('Guest Customers', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
                <div class="hrb-stat-number"><?php echo number_format($stats['this_month']); ?></div>
                <div class="hrb-stat-label"><?php _e('New This Month', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <!-- <div class="hrb-stat-card">
            <div class="hrb-stat-content">
                <div class="hrb-stat-number"><?php echo number_format($stats['avg_bookings'] ?: 0, 1); ?></div>
                <div class="hrb-stat-label"><?php _e('Avg Bookings', 'hourly-room-booking'); ?></div>
            </div>
        </div> -->
    </div>

    <!-- Search and Filters -->
    <div class="hrb-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="hrb-customers">

            <div class="hrb-search-box">
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?php selected($status_filter, 'all'); ?>><?php _e('All Customers', 'hourly-room-booking'); ?></option>
                    <option value="linked" <?php selected($status_filter, 'linked'); ?>><?php _e('Linked Users Only', 'hourly-room-booking'); ?></option>
                    <option value="guest" <?php selected($status_filter, 'guest'); ?>><?php _e('Guest Customers Only', 'hourly-room-booking'); ?></option>
                    <option value="verified" <?php selected($status_filter, 'verified'); ?>><?php _e('Verified Only', 'hourly-room-booking'); ?></option>
                    <option value="unverified" <?php selected($status_filter, 'unverified'); ?>><?php _e('Unverified Only', 'hourly-room-booking'); ?></option>
                </select>
                <input type="text" name="s" value="<?php echo esc_attr($search); ?>"
                    placeholder="<?php esc_attr_e('Search customers...', 'hourly-room-booking'); ?>">
                <button type="submit" class="button"><?php _e('Search', 'hourly-room-booking'); ?></button>
                <?php if (!empty($search) || $status_filter !== 'all'): ?>
                <a href="<?php echo admin_url('admin.php?page=hrb-customers'); ?>" class="button">
                    <?php _e('Clear Filters', 'hourly-room-booking'); ?>
                </a>
            <?php endif; ?>
            </div>



            
        </form>
    </div>

    <!-- Customers Table -->
    <div class="hrb-table-container">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col" class="column-customer"><?php _e('Customer', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-contact"><?php _e('Contact Info', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-bookings"><?php _e('Bookings', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-spent"><?php _e('Total Spent', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-status"><?php _e('Status', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-registered"><?php _e('Registered', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-actions"><?php _e('Actions', 'hourly-room-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" class="hrb-no-data">
                            <div class="hrb-empty-state">
                                <span class="dashicons dashicons-groups"></span>
                                <h3><?php _e('No customers found', 'hourly-room-booking'); ?></h3>
                                <p><?php _e('No customers match your search criteria.', 'hourly-room-booking'); ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr data-customer-id="<?php echo $customer->id; ?>">
                            <td class="column-customer">
                                <div class="customer-info">
                                    <div class="customer-avatar">
                                        <?php echo strtoupper(substr($customer->first_name, 0, 1) . substr($customer->last_name, 0, 1)); ?>
                                    </div>
                                    <div class="customer-details">
                                        <strong><?php echo esc_html($customer->first_name . ' ' . $customer->last_name); ?></strong>
                                        <div class="customer-id"><?php printf(__('Customer ID: %d', 'hourly-room-booking'), $customer->id); ?></div>
                                        <?php if (!empty($customer->wp_user_id)): ?>
                                            <?php
                                            $wp_user = get_user_by('ID', $customer->wp_user_id);
                                            if ($wp_user): ?>
                                                <div class="wp-user-link">
                                                    <span class="dashicons dashicons-admin-users"></span>
                                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $customer->wp_user_id); ?>" target="_blank">
                                                        <?php printf(__('WP User: %s', 'hourly-room-booking'), $wp_user->user_login); ?>
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <div class="wp-user-link wp-user-deleted">
                                                    <span class="dashicons dashicons-admin-users"></span>
                                                    <span style="color: #d63384;"><?php printf(__('WP User ID: %d (Deleted)', 'hourly-room-booking'), $customer->wp_user_id); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="wp-user-link wp-user-none">
                                                <span class="dashicons dashicons-admin-users"></span>
                                                <span style="color: #6c757d;"><?php _e('Guest Customer', 'hourly-room-booking'); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($customer->company)): ?>
                                            <div class="customer-company">
                                                <span class="dashicons dashicons-building"></span>
                                                <?php echo esc_html($customer->company); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="column-contact">
                                <div class="contact-info">
                                    <div class="customer-email">
                                        <span class="dashicons dashicons-email"></span>
                                        <a href="mailto:<?php echo esc_attr($customer->email); ?>"><?php echo esc_html($customer->email); ?></a>
                                    </div>
                                    <?php if (!empty($customer->phone)): ?>
                                        <div class="customer-phone">
                                            <span class="dashicons dashicons-phone"></span>
                                            <a href="tel:<?php echo esc_attr($customer->phone); ?>"><?php echo esc_html($customer->phone); ?></a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="column-bookings">
                                <div class="booking-stats">
                                    <strong><?php echo number_format($customer->booking_count); ?></strong>
                                    <?php if ($customer->last_booking_date): ?>
                                        <div class="last-booking">
                                            <?php printf(
                                                __('Last: %s', 'hourly-room-booking'),
                                                date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($customer->last_booking_date))
                                            ); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="column-spent">
                                <strong><?php echo hrb_format_amount($customer->total_spent ?: 0); ?></strong>
                            </td>
                            <td class="column-status">
                                <span class="hrb-status hrb-status-<?php echo $customer->is_verified ? 'verified' : 'unverified'; ?>">
                                    <?php echo $customer->is_verified ? __('Verified', 'hourly-room-booking') : __('Unverified', 'hourly-room-booking'); ?>
                                </span>
                            </td>
                            <td class="column-registered">
                                <?php echo date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($customer->created_at)); ?>
                            </td>
                            <td class="column-actions">
                                <div class="hrb-actions">
                                    <button type="button" class="button button-small" onclick="viewCustomer(<?php echo $customer->id; ?>)" title="<?php _e('View Customer', 'hourly-room-booking'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>

                                    <?php if (current_user_can('hrb_manage_customers')): ?>
                                    <button type="button" class="button button-small" onclick="editCustomer(<?php echo $customer->id; ?>)" title="<?php _e('Edit Customer', 'hourly-room-booking'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <?php endif; ?>

                                    <?php if (!$customer->is_verified): ?>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('hrb_admin_action', '_wpnonce'); ?>
                                            <input type="hidden" name="action" value="verify_customer">
                                            <input type="hidden" name="customer_id" value="<?php echo $customer->id; ?>">
                                            <button type="submit" class="button button-small hrb-verify-btn" title="<?php _e('Verify Customer', 'hourly-room-booking'); ?>">
                                                <span class="dashicons dashicons-yes"></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <button type="button" class="button button-small hrb-export-btn" onclick="exportCustomerData(<?php echo $customer->id; ?>)" title="<?php _e('Export Data', 'hourly-room-booking'); ?>">
                                        <span class="dashicons dashicons-download"></span>
                                    </button>

                                    <?php if (current_user_can('hrb_manage_customers')): ?>
                                    <button type="button" class="button button-small hrb-delete-btn" onclick="deleteCustomer(<?php echo $customer->id; ?>, '<?php echo esc_js($customer->first_name . ' ' . $customer->last_name); ?>')" title="<?php _e('Delete Customer', 'hourly-room-booking'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="hrb-pagination">
            <?php
            $pagination_args = [
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'current' => $current_page,
                'total' => $total_pages,
                'prev_text' => __('&laquo; Previous', 'hourly-room-booking'),
                'next_text' => __('Next &raquo;', 'hourly-room-booking'),
            ];
            echo paginate_links($pagination_args);
            ?>
        </div>
    <?php endif; ?>
</div>

<!-- Customer Modal -->
<div id="customer-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h2 id="customer-modal-title"><?php _e('Customer Details', 'hourly-room-booking'); ?></h2>
            <span class="hrb-modal-close" onclick="closeCustomerModal()">&times;</span>
        </div>
        <div class="hrb-modal-body" id="customer-modal-body">
            <!-- Content loaded via AJAX -->
        </div>
        <div class="hrb-modal-footer">
            <button type="button" class="button" onclick="closeCustomerModal()"><?php _e('Close', 'hourly-room-booking'); ?></button>
        </div>
    </div>
</div>

<!-- Delete confirmation form -->
<form id="delete-customer-form" method="post" style="display: none;">
    <?php wp_nonce_field('hrb_admin_action', '_wpnonce'); ?>
    <input type="hidden" name="action" value="delete_customer">
    <input type="hidden" name="customer_id" id="delete-customer-id" value="">
</form>

<style>
    /* Modern Professional Customers Management Styling */
    .hrb-admin-page {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 24px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .hrb-page-header {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        padding: 32px;
        border-radius: 16px;
        margin-bottom: 32px;
        box-shadow: 0 8px 32px rgba(16, 185, 129, 0.15);
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

    .hrb-page-actions .button-primary {
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

    .hrb-page-actions .button-primary:hover {
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
        background: white;
        border-radius: 16px;
        /* padding: 32px; */
        text-align: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
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
        background: linear-gradient(90deg, #10b981, #3b82f6);
    }

    .hrb-stat-card:nth-child(2)::before {
        background: linear-gradient(90deg, #3b82f6, #f59e0b);
    }

    .hrb-stat-card:nth-child(3)::before {
        background: linear-gradient(90deg, #f59e0b, #ef4444);
    }

    .hrb-stat-card:nth-child(4)::before {
        background: linear-gradient(90deg, #ef4444, #8b5cf6);
    }

    .hrb-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 40px rgba(0, 0, 0, 0.12);
    }

    .hrb-stat-number {
        font-size: 32px;
        font-weight: 800;
        color: #1f2937;
        line-height: 1;
        margin-bottom: 8px;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .hrb-stat-label {
        font-size: 0.95rem;
        color: #6b7280;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Modern Filters */
    .hrb-filters {
        background: white;
        padding: 24px;
        border-radius: 12px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        gap: 20px;
        align-items: center;
        flex-wrap: wrap;
    }

    .hrb-search-box {
        display: flex;
        gap: 8px;
    }

    .hrb-search-box input {
        min-width: 300px;
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 0.9rem;
        background: white;
        transition: border-color 0.2s ease;
    }

    .hrb-search-box input:focus {
        border-color: #10b981;
        outline: none;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .hrb-filters select {
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 0.9rem;
        background: white;
        transition: border-color 0.2s ease;
    }

    .hrb-filters select:focus {
        border-color: #10b981;
        outline: none;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .hrb-filters .button {
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 500;
    }

    /* Modern Table Container */
    .hrb-table-container {
        background: white;
        border-radius: 12px;
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

    .column-customer {
        width: 20%;
    }

    .column-contact {
        width: 20%;
    }

    .column-bookings {
        width: 15%;
    }

    .column-spent {
        width: 12%;
    }

    .column-status {
        width: 10%;
    }

    .column-registered {
        width: 12%;
    }

    .column-actions {
        width: 11%;
    }

    .customer-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .customer-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
    }

    .customer-details strong {
        color: #1f2937;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .customer-id {
        font-size: 0.8rem;
        color: #6b7280;
        margin-top: 2px;
        font-weight: 500;
    }

    .contact-info {
        font-size: 0.85rem;
    }

    .contact-info>div {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }

    .contact-info .dashicons {
        font-size: 16px;
        color: #6b7280;
    }

    .contact-info a {
        color: #374151;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .contact-info a:hover {
        color: #10b981;
    }

    .booking-stats {
        text-align: center;
    }

    .booking-stats strong {
        font-size: 1.5rem;
        color: #1f2937;
        font-weight: 700;
    }

    .last-booking {
        font-size: 0.8rem;
        color: #6b7280;
        margin-top: 4px;
        font-weight: 500;
    }

    .hrb-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: inline-block;
    }

    .hrb-status-verified {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #065f46;
    }

    .hrb-status-unverified {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
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
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
    }

    .hrb-actions .hrb-verify-btn {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .hrb-actions .hrb-export-btn {
        background: linear-gradient(135deg, #0ea5e9, #0284c7);
        color: white;
    }

    .hrb-actions .hrb-delete-btn {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    /* Modern Dropdown */
    .hrb-dropdown {
        position: relative;
    }

    .dropdown-toggle {
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        color: #6b7280;
    }

    .dropdown-toggle:hover {
        background: #e5e7eb;
        border-color: #9ca3af;
    }

    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        min-width: 180px;
        display: none;
        z-index: 1000;
        backdrop-filter: blur(8px);
    }

    .dropdown-menu.show {
        display: block;
        animation: fadeIn 0.15s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 12px 16px;
        border: none;
        background: none;
        text-align: left;
        font-size: 0.85rem;
        cursor: pointer;
        color: #374151;
        transition: all 0.2s ease;
    }

    .dropdown-item:hover {
        background: #f8f9fa;
        color: #1f2937;
    }

    .dropdown-item .dashicons {
        color: #6b7280;
    }

    .dropdown-item.delete-item:hover {
        background: #fef2f2;
        color: #dc2626;
    }

    .dropdown-item.delete-item:hover .dashicons {
        color: #dc2626;
    }

    .hrb-empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
    }

    .hrb-empty-state .dashicons {
        font-size: 64px;
        color: #d1d5db;
        margin-bottom: 16px;
    }

    .hrb-empty-state h3 {
        margin: 0 0 12px 0;
        font-size: 1.5rem;
        color: #374151;
        font-weight: 600;
    }

    .hrb-empty-state p {
        margin-bottom: 24px;
        font-size: 1rem;
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
        background: #10b981;
        border-color: #10b981;
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

    .hrb-modal-content {
        background: white;
        border-radius: 16px;
        width: 90%;
        max-width: 800px;
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
        border-radius: 16px 16px 0 0;
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

        .hrb-filters {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }

        .hrb-search-box {
            flex-direction: column;
        }

        .hrb-search-box input {
            min-width: auto;
        }

        .hrb-actions {
            flex-direction: column;
            gap: 4px;
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
        .hrb-dropdown {
            display: none;
        }
    }

    /* WordPress User Linkage Styles */
    .wp-user-link {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        margin: 4px 0;
        color: #666;
    }

    .wp-user-link .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }

    .wp-user-link a {
        color: #0073aa;
        text-decoration: none;
        font-weight: 500;
    }

    .wp-user-link a:hover {
        color: #005a87;
        text-decoration: underline;
    }

    .wp-user-none {
        color: #999;
        font-style: italic;
    }

    .wp-user-deleted {
        color: #d63384;
    }

    .customer-company {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        margin: 4px 0;
        color: #666;
    }

    .customer-company .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }

    .customer-id {
        font-size: 11px;
        color: #999;
        margin: 2px 0;
    }
</style>

<script>
    function viewCustomer(customerId) {
        loadCustomerModal(customerId, 'view');
    }

    function editCustomer(customerId) {
        loadCustomerModal(customerId, 'edit');
    }

    function loadCustomerModal(customerId, mode) {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hrb_get_customer_details',
                customer_id: customerId,
                mode: mode,
                nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    document.getElementById('customer-modal-title').textContent =
                        mode === 'edit' ? '<?php _e('Edit Customer', 'hourly-room-booking'); ?>' : '<?php _e('Customer Details', 'hourly-room-booking'); ?>';
                    document.getElementById('customer-modal-body').innerHTML = response.data.html;
                    document.getElementById('customer-modal').style.display = 'flex';
                }
            }
        });
    }

    function closeCustomerModal() {
        document.getElementById('customer-modal').style.display = 'none';
    }

    function deleteCustomer(customerId, customerName) {
        if (confirm('<?php _e('Are you sure you want to delete this customer?', 'hourly-room-booking'); ?>\n\n' + customerName + '\n\n<?php _e('This will also delete all their bookings and cannot be undone.', 'hourly-room-booking'); ?>')) {
            document.getElementById('delete-customer-id').value = customerId;
            document.getElementById('delete-customer-form').submit();
        }
    }

    function exportCustomerData(customerId) {
        window.location.href = ajaxurl + '?action=hrb_export_customer_data&customer_id=' + customerId + '&nonce=' + '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>';
    }
    function saveCustomer(customerId) {
        const form = document.getElementById('edit-customer-form');
        const formData = new FormData(form);
        formData.append('action', 'hrb_save_customer');
        formData.append('customer_id', customerId);
        formData.append('nonce', '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>');

        // Show loading state
        const saveButton = document.querySelector('.button-primary');
        const originalText = saveButton.textContent;
        saveButton.textContent = '<?php _e('Saving...', 'hourly-room-booking'); ?>';
        saveButton.disabled = true;

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert('<?php _e('Customer updated successfully!', 'hourly-room-booking'); ?>');
                    // Close modal
                    closeCustomerModal();
                    // Refresh the page to show updated data
                    location.reload();
                } else {
                    alert('<?php _e('Error:', 'hourly-room-booking'); ?> ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e('An error occurred while saving the customer.', 'hourly-room-booking'); ?>');
            },
            complete: function() {
                // Restore button state
                saveButton.textContent = originalText;
                saveButton.disabled = false;
            }
        });
    }

    function showAddCustomerModal() {
        document.getElementById('customer-modal-title').textContent = '<?php _e('Add New Customer', 'hourly-room-booking'); ?>';
        document.getElementById('customer-modal-body').innerHTML = '<div class="loading">Loading...</div>';
        document.getElementById('customer-modal').style.display = 'flex';

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hrb_get_customer_form',
                nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    document.getElementById('customer-modal-body').innerHTML = response.data.html;
                }
            }
        });
    }

    // Dropdown menus
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-toggle')) {
            e.preventDefault();
            const dropdown = e.target.nextElementSibling;
            const isVisible = dropdown.classList.contains('show');

            // Close all dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('show'));

            // Toggle current dropdown
            if (!isVisible) {
                dropdown.classList.add('show');
            }
        } else {
            // Close all dropdowns when clicking elsewhere
            document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('show'));
        }
    });

    // Close modal when clicking outside
    document.getElementById('customer-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCustomerModal();
        }
    });
</script>