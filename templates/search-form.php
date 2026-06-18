<?php
/**
 * Room Search Form Template
 * Displays search and filter form with room results
 */

if (!defined('ABSPATH')) {
    exit;
}

$show_filters = isset($atts['show_filters']) ? $atts['show_filters'] === 'true' : true;
$rooms_per_page = isset($atts['rooms_per_page']) ? intval($atts['rooms_per_page']) : 6;
$columns = isset($atts['columns']) ? intval($atts['columns']) : 3;
$show_price = isset($atts['show_price']) ? $atts['show_price'] === 'true' : true;
$show_capacity = isset($atts['show_capacity']) ? $atts['show_capacity'] === 'true' : true;
$show_amenities = isset($atts['show_amenities']) ? $atts['show_amenities'] === 'true' : true;
$show_view_button = isset($atts['show_view_button']) ? $atts['show_view_button'] === 'true' : true;

// Get settings for currency
$settings = HRB_Settings::getInstance();
$currency_symbol = $settings->get('hrb_currency_symbol', '�');

// Get customizable labels
$label_search_button = $settings->get_label('hrb_label_search_button');
$label_clear_all_button = $settings->get_label('hrb_label_clear_all_button');
$label_loading_message = $settings->get_label('hrb_label_loading_message');

// Get room manager
$room_manager = HRB_Room_Manager::getInstance();
$all_rooms = $room_manager->get_all_rooms();

// Filter active rooms only
$rooms = array_filter($all_rooms, function($room) {
    return $room->is_active;
});

// Get filter parameters from URL
$filter_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
$filter_time = isset($_GET['time']) ? sanitize_text_field($_GET['time']) : '';
$filter_duration = isset($_GET['duration']) ? sanitize_text_field($_GET['duration']) : '';

// Auto-select today's date if no date is provided
if (empty($filter_date)) {
    $filter_date = date('Y-m-d');
}

// Don't filter rooms - show all rooms but mark availability
$room_availability = array();
global $wpdb;

foreach ($rooms as $room) {
    $is_available = true;
    
    // Always check availability when date is set (now always set to today by default)
    if ($filter_date) {
        // Check if there are any available time slots for this room on this date
        // This respects the booking hours configured in settings and checks for master locks
        // Route through generate_available_time_slots() (the single lock-aware
        // source of truth: master locks, room locks, bookings, cooldown,
        // booking window and past-time slots). Mirrors the AJAX search_rooms()
        // logic so the initial render and live re-search always agree.
        $ajax_handler = HRB_Ajax_Handler::getInstance();
        $check_duration = $filter_duration ?: 2; // Use filter duration or default to 2 hours
        $all_slots = $ajax_handler->generate_available_time_slots($room->id, $filter_date, $check_duration, 0);

        if ($filter_time) {
            // Specific arrival time selected -> that exact slot must be free.
            $selected_start = substr($filter_time, 0, 5);
            $is_available = false;
            foreach ($all_slots as $slot) {
                if (substr($slot['start_time'], 0, 5) === $selected_start) {
                    $is_available = !empty($slot['available']);
                    break;
                }
            }
        } else {
            // No specific time -> any free slot of the (filter or default) duration.
            $is_available = false;
            foreach ($all_slots as $slot) {
                if (!empty($slot['available'])) {
                    $is_available = true;
                    break;
                }
            }
        }
    }
    $room_availability[$room->id] = $is_available;
}
?>

<style>
/* Enhanced Search Form Styles */


.hrb-search-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.hrb-search-form {
    background: var(--hrb-gradient-primary);
    border-radius: var(--hrb-radius-xl);
    padding: 40px;
    margin-bottom: 40px;
    box-shadow: var(--hrb-shadow-xl);
    position: relative;
    overflow: hidden;
}

.hrb-search-form::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
    pointer-events: none;
}

.hrb-filters-form {
    position: relative;
    z-index: 2;
}

.hrb-search-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    gap: 20px;
    align-items: end;
}

.hrb-search-col {
    display: flex;
    flex-direction: column;
}

.hrb-search-col label {
    color: white;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hrb-search-col input,
.hrb-search-col select {
    padding: 16px 20px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--hrb-radius-lg);
    background: rgba(255, 255, 255, 0.9);
    color: var(--hrb-text);
    font-size: 16px;
    font-weight: 500;
    transition: var(--hrb-transition);
    backdrop-filter: blur(10px);
}

