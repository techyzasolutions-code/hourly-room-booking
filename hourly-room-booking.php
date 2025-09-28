<?php
/**
 * Plugin Name: Hourly Room Booking System
 * Plugin URI: https://yoursite.com/
 * Description: Professional room booking system with hourly slots, payment integration, and comprehensive management features.
 * Version: 1.0.0
 * Author: Your Name
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hourly-room-booking
 * Domain Path: /languages
 * Network: false
 * 
 * @package HourlyRoomBooking
 * @version 1.0.0
 * @author Your Name
 * @copyright 2024 Your Company
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

// Ensure WordPress environment
if (!defined('ABSPATH')) {
    exit('Direct script access denied.');
}

// Start session for email verification
if (!session_id()) {
    session_start();
}

// Prevent multiple plugin instances
if (defined('HRB_VERSION')) {
    return;
}

/**
 * Plugin Constants
 * 
 * These constants are used throughout the plugin for consistency
 * and to avoid magic strings in the codebase.
 */
define('HRB_VERSION', '1.0.0');
define('HRB_MIN_PHP_VERSION', '7.4');
define('HRB_MIN_WP_VERSION', '5.0');
define('HRB_PLUGIN_FILE', __FILE__);
define('HRB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HRB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HRB_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('HRB_INCLUDES_DIR', HRB_PLUGIN_DIR . 'includes/');
define('HRB_ADMIN_DIR', HRB_PLUGIN_DIR . 'admin/');
define('HRB_TEMPLATES_DIR', HRB_PLUGIN_DIR . 'templates/');
define('HRB_ASSETS_URL', HRB_PLUGIN_URL . 'assets/');

/**
 * Main Plugin Bootstrap Class
 * 
 * This class is responsible for initializing the plugin, loading dependencies,
 * and coordinating all plugin components. It follows the Singleton pattern
 * to ensure only one instance exists throughout the application lifecycle.
 * 
 * @since 1.0.0
 */
final class HourlyRoomBooking {
    
    /**
     * Plugin instance
     * 
     * @since 1.0.0
     * @var HourlyRoomBooking|null
     */
    private static ?HourlyRoomBooking $instance = null;
    
    /**
     * Plugin initialization status
     * 
     * @since 1.0.0
     * @var bool
     */
    private bool $initialized = false;
    
    /**
     * Required class files mapping
     * 
     * Maps class names to their file paths for dependency management
     * and proper error reporting.
     * 
     * @since 1.0.0
     * @var array<string, string>
     */
    private array $required_classes = [
        'HRB_Database'            => 'class-database.php',
        'HRB_Room_Manager'        => 'class-room-manager.php',
        'HRB_Booking_Manager'     => 'class-booking-manager.php',
        'HRB_Payment_Handler'     => 'class-payment-handler.php',
        'HRB_Payment_Manager'     => 'class-payment-manager.php',
        'HRB_Notification_Manager'=> 'class-notification-manager.php',
        'HRB_Customer_Manager'    => 'class-customer-manager.php',
        'HRB_Calendar'            => 'class-calendar.php',
        'HRB_Settings'            => 'class-settings.php',
        'HRB_Input_Validator'     => 'class-input-validator.php',
        'HRB_Currency_Manager'    => 'class-currency-manager.php',
        'HRB_Extra_Stock_Manager' => 'class-extra-stock-manager.php',
        'HRB_Status_Constants'    => 'class-status-constants.php',
        'HRB_Extras'              => 'class-extras.php',
        'HRB_Invoice_Generator'   => 'class-invoice-generator.php',
        'HRB_PDF_Generator'       => 'class-pdf-generator.php',
        'HRB_Admin'               => 'class-admin.php',
        'HRB_Frontend'            => 'class-frontend.php',
        'HRB_Shortcodes'          => 'class-shortcodes.php',
        'HRB_Ajax_Handler'        => 'class-ajax-handler.php',
    ];
    
    /**
     * Component instances
     * 
     * @since 1.0.0
     * @var array<string, object>
     */
    private array $components = [];
    
    /**
     * Get plugin instance
     * 
     * Implements Singleton pattern to ensure single plugin instance.
     * 
     * @since 1.0.0
     * @return HourlyRoomBooking
     */
    public static function getInstance(): HourlyRoomBooking {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Prevent cloning
     * 
     * @since 1.0.0
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     * 
     * @since 1.0.0
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
    
    /**
     * Initialize plugin
     * 
     * Sets up the plugin by checking requirements, loading dependencies,
     * and registering hooks.
     * 
     * @since 1.0.0
     * @return void
     */
    private function init(): void {
        // Prevent multiple initialization
        if ($this->initialized) {
            return;
        }
        
        try {
            // Check system requirements
            $this->check_requirements();
            
            // Load dependencies
            $this->load_dependencies();
            
            // Load currency helpers
            require_once HRB_INCLUDES_DIR . 'currency-helpers.php';
            
            // Register WordPress hooks
            $this->register_hooks();
            
            // Mark as initialized
            $this->initialized = true;
            
        } catch (Exception $e) {
            $this->handle_initialization_error($e);
        }
    }
    
    /**
     * Check system requirements
     * 
     * Validates PHP version, WordPress version, and required extensions.
     * Throws exceptions for unmet requirements.
     * 
     * @since 1.0.0
     * @throws Exception If requirements are not met
     */
    private function check_requirements(): void {
        global $wp_version;
        
        // Check PHP version
        if (version_compare(PHP_VERSION, HRB_MIN_PHP_VERSION, '<')) {
            throw new Exception(
                sprintf(
                    'Hourly Room Booking requires PHP %s or higher. You are running PHP %s.',
                    HRB_MIN_PHP_VERSION,
                    PHP_VERSION
                )
            );
        }
        
        // Check WordPress version
        if (version_compare($wp_version, HRB_MIN_WP_VERSION, '<')) {
            throw new Exception(
                sprintf(
                    'Hourly Room Booking requires WordPress %s or higher. You are running WordPress %s.',
                    HRB_MIN_WP_VERSION,
                    $wp_version
                )
            );
        }
        
        // Check required PHP extensions
        $required_extensions = ['mysqli', 'json', 'curl'];
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                throw new Exception(
                    sprintf(
                        'Hourly Room Booking requires the PHP %s extension.',
                        $extension
                    )
                );
            }
        }
    }
    
    /**
     * Load plugin dependencies
     * 
     * Loads all required class files and validates their existence.
     * Uses require_once for better error reporting than autoloading.
     * 
     * @since 1.0.0
     * @throws Exception If required files are missing
     */
    private function load_dependencies(): void {
        $missing_files = [];
        
        foreach ($this->required_classes as $class_name => $file_name) {
            $file_path = HRB_INCLUDES_DIR . $file_name;
            
            if (!file_exists($file_path)) {
                $missing_files[] = $file_path;
                continue;
            }
            
            if (!is_readable($file_path)) {
                throw new Exception(
                    sprintf('Cannot read required file: %s', $file_path)
                );
            }
            
            require_once $file_path;
            
            if (!class_exists($class_name)) {
                throw new Exception(
                    sprintf(
                        'Class %s not found in file %s',
                        $class_name,
                        $file_path
                    )
                );
            }
        }
        
        // Report missing files
        if (!empty($missing_files)) {
            throw new Exception(
                'Missing required plugin files: ' . implode(', ', $missing_files)
            );
        }
    }
    
    /**
     * Register WordPress hooks
     * 
     * Sets up plugin hooks for initialization, activation, and deactivation.
     * Uses appropriate hook priorities for proper loading order.
     * 
     * @since 1.0.0
     */
    private function register_hooks(): void {
        // Core initialization
        add_action('plugins_loaded', [$this, 'plugins_loaded'], 10);
        add_action('init', [$this, 'init_components'], 10);
        
        // Asset loading
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets'], 10);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets'], 10);
        
        // Localization
        add_action('plugins_loaded', [$this, 'load_textdomain'], 5);
        
        // Activation and deactivation
        register_activation_hook(HRB_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(HRB_PLUGIN_FILE, [$this, 'deactivate']);
        
        // Admin notices
        add_action('admin_notices', [$this, 'display_admin_notices']);
    }
    
    /**
     * Handle plugins loaded hook
     * 
     * @since 1.0.0
     */
    public function plugins_loaded(): void {
        // Plugin is ready for use
        do_action('hrb_loaded');
    }
    
    /**
     * Initialize plugin components
     * 
     * Creates instances of all plugin components in the correct order
     * to respect dependencies.
     * 
     * @since 1.0.0
     */
    public function init_components(): void {
        try {
            // Initialize components in dependency order
            $this->components['database']     = HRB_Database::getInstance();
            $this->components['room_manager'] = HRB_Room_Manager::getInstance();
            $this->components['customer_manager'] = HRB_Customer_Manager::getInstance();
            $this->components['booking_manager']  = HRB_Booking_Manager::getInstance();
            $this->components['payment_handler']  = HRB_Payment_Handler::getInstance();
            $this->components['notification_manager'] = HRB_Notification_Manager::getInstance();
            $this->components['calendar']     = HRB_Calendar::getInstance();
            $this->components['settings']     = HRB_Settings::getInstance();
            $this->components['extras']       = HRB_Extras::getInstance();
            $this->components['admin']        = HRB_Admin::getInstance();
            $this->components['frontend']     = HRB_Frontend::getInstance();
            $this->components['shortcodes']   = HRB_Shortcodes::getInstance();
            $this->components['ajax_handler'] = HRB_Ajax_Handler::getInstance();
            
            // Components initialized successfully
            do_action('hrb_components_loaded', $this->components);
            
        } catch (Exception $e) {
            $this->handle_component_error($e);
        }
    }
    
    /**
     * Load plugin text domain for internationalization
     * 
     * @since 1.0.0
     */
    public function load_textdomain(): void {
        $domain = 'hourly-room-booking';
        
        // Get plugin language setting
        $plugin_language = get_option('hrb_plugin_language', 'en_US');
        
        // Use plugin language setting if available, otherwise use WordPress locale
        $locale = !empty($plugin_language) ? $plugin_language : determine_locale();
        $locale = apply_filters('plugin_locale', $locale, $domain);
        
        // Load from global languages directory first
        load_textdomain(
            $domain,
            WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo'
        );
        
        // Load from plugin languages directory with specific locale
        $mo_file = HRB_PLUGIN_DIR . 'languages/hourly-room-booking-' . $locale . '.mo';
        if (file_exists($mo_file)) {
            load_textdomain($domain, $mo_file);
        } else {
            // Fallback to default loading
            load_plugin_textdomain(
                $domain,
                false,
                dirname(HRB_PLUGIN_BASENAME) . '/languages/'
            );
        }
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @since 1.0.0
     */
    public function enqueue_frontend_assets(): void {
        if (is_admin()) {
            return;
        }

        $css_file = HRB_PLUGIN_DIR . 'assets/css/frontend.css';
        $js_file = HRB_PLUGIN_DIR . 'assets/js/frontend.js';

        if (file_exists($css_file)) {
            wp_enqueue_style(
                'hrb-frontend',
                HRB_ASSETS_URL . 'css/frontend.css',
                [],
                '1.1.13'
            );
        }

        // Enqueue local calendar assets
        $calendar_css = HRB_PLUGIN_DIR . 'assets/css/calendar.css';
        $calendar_js = HRB_PLUGIN_DIR . 'assets/js/calendar.js';

        if (file_exists($calendar_css)) {
            wp_enqueue_style(
                'hrb-calendar',
                HRB_ASSETS_URL . 'css/calendar.css',
                [],
                '1.1.13'
            );
        }

        // FullCalendar from CDN as fallback with better dependency management
        wp_enqueue_script(
            'moment',
            'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js',
            [],
            '2.29.4',
            true
        );

        wp_enqueue_style(
            'fullcalendar',
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css',
            [],
            '3.10.2'
        );

        wp_enqueue_script(
            'fullcalendar',
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js',
            ['jquery', 'moment'],
            '3.10.2',
            true
        );

        if (file_exists($calendar_js)) {
            wp_enqueue_script(
                'hrb-calendar',
                HRB_ASSETS_URL . 'js/calendar.js',
                ['jquery', 'fullcalendar'],
                '1.1.12',
                true
            );
        }

        //HRB_VERSION
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'hrb-frontend',
                HRB_ASSETS_URL . 'js/frontend.js',
                ['jquery', 'fullcalendar'],
                '1.1.13',
                true
            );

            // Localize script with necessary data
            wp_localize_script('hrb-frontend', 'hrbAjax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'ajaxUrl' => admin_url('admin-ajax.php'), // Keep both for compatibility
                'nonce'   => wp_create_nonce('hrb_nonce'),
                'currency_symbol' => HRB_Currency_Manager::getInstance()->get_currency_symbol(),
                'currency_code' => HRB_Currency_Manager::getInstance()->get_currency_code(),
                'strings' => [
                    'loading' => __('Loading...', 'hourly-room-booking'),
                    'error'   => __('An error occurred', 'hourly-room-booking'),
                    'success' => __('Success!', 'hourly-room-booking'),
                ],
            ]);
        }
    }
    
    /**
     * Enqueue admin assets
     * 
     * @since 1.0.0
     * @param string $hook_suffix
     */
    public function enqueue_admin_assets(string $hook_suffix): void {
        // Only load on plugin admin pages
        if (!$this->is_plugin_admin_page($hook_suffix)) {
            return;
        }
        
        $css_file = HRB_ADMIN_DIR . 'assets/css/admin.css';
        $js_file = HRB_ADMIN_DIR . 'assets/js/admin.js';
        
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'hrb-admin',
                HRB_PLUGIN_URL . 'admin/assets/css/admin.css',
                [],
                '1.1.3'
            );
        }
        //HRB_VERSION
        
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'hrb-admin',
                HRB_PLUGIN_URL . 'admin/assets/js/admin.js',
                ['jquery'],
                '1.1.3',
                true
            );
            
            wp_localize_script('hrb-admin', 'hrbAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('hrb_admin_nonce'),
            ]);
        }
    }
    
    /**
     * Check if current page is a plugin admin page
     * 
     * @since 1.0.0
     * @param string $hook_suffix
     * @return bool
     */
    private function is_plugin_admin_page(string $hook_suffix): bool {
        $plugin_pages = [
            'toplevel_page_hrb-dashboard',
            'room-bookings_page_hrb-bookings',
            'room-bookings_page_hrb-rooms',
            'room-bookings_page_hrb-calendar',
            'room-bookings_page_hrb-customers',
            'room-bookings_page_hrb-payments',
            'room-bookings_page_hrb-reports',
            'room-bookings_page_hrb-settings',
        ];
        
        return in_array($hook_suffix, $plugin_pages, true) || 
               strpos($hook_suffix, 'hrb-') !== false;
    }
    
    /**
     * Plugin activation handler
     * 
     * @since 1.0.0
     */
    public function activate(): void {
        try {
            // Set flag to prevent hooks from running during activation
            define('HRB_ACTIVATION_MODE', true);

            // Buffer output to prevent "unexpected output" errors
            ob_start();

            // Create database tables without initializing components that might produce output
            HRB_Database::create_tables();

            // Set default options
            $this->set_default_options();

            // Add rewrite rules
            $this->add_rewrite_rules();

            // Clear rewrite rules
            flush_rewrite_rules();

            // Initialize currency setting
            if (!get_option('hrb_currency')) {
                update_option('hrb_currency', 'EUR');
            }
            
            // Set activation flag for welcome message
            set_transient('hrb_activation_notice', true, 60);

            // Clean any output that might have been generated
            ob_end_clean();

        } catch (Exception $e) {
            // Clean output buffer in case of error
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Log error and prevent activation
            error_log('HRB Plugin Activation Error: ' . $e->getMessage());

            wp_die(
                esc_html($e->getMessage()),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }
    }
    
    /**
     * Set default plugin options
     * 
     * @since 1.0.0
     */
    private function set_default_options(): void {
        $defaults = [
            // Core plugin settings
            'hrb_version'                 => HRB_VERSION,
            'hrb_currency_symbol'         => '$',
            'hrb_date_format'            => 'd.m.Y',
            'hrb_time_format'            => 'H:i',
            'hrb_timezone'               => 'Europe/Berlin',
            
            // Booking settings
            'hrb_booking_advance_days'    => 30,
            'hrb_cancellation_hours'      => 24,
            'hrb_default_booking_duration'=> 1,
            'hrb_cooldown_minutes'        => 30,
            'hrb_enable_guest_booking'    => 1,
            'hrb_require_otp'             => 1,
            'hrb_max_concurrent_bookings' => 3,
            
            // PayPal settings
            'hrb_paypal_sandbox'          => 1,
            'hrb_paypal_client_id'        => '',
            'hrb_paypal_client_secret'    => '',
            
            // Notification settings
            'hrb_email_notifications'     => 1,
            'hrb_sms_notifications'       => 0,
            'hrb_whatsapp_notifications'  => 0,
            
            // Twilio settings
            'hrb_twilio_sid'              => '',
            'hrb_twilio_token'            => '',
            'hrb_twilio_from'             => '',
            
            // WhatsApp settings
            'hrb_whatsapp_token'          => '',
            'hrb_whatsapp_phone_id'       => '',
            
            // Company information
            'hrb_admin_email'             => get_option('admin_email'),
            'hrb_company_name'            => get_bloginfo('name'),
            'hrb_company_address'         => '',
            'hrb_company_phone'           => '',
            'hrb_company_email'           => get_option('admin_email'),
            
            // Legal pages
            'hrb_terms_page'              => '',
            'hrb_privacy_page'            => '',
            
            // Invoice settings
            'hrb_invoice_counter'         => 1,
            'hrb_tax_rate'                => 19,
        ];
        
        foreach ($defaults as $option_name => $option_value) {
            add_option($option_name, $option_value);
        }
    }

    /**
     * Add rewrite rules for booking pages
     *
     * @since 1.0.0
     */
    private function add_rewrite_rules(): void {
        add_rewrite_rule('^booking-details/?$', 'index.php?hrb_page=booking-details', 'top');
        add_rewrite_rule('^booking-success/?$', 'index.php?hrb_page=booking-success', 'top');
        add_rewrite_rule('^booking-cancelled/?$', 'index.php?hrb_page=booking-cancelled', 'top');
    }

    /**
     * Plugin deactivation handler
     * 
     * @since 1.0.0
     */
    public function deactivate(): void {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('hrb_cleanup_expired_bookings');
        wp_clear_scheduled_hook('hrb_send_booking_reminders');
        
        // Clear rewrite rules
        flush_rewrite_rules();
        
        // Clear any transients
        delete_transient('hrb_activation_notice');
    }
    
    /**
     * Display admin notices
     * 
     * @since 1.0.0
     */
    public function display_admin_notices(): void {
        // Show activation success message
        if (get_transient('hrb_activation_notice')) {
            printf(
                '<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
                esc_html__('Hourly Room Booking', 'hourly-room-booking'),
                esc_html__('plugin activated successfully!', 'hourly-room-booking')
            );
            delete_transient('hrb_activation_notice');
        }
    }
    
    /**
     * Handle initialization errors
     * 
     * @since 1.0.0
     * @param Exception $e
     */
    private function handle_initialization_error(Exception $e): void {
        error_log('HRB Plugin Initialization Error: ' . $e->getMessage());
        
        add_action('admin_notices', function() use ($e) {
            printf(
                '<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
                esc_html__('Hourly Room Booking Plugin Error', 'hourly-room-booking'),
                esc_html($e->getMessage())
            );
        });
    }
    
    /**
     * Handle component initialization errors
     * 
     * @since 1.0.0
     * @param Exception $e
     */
    private function handle_component_error(Exception $e): void {
        error_log('HRB Component Error: ' . $e->getMessage());
        
        add_action('admin_notices', function() use ($e) {
            printf(
                '<div class="notice notice-warning"><p><strong>%s:</strong> %s</p></div>',
                esc_html__('Room Booking Plugin Warning', 'hourly-room-booking'),
                esc_html($e->getMessage())
            );
        });
    }
    
    /**
     * Get component instance
     * 
     * @since 1.0.0
     * @param string $component_name
     * @return object|null
     */
    public function get_component(string $component_name): ?object {
        return $this->components[$component_name] ?? null;
    }
    
    /**
     * Check if plugin is fully initialized
     * 
     * @since 1.0.0
     * @return bool
     */
    public function is_initialized(): bool {
        return $this->initialized;
    }
}

/**
 * Initialize the plugin
 * 
 * This function serves as the main entry point for the plugin.
 * It's called early in the WordPress loading process.
 * 
 * @since 1.0.0
 * @return HourlyRoomBooking
 */
function hrb_init(): HourlyRoomBooking {
    return HourlyRoomBooking::getInstance();
}

/**
 * Get plugin instance
 * 
 * Convenience function to get the main plugin instance.
 * 
 * @since 1.0.0
 * @return HourlyRoomBooking
 */
function hrb(): HourlyRoomBooking {
    return HourlyRoomBooking::getInstance();
}

// Initialize the plugin
hrb_init();