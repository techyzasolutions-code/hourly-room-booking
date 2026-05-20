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
        <?php if (current_user_can('hrb_manage_bookings')): ?>
        <div class="hrb-page-actions">
            <button type="button" class="button button-primary" onclick="location.href='<?php echo admin_url('admin.php?page=hrb-bookings&action=add'); ?>'">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Add New Booking', 'hourly-room-booking'); ?>
            </button>
        </div>
        <?php endif; ?>
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
                <span class="legend-color" style="background: linear-gradient(135deg, #10b981, #059669);"></span>
                <?php _e('Confirmed', 'hourly-room-booking'); ?>
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background: linear-gradient(135deg, #f59e0b, #d97706);"></span>
                <?php _e('Pending', 'hourly-room-booking'); ?>
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);"></span>
                <?php _e('Completed', 'hourly-room-booking'); ?>
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background: linear-gradient(135deg, #ef4444, #dc2626);"></span>
                <?php _e('Cancelled', 'hourly-room-booking'); ?>
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
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    padding: 32px;
    border-radius: 6px;
    margin-bottom: 32px;
    box-shadow: 0 8px 32px rgba(220, 38, 38, 0.15);
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
    border-radius: 4px;
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
    border-radius: 6px;
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
    border-radius: 4px;
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
    border-radius: 4px;
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
    border: 2px solid #b91c1c !important;
    background: linear-gradient(135deg, #b91c1c, #dc2626);
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
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

.hrb-calendar-container {
    background: white;
    border-radius: 6px;
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
    border-radius: 6px;
    padding: 24px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(239, 68, 68, 0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    flex-direction: column;
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
    border-radius: 6px;
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
    /* background: linear-gradient(135deg, #ef4444, #dc2626); */
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
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    cursor: pointer;
    color: #6b7280;
    background: white;
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
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
    border-radius: 4px;
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

/* ===== Booking details modal v2 ===== */
/* Booking reference subtitle inside calendar event cards */
.fc-event-ref {
    font-size: 10px;
    font-weight: 600;
    opacity: 0.8;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    letter-spacing: 0.3px;
}

.fc-event-ref .fc-event-icon {
    font-size: 10px;
}

.hrb-bd {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.hrb-bd-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 18px;
}

.hrb-bd-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px 12px;
    background: #f9fafb;
    border-radius: 4px;
    border: 1px solid #eef0f3;
    min-width: 0;
}

.hrb-bd-item-total {
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    border-color: #fecaca;
}

.hrb-bd-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.hrb-bd-label .bi {
    color: #dc2626;
    font-size: 13px;
}

.hrb-bd-value {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    word-break: break-word;
}

.hrb-bd-value small {
    display: block;
    font-weight: 400;
    color: #6b7280;
    font-size: 12px;
    margin-top: 2px;
}

.hrb-bd-amount {
    font-size: 18px;
    color: #dc2626;
}

/* Notes blocks */
.hrb-bd-note {
    border-radius: 4px;
    padding: 14px 16px;
    border: 1px solid;
}

.hrb-bd-note-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.hrb-bd-note-header .bi {
    font-size: 15px;
}

.hrb-bd-note-body {
    font-size: 13.5px;
    line-height: 1.5;
    color: #374151;
    white-space: pre-wrap;
}

.hrb-bd-note-customer {
    background: #fef3c7;
    border-color: #fde68a;
}
.hrb-bd-note-customer .hrb-bd-note-header { color: #92400e; }

.hrb-bd-note-admin {
    background: #fee2e2;
    border-color: #fecaca;
}
.hrb-bd-note-admin .hrb-bd-note-header { color: #991b1b; }

/* Loading state */
.hrb-bd-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    color: #6b7280;
}

.hrb-bd-spinner {
    width: 38px;
    height: 38px;
    border: 3px solid #e5e7eb;
    border-top-color: #dc2626;
    border-radius: 50%;
    animation: hrb-bd-spin 0.8s linear infinite;
    margin-bottom: 12px;
}

@keyframes hrb-bd-spin {
    to { transform: rotate(360deg); }
}

.hrb-bd-error {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px;
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    border-radius: 4px;
    font-weight: 500;
}

/* Stack to one column on smaller modals */
@media (max-width: 560px) {
    .hrb-bd-grid {
        grid-template-columns: 1fr;
    }
}

/* Enhanced FullCalendar Styling */
.fc-event {
    cursor: pointer;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    border: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    padding: 4px 6px;
    margin: 2px 0;
    min-height: 24px;
    overflow: visible;
    white-space: normal;
    word-wrap: break-word;
    line-height: 1.3;
    color: white !important;
}

.fc-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    z-index: 10;
    /* background: inherit !important; */
    color: white !important;
}

.fc-event:hover .fc-event-title,
.fc-event:hover .fc-event-time {
    color: white !important;
}

.fc-event-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
    width: 100%;
    overflow: visible;
}

.fc-event-title {
    font-weight: 600;
    font-size: 12px;
    line-height: 1.3;
    overflow: visible;
    white-space: normal;
    word-wrap: break-word;
    max-width: 100%;
    color: white !important;
}

.fc-event-time {
    font-size: 11px;
    opacity: 1;
    line-height: 1.2;
    overflow: visible;
    white-space: normal;
    word-wrap: break-word;
    color: white !important;
}

/* ===== Calendar event card layout ===== */
.fc-event-content {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 4px 2px;
    width: 100%;
}

.fc-event-row {
    display: flex;
    align-items: center;
    gap: 6px;
    line-height: 1.25;
    color: #fff !important;
    overflow: hidden;
}

.fc-event-row span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    min-width: 0;
}

.fc-event-customer {
    font-weight: 700;
    font-size: 12px;
    padding-bottom: 3px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.25);
}