.hrb-search-col input:focus,
.hrb-search-col select:focus {
    outline: none;
    border-color: white;
    background: white;
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

.hrb-search-col input::placeholder {
    color: var(--hrb-text-muted);
}

.hrb-btn {
    padding: 16px 32px;
    background: white;
    color: var(--hrb-primary);
    border: none;
    border-radius: var(--hrb-radius-lg);
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: var(--hrb-transition);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: var(--hrb-shadow-lg);
    position: relative;
    overflow: hidden;
}



.hrb-btn:hover {
    transform: translateY(-3px);
    box-shadow: var(--hrb-shadow-xl);
    color: white;
}

.hrb-btn:hover::before {
    left: 0;
}

.hrb-btn span {
    position: relative;
    z-index: 2;
}

/* Search Buttons Container */
.hrb-search-buttons {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

/* Clear Filters Button Styles */
.hrb-search-buttons .hrb-clear-filters {
    padding: 10px 24px;
    border: 2px solid var(--hrb-error);
    border-radius: 4px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--hrb-error);
}

.hrb-search-buttons .hrb-clear-filters:hover {
    background: var(--hrb-error);
    color: white;
    text-decoration: none;
    transform: translateY(-2px);
    box-shadow: var(--hrb-shadow-md);
}

@media screen and (max-width: 768px) {
    .hrb-search-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .hrb-search-form {
        padding: 30px 20px;
    }
    
    .hrb-search-buttons {
        flex-direction: column;
        gap: 10px;
        width: 100%;
    }
    
    .hrb-search-buttons .hrb-clear-filters {
        font-size: 12px;
        padding: 16px 20px;
        width: 100%;
        min-width: auto;
        text-align: center;
    }
    
    .hrb-search-col {
        width: 100%;
    }
    
    .hrb-search-col .hrb-btn {
        width: 100%;
        padding: 16px 20px;
        font-size: 16px;
    }
}
</style>

<div class="hrb-search-container">
    <?php if ($show_filters): ?>
    <div class="hrb-search-form">
        <form class="hrb-filters-form" id="hrb-room-search-form">
            <div class="hrb-search-row">
                <div class="hrb-search-col">
                    <label for="hrb-search-date"><?php _e('Date', 'hourly-room-booking'); ?></label>
                    <input type="date"
                           id="hrb-search-date"
                           name="date"
                           min="<?php echo date('Y-m-d'); ?>"
                           max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>"
                           placeholder="<?php _e('Select date', 'hourly-room-booking'); ?>"
                           value="<?php echo esc_attr($filter_date); ?>">
                </div>

                <div class="hrb-search-col">
                    <label for="hrb-search-time"><?php _e('Time', 'hourly-room-booking'); ?></label>
                    <select id="hrb-search-time" name="time">
                        <option value=""><?php _e('Any time', 'hourly-room-booking'); ?></option>
                        <?php
                        // Get booking time range from settings
                        $booking_start_time = get_option('hrb_booking_start_time', '08:00');
                        $booking_end_time = get_option('hrb_booking_end_time', '20:00');
                        
                        // Parse start and end times
                        $start_hour = intval(substr($booking_start_time, 0, 2));
                        $end_hour = intval(substr($booking_end_time, 0, 2));
                        
                        // Generate time options based on settings (30-minute intervals)
                        for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
                            // Add :00 option
                            $time_value = sprintf('%02d:00', $hour);
                            echo '<option value="' . esc_attr($time_value) . '" ' . selected($filter_time, $time_value, false) . '>' . esc_html($time_value) . '</option>';
                            
                            // Add :30 option (except for the last hour if it ends exactly on the hour)
                            $booking_end_hour = intval(substr($booking_end_time, 0, 2));
                            $booking_end_minute = intval(substr($booking_end_time, 3, 2));
                            
                            // Only add :30 if it's not the last hour or if the end time has minutes
                            if ($hour < $booking_end_hour || ($hour == $booking_end_hour && $booking_end_minute > 0)) {
                                $time_value = sprintf('%02d:30', $hour);
                                echo '<option value="' . esc_attr($time_value) . '" ' . selected($filter_time, $time_value, false) . '>' . esc_html($time_value) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="hrb-search-col">
                    <label for="hrb-search-duration"><?php _e('Duration', 'hourly-room-booking'); ?></label>
                    <select id="hrb-search-duration" name="duration">
                        <option value=""><?php _e('Any duration', 'hourly-room-booking'); ?></option>
                        <?php for ($hours = 2; $hours <= 12; $hours++): ?>
                            <?php $selected = ($filter_duration == $hours) ? 'selected' : ''; ?>
                            <option value="<?php echo $hours; ?>" <?php echo $selected; ?>>
                                <?php echo $hours; ?> <?php _e('hours', 'hourly-room-booking'); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="hrb-search-col ">
                    <button type="submit" class="hrb-btn hrb-btn-primary">
                        <span><?php echo esc_html($label_search_button); ?></span>
                    </button>
                </div>
                <div class="hrb-search-col hrb-search-buttons">
                
                    <a href="<?php echo remove_query_arg(array('date', 'time', 'duration')); ?>" class="hrb-clear-filters">
                        <?php echo esc_html($label_clear_all_button); ?>
                    </a>
                
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="hrb-search-results">
        <div class="hrb-results-header">
            <h3 class="hrb-results-title">
                <?php 
                if ($filter_date || $filter_time || $filter_duration) {
                    $total_rooms = count($rooms);
                    $available_rooms = !empty($room_availability) ? array_sum($room_availability) : $total_rooms;
                    printf(__('Available Rooms (%d of %d)', 'hourly-room-booking'), $available_rooms, $total_rooms);
                } else {
                    printf(__('Available Rooms (%d)', 'hourly-room-booking'), count($rooms));
                }
                ?>
            </h3>
            <div class="hrb-results-controls">
                <select id="hrb-sort-rooms">
                    <option value="name"><?php _e('Sort by Name', 'hourly-room-booking'); ?></option>
                    <option value="price"><?php _e('Sort by Price', 'hourly-room-booking'); ?></option>
                </select>
            </div>
        </div>

        <div class="hrb-rooms-container" id="hrb-rooms-container">
            <?php 
            // Check if all rooms are unavailable when filters are applied
            $all_unavailable = false;
            if (!empty($room_availability) && !empty($rooms)) {
                $available_count = array_sum($room_availability);
                $all_unavailable = ($available_count === 0);
            }
            
            if (empty($rooms) || $all_unavailable): ?>
                <div class="hrb-no-results">
                    <div class="hrb-no-results-content">
                        <div class="hrb-no-results-icon">
                            <i class="hrb-icon-calendar"></i>
                        </div>
                        <h3><?php _e('All rooms are currently occupied', 'hourly-room-booking'); ?></h3>
                        <p><?php _e('All rooms that can be booked online are currently occupied for the selected time period.', 'hourly-room-booking'); ?></p>
                        <p><?php _e('Please call us at the following number and we will see what we can do for you:', 'hourly-room-booking'); ?></p>
                        <div class="">
                            <a href="tel:<?php echo esc_attr(get_option('hrb_company_phone', '')); ?>" class="hrb-phone-link">
                                <i class="hrb-icon-phone"></i>
                                <?php echo esc_html(get_option('hrb_company_phone', __('Phone number not configured', 'hourly-room-booking'))); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="hrb-rooms-grid hrb-columns-<?php echo esc_attr($columns); ?>">
                    <?php
                    $displayed_rooms = array_slice($rooms, 0, $rooms_per_page);
                    $available_count = 0;
                    foreach ($displayed_rooms as $room):
                        $is_available = isset($room_availability[$room->id]) ? $room_availability[$room->id] : true;
                        if ($is_available) $available_count++;
                    ?>
                        <div class="hrb-room-card <?php echo $is_available ? 'hrb-room-available' : 'hrb-room-unavailable'; ?>" data-room-id="<?php echo $room->id; ?>" data-available="<?php echo $is_available ? 'true' : 'false'; ?>">
                            <div class="hrb-room-image">
                                <?php
                                $images = $room_manager->get_room_images($room->id);
                                if (!empty($images)):
                                ?>
                                    <img src="<?php echo esc_url($images[0]); ?>" alt="<?php echo esc_attr($room->name); ?>">
                                <?php else: ?>
                                    <div class="hrb-room-placeholder">
                                        <i class="hrb-icon-room"></i>
                                    </div>
                                <?php endif; ?>

                            </div>

                            <div class="hrb-room-content">
                                <h3 class="hrb-room-title"><?php echo esc_html($room->name); ?></h3>

                                <?php if ($show_price): ?>
                                    <div class="hrb-room-price" data-room-id="<?php echo $room->id; ?>">
                                        <?php 
                                        $room_manager = HRB_Room_Manager::getInstance();
                                        
                                        // Always show price range by default, JavaScript will update it when filters are applied
                                        $price_range = $room_manager->get_room_price_range($room);
                                        echo '<span class="hrb-price">' . $price_range['formatted'] . '</span>';
                                        ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($room->description)): ?>
                                    <p class="hrb-room-description">
                                        <?php echo esc_html(wp_trim_words($room->description, 20)); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="hrb-room-details">
                                    <?php if ($show_capacity): ?>
                                        <div class="hrb-room-detail">
                                            <i class="hrb-icon-people"></i>
                                            <span><?php printf(__('Up to %d people', 'hourly-room-booking'), $room->capacity); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($show_amenities): ?>
                                        <?php
                                        $amenities = $room_manager->get_room_amenities($room->id);
                                        if (!empty($amenities)):
                                        ?>
                                            <div class="hrb-room-detail">
                                                <i class="hrb-icon-amenities"></i>
                                                <span>
                                                    <?php echo implode(', ', array_slice($amenities, 0, 3)); ?>
                                                    <?php if (count($amenities) > 3): ?>
                                                        <span class="hrb-more-amenities">+<?php echo count($amenities) - 3; ?> <?php _e('more', 'hourly-room-booking'); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$is_available): ?>
                                    <div class="hrb-room-unavailable-overlay">
                                        <div class="hrb-unavailable-badge">
                                            <i class="hrb-icon-calendar"></i>
                                            <?php _e('Unavailable', 'hourly-room-booking'); ?>
                                        </div>
                                        <p class="hrb-unavailable-message">
                                            <?php _e('This room is not available for the selected time period.', 'hourly-room-booking'); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <div class="hrb-room-actions">
                                    <?php if ($show_view_button): ?>
                                        <a href="#" class="hrb-btn hrb-btn-primary hrb-view-room" data-room-id="<?php echo $room->id; ?>" data-external-link="<?php echo esc_attr($room->external_link ?? ''); ?>">
                                            <?php _e('View Details', 'hourly-room-booking'); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($is_available): ?>
                                        <a href="#" class="hrb-btn hrb-btn-secondary hrb-book-room" data-room-id="<?php echo $room->id; ?>">
                                            <?php _e('Book Now', 'hourly-room-booking'); ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="tel:<?php echo esc_attr(get_option('hrb_company_phone', '')); ?>" class="hrb-btn hrb-btn-outline hrb-call-room" data-room-id="<?php echo $room->id; ?>">
                                            <i class="hrb-icon-phone"></i>
                                            <?php _e('Call to Check', 'hourly-room-booking'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($rooms) > $rooms_per_page): ?>
                    <div class="hrb-pagination">
                        <button class="hrb-btn hrb-btn-secondary" id="hrb-load-more"
                                data-page="1"
                                data-total="<?php echo count($rooms); ?>"
                                data-per-page="<?php echo $rooms_per_page; ?>">
                            <?php _e('Load More Rooms', 'hourly-room-booking'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>

.hrb-search-form {
    background: var(--hrb-background-light);
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.hrb-search-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 20px;
    align-items: end;
}

.hrb-search-col label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--hrb-text);
}

