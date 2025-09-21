<?php
/**
 * Room Calendar Template
 * Displays a calendar view for a specific room showing availability
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($atts) || empty($atts['room_id'])) {
    return;
}

$room_id = intval($atts['room_id']);
$view = isset($atts['view']) ? $atts['view'] : 'month';
$height = isset($atts['height']) ? intval($atts['height']) : 600;

// Get room information
$room_manager = HRB_Room_Manager::getInstance();
$room = $room_manager->get_room($room_id);

if (!$room || !$room->is_active) {
    echo '<div class="hrb-error">' . __('Room not found or inactive', 'hourly-room-booking') . '</div>';
    return;
}

// Get calendar instance for events
$calendar = HRB_Calendar::getInstance();
?>

<div class="hrb-calendar-container">
    <div class="hrb-calendar-header">
        <h3 class="hrb-calendar-title">
            <?php printf(__('Calendar for %s', 'hourly-room-booking'), esc_html($room->name)); ?>
        </h3>
        <div class="hrb-calendar-legend">
            <div class="hrb-legend-item">
                <span class="hrb-legend-color hrb-booking-confirmed"></span>
                <span><?php _e('Booked', 'hourly-room-booking'); ?></span>
            </div>
            <div class="hrb-legend-item">
                <span class="hrb-legend-color hrb-booking-pending"></span>
                <span><?php _e('Pending', 'hourly-room-booking'); ?></span>
            </div>
            <div class="hrb-legend-item">
                <span class="hrb-legend-color hrb-cooldown"></span>
                <span><?php _e('Cooldown', 'hourly-room-booking'); ?></span>
            </div>
        </div>
    </div>

    <div class="hrb-calendar-wrapper">
        <div id="hrb-room-calendar-<?php echo $room_id; ?>" class="hrb-calendar"></div>
    </div>

    <div class="hrb-calendar-info">
        <div class="hrb-room-quick-info">
            <h4><?php echo esc_html($room->name); ?></h4>
            <p class="hrb-room-capacity">
                <strong><?php _e('Capacity:', 'hourly-room-booking'); ?></strong>
                <?php printf(__('%d people', 'hourly-room-booking'), $room->capacity); ?>
            </p>
            <p class="hrb-room-price">
                <strong><?php _e('Starting from:', 'hourly-room-booking'); ?></strong>
                �<?php echo number_format($room->hourly_price, 2); ?> <?php _e('per hour', 'hourly-room-booking'); ?>
            </p>
            <a href="#" class="hrb-btn hrb-btn-primary hrb-book-this-room" data-room-id="<?php echo $room_id; ?>">
                <?php _e('Book This Room', 'hourly-room-booking'); ?>
            </a>
        </div>
    </div>
</div>

<style>
.hrb-calendar-container {
    max-width: 1200px;
    margin: 20px auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.hrb-calendar-header {
    background: #f8f9fa;
    padding: 20px 30px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.hrb-calendar-title {
    margin: 0;
    color: #333;
    font-size: 24px;
}

.hrb-calendar-legend {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.hrb-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.hrb-legend-color {
    width: 16px;
    height: 16px;
    border-radius: 3px;
}

.hrb-booking-confirmed {
    background: #dc3545;
}

.hrb-booking-pending {
    background: #ffc107;
}

.hrb-cooldown {
    background: #6c757d;
}

.hrb-calendar-wrapper {
    padding: 30px;
}

.hrb-calendar {
    min-height: <?php echo $height; ?>px;
}

.hrb-calendar-info {
    background: #f8f9fa;
    padding: 20px 30px;
    border-top: 1px solid #eee;
}

.hrb-room-quick-info h4 {
    margin: 0 0 15px 0;
    color: #333;
}

.hrb-room-quick-info p {
    margin: 0 0 10px 0;
    color: #666;
}

.hrb-btn {
    display: inline-block;
    padding: 12px 24px;
    background: #0073aa;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 500;
    margin-top: 15px;
    transition: background 0.3s ease;
}

.hrb-btn:hover {
    background: #005a87;
    color: white;
}

.hrb-error {
    background: #f8d7da;
    color: #721c24;
    padding: 20px;
    border-radius: 4px;
    margin: 20px 0;
    text-align: center;
}

/* FullCalendar custom styles */
.fc-event {
    border: none !important;
    font-size: 12px !important;
}

.fc-event.hrb-booking-confirmed {
    background: #dc3545 !important;
    color: white !important;
}

