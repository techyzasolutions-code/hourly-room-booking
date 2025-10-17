<?php
/**
 * Settings management class for the Hourly Room Booking plugin
 *
 * @package HourlyRoomBooking
 * @subpackage Settings
 * @since 1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit('Direct script access denied.');
}

/**
 * HRB_Settings Class
 *
 * Handles plugin settings management including default values,
 * validation, sanitization, and configuration management.
 *
 * @since 1.0.0
 */
class HRB_Settings {

    /**
     * Single instance of the class
     *
     * @since 1.0.0
     * @var HRB_Settings|null
     */
    private static ?HRB_Settings $instance = null;

    /**
     * Settings cache
     *
     * @since 1.0.0
     * @var array
     */
    private array $settings_cache = [];

    /**
     * Default settings configuration
     *
     * @since 1.0.0
     * @var array
     */
    private array $default_settings = [
        // Core plugin settings
        'hrb_version' => [
            'default' => '1.0.0',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_currency' => [
            'default' => '�',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_date_format' => [
            'default' => 'd.m.Y',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_time_format' => [
            'default' => 'H:i',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_timezone' => [
            'default' => 'Europe/Berlin',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],

        // Booking settings
        'hrb_booking_advance_days' => [
            'default' => 30,
            'type' => 'integer',
            'sanitize' => 'absint',
            'min' => 1,
            'max' => 365
        ],
        'hrb_cancellation_hours' => [
            'default' => 24,
            'type' => 'integer',
            'sanitize' => 'absint',
            'min' => 1,
            'max' => 168
        ],
        'hrb_booking_start_time' => [
            'default' => '08:00',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_booking_end_time' => [
            'default' => '20:00',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_default_booking_duration' => [
            'default' => 2,
            'type' => 'integer',
            'sanitize' => 'absint',
            'min' => 1,
            'max' => 12
        ],
        'hrb_cooldown_minutes' => [
            'default' => 30,
            'type' => 'integer',
            'sanitize' => 'absint',
            'min' => 0,
            'max' => 120
        ],
        'hrb_enable_guest_booking' => [
            'default' => 1,
            'type' => 'boolean',
            'sanitize' => 'absint'
        ],
        'hrb_require_otp' => [
            'default' => 1,
            'type' => 'boolean',
            'sanitize' => 'absint'
        ],
        'hrb_max_concurrent_bookings' => [
            'default' => 3,
            'type' => 'integer',
            'sanitize' => 'absint',
            'min' => 1,
            'max' => 10
        ],

        // Pricing settings
        'hrb_price_2_hours' => [
            'default' => 45,
            'type' => 'float',
            'sanitize' => 'floatval',
            'min' => 0
        ],
        'hrb_price_3_hours' => [
            'default' => 50,
            'type' => 'float',
            'sanitize' => 'floatval',
            'min' => 0
        ],
        'hrb_price_4_hours' => [
            'default' => 60,
            'type' => 'float',
            'sanitize' => 'floatval',
            'min' => 0
        ],
        'hrb_price_extra_hour' => [
            'default' => 10,
            'type' => 'float',
            'sanitize' => 'floatval',
            'min' => 0
        ],
        'hrb_extra_person_price' => [
            'default' => 15,
            'type' => 'float',
            'sanitize' => 'floatval',
            'min' => 0
        ],
        'hrb_max_extra_people' => [
            'default' => 10,
            'type' => 'integer',
            'sanitize' => 'absint',
            'min' => 0,
            'max' => 50
        ],

        // PayPal settings
        'hrb_paypal_sandbox' => [
            'default' => 1,
            'type' => 'boolean',
            'sanitize' => 'absint'
        ],
        'hrb_paypal_client_id' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_paypal_client_secret' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_paypal_fee_percentage' => [
            'default' => 3,
            'type' => 'float',
            'sanitize' => 'floatval',
            'min' => 0,
            'max' => 10
        ],

        // Notification settings
        'hrb_email_notifications' => [
            'default' => 1,
            'type' => 'boolean',
            'sanitize' => 'absint'
        ],
        'hrb_sms_notifications' => [
            'default' => 0,
            'type' => 'boolean',
            'sanitize' => 'absint'
        ],
        'hrb_whatsapp_notifications' => [
            'default' => 0,
            'type' => 'boolean',
            'sanitize' => 'absint'
        ],

        // Twilio settings
        'hrb_twilio_sid' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_twilio_token' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_twilio_from' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],

