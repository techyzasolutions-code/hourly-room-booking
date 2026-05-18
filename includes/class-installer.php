<?php
/**
 * Database Installer
 * Handles database table creation and updates
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Installer {
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Extra locks table
        $extra_locks_table = $wpdb->prefix . 'hrb_extra_locks';
        $extra_locks_sql = "CREATE TABLE $extra_locks_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            extra_id int(11) NOT NULL,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            reason text,
            created_by int(11) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY extra_id (extra_id),
            KEY start_datetime (start_datetime),
            KEY end_datetime (end_datetime),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Master extra locks table
        $master_extra_locks_table = $wpdb->prefix . 'hrb_master_extra_locks';
        $master_extra_locks_sql = "CREATE TABLE $master_extra_locks_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            start_datetime datetime NOT NULL,
            end_datetime datetime NOT NULL,
            reason text,
            created_by int(11) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY start_datetime (start_datetime),
            KEY end_datetime (end_datetime),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($extra_locks_sql);
        dbDelta($master_extra_locks_sql);
    }
    
    /**
     * Drop database tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hrb_extra_locks");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hrb_master_extra_locks");
    }
}
