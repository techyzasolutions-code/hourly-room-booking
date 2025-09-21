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
    public function send_notification($booking_id, $event) {
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        $notifications_sent = array();
        
        // Send email notification
        if (get_option('hrb_email_notifications', 1)) {
            $email_result = $this->send_email_notification($booking, $event);
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
    public function send_email_notification($booking, $event) {
        $template_data = $this->prepare_template_data($booking, $event);
        
        if (!$template_data) {
            return new WP_Error('template_error', __('Failed to prepare email template', 'hourly-room-booking'));
        }
        
        $to = $booking->email;
        $subject = $template_data['subject'];
        $message = $this->generate_email_html($template_data);
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('hrb_company_name', get_bloginfo('name')) . ' <' . get_option('hrb_company_email', get_option('admin_email')) . '>'
        );
        
        // Add BCC for admin notifications
        if (in_array($event, array('booking_confirmation', 'payment_confirmation'))) {
            $headers[] = 'Bcc: ' . get_option('hrb_admin_email', get_option('admin_email'));
        }
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        // Log notification
        $this->log_notification($booking->id, $booking->customer_id, 'email', $event, $to, $subject, $message, $sent ? 'sent' : 'failed');
        
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
    private function prepare_template_data($booking, $event) {
        global $wpdb;
        
        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($booking->room_id);
        
        if (!$room) {
            return false;
        }
        
        // Get template from database
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_email_templates WHERE template_key = %s AND is_active = 1",
            $event
        ));
        
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
            'booking_date' => date_i18n(get_option('date_format'), strtotime($booking->booking_date)),
            'start_time' => date_i18n(get_option('time_format'), strtotime($booking->start_time)),
            'end_time' => date_i18n(get_option('time_format'), strtotime($booking->end_time)),
            'duration' => $booking->total_hours . ' ' . _n('hour', 'hours', $booking->total_hours, 'hourly-room-booking'),
            'total_amount' => hrb_format_amount($booking->total_amount),
            'payment_method' => $booking->payment_method === 'paypal' ? 'PayPal' : __('On-site payment', 'hourly-room-booking'),
            'company_name' => $company_name,
            'company_phone' => get_option('hrb_company_phone', ''),
            'company_email' => get_option('hrb_company_email', get_option('admin_email')),
            'booking_url' => $booking_url,
            'cancel_url' => $booking_url . '&action=cancel',
            'booking_status' => ucfirst($booking->status)
        );
        
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
                '{booking_date}' => date_i18n(get_option('date_format'), strtotime($booking->booking_date)),
                '{start_time}' => date_i18n(get_option('time_format'), strtotime($booking->start_time)),
                '{end_time}' => date_i18n(get_option('time_format'), strtotime($booking->end_time)),
                '{duration}' => $booking->total_hours . ' ' . _n('hour', 'hours', $booking->total_hours, 'hourly-room-booking'),
                '{total_amount}' => hrb_format_amount($booking->total_amount),
                '{payment_method}' => $booking->payment_method === 'paypal' ? 'PayPal' : __('On-site payment', 'hourly-room-booking'),
                '{company_email}' => get_option('hrb_company_email', get_option('admin_email')),
                '{company_phone}' => get_option('hrb_company_phone', ''),
                '{booking_url}' => $booking_url,
                '{cancel_url}' => $booking_url . '&action=cancel',
                '{booking_status}' => ucfirst($booking->status)
            );
        }
        
        // Note: {heading} and {message} are handled separately to avoid circular references
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
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
            'booking_date' => date_i18n(get_option('date_format'), strtotime($booking->booking_date)),
            'start_time' => date_i18n(get_option('time_format'), strtotime($booking->start_time)),
            'end_time' => date_i18n(get_option('time_format'), strtotime($booking->end_time)),
            'duration' => $booking->total_hours . ' ' . _n('hour', 'hours', $booking->total_hours, 'hourly-room-booking'),
            'total_amount' => hrb_format_amount($booking->total_amount),
            'payment_method' => $booking->payment_method === 'paypal' ? 'PayPal' : __('On-site payment', 'hourly-room-booking'),
            'company_name' => $company_name,
            'company_phone' => get_option('hrb_company_phone', ''),
            'company_email' => get_option('hrb_company_email', get_option('admin_email')),
            'booking_url' => $booking_url,
            'cancel_url' => $booking_url . '&action=cancel',
            'booking_status' => ucfirst($booking->status)
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
            $subject = sprintf(__('Your verification code - %s', 'hourly-room-booking'), $company_name);
            $message = sprintf(
                __('Your verification code is: %s\n\nThis code will expire in 15 minutes.\n\n%s', 'hourly-room-booking'),
                $otp_code,
                $company_name
            );

            return wp_mail($email, $subject, $message);
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

            return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 201;
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
             AND expires_at > NOW() AND is_verified = 0",
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
}
?>