        // WhatsApp settings
        'hrb_whatsapp_token' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_whatsapp_phone_id' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],

        // Company information
        'hrb_company_name' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_company_address' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_textarea_field'
        ],
        'hrb_company_phone' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_company_email' => [
            'default' => '',
            'type' => 'email',
            'sanitize' => 'sanitize_email'
        ],
        'hrb_company_vat_id' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_company_logo' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'esc_url_raw'
        ],
        'hrb_admin_email' => [
            'default' => '',
            'type' => 'email',
            'sanitize' => 'sanitize_email'
        ],
        'hrb_staff_email' => [
            'default' => '',
            'type' => 'email',
            'sanitize' => 'sanitize_email'
        ],
        'hrb_pricing_label' => [
            'default' => '',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_admin_email_notifications' => [
            'default' => 1,
            'type' => 'boolean',
            'sanitize' => 'absint'
        ],
        'hrb_staff_email_notifications' => [
            'default' => 0,
            'type' => 'boolean',
            'sanitize' => 'absint'
        ],

        // Legal pages
        'hrb_terms_page' => [
            'default' => 0,
            'type' => 'integer',
            'sanitize' => 'absint'
        ],
        'hrb_privacy_page' => [
            'default' => 0,
            'type' => 'integer',
            'sanitize' => 'absint'
        ],

        // Invoice settings
        'hrb_invoice_counter' => [
            'default' => 1,
            'type' => 'integer',
            'sanitize' => 'absint',
            'min' => 1
        ],
        'hrb_tax_rate' => [
            'default' => 19,
            'type' => 'float',
            'sanitize' => 'floatval',
            'min' => 0,
            'max' => 100
        ],
        // Customizable Labels Defaults
        'hrb_label_booking_date' => [
            'default' => 'Booking Date',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_duration' => [
            'default' => 'Duration',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_start_time' => [
            'default' => 'Start Time',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_extra_people' => [
            'default' => 'Extra People',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_special_requests' => [
            'default' => 'Special Requests',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_next_button' => [
            'default' => 'Next',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_previous_button' => [
            'default' => 'Previous',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_book_now_button' => [
            'default' => 'Book Now',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_search_button' => [
            'default' => 'Search Rooms',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_clear_all_button' => [
            'default' => 'Clear All',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_select_duration' => [
            'default' => 'Select duration',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_select_date' => [
            'default' => 'Select date',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_loading_message' => [
            'default' => 'Loading...',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_no_slots_message' => [
            'default' => 'No time slots available for the selected date and duration.',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_use_custom_labels' => [
            'default' => false,
            'type' => 'boolean',
            'sanitize' => 'rest_sanitize_boolean'
        ],
        // Additional booking form labels
        'hrb_label_your_details' => [
            'default' => 'Your Details',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_welcome_back' => [
            'default' => 'Welcome back! Your details have been pre-filled from your account. You can modify them if needed.',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_first_name' => [
            'default' => 'First Name',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_last_name' => [
            'default' => 'Last Name',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_email_address' => [
            'default' => 'Email Address',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_phone_number' => [
            'default' => 'Phone Number',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_company' => [
            'default' => 'Company',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_company_optional' => [
            'default' => 'Company (Optional)',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_contact_verification' => [
            'default' => 'Contact Verification',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_verification_required' => [
            'default' => 'Verification Required Please verify your email address before proceeding. We will send you a verification code.',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_email_to_verify' => [
            'default' => 'Email to verify:',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        // Booking confirmation labels
        'hrb_label_booking_confirmation' => [
            'default' => 'Booking Confirmation',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_booking_details' => [
            'default' => 'Booking Details',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_room' => [
            'default' => 'Room',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_date' => [
            'default' => 'Date',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_time' => [
            'default' => 'Time',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_duration_hours' => [
            'default' => 'Duration',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_additional_people' => [
            'default' => 'Additional People',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_extras' => [
            'default' => 'Extras',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_customer_details' => [
            'default' => 'Customer Details',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_name' => [
            'default' => 'Name',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_email' => [
            'default' => 'Email',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_phone' => [
            'default' => 'Phone',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_payment' => [
            'default' => 'Payment',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_terms_conditions' => [
            'default' => 'I accept the Terms & Conditions and Privacy Policy.',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_complete_booking' => [
            'default' => 'Complete Booking',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_what_happens_next' => [
            'default' => 'What happens next?',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_confirmation_email' => [
            'default' => 'You will receive a confirmation email shortly with all the booking details.',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_payment_onsite' => [
            'default' => 'Please bring payment (cash or card) when you arrive for your booking.',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_arrive_early' => [
            'default' => 'Please arrive a few minutes before your booking time.',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_contact_modify' => [
            'default' => 'If you need to cancel or modify your booking, please contact us as soon as possible.',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_total_amount' => [
            'default' => 'Total Amount',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        'hrb_label_payment_method' => [
            'default' => 'Payment Method',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ],
        // Language Settings
        'hrb_plugin_language' => [
            'default' => 'en_US',
            'type' => 'string',
            'sanitize' => 'sanitize_text_field'
        ]
    ];

    /**
     * Get customizable label
     *
     * @since 1.0.0
     * @param string $label_key The label key
     * @return string The customized label or default
     */
    public function get_label(string $label_key): string {
        // Check if we should use custom labels or translations
        $use_custom_labels = $this->get('hrb_use_custom_labels', false);
        
        if ($use_custom_labels) {
            // Use custom labels from database
            $label_value = $this->get($label_key);
            if (!empty($label_value)) {
                return $label_value;
            }
        }
        
        // Always use translated text (respects language setting)
        switch ($label_key) {
            case 'hrb_label_booking_date':
                return __('Booking Date', 'hourly-room-booking');
            case 'hrb_label_duration':
                return __('Duration', 'hourly-room-booking');
            case 'hrb_label_start_time':
                return __('Start Time', 'hourly-room-booking');
            case 'hrb_label_extra_people':
                return __('Extra People', 'hourly-room-booking');
            case 'hrb_label_special_requests':
                return __('Special Requests', 'hourly-room-booking');
            case 'hrb_label_next_button':
                return __('Next', 'hourly-room-booking');
            case 'hrb_label_previous_button':
                return __('Previous', 'hourly-room-booking');
            case 'hrb_label_book_now_button':
                return __('Book Now', 'hourly-room-booking');
            case 'hrb_label_search_button':
                return __('Search Rooms', 'hourly-room-booking');
            case 'hrb_label_clear_all_button':
                return __('Clear All', 'hourly-room-booking');
            case 'hrb_label_select_duration':
                return __('Select duration', 'hourly-room-booking');
            case 'hrb_label_select_date':
                return __('Select date', 'hourly-room-booking');
            case 'hrb_label_loading_message':
                return __('Loading...', 'hourly-room-booking');
            case 'hrb_label_no_slots_message':
                return __('No time slots available for the selected date and duration.', 'hourly-room-booking');
            // Additional booking form labels
            case 'hrb_label_your_details':
                return __('Your Details', 'hourly-room-booking');
            case 'hrb_label_welcome_back':
                return __('Welcome back! Your details have been pre-filled from your account. You can modify them if needed.', 'hourly-room-booking');
            case 'hrb_label_first_name':
                return __('First Name', 'hourly-room-booking');
            case 'hrb_label_last_name':
                return __('Last Name', 'hourly-room-booking');
            case 'hrb_label_email_address':
                return __('Email Address', 'hourly-room-booking');
            case 'hrb_label_phone_number':
                return __('Phone Number', 'hourly-room-booking');
            case 'hrb_label_company':
                return __('Company', 'hourly-room-booking');
            case 'hrb_label_company_optional':
                return __('Company (Optional)', 'hourly-room-booking');
            case 'hrb_label_contact_verification':
                return __('Contact Verification', 'hourly-room-booking');
            case 'hrb_label_verification_required':
                return __('Verification Required Please verify your email address before proceeding. We will send you a verification code.', 'hourly-room-booking');
            case 'hrb_label_email_to_verify':
                return __('Email to verify:', 'hourly-room-booking');
            // Booking confirmation labels
            case 'hrb_label_booking_confirmation':
                return __('Booking Confirmation', 'hourly-room-booking');
            case 'hrb_label_booking_details':
                return __('Booking Details', 'hourly-room-booking');
            case 'hrb_label_room':
                return __('Room', 'hourly-room-booking');
            case 'hrb_label_date':
                return __('Date', 'hourly-room-booking');
            case 'hrb_label_time':
                return __('Time', 'hourly-room-booking');
            case 'hrb_label_duration_hours':
                return __('Duration', 'hourly-room-booking');
            case 'hrb_label_additional_people':
                return __('Additional People', 'hourly-room-booking');
            case 'hrb_label_extras':
                return __('Extras', 'hourly-room-booking');
            case 'hrb_label_customer_details':
                return __('Customer Details', 'hourly-room-booking');
            case 'hrb_label_name':
                return __('Name', 'hourly-room-booking');
            case 'hrb_label_email':
                return __('Email', 'hourly-room-booking');
            case 'hrb_label_phone':
                return __('Phone', 'hourly-room-booking');
            case 'hrb_label_payment':
                return __('Payment', 'hourly-room-booking');
            case 'hrb_label_terms_conditions':
                return __('I accept the Terms & Conditions and Privacy Policy.', 'hourly-room-booking');
            case 'hrb_label_complete_booking':
                return __('Complete Booking', 'hourly-room-booking');
            case 'hrb_label_what_happens_next':
                return __('What happens next?', 'hourly-room-booking');
            case 'hrb_label_confirmation_email':
                return __('You will receive a confirmation email shortly with all the booking details.', 'hourly-room-booking');
            case 'hrb_label_payment_onsite':
                return __('Please bring payment (cash or card) when you arrive for your booking.', 'hourly-room-booking');
            case 'hrb_label_arrive_early':
                return __('Please arrive a few minutes before your booking time.', 'hourly-room-booking');
            case 'hrb_label_contact_modify':
                return __('If you need to cancel or modify your booking, please contact us as soon as possible.', 'hourly-room-booking');
            case 'hrb_label_total_amount':
                return __('Total Amount', 'hourly-room-booking');
            case 'hrb_label_payment_method':
                return __('Payment Method', 'hourly-room-booking');
            default:
                return $default_value;
        }
    }

    /**
     * Reload text domain when language setting changes
     *
     * @since 1.0.0
     */
    public function reload_textdomain(): void {
        $domain = 'hourly-room-booking';
        $locale = get_option('hrb_plugin_language', 'en_US');
        
        // Unload current text domain
        unload_textdomain($domain);
        
        // Load new text domain with specific locale
        $mo_file = HRB_PLUGIN_DIR . 'languages/hourly-room-booking-' . $locale . '.mo';
        if (file_exists($mo_file)) {
            load_textdomain($domain, $mo_file);
        } else {
            // Fallback to default
            load_plugin_textdomain(
                $domain,
                false,
                dirname(HRB_PLUGIN_BASENAME) . '/languages/'
            );
        }
    }

    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return HRB_Settings
     */
    public static function getInstance(): HRB_Settings {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks(): void {
        add_action('init', [$this, 'maybe_initialize_settings']);
        add_action('wp_ajax_hrb_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_hrb_reset_settings', [$this, 'ajax_reset_settings']);
    }

    /**
     * Initialize settings if they don't exist
     *
     * @since 1.0.0
     */
    public function maybe_initialize_settings(): void {
        if (!get_option('hrb_settings_initialized')) {
            $this->initialize_default_settings();
            update_option('hrb_settings_initialized', true);
        }
    }

    /**
     * Initialize default settings
     *
     * @since 1.0.0
     */
    private function initialize_default_settings(): void {
        foreach ($this->default_settings as $key => $config) {
            if (!get_option($key)) {
                $default_value = $config['default'];

                // Set dynamic defaults
                switch ($key) {
                    case 'hrb_company_name':
                        $default_value = get_bloginfo('name');
                        break;
                    case 'hrb_company_email':
                    case 'hrb_admin_email':
                        $default_value = get_option('admin_email');
                        break;
                }

                add_option($key, $default_value);
            }
        }
    }

    /**
     * Get setting value
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed $default Default value if setting doesn't exist
     * @return mixed Setting value
     */
    public function get(string $key, $default = null) {
        // Check cache first
        if (isset($this->settings_cache[$key])) {
            return $this->settings_cache[$key];
        }

        // Get from database
        $value = get_option($key, $default);

        // If no explicit default provided, use config default
        if ($value === $default && isset($this->default_settings[$key])) {
            $value = $this->default_settings[$key]['default'];
        }

        // Cache the value
        $this->settings_cache[$key] = $value;

        return $value;
    }

    /**
     * Set setting value
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public function set(string $key, $value): bool {
        // Sanitize value
        $sanitized_value = $this->sanitize_setting($key, $value);

        // Get current value to check if it's actually changing
        $current_value = get_option($key, null);

        // Update in database
        $result = update_option($key, $sanitized_value);

        // WordPress update_option returns false if value hasn't changed
        // We should consider this a success, not a failure
        // Use loose comparison to handle type differences (string "30" vs int 30)
        if (!$result && ($current_value == $sanitized_value || strval($current_value) === strval($sanitized_value))) {
            $result = true; // No change needed, consider it successful
        }

        // Update cache
        if ($result) {
            $this->settings_cache[$key] = $sanitized_value;
        } else {
            // If update failed, clear cache to force fresh retrieval
            unset($this->settings_cache[$key]);
        }

        return $result;
    }

    /**
     * Get multiple settings
     *
     * @since 1.0.0
     * @param array $keys Setting keys
     * @return array Settings values
     */
    public function get_multiple(array $keys): array {
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $this->get($key);
        }
        return $settings;
    }

    /**
     * Get all settings
     *
     * @since 1.0.0
     * @return array All settings
     */
    public function get_all(): array {
        $settings = [];
        foreach ($this->default_settings as $key => $config) {
            $settings[$key] = $this->get($key);
        }
        return $settings;
    }

    /**
     * Sanitize setting value
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized value
     */
    private function sanitize_setting(string $key, $value) {
        if (!isset($this->default_settings[$key])) {
            return $value;
        }

        $config = $this->default_settings[$key];

        // Apply sanitization function
        if (isset($config['sanitize']) && function_exists($config['sanitize'])) {
            $value = call_user_func($config['sanitize'], $value);
        }

        // Apply min/max constraints
        if ($config['type'] === 'integer' || $config['type'] === 'float') {
            if (isset($config['min']) && $value < $config['min']) {
                $value = $config['min'];
            }
            if (isset($config['max']) && $value > $config['max']) {
                $value = $config['max'];
            }
        }

        return $value;
    }

    /**
     * Validate setting value
     *
     * @since 1.0.0
     * @param string $key Setting key
     * @param mixed $value Value to validate
     * @return bool|string True if valid, error message if not
     */
    public function validate_setting(string $key, $value) {
        if (!isset($this->default_settings[$key])) {
            return __('Unknown setting key', 'hourly-room-booking');
        }

        $config = $this->default_settings[$key];

        // Type validation
        switch ($config['type']) {
            case 'email':
                if (!empty($value) && !is_email($value)) {
                    return __('Invalid email address', 'hourly-room-booking');
                }
                break;

            case 'integer':
                if ($value !== '' && (!is_numeric($value) || floatval($value) != intval($value))) {
                    return __('Must be a whole number', 'hourly-room-booking');
                }
                break;

            case 'float':
                if ($value !== '' && !is_numeric($value)) {
                    return __('Must be a number', 'hourly-room-booking');
                }
                break;

            case 'boolean':
                // Allow empty values (treat as false) and common boolean representations
                if ($value !== '' && !in_array($value, [0, 1, '0', '1', true, false, 'on', 'off'], true)) {
                    return __('Must be true or false', 'hourly-room-booking');
                }
                break;
        }

        // Range validation
        if (($config['type'] === 'integer' || $config['type'] === 'float') && is_numeric($value)) {
            if (isset($config['min']) && $value < $config['min']) {
                return sprintf(__('Must be at least %s', 'hourly-room-booking'), $config['min']);
            }
            if (isset($config['max']) && $value > $config['max']) {
                return sprintf(__('Must be no more than %s', 'hourly-room-booking'), $config['max']);
            }
        }

        return true;
    }

    /**
     * Reset settings to defaults
     *
     * @since 1.0.0
     * @param array $keys Specific keys to reset, or empty for all
     * @return bool Success status
     */
    public function reset(array $keys = []): bool {
        if (empty($keys)) {
            $keys = array_keys($this->default_settings);
        }

        $success = true;
        foreach ($keys as $key) {
            if (isset($this->default_settings[$key])) {
                $default_value = $this->default_settings[$key]['default'];

                // Handle dynamic defaults
                switch ($key) {
                    case 'hrb_company_name':
                        $default_value = get_bloginfo('name');
                        break;
                    case 'hrb_company_email':
                    case 'hrb_admin_email':
                        $default_value = get_option('admin_email');
                        break;
                }

                if (!update_option($key, $default_value)) {
                    $success = false;
                }

                // Update cache
                $this->settings_cache[$key] = $default_value;
            }
        }

        return $success;
    }

    /**
     * Get settings groups for admin interface
     *
     * @since 1.0.0
     * @return array Settings groups
     */
    public function get_settings_groups(): array {
        return [
            'general' => [
                'title' => __('General Settings', 'hourly-room-booking'),
                'settings' => [
                    'hrb_currency',
                    'hrb_date_format',
                    'hrb_time_format',
                    'hrb_timezone',
                    'hrb_pricing_label',
                    'hrb_plugin_language'
                ]
            ],
            'booking' => [
                'title' => __('Booking Settings', 'hourly-room-booking'),
                'settings' => [
                    'hrb_booking_advance_days',
                    'hrb_cancellation_hours',
                    'hrb_booking_start_time',
                    'hrb_booking_end_time',
                    'hrb_default_booking_duration',
                    'hrb_cooldown_minutes',
                    'hrb_enable_guest_booking',
                    'hrb_require_otp',
                    'hrb_max_concurrent_bookings'
                ]
            ],
            'pricing' => [
                'title' => __('Pricing Settings', 'hourly-room-booking'),
                'settings' => [
                    'hrb_price_2_hours',
                    'hrb_price_3_hours',
                    'hrb_price_4_hours',
                    'hrb_price_extra_hour',
                    'hrb_extra_person_price',
                    'hrb_max_extra_people'
                ]
            ],
            'paypal' => [
                'title' => __('PayPal Settings', 'hourly-room-booking'),
                'settings' => [
                    'hrb_paypal_sandbox',
                    'hrb_paypal_client_id',
                    'hrb_paypal_client_secret',
                    'hrb_paypal_fee_percentage'
                ]
            ],
            'notifications' => [
                'title' => __('Notification Settings', 'hourly-room-booking'),
                'settings' => [
                    'hrb_email_notifications',
                    'hrb_sms_notifications',
                    'hrb_whatsapp_notifications',
                    'hrb_twilio_sid',
                    'hrb_twilio_token',
                    'hrb_twilio_from',
                    'hrb_whatsapp_token',
                    'hrb_whatsapp_phone_id'
                ]
            ],
            'company' => [
                'title' => __('Company Information', 'hourly-room-booking'),
                'settings' => [
                    'hrb_company_name',
                    'hrb_company_address',
                    'hrb_company_phone',
                    'hrb_company_email',
                    'hrb_company_vat_id',
                    'hrb_company_logo',
                    'hrb_admin_email',
                    'hrb_admin_email_notifications',
                    'hrb_staff_email',
                    'hrb_staff_email_notifications'
                ]
            ],
            'legal' => [
                'title' => __('Legal & Invoice Settings', 'hourly-room-booking'),
                'settings' => [
                    'hrb_terms_page',
                    'hrb_privacy_page',
                    'hrb_invoice_counter',
                    'hrb_tax_rate'
                ]
            ],
        'labels' => [
            'title' => __('Customizable Labels & Texts', 'hourly-room-booking'),
            'settings' => [
                'hrb_use_custom_labels',
                'hrb_label_booking_date',
                'hrb_label_duration',
                'hrb_label_start_time',
                'hrb_label_extra_people',
                'hrb_label_special_requests',
                'hrb_label_next_button',
                'hrb_label_previous_button',
                'hrb_label_book_now_button',
                'hrb_label_search_button',
                'hrb_label_clear_all_button',
                'hrb_label_select_duration',
                'hrb_label_select_date',
                'hrb_label_loading_message',
                'hrb_label_no_slots_message',
                // Additional booking form labels
                'hrb_label_your_details',
                'hrb_label_welcome_back',
                'hrb_label_first_name',
                'hrb_label_last_name',
                'hrb_label_email_address',
                'hrb_label_phone_number',
                'hrb_label_company',
                'hrb_label_company_optional',
                'hrb_label_contact_verification',
                'hrb_label_verification_required',
                'hrb_label_email_to_verify',
                // Booking confirmation labels
                'hrb_label_booking_confirmation',
                'hrb_label_booking_details',
                'hrb_label_room',
                'hrb_label_date',
                'hrb_label_time',
                'hrb_label_duration_hours',
                'hrb_label_additional_people',
                'hrb_label_extras',
                'hrb_label_customer_details',
                'hrb_label_name',
                'hrb_label_email',
                'hrb_label_phone',
                'hrb_label_payment',
                'hrb_label_terms_conditions',
                'hrb_label_complete_booking',
                'hrb_label_what_happens_next',
                'hrb_label_confirmation_email',
                'hrb_label_payment_onsite',
                'hrb_label_arrive_early',
                'hrb_label_contact_modify',
                'hrb_label_total_amount',
                'hrb_label_payment_method'
            ]
        ]
        ];
    }

    /**
     * AJAX handler for saving settings
     *
     * @since 1.0.0
     */
    public function ajax_save_settings(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        $settings = $_POST['settings'] ?? [];
        if (!is_array($settings)) {
            wp_send_json_error(__('Invalid settings data', 'hourly-room-booking'));
            return;
        }

        $errors = [];
        $saved_count = 0;

        foreach ($settings as $key => $value) {
            // Validate setting
            $validation = $this->validate_setting($key, $value);
            if ($validation !== true) {
                $errors[$key] = $validation;
                continue;
            }

            // Save setting
            if ($this->set($key, $value)) {
                $saved_count++;
            } else {
                $errors[$key] = __('Failed to save setting', 'hourly-room-booking');
            }
        }

        // Reload text domain if language setting was changed
        if (isset($settings['hrb_plugin_language'])) {
            $this->reload_textdomain();
        }

        if (!empty($errors)) {
            wp_send_json_error([
                'message' => __('Some settings could not be saved', 'hourly-room-booking'),
                'errors' => $errors
            ]);
        } else {
            wp_send_json_success([
                'message' => sprintf(__('%d settings saved successfully', 'hourly-room-booking'), $saved_count)
            ]);
        }
    }

    /**
     * AJAX handler for resetting settings
     *
     * @since 1.0.0
     */
    public function ajax_reset_settings(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        $keys = $_POST['keys'] ?? [];
        if (!is_array($keys)) {
            $keys = [];
        }

        if ($this->reset($keys)) {
            wp_send_json_success([
                'message' => empty($keys)
                    ? __('All settings reset to defaults', 'hourly-room-booking')
                    : __('Selected settings reset to defaults', 'hourly-room-booking')
            ]);
        } else {
            wp_send_json_error(__('Failed to reset settings', 'hourly-room-booking'));
        }
    }

    /**
     * Clear settings cache
     *
     * @since 1.0.0
     */
    public function clear_cache(): void {
        $this->settings_cache = [];
    }
}