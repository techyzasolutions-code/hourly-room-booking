<?php
/**
 * Invoice PDF Generator
 * Handles PDF generation for invoices
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Invoice_Generator {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate PDF invoice
     */
    public function generate_invoice_pdf($invoice_id) {
        global $wpdb;
        
        // Get invoice data
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, b.*, r.name as room_name, c.first_name, c.last_name, c.email, c.phone, c.address, c.city, c.postal_code, c.country
             FROM {$wpdb->prefix}hrb_invoices i
             JOIN {$wpdb->prefix}hrb_bookings b ON i.booking_id = b.id
             JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
             LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
             WHERE i.id = %d",
            $invoice_id
        ));
        
        if (!$invoice) {
            return new WP_Error('invoice_not_found', __('Invoice not found', 'hourly-room-booking'));
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $invoice_dir = $upload_dir['basedir'] . '/hrb-invoices';
        if (!file_exists($invoice_dir)) {
            wp_mkdir_p($invoice_dir);
        }
        
        // Generate PDF filename
        $filename = 'invoice-' . $invoice->invoice_number . '.pdf';
        $file_path = $invoice_dir . '/' . $filename;
        
        // Generate HTML content using the PDF generator
        $pdf_generator = HRB_PDF_Generator::getInstance();
        $html_content = $pdf_generator->generate_invoice_html($invoice->booking_id);
        
        if (!$html_content || empty(trim($html_content))) {
            return new WP_Error('html_generation_failed', __('Failed to generate HTML content', 'hourly-room-booking'));
        }
        
        // Generate PDF filename
        $pdf_filename = 'invoice-' . $invoice->invoice_number . '.pdf';
        $pdf_path = $invoice_dir . '/' . $pdf_filename;
        
        // Try to convert HTML to PDF
        $pdf_success = $pdf_generator->html_to_pdf($html_content, $pdf_path);
        
        if ($pdf_success && file_exists($pdf_path) && filesize($pdf_path) > 0) {
            $file_path = $pdf_path;
        } else {
            // Fallback to HTML file
            $html_file = $invoice_dir . '/invoice-' . $invoice->invoice_number . '.html';
            $html_written = file_put_contents($html_file, $html_content);
            if ($html_written === false) {
                return new WP_Error('file_write_failed', __('Failed to create invoice file', 'hourly-room-booking'));
            }
            $file_path = $html_file;
        }
        
        // Verify file exists before updating database
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Invoice file was not created', 'hourly-room-booking'));
        }
        
        // Update invoice with PDF path
        $update_result = $wpdb->update(
            $wpdb->prefix . 'hrb_invoices',
            array('pdf_file_path' => $file_path),
            array('id' => $invoice_id),
            array('%s'),
            array('%d')
        );
        
        if ($update_result === false) {
            // Don't fail completely - file was created, just DB update failed
        }
        
        return $file_path;
    }
    
    
    /**
     * Get booking extras
     */
    private function get_booking_extras($booking_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT be.*, e.name, e.price
             FROM {$wpdb->prefix}hrb_booking_extras be
             JOIN {$wpdb->prefix}hrb_extras e ON be.extra_id = e.id
             WHERE be.booking_id = %d",
            $booking_id
        ));
    }
    
    /**
     * Get invoice by booking ID
     */
    public function get_invoice_by_booking($booking_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_invoices WHERE booking_id = %d",
            $booking_id
        ));
    }
    
    /**
     * Update invoice data with latest booking information and regenerate PDF
     */
    public function regenerate_invoice($booking_id) {
        global $wpdb;
        
        // Get current booking data
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        // Get existing invoice
        $invoice = $this->get_invoice_by_booking($booking_id);
        
        if (!$invoice) {
            return new WP_Error('invoice_not_found', __('Invoice not found for this booking', 'hourly-room-booking'));
        }
        
        $tax_rate = floatval(get_option('hrb_tax_rate', 19));
        
        // Get total amount from payment records (source of truth)
        $payment_handler = HRB_Payment_Handler::getInstance();
        $all_payments = $payment_handler->get_booking_payments($booking_id);
        
        $total_amount_from_payments = 0;
        foreach ($all_payments as $payment) {
            $total_amount_from_payments += floatval($payment->amount);
        }
        
        // Fallback to booking table if no payment records exist
        if ($total_amount_from_payments == 0) {
            $total_amount_from_payments = floatval($booking->total_amount);
        }
        
        // Update invoice data with latest booking information
        $invoice_data = array(
            'subtotal' => $booking->base_price + $booking->extra_people_price + $booking->extras_price, // Include all base prices
            'tax_rate' => $tax_rate,
            'tax_amount' => $booking->tax_amount,
            'total_amount' => $total_amount_from_payments, // Use payment records as source of truth
            'issue_date' => current_time('Y-m-d')
        );
        
        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_invoices',
            $invoice_data,
            array('id' => $invoice->id),
            array('%f', '%f', '%f', '%f', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('invoice_update_failed', __('Failed to update invoice data', 'hourly-room-booking') . ': ' . $wpdb->last_error);
        }
        
        // Regenerate PDF
        $pdf_path = $this->generate_invoice_pdf($invoice->id);
        
        if (is_wp_error($pdf_path)) {
            return $pdf_path;
        }
        
        if (empty($pdf_path)) {
            return new WP_Error('pdf_generation_failed', __('Failed to generate PDF - no file path returned', 'hourly-room-booking'));
        }
        
        // Send invoice via email
        $email_result = $this->send_invoice_email($booking_id, $pdf_path);
        
        if (is_wp_error($email_result)) {
            // Don't fail the regeneration if email fails
        }
        
        return $pdf_path;
    }
    
    /**
     * Send invoice via email using template system
     */
    public function send_invoice_email($booking_id, $invoice_path = null) {
        // Get booking data using booking manager (includes customer email)
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'hourly-room-booking'));
        }
        
        // Check if booking has email (skip anonymous bookings without email)
        if (empty($booking->email) || $booking->email === 'anonymous@example.com') {
            return new WP_Error('no_email', __('No email address available for this booking', 'hourly-room-booking'));
        }
        
        // Use notification manager to send email with template
        // Pass the invoice path so the notification manager can attach it
        $notification_manager = HRB_Notification_Manager::getInstance();
        $sent = $notification_manager->send_email_notification($booking, 'invoice_regenerated', array(
            'invoice_path' => $invoice_path
        ));
        
        if (!$sent) {
            return new WP_Error('email_send_failed', __('Failed to send invoice email', 'hourly-room-booking'));
        }
        
        return true;
    }
}
