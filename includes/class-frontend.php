<?php
/**
 * Frontend Class
 * Handles frontend functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Frontend {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'cn_rewriterules'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_booking_pages'));
    }
    
    public function cn_rewriterules() {
        // Add rewrite rules for booking pages
        add_rewrite_rule('^booking-details/?$', 'index.php?hrb_page=booking-details', 'top');
        add_rewrite_rule('^booking-success/?(.*)$', 'index.php?hrb_page=booking-success&booking_ref=$matches[1]', 'top');
        add_rewrite_rule('^booking-cancelled/?$', 'index.php?hrb_page=booking-cancelled', 'top');
        add_rewrite_rule('^room-details/([0-9]+)/?$', 'index.php?hrb_page=room-details&room_id=$matches[1]', 'top');

        // Check if rewrite rules need to be flushed
        if (get_option('hrb_rewrite_rules_flushed') !== HRB_VERSION) {
            flush_rewrite_rules();
            update_option('hrb_rewrite_rules_flushed', HRB_VERSION);
        }
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'hrb_page';
        $vars[] = 'booking_ref';
        $vars[] = 'room_id';
        return $vars;
    }
    
    public function enqueue_scripts() {
        if (!is_admin()) {
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-style', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-ui/1.12.1/jquery-ui.min.css');
            
            // PayPal SDK
            $paypal_client_id = get_option('hrb_paypal_client_id');
            if (!empty($paypal_client_id)) {
                $paypal_env = get_option('hrb_paypal_sandbox', 1) ? 'sandbox' : 'production';
                wp_enqueue_script('paypal-sdk', 
                    "https://www.paypal.com/sdk/js?client-id={$paypal_client_id}&currency=EUR&intent=capture&environment={$paypal_env}", 
                    array(), null, true);
            }
        }
    }
    
    public function handle_booking_pages() {
        $hrb_page = get_query_var('hrb_page');

        // Fallback: check URL directly if rewrite rules aren't working
        if (empty($hrb_page) && isset($_SERVER['REQUEST_URI'])) {
            $request_uri = $_SERVER['REQUEST_URI'];
            if (strpos($request_uri, '/booking-success') !== false) {
                $hrb_page = 'booking-success';
            } elseif (strpos($request_uri, '/booking-details') !== false) {
                $hrb_page = 'booking-details';
            } elseif (strpos($request_uri, '/booking-cancelled') !== false) {
                $hrb_page = 'booking-cancelled';
            }
        }

        switch ($hrb_page) {
            case 'booking-details':
                $this->show_booking_details();
                exit;

            case 'booking-success':
                $this->show_booking_success();
                exit;

            case 'booking-cancelled':
                $this->show_booking_cancelled();
                exit;

            case 'room-details':
                $this->show_room_details();
                exit;
        }
    }
    
    private function show_booking_details() {
        $booking_ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
        
        if (empty($booking_ref)) {
            wp_redirect(home_url());
            exit;
        }
        
        $booking_manager = HRB_Booking_Manager::getInstance();
        $booking = $booking_manager->get_booking_by_reference($booking_ref);
        
        if (!$booking) {
            wp_redirect(home_url());
            exit;
        }
        
        // Handle cancellation
        if (isset($_GET['action']) && $_GET['action'] === 'cancel' && wp_verify_nonce($_GET['_wpnonce'], 'cancel_booking_' . $booking->id)) {
            $result = $booking_manager->cancel_booking($booking->id, 'Cancelled by customer');
            if (!is_wp_error($result)) {
                wp_redirect(add_query_arg('cancelled', '1', remove_query_arg(array('action', '_wpnonce'))));
                exit;
            }
        }
        
        get_header();
        include HRB_PLUGIN_DIR . 'templates/booking-details.php';
        get_footer();
    }
    
    private function show_booking_success() {
        get_header();
        include HRB_PLUGIN_DIR . 'templates/booking-success.php';
        get_footer();
    }
    
    private function show_booking_cancelled() {
        get_header();
        include HRB_PLUGIN_DIR . 'templates/booking-cancelled.php';
        get_footer();
    }
    
    private function show_room_details() {
        $room_id = get_query_var('room_id') ?: (isset($_GET['room_id']) ? intval($_GET['room_id']) : 0);
        
        if (!$room_id) {
            wp_redirect(home_url());
            exit;
        }
        
        // Set room_id for the template
        $GLOBALS['hrb_room_id'] = $room_id;
        
        include HRB_PLUGIN_DIR . 'templates/room-details.php';
        exit;
    }
}


?>