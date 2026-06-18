<?php

/**
 * Admin Bookings View
 *
 * @package HourlyRoomBooking
 * @subpackage Admin/Views
 * @since 1.0.0
 */

global $wpdb;

if (!defined('ABSPATH')) {
    exit('Direct script access denied.');
}

// This admin view uses $_SESSION to preserve booking-form values across
// redirects. The plugin no longer starts a session globally (it kills
// page caching), so we lazily start one here. Admin pages are excluded
// from page caching by every major cache plugin, so this has no impact.
if (!session_id() && !headers_sent()) {
    session_start();
}

/**
 * Get invoice file URL by booking ID
 */
function get_invoice_download_url($booking_id)
{
    global $wpdb;

    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT pdf_file_path FROM {$wpdb->prefix}hrb_invoices WHERE booking_id = %d",
        $booking_id
    ));

    if ($invoice && !empty($invoice->pdf_file_path) && file_exists($invoice->pdf_file_path)) {
        $upload_dir = wp_upload_dir();
        return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $invoice->pdf_file_path);
    }

    return false;
}


// Get current action
// If we're processing add_booking POST, force action to 'add' to show the form with errors
$action = $_GET['action'] ?? 'list';
if (isset($_POST['action']) && $_POST['action'] === 'add_booking' && isset($_POST['_wpnonce'])) {
    $action = 'add'; // Always show add form when processing add_booking POST
}
$booking_id = intval($_GET['id'] ?? 0);
$booking_manager = HRB_Booking_Manager::getInstance();
$room_manager = HRB_Room_Manager::getInstance();
$customer_manager = HRB_Customer_Manager::getInstance();
$admin = HRB_Admin::getInstance();

// Handle view and edit actions early
if (in_array($action, ['view', 'edit']) && $booking_id) {
    $booking = $booking_manager->get_booking($booking_id);
    if (!$booking) {
        echo '<div class="notice notice-error"><p>' . __('Booking not found.', 'hourly-room-booking') . '</p></div>';
        $action = 'list';
    }
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

        case 'add_booking':
            $room_id = intval($_POST['room_id'] ?? 0);
            // Customer data is now validated by the validator above
            
            // Process extras
            $extras = [];
            if (isset($_POST['extras']) && is_array($_POST['extras'])) {
                foreach ($_POST['extras'] as $extra_id) {
                    $extras[] = intval($extra_id);
                }
            }
            
            // Use the same validator as frontend for consistency
            $validator = HRB_Input_Validator::getInstance();

            // Check if this is an anonymous booking
            $is_anonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] === '1';

            
            // Prepare data for validator (same format as frontend - include customer fields)
            $validator_data = [
                'room_id' => $room_id,
                'booking_date' => $_POST['booking_date'] ?? '',
                'start_time' => $_POST['start_time'] ?? '',
                'end_time' => $_POST['end_time'] ?? '',
                'extra_people' => $_POST['extra_people'] ?? 0,
                'extras' => $extras,
                'special_requests' => $_POST['special_requests'] ?? '',
                'payment_method' => $_POST['payment_method'] ?? 'onsite',
                'is_anonymous' => $is_anonymous,
                // Add customer fields like frontend
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'company' => $_POST['company'] ?? '',
            ];
            
            // Validate booking data using the same validator as frontend
            $booking_data = $validator->validate_booking_data($validator_data);
            if (is_wp_error($booking_data)) {
                // Store all error messages in transient and set action to 'add'
                $error_messages = $booking_data->get_error_messages();
                set_transient('hrb_admin_booking_errors', $error_messages, 30);
                // Also store form data for pre-filling
                $_SESSION['hrb_admin_booking_form_data'] = $validator_data;
                $action = 'add';
                break;
            }
            
            // Add admin_notes to booking_data (not validated by validator, but handled by booking manager)
            $booking_data['admin_notes'] = sanitize_textarea_field($_POST['admin_notes'] ?? '');

            // Handle customer creation based on anonymous status
            if ($is_anonymous) {
                // For anonymous bookings, use a single anonymous customer record
                $provided_name = sanitize_text_field($_POST['first_name'] ?? '');

                if (empty($provided_name)) {
                    // Store error message in transient and set action to 'add'
                    set_transient('hrb_admin_booking_error', __('Name is required for anonymous bookings.', 'hourly-room-booking'), 30);
                    $action = 'add';
                    break;
                }

                // Get or create the single anonymous customer
                $anonymous_customer = $customer_manager->get_customer_by_email('anonymous@example.com');
                if (!$anonymous_customer) {
                    // Create the single anonymous customer record
                    $anonymous_customer_data = array(
                        'first_name' => 'Anonymous',
                        'last_name' => 'User',
                        'email' => 'anonymous@example.com',
                        'phone' => '0000000000',
                        'company' => '',
                        'address' => '',
                        'city' => '',
                        'postal_code' => '',
                        'country' => 'DE'
                    );
                    $customer_id = $customer_manager->create_customer($anonymous_customer_data);
                    if (is_wp_error($customer_id)) {
                        // Store error message in transient and set action to 'add'
                        set_transient('hrb_admin_booking_error', $customer_id->get_error_message(), 30);
                        $action = 'add';
                        break;
                    }
                } else {
                    $customer_id = $anonymous_customer->id ?? null;
                }

                // Store the actual booking name in the booking record
                $booking_data['is_anonymous'] = true;
                $booking_data['first_name'] = sanitize_text_field($_POST['first_name'] ?? '');
                $booking_data['last_name'] = sanitize_text_field($_POST['last_name'] ?? '');
            } else {
            // Validate customer data using the same validator as frontend
            $customer_data = $validator->validate_customer_data($validator_data);
            if (is_wp_error($customer_data)) {
                // Store error message in transient and set action to 'add'
                set_transient('hrb_admin_booking_error', $customer_data->get_error_message(), 30);
                $action = 'add';
                break;
            }
            
            // Create customer first, or re-use existing if email already exists
            $customer_id = $customer_manager->create_customer($customer_data);
            if (is_wp_error($customer_id)) {
                if ($customer_id->get_error_code() === 'email_exists') {
                    // Customer already exists - use existing record and update details
                    $existing_customer = $customer_manager->get_customer_by_email($customer_data['email']);
                    if ($existing_customer) {
                        $customer_id = intval($existing_customer->id);
                        $update_result = $customer_manager->update_customer($customer_id, array(
                            'first_name' => $customer_data['first_name'],
                            'last_name'  => $customer_data['last_name'],
                            'phone'      => $customer_data['phone'],
                            'company'    => $customer_data['company'] ?? '',
                            'address'    => $customer_data['address'] ?? '',
                            'city'       => $customer_data['city'] ?? '',
                            'postal_code'=> $customer_data['postal_code'] ?? '',
                            'country'    => $customer_data['country'] ?? 'DE'
                        ));

                        if (is_wp_error($update_result)) {
                            set_transient('hrb_admin_booking_error', $update_result->get_error_message(), 30);
                            $action = 'add';
                            break;
                        }
                    } else {
                        // Could not find existing customer despite email_exists error
                        set_transient('hrb_admin_booking_error', $customer_id->get_error_message(), 30);
                        $action = 'add';
                        break;
                    }
                } else {
                    // Store other errors and stop
                    set_transient('hrb_admin_booking_error', $customer_id->get_error_message(), 30);
                    $action = 'add';
                    break;
                }
            }

                // Store customer name in booking record for regular bookings too
                $booking_data['first_name'] = sanitize_text_field($_POST['first_name'] ?? '');
                $booking_data['last_name'] = sanitize_text_field($_POST['last_name'] ?? '');
            }
            
            $booking_data['customer_id'] = $customer_id;
            
            // Set admin-specific booking data
            $booking_data['status'] = 'confirmed';  // Admin-created bookings are confirmed by default
            $booking_data['payment_status'] = sanitize_text_field($_POST['payment_status'] ?? 'pending');  // Use selected payment status
            $booking_data['created_by_admin'] = 1;  // Mark as created by admin
            
            // Validate booking data before creating (allow past dates and inactive rooms for admin)
            $validation = $booking_manager->validate_booking_data($booking_data, true, true);
            if (is_wp_error($validation)) {
                // Store error message and form data, then set action to 'add'
                set_transient('hrb_admin_booking_error', $validation->get_error_message(), 30);
                $form_data = $booking_data;
                if (isset($customer_data) && is_array($customer_data)) {
                    $form_data = array_merge($customer_data, $booking_data);
                }
                $_SESSION['hrb_admin_booking_form_data'] = $form_data;
                $action = 'add';
                break;
            }
            
            // Additional admin-specific validations
            if (empty($booking_data['booking_date']) || empty($booking_data['start_time']) || empty($booking_data['end_time'])) {
                // Store error message and form data, then set action to 'add'
                set_transient('hrb_admin_booking_error', __('Date, start time, and end time are required.', 'hourly-room-booking'), 30);
                $form_data = $booking_data;
                if (isset($customer_data) && is_array($customer_data)) {
                    $form_data = array_merge($customer_data, $booking_data);
                }
                $_SESSION['hrb_admin_booking_form_data'] = $form_data;
                $action = 'add';
                break;
            }
            
            // Validate time format and logic
            $start_time = strtotime($booking_data['start_time']);
            $end_time = strtotime($booking_data['end_time']);
            if ($start_time >= $end_time) {
                // Store error message and form data, then set action to 'add'
                set_transient('hrb_admin_booking_error', __('End time must be after start time.', 'hourly-room-booking'), 30);
                $form_data = $booking_data;
                if (isset($customer_data) && is_array($customer_data)) {
                    $form_data = array_merge($customer_data, $booking_data);
                }
                $_SESSION['hrb_admin_booking_form_data'] = $form_data;
                $action = 'add';
                break;
            }
            
            // Check for booking conflicts
            $has_conflict = HRB_Database::check_booking_conflict($booking_data['room_id'], $booking_data['booking_date'], $booking_data['start_time'], $booking_data['end_time']);
            if ($has_conflict) {
                // Store error message and form data, then set action to 'add'
                set_transient('hrb_admin_booking_error', __('Selected time slot conflicts with an existing booking. Please choose a different time.', 'hourly-room-booking'), 30);
                $form_data = $booking_data;
                if (isset($customer_data) && is_array($customer_data)) {
                    $form_data = array_merge($customer_data, $booking_data);
                }
                $_SESSION['hrb_admin_booking_form_data'] = $form_data;
                $action = 'add';
                break;
            }
            
            // Create booking
            $result = $booking_manager->create_booking($booking_data);
            if (is_wp_error($result)) {
                // Store error message and form data, then set action to 'add'
                set_transient('hrb_admin_booking_error', $result->get_error_message(), 30);
                $form_data = $booking_data;
                if (isset($customer_data) && is_array($customer_data)) {
                    $form_data = array_merge($customer_data, $booking_data);
                }
                $_SESSION['hrb_admin_booking_form_data'] = $form_data;
                $action = 'add';
                break;
            } else {
                $booking_id = $result;
                
                // Save extras if any are selected
                if (!empty($booking_data['extras'])) {
                    $extras_result = $booking_manager->save_booking_extras(
                        $booking_id,
                        $booking_data['extras'],
                        $booking_data['booking_date'],
                        $booking_data['start_time'],
                        $booking_data['end_time']
                    );
                    
                    if (is_wp_error($extras_result)) {
                        // Store error message and form data, then set action to 'add'
                        set_transient('hrb_admin_booking_error', $extras_result->get_error_message(), 30);
                        $form_data = $booking_data;
                        if (isset($customer_data) && is_array($customer_data)) {
                            $form_data = array_merge($customer_data, $booking_data);
                        }
                        $_SESSION['hrb_admin_booking_form_data'] = $form_data;
                        $action = 'add';
                        break;
                    }
                }
                
                echo '<div class="notice notice-success"><p>' . __('Booking created successfully.', 'hourly-room-booking') . '</p></div>';
                ?>
                <script>
                    setTimeout(function() {
                    window.location.href = '<?php echo admin_url('admin.php?page=hrb-bookings&action=view&id=' . $booking_id); ?>';
                        }, 300);
                    
                </script>
                <?php
            }
            break;

        case 'delete_booking':
            if ($post_booking_id) {
                $result = $booking_manager->delete_booking($post_booking_id);
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Booking deleted successfully.', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to delete booking.', 'hourly-room-booking') . '</p></div>';
                }
            }
            break;


        case 'update_booking':
            if ($post_booking_id) {
                // Store original booking data before changes (for additional payment calculation)
                $original_total_amount = floatval($booking->total_amount);
                $original_payment_status = $booking->payment_status;
                $original_payment_status_normalized = strtolower(trim($original_payment_status ?? 'pending'));
                
                // Store original hours and extra people BEFORE update (for modification tracking)
                $original_hours = floatval($booking->total_hours);
                $original_extra_people = intval($booking->extra_people);
                $original_base_price = floatval($booking->base_price);
                $original_extra_people_price = floatval($booking->extra_people_price);
                
                // Check if there's ANY completed payment (not just the last one)
                // This ensures we handle cases where there's a pending payment after a completed one
                $is_payment_completed = hrb_booking_has_completed_payment($post_booking_id);
                
                // Get original extras before update (for comparison - currently not used but kept for future use)
                // Note: The extras comparison is handled in save_booking_extras() function
                
                // Process extras
                $extras = [];
                if (isset($_POST['extras']) && is_array($_POST['extras'])) {
                    foreach ($_POST['extras'] as $extra_id) {
                        $extras[] = intval($extra_id);
                    }
                }
                
                // Get duration and times from POST
                $new_duration = floatval($_POST['duration'] ?? 0);
                $new_start_time = sanitize_text_field($_POST['start_time'] ?? $booking->start_time);
                $new_end_time = sanitize_text_field($_POST['end_time'] ?? $booking->end_time);
                
                // If duration changed, recalculate end_time based on new duration
                if ($new_duration > 0 && !empty($new_start_time)) {
                    // Calculate new end_time based on duration
                    $start_timestamp = strtotime($new_start_time);
                    $new_end_timestamp = $start_timestamp + ($new_duration * 3600);
                    $new_end_time = date('H:i:s', $new_end_timestamp);
                }
                
                $update_data = [
                    'room_id' => intval($_POST['room_id'] ?? 0),
                    'status' => sanitize_text_field($_POST['booking_status'] ?? ''),
                    'payment_status' => sanitize_text_field($_POST['payment_status'] ?? ''),
                    'booking_date' => sanitize_text_field($_POST['booking_date'] ?? ''),
                    'start_time' => $new_start_time,
                    'end_time' => $new_end_time,
                    'total_hours' => $new_duration,
                    'extra_people' => intval($_POST['extra_people'] ?? 0),
                    'extras' => $extras,
                    'payment_method' => sanitize_text_field($_POST['payment_method'] ?? ''),
                    'special_requests' => sanitize_textarea_field($_POST['special_requests'] ?? ''),
                    'admin_notes' => sanitize_textarea_field($_POST['admin_notes'] ?? ''),
                ];

                // Check if payment method changed to PayPal
                $old_payment_method = $booking->payment_method;
                $new_payment_method = sanitize_text_field($_POST['payment_method'] ?? '');
                $payment_method_changed = ($old_payment_method !== $new_payment_method);

                if ($payment_method_changed && $new_payment_method === 'paypal') {
                    // Send PayPal payment email
                    $admin = HRB_Admin::getInstance();
                    $admin->send_paypal_payment_email($post_booking_id);
                }

                // Handle customer data based on booking type
                if ($booking->is_anonymous) {
                    // For anonymous bookings, update the booking table's name fields
                    $update_data['first_name'] = sanitize_text_field($_POST['first_name'] ?? '');
                    $update_data['last_name'] = sanitize_text_field($_POST['last_name'] ?? '');
                } else {
                    // For regular bookings, update customer data
                    $customer_data = [
                        'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
                        'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
                        'email' => sanitize_email($_POST['email'] ?? ''),
                        'phone' => sanitize_text_field($_POST['phone'] ?? ''),
                        'company' => sanitize_text_field($_POST['company'] ?? ''),
                    ];
                    
                    // Update customer data
                    if (!empty($customer_data['email'])) {
                        $customer_manager = HRB_Customer_Manager::getInstance();
                        $customer_manager->update_customer($booking->customer_id, $customer_data);
                    }
                }

                // Remove extras from update_data since it's handled separately
                $extras_data = $update_data['extras'];
                unset($update_data['extras']);

                // Update booking with new data
                $result = $booking_manager->update_booking($post_booking_id, $update_data);
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                    break;
                }
                
                // Update extras if any are selected (pass is_admin_edit = true to track admin-added extras)
                if (!empty($extras_data)) {
                    $extras_result = $booking_manager->save_booking_extras(
                        $post_booking_id,
                        $extras_data,
                        $update_data['booking_date'],
                        $update_data['start_time'],
                        $update_data['end_time'],
                        true // is_admin_edit = true
                    );
                    
                    if (is_wp_error($extras_result)) {
                        echo '<div class="notice notice-error"><p>' . $extras_result->get_error_message() . '</p></div>';
                        break;
                    }
                    $current_extras = $extras_data;
                } else {
                    // Remove all extras if none are selected
                    global $wpdb;
                    $wpdb->delete(
                        $wpdb->prefix . 'hrb_booking_extras',
                        ['booking_id' => $post_booking_id],
                        ['%d']
                    );
                    $current_extras = [];
                }

                // Recalculate prices after extras are saved/removed
                $booking = $booking_manager->get_booking($post_booking_id);
                if ($booking) {
                    // Prepare data for price calculation
                    $pricing_data = [
                        'room_id' => $booking->room_id,
                        'booking_date' => $update_data['booking_date'] ?? $booking->booking_date,
                        'start_time' => $update_data['start_time'] ?? $booking->start_time,
                        'end_time' => $update_data['end_time'] ?? $booking->end_time,
                        'extra_people' => $update_data['extra_people'] ?? $booking->extra_people,
                        'extras' => $current_extras,
                        'payment_method' => $update_data['payment_method'] ?? $booking->payment_method
                    ];

                    // Calculate prices
                    $pricing = $booking_manager->calculate_booking_price($pricing_data);
                    
                    // Calculate duration manually
                    $start_timestamp = strtotime($pricing_data['start_time']);
                    $end_timestamp = strtotime($pricing_data['end_time']);
                    $total_hours = ($end_timestamp - $start_timestamp) / 3600;
                    
                    // Calculate additional amount (new total - original total)
                    $new_total_amount = $pricing['total_amount'];
                    $additional_amount = $new_total_amount - $original_total_amount;
                    
                    // Track modifications for hours and extra people if increased
                    // Use the original values stored BEFORE the update
                    $new_hours = $total_hours;
                    $new_extra_people = intval($update_data['extra_people'] ?? $booking->extra_people);
                    
                    // Calculate price differences for hours and extra people
                    $new_base_price = $pricing['base_price'];
                    $hours_additional_amount = $new_base_price - $original_base_price;
                    
                    $new_extra_people_price = $pricing['extra_people_cost'];
                    $extra_people_additional_amount = $new_extra_people_price - $original_extra_people_price;
                    
                    // Track modifications using helper function
                    hrb_track_booking_modifications(
                        $booking_manager,
                        $post_booking_id,
                        $original_hours,
                        $new_hours,
                        $original_extra_people,
                        $new_extra_people,
                        $hours_additional_amount,
                        $extra_people_additional_amount
                    );
                    
                    // Update booking with recalculated base prices first
                    $price_update_data = [
                        'total_hours' => $total_hours,
                        'base_price' => $pricing['base_price'],
                        'extra_people_price' => $pricing['extra_people_cost'],
                        'extras_price' => $pricing['extras_cost'],
                        'tax_amount' => $pricing['tax_amount']
                    ];
                    
                    // If no payments have been completed yet, use calculated values
                    if (!$is_payment_completed) {
                        $price_update_data['paypal_fee'] = $pricing['paypal_fee'];
                        $price_update_data['total_amount'] = $pricing['total_amount'];
                    }

                    // Pass true for send_notification so booking_modified email is sent when booking is updated
                    $booking_manager->update_booking($post_booking_id, $price_update_data, true);
                    
                    // If payment is already completed and booking total increased, create or update pending payment record
                    // Check if current booking total is greater than what was already paid
                    if ($is_payment_completed) {
                        // Pass the subtotal WITHOUT PayPal fee to avoid double-calculation
                        // The function will calculate the fee only on the outstanding amount
                        $subtotal_without_fee = $pricing['base_price'] + $pricing['extra_people_cost'] + $pricing['extras_cost'] + $pricing['tax_amount'];
                        hrb_update_pending_additional_payment($post_booking_id, $subtotal_without_fee);
                        
                        // NOTE: Email is NOT sent automatically - admin must click "Send Payment Link" button
                        
                        // NOW update booking total_amount and paypal_fee from payment records (source of truth)
                        // This ensures accuracy after pending payment is created/updated
                        $completed_payments_total = $wpdb->get_var($wpdb->prepare(
                            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}hrb_payments 
                            WHERE booking_id = %d AND status IN ('completed', 'paid')",
                            $post_booking_id
                        ));
                        $pending_payments_total = $wpdb->get_var($wpdb->prepare(
                            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}hrb_payments 
                            WHERE booking_id = %d AND status = 'pending'",
                            $post_booking_id
                        ));
                        $total_amount_from_payments = $completed_payments_total + $pending_payments_total;
                        
                        // PayPal fee is sum of all fees in payment records
                        $total_fees_from_payments = $wpdb->get_var($wpdb->prepare(
                            "SELECT COALESCE(SUM(fees), 0) FROM {$wpdb->prefix}hrb_payments 
                            WHERE booking_id = %d",
                            $post_booking_id
                        ));
                        
                        // Update booking with accurate totals from payment records
                        $booking_manager->update_booking($post_booking_id, [
                            'total_amount' => $total_amount_from_payments,
                            'paypal_fee' => $total_fees_from_payments
                        ], false);
                    }
                }

                // Update payment status in payments table (source of truth)
                if (isset($_POST['payment_status'])) {
                    $new_booking_status = sanitize_text_field($_POST['booking_status'] ?? '');
                    $new_payment_status = sanitize_text_field($_POST['payment_status']);

                    global $wpdb;
                    
                    // Get OLD payment status from payment table (not booking table)
                    $old_payment_record = $wpdb->get_row($wpdb->prepare(
                        "SELECT status FROM {$wpdb->prefix}hrb_payments WHERE booking_id = %d ORDER BY id DESC LIMIT 1",
                        $post_booking_id
                    ));
                    $old_payment_status = $old_payment_record ? $old_payment_record->status : ($booking->payment_status ?? 'pending');
                    
                    // Check if payment record exists
                    $payment_exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_payments WHERE booking_id = %d",
                        $post_booking_id
                    ));
                    
                    // If booking is being cancelled, auto-cancel payment status.
                    // Never cancel the standalone cancellation-fee charge (CANCELFEE_*):
                    // it stays pending until marked collected on-site.
                    if ($new_booking_status === 'cancelled') {
                        if ($payment_exists) {
                        $update_result = $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}hrb_payments SET status = 'cancelled'
                             WHERE booking_id = %d AND (transaction_id NOT LIKE %s OR transaction_id IS NULL)",
                            $post_booking_id,
                            $wpdb->esc_like('CANCELFEE_') . '%'
                        ));

                        if ($update_result === false) {
                        }
                        }
                        // Sync to booking table
                        $update_data['payment_status'] = 'cancelled';
                    } else {
                        // Update or create payment status in payments table FIRST (this is source of truth)
                        if ($payment_exists) {
                            // Get all payment records for this booking
                            $all_payment_records = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}hrb_payments WHERE booking_id = %d ORDER BY id ASC",
                                $post_booking_id
                            ));
                            
                            // Find the original payment record (not additional payments with ADD_ prefix)
                            $original_payment_record = null;
                            foreach ($all_payment_records as $record) {
                                if (strpos($record->transaction_id, 'ADD_') !== 0) {
                                    $original_payment_record = $record;
                                    break;
                                }
                            }
                            
                            // Only update the original payment record if it exists and status is not already completed/paid
                            // NEVER update the amount of an already completed payment
                            if ($original_payment_record) {
                                $original_status = strtolower($original_payment_record->status);
                                $is_original_completed = in_array($original_status, ['completed', 'paid']);
                                
                                // Only update status if it's not already completed/paid (to avoid changing completed payments)
                                if (!$is_original_completed) {
                                    $update_payment_data = array('status' => $new_payment_status);
                                    $format = array('%s');
                                    
                                    // Also update payment_method if it changed
                                    if (isset($update_data['payment_method'])) {
                                        $update_payment_data['payment_method'] = $update_data['payment_method'];
                                        $format[] = '%s';
                                    }
                                    
                                    // NEVER update the amount of the original payment record
                                    // The original payment amount should remain unchanged
                                    
                                    $payment_update_result = $wpdb->update(
                                        $wpdb->prefix . 'hrb_payments',
                                        $update_payment_data,
                                        array('id' => $original_payment_record->id), // Update only this specific payment record
                                        $format,
                                        array('%d')
                                    );
                                    
                                    if ($payment_update_result === false) {
                                    }
                                }
                            }
                        } else {
                            // Create payment record if it doesn't exist
                            // Calculate the amount that was actually paid (not including additional services added later)
                            // If additional services were added, we need to calculate the original amount
                            $payment_amount = floatval($booking->total_amount);
                            
                            // Check if additional services were added (by checking if there are completed additional payments)
                            // If there are completed additional payments, subtract them from total to get original amount
                            $completed_additional_payments = $wpdb->get_var($wpdb->prepare(
                                "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}hrb_payments 
                                WHERE booking_id = %d AND status IN ('completed', 'paid') 
                                AND transaction_id LIKE 'ADD_%%'",
                                $post_booking_id
                            ));
                            
                            // If there are completed additional payments, the original amount is total minus additional payments
                            if ($completed_additional_payments > 0) {
                                $payment_amount = $payment_amount - $completed_additional_payments;
                            } else {
                                // Check if there are pending additional payments (means additional services were added)
                                // In this case, calculate original amount from modifications or use a different approach
                                $pending_additional_payments = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}hrb_payments 
                                    WHERE booking_id = %d AND status = 'pending' 
                                    AND transaction_id LIKE 'ADD_%%'",
                                    $post_booking_id
                                ));
                                
                                // If there are pending additional payments, subtract them to get original amount
                                if ($pending_additional_payments > 0) {
                                    $payment_amount = $payment_amount - $pending_additional_payments;
                                }
                            }
                            
                            // Ensure payment amount is not negative or zero
                            if ($payment_amount <= 0) {
                                $payment_amount = floatval($booking->total_amount);
                            }
                            
                            $payment_insert_result = $wpdb->insert(
                                $wpdb->prefix . 'hrb_payments',
                                array(
                                    'booking_id' => $post_booking_id,
                                    'payment_method' => $update_data['payment_method'] ?? $booking->payment_method,
                                    'amount' => $payment_amount,
                                    'currency' => 'EUR',
                                    'status' => $new_payment_status,
                                    'created_at' => current_time('mysql')
                                ),
                                array('%d', '%s', '%f', '%s', '%s', '%s')
                            );
                            
                            if ($payment_insert_result === false) {
                            }
                        }
                        
                        // Sync payment status to booking table
                        $update_data['payment_status'] = $new_payment_status;
                        
                        // Normalize payment status values for comparison
                        $new_payment_status_normalized = strtolower(trim($new_payment_status));
                        $old_payment_status_normalized = strtolower(trim($old_payment_status));
                        
                        // If payment status changed to paid/completed, generate invoice and send payment confirmation email
                        // EXACTLY like PayPal payment flow
                        // BUT: Don't send if payment was already completed (to avoid sending duplicate emails when updating booking)
                        $paid_statuses = ['paid', 'completed'];
                        // Only send email if status changed FROM non-paid TO paid (not if it was already paid)
                        // Check both the payment record status and the original booking payment status
                        $was_already_paid = in_array($old_payment_status_normalized, $paid_statuses) || 
                                           in_array($original_payment_status_normalized, $paid_statuses);
                        if (in_array($new_payment_status_normalized, $paid_statuses) && 
                            !in_array($old_payment_status_normalized, $paid_statuses) &&
                            !$was_already_paid) {
                            
                            // Get updated booking
                            $updated_booking = $booking_manager->get_booking($post_booking_id);
                            
                            if ($updated_booking) {
                                // Ensure booking status is confirmed (required for invoice and email)
                                if ($updated_booking->status !== 'confirmed') {
                                    $booking_manager->update_booking($post_booking_id, array('status' => 'confirmed'), false);
                                    $updated_booking = $booking_manager->get_booking($post_booking_id);
                                }
                                
                                // Generate invoice if it doesn't exist
                                $invoice_generator = HRB_Invoice_Generator::getInstance();
                                $existing_invoice = $invoice_generator->get_invoice_by_booking($post_booking_id);
                                
                                if (!$existing_invoice) {
                                    $invoice_id = $booking_manager->create_invoice($post_booking_id);
                                    if (!is_wp_error($invoice_id)) {
                                        // Generate PDF for the invoice
                                        $invoice_generator->generate_invoice_pdf($invoice_id);
                                    }
                                } else {
                                    // Invoice exists, ensure PDF is generated
                                    if (empty($existing_invoice->pdf_file_path) || !file_exists($existing_invoice->pdf_file_path)) {
                                        $invoice_generator->generate_invoice_pdf($existing_invoice->id);
                                    }
                                }
                                
                                // Send payment confirmation email EXACTLY like PayPal payment flow
                                $email_result = $booking_manager->send_booking_notification($post_booking_id, 'payment_confirmation');
                                if (is_wp_error($email_result)) {
                                }
                            }
                        }
                    }
                }

                // Customer data is already updated above (for non-anonymous bookings)
                // No need to update again here
                $customer_result = true; // Default to success

                if ($result && $customer_result) {
                    // Redirect to view page with success message
                    //wp_redirect(admin_url('admin.php?page=hrb-bookings&action=view&id=' . $post_booking_id . '&updated=1'));
                    ?>
                    <script>
                        window.location.href = '<?php echo admin_url('admin.php?page=hrb-bookings&action=view&id=' . $post_booking_id . '&updated=1'); ?>';
                    </script>
                    <?php
                    //exit;
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to update booking.', 'hourly-room-booking') . '</p></div>';
                }
            }
            break;
    }

    // Reset action to 'list' after processing, UNLESS we're processing add_booking
    // If we're processing add_booking, keep action as 'add' to show the form with errors
    if (!isset($_POST['action']) || $_POST['action'] !== 'add_booking') {
        $action = 'list';
    }
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
    'recent_only' => true, // Only show bookings from last 2 days
];