.hrb-search-col input,
.hrb-search-col select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--hrb-border);
    border-radius: 4px;
    font-size: 14px;
}

.hrb-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--hrb-border);
}

.hrb-results-title {
    margin: 0;
    color: var(--hrb-text);
}

.hrb-results-controls select {
    padding: 8px 12px;
    border: 1px solid var(--hrb-border);
    border-radius: 4px;
}

.hrb-rooms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
    margin-bottom: 30px;
}

.hrb-rooms-grid.hrb-columns-1 { grid-template-columns: 1fr !important; }
.hrb-rooms-grid.hrb-columns-2 { grid-template-columns: repeat(2, 1fr) !important; }
.hrb-rooms-grid.hrb-columns-3 { grid-template-columns: repeat(3, 1fr) !important; }
.hrb-rooms-grid.hrb-columns-4 { grid-template-columns: repeat(4, 1fr) !important; }

/* Responsive column adjustments */
@media screen and (max-width: 1200px) {
    .hrb-rooms-grid.hrb-columns-4 { grid-template-columns: repeat(3, 1fr) !important; }
}

@media screen and (max-width: 900px) {
    .hrb-rooms-grid.hrb-columns-4 { grid-template-columns: repeat(2, 1fr) !important; }
    .hrb-rooms-grid.hrb-columns-3 { grid-template-columns: repeat(2, 1fr) !important; }
}

@media screen and (max-width: 600px) {
    .hrb-rooms-grid.hrb-columns-4 { grid-template-columns: 1fr !important; }
    .hrb-rooms-grid.hrb-columns-3 { grid-template-columns: 1fr !important; }
    .hrb-rooms-grid.hrb-columns-2 { grid-template-columns: 1fr !important; }
}

/* CSS Variables are defined in frontend.css */

.hrb-room-card {
    background: var(--hrb-background);
    border: 1px solid var(--hrb-border);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: var(--hrb-shadow);
}

.hrb-room-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--hrb-shadow-hover);
    border-color: var(--hrb-primary);
}

