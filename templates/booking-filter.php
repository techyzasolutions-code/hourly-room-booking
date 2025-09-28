<?php
/**
 * Room Booking Filter Template (Homepage)
 * Displays only the search filter form with redirect functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

$redirect_url = isset($atts['redirect_url']) ? $atts['redirect_url'] : '/search-results/';
$show_title = isset($atts['show_title']) ? $atts['show_title'] === 'true' : true;
$title = isset($atts['title']) ? $atts['title'] : __('Find Your Perfect Room', 'hourly-room-booking');
$subtitle = isset($atts['subtitle']) ? $atts['subtitle'] : __('Search and book rooms by date, time, and duration', 'hourly-room-booking');
?>

<style>
/* Simple Filter Styles - Matching search-form.php */
.hrb-filter-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.hrb-filter-form {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 30px;
    margin: 20px 0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.hrb-filter-form-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: end;
}

.hrb-filter-field {
    display: flex;
    flex-direction: column;
}

.hrb-filter-field label {
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
    font-size: 14px;
}

.hrb-filter-field input,
.hrb-filter-field select {
    padding: 12px 16px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    width: 100%;
    box-sizing: border-box;
}

.hrb-filter-field input:focus,
.hrb-filter-field select:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
}

.hrb-filter-btn {
    padding: 12px 24px;
    background: #007cba;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hrb-filter-btn:hover {
    background: #005a87;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .hrb-filter-form {
        padding: 20px;
    }
    
    .hrb-filter-form-fields {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}
</style>

<div class="hrb-filter-container">
    <div class="hrb-filter-form">
        <form class="hrb-filter-form-fields" id="hrb-room-filter-form" method="GET" action="<?php echo esc_url($redirect_url); ?>">
            <div class="hrb-filter-field">
                <label for="hrb-filter-date"><?php _e('Date', 'hourly-room-booking'); ?></label>
                <input type="date"
                       id="hrb-filter-date"
                       name="date"
                       min="<?php echo date('Y-m-d'); ?>"
                       max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>"
                       placeholder="<?php _e('Select date', 'hourly-room-booking'); ?>"
                       value="<?php echo isset($_GET['date']) ? esc_attr($_GET['date']) : ''; ?>">
            </div>

            <div class="hrb-filter-field">
                <label for="hrb-filter-time"><?php _e('Time', 'hourly-room-booking'); ?></label>
                <select id="hrb-filter-time" name="time">
                    <option value=""><?php _e('Any time', 'hourly-room-booking'); ?></option>
                    <?php
                    // Get booking time range from settings
                    $booking_start_time = get_option('hrb_booking_start_time', '08:00');
                    $booking_end_time = get_option('hrb_booking_end_time', '20:00');
                    
                    // Parse start and end times
                    $start_hour = intval(substr($booking_start_time, 0, 2));
                    $end_hour = intval(substr($booking_end_time, 0, 2));
                    
                    // Generate time options based on settings
                    for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
                        $time_value = sprintf('%02d:00', $hour);
                        $selected = isset($_GET['time']) ? $_GET['time'] : '';
                        echo '<option value="' . esc_attr($time_value) . '" ' . selected($selected, $time_value, false) . '>' . esc_html($time_value) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="hrb-filter-field">
                <label for="hrb-filter-duration"><?php _e('Duration', 'hourly-room-booking'); ?></label>
                <select id="hrb-filter-duration" name="duration">
                    <option value=""><?php _e('Any duration', 'hourly-room-booking'); ?></option>
                    <option value="2" <?php selected(isset($_GET['duration']) ? $_GET['duration'] : '', '2'); ?>>2 <?php _e('hours', 'hourly-room-booking'); ?></option>
                    <option value="3" <?php selected(isset($_GET['duration']) ? $_GET['duration'] : '', '3'); ?>>3 <?php _e('hours', 'hourly-room-booking'); ?></option>
                    <option value="4" <?php selected(isset($_GET['duration']) ? $_GET['duration'] : '', '4'); ?>>4 <?php _e('hours', 'hourly-room-booking'); ?></option>
                    <option value="5" <?php selected(isset($_GET['duration']) ? $_GET['duration'] : '', '5'); ?>>5 <?php _e('hours', 'hourly-room-booking'); ?></option>
                    <option value="6" <?php selected(isset($_GET['duration']) ? $_GET['duration'] : '', '6'); ?>>6 <?php _e('hours', 'hourly-room-booking'); ?></option>
                    <option value="8" <?php selected(isset($_GET['duration']) ? $_GET['duration'] : '', '8'); ?>>8+ <?php _e('hours', 'hourly-room-booking'); ?></option>
                </select>
            </div>

            <div class="hrb-filter-field">
                <button type="submit" class="hrb-filter-btn">
                    <?php _e('Search Rooms', 'hourly-room-booking'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Simple form validation
    $('#hrb-room-filter-form').on('submit', function(e) {
        var $btn = $(this).find('button[type="submit"]');
        var originalText = $btn.text();
        $btn.text('<?php _e('Searching...', 'hourly-room-booking'); ?>').prop('disabled', true);
        
        // Re-enable after a short delay
        setTimeout(function() {
            $btn.text(originalText).prop('disabled', false);
        }, 2000);
    });
});
</script>