$bookings = $booking_manager->get_bookings_admin($filters, $per_page, $offset);
$total_bookings = $booking_manager->get_bookings_count_admin($filters);
$total_pages = ceil($total_bookings / $per_page);


$rooms = $room_manager->get_all_rooms(['status' => 'active']);

// Helper function to generate sortable column headers
function hrb_get_sortable_header($label, $orderby, $current_orderby, $current_order, $default_order = 'desc')
{
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
    
    return sprintf(
        '<a href="%s" class="%s">%s%s</a>',
        esc_url($url), 
        esc_attr($class), 
        esc_html($label),
        $arrow
    );
}

/**
 * Get modification display text (who added it)
 * 
 * @param object $modification The modification object
 * @return string The display text
 */
function hrb_get_modification_added_by_text($modification) {
    if (empty($modification)) {
        return '';
    }
    
    $added_by_text = __('Added by Admin', 'hourly-room-booking');
    if (!empty($modification->added_by_display_name)) {
        $added_by_text = sprintf(__('Added by %s', 'hourly-room-booking'), esc_html($modification->added_by_display_name));
    } elseif (!empty($modification->added_by_username)) {
        $added_by_text = sprintf(__('Added by %s', 'hourly-room-booking'), esc_html($modification->added_by_username));
    }
    
    return $added_by_text;
}

/**
 * Get modifications by type from a list of modifications
 * 
 * @param array $modifications List of modification objects
 * @param string $type The modification type ('hours' or 'extra_people')
 * @return object|null The modification object or null
 */
function hrb_get_modification_by_type($modifications, $type) {
    if (empty($modifications) || !is_array($modifications)) {
        return null;
    }
    
    foreach ($modifications as $mod) {
        if (isset($mod->modification_type) && $mod->modification_type === $type) {
            return $mod;
        }
    }
    
    return null;
}

/**
 * Get modification highlighting styles
 * 
 * @param object|null $modification The modification object
 * @return array Array with 'style' and 'class' keys
 */
function hrb_get_modification_highlight_styles($modification) {
    if (empty($modification)) {
        return [
            'style' => '',
            'class' => ''
        ];
    }
    
    return [
        'style' => 'background: #fff3cd; border-left: 3px solid #ffc107; padding-left: 15px;',
        'class' => 'hrb-summary-modified'
    ];
}

/**
 * Check if booking has completed payment
 * 
 * @param int $booking_id Booking ID
 * @return bool True if booking has completed payment
 */
function hrb_booking_has_completed_payment($booking_id) {
    global $wpdb;
    $has_completed_payment = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_payments 
        WHERE booking_id = %d AND status IN ('completed', 'paid') 
        AND transaction_id NOT LIKE 'ADD_%%'",
        $booking_id
    ));
    return ($has_completed_payment > 0);
}

/**
 * Calculate and update pending payment for additional services
 * 
 * @param int $booking_id Booking ID
 * @param float $current_booking_total Current booking total amount
 * @return bool True on success, false on failure
 */
function hrb_update_pending_additional_payment($booking_id, $current_booking_total) {
    global $wpdb;
    $payment_manager = HRB_Payment_Manager::getInstance();
    $currency = HRB_Currency_Manager::getInstance()->get_currency_code();
    
    // Get booking to check payment method
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT payment_method FROM {$wpdb->prefix}hrb_bookings WHERE id = %d",
        $booking_id
    ));
    
    $payment_method = $booking ? strtolower(trim($booking->payment_method)) : 'onsite';
    
    // Calculate total amount that should be pending
    // Note: $current_booking_total is now passed as subtotal WITHOUT fees
    // We need to subtract what was paid WITHOUT fees to compare apples to apples
    $already_paid_without_fees = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount - COALESCE(fees, 0)), 0) FROM {$wpdb->prefix}hrb_payments 
        WHERE booking_id = %d AND status IN ('completed', 'paid')",
        $booking_id
    ));
    
    // Calculate the outstanding amount without fees first
    $outstanding_without_fee = $current_booking_total - $already_paid_without_fees;
    
    // Calculate PayPal fee ONLY if payment method is PayPal AND there's an outstanding amount
    $paypal_fee = 0;
    if ($payment_method === 'paypal' && $outstanding_without_fee > 0) {
        // PayPal fee is 3% of the outstanding amount (not including the fee itself)
        $paypal_fee = $outstanding_without_fee * 0.03;
    }
    
    // Total pending includes the fee
    $total_pending_amount = $outstanding_without_fee + $paypal_fee;
    
    // Only create/update pending payment if there's an amount to pay (use 0.01 threshold for floating point precision)
    if ($total_pending_amount > 0.01) {
        // Check if there's an existing pending payment record for additional services
        $existing_pending_payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_payments 
            WHERE booking_id = %d AND status = 'pending' AND transaction_id LIKE 'ADD_%%'
            ORDER BY id DESC LIMIT 1",
            $booking_id
        ));
        
        if ($existing_pending_payment) {
            // Update existing pending payment record with new total amount and fees
            // Ensure payment_token exists (for old records that might not have one)
            $update_data = array(
                'amount' => $total_pending_amount,
                'fees' => $paypal_fee,
                'payment_method' => $payment_method,
                'is_additional_payment' => 1
            );
            $format = array('%f', '%f', '%s', '%d');
            
            // Generate token if it doesn't exist
            if (empty($existing_pending_payment->payment_token)) {
                $update_data['payment_token'] = wp_generate_password(32, false);
                $format[] = '%s';
            }
            
            $update_result = $wpdb->update(
                $wpdb->prefix . 'hrb_payments',
                $update_data,
                array('id' => $existing_pending_payment->id),
                $format,
                array('%d')
            );
            
            if ($update_result === false) {
                return false;
            }
        } else {
            // Create new pending payment record if none exists
            // Generate unique payment token for additional payments
            $payment_token = wp_generate_password(32, false);
            $payment_id = $payment_manager->create_payment(
                $booking_id,
                $total_pending_amount,
                $payment_method,
                $currency,
                array(
                    'status' => 'pending',
                    'transaction_id' => 'ADD_' . time() . '_' . $booking_id,
                    'is_additional_payment' => 1,
                    'payment_token' => $payment_token,
                    'fees' => $paypal_fee
                )
            );
            
            if (is_wp_error($payment_id)) {
                return false;
            }
        }
    } else {
        // If total pending is 0 or negative, delete any existing pending payment records
        // This handles cases where services were removed
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}hrb_payments 
            WHERE booking_id = %d AND status = 'pending' AND transaction_id LIKE 'ADD_%%'",
            $booking_id
        ));
    }
    
    return true;
}

/**
 * Track booking modifications (hours and extra people)
 * 
 * @param HRB_Booking_Manager $booking_manager Booking manager instance
 * @param int $booking_id Booking ID
 * @param float $original_hours Original hours
 * @param float $new_hours New hours
 * @param int $original_extra_people Original extra people
 * @param int $new_extra_people New extra people
 * @param float $hours_additional_amount Additional amount for hours
 * @param float $extra_people_additional_amount Additional amount for extra people
 * @return void
 */
function hrb_track_booking_modifications($booking_manager, $booking_id, $original_hours, $new_hours, $original_extra_people, $new_extra_people, $hours_additional_amount, $extra_people_additional_amount) {
    // Track hours modification if increased
    if ($new_hours > $original_hours && $hours_additional_amount > 0) {
        $booking_manager->track_booking_modification(
            $booking_id,
            'hours',
            $original_hours,
            $new_hours,
            $hours_additional_amount
        );
    }
    
    // Track extra people modification if increased
    // Always track if extra people increased, even if price is 0 (for display purposes)
    if ($new_extra_people > $original_extra_people) {
        $booking_manager->track_booking_modification(
            $booking_id,
            'extra_people',
            $original_extra_people,
            $new_extra_people,
            $extra_people_additional_amount
        );
    }
}
?>