.fc-event-room {
    font-weight: 500;
    font-size: 11px;
    opacity: 0.92;
}

.fc-event-row.fc-event-time {
    font-size: 11px;
    font-weight: 600;
    opacity: 0.95;
}

/* Override base FullCalendar title/time word-wrap rules for the new layout */
.fc-event .fc-event-title,
.fc-event .fc-event-time {
    white-space: normal;
    word-wrap: break-word;
}

/* Icons inline with text */
.fc-event-icon {
    font-size: 12px;
    line-height: 1;
    flex-shrink: 0;
    opacity: 0.95;
}

/* ===== Badges row (anonymous + status side by side) ===== */
.fc-event-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 2px;
}

/* Anonymous booking badge */
.fc-event-anon-badge {
    font-size: 9px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(0, 0, 0, 0.28);
    color: white !important;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid rgba(255, 255, 255, 0.45);
    line-height: 1.3;
}

.fc-event-anon-badge .bi {
    font-size: 10px;
    line-height: 1;
}

/* Icon inside status badge */
.fc-event-detail {
    display: inline-flex !important;
    align-items: center;
    gap: 4px;
    padding: 2px 6px !important;
    line-height: 1.3 !important;
}

.fc-event-detail .bi {
    font-size: 10px;
    line-height: 1;
}

/* Status badge styling */
.fc-event-detail {
    font-size: 9px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    margin-top: 2px;
    display: inline-block;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    line-height: 1.2;
    max-width: fit-content;
}

