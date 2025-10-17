<?php
/**
 * Admin Interface Class
 * Handles WordPress admin interface and dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Admin {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_hrb_admin_action', array($this, 'handle_admin_ajax'));
        add_action('wp_ajax_hrb_get_booking_chart_data', array($this, 'ajax_get_booking_chart_data'));
        add_action('wp_ajax_hrb_get_room_details', array($this, 'ajax_get_room_details'));
        add_action('wp_ajax_hrb_get_calendar_events', array($this, 'ajax_get_calendar_events'));
        add_action('wp_ajax_hrb_get_calendar_stats', array($this, 'ajax_get_calendar_stats'));
        add_action('wp_ajax_hrb_get_booking_details', array($this, 'ajax_get_booking_details_modal'));
        add_action('wp_ajax_hrb_get_customer_details', array($this, 'ajax_get_customer_details'));
        add_action('wp_ajax_hrb_save_customer', array($this, 'ajax_save_customer'));
        add_action('wp_ajax_hrb_export_customer_data', array($this, 'ajax_export_customer_data'));
        add_action('wp_ajax_hrb_get_extra_details', array($this, 'ajax_get_extra_details'));
        add_action('wp_ajax_hrb_get_template', array($this, 'ajax_get_template'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_notices', array($this, 'add_admin_notices'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main dashboard
        add_menu_page(
            __('Room Bookings', 'hourly-room-booking'),
            __('Room Bookings', 'hourly-room-booking'),
            'hrb_view_bookings',
            'hrb-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-calendar-alt',
            30
        );
        
        // Bookings submenu
        add_submenu_page(
            'hrb-dashboard',
            __('All Bookings', 'hourly-room-booking'),
            __('All Bookings', 'hourly-room-booking'),
            'hrb_view_bookings',
            'hrb-bookings',
            array($this, 'bookings_page')
        );

        // Old Bookings submenu
        add_submenu_page(
            'hrb-dashboard',
            __('Old Bookings', 'hourly-room-booking'),
            __('Old Bookings', 'hourly-room-booking'),
            'hrb_view_bookings',
            'hrb-old-bookings',
            array($this, 'old_bookings_page')
        );
        
        // Rooms submenu
        add_submenu_page(
            'hrb-dashboard',
            __('Rooms', 'hourly-room-booking'),
            __('Rooms', 'hourly-room-booking'),
            'hrb_view_bookings',
            'hrb-rooms',
            array($this, 'rooms_page')
        );
        
        // Calendar submenu
        add_submenu_page(
            'hrb-dashboard',
            __('Calendar', 'hourly-room-booking'),
            __('Calendar', 'hourly-room-booking'),
            'hrb_view_calendar',
            'hrb-calendar',
            array($this, 'calendar_page')
        );
        
        // Customers submenu
        add_submenu_page(
            'hrb-dashboard',
            __('Customers', 'hourly-room-booking'),
            __('Customers', 'hourly-room-booking'),
            'hrb_view_customers',
            'hrb-customers',
            array($this, 'customers_page')
        );

        // Extras submenu
        add_submenu_page(
            'hrb-dashboard',
            __('Extras', 'hourly-room-booking'),
            __('Extras', 'hourly-room-booking'),
            'hrb_view_bookings',
            'hrb-extras',
            array($this, 'extras_page')
        );

        // Payments submenu
        add_submenu_page(
            'hrb-dashboard',
            __('Payments', 'hourly-room-booking'),
            __('Payments', 'hourly-room-booking'),
            'hrb_view_payments',
            'hrb-payments',
            array($this, 'payments_page')
        );
        
        // Reports submenu
        add_submenu_page(
            'hrb-dashboard',
            __('Reports', 'hourly-room-booking'),
            __('Reports', 'hourly-room-booking'),
            'hrb_view_reports',
            'hrb-reports',
            array($this, 'reports_page')
        );
        
        // Email Templates submenu
        add_submenu_page(
            'hrb-dashboard',
            __('Email Templates', 'hourly-room-booking'),
            __('Email Templates', 'hourly-room-booking'),
            'hrb_view_bookings',
            'hrb-email-templates',
            array($this, 'render_email_templates_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'hrb-dashboard',
            __('Settings', 'hourly-room-booking'),
            __('Settings', 'hourly-room-booking'),
            'hrb_manage_settings',
            'hrb-settings',
            array($this, 'settings_page')
        );
        
        // Guide submenu
        add_submenu_page(
            'hrb-dashboard',
            __('User Guide', 'hourly-room-booking'),
            __('User Guide', 'hourly-room-booking'),
            'hrb_view_bookings',
            'hrb-guide',
            array($this, 'guide_page')
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        // Add user roles and capabilities
        self::add_user_roles();
        
        // Register settings
        register_setting('hrb_settings', 'hrb_paypal_client_id');
        register_setting('hrb_settings', 'hrb_paypal_client_secret');
        register_setting('hrb_settings', 'hrb_paypal_sandbox');
        register_setting('hrb_settings', 'hrb_twilio_sid');
        register_setting('hrb_settings', 'hrb_twilio_token');
        register_setting('hrb_settings', 'hrb_twilio_from');
        register_setting('hrb_settings', 'hrb_whatsapp_token');
        register_setting('hrb_settings', 'hrb_whatsapp_phone_id');
        register_setting('hrb_settings', 'hrb_company_name');
        register_setting('hrb_settings', 'hrb_company_email');
        register_setting('hrb_settings', 'hrb_company_phone');
        register_setting('hrb_settings', 'hrb_company_address');
        register_setting('hrb_settings', 'hrb_staff_email');
        register_setting('hrb_settings', 'hrb_pricing_label');
        register_setting('hrb_settings', 'hrb_admin_email_notifications');
        register_setting('hrb_settings', 'hrb_staff_email_notifications');
        register_setting('hrb_settings', 'hrb_email_notifications');
        register_setting('hrb_settings', 'hrb_sms_notifications');
        register_setting('hrb_settings', 'hrb_whatsapp_notifications');
        register_setting('hrb_settings', 'hrb_tax_rate');
        register_setting('hrb_settings', 'hrb_booking_advance_days');
        register_setting('hrb_settings', 'hrb_cancellation_hours');
        register_setting('hrb_settings', 'hrb_cooldown_minutes');
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'hrb-') === false && $hook !== 'toplevel_page_hrb-dashboard') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_style('jquery-ui-style', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-ui/1.12.1/jquery-ui.min.css');
        
        // Enqueue media library for image uploads
        wp_enqueue_media();
        
        // Chart.js for reports
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);
        
        // FullCalendar for calendar view
        wp_enqueue_script('fullcalendar', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.3/main.min.js', array(), '5.11.3', true);
        wp_enqueue_style('fullcalendar', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.3/main.min.css', array(), '5.11.3');
        
        // Admin scripts and styles
        wp_enqueue_script('hrb-admin', HRB_PLUGIN_URL . 'admin/assets/js/admin.js', array('jquery', 'chart-js'), HRB_VERSION, true);
        wp_enqueue_style('hrb-admin', HRB_PLUGIN_URL . 'admin/assets/css/admin.css', array(), HRB_VERSION);
        
        wp_localize_script('hrb-admin', 'hrb_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hrb_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'hourly-room-booking'),
                'confirm_cancel' => __('Are you sure you want to cancel this booking?', 'hourly-room-booking'),
                'loading' => __('Loading...', 'hourly-room-booking'),
                'saved' => __('Changes saved successfully', 'hourly-room-booking'),
                'error' => __('An error occurred', 'hourly-room-booking')
            )
        ));
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $this->check_permissions('hrb_view_bookings');
        
        $booking_manager = HRB_Booking_Manager::getInstance();
        $payment_handler = HRB_Payment_Handler::getInstance();
        $room_manager = HRB_Room_Manager::getInstance();
        
        // Get dashboard statistics
        $stats = $this->get_dashboard_stats();
        $recent_bookings = $booking_manager->get_bookings(array('limit' => 5));
        $rooms = $room_manager->get_all_rooms();
        
        include HRB_PLUGIN_DIR . 'admin/views/dashboard.php';
        //$this->render_admin_footer();
    }
    
    /**
     * Bookings page
     */
    public function bookings_page() {
        $this->check_permissions('hrb_view_bookings');
        $booking_manager = HRB_Booking_Manager::getInstance();
        $room_manager = HRB_Room_Manager::getInstance();
        
        // Check if user is trying to add or edit a booking but doesn't have permission
        if (isset($_GET['action']) && in_array($_GET['action'], ['add', 'edit']) && !current_user_can('hrb_manage_bookings')) {
            wp_die(__('You do not have permission to manage bookings.', 'hourly-room-booking'));
        }
        
        // Handle actions
        if (isset($_POST['action']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'hrb_admin_action')) {
            $this->handle_booking_actions();
        }
        
        // Get filters
        $filters = array(
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'room_id' => isset($_GET['room_id']) ? intval($_GET['room_id']) : '',
            'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '',
            'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '',
            'customer_search' => isset($_GET['customer_search']) ? sanitize_text_field($_GET['customer_search']) : '',
            'limit' => 25,
            'offset' => isset($_GET['paged']) ? (intval($_GET['paged']) - 1) * 25 : 0
        );
        
        $bookings = $booking_manager->get_bookings($filters);
        $rooms = $room_manager->get_all_rooms();
        
        //$this->render_admin_header(__('All Bookings', 'hourly-room-booking'));
        include HRB_PLUGIN_DIR . 'admin/views/bookings.php';
        //$this->render_admin_footer();
    }

    /**
     * Old Bookings Page
     */
    public function old_bookings_page() {
        $this->check_permissions('hrb_view_bookings');
        include HRB_PLUGIN_DIR . 'admin/views/old-bookings.php';
    }
    
    /**
     * Rooms page
     */
    public function rooms_page() {
        $this->check_permissions('hrb_view_bookings');
        $room_manager = HRB_Room_Manager::getInstance();
        
        // Check if user is trying to add/edit a room but doesn't have permission
        if (isset($_GET['action']) && in_array($_GET['action'], ['add', 'edit']) && !current_user_can('hrb_manage_rooms')) {
            wp_die(__('You do not have permission to manage rooms.', 'hourly-room-booking'));
        }
        
        // Handle actions
        if (isset($_POST['action']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'hrb_admin_action')) {
            $this->handle_room_actions();
        }
        
        $rooms = $room_manager->get_all_rooms(false); // Include inactive rooms
        
        //$this->render_admin_header(__('Rooms Management', 'hourly-room-booking'));
        include HRB_PLUGIN_DIR . 'admin/views/rooms.php';
        //$this->render_admin_footer();
    }
    
    /**
     * Calendar page
     */
    public function calendar_page() {
        $this->check_permissions('hrb_view_calendar');
        $room_manager = HRB_Room_Manager::getInstance();
        $booking_manager = HRB_Booking_Manager::getInstance();
        
        $rooms = $room_manager->get_all_rooms();
        
        // Get calendar data for current month
        $current_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
        $start_of_month = date('Y-m-01', strtotime($current_date));
        $end_of_month = date('Y-m-t', strtotime($current_date));
        
        $bookings = $booking_manager->get_bookings(array(
            'start_date' => $start_of_month,
            'end_date' => $end_of_month,
            'limit' => 1000
        ));
        
        //$this->render_admin_header(__('Booking Calendar', 'hourly-room-booking'));
        include HRB_PLUGIN_DIR . 'admin/views/calendar.php';
        //$this->render_admin_footer();
    }
    
    /**
     * Customers page
     */
    public function customers_page() {
        $this->check_permissions('hrb_view_customers');
        global $wpdb;
        
        // Get customers with booking statistics
        $customers = $wpdb->get_results("
            SELECT c.*, 
                   COUNT(b.id) as total_bookings,
                   SUM(b.total_amount) as total_spent,
                   MAX(b.created_at) as last_booking
            FROM {$wpdb->prefix}hrb_customers c
            LEFT JOIN {$wpdb->prefix}hrb_bookings b ON c.id = b.customer_id
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        
        //$this->render_admin_header(__('Customers', 'hourly-room-booking'));
        include HRB_PLUGIN_DIR . 'admin/views/customers.php';
        //$this->render_admin_footer();
    }
    
    /**
     * Payments page
     */
    public function payments_page() {
        $this->check_permissions('hrb_view_payments');
        global $wpdb;
        
        // Handle payment actions
        if (isset($_POST['action']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'hrb_admin_action')) {
            $this->handle_payment_actions();
        }
        
        $payments = $wpdb->get_results("
            SELECT p.*, b.booking_reference, c.first_name, c.last_name, r.name as room_name
            FROM {$wpdb->prefix}hrb_payments p
            JOIN {$wpdb->prefix}hrb_bookings b ON p.booking_id = b.id
            JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
            JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            ORDER BY p.created_at DESC
            LIMIT 50
        ");
        
        //$this->render_admin_header(__('Payments', 'hourly-room-booking'));
        include HRB_PLUGIN_DIR . 'admin/views/payments.php';
        //$this->render_admin_footer();
    }
    
    /**
     * Reports page
     */
    public function reports_page() {
        $this->check_permissions('hrb_view_reports');
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
        
        $reports_data = $this->generate_reports($start_date, $end_date);
        
        //$this->render_admin_header(__('Reports & Analytics', 'hourly-room-booking'));
        include HRB_PLUGIN_DIR . 'admin/views/reports.php';
        //$this->render_admin_footer();
    }
    
    /**
     * Render email templates page
     */
    public function render_email_templates_page() {
        $this->check_permissions('hrb_view_bookings');
        
        // Handle form submissions
        if (isset($_POST['action']) && wp_verify_nonce($_POST['hrb_nonce'], 'hrb_email_template_action')) {
            $this->handle_email_template_actions();
        }
        
        // Get templates
        global $wpdb;
        $templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hrb_email_templates ORDER BY template_name ASC");
        
        include HRB_PLUGIN_DIR . 'admin/views/email-templates.php';
    }
    
    /**
     * Handle email template actions
     */
    public function handle_email_template_actions() {
        global $wpdb;
        
        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'update_template':
                $template_id = intval($_POST['template_id']);
                $template_data = array(
                    'template_name' => sanitize_text_field($_POST['template_name']),
                    'template_type' => sanitize_text_field($_POST['template_type']),
                    'subject' => sanitize_text_field($_POST['subject']),
                    'heading' => sanitize_text_field($_POST['heading']),
                    'message' => sanitize_textarea_field($_POST['message']),
                    'html_content' => wp_unslash($_POST['html_content']), // Allow HTML content including style tags
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                );
                
                $result = $wpdb->update(
                    $wpdb->prefix . 'hrb_email_templates',
                    $template_data,
                    array('id' => $template_id),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%d'),
                    array('%d')
                );
                
                if ($result !== false) {
                    echo '<div class="notice notice-success"><p>' . __('Template updated successfully.', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to update template.', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'toggle_template':
                $template_id = intval($_POST['template_id']);
                $is_active = intval($_POST['is_active']);
                
                $result = $wpdb->update(
                    $wpdb->prefix . 'hrb_email_templates',
                    array('is_active' => $is_active),
                    array('id' => $template_id),
                    array('%d'),
                    array('%d')
                );
                
                if ($result !== false) {
                    $status = $is_active ? __('activated', 'hourly-room-booking') : __('deactivated', 'hourly-room-booking');
                    echo '<div class="notice notice-success"><p>' . sprintf(__('Template %s successfully.', 'hourly-room-booking'), $status) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to update template status.', 'hourly-room-booking') . '</p></div>';
                }
                break;
        }
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        $this->check_permissions('hrb_manage_settings');

        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'hrb_settings')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'hourly-room-booking') . '</p></div>';
        }

        //$this->render_admin_header(__('Plugin Settings', 'hourly-room-booking'));
        include HRB_PLUGIN_DIR . 'admin/views/settings.php';
        //$this->render_admin_footer();
    }

    /**
     * Extras management page
     */
    public function extras_page() {
        $this->check_permissions('hrb_view_bookings');
        $extras_manager = HRB_Extras::getInstance();

        // Handle actions
        if (isset($_POST['action']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'hrb_admin_action')) {
            $this->handle_extras_actions();
        }

        //$this->render_admin_header(__('Manage Extras', 'hourly-room-booking'));
        include HRB_PLUGIN_DIR . 'admin/views/extras.php';
        //$this->render_admin_footer();
    }

    /**
     * Handle booking actions
     */
    private function handle_booking_actions() {
        // Check if user can manage bookings
        if (!current_user_can('hrb_manage_bookings')) {
            echo '<div class="notice notice-error"><p>' . __('You do not have permission to manage bookings.', 'hourly-room-booking') . '</p></div>';
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        $booking_manager = HRB_Booking_Manager::getInstance();
        
        switch ($action) {
            case 'update_status':
                $booking_id = intval($_POST['booking_id']);
                $new_status = sanitize_text_field($_POST['new_status']);
                $result = $booking_manager->update_booking($booking_id, array('status' => $new_status));
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Booking status updated successfully!', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'cancel_booking':
                $booking_id = intval($_POST['booking_id']);
                $reason = sanitize_textarea_field($_POST['cancel_reason']);
                $result = $booking_manager->cancel_booking($booking_id, $reason);
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Booking cancelled successfully!', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'mark_paid':
                $booking_id = intval($_POST['booking_id']);
                $payment_handler = HRB_Payment_Handler::getInstance();
                $result = $payment_handler->complete_onsite_payment($booking_id, 'Marked as paid by admin');
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Payment marked as completed!', 'hourly-room-booking') . '</p></div>';
                }
                break;
        }
    }
    
    /**
     * Handle room actions
     */
    private function handle_room_actions() {
        // Check if user can manage rooms
        if (!current_user_can('hrb_manage_rooms')) {
            echo '<div class="notice notice-error"><p>' . __('You do not have permission to manage rooms.', 'hourly-room-booking') . '</p></div>';
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        $room_manager = HRB_Room_Manager::getInstance();
        
        switch ($action) {
            case 'create_room':
                // Use input validator for room data
                $validator = HRB_Input_Validator::getInstance();
                
                // Prepare room data for validation
                $room_input = array(
                    'name' => $_POST['room_name'] ?? '',
                    'description' => $_POST['room_description'] ?? '',
                    'capacity' => $_POST['room_capacity'] ?? '',
                    'price_2_hours' => $_POST['room_price_2_hours'] ?? 0,
                    'price_3_hours' => $_POST['room_price_3_hours'] ?? 0,
                    'price_4_hours' => $_POST['room_price_4_hours'] ?? 0,
                    'price_extra_hour' => $_POST['room_price_extra_hour'] ?? 0,
                    'amenities' => isset($_POST['room_amenities']) ? 
                        array_filter(array_map('trim', explode(',', $_POST['room_amenities']))) : [],
                    'images' => isset($_POST['room_images']) ? 
                        array_filter(array_map('trim', explode(',', $_POST['room_images']))) : [],
                    'external_link' => $_POST['room_external_link'] ?? ''
                );
                
                $room_data = $validator->validate_room_data($room_input);
                if (is_wp_error($room_data)) {
                    wp_die($room_data->get_error_message());
                }
                
                
                // Add additional fields
                $room_data['is_active'] = isset($_POST['room_is_active']) ? 1 : 0;
                
                $result = $room_manager->create_room($room_data);
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Room created successfully!', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'update_room':
                $room_id = intval($_POST['room_id']);

                // Process amenities from comma-separated string to JSON array
                $amenities_input = isset($_POST['room_amenities']) ? sanitize_text_field($_POST['room_amenities']) : '';
                $amenities_array = array_filter(array_map('trim', explode(',', $amenities_input)));
                $amenities_json = !empty($amenities_array) ? json_encode($amenities_array) : '';

                // Handle images
                $images_input = isset($_POST['room_images']) ? sanitize_text_field($_POST['room_images']) : '';
                $images_json = '';
                if (!empty($images_input)) {
                    $images_array = array_filter(array_map('trim', explode(',', $images_input)));
                    $images_json = json_encode($images_array);
                }

                $room_data = array(
                    'name' => sanitize_text_field($_POST['room_name']),
                    'description' => sanitize_textarea_field($_POST['room_description']),
                    'capacity' => intval($_POST['room_capacity']),
                    'price_2_hours' => floatval($_POST['room_price_2_hours'] ?? 0),
                    'price_3_hours' => floatval($_POST['room_price_3_hours'] ?? 0),
                    'price_4_hours' => floatval($_POST['room_price_4_hours'] ?? 0),
                    'price_extra_hour' => floatval($_POST['room_price_extra_hour'] ?? 0),
                    'amenities' => $amenities_json,
                    'images' => $images_json,
                    'external_link' => sanitize_url($_POST['room_external_link'] ?? ''),
                    'is_active' => isset($_POST['room_is_active']) ? 1 : 0
                );
                
                $result = $room_manager->update_room($room_id, $room_data);
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Room updated successfully!', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'delete_room':
                $room_id = intval($_POST['room_id']);
                $force_delete = isset($_POST['force_delete']);
                $result = $room_manager->delete_room($room_id, $force_delete);

                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Room deleted successfully!', 'hourly-room-booking') . '</p></div>';
                }
                break;

            case 'toggle_room_status':
                $room_id = intval($_POST['room_id']);
                $result = $room_manager->toggle_room_status($room_id);

                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Room status updated successfully!', 'hourly-room-booking') . '</p></div>';
                }
                break;
        }
    }
    
    /**
     * Handle payment actions
     */
    private function handle_payment_actions() {
        // Check if user can manage payments
        if (!current_user_can('hrb_manage_payments')) {
            echo '<div class="notice notice-error"><p>' . __('You do not have permission to manage payments.', 'hourly-room-booking') . '</p></div>';
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        $payment_handler = HRB_Payment_Handler::getInstance();
        
        switch ($action) {
            case 'mark_payment_completed':
                $booking_id = intval($_POST['booking_id']);
                $notes = sanitize_textarea_field($_POST['payment_notes']);
                $result = $payment_handler->complete_onsite_payment($booking_id, $notes);
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Payment marked as completed!', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'process_refund':
                $booking_id = intval($_POST['booking_id']);
                $result = $payment_handler->process_refund($booking_id);
                
                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('Refund processed successfully!', 'hourly-room-booking') . '</p></div>';
                }
                break;
        }
    }

    /**
     * Handle extras actions
     */
    private function handle_extras_actions() {
        // Check if user can manage bookings (extras are part of booking management)
        if (!current_user_can('hrb_manage_bookings')) {
            echo '<div class="notice notice-error"><p>' . __('You do not have permission to manage extras.', 'hourly-room-booking') . '</p></div>';
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        $extras_manager = HRB_Extras::getInstance();

        switch ($action) {
            case 'create_extra':
                $name = sanitize_text_field($_POST['extra_name']);
                $description = sanitize_textarea_field($_POST['extra_description']);
                $price = floatval($_POST['extra_price']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $track_stock = isset($_POST['track_stock']) ? 1 : 0;
                $image_url = esc_url_raw($_POST['extra_image_url']);
                $is_active = isset($_POST['extra_is_active']) ? 1 : 0;
                $sort_order = intval($_POST['extra_sort_order']);

                $result = $extras_manager->create_extra([
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'stock_quantity' => $stock_quantity,
                    'track_stock' => $track_stock,
                    'image_url' => $image_url,
                    'is_active' => $is_active,
                    'sort_order' => $sort_order
                ]);

                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Extra created successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to create extra.', 'hourly-room-booking') . '</p></div>';
                }
                break;

            case 'update_extra':
                $extra_id = intval($_POST['extra_id']);
                $name = sanitize_text_field($_POST['extra_name']);
                $description = sanitize_textarea_field($_POST['extra_description']);
                $price = floatval($_POST['extra_price']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $track_stock = isset($_POST['track_stock']) ? 1 : 0;
                $image_url = esc_url_raw($_POST['extra_image_url']);
                $is_active = isset($_POST['extra_is_active']) ? 1 : 0;
                $sort_order = intval($_POST['extra_sort_order']);

                $result = $extras_manager->update_extra($extra_id, [
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'stock_quantity' => $stock_quantity,
                    'track_stock' => $track_stock,
                    'image_url' => $image_url,
                    'is_active' => $is_active,
                    'sort_order' => $sort_order
                ]);

                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Extra updated successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to update extra.', 'hourly-room-booking') . '</p></div>';
                }
                break;

            case 'delete_extra':
                $extra_id = intval($_POST['extra_id']);
                $result = $extras_manager->delete_extra($extra_id);

                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
                } elseif ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Extra deleted successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to delete extra.', 'hourly-room-booking') . '</p></div>';
                }
                break;

            case 'toggle_extra_status':
                $extra_id = intval($_POST['extra_id']);
                $result = $extras_manager->toggle_extra_status($extra_id);

                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Extra status updated successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to update extra status.', 'hourly-room-booking') . '</p></div>';
                }
                break;

            case 'update_sort_order':
                $extra_ids = $_POST['extra_ids'];
                if (is_array($extra_ids)) {
                    $result = $extras_manager->update_sort_order($extra_ids);
                    if ($result) {
                        echo '<div class="notice notice-success"><p>' . __('Sort order updated successfully!', 'hourly-room-booking') . '</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>' . __('Failed to update sort order.', 'hourly-room-booking') . '</p></div>';
                    }
                }
                break;
        }
    }

    /**
     * Generate reports
     */
    private function generate_reports($start_date, $end_date) {
        global $wpdb;
        
        $reports = array();
        
        // Booking statistics
        $reports['bookings'] = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show_bookings,
                AVG(total_hours) as avg_duration,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_booking_value
            FROM {$wpdb->prefix}hrb_bookings 
            WHERE booking_date BETWEEN %s AND %s
        ", $start_date, $end_date));
        
        // Payment statistics
        $reports['payments'] = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN payment_method = 'paypal' THEN 1 ELSE 0 END) as paypal_payments,
                SUM(CASE WHEN payment_method = 'onsite' THEN 1 ELSE 0 END) as onsite_payments,
                SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN payment_method = 'paypal' AND payment_status = 'paid' THEN total_amount * 0.03 ELSE 0 END) as paypal_fees
            FROM {$wpdb->prefix}hrb_bookings 
            WHERE booking_date BETWEEN %s AND %s
        ", $start_date, $end_date));
        
        return $reports;
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        $settings = array(
            'hrb_paypal_client_id',
            'hrb_paypal_client_secret',
            'hrb_paypal_sandbox',
            'hrb_twilio_sid',
            'hrb_twilio_token',
            'hrb_twilio_from',
            'hrb_whatsapp_token',
            'hrb_whatsapp_phone_id',
            'hrb_company_name',
            'hrb_company_email',
            'hrb_company_phone',
            'hrb_company_address',
            'hrb_company_logo',
            'hrb_email_notifications',
            'hrb_sms_notifications',
            'hrb_whatsapp_notifications',
            'hrb_tax_rate',
            'hrb_booking_advance_days',
            'hrb_cancellation_hours',
            'hrb_cooldown_minutes'
        );
        
        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                update_option($setting, sanitize_text_field($_POST[$setting]));
            }
        }
        
        // Handle checkbox settings
        $checkbox_settings = array(
            'hrb_paypal_sandbox',
            'hrb_email_notifications',
            'hrb_sms_notifications',
            'hrb_whatsapp_notifications'
        );
        
        foreach ($checkbox_settings as $setting) {
            update_option($setting, isset($_POST[$setting]) ? 1 : 0);
        }
    }
    
    /**
     * Handle admin AJAX requests
     */
    public function handle_admin_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_die(__('Security check failed', 'hourly-room-booking'));
        }
        
        $action = sanitize_text_field($_POST['admin_action']);
        
        switch ($action) {
            case 'get_booking_details':
                $this->ajax_get_booking_details();
                break;
                
            case 'update_booking_status':
                $this->ajax_update_booking_status();
                break;
                
            case 'get_room_availability':
                $this->ajax_get_room_availability();
                break;
                
            case 'export_bookings':
                $this->ajax_export_bookings();
                break;
                
        case 'cleanup_expired_bookings':
            $this->ajax_cleanup_expired_bookings();
            break;
            
        case 'fix_extra_people_pricing':
            $this->ajax_fix_extra_people_pricing();
            break;
                
            default:
                wp_send_json_error(__('Invalid action', 'hourly-room-booking'));
        }
    }
    
    /**
     * AJAX: Get booking details
     */
    private function ajax_get_booking_details() {
        $booking_id = intval($_POST['booking_id']);
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            wp_send_json_error(__('Booking not found', 'hourly-room-booking'));
        }
        
        wp_send_json_success($booking);
    }
    
    /**
     * AJAX: Update booking status
     */
    private function ajax_update_booking_status() {
        $booking_id = intval($_POST['booking_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        $booking_manager = HRB_Booking_Manager::getInstance();
        $result = $booking_manager->update_booking($booking_id, array('status' => $new_status));
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Status updated successfully', 'hourly-room-booking'));
    }
    
    /**
     * AJAX: Get room availability
     */
    private function ajax_get_room_availability() {
        $room_id = intval($_POST['room_id']);
        $date = sanitize_text_field($_POST['date']);
        
        $room_manager = HRB_Room_Manager::getInstance();
        $availability = $room_manager->get_room_availability($room_id, $date);
        
        wp_send_json_success($availability);
    }
    
    /**
     * AJAX: Export bookings to CSV
     */
    private function ajax_export_bookings() {
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        
        $booking_manager = HRB_Booking_Manager::getInstance();
        $bookings = $booking_manager->get_bookings(array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'limit' => 10000
        ));
        
        // Generate CSV
        $csv_data = "Booking Reference,Customer Name,Email,Phone,Room,Date,Start Time,End Time,Duration,Total Amount,Payment Status,Status\n";
        
        foreach ($bookings as $booking) {
            $csv_data .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%.2f","%.2f €","%s","%s"' . "\n",
                $booking->booking_reference,
                $booking->first_name . ' ' . $booking->last_name,
                $booking->email,
                $booking->phone,
                $booking->room_name,
                $booking->booking_date,
                $booking->start_time,
                $booking->end_time,
                $booking->total_hours,
                $booking->total_amount,
                hrb_get_payment_status_label($booking->payment_status),
                hrb_get_booking_status_label($booking->status)
            );
        }
        
        wp_send_json_success(array(
            'csv_data' => $csv_data,
            'filename' => 'bookings_' . $start_date . '_to_' . $end_date . '.csv'
        ));
    }
    
    /**
     * Add custom user roles
     */
    public static function add_user_roles() {
        // Remove existing role first to ensure clean update
        remove_role('hrb_staff');
        
        // Add room booking staff role (VIEW-ONLY)
        add_role('hrb_staff', __('Room Booking Staff', 'hourly-room-booking'), array(
            'read' => true,
            'hrb_view_bookings' => true,
            'hrb_manage_bookings' => false,  // Staff cannot manage bookings
            'hrb_view_calendar' => true,
            'hrb_view_customers' => true,
            'hrb_view_payments' => true,
            'hrb_view_reports' => true,
            'hrb_manage_settings' => false,  // Staff cannot access settings
            'hrb_manage_rooms' => false,    // Staff cannot manage rooms
            'hrb_manage_customers' => false, // Staff cannot manage customers
            'hrb_manage_payments' => false   // Staff cannot manage payments
        ));
        
        // Force update existing users with hrb_staff role
        self::update_existing_staff_capabilities();
        
        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('hrb_view_bookings');
            $admin_role->add_cap('hrb_manage_bookings');
            $admin_role->add_cap('hrb_manage_rooms');
            $admin_role->add_cap('hrb_view_calendar');
            $admin_role->add_cap('hrb_view_customers');
            $admin_role->add_cap('hrb_manage_customers');
            $admin_role->add_cap('hrb_view_payments');
            $admin_role->add_cap('hrb_manage_payments');
            $admin_role->add_cap('hrb_view_reports');
            $admin_role->add_cap('hrb_manage_settings');
        }
    }
    
    /**
     * Update existing staff users with correct capabilities
     */
    public static function update_existing_staff_capabilities() {
        // Get all users with hrb_staff role
        $staff_users = get_users(array('role' => 'hrb_staff'));
        
        foreach ($staff_users as $user) {
            // Remove old capabilities that might exist
            $user->remove_cap('hrb_manage_bookings');
            $user->remove_cap('hrb_manage_rooms');
            $user->remove_cap('hrb_manage_customers');
            $user->remove_cap('hrb_manage_payments');
            $user->remove_cap('hrb_manage_settings');
            
            // Ensure they have the correct view-only capabilities
            $user->add_cap('hrb_view_bookings');
            $user->add_cap('hrb_view_calendar');
            $user->add_cap('hrb_view_customers');
            $user->add_cap('hrb_view_payments');
            $user->add_cap('hrb_view_reports');
        }
    }
    
    /**
     * Remove custom user roles
     */
    public static function remove_user_roles() {
        remove_role('hrb_staff');
        
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->remove_cap('hrb_view_bookings');
            $admin_role->remove_cap('hrb_manage_bookings');
            $admin_role->remove_cap('hrb_manage_rooms');
            $admin_role->remove_cap('hrb_view_calendar');
            $admin_role->remove_cap('hrb_view_customers');
            $admin_role->remove_cap('hrb_manage_customers');
            $admin_role->remove_cap('hrb_view_payments');
            $admin_role->remove_cap('hrb_manage_payments');
            $admin_role->remove_cap('hrb_view_reports');
            $admin_role->remove_cap('hrb_manage_settings');
        }
    }
    
    /**
     * Add admin notices for important information
     */
    public function add_admin_notices() {
        $screen = get_current_screen();
        
        // Only show on plugin pages
        if (!$screen || strpos($screen->base, 'hrb-') === false) {
            return;
        }
        
        // Check if PayPal is configured
        $paypal_client_id = get_option('hrb_paypal_client_id');
        if (empty($paypal_client_id) && current_user_can('manage_options')) {
            echo '<div class="notice notice-warning">
                <p><strong>Room Booking Plugin:</strong> ' . 
                sprintf(__('PayPal is not configured. <a href="%s">Configure it now</a> to accept online payments.', 'hourly-room-booking'), 
                admin_url('admin.php?page=hrb-settings')) . 
                '</p>
            </div>';
        }
        
        // Check if notification methods are configured
        $email_enabled = get_option('hrb_email_notifications', 1);
        $sms_enabled = get_option('hrb_sms_notifications', 0);
        $whatsapp_enabled = get_option('hrb_whatsapp_notifications', 0);
        
        if (!$email_enabled && !$sms_enabled && !$whatsapp_enabled) {
            echo '<div class="notice notice-warning">
                <p><strong>Room Booking Plugin:</strong> ' . 
                sprintf(__('No notification methods are enabled. <a href="%s">Enable notifications</a> to keep customers informed.', 'hourly-room-booking'), 
                admin_url('admin.php?page=hrb-settings')) . 
                '</p>
            </div>';
        }
    }
    
    /**
     * Check user permissions for admin pages
     */
    private function check_permissions($capability = 'hrb_view_bookings') {
        if (!current_user_can($capability)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'hourly-room-booking'));
        }
    }
    
    /**
     * Get admin page URL
     */
    public function get_admin_url($page, $args = array()) {
        $url = admin_url('admin.php?page=' . $page);
        
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }
        
        return $url;
    }
    
    /**
     * Render admin page header
     */
    private function render_admin_header($title, $description = '') {
        echo '<div class="wrap hrb-admin-wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html($title) . '</h1>';
        
        if (!empty($description)) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        
        echo '<hr class="wp-header-end">';
    }
    
    /**
     * Render admin page footer
     */
    private function render_admin_footer() {
        echo '</div>'; // Close .wrap
    }
    
    
    /**
     * Format currency for display
     */
    public function format_currency($amount) {
        return number_format(floatval($amount), 2, ',', '.') . ' €';
    }
    
    /**
     * Format date for display
     */
    public function format_date($date, $format = null) {
        if (!$format) {
            $format = get_option('date_format');
        }
        
        return date_i18n($format, strtotime($date));
    }
    
    /**
     * Format time for display
     */
    public function format_time($time, $format = null) {
        if (!$format) {
            $format = get_option('time_format');
        }
        
        return date_i18n($format, strtotime($time));
    }
    
    /**
     * Get pagination HTML
     */
    public function get_pagination($current_page, $total_items, $per_page = 25, $base_url = '') {
        if ($total_items <= $per_page) {
            return '';
        }
        
        $total_pages = ceil($total_items / $per_page);
        
        if (empty($base_url)) {
            $base_url = remove_query_arg('paged');
        }
        
        $pagination_args = array(
            'base' => add_query_arg('paged', '%#%', $base_url),
            'format' => '',
            'prev_text' => __('&laquo; Previous', 'hourly-room-booking'),
            'next_text' => __('Next &raquo;', 'hourly-room-booking'),
            'current' => $current_page,
            'total' => $total_pages,
            'type' => 'array'
        );
        
        $links = paginate_links($pagination_args);
        
        if (!$links) {
            return '';
        }
        
        $html = '<div class="hrb-pagination tablenav-pages">';
        $html .= '<span class="displaying-num">' . sprintf(_n('%s item', '%s items', $total_items, 'hourly-room-booking'), number_format_i18n($total_items)) . '</span>';
        $html .= '<span class="pagination-links">';
        
        foreach ($links as $link) {
            $html .= $link;
        }
        
        $html .= '</span></div>';
        
        return $html;
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'hrb_dashboard_overview',
            __('Room Bookings Overview', 'hourly-room-booking'),
            array($this, 'dashboard_widget_overview')
        );
        
        wp_add_dashboard_widget(
            'hrb_dashboard_recent_bookings',
            __('Recent Bookings', 'hourly-room-booking'),
            array($this, 'dashboard_widget_recent_bookings')
        );
    }
    
    /**
     * Dashboard widget: Overview
     */
    public function dashboard_widget_overview() {
        $stats = $this->get_dashboard_stats();
        
        echo '<div class="hrb-dashboard-widget">';
        echo '<div class="hrb-stat-grid">';
        
        printf(
            '<div class="hrb-stat-item">
                <div class="hrb-stat-number">%d</div>
                <div class="hrb-stat-label">%s</div>
            </div>',
            $stats['todays_bookings'],
            __('Today\'s Bookings', 'hourly-room-booking')
        );
        
        printf(
            '<div class="hrb-stat-item">
                <div class="hrb-stat-number">%s</div>
                <div class="hrb-stat-label">%s</div>
            </div>',
            $this->format_currency($stats['monthly_revenue']),
            __('Monthly Revenue', 'hourly-room-booking')
        );
        
        printf(
            '<div class="hrb-stat-item">
                <div class="hrb-stat-number">%d</div>
                <div class="hrb-stat-label">%s</div>
            </div>',
            $stats['pending_payments'],
            __('Pending Payments', 'hourly-room-booking')
        );
        
        printf(
            '<div class="hrb-stat-item">
                <div class="hrb-stat-number">%d</div>
                <div class="hrb-stat-label">%s</div>
            </div>',
            $stats['active_rooms'],
            __('Active Rooms', 'hourly-room-booking')
        );
        
        echo '</div>';
        
        printf(
            '<p><a href="%s" class="button">%s</a></p>',
            $this->get_admin_url('hrb-dashboard'),
            __('View Full Dashboard', 'hourly-room-booking')
        );
        
        echo '</div>';
    }
    
    /**
     * Dashboard widget: Recent bookings
     */
    public function dashboard_widget_recent_bookings() {
        $booking_manager = HRB_Booking_Manager::getInstance();
        $recent_bookings = $booking_manager->get_bookings(array('limit' => 5));
        
        if (empty($recent_bookings)) {
            echo '<p>' . __('No recent bookings found.', 'hourly-room-booking') . '</p>';
            return;
        }
        
        echo '<div class="hrb-recent-bookings">';
        
        foreach ($recent_bookings as $booking) {
            printf(
                '<div class="hrb-recent-booking-item">
                    <div class="hrb-booking-info">
                        <strong>%s</strong> - %s<br>
                        <small>%s at %s | %s</small>
                    </div>
                    <div class="hrb-booking-status">
                        %s
                    </div>
                </div>',
                esc_html($booking->first_name . ' ' . $booking->last_name),
                esc_html($booking->room_name),
                $this->format_date($booking->booking_date),
                $this->format_time($booking->start_time),
                $this->format_currency($booking->total_amount),
                $this->get_status_badge($booking->status)
            );
        }
        
        printf(
            '<p><a href="%s" class="button">%s</a></p>',
            $this->get_admin_url('hrb-bookings'),
            __('View All Bookings', 'hourly-room-booking')
        );
        
        echo '</div>';
    }

    /**
     * Get dashboard statistics
     *
     * @since 1.0.0
     * @return array Dashboard statistics
     */
    public function get_dashboard_stats(): array {
        global $wpdb;

        $today = date('Y-m-d');
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');

        // Today's bookings
        $today_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}hrb_bookings
            WHERE booking_date = %s
            AND status NOT IN ('cancelled', 'no_show')
        ", $today));

        // This month's revenue
        $month_revenue = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM {$wpdb->prefix}hrb_bookings
            WHERE booking_date BETWEEN %s AND %s
            AND status IN ('confirmed', 'completed')
        ", $month_start, $month_end));

        // Total active rooms
        $total_rooms = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}hrb_rooms
            WHERE is_active = 1
        ");

        // Pending payments
        $pending_payments = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}hrb_bookings
            WHERE payment_status = 'pending'
            AND status NOT IN ('cancelled', 'no_show')
        ");

        return [
            'today_bookings' => intval($today_bookings),
            'month_revenue' => floatval($month_revenue),
            'total_rooms' => intval($total_rooms),
            'pending_payments' => intval($pending_payments)
        ];
    }

    /**
     * Get recent bookings for dashboard
     *
     * @since 1.0.0
     * @param int $limit Number of bookings to retrieve
     * @return array Recent bookings
     */
    public function get_recent_bookings(int $limit = 10): array {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT
                b.id,
                b.booking_reference,
                b.customer_id,
                b.room_id,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.status,
                b.total_amount,
                b.payment_status,
                b.created_at,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                r.name as room_name
            FROM {$wpdb->prefix}hrb_bookings b
            LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
            LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            ORDER BY b.created_at DESC
            LIMIT %d
        ", $limit);

        return $wpdb->get_results($query, ARRAY_A) ?: [];
    }

    /**
     * Get status badge HTML
     *
     * @since 1.0.0
     * @param string $status Status to generate badge for
     * @return string Status badge HTML
     */
    public function get_status_badge(string $status): string {
        $badges = [
            'pending' => ['class' => 'hrb-status-pending', 'text' => __('Pending', 'hourly-room-booking')],
            'confirmed' => ['class' => 'hrb-status-confirmed', 'text' => __('Confirmed', 'hourly-room-booking')],
            'completed' => ['class' => 'hrb-status-completed', 'text' => __('Completed', 'hourly-room-booking')],
            'cancelled' => ['class' => 'hrb-status-cancelled', 'text' => __('Cancelled', 'hourly-room-booking')],
            'no_show' => ['class' => 'hrb-status-no_show', 'text' => __('No Show', 'hourly-room-booking')]
        ];

        $badge = $badges[$status] ?? $badges['pending'];

        return sprintf(
            '<span class="hrb-status-badge %s">%s</span>',
            esc_attr($badge['class']),
            esc_html($badge['text'])
        );
    }

    /**
     * Get payment status badge
     *
     * @since 1.0.0
     * @param string $status Payment status to generate badge for
     * @return string Payment status badge HTML
     */
    public function get_payment_status_badge(string $status): string {
        $badges = [
            'pending' => ['class' => 'hrb-payment-status-pending', 'text' => __('Pending', 'hourly-room-booking')],
            'completed' => ['class' => 'hrb-payment-status-completed', 'text' => __('Completed', 'hourly-room-booking')],
            'failed' => ['class' => 'hrb-payment-status-failed', 'text' => __('Failed', 'hourly-room-booking')],
            'refunded' => ['class' => 'hrb-payment-status-refunded', 'text' => __('Refunded', 'hourly-room-booking')],
            'partially_refunded' => ['class' => 'hrb-payment-status-partially_refunded', 'text' => __('Partially Refunded', 'hourly-room-booking')]
        ];

        $badge = $badges[$status] ?? $badges['pending'];

        return sprintf(
            '<span class="hrb-payment-status-badge %s">%s</span>',
            esc_attr($badge['class']),
            esc_html($badge['text'])
        );
    }

    /**
     * AJAX handler for booking chart data
     *
     * @since 1.0.0
     */
    public function ajax_get_booking_chart_data(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        global $wpdb;

        // Get data for last 30 days
        $data = [];
        $labels = [];
        $bookings = [];
        $revenue = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $display_date = date('M j', strtotime($date));

            // Get bookings count for this date
            $booking_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$wpdb->prefix}hrb_bookings
                WHERE booking_date = %s
                AND status NOT IN ('cancelled', 'no_show')
            ", $date));

            // Get revenue for this date
            $day_revenue = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(SUM(total_amount), 0)
                FROM {$wpdb->prefix}hrb_bookings
                WHERE booking_date = %s
                AND status IN ('confirmed', 'completed')
            ", $date));

            $labels[] = $display_date;
            $bookings[] = intval($booking_count);
            $revenue[] = floatval($day_revenue);
        }

        wp_send_json_success([
            'labels' => $labels,
            'bookings' => $bookings,
            'revenue' => $revenue
        ]);
    }

    /**
     * AJAX handler to get room details for editing
     */
    public function ajax_get_room_details() {
        check_ajax_referer('hrb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        $room_id = intval($_POST['room_id']);

        if (!$room_id) {
            wp_send_json_error(__('Invalid room ID', 'hourly-room-booking'));
            return;
        }

        $room_manager = HRB_Room_Manager::getInstance();
        $room = $room_manager->get_room($room_id);

        if (!$room) {
            wp_send_json_error(__('Room not found', 'hourly-room-booking'));
            return;
        }

        // Convert amenities from JSON to comma-separated string for the form
        $amenities_display = '';
        if (!empty($room->amenities)) {
            $amenities_array = json_decode($room->amenities, true);
            if (is_array($amenities_array)) {
                $amenities_display = implode(', ', $amenities_array);
            } else {
                // Fallback for string format
                $amenities_display = $room->amenities;
            }
        }

        wp_send_json_success([
            'id' => $room->id,
            'name' => $room->name,
            'description' => $room->description,
            'capacity' => $room->capacity,
            'hourly_price' => $room->hourly_price,
            'price_2_hours' => $room->price_2_hours ?? 0,
            'price_3_hours' => $room->price_3_hours ?? 0,
            'price_4_hours' => $room->price_4_hours ?? 0,
            'price_extra_hour' => $room->price_extra_hour ?? 0,
            'amenities' => $amenities_display,
            'images' => $room->images, // Add images field
            'external_link' => $room->external_link ?? '',
            'is_active' => $room->is_active,
            'created_at' => $room->created_at,
            'updated_at' => $room->updated_at
        ]);
    }

    /**
     * AJAX handler for calendar events (admin version)
     *
     * @since 1.0.0
     */
    public function ajax_get_calendar_events(): void {
        try {
            check_ajax_referer('hrb_admin_nonce', 'nonce');

            $room_id = intval($_POST['room_id'] ?? 0);
            $start_date = sanitize_text_field($_POST['start'] ?? '');
            $end_date = sanitize_text_field($_POST['end'] ?? '');

            global $wpdb;

            // Build query based on room filter
            $where_room = '';
            $params = [$start_date, $end_date];

            if ($room_id > 0) {
                $where_room = 'AND b.room_id = %d';
                $params[] = $room_id;
            }

            $events = $wpdb->get_results($wpdb->prepare(
                "SELECT b.*, c.first_name, c.last_name, r.name as room_name
                 FROM {$wpdb->prefix}hrb_bookings b
                 JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
                 JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
                 WHERE b.booking_date BETWEEN %s AND %s
                 {$where_room}
                 AND b.status NOT IN ('cancelled')
                 ORDER BY b.booking_date, b.start_time",
                $params
            ));

            $calendar_events = array();

            foreach ($events as $event) {
                $calendar_events[] = array(
                    'id' => $event->id,
                    'title' => $event->first_name . ' ' . $event->last_name . ' - ' . $event->room_name,
                    'start' => $event->booking_date . 'T' . $event->start_time,
                    'end' => $event->booking_date . 'T' . $event->end_time,
                    'backgroundColor' => $this->get_status_color($event->status),
                    'borderColor' => $this->get_status_color($event->status),
                    'textColor' => '#fff',
                    'extendedProps' => array(
                        'booking_reference' => $event->booking_reference,
                        'customer_name' => $event->first_name . ' ' . $event->last_name,
                        'room_name' => $event->room_name,
                        'status' => $event->status,
                        'payment_status' => $event->payment_status,
                        'total_amount' => number_format($event->total_amount, 2)
                    )
                );
            }

            wp_send_json_success($calendar_events);

        } catch (Exception $e) {
            wp_send_json_error('Calendar error: ' . $e->getMessage());
        }
    }

    /**
     * Get color for booking status
     */
    private function get_status_color($status) {
        $colors = array(
            'pending' => '#ffc107',
            'confirmed' => '#28a745',
            'completed' => '#6c757d',
            'cancelled' => '#dc3545',
            'no_show' => '#fd7e14'
        );

        return isset($colors[$status]) ? $colors[$status] : '#007bff';
    }

    /**
     * AJAX handler for calendar statistics
     *
     * @since 1.0.0
     */
    public function ajax_get_calendar_stats(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');

        if (!current_user_can('hrb_view_calendar')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        $room_id = intval($_POST['room_id'] ?? 0);

        global $wpdb;

        $today = date('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');

        // Build room filter
        $room_filter = $room_id > 0 ? $wpdb->prepare(" AND room_id = %d", $room_id) : "";

        // Today's bookings
        $today_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}hrb_bookings
            WHERE booking_date = %s
            {$room_filter}
            AND status NOT IN ('cancelled', 'no_show')
        ", $today));

        // This week's bookings
        $week_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}hrb_bookings
            WHERE booking_date BETWEEN %s AND %s
            {$room_filter}
            AND status NOT IN ('cancelled', 'no_show')
        ", $week_start, $week_end));

        // This month's bookings
        $month_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}hrb_bookings
            WHERE booking_date BETWEEN %s AND %s
            {$room_filter}
            AND status NOT IN ('cancelled', 'no_show')
        ", $month_start, $month_end));

        // This month's revenue
        $month_revenue = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM {$wpdb->prefix}hrb_bookings
            WHERE booking_date BETWEEN %s AND %s
            {$room_filter}
            AND status IN ('confirmed', 'completed')
        ", $month_start, $month_end));


        wp_send_json_success([
            'today' => intval($today_bookings),
            'week' => intval($week_bookings),
            'month' => intval($month_bookings),
            'revenue' => floatval($month_revenue)
        ]);
    }

    /**
     * AJAX handler for booking details modal
     *
     * @since 1.0.0
     */
    public function ajax_get_booking_details_modal(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        $booking_id = intval($_POST['booking_id'] ?? 0);

        if (!$booking_id) {
            wp_send_json_error(__('Invalid booking ID', 'hourly-room-booking'));
            return;
        }

        global $wpdb;

        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*,
                   CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                   c.email as customer_email,
                   c.phone as customer_phone,
                   r.name as room_name
            FROM {$wpdb->prefix}hrb_bookings b
            LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
            LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            WHERE b.id = %d
        ", $booking_id));

        if (!$booking) {
            wp_send_json_error(__('Booking not found', 'hourly-room-booking'));
            return;
        }

        // Generate HTML for modal
        $html = '<div class="hrb-booking-details">';
        $html .= '<div class="hrb-detail-row">';
        $html .= '<strong>' . __('Booking Reference:', 'hourly-room-booking') . '</strong> ' . esc_html($booking->booking_reference);
        $html .= '</div>';

        $html .= '<div class="hrb-detail-row">';
        $html .= '<strong>' . __('Customer:', 'hourly-room-booking') . '</strong> ' . esc_html($booking->customer_name);
        $html .= '</div>';

        $html .= '<div class="hrb-detail-row">';
        $html .= '<strong>' . __('Email:', 'hourly-room-booking') . '</strong> ' . esc_html($booking->customer_email);
        $html .= '</div>';

        if ($booking->customer_phone) {
            $html .= '<div class="hrb-detail-row">';
            $html .= '<strong>' . __('Phone:', 'hourly-room-booking') . '</strong> ' . esc_html($booking->customer_phone);
            $html .= '</div>';
        }

        $html .= '<div class="hrb-detail-row">';
        $html .= '<strong>' . __('Room:', 'hourly-room-booking') . '</strong> ' . esc_html($booking->room_name);
        $html .= '</div>';

        $html .= '<div class="hrb-detail-row">';
        $html .= '<strong>' . __('Date:', 'hourly-room-booking') . '</strong> ' . date_i18n(get_option('hrb_date_format', 'd.m.Y'), strtotime($booking->booking_date));
        $html .= '</div>';

        $html .= '<div class="hrb-detail-row">';
        $html .= '<strong>' . __('Time:', 'hourly-room-booking') . '</strong> ' . date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->start_time)) . ' - ' . date_i18n(get_option('hrb_time_format', 'H:i'), strtotime($booking->end_time));
        $html .= '</div>';

        $html .= '<div class="hrb-detail-row">';
        $html .= '<strong>' . __('Status:', 'hourly-room-booking') . '</strong> ' . $this->get_status_badge($booking->status);
        $html .= '</div>';

        $html .= '<div class="hrb-detail-row">';
        $html .= '<strong>' . __('Payment Status:', 'hourly-room-booking') . '</strong> ' . $this->get_payment_status_badge($booking->payment_status);
        $html .= '</div>';

        $html .= '<div class="hrb-detail-row">';
        $html .= '<strong>' . __('Total Amount:', 'hourly-room-booking') . '</strong> ' . number_format($booking->total_amount, 2) . ' €';
        $html .= '</div>';

        if ($booking->extra_people > 0) {
            $html .= '<div class="hrb-detail-row">';
            $html .= '<strong>' . __('Extra People:', 'hourly-room-booking') . '</strong> ' . $booking->extra_people;
            $html .= '</div>';
        }

        $html .= '</div>';

        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX handler for getting customer details
     */
    public function ajax_get_customer_details(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        $customer_id = intval($_POST['customer_id'] ?? 0);
        $mode = sanitize_text_field($_POST['mode'] ?? 'view');

        if (!$customer_id) {
            wp_send_json_error(__('Invalid customer ID', 'hourly-room-booking'));
            return;
        }

        global $wpdb;

        // Get customer details
        $customer = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}hrb_customers
            WHERE id = %d
        ", $customer_id));

        if (!$customer) {
            wp_send_json_error(__('Customer not found', 'hourly-room-booking'));
            return;
        }

        // Get customer's booking history
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT b.*, r.name as room_name,
                   CASE b.status
                       WHEN 'confirmed' THEN 'Confirmed'
                       WHEN 'pending' THEN 'Pending'
                       WHEN 'cancelled' THEN 'Cancelled'
                       WHEN 'completed' THEN 'Completed'
                       ELSE b.status
                   END as status_label
            FROM {$wpdb->prefix}hrb_bookings b
            LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            WHERE b.customer_id = %d
            ORDER BY b.booking_date DESC
            LIMIT 10
        ", $customer_id));

        // Generate customer details HTML
        $html = '<div class="hrb-customer-details">';

        if ($mode === 'edit') {
            // Edit form
            $html .= '<form id="edit-customer-form" method="post">';
            $html .= '<div class="hrb-detail-section">';
            $html .= '<h3>' . __('Edit Customer Information', 'hourly-room-booking') . '</h3>';

            $html .= '<div class="hrb-form-row">';
            $html .= '<label for="first_name"><strong>' . __('First Name:', 'hourly-room-booking') . '</strong></label>';
            $html .= '<input type="text" id="first_name" name="first_name" value="' . esc_attr($customer->first_name) . '" required>';
            $html .= '</div>';

            $html .= '<div class="hrb-form-row">';
            $html .= '<label for="last_name"><strong>' . __('Last Name:', 'hourly-room-booking') . '</strong></label>';
            $html .= '<input type="text" id="last_name" name="last_name" value="' . esc_attr($customer->last_name) . '">';
            $html .= '</div>';

            $html .= '<div class="hrb-form-row">';
            $html .= '<label for="email"><strong>' . __('Email:', 'hourly-room-booking') . '</strong></label>';
            $html .= '<input type="email" id="email" name="email" value="' . esc_attr($customer->email) . '" required>';
            $html .= '</div>';

            $html .= '<div class="hrb-form-row">';
            $html .= '<label for="phone"><strong>' . __('Phone:', 'hourly-room-booking') . '</strong></label>';
            $html .= '<input type="tel" id="phone" name="phone" value="' . esc_attr($customer->phone) . '">';
            $html .= '</div>';

            $html .= '<div class="hrb-form-row">';
            $html .= '<label for="address"><strong>' . __('Address:', 'hourly-room-booking') . '</strong></label>';
            $html .= '<textarea id="address" name="address" rows="3">' . esc_textarea($customer->address ?? '') . '</textarea>';
            $html .= '</div>';

            $html .= '<div class="hrb-form-row">';
            $html .= '<strong>' . __('Registration Date:', 'hourly-room-booking') . '</strong> ' . date_i18n(get_option('date_format'), strtotime($customer->created_at));
            $html .= '</div>';

            $html .= '<div class="hrb-form-actions">';
            $html .= '<button type="button" class="button button-primary" onclick="saveCustomer(' . $customer->id . ')">' . __('Save Changes', 'hourly-room-booking') . '</button>';
            $html .= '<button type="button" class="button" onclick="closeCustomerModal()">' . __('Cancel', 'hourly-room-booking') . '</button>';
            $html .= '</div>';

            $html .= '</div>';
            $html .= '</form>';
        } else {
            // View mode
            $html .= '<div class="hrb-detail-section">';
            $html .= '<h3>' . __('Customer Information', 'hourly-room-booking') . '</h3>';

            $html .= '<div class="hrb-detail-row">';
            $html .= '<strong>' . __('Name:', 'hourly-room-booking') . '</strong> ' . esc_html($customer->first_name . ' ' . $customer->last_name);
            $html .= '</div>';

            $html .= '<div class="hrb-detail-row">';
            $html .= '<strong>' . __('Email:', 'hourly-room-booking') . '</strong> ' . esc_html($customer->email);
            $html .= '</div>';

            $html .= '<div class="hrb-detail-row">';
            $html .= '<strong>' . __('Phone:', 'hourly-room-booking') . '</strong> ' . esc_html($customer->phone);
            $html .= '</div>';

            if (!empty($customer->address)) {
                $html .= '<div class="hrb-detail-row">';
                $html .= '<strong>' . __('Address:', 'hourly-room-booking') . '</strong> ' . esc_html($customer->address);
                $html .= '</div>';
            }

            $html .= '<div class="hrb-detail-row">';
            $html .= '<strong>' . __('Registration Date:', 'hourly-room-booking') . '</strong> ' . date_i18n(get_option('date_format'), strtotime($customer->created_at));
            $html .= '</div>';

            $html .= '</div>';
        }

        // Booking history section
        if (!empty($bookings)) {
            $html .= '<div class="hrb-detail-section">';
            $html .= '<h3>' . __('Recent Bookings', 'hourly-room-booking') . '</h3>';

            $html .= '<table class="wp-list-table widefat striped">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th>' . __('Date', 'hourly-room-booking') . '</th>';
            $html .= '<th>' . __('Room', 'hourly-room-booking') . '</th>';
            $html .= '<th>' . __('Time', 'hourly-room-booking') . '</th>';
            $html .= '<th>' . __('Status', 'hourly-room-booking') . '</th>';
            $html .= '<th>' . __('Amount', 'hourly-room-booking') . '</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';

            foreach ($bookings as $booking) {
                $html .= '<tr>';
                $html .= '<td>' . date_i18n(get_option('date_format'), strtotime($booking->booking_date)) . '</td>';
                $html .= '<td>' . esc_html($booking->room_name) . '</td>';
                $html .= '<td>' . esc_html($booking->start_time . ' - ' . $booking->end_time) . '</td>';
                $html .= '<td><span class="status-' . esc_attr($booking->status) . '">' . esc_html($booking->status_label) . '</span></td>';
                $html .= '<td>€' . number_format($booking->total_amount, 2) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
        }

        $html .= '</div>';

        wp_send_json_success([
            'html' => $html,
            'customer' => $customer,
            'bookings' => $bookings
        ]);
    }

    /**
     * AJAX handler for saving customer details
     */
    public function ajax_save_customer(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        $customer_id = intval($_POST['customer_id'] ?? 0);
        if (!$customer_id) {
            wp_send_json_error(__('Invalid customer ID', 'hourly-room-booking'));
            return;
        }

        // Sanitize input data
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $address = sanitize_textarea_field($_POST['address'] ?? '');

        // Validate required fields
        if (empty($first_name) || empty($email)) {
            wp_send_json_error(__('First name and email are required', 'hourly-room-booking'));
            return;
        }

        if (!is_email($email)) {
            wp_send_json_error(__('Please enter a valid email address', 'hourly-room-booking'));
            return;
        }

        global $wpdb;

        // Check if email is already taken by another customer
        $existing_customer = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}hrb_customers
            WHERE email = %s AND id != %d
        ", $email, $customer_id));

        if ($existing_customer) {
            wp_send_json_error(__('This email address is already in use by another customer', 'hourly-room-booking'));
            return;
        }

        // Update customer data
        $result = $wpdb->update(
            $wpdb->prefix . 'hrb_customers',
            [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $customer_id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            wp_send_json_error(__('Failed to update customer', 'hourly-room-booking'));
            return;
        }

        wp_send_json_success([
            'message' => __('Customer updated successfully', 'hourly-room-booking')
        ]);
    }

    /**
     * AJAX handler for exporting customer data
     */
    public function ajax_export_customer_data(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        $customer_id = intval($_GET['customer_id'] ?? 0);
        if (!$customer_id) {
            wp_die(__('Invalid customer ID', 'hourly-room-booking'));
            return;
        }

        global $wpdb;

        // Get customer details
        $customer = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}hrb_customers
            WHERE id = %d
        ", $customer_id));

        if (!$customer) {
            wp_die(__('Customer not found', 'hourly-room-booking'));
            return;
        }

        // Get customer's booking history
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT b.*, r.name as room_name,
                   CASE b.status
                       WHEN 'confirmed' THEN 'Confirmed'
                       WHEN 'pending' THEN 'Pending'
                       WHEN 'cancelled' THEN 'Cancelled'
                       WHEN 'completed' THEN 'Completed'
                       ELSE b.status
                   END as status_label
            FROM {$wpdb->prefix}hrb_bookings b
            LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
            WHERE b.customer_id = %d
            ORDER BY b.booking_date DESC
        ", $customer_id));

        // Prepare CSV data
        $filename = 'customer_' . $customer_id . '_export_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Customer information section
        fputcsv($output, ['Customer Information']);
        fputcsv($output, ['Field', 'Value']);
        fputcsv($output, ['Name', $customer->first_name . ' ' . $customer->last_name]);
        fputcsv($output, ['Email', $customer->email]);
        fputcsv($output, ['Phone', $customer->phone]);
        fputcsv($output, ['Address', $customer->address]);
        fputcsv($output, ['Registration Date', $customer->created_at]);
        fputcsv($output, []);

        // Booking history section
        if (!empty($bookings)) {
            fputcsv($output, ['Booking History']);
            fputcsv($output, ['Date', 'Room', 'Start Time', 'End Time', 'Status', 'Amount', 'Booking Date Created']);

            foreach ($bookings as $booking) {
                fputcsv($output, [
                    $booking->booking_date,
                    $booking->room_name,
                    $booking->start_time,
                    $booking->end_time,
                    $booking->status_label,
                    '€' . number_format($booking->total_amount, 2),
                    $booking->created_at
                ]);
            }
        }

        fclose($output);
        exit();
    }

    /**
     * AJAX handler to get extra details for editing
     */
    public function ajax_get_extra_details() {
        check_ajax_referer('hrb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        $extra_id = intval($_POST['extra_id']);

        if (!$extra_id) {
            wp_send_json_error(__('Invalid extra ID', 'hourly-room-booking'));
            return;
        }

        $extras_manager = HRB_Extras::getInstance();
        $extra = $extras_manager->get_extra($extra_id);

        if (!$extra) {
            wp_send_json_error(__('Extra not found', 'hourly-room-booking'));
            return;
        }

        wp_send_json_success([
            'id' => $extra->id,
            'name' => $extra->name,
            'description' => $extra->description,
            'price' => $extra->price,
            'stock_quantity' => isset($extra->stock_quantity) ? $extra->stock_quantity : 0,
            'track_stock' => isset($extra->track_stock) ? $extra->track_stock : 1,
            'image_url' => $extra->image_url,
            'is_active' => $extra->is_active,
            'sort_order' => $extra->sort_order,
            'created_at' => $extra->created_at,
            'updated_at' => $extra->updated_at
        ]);
    }

    /**
     * AJAX handler for getting template data
     */
    public function ajax_get_template() {
        check_ajax_referer('hrb_get_template', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }
        
        global $wpdb;
        $template_id = intval($_POST['template_id']);
        
        if (!$template_id) {
            wp_send_json_error(__('Invalid template ID', 'hourly-room-booking'));
            return;
        }
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_email_templates WHERE id = %d",
            $template_id
        ));
        
        if ($template) {
            wp_send_json_success($template);
        } else {
            wp_send_json_error(__('Template not found', 'hourly-room-booking'));
        }
    }

    /**
     * Guide page
     */
    public function guide_page() {
        $this->check_permissions('hrb_view_bookings');
        include HRB_PLUGIN_DIR . 'admin/views/guide.php';
    }
    
    /**
     * AJAX: Cleanup expired bookings
     */
    private function ajax_cleanup_expired_bookings() {
        $booking_manager = HRB_Booking_Manager::getInstance();

        // Run the cleanup
        $booking_manager->cleanup_expired_bookings();

        wp_send_json_success(array(
            'message' => __('Booking cleanup completed successfully', 'hourly-room-booking')
        ));
    }
    
    private function ajax_fix_extra_people_pricing() {
        $booking_manager = HRB_Booking_Manager::getInstance();

        // Fix extra people pricing for existing bookings
        $fixed_count = $booking_manager->fix_extra_people_pricing();

        wp_send_json_success(array(
            'message' => sprintf(__('Fixed extra people pricing for %d bookings', 'hourly-room-booking'), $fixed_count)
        ));
    }

}
?>