.hrb-room-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.hrb-room-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.hrb-room-card:hover .hrb-room-image img {
    transform: scale(1.05);
}

.hrb-room-placeholder {
    width: 100%;
    height: 100%;
    background: var(--hrb-background-light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hrb-text-light);
    font-size: 32px;
}

.hrb-room-price {
    margin: 10px 0 15px 0;
    padding: 12px 16px;
    background: var(--hrb-background-light);
    border: 1px solid var(--hrb-border);
    border-radius: 8px;
    text-align: center;
}

.hrb-price {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: var(--hrb-primary);
}

.hrb-price-label {
    display: block;
    font-size: 11px;
    color: var(--hrb-text-light);
}

.hrb-room-content {
    padding: 20px;
}

.hrb-room-title {
    margin: 0 0 12px 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--hrb-text);
}

.hrb-room-description {
    margin: 0 0 16px 0;
    color: var(--hrb-text-light);
    line-height: 1.5;
    font-size: 14px;
}

.hrb-room-details {
    margin-bottom: 20px;
}

.hrb-room-detail {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-size: 14px;
    color: var(--hrb-text);
    background: var(--hrb-background-light);
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid var(--hrb-primary);
}

.hrb-room-detail i {
    margin-right: 8px;
    font-size: 16px;
}

.hrb-room-detail:last-child {
    margin-bottom: 0;
}

.hrb-more-amenities {
    color: var(--hrb-text-light);
    font-size: 12px;
}

.hrb-room-actions {
    display: flex;
    gap: 10px;
}

.hrb-btn {
        flex: 1;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
    font-size: 14px;
}

.hrb-btn-primary {
    background: var(--hrb-primary);
    color: var(--hrb-background);
}

.hrb-btn-primary:hover {
    background: var(--hrb-secondary);
    transform: translateY(-1px);
}

.hrb-btn-secondary {
    background: var(--hrb-background-light);
    color: var(--hrb-text);
    border: 1px solid var(--hrb-border);
}

.hrb-btn-secondary:hover {
    background: var(--hrb-border);
}

/* Icons using modern Unicode */
.hrb-icon-room:before { content: "🏢"; }
.hrb-icon-people:before { content: "👥"; }
.hrb-icon-amenities:before { content: "⭐"; }

