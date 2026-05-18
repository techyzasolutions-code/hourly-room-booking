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
        add_action('wp_ajax_hrb_get_lock_events', array($this, 'ajax_get_lock_events'));
        add_action('wp_ajax_hrb_get_extras_lock_events', array($this, 'ajax_get_extras_lock_events'));
        add_action('wp_ajax_hrb_get_booking_details', array($this, 'ajax_get_booking_details_modal'));
        add_action('wp_ajax_hrb_get_customer_details', array($this, 'ajax_get_customer_details'));
        add_action('wp_ajax_hrb_save_customer', array($this, 'ajax_save_customer'));
        add_action('wp_ajax_hrb_export_customer_data', array($this, 'ajax_export_customer_data'));
        add_action('wp_ajax_hrb_check_customer_bookings', array($this, 'ajax_check_customer_bookings'));
        add_action('wp_ajax_hrb_get_extra_details', array($this, 'ajax_get_extra_details'));
        add_action('wp_ajax_hrb_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_hrb_test_reminders', array($this, 'ajax_test_reminders'));
        add_action('wp_ajax_hrb_delete_notification_log', array($this, 'ajax_delete_notification_log'));
        add_action('wp_ajax_hrb_resend_notification', array($this, 'ajax_resend_notification'));
        add_action('wp_ajax_hrb_send_additional_payment_link', array($this, 'ajax_send_additional_payment_link'));
        add_action('wp_ajax_hrb_mark_additional_payment_complete', array($this, 'ajax_mark_additional_payment_complete'));
        add_action('wp_ajax_hrb_regenerate_invoice', array($this, 'ajax_regenerate_invoice'));
        add_action('wp_ajax_hrb_check_new_bookings', array($this, 'ajax_check_new_bookings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_notification_assets'));
        add_action('admin_notices', array($this, 'add_admin_notices'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        add_action('admin_footer', array($this, 'render_confirmation_modal'));
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
        
        // Room Availability submenu
        add_submenu_page(
            'hrb-dashboard',
            __('Room Availability', 'hourly-room-booking'),
            __('Room Availability', 'hourly-room-booking'),
            'hrb_manage_rooms',
            'hrb-room-availability',
            array($this, 'room_availability_page')
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

        // Extras Availability submenu
        add_submenu_page(
            'hrb-dashboard',
            __('Extras Availability', 'hourly-room-booking'),
            __('Extras Availability', 'hourly-room-booking'),
            'hrb_manage_extras',
            'hrb-extras-availability',
            array($this, 'extras_availability_page')
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
        
        // Email Logs submenu (formerly Reminder Logs)
        add_submenu_page(
            'hrb-dashboard',
            __('Email Logs', 'hourly-room-booking'),
            __('Email Logs', 'hourly-room-booking'),
            'hrb_view_bookings',
            'hrb-reminder-logs',
            array($this, 'reminder_logs_page')
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
        
        wp_localize_script('hrb-admin', 'hrbAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hrb_admin_nonce'),
            'i18n' => array(
                'confirmAction' => __('Confirm Action', 'hourly-room-booking'),
                'confirmTimeSlotChange' => __('Confirm Time Slot Change', 'hourly-room-booking'),
                'originalTime' => __('Original time:', 'hourly-room-booking'),
                'newTime' => __('New time:', 'hourly-room-booking'),
                'confirm' => __('Confirm', 'hourly-room-booking'),
                'cancel' => __('Cancel', 'hourly-room-booking'),
                'sureChangeTimeSlot' => __('Are you sure you want to change the time slot?', 'hourly-room-booking'),
            ),
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
     * Room Availability page
     */
    public function room_availability_page() {
        $this->check_permissions('hrb_manage_rooms');
        $room_manager = HRB_Room_Manager::getInstance();
        
        // Handle AJAX actions
        if (isset($_POST['action']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'hrb_admin_action')) {
            $this->handle_room_availability_actions();
        }
        
        $rooms = $room_manager->get_all_rooms(false); // Include inactive rooms
        
        include HRB_PLUGIN_DIR . 'admin/views/room-availability.php';
    }
    
    /**
     * Extras Availability page
     */
    public function extras_availability_page() {
        $this->check_permissions('hrb_manage_extras');
        $extras_manager = HRB_Extras::getInstance();
        
        // Handle AJAX actions
        if (isset($_POST['action']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'hrb_admin_action')) {
            $this->handle_extras_availability_actions();
        }
        
        $extras = $extras_manager->get_extras('all');
        
        include HRB_PLUGIN_DIR . 'admin/views/extras-availability.php';
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
                    'color' => sanitize_hex_color($_POST['room_color'] ?? '#3498db'),
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
                    'color' => sanitize_hex_color($_POST['room_color'] ?? '#3498db'),
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
     * Handle room availability actions
     */
    private function handle_room_availability_actions() {
        // Check if user can manage rooms
        if (!current_user_can('hrb_manage_rooms')) {
            echo '<div class="notice notice-error"><p>' . __('You do not have permission to manage room availability.', 'hourly-room-booking') . '</p></div>';
            return;
        }
        
        $action = sanitize_text_field($_POST['action']);
        $sub_action = sanitize_text_field($_POST['sub_action'] ?? '');
        global $wpdb;
        
        switch ($sub_action) {
            case 'lock_room':
                $room_id = intval($_POST['room_id']);
                $start_datetime = sanitize_text_field($_POST['start_datetime']);
                $end_datetime = sanitize_text_field($_POST['end_datetime']);
                $reason = sanitize_textarea_field($_POST['reason']);
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'hrb_room_locks',
                    array(
                        'room_id' => $room_id,
                        'start_datetime' => $start_datetime,
                        'end_datetime' => $end_datetime,
                        'reason' => $reason,
                        'created_by' => get_current_user_id(),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s', '%d', '%s')
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Room locked successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to lock room.', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'unlock_room':
                $lock_id = intval($_POST['lock_id']);
                
                $result = $wpdb->delete(
                    $wpdb->prefix . 'hrb_room_locks',
                    array('id' => $lock_id),
                    array('%d')
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Room unlocked successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to unlock room.', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'master_lock':
                $start_datetime = sanitize_text_field($_POST['start_datetime']);
                $end_datetime = sanitize_text_field($_POST['end_datetime']);
                $reason = sanitize_textarea_field($_POST['reason']);
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'hrb_master_locks',
                    array(
                        'start_datetime' => $start_datetime,
                        'end_datetime' => $end_datetime,
                        'reason' => $reason,
                        'created_by' => get_current_user_id(),
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%d', '%s')
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Master lock applied successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to apply master lock.', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'edit_room_lock':
                $lock_id = intval($_POST['lock_id']);
                $room_id = intval($_POST['room_id']);
                $start_datetime = sanitize_text_field($_POST['start_datetime']);
                $end_datetime = sanitize_text_field($_POST['end_datetime']);
                $reason = sanitize_textarea_field($_POST['reason']);
                
                $result = $wpdb->update(
                    $wpdb->prefix . 'hrb_room_locks',
                    array(
                        'room_id' => $room_id,
                        'start_datetime' => $start_datetime,
                        'end_datetime' => $end_datetime,
                        'reason' => $reason
                    ),
                    array('id' => $lock_id),
                    array('%d', '%s', '%s', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    echo '<div class="notice notice-success"><p>' . __('Room lock updated successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to update room lock.', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'edit_master_lock':
                $lock_id = intval($_POST['lock_id']);
                $start_datetime = sanitize_text_field($_POST['start_datetime']);
                $end_datetime = sanitize_text_field($_POST['end_datetime']);
                $reason = sanitize_textarea_field($_POST['reason']);
                
                $result = $wpdb->update(
                    $wpdb->prefix . 'hrb_master_locks',
                    array(
                        'start_datetime' => $start_datetime,
                        'end_datetime' => $end_datetime,
                        'reason' => $reason
                    ),
                    array('id' => $lock_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    echo '<div class="notice notice-success"><p>' . __('Master lock updated successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to update master lock.', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'master_unlock':
                $lock_id = intval($_POST['lock_id']);
                
                $result = $wpdb->delete(
                    $wpdb->prefix . 'hrb_master_locks',
                    array('id' => $lock_id),
                    array('%d')
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Master lock removed successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to remove master lock.', 'hourly-room-booking') . '</p></div>';
                }
                break;
        }
    }
    
    /**
     * Create missing extra locks tables if they don't exist
     */
    public function ensure_extra_locks_tables() {
        global $wpdb;
        
        $extra_locks_table = $wpdb->prefix . 'hrb_extra_locks';
        $master_extra_locks_table = $wpdb->prefix . 'hrb_master_extra_locks';
        
        // Check if tables exist
        $extra_locks_exists = $wpdb->get_var("SHOW TABLES LIKE '$extra_locks_table'") == $extra_locks_table;
        $master_extra_locks_exists = $wpdb->get_var("SHOW TABLES LIKE '$master_extra_locks_table'") == $master_extra_locks_table;
        
        if (!$extra_locks_exists || !$master_extra_locks_exists) {
            // Create the missing tables
            HRB_Database::create_tables();
        }
    }
    
    /**
     * Handle extras availability actions
     */
    private function handle_extras_availability_actions() {
        // Check if user can manage extras
        if (!current_user_can('hrb_manage_extras')) {
            echo '<div class="notice notice-error"><p>' . __('You do not have permission to manage extras availability.', 'hourly-room-booking') . '</p></div>';
            return;
        }
        
        $sub_action = sanitize_text_field($_POST['sub_action']);
        global $wpdb;
        
        switch ($sub_action) {
            case 'lock_extra':
                $extra_id = intval($_POST['extra_id']);
                $start_datetime = sanitize_text_field($_POST['start_datetime']);
                $end_datetime = sanitize_text_field($_POST['end_datetime']);
                $reason = sanitize_textarea_field($_POST['reason']);
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'hrb_extra_locks',
                    array(
                        'extra_id' => $extra_id,
                        'start_datetime' => $start_datetime,
                        'end_datetime' => $end_datetime,
                        'reason' => $reason,
                        'created_by' => get_current_user_id(),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s', '%d', '%s')
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Extra locked successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to lock extra.', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'unlock_extra':
                $lock_id = intval($_POST['lock_id']);
                
                $result = $wpdb->delete(
                    $wpdb->prefix . 'hrb_extra_locks',
                    array('id' => $lock_id),
                    array('%d')
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Extra unlocked successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to unlock extra.', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'master_lock_extras':
                $start_datetime = sanitize_text_field($_POST['start_datetime']);
                $end_datetime = sanitize_text_field($_POST['end_datetime']);
                $reason = sanitize_textarea_field($_POST['reason']);
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'hrb_master_extra_locks',
                    array(
                        'start_datetime' => $start_datetime,
                        'end_datetime' => $end_datetime,
                        'reason' => $reason,
                        'created_by' => get_current_user_id(),
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%d', '%s')
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Master lock applied successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to apply master lock.', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'master_unlock_extras':
                $lock_id = intval($_POST['lock_id']);
                
                $result = $wpdb->delete(
                    $wpdb->prefix . 'hrb_master_extra_locks',
                    array('id' => $lock_id),
                    array('%d')
                );
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . __('Master lock removed successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to remove master lock.', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'edit_extra_lock':
                $lock_id = intval($_POST['lock_id']);
                $extra_id = intval($_POST['extra_id']);
                $start_datetime = sanitize_text_field($_POST['start_datetime']);
                $end_datetime = sanitize_text_field($_POST['end_datetime']);
                $reason = sanitize_textarea_field($_POST['reason']);
                
                $result = $wpdb->update(
                    $wpdb->prefix . 'hrb_extra_locks',
                    array(
                        'extra_id' => $extra_id,
                        'start_datetime' => $start_datetime,
                        'end_datetime' => $end_datetime,
                        'reason' => $reason
                    ),
                    array('id' => $lock_id),
                    array('%d', '%s', '%s', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    echo '<div class="notice notice-success"><p>' . __('Extra lock updated successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to update extra lock.', 'hourly-room-booking') . '</p></div>';
                }
                break;
                
            case 'edit_master_extra_lock':
                $lock_id = intval($_POST['lock_id']);
                $start_datetime = sanitize_text_field($_POST['start_datetime']);
                $end_datetime = sanitize_text_field($_POST['end_datetime']);
                $reason = sanitize_textarea_field($_POST['reason']);
                
                $result = $wpdb->update(
                    $wpdb->prefix . 'hrb_master_extra_locks',
                    array(
                        'start_datetime' => $start_datetime,
                        'end_datetime' => $end_datetime,
                        'reason' => $reason
                    ),
                    array('id' => $lock_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    echo '<div class="notice notice-success"><p>' . __('Master extra lock updated successfully!', 'hourly-room-booking') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to update master extra lock.', 'hourly-room-booking') . '</p></div>';
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
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
        
        // Add room booking staff role (FULL ACCESS)
        add_role('hrb_staff', __('Room Booking Staff', 'hourly-room-booking'), array(
            'read' => true,
            'hrb_view_bookings' => true,
            'hrb_manage_bookings' => true,   // Staff can manage bookings
            'hrb_view_calendar' => true,
            'hrb_view_customers' => true,
            'hrb_manage_customers' => true,  // Staff can manage customers
            'hrb_view_payments' => true,
            'hrb_manage_payments' => true,   // Staff can manage payments
            'hrb_view_reports' => true,
            'hrb_manage_settings' => true,   // Staff can access settings
            'hrb_manage_rooms' => true,      // Staff can manage rooms
            'hrb_manage_extras' => true,     // Staff can manage extras
            'hrb_view_extras' => true,       // Staff can view extras
            'hrb_export_data' => true        // Staff can export data
        ));
        
        // Force update existing users with hrb_staff role
        self::update_existing_staff_capabilities();
        
        // Force update existing administrator users
        self::update_existing_admin_capabilities();
        
        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('hrb_view_bookings');
            $admin_role->add_cap('hrb_manage_bookings');
            $admin_role->add_cap('hrb_manage_rooms');
            $admin_role->add_cap('hrb_manage_extras');
            $admin_role->add_cap('hrb_view_extras');
            $admin_role->add_cap('hrb_view_calendar');
            $admin_role->add_cap('hrb_view_customers');
            $admin_role->add_cap('hrb_manage_customers');
            $admin_role->add_cap('hrb_view_payments');
            $admin_role->add_cap('hrb_manage_payments');
            $admin_role->add_cap('hrb_view_reports');
            $admin_role->add_cap('hrb_manage_settings');
            $admin_role->add_cap('hrb_export_data');  // Add export capability
        }
    }
    
    /**
     * Update existing staff users with correct capabilities
     */
    public static function update_existing_staff_capabilities() {
        // Get all users with hrb_staff role
        $staff_users = get_users(array('role' => 'hrb_staff'));
        
        foreach ($staff_users as $user) {
            // Give staff full access to all plugin capabilities
            $user->add_cap('hrb_view_bookings');
            $user->add_cap('hrb_manage_bookings');
            $user->add_cap('hrb_view_calendar');
            $user->add_cap('hrb_view_customers');
            $user->add_cap('hrb_manage_customers');
            $user->add_cap('hrb_view_payments');
            $user->add_cap('hrb_manage_payments');
            $user->add_cap('hrb_view_reports');
            $user->add_cap('hrb_manage_settings');
            $user->add_cap('hrb_manage_rooms');
            $user->add_cap('hrb_manage_extras');
            $user->add_cap('hrb_view_extras');
            $user->add_cap('hrb_export_data');
        }
    }
    
    /**
     * Update existing administrator users with correct capabilities
     */
    public static function update_existing_admin_capabilities() {
        // Get all users with administrator role
        $admin_users = get_users(array('role' => 'administrator'));
        
        foreach ($admin_users as $user) {
            // Give administrators all plugin capabilities
            $user->add_cap('hrb_view_bookings');
            $user->add_cap('hrb_manage_bookings');
            $user->add_cap('hrb_manage_rooms');
            $user->add_cap('hrb_manage_extras');
            $user->add_cap('hrb_view_extras');
            $user->add_cap('hrb_view_calendar');
            $user->add_cap('hrb_view_customers');
            $user->add_cap('hrb_manage_customers');
            $user->add_cap('hrb_view_payments');
            $user->add_cap('hrb_manage_payments');
            $user->add_cap('hrb_view_reports');
            $user->add_cap('hrb_manage_settings');
            $user->add_cap('hrb_export_data');
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
            $admin_role->remove_cap('hrb_manage_extras');
            $admin_role->remove_cap('hrb_view_extras');
            $admin_role->remove_cap('hrb_view_calendar');
            $admin_role->remove_cap('hrb_view_customers');
            $admin_role->remove_cap('hrb_manage_customers');
            $admin_role->remove_cap('hrb_view_payments');
            $admin_role->remove_cap('hrb_manage_payments');
            $admin_role->remove_cap('hrb_view_reports');
            $admin_role->remove_cap('hrb_manage_settings');
            $admin_role->remove_cap('hrb_export_data');  // Remove export capability
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
                b.is_anonymous,
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

        if (!current_user_can('hrb_view_bookings')) {
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

        if (!current_user_can('hrb_view_bookings')) {
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
            'color' => $room->color ?? '#3498db', // Add color field
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
                "SELECT b.*, b.first_name as booking_first_name, b.last_name as booking_last_name, c.first_name, c.last_name, r.name as room_name, r.color as room_color
                 FROM {$wpdb->prefix}hrb_bookings b
                 LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
                 JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
                 WHERE b.booking_date BETWEEN %s AND %s
                 {$where_room}
                 AND b.status NOT IN ('cancelled')
                 ORDER BY b.booking_date, b.start_time",
                $params
            ));

            $calendar_events = array();

            foreach ($events as $event) {
                // Handle anonymous bookings
                if ($event->is_anonymous) {
                    $anon_name = trim(($event->booking_first_name ?? '') . ' ' . ($event->booking_last_name ?? ''));
                    $customer_display = !empty($anon_name) ? $anon_name : sprintf(__('Anonymous (%s)', 'hourly-room-booking'), $event->booking_reference);
                    $customer_name = !empty($anon_name) ? $anon_name : __('Anonymous', 'hourly-room-booking');
                } else {
                    $customer_display = $event->first_name . ' ' . $event->last_name;
                    $customer_name = $event->first_name . ' ' . $event->last_name;
                }
                
                // Get room color or default to blue
                $room_color = !empty($event->room_color) ? $event->room_color : '#3498db';
                
                // Create title with customer name, room name, and status
                $status_text = ucfirst($event->status);
                $title = $customer_display . ' - ' . $event->room_name . ' (' . $status_text . ')';
                
                $calendar_events[] = array(
                    'id' => $event->id,
                    'title' => $title,
                    'start' => $event->booking_date . 'T' . $event->start_time,
                    'end' => $event->booking_date . 'T' . $event->end_time,
                    'backgroundColor' => $room_color,
                    'borderColor' => $room_color,
                    'textColor' => '#fff',
                    'extendedProps' => array(
                        'booking_reference' => $event->booking_reference,
                        'is_anonymous' => (int) $event->is_anonymous,
                        'customer_name' => $customer_name,
                        'room_name' => $event->room_name,
                        'room_color' => $room_color,
                        'status' => $event->status,
                        'payment_status' => $event->payment_status,
                        'total_amount' => number_format($event->total_amount, 2)
                    )
                );
            }

            // Add room locks to calendar
            $room_locks_query = '';
            $lock_params = [$start_date, $end_date];
            
            if ($room_id > 0) {
                $room_locks_query = 'AND rl.room_id = %d';
                $lock_params[] = $room_id;
            }

            $room_locks = $wpdb->get_results($wpdb->prepare(
                "SELECT rl.*, r.name as room_name, r.color as room_color
                 FROM {$wpdb->prefix}hrb_room_locks rl
                 LEFT JOIN {$wpdb->prefix}hrb_rooms r ON rl.room_id = r.id
                 WHERE DATE(rl.start_datetime) >= %s AND DATE(rl.end_datetime) <= %s
                 {$room_locks_query}
                 ORDER BY rl.start_datetime",
                $lock_params
            ));

            foreach ($room_locks as $lock) {
                $event_start = date('Y-m-d', strtotime($lock->start_datetime));
                $event_end = date('Y-m-d', strtotime($lock->end_datetime . ' +1 day'));
                
                // Extract time components for display
                $start_time = date('H:i:s', strtotime($lock->start_datetime));
                $end_time = date('H:i:s', strtotime($lock->end_datetime));
                
                if ($start_time !== '00:00:00' || $end_time !== '23:59:59') {
                    // Time-specific lock
                    $event_start .= 'T' . substr($start_time, 0, 5);
                    $event_end = date('Y-m-d', strtotime($lock->end_datetime)) . 'T' . substr($end_time, 0, 5);
                }
                
                // Use room color for lock events
                $room_color = !empty($lock->room_color) ? $lock->room_color : '#95a5a6';
                
                // Format title without time information
                $title = '🔒 ' . $lock->room_name;
                if ($lock->reason) {
                    $title .= ' - ' . $lock->reason;
                }
                
                $calendar_events[] = array(
                    'id' => 'lock_' . $lock->id,
                    'title' => $title,
                    'start' => $event_start,
                    'end' => $event_end,
                    'backgroundColor' => $room_color,
                    'borderColor' => $room_color,
                    'textColor' => '#fff',
                    'extendedProps' => array(
                        'type' => 'room_lock',
                        'lock_id' => $lock->id,
                        'room_id' => $lock->room_id,
                        'room_name' => $lock->room_name,
                        'room_color' => $room_color,
                        'reason' => $lock->reason,
                        'start_datetime' => $lock->start_datetime,
                        'end_datetime' => $lock->end_datetime
                    )
                );
            }

            // Add master locks to calendar
            $master_locks = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hrb_master_locks
                 WHERE DATE(start_datetime) >= %s AND DATE(end_datetime) <= %s
                 ORDER BY start_datetime",
                [$start_date, $end_date]
            ));

            foreach ($master_locks as $lock) {
                $calendar_events[] = array(
                    'id' => 'master_lock_' . $lock->id,
                    'title' => '🔒🔒 MASTER LOCK - ' . ($lock->reason ?: 'All rooms locked'),
                    'start' => date('Y-m-d', strtotime($lock->start_datetime)),
                    'end' => date('Y-m-d', strtotime($lock->end_datetime . ' +1 day')),
                    'backgroundColor' => '#e74c3c',
                    'borderColor' => '#c0392b',
                    'textColor' => '#fff',
                    'display' => 'background',
                    'extendedProps' => array(
                        'type' => 'master_lock',
                        'lock_id' => $lock->id,
                        'reason' => $lock->reason,
                        'start_datetime' => $lock->start_datetime,
                        'end_datetime' => $lock->end_datetime
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
     * Enqueue the booking notification toast/poller on every admin page
     * for users with hrb_view_bookings (admins + Room Booking Staff).
     *
     * @since 1.3.0
     */
    public function enqueue_notification_assets(): void {
        if (!is_user_logged_in() || !current_user_can('hrb_view_bookings')) {
            return;
        }

        wp_enqueue_style(
            'hrb-bootstrap-icons-toast',
            HRB_ASSETS_URL . 'vendor/bootstrap-icons/bootstrap-icons.min.css',
            array(),
            '1.11.3'
        );

        wp_enqueue_style(
            'hrb-notifications',
            HRB_PLUGIN_URL . 'admin/assets/css/notifications.css',
            array('hrb-bootstrap-icons-toast'),
            HRB_VERSION
        );

        wp_enqueue_script(
            'hrb-notifications',
            HRB_PLUGIN_URL . 'admin/assets/js/notifications.js',
            array('jquery'),
            HRB_VERSION,
            true
        );

        wp_localize_script('hrb-notifications', 'hrbNotifications', array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('hrb_admin_nonce'),
            'pollInterval' => 60000, // 60 seconds — paused while tab is hidden
            'strings'      => array(
                'newBooking' => __('New Booking', 'hourly-room-booking'),
                'view'       => __('View', 'hourly-room-booking'),
                'dismiss'    => __('Dismiss', 'hourly-room-booking'),
                'anonymous'  => __('Anonymous', 'hourly-room-booking'),
            ),
        ));
    }

    /**
     * AJAX handler — return customer-made bookings created after $since_id.
     *
     * The first call (since_id = 0) returns no bookings and only the current
     * max id, so existing bookings don't spam toasts on initial load.
     *
     * Admin-created bookings (created_by_admin = 1) are excluded — staff
     * already know about those.
     *
     * @since 1.3.0
     */
    public function ajax_check_new_bookings(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');

        if (!current_user_can('hrb_view_bookings')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        global $wpdb;
        $since_id = intval($_POST['since_id'] ?? 0);

        // Bootstrap call — establish baseline without showing toasts for
        // every pre-existing booking.
        if ($since_id <= 0) {
            $max_id = (int) $wpdb->get_var("SELECT COALESCE(MAX(id), 0) FROM {$wpdb->prefix}hrb_bookings");
            wp_send_json_success(array(
                'bookings'  => array(),
                'latest_id' => $max_id,
            ));
            return;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.booking_reference, b.is_anonymous, b.booking_date,
                    b.start_time, b.end_time,
                    b.first_name AS booking_first_name, b.last_name AS booking_last_name,
                    r.name AS room_name,
                    c.first_name AS cust_first, c.last_name AS cust_last
             FROM {$wpdb->prefix}hrb_bookings b
             LEFT JOIN {$wpdb->prefix}hrb_rooms r ON b.room_id = r.id
             LEFT JOIN {$wpdb->prefix}hrb_customers c ON b.customer_id = c.id
             WHERE b.id > %d
               AND (b.created_by_admin IS NULL OR b.created_by_admin = 0)
             ORDER BY b.id ASC
             LIMIT 10",
            $since_id
        ));

        $date_fmt = get_option('hrb_date_format', 'd.m.Y');
        $bookings = array();
        foreach ($rows as $b) {
            if ((int) $b->is_anonymous === 1) {
                $name = trim(($b->booking_first_name ?? '') . ' ' . ($b->booking_last_name ?? ''));
                if ($name === '' || $name === '0') {
                    $name = __('Anonymous', 'hourly-room-booking');
                }
            } else {
                $name = trim(($b->cust_first ?? '') . ' ' . ($b->cust_last ?? ''));
                if ($name === '') {
                    $name = __('Customer', 'hourly-room-booking');
                }
            }

            $bookings[] = array(
                'id'            => (int) $b->id,
                'reference'     => (string) $b->booking_reference,
                'customer_name' => $name,
                'is_anonymous'  => (int) $b->is_anonymous === 1,
                'room_name'     => (string) $b->room_name,
                'date'          => date_i18n($date_fmt, strtotime($b->booking_date)),
                'time'          => date_i18n('H:i', strtotime($b->start_time)) . ' – ' . date_i18n('H:i', strtotime($b->end_time)),
                'edit_url'      => admin_url('admin.php?page=hrb-bookings&action=edit&booking_id=' . (int) $b->id),
            );
        }

        // Use the true current max id as the next baseline so admin-created
        // bookings (excluded above) don't re-trigger on the next poll.
        $latest_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(id), %d) FROM {$wpdb->prefix}hrb_bookings WHERE id >= %d",
            $since_id,
            $since_id
        ));

        wp_send_json_success(array(
            'bookings'  => $bookings,
            'latest_id' => $latest_id,
        ));
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
     * AJAX handler for getting extra lock events for calendar
     */
    public function ajax_get_extras_lock_events() {
        try {
            check_ajax_referer('hrb_admin_nonce', 'nonce');
            
            if (!current_user_can('hrb_manage_extras')) {
                wp_send_json_error('Insufficient permissions');
            }

            // Ensure tables exist
            $this->ensure_extra_locks_tables();

            $start_date = sanitize_text_field($_POST['start'] ?? '');
            $end_date = sanitize_text_field($_POST['end'] ?? '');
            $extra_id = intval($_POST['extra_id'] ?? 0);

            global $wpdb;
            $lock_events = array();

            // Get extra locks
            $extra_locks_query = "SELECT el.*, e.name as extra_name
                                 FROM {$wpdb->prefix}hrb_extra_locks el
                                 LEFT JOIN {$wpdb->prefix}hrb_extras e ON el.extra_id = e.id
                                 WHERE (DATE(el.start_datetime) <= %s AND DATE(el.end_datetime) >= %s)";
            
            $extra_locks_params = array($end_date, $start_date);
            
            if ($extra_id > 0) {
                $extra_locks_query .= " AND el.extra_id = %d";
                $extra_locks_params[] = $extra_id;
            }
            
            $extra_locks_query .= " ORDER BY el.start_datetime";
            
            $extra_locks = $wpdb->get_results($wpdb->prepare($extra_locks_query, $extra_locks_params));

            foreach ($extra_locks as $lock) {
                $event_start = date('Y-m-d', strtotime($lock->start_datetime));
                $event_end = date('Y-m-d', strtotime($lock->end_datetime . ' +1 day'));
                
                // Extract time components for display
                $start_time = date('H:i:s', strtotime($lock->start_datetime));
                $end_time = date('H:i:s', strtotime($lock->end_datetime));
                
                if ($start_time !== '00:00:00' || $end_time !== '23:59:59') {
                    // Time-specific lock
                    $event_start .= 'T' . substr($start_time, 0, 5);
                    $event_end = date('Y-m-d', strtotime($lock->end_datetime)) . 'T' . substr($end_time, 0, 5);
                }
                
                $lock_events[] = array(
                    'id' => 'extra_lock_' . $lock->id,
                    'title' => '🔒 ' . $lock->extra_name . ($lock->reason ? ' - ' . $lock->reason : ''),
                    'start' => $event_start,
                    'end' => $event_end,
                    'backgroundColor' => '#95a5a6',
                    'borderColor' => '#7f8c8d',
                    'textColor' => '#fff',
                    'extendedProps' => array(
                        'type' => 'extra_lock',
                        'lock_id' => $lock->id,
                        'extra_id' => $lock->extra_id,
                        'extra_name' => $lock->extra_name,
                        'reason' => $lock->reason,
                        'start_datetime' => $lock->start_datetime,
                        'end_datetime' => $lock->end_datetime
                    )
                );
            }

            // Get master extra locks
            $master_extra_locks = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hrb_master_extra_locks
                 WHERE DATE(start_datetime) <= %s AND DATE(end_datetime) >= %s
                 ORDER BY start_datetime",
                $end_date, $start_date
            ));

            foreach ($master_extra_locks as $lock) {
                $lock_events[] = array(
                    'id' => 'master_extra_lock_' . $lock->id,
                    'title' => '🔒🔒 MASTER LOCK' . ($lock->reason ? ' - ' . $lock->reason : ''),
                    'start' => date('Y-m-d', strtotime($lock->start_datetime)),
                    'end' => date('Y-m-d', strtotime($lock->end_datetime . ' +1 day')),
                    'backgroundColor' => '#e74c3c',
                    'borderColor' => '#c0392b',
                    'textColor' => '#fff',
                    'extendedProps' => array(
                        'type' => 'master_extra_lock',
                        'lock_id' => $lock->id,
                        'reason' => $lock->reason,
                        'start_datetime' => $lock->start_datetime,
                        'end_datetime' => $lock->end_datetime
                    )
                );
            }

            wp_send_json_success($lock_events);

        } catch (Exception $e) {
            wp_send_json_error('Extra lock calendar error: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for lock events (dedicated lock calendar)
     *
     * @since 1.0.0
     */
    public function ajax_get_lock_events() {
        try {
            check_ajax_referer('hrb_admin_nonce', 'nonce');
            
            if (!current_user_can('hrb_manage_rooms')) {
                wp_send_json_error('Insufficient permissions');
            }

            $start_date = sanitize_text_field($_POST['start'] ?? '');
            $end_date = sanitize_text_field($_POST['end'] ?? '');
            $room_id = intval($_POST['room_id'] ?? 0);

            global $wpdb;
            $lock_events = array();

            // Get room locks
            $room_locks_query = "SELECT rl.*, r.name as room_name, r.color as room_color
                                FROM {$wpdb->prefix}hrb_room_locks rl
                                LEFT JOIN {$wpdb->prefix}hrb_rooms r ON rl.room_id = r.id
                                WHERE DATE(rl.start_datetime) <= %s AND DATE(rl.end_datetime) >= %s";
            
            $room_locks_params = array($end_date, $start_date);
            
            if ($room_id > 0) {
                $room_locks_query .= " AND rl.room_id = %d";
                $room_locks_params[] = $room_id;
            }
            
            $room_locks_query .= " ORDER BY rl.start_datetime";
            
            $room_locks = $wpdb->get_results($wpdb->prepare($room_locks_query, $room_locks_params));

            foreach ($room_locks as $lock) {
                $event_start = date('Y-m-d', strtotime($lock->start_datetime));
                $event_end = date('Y-m-d', strtotime($lock->end_datetime . ' +1 day'));
                
                // Extract time components for display
                $start_time = date('H:i:s', strtotime($lock->start_datetime));
                $end_time = date('H:i:s', strtotime($lock->end_datetime));
                
                if ($start_time !== '00:00:00' || $end_time !== '23:59:59') {
                    // Time-specific lock
                    $event_start .= 'T' . substr($start_time, 0, 5);
                    $event_end = date('Y-m-d', strtotime($lock->end_datetime)) . 'T' . substr($end_time, 0, 5);
                }
                
                // Use room color for lock events
                $room_color = !empty($lock->room_color) ? $lock->room_color : '#95a5a6';
                
                // Format title without time information (time will be shown separately)
                $title = '🔒 ' . $lock->room_name;
                if ($lock->reason) {
                    $title .= ' - ' . $lock->reason;
                }
                
                // Format time range for display
                $time_range = '';
                if ($start_time !== '00:00:00' || $end_time !== '23:59:59') {
                    $start_time_formatted = substr($start_time, 0, 5);
                    $end_time_formatted = substr($end_time, 0, 5);
                    $time_range = $start_time_formatted . ' - ' . $end_time_formatted;
                }
                
                $lock_events[] = array(
                    'id' => 'lock_' . $lock->id,
                    'title' => $title,
                    'start' => $event_start,
                    'end' => $event_end,
                    'backgroundColor' => $room_color,
                    'borderColor' => $room_color,
                    'textColor' => '#fff',
                    'extendedProps' => array(
                        'type' => 'room_lock',
                        'lock_id' => $lock->id,
                        'room_id' => $lock->room_id,
                        'room_name' => $lock->room_name,
                        'room_color' => $room_color,
                        'reason' => $lock->reason,
                        'start_datetime' => $lock->start_datetime,
                        'end_datetime' => $lock->end_datetime,
                        'time_range' => $time_range
                    )
                );
            }

            // Get master locks
            $master_locks = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hrb_master_locks
                 WHERE DATE(start_datetime) <= %s AND DATE(end_datetime) >= %s
                 ORDER BY start_datetime",
                $end_date, $start_date
            ));

            foreach ($master_locks as $lock) {
            $lock_events[] = array(
                'id' => 'master_lock_' . $lock->id,
                'title' => '🔒🔒 MASTER LOCK' . ($lock->reason ? ' - ' . $lock->reason : ''),
                'start' => date('Y-m-d', strtotime($lock->start_datetime)),
                'end' => date('Y-m-d', strtotime($lock->end_datetime . ' +1 day')),
                'backgroundColor' => '#e74c3c',
                'borderColor' => '#c0392b',
                'textColor' => '#fff',
                'display' => 'background',
                'extendedProps' => array(
                    'type' => 'master_lock',
                    'lock_id' => $lock->id,
                    'reason' => $lock->reason,
                    'start_datetime' => $lock->start_datetime,
                    'end_datetime' => $lock->end_datetime
                )
            );
            }

            wp_send_json_success($lock_events);

        } catch (Exception $e) {
            wp_send_json_error('Lock calendar error: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for booking details modal
     *
     * @since 1.0.0
     */
    public function ajax_get_booking_details_modal(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');

        if (!current_user_can('hrb_view_bookings')) {
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
                   CASE 
                       WHEN b.is_anonymous = 1 THEN CONCAT(
                           CASE WHEN b.first_name IS NOT NULL AND b.first_name != '' AND b.first_name != '0' THEN b.first_name ELSE '' END, 
                           CASE WHEN b.last_name IS NOT NULL AND b.last_name != '' AND b.last_name != '0' THEN CONCAT(' ', b.last_name) ELSE '' END
                       )
                       ELSE CONCAT(c.first_name, ' ', c.last_name)
                   END as customer_name,
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

        // Build a clean, icon-labeled details card
        $date_fmt = get_option('hrb_date_format', 'd.m.Y');
        $time_fmt = get_option('hrb_time_format', 'H:i');

        $is_anonymous = !empty($booking->is_anonymous);
        $customer_html = hrb_display_customer_info($booking, 'name_email');

        ob_start();
        ?>
        <div class="hrb-bd">
            <div class="hrb-bd-grid">
                <div class="hrb-bd-item">
                    <span class="hrb-bd-label"><i class="bi bi-hash"></i><?php _e('Reference', 'hourly-room-booking'); ?></span>
                    <span class="hrb-bd-value"><?php echo esc_html($booking->booking_reference); ?></span>
                </div>
                <div class="hrb-bd-item">
                    <span class="hrb-bd-label"><i class="bi bi-person-fill"></i><?php _e('Customer', 'hourly-room-booking'); ?></span>
                    <span class="hrb-bd-value"><?php echo $customer_html; ?></span>
                </div>
                <div class="hrb-bd-item">
                    <span class="hrb-bd-label"><i class="bi bi-door-closed-fill"></i><?php _e('Room', 'hourly-room-booking'); ?></span>
                    <span class="hrb-bd-value"><?php echo esc_html($booking->room_name); ?></span>
                </div>
                <div class="hrb-bd-item">
                    <span class="hrb-bd-label"><i class="bi bi-calendar-event-fill"></i><?php _e('Date', 'hourly-room-booking'); ?></span>
                    <span class="hrb-bd-value"><?php echo esc_html(date_i18n($date_fmt, strtotime($booking->booking_date))); ?></span>
                </div>
                <div class="hrb-bd-item">
                    <span class="hrb-bd-label"><i class="bi bi-clock-fill"></i><?php _e('Time', 'hourly-room-booking'); ?></span>
                    <span class="hrb-bd-value"><?php echo esc_html(date_i18n($time_fmt, strtotime($booking->start_time)) . ' – ' . date_i18n($time_fmt, strtotime($booking->end_time))); ?></span>
                </div>
                <div class="hrb-bd-item">
                    <span class="hrb-bd-label"><i class="bi bi-flag-fill"></i><?php _e('Status', 'hourly-room-booking'); ?></span>
                    <span class="hrb-bd-value"><?php echo $this->get_status_badge($booking->status); ?></span>
                </div>
                <div class="hrb-bd-item">
                    <span class="hrb-bd-label"><i class="bi bi-credit-card-fill"></i><?php _e('Payment Status', 'hourly-room-booking'); ?></span>
                    <span class="hrb-bd-value"><?php echo $this->get_payment_status_badge($booking->payment_status); ?></span>
                </div>
                <div class="hrb-bd-item hrb-bd-item-total">
                    <span class="hrb-bd-label"><i class="bi bi-currency-euro"></i><?php _e('Total Amount', 'hourly-room-booking'); ?></span>
                    <span class="hrb-bd-value hrb-bd-amount"><?php echo esc_html(number_format((float) $booking->total_amount, 2)); ?> €</span>
                </div>
                <?php if (!empty($booking->extra_people) && $booking->extra_people > 0): ?>
                <div class="hrb-bd-item">
                    <span class="hrb-bd-label"><i class="bi bi-people-fill"></i><?php _e('Extra People', 'hourly-room-booking'); ?></span>
                    <span class="hrb-bd-value"><?php echo (int) $booking->extra_people; ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($booking->special_requests) && trim($booking->special_requests) !== ''): ?>
            <div class="hrb-bd-note hrb-bd-note-customer">
                <div class="hrb-bd-note-header"><i class="bi bi-chat-square-quote-fill"></i> <?php _e('Special Requests', 'hourly-room-booking'); ?></div>
                <div class="hrb-bd-note-body"><?php echo nl2br(esc_html($booking->special_requests)); ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($booking->admin_notes) && trim($booking->admin_notes) !== ''): ?>
            <div class="hrb-bd-note hrb-bd-note-admin">
                <div class="hrb-bd-note-header"><i class="bi bi-shield-lock-fill"></i> <?php _e('Admin Notes', 'hourly-room-booking'); ?></div>
                <div class="hrb-bd-note-body"><?php echo nl2br(esc_html($booking->admin_notes)); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX handler for getting customer details
     */
    public function ajax_get_customer_details(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');
        if (!current_user_can('hrb_view_customers')) {
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

        // Get customer details (exclude anonymous user)
        $customer = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}hrb_customers
            WHERE id = %d AND email != 'anonymous@example.com'
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
            $html .= '<th>' . __('Reference', 'hourly-room-booking') . '</th>';
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
                $html .= '<td><strong>' . esc_html($booking->booking_reference) . '</strong></td>';
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
        if (!current_user_can('hrb_manage_customers')) {
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

        // Check if email is already taken by another customer (exclude anonymous user)
        $existing_customer = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}hrb_customers
            WHERE email = %s AND id != %d AND email != 'anonymous@example.com'
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
        if (!current_user_can('hrb_export_data')) {
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
            fputcsv($output, ['Reference', 'Date', 'Room', 'Start Time', 'End Time', 'Status', 'Amount', 'Booking Date Created']);

            foreach ($bookings as $booking) {
                fputcsv($output, [
                    $booking->booking_reference,
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

        if (!current_user_can('hrb_view_bookings')) {
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
        
        if (!current_user_can('hrb_view_bookings')) {
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
     * Email notification logs page (formerly Reminder Logs)
     */
    public function reminder_logs_page() {
        $this->check_permissions('hrb_view_bookings');
        
        global $wpdb;
        
        // Handle delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete_logs' && check_admin_referer('hrb_delete_logs', 'hrb_nonce')) {
            if (current_user_can('hrb_manage_bookings')) {
                $delete_days = intval($_POST['delete_days'] ?? 0);
                if ($delete_days > 0) {
                    $deleted = $wpdb->query($wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}hrb_notification_logs 
                         WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                        $delete_days
                    ));
                    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('%d log entries deleted successfully.', 'hourly-room-booking'), $deleted) . '</p></div>';
                }
            }
        }
        
        // Get filters
        $filters = array(
            'type' => sanitize_text_field($_GET['type'] ?? ''),
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'event' => sanitize_text_field($_GET['event'] ?? ''),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_GET['date_to'] ?? ''),
            'search' => sanitize_text_field($_GET['s'] ?? '')
        );
        
        // Pagination
        $page = intval($_GET['paged'] ?? 1);
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE clause
        $where_conditions = array('1=1');
        $params = array();
        
        if (!empty($filters['type'])) {
            $where_conditions[] = 'type = %s';
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = 'status = %s';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['event'])) {
            $where_conditions[] = 'event = %s';
            $params[] = $filters['event'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= %s';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'created_at <= %s';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = '(recipient LIKE %s OR subject LIKE %s OR booking_id = %d)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = intval($filters['search']);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_params = $params;
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_notification_logs WHERE $where_clause";
        if (!empty($count_params)) {
            $total_logs = $wpdb->get_var($wpdb->prepare($count_sql, $count_params));
        } else {
            $total_logs = $wpdb->get_var($count_sql);
        }
        
        $total_pages = ceil($total_logs / $per_page);
        
        // Get logs
        $query_params = $params;
        $query_params[] = $per_page;
        $query_params[] = $offset;
        
        $sql = "SELECT * FROM {$wpdb->prefix}hrb_notification_logs 
                WHERE $where_clause 
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d";
        
        if (!empty($query_params)) {
            $logs = $wpdb->get_results($wpdb->prepare($sql, $query_params));
        } else {
            $logs = $wpdb->get_results($sql);
        }
        
        // Get overall stats
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_logs,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_logs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_logs,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_logs,
                SUM(CASE WHEN type = 'email' THEN 1 ELSE 0 END) as email_logs,
                SUM(CASE WHEN type = 'sms' THEN 1 ELSE 0 END) as sms_logs,
                SUM(CASE WHEN type = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp_logs
            FROM {$wpdb->prefix}hrb_notification_logs 
        ");
        
        // Get unique events for filter dropdown
        $events = $wpdb->get_col("SELECT DISTINCT event FROM {$wpdb->prefix}hrb_notification_logs ORDER BY event");
        
        // Get cron job status
        $next_reminders = wp_next_scheduled('hrb_send_booking_reminders');
        
        // Include the view file
        include HRB_PLUGIN_DIR . 'admin/views/notification-logs.php';
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
    
    /**
     * AJAX: Test reminder system
     */
    public function ajax_test_reminders() {
        check_ajax_referer('hrb_admin_nonce', 'nonce');
        
        if (!current_user_can('hrb_view_bookings')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }
        
        $booking_manager = HRB_Booking_Manager::getInstance();
        
        try {
            $result = $booking_manager->send_booking_reminders();
            
            $message = sprintf(
                __('Reminder test completed: %d found, %d sent, %d skipped', 'hourly-room-booking'),
                $result['total_found'],
                $result['reminders_sent'],
                $result['reminders_skipped']
            );
            
            if (isset($result['reminders_failed']) && $result['reminders_failed'] > 0) {
                $message .= sprintf(__(' (%d failed)', 'hourly-room-booking'), $result['reminders_failed']);
            }
            
            wp_send_json_success(array('message' => $message, 'result' => $result));
            
        } catch (Exception $e) {
            wp_send_json_error('Test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Delete single notification log
     */
    public function ajax_delete_notification_log() {
        check_ajax_referer('hrb_admin_nonce', 'nonce');
        
        if (!current_user_can('hrb_manage_bookings')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }
        
        $log_id = intval($_POST['log_id'] ?? 0);
        
        if (!$log_id) {
            wp_send_json_error(__('Invalid log ID', 'hourly-room-booking'));
            return;
        }
        
        global $wpdb;
        
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'hrb_notification_logs',
            array('id' => $log_id),
            array('%d')
        );
        
        if ($deleted) {
            wp_send_json_success(array('message' => __('Log entry deleted successfully.', 'hourly-room-booking')));
        } else {
            wp_send_json_error(__('Failed to delete log entry.', 'hourly-room-booking'));
        }
    }

    /**
     * AJAX: Resend a notification from log entry
     */
    public function ajax_resend_notification() {
        check_ajax_referer('hrb_admin_nonce', 'nonce');
        
        if (!current_user_can('hrb_manage_bookings')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }
        
        $log_id = intval($_POST['log_id'] ?? 0);
        
        if (!$log_id) {
            wp_send_json_error(__('Invalid log ID', 'hourly-room-booking'));
            return;
        }
        
        global $wpdb;
        
        // Get the log entry
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_notification_logs WHERE id = %d",
            $log_id
        ));
        
        if (!$log) {
            wp_send_json_error(__('Log entry not found', 'hourly-room-booking'));
            return;
        }
        
        // Only allow resending emails for now
        if ($log->type !== 'email') {
            wp_send_json_error(__('Only email notifications can be resent at this time', 'hourly-room-booking'));
            return;
        }
        
        // Check if log has a booking_id (required for resending)
        if (!$log->booking_id || $log->booking_id <= 0) {
            wp_send_json_error(__('Cannot resend notification: No booking associated with this log entry', 'hourly-room-booking'));
            return;
        }
        
        // Get the booking
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($log->booking_id);
        
        if (!$booking) {
            wp_send_json_error(__('Booking not found', 'hourly-room-booking'));
            return;
        }
        
        // Check if booking is anonymous (skip notifications for anonymous bookings)
        if ($booking->is_anonymous) {
            wp_send_json_error(__('Cannot resend notification for anonymous bookings', 'hourly-room-booking'));
            return;
        }
        
        // Get the event from the log (remove _admin suffix if present)
        $event = $log->event;
        if (strpos($event, '_admin') !== false) {
            // For admin notifications, we can't resend them directly
            // Instead, we'll resend the customer notification
            $event = str_replace('_admin', '', $event);
        }
        
        // Resend the notification
        $notification_manager = HRB_Notification_Manager::getInstance();
        $result = $notification_manager->send_notification($log->booking_id, $event);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        // Check if email was sent successfully
        if (isset($result['email'])) {
            if (is_wp_error($result['email'])) {
                wp_send_json_error($result['email']->get_error_message());
                return;
            } elseif ($result['email'] === true || $result['email'] === 1) {
                wp_send_json_success(array('message' => __('Notification resent successfully.', 'hourly-room-booking')));
                return;
            }
        }
        
        wp_send_json_error(__('Failed to resend notification', 'hourly-room-booking'));
    }

    /**
     * AJAX handler for checking customer bookings before deletion
     */
    public function ajax_check_customer_bookings(): void {
        check_ajax_referer('hrb_admin_nonce', 'nonce');
        if (!current_user_can('hrb_manage_customers')) {
            wp_send_json_error(__('Insufficient permissions', 'hourly-room-booking'));
            return;
        }

        $customer_id = intval($_POST['customer_id'] ?? 0);
        if (!$customer_id) {
            wp_send_json_error(__('Invalid customer ID', 'hourly-room-booking'));
            return;
        }

        global $wpdb;

        // Check for active bookings (not cancelled or completed)
        $booking_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}hrb_bookings 
            WHERE customer_id = %d AND status NOT IN ('cancelled', 'completed')
        ", $customer_id));

        wp_send_json_success([
            'booking_count' => intval($booking_count)
        ]);
    }

    /**
     * Send PayPal payment email when payment method is changed to PayPal
     */
    public function send_paypal_payment_email($booking_id) {
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            return false;
        }
        
        // Get customer email
        $customer_manager = HRB_Customer_Manager::getInstance();
        $customer = $customer_manager->get_customer($booking->customer_id);
        
        if (!$customer || empty($customer->email) || $customer->email === 'anonymous@example.com') {
            // No valid email address for PayPal payment
            return false;
        }
        
        // Generate PayPal payment link
        $payment_link = $this->generate_paypal_payment_link($booking_id);
        
        if (!$payment_link) {
            return false;
        }
        
        // Use notification manager to send email with template
        $notification_manager = HRB_Notification_Manager::getInstance();
        
        // Send notification with custom data including payment link
        $custom_data = array(
            'payment_link' => $payment_link
        );
        
        return $notification_manager->send_notification($booking_id, 'paypal_payment_required', $custom_data);
    }
    
    /**
     * Generate PayPal payment link for existing booking
     */
    private function generate_paypal_payment_link($booking_id, $amount = null) {
        // Get booking reference instead of using booking ID
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking || empty($booking->booking_reference)) {
            return false;
        }
        
        $site_url = home_url();
        $link = $site_url . '/paypal-payment/?ref=' . urlencode($booking->booking_reference);
        
        global $wpdb;
        
        // For additional payments, find the payment record and use its token
        if ($amount !== null && $amount > 0) {
            // Find the pending payment record for additional services
            $payment_record = $wpdb->get_row($wpdb->prepare(
                "SELECT payment_token FROM {$wpdb->prefix}hrb_payments 
                WHERE booking_id = %d AND amount = %f AND status = 'pending' 
                AND transaction_id LIKE 'ADD_%%'
                ORDER BY id DESC LIMIT 1",
                $booking_id,
                $amount
            ));
            
            if ($payment_record && !empty($payment_record->payment_token)) {
                // Use payment token instead of amount or ID for security
                $link .= '&token=' . urlencode($payment_record->payment_token);
            }
        } else {
            // For initial payments, find the pending payment record (not additional)
            $payment_record = $wpdb->get_row($wpdb->prepare(
                "SELECT payment_token FROM {$wpdb->prefix}hrb_payments 
                WHERE booking_id = %d AND payment_method = 'paypal' AND status = 'pending'
                AND (transaction_id IS NULL OR transaction_id NOT LIKE 'ADD_%%')
                ORDER BY id DESC LIMIT 1",
                $booking_id
            ));
            
            if ($payment_record && !empty($payment_record->payment_token)) {
                // Use payment token for security
                $link .= '&token=' . urlencode($payment_record->payment_token);
            }
        }
        
        return $link;
    }
    
    /**
     * Send additional payment email when admin adds services to already-paid booking
     */
    public function send_additional_payment_email($booking_id, $additional_amount, $newly_added_extra_ids = array()) {
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            return false;
        }
        
        // Get customer email
        $customer_manager = HRB_Customer_Manager::getInstance();
        $customer = $customer_manager->get_customer($booking->customer_id);
        
        if (!$customer || empty($customer->email) || $customer->email === 'anonymous@example.com') {
            // No valid email address for payment
            return false;
        }
        
        // Get all pending payments for additional services (ADD_ prefix) to calculate total outstanding amount
        global $wpdb;
        $total_pending_amount = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}hrb_payments 
            WHERE booking_id = %d AND status = 'pending' AND transaction_id LIKE 'ADD_%%'",
            $booking_id
        ));
        
        // Use total pending amount for payment link (not just the current additional amount)
        $payment_amount = $total_pending_amount > 0 ? $total_pending_amount : $additional_amount;
        
        // Generate PayPal payment link with total pending amount
        $payment_link = $this->generate_paypal_payment_link($booking_id, $payment_amount);
        
        if (!$payment_link) {
            return false;
        }
        
        // Get details of what was added
        $modifications = $booking_manager->get_booking_modifications($booking_id);
        $extras_manager = HRB_Extras::getInstance();
        $booking_extras = $extras_manager->get_booking_extras($booking_id);
        
        $added_services = array();
        
        // Check for hours modification
        foreach ($modifications as $mod) {
            if ($mod->modification_type === 'hours') {
                $hours_increase = $mod->new_value - $mod->original_value;
                $added_services[] = sprintf(__('+%s hours', 'hourly-room-booking'), number_format($hours_increase, 1));
            } elseif ($mod->modification_type === 'extra_people') {
                $people_increase = $mod->new_value - $mod->original_value;
                $added_services[] = sprintf(__('+%d extra people', 'hourly-room-booking'), $people_increase);
            }
        }
        
        // Check for newly added extras (passed as parameter or check admin-added extras)
        if (!empty($newly_added_extra_ids) && is_array($newly_added_extra_ids) && count($newly_added_extra_ids) > 0) {
            // Convert to integers for comparison
            $newly_added_extra_ids_int = array_map('intval', $newly_added_extra_ids);
            // Use the passed newly added extra IDs
            foreach ($booking_extras as $extra) {
                // Compare with extra's id (which is the extra_id from extras table)
                if (in_array(intval($extra->id), $newly_added_extra_ids_int)) {
                    $added_services[] = $extra->name . ' (' . hrb_format_amount($extra->total_price) . ')';
                }
            }
        }
        
        // Also check for admin-added extras (in case newly_added_extra_ids wasn't passed or is empty)
        // This ensures we capture all admin-added extras
        foreach ($booking_extras as $extra) {
            if (!empty($extra->added_by_admin) && ($extra->added_by_admin == 1 || $extra->added_by_admin === '1' || $extra->added_by_admin === true)) {
                // Only add if not already in the list (avoid duplicates)
                $extra_text = $extra->name . ' (' . hrb_format_amount($extra->total_price) . ')';
                if (!in_array($extra_text, $added_services)) {
                    $added_services[] = $extra_text;
                }
            }
        }
        
        // Format as HTML list for better readability in email
        if (!empty($added_services)) {
            $added_services_text = '<ul style="margin: 10px 0; padding-left: 20px;">';
            foreach ($added_services as $service) {
                $added_services_text .= '<li style="margin: 5px 0;">' . esc_html($service) . '</li>';
            }
            $added_services_text .= '</ul>';
        } else {
            // If no services found, try to get all admin-added extras as fallback
            $added_services_text = '<p>' . __('Additional services', 'hourly-room-booking') . '</p>';
        }
        
        // Use notification manager to send email with template
        $notification_manager = HRB_Notification_Manager::getInstance();
        
        // Send notification with custom data including payment link, additional amount (will be formatted by notification manager), and services details
        // Pass raw amount - notification manager will format it like total_amount
        // Use total pending amount (not just current additional amount)
        $custom_data = array(
            'payment_link' => $payment_link,
            'additional_amount' => $payment_amount, // Pass total pending amount, notification manager will format it
            'added_services' => $added_services_text // Pass HTML formatted list
        );
        
        return $notification_manager->send_notification($booking_id, 'additional_payment_required', $custom_data);
    }
    
    /**
     * AJAX handler: Send additional payment link email
     */
    public function ajax_send_additional_payment_link() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
        }
        
        // Check permissions
        if (!current_user_can('hrb_manage_bookings')) {
            wp_send_json_error(__('You do not have permission to perform this action', 'hourly-room-booking'));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        if (!$booking_id) {
            wp_send_json_error(__('Invalid booking ID', 'hourly-room-booking'));
        }
        
        // Get booking details
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            wp_send_json_error(__('Booking not found', 'hourly-room-booking'));
        }
        
        // Get pending payment amount
        global $wpdb;
        $total_pending_amount = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}hrb_payments 
            WHERE booking_id = %d AND status = 'pending' AND transaction_id LIKE 'ADD_%%'",
            $booking_id
        ));
        
        if ($total_pending_amount <= 0) {
            wp_send_json_error(__('No pending payment found for this booking', 'hourly-room-booking'));
        }
        
        // Get newly added extras (all admin-added extras)
        $extras_manager = HRB_Extras::getInstance();
        $booking_extras = $extras_manager->get_booking_extras($booking_id);
        
        $newly_added_extras = array();
        foreach ($booking_extras as $extra) {
            if (!empty($extra->added_by_admin) && ($extra->added_by_admin == 1 || $extra->added_by_admin === '1' || $extra->added_by_admin === true)) {
                $newly_added_extras[] = intval($extra->id);
            }
        }
        
        // Send email
        $result = $this->send_additional_payment_email($booking_id, $total_pending_amount, $newly_added_extras);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Payment link email sent successfully', 'hourly-room-booking')
        ));
    }
    
    /**
     * AJAX handler: Mark additional payment as complete for onsite payments
     */
    public function ajax_mark_additional_payment_complete() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
        }
        
        // Check permissions
        if (!current_user_can('hrb_manage_bookings')) {
            wp_send_json_error(__('You do not have permission to perform this action', 'hourly-room-booking'));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        if (!$booking_id) {
            wp_send_json_error(__('Invalid booking ID', 'hourly-room-booking'));
        }
        
        // Get booking details
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            wp_send_json_error(__('Booking not found', 'hourly-room-booking'));
        }
        
        // Verify payment method is onsite or cash
        $payment_method_normalized = strtolower(trim($booking->payment_method ?? ''));
        if (!in_array($payment_method_normalized, ['onsite', 'cash'])) {
            wp_send_json_error(__('This action is only available for onsite/cash payment methods', 'hourly-room-booking'));
        }
        
        // Get ALL pending payments for this booking (including original and additional service payments)
        global $wpdb;
        $pending_payments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hrb_payments 
            WHERE booking_id = %d AND status = 'pending'",
            $booking_id
        ));
        
        if (empty($pending_payments)) {
            wp_send_json_error(__('No pending payments found for this booking', 'hourly-room-booking'));
        }
        
        // Check if there are already completed payments (to determine if this is first time or additional payment)
        $existing_completed_payments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hrb_payments 
            WHERE booking_id = %d AND status IN ('completed', 'paid')",
            $booking_id
        ));
        
        $is_first_payment = ($existing_completed_payments == 0);
        
        // Update all pending payments to 'completed'
        $updated_count = 0;
        foreach ($pending_payments as $payment) {
            $result = $wpdb->update(
                $wpdb->prefix . 'hrb_payments',
                array(
                    'status' => 'completed',
                    'processed_at' => current_time('mysql')
                ),
                array('id' => $payment->id),
                array('%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $updated_count++;
            }
        }
        
        if ($updated_count === 0) {
            wp_send_json_error(__('Failed to update payment status', 'hourly-room-booking'));
        }
        
        // Sync payment status to booking table
        $booking_manager->update_booking($booking_id, array(
            'payment_status' => 'completed'
        ), false); // Don't send notification during update
        
        // Get updated booking
        $booking = $booking_manager->get_booking($booking_id);
        
        if ($booking) {
            // Ensure booking status is confirmed (required for invoice and email)
            if ($booking->status !== 'confirmed') {
                $booking_manager->update_booking($booking_id, array('status' => 'confirmed'), false);
                $booking = $booking_manager->get_booking($booking_id);
            }
            
            $invoice_generator = HRB_Invoice_Generator::getInstance();
            
            if ($is_first_payment) {
                // First time payment: Generate invoice if it doesn't exist and send payment confirmation email
                $existing_invoice = $invoice_generator->get_invoice_by_booking($booking_id);
                
                if (!$existing_invoice) {
                    $invoice_id = $booking_manager->create_invoice($booking_id);
                    if (!is_wp_error($invoice_id)) {
                        // Generate PDF for the invoice
                        $invoice_generator->generate_invoice_pdf($invoice_id);
                    }
                } else {
                    // Ensure PDF exists
                    if (empty($existing_invoice->pdf_file_path)) {
                        $invoice_generator->generate_invoice_pdf($existing_invoice->id);
                    }
                }
                
                // Send payment confirmation email for first payment
                $booking_manager->send_booking_notification($booking_id, 'payment_confirmation');
            } else {
                // Additional payment: Regenerate invoice (which automatically sends updated invoice email)
                $invoice_result = $invoice_generator->regenerate_invoice($booking_id);
                
                if (is_wp_error($invoice_result)) {
                    // Continue anyway - invoice regeneration failure shouldn't block payment completion
                    // Log error but don't fail the operation
                }
                // regenerate_invoice() automatically sends the updated invoice email, so no need to send payment_confirmation
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('Successfully marked %d payment(s) as complete', 'hourly-room-booking'),
                $updated_count
            )
        ));
    }
    
    /**
     * AJAX handler for regenerating invoice
     */
    public function ajax_regenerate_invoice() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hrb_nonce')) {
            wp_send_json_error(__('Security check failed', 'hourly-room-booking'));
        }
        
        // Check permissions
        if (!current_user_can('hrb_manage_bookings')) {
            wp_send_json_error(__('You do not have permission to perform this action', 'hourly-room-booking'));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        if (!$booking_id) {
            wp_send_json_error(__('Invalid booking ID', 'hourly-room-booking'));
        }
        
        // Get booking details
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking($booking_id);
        
        if (!$booking) {
            wp_send_json_error(__('Booking not found', 'hourly-room-booking'));
        }
        
        // Regenerate invoice
        $invoice_generator = HRB_Invoice_Generator::getInstance();
        $result = $invoice_generator->regenerate_invoice($booking_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Invoice regenerated and sent via email successfully', 'hourly-room-booking')
        ));
    }

    /**
     * Render confirmation modal HTML in admin footer
     * This makes it available on all admin pages
     */
    public function render_confirmation_modal() {
        // Only render on plugin admin pages
        $screen = get_current_screen();
        if (!$screen || (strpos($screen->id, 'hrb-') === false && $screen->id !== 'toplevel_page_hrb-dashboard')) {
            return;
        }
        ?>
        <!-- Custom Confirmation Modal Styles -->
        <style type="text/css">
        #hrb-confirm-modal {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: rgba(0, 0, 0, 0.6) !important;
            backdrop-filter: blur(4px);
            z-index: 9999999999 !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            animation: fadeInOverlay 0.3s ease;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            overflow: auto !important;
        }
        #hrb-confirm-modal.show {
            display: flex !important;
        }
        @keyframes fadeInOverlay {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        #hrb-confirm-modal .hrb-confirm-content {
            background: #ffffff !important;
            border-radius: 16px !important;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
            max-width: 480px !important;
            width: 90% !important;
            max-height: 90vh !important;
            overflow: hidden !important;
            animation: confirmSlideIn 0.3s ease;
            transform: scale(0.9);
            margin: auto !important;
            position: relative !important;
        }
        #hrb-confirm-modal.show .hrb-confirm-content {
            transform: scale(1) !important;
        }
        @keyframes confirmSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        #hrb-confirm-modal .hrb-confirm-icon {
            width: 64px !important;
            height: 64px !important;
            margin: 0 auto 20px !important;
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%) !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 32px !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3) !important;
            animation: iconPulse 0.5s ease;
        }
        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        #hrb-confirm-modal .hrb-confirm-header {
            padding: 30px 30px 20px !important;
            text-align: center !important;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%) !important;
        }
        #hrb-confirm-modal .hrb-confirm-header h3 {
            margin: 0 0 10px 0 !important;
            font-size: 20px !important;
            font-weight: 600 !important;
            color: #1d2327 !important;
            line-height: 1.4 !important;
        }
        #hrb-confirm-modal .hrb-confirm-body {
            padding: 20px 30px !important;
            text-align: center !important;
            color: #646970 !important;
            line-height: 1.6 !important;
        }
        #hrb-confirm-modal .hrb-confirm-message {
            font-size: 15px;
            margin-bottom: 20px;
            color: #1d2327;
        }
        #hrb-confirm-modal .hrb-confirm-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
            text-align: left;
            border-left: 3px solid #0073aa;
        }
        #hrb-confirm-modal .hrb-confirm-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e1e5e9;
        }
        #hrb-confirm-modal .hrb-confirm-detail-row:last-child {
            border-bottom: none;
        }
        #hrb-confirm-modal .hrb-confirm-detail-label {
            font-weight: 600;
            color: #646970;
            font-size: 14px;
        }
        #hrb-confirm-modal .hrb-confirm-detail-value {
            font-weight: 600;
            color: #1d2327;
            font-size: 14px;
        }
        #hrb-confirm-modal .hrb-confirm-detail-value.original {
            color: #dc3545;
        }
        #hrb-confirm-modal .hrb-confirm-detail-value.new {
            color: #28a745;
        }
        #hrb-confirm-modal .hrb-confirm-footer {
            padding: 20px 30px !important;
            border-top: 1px solid #e1e5e9 !important;
            display: flex !important;
            gap: 12px !important;
            justify-content: flex-end !important;
            background: #f8f9fa !important;
        }
        #hrb-confirm-modal .hrb-confirm-btn {
            padding: 12px 24px !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            cursor: pointer !important;
            transition: all 0.2s ease;
            min-width: 100px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
        }
        #hrb-confirm-modal .hrb-confirm-btn-cancel {
            background: #f0f0f1;
            color: #1d2327;
            border: 1px solid #c3c4c7;
        }
        #hrb-confirm-modal .hrb-confirm-btn-cancel:hover {
            background: #e0e0e1;
            border-color: #a7aaad;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        #hrb-confirm-modal .hrb-confirm-btn-confirm {
            background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 115, 170, 0.3);
        }
        #hrb-confirm-modal .hrb-confirm-btn-confirm:hover {
            background: linear-gradient(135deg, #005a87 0%, #004a70 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0, 115, 170, 0.4);
        }
        #hrb-confirm-modal .hrb-confirm-btn:active {
            transform: translateY(0);
        }
        
        /* Type variants: success, danger, warning */
        #hrb-confirm-modal.type-success .hrb-confirm-icon {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%) !important;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3) !important;
        }
        #hrb-confirm-modal.type-success .hrb-confirm-details {
            border-left-color: #28a745;
        }
        #hrb-confirm-modal.type-success .hrb-confirm-btn-confirm {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        #hrb-confirm-modal.type-success .hrb-confirm-btn-confirm:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
        }
        #hrb-confirm-modal.type-success .hrb-confirm-warning-message {
            color: #28a745;
        }
        
        #hrb-confirm-modal.type-danger .hrb-confirm-icon {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3) !important;
        }
        #hrb-confirm-modal.type-danger .hrb-confirm-details {
            border-left-color: #dc3545;
        }
        #hrb-confirm-modal.type-danger .hrb-confirm-btn-confirm {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        #hrb-confirm-modal.type-danger .hrb-confirm-btn-confirm:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            box-shadow: 0 6px 16px rgba(220, 53, 69, 0.4);
        }
        #hrb-confirm-modal.type-danger .hrb-confirm-warning-message {
            color: #dc3545;
        }
        
        #hrb-confirm-modal.type-warning .hrb-confirm-icon {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%) !important;
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3) !important;
        }
        #hrb-confirm-modal.type-warning .hrb-confirm-details {
            border-left-color: #ffc107;
        }
        #hrb-confirm-modal.type-warning .hrb-confirm-btn-confirm {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
        }
        #hrb-confirm-modal.type-warning .hrb-confirm-btn-confirm:hover {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            box-shadow: 0 6px 16px rgba(255, 152, 0, 0.4);
        }
        #hrb-confirm-modal.type-warning .hrb-confirm-warning-message {
            color: #ff9800;
        }
        
        #hrb-confirm-modal .hrb-confirm-warning-message {
            font-size: 14px;
            margin-top: 10px;
            font-weight: 500;
        }
        @media (max-width: 480px) {
            #hrb-confirm-modal .hrb-confirm-content {
                width: 95%;
                margin: 10px;
            }
            #hrb-confirm-modal .hrb-confirm-header,
            #hrb-confirm-modal .hrb-confirm-body,
            #hrb-confirm-modal .hrb-confirm-footer {
                padding: 20px;
            }
            #hrb-confirm-modal .hrb-confirm-footer {
                flex-direction: column;
            }
            #hrb-confirm-modal .hrb-confirm-btn {
                width: 100%;
            }
        }
        </style>
        <!-- Custom Confirmation Modal -->
        <div id="hrb-confirm-modal">
            <div class="hrb-confirm-content">
                <div class="hrb-confirm-header">
                    <div class="hrb-confirm-icon">⚠️</div>
                    <h3 id="hrb-confirm-title"><?php _e('Confirm Action', 'hourly-room-booking'); ?></h3>
                </div>
                <div class="hrb-confirm-body">
                    <div class="hrb-confirm-message" id="hrb-confirm-message"></div>
                    <div class="hrb-confirm-warning-message" id="hrb-confirm-warning-message"></div>
                    <div class="hrb-confirm-details1" id="hrb-confirm-details"></div>
                </div>
                <div class="hrb-confirm-footer">
                    <button type="button" class="hrb-confirm-btn hrb-confirm-btn-cancel" id="hrb-confirm-cancel">
                        <?php _e('Cancel', 'hourly-room-booking'); ?>
                    </button>
                    <button type="button" class="hrb-confirm-btn hrb-confirm-btn-confirm" id="hrb-confirm-ok">
                        <?php _e('Confirm', 'hourly-room-booking'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}
?>