<?php
/**
 * Admin Settings Page
 *
 * @package HourlyRoomBooking
 * @subpackage Admin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct script access denied.');
}

$settings = HRB_Settings::getInstance();
$settings_groups = $settings->get_settings_groups();
$current_settings = $settings->get_all();

// Get pages for dropdown
$pages = get_pages();
$page_options = ['0' => __('Select a page...', 'hourly-room-booking')];
foreach ($pages as $page) {
    $page_options[$page->ID] = $page->post_title;
}

// Timezone options
$timezone_options = [
    'Europe/Berlin' => __('Europe/Berlin', 'hourly-room-booking'),
    'Europe/London' => __('Europe/London', 'hourly-room-booking'),
    'Europe/Paris' => __('Europe/Paris', 'hourly-room-booking'),
    'America/New_York' => __('America/New_York', 'hourly-room-booking'),
    'America/Chicago' => __('America/Chicago', 'hourly-room-booking'),
    'America/Los_Angeles' => __('America/Los_Angeles', 'hourly-room-booking'),
    'Asia/Tokyo' => __('Asia/Tokyo', 'hourly-room-booking'),
    'Australia/Sydney' => __('Australia/Sydney', 'hourly-room-booking'),
];

// Helper class for field rendering
class HRB_Settings_Helper {

    public function get_setting_label(string $key): string {
        $labels = [
            'hrb_currency' => __('Currency', 'hourly-room-booking'),
            'hrb_date_format' => __('Date Format', 'hourly-room-booking'),
            'hrb_time_format' => __('Time Format', 'hourly-room-booking'),
            'hrb_timezone' => __('Timezone', 'hourly-room-booking'),
            'hrb_booking_advance_days' => __('Booking Advance Days', 'hourly-room-booking'),
            'hrb_cancellation_hours' => __('Cancellation Hours', 'hourly-room-booking'),
            'hrb_booking_start_time' => __('Booking Start Time', 'hourly-room-booking'),
            'hrb_booking_end_time' => __('Booking End Time', 'hourly-room-booking'),
            'hrb_default_booking_duration' => __('Default Booking Duration', 'hourly-room-booking'),
            'hrb_cooldown_minutes' => __('Cooldown Minutes', 'hourly-room-booking'),
            'hrb_enable_guest_booking' => __('Enable Guest Booking', 'hourly-room-booking'),
            'hrb_require_otp' => __('Require OTP Verification', 'hourly-room-booking'),
            'hrb_max_concurrent_bookings' => __('Max Concurrent Bookings', 'hourly-room-booking'),
            'hrb_price_2_hours' => __('2 Hours Price', 'hourly-room-booking'),
            'hrb_price_3_hours' => __('3 Hours Price', 'hourly-room-booking'),
            'hrb_price_4_hours' => __('4 Hours Price', 'hourly-room-booking'),
            'hrb_price_extra_hour' => __('Extra Hour Price', 'hourly-room-booking'),
            'hrb_extra_person_price' => __('Extra Person Price', 'hourly-room-booking'),
            'hrb_max_extra_people' => __('Max Extra People', 'hourly-room-booking'),
            'hrb_paypal_sandbox' => __('PayPal Sandbox Mode', 'hourly-room-booking'),
            'hrb_paypal_client_id' => __('PayPal Client ID', 'hourly-room-booking'),
            'hrb_paypal_client_secret' => __('PayPal Client Secret', 'hourly-room-booking'),
            'hrb_paypal_fee_percentage' => __('PayPal Fee Percentage', 'hourly-room-booking'),
            'hrb_email_notifications' => __('Email Notifications', 'hourly-room-booking'),
            'hrb_sms_notifications' => __('SMS Notifications', 'hourly-room-booking'),
            'hrb_whatsapp_notifications' => __('WhatsApp Notifications', 'hourly-room-booking'),
            'hrb_twilio_sid' => __('Twilio Account SID', 'hourly-room-booking'),
            'hrb_twilio_token' => __('Twilio Auth Token', 'hourly-room-booking'),
            'hrb_twilio_from' => __('Twilio Phone Number', 'hourly-room-booking'),
            'hrb_whatsapp_token' => __('WhatsApp Access Token', 'hourly-room-booking'),
            'hrb_whatsapp_phone_id' => __('WhatsApp Phone Number ID', 'hourly-room-booking'),
            'hrb_company_name' => __('Company Name', 'hourly-room-booking'),
            'hrb_company_address' => __('Company Address', 'hourly-room-booking'),
            'hrb_company_phone' => __('Company Phone', 'hourly-room-booking'),
            'hrb_company_email' => __('Company Email', 'hourly-room-booking'),
            'hrb_company_logo' => __('Company Logo', 'hourly-room-booking'),
            'hrb_admin_email' => __('Admin Email', 'hourly-room-booking'),
            'hrb_admin_email_notifications' => __('Admin Email Notifications', 'hourly-room-booking'),
            'hrb_staff_email' => __('Staff Email', 'hourly-room-booking'),
            'hrb_staff_email_notifications' => __('Staff Email Notifications', 'hourly-room-booking'),
            'hrb_pricing_label' => __('Pricing Label', 'hourly-room-booking'),
            'hrb_terms_page' => __('Terms & Conditions Page', 'hourly-room-booking'),
            'hrb_privacy_page' => __('Privacy Policy Page', 'hourly-room-booking'),
            'hrb_invoice_counter' => __('Invoice Counter', 'hourly-room-booking'),
            'hrb_tax_rate' => __('Tax Rate (%)', 'hourly-room-booking'),
            'hrb_plugin_language' => __('Plugin Language', 'hourly-room-booking'),
        // Customizable Labels
        'hrb_use_custom_labels' => __('Use Custom Labels', 'hourly-room-booking'),
        'hrb_label_booking_date' => __('Booking Date Label', 'hourly-room-booking'),
        'hrb_label_duration' => __('Duration Label', 'hourly-room-booking'),
        'hrb_label_start_time' => __('Start Time Label', 'hourly-room-booking'),
        'hrb_label_extra_people' => __('Extra People Label', 'hourly-room-booking'),
        'hrb_label_special_requests' => __('Special Requests Label', 'hourly-room-booking'),
        'hrb_label_next_button' => __('Next Button Text', 'hourly-room-booking'),
        'hrb_label_previous_button' => __('Previous Button Text', 'hourly-room-booking'),
        'hrb_label_book_now_button' => __('Book Now Button Text', 'hourly-room-booking'),
        'hrb_label_search_button' => __('Search Button Text', 'hourly-room-booking'),
        'hrb_label_clear_all_button' => __('Clear All Button Text', 'hourly-room-booking'),
        'hrb_label_select_duration' => __('Select Duration Text', 'hourly-room-booking'),
        'hrb_label_select_date' => __('Select Date Text', 'hourly-room-booking'),
        'hrb_label_loading_message' => __('Loading Message', 'hourly-room-booking'),
        'hrb_label_no_slots_message' => __('No Time Slots Message', 'hourly-room-booking'),
        // Additional booking form labels
        'hrb_label_your_details' => __('Your Details Label', 'hourly-room-booking'),
        'hrb_label_welcome_back' => __('Welcome Back Message', 'hourly-room-booking'),
        'hrb_label_first_name' => __('First Name Label', 'hourly-room-booking'),
        'hrb_label_last_name' => __('Last Name Label', 'hourly-room-booking'),
        'hrb_label_email_address' => __('Email Address Label', 'hourly-room-booking'),
        'hrb_label_phone_number' => __('Phone Number Label', 'hourly-room-booking'),
        'hrb_label_company' => __('Company Label', 'hourly-room-booking'),
        'hrb_label_company_optional' => __('Company Optional Label', 'hourly-room-booking'),
        'hrb_label_contact_verification' => __('Contact Verification Label', 'hourly-room-booking'),
        'hrb_label_verification_required' => __('Verification Required Message', 'hourly-room-booking'),
        'hrb_label_email_to_verify' => __('Email to Verify Label', 'hourly-room-booking'),
        // Booking confirmation labels
        'hrb_label_booking_confirmation' => __('Booking Confirmation Label', 'hourly-room-booking'),
        'hrb_label_booking_details' => __('Booking Details Label', 'hourly-room-booking'),
        'hrb_label_room' => __('Room Label', 'hourly-room-booking'),
        'hrb_label_date' => __('Date Label', 'hourly-room-booking'),
        'hrb_label_time' => __('Time Label', 'hourly-room-booking'),
        'hrb_label_duration_hours' => __('Duration Hours Label', 'hourly-room-booking'),
        'hrb_label_additional_people' => __('Additional People Label', 'hourly-room-booking'),
        'hrb_label_extras' => __('Extras Label', 'hourly-room-booking'),
        'hrb_label_customer_details' => __('Customer Details Label', 'hourly-room-booking'),
        'hrb_label_name' => __('Name Label', 'hourly-room-booking'),
        'hrb_label_email' => __('Email Label', 'hourly-room-booking'),
        'hrb_label_phone' => __('Phone Label', 'hourly-room-booking'),
        'hrb_label_payment' => __('Payment Label', 'hourly-room-booking'),
        'hrb_label_terms_conditions' => __('Terms & Conditions Text', 'hourly-room-booking'),
        'hrb_label_complete_booking' => __('Complete Booking Button', 'hourly-room-booking'),
        'hrb_label_what_happens_next' => __('What Happens Next Label', 'hourly-room-booking'),
        'hrb_label_confirmation_email' => __('Confirmation Email Message', 'hourly-room-booking'),
        'hrb_label_payment_onsite' => __('Payment Onsite Message', 'hourly-room-booking'),
        'hrb_label_arrive_early' => __('Arrive Early Message', 'hourly-room-booking'),
        'hrb_label_contact_modify' => __('Contact Modify Message', 'hourly-room-booking'),
        'hrb_label_total_amount' => __('Total Amount Label', 'hourly-room-booking'),
        'hrb_label_payment_method' => __('Payment Method Label', 'hourly-room-booking'),
        ];

        return $labels[$key] ?? $key;
    }

    public function get_setting_description(string $key): string {
        $descriptions = [
            'hrb_currency' => __('Select the currency for all payments and pricing', 'hourly-room-booking'),
            'hrb_date_format' => __('PHP date format for displaying dates', 'hourly-room-booking'),
            'hrb_time_format' => __('PHP time format for displaying times', 'hourly-room-booking'),
            'hrb_timezone' => __('Timezone for booking calculations', 'hourly-room-booking'),
            'hrb_booking_advance_days' => __('How many days in advance can bookings be made', 'hourly-room-booking'),
            'hrb_cancellation_hours' => __('Hours before booking when cancellation is allowed', 'hourly-room-booking'),
            'hrb_booking_start_time' => __('Earliest time when bookings can start (24-hour format, e.g., 08:00)', 'hourly-room-booking'),
            'hrb_booking_end_time' => __('Latest time when bookings can end (24-hour format, e.g., 20:00)', 'hourly-room-booking'),
            'hrb_default_booking_duration' => __('Default booking duration in hours', 'hourly-room-booking'),
            'hrb_cooldown_minutes' => __('Minutes between bookings for room preparation', 'hourly-room-booking'),
            'hrb_enable_guest_booking' => __('Allow non-registered users to make bookings', 'hourly-room-booking'),
            'hrb_require_otp' => __('Require phone verification for new customers', 'hourly-room-booking'),
            'hrb_max_concurrent_bookings' => __('Maximum concurrent bookings per customer', 'hourly-room-booking'),
            'hrb_paypal_sandbox' => __('Use PayPal sandbox for testing', 'hourly-room-booking'),
            'hrb_paypal_fee_percentage' => __('PayPal transaction fee percentage', 'hourly-room-booking'),
            'hrb_email_notifications' => __('Send email notifications for bookings', 'hourly-room-booking'),
            'hrb_sms_notifications' => __('Send SMS notifications via Twilio', 'hourly-room-booking'),
            'hrb_whatsapp_notifications' => __('Send WhatsApp notifications', 'hourly-room-booking'),
            'hrb_invoice_counter' => __('Starting number for invoice numbering', 'hourly-room-booking'),
            'hrb_tax_rate' => __('Tax rate percentage for invoices', 'hourly-room-booking'),
            'hrb_company_logo' => __('Company logo that will appear on invoices (recommended size: 200x100px)', 'hourly-room-booking'),
            'hrb_pricing_label' => __('Custom label to display with pricing (e.g., "Starting from", "From", etc.)', 'hourly-room-booking'),
            'hrb_admin_email_notifications' => __('Send booking notifications to admin email', 'hourly-room-booking'),
            'hrb_staff_email' => __('Staff email address for notifications', 'hourly-room-booking'),
            'hrb_staff_email_notifications' => __('Send booking notifications to staff email', 'hourly-room-booking'),
            'hrb_plugin_language' => __('Select the language for the plugin interface. This overrides the WordPress site language for this plugin only.', 'hourly-room-booking'),
        // Customizable Labels Descriptions
        'hrb_use_custom_labels' => __('Enable to use custom labels instead of translated text. When disabled, labels will be automatically translated based on the selected language.', 'hourly-room-booking'),
        'hrb_label_booking_date' => __('Custom label for booking date field', 'hourly-room-booking'),
        'hrb_label_duration' => __('Custom label for duration field', 'hourly-room-booking'),
        'hrb_label_start_time' => __('Custom label for start time field', 'hourly-room-booking'),
        'hrb_label_extra_people' => __('Custom label for extra people field', 'hourly-room-booking'),
        'hrb_label_special_requests' => __('Custom label for special requests field', 'hourly-room-booking'),
        'hrb_label_next_button' => __('Custom text for next button', 'hourly-room-booking'),
        'hrb_label_previous_button' => __('Custom text for previous button', 'hourly-room-booking'),
        'hrb_label_book_now_button' => __('Custom text for book now button', 'hourly-room-booking'),
        'hrb_label_search_button' => __('Custom text for search button', 'hourly-room-booking'),
        'hrb_label_clear_all_button' => __('Custom text for clear all button', 'hourly-room-booking'),
        'hrb_label_select_duration' => __('Custom text for select duration placeholder', 'hourly-room-booking'),
        'hrb_label_select_date' => __('Custom text for select date placeholder', 'hourly-room-booking'),
        'hrb_label_loading_message' => __('Custom loading message text', 'hourly-room-booking'),
        'hrb_label_no_slots_message' => __('Custom message when no time slots available', 'hourly-room-booking'),
        // Additional booking form labels descriptions
        'hrb_label_your_details' => __('Custom label for your details section', 'hourly-room-booking'),
        'hrb_label_welcome_back' => __('Custom welcome back message for returning users', 'hourly-room-booking'),
        'hrb_label_first_name' => __('Custom label for first name field', 'hourly-room-booking'),
        'hrb_label_last_name' => __('Custom label for last name field', 'hourly-room-booking'),
        'hrb_label_email_address' => __('Custom label for email address field', 'hourly-room-booking'),
        'hrb_label_phone_number' => __('Custom label for phone number field', 'hourly-room-booking'),
        'hrb_label_company' => __('Custom label for company field', 'hourly-room-booking'),
        'hrb_label_company_optional' => __('Custom label for optional company field', 'hourly-room-booking'),
        'hrb_label_contact_verification' => __('Custom label for contact verification section', 'hourly-room-booking'),
        'hrb_label_verification_required' => __('Custom message for verification requirement', 'hourly-room-booking'),
        'hrb_label_email_to_verify' => __('Custom label for email verification field', 'hourly-room-booking'),
        // Booking confirmation labels descriptions
        'hrb_label_booking_confirmation' => __('Custom label for booking confirmation title', 'hourly-room-booking'),
        'hrb_label_booking_details' => __('Custom label for booking details section', 'hourly-room-booking'),
        'hrb_label_room' => __('Custom label for room field', 'hourly-room-booking'),
        'hrb_label_date' => __('Custom label for date field', 'hourly-room-booking'),
        'hrb_label_time' => __('Custom label for time field', 'hourly-room-booking'),
        'hrb_label_duration_hours' => __('Custom label for duration field in confirmation', 'hourly-room-booking'),
        'hrb_label_additional_people' => __('Custom label for additional people field', 'hourly-room-booking'),
        'hrb_label_extras' => __('Custom label for extras field', 'hourly-room-booking'),
        'hrb_label_customer_details' => __('Custom label for customer details section', 'hourly-room-booking'),
        'hrb_label_name' => __('Custom label for name field', 'hourly-room-booking'),
        'hrb_label_email' => __('Custom label for email field', 'hourly-room-booking'),
        'hrb_label_phone' => __('Custom label for phone field', 'hourly-room-booking'),
        'hrb_label_payment' => __('Custom label for payment field', 'hourly-room-booking'),
        'hrb_label_terms_conditions' => __('Custom text for terms and conditions checkbox', 'hourly-room-booking'),
        'hrb_label_complete_booking' => __('Custom text for complete booking button', 'hourly-room-booking'),
        'hrb_label_what_happens_next' => __('Custom label for what happens next section', 'hourly-room-booking'),
        'hrb_label_confirmation_email' => __('Custom message about confirmation email', 'hourly-room-booking'),
        'hrb_label_payment_onsite' => __('Custom message about onsite payment', 'hourly-room-booking'),
        'hrb_label_arrive_early' => __('Custom message about arriving early', 'hourly-room-booking'),
        'hrb_label_contact_modify' => __('Custom message about contacting for modifications', 'hourly-room-booking'),
        'hrb_label_total_amount' => __('Custom label for total amount field', 'hourly-room-booking'),
        'hrb_label_payment_method' => __('Custom label for payment method field', 'hourly-room-booking'),
        ];

        return $descriptions[$key] ?? '';
    }

    public function get_setting_field_type(string $key): string {
        $boolean_fields = ['hrb_paypal_sandbox', 'hrb_enable_guest_booking', 'hrb_require_otp',
                          'hrb_email_notifications', 'hrb_sms_notifications', 'hrb_whatsapp_notifications',
                          'hrb_admin_email_notifications', 'hrb_staff_email_notifications'];

        $number_fields = ['hrb_booking_advance_days', 'hrb_cancellation_hours', 'hrb_default_booking_duration',
                         'hrb_cooldown_minutes', 'hrb_max_concurrent_bookings', 'hrb_price_2_hours',
                         'hrb_price_3_hours', 'hrb_price_4_hours', 'hrb_price_extra_hour',
                         'hrb_extra_person_price', 'hrb_max_extra_people', 'hrb_paypal_fee_percentage',
                         'hrb_invoice_counter', 'hrb_tax_rate'];

        $email_fields = ['hrb_company_email', 'hrb_admin_email', 'hrb_staff_email'];
        $password_fields = ['hrb_paypal_client_secret', 'hrb_twilio_token', 'hrb_whatsapp_token'];
        $textarea_fields = ['hrb_company_address'];
        $file_fields = ['hrb_company_logo'];
        $select_fields = ['hrb_timezone', 'hrb_terms_page', 'hrb_privacy_page', 'hrb_currency'];

        if (in_array($key, $boolean_fields)) return 'checkbox';
        if (in_array($key, $number_fields)) return 'number';
        if (in_array($key, $email_fields)) return 'email';
        if (in_array($key, $password_fields)) return 'password';
        if (in_array($key, $textarea_fields)) return 'textarea';
        if (in_array($key, $file_fields)) return 'file';
        if (in_array($key, $select_fields)) return 'select';

        return 'text';
    }

    public function get_setting_options(string $key, array $page_options, array $timezone_options): array {
        switch ($key) {
            case 'hrb_currency':
                return [
                    'USD' => __('US Dollar ($)', 'hourly-room-booking'),
                    'EUR' => __('Euro (€)', 'hourly-room-booking')
                ];
            case 'hrb_timezone':
                return $timezone_options;
            case 'hrb_terms_page':
            case 'hrb_privacy_page':
                return $page_options;
            default:
                return [];
        }
    }

    public function get_setting_placeholder(string $key): string {
        $placeholders = [
            'hrb_paypal_client_id' => __('Enter PayPal Client ID', 'hourly-room-booking'),
            'hrb_paypal_client_secret' => __('Enter PayPal Client Secret', 'hourly-room-booking'),
            'hrb_twilio_sid' => __('Enter Twilio Account SID', 'hourly-room-booking'),
            'hrb_twilio_token' => __('Enter Twilio Auth Token', 'hourly-room-booking'),
            'hrb_twilio_from' => __('Enter Twilio Phone Number', 'hourly-room-booking'),
            'hrb_whatsapp_token' => __('Enter WhatsApp Access Token', 'hourly-room-booking'),
            'hrb_whatsapp_phone_id' => __('Enter WhatsApp Phone Number ID', 'hourly-room-booking'),
            'hrb_company_name' => __('Enter company name', 'hourly-room-booking'),
            'hrb_company_address' => __('Enter company address', 'hourly-room-booking'),
            'hrb_company_phone' => __('Enter company phone number', 'hourly-room-booking'),
            'hrb_company_email' => __('Enter company email', 'hourly-room-booking'),
            'hrb_company_logo' => __('Upload company logo for invoices', 'hourly-room-booking'),
            'hrb_admin_email' => __('Enter admin email', 'hourly-room-booking'),
            'hrb_staff_email' => __('Enter staff email', 'hourly-room-booking'),
            'hrb_pricing_label' => __('Enter pricing label (e.g., "Starting from")', 'hourly-room-booking'),
        ];

        return $placeholders[$key] ?? '';
    }

    public function get_setting_min(string $key): string {
        $mins = [
            'hrb_booking_advance_days' => '1',
            'hrb_cancellation_hours' => '1',
            'hrb_default_booking_duration' => '1',
            'hrb_cooldown_minutes' => '0',
            'hrb_max_concurrent_bookings' => '1',
            'hrb_price_2_hours' => '0',
            'hrb_price_3_hours' => '0',
            'hrb_price_4_hours' => '0',
            'hrb_price_extra_hour' => '0',
            'hrb_extra_person_price' => '0',
            'hrb_max_extra_people' => '0',
            'hrb_paypal_fee_percentage' => '0',
            'hrb_invoice_counter' => '1',
            'hrb_tax_rate' => '0',
        ];

        return $mins[$key] ?? '0';
    }

    public function get_setting_max(string $key): string {
        $maxs = [
            'hrb_booking_advance_days' => '365',
            'hrb_cancellation_hours' => '168',
            'hrb_default_booking_duration' => '12',
            'hrb_cooldown_minutes' => '120',
            'hrb_max_concurrent_bookings' => '10',
            'hrb_max_extra_people' => '50',
            'hrb_paypal_fee_percentage' => '10',
            'hrb_tax_rate' => '100',
        ];

        return $maxs[$key] ?? '';
    }

    public function get_setting_step(string $key): string {
        $float_fields = ['hrb_price_2_hours', 'hrb_price_3_hours', 'hrb_price_4_hours',
                        'hrb_price_extra_hour', 'hrb_extra_person_price', 'hrb_paypal_fee_percentage', 'hrb_tax_rate'];

        return in_array($key, $float_fields) ? '0.01' : '1';
    }

}

$helper = new HRB_Settings_Helper();
?>

<div class="wrap hrb-settings-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="hrb-settings-header">
        <div class="hrb-settings-actions">
            <button type="button" class="button button-primary hrb-save-settings">
                <span class="dashicons dashicons-yes"></span>
                <?php esc_html_e('Save Changes', 'hourly-room-booking'); ?>
            </button>
            <button type="button" class="button hrb-reset-settings" data-confirm="<?php esc_attr_e('Are you sure you want to reset all settings to defaults? This action cannot be undone.', 'hourly-room-booking'); ?>">
                <span class="dashicons dashicons-undo"></span>
                <?php esc_html_e('Reset to Defaults', 'hourly-room-booking'); ?>
            </button>
        </div>
    </div>

    <form id="hrb-settings-form" method="post">
        <?php wp_nonce_field('hrb_admin_nonce', 'hrb_admin_nonce'); ?>

        <div class="hrb-settings-tabs-wrapper">
            <div class="hrb-settings-tabs">
                <?php $active_tab = true; ?>
                <?php foreach ($settings_groups as $group_key => $group): ?>
                    <button type="button" class="hrb-tab-button<?php echo $active_tab ? ' active' : ''; ?>" data-tab="<?php echo esc_attr($group_key); ?>">
                        <?php echo esc_html($group['title']); ?>
                    </button>
                    <?php $active_tab = false; ?>
                <?php endforeach; ?>
            </div>

            <div class="hrb-settings-content">
                <?php $active_content = true; ?>
                <?php foreach ($settings_groups as $group_key => $group): ?>
                    <div class="hrb-tab-content<?php echo $active_content ? ' active' : ''; ?>" id="hrb-tab-<?php echo esc_attr($group_key); ?>">
                        <div class="hrb-settings-group">
                            <h2><?php echo esc_html($group['title']); ?></h2>
                            <div class="hrb-settings-fields">
                                <?php foreach ($group['settings'] as $setting_key): ?>
                                    <?php
                                    $field_value = $current_settings[$setting_key] ?? '';
                                    $field_label = $helper->get_setting_label($setting_key);
                                    $field_description = $helper->get_setting_description($setting_key);
                                    $field_type = $helper->get_setting_field_type($setting_key);
                                    
                                    // Debug: Log logo field value
                                    if ($setting_key === 'hrb_company_logo') {
                                        error_log('HRB: Logo field value: ' . $field_value);
                                    }
                                    ?>

                                    <div class="hrb-setting-field" data-setting="<?php echo esc_attr($setting_key); ?>">
                                        <label for="<?php echo esc_attr($setting_key); ?>">
                                            <?php echo esc_html($field_label); ?>
                                        </label>

                                        <?php if ($field_type === 'checkbox'): ?>
                                            <div class="hrb-checkbox-wrapper">
                                                <input type="hidden" name="settings[<?php echo esc_attr($setting_key); ?>]" value="0">
                                                <input
                                                    type="checkbox"
                                                    id="<?php echo esc_attr($setting_key); ?>"
                                                    name="settings[<?php echo esc_attr($setting_key); ?>]"
                                                    value="1"
                                                    <?php checked($field_value, 1); ?>
                                                >
                                                <label for="<?php echo esc_attr($setting_key); ?>" class="hrb-checkbox-label">
                                                    <?php echo esc_html($field_description); ?>
                                                </label>
                                            </div>

                                        <?php elseif ($field_type === 'select'): ?>
                                            <select id="<?php echo esc_attr($setting_key); ?>" name="settings[<?php echo esc_attr($setting_key); ?>]">
                                                <?php
                                                $options = $helper->get_setting_options($setting_key, $page_options, $timezone_options);
                                                foreach ($options as $value => $label):
                                                ?>
                                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($field_value, $value); ?>>
                                                        <?php echo esc_html($label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                        <?php elseif ($setting_key === 'hrb_plugin_language'): ?>
                                            <select id="<?php echo esc_attr($setting_key); ?>" name="settings[<?php echo esc_attr($setting_key); ?>]">
                                                <?php
                                                // Check which language files actually exist
                                                $available_languages = [
                                                    'en_US' => 'English (US)',
                                                    'de_DE' => 'Deutsch (German)'
                                                ];
                                                
                                                foreach ($available_languages as $code => $name):
                                                    $mo_file = HRB_PLUGIN_DIR . 'languages/hourly-room-booking-' . $code . '.mo';
                                                    if (file_exists($mo_file)):
                                                ?>
                                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($field_value, $code); ?>>
                                                        <?php echo esc_html($name); ?>
                                                    </option>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </select>

                                        <?php elseif ($setting_key === 'hrb_use_custom_labels'): ?>
                                            <select id="<?php echo esc_attr($setting_key); ?>" name="settings[<?php echo esc_attr($setting_key); ?>]">
                                                <option value="0" <?php selected($field_value, '0'); ?>><?php _e('Off', 'hourly-room-booking'); ?></option>
                                                <option value="1" <?php selected($field_value, '1'); ?>><?php _e('On', 'hourly-room-booking'); ?></option>
                                            </select>

                                        <?php elseif ($field_type === 'textarea'): ?>
                                            <textarea
                                                id="<?php echo esc_attr($setting_key); ?>"
                                                name="settings[<?php echo esc_attr($setting_key); ?>]"
                                                rows="4"
                                                cols="50"
                                                placeholder="<?php echo esc_attr($helper->get_setting_placeholder($setting_key)); ?>"
                                            ><?php echo esc_textarea($field_value); ?></textarea>

                                        <?php elseif ($field_type === 'password'): ?>
                                            <input
                                                type="password"
                                                id="<?php echo esc_attr($setting_key); ?>"
                                                name="settings[<?php echo esc_attr($setting_key); ?>]"
                                                value="<?php echo esc_attr($field_value); ?>"
                                                placeholder="<?php echo esc_attr($helper->get_setting_placeholder($setting_key)); ?>"
                                                autocomplete="new-password"
                                            >

                                        <?php elseif ($field_type === 'file'): ?>
                                            <div class="hrb-media-upload-wrapper">
                                                <?php if (!empty($field_value)): ?>
                                                    <div class="hrb-current-file">
                                                        <img src="<?php echo esc_url($field_value); ?>" alt="Current logo" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px; margin-bottom: 10px;">
                                                        <p><strong><?php _e('Current logo:', 'hourly-room-booking'); ?></strong></p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <input
                                                    type="hidden"
                                                    id="<?php echo esc_attr($setting_key); ?>"
                                                    name="settings[<?php echo esc_attr($setting_key); ?>]"
                                                    value="<?php echo esc_attr($field_value); ?>"
                                                >
                                                
                                                <button type="button" class="button hrb-media-upload-btn" data-target="<?php echo esc_attr($setting_key); ?>">
                                                    <?php echo !empty($field_value) ? __('Change Logo', 'hourly-room-booking') : __('Select Logo from Library', 'hourly-room-booking'); ?>
                                                </button>
                                                
                                                <?php if (!empty($field_value)): ?>
                                                    <button type="button" class="button hrb-media-remove-btn" data-target="<?php echo esc_attr($setting_key); ?>" style="margin-left: 10px;">
                                                        <?php _e('Remove Logo', 'hourly-room-booking'); ?>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <p class="description"><?php _e('Select an image from your media library. Recommended size: 200x100px', 'hourly-room-booking'); ?></p>
                                            </div>

                                        <?php else: ?>
                                            <input
                                                type="<?php echo esc_attr($field_type); ?>"
                                                id="<?php echo esc_attr($setting_key); ?>"
                                                name="settings[<?php echo esc_attr($setting_key); ?>]"
                                                value="<?php echo esc_attr($field_value); ?>"
                                                placeholder="<?php echo esc_attr($helper->get_setting_placeholder($setting_key)); ?>"
                                                <?php if ($field_type === 'number'): ?>
                                                    min="<?php echo esc_attr($helper->get_setting_min($setting_key)); ?>"
                                                    max="<?php echo esc_attr($helper->get_setting_max($setting_key)); ?>"
                                                    step="<?php echo esc_attr($helper->get_setting_step($setting_key)); ?>"
                                                <?php endif; ?>
                                            >
                                        <?php endif; ?>

                                        <?php if ($field_description && $field_type !== 'checkbox'): ?>
                                            <p class="description"><?php echo esc_html($field_description); ?></p>
                                        <?php endif; ?>

                                        <div class="hrb-field-error" style="display: none;"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php $active_content = false; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var $form = $('#hrb-settings-form');
    var $saveButton = $('.hrb-save-settings');
    var $resetButton = $('.hrb-reset-settings');

    // Tab switching
    $('.hrb-tab-button').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');

        $('.hrb-tab-button').removeClass('active');
        $(this).addClass('active');

        $('.hrb-tab-content').removeClass('active');
        $('#hrb-tab-' + tabId).addClass('active');
    });

    // Save settings
    $saveButton.on('click', function(e) {
        e.preventDefault();
        saveSettings();
    });

    // Reset settings
    $resetButton.on('click', function(e) {
        e.preventDefault();
        var confirmMessage = $(this).data('confirm');
        if (confirm(confirmMessage)) {
            resetSettings();
        }
    });

    function saveSettings() {
        $saveButton.prop('disabled', true).find('span').removeClass('dashicons-yes').addClass('dashicons-update-alt');
        $('.hrb-field-error').hide();

        // Only collect data from the currently active tab
        var formData = new FormData();
        var activeTab = $('.hrb-tab-content.active');

        // Get all setting fields from the form
        var settings = {};

        // First, collect all checkbox values (including unchecked ones)
        activeTab.find('input[type="checkbox"]').each(function() {
            var $field = $(this);
            var name = $field.attr('name');
            if (name && name.startsWith('settings[')) {
                var settingKey = name.replace('settings[', '').replace(']', '');
                settings[settingKey] = $field.is(':checked') ? '1' : '0';
            }
        });

        // Then collect all other field types (including hidden inputs for file fields)
        activeTab.find('input:not([type="checkbox"]), select, textarea').each(function() {
            var $field = $(this);
            var name = $field.attr('name');
            var type = $field.attr('type');

            if (name && name.startsWith('settings[')) {
                var settingKey = name.replace('settings[', '').replace(']', '');

                if (type === 'radio') {
                    if ($field.is(':checked')) {
                        settings[settingKey] = $field.val();
                    }
                } else {
                    settings[settingKey] = $field.val() || '';
                }
            }
        });

        // Debug: Log settings being saved
        console.log('Settings being saved:', settings);
        
        // Add all settings to formData
        $.each(settings, function(key, value) {
            formData.append('settings[' + key + ']', value);
        });

        formData.append('action', 'hrb_save_settings');
        formData.append('nonce', $('#hrb_admin_nonce').val());

        // Send settings data

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                } else {
                    showNotice(response.data.message || 'Settings could not be saved.', 'error');

                    // Show field-specific errors
                    if (response.data.errors) {
                        $.each(response.data.errors, function(field, error) {
                            var $field = $('.hrb-setting-field[data-setting="' + field + '"] .hrb-field-error');
                            $field.text(error).show();
                        });
                    }
                }
            },
            error: function() {
                showNotice('An error occurred while saving settings.', 'error');
            },
            complete: function() {
                $saveButton.prop('disabled', false).find('span').removeClass('dashicons-update-alt').addClass('dashicons-yes');
            }
        });
    }

    function resetSettings() {
        $resetButton.prop('disabled', true).find('span').removeClass('dashicons-undo').addClass('dashicons-update-alt');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hrb_reset_settings',
                nonce: $('#hrb_admin_nonce').val(),
                keys: []
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    location.reload(); // Reload to show default values
                } else {
                    showNotice(response.data.message || 'Settings could not be reset.', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while resetting settings.', 'error');
            },
            complete: function() {
                $resetButton.prop('disabled', false).find('span').removeClass('dashicons-update-alt').addClass('dashicons-undo');
            }
        });
    }

    function showNotice(message, type) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.hrb-settings-wrap h1').after($notice);

        // Auto-dismiss success notices
        if (type === 'success') {
            setTimeout(function() {
                $notice.fadeOut();
            }, 3000);
        }
    }
    });
    
    // Media library integration for logo upload
    jQuery(document).ready(function($) {
        var mediaUploader;
        
        // Handle media library button click
        $('.hrb-media-upload-btn').on('click', function(e) {
            e.preventDefault();
            
            var targetInput = $(this).data('target');
            
            // If the uploader object has already been created, reopen the dialog
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            // Create the media frame
            mediaUploader = wp.media({
                title: 'Select Company Logo',
                button: {
                    text: 'Use this logo'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            // When an image is selected, run a callback
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#' + targetInput).val(attachment.url);
                
                // Update the preview
                var wrapper = $('#' + targetInput).closest('.hrb-media-upload-wrapper');
                if (wrapper.find('.hrb-current-file').length === 0) {
                    wrapper.prepend('<div class="hrb-current-file"><img src="' + attachment.url + '" alt="Current logo" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px; margin-bottom: 10px;"><p><strong>Current logo:</strong></p></div>');
                } else {
                    wrapper.find('.hrb-current-file img').attr('src', attachment.url);
                }
                
                // Update button text
                $(this).text('Change Logo');
                
                // Show remove button if not already visible
                if (wrapper.find('.hrb-media-remove-btn').length === 0) {
                    wrapper.find('.hrb-media-upload-btn').after('<button type="button" class="button hrb-media-remove-btn" data-target="' + targetInput + '" style="margin-left: 10px;">Remove Logo</button>');
                }
            });
            
            // Open the uploader dialog
            mediaUploader.open();
        });
        
        // Handle remove button click
        $(document).on('click', '.hrb-media-remove-btn', function(e) {
            e.preventDefault();
            
            var targetInput = $(this).data('target');
            $('#' + targetInput).val('');
            
            // Remove preview
            $('#' + targetInput).closest('.hrb-media-upload-wrapper').find('.hrb-current-file').remove();
            
            // Update button text
            $('#' + targetInput).closest('.hrb-media-upload-wrapper').find('.hrb-media-upload-btn').text('Select Logo from Library');
            
            // Hide remove button
            $(this).remove();
        });
    });
</script>

<style>
/* Modern Professional Settings Page Styling */
.hrb-settings-wrap {
    background: #f8f9fa;
    min-height: 100vh;
    padding: 24px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.hrb-settings-wrap h1 {
    background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%);
    color: white;
    margin: 0 0 32px 0;
    padding: 32px;
    border-radius: 16px;
    font-size: 2.5rem;
    font-weight: 700;
    letter-spacing: -0.025em;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    box-shadow: 0 8px 32px rgba(139, 92, 246, 0.15);
    position: relative;
    overflow: hidden;
}

