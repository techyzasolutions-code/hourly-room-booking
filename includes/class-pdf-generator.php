<?php

/**
 * PDF Generator for Hourly Room Booking Plugin
 * 
 * This class handles PDF generation for invoices using DomPDF
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_PDF_Generator {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Convert HTML content to PDF using DomPDF
     */
    public function html_to_pdf($html_content, $output_path) {
        // Include DomPDF autoloader
        $autoloader_path = plugin_dir_path(__FILE__) . '../vendor/autoload.php';
        if (!file_exists($autoloader_path)) {
            return file_put_contents($output_path, $html_content) !== false;
        }
        
        require_once $autoloader_path;
        
        try {
            // Configure Dompdf options
            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'Helvetica');
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('chroot', ABSPATH);
            
            // Instantiate dompdf with options
            $dompdf = new \Dompdf\Dompdf($options);
            
            $dompdf->loadHtml($html_content);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            // Get PDF content and save to file
            $pdf_output = $dompdf->output();
            $result = file_put_contents($output_path, $pdf_output);
            
            return $result !== false;
            
        } catch (Exception $e) {
            // Fallback: save as HTML file
            return file_put_contents($output_path, $html_content) !== false;
        }
    }
    
    /**
     * Generate invoice HTML content
     */
    public function generate_invoice_html($booking_id) {
        global $wpdb;
        
        // Get booking data
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return false;
        }
        
        // Get customer data
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_customers WHERE id = %d",
            $booking->customer_id
        ));
        
        // Get room data
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_rooms WHERE id = %d",
            $booking->room_id
        ));
        
        // Get extras
        $extras = $wpdb->get_results($wpdb->prepare(
            "SELECT be.*, e.name, e.price FROM {$wpdb->prefix}hrb_booking_extras be 
             LEFT JOIN {$wpdb->prefix}hrb_extras e ON be.extra_id = e.id 
             WHERE be.booking_id = %d",
            $booking_id
        ));
        
        // Get company logo
        $company_logo = get_option('hrb_company_logo', '');
        
        // Generate invoice HTML
        $html = $this->create_invoice_html($booking, $customer, $room, $extras, $company_logo);
        
        return $html;
    }
    
    /**
     * Create invoice HTML
     */
    private function create_invoice_html($booking, $customer, $room, $extras, $company_logo) {
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad($booking->id, 4, '0', STR_PAD_LEFT);
        $booking_date = date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date));
        $due_date = date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date . ' +30 days'));
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rechnung</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            color: #333;
            font-size: 12px;
        }
        .header { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 30px; 
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }
        .logo { 
            width: 150px; 
            height: 80px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            
        }
        .logo img {
            max-width: 100%;
            max-height: 100%;
        }
        .invoice-title { 
            text-align: right; 
        }
        .invoice-title h1 { 
            margin: 0; 
            font-size: 28px; 
            color: #2c3e50;
        }
        .invoice-details { 
            margin-top: 15px; 
        }
        .invoice-details p {
            margin: 5px 0;
        }
        .billing { 
            display: flex; 
            justify-content: space-between; 
            margin: 30px 0; 
            width: 100%;
        }
        .bill-from, .bill-to { 
            width: 48%; 
            display: inline-block;
            vertical-align: top;
        }
        .bill-from h3, .bill-to h3 { 
            margin: 0 0 15px 0; 
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .bill-from p, .bill-to p {
            margin: 5px 0;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 30px 0; 
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left; 
        }
        th { 
            background-color: #f8f9fa; 
            font-weight: bold;
            color: #2c3e50;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .totals { 
            text-align: right; 
            margin-top: 30px; 
            border-top: 2px solid #eee;
            padding-top: 20px;
        }
        .totals p {
            margin: 5px 0;
        }
        .total-row { 
            font-weight: bold; 
            font-size: 18px; 
            color: #2c3e50;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .footer { 
            margin-top: 50px; 
            text-align: center; 
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            ' . ($company_logo ? '<img src="' . esc_url($company_logo) . '" alt="Company Logo">' : '<div>LOGO</div>') . '
        </div>
        <div class="invoice-title">
            <h1>RECHNUNG</h1>
            <div class="invoice-details">
                <p><strong>Rechnungsnummer:</strong> ' . esc_html($invoice_number) . '</p>
                <p><strong>Datum:</strong> ' . esc_html($booking_date) . '</p>
                <p><strong>Fälligkeitsdatum:</strong> ' . esc_html($due_date) . '</p>
                <p><strong>Buchungsnummer:</strong> ' . esc_html($booking->booking_reference) . '</p>
            </div>
        </div>
    </div>

    <div class="billing">
        <div class="bill-from">
            <h3>Absender:</h3>
            <p><strong>' . esc_html(get_option('hrb_company_name', get_bloginfo('name'))) . '</strong></p>
            <p>' . nl2br(esc_html(get_option('hrb_company_address', ''))) . '</p>
            <p>Tel: ' . esc_html(get_option('hrb_company_phone', '')) . '</p>
            <p>E-Mail: ' . esc_html(get_option('hrb_company_email', get_option('admin_email'))) . '</p>
            ' . (get_option('hrb_company_vat_id', '') ? '<p>Umsatzsteuer ID: ' . esc_html(get_option('hrb_company_vat_id', '')) . '</p>' : '') . '
        </div>
        <div class="bill-to">
            <h3>Rechnungsempfänger:</h3>
            <p><strong>' . esc_html($customer->first_name ?? '') . ' ' . esc_html($customer->last_name ?? '') . '</strong></p>
            <p>' . esc_html($customer->email ?? '') . '</p>
            <p>Tel: ' . esc_html($customer->phone ?? '') . '</p>
            <p>' . esc_html($customer->address ?? '') . '</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Beschreibung</th>
                <th>Datum</th>
                <th>Gebuchte Uhrzeit</th>
                <th>Anzahl der Std.</th>
                <th>Kosten</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>' . esc_html($room->name . ' gebuchtes Zimmer') . '</td>
                <td>' . esc_html($booking_date) . '</td>
                <td>' . esc_html(date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->start_time)) . ' - ' . date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->end_time))) . '</td>
                <td>' . esc_html($booking->total_hours . ' Std.') . '</td>
                <td>' . esc_html(hrb_format_amount($booking->base_price)) . '</td>
            </tr>';

        // Add extras
        if (!empty($extras)) {
            foreach ($extras as $extra) {
                $html .= '<tr>
                    <td>' . esc_html($extra->name . ' (Extra)') . '</td>
                    <td>-</td>
                    <td>-</td>
                    <td>' . esc_html($extra->quantity . 'x') . '</td>
                    <td>' . esc_html(hrb_format_amount($extra->price * $extra->quantity)) . '</td>
                </tr>';
            }
        }

        // Add additional people if any
        if ($booking->extra_people > 0) {
            $html .= '<tr>
                <td>Zusätzliche Person</td>
                <td>-</td>
                <td>-</td>
                <td>' . esc_html($booking->extra_people) . '</td>
                <td>' . esc_html(hrb_format_amount($booking->extra_people_price)) . '</td>
            </tr>';
        }

        // Add PayPal fee as separate line item if payment method is PayPal
        if ($booking->payment_method === 'paypal' && $booking->paypal_fee > 0) {
            $html .= '<tr>
                <td>PayPal Gebühren (3%)</td>
                <td>-</td>
                <td>-</td>
                <td>1x</td>
                <td>' . esc_html(hrb_format_amount($booking->paypal_fee)) . '</td>
            </tr>';
        }

        $html .= '</tbody>
    </table>

    <div class="totals">
        <p>Netto: ' . esc_html(hrb_format_amount($booking->base_price + $booking->extras_price + $booking->extra_people_price)) . '</p>';
        
        if ($booking->tax_amount > 0) {
            $tax_rate = floatval(get_option('hrb_tax_rate', 19));
            $html .= '<p>zzgl. ' . esc_html($tax_rate) . '% MwSt.: ' . esc_html(hrb_format_amount($booking->tax_amount)) . '</p>';
        }
        
        $html .= '<p class="total-row">Gesamt: ' . esc_html(hrb_format_amount($booking->total_amount)) . '</p>
        <p class="total-row"><strong>Gesamtbetrag: ' . esc_html(hrb_format_amount($booking->total_amount)) . '</strong></p>
    </div>

    <div class="footer">
        <p>Vielen Dank für Ihr Vertrauen! Bei Fragen zu dieser Rechnung kontaktieren Sie uns bitte</p>
        <p>Telefon: ' . esc_html(get_option('hrb_company_phone', '')) . '</p>
        <p>E-Mail: ' . esc_html(get_option('hrb_company_email', get_option('admin_email'))) . '</p>
        <p><strong>Hinweis:</strong> ' . ($booking->payment_method === 'paypal' ? 
            'Die Zahlung ist bereits bei der Online-Buchung mit Paypal von Ihnen beglichen worden' : 
            'Der Rechnungsbetrag wird wunschgemäß vor Ort von Ihnen bezahlt') . '</p>
    </div>
</body>
</html>';

        return $html;
    }
}