/* Status badge colors */
.fc-event-status-confirmed {
    background: linear-gradient(135deg, #10b981, #059669) !important;
    color: white !important;
}

.fc-event-status-pending {
    background: linear-gradient(135deg, #f59e0b, #d97706) !important;
    color: white !important;
}

.fc-event-status-cancelled {
    background: linear-gradient(135deg, #ef4444, #dc2626) !important;
    color: white !important;
}

.fc-event-status-completed {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8) !important;
    color: white !important;
}

/* Combined time + price row styling */
.fc-event-time-price {
    display: flex !important;
    gap: 8px;
    align-items: center;
    font-size: 11px;
    font-weight: 600;
    color: #fff !important;
    padding-top: 2px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.fc-event-time-part,
.fc-event-price-part {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
    min-width: 0;
}

.fc-event-time-part span,
.fc-event-price-part span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    min-width: 0;
}

.fc-event-price-part {
    font-weight: 700;
}

.fc-event-price-part .fc-event-icon {
    font-size: 11px;
}

/* Extras row styling */
.fc-event-extras {
    font-size: 10px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.9) !important;
    opacity: 0.9;
    display: flex;
    gap: 6px;
    align-items: flex-start;
}

.fc-event-extras .fc-event-icon {
    font-size: 11px;
    flex-shrink: 0;
    padding-top: 1px;
}

.fc-event-extras-text {
    flex: 1;
    white-space: normal;
    word-wrap: break-word;
    line-height: 1.3;
    overflow: visible;
}

.fc-event-status-no_show {
    background: linear-gradient(135deg, #8a8c8f, #6b7280) !important;
    color: white !important;
}

/* Month view specific styling */
.fc-daygrid-event {
    margin: 2px 0;
    border-radius: 4px;
    padding: 3px 5px;
    min-height: 22px;
}

.fc-daygrid-event .fc-event-title {
    font-size: 11px;
    line-height: 1.2;
    max-width: 100%;
    overflow: visible;
    white-space: normal;
    word-wrap: break-word;
    color: white !important;
}

.fc-daygrid-event .fc-event-time {
    font-size: 10px;
    line-height: 1.1;
    opacity: 1;
    white-space: normal;
    word-wrap: break-word;
    color: white !important;
    font-weight: bold;
}

.fc-daygrid-event .fc-event-detail {
    font-size: 8px;
    padding: 1px 4px;
    margin-top: 1px;
}

/* Week and Day view styling */
.fc-timegrid-event {
    border-radius: 4px;
    padding: 4px 6px;
    font-size: 12px;
    min-height: 20px;
}

.fc-timegrid-event .fc-event-title {
    font-size: 12px;
    line-height: 1.3;
    white-space: normal;
    word-wrap: break-word;
    color: white !important;
}

.fc-timegrid-event .fc-event-time {
    font-size: 11px;
    line-height: 1.2;
    white-space: normal;
    word-wrap: break-word;
    color: white !important;
}

.fc-timegrid-event .fc-event-detail {
    font-size: 9px;
    padding: 2px 5px;
    margin-top: 2px;
}

/* Calendar grid improvements */
.fc-daygrid-day-frame {
    min-height: 120px;
}

.fc-daygrid-day-events {
    margin: 0;
    padding: 0;
}

.fc-daygrid-event-harness {
    margin: 2px 0;
}

/* Better spacing for multiple events */
.fc-daygrid-day-events .fc-event {
    margin: 2px 0;
    max-width: 100%;
    min-height: 20px;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .fc-event-title {
        font-size: 10px;
    }
    
    .fc-event-time {
        font-size: 9px;
    }
    
    .fc-daygrid-event .fc-event-title {
        font-size: 10px;
    }
    
    .fc-daygrid-event .fc-event-time {
        font-size: 9px;
    }
}

/* Room color-based events - colors are set dynamically via backgroundColor */
.fc-event-room-color {
    /* Base styling for room color events */
    border-radius: 8px;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Force room colors to override any other styles */
.fc-event-room-color,
.fc-event-room-color:hover,
.fc-event-room-color.fc-event-mirror,
.fc-daygrid-event.fc-event-room-color,
.fc-daygrid-event.fc-event-room-color:hover,
.fc-daygrid-dot-event.fc-event-room-color,
.fc-daygrid-dot-event.fc-event-room-color:hover {
    /* Force room colors to take precedence over any other CSS */
    background: var(--room-color, #3498db) !important;
    background-color: var(--room-color, #3498db) !important;
    border-color: var(--room-color, #3498db) !important;
}

/* Override any FullCalendar default styles */
.fc-event.fc-event-room-color,
.fc-event.fc-event-room-color:hover,
.fc-event.fc-event-room-color:focus,
.fc-event.fc-event-room-color:active,
.fc-event.fc-event-room-color.fc-event-mirror,
.fc-daygrid-event.fc-event-room-color,
.fc-daygrid-event.fc-event-room-color:hover,
.fc-timegrid-event.fc-event-room-color,
.fc-timegrid-event.fc-event-room-color:hover {
    background: var(--room-color, #3498db) !important;
    background-color: var(--room-color, #3498db) !important;
    border-color: var(--room-color, #3498db) !important;
}

/* Nuclear option - override everything */
[class*="fc-event"][class*="room-color"] {
    background: var(--room-color, #3498db) !important;
    background-color: var(--room-color, #3498db) !important;
    border-color: var(--room-color, #3498db) !important;
}

/* Status-based styling for reference (kept for legend) */
.fc-event-no_show {
    background: #8a8c8f;
    color: #383d41;
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
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

.fc-header-toolbar {
    margin-bottom: 20px;
}

.fc-button-primary {
    /* border: 2px solid #b91c1c; */
    background: linear-gradient(135deg, #b91c1c, #dc2626);
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

/* Lock event styling */
.fc-event[data-type="room_lock"] {
    display: none;
    font-style: italic;
}

.fc-event[data-type="master_lock"] {
   
}

/* Removed black background override to allow room colors to show */
</style>

<!-- Bootstrap Icons (local) -->
<link href="<?php echo HRB_ASSETS_URL . 'vendor/bootstrap-icons/bootstrap-icons.min.css?ver=1.11.3'; ?>" rel="stylesheet">

<!-- FullCalendar CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/de.global.min.js"></script>
<script src="<?php echo plugin_dir_url(__FILE__) . '../assets/js/calendar-common.js'; ?>"></script>

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
        locale: 'de',
        dayHeaderFormat: { weekday: 'long' },
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },
        buttonText: {
            today: '<?php _e('Today', 'hourly-room-booking'); ?>'
        },
        height: 'auto',
        events: function(info, successCallback, failureCallback) {
            fetchCalendarEvents(info.start, info.end, successCallback, failureCallback);
        },
        eventDidMount: function(info) {
            // Set data-type attribute for events
            setEventDataType(info);
            
            // Apply room colors to booking events
            applyBookingColors(info);
            
            // Apply room colors to room lock events
            applyRoomColors(info, ['room_lock']);
        },
        eventClick: function(info) {
            // Don't show booking details for lock events
            if (info.event.extendedProps && info.event.extendedProps.type) {
                return;
            }
            showBookingDetails(info.event.id);
        },
        eventClassNames: function(arg) {
            return ['fc-event-room-color'];
        },
        eventContent: function(arg) {
            // Handle room lock events separately
            if (arg.event.extendedProps && arg.event.extendedProps.type === 'room_lock') {
                const title = arg.event.title;
                const startTime = arg.event.extendedProps.start_time;
                const endTime = arg.event.extendedProps.end_time;
                
                let timeText = '';
                if (startTime && endTime) {
                    // Format time range: "10:00 - 12:00"
                    timeText = startTime.substring(0, 5) + ' - ' + endTime.substring(0, 5);
                }
                
                return {
                    html: '<div class="fc-event-content">' +
                          '<div class="fc-event-title">' + title + '</div>' +
                          '<div class="fc-event-time">' + timeText + '</div>' +
                          '</div>'
                };
            }
            
            // Handle booking events
            // Extract status from title (format: "Customer - Room (Status)")
            let title = arg.event.title;
            let status = arg.event.extendedProps.status || '';

            // Fallback: parse from title if extendedProp missing
            if (!status) {
                const statusMatch = title.match(/\(([^)]+)\)$/);
                if (statusMatch) { status = statusMatch[1]; }
            }

            // Use clean fields from extendedProps when available
            const customerName = arg.event.extendedProps.customer_name || title.replace(/\s*\([^)]+\)$/, '');
            const roomName = arg.event.extendedProps.room_name || '';

            // Format time text to show proper AM/PM format
            let timeText = arg.timeText;
            if (timeText.includes(' - ')) {
                timeText = timeText.replace(' - ', '-');
            }
            // Convert time format from "8a" to "8 AM"
            timeText = timeText.replace(/(\d+)a/g, '$1 AM');
            timeText = timeText.replace(/(\d+)p/g, '$1 PM');
            
            // Create status badge with appropriate color class
            let statusBadge = '';
            let statusIcon = 'bi-tag-fill';
            if (status) {
                const statusClass = 'fc-event-status-' + status.toLowerCase();

                // Translate status to German and pick icon
                let translatedStatus = status;
                switch(status.toLowerCase()) {
                    case 'confirmed':
                        translatedStatus = '<?php _e('Confirmed', 'hourly-room-booking'); ?>';
                        statusIcon = 'bi-check-circle-fill';
                        break;
                    case 'pending':
                        translatedStatus = '<?php _e('Pending', 'hourly-room-booking'); ?>';
                        statusIcon = 'bi-hourglass-split';
                        break;
                    case 'cancelled':
                        translatedStatus = '<?php _e('Cancelled', 'hourly-room-booking'); ?>';
                        statusIcon = 'bi-x-circle-fill';
                        break;
                    case 'completed':
                        translatedStatus = '<?php _e('Completed', 'hourly-room-booking'); ?>';
                        statusIcon = 'bi-check2-all';
                        break;
                    case 'no_show':
                        translatedStatus = '<?php _e('No Show', 'hourly-room-booking'); ?>';
                        statusIcon = 'bi-person-x-fill';
                        break;
                }

                statusBadge = '<div class="fc-event-detail ' + statusClass + '"><i class="bi ' + statusIcon + '"></i><span>' + translatedStatus + '</span></div>';
            }

            let anonymousBadge = '';
            // Use loose-equality vs. truthy: PHP/JSON may serialise tinyint(1)
            // as the string "0", which is truthy in JS. == 1 is correct here.
            if (arg.event.extendedProps.is_anonymous == 1) {
                anonymousBadge = '<div class="fc-event-anon-badge"><i class="bi bi-incognito"></i><span><?php _e('Anonymous', 'hourly-room-booking'); ?></span></div>';
            }

            // Build customer + room rows (room only shown if present)
            let customerRow = '<div class="fc-event-row fc-event-customer"><i class="bi bi-person-fill fc-event-icon"></i><span>' + customerName + '</span></div>';
            let roomRow = roomName
                ? '<div class="fc-event-row fc-event-room"><i class="bi bi-door-closed-fill fc-event-icon"></i><span>' + roomName + '</span></div>'
                : '';

            const bookingRef = arg.event.extendedProps.booking_reference || '';
            let refRow = bookingRef
                ? '<div class="fc-event-row fc-event-ref"><i class="bi bi-hash fc-event-icon"></i><span>' + bookingRef + '</span></div>'
                : '';

            // Build combined time + price row
            const totalAmount = arg.event.extendedProps.total_amount || '0.00';
            let timeAndPriceRow = '<div class="fc-event-row fc-event-time-price">' +
                                    '<div class="fc-event-time-part"><i class="bi bi-clock-fill fc-event-icon"></i><span>' + timeText + '</span></div>' +
                                    '<div class="fc-event-price-part"><i class="bi bi-currency-euro fc-event-icon"></i><span>' + totalAmount + ' €</span></div>' +
                                    '</div>';

            // Build extras row (if any) - show all extras with wrapping
            const extras = arg.event.extendedProps.extras || [];
            let extrasRow = '';
            if (extras.length > 0) {
                const extrasText = extras.join(', ');
                extrasRow = '<div class="fc-event-row fc-event-extras"><i class="bi bi-plus-circle-fill fc-event-icon"></i><span class="fc-event-extras-text">' + extrasText + '</span></div>';
            }

            return {
                html: '<div class="fc-event-content">' +
                      customerRow +
                      roomRow +
                      refRow +
                      timeAndPriceRow +
                      extrasRow +
                      '<div class="fc-event-badges">' +
                          anonymousBadge +
                          statusBadge +
                      '</div>' +
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
    
    // Update stats with new room filter
    loadCalendarStats();

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
    const modalBody = document.getElementById('booking-modal-body');
    const modal = document.getElementById('booking-modal');

    // Open immediately with a loading skeleton — no wait on AJAX
    modalBody.innerHTML = '<div class="hrb-bd-loading">' +
        '<div class="hrb-bd-spinner"></div>' +
        '<p><?php echo esc_js(__('Loading booking details…', 'hourly-room-booking')); ?></p>' +
        '</div>';
    modal.style.display = 'flex';

    document.getElementById('edit-booking-btn').onclick = function() {
        editBooking(bookingId);
    };

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
                modalBody.innerHTML = response.data.html;
            } else {
                modalBody.innerHTML = '<div class="hrb-bd-error"><i class="bi bi-exclamation-triangle-fill"></i> ' +
                    '<?php echo esc_js(__('Could not load booking details.', 'hourly-room-booking')); ?>' +
                    '</div>';
            }
        },
        error: function() {
            modalBody.innerHTML = '<div class="hrb-bd-error"><i class="bi bi-exclamation-triangle-fill"></i> ' +
                '<?php echo esc_js(__('Network error. Please try again.', 'hourly-room-booking')); ?>' +
                '</div>';
        }
    });
}

function closeBookingModal() {
    // Hide the modal
    document.getElementById('booking-modal').style.display = 'none';
    
    // Clear the modal body content
    document.getElementById('booking-modal-body').innerHTML = '';
    
    // Reset modal title
    document.getElementById('booking-modal-title').textContent = '<?php _e('Booking Details', 'hourly-room-booking'); ?>';
    
    // Remove any event handlers from the edit button
    const editBtn = document.getElementById('edit-booking-btn');
    if (editBtn) {
        editBtn.onclick = null;
    }
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
            } else {
                /* removed debug console.error */
            }
        },
        error: function(xhr, status, error) {
            /* removed debug console.error */
            /* removed debug console.error */
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