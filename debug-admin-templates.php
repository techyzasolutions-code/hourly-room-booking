<?php
/**
 * Debug script to test admin email templates
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if admin templates exist
global $wpdb;

echo "<h2>Admin Email Templates Debug</h2>";

// Check if template_type column exists
$column_exists = $wpdb->get_results($wpdb->prepare(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'template_type'",
    DB_NAME, $wpdb->prefix . 'hrb_email_templates'
));

echo "<h3>Database Structure:</h3>";
if (empty($column_exists)) {
    echo "<p style='color: red;'>❌ template_type column does NOT exist</p>";
} else {
    echo "<p style='color: green;'>✅ template_type column exists</p>";
}

// Check existing templates
$templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hrb_email_templates ORDER BY template_type, template_key");

echo "<h3>Existing Templates:</h3>";
if (empty($templates)) {
    echo "<p>No templates found</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Template Key</th><th>Template Name</th><th>Template Type</th><th>Subject</th><th>Active</th></tr>";
    foreach ($templates as $template) {
        echo "<tr>";
        echo "<td>{$template->id}</td>";
        echo "<td>{$template->template_key}</td>";
        echo "<td>{$template->template_name}</td>";
        echo "<td>" . (isset($template->template_type) ? $template->template_type : 'N/A') . "</td>";
        echo "<td>{$template->subject}</td>";
        echo "<td>" . ($template->is_active ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test admin template lookup
echo "<h3>Admin Template Lookup Test:</h3>";
$admin_templates = array('booking_confirmation_admin', 'payment_confirmation_admin');

foreach ($admin_templates as $template_key) {
    $template = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hrb_email_templates WHERE template_key = %s AND template_type = 'admin' AND is_active = 1",
        $template_key
    ));
    
    if ($template) {
        echo "<p style='color: green;'>✅ Found admin template: {$template_key}</p>";
        echo "<p>Subject: {$template->subject}</p>";
    } else {
        echo "<p style='color: red;'>❌ Admin template not found: {$template_key}</p>";
    }
}

// Test user template lookup
echo "<h3>User Template Lookup Test:</h3>";
$user_templates = array('booking_confirmation_user', 'payment_confirmation_user');

foreach ($user_templates as $template_key) {
    $template = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hrb_email_templates WHERE template_key = %s AND template_type = 'user' AND is_active = 1",
        $template_key
    ));
    
    if ($template) {
        echo "<p style='color: green;'>✅ Found user template: {$template_key}</p>";
        echo "<p>Subject: {$template->subject}</p>";
    } else {
        echo "<p style='color: red;'>❌ User template not found: {$template_key}</p>";
    }
}

echo "<h3>Recommendations:</h3>";
if (empty($column_exists)) {
    echo "<p>1. Run the database migration to add template_type column</p>";
    echo "<p>2. Re-run the default data insertion to create admin templates</p>";
} else {
    echo "<p>Database structure looks good. Check if admin templates were created properly.</p>";
}
?>
