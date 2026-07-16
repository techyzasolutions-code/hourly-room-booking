<?php
/**
 * Calendar View
 * Displays the admin calendar interface with FullCalendar integration
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if (isset($_POST['action']) && $_POST['action'] === 'room_availability_action') {
    $admin = HRB_Admin::getInstance();
    $admin->handle_room_availability_actions();
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
            <h1><?php _e('Room Availability Management', 'hourly-room-booking'); ?></h1>
            <p class="description"><?php _e('Manage room locks and view availability in a visual calendar format.', 'hourly-room-booking'); ?></p>
        </div>
        <div class="hrb-page-actions">
            <button type="button" class="page-title-action" id="hrb-add-room-lock">
                <?php _e('Lock Room', 'hourly-room-booking'); ?>
            </button>
            <button type="button" class="page-title-action" id="hrb-add-master-lock">
                <?php _e('Master Lock', 'hourly-room-booking'); ?>
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

       

        
    </div>

    <!-- Calendar Container -->
    <div id="lock-calendar" class="hrb-calendar-container"></div>

</div>

<!-- Room Lock Modal -->
<div id="room-lock-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h3 id="room-lock-modal-title"><?php _e('Lock Room', 'hourly-room-booking'); ?></h3>
            <span class="hrb-modal-close" onclick="closeRoomLockModal()">&times;</span>
        </div>
        <form id="room-lock-form" method="post">
            <?php wp_nonce_field('hrb_admin_nonce', '_wpnonce'); ?>
            <input type="hidden" name="action" value="room_availability_action">
            <input type="hidden" name="sub_action" value="lock_room">
            <input type="hidden" name="lock_id" id="room-lock-id" value="">
            
            <div class="hrb-modal-body">
                <div class="hrb-form-group">
                    <label for="room-lock-room"><?php _e('Room:', 'hourly-room-booking'); ?></label>
                    <select name="room_id" id="room-lock-room" required>
                        <option value=""><?php _e('Select Room', 'hourly-room-booking'); ?></option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room->id; ?>"><?php echo esc_html($room->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="hrb-form-group">
                    <label for="room-lock-start-datetime"><?php _e('Start Date & Time:', 'hourly-room-booking'); ?></label>
                    <input type="datetime-local" name="start_datetime" id="room-lock-start-datetime" required>
                    <small><?php _e('Select date and time when the lock should start.', 'hourly-room-booking'); ?></small>
                </div>
                
                <div class="hrb-form-group">
                    <label for="room-lock-end-datetime"><?php _e('End Date & Time:', 'hourly-room-booking'); ?></label>
                    <input type="datetime-local" name="end_datetime" id="room-lock-end-datetime" required>
                    <small><?php _e('Select date and time when the lock should end.', 'hourly-room-booking'); ?></small>
                </div>
                
                <div class="hrb-form-group">
                    <label for="room-lock-reason"><?php _e('Reason:', 'hourly-room-booking'); ?></label>
                    <textarea name="reason" id="room-lock-reason" rows="3" placeholder="<?php _e('Enter reason for locking this room...', 'hourly-room-booking'); ?>"></textarea>
                </div>
            </div>
            
            <div class="hrb-modal-footer">
                <button type="button" class="button" onclick="closeRoomLockModal()"><?php _e('Cancel', 'hourly-room-booking'); ?></button>
                <button type="submit" class="button button-primary" id="room-lock-submit"><?php _e('Lock Room', 'hourly-room-booking'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Master Lock Modal -->
<div id="master-lock-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h2 id="master-lock-modal-title"><?php _e('Master Lock', 'hourly-room-booking'); ?></h2>
            <span class="hrb-modal-close" onclick="closeMasterLockModal()">&times;</span>
        </div>
        <form id="master-lock-form" method="post">
            <?php wp_nonce_field('hrb_admin_nonce', '_wpnonce'); ?>
            <input type="hidden" name="action" value="room_availability_action">
            <input type="hidden" name="sub_action" value="master_lock">
            <input type="hidden" name="lock_id" id="master-lock-id" value="">
            
            <div class="hrb-modal-body">
                <div class="hrb-form-group">
                    <label for="master-lock-start-datetime"><?php _e('Start Date & Time:', 'hourly-room-booking'); ?></label>
                    <input type="datetime-local" name="start_datetime" id="master-lock-start-datetime" required>
                    <small><?php _e('Select date and time when the master lock should start.', 'hourly-room-booking'); ?></small>
                </div>
                
                <div class="hrb-form-group">
                    <label for="master-lock-end-datetime"><?php _e('End Date & Time:', 'hourly-room-booking'); ?></label>
                    <input type="datetime-local" name="end_datetime" id="master-lock-end-datetime" required>
                    <small><?php _e('Select date and time when the master lock should end.', 'hourly-room-booking'); ?></small>
                </div>
                
                <div class="hrb-form-group">
                    <label for="master-lock-reason"><?php _e('Reason:', 'hourly-room-booking'); ?></label>
                    <textarea name="reason" id="master-lock-reason" rows="3" placeholder="<?php _e('Enter reason for master lock...', 'hourly-room-booking'); ?>"></textarea>
                </div>
            </div>
            
            <div class="hrb-modal-footer">
                <button type="button" class="button" onclick="closeMasterLockModal()"><?php _e('Cancel', 'hourly-room-booking'); ?></button>
                <button type="submit" class="button button-primary" id="master-lock-submit"><?php _e('Master Lock', 'hourly-room-booking'); ?></button>
            </div>
        </form>
    </div>
</div>





<!-- FullCalendar CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<link href="<?php echo HRB_PLUGIN_URL; ?>admin/assets/css/calendar-common.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/de.global.min.js"></script>
<script src="<?php echo plugin_dir_url(__FILE__) . '../assets/js/calendar-common.js'; ?>"></script>

<script>
// Plugin settings for JavaScript
const hrbSettings = {
    dateFormat: '<?php echo esc_js(get_option('hrb_date_format', 'd.m.Y')); ?>',
    timeFormat: '<?php echo esc_js(get_option('hrb_time_format', 'H:i')); ?>',
    timezone: '<?php echo esc_js(get_option('hrb_timezone', 'Europe/Berlin')); ?>'
};

// Utility functions for formatting dates and times
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE');
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('de-DE', {hour: '2-digit', minute:'2-digit', hour12: false});
}

function formatDateTime(dateString) {
    return formatDate(dateString) + ' ' + formatTime(dateString);
}

let calendar;
let currentRoomFilter = <?php echo $selected_room; ?>;

document.addEventListener('DOMContentLoaded', function() {
    initializeCalendar();
});

function initializeCalendar() {
    const calendarEl = document.getElementById('lock-calendar');

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'de',
        dayHeaderFormat: window.innerWidth <= 782 ? { weekday: 'short' } : { weekday: 'long' },
        dayMaxEvents: window.innerWidth <= 782 ? 3 : false,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: {
            today: '<?php _e('Today', 'hourly-room-booking'); ?>',
            month: '<?php _e('Month', 'hourly-room-booking'); ?>',
            week: '<?php _e('Week', 'hourly-room-booking'); ?>',
            day: '<?php _e('Day', 'hourly-room-booking'); ?>',
            list: '<?php _e('List', 'hourly-room-booking'); ?>'
        },
        height: 'auto',
        events: function(info, successCallback, failureCallback) {
            fetchLockEvents(info.start, info.end, successCallback, failureCallback);
        },
        eventDidMount: function(info) {
            // Set data-type attribute for events
            setEventDataType(info);
            
            // Apply room colors to room lock events
            applyRoomColors(info, ['room_lock']);
        },
        eventClick: function(info) {
            // Show lock details for lock events
            if (info.event.extendedProps && info.event.extendedProps.type) {
                showLockDetails(info.event);
                return;
            }
            showBookingDetails(info.event.id);
        },
        eventClassNames: function(arg) {
            return ['fc-event-room-color'];
        },
        eventContent: function(arg) {
            // Custom content for room lock events to show time in fc-event-time
            if (arg.event.extendedProps && arg.event.extendedProps.type === 'room_lock') {
                const title = arg.event.title;
                const startDatetime = arg.event.extendedProps.start_datetime;
                const endDatetime = arg.event.extendedProps.end_datetime;
                
                let timeText = '';
                if (startDatetime && endDatetime) {
                    const startTime = formatTime(startDatetime);
                    const endTime = formatTime(endDatetime);
                    timeText = startTime + ' - ' + endTime;
                }
                
                return {
                    html: '<div class="fc-event-content">' +
                          '<div class="fc-event-title">' + title + '</div>' +
                          '<div class="fc-event-time">' + timeText + '</div>' +
                          '</div>'
                };
            }
            // Default content for other events
            return {
                html: '<div class="fc-event-content">' +
                      '<div class="fc-event-title">' + arg.event.title + '</div>' +
                      '</div>'
            };
        }
    });

    calendar.render();
}

function fetchLockEvents(start, end, successCallback, failureCallback) {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'hrb_get_lock_events',
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
    // Store the current room filter
    currentRoomFilter = parseInt(roomId);
    
    // Refresh the calendar to show filtered events
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

function showLockDetails(event) {
    var details = '';
    var actions = '';
    
    if (event.extendedProps.type === 'master_lock') {
        details = '<h3><?php _e('Master Lock', 'hourly-room-booking'); ?></h3>';
        details += '<p><strong><?php _e('Affects:', 'hourly-room-booking'); ?></strong> <?php _e('All rooms', 'hourly-room-booking'); ?></p>';
        actions = '<div style="margin-top: 15px;">';
        actions += '<button onclick="editMasterLock(' + event.extendedProps.lock_id + ')" class="button button-primary" style="margin-right: 10px;"><?php _e('Edit', 'hourly-room-booking'); ?></button>';
        actions += '<button onclick="deleteMasterLock(' + event.extendedProps.lock_id + ')" class="button button-secondary" style="background: #dc3545; color: white; border-color: #dc3545;"><?php _e('Delete', 'hourly-room-booking'); ?></button>';
        actions += '</div>';
    } else {
        details = '<h3><?php _e('Room Lock', 'hourly-room-booking'); ?></h3>';
        details += '<p><strong><?php _e('Room:', 'hourly-room-booking'); ?></strong> ' + event.extendedProps.room_name + '</p>';
        actions = '<div style="margin-top: 15px;">';
        actions += '<button onclick="editRoomLock(' + event.extendedProps.lock_id + ')" class="button button-primary" style="margin-right: 10px;"><?php _e('Edit', 'hourly-room-booking'); ?></button>';
        actions += '<button onclick="deleteRoomLock(' + event.extendedProps.lock_id + ')" class="button button-secondary" style="background: #dc3545; color: white; border-color: #dc3545;"><?php _e('Delete', 'hourly-room-booking'); ?></button>';
        actions += '</div>';
    }
    
    // Format datetime values properly using plugin settings
    if (event.extendedProps.start_datetime && event.extendedProps.end_datetime) {
        const startDateTime = formatDateTime(event.extendedProps.start_datetime);
        const endDateTime = formatDateTime(event.extendedProps.end_datetime);
        
        details += '<p><strong><?php _e('Start:', 'hourly-room-booking'); ?></strong> ' + startDateTime + '</p>';
        details += '<p><strong><?php _e('End:', 'hourly-room-booking'); ?></strong> ' + endDateTime + '</p>';
    } else {
        details += '<p><strong><?php _e('Start:', 'hourly-room-booking'); ?></strong> ' + formatDate(event.start) + '</p>';
        details += '<p><strong><?php _e('End:', 'hourly-room-booking'); ?></strong> ' + formatDate(event.end) + '</p>';
    }
    
    if (event.extendedProps.reason) {
        details += '<p><strong><?php _e('Reason:', 'hourly-room-booking'); ?></strong> ' + event.extendedProps.reason + '</p>';
    }
    
    // Create a modal instead of alert
    showLockDetailsModal(details + actions, event.extendedProps);
}

function showLockDetailsModal(content, lockData) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('lock-details-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'lock-details-modal';
        modal.className = 'hrb-modal';
        modal.style.display = 'none';
        modal.innerHTML = `
            <div class="hrb-modal-content">
                <div class="hrb-modal-header">
                    <h2><?php _e('Lock Details', 'hourly-room-booking'); ?></h2>
                    <span class="hrb-modal-close" onclick="closeLockDetailsModal()">&times;</span>
                </div>
                <div class="hrb-modal-body" id="lock-details-content">
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Add click outside to close
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeLockDetailsModal();
            }
        });
    }
    
    document.getElementById('lock-details-content').innerHTML = content;
    modal.style.display = 'flex';
    
    // Store lock data for edit/delete functions
    window.currentLockData = lockData;
}

function closeLockDetailsModal() {
    const modal = document.getElementById('lock-details-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function editRoomLock(lockId) {
    closeLockDetailsModal();
    
    // Show modal first (in edit mode)
    showRoomLockModal(true);
    
    // Then populate form with current data
    const lockData = window.currentLockData;
    document.getElementById('room-lock-id').value = lockId;
    document.getElementById('room-lock-room').value = lockData.room_id;
    
    // Use the datetime values directly (format: YYYY-MM-DD HH:MM:SS)
    const startDateTime = lockData.start_datetime.replace(' ', 'T').substring(0, 16);
    const endDateTime = lockData.end_datetime.replace(' ', 'T').substring(0, 16);
    
    document.getElementById('room-lock-start-datetime').value = startDateTime;
    document.getElementById('room-lock-end-datetime').value = endDateTime;
    document.getElementById('room-lock-reason').value = lockData.reason || '';
    
    // Update form action and title
    document.querySelector('#room-lock-form input[name="sub_action"]').value = 'edit_room_lock';
    document.getElementById('room-lock-modal-title').textContent = '<?php _e('Edit Room Lock', 'hourly-room-booking'); ?>';
    document.getElementById('room-lock-submit').textContent = '<?php _e('Update Lock', 'hourly-room-booking'); ?>';
}

function editMasterLock(lockId) {
    closeLockDetailsModal();
    
    // Show modal first (in edit mode)
    showMasterLockModal(true);
    
    // Then populate form with current data
    const lockData = window.currentLockData;
    document.getElementById('master-lock-id').value = lockId;
    
    // Use the datetime values directly (format: YYYY-MM-DD HH:MM:SS)
    const startDateTime = lockData.start_datetime.replace(' ', 'T').substring(0, 16);
    const endDateTime = lockData.end_datetime.replace(' ', 'T').substring(0, 16);
    
    document.getElementById('master-lock-start-datetime').value = startDateTime;
    document.getElementById('master-lock-end-datetime').value = endDateTime;
    document.getElementById('master-lock-reason').value = lockData.reason || '';
    
    // Update form action and title
    document.querySelector('#master-lock-form input[name="sub_action"]').value = 'edit_master_lock';
    document.getElementById('master-lock-modal-title').textContent = '<?php _e('Edit Master Lock', 'hourly-room-booking'); ?>';
    document.getElementById('master-lock-submit').textContent = '<?php _e('Update Lock', 'hourly-room-booking'); ?>';
}

function deleteRoomLock(lockId) {
    // Use custom alert dialog with danger type
    window.hrbShowAlertDialog(
        <?php echo json_encode(__('Are you sure you want to delete this room lock?', 'hourly-room-booking')); ?>,
        {
            warningMessage: <?php echo json_encode(__('This action cannot be undone.', 'hourly-room-booking')); ?>,
            title: <?php echo json_encode(__('Delete Room Lock', 'hourly-room-booking')); ?>,
            confirmText: <?php echo json_encode(__('Delete', 'hourly-room-booking')); ?>,
            cancelText: <?php echo json_encode(__('Cancel', 'hourly-room-booking')); ?>,
            type: 'danger'
        },
        function() {
            // User confirmed - proceed with deletion
            const formData = new FormData();
            formData.append('action', 'room_availability_action');
            formData.append('sub_action', 'unlock_room');
            formData.append('lock_id', lockId);
            formData.append('_wpnonce', '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                if (html.includes('notice-success')) {
                    showSuccessMessage('<?php _e('Room lock deleted successfully!', 'hourly-room-booking'); ?>');
                    closeLockDetailsModal();
                    calendar.refetchEvents();
                } else {
                    showErrorMessage('<?php _e('Failed to delete room lock.', 'hourly-room-booking'); ?>');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('<?php _e('An error occurred while deleting the lock.', 'hourly-room-booking'); ?>');
            });
        }
    );
}

function deleteMasterLock(lockId) {
    // Use custom alert dialog with danger type
    window.hrbShowAlertDialog(
        <?php echo json_encode(__('Are you sure you want to delete this master lock?', 'hourly-room-booking')); ?>,
        {
            warningMessage: <?php echo json_encode(__('This action cannot be undone.', 'hourly-room-booking')); ?>,
            title: <?php echo json_encode(__('Delete Master Lock', 'hourly-room-booking')); ?>,
            confirmText: <?php echo json_encode(__('Delete', 'hourly-room-booking')); ?>,
            cancelText: <?php echo json_encode(__('Cancel', 'hourly-room-booking')); ?>,
            type: 'danger'
        },
        function() {
            // User confirmed - proceed with deletion
            const formData = new FormData();
            formData.append('action', 'room_availability_action');
            formData.append('sub_action', 'master_unlock');
            formData.append('lock_id', lockId);
            formData.append('_wpnonce', '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                if (html.includes('notice-success')) {
                    showSuccessMessage('<?php _e('Master lock deleted successfully!', 'hourly-room-booking'); ?>');
                    closeLockDetailsModal();
                    calendar.refetchEvents();
                } else {
                    showErrorMessage('<?php _e('Failed to delete master lock.', 'hourly-room-booking'); ?>');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('<?php _e('An error occurred while deleting the lock.', 'hourly-room-booking'); ?>');
            });
        }
    );
}

// Modal functions
function showRoomLockModal(isEdit = false) {
    document.getElementById('room-lock-modal').style.display = 'flex';
    
    if (!isEdit) {
        document.getElementById('room-lock-form').reset();
        document.getElementById('room-lock-id').value = '';
        document.getElementById('room-lock-modal-title').textContent = '<?php _e('Lock Room', 'hourly-room-booking'); ?>';
        document.getElementById('room-lock-submit').textContent = '<?php _e('Lock Room', 'hourly-room-booking'); ?>';
        document.querySelector('#room-lock-form input[name="sub_action"]').value = 'lock_room';
    }
}

function closeRoomLockModal() {
    document.getElementById('room-lock-modal').style.display = 'none';
}

function showMasterLockModal(isEdit = false) {
    document.getElementById('master-lock-modal').style.display = 'flex';
    
    if (!isEdit) {
        document.getElementById('master-lock-form').reset();
        document.getElementById('master-lock-id').value = '';
        document.getElementById('master-lock-modal-title').textContent = '<?php _e('Master Lock', 'hourly-room-booking'); ?>';
        document.getElementById('master-lock-submit').textContent = '<?php _e('Master Lock', 'hourly-room-booking'); ?>';
        document.querySelector('#master-lock-form input[name="sub_action"]').value = 'master_lock';
    }
}

function closeMasterLockModal() {
    document.getElementById('master-lock-modal').style.display = 'none';
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Room lock button
    document.getElementById('hrb-add-room-lock').addEventListener('click', showRoomLockModal);
    
    // Master lock button
    document.getElementById('hrb-add-master-lock').addEventListener('click', showMasterLockModal);
    
    // Close modals when clicking outside
    document.getElementById('room-lock-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRoomLockModal();
        }
    });
    
    document.getElementById('master-lock-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeMasterLockModal();
        }
    });
    
    // Calendar view buttons
    document.querySelectorAll('.calendar-view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.calendar-view-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            calendar.changeView(this.dataset.view);
        });
    });
    
    // Handle form submissions
    document.getElementById('room-lock-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitRoomLockForm();
    });
    
    document.getElementById('master-lock-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitMasterLockForm();
    });
});

function submitRoomLockForm() {
    const form = document.getElementById('room-lock-form');
    const formData = new FormData(form);
    
    // Show loading state
    const submitBtn = document.getElementById('room-lock-submit');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '<?php _e('Creating Lock...', 'hourly-room-booking'); ?>';
    submitBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Check if response contains success message
        if (html.includes('notice-success')) {
            const isEdit = document.querySelector('#room-lock-form input[name="sub_action"]').value === 'edit_room_lock';
            const message = isEdit ? '<?php _e('Room lock updated successfully!', 'hourly-room-booking'); ?>' : '<?php _e('Room locked successfully!', 'hourly-room-booking'); ?>';
            showSuccessMessage(message);
            closeRoomLockModal();
            calendar.refetchEvents(); // Refresh calendar
        } else if (html.includes('notice-error')) {
            showErrorMessage('<?php _e('Failed to lock room. Please try again.', 'hourly-room-booking'); ?>');
        } else {
            showErrorMessage('<?php _e('An error occurred. Please try again.', 'hourly-room-booking'); ?>');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('<?php _e('An error occurred. Please try again.', 'hourly-room-booking'); ?>');
    })
    .finally(() => {
        // Reset button state
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

function submitMasterLockForm() {
    const form = document.getElementById('master-lock-form');
    const formData = new FormData(form);
    
    // Show loading state
    const submitBtn = document.getElementById('master-lock-submit');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = '<?php _e('Creating Master Lock...', 'hourly-room-booking'); ?>';
    submitBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Check if response contains success message
        if (html.includes('notice-success')) {
            const isEdit = document.querySelector('#master-lock-form input[name="sub_action"]').value === 'edit_master_lock';
            const message = isEdit ? '<?php _e('Master lock updated successfully!', 'hourly-room-booking'); ?>' : '<?php _e('Master lock applied successfully!', 'hourly-room-booking'); ?>';
            showSuccessMessage(message);
            closeMasterLockModal();
            calendar.refetchEvents(); // Refresh calendar
        } else if (html.includes('notice-error')) {
            showErrorMessage('<?php _e('Failed to apply master lock. Please try again.', 'hourly-room-booking'); ?>');
        } else {
            showErrorMessage('<?php _e('An error occurred. Please try again.', 'hourly-room-booking'); ?>');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('<?php _e('An error occurred. Please try again.', 'hourly-room-booking'); ?>');
    })
    .finally(() => {
        // Reset button state
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

function showSuccessMessage(message) {
    showMessage(message, 'success');
}

function showErrorMessage(message) {
    showMessage(message, 'error');
}

function showMessage(message, type) {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.hrb-message');
    existingMessages.forEach(msg => msg.remove());
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `hrb-message notice notice-${type}`;
    messageDiv.style.cssText = `
        position: fixed;
        top: 32px;
        right: 20px;
        z-index: 1000000;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        max-width: 400px;
        animation: slideInRight 0.3s ease;
    `;
    
    if (type === 'success') {
        messageDiv.style.backgroundColor = '#d4edda';
        messageDiv.style.color = '#155724';
        messageDiv.style.border = '1px solid #c3e6cb';
    } else {
        messageDiv.style.backgroundColor = '#f8d7da';
        messageDiv.style.color = '#721c24';
        messageDiv.style.border = '1px solid #f5c6cb';
    }
    
    messageDiv.innerHTML = `
        <p style="margin: 0; font-weight: 500;">${message}</p>
        <button onclick="this.parentElement.remove()" style="
            position: absolute;
            top: 8px;
            right: 8px;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        ">&times;</button>
    `;
    
    document.body.appendChild(messageDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentElement) {
            messageDiv.remove();
        }
    }, 5000);
}

</script>