.fc-event.hrb-booking-pending {
    background: #ffc107 !important;
    color: #212529 !important;
}

.fc-event.hrb-cooldown {
    background: #6c757d !important;
    color: white !important;
}

.fc-time {
    font-weight: bold !important;
}

.fc-title {
    padding-left: 5px !important;
}

/* Responsive design */
@media (max-width: 768px) {
    .hrb-calendar-header {
        flex-direction: column;
        align-items: stretch;
    }

    .hrb-calendar-legend {
        justify-content: center;
    }

    .hrb-calendar-wrapper {
        padding: 15px;
    }

    .hrb-calendar-info {
        padding: 15px;
    }

    .hrb-calendar {
        min-height: 400px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Add a small delay to ensure all scripts are loaded
    setTimeout(function() {
        // Check if FullCalendar is loaded
        if (typeof $.fn.fullCalendar === 'undefined') {
            console.error('FullCalendar library is not loaded');
            console.log('Available jQuery plugins:', Object.keys($.fn));
            $('#hrb-room-calendar-<?php echo $room_id; ?>').html('<div class="hrb-error" style="padding: 20px; text-align: center; color: #dc3545;"><?php _e('Calendar library failed to load. Please refresh the page.', 'hourly-room-booking'); ?></div>');
            return;
        }

        initializeCalendar();
    }, 100);

    function initializeCalendar() {

    // Initialize FullCalendar
    $('#hrb-room-calendar-<?php echo $room_id; ?>').fullCalendar({
        defaultView: '<?php echo esc_js($view); ?>',
        height: <?php echo $height; ?>,
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        businessHours: {
            start: '08:00',
            end: '20:00',
            dow: [1, 2, 3, 4, 5, 6] // Monday through Saturday
        },
        minTime: '08:00:00',
        maxTime: '20:00:00',
        allDaySlot: false,
        slotDuration: '00:30:00',
        snapDuration: '00:30:00',
        events: function(start, end, timezone, callback) {
            // Load events for the calendar
            loadCalendarEvents(start, end, callback);
        },
        eventRender: function(event, element) {
            // Customize event appearance
            element.addClass('hrb-' + event.status);

            // Add tooltip with more information
            element.attr('title', event.title + '\n' +
                        '<?php _e('Time:', 'hourly-room-booking'); ?> ' + event.start.format('HH:mm') + ' - ' + event.end.format('HH:mm') + '\n' +
                        '<?php _e('Status:', 'hourly-room-booking'); ?> ' + event.statusText);
        },
        dayClick: function(date, jsEvent, view) {
            // Handle day/time slot click for booking
            if (date.isBefore(moment())) {
                alert('<?php _e('Cannot book for past dates', 'hourly-room-booking'); ?>');
                return;
            }

            // Redirect to booking form with pre-selected date
            const bookingUrl = '<?php echo site_url(); ?>?room_booking=<?php echo $room_id; ?>&date=' + date.format('YYYY-MM-DD') + '&time=' + date.format('HH:mm');
            window.location.href = bookingUrl;
        },
        eventClick: function(event, jsEvent, view) {
            // Show booking details
            showBookingDetails(event);
        }
    });

    // Handle book this room button
    $('.hrb-book-this-room').on('click', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');
        const bookingUrl = '<?php echo site_url(); ?>?room_booking=' + roomId;
        window.location.href = bookingUrl;
    });

    function loadCalendarEvents(start, end, callback) {
        // AJAX call to get calendar events
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'hrb_get_calendar_events',
                room_id: <?php echo $room_id; ?>,
                start: start.format('YYYY-MM-DD'),
                end: end.format('YYYY-MM-DD'),
                nonce: '<?php echo wp_create_nonce('hrb_calendar_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data.events);
                } else {
                    console.error('Failed to load calendar events:', response.data);
                    callback([]);
                }
            },
            error: function() {
                console.error('AJAX error loading calendar events');
                callback([]);
            }
        });
    }

    function showBookingDetails(event) {
        if (event.bookingId) {
            // Show booking details modal or redirect to booking page
            alert('<?php _e('Booking ID:', 'hourly-room-booking'); ?> ' + event.bookingId + '\n' +
                  '<?php _e('Customer:', 'hourly-room-booking'); ?> ' + (event.customerName || 'N/A') + '\n' +
                  '<?php _e('Status:', 'hourly-room-booking'); ?> ' + event.statusText);
        }
    }
    } // End initializeCalendar function
});
</script>