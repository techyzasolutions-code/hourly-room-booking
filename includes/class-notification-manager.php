<?php
/**
 * Notification Manager Class
 * Handles email, SMS, and WhatsApp notifications
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Notification_Manager {
    
    private static $instance = null;
    private $twilio_sid;
    private $twilio_token;
    private $twilio_from;
    private $whatsapp_token;
    private $whatsapp_phone_id;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->twilio_sid = get_option('hrb_twilio_sid', '');
        $this->twilio_token = get_option('hrb_twilio_token', '');
        $this->twilio_from = get_option('hrb_twilio_from', '');
        $this->whatsapp_token = get_option('hrb_whatsapp_token', '');
        $this->whatsapp_phone_id = get_option('hrb_whatsapp_phone_id', '');
    }
    
    /**
     * Send notification based on event
     */
    public function send_notification($booking_id, $event, $custom_data = array()) {
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        // Skip notifications for anonymous bookings
        if ($booking->is_anonymous) {
            /* removed error_log - production cleanup */
            return array(
                'email' => 'skipped_anonymous',
                'sms' => 'skipped_anonymous',
                'whatsapp' => 'skipped_anonymous'
            );
        }
        
        $notifications_sent = array();
        
        // Send email notification
        if (get_option('hrb_email_notifications', 1)) {
            $email_result = $this->send_email_notification($booking, $event, $custom_data);
            $notifications_sent['email'] = $email_result;
        }
        
        // Send SMS notification
        if (get_option('hrb_sms_notifications', 0) && !empty($booking->phone)) {
            $sms_result = $this->send_sms_notification($booking, $event);
            $notifications_sent['sms'] = $sms_result;
        }
        
        // Send WhatsApp notification
        if (get_option('hrb_whatsapp_notifications', 0) && !empty($booking->phone)) {
            $whatsapp_result = $this->send_whatsapp_notification($booking, $event);
            $notifications_sent['whatsapp'] = $whatsapp_result;
        }
        
        return $notifications_sent;
    }
    
    /**
     * Send email notification
     */
    public function send_email_notification($booking, $event, $custom_data = array()) {
        // Validate email before proceeding
        if (empty($booking->email) || !is_email($booking->email)) {
            return new WP_Error('invalid_email', __('Invalid or empty email address', 'hourly-room-booking'));
        }
        
        $template_data = $this->prepare_template_data($booking, $event, $custom_data);
        
        if (!$template_data) {
            return new WP_Error('template_error', __('Failed to prepare email template', 'hourly-room-booking'));
        }
        
        $to = sanitize_email($booking->email);
        $subject = $template_data['subject'];
        $message = $this->generate_email_html($template_data);
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('hrb_company_name', get_bloginfo('name')) . ' <' . get_option('hrb_company_email', get_option('admin_email')) . '>'
        );
        
        // Attach invoice for booking confirmation, payment confirmation, and invoice regeneration
        $attachments = array();
        
        if ($event === 'invoice_regenerated') {
            // For invoice regeneration, always attach the invoice
            // First check if invoice path was passed in custom_data (from regenerate_invoice)
            $invoice_path = isset($custom_data['invoice_path']) ? $custom_data['invoice_path'] : null;
            
            if ($invoice_path && file_exists($invoice_path)) {
                // Use the path passed from regenerate_invoice (most recent)
                $attachments[] = $invoice_path;
            } else {
                // Fallback: get invoice path from database
                $invoice_generator = HRB_Invoice_Generator::getInstance();
                $invoice = $invoice_generator->get_invoice_by_booking($booking->id);
                if ($invoice && !empty($invoice->pdf_file_path) && file_exists($invoice->pdf_file_path)) {
                    $attachments[] = $invoice->pdf_file_path;
                }
            }
        } elseif (in_array($event, array('booking_confirmation', 'payment_confirmation'))) {
            // Only attach invoice if it should exist based on payment method and status
            $should_have_invoice = false;
            
            if ($booking->status === 'confirmed') {
                // For PayPal payments, invoice should exist
                if ($booking->payment_method === 'paypal') {
                    $should_have_invoice = true;
                }
                // For cash/onsite payments, invoice should exist when status is 'paid' or 'completed'
                elseif (in_array($booking->payment_method, ['onsite', 'cash']) && 
                        in_array($booking->payment_status, ['paid', 'completed'])) {
                    $should_have_invoice = true;
                }
                // For other payment methods, invoice should exist
                elseif (!in_array($booking->payment_method, ['onsite', 'cash', 'paypal'])) {
                    $should_have_invoice = true;
                }
            }
            
            if ($should_have_invoice) {
                // Note: Invoice regeneration for additional payments is handled in payment handler
                // before sending notification, so we don't need to regenerate here again
                // This prevents double regeneration and ensures we use the already-updated invoice
                
                $invoice_path = $this->get_or_generate_invoice($booking->id);
                if ($invoice_path && file_exists($invoice_path)) {
                    $attachments[] = $invoice_path;
                }
            }
        }
        
        // Send separate admin notifications
        if (in_array($event, array('booking_confirmation', 'payment_confirmation'))) {
            $this->send_admin_notification($booking, $event);
        }
        
        // Log email attempt
        /* removed error_log - production cleanup */
        
        $sent = wp_mail($to, $subject, $message, $headers, $attachments);
        
        if ($sent) {
            /* removed error_log - production cleanup */
        } else {
            /* removed error_log - production cleanup */
        }
        
        // Log notification
        $this->log_notification($booking->id, $booking->customer_id, 'email', $event, $to, $subject, $message, $sent ? 'sent' : 'failed');
        
        return $sent;
    }
    
    /**
     * Send admin notification
     */
    private function send_admin_notification($booking, $event) {
        global $wpdb;
        
        $settings = HRB_Settings::getInstance();
        
        // Get admin template
        $template_key = $event . '_admin';
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_email_templates WHERE template_key = %s AND template_type = 'admin' AND is_active = 1",
            $template_key
        ));
        
        if (!$template) {
            // Fallback to default admin template
            $template_data = $this->get_default_admin_template_data($booking, $event);
        } else {
            $template_data = $this->prepare_admin_template_data($booking, $event, $template);
        }
        
        if (!$template_data) {
            return false;
        }
        
        // Send to admin email if enabled
        if ($settings->get('hrb_admin_email_notifications', 1)) {
            $admin_email = $settings->get('hrb_admin_email', '');
            if (empty($admin_email)) {
                $admin_email = get_option('admin_email');
            }
            if (!empty($admin_email)) {
                $this->send_admin_email($admin_email, $template_data, $booking, $event);
            }
        }
        
        // Send to staff email if enabled
        if ($settings->get('hrb_staff_email_notifications', 0)) {
            $staff_email = $settings->get('hrb_staff_email', '');
            if (!empty($staff_email)) {
                $this->send_admin_email($staff_email, $template_data, $booking, $event);
            }
        }
    }
    
    /**
     * Send admin email
     */
    private function send_admin_email($to, $template_data, $booking, $event) {
        $subject = $template_data['subject'];
        $message = $this->generate_email_html($template_data);
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('hrb_company_name', get_bloginfo('name')) . ' <' . get_option('hrb_company_email', get_option('admin_email')) . '>'
        );
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        // Log notification
        $this->log_notification($booking->id, $booking->customer_id, 'email', $event . '_admin', $to, $subject, $message, $sent ? 'sent' : 'failed');
        
        return $sent;
    }
    
    /**
     * Send SMS notification via Twilio
     */
    public function send_sms_notification($booking, $event) {
        if (empty($this->twilio_sid) || empty($this->twilio_token) || empty($this->twilio_from)) {
            return new WP_Error('twilio_config', __('Twilio configuration is missing', 'hourly-room-booking'));
        }
        
        $template_data = $this->prepare_template_data($booking, $event);
        $message = $this->generate_sms_message($template_data);
        
        // Format phone number for Twilio
        $to_phone = $this->format_phone_number($booking->phone);
        
        $twilio_url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilio_sid}/Messages.json";
        
        $response = wp_remote_post($twilio_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->twilio_sid . ':' . $this->twilio_token),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'From' => $this->twilio_from,
                'To' => $to_phone,
                'Body' => $message
            ),
            'timeout' => 30
        ));
        
        $success = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 201;
        
        // Log notification
        $this->log_notification(
            $booking->id, 
            $booking->customer_id, 
            'sms', 
            $event, 
            $to_phone, 
            null, 
            $message, 
            $success ? 'sent' : 'failed',
            is_wp_error($response) ? $response->get_error_message() : null
        );
        
        return $success;
    }
    
    /**
     * Send WhatsApp notification via WhatsApp Business API
     */
    public function send_whatsapp_notification($booking, $event) {
        if (empty($this->whatsapp_token) || empty($this->whatsapp_phone_id)) {
            return new WP_Error('whatsapp_config', __('WhatsApp configuration is missing', 'hourly-room-booking'));
        }
        
        $template_data = $this->prepare_template_data($booking, $event);
        $message = $this->generate_whatsapp_message($template_data);
        
        // Format phone number for WhatsApp
        $to_phone = $this->format_phone_number_whatsapp($booking->phone);
        
        $whatsapp_url = "https://graph.facebook.com/v17.0/{$this->whatsapp_phone_id}/messages";
        
        $message_data = array(
            'messaging_product' => 'whatsapp',
            'to' => $to_phone,
            'type' => 'text',
            'text' => array('body' => $message)
        );
        
        $response = wp_remote_post($whatsapp_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->whatsapp_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($message_data),
            'timeout' => 30
        ));
        
        $success = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
        
        // Log notification
        $this->log_notification(
            $booking->id, 
            $booking->customer_id, 
            'whatsapp', 
            $event, 
            $to_phone, 
            null, 
            $message, 
            $success ? 'sent' : 'failed',
            is_wp_error($response) ? $response->get_error_message() : null
        );
        
        return $success;
    }
    
    /**
     * Prepare template data for notifications
     */
    private function prepare_template_data($booking, $event, $custom_data = array()) {
        global $wpdb;
        
        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($booking->room_id);
        
        if (!$room) {
            return false;
        }
        
        // Get template from database based on recipient type
        $template_key = $event . '_user'; // Default to user template
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_email_templates WHERE template_key = %s AND template_type = 'user' AND is_active = 1",
            $template_key
        ));
        
        // If user template not found, try the original template key (for backward compatibility)
        if (!$template) {
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hrb_email_templates WHERE template_key = %s AND is_active = 1",
                $event
            ));
        }
        
        if (!$template) {
            // Fallback to default template if not found in database
            return $this->get_default_template_data($booking, $event, $room);
        }
        
        $company_name = get_option('hrb_company_name', get_bloginfo('name'));
        $booking_url = site_url("/booking-details/?ref=" . $booking->booking_reference);
        
        // First, create basic data without template variables
        $basic_data = array(
            'customer_name' => $booking->first_name . ' ' . $booking->last_name,
            'customer_first_name' => $booking->first_name,
            'booking_reference' => $booking->booking_reference,
            'room_name' => $room->name,
            'booking_date' => date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date)),
            'start_time' => date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->start_time)),
            'end_time' => date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->end_time)),
            'duration' => $booking->total_hours . ' ' . _n('hour', 'hours', $booking->total_hours, 'hourly-room-booking'),
            'total_amount' => hrb_format_amount($booking->total_amount),
            'payment_method' => $this->get_payment_method_label($booking->payment_method),
            'company_name' => $company_name,
            'company_phone' => get_option('hrb_company_phone', ''),
            'company_email' => get_option('hrb_company_email', get_option('admin_email')),
            'booking_url' => $booking_url,
            'cancel_url' => $booking_url . '&action=cancel',
            'booking_status' => $this->get_booking_status_label($booking->status),
            'payment_status' => $this->get_payment_status_label($booking->id)
        );
        
        // Merge custom data into basic_data (custom data takes precedence)
        if (!empty($custom_data) && is_array($custom_data)) {
            // Format additional_amount if it's a numeric value (not already formatted)
            if (isset($custom_data['additional_amount']) && is_numeric($custom_data['additional_amount'])) {
                $custom_data['additional_amount'] = hrb_format_amount($custom_data['additional_amount']);
            }
            $basic_data = array_merge($basic_data, $custom_data);
        }
        
        // Now create the full data with template variables replaced
        $data = array_merge($basic_data, array(
            'subject' => $this->replace_template_variables($template->subject, $booking, $room, $basic_data),
            'heading' => $this->replace_template_variables($template->heading, $booking, $room, $basic_data),
            'message' => $this->replace_template_variables($template->message, $booking, $room, $basic_data),
            'html_content' => $this->replace_template_variables($template->html_content, $booking, $room, $basic_data)
        ));
        
        // The {booking_status} variable should already be replaced by replace_template_variables
        // But let's ensure {heading} and {message} are properly replaced in html_content
        $data['html_content'] = str_replace(
            array('{heading}', '{message}'),
            array($data['heading'], $data['message']),
            $data['html_content']
        );

        // Remove payment button if no payment link should be shown
        if ($event === 'online_payment_pending' && empty($basic_data['payment_link'])) {
            // Remove wrapping container that only holds the PayPal button (if present)
            $data['html_content'] = preg_replace(
                '/<div[^>]*>\s*<a[^>]*payment-button[^>]*>.*?<\/a>\s*<\/div>/is',
                '',
                $data['html_content']
            );

            // Remove any remaining anchors styled as payment buttons
            $data['html_content'] = preg_replace(
                '/<a[^>]*payment-button[^>]*>.*?<\/a>/is',
                '',
                $data['html_content']
            );
        }

        // Ensure critical payment links are present even if template hasn't been updated
        if ($event === 'online_payment_pending' && !empty($basic_data['payment_link'])) {
            $payment_link = esc_url($basic_data['payment_link']);
            $button_html = '<div style="text-align:center;margin:20px 0;">'
                . '<a href="' . $payment_link . '" '
                . 'style="display:inline-block;padding:15px 30px;background:linear-gradient(135deg,#0070ba,#005ea6);'
                . 'color:#fff;text-decoration:none;border-radius:5px;font-weight:bold;font-size:16px;">'
                . __('Jetzt mit PayPal bezahlen', 'hourly-room-booking')
                . '</a></div>';

            // Check if payment button already exists in the template
            // Look for: payment-button class, payment link URL, or button text
            $button_exists = (
                strpos($data['html_content'], 'payment-button') !== false ||
                strpos($data['html_content'], $payment_link) !== false ||
                strpos($data['html_content'], __('Jetzt mit PayPal bezahlen', 'hourly-room-booking')) !== false ||
                strpos($data['html_content'], 'Jetzt mit PayPal bezahlen') !== false
            );

            // Only add button if placeholder exists and button doesn't exist yet
            if (strpos($data['html_content'], '{payment_link}') !== false) {
                // Placeholder exists, it will be replaced by replace_template_variables
                // Don't add duplicate button
            } elseif (!$button_exists) {
                // No placeholder and no button found - add button
                if (stripos($data['html_content'], '</body>') !== false) {
                    $data['html_content'] = preg_replace(
                        '/<\/body>/i',
                        $button_html . '</body>',
                        $data['html_content'],
                        1
                    );
                } else {
                    $data['html_content'] .= $button_html;
                }
            }

            if (strpos($data['message'], '{payment_link}') === false &&
                strpos($data['message'], $payment_link) === false) {
                $data['message'] .= "\n\n" . sprintf(
                    __('Jetzt bezahlen: %s', 'hourly-room-booking'),
                    $payment_link
                );
            }
        }
        
        
        return $data;
    }
    
    /**
     * Prepare admin template data for notifications
     */
    private function prepare_admin_template_data($booking, $event, $template) {
        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($booking->room_id);
        
        if (!$room) {
            return false;
        }
        
        $company_name = get_option('hrb_company_name', get_bloginfo('name'));
        $booking_url = site_url("/booking-details/?ref=" . $booking->booking_reference);
        
        // Create admin-specific data with additional fields
        $data = array(
            'customer_name' => $booking->first_name . ' ' . $booking->last_name,
            'customer_first_name' => $booking->first_name,
            'customer_email' => $booking->email,
            'customer_phone' => $booking->phone,
            'booking_reference' => $booking->booking_reference,
            'room_name' => $room->name,
            'booking_date' => date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date)),
            'start_time' => date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->start_time)),
            'end_time' => date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->end_time)),
            'duration' => $booking->total_hours . ' ' . _n('hour', 'hours', $booking->total_hours, 'hourly-room-booking'),
            'total_amount' => hrb_format_amount($booking->total_amount),
            'payment_method' => $this->get_payment_method_label($booking->payment_method),
            'company_name' => $company_name,
            'company_phone' => get_option('hrb_company_phone', ''),
            'company_email' => get_option('hrb_company_email', get_option('admin_email')),
            'booking_url' => $booking_url,
            'cancel_url' => $booking_url . '&action=cancel',
            'booking_status' => $this->get_booking_status_label($booking->status),
            'payment_status' => $this->get_payment_status_label($booking->id),
            'subject' => $template->subject,
            'heading' => $template->heading,
            'message' => $template->message,
            'html_content' => $template->html_content
        );
        
        // Replace template variables in content
        $data['html_content'] = $this->replace_template_variables($template->html_content, $booking, $room, $data);
        $data['subject'] = $this->replace_template_variables($template->subject, $booking, $room, $data);
        $data['heading'] = $this->replace_template_variables($template->heading, $booking, $room, $data);
        $data['message'] = $this->replace_template_variables($template->message, $booking, $room, $data);
        
        return $data;
    }
    
    /**
     * Replace template variables with actual data
     */
    private function replace_template_variables($content, $booking, $room, $template_data = null) {
        // If template_data is provided, use it for replacements
        if ($template_data && is_array($template_data)) {
            $replacements = array();
            foreach ($template_data as $key => $value) {
                $replacements['{' . $key . '}'] = $value;
            }
        } else {
            // Fallback to individual replacements
            $company_name = get_option('hrb_company_name', get_bloginfo('name'));
            $booking_url = site_url("/booking-details/?ref=" . $booking->booking_reference);
            
            $replacements = array(
                '{company_name}' => $company_name,
                '{customer_name}' => $booking->first_name . ' ' . $booking->last_name,
                '{customer_first_name}' => $booking->first_name,
                '{booking_reference}' => $booking->booking_reference,
                '{room_name}' => $room->name,
                '{booking_date}' => date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date)),
                '{start_time}' => date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->start_time)),
                '{end_time}' => date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->end_time)),
                '{duration}' => $booking->total_hours . ' ' . _n('hour', 'hours', $booking->total_hours, 'hourly-room-booking'),
                '{total_amount}' => hrb_format_amount($booking->total_amount),
                '{payment_method}' => $this->get_payment_method_label($booking->payment_method),
                '{company_email}' => get_option('hrb_company_email', get_option('admin_email')),
                '{company_phone}' => get_option('hrb_company_phone', ''),
                '{booking_url}' => $booking_url,
                '{cancel_url}' => $booking_url . '&action=cancel',
                '{booking_status}' => $this->get_booking_status_label($booking->status),
                '{payment_status}' => $this->get_payment_status_label($booking->id)
            );
        }
        
        // Note: {heading} and {message} are handled separately to avoid circular references

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        // Cancellation-fee tokens — resolved centrally from the booking so every
        // template path (user/admin/fallback) renders them consistently.
        // {cancellation_fee}        -> formatted amount (empty when no fee)
        // {cancellation_fee_notice} -> a full sentence (empty when no fee)
        $cancellation_fee = isset($booking->cancellation_fee) ? floatval($booking->cancellation_fee) : 0;
        $cancellation_fee_notice = '';
        $cancellation_fee_notice_html = '';
        if ($cancellation_fee > 0) {
            $cancellation_fee_notice = sprintf(
                /* translators: %s: formatted cancellation fee amount */
                __('A cancellation fee of %s applies and is payable on-site.', 'hourly-room-booking'),
                hrb_format_amount($cancellation_fee)
            );
            // Styled block for HTML email templates (only rendered when a fee applies).
            $cancellation_fee_notice_html =
                '<div style="margin:20px 0;padding:14px 16px;background:#fdecea;border-left:4px solid #981b1e;">'
                . '<strong style="color:#981b1e;">' . esc_html($cancellation_fee_notice) . '</strong>'
                . '</div>';
        }
        $content = str_replace(
            array('{cancellation_fee}', '{cancellation_fee_notice}', '{cancellation_fee_notice_html}'),
            array(
                $cancellation_fee > 0 ? hrb_format_amount($cancellation_fee) : '',
                $cancellation_fee_notice,
                $cancellation_fee_notice_html,
            ),
            $content
        );

        return $content;
    }
    
    /**
     * Get default template data (fallback)
     */
    private function get_default_template_data($booking, $event, $room) {
        $company_name = get_option('hrb_company_name', get_bloginfo('name'));
        $booking_url = site_url("/booking-details/?ref=" . $booking->booking_reference);
        
        $data = array(
            'customer_name' => $booking->first_name . ' ' . $booking->last_name,
            'customer_first_name' => $booking->first_name,
            'booking_reference' => $booking->booking_reference,
            'room_name' => $room->name,
            'booking_date' => date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date)),
            'start_time' => date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->start_time)),
            'end_time' => date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->end_time)),
            'duration' => $booking->total_hours . ' ' . _n('hour', 'hours', $booking->total_hours, 'hourly-room-booking'),
            'total_amount' => hrb_format_amount($booking->total_amount),
            'payment_method' => $this->get_payment_method_label($booking->payment_method),
            'company_name' => $company_name,
            'company_phone' => get_option('hrb_company_phone', ''),
            'company_email' => get_option('hrb_company_email', get_option('admin_email')),
            'booking_url' => $booking_url,
            'cancel_url' => $booking_url . '&action=cancel',
            'booking_status' => $this->get_booking_status_label($booking->status),
            'payment_status' => $this->get_payment_status_label($booking->id)
        );
        
        switch ($event) {
            case 'booking_confirmation':
                $data['subject'] = sprintf(__('Booking Confirmation - %s', 'hourly-room-booking'), $booking->booking_reference);
                $data['heading'] = __('Booking Confirmed!', 'hourly-room-booking');
                $data['message'] = __('Thank you for your booking. Here are your booking details:', 'hourly-room-booking');
                break;
                
            case 'payment_confirmation':
                $data['subject'] = sprintf(__('Payment Confirmed - %s', 'hourly-room-booking'), $booking->booking_reference);
                $data['heading'] = __('Payment Received!', 'hourly-room-booking');
                $data['message'] = __('Your payment has been successfully processed.', 'hourly-room-booking');
                break;
                
            case 'booking_reminder':
                $data['subject'] = sprintf(__('Booking Reminder - %s', 'hourly-room-booking'), $booking->booking_reference);
                $data['heading'] = __('Booking Reminder', 'hourly-room-booking');
                $data['message'] = __('This is a reminder that your booking starts in 1 hour.', 'hourly-room-booking');
                break;
                
            case 'booking_cancelled':
                $data['subject'] = sprintf(__('Booking Cancelled - %s', 'hourly-room-booking'), $booking->booking_reference);
                $data['heading'] = __('Booking Cancelled', 'hourly-room-booking');
                $data['message'] = __('Your booking has been cancelled.', 'hourly-room-booking');
                break;
                
            case 'booking_modified':
                $data['subject'] = sprintf(__('Booking Modified - %s', 'hourly-room-booking'), $booking->booking_reference);
                $data['heading'] = __('Booking Updated', 'hourly-room-booking');
                $data['message'] = __('Your booking has been modified. Please review the updated details:', 'hourly-room-booking');
                break;
                
            default:
                return false;
        }
        
        return $data;
    }
    
    /**
     * Get default admin template data (fallback)
     */
    private function get_default_admin_template_data($booking, $event) {
        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($booking->room_id);
        
        if (!$room) {
            return false;
        }
        
        $company_name = get_option('hrb_company_name', get_bloginfo('name'));
        $booking_url = site_url("/booking-details/?ref=" . $booking->booking_reference);
        
        $data = array(
            'customer_name' => $booking->first_name . ' ' . $booking->last_name,
            'customer_first_name' => $booking->first_name,
            'customer_email' => $booking->email,
            'customer_phone' => $booking->phone,
            'booking_reference' => $booking->booking_reference,
            'room_name' => $room->name,
            'booking_date' => date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date)),
            'start_time' => date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->start_time)),
            'end_time' => date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->end_time)),
            'duration' => $booking->total_hours . ' ' . _n('hour', 'hours', $booking->total_hours, 'hourly-room-booking'),
            'total_amount' => hrb_format_amount($booking->total_amount),
            'payment_method' => $this->get_payment_method_label($booking->payment_method),
            'company_name' => $company_name,
            'company_phone' => get_option('hrb_company_phone', ''),
            'company_email' => get_option('hrb_company_email', get_option('admin_email')),
            'booking_url' => $booking_url,
            'cancel_url' => $booking_url . '&action=cancel',
            'booking_status' => $this->get_booking_status_label($booking->status),
            'payment_status' => $this->get_payment_status_label($booking->id)
        );
        
        switch ($event) {
            case 'booking_confirmation':
                $data['subject'] = sprintf(__('New Booking Received - %s', 'hourly-room-booking'), $booking->booking_reference);
                $data['heading'] = __('New Booking Alert!', 'hourly-room-booking');
                $data['message'] = __('A new booking has been received. Here are the details:', 'hourly-room-booking');
                break;
                
            case 'payment_confirmation':
                $data['subject'] = sprintf(__('Payment Received - %s', 'hourly-room-booking'), $booking->booking_reference);
                $data['heading'] = __('Payment Confirmed!', 'hourly-room-booking');
                $data['message'] = __('A payment has been successfully processed for a booking.', 'hourly-room-booking');
                break;
                
            default:
                return false;
        }
        
        return $data;
    }
    
    /**
     * Generate HTML email content
     */
    private function generate_email_html($data) {
        // If we have custom HTML content from template, use it
        if (isset($data['html_content']) && !empty($data['html_content'])) {
            return $data['html_content'];
        }
        
        // Fallback to default HTML template
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($data['subject']) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #0073aa; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .booking-details { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
                .detail-row { margin-bottom: 10px; }
                .label { font-weight: bold; display: inline-block; width: 150px; }
                .button { display: inline-block; padding: 12px 24px; background-color: #0073aa; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . esc_html($data['company_name']) . '</h1>
                    <h2>' . esc_html($data['heading']) . '</h2>
                </div>
                
                <div class="content">
                    <p>Hello ' . esc_html($data['customer_first_name']) . ',</p>
                    <p>' . esc_html($data['message']) . '</p>
                    
                    <div class="booking-details">
                        <h3>Booking Details</h3>
                        <div class="detail-row">
                            <span class="label">Booking Reference:</span>
                            <strong>' . esc_html($data['booking_reference']) . '</strong>
                        </div>
                        <div class="detail-row">
                            <span class="label">Room:</span>
                            ' . esc_html($data['room_name']) . '
                        </div>
                        <div class="detail-row">
                            <span class="label">Date:</span>
                            ' . esc_html($data['booking_date']) . '
                        </div>
                        <div class="detail-row">
                            <span class="label">Time:</span>
                            ' . esc_html($data['start_time']) . ' - ' . esc_html($data['end_time']) . '
                        </div>
                        <div class="detail-row">
                            <span class="label">Duration:</span>
                            ' . esc_html($data['duration']) . '
                        </div>
                        <div class="detail-row">
                            <span class="label">Total Amount:</span>
                            <strong>' . esc_html($data['total_amount']) . '</strong>
                        </div>
                        <div class="detail-row">
                            <span class="label">Payment Method:</span>
                            ' . esc_html($data['payment_method']) . '
                        </div>
                        <div class="detail-row">
                            <span class="label">Status:</span>
                            ' . esc_html($data['booking_status']) . '
                        </div>
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="' . esc_url($data['booking_url']) . '" class="button">View Booking</a>
                    </p>
                    
                    <p>If you have any questions, please contact us:</p>
                    <p>
                        <strong>Phone:</strong> ' . esc_html($data['company_phone']) . '<br>
                        <strong>Email:</strong> ' . esc_html($data['company_email']) . '
                    </p>
                </div>
                
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . esc_html($data['company_name']) . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Generate SMS message content
     */
    private function generate_sms_message($data) {
        $message = $data['company_name'] . "\n\n";
        $message .= $data['heading'] . "\n\n";
        $message .= "Buchung: " . $data['booking_reference'] . "\n";
        $message .= "Raum: " . $data['room_name'] . "\n";
        $message .= "Datum: " . $data['booking_date'] . "\n";
        $message .= "Zeit: " . $data['start_time'] . " - " . $data['end_time'] . "\n";
        $message .= "Betrag: " . $data['total_amount'] . "\n\n";
        $message .= "Details: " . $data['booking_url'];
        
        return $message;
    }
    
    /**
     * Generate WhatsApp message content
     */
    private function generate_whatsapp_message($data) {
        $message = "🏢 *" . $data['company_name'] . "*\n\n";
        $message .= "✅ *" . $data['heading'] . "*\n\n";
        $message .= "📋 *Buchungsdetails:*\n";
        $message .= "• Buchungsnummer: " . $data['booking_reference'] . "\n";
        $message .= "• Raum: " . $data['room_name'] . "\n";
        $message .= "• Datum: " . $data['booking_date'] . "\n";
        $message .= "• Zeit: " . $data['start_time'] . " - " . $data['end_time'] . "\n";
        $message .= "• Dauer: " . $data['duration'] . "\n";
        $message .= "• Betrag: " . $data['total_amount'] . "\n\n";
        $message .= "🔗 Buchung anzeigen: " . $data['booking_url'] . "\n\n";
        $message .= "📞 Support: " . $data['company_phone'];
        
        return $message;
    }
    
    /**
     * Format phone number for Twilio (E.164 format)
     */
    private function format_phone_number($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add German country code if missing
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            $phone = '+49' . substr($phone, 1);
        } elseif (strlen($phone) === 11 && substr($phone, 0, 2) === '49') {
            $phone = '+' . $phone;
        } elseif (substr($phone, 0, 1) !== '+') {
            $phone = '+49' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Format phone number for WhatsApp (no + prefix)
     */
    private function format_phone_number_whatsapp($phone) {
        $formatted = $this->format_phone_number($phone);
        return ltrim($formatted, '+');
    }
    
    /**
     * Log notification to database
     */
    private function log_notification($booking_id, $customer_id, $type, $event, $recipient, $subject, $message, $status, $error_message = null) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'hrb_notification_logs',
            array(
                'booking_id' => $booking_id,
                'customer_id' => $customer_id,
                'type' => $type,
                'event' => $event,
                'recipient' => $recipient,
                'subject' => $subject,
                'message' => $message,
                'status' => $status,
                'sent_at' => $status === 'sent' ? current_time('mysql') : null,
                'error_message' => $error_message
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Send OTP verification
     */
    public function send_otp_verification($email, $phone, $type = 'email') {
        global $wpdb;
        
        // Generate OTP
        $otp_code = sprintf('%06d', mt_rand(100000, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Store OTP in database
        $wpdb->replace(
            $wpdb->prefix . 'hrb_otp_verification',
            array(
                'email' => $email,
                'phone' => $phone,
                'otp_code' => $otp_code,
                'verification_type' => $type,
                'expires_at' => $expires_at,
                'attempts' => 0
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        $company_name = get_option('hrb_company_name', get_bloginfo('name'));
        
        if ($type === 'email') {
            // Use OTP email template from database
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hrb_email_templates WHERE template_key = 'otp_verification_user' AND template_type = 'user' AND is_active = 1"
            ));
            
            if ($template) {
                // Use template from database
                $template_data = array(
                    'otp_code' => $otp_code,
                    'customer_name' => 'Customer', // We don't have customer name at this point
                    'company_name' => $company_name,
                    'company_email' => get_option('hrb_company_email', get_option('admin_email')),
                    'company_phone' => get_option('hrb_company_phone', ''),
                );
                
                // Replace template variables
                $subject = $this->replace_template_variables($template->subject, null, null, $template_data);
                $heading = $this->replace_template_variables($template->heading, null, null, $template_data);
                $message = $this->replace_template_variables($template->message, null, null, $template_data);
                $html_content = $this->replace_template_variables($template->html_content, null, null, $template_data);
                
                $message = $this->generate_email_html(array(
                    'subject' => $subject,
                    'heading' => $heading,
                    'message' => $message,
                    'html_content' => $html_content,
                ));
            } else {
                // Fallback to hardcoded message if template not found
                $subject = sprintf(__('Your verification code - %s', 'hourly-room-booking'), $company_name);
                $message = sprintf(
                    __('Your verification code is: %s\n\nThis code will expire in 15 minutes.\n\n%s', 'hourly-room-booking'),
                    $otp_code,
                    $company_name
                );
            }

            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $company_name . ' <' . get_option('hrb_company_email', get_option('admin_email')) . '>'
            );

            $sent = wp_mail($email, $subject, $message, $headers);
            
            // Log OTP notification (no booking_id or customer_id for OTP)
            $this->log_notification(0, 0, 'email', 'otp_verification', $email, $subject, $message, $sent ? 'sent' : 'failed');
            
            return $sent;
        } elseif ($type === 'sms' && !empty($this->twilio_sid)) {
            $message = sprintf(
                __('Your %s verification code is: %s. Valid for 15 minutes.', 'hourly-room-booking'),
                $company_name,
                $otp_code
            );
            
            $formatted_phone = $this->format_phone_number($phone);
            
            $twilio_url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilio_sid}/Messages.json";
            
            $response = wp_remote_post($twilio_url, array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($this->twilio_sid . ':' . $this->twilio_token),
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ),
                'body' => array(
                    'From' => $this->twilio_from,
                    'To' => $formatted_phone,
                    'Body' => $message
                ),
                'timeout' => 30
            ));

            $success = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 201;
            
            // Log SMS OTP notification
            $this->log_notification(0, 0, 'sms', 'otp_verification', $formatted_phone, null, $message, $success ? 'sent' : 'failed');
            
            return $success;
        }
        
        return false;
    }
    
    /**
     * Verify OTP code
     */
    public function verify_otp($email, $phone, $otp_code) {
        global $wpdb;
        
        $otp_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_otp_verification 
             WHERE email = %s AND phone = %s AND otp_code = %s 
             AND expires_at > UTC_TIMESTAMP() AND is_verified = 0",
            $email, $phone, $otp_code
        ));
        
        if (!$otp_record) {
            // Increment attempts
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}hrb_otp_verification 
                 SET attempts = attempts + 1 
                 WHERE email = %s AND phone = %s",
                $email, $phone
            ));
            
            return false;
        }
        
        // Mark as verified
        $wpdb->update(
            $wpdb->prefix . 'hrb_otp_verification',
            array('is_verified' => 1),
            array('id' => $otp_record->id),
            array('%d'),
            array('%d')
        );
        
        return true;
    }
    
    /**
     * Get notification statistics
     */
    public function get_notification_stats($start_date = null, $end_date = null) {
        global $wpdb;
        
        $date_condition = '';
        $params = array();
        
        if ($start_date && $end_date) {
            $date_condition = 'WHERE created_at BETWEEN %s AND %s';
            $params[] = $start_date;
            $params[] = $end_date;
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_notifications,
                    SUM(CASE WHEN type = 'email' THEN 1 ELSE 0 END) as email_notifications,
                    SUM(CASE WHEN type = 'sms' THEN 1 ELSE 0 END) as sms_notifications,
                    SUM(CASE WHEN type = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp_notifications,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_notifications,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_notifications,
                    (SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) / COUNT(*) * 100) as success_rate
                FROM {$wpdb->prefix}hrb_notification_logs
                $date_condition";
        
        if (!empty($params)) {
            return $wpdb->get_row($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_row($sql);
        }
    }
    
    /**
     * Get notification logs
     */
    public function get_notification_logs($filters = array()) {
        global $wpdb;
        
        $where_conditions = array('1=1');
        $params = array();
        
        if (!empty($filters['booking_id'])) {
            $where_conditions[] = 'booking_id = %d';
            $params[] = intval($filters['booking_id']);
        }
        
        if (!empty($filters['type'])) {
            $where_conditions[] = 'type = %s';
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['start_date'])) {
            $where_conditions[] = 'created_at >= %s';
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_conditions[] = 'created_at <= %s';
            $params[] = $filters['end_date'];
        }
        
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
        $offset = isset($filters['offset']) ? intval($filters['offset']) : 0;
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT * FROM {$wpdb->prefix}hrb_notification_logs 
                WHERE $where_clause 
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Get or generate invoice for booking
     */
    private function get_or_generate_invoice($booking_id) {
        global $wpdb;
        
        // Check if invoice already exists
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_invoices WHERE booking_id = %d",
            $booking_id
        ));
        
        if ($invoice) {
            // Invoice exists, check if PDF exists
            if (!empty($invoice->pdf_file_path) && file_exists($invoice->pdf_file_path)) {
                return $invoice->pdf_file_path;
            } else {
                // Invoice exists but PDF is missing, try to generate PDF for existing invoice
                $invoice_generator = HRB_Invoice_Generator::getInstance();
                $pdf_path = $invoice_generator->generate_invoice_pdf($invoice->id);
                
                if (is_wp_error($pdf_path)) {
                    return false;
                }
                
                return $pdf_path;
            }
        }
        
        // Create invoice if it doesn't exist
        $booking_manager = HRB_Booking_Manager::getInstance();
        $invoice_id = $booking_manager->create_invoice($booking_id);
        
        if (is_wp_error($invoice_id)) {
            return false;
        }
        
        // Generate PDF
        $invoice_generator = HRB_Invoice_Generator::getInstance();
        $pdf_path = $invoice_generator->generate_invoice_pdf($invoice_id);
        
        if (is_wp_error($pdf_path)) {
            return false;
        }
        
        return $pdf_path;
    }

    /**
     * Get payment method label with translation
     *
     * @since 1.0.0
     * @param string $payment_method Payment method
     * @return string Translated payment method label
     */
    private function get_payment_method_label($payment_method) {
        return hrb_get_payment_method_label($payment_method);
    }

    /**
     * Get booking status label with translation
     *
     * @since 1.0.0
     * @param string $status Booking status
     * @return string Translated booking status label
     */
    private function get_booking_status_label($status) {
        return hrb_get_booking_status_label($status);
    }
    
    /**
     * Get payment status label for a booking
     *
     * @since 1.0.0
     * @param int $booking_id Booking ID
     * @return string Translated payment status label or 'Pending'
     */
    private function get_payment_status_label($booking_id) {
        $payment_manager = HRB_Payment_Manager::getInstance();
        $payment = $payment_manager->get_payment_by_booking($booking_id);
        
        if ($payment && isset($payment->status)) {
            return hrb_get_payment_status_label($payment->status);
        }
        
        // Return default if no payment record exists
        return hrb_get_payment_status_label('pending');
    }
}
?>