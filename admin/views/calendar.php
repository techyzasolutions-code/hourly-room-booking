<?php
/**
 * Calendar View
 * Displays the admin calendar interface with FullCalendar integration
 */

if (!defined('ABSPATH')) {
    exit;
}

$calendar = HRB_Calendar::getInstance();
$room_manager = HRB_Room_Manager::getInstance();
$rooms = $room_manager->get_all_rooms('all');

// Get selected room from URL parameter
$selected_room = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
?>

<div class="hrb-admin-page">
    <div class="hrb-page-header">
        <div class="hrb-page-title">
            <h1><?php _e('Calendar View', 'hourly-room-booking'); ?></h1>
            <p class="description"><?php _e('View and manage all room bookings in a visual calendar format.', 'hourly-room-booking'); ?></p>
        </div>
        <div class="hrb-page-actions">
            <button type="button" class="button button-primary" onclick="location.href='<?php echo admin_url('admin.php?page=hrb-bookings&action=add'); ?>'">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Add New Booking', 'hourly-room-booking'); ?>
            </button>
        </div>
    </div>

    <!-- Calendar Controls -->
    <div class="hrb-calendar-controls">
        <div class="hrb-room-filter">
            <label for="room-filter"><?php _e('Filter by Room:', 'hourly-room-booking'); ?></label>
            <select id="room-filter" onchange="filterByRoom(this.value)">
                <option value="0" <?php selected($selected_room, 0); ?>><?php _e('All Rooms', 'hourly-room-booking'); ?></option>
                <?php foreach ($rooms as $room): ?>
                    <option value="<?php echo $room->id; ?>" <?php selected($selected_room, $room->id); ?>>
                        <?php echo esc_html($room->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="hrb-calendar-views">
            <button type="button" class="button calendar-view-btn active" data-view="dayGridMonth">
                <?php _e('Month', 'hourly-room-booking'); ?>
            </button>
            <button type="button" class="button calendar-view-btn" data-view="timeGridWeek">
                <?php _e('Week', 'hourly-room-booking'); ?>
            </button>
            <button type="button" class="button calendar-view-btn" data-view="timeGridDay">
                <?php _e('Day', 'hourly-room-booking'); ?>
            </button>
            <button type="button" class="button calendar-view-btn" data-view="listWeek">
                <?php _e('List', 'hourly-room-booking'); ?>
            </button>
        </div>

        <div class="hrb-calendar-legend">
            <div class="legend-item">
                <span class="legend-color confirmed"></span>
                <?php _e('Confirmed', 'hourly-room-booking'); ?>
            </div>
            <div class="legend-item">
                <span class="legend-color pending"></span>
                <?php _e('Pending', 'hourly-room-booking'); ?>
            </div>
            <div class="legend-item">
                <span class="legend-color cancelled"></span>
                <?php _e('Cancelled', 'hourly-room-booking'); ?>
            </div>
            <div class="legend-item">
                <span class="legend-color completed"></span>
                <?php _e('Completed', 'hourly-room-booking'); ?>
            </div>
        </div>
    </div>

    <!-- Calendar Container -->
    <div id="hrb-calendar" class="hrb-calendar-container"></div>

    <!-- Quick Stats below calendar -->
    <div class="hrb-calendar-stats">
        <div class="hrb-stats-grid">
            <div class="hrb-stat-card">
                <div class="hrb-stat-number" id="stats-today">0</div>
                <div class="hrb-stat-label"><?php _e('Today\'s Bookings', 'hourly-room-booking'); ?></div>
            </div>
            <div class="hrb-stat-card">
                <div class="hrb-stat-number" id="stats-week">0</div>
                <div class="hrb-stat-label"><?php _e('This Week', 'hourly-room-booking'); ?></div>
            </div>
            <div class="hrb-stat-card">
                <div class="hrb-stat-number" id="stats-month">0</div>
                <div class="hrb-stat-label"><?php _e('This Month', 'hourly-room-booking'); ?></div>
            </div>
            <div class="hrb-stat-card">
                <div class="hrb-stat-number" id="stats-revenue"><?php echo hrb_format_amount(0); ?></div>
                <div class="hrb-stat-label"><?php _e('Month Revenue', 'hourly-room-booking'); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Booking Details Modal -->
<div id="booking-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h2 id="booking-modal-title"><?php _e('Booking Details', 'hourly-room-booking'); ?></h2>
            <span class="hrb-modal-close" onclick="closeBookingModal()">&times;</span>
        </div>
        <div class="hrb-modal-body" id="booking-modal-body">
            <!-- Content loaded via AJAX -->
        </div>
        <div class="hrb-modal-footer">
            <button type="button" class="button" onclick="closeBookingModal()"><?php _e('Close', 'hourly-room-booking'); ?></button>
            <button type="button" class="button button-primary" id="edit-booking-btn" onclick="editBooking()">
                <?php _e('Edit Booking', 'hourly-room-booking'); ?>
            </button>
        </div>
    </div>
</div>

<style>
/* Modern Calendar Page Styling with Red Gradient Theme */
.hrb-admin-page {
       background: #f8f9fa;
    min-height: 100vh;
    padding: 24px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.hrb-page-header {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    padding: 32px;
    border-radius: 16px;
    margin-bottom: 32px;
    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.15);
    position: relative;
    overflow: hidden;
    display: flex
;
    justify-content: space-between;
    align-items: flex-start;
}

/* .hrb-page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="%23ffffff" stroke-width="0.5" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>') repeat;
    pointer-events: none;
} */

.hrb-page-title {
    position: relative;
    z-index: 2;
}

.hrb-page-title h1 {
    margin: 0 0 8px 0;
    font-size: 2.5em;
    font-weight: 700;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    letter-spacing: -0.5px;
    color: #fff;
}

.hrb-page-title .description {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    margin: 0;
    font-weight: 400;
}

.hrb-page-actions {
    position: relative;
    z-index: 2;
}

.hrb-page-actions .button-primary {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.hrb-page-actions .button-primary:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

.hrb-calendar-controls {
    background: white;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.hrb-room-filter {
    display: flex;
    align-items: center;
    gap: 12px;
}

.hrb-room-filter label {
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.hrb-room-filter select {
    padding: 8px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: white;
    color: #374151;
    font-weight: 500;
    min-width: 180px;
    transition: all 0.3s ease;
}

.hrb-room-filter select:focus {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    outline: none;
}

.hrb-calendar-views {
    display: flex;
    gap: 8px;
    background: #f9fafb;
    padding: 4px;
    border-radius: 12px;
}

.calendar-view-btn {
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: #6b7280;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
}

.calendar-view-btn.active {
    border: 2px solid #8b5cf6 !important;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff !important;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.calendar-view-btn:hover:not(.active) {
    background: #e5e7eb;
    color: #374151;
}

.hrb-calendar-legend {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: center;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
}

.legend-color {
    width: 18px;
    height: 18px;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.legend-color.confirmed {
    background: linear-gradient(135deg, #10b981, #059669);
}

.legend-color.pending {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.legend-color.cancelled {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.legend-color.completed {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.hrb-calendar-container {
    background: white;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.1);
    min-height: 600px;
}

.hrb-calendar-stats {
    margin-top: 30px;
}

.hrb-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
}

.hrb-stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.hrb-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #ef4444, #dc2626);
}

.hrb-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(239, 68, 68, 0.15);
}

.hrb-stat-number {
    font-size: 2.5em;
    font-weight: 700;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.2;
    margin-bottom: 8px;
}

.hrb-stat-label {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hrb-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.hrb-modal-content {
    background: white;
    border-radius: 20px;
    width: 90%;
    max-width: 600px;
    max-height: 90%;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: scale(0.9);
    animation: modalIn 0.3s ease forwards;
}

@keyframes modalIn {
    to {
        transform: scale(1);
    }
}

.hrb-modal-header {
    padding: 24px 30px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hrb-modal-header h2 {
    margin: 0;
    font-weight: 600;
    font-size: 1.3em;
}

.hrb-modal-close {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 18px;
    color: white;
}

.hrb-modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.hrb-modal-body {
    padding: 30px;
    max-height: 400px;
    overflow-y: auto;
}

.hrb-modal-footer {
    padding: 20px 30px;
    background: #f9fafb;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.hrb-modal-footer .button {
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.hrb-modal-footer .button-primary {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border: none;
    color: white;
}

.hrb-modal-footer .button-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Enhanced FullCalendar Styling */
.fc-event {
    cursor: pointer;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    border: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.fc-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.fc-event-confirmed {
    background: linear-gradient(135deg, #10b981, #059669);
}

.fc-event-pending {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.fc-event-cancelled {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.fc-event-completed {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.fc-header-toolbar {
    margin-bottom: 20px;
}

.fc-button-primary {
    /* border: 2px solid #8b5cf6; */
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.fc-button-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

@media (max-width: 768px) {
    .hrb-page-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }

    .hrb-calendar-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .hrb-calendar-views,
    .hrb-calendar-legend {
        justify-content: center;
    }

    .hrb-stats-grid {
        grid-template-columns: 1fr;
    }

    .hrb-modal-content {
        width: 95%;
        margin: 20px;
    }

    .hrb-page-title h1 {
        font-size: 1.8em;
    }
}
</style>

<!-- FullCalendar CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
let calendar;
let currentRoomFilter = <?php echo $selected_room; ?>;

document.addEventListener('DOMContentLoaded', function() {
    initializeCalendar();
    loadCalendarStats();
});

function initializeCalendar() {
    const calendarEl = document.getElementById('hrb-calendar');

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },
        height: 'auto',
        events: function(info, successCallback, failureCallback) {
            fetchCalendarEvents(info.start, info.end, successCallback, failureCallback);
        },
        eventClick: function(info) {
            showBookingDetails(info.event.id);
        },
        eventClassNames: function(arg) {
            return ['fc-event-' + arg.event.extendedProps.status];
        },
        eventContent: function(arg) {
            return {
                html: '<div class="fc-event-content">' +
                      '<div class="fc-event-title">' + arg.event.title + '</div>' +
                      '<div class="fc-event-time">' + arg.timeText + '</div>' +
                      '</div>'
            };
        }
    });

    calendar.render();
}

function fetchCalendarEvents(start, end, successCallback, failureCallback) {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'hrb_get_calendar_events',
            start: start.toISOString().split('T')[0],
            end: end.toISOString().split('T')[0],
            room_id: currentRoomFilter,
            nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                successCallback(response.data);
            } else {
                failureCallback();
            }
        },
        error: function() {
            failureCallback();
        }
    });
}

function filterByRoom(roomId) {
    currentRoomFilter = parseInt(roomId);
    calendar.refetchEvents();

    // Update URL
    const url = new URL(window.location);
    if (roomId > 0) {
        url.searchParams.set('room_id', roomId);
    } else {
        url.searchParams.delete('room_id');
    }
    window.history.pushState({}, '', url);
}

// Calendar view buttons
document.querySelectorAll('.calendar-view-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.calendar-view-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        calendar.changeView(this.dataset.view);
    });
});

function showBookingDetails(bookingId) {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'hrb_get_booking_details',
            booking_id: bookingId,
            nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                document.getElementById('booking-modal-body').innerHTML = response.data.html;
                document.getElementById('edit-booking-btn').onclick = function() {
                    editBooking(bookingId);
                };
                document.getElementById('booking-modal').style.display = 'flex';
            }
        }
    });
}

function closeBookingModal() {
    document.getElementById('booking-modal').style.display = 'none';
}

function editBooking(bookingId) {
    window.location.href = '<?php echo admin_url('admin.php?page=hrb-bookings&action=edit&booking_id='); ?>' + bookingId;
}

function loadCalendarStats() {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'hrb_get_calendar_stats',
            room_id: currentRoomFilter,
            nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                const stats = response.data;
                document.getElementById('stats-today').textContent = stats.today || 0;
                document.getElementById('stats-week').textContent = stats.week || 0;
                document.getElementById('stats-month').textContent = stats.month || 0;
                document.getElementById('stats-revenue').textContent = '<?php echo hrb_get_currency_symbol(); ?>' + (stats.revenue || 0).toFixed(2);
            }
        }
    });
}

// Close modal when clicking outside
document.getElementById('booking-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeBookingModal();
    }
});
</script>