/* Professional Modal Styles */
.hrb-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.hrb-modal-content {
    background: var(--hrb-background);
    border: 1px solid var(--hrb-border);
    border-radius: 8px;
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.hrb-modal-header {
    background: var(--hrb-background-light);
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hrb-modal-header h3 {
    margin: 0;
    color: var(--hrb-text);
    font-size: 20px;
    font-weight: 600;
}

.hrb-modal-close {
    background: var(--hrb-background);
    border: 1px solid var(--hrb-border);
    font-size: 18px;
    color: var(--hrb-text);
    cursor: pointer;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.hrb-modal-close:hover {
    background: var(--hrb-background-light);
    border-color: var(--hrb-primary);
}

.hrb-modal-body {
    padding: 24px;
    max-height: calc(90vh - 80px);
    overflow-y: auto;
    background: var(--hrb-background);
}

.hrb-modal-body::-webkit-scrollbar {
    width: 6px;
}

.hrb-modal-body::-webkit-scrollbar-track {
    background: var(--hrb-background-light);
    border-radius: 3px;
}

.hrb-modal-body::-webkit-scrollbar-thumb {
    background: #e5e7eb;
    border-radius: 3px;
}

.hrb-loading-message {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.hrb-loading-spinner {
    width: 35px;
    height: 35px;
    border: 2px solid var(--hrb-border);
    border-radius: 50%;
    border-top-color: var(--hrb-primary);
    animation: hrb-spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes hrb-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.hrb-error {
    background: var(--hrb-error-light);
    color: var(--hrb-error-dark);
    padding: 12px 16px;
    border: 1px solid var(--hrb-error-light);
    border-radius: 6px;
    text-align: center;
}

@media (max-width: 768px) {
    .hrb-modal-content {
        margin: 10px;
        max-width: calc(100% - 20px);
        border-radius: 15px;
    }

    .hrb-modal-header {
        padding: 20px 25px;
    }

    .hrb-modal-header h3 {
        font-size: 20px;
    }

    .hrb-modal-body {
        padding: 25px;
    }

    .hrb-modal-close {
        width: 35px;
        height: 35px;
        font-size: 18px;
    }
}

.hrb-room-content {
    padding: 20px;
}

.hrb-room-title {
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 600;
}

.hrb-room-description {
    margin: 0 0 15px 0;
    color: var(--hrb-text-light);
    line-height: 1.4;
}

.hrb-room-meta {
    margin-bottom: 15px;
    color: var(--hrb-text-light);
    font-size: 14px;
}

.hrb-icon-people:before,
.hrb-icon-room:before {
    margin-right: 5px;
}

.hrb-pagination {
    text-align: center;
    margin-top: 30px;
}

.hrb-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.hrb-btn-primary {
    background: var(--hrb-primary);
    color: white;
}

.hrb-btn-primary:hover {
    background: var(--hrb-primary-dark);
}

.hrb-btn-secondary {
    background: var(--hrb-background-light);
    color: var(--hrb-primary);
    border: 1px solid var(--hrb-primary);
}

.hrb-btn-secondary:hover {
    background: var(--hrb-primary);
    color: white;
}

.hrb-no-results {
    text-align: center;
    padding: 60px 20px;
    color: var(--hrb-text-light);
}

.hrb-no-results-content {
    max-width: 500px;
    margin: 0 auto;
}

.hrb-no-results-icon {
    font-size: 48px;
    color: #e74c3c;
    margin-bottom: 20px;
}

.hrb-no-results h3 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 24px;
}

.hrb-no-results p {
    margin-bottom: 15px;
    line-height: 1.6;
}

.hrb-contact-info {
    margin-top: 25px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #3498db;
}

.hrb-phone-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #2c3e50;
    text-decoration: none;
    font-size: 18px;
    font-weight: 600;
    padding: 12px 24px;
    background: #3498db;
    color: white;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.hrb-phone-link:hover {
    background: #2980b9;
    color: white;
    text-decoration: none;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

/* Unavailable Room Styling */
.hrb-room-unavailable {
    opacity: 0.6;
    position: relative;
    filter: grayscale(0.3);
}

.hrb-room-unavailable .hrb-room-image {
    position: relative;
}

.hrb-room-unavailable-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    text-align: center;
    padding: 20px;
    z-index: 10;
}

.hrb-unavailable-badge {
    background: #e74c3c;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.hrb-unavailable-message {
    font-size: 14px;
    margin: 0;
    opacity: 0.9;
}

.hrb-btn-outline {
    background: transparent;
    border: 2px solid #3498db;
    color: #3498db;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.hrb-btn-outline:hover {
    background: #3498db;
    color: white;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.hrb-call-room {
    width: 100%;
    justify-content: center;
}

/* Icons */
.hrb-icon-room:before { content: "🏢"; }
.hrb-icon-people:before { content: "👥"; }
.hrb-icon-amenities:before { content: "⭐"; }
.hrb-icon-phone:before { content: "📞"; }
.hrb-icon-calendar:before { content: "📅"; }

@media screen and (max-width: 480px) {
    .hrb-search-row {
        grid-template-columns: 1fr;
    }
    .hrb-search-form{
        padding: 20px;
    }
    .hrb-search-container{
        padding: 15px;
    }

    .hrb-results-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }

    /* Only force single column on very small screens (phones) */
    .hrb-rooms-grid {
        grid-template-columns: 1fr !important;
    }
    
    .hrb-room-actions {
        flex-direction: column;
        gap: 10px;
    }
    
    .hrb-btn {
        width: 100%;
        padding: 12px 16px;
        font-size: 16px;
        text-align: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Translation strings
    const hrbTranslations = {
        bookNow: '<?php _e('Book Now', 'hourly-room-booking'); ?>',
        viewDetails: '<?php _e('View Details', 'hourly-room-booking'); ?>',
        upToPeople: '<?php _e('Up to %d people', 'hourly-room-booking'); ?>',
        more: '<?php _e('more', 'hourly-room-booking'); ?>',
        unavailable: '<?php _e('Unavailable', 'hourly-room-booking'); ?>',
        roomUnavailableMessage: '<?php _e('This room is not available for the selected time period.', 'hourly-room-booking'); ?>',
        callToCheck: '<?php _e('Call to Check', 'hourly-room-booking'); ?>'
    };
    
    // Overlay Loading Functions
    function showOverlayLoading() {
        // Remove any existing overlay
        hideOverlayLoading();

        // Create overlay
        const overlay = $(`
            <div class="hrb-loading-overlay">
                <div class="hrb-loading-message">
                    <div class="hrb-loading-spinner"></div>
                </div>
            </div>
        `);

        // Scope the loader to the search/results section instead of the whole
        // viewport. Append it to the results section (made position:relative so
        // the absolutely-positioned overlay covers just that area). Inline
        // position:absolute overrides the global fixed full-screen rule.
        let $target = $('.hrb-search-results');
        if (!$target.length) {
            $target = $('.hrb-search-container');
        }

        if ($target.length) {
            if ($target.css('position') === 'static') {
                $target.css('position', 'relative');
            }
            overlay.css({ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, width: 'auto', height: 'auto' });
            $target.append(overlay);
        } else {
            // Fallback: full-screen overlay if the section isn't present.
            $('body').append(overlay);
        }
    }
    
    function hideOverlayLoading() {
        $('.hrb-loading-overlay').remove();
    }
    
    // Auto-select today's date if date field is empty
    const dateInput = $('#hrb-search-date');
    if (!dateInput.val()) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.val(today);
    }
    
    // Check if URL has filter parameters
    const urlParams = new URLSearchParams(window.location.search);
    const hasUrlFilters = urlParams.has('date') || urlParams.has('time') || urlParams.has('duration');
    
    // Always trigger filter on page load to show availability for today's date (or URL date)
    // This ensures validation is applied even on initial page load
    showOverlayLoading();
    setTimeout(function() {
        filterRooms();
    }, 100); // Small delay to ensure DOM is ready
    // Handle search form submission
    $('#hrb-room-search-form').on('submit', function(e) {
        e.preventDefault();
        filterRooms();
    });

    // Handle duration change to update prices
    $('#hrb-search-duration').on('change', function() {
        updateRoomPrices();
    });

    // Function to update room prices based on selected duration
    function updateRoomPrices() {
        const duration = $('#hrb-search-duration').val();
        
        if (duration) {
            // Get all room price elements
            $('.hrb-room-price').each(function() {
                const roomId = $(this).data('room-id');
                const $priceElement = $(this).find('.hrb-price');
                
                // Make AJAX call to get specific price for this room and duration
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'hrb_get_room_price_for_duration',
                        room_id: roomId,
                        duration: duration,
                        nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $priceElement.text(response.data.formatted_price);
                        }
                    }
                });
            });
        } else {
            // Reset to price range when no duration selected
            $('.hrb-room-price').each(function() {
                const roomId = $(this).data('room-id');
                const $priceElement = $(this).find('.hrb-price');
                
                // Make AJAX call to get price range for this room
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'hrb_get_room_price_range',
                        room_id: roomId,
                        nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $priceElement.text(response.data.formatted_price);
                        }
                    }
                });
            });
        }
    }

    // Handle sort change
    $('#hrb-sort-rooms').on('change', function() {
        sortRooms($(this).val());
    });

    // Handle book room button (for search results and initial load)
    $(document).on('click', '.hrb-book-room', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');

        // Show booking form in modal (same as room-list.php)
        showBookingModal(roomId);
    });

    // Handle view room details button - redirect to external link
    $(document).on('click', '.hrb-view-room', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');
        const externalLink = $(this).data('external-link');

        // Use external link if available, otherwise use default room detail page
        if (externalLink && externalLink.trim() !== '') {
            window.open(externalLink, '_blank');
        } else {
            const roomDetailUrl = '<?php echo home_url('/room-details/'); ?>' + roomId;
            window.location.href = roomDetailUrl;
        }
    });

    // Handle legacy book now button (for backward compatibility)
    $(document).on('click', '.hrb-book-now', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');
        showBookingModal(roomId);
    });

    // Handle "Book This Room" button inside room details modal
    $(document).on('click', '.hrb-modal-body .hrb-btn-primary', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');
        if (roomId) {
            // Close the room details modal first
            $('.hrb-modal-overlay').remove();
            // Open booking modal
            showBookingModal(roomId);
        }
    });

    // Handle any button with "book" in the text or class inside modals
    $(document).on('click', '.hrb-modal-body button[data-room-id], .hrb-modal-body a[data-room-id]', function(e) {
        const buttonText = $(this).text().toLowerCase();
        if (buttonText.includes('book')) {
            e.preventDefault();
            const roomId = $(this).data('room-id');
            if (roomId) {
                // Close any existing modal first
                $('.hrb-modal-overlay').remove();
                // Open booking modal
                showBookingModal(roomId);
            }
        }
    });

    // Handle load more
    $('#hrb-load-more').on('click', function() {
        loadMoreRooms();
    });

    function filterRooms() {
        const formData = {
            date: $('#hrb-search-date').val(),
            time: $('#hrb-search-time').val(),
            duration: $('#hrb-search-duration').val()
        };

        // Show overlay loading
        showOverlayLoading();

        // Build the availability query.
        // Whenever the user selects a specific TIME we send it as start_time so the
        // server checks that exact slot (lock-aware), using the selected duration or a
        // 2h default. For "Any time" we send an empty start_time and the server checks
        // whole-day availability (a room is bookable if it has ANY free slot of the
        // requested/default duration that day).
        let startTime = '';
        let endTime = '';

        if (formData.time) {
            startTime = formData.time;

            // Ensure time format includes seconds for database compatibility
            if (!startTime.includes(':')) {
                startTime = startTime + ':00:00';
            } else if (startTime.split(':').length === 2) {
                startTime = startTime + ':00';
            }

            // Calculate end time using the selected duration (default 2h) — informational;
            // the server derives availability from start_time + duration.
            const durHours = parseInt(formData.duration, 10) || 2;
            const startTimestamp = new Date('1970-01-01T' + startTime + 'Z').getTime();
            const endTimestamp = startTimestamp + (durHours * 60 * 60 * 1000);
            endTime = new Date(endTimestamp).toISOString().substr(11, 8);
        }

        // Make AJAX call to filter rooms based on availability
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'hrb_search_rooms',
                date: formData.date,
                start_time: startTime,
                end_time: endTime,
                duration: formData.duration,
                min_capacity: 1,
                max_price: 999999,
                nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>'
            },
            success: function(response) {
                hideOverlayLoading();
                if (response.success && Array.isArray(response.data)) {
                    // Generate HTML from room data
                    let roomsHtml = '';
                    if (response.data.length > 0) {
                        // Check if all rooms are unavailable
                        const availableCount = response.data.filter(room => room.is_available !== false).length;
                        const allUnavailable = (availableCount === 0);
                        
                        if (allUnavailable) {
                            // Show enhanced message when all rooms are unavailable
                            roomsHtml = '<div class="hrb-no-results">' +
                                '<div class="hrb-no-results-content">' +
                                    '<div class="hrb-no-results-icon">' +
                                        '<i class="hrb-icon-calendar"></i>' +
                                    '</div>' +
                                    '<h3><?php _e('All rooms are currently occupied', 'hourly-room-booking'); ?></h3>' +
                                    '<p><?php _e('All rooms that can be booked online are currently occupied for the selected time period.', 'hourly-room-booking'); ?></p>' +
                                    '<p><?php _e('Please call us at the following number and we will see what we can do for you:', 'hourly-room-booking'); ?></p>' +
                                    '<div class="">' +
                                        '<a href="tel:<?php echo esc_attr(get_option('hrb_company_phone', '')); ?>" class="hrb-phone-link">' +
                                            '<i class="hrb-icon-phone"></i>' +
                                            '<?php echo esc_js(get_option('hrb_company_phone', __('Phone number not configured', 'hourly-room-booking'))); ?>' +
                                        '</a>' +
                                    '</div>' +
                                '</div>' +
                            '</div>';
                        } else {
                            // Show rooms with availability indicators
                            roomsHtml = '<div class="hrb-rooms-grid hrb-columns-<?php echo esc_attr($columns); ?>">';
                            response.data.forEach(function(room) {
                                roomsHtml += generateRoomCardHtml(room);
                            });
                            roomsHtml += '</div>';
                        }
                    } else {
                        // No rooms found at all
                        roomsHtml = '<div class="hrb-no-results">' +
                            '<div class="hrb-no-results-content">' +
                                '<div class="hrb-no-results-icon">' +
                                    '<i class="hrb-icon-calendar"></i>' +
                                '</div>' +
                                '<h3><?php _e('All rooms are currently occupied', 'hourly-room-booking'); ?></h3>' +
                                '<p><?php _e('All rooms that can be booked online are currently occupied for the selected time period.', 'hourly-room-booking'); ?></p>' +
                                '<p><?php _e('Please call us at the following number and we will see what we can do for you:', 'hourly-room-booking'); ?></p>' +
                                '<div class="hrb-contact-info">' +
                                    '<a href="tel:<?php echo esc_attr(get_option('hrb_company_phone', '')); ?>" class="hrb-phone-link">' +
                                        '<i class="hrb-icon-phone"></i>' +
                                        '<?php echo esc_js(get_option('hrb_company_phone', __('Phone number not configured', 'hourly-room-booking'))); ?>' +
                                    '</a>' +
                                '</div>' +
                            '</div>' +
                        '</div>';
                    }

                    $('#hrb-rooms-container').html(roomsHtml);
                    if (response.data.length > 0) {
                        const availableCount = response.data.filter(room => room.is_available !== false).length;
                        if (availableCount === 0) {
                            $('.hrb-results-title').text('Verfügbare Räume (0 von ' + response.data.length + ')');
                        } else {
                            $('.hrb-results-title').text('Verfügbare Räume (' + availableCount + ' von ' + response.data.length + ')');
                        }
                    } else {
                        $('.hrb-results-title').text('Verfügbare Räume (0)');
                    }
                    
                    // Update prices based on current duration selection
                    setTimeout(function() {
                        updateRoomPrices();
                    }, 100);
                } else {
                    // Fallback to showing all rooms
                    showAllRooms();
                }
            },
            error: function() {
                hideOverlayLoading();
                /* removed debug console.error */
                // Fallback to showing all rooms
                showAllRooms();
            }
        });

    }

    function showAllRooms() {
        const allRooms = $('.hrb-room-card');
        allRooms.show();
        $('.hrb-results-title').text('Verfügbare Räume (' + allRooms.length + ')');
        
        // Update prices based on current duration selection
        updateRoomPrices();
    }

    function generateRoomCardHtml(room) {

        const imageHtml = room.images && room.images.length > 0
            ? '<img src="' + room.images[0] + '" alt="' + room.name + '">'
            : '<div class="hrb-room-placeholder"><i class="hrb-icon-room"></i></div>';

        // Generate price section if show_price is true
        let priceHtml = '';
        <?php if ($show_price): ?>
        priceHtml = `
            <div class="hrb-room-price" data-room-id="${room.id}">
                <span class="hrb-price">${room.formatted_price}</span>
            </div>
        `;
        <?php endif; ?>

        // Generate capacity section if show_capacity is true
        let capacityHtml = '';
        <?php if ($show_capacity): ?>
        capacityHtml = `
            <div class="hrb-room-detail">
                <i class="hrb-icon-people"></i>
                <span>Up to ${room.capacity} people</span>
            </div>
        `;
        <?php endif; ?>

        // Generate amenities section if show_amenities is true
        let amenitiesHtml = '';
        <?php if ($show_amenities): ?>
        if (room.amenities && Array.isArray(room.amenities) && room.amenities.length > 0) {
            const displayAmenities = room.amenities.slice(0, 3);
            let amenitiesText = displayAmenities.join(', ');
            if (room.amenities.length > 3) {
                amenitiesText += ' <span class="hrb-more-amenities">+' + (room.amenities.length - 3) + ' ' + hrbTranslations.more + '</span>';
            }
            amenitiesHtml = `
                <div class="hrb-room-detail">
                    <i class="hrb-icon-amenities"></i>
                    <span>${amenitiesText}</span>
                </div>
            `;
        }
        <?php endif; ?>

        // Generate view button section if show_view_button is true
        let viewButtonHtml = '';
        <?php if ($show_view_button): ?>
        viewButtonHtml = `
            <a href="#" class="hrb-btn hrb-btn-primary hrb-view-room" data-room-id="${room.id}" data-external-link="${room.external_link || ''}">
                ${hrbTranslations.viewDetails}
            </a>
        `;
        <?php endif; ?>

        // Description with word limit like room-list
        const description = room.description ? room.description.split(' ').slice(0, 20).join(' ') : '';
        const descriptionHtml = description ? '<p class="hrb-room-description">' + description + '</p>' : '';

        // Check availability
        const isAvailable = room.is_available !== false;
        const availabilityClass = isAvailable ? 'hrb-room-available' : 'hrb-room-unavailable';
        const dataAvailable = isAvailable ? 'true' : 'false';
        
        // Generate unavailable overlay if room is not available
        let unavailableOverlay = '';
        if (!isAvailable) {
            unavailableOverlay = `
                <div class="hrb-room-unavailable-overlay">
                    <div class="hrb-unavailable-badge">
                        <i class="hrb-icon-calendar"></i>
                        ${hrbTranslations.unavailable}
                    </div>
                    <p class="hrb-unavailable-message">
                        ${hrbTranslations.roomUnavailableMessage}
                    </p>
                </div>
            `;
        }
        
        // Generate appropriate action button
        let actionButton = '';
        if (isAvailable) {
            actionButton = `<a href="#" class="hrb-btn hrb-btn-secondary hrb-book-room" data-room-id="${room.id}">${hrbTranslations.bookNow}</a>`;
        } else {
            actionButton = `<a href="tel:<?php echo esc_attr(get_option('hrb_company_phone', '')); ?>" class="hrb-btn hrb-btn-outline hrb-call-room" data-room-id="${room.id}"><i class="hrb-icon-phone"></i>${hrbTranslations.callToCheck}</a>`;
        }

        return `
            <div class="hrb-room-card ${availabilityClass}" data-room-id="${room.id}" data-available="${dataAvailable}">
                <div class="hrb-room-image">
                    ${imageHtml}
                    ${unavailableOverlay}
                </div>
                <div class="hrb-room-content">
                    <h3 class="hrb-room-title">${room.name}</h3>
                    ${priceHtml}
                    ${descriptionHtml}
                    <div class="hrb-room-details">
                        ${capacityHtml}
                        ${amenitiesHtml}
                    </div>
                    <div class="hrb-room-actions">
                        ${viewButtonHtml}
                        ${actionButton}
                    </div>
                </div>
            </div>
        `;
    }

    function sortRooms(sortBy) {
        const container = $('#hrb-rooms-container .hrb-rooms-grid');
        if (container.length === 0) {
            // Try alternative container selector
            const altContainer = $('#hrb-rooms-container');
            if (altContainer.find('.hrb-room-card').length > 0) {
                sortRoomsInContainer(altContainer, sortBy);
                return;
            }
            return;
        }

        sortRoomsInContainer(container, sortBy);
    }

    function sortRoomsInContainer(container, sortBy) {
        const rooms = container.find('.hrb-room-card').detach();
        if (rooms.length === 0) {
            return;
        }

        rooms.sort(function(a, b) {
            let aVal, bVal;

            switch(sortBy) {
                case 'price':
                    // Check for both possible price structures
                    let priceA = $(a).find('.hrb-room-price .hrb-price').text();
                    if (!priceA) priceA = $(a).find('.hrb-room-price').text();
                    aVal = parseFloat(priceA.replace(/[^\d.]/g, '')) || 0;

                    let priceB = $(b).find('.hrb-room-price .hrb-price').text();
                    if (!priceB) priceB = $(b).find('.hrb-room-price').text();
                    bVal = parseFloat(priceB.replace(/[^\d.]/g, '')) || 0;
                    break;
                case 'name':
                    // Look for name in .hrb-room-title element
                    aVal = $(a).find('.hrb-room-title').text().toLowerCase().trim() || '';
                    bVal = $(b).find('.hrb-room-title').text().toLowerCase().trim() || '';
                    break;
                default:
                    return 0;
            }

            if (sortBy === 'price') {
                return aVal - bVal; // Numeric sort for price
            } else {
                // String sort for name
                if (aVal < bVal) return -1;
                if (aVal > bVal) return 1;
                return 0;
            }
        });

        // Append back to the correct container
        if (container.hasClass('hrb-rooms-grid')) {
            container.append(rooms);
        } else {
            // If container doesn't have the grid class, it might need to wrap in grid
            container.html('<div class="hrb-rooms-grid">' + rooms.map(function() { return this.outerHTML; }).get().join('') + '</div>');
        }

        /* removed debug log */
    }

    function loadMoreRooms() {
        const loadMoreBtn = $('#hrb-load-more');
        const currentPage = parseInt(loadMoreBtn.data('page')) || 1;
        const totalRooms = parseInt(loadMoreBtn.data('total')) || 0;
        const perPage = parseInt(loadMoreBtn.data('per-page')) || 6;
        const nextPage = currentPage + 1;

        // Load more rooms

        // Calculate if there are more rooms to load
        const loadedRooms = currentPage * perPage;
        if (loadedRooms >= totalRooms) {
            loadMoreBtn.text('<?php _e('No More Rooms', 'hourly-room-booking'); ?>').prop('disabled', true);
            return;
        }

        // Show loading state
        const originalText = loadMoreBtn.text();
        loadMoreBtn.html('<div class="hrb-loading-spinner"></div>').prop('disabled', true);

        // Load more rooms via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'hrb_load_more_rooms',
                page: nextPage,
                per_page: perPage,
                duration: $('#hrb-search-duration').val(),
                columns: <?php echo $columns; ?>,
                show_price: <?php echo $show_price ? 'true' : 'false'; ?>,
                show_capacity: <?php echo $show_capacity ? 'true' : 'false'; ?>,
                show_amenities: <?php echo $show_amenities ? 'true' : 'false'; ?>,
                show_view_button: <?php echo $show_view_button ? 'true' : 'false'; ?>,
                nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.rooms_html) {
                    // Append new rooms to the grid
                    $('.hrb-rooms-grid').append(response.data.rooms_html);

                    // Update prices for the newly loaded rooms
                    setTimeout(function() {
                        updateRoomPrices();
                    }, 100);

                    // Update button state
                    loadMoreBtn.data('page', nextPage);
                    loadMoreBtn.text(originalText).prop('disabled', false);

                    // Check if we've loaded all rooms
                    const newLoadedCount = nextPage * perPage;
                    if (newLoadedCount >= totalRooms) {
                        loadMoreBtn.text('<?php _e('No More Rooms', 'hourly-room-booking'); ?>').prop('disabled', true);
                    }

                } else {
                    /* removed debug console.error */
                    loadMoreBtn.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                /* removed debug console.error */
                loadMoreBtn.text(originalText).prop('disabled', false);
            }
        });
    }

    function showBookingModal(roomId) {
        // Get current search form values to pre-fill booking form
        const searchDate = $('#hrb-search-date').val();
        const searchTime = $('#hrb-search-time').val();
        const searchDuration = $('#hrb-search-duration').val();
        
        // Pre-fill values
        
        // Create modal overlay
        const modalHtml = `
            <div class="hrb-modal-overlay" id="hrb-booking-modal">
                <div class="hrb-modal-content">
                    <div class="hrb-modal-header">
                        <h3><?php _e('Book This Room', 'hourly-room-booking'); ?></h3>
                        <button class="hrb-modal-close">&times;</button>
                    </div>
                    <div class="hrb-modal-body">
                        <div class="hrb-loading-message"><div class="hrb-loading-spinner"></div></div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);

        // Load booking form via AJAX with pre-filled values
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'hrb_get_booking_form',
                room_id: roomId,
                // Pass search form values to pre-fill booking form
                date: searchDate,
                time: searchTime,
                duration: searchDuration,
                nonce: '<?php echo wp_create_nonce('hrb_booking_form_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#hrb-booking-modal .hrb-modal-body').html(response.data.html);
                } else {
                    $('#hrb-booking-modal .hrb-modal-body').html('<p class="hrb-error">' + response.data + '</p>');
                }
            },
            error: function() {
                $('#hrb-booking-modal .hrb-modal-body').html('<p class="hrb-error"><?php _e('Failed to load booking form', 'hourly-room-booking'); ?></p>');
            }
        });
    }

    function showRoomDetailsModal(roomId) {
        // Create modal overlay
        const modalHtml = `
            <div class="hrb-modal-overlay" id="hrb-details-modal">
                <div class="hrb-modal-content">
                    <div class="hrb-modal-header">
                        <h3><?php _e('Room Details', 'hourly-room-booking'); ?></h3>
                        <button class="hrb-modal-close">&times;</button>
                    </div>
                    <div class="hrb-modal-body">
                        <div class="hrb-loading-message"><div class="hrb-loading-spinner"></div></div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);

        // Load room details via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'hrb_get_room_details_modal',
                room_id: roomId,
                nonce: '<?php echo wp_create_nonce('hrb_room_details_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#hrb-details-modal .hrb-modal-body').html(response.data.html);
                } else {
                    $('#hrb-details-modal .hrb-modal-body').html('<p class="hrb-error">' + response.data + '</p>');
                }
            },
            error: function() {
                $('#hrb-details-modal .hrb-modal-body').html('<p class="hrb-error"><?php _e('Failed to load room details', 'hourly-room-booking'); ?></p>');
            }
        });
    }

    // Handle modal close
    $(document).on('click', '.hrb-modal-close, .hrb-modal-overlay', function(e) {
        if (e.target === this) {
            $(this).closest('.hrb-modal-overlay').remove();
        }
    });

    // Close modal on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.hrb-modal-overlay').remove();
        }
    });
});
</script>