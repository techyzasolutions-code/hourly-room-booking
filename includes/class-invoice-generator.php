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
             JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
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
        
        if (!$html_content) {
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
            file_put_contents($html_file, $html_content);
            $file_path = $html_file;
        }
        
        // Update invoice with PDF path
        $wpdb->update(
            $wpdb->prefix . 'hrb_invoices',
            array('pdf_file_path' => $file_path),
            array('id' => $invoice_id),
            array('%s'),
            array('%d')
        );
        
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
}
