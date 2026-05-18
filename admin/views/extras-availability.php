<?php
/**
 * Extras Availability Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if (isset($_POST['action']) && $_POST['action'] === 'extras_availability_action') {
    $admin = HRB_Admin::getInstance();
    $admin->handle_extras_availability_actions();
}

// Ensure extra locks tables exist
$admin = HRB_Admin::getInstance();
$admin->ensure_extra_locks_tables();

$extras_manager = HRB_Extras::getInstance();
$extras = $extras_manager->get_extras('all');

// Get selected extra from URL parameter
$selected_extra = isset($_GET['extra_id']) ? intval($_GET['extra_id']) : 0;
?>

<div class="hrb-admin-page">
    <div class="hrb-page-header">
        <div class="hrb-page-title">
            <h1><?php _e('Extras Availability Management', 'hourly-room-booking'); ?></h1>
            <p class="description"><?php _e('Manage extra locks and view availability in a visual calendar format.', 'hourly-room-booking'); ?></p>
        </div>
        <div class="hrb-page-actions">
            <button type="button" class="page-title-action" id="hrb-add-extra-lock">
                <?php _e('Lock Extra', 'hourly-room-booking'); ?>
            </button>
            <button type="button" class="page-title-action" id="hrb-add-master-extra-lock">
                <?php _e('Master Lock', 'hourly-room-booking'); ?>
            </button>
        </div>
    </div>

    <!-- Calendar Controls -->
    <div class="hrb-calendar-controls">
        <div class="hrb-room-filter">
            <label for="extra-filter"><?php _e('Filter by Extra:', 'hourly-room-booking'); ?></label>
            <select id="extra-filter" onchange="filterByExtra(this.value)">
                <option value="0" <?php selected($selected_extra, 0); ?>><?php _e('All Extras', 'hourly-room-booking'); ?></option>
                <?php foreach ($extras as $extra): ?>
                    <option value="<?php echo $extra->id; ?>" <?php selected($selected_extra, $extra->id); ?>>
                        <?php echo esc_html($extra->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Calendar View -->
    <div class="hrb-calendar-container">
        <div id="extras-lock-calendar"></div>
    </div>

</div>

<!-- FullCalendar CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<link href="<?php echo HRB_PLUGIN_URL; ?>admin/assets/css/calendar-common.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/de.global.min.js"></script>
<script src="<?php echo plugin_dir_url(__FILE__) . '../assets/js/calendar-common.js'; ?>"></script>

<!-- Extra Lock Modal -->
<div id="hrb-extra-lock-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-overlay"></div>
    <div class="hrb-modal-content">
        <div class="hrb-modal-header" style="padding: 10px 30px;">
            <h3 id="extra-lock-modal-title"><?php _e('Lock Extra', 'hourly-room-booking'); ?></h3>
            <button type="button" class="hrb-modal-close">&times;</button>
        </div>
        <form method="POST" class="hrb-extra-lock-form" id="extra-lock-form">
            <?php wp_nonce_field('hrb_admin_action', 'hrb_nonce'); ?>
            <input type="hidden" name="action" value="extras_availability_action">
            <input type="hidden" name="sub_action" value="lock_extra">
            <input type="hidden" name="lock_id" id="extra-lock-id" value="">
            <div class="hrb-modal-body">
                <div class="hrb-form-group">
                    <label for="extra_id"><?php _e('Extra', 'hourly-room-booking'); ?></label>
                    <select name="extra_id" id="extra_id">
                        <option value=""><?php _e('Select an extra', 'hourly-room-booking'); ?></option>
                        <?php foreach ($extras as $extra): ?>
                            <option value="<?php echo $extra->id; ?>">
                                <?php echo esc_html($extra->name); ?>
                                <?php if (!$extra->is_active): ?>
                                    (<?php _e('Inactive', 'hourly-room-booking'); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hrb-field-error" id="extra_id_error" style="display: none;"></div>
                </div>
                
                <div class="hrb-form-group">
                    <label for="start_datetime"><?php _e('Start Date & Time:', 'hourly-room-booking'); ?></label>
                    <input type="datetime-local" name="start_datetime" id="extra-lock-start-datetime">
                    <div class="hrb-field-error" id="extra-lock-start-datetime_error" style="display: none;"></div>
                </div>
                
                <div class="hrb-form-group">
                    <label for="end_datetime"><?php _e('End Date & Time:', 'hourly-room-booking'); ?></label>
                    <input type="datetime-local" name="end_datetime" id="extra-lock-end-datetime">
                    <div class="hrb-field-error" id="extra-lock-end-datetime_error" style="display: none;"></div>
                </div>
                
                <div class="hrb-form-group">
                    <label for="reason"><?php _e('Reason:', 'hourly-room-booking'); ?></label>
                    <textarea name="reason" id="reason" rows="3" placeholder="<?php _e('Enter reason for locking this extra...', 'hourly-room-booking'); ?>"></textarea>
                </div>
            </div>
            <div class="hrb-modal-footer">
                <button type="button" class="button hrb-modal-cancel"><?php _e('Cancel', 'hourly-room-booking'); ?></button>
                <button type="submit" id="extra-lock-submit" class="button button-primary"><?php _e('Lock Extra', 'hourly-room-booking'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Master Extra Lock Modal -->
<div id="hrb-master-extra-lock-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-overlay"></div>
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h3 id="master-extra-lock-modal-title"><?php _e('Master Lock (All Extras)', 'hourly-room-booking'); ?></h3>
            <button type="button" class="hrb-modal-close">&times;</button>
        </div>
        <form method="POST" class="hrb-master-extra-lock-form" id="master-extra-lock-form">
            <?php wp_nonce_field('hrb_admin_action', 'hrb_nonce'); ?>
            <input type="hidden" name="action" value="extras_availability_action">
            <input type="hidden" name="sub_action" value="master_lock_extras">
            <input type="hidden" name="lock_id" id="master-extra-lock-id" value="">
            <div class="hrb-modal-body">
                <div class="hrb-form-group">
                    <label for="start_datetime"><?php _e('Start Date & Time:', 'hourly-room-booking'); ?></label>
                    <input type="datetime-local" name="start_datetime" id="master-extra-lock-start-datetime">
                    <div class="hrb-field-error" id="master-extra-lock-start-datetime_error" style="display: none;"></div>
                </div>
                
                <div class="hrb-form-group">
                    <label for="end_datetime"><?php _e('End Date & Time:', 'hourly-room-booking'); ?></label>
                    <input type="datetime-local" name="end_datetime" id="master-extra-lock-end-datetime">
                    <div class="hrb-field-error" id="master-extra-lock-end-datetime_error" style="display: none;"></div>
                </div>
                
                <div class="hrb-form-group">
                    <label for="master_reason"><?php _e('Reason:', 'hourly-room-booking'); ?></label>
                    <textarea name="reason" id="master_reason" rows="3" placeholder="<?php _e('Enter reason for locking all extras...', 'hourly-room-booking'); ?>"></textarea>
                </div>
                
                <div class="hrb-warning-notice">
                    <p><strong><?php _e('Warning:', 'hourly-room-booking'); ?></strong> <?php _e('This will lock ALL extras for the specified period. Existing bookings will not be affected, but no new extras can be added to bookings.', 'hourly-room-booking'); ?></p>
                </div>
            </div>
            <div class="hrb-modal-footer">
                <button type="button" class="button hrb-modal-cancel"><?php _e('Cancel', 'hourly-room-booking'); ?></button>
                <button type="submit" id="master-extra-lock-submit" class="button button-primary"><?php _e('Apply Master Lock', 'hourly-room-booking'); ?></button>
            </div>
        </form>
    </div>
</div>


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

let extrasLockCalendar;
let currentExtraFilter = <?php echo isset($_GET['extra_id']) ? intval($_GET['extra_id']) : 0; ?>;

// Initialize calendar
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to clear errors when user starts typing
    document.querySelectorAll('#extra_id, #extra-lock-start-datetime, #extra-lock-end-datetime, #master-extra-lock-start-datetime, #master-extra-lock-end-datetime').forEach(field => {
        field.addEventListener('change', function() {
            clearFieldError(this.id);
        });
    });
    
    initializeExtrasLockCalendar();
});

function initializeExtrasLockCalendar() {
    var calendarEl = document.getElementById('extras-lock-calendar');
    
    extrasLockCalendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'de',
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
        editable: false,
        events: function(info, successCallback, failureCallback) {
            fetchExtrasLockEvents(info.start, info.end, successCallback, failureCallback);
        },
        eventDidMount: function(info) {
            // Set data-type attribute for events
            setEventDataType(info);
            
            // Apply colors to extra lock events (using default gray color)
            applyRoomColors(info, ['extra_lock', 'master_extra_lock']);
        },
        eventClick: function(info) {
            showExtrasLockDetails(info.event);
        },
        eventContent: function(arg) {
            if (arg.event.extendedProps && arg.event.extendedProps.type === 'extra_lock') {
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
            } else if (arg.event.extendedProps && arg.event.extendedProps.type === 'master_extra_lock') {
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
            return null; // Use default rendering for other events
        }
    });
    
    extrasLockCalendar.render();
}

function fetchExtrasLockEvents(start, end, successCallback, failureCallback) {
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        data: {
            action: 'hrb_get_extras_lock_events',
            start: start.toISOString().split('T')[0],
            end: end.toISOString().split('T')[0],
            extra_id: currentExtraFilter,
            nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                successCallback(response.data);
            } else {
                failureCallback(response.data);
            }
        },
        error: function(xhr, status, error) {
            failureCallback('Error loading extra lock events');
        }
    });
}

function filterByExtra(extraId) {
    currentExtraFilter = extraId;
    extrasLockCalendar.refetchEvents();
    
    // Update URL
    var url = new URL(window.location);
    if (extraId) {
        url.searchParams.set('extra_id', extraId);
    } else {
        url.searchParams.delete('extra_id');
    }
    window.history.pushState({}, '', url);
}

function showExtrasLockDetails(event) {
    var details = '';
    var actions = '';
    
    if (event.extendedProps.type === 'master_extra_lock') {
        details = '<h3><?php _e('Master Lock', 'hourly-room-booking'); ?></h3>';
        details += '<p><strong><?php _e('Affects:', 'hourly-room-booking'); ?></strong> <?php _e('All extras', 'hourly-room-booking'); ?></p>';
        actions = '<div style="margin-top: 15px;">';
        actions += '<button onclick="editMasterExtraLock(' + event.extendedProps.lock_id + ')" class="button button-primary" style="margin-right: 10px;"><?php _e('Edit', 'hourly-room-booking'); ?></button>';
        actions += '<button onclick="deleteMasterExtraLock(' + event.extendedProps.lock_id + ')" class="button button-secondary" style="background: #dc3545; color: white; border-color: #dc3545;"><?php _e('Delete', 'hourly-room-booking'); ?></button>';
        actions += '</div>';
    } else {
        details = '<h3><?php _e('Extra Lock', 'hourly-room-booking'); ?></h3>';
        details += '<p><strong><?php _e('Extra:', 'hourly-room-booking'); ?></strong> ' + event.extendedProps.extra_name + '</p>';
        actions = '<div style="margin-top: 15px;">';
        actions += '<button onclick="editExtraLock(' + event.extendedProps.lock_id + ')" class="button button-primary" style="margin-right: 10px;"><?php _e('Edit', 'hourly-room-booking'); ?></button>';
        actions += '<button onclick="deleteExtraLock(' + event.extendedProps.lock_id + ')" class="button button-secondary" style="background: #dc3545; color: white; border-color: #dc3545;"><?php _e('Delete', 'hourly-room-booking'); ?></button>';
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
    showExtrasLockDetailsModal(details + actions, event.extendedProps);
}

function showExtrasLockDetailsModal(content, lockData) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('extras-lock-details-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'extras-lock-details-modal';
        modal.className = 'hrb-modal';
        modal.innerHTML = `
            <div class="hrb-modal-overlay" onclick="closeExtrasLockDetailsModal()"></div>
            <div class="hrb-modal-content">
                <div class="hrb-modal-header">
                    <h3><?php _e('Lock Details', 'hourly-room-booking'); ?></h3>
                    <button type="button" class="hrb-modal-close" onclick="closeExtrasLockDetailsModal()">&times;</button>
                </div>
                <div class="hrb-modal-body" id="extras-lock-details-content"></div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    document.getElementById('extras-lock-details-content').innerHTML = content;
    modal.style.display = 'flex';
    
    // Store lock data for edit/delete functions
    window.currentLockData = lockData;
}

function closeExtrasLockDetailsModal() {
    document.getElementById('extras-lock-details-modal').style.display = 'none';
}

// Calendar view buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.calendar-view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.dataset.view;
            extrasLockCalendar.changeView(view);
        });
    });
});

// Modal functions
function showExtraLockModal(isEdit = false) {
    if (!isEdit) {
        document.getElementById('hrb-extra-lock-modal').querySelector('form').reset();
    }
    document.getElementById('hrb-extra-lock-modal').style.display = 'flex';
}

function closeExtraLockModal() {
    document.getElementById('hrb-extra-lock-modal').style.display = 'none';
}

function showMasterExtraLockModal(isEdit = false) {
    if (!isEdit) {
        document.getElementById('hrb-master-extra-lock-modal').querySelector('form').reset();
    }
    document.getElementById('hrb-master-extra-lock-modal').style.display = 'flex';
}

function closeMasterExtraLockModal() {
    document.getElementById('hrb-master-extra-lock-modal').style.display = 'none';
}

// Form submission
function submitExtraLockForm() {
    const form = document.querySelector('.hrb-extra-lock-form');
    
    // Clear previous errors
    clearFieldErrors();
    
    // JavaScript validation
    const extraId = form.querySelector('#extra_id').value;
    const startDatetime = form.querySelector('#extra-lock-start-datetime').value;
    const endDatetime = form.querySelector('#extra-lock-end-datetime').value;
    
    let hasErrors = false;
    
    if (!extraId) {
        showFieldError('extra_id', '<?php _e('Please select an extra.', 'hourly-room-booking'); ?>');
        hasErrors = true;
    }
    
    if (!startDatetime) {
        showFieldError('extra-lock-start-datetime', '<?php _e('Please select a start date and time.', 'hourly-room-booking'); ?>');
        hasErrors = true;
    }
    
    if (!endDatetime) {
        showFieldError('extra-lock-end-datetime', '<?php _e('Please select an end date and time.', 'hourly-room-booking'); ?>');
        hasErrors = true;
    }
    
    // Check if end datetime is after start datetime
    if (startDatetime && endDatetime && new Date(endDatetime) <= new Date(startDatetime)) {
        showFieldError('extra-lock-end-datetime', '<?php _e('End date and time must be after start date and time.', 'hourly-room-booking'); ?>');
        hasErrors = true;
    }
    
    if (hasErrors) {
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'extras_availability_action');
    
    // Debug: Log what's being sent
    console.log('Extra lock form submission - sub_action:', form.querySelector('input[name="sub_action"]').value);
    console.log('Extra lock form submission - lock_id:', form.querySelector('#extra-lock-id').value);
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    const isEdit = form.querySelector('#extra-lock-id').value !== '';
    submitBtn.textContent = isEdit ? '<?php _e('Updating...', 'hourly-room-booking'); ?>' : '<?php _e('Creating...', 'hourly-room-booking'); ?>';
    submitBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const notice = doc.querySelector('.notice-success');
        
        if (notice) {
            const message = isEdit ? '<?php _e('Extra lock updated successfully!', 'hourly-room-booking'); ?>' : '<?php _e('Extra lock created successfully!', 'hourly-room-booking'); ?>';
            showSuccessMessage(message);
            closeExtraLockModal();
            extrasLockCalendar.refetchEvents();
        } else {
            const errorNotice = doc.querySelector('.notice-error');
            showErrorMessage(errorNotice ? errorNotice.textContent.trim() : '<?php _e('Error creating extra lock', 'hourly-room-booking'); ?>');
        }
    })
    .catch(error => {
        showErrorMessage('<?php _e('Error creating extra lock', 'hourly-room-booking'); ?>');
    })
    .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

function submitMasterExtraLockForm() {
    const form = document.querySelector('.hrb-master-extra-lock-form');
    
    // Clear previous errors
    clearFieldErrors();
    
    // JavaScript validation
    const startDatetime = form.querySelector('#master-extra-lock-start-datetime').value;
    const endDatetime = form.querySelector('#master-extra-lock-end-datetime').value;
    
    let hasErrors = false;
    
    if (!startDatetime) {
        showFieldError('master-extra-lock-start-datetime', '<?php _e('Please select a start date and time.', 'hourly-room-booking'); ?>');
        hasErrors = true;
    }
    
    if (!endDatetime) {
        showFieldError('master-extra-lock-end-datetime', '<?php _e('Please select an end date and time.', 'hourly-room-booking'); ?>');
        hasErrors = true;
    }
    
    // Check if end datetime is after start datetime
    if (startDatetime && endDatetime && new Date(endDatetime) <= new Date(startDatetime)) {
        showFieldError('master-extra-lock-end-datetime', '<?php _e('End date and time must be after start date and time.', 'hourly-room-booking'); ?>');
        hasErrors = true;
    }
    
    if (hasErrors) {
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'extras_availability_action');
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    const isEdit = form.querySelector('#master-extra-lock-id').value !== '';
    submitBtn.textContent = isEdit ? '<?php _e('Updating...', 'hourly-room-booking'); ?>' : '<?php _e('Creating...', 'hourly-room-booking'); ?>';
    submitBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const notice = doc.querySelector('.notice-success');
        
        if (notice) {
            const message = isEdit ? '<?php _e('Master extra lock updated successfully!', 'hourly-room-booking'); ?>' : '<?php _e('Master extra lock created successfully!', 'hourly-room-booking'); ?>';
            showSuccessMessage(message);
            closeMasterExtraLockModal();
            extrasLockCalendar.refetchEvents();
        } else {
            const errorNotice = doc.querySelector('.notice-error');
            showErrorMessage(errorNotice ? errorNotice.textContent.trim() : '<?php _e('Error creating master lock', 'hourly-room-booking'); ?>');
        }
    })
    .catch(error => {
        showErrorMessage('<?php _e('Error creating master lock', 'hourly-room-booking'); ?>');
    })
    .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

// Field validation helpers
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorDiv = document.getElementById(fieldId + '_error');
    
    if (field && errorDiv) {
        field.classList.add('error');
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }
}

function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    const errorDiv = document.getElementById(fieldId + '_error');
    
    if (field && errorDiv) {
        field.classList.remove('error');
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
    }
}

function clearFieldErrors() {
    // Clear all field errors
    const errorDivs = document.querySelectorAll('.hrb-field-error');
    errorDivs.forEach(div => {
        div.style.display = 'none';
        div.textContent = '';
    });
    
    // Remove error classes from inputs
    const errorFields = document.querySelectorAll('.hrb-form-group input.error, .hrb-form-group select.error, .hrb-form-group textarea.error');
    errorFields.forEach(field => {
        field.classList.remove('error');
    });
}

// Message system
function showMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `notice notice-${type} is-dismissible`;
    messageDiv.innerHTML = `<p>${message}</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>`;
    
    const container = document.querySelector('.hrb-admin-page');
    if (container) {
        container.insertBefore(messageDiv, container.firstChild);
    } else {
        // Fallback: append to body
        document.body.insertBefore(messageDiv, document.body.firstChild);
    }
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 5000);
    
    // Manual dismiss
    messageDiv.querySelector('.notice-dismiss').addEventListener('click', () => {
        messageDiv.remove();
    });
}

function showSuccessMessage(message) {
    showMessage(message, 'success');
}

function showErrorMessage(message) {
    showMessage(message, 'error');
}

// Edit and Delete functions
function editExtraLock(lockId) {
    closeExtrasLockDetailsModal();
    
    // Show modal first (in edit mode)
    showExtraLockModal(true);
    
    // Then populate form with current data
    const lockData = window.currentLockData;
    document.getElementById('extra-lock-id').value = lockId;
    document.getElementById('extra_id').value = lockData.extra_id;
    
    // Use the datetime values directly (format: YYYY-MM-DD HH:MM:SS)
    const startDateTime = lockData.start_datetime.replace(' ', 'T').substring(0, 16);
    const endDateTime = lockData.end_datetime.replace(' ', 'T').substring(0, 16);
    
    document.getElementById('extra-lock-start-datetime').value = startDateTime;
    document.getElementById('extra-lock-end-datetime').value = endDateTime;
    document.getElementById('reason').value = lockData.reason || '';
    
    // Update form action and title
    document.querySelector('#extra-lock-form input[name="sub_action"]').value = 'edit_extra_lock';
    document.getElementById('extra-lock-modal-title').textContent = '<?php _e('Edit Extra Lock', 'hourly-room-booking'); ?>';
    document.getElementById('extra-lock-submit').textContent = '<?php _e('Update Lock', 'hourly-room-booking'); ?>';
    
    // Debug: Log the sub_action value
    console.log('Edit mode - sub_action set to:', document.querySelector('#extra-lock-form input[name="sub_action"]').value);
    console.log('Edit mode - lock_id set to:', document.getElementById('extra-lock-id').value);
}

function deleteExtraLock(lockId) {
    // Use custom alert dialog with danger type
    window.hrbShowAlertDialog(
        <?php echo json_encode(__('Are you sure you want to delete this extra lock?', 'hourly-room-booking')); ?>,
        {
            warningMessage: <?php echo json_encode(__('This action cannot be undone.', 'hourly-room-booking')); ?>,
            title: <?php echo json_encode(__('Delete Extra Lock', 'hourly-room-booking')); ?>,
            confirmText: <?php echo json_encode(__('Delete', 'hourly-room-booking')); ?>,
            cancelText: <?php echo json_encode(__('Cancel', 'hourly-room-booking')); ?>,
            type: 'danger'
        },
        function() {
            // User confirmed - proceed with deletion
            const formData = new FormData();
            formData.append('action', 'extras_availability_action');
            formData.append('sub_action', 'unlock_extra');
            formData.append('lock_id', lockId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                showSuccessMessage('<?php _e('Extra lock deleted successfully!', 'hourly-room-booking'); ?>');
                closeExtrasLockDetailsModal();
                extrasLockCalendar.refetchEvents();
            })
            .catch(error => {
                showErrorMessage('<?php _e('Error deleting extra lock', 'hourly-room-booking'); ?>');
            });
        }
    );
}

function editMasterExtraLock(lockId) {
    closeExtrasLockDetailsModal();
    
    // Show modal first (in edit mode)
    showMasterExtraLockModal(true);
    
    // Then populate form with current data
    const lockData = window.currentLockData;
    document.getElementById('master-extra-lock-id').value = lockId;
    
    // Use the datetime values directly (format: YYYY-MM-DD HH:MM:SS)
    const startDateTime = lockData.start_datetime.replace(' ', 'T').substring(0, 16);
    const endDateTime = lockData.end_datetime.replace(' ', 'T').substring(0, 16);
    
    document.getElementById('master-extra-lock-start-datetime').value = startDateTime;
    document.getElementById('master-extra-lock-end-datetime').value = endDateTime;
    document.getElementById('master_reason').value = lockData.reason || '';
    
    // Update form action and title
    document.querySelector('#master-extra-lock-form input[name="sub_action"]').value = 'edit_master_extra_lock';
    document.getElementById('master-extra-lock-modal-title').textContent = '<?php _e('Edit Master Extra Lock', 'hourly-room-booking'); ?>';
    document.getElementById('master-extra-lock-submit').textContent = '<?php _e('Update Lock', 'hourly-room-booking'); ?>';
}

function deleteMasterExtraLock(lockId) {
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
            formData.append('action', 'extras_availability_action');
            formData.append('sub_action', 'master_unlock_extras');
            formData.append('lock_id', lockId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                showSuccessMessage('<?php _e('Master lock deleted successfully!', 'hourly-room-booking'); ?>');
                closeExtrasLockDetailsModal();
                extrasLockCalendar.refetchEvents();
            })
            .catch(error => {
                showErrorMessage('<?php _e('Error deleting master lock', 'hourly-room-booking'); ?>');
            });
        }
    );
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Modal buttons
    document.getElementById('hrb-add-extra-lock').addEventListener('click', () => showExtraLockModal());
    document.getElementById('hrb-add-master-extra-lock').addEventListener('click', () => showMasterExtraLockModal());
    
    // Modal close handlers
    document.querySelectorAll('.hrb-modal-close, .hrb-modal-cancel').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.hrb-modal').style.display = 'none';
        });
    });
    
    document.querySelectorAll('.hrb-modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function() {
            this.closest('.hrb-modal').style.display = 'none';
        });
    });
    
    // Form submissions
    document.querySelector('.hrb-extra-lock-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitExtraLockForm();
    });
    
    document.querySelector('.hrb-master-extra-lock-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitMasterExtraLockForm();
    });
});
</script>