.hrb-settings-wrap h1::before {
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

.hrb-settings-header {
    margin-bottom: 32px;
    padding: 24px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.hrb-settings-actions {
    display: flex;
    gap: 16px;
}

.hrb-settings-actions .button {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.hrb-settings-actions .button-primary {
    background: #8b5cf6;
    border-color: #8b5cf6;
    color: white;
}

.hrb-settings-actions .button-primary:hover {
    background: #7c3aed;
    border-color: #7c3aed;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.hrb-settings-actions .button:not(.button-primary) {
    background: white;
    border: 2px solid #e5e7eb;
    color: #374151;
}

.hrb-settings-actions .button:not(.button-primary):hover {
    border-color: #d1d5db;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.hrb-settings-actions .dashicons {
    margin-right: 8px;
}

.hrb-settings-tabs-wrapper {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.hrb-settings-tabs {
    display: flex;
    background: #f8f9fa;
    border-bottom: 2px solid #e5e7eb;
    margin: 0;
    overflow-x: auto;
}

.hrb-tab-button {
    background: none;
    border: none;
    padding: 20px 24px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    font-size: 0.9rem;
    font-weight: 600;
    color: #6b7280;
    transition: all 0.3s ease;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.hrb-tab-button:hover {
    background: #f3f4f6;
    color: #374151;
}

.hrb-tab-button.active {
    border-bottom-color: #8b5cf6;
    color: #8b5cf6;
    background: white;
}

.hrb-settings-content {
    padding: 32px;
}

.hrb-tab-content {
    display: none;
}

.hrb-tab-content.active {
    display: block;
}

.hrb-settings-group h2 {
    margin: 0 0 32px 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 16px;
    position: relative;
}

.hrb-settings-group h2::before {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 60px;
    height: 2px;
    background: #8b5cf6;
}

.hrb-settings-fields {
    display: grid;
    gap: 24px;
}

.hrb-setting-field {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 24px;
    align-items: start;
    padding: 24px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}

.hrb-setting-field:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.hrb-setting-field label {
    font-weight: 600;
    color: #374151;
    font-size: 0.95rem;
    line-height: 1.5;
    margin-top: 8px;
}

.hrb-setting-field input,
.hrb-setting-field select,
.hrb-setting-field textarea {
    width: 100%;
    max-width: 400px;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
    transition: border-color 0.2s ease;
}

.hrb-setting-field input:focus,
.hrb-setting-field select:focus,
.hrb-setting-field textarea:focus {
    border-color: #8b5cf6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.hrb-setting-field input[type="password"] {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-color: #f59e0b;
}

.hrb-setting-field input[type="password"]:focus {
    border-color: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
}

.hrb-setting-field textarea {
    resize: vertical;
    min-height: 100px;
}

.hrb-checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.hrb-checkbox-wrapper input[type="checkbox"] {
    width: 20px;
    height: 20px;
    max-width: 20px;
    min-width: 20px;
    border: 2px solid #e5e7eb;
    border-radius: 4px;
    position: relative;
    appearance: none;
    cursor: pointer;
    transition: all 0.2s ease;
    background: white;
    flex-shrink: 0;
}

.hrb-checkbox-wrapper input[type="checkbox"]:checked {
    background: #8b5cf6;
    border-color: #8b5cf6;
}

.hrb-checkbox-wrapper input[type="checkbox"]:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: bold;
    line-height: 1;
    display: block;
}

.hrb-checkbox-wrapper input[type="checkbox"]:focus {
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.hrb-checkbox-label {
    font-weight: 500 !important;
    color: #6b7280 !important;
    margin: 0 !important;
    cursor: pointer;
}

.hrb-field-error {
    color: #dc2626;
    font-size: 0.85rem;
    font-weight: 500;
    grid-column: 2;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    padding: 8px 12px;
    margin-top: 8px;
}

.description {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 8px;
    grid-column: 2;
    line-height: 1.5;
}

/* Notice Styling */
.notice {
    margin: 16px 0;
    padding: 16px 20px;
    border-radius: 8px;
    border-left: 4px solid;
    font-weight: 500;
}

.notice-success {
    background: #f0fdf4;
    border-left-color: #10b981;
    color: #065f46;
}

.notice-error {
    background: #fef2f2;
    border-left-color: #ef4444;
    color: #7f1d1d;
}

/* Responsive Design */
@media (max-width: 768px) {
    .hrb-settings-wrap {
        padding: 16px;
        margin: -10px;
    }

    .hrb-settings-wrap h1 {
        font-size: 2rem;
        padding: 24px;
        margin-bottom: 24px;
    }

    .hrb-settings-header {
        padding: 20px;
    }

    .hrb-settings-actions {
        flex-direction: column;
        gap: 12px;
    }

    .hrb-settings-content {
        padding: 20px;
    }

    .hrb-setting-field {
        grid-template-columns: 1fr;
        gap: 16px;
        padding: 20px;
    }

    .hrb-field-error,
    .description {
        grid-column: 1;
    }

    .hrb-settings-tabs {
        flex-wrap: wrap;
    }

    .hrb-tab-button {
        flex: 1;
        min-width: 120px;
        padding: 16px 20px;
    }

    .hrb-setting-field input,
    .hrb-setting-field select,
    .hrb-setting-field textarea {
        max-width: none;
    }
}

/* Print Styles */
@media print {
    .hrb-settings-wrap {
        background: white;
        margin: 0;
        padding: 0;
    }

    .hrb-settings-wrap h1 {
        background: white !important;
        color: black !important;
        box-shadow: none;
    }

    .hrb-settings-header,
    .hrb-settings-actions {
        display: none;
    }

    .hrb-settings-tabs-wrapper {
        box-shadow: none;
        border: 1px solid #ddd;
    }

    .hrb-tab-button {
        display: none;
    }

    .hrb-tab-content {
        display: block !important;
    }
}
</style>