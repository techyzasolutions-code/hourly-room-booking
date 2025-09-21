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
            'default' => HRB_VERSION,
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
        'hrb_admin_email' => [
            'default' => '',
            'type' => 'email',
            'sanitize' => 'sanitize_email'
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
        ]
    ];

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
                    'hrb_timezone'
                ]
            ],
            'booking' => [
                'title' => __('Booking Settings', 'hourly-room-booking'),
                'settings' => [
                    'hrb_booking_advance_days',
                    'hrb_cancellation_hours',
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
                    'hrb_admin_email'
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