<?php if ($action === 'view' && isset($booking)): ?>
    <!-- VIEW BOOKING -->
    <div class="wrap hrb-admin-booking-view">
        <div class="hrb-page-header">
            <h1 class="wp-heading-inline">
            <?php printf(__('Booking Details - #%s', 'hourly-room-booking'), esc_html($booking->booking_reference)); ?>
        </h1>
        <a href="<?php echo admin_url('admin.php?page=hrb-bookings'); ?>" class="page-title-action">
            <?php _e('Back to Bookings', 'hourly-room-booking'); ?>
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
                        <?php
                        // Get booking modifications for highlighting
                        $booking_modifications_info = $booking_manager->get_booking_modifications($booking->id);
                        $hours_modification_info = hrb_get_modification_by_type($booking_modifications_info, 'hours');
                        $extra_people_modification_info = hrb_get_modification_by_type($booking_modifications_info, 'extra_people');
                        ?>
                        <?php
                        $hours_highlight = hrb_get_modification_highlight_styles($hours_modification_info);
                        $extra_people_highlight = hrb_get_modification_highlight_styles($extra_people_modification_info);
                        ?>
                        <tr>
                            <th><?php _e('Duration', 'hourly-room-booking'); ?></th>
                            <td>
                                <?php echo esc_html($booking->total_hours); ?> <?php _e('hours', 'hourly-room-booking'); ?>
                            </td>
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
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="hrb-details-section">
                    <h3><?php _e('Customer Information', 'hourly-room-booking'); ?></h3>
                    <table class="widefat">
                        <?php if ($booking->is_anonymous): ?>
                            <tr>
                                <th><?php _e('Booking Type', 'hourly-room-booking'); ?></th>
                                <td><span class="hrb-badge hrb-badge-warning"><?php _e('Anonymous Booking', 'hourly-room-booking'); ?></span></td>
                            </tr>
                            <?php if (!empty($booking->first_name)): ?>
                                <tr>
                                    <th><?php _e('Name', 'hourly-room-booking'); ?></th>
                                    <td><?php echo esc_html(trim($booking->first_name . ' ' . ($booking->last_name ?? ''))); ?></td>
                                </tr>
                            <?php else: ?>
                            <tr>
                                <th><?php _e('Contact Information', 'hourly-room-booking'); ?></th>
                                    <td><?php _e('No name provided for this anonymous booking', 'hourly-room-booking'); ?></td>
                            </tr>
                            <?php endif; ?>
                        <?php else: ?>
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
                        <?php endif; ?>
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
                                <?php 
                                $extra_people_unit_price = $booking->extra_people > 0 ? $booking->extra_people_price / $booking->extra_people : 0;
                                echo $booking->extra_people . ' × ' . hrb_format_amount($extra_people_unit_price) . ' = ' . hrb_format_amount($booking->extra_people_price); 
                                ?>
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
                        
                        <?php 
                        // Get PayPal fees from payment records (more accurate for partial payments)
                        $payment_handler_view = HRB_Payment_Handler::getInstance();
                        $all_payments_view = $payment_handler_view->get_booking_payments($booking->id);
                        $total_paypal_fees = 0;
                        foreach ($all_payments_view as $payment_view) {
                            if (isset($payment_view->fees) && $payment_view->fees > 0) {
                                $total_paypal_fees += floatval($payment_view->fees);
                            }
                        }
                        
                        // Fallback to booking table if no fees in payment records
                        if ($total_paypal_fees == 0 && $booking->paypal_fee > 0) {
                            $total_paypal_fees = $booking->paypal_fee;
                        }
                        
                        if ($total_paypal_fees > 0): ?>
                        <tr>
                            <th><?php _e('PayPal Fee', 'hourly-room-booking'); ?></th>
                            <td><?php echo hrb_format_amount($total_paypal_fees); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr style="border-top: 2px solid #333; font-weight: bold; font-size: 1.1em;">
                            <th><?php _e('Total', 'hourly-room-booking'); ?></th>
                            <td><strong><?php echo hrb_format_amount($booking->total_amount); ?></strong></td>
                        </tr>

                        <?php if (isset($booking->cancellation_fee) && floatval($booking->cancellation_fee) > 0): ?>
                        <tr>
                            <th style="color:#b32d2e;"><?php _e('Cancellation Fee', 'hourly-room-booking'); ?></th>
                            <td style="color:#b32d2e;"><strong><?php echo hrb_format_amount($booking->cancellation_fee); ?></strong> <span style="font-weight:normal;">(<?php _e('payable on-site', 'hourly-room-booking'); ?>)</span></td>
                        </tr>
                        <?php endif; ?>
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

                <?php if (!empty($booking->admin_notes)): ?>
                    <div class="hrb-details-section">
                        <h3><?php _e('Admin Notes', 'hourly-room-booking'); ?></h3>
                        <div class="hrb-admin-notes">
                            <?php echo nl2br(esc_html($booking->admin_notes)); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Price Summary - Full Width Section -->
            <div class="hrb-details-section hrb-price-summary-fullwidth">
                <h3><?php _e('Price Summary', 'hourly-room-booking'); ?></h3>
                <div class="hrb-summary-content">
                        <?php
                        // Get room details
                        $room_manager = HRB_Room_Manager::getInstance();
                        $room = $room_manager->get_room($booking->room_id);
                        $room_name = $room ? $room->name : __('Room', 'hourly-room-booking');
                        
                        // Get booking extras with details
                        $extras_manager = HRB_Extras::getInstance();
                        $booking_extras = $extras_manager->get_booking_extras($booking->id);
                        
                        // Get booking modifications
                        $booking_modifications = $booking_manager->get_booking_modifications($booking->id);
                        $hours_modification = hrb_get_modification_by_type($booking_modifications, 'hours');
                        $extra_people_modification = hrb_get_modification_by_type($booking_modifications, 'extra_people');
                        
                        // Get PayPal fee from payment records (accurate for partial payments)
                        $subtotal = $booking->base_price + $booking->extra_people_price + $booking->extras_price;
                        $paypal_fee = 0;
                        // Use the already calculated $total_paypal_fees from above
                        if (isset($total_paypal_fees)) {
                            $paypal_fee = $total_paypal_fees;
                        } else {
                            // Recalculate if not already done
                            foreach ($all_payments_summary as $payment_summary) {
                                if (isset($payment_summary->fees) && $payment_summary->fees > 0) {
                                    $paypal_fee += floatval($payment_summary->fees);
                                }
                            }
                            if ($paypal_fee == 0) {
                                $paypal_fee = $booking->paypal_fee; // Fallback
                            }
                        }
                        
                        // Get currency settings
                        $currency_symbol = hrb_get_currency_symbol();
                        $currency_code = hrb_get_currency_code();
                        
                        // Calculate payment breakdown for Price Summary
                        $payment_handler = HRB_Payment_Handler::getInstance();
                        $all_payments_summary = $payment_handler->get_booking_payments($booking->id);
                        
                        // Calculate already paid amount (completed payments that are NOT additional payments)
                        $already_paid_amount_summary = 0;
                        // Calculate additional services paid amount (completed payments that ARE additional payments)
                        $additional_services_paid_summary = 0;
                        $total_pending_amount_summary = 0;
                        foreach ($all_payments_summary as $payment) {
                            $is_additional = isset($payment->is_additional_payment) && $payment->is_additional_payment == 1;
                            $is_completed = in_array(strtolower($payment->status), ['completed', 'paid']);
                            
                            if ($is_completed && !$is_additional) {
                                // This is the original payment (not an additional payment)
                                $already_paid_amount_summary += floatval($payment->amount);
                            } elseif ($is_completed && $is_additional) {
                                // This is a completed additional payment
                                $additional_services_paid_summary += floatval($payment->amount);
                            } elseif ($payment->status === 'pending') {
                                // This is ANY pending payment (original or additional)
                                $total_pending_amount_summary += floatval($payment->amount);
                            }
                        }
                        
                        // If no pending payments found, calculate still need to pay as difference between booking total and already paid
                        // This handles cases where additional services were added but pending payment record might not exist
                        if ($total_pending_amount_summary == 0 && $already_paid_amount_summary > 0) {
                            $current_booking_total_summary = floatval($booking->total_amount);
                            $total_paid_summary = $already_paid_amount_summary + $additional_services_paid_summary;
                            if ($current_booking_total_summary > $total_paid_summary) {
                                $total_pending_amount_summary = $current_booking_total_summary - $total_paid_summary;
                            }
                        }
                        
                        // Handle case where payment method is PayPal but payment status is pending (onsite payment)
                        // Admin will collect payment manually, so show full booking amount as outstanding
                        if ($total_pending_amount_summary == 0 && $already_paid_amount_summary == 0) {
                            $payment_status_normalized = strtolower(trim($booking->payment_status ?? 'pending'));
                            if ($payment_status_normalized === 'pending') {
                                // No payments completed yet and status is pending - show full booking amount as outstanding
                                $total_pending_amount_summary = floatval($booking->total_amount);
                            }
                        }
                        
                        // Get PayPal fee from pending payment records (more accurate than calculating)
                        // This ensures we show the exact fee that will be charged
                        if ($booking->payment_method === 'paypal') {
                            $paypal_fee_from_payments = 0;
                            foreach ($all_payments_summary as $payment) {
                                if ($payment->status === 'pending' && isset($payment->fees)) {
                                    $paypal_fee_from_payments += floatval($payment->fees);
                                }
                            }
                            
                            // If we have fees from payment records, use those (most accurate)
                            if ($paypal_fee_from_payments > 0) {
                                $paypal_fee = $paypal_fee_from_payments;
                            } elseif ($already_paid_amount_summary > 0 || $additional_services_paid_summary > 0) {
                                // Fallback calculation for outstanding amount only
                                $outstanding_without_fee = $total_pending_amount_summary / 1.03;
                                $paypal_fee = $outstanding_without_fee * 0.03;
                            }
                        }
                        
                        // Grand total = already paid + additional services paid + still need to pay (or booking total if no payments yet)
                        $grand_total_summary = ($already_paid_amount_summary > 0 || $additional_services_paid_summary > 0) ? ($already_paid_amount_summary + $additional_services_paid_summary + $total_pending_amount_summary) : floatval($booking->total_amount);
                        ?>
                        
                        <?php
                        $hours_highlight = hrb_get_modification_highlight_styles($hours_modification);
                        $extra_people_highlight = hrb_get_modification_highlight_styles($extra_people_modification);
                        ?>
                        <div class="hrb-summary-item <?php echo esc_attr($hours_highlight['class']); ?>" <?php echo $hours_highlight['style'] ? 'style="' . esc_attr($hours_highlight['style']) . '"' : ''; ?>>
                            <span>
                                <?php echo esc_html($room_name); ?> (<?php echo esc_html($booking->total_hours); ?>h)
                                <?php if ($hours_modification): ?>
                                    <?php
                                    $added_by_text = hrb_get_modification_added_by_text($hours_modification);
                                    $hours_increase = $hours_modification->new_value - $hours_modification->original_value;
                                    ?>
                                    <br><small class="hrb-modification-text">
                                        +<?php echo esc_html($hours_increase); ?> <?php _e('hours', 'hourly-room-booking'); ?> <?php echo $added_by_text; ?> (+<?php echo hrb_format_amount($hours_modification->additional_amount); ?>)
                                    </small>
                                <?php endif; ?>
                            </span>
                            <span><?php echo hrb_format_amount($booking->base_price); ?></span>
                        </div>
                        
                        <?php if ($booking->extra_people > 0): ?>
                        <div class="hrb-summary-item <?php echo esc_attr($extra_people_highlight['class']); ?>" <?php echo $extra_people_highlight['style'] ? 'style="' . esc_attr($extra_people_highlight['style']) . '"' : ''; ?>>
                            <span>
                                <?php _e('Extra People', 'hourly-room-booking'); ?> (<?php echo esc_html($booking->extra_people); ?>)
                                <?php if ($extra_people_modification): ?>
                                    <?php
                                    $added_by_text = hrb_get_modification_added_by_text($extra_people_modification);
                                    $people_increase = $extra_people_modification->new_value - $extra_people_modification->original_value;
                                    ?>
                                    <br><small class="hrb-modification-text">
                                        +<?php echo esc_html($people_increase); ?> <?php _e('people', 'hourly-room-booking'); ?> <?php echo $added_by_text; ?> (+<?php echo hrb_format_amount($extra_people_modification->additional_amount); ?>)
                                    </small>
                                <?php endif; ?>
                            </span>
                            <span><?php echo hrb_format_amount($booking->extra_people_price); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($booking_extras)): ?>
                        <div class="hrb-summary-section">
                            <strong><?php _e('Extras', 'hourly-room-booking'); ?></strong>
                        </div>
                        <?php foreach ($booking_extras as $extra): ?>
                        <div class="hrb-summary-item hrb-summary-extra">
                            <span class="hrb-extra-summary-name">
                                <?php echo esc_html($extra->name); ?>
                                <?php if (!empty($extra->added_by_admin) && ($extra->added_by_admin == 1 || $extra->added_by_admin === '1' || $extra->added_by_admin === true)): ?>
                                    <?php
                                    // Show username if available, otherwise show "Added by Admin"
                                    $added_by_text = __('Added by Admin', 'hourly-room-booking');
                                    if (!empty($extra->added_by_user_id) && !empty($extra->added_by_display_name)) {
                                        $added_by_text = sprintf(__('Added by %s', 'hourly-room-booking'), esc_html($extra->added_by_display_name));
                                    } elseif (!empty($extra->added_by_user_id) && !empty($extra->added_by_username)) {
                                        $added_by_text = sprintf(__('Added by %s', 'hourly-room-booking'), esc_html($extra->added_by_username));
                                    }
                                    ?>
                                    <span class="hrb-modification-badge">(<?php echo $added_by_text; ?>)</span>
                                <?php endif; ?>
                            </span>
                            <span class="hrb-extra-summary-price"><?php echo hrb_format_amount($extra->total_price); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if ($paypal_fee > 0): ?>
                        <div class="hrb-summary-item hrb-summary-fee">
                            <span><?php _e('PayPal Fee', 'hourly-room-booking'); ?> (3%)</span>
                            <span><?php echo hrb_format_amount($paypal_fee); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($already_paid_amount_summary > 0 || $additional_services_paid_summary > 0): ?>
                        <div class="hrb-summary-section" style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #ddd;">
                            <strong><?php _e('Payment Status', 'hourly-room-booking'); ?></strong>
                        </div>
                        <?php 
                        // Show individual completed payments for better transparency
                        $completed_payment_count = 0;
                        foreach ($all_payments_summary as $payment) {
                            $is_completed = in_array(strtolower($payment->status), ['completed', 'paid']);
                            if ($is_completed) {
                                $completed_payment_count++;
                                $is_additional = isset($payment->is_additional_payment) && $payment->is_additional_payment == 1;
                                $payment_label = $is_additional 
                                    ? __('Additional Payment', 'hourly-room-booking') 
                                    : __('Original Payment', 'hourly-room-booking');
                                
                                // Show payment method (translated)
                                $payment_method_display = '';
                                if (!empty($payment->payment_method)) {
                                    $payment_method_display = ' (' . hrb_get_payment_method_label($payment->payment_method) . ')';
                                }
                                ?>
                            <div class="hrb-summary-paid">
                                    <span class="hrb-summary-paid-text hrb-summary-paid-lbl">
                                    <span class="dashicons dashicons-yes-alt hrb-summary-paid-icon"></span>
                                        <strong><?php echo esc_html($payment_label); ?> #<?php echo $completed_payment_count; ?></strong>
                                        <br><small class="hrb-summary-paid-small">
                                            <?php echo esc_html($payment_method_display); ?>
                                            <?php if (!empty($payment->processed_at)): ?>
                                                <?php echo ' - ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->processed_at)); ?>
                                            <?php endif; ?>
                                        </small>
                                    </span>
                                <span class="hrb-summary-paid-text">
                                        <strong><?php echo hrb_format_amount($payment->amount); ?></strong>
                                        <?php if (isset($payment->fees) && $payment->fees > 0): ?>
                                            <br><small class="hrb-summary-paid-small" style="color: #666;">
                                                <?php printf(__('(incl. %s fee)', 'hourly-room-booking'), hrb_format_amount($payment->fees)); ?>
                                            </small>
                                        <?php endif; ?>
                                </span>
                            </div>
                                <?php
                            }
                        }
                        ?>
                            
                        <?php // Show total of all completed payments
                        $total_completed = $already_paid_amount_summary + $additional_services_paid_summary;
                        if ($total_completed > 0): ?>
                        <div class="hrb-summary-item" style="background: #e8f5e9; padding: 12px; margin-top: 5px; border-radius: 4px;">
                            <span><strong><?php _e('Total Paid', 'hourly-room-booking'); ?></strong></span>
                            <span><strong><?php echo hrb_format_amount($total_completed); ?></strong></span>
                            </div>
                        <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($total_pending_amount_summary > 0.01): ?>
                            <?php 
                            // Determine if this is for additional services or full payment
                            $payment_status_normalized = strtolower(trim($booking->payment_status ?? 'pending'));
                            $is_full_payment_pending = ($already_paid_amount_summary == 0 && $payment_status_normalized === 'pending');
                        
                        // Calculate the breakdown: amount + fee
                        $pending_fees_total = 0;
                        foreach ($all_payments_summary as $payment) {
                            if ($payment->status === 'pending' && isset($payment->fees)) {
                                $pending_fees_total += floatval($payment->fees);
                            }
                        }
                        $outstanding_base_amount = $total_pending_amount_summary - $pending_fees_total;
                            ?>
                            <div class="hrb-summary-pending">
                                <span class="hrb-summary-pending-text">
                                    <strong><?php _e('Outstanding Payment Required', 'hourly-room-booking'); ?></strong>
                                    <?php if (!$is_full_payment_pending): ?>
                                    <br><small class="hrb-summary-pending-small"><?php _e('For additional services', 'hourly-room-booking'); ?></small>
                                    <?php endif; ?>
                                <?php if ($pending_fees_total > 0): ?>
                                <br><small class="hrb-summary-pending-small" style="color: #666; font-style: italic;">
                                    <?php printf(
                                        __('Base: %s + Fee: %s', 'hourly-room-booking'),
                                        hrb_format_amount($outstanding_base_amount),
                                        hrb_format_amount($pending_fees_total)
                                    ); ?>
                                </small>
                                <?php endif; ?>
                                </span>
                                <span class="hrb-summary-pending-text">
                                    <strong><?php echo hrb_format_amount($total_pending_amount_summary); ?></strong>
                                <?php if ($booking->payment_method === 'paypal' && $pending_fees_total > 0): ?>
                                <br><small class="hrb-summary-small-text">
                                    <?php printf(__('(3%% fee on %s)', 'hourly-room-booking'), hrb_format_amount($outstanding_base_amount)); ?>
                                </small>
                                    <?php else: ?>
                                    <br><small class="hrb-summary-small-text"><?php _e('Amount due', 'hourly-room-booking'); ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="hrb-summary-total">
                            <span><strong><?php _e('Total', 'hourly-room-booking'); ?></strong></span>
                            <span><strong><?php echo hrb_format_amount($grand_total_summary); ?></strong></span>
                        </div>
                    </div>
                </div>

            <div class="hrb-booking-actions">
                <?php if (current_user_can('hrb_manage_bookings')): ?>
                <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=edit&id=' . $booking->id); ?>" class="button button-primary">
                    <?php _e('Edit Booking', 'hourly-room-booking'); ?>
                </a>
                <?php endif; ?>

                <?php if (current_user_can('hrb_manage_bookings')): ?>
                    <?php 
                    $invoice_generator = HRB_Invoice_Generator::getInstance();
                    $existing_invoice = $invoice_generator->get_invoice_by_booking($booking->id);
                    $invoice_url = get_invoice_download_url($booking->id);
                    ?>
                    <?php if ($existing_invoice): ?>
                        <?php if ($invoice_url): ?>
                            <a href="<?php echo esc_url($invoice_url); ?>" target="_blank" class="button button-secondary">
                                <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
                                <?php _e('Download Invoice', 'hourly-room-booking'); ?>
                            </a>
                        <?php endif; ?>
                        <button type="button" id="hrb-regenerate-invoice-btn" class="button button-secondary" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                            <span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php _e('Regenerate Invoice', 'hourly-room-booking'); ?>
                        </button>
                        <span id="hrb-regenerate-invoice-message" style="margin-left: 10px; display: none;"></span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php
                // Show payment action buttons based on payment method
                $payment_method_normalized = strtolower(trim($booking->payment_method ?? ''));
                $is_onsite_payment = ($payment_method_normalized === 'onsite' || $payment_method_normalized === 'cash');
                
                    global $wpdb;
                    $payment_handler = HRB_Payment_Handler::getInstance();
                    $all_payments_view = $payment_handler->get_booking_payments($booking->id);
                    
                    $has_pending_payment = false;
                $has_pending_additional_payment = false;
                    $pending_amount = 0;
                $pending_additional_amount = 0;
                
                    foreach ($all_payments_view as $payment) {
                    if ($payment->status === 'pending') {
                            $has_pending_payment = true;
                            $pending_amount += floatval($payment->amount);
                        
                        // Track additional payments separately for PayPal payment link
                        if (!empty($payment->transaction_id) && strpos((string) $payment->transaction_id, 'ADD_') === 0) {
                            $has_pending_additional_payment = true;
                            $pending_additional_amount += floatval($payment->amount);
                        }
                        }
                    }
                    
                if ($has_pending_payment && current_user_can('hrb_manage_bookings')):
                    if (!$is_onsite_payment && $has_pending_additional_payment): 
                        // For PayPal and other online payments - show "Send Payment Link" button
                        // Only for additional payments (original PayPal payments are handled during booking creation)
                        ?>
                        <button type="button" id="hrb-send-payment-link-btn" class="button button-secondary" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                            <span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php _e('Send Payment Link', 'hourly-room-booking'); ?>
                        </button>
                        <span id="hrb-send-payment-link-message" style="margin-left: 10px; display: none;"></span>
                    <?php elseif ($is_onsite_payment): 
                        // For onsite/cash payments - show "Mark Payment as Complete" button for ALL pending payments
                        ?>
                        <button type="button" id="hrb-mark-additional-payment-complete-btn" class="button button-primary" data-booking-id="<?php echo esc_attr($booking->id); ?>" data-amount="<?php echo esc_attr($pending_amount); ?>">
                            <span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php _e('Mark Payment as Complete', 'hourly-room-booking'); ?>
                        </button>
                        <span id="hrb-mark-additional-payment-message" style="margin-left: 10px; display: none;"></span>
                    <?php endif;
                endif; ?>

                <?php if ($booking->status === 'pending' && current_user_can('hrb_manage_bookings')): ?>
                    <form method="POST" style="display: inline;">
                        <?php wp_nonce_field('hrb_admin_action', 'hrb_nonce'); ?>
                        <input type="hidden" name="action" value="confirm">
                        <input type="hidden" name="id" value="<?php echo esc_attr($booking->id); ?>">
                        <button type="submit" class="button button-secondary"><?php _e('Confirm Booking', 'hourly-room-booking'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($action === 'edit' && isset($booking)): ?>
    <!-- EDIT BOOKING -->
    <?php
    // Get payment status from payment table (this is the source of truth)
    global $wpdb;
    $payment = $wpdb->get_row($wpdb->prepare(
        "SELECT status FROM {$wpdb->prefix}hrb_payments WHERE booking_id = %d ORDER BY id DESC LIMIT 1",
        $booking->id
    ));
    
    // Use payment table status if available, otherwise fall back to booking table
    $actual_payment_status = $payment ? $payment->status : $booking->payment_status;
    
    // Get booking extras for edit form
    $extras_manager = HRB_Extras::getInstance();
    $booking_extras = $extras_manager->get_booking_extras($booking->id);
    
    // Get booking modifications for edit form
    $booking_modifications = $booking_manager->get_booking_modifications($booking->id);
    ?>
    <div class="wrap hrb-admin-booking-edit">
        <div class="hrb-page-header">
            <h1 class="wp-heading-inline">
            <?php printf(__('Edit Booking - #%s', 'hourly-room-booking'), esc_html($booking->booking_reference)); ?>
        </h1>
        <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=view&id=' . $booking->id); ?>" class="page-title-action">
            <?php _e('View Details', 'hourly-room-booking'); ?>
        </a>

        </div>
        
        <hr class="wp-header-end">

        <form method="POST" class="hrb-edit-booking-form">
            <?php wp_nonce_field('hrb_admin_action', 'hrb_nonce'); ?>
            <input type="hidden" name="action" value="update_booking">
            <input type="hidden" name="id" value="<?php echo $booking->id; ?>">

            <div class="hrb-edit-grid">
                <div class="hrb-edit-section">
                    <h3><?php _e('Booking Details', 'hourly-room-booking'); ?></h3>

                    <table class="form-table">
                        <tr>
                            <th><label for="booking_status"><?php _e('Status', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <select name="booking_status" id="booking_status">
                                    <option value="pending" <?php selected($booking->status, 'pending'); ?>><?php _e('Pending', 'hourly-room-booking'); ?></option>
                                    <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>><?php _e('Confirmed', 'hourly-room-booking'); ?></option>
                                    <option value="completed" <?php selected($booking->status, 'completed'); ?>><?php _e('Completed', 'hourly-room-booking'); ?></option>
                                    <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>><?php _e('Cancelled', 'hourly-room-booking'); ?></option>
                                    <option value="no_show" <?php selected($booking->status, 'no_show'); ?>><?php _e('No Show', 'hourly-room-booking'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="payment_status"><?php _e('Payment Status', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <select name="payment_status" id="payment_status">
                                    <option value="pending" <?php selected($actual_payment_status, 'pending'); ?>><?php _e('Pending', 'hourly-room-booking'); ?></option>
                                    <option value="completed" <?php selected($actual_payment_status, 'completed'); ?>><?php _e('Completed', 'hourly-room-booking'); ?></option>
                                    <option value="cancelled" <?php selected($actual_payment_status, 'cancelled'); ?>><?php _e('Cancelled', 'hourly-room-booking'); ?></option>
                                    <option value="refunded" <?php selected($actual_payment_status, 'refunded'); ?>><?php _e('Refunded', 'hourly-room-booking'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="booking_date"><?php _e('Date', 'hourly-room-booking'); ?></label></th>
                            <td><input type="date" name="booking_date" id="booking_date" value="<?php echo esc_attr($booking->booking_date); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="duration"><?php _e('Duration', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <select name="duration" id="duration" class="regular-text">
                                    <option value=""><?php _e('Select duration', 'hourly-room-booking'); ?></option>
                                    <?php for ($hours = 2; $hours <= 12; $hours++): ?>
                                        <option value="<?php echo $hours; ?>" data-hours="<?php echo $hours; ?>" <?php selected(intval($booking->total_hours), $hours); ?>>
                                            <?php echo sprintf(__('%d Hours', 'hourly-room-booking'), $hours); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="extra_people"><?php _e('Extra People', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <input type="number" name="extra_people" id="extra_people" value="<?php echo esc_attr($booking->extra_people ?? 0); ?>" min="0" max="10" class="small-text">
                                <p class="description"><?php printf(__('Number of additional people beyond the base (%s per extra person, max 10)', 'hourly-room-booking'), hrb_format_amount(15)); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="payment_method"><?php _e('Payment Method', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <select name="payment_method" id="payment_method" class="regular-text">
                                    <option value="onsite" <?php selected($booking->payment_method, 'onsite'); ?>><?php _e('On-site Payment', 'hourly-room-booking'); ?></option>
                                    <option value="paypal" <?php selected($booking->payment_method, 'paypal'); ?>><?php _e('PayPal', 'hourly-room-booking'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="room_id"><?php _e('Room', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <select name="room_id" id="room_id" class="regular-text">
                                    <?php
                                    $room_manager = HRB_Room_Manager::getInstance();
                                    $all_rooms = $room_manager->get_all_rooms('all'); // Get all rooms including inactive
                                    foreach ($all_rooms as $room): ?>
                                        <option value="<?php echo $room->id; ?>" <?php selected($booking->room_id, $room->id); ?>>
                                            <?php echo esc_html($room->name); ?>
                                            <?php if ($room->is_active): ?>
                                                (<?php _e('Active', 'hourly-room-booking'); ?>)
                                            <?php else: ?>
                                                (<?php _e('Inactive', 'hourly-room-booking'); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="time_slots"><?php _e('Available Time Slots', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <div id="time-slots-container">
                                    <div class="hrb-loading-message">
                                        <div class="hrb-loading-text"><?php _e('Please select a date and duration first', 'hourly-room-booking'); ?></div>
                                    </div>
                                </div>
                                <input type="hidden" name="start_time" id="start_time" value="<?php echo esc_attr($booking->start_time); ?>">
                                <input type="hidden" name="end_time" id="end_time" value="<?php echo esc_attr($booking->end_time); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="extras"><?php _e('Extras', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <div id="extras-container">
                                    <div class="hrb-loading-message">
                                        <div class="hrb-loading-text"><?php _e('Please select a room, date and time slot first', 'hourly-room-booking'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="special_requests"><?php _e('Special Requests', 'hourly-room-booking'); ?></label></th>
                            <td><textarea name="special_requests" id="special_requests" rows="4" class="large-text"><?php echo esc_textarea($booking->special_requests); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="admin_notes"><?php _e('Admin Notes', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <textarea name="admin_notes" id="admin_notes" rows="4" class="large-text" placeholder="<?php _e('Enter any additional notes, extra payments, services, or other information about this booking...', 'hourly-room-booking'); ?>"><?php echo esc_textarea($booking->admin_notes ?? ''); ?></textarea>
                                <p class="description"><?php _e('Use this field to add notes about extra payments, services, or any other information for internal use.', 'hourly-room-booking'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php _e('Price Summary', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <div id="admin-booking-summary" class="hrb-admin-summary">
                                    <div class="hrb-loading-message">
                                        <div class="hrb-loading-text"><?php _e('Please select room, date, duration and time slot to see pricing', 'hourly-room-booking'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="hrb-edit-section">
                    <h3><?php _e('Customer Information', 'hourly-room-booking'); ?></h3>

                    <?php if ($booking->is_anonymous): ?>
                        <div class="hrb-anonymous-booking-notice">
                            <p><strong><?php _e('Anonymous Booking', 'hourly-room-booking'); ?></strong></p>
                        </div>
                        <table class="form-table">
                            <tr>
                                <th><label for="first_name"><?php _e('First Name', 'hourly-room-booking'); ?></label></th>
                                <td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($booking->first_name); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="last_name"><?php _e('Last Name', 'hourly-room-booking'); ?></label></th>
                                <td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($booking->last_name); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                    <?php else: ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="first_name"><?php _e('First Name', 'hourly-room-booking'); ?></label></th>
                                <td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($booking->first_name); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="last_name"><?php _e('Last Name', 'hourly-room-booking'); ?></label></th>
                                <td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($booking->last_name); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="email"><?php _e('Email', 'hourly-room-booking'); ?></label></th>
                                <td><input type="email" name="email" id="email" value="<?php echo esc_attr($booking->email); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="phone"><?php _e('Phone', 'hourly-room-booking'); ?></label></th>
                                <td><input type="tel" name="phone" id="phone" value="<?php echo esc_attr($booking->phone); ?>" class="regular-text" pattern="[0-9+\-\s\(\)]+" title="<?php _e('Please enter a valid phone number', 'hourly-room-booking'); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="company"><?php _e('Company', 'hourly-room-booking'); ?></label></th>
                                <td><input type="text" name="company" id="company" value="<?php echo esc_attr($booking->company); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hrb-booking-actions">
                <input type="submit" name="submit" class="button button-primary" value="<?php _e('Update Booking', 'hourly-room-booking'); ?>">
                <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=view&id=' . $booking->id); ?>" class="button button-secondary"><?php _e('Cancel', 'hourly-room-booking'); ?></a>
            </div>
           
        </form>
    </div>

<?php elseif ($action === 'add'): ?>
    <!-- ADD BOOKING -->
    <div class="wrap hrb-admin-booking-add hrb-admin-bookings">
        <div class="hrb-page-header">
            <h1 class="wp-heading-inline">
                <?php _e('Add New Booking', 'hourly-room-booking'); ?>
            </h1>
            <a href="<?php echo admin_url('admin.php?page=hrb-bookings'); ?>" class="page-title-action">
                <?php _e('Back to Bookings', 'hourly-room-booking'); ?>
            </a>
        </div>
        
        <hr class="wp-header-end">
        
        <?php
        // Display all error messages from transient if they exist
        $error_messages = get_transient('hrb_admin_booking_errors');
        $single_error = get_transient('hrb_admin_booking_error');
        $has_validation_errors = false;
        if ($error_messages && is_array($error_messages)) {
            $has_validation_errors = true;
            echo '<div class="notice notice-error"><p><strong>' . __('Please fix the following errors:', 'hourly-room-booking') . '</strong></p><ul style="margin-left: 20px;">';
            foreach ($error_messages as $error_msg) {
                echo '<li>' . esc_html($error_msg) . '</li>';
            }
            echo '</ul></div>';
            // Don't delete transient yet - let JavaScript use it
        } elseif (!empty($single_error)) {
            $has_validation_errors = true;
            echo '<div class="notice notice-error"><p>' . esc_html($single_error) . '</p></div>';
            delete_transient('hrb_admin_booking_error');
        }
        ?>

        <form method="POST" class="hrb-add-booking-form">
            <?php wp_nonce_field('hrb_admin_action', 'hrb_nonce'); ?>
            <input type="hidden" name="action" value="add_booking">

            <div class="hrb-edit-grid">
                <div class="hrb-edit-section">
                    <h3><?php _e('Booking Details', 'hourly-room-booking'); ?></h3>

                    <table class="form-table">
                        <tr>
                            <th><label for="room_id"><?php _e('Room', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <select name="room_id" id="room_id">
                                    <option value=""><?php _e('Select a room', 'hourly-room-booking'); ?></option>
                                    <?php
                                    $all_rooms = $room_manager->get_all_rooms('all'); // Get all rooms including inactive
                                    foreach ($all_rooms as $room_option):
                                    ?>
                                        <option value="<?php echo $room_option->id; ?>">
                                            <?php echo esc_html($room_option->name); ?>
                                            <?php if ($room_option->is_active): ?>
                                                (<?php _e('Active', 'hourly-room-booking'); ?>)
                                            <?php else: ?>
                                                (<?php _e('Inactive', 'hourly-room-booking'); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php
                                    endforeach;
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <!-- Status field removed - will use default 'pending' like frontend -->
                        <tr>
                            <th><label for="payment_status"><?php _e('Payment Status', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <select name="payment_status" id="payment_status">
                                    <option value="pending"><?php _e('Pending', 'hourly-room-booking'); ?></option>
                                    <option value="completed"><?php _e('Completed', 'hourly-room-booking'); ?></option>
                                    <option value="cancelled"><?php _e('Cancelled', 'hourly-room-booking'); ?></option>
                                    <option value="refunded"><?php _e('Refunded', 'hourly-room-booking'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="payment_method"><?php _e('Payment Method', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <select name="payment_method" id="payment_method">
                                    <option value="onsite"><?php _e('On-site Payment', 'hourly-room-booking'); ?></option>
                                    <option value="paypal"><?php _e('PayPal', 'hourly-room-booking'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="booking_date"><?php _e('Date', 'hourly-room-booking'); ?></label></th>
                            <td><input type="date" name="booking_date" id="booking_date" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="duration"><?php _e('Duration', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <select name="duration" id="duration" class="regular-text">
                                    <option value=""><?php _e('Select duration', 'hourly-room-booking'); ?></option>
                                    <?php for ($hours = 2; $hours <= 12; $hours++): ?>
                                        <option value="<?php echo $hours; ?>" data-hours="<?php echo $hours; ?>">
                                            <?php echo sprintf(__('%d Hours', 'hourly-room-booking'), $hours); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                
                            </td>
                        </tr>
                        <tr>
                            <th><label for="time_slots"><?php _e('Available Time Slots', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <div id="time-slots-container">
                                    <div class="hrb-loading-message">
                                        <div class="hrb-loading-text"><?php _e('Please select a date and duration first', 'hourly-room-booking'); ?></div>
                                    </div>
                                </div>
                                <input type="hidden" name="start_time" id="add_start_time" value="">
                                <input type="hidden" name="end_time" id="add_end_time" value="">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="extra_people"><?php _e('Extra People', 'hourly-room-booking'); ?></label></th>
                            <td>
                                <input type="number" name="extra_people" id="extra_people" value="0" min="0" max="10" class="small-text">
                                <p class="description"><?php printf(__('Number of additional people beyond the base (%s per extra person, max 10)', 'hourly-room-booking'), hrb_format_amount(15)); ?></p>
                            </td>
                        </tr>
                               <tr>
                                   <th><label for="special_requests"><?php _e('Special Requests', 'hourly-room-booking'); ?></label></th>
                                   <td><textarea name="special_requests" id="special_requests" rows="4" class="large-text"></textarea></td>
                               </tr>
                               <tr>
                                   <th><label><?php _e('Available Extras', 'hourly-room-booking'); ?></label></th>
                                   <td>
                                       <div id="extras-container">
                                           <div class="hrb-loading-message">
                                               <div class="hrb-loading-text"><?php _e('Please select a room, date and duration first', 'hourly-room-booking'); ?></div>
                                           </div>
                                       </div>
                                   </td>
                               </tr>
                               <tr>
                                   <th><label for="admin_notes"><?php _e('Admin Notes', 'hourly-room-booking'); ?></label></th>
                                   <td>
                                       <textarea name="admin_notes" id="admin_notes" rows="4" class="large-text" placeholder="<?php _e('Enter any additional notes, extra payments, services, or other information about this booking...', 'hourly-room-booking'); ?>"></textarea>
                                       <p class="description"><?php _e('Use this field to add notes about extra payments, services, or any other information for internal use.', 'hourly-room-booking'); ?></p>
                                   </td>
                               </tr>
                               <tr>
                                   <th><label><?php _e('Price Summary', 'hourly-room-booking'); ?></label></th>
                                   <td>
                                       <div id="admin-booking-summary" class="hrb-admin-summary">
                                           <div class="hrb-loading-message">
                                               <div class="hrb-loading-text"><?php _e('Please select room, date, duration and time slot to see pricing', 'hourly-room-booking'); ?></div>
                                           </div>
                                       </div>
                                   </td>
                               </tr>
                           </table>
                       </div>

                <div class="hrb-edit-section">
                    <h3><?php _e('Customer Information', 'hourly-room-booking'); ?></h3>

                    <!-- Anonymous Booking Option -->
                    <div class="hrb-anonymous-option" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 8px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" id="hrb-anonymous-booking" name="is_anonymous" value="1" style="margin-right: 10px;">
                            <strong><?php _e('Anonymous Booking', 'hourly-room-booking'); ?></strong>
                            <span style="margin-left: 10px; color: #666; font-size: 13px;"><?php _e('(Kundendetails für Walk-in-Buchung überspringen))', 'hourly-room-booking'); ?></span>
                        </label>
                    </div>

                    <div class="hrb-customer-details">
                    <table class="form-table">
                            <tr class="hrb-name-field">
                                <th><label for="add_first_name"><?php _e('First Name', 'hourly-room-booking'); ?></label></th>
                                <td><input type="text" name="first_name" id="add_first_name" class="regular-text"></td>
                        </tr>
                            <tr class="hrb-name-field">
                                <th><label for="add_last_name"><?php _e('Last Name', 'hourly-room-booking'); ?></label></th>
                                <td><input type="text" name="last_name" id="add_last_name" class="regular-text"></td>
                        </tr>
                            <tr class="hrb-email-field">
                            <th><label for="email"><?php _e('Email', 'hourly-room-booking'); ?></label></th>
                                <td><input type="email" name="email" id="email" class="regular-text"></td>
                        </tr>
                            <tr class="hrb-phone-field">
                            <th><label for="phone"><?php _e('Phone', 'hourly-room-booking'); ?></label></th>
                            <td><input type="tel" name="phone" id="phone" class="regular-text" pattern="[0-9+\-\s\(\)]+" title="<?php _e('Please enter a valid phone number', 'hourly-room-booking'); ?>"></td>
                        </tr>
                            <tr class="hrb-company-field">
                            <th><label for="company"><?php _e('Company', 'hourly-room-booking'); ?></label></th>
                            <td><input type="text" name="company" id="company" class="regular-text"></td>
                        </tr>
                    </table>
                    </div>
                </div>
            </div>
            <div class="hrb-booking-actions">
                <input type="submit" name="submit" class="button button-primary" value="<?php _e('Create Booking', 'hourly-room-booking'); ?>">
                <a href="<?php echo admin_url('admin.php?page=hrb-bookings'); ?>" class="button button-secondary"><?php _e('Cancel', 'hourly-room-booking'); ?></a>
            </div>
        </form>

        <!-- Anonymous Booking Confirmation Modal -->
        <div id="hrb-anonymous-modal" class="hrb-modal" style="display: none;">
            <div class="hrb-modal-overlay"></div>
            <div class="hrb-modal-content">
                <div class="hrb-modal-header">
                    <h3><?php _e('Wichtiger Hinweis zur anonymen Buchung', 'hourly-room-booking'); ?></h3>
                    <button type="button" class="hrb-modal-close">&times;</button>
                </div>
                <div class="hrb-modal-body">
                    <p><strong><?php _e('Du hast eine 100 % anonyme Buchung gewählt.', 'hourly-room-booking'); ?></strong></p>
                    <p><?php _e('Das bedeutet:', 'hourly-room-booking'); ?></p>
                    <ul>
                        <li><?php _e('Du erhältst keine Buchungsbestätigung, Erinnerungen oder Änderungsmöglichkeiten per E-Mail.', 'hourly-room-booking'); ?></li>
                        <li><?php _e('Du kannst deine Buchung nicht online bearbeiten oder stornieren. Solltest du nach einer Buchung diese wieder stornieren wollen, ist dies nur mit der Buchungs-ID telefonisch oder per E-Mail an: <a href=mailto:info@wi-stundenzimmer.de> info@wi-stundenzimmer.de </a> möglich.', 'hourly-room-booking'); ?></li>
                        <li><?php _e('Die einmalig angezeigte Buchungs-ID dient als einziger Nachweis deiner Buchung.', 'hourly-room-booking'); ?></li>
                    </ul>
                    <p><?php _e('Bitte notiere oder speichere diese ID nach Abschluss deiner Buchung, da sie nicht erneut angezeigt oder per E-Mail gesendet wird.', 'hourly-room-booking'); ?></p>
                </div>
                <div class="hrb-modal-footer">
                    <button type="button" class="hrb-btn hrb-btn-secondary" id="hrb-anonymous-cancel">
                        <?php _e('Abbrechen', 'hourly-room-booking'); ?>
                    </button>
                    <button type="button" class="hrb-btn hrb-btn-primary" id="hrb-anonymous-continue">
                        <?php _e('Verstanden, anonym buchen', 'hourly-room-booking'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Restore form data from session if available
            <?php if (isset($_SESSION['hrb_admin_booking_form_data'])): ?>
            var formData = <?php echo json_encode($_SESSION['hrb_admin_booking_form_data']); ?>;
            
            // Pre-fill form fields
            if (formData.first_name) $('#add_first_name').val(formData.first_name);
            if (formData.last_name) $('#add_last_name').val(formData.last_name);
            if (formData.email) $('#email').val(formData.email);
            if (formData.phone) $('#phone').val(formData.phone);
            if (formData.company) $('#company').val(formData.company);
            if (formData.room_id) $('#room_id').val(formData.room_id);
            if (formData.booking_date) $('#booking_date').val(formData.booking_date);
            if (formData.duration) $('#duration').val(formData.duration);
            if (formData.extra_people) $('#extra_people').val(formData.extra_people);
            if (formData.special_requests) $('#special_requests').val(formData.special_requests);
            if (formData.admin_notes) $('#admin_notes').val(formData.admin_notes);
            if (formData.payment_status) $('#payment_status').val(formData.payment_status);
            if (formData.payment_method) $('#payment_method').val(formData.payment_method);
            
            // Clear session data
            <?php unset($_SESSION['hrb_admin_booking_form_data']); ?>
            
            // If there are validation errors, run validation to show field-level errors
            <?php if ($has_validation_errors): ?>
            // Run validation after form fields are pre-filled and time slots are loaded
            setTimeout(function() {
                validateAdminBookingForm();
            }, 500);
            <?php 
            // Delete transient after JavaScript has had a chance to use it
            delete_transient('hrb_admin_booking_errors');
            endif; ?>
            
            // Load time slots if we have the required data
            if (formData.room_id && formData.booking_date && formData.duration) {
                loadTimeSlots();
            }
            <?php endif; ?>
            
            // Load time slots when date or duration changes
            $('#booking_date, #duration').on('change', function() {
                loadTimeSlots();
                updatePriceSummary();
            });
            
            // Update price summary when relevant fields change
            $('#room_id, #duration, #extra_people, #payment_method').on('change', function() {
                updatePriceSummary();
            });
            
            // Update duration dropdown with prices when room is selected
            $('#room_id').on('change', function() {
                updateDurationPrices();
            });
            
            // Update price summary when extras are selected/deselected
            $(document).on('change', '#extras-container input[type="checkbox"]', function() {
                    /* removed debug log */
                updatePriceSummary();
            });
            
            function loadTimeSlots() {
                var roomId = $('#room_id').val();
                var date = $('#booking_date').val();
                var duration = $('#duration').val();
                var container = $('#time-slots-container');
                
                if (!roomId || !date || !duration) {
                    container.html('<div class="hrb-loading-message"><div class="hrb-loading-text"><?php _e('Please select a room, date and duration first', 'hourly-room-booking'); ?></div></div>');
                    return;
                }
                
                container.html('<div class="hrb-loading-message"><div class="hrb-loading-spinner"></div></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hrb_get_available_time_slots',
                        nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>',
                        room_id: roomId,
                        date: date,
                            duration: duration,
                            is_admin: true,
                            <?php if (isset($booking) && $booking->id): ?>
                            booking_id: '<?php echo esc_js($booking->id); ?>',
                            <?php endif; ?>
                    },
                    success: function(response) {
                        if (response.success && response.data.slots) {
                                displayTimeSlots(response.data.slots, 'time-slots-container');
                        } else {
                            container.html('<div class="hrb-no-slots"><?php _e('No available time slots for this date and duration', 'hourly-room-booking'); ?></div>');
                        }
                    },
                    error: function() {
                        container.html('<div class="hrb-error-message"><?php _e('Error loading time slots. Please try again.', 'hourly-room-booking'); ?></div>');
                    }
                });
            }
            

            
            // Price calculation and summary display
                window.updatePriceSummary = function() {
                var roomId = $('#room_id').val();
                var duration = $('#duration').val();
                var extraPeople = parseInt($('#extra_people').val()) || 0;
                var paymentMethod = $('#payment_method').val();
                
                if (!roomId || !duration) {
                    $('#admin-booking-summary').html('<div class="hrb-loading-message"><div class="hrb-loading-text"><?php _e('Please select room and duration to see pricing', 'hourly-room-booking'); ?></div></div>');
                    return;
                }
                
                // Get room pricing data via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hrb_get_room_pricing',
                        nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>',
                        room_id: roomId,
                        duration: duration
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            displayPriceSummary(response.data, extraPeople, paymentMethod);
                        } else {
                            $('#admin-booking-summary').html('<div class="hrb-error-message"><?php _e('Error loading pricing information', 'hourly-room-booking'); ?></div>');
                        }
                    },
                    error: function() {
                        $('#admin-booking-summary').html('<div class="hrb-error-message"><?php _e('Error loading pricing information', 'hourly-room-booking'); ?></div>');
                    }
                });
            }
            
            function displayPriceSummary(pricing, extraPeople, paymentMethod) {
                var currencySymbol = '<?php echo hrb_get_currency_symbol(); ?>';
                var currencyCode = '<?php echo HRB_Currency_Manager::getInstance()->get_currency_code(); ?>';
                
                // Calculate base price
                var basePrice = parseFloat(pricing.base_price) || 0;
                
                // Calculate extra people cost
                var extraPeoplePrice = 15.00; // €15 per extra person
                var additionalPeopleCost = extraPeople * extraPeoplePrice;
                
                // Get already paid amount from the booking payments
                var alreadyPaidTotal = 0;
                if (typeof currentBookingPayments !== 'undefined' && currentBookingPayments && Array.isArray(currentBookingPayments)) {
                    currentBookingPayments.forEach(function(payment) {
                        var paymentStatus = (payment.status || '').toLowerCase();
                        if (paymentStatus === 'completed' || paymentStatus === 'paid') {
                            alreadyPaidTotal += parseFloat(payment.amount) || 0;
                        }
                    });
                }
                
                // Get original booking extras (already paid for)
                var originalExtrasIds = [];
                var originalExtrasCost = 0;
                if (typeof currentBookingExtras !== 'undefined' && Array.isArray(currentBookingExtras)) {
                    currentBookingExtras.forEach(function(extra) {
                        originalExtrasIds.push(parseInt(extra.extra_id));
                        originalExtrasCost += parseFloat(extra.total_price || extra.price || 0);
                    });
                }
                
                // Calculate selected extras cost and separate new vs existing
                var allExtrasCost = 0;
                var newExtrasCost = 0;
                var existingExtrasCost = 0;
                var newExtrasDetails = [];
                var existingExtrasDetails = [];
                var checkedExtras = $('#extras-container input[type="checkbox"]:checked');
                
                checkedExtras.each(function() {
                    var extraId = parseInt($(this).val());
                    var price = parseFloat($(this).data('price')) || 0;
                    var name = $(this).data('name') || '';
                    allExtrasCost += price;
                    
                    var extraDetail = '<span class="hrb-extra-summary-name">' + name + '</span><span class="hrb-extra-summary-price"> ' + formatPrice(price, currencySymbol, currencyCode) + '</span>';
                    
                    // Check if this extra was in the original booking
                    if (originalExtrasIds.indexOf(extraId) !== -1) {
                        existingExtrasCost += price;
                        existingExtrasDetails.push(extraDetail);
                    } else {
                        newExtrasCost += price;
                        newExtrasDetails.push(extraDetail);
                    }
                });
                
                // Calculate subtotal
                var subtotal = basePrice + additionalPeopleCost + allExtrasCost;
                
                // Calculate PayPal fee (3% if PayPal selected)
                // IMPORTANT: Only apply fee to OUTSTANDING amount, not to already-paid amounts
                var paypalFee = 0;
                if (paymentMethod === 'paypal') {
                    // Calculate outstanding amount (what still needs to be paid)
                    // Outstanding = new services cost (new extras + any other new charges)
                    var outstandingAmount = newExtrasCost; // Only new extras need payment
                    
                    // Only apply PayPal fee to the outstanding amount
                    if (outstandingAmount > 0) {
                        paypalFee = outstandingAmount * 0.03;
                    }
                }
                
                // Calculate total
                var total = subtotal + paypalFee;
                
                // Build summary HTML
                var summaryHtml = '<div class="hrb-summary-content">';
                summaryHtml += '<div class="hrb-summary-item">';
                    summaryHtml += '<span>' + $('#room_id option:selected').text() + ' (' + $('#duration').val() + 'h)</span>';
                summaryHtml += '<span>' + formatPrice(basePrice, currencySymbol, currencyCode) + '</span>';
                summaryHtml += '</div>';
                
                if (extraPeople > 0) {
                    summaryHtml += '<div class="hrb-summary-item">';
                        summaryHtml += '<span><?php _e('Extra People', 'hourly-room-booking'); ?> (' + extraPeople + ')</span>';
                    summaryHtml += '<span>' + formatPrice(additionalPeopleCost, currencySymbol, currencyCode) + '</span>';
                    summaryHtml += '</div>';
                }
                
                // Show existing extras (already paid)
                if (existingExtrasDetails.length > 0) {
                    summaryHtml += '<div class="hrb-summary-section"><strong><?php _e('Extras', 'hourly-room-booking'); ?> (<?php _e('Already Paid', 'hourly-room-booking'); ?>)</strong></div>';
                    existingExtrasDetails.forEach(function(detail) {
                        summaryHtml += '<div class="hrb-summary-item hrb-summary-extra" style="opacity: 0.7;">' + detail + '</div>';
                    });
                }
                
                // Show new extras (being added now)
                if (newExtrasDetails.length > 0) {
                    summaryHtml += '<div class="hrb-summary-section" style="margin-top: 10px;"><strong><?php _e('New Services', 'hourly-room-booking'); ?> (<?php _e('To Be Paid', 'hourly-room-booking'); ?>)</strong></div>';
                    newExtrasDetails.forEach(function(detail) {
                        summaryHtml += '<div class="hrb-summary-item hrb-summary-extra" style="background: #fff3cd; border-left: 3px solid #ffc107;">' + detail + '</div>';
                    });
                } else if (allExtrasCost > 0 && existingExtrasDetails.length === 0) {
                    // All extras are new (no existing extras)
                    summaryHtml += '<div class="hrb-summary-section"><strong><?php _e('Extras', 'hourly-room-booking'); ?></strong></div>';
                    var allExtrasDetails = [];
                    checkedExtras.each(function() {
                        var price = parseFloat($(this).data('price')) || 0;
                        var name = $(this).data('name') || '';
                        allExtrasDetails.push('<span class="hrb-extra-summary-name">' + name + '</span><span class="hrb-extra-summary-price"> ' + formatPrice(price, currencySymbol, currencyCode) + '</span>');
                    });
                    allExtrasDetails.forEach(function(detail) {
                        summaryHtml += '<div class="hrb-summary-item hrb-summary-extra">' + detail + '</div>';
                    });
                }
                
                if (paypalFee > 0) {
                    summaryHtml += '<div class="hrb-summary-item hrb-summary-fee">';
                        summaryHtml += '<span><?php _e('PayPal Fee', 'hourly-room-booking'); ?> (3%)</span>';
                    summaryHtml += '<span>' + formatPrice(paypalFee, currencySymbol, currencyCode) + '</span>';
                    summaryHtml += '</div>';
                }
                
                // Show already paid section
                if (alreadyPaidTotal > 0) {
                    summaryHtml += '<div class="hrb-summary-section" style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #ddd;">';
                    summaryHtml += '<div class="hrb-summary-item" style="background: #e8f5e9; padding: 12px; border-radius: 4px;">';
                    summaryHtml += '<span><strong><?php _e('Already Paid', 'hourly-room-booking'); ?></strong></span>';
                    summaryHtml += '<span><strong>' + formatPrice(alreadyPaidTotal, currencySymbol, currencyCode) + '</strong></span>';
                    summaryHtml += '</div></div>';
                }
                
                // Show new amount to be paid (if any)
                if (newExtrasCost > 0 || paypalFee > 0) {
                    var newAmountToPay = newExtrasCost + paypalFee;
                    summaryHtml += '<div class="hrb-summary-item" style="background: #fff3cd; padding: 12px; margin-top: 5px; border-radius: 4px; border-left: 3px solid #ffc107;">';
                    summaryHtml += '<span><strong><?php _e('New Amount to Pay', 'hourly-room-booking'); ?></strong></span>';
                    summaryHtml += '<span><strong>' + formatPrice(newAmountToPay, currencySymbol, currencyCode) + '</strong></span>';
                    summaryHtml += '</div>';
                }
                
                summaryHtml += '<div class="hrb-summary-item hrb-summary-total">';
                    summaryHtml += '<span><strong><?php _e('Total', 'hourly-room-booking'); ?></strong></span>';
                summaryHtml += '<span><strong>' + formatPrice(total, currencySymbol, currencyCode) + '</strong></span>';
                summaryHtml += '</div>';
                summaryHtml += '</div>';
                
                $('#admin-booking-summary').html(summaryHtml);
                };
            
            function formatPrice(amount, currencySymbol, currencyCode) {
                var formattedAmount = parseFloat(amount).toFixed(2);
                
                // Currency positioning logic
                if (currencyCode === 'USD') {
                    // USD: symbol before amount
                    return currencySymbol + formattedAmount;
                } else {
                    // EUR and others: symbol after amount
                    return formattedAmount + ' ' + currencySymbol;
                }
            }
            
            function updateDurationPrices() {
                var roomId = $('#room_id').val();
                var durationSelect = $('#duration');
                
                if (!roomId) {
                    // Reset duration options to default
                    durationSelect.find('option').each(function() {
                        var hours = $(this).data('hours');
                        if (hours) {
                            $(this).text(hours + ' Hours');
                        }
                    });
                    return;
                }
                
                // Get room pricing data via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hrb_get_room_pricing_data',
                        nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>',
                        room_id: roomId
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            updateDurationOptions(response.data);
                        }
                    },
                    error: function() {
                            /* removed debug log */
                    }
                });
            }
            
            function updateDurationOptions(roomData) {
                var durationSelect = $('#duration');
                var currencySymbol = '<?php echo hrb_get_currency_symbol(); ?>';
                var currencyCode = '<?php echo HRB_Currency_Manager::getInstance()->get_currency_code(); ?>';
                
                durationSelect.find('option').each(function() {
                    var hours = $(this).data('hours');
                    if (hours) {
                        var price = calculateDurationPrice(hours, roomData);
                        var priceText = price > 0 ? ' - ' + formatPrice(price, currencySymbol, currencyCode) : '';
                        $(this).text(hours + ' Hours' + priceText);
                    }
                });
            }
            
            function calculateDurationPrice(duration, roomData) {
                var price = 0;
                var useRoomPrice = false;
                
                if (duration == 2 && roomData.price_2_hours > 0) {
                    price = roomData.price_2_hours;
                    useRoomPrice = true;
                } else if (duration == 3 && roomData.price_3_hours > 0) {
                    price = roomData.price_3_hours;
                    useRoomPrice = true;
                } else if (duration == 4 && roomData.price_4_hours > 0) {
                    price = roomData.price_4_hours;
                    useRoomPrice = true;
                } else if (duration > 4) {
                    // Calculate price for durations > 4 hours
                    var basePrice = 0;
                    var extraHours = duration - 4;
                    
                    // Use 4-hour price as base if available
                    if (roomData.price_4_hours > 0) {
                        basePrice = roomData.price_4_hours;
                        useRoomPrice = true;
                    } else if (roomData.price_3_hours > 0) {
                        basePrice = roomData.price_3_hours;
                        useRoomPrice = true;
                    } else if (roomData.price_2_hours > 0) {
                        basePrice = roomData.price_2_hours;
                        useRoomPrice = true;
                    }
                    
                    // Add extra hour pricing
                    if (roomData.price_extra_hour > 0) {
                        price = basePrice + (extraHours * roomData.price_extra_hour);
                        useRoomPrice = true;
                    }
                }
                
                // Fallback to global pricing if no room-specific pricing
                if (!useRoomPrice) {
                    var globalPrices = {
                        price_2_hours: <?php echo floatval(get_option('hrb_price_2_hours', 0)); ?>,
                        price_3_hours: <?php echo floatval(get_option('hrb_price_3_hours', 0)); ?>,
                        price_4_hours: <?php echo floatval(get_option('hrb_price_4_hours', 0)); ?>,
                        price_extra_hour: <?php echo floatval(get_option('hrb_price_extra_hour', 0)); ?>
                    };
                    
                    if (duration == 2 && globalPrices.price_2_hours > 0) {
                        price = globalPrices.price_2_hours;
                    } else if (duration == 3 && globalPrices.price_3_hours > 0) {
                        price = globalPrices.price_3_hours;
                    } else if (duration == 4 && globalPrices.price_4_hours > 0) {
                        price = globalPrices.price_4_hours;
                    } else if (duration > 4) {
                        var basePrice = 0;
                        var extraHours = duration - 4;
                        
                        if (globalPrices.price_4_hours > 0) {
                            basePrice = globalPrices.price_4_hours;
                        } else if (globalPrices.price_3_hours > 0) {
                            basePrice = globalPrices.price_3_hours;
                        } else if (globalPrices.price_2_hours > 0) {
                            basePrice = globalPrices.price_2_hours;
                        }
                        
                        if (globalPrices.price_extra_hour > 0) {
                            price = basePrice + (extraHours * globalPrices.price_extra_hour);
                        }
                    }
                }
                
                return price;
            }
            
                // Old form validation removed - now using custom German validation below
            });
        </script>

        <?php if ($action === 'edit'): ?>
            <script>
                jQuery(document).ready(function($) {
                    // Initialize edit form with current booking data
                    var currentBooking = {
                        room_id: <?php echo $booking->room_id; ?>,
                        booking_date: '<?php echo $booking->booking_date; ?>',
                        duration: <?php echo $booking->total_hours; ?>,
                        start_time: '<?php echo $booking->start_time; ?>',
                        end_time: '<?php echo $booking->end_time; ?>',
                        extra_people: <?php echo $booking->extra_people ?? 0; ?>,
                        special_requests: '<?php echo esc_js($booking->special_requests); ?>',
                        admin_notes: '<?php echo esc_js($booking->admin_notes); ?>',
                        booking_status: '<?php echo $booking->status; ?>',
                        payment_status: '<?php echo $booking->payment_status; ?>',
                        payment_method: '<?php echo $booking->payment_method; ?>'
                    };
                    
                    // Store original time slot for comparison (for confirmation dialog)
                    // Make them global so they're accessible to the click handler
                    window.originalStartTime = currentBooking.start_time;
                    window.originalEndTime = currentBooking.end_time;
                    
                    // Also store in data attributes on the hidden fields as backup
                    $('#start_time').data('original', currentBooking.start_time);
                    $('#end_time').data('original', currentBooking.end_time);

                    // Pre-fill form fields
                    $('#room_id').val(currentBooking.room_id);
                    $('#booking_date').val(currentBooking.booking_date);
                    $('#duration').val(currentBooking.duration);
                    $('#start_time').val(currentBooking.start_time);
                    $('#end_time').val(currentBooking.end_time);
                    $('#extra_people').val(currentBooking.extra_people);
                    $('#special_requests').val(currentBooking.special_requests);
                    $('#admin_notes').val(currentBooking.admin_notes);
                    $('#booking_status').val(currentBooking.booking_status);
                    $('#payment_status').val(currentBooking.payment_status);
                    $('#payment_method').val(currentBooking.payment_method);

                    // Load time slots and extras for current booking
                    if (currentBooking.room_id && currentBooking.booking_date && currentBooking.duration) {
                        loadTimeSlots();
                        // Load extras after a short delay to ensure time slots are loaded and hidden fields are set
                        setTimeout(function() {
                            // Ensure hidden fields are set to current booking's time slot
                            $('#start_time').val(currentBooking.start_time);
                            $('#end_time').val(currentBooking.end_time);
                            loadExtras();
                        }, 500);
                    }

                    // Load time slots when date or duration changes
                    $('#booking_date, #duration').on('change', function() {
                        loadTimeSlots();
                    });

                    // Load extras when room, date, or duration changes
                    $('#room_id, #booking_date, #duration').on('change', function() {
                        setTimeout(function() {
                            loadExtras();
                        }, 500);
                    });

                    // Function to load time slots
                    function loadTimeSlots() {
                var roomId = $('#room_id').val();
                        var date = $('#booking_date').val();
                var duration = $('#duration').val();
                        var container = $('#time-slots-container');

                        if (!roomId || !date || !duration) {
                            container.html('<div class="hrb-loading-message"><div class="hrb-loading-text"><?php _e('Please select a room, date and duration first', 'hourly-room-booking'); ?></div></div>');
                            return;
                        }

                        container.html('<div class="hrb-loading-message"><div class="hrb-loading-spinner"></div></div>');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'hrb_get_available_time_slots',
                                nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>',
                                room_id: roomId,
                                date: date,
                                duration: duration,
                                is_admin: true,
                                booking_id: '<?php echo $booking->id; ?>'
                            },
                            success: function(response) {
                                if (response.success && response.data.slots) {
                                    displayTimeSlots(response.data.slots, 'time-slots-container', currentBooking);
                                } else {
                                    container.html('<div class="hrb-no-slots"><?php _e('No time slots available for the selected date and duration.', 'hourly-room-booking'); ?></div>');
                                }
                            },
                            error: function(xhr, status, error) {
                                container.html('<div class="hrb-error-message"><?php _e('Error loading time slots. Please try again.', 'hourly-room-booking'); ?></div>');
                            }
                        });
                    }


                    // Function to display extras
        });
        </script>
        <?php endif; ?>
        
        <style>
        /* Time slots styling for admin */
        /* Phone validation error styling */
        input[type="tel"].error {
            border-color: #dc3232 !important;
            box-shadow: 0 0 2px rgba(220, 50, 50, 0.8) !important;
        }

        .hrb-time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .hrb-time-slot {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        .hrb-time-slot:hover {
            border-color: #0073aa;
            background: #f0f8ff;
        }
        
        .hrb-time-slot.selected {
            border-color: #0073aa;
            background: #0073aa;
            color: white;
        }
        
        .hrb-time-slot.available {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .hrb-time-slot.available:hover {
            border-color: #28a745;
            background: #c3e6cb;
        }
        
        .hrb-time-slot.unavailable {
            border-color: #dc3545;
            background: #f8d7da;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .hrb-time-slot.locked {
            border-color: #ff9800;
            background: #fff3e0;
            border-style: dashed;
        }
        
        .hrb-time-slot.locked:hover {
            border-color: #ff9800;
            background: #ffe0b2;
            border-style: solid;
        }
        
        .hrb-time-slot.locked.selected {
            border-color: #ff9800;
            background: #ff9800;
            color: white;
            border-style: solid;
        }
        
        .hrb-time-slot-time {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .hrb-time-slot-status {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .hrb-loading-message {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .hrb-loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #0073aa;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
        }
        
        .hrb-no-slots {
            text-align: center;
            padding: 20px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            color: #dc2626;
            margin: 10px 0;
        }
        
               .hrb-error-message {
                   text-align: center;
                   padding: 20px;
                   background: #fef2f2;
                   border: 1px solid #fecaca;
                   border-radius: 8px;
                   color: #dc2626;
                   margin: 10px 0;
               }
        </style>
    </div>

<?php else: ?>
    <!-- LIST BOOKINGS -->
    <div class="wrap hrb-admin-bookings">
        <div class="hrb-page-header">
            <h1 class="wp-heading-inline">
        <?php _e('Bookings', 'hourly-room-booking'); ?>
        <a href="<?php echo admin_url('admin.php?page=hrb-old-bookings'); ?>" class="page-title-action">
            <?php _e('View Old Bookings', 'hourly-room-booking'); ?>
        </a>
            </h1>
    
            <?php if (current_user_can('hrb_manage_bookings')): ?>
            <div class="hrb-page-actions">
                <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=add'); ?>" class="page-title-action">
                    <?php _e('Add New Booking', 'hourly-room-booking'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>


        <hr class="wp-header-end">

        <!-- Filters -->
        <div class="hrb-filters-section">
            <form method="GET" action="<?php echo admin_url('admin.php'); ?>">
                <input type="hidden" name="page" value="hrb-bookings">

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
                        <a href="<?php echo admin_url('admin.php?page=hrb-bookings'); ?>" class="button"><?php _e('Clear', 'hourly-room-booking'); ?></a>
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
                            printf(
                                __('Sorted by %s (%s)', 'hourly-room-booking'),
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
                                    <strong><a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=view&id=' . $booking['id']); ?>">
                                            #<?php echo esc_html($booking['booking_reference']); ?>
                                        </a></strong>
                                </td>
                                <td class="column-customer">
                                    <div class="hrb-customer-info">
                                        <?php echo hrb_display_customer_info($booking, 'full'); ?>
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
                                    if ($end <= $start) {
                                        $end->modify('+1 day');
                                    }
                                    $interval = $start->diff($end);
                                    echo $interval->format('%h') . __(' Stunden', 'hourly-room-booking');
                                    if ($interval->format('%i') > 0) {
                                        echo ' ' . $interval->format('%i') . __('m', 'hourly-room-booking');
                                    }
                                    ?>
                                </td>                               <td class="column-status">
                                    <?php echo $admin->get_status_badge($booking['status']); ?>
                                    <?php if (isset($booking['cancellation_fee']) && floatval($booking['cancellation_fee']) > 0): ?>
                                        <br>
                                        <span class="hrb-cancellation-fee-badge" title="<?php esc_attr_e('Cancellation fee payable on-site', 'hourly-room-booking'); ?>">
                                            <?php printf(__('Cancel fee: %s', 'hourly-room-booking'), hrb_format_amount($booking['cancellation_fee'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-payment">
                                    <div class="hrb-payment-info">
                                        <?php $actual_payment_status = $booking['actual_payment_status'] ?: $booking['payment_status']; ?>
                                        <span class="hrb-payment-status hrb-payment-<?php echo esc_attr($actual_payment_status); ?>">
                                            <?php
                                            echo esc_html(hrb_get_payment_status_label($actual_payment_status));
                                            ?>
                                        </span>
                                        <br>
                                        <small>
                                            <?php echo esc_html(hrb_get_payment_method_label($booking['payment_method'])); ?>
                                        </small>
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
                                        <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=view&id=' . $booking['id']); ?>"
                                            class="button button-small" title="<?php _e('View Details', 'hourly-room-booking'); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </a>

                                        <?php if (current_user_can('hrb_manage_bookings')): ?>
                                            <?php $invoice_url = get_invoice_download_url($booking['id']); ?>
                                            <?php if ($invoice_url): ?>
                                                <a href="<?php echo esc_url($invoice_url); ?>" target="_blank"
                                                    class="button button-small" title="<?php _e('Download Invoice', 'hourly-room-booking'); ?>">
                                                    <span class="dashicons dashicons-download"></span>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>

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

                                        <?php // Cancel booking button removed - cancellations should only be handled via phone or email 
                                        ?>

                                        <?php if (current_user_can('hrb_manage_bookings')): ?>
                                        <a href="<?php echo admin_url('admin.php?page=hrb-bookings&action=edit&id=' . $booking['id']); ?>"
                                            class="button button-secondary button-small" title="<?php _e('Edit', 'hourly-room-booking'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        
                                        <form method="POST" style="display: inline;" id="delete-booking-form-<?php echo $booking['id']; ?>">
                                            <?php wp_nonce_field('hrb_admin_action', 'hrb_nonce'); ?>
                                            <input type="hidden" name="action" value="delete_booking">
                                            <input type="hidden" name="id" value="<?php echo $booking['id']; ?>">
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
                            'base' => admin_url('admin.php?page=hrb-bookings&%_%'),
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
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        color: white;
        /* padding: 32px; */
        border-radius: 6px;
        /* margin-bottom: 32px; */
        box-shadow: 0 8px 32px rgba(220, 38, 38, 0.15);
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
        background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
        color: white;
        padding: 32px;
        border-radius: 6px;
        /* margin-bottom: 32px; */
        box-shadow: 0 8px 32px rgba(220, 38, 38, 0.15);
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
        border-radius: 4px;
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
        border-radius: 6px;
        padding: 24px;
        margin: 24px 0;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(220, 38, 38, 0.1);
    }

    .hrb-filters-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(166px, 1fr));
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
        border-radius: 4px;
        background: white;
        color: #374151;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .hrb-filter-item input:focus,
    .hrb-filter-item select:focus {
        border-color: #b91c1c;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
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
        border-radius: 4px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: 2px solid #b91c1c;
        background: linear-gradient(135deg, #b91c1c, #dc2626);
        color: white;
    }

    .hrb-filter-actions .button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
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
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(220, 38, 38, 0.1);
    }

    .hrb-bookings-table th {
        background: linear-gradient(135deg, #b91c1c, #dc2626);
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

    .hrb-cancellation-fee-badge {
        display: inline-block;
        margin-top: 4px;
        padding: 1px 6px;
        font-size: 11px;
        font-weight: 600;
        color: #fff;
        background: #b32d2e;
        border-radius: 3px;
        white-space: nowrap;
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
        color: #b91c1c;
        font-weight: 700;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .column-booking-id a:hover {
        color: #dc2626;
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
        border-radius: 6px;
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
        border-radius: 6px;
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
        border-radius: 6px;
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
        border-radius: 6px;
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
        border-radius: 6px;
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

    .hrb-payment-cancelled {
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
        background: linear-gradient(135deg, #b91c1c, #dc2626);
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
        border-radius: 6px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(220, 38, 38, 0.1);
    }

    .hrb-no-data-icon {
        font-size: 64px;
        color: #b91c1c;
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
        background: linear-gradient(135deg, #b91c1c, #dc2626);
        color: white;
        padding: 12px 24px;
        border-radius: 4px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        text-decoration: none;
    }

    .hrb-no-data .button-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
    }

    /* Enhanced Pagination */
    .hrb-pagination {
        text-align: center;
        margin: 32px 0;
    }

    .hrb-pagination .tablenav-pages {
        background: white;
        padding: 16px 24px;
        border-radius: 6px;
        display: inline-block;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(220, 38, 38, 0.1);
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
        color: #b91c1c;
        background: #f8fafc;
    }

    .hrb-pagination a:hover {
        background: linear-gradient(135deg, #b91c1c, #dc2626);
        color: white;
        transform: translateY(-1px);
    }

    .hrb-pagination .current {
        background: linear-gradient(135deg, #b91c1c, #dc2626);
        color: white;
        box-shadow: 0 2px 4px rgba(220, 38, 38, 0.3);
    }

    /* Enhanced Booking Details View */
    .hrb-booking-details {
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border: none;
        border-radius: 6px;
        padding: 0;
        margin: 32px 0;
        box-shadow: 0 8px 30px rgba(220, 38, 38, 0.15);
        overflow: hidden;
        border: 1px solid rgba(220, 38, 38, 0.1);
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

    .hrb-price-summary-fullwidth {
        border-right: none !important;
        margin-top: 20px;
        clear: both;
    }

    /* Price Summary Styles */
    .hrb-summary-content {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 4px;
        margin-top: 10px;
    }

    .hrb-summary-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #ddd;
    }

    .hrb-summary-section {
        margin-top: 15px;
        margin-bottom: 10px;
    }

    .hrb-summary-paid {
        display: flex;
        justify-content: space-between;
        padding: 15px;
        margin-top: 10px;
        border-top: 2px solid #28a745;
        background: #d4edda;
        border-radius: 4px;
        font-weight: bold;
        font-size: 1.1em;
    }
    .hrb-summary-paid-text.hrb-summary-paid-lbl {
        text-align: start;
    }
    .hrb-summary-paid-text {
        color: #155724;
        text-align: end;
    }

    .hrb-summary-paid-icon {
        color: #28a745;
        vertical-align: middle;
        margin-right: 5px;
    }

    .hrb-summary-paid-small {
        font-weight: normal;
        font-size: 0.85em;
    }

    .hrb-summary-pending {
        display: flex;
        justify-content: space-between;
        padding: 15px;
        margin-top: 10px;
        border-top: 2px solid #ffc107;
        background: #fff3cd;
        border-radius: 4px;
        font-weight: bold;
        font-size: 1.1em;
    }

    .hrb-summary-pending-text {
        color: #856404;
        text-align: end;
    }

    .hrb-summary-pending-small {
        font-weight: normal;
        font-size: 0.85em;
        color: #856404;
    }

    .hrb-summary-total {
        display: flex;
        justify-content: space-between;
        padding: 15px 0;
        margin-top: 10px;
        border-top: 2px solid #333;
        font-weight: bold;
        font-size: 1.2em;
    }

    .hrb-modification-badge {
        background: #fff3cd;
        padding: 2px 6px;
        border-radius: 3px;
        border-left: 2px solid #ffc107;
        color: #856404;
        font-size: 0.85em;
        margin-left: 5px;
    }

    .hrb-modification-text {
        color: #856404;
        font-size: 0.9em;
    }

    .hrb-summary-small-text {
        font-weight: normal;
        font-size: 0.85em;
    }

    .hrb-details-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, #b91c1c, #dc2626);
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
        background: linear-gradient(45deg, #b91c1c, #dc2626);
        box-shadow: 0 2px 4px rgba(220, 38, 38, 0.3);
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
        color: #b91c1c;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
    }

    .hrb-details-section td a:hover {
        color: #dc2626;
        text-decoration: underline;
    }

    .hrb-special-requests {
        background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
        padding: 24px;
        border-radius: 4px;
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
        color: #b91c1c;
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
        border-radius: 4px;
        font-weight: 600;
        padding: 12px 24px;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        text-decoration: none;
    }

    .hrb-booking-actions .button-primary {
        background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
        color: white;
    }

    .hrb-booking-actions .button-primary:hover {
        background: linear-gradient(135deg, #dc2626 0%, #dc2626 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.3);
        color: white;
    }

    .hrb-booking-actions .button-secondary {
        background: #fff;
        color: #374151;
        border: 2px solid #e5e7eb;
    }

    .hrb-booking-actions .button-secondary:hover {
        background: #f9fafb;
        border-color: #b91c1c;
        transform: translateY(-2px);
        color: #374151;
    }

    /* Enhanced Edit Form */
    .hrb-edit-booking-form {
        background: #fff;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 8px 30px rgba(220, 38, 38, 0.15);
        border: 1px solid rgba(220, 38, 38, 0.1);
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
        background: linear-gradient(90deg, #b91c1c, #dc2626);
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
        background: linear-gradient(45deg, #b91c1c, #dc2626);
        box-shadow: 0 2px 4px rgba(220, 38, 38, 0.3);
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
        border-radius: 4px;
        padding: 14px 18px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: #fff;
        font-weight: 500;
    }

    .hrb-edit-section input:focus,
    .hrb-edit-section select:focus,
    .hrb-edit-section textarea:focus {
        border-color: #b91c1c;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
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
        border-radius: 4px;
        font-weight: 600;
        padding: 14px 28px;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        margin-right: 16px;
        text-decoration: none;
    }

    .hrb-edit-section .submit .button-primary {
        background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
        color: white;
    }

    .hrb-edit-section .submit .button-primary:hover {
        background: linear-gradient(135deg, #dc2626 0%, #dc2626 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.3);
    }

    .hrb-edit-section .submit .button-secondary {
        background: #fff;
        color: #374151;
        border: 2px solid #e5e7eb;
    }

    .hrb-edit-section .submit .button-secondary:hover {
        background: #f9fafb;
        border-color: #b91c1c;
        transform: translateY(-2px);
        color: #374151;
    }

    .hrb-anonymous-booking-notice {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        border: 2px solid #f59e0b;
        border-radius: 4px;
        padding: 20px;
        margin: 20px 0;
    }

    .hrb-anonymous-booking-notice p {
        margin: 0 0 10px 0;
        color: #92400e;
    }

    .hrb-anonymous-booking-notice p:last-child {
        margin-bottom: 0;
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
        border-radius: 6px;
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
        /* background: linear-gradient(135deg, #b91c1c, #dc2626); */
        /* color: white; */
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
        border-radius: 4px;
        font-size: 14px;
        transition: all 0.3s ease;
        resize: vertical;
        min-height: 100px;
    }

    .hrb-modal-body textarea:focus {
        border-color: #b91c1c;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
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
        border-radius: 4px;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        text-decoration: none;
    }

    .hrb-modal-footer .button-primary {
        background: linear-gradient(135deg, #b91c1c, #dc2626);
        color: white;
        box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    .hrb-modal-footer .button-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
    }

    .hrb-modal-footer .button-secondary {
        background: #fff;
        color: #374151;
        border: 2px solid #e5e7eb;
    }

    .hrb-modal-footer .button-secondary:hover {
        background: #f9fafb;
        border-color: #b91c1c;
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

    /* Time slot styling - available for both edit and add pages */
    .hrb-time-slots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }
    
    .hrb-time-slot {
        padding: 12px;
        border: 2px solid #ddd;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #fff;
    }
    
    .hrb-time-slot:hover {
        border-color: #0073aa;
        background: #f0f8ff;
    }
    
    .hrb-time-slot.selected {
        border-color: #0073aa;
        background: #0073aa;
        color: white;
    }
    
    .hrb-time-slot.available {
        border-color: #28a745;
        background: #d4edda;
    }
    
    .hrb-time-slot.available:hover {
        border-color: #28a745;
        background: #c3e6cb;
    }
    
    .hrb-time-slot.unavailable {
        border-color: #dc3545;
        background: #f8d7da;
        cursor: not-allowed;
        opacity: 0.6;
    }
    
    .hrb-time-slot.locked {
        border-color: #ff9800;
        background: #fff3e0;
        border-style: dashed;
    }
    
    .hrb-time-slot.locked:hover {
        border-color: #ff9800;
        background: #ffe0b2;
        border-style: solid;
    }
    
    .hrb-time-slot.locked.selected {
        border-color: #ff9800;
        background: #ff9800;
        color: white;
        border-style: solid;
    }
    
    .hrb-time-slot-time {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 4px;
    }
    
    .hrb-time-slot-status {
        font-size: 12px;
        opacity: 0.8;
    }
</style>

<script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var currentBookingExtras = <?php echo json_encode($booking_extras ?? []); ?>;
    var currentBookingPayments = <?php 
        // Get all payments for this booking to calculate already-paid amounts
        $payments_data = [];
        if (isset($booking) && isset($booking->id)) {
            $payment_handler = HRB_Payment_Handler::getInstance();
            $all_booking_payments = $payment_handler->get_booking_payments($booking->id);
            foreach ($all_booking_payments as $payment) {
                $payments_data[] = [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'fees' => $payment->fees ?? 0,
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                    'is_additional_payment' => $payment->is_additional_payment ?? 0
                ];
            }
        }
        echo json_encode($payments_data);
    ?>;
    var currentBookingModifications = <?php 
        $modifications_data = [];
        if (isset($booking_modifications) && is_array($booking_modifications)) {
            foreach ($booking_modifications as $mod) {
                $modifications_data[] = [
                    'modification_type' => $mod->modification_type,
                    'original_value' => $mod->original_value,
                    'new_value' => $mod->new_value,
                    'additional_amount' => $mod->additional_amount,
                    'added_by_user_id' => $mod->added_by_user_id,
                    'added_by_display_name' => $mod->added_by_display_name ?? '',
                    'added_by_username' => $mod->added_by_username ?? ''
                ];
            }
        }
        echo json_encode($modifications_data);
    ?>;

    jQuery(document).ready(function($) {
        // Global function to display time slots - used everywhere
        window.displayTimeSlots = function(slots, containerId, currentBooking) {
            var container = $('#' + (containerId || 'time-slots-container'));
            var html = '';

            if (slots.length === 0) {
                html = '<div class="hrb-no-slots"><?php _e('No available time slots for this date and duration', 'hourly-room-booking'); ?></div>';
            } else {
                html = '<div class="hrb-time-slots-grid">';
                slots.forEach(function(slot) {
                    var isAvailable = slot.available;
                    var isLocked = slot.is_locked || false;
                    var lockType = slot.lock_type || null;
                    var statusClass = isAvailable ? 'available' : 'unavailable';
                    var statusText = isAvailable ? '<?php _e('Available', 'hourly-room-booking'); ?>' : '<?php _e('Unavailable', 'hourly-room-booking'); ?>';

                    // Check if this is the current booking's time slot
                    var isCurrentSlot = false;
                    if (currentBooking) {
                        isCurrentSlot = slot.is_current_booking || (slot.start_time === currentBooking.start_time && slot.end_time === currentBooking.end_time);
                    }
                    
                    // If it's the current booking slot, always show as available (even if normally unavailable)
                    // And mark it as selected, but don't add unavailable class if it's the current booking
                    if (isCurrentSlot) {
                        statusClass = 'available selected';
                        statusText = '<?php _e('Current Booking', 'hourly-room-booking'); ?>';
                    } else if (isLocked) {
                        var lockLabel = (lockType === 'master')
                            ? '<?php _e('Master Locked', 'hourly-room-booking'); ?>'
                            : '<?php _e('Room Locked', 'hourly-room-booking'); ?>';

                        if (isAvailable) {
                            // Locked but otherwise free – allow admin override, keep locked styling
                            statusClass = 'locked';
                            statusText = lockLabel;
                        } else {
                            // Locked AND already booked – show as unavailable so admin sees conflict
                            statusClass = 'locked unavailable';
                            statusText = lockLabel;
                        }
                    } else if (isAvailable) {
                        statusClass = 'available';
                    } else {
                        statusClass = 'unavailable';
                    }

                    html += '<div class="hrb-time-slot ' + statusClass + '" data-start-time="' + slot.start_time + '" data-end-time="' + slot.end_time + '" data-available="' + (isAvailable ? 'true' : 'false') + '" data-locked="' + (isLocked ? 'true' : 'false') + '">';
                    html += '<div class="hrb-time-slot-time">' + slot.label + '</div>';
                    html += '<div class="hrb-time-slot-status">' + statusText + '</div>';
                    html += '</div>';
                });
                html += '</div>';
            }
            container.html(html);
            
            // Store original booking times in container data for confirmation check
            if (currentBooking && currentBooking.original_start_time && currentBooking.original_end_time) {
                container.data('original-start-time', currentBooking.original_start_time);
                container.data('original-end-time', currentBooking.original_end_time);
            } else if (currentBooking && currentBooking.start_time && currentBooking.end_time && $('#start_time').length > 0) {
                // If it's edit form but original times not passed, use currentBooking times as original
                container.data('original-start-time', currentBooking.start_time);
                container.data('original-end-time', currentBooking.end_time);
            }
            
            // Bind time slot selection - allow available slots and locked slots (admin can book locked rooms)
            container.find('.hrb-time-slot.available, .hrb-time-slot.locked').on('click', function(e) {
                if ($(this).hasClass('unavailable')) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                var selectedSlot = $(this);
                var newStartTime = selectedSlot.data('start-time') || selectedSlot.attr('data-start-time');
                var newEndTime = selectedSlot.data('end-time') || selectedSlot.attr('data-end-time');

                // Check if confirmation is needed (for edit booking form only)
                var originalStartTime = container.data('original-start-time');
                var originalEndTime = container.data('original-end-time');
                
                if ($('#start_time').length > 0 && originalStartTime && originalEndTime) {
                    // This is edit booking form - check if time slot is different from original
                    if (newStartTime !== originalStartTime || newEndTime !== originalEndTime) {
                        // Stop event propagation to prevent default selection
                        e.stopPropagation();
                        e.preventDefault();
                        
                        // Show custom confirmation dialog
                        var confirmMessage = '<?php _e('Are you sure you want to change the time slot?', 'hourly-room-booking'); ?>';
                        var originalTimeText = originalStartTime + ' - ' + originalEndTime;
                        var newTimeText = newStartTime + ' - ' + newEndTime;
                        
                        
                        
                        window.hrbShowConfirmDialog(
                            confirmMessage,
                            originalTimeText,
                            newTimeText,
                            function() {
                                // User confirmed - proceed with selection
                                container.find('.hrb-time-slot').removeClass('selected');
                                selectedSlot.addClass('selected');
                                
                                // Update hidden fields
                                $('#start_time').val(newStartTime);
                                $('#end_time').val(newEndTime);
                                
                                // Load extras when time slot is selected
                                if (typeof window.loadExtras === 'function') {
                                    window.loadExtras();
                                }
                                
                                // Update price summary
                                if (typeof window.updatePriceSummary === 'function') {
                                    window.updatePriceSummary();
                                }
                            },
                            function() {
                                // User cancelled - do nothing
                            }
                        );
                        
                        return false;
                    }
                }
                
                // If we get here, either it's not edit form OR time slot matches original - proceed with normal selection
                // Remove previous selection
                container.find('.hrb-time-slot').removeClass('selected');
                selectedSlot.addClass('selected');

                // Update hidden fields - check which form we're in
                if ($('#add_start_time').length > 0) {
                    // Add booking form
                    $('#add_start_time').val(newStartTime);
                    $('#add_end_time').val(newEndTime);
                } else {
                    // Edit booking form
                    $('#start_time').val(newStartTime);
                    $('#end_time').val(newEndTime);
                }

                // Load extras when time slot is selected
                if (typeof window.loadExtras === 'function') {
                    window.loadExtras();
                }
                
                // Update price summary
                if (typeof window.updatePriceSummary === 'function') {
                    window.updatePriceSummary();
                }
            });
        };

        // Global function to load extras - handles all cases
        window.loadExtras = function(roomId, bookingDate, startTime, endTime) {
            // If no parameters provided, get values from form fields
            if (typeof roomId === 'undefined') {
                roomId = $('#room_id').val();
                bookingDate = $('#booking_date').val();

                // Check which form we're in
                if ($('#add_start_time').length > 0) {
                    // Add booking form
                    startTime = $('#add_start_time').val();
                    endTime = $('#add_end_time').val();
                } else {
                    // Edit booking form
                    startTime = $('#start_time').val();
                    endTime = $('#end_time').val();
                }
            }

            var container = $('#extras-container');

            if (!roomId || !bookingDate || !startTime || !endTime) {
                container.html('<div class="hrb-loading-message"><div class="hrb-loading-text"><?php _e('Please select a room, date and time slot first', 'hourly-room-booking'); ?></div></div>');
                return;
            }

            container.html('<div class="hrb-loading-message"><div class="hrb-loading-spinner"></div></div>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hrb_get_available_extras',
                    nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>',
                    room_id: roomId,
                    booking_date: bookingDate,
                    start_time: startTime,
                    end_time: endTime,
                    booking_id: '<?php  if(isset($booking) && is_object($booking)){ echo $booking->id; } else { echo ''; } ?>'
                },
                success: function(response) {
                    if (response.success && response.data.extras) {
                        displayExtras(response.data.extras, 'extras-container', currentBookingExtras);
                    } else {
                        container.html('<div class="hrb-no-extras"><?php _e('No extras available for the selected date and time.', 'hourly-room-booking'); ?></div>');
                    }
                },
                error: function() {
                    container.html('<div class="hrb-loading-message"><div class="hrb-loading-text"><?php _e('Error loading extras', 'hourly-room-booking'); ?></div></div>');
                }
            });
        };

        // Global function to display extras - used everywhere
        window.displayExtras = function(extras, containerId, currentBookingExtras) {
            var container = $('#' + (containerId || 'extras-container'));
            var html = '';

            if (extras.length === 0) {
                html = '<div class="hrb-no-extras"><?php _e('No extras available for the selected date and time.', 'hourly-room-booking'); ?></div>';
            } else {
                html = '<div class="hrb-extras-list">';
                extras.forEach(function(extra) {
                    var maxQuantity = extra.available_quantity || 999;
                    var stockInfo = extra.track_stock ?
                        (maxQuantity > 0 ? ' ' + maxQuantity + ' <?php _e('Available', 'hourly-room-booking'); ?>' : ' (Out of Stock)') : '';

                    if (maxQuantity > 0) {
                        // Check if this extra is already selected for the current booking
                        var isSelected = false;

                        if (currentBookingExtras && currentBookingExtras.length > 0) {
                            var bookingExtra = currentBookingExtras.find(function(be) {
                                return (be.extra_id == extra.id) || (be.id == extra.id);
                            });
                            if (bookingExtra) {
                                isSelected = true;
                            }
                        } else if (extra.is_selected) {
                            isSelected = true;
                        }

                        var checkedAttr = isSelected ? ' checked' : '';

                        html += '<div class="hrb-extra-item' + (isSelected ? ' hrb-extra-selected' : '') + '">';
                        html += '<input type="checkbox" name="extras[]" value="' + extra.id + '" data-price="' + extra.price + '" data-name="' + extra.name + '"' + checkedAttr + ' style="display: none;">';
                        html += '<div class="hrb-extra-content">';
                        html += '<div class="hrb-extra-header">';
                        html += '<div class="hrb-extra-icon">';
                        html += extra.image_url ? '<img src="' + extra.image_url + '" alt="' + extra.name + '">' : '⭐';
                        html += '</div>';
                        html += '<div class="hrb-extra-title"><span>' + extra.name+'</span>';
                        // Show status label if is_active is provided
                        if (typeof extra.is_active !== 'undefined') {
                            html += extra.is_active ? ' <span style="color: #28a745; font-size: 0.85em;">(<?php _e('Active', 'hourly-room-booking'); ?>)</span>' : ' <span style="color: #dc3545; font-size: 0.85em;">(<?php _e('Inactive', 'hourly-room-booking'); ?>)</span>';
                        }
                        // Show stock count for the selected time slot
                        if (stockInfo) {
                            html += ' <span class="stockInfo" style="color: #6c757d; font-size: 0.85em;">' + stockInfo + '</span>';
                        }
                        html += '</div>';
                        html += '<div class="hrb-extra-price">+<?php echo hrb_get_currency_symbol(); ?>' + parseFloat(extra.price).toFixed(2) + '</div>';
                        html += '</div>';
                        if (extra.description) {
                            html += '<div class="hrb-extra-description">' + extra.description + '</div>';
                        }
                        html += '</div>';
                        html += '</div>';
                    }
                });
                html += '</div>';
            }

            container.html(html);

            // Add click functionality to entire extra item
            container.find('.hrb-extra-item').on('click', function(e) {
                if (e.target.type !== 'checkbox') {
                    var checkbox = $(this).find('input[type="checkbox"]');
                    checkbox.prop('checked', !checkbox.prop('checked'));

                    // Toggle selected class
                    $(this).toggleClass('hrb-extra-selected', checkbox.prop('checked'));

                    // Trigger price update when extra is clicked
                    if (typeof window.updatePriceSummary === 'function') {
                        window.updatePriceSummary();
                    }
                }
            });

            // Add change event for checkboxes
            container.find('input[type="checkbox"]').on('change', function() {
                if (typeof window.updatePriceSummary === 'function') {
                    window.updatePriceSummary();
                }
            });

            // Update price summary when extras change
            if (typeof window.updatePriceSummary === 'function') {
                window.updatePriceSummary();
            }
        };

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

        // Phone number validation
        function validatePhoneNumber(phone) {
            // Remove all non-digit characters for validation
            const cleanPhone = phone.replace(/[^\d]/g, '');
            // Check if it has at least 7 digits (minimum valid phone number)
            return cleanPhone.length >= 7 && cleanPhone.length <= 15;
        }

        // Add phone validation to all phone inputs
        $('input[type="tel"]').on('input', function() {
            const phone = $(this).val();
            const isValid = validatePhoneNumber(phone);
            
            if (phone && !isValid) {
                $(this).addClass('error');
                $(this).attr('title', '<?php _e('Please enter a valid phone number (7-15 digits)', 'hourly-room-booking'); ?>');
            } else {
                $(this).removeClass('error');
                $(this).attr('title', '');
            }
        });

        // Phone validation for edit booking form (add form validation is handled below)
        $('.hrb-edit-booking-form').on('submit', function(e) {
            const phoneInput = $(this).find('input[type="tel"]');
            if (phoneInput.length && phoneInput.val()) {
                if (!validatePhoneNumber(phoneInput.val())) {
                    e.preventDefault();
                    alert('<?php _e('Please enter a valid phone number', 'hourly-room-booking'); ?>');
                    phoneInput.focus();
                    return false;
                }
            }
        });

        // Anonymous booking functionality
        function handleAnonymousBookingChange() {
            const isAnonymous = $('#hrb-anonymous-booking').is(':checked');
            const customerDetails = $('.hrb-customer-details');

            if (isAnonymous) {
                // Show modal
                $('#hrb-anonymous-modal').show().addClass('show');
            } else {
                // Show customer details and reset requirements
                customerDetails.show();
                setAnonymousFieldRequirements(false);
            }
        }

        function setAnonymousFieldRequirements(isAnonymous) {
            const firstNameField = $('#add_first_name');
            const lastNameField = $('#add_last_name');
            const emailField = $('.hrb-email-field input');
            const phoneField = $('.hrb-phone-field input');

            if (isAnonymous) {
                // Only first name is required for anonymous bookings
                firstNameField.prop('required', false).prop('disabled', false);
                lastNameField.prop('required', false).prop('disabled', false);
                // Email field is optional for anonymous bookings
                emailField.prop('required', false).prop('disabled', false);
                phoneField.prop('required', false).prop('disabled', false);
                // Hide email, phone, and company fields for anonymous bookings
                $('.hrb-email-field, .hrb-phone-field, .hrb-company-field').hide();
            } else {
                // Both names and email required for regular bookings
                firstNameField.prop('required', false).prop('disabled', false);
                lastNameField.prop('required', false).prop('disabled', false);
                emailField.prop('required', false).prop('disabled', false);
                phoneField.prop('required', false).prop('disabled', false);
                // Show all fields for regular bookings
                $('.hrb-email-field, .hrb-phone-field, .hrb-company-field').show();
            }
        }

        // Handle anonymous booking checkbox change
        $('#hrb-anonymous-booking').on('change', function() {
            handleAnonymousBookingChange();
        });

        // Handle anonymous modal continue
        $('#hrb-anonymous-continue').on('click', function() {
            $('#hrb-anonymous-modal').hide().removeClass('show');
            // Keep customer details visible but set anonymous requirements
            $('.hrb-customer-details').show();
            setAnonymousFieldRequirements(true);
        });

        // Handle anonymous modal cancel
        $('#hrb-anonymous-cancel').on('click', function() {
            $('#hrb-anonymous-modal').hide().removeClass('show');
            $('#hrb-anonymous-booking').prop('checked', false);
            handleAnonymousBookingChange();
        });

        // Handle modal close
        $('.hrb-modal-close, .hrb-modal-overlay').on('click', function() {
            $('#hrb-anonymous-modal').hide().removeClass('show');
            // Reset anonymous booking state when modal is closed
            $('#hrb-anonymous-booking').prop('checked', false);
            handleAnonymousBookingChange();
        });

        // Handle form submission for add booking form
        $('.hrb-add-booking-form').on('submit', function(e) {
            const form = this; // Store reference to the form DOM element
            const isAnonymous = $('#hrb-anonymous-booking').is(':checked');

            // Check if form is already being submitted (prevent double submission)
            if ($(form).data('submitting')) {
                e.preventDefault();
                return false;
            }

            // Run custom validation with German messages
            if (!validateAdminBookingForm()) {
                // Validation failed - prevent submission and show errors
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

            // Validate phone number if provided
            const phoneInput = $(this).find('input[type="tel"]');
            if (phoneInput.length && phoneInput.val()) {
                if (!validatePhoneNumber(phoneInput.val())) {
                    e.preventDefault();
                    e.stopPropagation();
                    showAdminValidationError('<?php _e('Please enter a valid phone number (7-15 digits)', 'hourly-room-booking'); ?>', phoneInput);
                    phoneInput.focus();
                    return false;
                }
            }

            // If we get here, all validations passed - allow form to submit normally
            // Mark form as submitting to prevent double submission
            $(form).data('submitting', true);
            
            // Prepare form for submission
            if (isAnonymous) {
                // For anonymous bookings, disable HTML validation requirements
                $('.hrb-email-field input').prop('required', false);
                $('.hrb-name-field input').prop('required', false);
                // Hide email, phone, and company fields for anonymous bookings
                $('.hrb-email-field, .hrb-phone-field, .hrb-company-field').hide();
            } else {
                // Show all fields for regular bookings
                $('.hrb-email-field, .hrb-phone-field, .hrb-company-field').show();
            }

            // Allow form to submit naturally - don't prevent default
            // The form will submit normally now
            return true;
        });

        // Custom validation function with German messages
        function validateAdminBookingForm() {
            let isValid = true;

            // Only validate if the add booking form exists
            if ($('.hrb-add-booking-form').length === 0) {
                return true; // Form doesn't exist, skip validation
            }

            // Clear previous errors
            $('.hrb-admin-validation-error').remove();

            // Validate room selection - only check if field exists in add form
            const roomIdField = $('.hrb-add-booking-form').find('#room_id');
            if (roomIdField.length === 0) {
                return true; // Field doesn't exist, skip validation
            }
            
            const roomId = roomIdField.val();
            if (!roomId) {
                showAdminValidationError('<?php _e('Bitte wählen Sie einen Raum aus', 'hourly-room-booking'); ?>', roomIdField);
                isValid = false;
            }

            // Validate booking date - only check if field exists in add form
            const bookingDateField = $('.hrb-add-booking-form').find('#booking_date');
            if (bookingDateField.length > 0) {
                const bookingDate = bookingDateField.val();
                if (!bookingDate) {
                    showAdminValidationError('<?php _e('Bitte wählen Sie ein Buchungsdatum aus', 'hourly-room-booking'); ?>', bookingDateField);
                    isValid = false;
                }
            }

            // Validate duration - only check if field exists in add form
            const durationField = $('.hrb-add-booking-form').find('#duration');
            if (durationField.length > 0) {
                const duration = durationField.val();
                if (!duration) {
                    showAdminValidationError('<?php _e('Bitte wählen Sie eine Dauer aus', 'hourly-room-booking'); ?>', durationField);
                    isValid = false;
                }
            }

            // Validate time slots - check both add and edit forms
            const startTime = $('#add_start_time').val() || $('#start_time').val();
            if (!startTime) {
                const timeField = $('#add_start_time').length > 0 ? $('#add_start_time') : $('#start_time');
                showAdminValidationError('<?php _e('Bitte wählen Sie eine Zeitspanne aus', 'hourly-room-booking'); ?>', timeField);
                isValid = false;
            }

            // Validate customer details - check anonymous booking status
            const isAnonymous = $('#hrb-anonymous-booking').is(':checked');

            // Validate first name (always required for both anonymous and non-anonymous)
            const firstNameField = $('.hrb-add-booking-form').find('#add_first_name');
            if (firstNameField.length > 0) {
                const firstName = firstNameField.val();
                if (!firstName || !firstName.trim()) {
                    showAdminValidationError('<?php _e('Bitte geben Sie einen Namen ein', 'hourly-room-booking'); ?>', firstNameField);
                    isValid = false;
                }
            }

            if (!isAnonymous) {
                // For non-anonymous bookings, validate all required fields
                
                // Validate last name (required for non-anonymous)
                const lastNameField = $('.hrb-add-booking-form').find('#add_last_name');
                if (lastNameField.length > 0) {
                    const lastName = lastNameField.val();
                    if (!lastName || !lastName.trim()) {
                        showAdminValidationError('<?php _e('Bitte geben Sie einen Nachnamen ein', 'hourly-room-booking'); ?>', lastNameField);
                        isValid = false;
                    }
                }

                // Validate email (required for non-anonymous)
                const emailField = $('.hrb-add-booking-form').find('#email');
                if (emailField.length > 0) {
                    const email = emailField.val();
                    if (!email || !email.trim()) {
                        showAdminValidationError('<?php _e('Bitte geben Sie Ihre E-Mail-Adresse ein', 'hourly-room-booking'); ?>', emailField);
                        isValid = false;
                    } else if (!isValidEmail(email)) {
                        showAdminValidationError('<?php _e('Bitte geben Sie eine gültige E-Mail-Adresse ein', 'hourly-room-booking'); ?>', emailField);
                        isValid = false;
                    }
                }
            } else {
                // For anonymous bookings, email is optional but if provided, must be valid
                const emailField = $('.hrb-add-booking-form').find('#email');
                if (emailField.length > 0) {
                    const email = emailField.val();
                    if (email && email.trim() && !isValidEmail(email)) {
                        showAdminValidationError('<?php _e('Bitte geben Sie eine gültige E-Mail-Adresse ein', 'hourly-room-booking'); ?>', emailField);
                        isValid = false;
                    }
                }
            }

            return isValid;
        }

        // Show validation error function
        function showAdminValidationError(message, field) {
            // Remove existing error for this field
            field.siblings('.hrb-admin-validation-error').remove();

            // Create error message
            const errorHtml = '<div class="hrb-admin-validation-error" style="color: #d63638; font-size: 12px; margin-top: 5px;">' + message + '</div>';
            field.after(errorHtml);

            // Focus on the field
            field.focus();
        }

        // Initialize edit booking form with existing data
        function initializeEditBookingForm() {
            const roomId = $('#room_id').val();
            const bookingDate = $('#booking_date').val();
            const startTime = $('#start_time').val();
            const endTime = $('#end_time').val();
            const duration = $('#duration').val();

            if (roomId && bookingDate && duration && startTime && endTime) {
                // Load time slots
                loadTimeSlots(roomId, bookingDate);

                // Load extras
                window.loadExtras(roomId, bookingDate, startTime, endTime);

                // After a delay, select the current time slot and update price summary
                setTimeout(function() {
                    selectCurrentTimeSlot(startTime, endTime);

                    // Trigger change events to ensure all handlers are called
                    $('#room_id').trigger('change');
                    $('#duration').trigger('change');
                    $('#extra_people').trigger('change');
                    $('#payment_method').trigger('change');

                    // Update price summary with current booking data
                    if (typeof window.updatePriceSummary === 'function') {
                        window.updatePriceSummary();
                    }
                }, 1000);
            }
        }

        // Load time slots for edit form
        function loadTimeSlots(roomId, bookingDate) {
            if (!roomId || !bookingDate) return;

            const duration = $('#duration').val() || '2'; // Default to 2 hours if not set

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hrb_get_available_time_slots',
                    nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>',
                    room_id: roomId,
                    date: bookingDate,
                    duration: duration,
                    is_admin: true,
                    booking_id: '<?php if(isset($booking) && is_object($booking)){ echo $booking->id; } else { echo ''; } ?>'
                },
                success: function(response) {
                    if (response.success && response.data.slots) {
                        displayTimeSlots(response.data.slots, 'time-slots-container', {
                            start_time: $('#start_time').val(),
                            end_time: $('#end_time').val(),
                            original_start_time: window.originalStartTime || $('#start_time').data('original') || $('#start_time').val(),
                            original_end_time: window.originalEndTime || $('#end_time').data('original') || $('#end_time').val()
                        });
                    } else {
                        $('#time-slots-container').html('<div class="hrb-loading-message"><div class="hrb-loading-text">' + (response.data.message || '<?php _e('No time slots available', 'hourly-room-booking'); ?>') + '</div></div>');
                    }
                },
                error: function() {
                    $('#time-slots-container').html('<div class="hrb-loading-message"><div class="hrb-loading-text"><?php _e('Error loading time slots', 'hourly-room-booking'); ?></div></div>');
                }
            });
        }



        // Select current time slot
        function selectCurrentTimeSlot(startTime, endTime) {
            $('.hrb-time-slot').each(function() {
                // Use data-start-time and data-end-time to match the display function
                const slotStart = $(this).data('start-time') || $(this).attr('data-start-time');
                const slotEnd = $(this).data('end-time') || $(this).attr('data-end-time');

                if (slotStart === startTime && slotEnd === endTime) {
                    // Remove selected from all slots first
                    $('.hrb-time-slot').removeClass('selected');
                    // Add selected to this slot and ensure it's visible
                    $(this).addClass('selected').removeClass('unavailable');
                    $('#start_time').val(startTime);
                    $('#end_time').val(endTime);
                }
            });
        }

        // Initialize form when page loads
        initializeEditBookingForm();

        // Time slot selection handler - with confirmation for edit booking (allow available and locked slots)
        $(document).on('click', '.hrb-time-slot.available, .hrb-time-slot.locked', function() {
            // Only show confirmation on edit booking page (check if edit form exists)
            if ($('#start_time').length > 0 && typeof window.originalStartTime !== 'undefined' && typeof window.originalEndTime !== 'undefined') {
                // This is edit booking form
                const clickedSlot = $(this);
                const newStartTime = clickedSlot.data('start-time') || clickedSlot.attr('data-start-time');
                const newEndTime = clickedSlot.data('end-time') || clickedSlot.attr('data-end-time');
                
                // Get current selected time slot
                const currentStartTime = $('#start_time').val();
                const currentEndTime = $('#end_time').val();
                
                // Check if this is a different time slot than the original booking time
                const isDifferentFromOriginal = (newStartTime !== window.originalStartTime || newEndTime !== window.originalEndTime);
                
                if (isDifferentFromOriginal) {
                    // Show confirmation dialog
                    const confirmed = confirm('<?php _e('Are you sure you want to change the time slot?', 'hourly-room-booking'); ?>\n\n' +
                        '<?php _e('Original time:', 'hourly-room-booking'); ?> ' + window.originalStartTime + ' - ' + window.originalEndTime + '\n' +
                        '<?php _e('New time:', 'hourly-room-booking'); ?> ' + newStartTime + ' - ' + newEndTime);
                    
                    if (!confirmed) {
                        // User cancelled - don't change anything
                        return false;
                    }
                }
            }
            
            // Proceed with selection (for both add and edit forms)
            $('.hrb-time-slot').removeClass('selected');
            $(this).addClass('selected');

            // Use data-start-time and data-end-time to match the display function
            const startTime = $(this).data('start-time') || $(this).attr('data-start-time');
            const endTime = $(this).data('end-time') || $(this).attr('data-end-time');

            if (startTime && endTime) {
                // Check which form we're in
                if ($('#add_start_time').length > 0) {
                    // Add booking form
                    $('#add_start_time').val(startTime);
                    $('#add_end_time').val(endTime);
                } else {
                    // Edit booking form
                    $('#start_time').val(startTime);
                    $('#end_time').val(endTime);
                }
                
                // Load extras when time slot is selected
                if (typeof window.loadExtras === 'function') {
                    window.loadExtras();
                }
                
                // Update price summary
                if (typeof window.updatePriceSummary === 'function') {
                    window.updatePriceSummary();
                }
            }
        });

        // Reload time slots and extras when room, date, or duration changes
        $('#room_id, #booking_date, #duration').on('change', function() {
            const roomId = $('#room_id').val();
            const bookingDate = $('#booking_date').val();
            const duration = $('#duration').val();
            const startTime = $('#start_time').val();
            const endTime = $('#end_time').val();

            if (roomId && bookingDate && duration) {
                loadTimeSlots(roomId, bookingDate);
                if (startTime && endTime) {
                    window.loadExtras(roomId, bookingDate, startTime, endTime);
                }
            }
        });

        // Update price summary when relevant fields change (for edit form)
        $('#room_id, #duration, #extra_people, #payment_method').on('change', function() {
            if (typeof window.updatePriceSummary === 'function') {
                window.updatePriceSummary();
            }
        });

        // Update price summary when extras are selected/deselected (for edit form)
        $(document).on('change', '#extras-container input[type="checkbox"]', function() {
            if (typeof window.updatePriceSummary === 'function') {
                window.updatePriceSummary();
            }
        });

        // Email validation function
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Global Price calculation and summary display function
        window.updatePriceSummary = function() {
            var roomId = $('#room_id').val();
            var duration = $('#duration').val();
            var extraPeople = parseInt($('#extra_people').val()) || 0;
            var paymentMethod = $('#payment_method').val();

            if (!roomId || !duration) {
                $('#admin-booking-summary').html('<div class="hrb-loading-message"><div class="hrb-loading-text"><?php _e('Please select room and duration to see pricing', 'hourly-room-booking'); ?></div></div>');
                return;
            }

            // Get room pricing data via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hrb_get_room_pricing',
                    nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>',
                    room_id: roomId,
                    duration: duration
                },
                success: function(response) {
                    if (response.success && response.data) {
                        displayPriceSummary(response.data, extraPeople, paymentMethod);
                    } else {
                        $('#admin-booking-summary').html('<div class="hrb-error-message"><?php _e('Error loading pricing information', 'hourly-room-booking'); ?></div>');
                    }
                },
                error: function() {
                    $('#admin-booking-summary').html('<div class="hrb-error-message"><?php _e('Error loading pricing information', 'hourly-room-booking'); ?></div>');
                }
            });
        };

        // Global function to display price summary
        function displayPriceSummary(pricing, extraPeople, paymentMethod) {
            var currencySymbol = '<?php echo hrb_get_currency_symbol(); ?>';
            var currencyCode = '<?php echo HRB_Currency_Manager::getInstance()->get_currency_code(); ?>';

            // Calculate base price
            var basePrice = parseFloat(pricing.base_price) || 0;

            // Calculate extra people cost
            var extraPeoplePrice = 15.00; // €15 per extra person
            var additionalPeopleCost = extraPeople * extraPeoplePrice;

            // Calculate selected extras cost
            var extrasCost = 0;
            var extrasDetails = [];
            var checkedExtras = $('#extras-container input[type="checkbox"]:checked');

            checkedExtras.each(function() {
                var price = parseFloat($(this).data('price')) || 0;
                var name = $(this).data('name') || '';
                var extraId = parseInt($(this).val()) || 0;
                extrasCost += price;
                
                // Check if this extra was added by admin
                var isAdminAdded = false;
                if (typeof currentBookingExtras !== 'undefined' && currentBookingExtras && Array.isArray(currentBookingExtras) && currentBookingExtras.length > 0) {
                    // Find the matching booking extra by ID (the result has 'id' from extras table)
                    var bookingExtra = currentBookingExtras.find(function(be) {
                        // Match by id (from extras table - e.id in SQL result)
                        if (be.id && parseInt(be.id) === parseInt(extraId)) {
                            return true;
                        }
                        // Also check extra_id if it exists (from booking_extras table)
                        if (be.extra_id && parseInt(be.extra_id) === parseInt(extraId)) {
                            return true;
                        }
                        return false;
                    });
                    
                    // Only mark as admin-added if explicitly set to 1, true, or '1'
                    // If field is 0, NULL, undefined, or missing, it's NOT admin-added
                    var addedByText = <?php echo json_encode(__('Added by Admin', 'hourly-room-booking')); ?>;
                    if (bookingExtra) {
                        // Check if the field exists and is explicitly set to 1
                        if (bookingExtra.hasOwnProperty('added_by_admin')) {
                            var addedByAdmin = bookingExtra.added_by_admin;
                            // Explicitly check for 1, true, or '1' - anything else (0, null, undefined, false, '0') is NOT admin-added
                            if (addedByAdmin === 1 || addedByAdmin === true || addedByAdmin === '1') {
                                isAdminAdded = true;
                                // Get username if available
                                if (bookingExtra.added_by_display_name) {
                                    addedByText = <?php echo json_encode(__('Added by %s', 'hourly-room-booking')); ?>.replace('%s', bookingExtra.added_by_display_name);
                                } else if (bookingExtra.added_by_username) {
                                    addedByText = <?php echo json_encode(__('Added by %s', 'hourly-room-booking')); ?>.replace('%s', bookingExtra.added_by_username);
                                }
                            }
                            // If addedByAdmin is 0, '0', false, null, or undefined, isAdminAdded stays false
                        }
                        // If field doesn't exist (old bookings), isAdminAdded stays false
                    }
                }
                
                // Add highlighting for admin-added extras
                var extraHtml = '<span class="hrb-extra-summary-name">' + name;
                if (isAdminAdded) {
                    extraHtml += ' <span style="background: #fff3cd; padding: 2px 6px; border-radius: 3px; border-left: 2px solid #ffc107; color: #856404; font-size: 0.85em; margin-left: 5px;">(' + addedByText + ')</span>';
                }
                extraHtml += '</span><span class="hrb-extra-summary-price"> ' + formatPrice(price, currencySymbol, currencyCode) + '</span>';
                extrasDetails.push(extraHtml);
            });

            // Calculate subtotal
            var subtotal = basePrice + additionalPeopleCost + extrasCost;

            // Calculate PayPal fee (3% if PayPal selected)
            // IMPORTANT: Only apply fee to OUTSTANDING amount, not to already-paid amounts
            var paypalFee = 0;
            if (paymentMethod === 'paypal') {
                // Get already paid amount from the booking payments
                var alreadyPaidTotal = 0;
                if (typeof currentBookingPayments !== 'undefined' && currentBookingPayments && Array.isArray(currentBookingPayments)) {
                    currentBookingPayments.forEach(function(payment) {
                        var paymentStatus = (payment.status || '').toLowerCase();
                        if (paymentStatus === 'completed' || paymentStatus === 'paid') {
                            alreadyPaidTotal += parseFloat(payment.amount) || 0;
                        }
                    });
                }
                
                // Calculate outstanding amount (what still needs to be paid)
                var outstandingAmount = subtotal - alreadyPaidTotal;
                
                // Only apply PayPal fee to the outstanding amount
                if (outstandingAmount > 0) {
                    paypalFee = outstandingAmount * 0.03;
                }
            }

            // Calculate total
            var total = subtotal + paypalFee;

            // Helper function to get modification by type
            function getModificationByType(modifications, type) {
                if (!modifications || !Array.isArray(modifications) || modifications.length === 0) {
                    return null;
                }
                return modifications.find(function(mod) {
                    return mod.modification_type === type;
                }) || null;
            }
            
            // Helper function to get modification added by text
            function getModificationAddedByText(modification) {
                if (!modification) {
                    return '';
                }
                var addedByText = <?php echo json_encode(__('Added by Admin', 'hourly-room-booking')); ?>;
                if (modification.added_by_display_name) {
                    addedByText = <?php echo json_encode(__('Added by %s', 'hourly-room-booking')); ?>.replace('%s', modification.added_by_display_name);
                } else if (modification.added_by_username) {
                    addedByText = <?php echo json_encode(__('Added by %s', 'hourly-room-booking')); ?>.replace('%s', modification.added_by_username);
                }
                return addedByText;
            }
            
            // Helper function to get modification highlight styles
            function getModificationHighlightStyles(modification) {
                if (!modification) {
                    return { style: '', class: '' };
                }
                return {
                    style: 'background: #fff3cd; border-left: 3px solid #ffc107; padding-left: 15px;',
                    class: 'hrb-summary-modified'
                };
            }

            // Check for modifications
            var hoursModification = getModificationByType(currentBookingModifications, 'hours');
            var extraPeopleModification = getModificationByType(currentBookingModifications, 'extra_people');

            // Build summary HTML
            var summaryHtml = '<div class="hrb-summary-content">';
            
            // Room with hours - add highlighting if modified
            var hoursHighlight = getModificationHighlightStyles(hoursModification);
            var hoursModificationHtml = '';
            if (hoursModification) {
                var addedByText = getModificationAddedByText(hoursModification);
                var hoursIncrease = parseFloat(hoursModification.new_value) - parseFloat(hoursModification.original_value);
                hoursModificationHtml = '<br><small style="color: #856404; font-size: 0.9em;">+' + hoursIncrease + ' <?php echo esc_js(__('hours', 'hourly-room-booking')); ?> ' + addedByText + ' (+' + formatPrice(hoursModification.additional_amount, currencySymbol, currencyCode) + ')</small>';
            }
            summaryHtml += '<div class="hrb-summary-item ' + hoursHighlight.class + '" style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd; ' + hoursHighlight.style + '">';
            summaryHtml += '<span>' + $('#room_id option:selected').text() + ' (' + $('#duration').val() + 'h)' + hoursModificationHtml + '</span>';
            summaryHtml += '<span>' + formatPrice(basePrice, currencySymbol, currencyCode) + '</span>';
            summaryHtml += '</div>';

            if (extraPeople > 0) {
                // Extra People - add highlighting if modified
                var extraPeopleHighlight = getModificationHighlightStyles(extraPeopleModification);
                var extraPeopleModificationHtml = '';
                if (extraPeopleModification) {
                    var addedByText = getModificationAddedByText(extraPeopleModification);
                    var peopleIncrease = parseFloat(extraPeopleModification.new_value) - parseFloat(extraPeopleModification.original_value);
                    extraPeopleModificationHtml = '<br><small style="color: #856404; font-size: 0.9em;">+' + peopleIncrease + ' <?php echo esc_js(__('people', 'hourly-room-booking')); ?> ' + addedByText + ' (+' + formatPrice(extraPeopleModification.additional_amount, currencySymbol, currencyCode) + ')</small>';
                }
                summaryHtml += '<div class="hrb-summary-item ' + extraPeopleHighlight.class + '" style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd; ' + extraPeopleHighlight.style + '">';
                summaryHtml += '<span><?php echo esc_js(__('Extra People', 'hourly-room-booking')); ?> (' + extraPeople + ')' + extraPeopleModificationHtml + '</span>';
                summaryHtml += '<span>' + formatPrice(additionalPeopleCost, currencySymbol, currencyCode) + '</span>';
                summaryHtml += '</div>';
            }

            if (extrasDetails.length > 0) {
                summaryHtml += '<div class="hrb-summary-section"><strong>Extras</strong></div>';
                extrasDetails.forEach(function(detail) {
                    summaryHtml += '<div class="hrb-summary-item hrb-summary-extra">' + detail + '</div>';
                });
            }

            if (paypalFee > 0) {
                summaryHtml += '<div class="hrb-summary-item hrb-summary-fee">';
                summaryHtml += '<span>PayPal Gebühr (3%)</span>';
                summaryHtml += '<span>' + formatPrice(paypalFee, currencySymbol, currencyCode) + '</span>';
                summaryHtml += '</div>';
            }

            summaryHtml += '<div class="hrb-summary-total">';
            summaryHtml += '<span><strong>Gesamt</strong></span>';
            summaryHtml += '<span><strong>' + formatPrice(total, currencySymbol, currencyCode) + '</strong></span>';
            summaryHtml += '</div>';
            summaryHtml += '</div>';

            $('#admin-booking-summary').html(summaryHtml);
        }

        // Global function to format price
        function formatPrice(price, currencySymbol, currencyCode) {
            var formattedPrice = parseFloat(price).toFixed(2);
            return currencySymbol + formattedPrice;
        }
    });

    // Function to confirm booking deletion using custom dialog (global scope)
    window.confirmDeleteBooking = function(buttonElement) {
        var bookingId = buttonElement.getAttribute('data-booking-id');
        var bookingReference = buttonElement.getAttribute('data-booking-reference');

        // Use custom alert dialog with danger type
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
    };
    
    // Handle "Send Payment Link" button click
    jQuery(document).ready(function($) {
        $('#hrb-send-payment-link-btn').on('click', function() {
            var button = $(this);
            var bookingId = button.data('booking-id');
            var messageSpan = $('#hrb-send-payment-link-message');
            
            // Disable button and show loading
            button.prop('disabled', true);
            button.html('<span class="dashicons dashicons-update spin" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Sending...', 'hourly-room-booking'); ?>');
            messageSpan.hide();
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hrb_send_additional_payment_link',
                    nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>',
                    booking_id: bookingId
                },
                success: function(response) {
                    if (response.success) {
                        messageSpan.html('<span style="color: #28a745;">✓ ' + response.data.message + '</span>').show();
                        button.html('<span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Send Payment Link', 'hourly-room-booking'); ?>');
                    } else {
                        messageSpan.html('<span style="color: #dc3545;">✗ ' + (response.data.message || '<?php _e('Failed to send email', 'hourly-room-booking'); ?>') + '</span>').show();
                        button.html('<span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Send Payment Link', 'hourly-room-booking'); ?>');
                    }
                    button.prop('disabled', false);
                },
                error: function() {
                    messageSpan.html('<span style="color: #dc3545;">✗ <?php _e('Error sending email', 'hourly-room-booking'); ?></span>').show();
                    button.html('<span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Send Payment Link', 'hourly-room-booking'); ?>');
                    button.prop('disabled', false);
                }
            });
        });
        
        // Handle "Mark Payment as Complete" button click for onsite payments
        $('#hrb-mark-additional-payment-complete-btn').on('click', function() {
            var button = $(this);
            var bookingId = button.data('booking-id');
            var amount = button.data('amount');
            var messageSpan = $('#hrb-mark-additional-payment-message');
            
            // Format amount for display
            var currencySymbol = '<?php echo HRB_Currency_Manager::getInstance()->get_currency_symbol(); ?>';
            var formattedAmount = currencySymbol + parseFloat(amount).toFixed(2);
            
            // Use custom alert dialog for confirmation
            window.hrbShowAlertDialog(
                <?php echo json_encode(__('Are you sure you want to mark this additional payment as complete?', 'hourly-room-booking')); ?>,
                {
                    warningMessage: <?php echo json_encode(__('This will update the payment status to "completed".', 'hourly-room-booking')); ?>,
                    title: <?php echo json_encode(__('Confirm Payment Completion', 'hourly-room-booking')); ?>,
                    details: [
                        {
                            label: <?php echo json_encode(__('Outstanding Amount:', 'hourly-room-booking')); ?>,
                            value: formattedAmount,
                            class: 'original'
                        }
                    ],
                    confirmText: <?php echo json_encode(__('Mark as Complete', 'hourly-room-booking')); ?>,
                    cancelText: <?php echo json_encode(__('Cancel', 'hourly-room-booking')); ?>,
                    type: 'success'
                },
                function() {
                    // User confirmed - proceed with AJAX request
                    button.prop('disabled', true);
                    button.html('<span class="dashicons dashicons-update spin" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Processing...', 'hourly-room-booking'); ?>');
                    messageSpan.hide();
                    
                    // Send AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hrb_mark_additional_payment_complete',
                            nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>',
                            booking_id: bookingId
                        },
                        success: function(response) {
                            if (response.success) {
                                messageSpan.html('<span style="color: #28a745;">✓ ' + response.data.message + '</span>').show();
                                // Reload the page after 2 seconds to reflect the changes
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                messageSpan.html('<span style="color: #dc3545;">✗ ' + (response.data.message || '<?php _e('Failed to update payment', 'hourly-room-booking'); ?>') + '</span>').show();
                                button.html('<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Mark Payment as Complete', 'hourly-room-booking'); ?>');
                                button.prop('disabled', false);
                            }
                        },
                        error: function() {
                            messageSpan.html('<span style="color: #dc3545;">✗ <?php _e('Error updating payment', 'hourly-room-booking'); ?></span>').show();
                            button.html('<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Mark Payment as Complete', 'hourly-room-booking'); ?>');
                            button.prop('disabled', false);
                        }
                    });
                }
            );
        });
        
        $('#hrb-regenerate-invoice-btn').on('click', function() {
            var button = $(this);
            var bookingId = button.data('booking-id');
            var messageSpan = $('#hrb-regenerate-invoice-message');
            
            // Disable button and show loading
            button.prop('disabled', true);
            button.html('<span class="dashicons dashicons-update spin" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Regenerating...', 'hourly-room-booking'); ?>');
            messageSpan.hide();
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hrb_regenerate_invoice',
                    nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>',
                    booking_id: bookingId
                },
                success: function(response) {
                    if (response.success) {
                        messageSpan.html('<span style="color: #28a745;">✓ ' + response.data.message + '</span>').show();
                        button.html('<span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Regenerate Invoice', 'hourly-room-booking'); ?>');
                        // Reload page after 1 second to show updated invoice
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        messageSpan.html('<span style="color: #dc3545;">✗ ' + (response.data.message || '<?php _e('Failed to regenerate invoice', 'hourly-room-booking'); ?>') + '</span>').show();
                        button.html('<span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Regenerate Invoice', 'hourly-room-booking'); ?>');
                    }
                    button.prop('disabled', false);
                },
                error: function() {
                    messageSpan.html('<span style="color: #dc3545;">✗ <?php _e('Error regenerating invoice', 'hourly-room-booking'); ?></span>').show();
                    button.html('<span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 5px;"></span><?php _e('Regenerate Invoice', 'hourly-room-booking'); ?>');
                    button.prop('disabled', false);
                }
            });
        });
    });
</script>
<style>
    .spin {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>
</style>
</style>
</style>