<?php
/**
 * Database Handler Class
 * Handles all database operations for the Room Booking System
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Database {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Create all plugin tables
     */
    public static function create_tables() {
        global $wpdb;

        // Start output buffering to prevent any debug output during activation
        ob_start();

        $charset_collate = $wpdb->get_charset_collate();
        
        // Rooms table
        $rooms_table = $wpdb->prefix . 'hrb_rooms';
        
        $sql_rooms = "CREATE TABLE $rooms_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            capacity int(11) NOT NULL DEFAULT 1,
            hourly_price decimal(10,2) NOT NULL DEFAULT 0.00,
            price_2_hours decimal(10,2) NOT NULL DEFAULT 0.00,
            price_3_hours decimal(10,2) NOT NULL DEFAULT 0.00,
            price_4_hours decimal(10,2) NOT NULL DEFAULT 0.00,
            price_extra_hour decimal(10,2) NOT NULL DEFAULT 0.00,
            available_from time NOT NULL DEFAULT '00:00:00',
            available_to time NOT NULL DEFAULT '00:00:00',
            images text,
            amenities text,
            color varchar(7) NOT NULL DEFAULT '#3498db',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Customers table
        $customers_table = $wpdb->prefix . 'hrb_customers';
        $sql_customers = "CREATE TABLE $customers_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            wp_user_id int(11) NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(20) NOT NULL,
            company varchar(255) NULL,
            address text NULL,
            city varchar(100) NULL,
            postal_code varchar(20) NULL,
            country varchar(2) NULL DEFAULT 'DE',
            date_of_birth date NULL,
            is_verified tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY wp_user_id (wp_user_id)
        ) $charset_collate;";
        
        // Bookings table
        $bookings_table = $wpdb->prefix . 'hrb_bookings';
        $sql_bookings = "CREATE TABLE $bookings_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_reference varchar(20) NOT NULL,
            room_id int(11) NOT NULL,
            customer_id int(11) NOT NULL,
            booking_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            total_hours decimal(4,2) NOT NULL,
            base_price decimal(10,2) NOT NULL,
            extra_people int(11) NOT NULL DEFAULT 0,
            extra_people_price decimal(10,2) NOT NULL DEFAULT 0.00,
            extras_price decimal(10,2) NOT NULL DEFAULT 0.00,
            tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            paypal_fee decimal(10,2) NOT NULL DEFAULT 0.00,
            total_amount decimal(10,2) NOT NULL,
            cancellation_fee decimal(10,2) NOT NULL DEFAULT 0.00,
            price_override tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            payment_status varchar(20) NOT NULL DEFAULT 'pending',
            payment_method varchar(50) NULL,
            special_requests text NULL,
            admin_notes text NULL,
            created_by_admin tinyint(1) NOT NULL DEFAULT 0,
            cooldown_override tinyint(1) NOT NULL DEFAULT 0,
            is_anonymous tinyint(1) NOT NULL DEFAULT 0,
            first_name varchar(255) NULL,
            last_name varchar(255) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY booking_reference (booking_reference),
            KEY room_date_time (room_id, booking_date, start_time, end_time),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY booking_date (booking_date)
        ) $charset_collate;";
        
        // Payments table
        $payments_table = $wpdb->prefix . 'hrb_payments';
        $sql_payments = "CREATE TABLE $payments_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_id int(11) NOT NULL,
            transaction_id varchar(255) NULL,
            gateway_transaction_id varchar(255) NULL,
            payment_method varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            fees decimal(10,2) NOT NULL DEFAULT 0.00,
            refunded_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(3) NOT NULL DEFAULT 'EUR',
            status varchar(20) NOT NULL DEFAULT 'pending',
            is_additional_payment tinyint(1) NOT NULL DEFAULT 0,
            payment_token varchar(64) NULL,
            refund_reason text NULL,
            gateway_response text NULL,
            processed_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY transaction_id (transaction_id),
            KEY gateway_transaction_id (gateway_transaction_id),
            KEY status (status),
            KEY is_additional_payment (is_additional_payment),
            KEY payment_token (payment_token)
        ) $charset_collate;";
        
        // Invoices table
        $invoices_table = $wpdb->prefix . 'hrb_invoices';
        $sql_invoices = "CREATE TABLE $invoices_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            invoice_number varchar(50) NOT NULL,
            booking_id int(11) NOT NULL,
            customer_id int(11) NOT NULL,
            issue_date date NOT NULL,
            due_date date NOT NULL,
            subtotal decimal(10,2) NOT NULL,
            tax_rate decimal(5,2) NOT NULL DEFAULT 19.00,
            tax_amount decimal(10,2) NOT NULL,
            total_amount decimal(10,2) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            pdf_file_path varchar(255) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY booking_id (booking_id),
            KEY customer_id (customer_id),
            KEY issue_date (issue_date)
        ) $charset_collate;";
        
        // Room availability exceptions table
        $availability_table = $wpdb->prefix . 'hrb_room_availability';
        $sql_availability = "CREATE TABLE $availability_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            room_id int(11) NOT NULL,
            exception_date date NOT NULL,
            start_time time NULL,
            end_time time NULL,
            type varchar(20) NOT NULL DEFAULT 'closed',
            reason varchar(255) NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY room_date (room_id, exception_date),
            KEY exception_date (exception_date)
        ) $charset_collate;";
        
        // OTP verification table
        $otp_table = $wpdb->prefix . 'hrb_otp_verification';
        $sql_otp = "CREATE TABLE $otp_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            phone varchar(20) NOT NULL,
            otp_code varchar(10) NOT NULL,
            verification_type varchar(20) NOT NULL DEFAULT 'email',
            is_verified tinyint(1) NOT NULL DEFAULT 0,
            expires_at datetime NOT NULL,
            attempts int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY phone (phone),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Notification logs table
        $notifications_table = $wpdb->prefix . 'hrb_notification_logs';
        $sql_notifications = "CREATE TABLE $notifications_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_id int(11) NOT NULL,
            customer_id int(11) NOT NULL,
            type varchar(20) NOT NULL,
            event varchar(50) NOT NULL,
            recipient varchar(255) NOT NULL,
            subject varchar(255) NULL,
            message text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            sent_at datetime NULL,
            error_message text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        
        // Extras table
        $extras_table = $wpdb->prefix . 'hrb_extras';
        $sql_extras = "CREATE TABLE $extras_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text NULL,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            stock_quantity int(11) NOT NULL DEFAULT 0,
            track_stock tinyint(1) NOT NULL DEFAULT 1,
            image_url varchar(500) NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY sort_order (sort_order),
            KEY stock_quantity (stock_quantity)
        ) $charset_collate;";

        // Booking extras table (consolidated for both pricing and stock management)
        $booking_extras_table = $wpdb->prefix . 'hrb_booking_extras';
        $sql_booking_extras = "CREATE TABLE $booking_extras_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_id int(11) NOT NULL,
            extra_id int(11) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            unit_price decimal(10,2) NOT NULL,
            total_price decimal(10,2) NOT NULL,
            booking_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY extra_id (extra_id),
            KEY booking_date (booking_date),
            KEY time_slot (booking_date, start_time, end_time),
            KEY extra_time_slot (extra_id, booking_date, start_time, end_time),
            UNIQUE KEY booking_extra (booking_id, extra_id)
        ) $charset_collate;";

        // Booking modifications table to track admin/staff changes to hours and extra people
        $booking_modifications_table = $wpdb->prefix . 'hrb_booking_modifications';
        $sql_booking_modifications = "CREATE TABLE $booking_modifications_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_id int(11) NOT NULL,
            modification_type varchar(20) NOT NULL,
            original_value decimal(10,2) NOT NULL,
            new_value decimal(10,2) NOT NULL,
            additional_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            added_by_user_id int(11) NULL DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY modification_type (modification_type),
            KEY added_by_user_id (added_by_user_id)
        ) $charset_collate;";
        
        // OTP verification table
        $otp_table = $wpdb->prefix . 'hrb_otp_verification';
        $sql_otp_verification = "CREATE TABLE $otp_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            phone varchar(20) NOT NULL,
            otp_code varchar(6) NOT NULL,
            verification_type varchar(10) NOT NULL DEFAULT 'email',
            is_verified tinyint(1) NOT NULL DEFAULT 0,
            attempts int(11) NOT NULL DEFAULT 0,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY phone (phone),
            KEY expires_at (expires_at),
            KEY is_verified (is_verified)
        ) $charset_collate;";

        // Settings table for dynamic configuration
        $settings_table = $wpdb->prefix . 'hrb_settings';
        $sql_settings = "CREATE TABLE $settings_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext NULL,
            setting_type varchar(20) NOT NULL DEFAULT 'string',
            description text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Email templates table
        $email_templates_table = $wpdb->prefix . 'hrb_email_templates';
        $sql_email_templates = "CREATE TABLE $email_templates_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            template_key varchar(50) NOT NULL,
            template_name varchar(100) NOT NULL,
            template_type varchar(20) NOT NULL DEFAULT 'user',
            subject varchar(255) NOT NULL,
            heading varchar(255) NOT NULL,
            message text NOT NULL,
            html_content longtext NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY template_key_type (template_key, template_type)
        ) $charset_collate;";

        // Room locks table
        $room_locks_table = $wpdb->prefix . 'hrb_room_locks';
        $sql_room_locks = "CREATE TABLE $room_locks_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            room_id int(11) NOT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            reason text NULL,
            created_by int(11) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY room_id (room_id),
            KEY start_datetime (start_datetime),
            KEY end_datetime (end_datetime),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Master locks table
        $master_locks_table = $wpdb->prefix . 'hrb_master_locks';
        $sql_master_locks = "CREATE TABLE $master_locks_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            reason text NULL,
            created_by int(11) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY start_datetime (start_datetime),
            KEY end_datetime (end_datetime),
            KEY created_by (created_by)
        ) $charset_collate;";

        // Extra locks table
        $extra_locks_table = $wpdb->prefix . 'hrb_extra_locks';
        $sql_extra_locks = "CREATE TABLE $extra_locks_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            extra_id int(11) NOT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            reason text NULL,
            created_by int(11) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY extra_id (extra_id),
            KEY start_datetime (start_datetime),
            KEY end_datetime (end_datetime),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Master extra locks table
        $master_extra_locks_table = $wpdb->prefix . 'hrb_master_extra_locks';
        $sql_master_extra_locks = "CREATE TABLE $master_extra_locks_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            reason text NULL,
            created_by int(11) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY start_datetime (start_datetime),
            KEY end_datetime (end_datetime),
            KEY created_by (created_by)
        ) $charset_collate;";

        // Execute table creation with error logging
        $tables_to_create = [
            'rooms' => $sql_rooms,
            'customers' => $sql_customers,
            'bookings' => $sql_bookings,
            'payments' => $sql_payments,
            'invoices' => $sql_invoices,
            'availability' => $sql_availability,
            'otp_verification' => $sql_otp_verification,
            'notifications' => $sql_notifications,
            'settings' => $sql_settings,
            'extras' => $sql_extras,
            'booking_extras' => $sql_booking_extras,
            'booking_modifications' => $sql_booking_modifications,
            'email_templates' => $sql_email_templates,
            'room_locks' => $sql_room_locks,
            'master_locks' => $sql_master_locks,
            'extra_locks' => $sql_extra_locks,
            'master_extra_locks' => $sql_master_extra_locks
        ];

        foreach ($tables_to_create as $table_name => $sql) {
            // First try dbDelta
            $result = dbDelta($sql);

            // Check if table was created
            $table_full_name = $wpdb->prefix . 'hrb_' . $table_name;
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_full_name));

            // If dbDelta failed, try direct SQL execution
            if (!$table_exists) {
                // Clear any previous errors
                $wpdb->hide_errors();
                $direct_result = $wpdb->query($sql);
                $table_exists_after = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_full_name));

                // Force log critical table creation failures even during activation
                if (!$table_exists_after) {
                    /* removed error_log - avoid noisy logs in production */
                    if ($wpdb->last_error) {
                        /* removed error_log - avoid noisy logs in production */
                        /* removed error_log - avoid noisy logs in production */
                    }
                } else {
                    /* removed error_log - avoid noisy logs in production */
                }
            } else {
                // Log success for debugging (skip during activation)
                if (defined('WP_DEBUG') && WP_DEBUG && !defined('HRB_ACTIVATION_MODE')) {
                    /* removed error_log - avoid noisy logs in production */
                }
            }
        }
        
        // Fix existing table structures
        self::fix_existing_tables();

        // Add default data
        self::insert_default_data();

        // Update database version
        update_option('hrb_database_version', HRB_VERSION);
        
        // Run migrations for existing installations
        self::add_template_type_column();
        self::add_missing_templates();
        self::add_room_color_column();
        self::add_payment_token_column();
        self::update_email_templates_with_status();
        self::seed_branded_email_templates();

        // Clean output buffer to prevent unexpected output
        if (ob_get_level()) {
            ob_end_clean();
        }
    }

    /**
     * Fix existing database tables (add missing columns)
     */
    public static function fix_existing_tables() {
        global $wpdb;

        $booking_extras_table = $wpdb->prefix . 'hrb_booking_extras';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$booking_extras_table}'");

        if ($table_exists) {
            // Check if columns exist and add missing ones
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$booking_extras_table}");
            $column_names = array_column($columns, 'Field');

            // Add unit_price column if missing
            if (!in_array('unit_price', $column_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} ADD COLUMN unit_price decimal(10,2) NOT NULL AFTER quantity");
            }

            // Add total_price column if missing
            if (!in_array('total_price', $column_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} ADD COLUMN total_price decimal(10,2) NOT NULL AFTER unit_price");
            }

            // Add time slot columns for stock management
            if (!in_array('booking_date', $column_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} ADD COLUMN booking_date date NOT NULL AFTER total_price");
            }

            if (!in_array('start_time', $column_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} ADD COLUMN start_time time NOT NULL AFTER booking_date");
            }

            if (!in_array('end_time', $column_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} ADD COLUMN end_time time NOT NULL AFTER start_time");
            }

            // Add added_by_admin column to track admin-added extras
            if (!in_array('added_by_admin', $column_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} ADD COLUMN added_by_admin tinyint(1) NOT NULL DEFAULT 0 AFTER end_time");
            }

            // Add added_by_user_id column to track which user (admin/staff) added the extra
            if (!in_array('added_by_user_id', $column_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} ADD COLUMN added_by_user_id int(11) NULL DEFAULT NULL AFTER added_by_admin");
            }

            // Rename price to unit_price if it exists and unit_price doesn't
            if (in_array('price', $column_names) && !in_array('unit_price', $column_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} CHANGE COLUMN price unit_price decimal(10,2) NOT NULL");
            }

            // Drop old price column if both unit_price and price exist
            if (in_array('price', $column_names) && in_array('unit_price', $column_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} DROP COLUMN price");
            }
        }

        // Check and create booking_modifications table if it doesn't exist
        $booking_modifications_table = $wpdb->prefix . 'hrb_booking_modifications';
        $modifications_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$booking_modifications_table}'");
        
        if (!$modifications_table_exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql_booking_modifications = "CREATE TABLE {$booking_modifications_table} (
                id int(11) NOT NULL AUTO_INCREMENT,
                booking_id int(11) NOT NULL,
                modification_type varchar(20) NOT NULL,
                original_value decimal(10,2) NOT NULL,
                new_value decimal(10,2) NOT NULL,
                additional_amount decimal(10,2) NOT NULL DEFAULT 0.00,
                added_by_user_id int(11) NULL DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY booking_id (booking_id),
                KEY modification_type (modification_type),
                KEY added_by_user_id (added_by_user_id)
            ) $charset_collate;";
            $wpdb->query($sql_booking_modifications);
        }

        // Check and add is_additional_payment column to payments table if it doesn't exist
        $payments_table = $wpdb->prefix . 'hrb_payments';
        $payments_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$payments_table}'");
        
        if ($payments_table_exists) {
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$payments_table}");
            $column_names = array_column($columns, 'Field');
            
            // Add is_additional_payment column if missing
            if (!in_array('is_additional_payment', $column_names)) {
                $wpdb->query("ALTER TABLE {$payments_table} ADD COLUMN is_additional_payment tinyint(1) NOT NULL DEFAULT 0 AFTER status");
                $wpdb->query("ALTER TABLE {$payments_table} ADD INDEX is_additional_payment (is_additional_payment)");
                
                // Update existing records: set is_additional_payment = 1 for payments with ADD_ prefix in transaction_id
                $wpdb->query("UPDATE {$payments_table} SET is_additional_payment = 1 WHERE transaction_id LIKE 'ADD_%%'");
            }
        }

        // Continue with booking_extras table indexes if table exists
        $booking_extras_table = $wpdb->prefix . 'hrb_booking_extras';
        $extras_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$booking_extras_table}'");
        
        if ($extras_table_exists) {
            // Add indexes for time slot queries
            $indexes = $wpdb->get_results("SHOW INDEX FROM {$booking_extras_table}");
            $index_names = array_column($indexes, 'Key_name');

            if (!in_array('booking_date', $index_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} ADD INDEX booking_date (booking_date)");
            }

            if (!in_array('time_slot', $index_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} ADD INDEX time_slot (booking_date, start_time, end_time)");
            }

            if (!in_array('extra_time_slot', $index_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} ADD INDEX extra_time_slot (extra_id, booking_date, start_time, end_time)");
            }

            /* removed error_log - avoid noisy logs in production */
        }

        // Fix payments table
        $payments_table = $wpdb->prefix . 'hrb_payments';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $payments_table));

        if ($table_exists) {
            // Check if columns exist and add missing ones
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$payments_table}");
            $column_names = array_column($columns, 'Field');

            // Add gateway_transaction_id column if missing
            if (!in_array('gateway_transaction_id', $column_names)) {
                $wpdb->query("ALTER TABLE {$payments_table} ADD COLUMN gateway_transaction_id varchar(255) NULL AFTER transaction_id");
                $wpdb->query("ALTER TABLE {$payments_table} ADD KEY gateway_transaction_id (gateway_transaction_id)");
            }

            // Add fees column if missing
            if (!in_array('fees', $column_names)) {
                $wpdb->query("ALTER TABLE {$payments_table} ADD COLUMN fees decimal(10,2) NOT NULL DEFAULT 0.00 AFTER amount");
            }

            // Add refunded_amount column if missing
            if (!in_array('refunded_amount', $column_names)) {
                $wpdb->query("ALTER TABLE {$payments_table} ADD COLUMN refunded_amount decimal(10,2) NOT NULL DEFAULT 0.00 AFTER fees");
            }

            // Add refund_reason column if missing
            if (!in_array('refund_reason', $column_names)) {
                $wpdb->query("ALTER TABLE {$payments_table} ADD COLUMN refund_reason text NULL AFTER status");
            }

            /* removed error_log - avoid noisy logs in production */
        }

        // Fix extras table - Add stock management columns
        $extras_table = $wpdb->prefix . 'hrb_extras';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $extras_table));

        if ($table_exists) {
            // Check if columns exist and add missing ones
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$extras_table}");
            $column_names = array_column($columns, 'Field');

            // Add stock_quantity column if missing
            if (!in_array('stock_quantity', $column_names)) {
                $wpdb->query("ALTER TABLE {$extras_table} ADD COLUMN stock_quantity int(11) NOT NULL DEFAULT 0 AFTER price");
                $wpdb->query("ALTER TABLE {$extras_table} ADD KEY stock_quantity (stock_quantity)");
            }

            // Add track_stock column if missing
            if (!in_array('track_stock', $column_names)) {
                $wpdb->query("ALTER TABLE {$extras_table} ADD COLUMN track_stock tinyint(1) NOT NULL DEFAULT 1 AFTER stock_quantity");
            }

            /* removed error_log - avoid noisy logs in production */
        }

        // Fix rooms table - Add new pricing columns
        $rooms_table = $wpdb->prefix . 'hrb_rooms';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rooms_table));

        if ($table_exists) {
            // Check if columns exist and add missing ones
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$rooms_table}");
            $column_names = array_column($columns, 'Field');

            // Add new pricing columns if missing
            if (!in_array('price_2_hours', $column_names)) {
                $wpdb->query("ALTER TABLE {$rooms_table} ADD COLUMN price_2_hours decimal(10,2) NOT NULL DEFAULT 0.00 AFTER hourly_price");
            }

            if (!in_array('price_3_hours', $column_names)) {
                $wpdb->query("ALTER TABLE {$rooms_table} ADD COLUMN price_3_hours decimal(10,2) NOT NULL DEFAULT 0.00 AFTER price_2_hours");
            }

            if (!in_array('price_4_hours', $column_names)) {
                $wpdb->query("ALTER TABLE {$rooms_table} ADD COLUMN price_4_hours decimal(10,2) NOT NULL DEFAULT 0.00 AFTER price_3_hours");
            }

            if (!in_array('price_extra_hour', $column_names)) {
                $wpdb->query("ALTER TABLE {$rooms_table} ADD COLUMN price_extra_hour decimal(10,2) NOT NULL DEFAULT 0.00 AFTER price_4_hours");
            }

            // Add external_link column if missing
            if (!in_array('external_link', $column_names)) {
                $wpdb->query("ALTER TABLE {$rooms_table} ADD COLUMN external_link varchar(500) NULL AFTER amenities");
            }
            if (!in_array('available_from', $column_names)) {
                $wpdb->query("ALTER TABLE {$rooms_table} ADD COLUMN available_from time NOT NULL DEFAULT '00:00:00' AFTER price_extra_hour");
            }
            if (!in_array('available_to', $column_names)) {
                $wpdb->query("ALTER TABLE {$rooms_table} ADD COLUMN available_to time NOT NULL DEFAULT '00:00:00' AFTER available_from");
            }

            /* removed error_log - avoid noisy logs in production */
        }

        // Bookings table: add cancellation_fee column if missing
        $bookings_table = $wpdb->prefix . 'hrb_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$bookings_table}'")) {
            $booking_columns = array_column($wpdb->get_results("SHOW COLUMNS FROM {$bookings_table}"), 'Field');
            if (!in_array('cancellation_fee', $booking_columns)) {
                $wpdb->query("ALTER TABLE {$bookings_table} ADD COLUMN cancellation_fee decimal(10,2) NOT NULL DEFAULT 0.00 AFTER total_amount");
            }
            // Manual price override flag (admin-set final price for on-site bookings)
            if (!in_array('price_override', $booking_columns)) {
                $wpdb->query("ALTER TABLE {$bookings_table} ADD COLUMN price_override tinyint(1) NOT NULL DEFAULT 0 AFTER total_amount");
            }
        }

        return true;
    }

    /**
     * Insert default data
     */
    public static function insert_default_data() {
        global $wpdb;
        
        // Check if rooms table exists
        $rooms_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}hrb_rooms'");
        
        if (!$rooms_table_exists) {
            return;
        }
        
        // Check if rooms already exist
        $rooms_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hrb_rooms");
        
        if ($rooms_count == 0) {
            // Insert sample rooms
            $sample_rooms = array(
                array(
                    'name' => 'Conference Room A',
                    'description' => 'Modern conference room with projector and whiteboard, perfect for meetings and presentations.',
                    'capacity' => 10,
                    'hourly_price' => 25.00,
                    'price_2_hours' => 45.00,
                    'price_3_hours' => 65.00,
                    'price_4_hours' => 85.00,
                    'price_extra_hour' => 20.00,
                    'amenities' => json_encode(array('Projector', 'Whiteboard', 'WiFi', 'Air Conditioning', 'Coffee Machine'))
                ),
                array(
                    'name' => 'Meeting Room B',
                    'description' => 'Intimate meeting space ideal for small team discussions and client meetings.',
                    'capacity' => 6,
                    'hourly_price' => 20.00,
                    'price_2_hours' => 35.00,
                    'price_3_hours' => 50.00,
                    'price_4_hours' => 65.00,
                    'price_extra_hour' => 15.00,
                    'amenities' => json_encode(array('TV Screen', 'WiFi', 'Air Conditioning', 'Flipchart'))
                ),
                array(
                    'name' => 'Training Room C',
                    'description' => 'Spacious training room with flexible seating arrangements and modern AV equipment.',
                    'capacity' => 20,
                    'hourly_price' => 40.00,
                    'price_2_hours' => 75.00,
                    'price_3_hours' => 110.00,
                    'price_4_hours' => 145.00,
                    'price_extra_hour' => 35.00,
                    'amenities' => json_encode(array('Projector', 'Sound System', 'WiFi', 'Air Conditioning', 'Flexible Seating'))
                )
            );
            
            foreach ($sample_rooms as $room) {
                $wpdb->insert(
                    $wpdb->prefix . 'hrb_rooms',
                    $room,
                    array('%s', '%s', '%d', '%f', '%f', '%f', '%f', '%f', '%s')
                );
            }
        }
        
        // Check if extras table exists
        $extras_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}hrb_extras'");
        
        if (!$extras_table_exists) {
            return;
        }
        
        // Check if extras already exist
        $extras_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hrb_extras");
        
        if ($extras_count == 0) {
            // Insert sample extras
            $sample_extras = array(
                array(
                    'name' => 'Projector',
                    'description' => 'High-definition projector for presentations and meetings.',
                    'price' => 15.00,
                    'stock_quantity' => 3,
                    'track_stock' => 1,
                    'is_active' => 1
                ),
                array(
                    'name' => 'Whiteboard',
                    'description' => 'Large whiteboard with markers and eraser.',
                    'price' => 8.00,
                    'stock_quantity' => 5,
                    'track_stock' => 1,
                    'is_active' => 1
                ),
                array(
                    'name' => 'Catering Service',
                    'description' => 'Coffee, tea, and light refreshments for your meeting.',
                    'price' => 25.00,
                    'stock_quantity' => 10,
                    'track_stock' => 1,
                    'is_active' => 1
                ),
                array(
                    'name' => 'Video Conferencing',
                    'description' => 'Professional video conferencing setup with camera and microphone.',
                    'price' => 20.00,
                    'stock_quantity' => 2,
                    'track_stock' => 1,
                    'is_active' => 1
                )
            );
            
            foreach ($sample_extras as $extra) {
                $wpdb->insert(
                    $wpdb->prefix . 'hrb_extras',
                    $extra,
                    array('%s', '%s', '%f', '%d', '%d', '%d')
                );
            }
        }
        
        // Insert default settings
        $default_settings = array(
            'business_hours_start' => array(
                'value' => '08:00',
                'type' => 'string',
                'description' => 'Business opening time'
            ),
            'business_hours_end' => array(
                'value' => '20:00',
                'type' => 'string',
                'description' => 'Business closing time'
            ),
            'booking_time_slots' => array(
                'value' => json_encode(array('08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00')),
                'type' => 'json',
                'description' => 'Available booking time slots'
            ),
            'weekend_days' => array(
                'value' => json_encode(array('saturday', 'sunday')),
                'type' => 'json',
                'description' => 'Weekend days for pricing'
            ),
            'holidays' => array(
                'value' => json_encode(array()),
                'type' => 'json',
                'description' => 'Holiday dates for special pricing'
            )
        );
        
        $settings_table = $wpdb->prefix . 'hrb_settings';
        foreach ($default_settings as $key => $setting) {
            $wpdb->insert(
                $settings_table,
                array(
                    'setting_key' => $key,
                    'setting_value' => $setting['value'],
                    'setting_type' => $setting['type'],
                    'description' => $setting['description']
                ),
                array('%s', '%s', '%s', '%s')
            );
        }

        // Insert default email templates
        $default_templates = array(
            // User templates
            'booking_confirmation_user' => array(
                'template_name' => 'Booking Confirmation (User)',
                'template_type' => 'user',
                'subject' => 'Booking Confirmed - {booking_reference}',
                'heading' => 'Booking Confirmed!',
                'message' => 'Thank you for your booking. Here are your booking details:',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
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
            <h1>{company_name}</h1>
            <h2>{heading}</h2>
        </div>
        
        <div class="content">
            <p>Hello {customer_first_name},</p>
            <p>{message}</p>
            
            <div class="booking-details">
                <h3>Booking Details</h3>
                <div class="detail-row">
                    <span class="label">Booking Reference:</span>
                    <strong>{booking_reference}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Room:</span>
                    {room_name}
                </div>
                <div class="detail-row">
                    <span class="label">Date:</span>
                    {booking_date}
                </div>
                <div class="detail-row">
                    <span class="label">Time:</span>
                    {start_time} - {end_time}
                </div>
                <div class="detail-row">
                    <span class="label">Duration:</span>
                    {duration}
                </div>
                <div class="detail-row">
                    <span class="label">Total Amount:</span>
                    <strong>{total_amount}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Payment Method:</span>
                    {payment_method}
                </div>
                <div class="detail-row">
                    <span class="label">Booking Status:</span>
                    {booking_status}
                </div>
                <div class="detail-row">
                    <span class="label">Payment Status:</span>
                    {payment_status}
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button">View Booking Details</a>
            </p>
        </div>
        
        <div class="footer">
            <p>Thank you for choosing {company_name}!</p>
            <p>If you have any questions, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>'
            ),
            'invoice_regenerated_user' => array(
                'template_name' => 'Updated Invoice (User)',
                'template_type' => 'user',
                'subject' => 'Updated Invoice - {booking_reference}',
                'heading' => 'Updated Invoice',
                'message' => 'Your invoice has been updated. Please find the updated invoice attached to this email.',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0073aa; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .message-box { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .booking-details { background-color: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .detail-row { margin-bottom: 10px; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{company_name}</h1>
            <h2>{heading}</h2>
        </div>
        <div class="content">
            <div class="message-box">
                <p>Dear {customer_first_name},</p>
                <p>{message}</p>
            </div>
            <div class="booking-details">
                <h3>Invoice Details</h3>
                <div class="detail-row">
                    <span class="label">Booking Reference:</span>
                    <strong>{booking_reference}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Total Amount:</span>
                    <strong>{total_amount}</strong>
                </div>
            </div>
        </div>
        <div class="footer">
            <p>Thank you for choosing {company_name}!</p>
            <p>If you have any questions, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>'
            ),
            'payment_confirmation_user' => array(
                'template_name' => 'Payment Confirmation (User)',
                'template_type' => 'user',
                'subject' => 'Payment Confirmed - {booking_reference}',
                'heading' => 'Payment Received!',
                'message' => 'Your payment has been successfully processed.',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .booking-details { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .detail-row { margin-bottom: 10px; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{company_name}</h1>
            <h2>{heading}</h2>
        </div>
        
        <div class="content">
            <p>Hello {customer_first_name},</p>
            <p>{message}</p>
            
            <div class="booking-details">
                <h3>Payment Details</h3>
                <div class="detail-row">
                    <span class="label">Booking Reference:</span>
                    <strong>{booking_reference}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Room:</span>
                    {room_name}
                </div>
                <div class="detail-row">
                    <span class="label">Date:</span>
                    {booking_date}
                </div>
                <div class="detail-row">
                    <span class="label">Time:</span>
                    {start_time} - {end_time}
                </div>
                <div class="detail-row">
                    <span class="label">Duration:</span>
                    {duration}
                </div>
                <div class="detail-row">
                    <span class="label">Amount Paid:</span>
                    <strong>{total_amount}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Payment Method:</span>
                    {payment_method}
                </div>
                <div class="detail-row">
                    <span class="label">Booking Status:</span>
                    {booking_status}
                </div>
                <div class="detail-row">
                    <span class="label">Payment Status:</span>
                    {payment_status}
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button">View Booking Details</a>
            </p>
        </div>
        
        <div class="footer">
            <p>Thank you for your payment!</p>
            <p>If you have any questions, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>'
            ),
            'booking_reminder' => array(
                'template_name' => 'Booking Reminder',
                'subject' => 'Booking Reminder - {booking_reference}',
                'heading' => 'Booking Reminder',
                'message' => 'This is a reminder that your booking starts in 1 hour.',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #ffc107; color: #333; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .booking-details { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .detail-row { margin-bottom: 10px; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #ffc107; color: #333; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{company_name}</h1>
            <h2>{heading}</h2>
        </div>
        
        <div class="content">
            <p>Hello {customer_first_name},</p>
            <p>{message}</p>
            
            <div class="booking-details">
                <h3>Booking Details</h3>
                <div class="detail-row">
                    <span class="label">Booking Reference:</span>
                    <strong>{booking_reference}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Room:</span>
                    {room_name}
                </div>
                <div class="detail-row">
                    <span class="label">Date:</span>
                    {booking_date}
                </div>
                <div class="detail-row">
                    <span class="label">Time:</span>
                    {start_time} - {end_time}
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button">View Booking Details</a>
            </p>
        </div>
        
        <div class="footer">
            <p>We look forward to seeing you soon!</p>
            <p>If you have any questions, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>'
            ),
            'booking_cancelled' => array(
                'template_name' => 'Booking Cancelled',
                'subject' => 'Booking Cancelled - {booking_reference}',
                'heading' => 'Booking Cancelled',
                'message' => 'Your booking has been cancelled.',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
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
            <h1>{company_name}</h1>
            <h2>{heading}</h2>
        </div>
        
        <div class="content">
            <p>Hello {customer_first_name},</p>
            <p>{message}</p>
            
            <div class="booking-details">
                <h3>Cancelled Booking Details</h3>
                <div class="detail-row">
                    <span class="label">Booking Reference:</span>
                    <strong>{booking_reference}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Room:</span>
                    {room_name}
                </div>
                <div class="detail-row">
                    <span class="label">Date:</span>
                    {booking_date}
                </div>
                <div class="detail-row">
                    <span class="label">Time:</span>
                    {start_time} - {end_time}
                </div>
            </div>
            
            <p>If you would like to make a new booking, please visit our website.</p>
        </div>
        
        <div class="footer">
            <p>If you have any questions, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>'
            ),
            'booking_modified' => array(
                'template_name' => 'Booking Modified',
                'subject' => 'Booking Modified - {booking_reference}',
                'heading' => 'Booking Updated',
                'message' => 'Your booking has been modified. Please review the updated details:',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #17a2b8; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .booking-details { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .detail-row { margin-bottom: 10px; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #17a2b8; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{company_name}</h1>
            <h2>{heading}</h2>
        </div>
        
        <div class="content">
            <p>Hello {customer_first_name},</p>
            <p>{message}</p>
            
            <div class="booking-details">
                <h3>Updated Booking Details</h3>
                <div class="detail-row">
                    <span class="label">Booking Reference:</span>
                    <strong>{booking_reference}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Room:</span>
                    {room_name}
                </div>
                <div class="detail-row">
                    <span class="label">Date:</span>
                    {booking_date}
                </div>
                <div class="detail-row">
                    <span class="label">Time:</span>
                    {start_time} - {end_time}
                </div>
                <div class="detail-row">
                    <span class="label">Duration:</span>
                    {duration}
                </div>
                <div class="detail-row">
                    <span class="label">Total Amount:</span>
                    <strong>{total_amount}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Payment Method:</span>
                    {payment_method}
                </div>
                <div class="detail-row">
                    <span class="label">Booking Status:</span>
                    {booking_status}
                </div>
                <div class="detail-row">
                    <span class="label">Payment Status:</span>
                    {payment_status}
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button">View Updated Booking</a>
            </p>
        </div>
        
        <div class="footer">
            <p>If you have any questions about these changes, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>'
            ),
            
            // Admin templates
            'booking_confirmation_admin' => array(
                'template_name' => 'Booking Confirmation (Admin)',
                'template_type' => 'admin',
                'subject' => 'New Booking Received - {booking_reference}',
                'heading' => 'New Booking Alert!',
                'message' => 'A new booking has been received. Here are the details:',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Booking Alert</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px; }
        .content { padding: 20px 0; }
        .booking-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .detail-row { display: flex; justify-content: space-between; margin: 8px 0; padding: 5px 0; border-bottom: 1px solid #eee; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
        .alert { background: #e74c3c; color: white; padding: 10px; border-radius: 5px; margin: 15px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Booking Alert</h1>
            <p>Booking Reference: {booking_reference}</p>
        </div>
        
        <div class="content">
            <div class="alert">
                <strong>Action Required:</strong> A new booking has been received and requires your attention.
            </div>
            
            <div class="booking-details">
                <h3>Customer Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Customer Name:</span>
                    <span class="detail-value">{customer_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">{customer_email}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">{customer_phone}</span>
                </div>
            </div>
            
            <div class="booking-details">
                <h3>Booking Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Room:</span>
                    <span class="detail-value">{room_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">{booking_date}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">{start_time} - {end_time}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span class="detail-value">{duration}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value">{total_amount}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value">{payment_method}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">{booking_status}</span>
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button" style="background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Booking Details</a>
            </p>
        </div>
        
        <div class="footer">
            <p>This is an automated notification from {company_name}.</p>
        </div>
    </div>
</body>
</html>'
            ),
            
            'payment_confirmation_admin' => array(
                'template_name' => 'Payment Confirmation (Admin)',
                'template_type' => 'admin',
                'subject' => 'Payment Received - {booking_reference}',
                'heading' => 'Payment Confirmed!',
                'message' => 'A payment has been successfully processed for a booking.',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #27ae60; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px; }
        .content { padding: 20px 0; }
        .booking-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .detail-row { display: flex; justify-content: space-between; margin: 8px 0; padding: 5px 0; border-bottom: 1px solid #eee; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
        .success { background: #27ae60; color: white; padding: 10px; border-radius: 5px; margin: 15px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Confirmed</h1>
            <p>Booking Reference: {booking_reference}</p>
        </div>
        
        <div class="content">
            <div class="success">
                <strong>Payment Successfully Processed!</strong>
            </div>
            
            <div class="booking-details">
                <h3>Customer Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Customer Name:</span>
                    <span class="detail-value">{customer_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">{customer_email}</span>
                </div>
            </div>
            
            <div class="booking-details">
                <h3>Payment Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value">{total_amount}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value">{payment_method}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Room:</span>
                    <span class="detail-value">{room_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">{booking_date}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">{start_time} - {end_time}</span>
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button" style="background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Booking Details</a>
            </p>
        </div>
        
        <div class="footer">
            <p>This is an automated notification from {company_name}.</p>
        </div>
    </div>
</body>
</html>'
            )
        );

        $templates_table = $wpdb->prefix . 'hrb_email_templates';
        foreach ($default_templates as $key => $template) {
            $wpdb->insert(
                $templates_table,
                array(
                    'template_key' => $key,
                    'template_name' => $template['template_name'],
                    'template_type' => $template['template_type'],
                    'subject' => $template['subject'],
                    'heading' => $template['heading'],
                    'message' => $template['message'],
                    'html_content' => $template['html_content'],
                    'is_active' => 1
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
        }
    }
    
    /**
     * Drop all plugin tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'hrb_notification_logs',
            $wpdb->prefix . 'hrb_otp_verification',
            $wpdb->prefix . 'hrb_room_availability',
            $wpdb->prefix . 'hrb_invoices',
            $wpdb->prefix . 'hrb_payments',
            $wpdb->prefix . 'hrb_bookings',
            $wpdb->prefix . 'hrb_customers',
            $wpdb->prefix . 'hrb_rooms',
            $wpdb->prefix . 'hrb_settings'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('hrb_database_version');
    }
    
    /**
     * Get setting value
     */
    public static function get_setting($key, $default = null) {
        global $wpdb;
        
        $setting = $wpdb->get_row($wpdb->prepare(
            "SELECT setting_value, setting_type FROM {$wpdb->prefix}hrb_settings WHERE setting_key = %s",
            $key
        ));
        
        if (!$setting) {
            return $default;
        }
        
        $value = $setting->setting_value;
        
        switch ($setting->setting_type) {
            case 'number':
                return floatval($value);
            case 'boolean':
                return (bool) $value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    /**
     * Update setting value
     */
    public static function update_setting($key, $value, $type = 'string', $description = '') {
        global $wpdb;
        
        if ($type === 'json') {
            $value = json_encode($value);
        } elseif ($type === 'boolean') {
            $value = $value ? 1 : 0;
        }
        
        $result = $wpdb->replace(
            $wpdb->prefix . 'hrb_settings',
            array(
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_type' => $type,
                'description' => $description
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Check if booking conflicts with existing bookings.
     *
     * @param int      $room_id
     * @param string   $booking_date
     * @param string   $start_time
     * @param string   $end_time
     * @param int|null $exclude_booking_id
     * @param bool     $acquire_room_lock When true, acquires a row-level lock
     *        on the room (SELECT ... FOR UPDATE) to serialise concurrent
     *        booking attempts for the same room. MUST be called inside an
     *        active SQL transaction; the lock is released on COMMIT/ROLLBACK.
     *        On autocommit (no transaction) the lock is acquired and released
     *        immediately, which provides no protection — so write-paths must
     *        wrap this call in a transaction.
     */
    /**
     * Whether the given slot overlaps a maintenance lock (master or room-specific).
     * Maintenance locks always block bookings and take precedence over availability windows.
     */
    public static function is_slot_locked($room_id, $booking_date, $start_time, $end_time) {
        global $wpdb;
        $start_time = date('H:i:s', strtotime($start_time));
        $end_time   = date('H:i:s', strtotime($end_time));
        $slot_start = $booking_date . ' ' . $start_time;
        // End at/kbefore start (midnight or cross-midnight) ends on the next day.
        $end_date = (strtotime($end_time) <= strtotime($start_time))
            ? date('Y-m-d', strtotime($booking_date . ' +1 day'))
            : $booking_date;
        $slot_end = $end_date . ' ' . $end_time;

        $master = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_master_locks WHERE start_datetime < %s AND end_datetime > %s",
            $slot_end, $slot_start
        ));
        if ($master > 0) {
            return true;
        }
        $room = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_room_locks WHERE room_id = %d AND start_datetime < %s AND end_datetime > %s",
            $room_id, $slot_end, $slot_start
        ));
        return $room > 0;
    }

    public static function check_booking_conflict($room_id, $booking_date, $start_time, $end_time, $exclude_booking_id = null, $acquire_room_lock = false) {
        global $wpdb;

        if ($acquire_room_lock) {
            // Lock the room row so no other booking transaction can pass its
            // own conflict check and insert for the same room concurrently.
            $wpdb->query($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}hrb_rooms WHERE id = %d FOR UPDATE",
                $room_id
            ));
        }

        // Get cooldown minutes setting (default 30 minutes)
        $cooldown_minutes = 30;
        if (function_exists('get_option')) {
            $cooldown_minutes = intval(get_option('hrb_cooldown_minutes', 30));
        }

        // Build date range (previous, current, next day) to handle cross-midnight bookings
        $date_obj     = new DateTime($booking_date);
        $prev_day_str = $date_obj->modify('-1 day')->format('Y-m-d');
        $date_obj->modify('+1 day'); // back to original
        $current_str  = $booking_date;
        $next_day_str = $date_obj->modify('+1 day')->format('Y-m-d');

        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT id, booking_date, start_time, end_time, cooldown_override
             FROM {$wpdb->prefix}hrb_bookings
             WHERE room_id = %d
             AND booking_date IN (%s, %s, %s)
             AND status NOT IN ('cancelled', 'no_show')",
            $room_id,
            $prev_day_str,
            $current_str,
            $next_day_str
        ));

        // Prepare slot datetimes
        $slot_start = new DateTime("{$current_str} {$start_time}");
        $slot_end   = new DateTime("{$current_str} {$end_time}");
        if ($slot_end <= $slot_start) {
            $slot_end->modify('+1 day'); // crosses midnight
        }

        foreach ($bookings as $booking) {
            if ($exclude_booking_id && intval($booking->id) === intval($exclude_booking_id)) {
                continue;
            }

            $booking_start = new DateTime("{$booking->booking_date} {$booking->start_time}");
            $booking_end   = new DateTime("{$booking->booking_date} {$booking->end_time}");
            if ($booking_end <= $booking_start) {
                $booking_end->modify('+1 day'); // crosses midnight
            }

            // Direct overlap: existing_start < new_end AND existing_end > new_start
            if ($booking_start < $slot_end && $booking_end > $slot_start) {
                return true;
            }

            // Cooldown checks if not overridden
            if (empty($booking->cooldown_override)) {
                // After existing
                $booking_end_cd = clone $booking_end;
                $booking_end_cd->modify("+{$cooldown_minutes} minutes");
                if ($slot_start >= $booking_end && $slot_start < $booking_end_cd) {
                    return true;
                }

                // Before existing
                $booking_start_cd = clone $booking_start;
                $booking_start_cd->modify("-{$cooldown_minutes} minutes");
                if ($slot_end > $booking_start_cd && $slot_end <= $booking_start) {
                    return true;
                }
            }
        }

        return false;
    }
    
    /**
     * Generate unique booking reference
     */
    public static function generate_booking_reference() {
        global $wpdb;
        
        do {
            $reference = 'HRB-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT) . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_bookings WHERE booking_reference = %s",
                $reference
            ));
        } while ($exists > 0);

        return $reference;
    }

    /**
     * Sync the branded email templates into the database.
     *
     * The email templates live in the wp_hrb_email_templates table (not in code),
     * so a plain plugin-file update would not refresh them. This one-time,
     * option-gated routine loads the bundled branded templates from
     * includes/email-templates-data.php and writes them into the table:
     * existing templates (matched by key + type) are updated, missing ones are
     * inserted. It runs once per design version (see $design_version), so any
     * later manual edits in the admin Email Templates editor are preserved.
     *
     * Hooked on admin_init and also called from create_tables(), so it applies
     * automatically the first time the new plugin code is loaded — no
     * reactivation required.
     */
    public static function ensure_room_availability_columns() {
        global $wpdb;
        if (get_option('hrb_room_availability_migrated') === 'yes') {
            return;
        }
        $rooms_table = $wpdb->prefix . 'hrb_rooms';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$rooms_table}'")) {
            $cols = array_column($wpdb->get_results("SHOW COLUMNS FROM {$rooms_table}"), 'Field');
            if (!in_array('available_from', $cols)) {
                $wpdb->query("ALTER TABLE {$rooms_table} ADD COLUMN available_from time NOT NULL DEFAULT '00:00:00' AFTER price_extra_hour");
            }
            if (!in_array('available_to', $cols)) {
                $wpdb->query("ALTER TABLE {$rooms_table} ADD COLUMN available_to time NOT NULL DEFAULT '00:00:00' AFTER available_from");
            }
        }
        update_option('hrb_room_availability_migrated', 'yes');
    }

    public static function ensure_price_override_column() {
        global $wpdb;

        // Cheap guard: only do the SHOW COLUMNS check once per install.
        if (get_option('hrb_price_override_migrated') === 'yes') {
            return;
        }

        $bookings_table = $wpdb->prefix . 'hrb_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$bookings_table}'")) {
            $columns = array_column($wpdb->get_results("SHOW COLUMNS FROM {$bookings_table}"), 'Field');
            if (!in_array('price_override', $columns)) {
                $wpdb->query("ALTER TABLE {$bookings_table} ADD COLUMN price_override tinyint(1) NOT NULL DEFAULT 0 AFTER total_amount");
            }
        }

        update_option('hrb_price_override_migrated', 'yes');
    }

    public static function seed_branded_email_templates() {
        global $wpdb;

        // Bump this when the bundled templates change to re-sync on the next load.
        $design_version = '2026-07-16';
        if (get_option('hrb_email_design_version') === $design_version) {
            return;
        }

        $file = HRB_PLUGIN_DIR . 'includes/email-templates-data.php';
        if (!file_exists($file)) {
            return;
        }

        $templates = include $file;
        if (!is_array($templates) || empty($templates)) {
            return;
        }

        $table = $wpdb->prefix . 'hrb_email_templates';
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
            return;
        }

        foreach ($templates as $tpl) {
            if (empty($tpl['template_key']) || empty($tpl['template_type'])) {
                continue;
            }

            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE template_key = %s AND template_type = %s",
                $tpl['template_key'],
                $tpl['template_type']
            ));

            if ($existing_id) {
                $wpdb->update(
                    $table,
                    array(
                        'template_name' => $tpl['template_name'],
                        'subject'       => $tpl['subject'],
                        'heading'       => $tpl['heading'],
                        'message'       => $tpl['message'],
                        'html_content'  => $tpl['html_content'],
                    ),
                    array('id' => $existing_id),
                    array('%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
            } else {
                $wpdb->insert(
                    $table,
                    array(
                        'template_key'  => $tpl['template_key'],
                        'template_type' => $tpl['template_type'],
                        'template_name' => $tpl['template_name'],
                        'subject'       => $tpl['subject'],
                        'heading'       => $tpl['heading'],
                        'message'       => $tpl['message'],
                        'html_content'  => $tpl['html_content'],
                        'is_active'     => 1,
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
                );
            }
        }

        update_option('hrb_email_design_version', $design_version);
    }

    /**
     * Add template_type column to existing email_templates table
     */
    public static function add_template_type_column() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'hrb_email_templates';
        
        // Check if template_type column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'template_type'",
            DB_NAME, $table_name
        ));
        
        if (empty($column_exists)) {
            // Add the template_type column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN template_type varchar(20) NOT NULL DEFAULT 'user' AFTER template_name");
            
            // Update existing templates to be user type
            $wpdb->query("UPDATE $table_name SET template_type = 'user' WHERE template_type = '' OR template_type IS NULL");
            
            // Add unique constraint
            $wpdb->query("ALTER TABLE $table_name ADD UNIQUE KEY template_key_type (template_key, template_type)");
        }
    }
    
    /**
     * Add missing email templates for existing installations
     */
    public static function add_missing_templates() {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'hrb_email_templates';
        
        // Check if templates table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$templates_table'");
        if (!$table_exists) {
            return;
        }
        
        // Define missing templates
        $missing_templates = array(
            'invoice_regenerated_user' => array(
                'template_name' => 'Updated Invoice (User)',
                'template_type' => 'user',
                'subject' => 'Updated Invoice - {booking_reference}',
                'heading' => 'Updated Invoice',
                'message' => 'Your invoice has been updated. Please find the updated invoice attached to this email.',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0073aa; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .message-box { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .booking-details { background-color: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .detail-row { margin-bottom: 10px; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{company_name}</h1>
            <h2>{heading}</h2>
        </div>
        <div class="content">
            <div class="message-box">
                <p>Dear {customer_first_name},</p>
                <p>{message}</p>
            </div>
            <div class="booking-details">
                <h3>Invoice Details</h3>
                <div class="detail-row">
                    <span class="label">Booking Reference:</span>
                    <strong>{booking_reference}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Total Amount:</span>
                    <strong>{total_amount}</strong>
                </div>
            </div>
        </div>
        <div class="footer">
            <p>Thank you for choosing {company_name}!</p>
            <p>If you have any questions, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>'
            ),
            'booking_reminder_user' => array(
                'template_name' => 'Booking Reminder (User)',
                'template_type' => 'user',
                'subject' => 'Booking Reminder - {booking_reference}',
                'heading' => 'Booking Reminder',
                'message' => 'This is a reminder that your booking starts in 1 hour.',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #f39c12; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px; }
        .content { padding: 20px 0; }
        .booking-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .detail-row { display: flex; justify-content: space-between; margin: 8px 0; padding: 5px 0; border-bottom: 1px solid #eee; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
        .reminder { background: #f39c12; color: white; padding: 10px; border-radius: 5px; margin: 15px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Booking Reminder</h1>
            <p>Booking Reference: {booking_reference}</p>
        </div>
        
        <div class="content">
            <div class="reminder">
                <strong>Reminder:</strong> Your booking starts in 1 hour!
            </div>
            
            <div class="booking-details">
                <h3>Booking Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Room:</span>
                    <span class="detail-value">{room_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">{booking_date}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">{start_time} - {end_time}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span class="detail-value">{duration}</span>
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button" style="background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Booking Details</a>
            </p>
        </div>
        
        <div class="footer">
            <p>If you have any questions, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>'
            ),
            
            'booking_cancelled_user' => array(
                'template_name' => 'Booking Cancelled (User)',
                'template_type' => 'user',
                'subject' => 'Booking Cancelled - {booking_reference}',
                'heading' => 'Booking Cancelled',
                'message' => 'Your booking has been cancelled.',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Cancelled</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #e74c3c; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px; }
        .content { padding: 20px 0; }
        .booking-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .detail-row { display: flex; justify-content: space-between; margin: 8px 0; padding: 5px 0; border-bottom: 1px solid #eee; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
        .cancelled { background: #e74c3c; color: white; padding: 10px; border-radius: 5px; margin: 15px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Booking Cancelled</h1>
            <p>Booking Reference: {booking_reference}</p>
        </div>
        
        <div class="content">
            <div class="cancelled">
                <strong>Booking Cancelled</strong>
            </div>
            
            <div class="booking-details">
                <h3>Cancelled Booking Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Room:</span>
                    <span class="detail-value">{room_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">{booking_date}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">{start_time} - {end_time}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span class="detail-value">{duration}</span>
                </div>
            </div>
            
            <p>If you have any questions about this cancellation, please contact us at {company_email} or {company_phone}</p>
        </div>
        
        <div class="footer">
            <p>Thank you for using our booking service.</p>
        </div>
    </div>
</body>
</html>'
            ),
            
            'booking_modified_user' => array(
                'template_name' => 'Booking Modified (User)',
                'template_type' => 'user',
                'subject' => 'Booking Modified - {booking_reference}',
                'heading' => 'Booking Updated',
                'message' => 'Your booking has been modified. Please review the updated details:',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Modified</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #3498db; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px; }
        .content { padding: 20px 0; }
        .booking-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .detail-row { display: flex; justify-content: space-between; margin: 8px 0; padding: 5px 0; border-bottom: 1px solid #eee; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
        .modified { background: #3498db; color: white; padding: 10px; border-radius: 5px; margin: 15px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Booking Updated</h1>
            <p>Booking Reference: {booking_reference}</p>
        </div>
        
        <div class="content">
            <div class="modified">
                <strong>Booking Modified</strong>
            </div>
            
            <div class="booking-details">
                <h3>Updated Booking Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Room:</span>
                    <span class="detail-value">{room_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">{booking_date}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">{start_time} - {end_time}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span class="detail-value">{duration}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value">{total_amount}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value">{payment_method}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Booking Status:</span>
                    <span class="detail-value">{booking_status}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Status:</span>
                    <span class="detail-value">{payment_status}</span>
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button" style="background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Updated Booking</a>
            </p>
        </div>
        
        <div class="footer">
            <p>If you have any questions about these changes, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>'
            ),
            
            'otp_verification_user' => array(
                'template_name' => 'OTP Verification (User)',
                'template_type' => 'user',
                'subject' => 'Your Verification Code - {otp_code}',
                'heading' => 'Email Verification',
                'message' => 'Please use the following code to verify your email address:',
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #27ae60; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px; }
        .content { padding: 20px 0; }
        .otp-code { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; border: 2px dashed #27ae60; }
        .otp-number { font-size: 32px; font-weight: bold; color: #27ae60; letter-spacing: 5px; margin: 10px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
        .warning { background: #f39c12; color: white; padding: 10px; border-radius: 5px; margin: 15px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Email Verification</h1>
            <p>Please verify your email address</p>
        </div>
        
        <div class="content">
            <p>Hello {customer_name},</p>
            
            <p>To complete your booking, please verify your email address using the code below:</p>
            
            <div class="otp-code">
                <h3>Your Verification Code</h3>
                <div class="otp-number">{otp_code}</div>
                <p><small>This code will expire in 15 minutes</small></p>
            </div>
            
            <div class="warning">
                <strong>Important:</strong> Do not share this code with anyone. Our team will never ask for your verification code.
            </div>
            
            <p>If you did not request this verification, please ignore this email.</p>
        </div>
        
        <div class="footer">
            <p>If you have any questions, please contact us at {company_email} or {company_phone}</p>
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>'
            )
        );
        
        // Insert missing templates
        foreach ($missing_templates as $key => $template) {
            // Check if template already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $templates_table WHERE template_key = %s AND template_type = %s",
                $key, $template['template_type']
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $templates_table,
                    array(
                        'template_key' => $key,
                        'template_name' => $template['template_name'],
                        'template_type' => $template['template_type'],
                        'subject' => $template['subject'],
                        'heading' => $template['heading'],
                        'message' => $template['message'],
                        'html_content' => $template['html_content'],
                        'is_active' => 1
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
                );
            }
        }
        
        // Add new PayPal payment templates
        self::add_paypal_payment_templates();
        
    }
    
    /**
     * Add PayPal payment templates
     */
    public static function add_paypal_payment_templates() {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'hrb_email_templates';
        
        // Template 1: Online Payment Pending
        $online_payment_template = array(
            'template_key' => 'online_payment_pending',
            'template_name' => 'Online Payment',
            'template_type' => 'user',
            'subject' => 'PayPal-Zahlung ausstehend - Buchung {booking_reference}',
            'heading' => 'PayPal-Zahlung ausstehend',
            'message' => 'Die Zahlung über Paypal ist noch ausstehend. Sollte diese nicht innerhalb der nächsten 15min durchgeführt werden, wird Ihre Buchung auf Grund fehlender Zahlung storniert.',
            'html_content' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal-Zahlung ausstehend</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #0070ba, #005ea6); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; margin: -20px -20px 20px -20px; }
        .content { padding: 20px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .payment-button { display: inline-block; padding: 15px 30px; background: linear-gradient(135deg, #0070ba, #005ea6); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; text-align: center; font-weight: bold; font-size: 16px; }
        .payment-button:hover { background: linear-gradient(135deg, #005ea6, #004d8f); }
        .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PayPal-Zahlung ausstehend</h1>
        </div>
        <div class="content">
            <p>Sehr geehrte*r {customer_name},</p>
            
            <div class="warning">
                <strong>⚠️ Wichtiger Hinweis:</strong><br>
                Die Zahlung über PayPal ist noch ausstehend. Sollte diese nicht innerhalb der nächsten 15 Minuten durchgeführt werden, wird Ihre Buchung auf Grund fehlender Zahlung storniert.
            </div>
            
            <p><strong>Buchungsdetails:</strong></p>
            <ul>
                <li><strong>Buchungsnummer:</strong> {booking_reference}</li>
                <li><strong>Datum:</strong> {booking_date}</li>
                <li><strong>Zeit:</strong> {start_time} - {end_time}</li>
                <li><strong>Raum:</strong> {room_name}</li>
                <li><strong>Betrag:</strong> {total_amount} €</li>
            </ul>
            
            <p>Bitte führen Sie die PayPal-Zahlung so schnell wie möglich durch, um Ihre Buchung zu bestätigen.</p>
            
            <div style="text-align: center;">
                <a href="{payment_link}" class="payment-button">Jetzt mit PayPal bezahlen</a>
            </div>
        </div>
        <div class="footer">
            <p>Dies ist eine automatische Benachrichtigung von {company_name}.</p>
        </div>
    </div>
</body>
</html>'
        );
        
        $existing_online_template = $wpdb->get_row($wpdb->prepare(
            "SELECT id, html_content FROM {$templates_table} WHERE template_key = %s LIMIT 1",
            'online_payment_pending'
        ));
        
        if ($existing_online_template) {
            if (strpos($existing_online_template->html_content, '{payment_link}') === false) {
                $wpdb->update(
                    $templates_table,
                    array('html_content' => $online_payment_template['html_content']),
                    array('id' => $existing_online_template->id),
                    array('%s'),
                    array('%d')
                );
            }
        } else {
            $wpdb->insert($templates_table, $online_payment_template);
        }
        
        // Template 2: Payment Timeout Cancellation
        $payment_timeout_template = array(
            'template_key' => 'payment_timeout_cancellation',
            'template_name' => 'Payment Timeout Cancellation',
            'template_type' => 'user',
            'subject' => 'Ihre Buchung wurde storniert – fehlende PayPal-Zahlung',
            'heading' => 'Buchung storniert - Zahlung nicht rechtzeitig erfolgt',
            'message' => 'Sehr geehrte*r [Name], leider konnten wir innerhalb der vorgesehenen Frist keine abgeschlossene PayPal-Zahlung zu Ihrer Buchung feststellen. Da unser System das Zimmer nur für 15 Minuten reserviert, wurde Ihre Buchung automatisch storniert, da die Zahlung nicht rechtzeitig durchgeführt wurde. Sollten Sie weiterhin Interesse an einer Buchung haben, können Sie gerne eine neue Reservierung über unser System vornehmen. Bei Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung. Mit freundlichen Grüßen',
            'html_content' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buchung storniert - Zahlung nicht rechtzeitig erfolgt</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; margin: -20px -20px 20px -20px; }
        .content { padding: 20px 0; }
        .info-box { background: #e9ecef; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Buchung storniert</h1>
        </div>
        <div class="content">
            <p>Sehr geehrte*r {customer_name},</p>
            
            <div class="info-box">
                <strong>❌ Buchung storniert</strong><br>
                Leider konnten wir innerhalb der vorgesehenen Frist keine abgeschlossene PayPal-Zahlung zu Ihrer Buchung feststellen.
            </div>
            
            <p>Da unser System das Zimmer nur für 15 Minuten reserviert, wurde Ihre Buchung automatisch storniert, da die Zahlung nicht rechtzeitig durchgeführt wurde.</p>
            
            <p><strong>Stornierte Buchungsdetails:</strong></p>
            <ul>
                <li><strong>Buchungsnummer:</strong> {booking_reference}</li>
                <li><strong>Datum:</strong> {booking_date}</li>
                <li><strong>Zeit:</strong> {start_time} - {end_time}</li>
                <li><strong>Raum:</strong> {room_name}</li>
                <li><strong>Betrag:</strong> {total_amount} €</li>
            </ul>
            
            <p>Sollten Sie weiterhin Interesse an einer Buchung haben, können Sie gerne eine neue Reservierung über unser System vornehmen.</p>
            
            <p>Bei Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.</p>
            
            <p>Mit freundlichen Grüßen<br>
            {company_name}</p>
        </div>
        <div class="footer">
            <p>Dies ist eine automatische Benachrichtigung von {company_name}.</p>
        </div>
    </div>
</body>
</html>'
        );
        
        // Template 3: PayPal Payment Required (when payment method is changed to PayPal)
        $paypal_payment_required_template = array(
            'template_key' => 'paypal_payment_required_user',
            'template_name' => 'PayPal Payment Required',
            'template_type' => 'user',
            'subject' => 'PayPal-Zahlung erforderlich - Buchung {booking_reference}',
            'heading' => 'PayPal-Zahlung erforderlich',
            'message' => 'Ihre Buchung wurde auf PayPal-Zahlung umgestellt. Bitte führen Sie die Zahlung über den unten stehenden Link durch.',
            'html_content' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal-Zahlung erforderlich</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #0070ba, #005ea6); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; margin: -20px -20px 20px -20px; }
        .content { padding: 20px 0; }
        .booking-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .detail-row { display: flex; justify-content: space-between; margin: 8px 0; padding: 5px 0; border-bottom: 1px solid #eee; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .payment-button { display: inline-block; padding: 15px 30px; background: linear-gradient(135deg, #0070ba, #005ea6); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; text-align: center; font-weight: bold; font-size: 16px; }
        .payment-button:hover { background: linear-gradient(135deg, #005ea6, #004d8f); }
        .info-box { background: #e7f3ff; border-left: 4px solid #0070ba; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PayPal-Zahlung erforderlich</h1>
        </div>
        <div class="content">
            <p>Sehr geehrte*r {customer_name},</p>
            
            <p>Ihre Buchung wurde auf PayPal-Zahlung umgestellt. Bitte führen Sie die Zahlung über den unten stehenden Link durch.</p>
            
            <div class="booking-details">
                <h3>Buchungsdetails</h3>
                <div class="detail-row">
                    <span class="detail-label">Buchungsnummer:</span>
                    <span class="detail-value">{booking_reference}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Raum:</span>
                    <span class="detail-value">{room_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Datum:</span>
                    <span class="detail-value">{booking_date}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Zeit:</span>
                    <span class="detail-value">{start_time} - {end_time}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dauer:</span>
                    <span class="detail-value">{duration}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Betrag:</span>
                    <span class="detail-value"><strong>{total_amount}</strong></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Zahlungsmethode:</span>
                    <span class="detail-value">{payment_method}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Buchungsstatus:</span>
                    <span class="detail-value">{booking_status}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Zahlungsstatus:</span>
                    <span class="detail-value">{payment_status}</span>
                </div>
            </div>
            
            <div class="info-box">
                <strong>ℹ️ Wichtiger Hinweis:</strong><br>
                Bitte führen Sie die PayPal-Zahlung so schnell wie möglich durch, um Ihre Buchung zu bestätigen.
            </div>
            
            <div style="text-align: center;">
                <a href="{payment_link}" class="payment-button">Jetzt mit PayPal bezahlen</a>
            </div>
            
            <p>Falls Sie Fragen haben, kontaktieren Sie uns bitte unter {company_email} oder {company_phone}.</p>
        </div>
        <div class="footer">
            <p>Dies ist eine automatische Benachrichtigung von {company_name}.</p>
        </div>
    </div>
</body>
</html>'
        );
        
        // Template 4: Additional Payment Required (when admin adds services to already-paid booking)
        $additional_payment_required_template = array(
            'template_key' => 'additional_payment_required_user',
            'template_name' => 'Additional Payment Required',
            'template_type' => 'user',
            'subject' => 'Zusätzliche Zahlung erforderlich - Buchung {booking_reference}',
            'heading' => 'Zusätzliche Zahlung erforderlich',
            'message' => 'Ihre Buchung wurde um zusätzliche Dienstleistungen erweitert. Bitte führen Sie die Zahlung für die zusätzlichen Leistungen über den unten stehenden Link durch.',
            'html_content' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zusätzliche Zahlung erforderlich</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #ffc107, #ff9800); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; margin: -20px -20px 20px -20px; }
        .content { padding: 20px 0; }
        .booking-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .detail-row { display: flex; justify-content: space-between; margin: 8px 0; padding: 5px 0; border-bottom: 1px solid #eee; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .payment-button { display: inline-block; padding: 15px 30px; background: linear-gradient(135deg, #0070ba, #005ea6); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; text-align: center; font-weight: bold; font-size: 16px; }
        .payment-button:hover { background: linear-gradient(135deg, #005ea6, #004d8f); }
        .info-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .amount-highlight { background: #fff3cd; padding: 10px; border-radius: 5px; text-align: center; margin: 15px 0; font-size: 1.2em; font-weight: bold; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Zusätzliche Zahlung erforderlich</h1>
        </div>
        <div class="content">
            <p>Sehr geehrte*r {customer_name},</p>
            
            <p>Ihre Buchung wurde um zusätzliche Dienstleistungen erweitert. Bitte führen Sie die Zahlung für die zusätzlichen Leistungen über den unten stehenden Link durch.</p>
            
            <div class="booking-details">
                <h3>Buchungsdetails</h3>
                <div class="detail-row">
                    <span class="detail-label">Buchungsnummer:</span>
                    <span class="detail-value">{booking_reference}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Raum:</span>
                    <span class="detail-value">{room_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Datum:</span>
                    <span class="detail-value">{booking_date}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Zeit:</span>
                    <span class="detail-value">{start_time} - {end_time}</span>
                </div>
            </div>
            
            <div class="amount-highlight">
                Zusätzlicher Betrag: {additional_amount}
            </div>
            
            <div class="booking-details" style="margin-top: 20px;">
                <h3>Zusätzliche Leistungen:</h3>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 10px 0;">
                    {added_services}
                </div>
                <p style="margin-top: 10px; font-size: 0.9em; color: #666;">
                    Die oben genannten Leistungen wurden zu Ihrer Buchung hinzugefügt. Bitte führen Sie die Zahlung für diese zusätzlichen Leistungen durch.
                </p>
            </div>
            
            <div class="info-box">
                <strong>ℹ️ Wichtiger Hinweis:</strong><br>
                Bitte führen Sie die PayPal-Zahlung für die zusätzlichen Leistungen so schnell wie möglich durch.
            </div>
            
            <div style="text-align: center;">
                <a href="{payment_link}" class="payment-button">Jetzt zusätzliche Zahlung durchführen</a>
            </div>
            
            <p>Falls Sie Fragen haben, kontaktieren Sie uns bitte unter {company_email} oder {company_phone}.</p>
        </div>
        <div class="footer">
            <p>Dies ist eine automatische Benachrichtigung von {company_name}.</p>
        </div>
    </div>
</body>
</html>'
        );
        
        // Insert templates if they don't exist
        $templates_to_add = array(
            'online_payment_pending' => $online_payment_template,
            'payment_timeout_cancellation' => $payment_timeout_template,
            'paypal_payment_required_user' => $paypal_payment_required_template,
            'additional_payment_required_user' => $additional_payment_required_template
        );
        
        foreach ($templates_to_add as $key => $template) {
            // Check if template already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $templates_table WHERE template_key = %s AND template_type = %s",
                $key, $template['template_type']
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $templates_table,
                    array(
                        'template_key' => $template['template_key'],
                        'template_name' => $template['template_name'],
                        'template_type' => $template['template_type'],
                        'subject' => $template['subject'],
                        'heading' => $template['heading'],
                        'message' => $template['message'],
                        'html_content' => $template['html_content'],
                        'is_active' => 1
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
                );
            }
        }
    }
    
    /**
     * Update email templates to include booking status and payment status
     */
    public static function update_email_templates_with_status() {
        global $wpdb;
        $templates_table = $wpdb->prefix . 'hrb_email_templates';
        
        // Updated Booking Modified template (non-user version)
        $booking_modified_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #17a2b8; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .booking-details { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .detail-row { margin-bottom: 10px; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #17a2b8; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{company_name}</h1>
            <h2>{heading}</h2>
        </div>
        
        <div class="content">
            <p>Hello {customer_first_name},</p>
            <p>{message}</p>
            
            <div class="booking-details">
                <h3>Updated Booking Details</h3>
                <div class="detail-row">
                    <span class="label">Booking Reference:</span>
                    <strong>{booking_reference}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Room:</span>
                    {room_name}
                </div>
                <div class="detail-row">
                    <span class="label">Date:</span>
                    {booking_date}
                </div>
                <div class="detail-row">
                    <span class="label">Time:</span>
                    {start_time} - {end_time}
                </div>
                <div class="detail-row">
                    <span class="label">Duration:</span>
                    {duration}
                </div>
                <div class="detail-row">
                    <span class="label">Total Amount:</span>
                    <strong>{total_amount}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Payment Method:</span>
                    {payment_method}
                </div>
                <div class="detail-row">
                    <span class="label">Booking Status:</span>
                    {booking_status}
                </div>
                <div class="detail-row">
                    <span class="label">Payment Status:</span>
                    {payment_status}
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button">View Updated Booking</a>
            </p>
        </div>
        
        <div class="footer">
            <p>If you have any questions about these changes, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>';
        
        // Updated Booking Modified User template
        $booking_modified_user_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Modified</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #3498db; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px; }
        .content { padding: 20px 0; }
        .booking-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .detail-row { display: flex; justify-content: space-between; margin: 8px 0; padding: 5px 0; border-bottom: 1px solid #eee; }
        .detail-label { font-weight: bold; color: #555; }
        .detail-value { color: #333; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
        .modified { background: #3498db; color: white; padding: 10px; border-radius: 5px; margin: 15px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Booking Updated</h1>
            <p>Booking Reference: {booking_reference}</p>
        </div>
        
        <div class="content">
            <div class="modified">
                <strong>Booking Modified</strong>
            </div>
            
            <div class="booking-details">
                <h3>Updated Booking Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Room:</span>
                    <span class="detail-value">{room_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">{booking_date}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">{start_time} - {end_time}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span class="detail-value">{duration}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value">{total_amount}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value">{payment_method}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Booking Status:</span>
                    <span class="detail-value">{booking_status}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Status:</span>
                    <span class="detail-value">{payment_status}</span>
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button" style="background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Updated Booking</a>
            </p>
        </div>
        
        <div class="footer">
            <p>If you have any questions about these changes, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>';
        
        // Updated Payment Confirmation template
        $payment_confirmation_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .booking-details { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .detail-row { margin-bottom: 10px; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{company_name}</h1>
            <h2>{heading}</h2>
        </div>
        
        <div class="content">
            <p>Hello {customer_first_name},</p>
            <p>{message}</p>
            
            <div class="booking-details">
                <h3>Payment Details</h3>
                <div class="detail-row">
                    <span class="label">Booking Reference:</span>
                    <strong>{booking_reference}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Room:</span>
                    {room_name}
                </div>
                <div class="detail-row">
                    <span class="label">Date:</span>
                    {booking_date}
                </div>
                <div class="detail-row">
                    <span class="label">Time:</span>
                    {start_time} - {end_time}
                </div>
                <div class="detail-row">
                    <span class="label">Duration:</span>
                    {duration}
                </div>
                <div class="detail-row">
                    <span class="label">Amount Paid:</span>
                    <strong>{total_amount}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Payment Method:</span>
                    {payment_method}
                </div>
                <div class="detail-row">
                    <span class="label">Booking Status:</span>
                    {booking_status}
                </div>
                <div class="detail-row">
                    <span class="label">Payment Status:</span>
                    {payment_status}
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button">View Booking Details</a>
            </p>
        </div>
        
        <div class="footer">
            <p>Thank you for your payment!</p>
            <p>If you have any questions, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>';
        
        // Updated Booking Confirmation template
        $booking_confirmation_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
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
            <h1>{company_name}</h1>
            <h2>{heading}</h2>
        </div>
        
        <div class="content">
            <p>Hello {customer_first_name},</p>
            <p>{message}</p>
            
            <div class="booking-details">
                <h3>Booking Details</h3>
                <div class="detail-row">
                    <span class="label">Booking Reference:</span>
                    <strong>{booking_reference}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Room:</span>
                    {room_name}
                </div>
                <div class="detail-row">
                    <span class="label">Date:</span>
                    {booking_date}
                </div>
                <div class="detail-row">
                    <span class="label">Time:</span>
                    {start_time} - {end_time}
                </div>
                <div class="detail-row">
                    <span class="label">Duration:</span>
                    {duration}
                </div>
                <div class="detail-row">
                    <span class="label">Total Amount:</span>
                    <strong>{total_amount}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Payment Method:</span>
                    {payment_method}
                </div>
                <div class="detail-row">
                    <span class="label">Booking Status:</span>
                    {booking_status}
                </div>
                <div class="detail-row">
                    <span class="label">Payment Status:</span>
                    {payment_status}
                </div>
            </div>
            
            <p>
                <a href="{booking_url}" class="button">View Booking Details</a>
            </p>
        </div>
        
        <div class="footer">
            <p>Thank you for choosing {company_name}!</p>
            <p>If you have any questions, please contact us at {company_email} or {company_phone}</p>
        </div>
    </div>
</body>
</html>';
        
        // Update templates
        $templates_to_update = array(
            array(
                'key' => 'booking_confirmation_user',
                'type' => 'user',
                'html' => $booking_confirmation_html
            ),
            array(
                'key' => 'booking_modified',
                'type' => null, // This template might not have template_type
                'html' => $booking_modified_html
            ),
            array(
                'key' => 'booking_modified_user',
                'type' => 'user',
                'html' => $booking_modified_user_html
            ),
            array(
                'key' => 'payment_confirmation_user',
                'type' => 'user',
                'html' => $payment_confirmation_html
            )
        );
        
        foreach ($templates_to_update as $template) {
            // Check if template exists - try with template_type first, then without
            $exists = false;
            $where = array();
            $where_format = array();
            
            if ($template['type']) {
                // Check with template_type
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $templates_table WHERE template_key = %s AND template_type = %s",
                    $template['key'], $template['type']
                ));
                if ($exists) {
                    $where = array(
                        'template_key' => $template['key'],
                        'template_type' => $template['type']
                    );
                    $where_format = array('%s', '%s');
                }
            }
            
            // If not found with type, try without template_type (for legacy templates)
            if (!$exists && $template['key'] === 'booking_modified') {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $templates_table WHERE template_key = %s AND (template_type IS NULL OR template_type = '')",
                    $template['key']
                ));
                if ($exists) {
                    $where = array('template_key' => $template['key']);
                    $where_format = array('%s');
                }
            }
            
            if ($exists) {
                // Update existing template
                $wpdb->update(
                    $templates_table,
                    array('html_content' => $template['html']),
                    $where,
                    array('%s'),
                    $where_format
                );
            }
        }
    }
    
    /**
     * Add color column to existing rooms table
     */
    public static function add_room_color_column() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'hrb_rooms';
        
        // Check if color column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'color'",
            DB_NAME, $table_name
        ));
        
        if (empty($column_exists)) {
            // Add the color column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN color varchar(7) NOT NULL DEFAULT '#3498db' AFTER amenities");
        }
    }
    
    /**
     * Add payment_token column to payments table
     */
    public static function add_payment_token_column() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'hrb_payments';
        
        // Check if payment_token column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'payment_token'",
            DB_NAME, $table_name
        ));
        
        if (empty($column_exists)) {
            // Add the payment_token column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN payment_token varchar(64) NULL AFTER is_additional_payment");
            
            // Add index for payment_token
            $wpdb->query("ALTER TABLE $table_name ADD INDEX payment_token (payment_token)");
            
            // Generate tokens for existing pending additional payments
            $pending_payments = $wpdb->get_results(
                "SELECT id FROM {$table_name} 
                WHERE status = 'pending' 
                AND transaction_id LIKE 'ADD_%%' 
                AND payment_token IS NULL"
            );
            
            foreach ($pending_payments as $payment) {
                $token = wp_generate_password(32, false);
                $wpdb->update(
                    $table_name,
                    array('payment_token' => $token),
                    array('id' => $payment->id),
                    array('%s'),
                    array('%d')
                );
            }
        }
    }
    
}
?>