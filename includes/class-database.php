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
            images text,
            amenities text,
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
            status varchar(20) NOT NULL DEFAULT 'pending',
            payment_status varchar(20) NOT NULL DEFAULT 'pending',
            payment_method varchar(50) NULL,
            special_requests text NULL,
            admin_notes text NULL,
            created_by_admin tinyint(1) NOT NULL DEFAULT 0,
            cooldown_override tinyint(1) NOT NULL DEFAULT 0,
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
            refund_reason text NULL,
            gateway_response text NULL,
            processed_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY transaction_id (transaction_id),
            KEY gateway_transaction_id (gateway_transaction_id),
            KEY status (status)
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
            subject varchar(255) NOT NULL,
            heading varchar(255) NOT NULL,
            message text NOT NULL,
            html_content longtext NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY template_key (template_key)
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
            'email_templates' => $sql_email_templates
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
                    error_log("HRB Database CRITICAL: Failed to create {$table_name} table");
                    if ($wpdb->last_error) {
                        error_log("HRB Database SQL Error for {$table_name}: " . $wpdb->last_error);
                        error_log("HRB Database SQL Query: " . $sql);
                    }
                } else {
                    error_log("HRB Database: {$table_name} table created successfully with direct SQL");
                }
            } else {
                // Log success for debugging (skip during activation)
                if (defined('WP_DEBUG') && WP_DEBUG && !defined('HRB_ACTIVATION_MODE')) {
                    error_log("HRB Database: {$table_name} table created successfully with dbDelta");
                }
            }
        }
        
        // Fix existing table structures
        self::fix_existing_tables();

        // Add default data
        self::insert_default_data();

        // Update database version
        update_option('hrb_database_version', HRB_VERSION);

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

            // Rename price to unit_price if it exists and unit_price doesn't
            if (in_array('price', $column_names) && !in_array('unit_price', $column_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} CHANGE COLUMN price unit_price decimal(10,2) NOT NULL");
            }

            // Drop old price column if both unit_price and price exist
            if (in_array('price', $column_names) && in_array('unit_price', $column_names)) {
                $wpdb->query("ALTER TABLE {$booking_extras_table} DROP COLUMN price");
            }

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

            error_log("HRB Database: Fixed booking_extras table structure with time slot columns");
        }

        // Fix payments table
        $payments_table = $wpdb->prefix . 'hrb_payments';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $payments_table));

        if ($table_exists) {
            // Check if columns exist and add missing ones
            $columns = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM %s", $payments_table));
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

            error_log("HRB Database: Fixed payments table structure");
        }

        // Fix extras table - Add stock management columns
        $extras_table = $wpdb->prefix . 'hrb_extras';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $extras_table));

        if ($table_exists) {
            // Check if columns exist and add missing ones
            $columns = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM %s", $extras_table));
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

            error_log("HRB Database: Fixed extras table structure with stock management");
        }

        // Fix rooms table - Add new pricing columns
        $rooms_table = $wpdb->prefix . 'hrb_rooms';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rooms_table));

        if ($table_exists) {
            // Check if columns exist and add missing ones
            $columns = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM %s", $rooms_table));
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

            error_log("HRB Database: Fixed rooms table structure with new pricing columns");
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
            'booking_confirmation' => array(
                'template_name' => 'Booking Confirmation',
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
                    <span class="label">Status:</span>
                    {booking_status}
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
            'payment_confirmation' => array(
                'template_name' => 'Payment Confirmation',
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
                    <span class="label">Amount Paid:</span>
                    <strong>{total_amount}</strong>
                </div>
                <div class="detail-row">
                    <span class="label">Payment Method:</span>
                    {payment_method}
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
                    <span class="label">Status:</span>
                    {booking_status}
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
            )
        );

        $templates_table = $wpdb->prefix . 'hrb_email_templates';
        foreach ($default_templates as $key => $template) {
            $wpdb->insert(
                $templates_table,
                array(
                    'template_key' => $key,
                    'template_name' => $template['template_name'],
                    'subject' => $template['subject'],
                    'heading' => $template['heading'],
                    'message' => $template['message'],
                    'html_content' => $template['html_content'],
                    'is_active' => 1
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d')
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
     * Check if booking conflicts with existing bookings
     */
    public static function check_booking_conflict($room_id, $booking_date, $start_time, $end_time, $exclude_booking_id = null) {
        global $wpdb;

        $exclude_sql = '';

        // Get cooldown minutes setting (default 30 minutes)
        $cooldown_minutes = 30; // Hardcode for now, will be configurable later
        if (function_exists('get_option')) {
            $cooldown_minutes = intval(get_option('hrb_cooldown_minutes', 30));
        }

        // Calculate time with cooldown buffer
        $start_with_cooldown = date('H:i:s', strtotime($start_time) - ($cooldown_minutes * 60));
        $end_with_cooldown = date('H:i:s', strtotime($end_time) + ($cooldown_minutes * 60));

        $params = array($room_id, $booking_date, $start_with_cooldown, $end_with_cooldown, $start_with_cooldown, $end_with_cooldown);

        if ($exclude_booking_id) {
            $exclude_sql = ' AND id != %d';
            $params[] = $exclude_booking_id;
        }

        // Check for direct overlap (always blocked)
        // Parameters: room_id, booking_date, new_start, new_start, new_end, new_end, new_start, new_end
        $direct_params = array($room_id, $booking_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time);
        if ($exclude_booking_id) {
            $direct_params[] = $exclude_booking_id;
        }

        $direct_conflict = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_bookings
             WHERE room_id = %d
             AND booking_date = %s
             AND status NOT IN ('cancelled', 'no_show')
             AND (
                 (start_time <= %s AND end_time > %s) OR
                 (start_time < %s AND end_time >= %s) OR
                 (start_time >= %s AND end_time <= %s)
             )
             $exclude_sql",
            $direct_params
        ));

        // If direct conflict exists, return immediately
        if ($direct_conflict > 0) {
            return true;
        }

        // Check for cooldown conflicts (unless admin overrides)
        $cooldown_conflict = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_bookings
             WHERE room_id = %d
             AND booking_date = %s
             AND status NOT IN ('cancelled', 'no_show')
             AND cooldown_override = 0
             AND (
                 (start_time <= %s AND end_time > %s) OR
                 (start_time < %s AND end_time >= %s)
             )
             $exclude_sql",
            $params
        ));

        return ($cooldown_conflict > 0);
    }
    
    /**
     * Generate unique booking reference
     */
    public static function generate_booking_reference() {
        global $wpdb;
        
        do {
            $reference = 'HRB-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_bookings WHERE booking_reference = %s",
                $reference
            ));
        } while ($exists > 0);
        
        return $reference;
    }
}
?>