<?php

/**
 * Admin Old Bookings View
 *
 * @package HourlyRoomBooking
 * @subpackage Admin/Views
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct script access denied.');
}

// Get current action
$action = $_GET['action'] ?? 'list';
$booking_id = intval($_GET['id'] ?? 0);
$booking_manager = HRB_Booking_Manager::getInstance();
$room_manager = HRB_Room_Manager::getInstance();
$customer_manager = HRB_Customer_Manager::getInstance();
$admin = HRB_Admin::getInstance();

// Handle view action early (edit is not allowed for old bookings)
if ($action === 'view' && $booking_id) {
    $booking = $booking_manager->get_booking($booking_id);
    if (!$booking) {
        echo '<div class="notice notice-error"><p>' . __('Booking not found.', 'hourly-room-booking') . '</p></div>';
        $action = 'list';
    }
} elseif ($action === 'edit') {
    // Redirect to view if someone tries to edit an old booking
    wp_redirect(admin_url('admin.php?page=hrb-old-bookings&action=view&id=' . $booking_id));
    exit;
}

// Handle form submissions
if ($_POST && check_admin_referer('hrb_admin_action', 'hrb_nonce')) {
    $post_action = sanitize_text_field($_POST['action'] ?? '');
    $post_booking_id = intval($_POST['id'] ?? 0);

    switch ($post_action) {
        case 'confirm':
            if ($post_booking_id) {
                $result = $booking_manager->update_booking_status($post_booking_id, 'confirmed');
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Booking confirmed successfully.', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to confirm booking.', 'hourly-room-booking') . '</p></div>';
                }
            }
            break;

        case 'cancel':
            if ($post_booking_id) {
                $reason = sanitize_text_field($_POST['cancellation_reason'] ?? '');
                $result = $booking_manager->cancel_booking($post_booking_id, $reason);
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Booking cancelled successfully.', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to cancel booking.', 'hourly-room-booking') . '</p></div>';
                }
            }
            break;

        case 'delete_booking':
            if ($post_booking_id && current_user_can('hrb_manage_bookings')) {
                $result = $booking_manager->delete_booking($post_booking_id);
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Booking deleted successfully.', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to delete booking.', 'hourly-room-booking') . '</p></div>';
                }
            }
            break;

        // update_booking action removed - old bookings cannot be edited
    }

    // Reset action to 'list' after processing
    $action = 'list';
}

// Get bookings list with filters
$page = intval($_GET['paged'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$filters = [
    'status' => sanitize_text_field($_GET['status'] ?? ''),
    'room_id' => intval($_GET['room_id'] ?? 0),
    'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
    'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
    'search' => sanitize_text_field($_GET['s'] ?? ''),
    'orderby' => sanitize_text_field($_GET['orderby'] ?? ''),
    'order' => sanitize_text_field($_GET['order'] ?? ''),
    'old_only' => true, // Only show bookings older than 2 days
];

$bookings = $booking_manager->get_bookings_admin($filters, $per_page, $offset);
$total_bookings = $booking_manager->get_bookings_count_admin($filters);
$total_pages = ceil($total_bookings / $per_page);


$rooms = $room_manager->get_all_rooms(['status' => 'active']);

// Helper function to generate sortable column headers
function hrb_get_sortable_header($label, $orderby, $current_orderby, $current_order, $default_order = 'desc') {
    $url = add_query_arg([
        'orderby' => $orderby,
        'order' => ($current_orderby === $orderby && $current_order === 'asc') ? 'desc' : 'asc'
    ], remove_query_arg(['paged']));
    
    $class = 'sortable';
    if ($current_orderby === $orderby) {
        $class .= ' sorted ' . $current_order;
    } else {
        $class .= ' sortable-desc';
    }
    
    $arrow = '';
    if ($current_orderby === $orderby) {
        $arrow = $current_order === 'asc' ? ' ↑' : ' ↓';
    }
    
    return sprintf('<a href="%s" class="%s">%s%s</a>', 
        esc_url($url), 
        esc_attr($class), 
        esc_html($label),
        $arrow
    );
}
?>

<?php if ($action === 'view' && isset($booking)): ?>
    <!-- VIEW BOOKING -->
    <div class="wrap hrb-admin-booking-view">
        <div class="hrb-page-header">
            <h1 class="wp-heading-inline">
            <?php printf(__('Booking Details - #%s', 'hourly-room-booking'), esc_html($booking->booking_reference)); ?>
        </h1>
        <a href="<?php echo admin_url('admin.php?page=hrb-old-bookings'); ?>" class="page-title-action">
            <?php _e('Back to Old Bookings', 'hourly-room-booking'); ?>
        </a>
        </div>
        
        <hr class="wp-header-end">

        <?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Booking updated successfully.', 'hourly-room-booking'); ?></p>
            </div>
        <?php endif; ?>

        <div class="hrb-booking-details">
            <div class="hrb-details-grid">
                <div class="hrb-details-section">
                    <h3><?php _e('Booking Information', 'hourly-room-booking'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <th><?php _e('Booking Reference', 'hourly-room-booking'); ?></th>
                            <td>#<?php echo esc_html($booking->booking_reference); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Status', 'hourly-room-booking'); ?></th>
                            <td><?php echo $admin->get_status_badge($booking->status); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Room', 'hourly-room-booking'); ?></th>
                            <td><?php echo esc_html($booking->room_name ?? 'Unknown Room'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Date', 'hourly-room-booking'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date))); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Time', 'hourly-room-booking'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->start_time)) . ' - ' . date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->end_time))); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Duration', 'hourly-room-booking'); ?></th>
                            <td><?php echo esc_html($booking->total_hours); ?> <?php _e('hours', 'hourly-room-booking'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('People', 'hourly-room-booking'); ?></th>
                            <td><?php
                                $base_people = 1; // Assuming base booking is for 1 person
                                $extra_people = intval($booking->extra_people ?? 0);
                                $total_people = $base_people + $extra_people;
                                echo esc_html($total_people);
                                if ($extra_people > 0) {
                                    echo ' (' . esc_html($base_people) . ' + ' . esc_html($extra_people) . ' ' . __('extra', 'hourly-room-booking') . ')';
                                }
                                ?></td>
                        </tr>
                        <?php
                        // Check if there are any extras for this booking
                        $extras_manager = HRB_Extras::getInstance();
                        $booking_extras = $extras_manager->get_booking_extras($booking->id);
                        if (!empty($booking_extras)): ?>
                        <tr>
                            <th><?php _e('Extras', 'hourly-room-booking'); ?></th>
                            <td>
                                <?php
                                $extras_list = [];
                                foreach ($booking_extras as $extra) {
                                    $extras_list[] = $extra->name . ' (' . hrb_format_amount($extra->total_price) . ')';
                                }
                                echo implode(', ', $extras_list);
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="hrb-details-section">
                    <h3><?php _e('Customer Information', 'hourly-room-booking'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <th><?php _e('Name', 'hourly-room-booking'); ?></th>
                            <td><?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Email', 'hourly-room-booking'); ?></th>
                            <td><a href="mailto:<?php echo esc_attr($booking->email); ?>"><?php echo esc_html($booking->email); ?></a></td>
                        </tr>
                        <tr>
                            <th><?php _e('Phone', 'hourly-room-booking'); ?></th>
                            <td><?php echo esc_html($booking->phone ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Company', 'hourly-room-booking'); ?></th>
                            <td><?php echo esc_html($booking->company ?? 'N/A'); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="hrb-details-section">
                    <h3><?php _e('Payment Information', 'hourly-room-booking'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <th><?php _e('Amount', 'hourly-room-booking'); ?></th>
                            <td><?php echo hrb_format_amount($booking->total_amount); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Payment Status', 'hourly-room-booking'); ?></th>
                            <td>
                                <?php
                                $payment_status = $booking->actual_payment_status ?: $booking->payment_status;
                                $status_class = 'hrb-payment-' . esc_attr($payment_status);
                                ?>
                                <?php echo $admin->get_payment_status_badge($payment_status); ?>
                                <?php if ($booking->actual_payment_status && $booking->processed_at): ?>
                                    <br><small><?php printf(__('Processed: %s', 'hourly-room-booking'), date_i18n(get_option('hrb_date_format', 'd.m.Y') . ' ' . get_option('hrb_time_format', 'H:i'), strtotime($booking->processed_at))); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Payment Method', 'hourly-room-booking'); ?></th>
                            <td><?php echo esc_html(hrb_get_payment_method_label($booking->payment_method ?? 'N/A')); ?></td>
                        </tr>
                        <?php if ($booking->transaction_id): ?>
                            <tr>
                                <th><?php _e('Transaction ID', 'hourly-room-booking'); ?></th>
                                <td>
                                    <?php echo esc_html($booking->transaction_id); ?>
                                    <a href="<?php echo admin_url('admin.php?page=hrb-payments&s=' . urlencode($booking->transaction_id)); ?>" class="button button-small"><?php _e('View Payment', 'hourly-room-booking'); ?></a>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th><?php _e('Created', 'hourly-room-booking'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('hrb_date_format', 'd.m.Y') . ' ' . get_option('hrb_time_format', 'H:i'), strtotime($booking->created_at))); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="hrb-details-section">
                    <h3><?php _e('Pricing Breakdown', 'hourly-room-booking'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <th><?php _e('Base Price', 'hourly-room-booking'); ?></th>
                            <td><?php echo hrb_format_amount($booking->base_price); ?></td>
                        </tr>
                        <?php if ($booking->extra_people > 0): ?>
                        <tr>
                            <th><?php _e('Extra People', 'hourly-room-booking'); ?></th>
                            <td>
                                <?php echo $booking->extra_people . ' × ' . hrb_format_amount($booking->extra_people_price / $booking->extra_people) . ' = ' . hrb_format_amount($booking->extra_people_price); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($booking->extras_price > 0): ?>
                        <tr>
                            <th><?php _e('Extras', 'hourly-room-booking'); ?></th>
                            <td><?php echo hrb_format_amount($booking->extras_price); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        
                        <?php if ($booking->tax_amount > 0): ?>
                        <tr>
                            <th><?php _e('Tax', 'hourly-room-booking'); ?></th>
                            <td><?php echo hrb_format_amount($booking->tax_amount); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($booking->paypal_fee > 0): ?>
                        <tr>
                            <th><?php _e('PayPal Fee', 'hourly-room-booking'); ?></th>
                            <td><?php echo hrb_format_amount($booking->paypal_fee); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr style="border-top: 2px solid #ddd; font-weight: bold;">
                            <th><?php _e('Total Amount', 'hourly-room-booking'); ?></th>
                            <td><?php echo hrb_format_amount($booking->total_amount); ?></td>
                        </tr>
                    </table>
                </div>

                <?php if (!empty($booking->special_requests)): ?>
                    <div class="hrb-details-section">
                        <h3><?php _e('Special Requests', 'hourly-room-booking'); ?></h3>
                        <div class="hrb-special-requests">
                            <?php echo nl2br(esc_html($booking->special_requests)); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="hrb-booking-actions">
                <?php if ($booking->status === 'pending' && current_user_can('hrb_manage_bookings')): ?>
                    <form method="POST" style="display: inline;">
                        <?php wp_nonce_field('hrb_admin_action', 'hrb_nonce'); ?>
                        <input type="hidden" name="action" value="confirm">
                        <input type="hidden" name="id" value="<?php echo esc_attr($booking->id); ?>">
                        <button type="submit" class="button button-secondary"><?php _e('Confirm Booking', 'hourly-room-booking'); ?></button>
                    </form>
                <?php endif; ?>
                <?php if (current_user_can('hrb_manage_bookings')): ?>
                    <form method="POST" style="display: inline;" id="delete-booking-form-<?php echo $booking->id; ?>">
                        <?php wp_nonce_field('hrb_admin_action', 'hrb_nonce'); ?>
                        <input type="hidden" name="action" value="delete_booking">
                        <input type="hidden" name="id" value="<?php echo esc_attr($booking->id); ?>">
                        <button type="button" class="button button-small hrb-delete-btn" title="<?php _e('Delete', 'hourly-room-booking'); ?>" 
                                data-booking-id="<?php echo esc_attr($booking->id); ?>" 
                                data-booking-reference="<?php echo esc_attr($booking->booking_reference); ?>"
                                onclick="confirmDeleteBooking(this)">
                            <span class="dashicons dashicons-trash"></span> <?php _e('Delete', 'hourly-room-booking'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- LIST BOOKINGS -->
    <div class="wrap hrb-admin-bookings">
        <div class="hrb-page-header">
    <h1 class="wp-heading-inline">
        <?php _e('Old Bookings', 'hourly-room-booking'); ?>
        <a href="<?php echo admin_url('admin.php?page=hrb-bookings'); ?>" class="page-title-action">
            <?php _e('View Recent Bookings', 'hourly-room-booking'); ?>
        </a>
    </h1>
    
            <div class="hrb-page-actions">
                <!-- <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=add'); ?>" class="page-title-action">
                    <?php _e('Add New Booking', 'hourly-room-booking'); ?>
                </a> -->
            </div>
        </div>


        <hr class="wp-header-end">

        <!-- Filters -->
        <div class="hrb-filters-section">
            <form method="GET" action="<?php echo admin_url('admin.php'); ?>">
                <input type="hidden" name="page" value="hrb-old-bookings">

                <div class="hrb-filters-grid">
                    <div class="hrb-filter-item">
                        <label for="filter-status"><?php _e('Status', 'hourly-room-booking'); ?></label>
                        <select id="filter-status" name="status">
                            <option value=""><?php _e('All Statuses', 'hourly-room-booking'); ?></option>
                            <option value="pending" <?php selected($filters['status'], 'pending'); ?>><?php _e('Pending', 'hourly-room-booking'); ?></option>
                            <option value="confirmed" <?php selected($filters['status'], 'confirmed'); ?>><?php _e('Confirmed', 'hourly-room-booking'); ?></option>
                            <option value="completed" <?php selected($filters['status'], 'completed'); ?>><?php _e('Completed', 'hourly-room-booking'); ?></option>
                            <option value="cancelled" <?php selected($filters['status'], 'cancelled'); ?>><?php _e('Cancelled', 'hourly-room-booking'); ?></option>
                            <option value="no_show" <?php selected($filters['status'], 'no_show'); ?>><?php _e('No Show', 'hourly-room-booking'); ?></option>
                        </select>
                    </div>

                    <div class="hrb-filter-item">
                        <label for="filter-room"><?php _e('Room', 'hourly-room-booking'); ?></label>
                        <select id="filter-room" name="room_id">
                            <option value=""><?php _e('All Rooms', 'hourly-room-booking'); ?></option>
                            <?php foreach ($rooms as $room) { ?>
                                <option value="<?php echo esc_attr($room->id); ?>" <?php selected($filters['room_id'], $room->id); ?>>
                                    <?php echo esc_html($room->name); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="hrb-filter-item">
                        <label for="filter-date-from"><?php _e('Date From', 'hourly-room-booking'); ?></label>
                        <input type="date" id="filter-date-from" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>">
                    </div>

                    <div class="hrb-filter-item">
                        <label for="filter-date-to"><?php _e('Date To', 'hourly-room-booking'); ?></label>
                        <input type="date" id="filter-date-to" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>">
                    </div>

                    <div class="hrb-filter-item hrb-filter-search">
                        <label for="filter-search"><?php _e('Search', 'hourly-room-booking'); ?></label>
                        <input type="search" id="filter-search" name="s" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php _e('Search bookings...', 'hourly-room-booking'); ?>">
                    </div>

                    <div class="hrb-filter-actions">
                        <button type="submit" class="button"><?php _e('Filter', 'hourly-room-booking'); ?></button>
                        <a href="<?php echo admin_url('admin.php?page=hrb-old-bookings'); ?>" class="button"><?php _e('Clear', 'hourly-room-booking'); ?></a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bookings Table -->
        <div class="hrb-table-container">
            <?php if (!empty($bookings)): ?>
                <?php if ($filters['orderby']): ?>
                    <div class="hrb-sorting-info">
                        <small>
                            <?php 
                            $sort_labels = [
                                'datetime' => __('Date & Time', 'hourly-room-booking'),
                                'amount' => __('Amount', 'hourly-room-booking'),
                                'status' => __('Status', 'hourly-room-booking'),
                                'customer' => __('Customer', 'hourly-room-booking'),
                                'room' => __('Room', 'hourly-room-booking'),
                                'created' => __('Created Date', 'hourly-room-booking')
                            ];
                            $order_label = $filters['order'] === 'asc' ? __('Ascending', 'hourly-room-booking') : __('Descending', 'hourly-room-booking');
                            printf(__('Sorted by %s (%s)', 'hourly-room-booking'), 
                                $sort_labels[$filters['orderby']] ?? $filters['orderby'], 
                                $order_label
                            );
                            ?>
                        </small>
                    </div>
                <?php endif; ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th scope="col" class="column-booking-id">
                                <?php _e('Booking ID', 'hourly-room-booking'); ?>
                            </th>
                            <th scope="col" class="column-customer">
                                <?php echo hrb_get_sortable_header(__('Customer', 'hourly-room-booking'), 'customer', $filters['orderby'], $filters['order']); ?>
                            </th>
                            <th scope="col" class="column-room">
                                <?php echo hrb_get_sortable_header(__('Room', 'hourly-room-booking'), 'room', $filters['orderby'], $filters['order']); ?>
                            </th>
                            <th scope="col" class="column-datetime">
                                <?php echo hrb_get_sortable_header(__('Date & Time', 'hourly-room-booking'), 'datetime', $filters['orderby'], $filters['order']); ?>
                            </th>
                            <th scope="col" class="column-duration">
                                <?php _e('Duration', 'hourly-room-booking'); ?>
                            </th>
                            <th scope="col" class="column-status">
                                <?php echo hrb_get_sortable_header(__('Status', 'hourly-room-booking'), 'status', $filters['orderby'], $filters['order']); ?>
                            </th>
                            <th scope="col" class="column-payment">
                                <?php _e('Payment', 'hourly-room-booking'); ?>
                            </th>
                            <th scope="col" class="column-amount">
                                <?php echo hrb_get_sortable_header(__('Amount', 'hourly-room-booking'), 'amount', $filters['orderby'], $filters['order']); ?>
                            </th>
                            <th scope="col" class="column-actions">
                                <?php _e('Actions', 'hourly-room-booking'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td class="column-booking-id">
                                    <strong><a href="<?php echo admin_url('admin.php?page=hrb-old-bookings&action=view&id=' . $booking['id']); ?>">
                                            #<?php echo esc_html($booking['booking_reference']); ?>
                                        </a></strong>
                                </td>
                                <td class="column-customer">
                                    <div class="hrb-customer-info">
                                        <strong><?php echo esc_html($booking['customer_name']); ?></strong><br>
                                        <small><?php echo esc_html($booking['customer_email']); ?></small>
                                        <?php if ($booking['customer_phone']): ?>
                                            <br><small><?php echo esc_html($booking['customer_phone']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="column-room">
                                    <strong><?php echo esc_html($booking['room_name']); ?></strong>
                                </td>
                                <td class="column-datetime">
                                    <div class="hrb-datetime">
                                        <strong><?php echo date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking['booking_date'])); ?></strong><br>
                                        <small><?php echo date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking['start_time'])) . ' - ' . date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking['end_time'])); ?></small>
                                    </div>
                                </td>
                                <td class="column-duration">
                                    <?php
                                    $start = new DateTime($booking['start_time']);
                                    $end = new DateTime($booking['end_time']);
                                    $interval = $start->diff($end);
                                    echo $interval->format('%h') . 'h';
                                    if ($interval->format('%i') > 0) {
                                        echo ' ' . $interval->format('%i') . 'm';
                                    }
                                    ?>
                                </td>
                                <td class="column-status">
                                    <?php echo $admin->get_status_badge($booking['status']); ?>
                                </td>
                                <td class="column-payment">
                                    <div class="hrb-payment-info">
                                        <?php $actual_payment_status = $booking['actual_payment_status'] ?: $booking['payment_status']; ?>
                                        <span class="hrb-payment-status hrb-payment-<?php echo esc_attr($actual_payment_status); ?>">
                                            <?php
                                            echo esc_html(hrb_get_payment_status_label($actual_payment_status));
                                            ?>
                                        </span>
                                        <br><small><?php echo esc_html(hrb_get_payment_method_label($booking['payment_method'])); ?></small>
                                    </div>
                                </td>
                                <td class="column-amount">
                                    <strong><?php echo hrb_format_amount($booking['total_amount']); ?></strong>
                                    <?php if ($booking['extra_people'] > 0): ?>
                                        <br><small><?php printf(__('%d extra people', 'hourly-room-booking'), $booking['extra_people']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-actions">
                                    <div class="hrb-actions">
                                        <a href="<?php echo admin_url('admin.php?page=hrb-old-bookings&action=view&id=' . $booking['id']); ?>"
                                            class="button button-small" title="<?php _e('View Details', 'hourly-room-booking'); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </a>

                                        <?php if ($booking['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline-block;">
                                                <?php wp_nonce_field('hrb_admin_action', 'hrb_nonce'); ?>
                                                <input type="hidden" name="action" value="confirm">
                                                <input type="hidden" name="id" value="<?php echo esc_attr($booking['id']); ?>">
                                                <button type="submit" class="button button-primary button-small" title="<?php _e('Confirm', 'hourly-room-booking'); ?>">
                                                    <span class="dashicons dashicons-yes"></span>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php // Cancel booking button removed - cancellations should only be handled via phone or email ?>

                                        <?php if (current_user_can('hrb_manage_bookings')): ?>
                                        <form method="POST" style="display: inline;" id="delete-booking-form-<?php echo $booking['id']; ?>">
                                            <?php wp_nonce_field('hrb_admin_action', 'hrb_nonce'); ?>
                                            <input type="hidden" name="action" value="delete_booking">
                                            <input type="hidden" name="id" value="<?php echo esc_attr($booking['id']); ?>">
                                            <button type="button" class="button button-small hrb-delete-btn" title="<?php _e('Delete', 'hourly-room-booking'); ?>" 
                                                    data-booking-id="<?php echo esc_attr($booking['id']); ?>" 
                                                    data-booking-reference="<?php echo esc_attr($booking['booking_reference']); ?>"
                                                    onclick="confirmDeleteBooking(this)">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="hrb-pagination">
                        <?php
                        $page_links = paginate_links([
                            'base' => admin_url('admin.php?page=hrb-old-bookings&%_%'),
                            'format' => '&paged=%#%',
                            'current' => $page,
                            'total' => $total_pages,
                            'show_all' => false,
                            'end_size' => 1,
                            'mid_size' => 2,
                            'prev_next' => true,
                            'prev_text' => __('&laquo; Previous', 'hourly-room-booking'),
                            'next_text' => __('Next &raquo;', 'hourly-room-booking'),
                            'type' => 'plain',
                            'add_args' => array_filter($filters)
                        ]);

                        if ($page_links) {
                            echo '<div class="tablenav-pages">' . $page_links . '</div>';
                        }
                        ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="hrb-no-data">
                    <div class="hrb-no-data-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <h3><?php _e('No bookings found', 'hourly-room-booking'); ?></h3>
                    <p><?php _e('No bookings match your current filters. Try adjusting your search criteria.', 'hourly-room-booking'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=add'); ?>" class="button button-primary">
                        <?php _e('Add New Booking', 'hourly-room-booking'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Cancellation Modal -->
<div id="hrb-cancel-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h3><?php _e('Cancel Booking', 'hourly-room-booking'); ?></h3>
            <span class="hrb-modal-close">&times;</span>
        </div>
        <form method="POST" id="hrb-cancel-form">
            <?php wp_nonce_field('hrb_admin_action', 'hrb_nonce'); ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="id" id="hrb-cancel-booking-id" value="">

            <div class="hrb-modal-body">
                <div class="hrb-form-row">
                    <label for="cancellation-reason"><?php _e('Cancellation Reason', 'hourly-room-booking'); ?></label>
                    <textarea id="cancellation-reason" name="cancellation_reason" rows="4"
                        placeholder="<?php _e('Enter reason for cancellation (optional)', 'hourly-room-booking'); ?>"></textarea>
                </div>

                <p class="description">
                    <?php _e('This action cannot be undone. The customer will be notified of the cancellation.', 'hourly-room-booking'); ?>
                </p>
            </div>

            <div class="hrb-modal-footer">
                <button type="button" class="button button-secondary" data-dismiss="modal">
                    <?php _e('Cancel', 'hourly-room-booking'); ?>
                </button>
                <button type="submit" class="button button-primary">
                    <?php _e('Cancel Booking', 'hourly-room-booking'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .hrb-page-header {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        /* padding: 32px; */
        border-radius: 16px;
        /* margin-bottom: 32px; */
        box-shadow: 0 8px 32px rgba(99, 102, 241, 0.15);
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Modern Bookings Page Styling with Purple/Blue Gradient Theme */
    .wrap.hrb-admin-bookings,
    .wrap.hrb-admin-booking-view,
    .wrap.hrb-admin-booking-edit {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        min-height: 100vh;
        padding: 24px;
    }

    /* Modern Page Header */
    .wrap.hrb-admin-bookings .wp-heading-inline,
    .wrap.hrb-admin-booking-view .wp-heading-inline,
    .wrap.hrb-admin-booking-edit .wp-heading-inline {
        background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
        color: white;
        padding: 32px;
        border-radius: 16px;
        /* margin-bottom: 32px; */
        box-shadow: 0 8px 32px rgba(139, 92, 246, 0.15);
        position: relative;
        overflow: hidden;
        display: block;
        font-size: 2.2em;
        font-weight: 700;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        letter-spacing: -0.5px;
    }

    /* .wrap.hrb-admin-bookings .wp-heading-inline::before,
    .wrap.hrb-admin-booking-view .wp-heading-inline::before,
    .wrap.hrb-admin-booking-edit .wp-heading-inline::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="%23ffffff" stroke-width="0.5" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>') repeat;
        pointer-events: none;
    } */

    .wrap.hrb-admin-bookings .page-title-action,
    .wrap.hrb-admin-booking-view .page-title-action,
    .wrap.hrb-admin-booking-edit .page-title-action {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        margin-right: 20px;
    }

    .wrap.hrb-admin-bookings .page-title-action:hover,
    .wrap.hrb-admin-booking-view .page-title-action:hover,
    .wrap.hrb-admin-booking-edit .page-title-action:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        color: white;
    }

    /* Enhanced Filters Section */
    .hrb-filters-section {
        background: white;
        border-radius: 16px;
        padding: 24px;
        margin: 24px 0;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(139, 92, 246, 0.1);
    }

    .hrb-filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 20px;
        align-items: end;
    }

    .hrb-filter-item label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #374151;
        font-size: 14px;
    }

    .hrb-filter-item input,
    .hrb-filter-item select {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        background: white;
        color: #374151;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .hrb-filter-item input:focus,
    .hrb-filter-item select:focus {
        border-color: #8b5cf6;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        outline: none;
    }

    .hrb-filter-search {
        grid-column: span 1;
    }

    .hrb-filter-actions {
        display: flex;
        gap: 12px;
    }

    .hrb-filter-actions .button {
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: 2px solid #8b5cf6;
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
    }

    .hrb-filter-actions .button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    }

    /* Enhanced Table Section */
    .hrb-table-section {
        margin: 24px 0;
    }

    .hrb-bulk-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 0;
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 16px;
    }

    .hrb-results-info {
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }

    .hrb-bookings-table {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(139, 92, 246, 0.1);
    }

    .hrb-bookings-table th {
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
        padding: 16px 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 12px;
        border: none;
    }

    .hrb-bookings-table td {
        padding: 16px 12px;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }

    .hrb-bookings-table tr:hover {
        background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
    }

    .column-booking-id {
        width: 130px;
    }

    .column-customer {
        width: 200px;
    }

    .column-room {
        width: 150px;
    }

    .column-datetime {
        width: 140px;
    }

    .column-duration {
        width: 90px;
    }

    .column-status {
        width: 110px;
    }

    .column-payment {
        width: 130px;
    }

    .column-amount {
        width: 110px;
    }

    .column-actions {
        width: 140px;
    }

    .column-booking-id a {
        color: #8b5cf6;
        font-weight: 700;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .column-booking-id a:hover {
        color: #6366f1;
        text-decoration: underline;
    }

    .hrb-customer-info strong {
        color: #1f2937;
        font-weight: 600;
    }

    .hrb-customer-info small {
        color: #6b7280;
        font-size: 12px;
    }

    .hrb-datetime strong {
        color: #1f2937;
        font-weight: 600;
    }

    .hrb-datetime small {
        color: #6b7280;
        font-size: 12px;
    }

    /* Enhanced Status Badges */
    .hrb-status-pending {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
    }

    .hrb-status-confirmed {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
    }

    .hrb-status-completed {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(107, 114, 128, 0.3);
    }

    .hrb-status-cancelled {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
    }

    .hrb-status-no_show {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
    }

    /* Enhanced Payment Status */
    .hrb-payment-pending {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
    }

    .hrb-payment-completed {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 1px 3px rgba(16, 185, 129, 0.3);
    }

    .hrb-payment-failed {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 1px 3px rgba(239, 68, 68, 0.3);
    }

    .hrb-payment-refunded {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 1px 3px rgba(107, 114, 128, 0.3);
    }

    .hrb-payment-cancelled {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 1px 3px rgba(107, 114, 128, 0.3);
    }

    .hrb-payment-partially_refunded {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
    }

    .hrb-payment-completed {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 1px 3px rgba(16, 185, 129, 0.3);
    }

    .hrb-payment-status {
        display: inline-block;
        margin-bottom: 3px;
    }

    .hrb-payment-info small {
        color: #6b7280;
        font-size: 10px;
        font-weight: 500;
    }

    /* Payment Status Badge Styles */
    .hrb-payment-status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 3px;
    }

    .hrb-payment-status-pending {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .hrb-payment-status-completed {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .hrb-payment-status-failed {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .hrb-payment-status-refunded {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
    }

    .hrb-payment-status-partially_refunded {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    /* Enhanced Actions */
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

    .hrb-actions .button-primary {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .hrb-actions .button-secondary {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
    }

    /* Enhanced No Data State */
    .hrb-no-data {
        text-align: center;
        padding: 80px 20px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(139, 92, 246, 0.1);
    }

    .hrb-no-data-icon {
        font-size: 64px;
        color: #8b5cf6;
        margin-bottom: 24px;
        opacity: 0.6;
    }

    .hrb-no-data h3 {
        margin: 0 0 12px 0;
        color: #1f2937;
        font-size: 1.5em;
        font-weight: 700;
    }

    .hrb-no-data p {
        color: #6b7280;
        margin-bottom: 24px;
        font-size: 16px;
        line-height: 1.6;
    }

    .hrb-no-data .button-primary {
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
        text-decoration: none;
    }

    .hrb-no-data .button-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
    }

    /* Enhanced Pagination */
    .hrb-pagination {
        text-align: center;
        margin: 32px 0;
    }

    .hrb-pagination .tablenav-pages {
        background: white;
        padding: 16px 24px;
        border-radius: 16px;
        display: inline-block;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(139, 92, 246, 0.1);
    }

    .hrb-pagination a,
    .hrb-pagination .current {
        padding: 8px 12px;
        margin: 0 4px;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .hrb-pagination a {
        color: #8b5cf6;
        background: #f8fafc;
    }

    .hrb-pagination a:hover {
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
        transform: translateY(-1px);
    }

    .hrb-pagination .current {
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
        box-shadow: 0 2px 4px rgba(139, 92, 246, 0.3);
    }

    /* Enhanced Booking Details View */
    .hrb-booking-details {
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border: none;
        border-radius: 20px;
        padding: 0;
        margin: 32px 0;
        box-shadow: 0 8px 30px rgba(139, 92, 246, 0.15);
        overflow: hidden;
        border: 1px solid rgba(139, 92, 246, 0.1);
    }

    .hrb-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 0;
        margin-bottom: 0;
    }

    .hrb-details-section {
        background: #fff;
        padding: 32px;
        border-right: 1px solid #e5e7eb;
        position: relative;
    }

    .hrb-details-section:last-child {
        border-right: none;
    }

    .hrb-details-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, #8b5cf6, #6366f1);
    }

    .hrb-details-section h3 {
        margin: 0 0 24px 0;
        padding: 0;
        border: none;
        color: #1f2937;
        font-size: 1.3em;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .hrb-details-section h3::before {
        content: '';
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: linear-gradient(45deg, #8b5cf6, #6366f1);
        box-shadow: 0 2px 4px rgba(139, 92, 246, 0.3);
    }

    .hrb-details-section table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .hrb-details-section tr {
        border-bottom: 1px solid #f3f4f6;
        transition: background-color 0.3s ease;
    }

    .hrb-details-section tr:hover {
        background-color: #faf5ff;
    }

    .hrb-details-section tr:last-child {
        border-bottom: none;
    }

    .hrb-details-section th {
        text-align: left;
        padding: 16px 20px 16px 0;
        font-weight: 600;
        color: #374151;
        width: 45%;
        font-size: 14px;
    }

    .hrb-details-section td {
        padding: 16px 0;
        color: #6b7280;
        font-size: 14px;
        font-weight: 500;
    }

    .hrb-details-section td a {
        color: #8b5cf6;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
    }

    .hrb-details-section td a:hover {
        color: #6366f1;
        text-decoration: underline;
    }

    .hrb-special-requests {
        background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
        padding: 24px;
        border-radius: 12px;
        border: 1px solid #e9d5ff;
        margin-top: 24px;
        position: relative;
        font-style: italic;
        line-height: 1.6;
        color: #6b46c1;
    }

    .hrb-special-requests::before {
        content: '"';
        position: absolute;
        top: -8px;
        left: 20px;
        font-size: 56px;
        color: #8b5cf6;
        opacity: 0.3;
        font-family: serif;
    }

    .hrb-booking-actions {
        padding: 32px;
        background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
        border-top: 1px solid #e9d5ff;
        display: flex;
        gap: 16px;
        align-items: center;
    }

    .hrb-booking-actions .button {
        border-radius: 12px;
        font-weight: 600;
        padding: 12px 24px;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        text-decoration: none;
    }

    .hrb-booking-actions .button-primary {
        background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
        color: white;
    }

    .hrb-booking-actions .button-primary:hover {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(139, 92, 246, 0.3);
        color: white;
    }

    .hrb-booking-actions .button-secondary {
        background: #fff;
        color: #374151;
        border: 2px solid #e5e7eb;
    }

    .hrb-booking-actions .button-secondary:hover {
        background: #f9fafb;
        border-color: #8b5cf6;
        transform: translateY(-2px);
        color: #374151;
    }

    /* Enhanced Edit Form */
    .hrb-edit-booking-form {
        background: #fff;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 8px 30px rgba(139, 92, 246, 0.15);
        border: 1px solid rgba(139, 92, 246, 0.1);
    }

    .hrb-edit-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        margin: 32px 0;
    }

    .hrb-edit-section {
        background: #fff;
        border: none;
        border-right: 1px solid #e5e7eb;
        padding: 40px;
        position: relative;
    }

    .hrb-edit-section:last-child {
        border-right: none;
    }

    .hrb-edit-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, #8b5cf6, #6366f1);
    }

    .hrb-edit-section h3 {
        margin: 0 0 30px 0;
        padding: 0;
        border: none;
        color: #1f2937;
        font-size: 1.4em;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .hrb-edit-section h3::before {
        content: '';
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: linear-gradient(45deg, #8b5cf6, #6366f1);
        box-shadow: 0 2px 4px rgba(139, 92, 246, 0.3);
    }

    .hrb-edit-section .form-table {
        background: none;
    }

    .hrb-edit-section .form-table th {
        padding: 20px 20px 20px 0;
        font-weight: 600;
        color: #374151;
        font-size: 14px;
        width: 40%;
    }

    .hrb-edit-section .form-table td {
        padding: 20px 0;
    }

    .hrb-edit-section input,
    .hrb-edit-section select,
    .hrb-edit-section textarea {
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        padding: 14px 18px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: #fff;
        font-weight: 500;
    }

    .hrb-edit-section input:focus,
    .hrb-edit-section select:focus,
    .hrb-edit-section textarea:focus {
        border-color: #8b5cf6;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        outline: none;
    }

    .hrb-edit-section .description {
        margin-top: 10px;
        color: #6b7280;
        font-size: 12px;
        font-style: italic;
        line-height: 1.5;
    }

    .hrb-edit-section .submit {
        background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
        padding: 32px 40px;
        margin: 0 -40px -40px -40px;
        border-top: 1px solid #e9d5ff;
    }

    .hrb-edit-section .submit .button {
        border-radius: 12px;
        font-weight: 600;
        padding: 14px 28px;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        margin-right: 16px;
        text-decoration: none;
    }

    .hrb-edit-section .submit .button-primary {
        background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
        color: white;
    }

    .hrb-edit-section .submit .button-primary:hover {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(139, 92, 246, 0.3);
    }

    .hrb-edit-section .submit .button-secondary {
        background: #fff;
        color: #374151;
        border: 2px solid #e5e7eb;
    }

    .hrb-edit-section .submit .button-secondary:hover {
        background: #f9fafb;
        border-color: #8b5cf6;
        transform: translateY(-2px);
        color: #374151;
    }

    /* Enhanced Modal */
    .hrb-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(8px);
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
        border-radius: 20px;
        width: 90%;
        max-width: 500px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        transform: scale(0.9);
        animation: modalIn 0.3s ease forwards;
    }

    @keyframes modalIn {
        to {
            transform: scale(1);
        }
    }

    .hrb-modal-header {
        padding: 24px 32px;
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .hrb-modal-header h3 {
        margin: 0;
        font-weight: 600;
        font-size: 1.3em;
    }

    .hrb-modal-close {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 20px;
        color: white;
        border: none;
    }

    .hrb-modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    .hrb-modal-body {
        padding: 32px;
    }

    .hrb-modal-body .hrb-form-row {
        margin-bottom: 20px;
    }

    .hrb-modal-body label {
        display: block;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
        font-size: 14px;
    }

    .hrb-modal-body textarea {
        width: 100%;
        padding: 14px 18px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
        resize: vertical;
        min-height: 100px;
    }

    .hrb-modal-body textarea:focus {
        border-color: #8b5cf6;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        outline: none;
    }

    .hrb-modal-body .description {
        color: #6b7280;
        font-size: 13px;
        line-height: 1.5;
        margin-top: 16px;
    }

    .hrb-modal-footer {
        padding: 24px 32px;
        background: #f8fafc;
        display: flex;
        justify-content: flex-end;
        gap: 16px;
    }

    .hrb-modal-footer .button {
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        text-decoration: none;
    }

    .hrb-modal-footer .button-primary {
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: white;
        box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
    }

    .hrb-modal-footer .button-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
    }

    .hrb-modal-footer .button-secondary {
        background: #fff;
        color: #374151;
        border: 2px solid #e5e7eb;
    }

    .hrb-modal-footer .button-secondary:hover {
        background: #f9fafb;
        border-color: #8b5cf6;
        color: #374151;
    }

    /* Responsive Design */
    @media (max-width: 768px) {

        .wrap.hrb-admin-bookings,
        .wrap.hrb-admin-booking-view,
        .wrap.hrb-admin-booking-edit {
            padding: 16px;
            margin: -20px -16px -20px -16px;
        }

        .wrap.hrb-admin-bookings .wp-heading-inline,
        .wrap.hrb-admin-booking-view .wp-heading-inline,
        .wrap.hrb-admin-booking-edit .wp-heading-inline {
            font-size: 1.8em;
            padding: 24px;
            text-align: center;
        }

        .wrap.hrb-admin-bookings .page-title-action,
        .wrap.hrb-admin-booking-view .page-title-action,
        .wrap.hrb-admin-booking-edit .page-title-action {
            margin-left: 0;
            margin-top: 16px;
            display: block;
            text-align: center;
        }

        .hrb-filters-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .hrb-filter-actions {
            justify-content: center;
        }

        .hrb-bookings-table {
            font-size: 12px;
        }

        .hrb-bookings-table th,
        .hrb-bookings-table td {
            padding: 8px 4px;
        }

        .hrb-details-grid,
        .hrb-edit-grid {
            grid-template-columns: 1fr;
            gap: 0;
        }

        .hrb-details-section,
        .hrb-edit-section {
            border-right: none;
            border-bottom: 1px solid #e5e7eb;
            padding: 24px;
        }

        .hrb-details-section:last-child,
        .hrb-edit-section:last-child {
            border-bottom: none;
        }

        .hrb-booking-actions {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }

        .hrb-booking-actions .button {
            width: 100%;
            text-align: center;
        }

        .hrb-edit-section .submit .button {
            width: 100%;
            margin-bottom: 12px;
            margin-right: 0;
        }

        .hrb-modal-content {
            width: 95%;
            margin: 20px;
        }

        .hrb-modal-header,
        .hrb-modal-body,
        .hrb-modal-footer {
            padding: 20px;
        }
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Handle cancel booking modal
        $('.hrb-cancel-booking').on('click', function(e) {
            e.preventDefault();

            var url = $(this).attr('href');
            var bookingId = url.match(/id=(\d+)/)[1];

            $('#hrb-cancel-booking-id').val(bookingId);
            $('#hrb-cancel-modal').fadeIn();
        });

        // Modal close handlers
        $('.hrb-modal-close, [data-dismiss="modal"]').on('click', function() {
            $('#hrb-cancel-modal').fadeOut();
        });

        $(document).on('click', '.hrb-modal', function(e) {
            if (e.target === this) {
                $(this).fadeOut();
            }
        });
    });

    // Delete booking confirmation function
    window.confirmDeleteBooking = function(buttonElement) {
        var bookingId = buttonElement.getAttribute('data-booking-id');
        var bookingReference = buttonElement.getAttribute('data-booking-reference');

        // Use custom alert dialog with danger type if available
        if (typeof window.hrbShowAlertDialog === 'function') {
            window.hrbShowAlertDialog(
                <?php echo json_encode(__('Are you sure you want to delete this booking?', 'hourly-room-booking')); ?>,
                {
                    warningMessage: <?php echo json_encode(__('This action cannot be undone.', 'hourly-room-booking')); ?>,
                    title: <?php echo json_encode(__('Delete Booking', 'hourly-room-booking')); ?>,
                    details: [
                        {
                            label: <?php echo json_encode(__('Booking Reference:', 'hourly-room-booking')); ?>,
                            value: bookingReference,
                            class: 'original'
                        }
                    ],
                    confirmText: <?php echo json_encode(__('Delete', 'hourly-room-booking')); ?>,
                    cancelText: <?php echo json_encode(__('Cancel', 'hourly-room-booking')); ?>,
                    type: 'danger'
                },
                function() {
                    // User confirmed - submit the form
                    document.getElementById('delete-booking-form-' + bookingId).submit();
                }
            );
        } else {
            // Fallback to standard confirm if custom dialog is not available
            if (confirm(<?php echo json_encode(__('Are you sure you want to delete this booking? This action cannot be undone.', 'hourly-room-booking')); ?>)) {
                document.getElementById('delete-booking-form-' + bookingId).submit();
            }
        }
    };
</script>