<?php

/**
 * Room Booking Form Template
 * Displays the multi-step booking form for a specific room
 */

if (!defined('ABSPATH')) {
    exit;
}

$room_id = isset($atts['room_id']) ? intval($atts['room_id']) : 0;
$show_room_info = isset($atts['show_room_info']) ? $atts['show_room_info'] === 'true' : true;

// Get URL parameters for pre-filling form
// Check for global variables first (set by AJAX), then fallback to URL parameters
$prefill_date = isset($GLOBALS['prefill_date']) ? $GLOBALS['prefill_date'] : (isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '');
$prefill_time = isset($GLOBALS['prefill_time']) ? $GLOBALS['prefill_time'] : (isset($_GET['time']) ? sanitize_text_field($_GET['time']) : '');
$prefill_duration = isset($GLOBALS['prefill_duration']) ? $GLOBALS['prefill_duration'] : (isset($_GET['duration']) ? sanitize_text_field($_GET['duration']) : '');

// Set default values if no prefill data is available (direct access to booking form)
if (empty($prefill_date)) {
    $prefill_date = date('Y-m-d'); // Today's date
}
if (empty($prefill_duration)) {
    $prefill_duration = '2'; // Default 2 hours
}


if (!$room_id || !$room) {
    echo '<div class="hrb-alert hrb-alert-error">' . __('Room not found or inactive', 'hourly-room-booking') . '</div>';
    return;
}

$room_manager = HRB_Room_Manager::getInstance();
$amenities = $room_manager->get_room_amenities($room_id);
$images = $room_manager->get_room_images($room_id);

// Get available extras based on stock and date/time
$extras_manager = HRB_Extras::getInstance();
// For now, get all active extras - will be filtered via AJAX when date/time is selected
$available_extras = $extras_manager->get_extras('active');

// Get settings for pricing
$settings = HRB_Settings::getInstance();
$extra_people_price = $settings->get('hrb_extra_person_price', 15);
$currency_symbol = hrb_get_currency_symbol();

// Get customizable labels
$label_booking_date = $settings->get_label('hrb_label_booking_date');
$label_duration = $settings->get_label('hrb_label_duration');
$label_start_time = $settings->get_label('hrb_label_start_time');
$label_extra_people = $settings->get_label('hrb_label_extra_people');
$label_special_requests = $settings->get_label('hrb_label_special_requests');
$label_next_button = $settings->get_label('hrb_label_next_button');
$label_previous_button = $settings->get_label('hrb_label_previous_button');
$label_book_now_button = $settings->get_label('hrb_label_book_now_button');
$label_select_duration = $settings->get_label('hrb_label_select_duration');
$label_loading_message = $settings->get_label('hrb_label_loading_message');
$label_no_slots_message = $settings->get_label('hrb_label_no_slots_message');
?>

<style>
  /* Enhanced Professional Booking Form Variables */


/* Main Container */
.hrb-booking-form-wrapper {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.95) 100%);
    border-radius: var(--hrb-radius-xl);
    padding: 0;
    margin: 40px 0;
    box-shadow: var(--hrb-shadow-xl);
    border: 1px solid var(--hrb-border-light);
    overflow: hidden;
    position: relative;
    backdrop-filter: blur(10px);
    animation: slideInUp 0.6s ease-out;
}

.hrb-booking-form {
    padding: 40px;
    position: relative;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Room Info Header */
.hrb-room-info {
    background: linear-gradient(135deg, var(--hrb-primary) 0%, var(--hrb-secondary) 100%);
    color: white;
    padding: 40px;
    margin: -40px -40px 40px -40px;
    border-radius: var(--hrb-radius-xl) var(--hrb-radius-xl) 0 0;
    position: relative;
    overflow: hidden;
}

.hrb-room-info h2.hrb-heading-md {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 15px 0;
    letter-spacing: -0.02em;
    position: relative;
    z-index: 1;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.hrb-room-info .hrb-text-muted {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 25px;
    position: relative;
    z-index: 1;
    font-weight: 400;
}

/* Step Navigation */
.hrb-form-steps {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    padding: 20px 25px;
    position: relative;
    background: linear-gradient(135deg, var(--hrb-background) 0%, var(--hrb-background-light) 100%);
    border-radius: 15px;
    border: 1px solid var(--hrb-border);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.hrb-form-steps::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 8%;
    right: 8%;
    height: 4px;
    background: var(--hrb-border);
    transform: translateY(-50%);
    z-index: 1;
    border-radius: 2px;
}

.hrb-step {
    display: flex;
    align-items: center;
    position: relative;
    z-index: 2;
    background: transparent;
    padding: 8px 12px;
    transition: var(--hrb-transition);
    cursor: pointer;
    min-width: 60px;
    border-radius: 8px;
    gap: 8px;
}

.hrb-step:hover {
    background: rgba(99, 102, 241, 0.05);
    transform: translateY(-2px);
}

.hrb-step-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--hrb-background-dark);
    color: var(--hrb-text-light);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    margin: 0;
    transition: var(--hrb-transition);
    border: 2px solid var(--hrb-border);
    font-size: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    position: relative;
    z-index: 2;
    flex-shrink: 0;
}

.hrb-step-label {
    font-size: 12px;
    color: var(--hrb-text-light);
    text-align: left;
    font-weight: 600;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    margin: 0;
    transition: var(--hrb-transition);
    line-height: 1.2;
    white-space: nowrap;
}

/* Active Step */
.hrb-step.active .hrb-step-icon {
    background: linear-gradient(135deg, var(--hrb-primary) 0%, var(--hrb-primary-dark) 100%);
    color: white;
    border-color: var(--hrb-primary);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
    transform: scale(1.1);
    animation: activePulse 2s infinite;
}

.hrb-step.active .hrb-step-label {
    color: var(--hrb-primary);
    font-weight: 700;
}

/* Completed Step */
.hrb-step.completed .hrb-step-icon {
    background: linear-gradient(135deg, var(--hrb-success) 0%, var(--hrb-success-dark) 100%);
    color: white;
    border-color: var(--hrb-success);
    box-shadow: 0 3px 12px rgba(16, 185, 129, 0.4);
    transform: scale(1.05);
}

.hrb-step.completed .hrb-step-label {
    color: var(--hrb-success);
    font-weight: 700;
}
.hrb-verification-code-section input {
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    letter-spacing: 3px;
    font-family: monospace;
}


@keyframes activePulse {
    0%, 100% {
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
    }
    50% {
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.6), 0 0 0 4px rgba(99, 102, 241, 0.1);
    }
}

/* Form Content */
.hrb-form-step-content {
    display: none;
}

.hrb-form-step-content.active {
    display: block;
    animation: slideInFadeIn 0.4s ease;
}

@keyframes slideInFadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Form Groups */
.hrb-form-group {
    margin-bottom: 20px;
    position: relative;
}

.hrb-form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--hrb-text);
    font-size: 14px;
    letter-spacing: 0.01em;
}

.hrb-form-label.required::after {
    content: ' *';
    color: var(--hrb-error);
    margin-left: 4px;
    font-weight: 700;
}

/* Form Inputs */
.hrb-form-input,
.hrb-form-select,
.hrb-form-control,
.hrb-form-textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--hrb-border);
    border-radius: var(--hrb-radius);
    font-size: 14px;
    font-weight: 400;
    transition: var(--hrb-transition);
    background: var(--hrb-background);
    color: var(--hrb-text);
    box-sizing: border-box;
    box-shadow: var(--hrb-shadow);
    line-height: 1.5;
    font-family: inherit;
}

.hrb-form-input:hover,
.hrb-form-select:hover,
.hrb-form-control:hover,
.hrb-form-textarea:hover {
    border-color: var(--hrb-primary);
    box-shadow: var(--hrb-shadow-md);
    transform: translateY(-1px);
}

.hrb-form-input:focus,
.hrb-form-select:focus,
.hrb-form-control:focus,
.hrb-form-textarea:focus {
    outline: none;
    border-color: var(--hrb-primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

.hrb-form-input.error,
.hrb-form-select.error,
.hrb-form-control.error {
    border-color: var(--hrb-error-dark);
    background: var(--hrb-error-light);
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
}

.hrb-form-textarea {
    resize: vertical;
    min-height: 100px;
}

/* Select Dropdown */
.hrb-form-select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

/* Buttons */
.hrb-btn {
    padding: 12px 24px;
    border: none;
    border-radius: var(--hrb-radius);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--hrb-transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    min-width: 140px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--hrb-shadow);
    letter-spacing: 0.025em;
}

.hrb-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--hrb-shadow-lg);
}

.hrb-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.hrb-btn-primary {
    background: var(--hrb-primary);
    color: white;
}

.hrb-btn-primary:hover:not(:disabled) {
    background: var(--hrb-primary-dark);
}

.hrb-btn-secondary {
    background: var(--hrb-text-light);
    color: white;
}

.hrb-btn-secondary:hover:not(:disabled) {
    background: var(--hrb-text);
}

.hrb-btn-success {
    background: var(--hrb-success);
    color: white;
}

.hrb-btn-success:hover:not(:disabled) {
    background: var(--hrb-success-dark);
}

.hrb-btn-sm {
    padding: 8px 16px;
    font-size: 12px;
    min-width: auto;
}

/* Time Slots */
.hrb-time-slots,
.hrb-time-slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-top: 15px;
}

.hrb-time-slot {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    min-height: 70px;
    padding: 12px 8px;
    background: var(--hrb-background);
    border: 2px solid var(--hrb-border);
    border-radius: var(--hrb-radius);
    cursor: pointer;
    text-align: center;
    transition: var(--hrb-transition);
    color: var(--hrb-text);
}

.hrb-time-slot.available {
    border-color: var(--hrb-success);
    background: var(--hrb-success-light);
    color: var(--hrb-success-dark);
}

.hrb-time-slot.available:hover {
    border-color: var(--hrb-success-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.hrb-time-slot.selected {
    border-color: var(--hrb-accent);
    background: var(--hrb-primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.hrb-time-slot.unavailable {
    background: var(--hrb-error-light);
    color: var(--hrb-error-dark);
    cursor: not-allowed;
    opacity: 0.6;
    border-color: var(--hrb-error);
}

.hrb-time-slot-time {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 4px;
    line-height: 1.2;
}

.hrb-time-slot-status,
.hrb-time-slot-price {
    font-size: 11px;
    opacity: 0.8;
    line-height: 1.2;
}

/* Extras */
.hrb-extra-item {
    border: 1px solid var(--hrb-border);
    border-radius: var(--hrb-radius);
    cursor: pointer;
    transition: var(--hrb-transition);
    background: var(--hrb-background);
    box-shadow: var(--hrb-shadow);
    margin-bottom: 15px;
    position: relative;
}

.hrb-extra-item:hover {
    border-color: var(--hrb-primary);
    box-shadow: var(--hrb-shadow-md);
    transform: translateY(-2px);
}

.hrb-extra-item:has(input[type="checkbox"]:checked) {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05));
    border-color: var(--hrb-primary);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
}

.hrb-extra-content {
    display: flex;
    flex-direction: column;
    padding: 16px;
    gap: 12px;
    transition: var(--hrb-transition);
}

.hrb-extra-header {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
}

.hrb-extra-checkbox {
    display: none;
}

.hrb-extra-icon {
    width: 40px;
    height: 40px;
    background: var(--hrb-background-light);
    border: 1px solid var(--hrb-border);
    border-radius: var(--hrb-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--hrb-primary);
    flex-shrink: 0;
}

.hrb-extra-details {
    flex: 1;
    min-width: 0;
}

.hrb-extra-title {
    font-weight: 600;
    color: var(--hrb-text);
    font-size: 16px;
    margin: 0;
    line-height: 1.3;
}

.hrb-extra-price {
    flex: none;
    font-weight: bold;
    color: var(--hrb-success-dark);
    font-size: 16px;
    background: var(--hrb-success-light);
    padding: 1px 5px;
    border-radius: 4px;
    border: 1px solid var(--hrb-success);
}

.hrb-extra-description {
    color: var(--hrb-text-light);
    margin: 0;
    font-size: 14px;
    line-height: 1.4;
    padding: 8px 12px;
    background: linear-gradient(135deg, var(--hrb-background-light), var(--hrb-background-dark));
    border-radius: 6px;
    border-left: 3px solid var(--hrb-primary);
    box-shadow: var(--hrb-shadow);
}

/* People Counter */
.hrb-people-counter {
    display: flex;
    /* align-items: center; */
    gap: 10px;
    margin-top: 8px;
}

.hrb-people-counter .hrb-btn {
    padding: 8px 12px !important;
    font-size: 14px !important;
    line-height: 1 !important;
    min-width: 32px !important;
    border-radius: 4px !important;
    flex: none !important;
    font-weight: 600 !important;
    background: var(--hrb-primary) !important;
    color: white !important;
    border: 1px solid var(--hrb-primary) !important;
}

.hrb-people-counter input {
    text-align: center;
    max-width: 80px;
    flex: none;
}

/* Verification Methods */
.hrb-verification-methods {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 15px 0;
}

.hrb-verification-method {
    border: 2px solid var(--hrb-border);
    border-radius: var(--hrb-radius);
    cursor: pointer;
    transition: var(--hrb-transition);
    overflow: hidden;
}

.hrb-verification-method:hover {
    border-color: var(--hrb-primary);
    box-shadow: var(--hrb-shadow-md);
}

.hrb-verification-method input[type="radio"] {
    display: none;
}

.hrb-verification-method input[type="radio"]:checked + .hrb-verification-method-content {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(139, 92, 246, 0.05));
    border-left: 4px solid var(--hrb-primary);
}

.hrb-verification-method-content {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 16px;
    transition: var(--hrb-transition);
}

/* Payment Methods */
.hrb-payment-methods {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin: 20px 0;
}

.hrb-payment-method {
    border: 2px solid var(--hrb-border);
    border-radius: var(--hrb-radius-lg);
    cursor: pointer;
    transition: var(--hrb-transition);
    overflow: hidden;
    background: var(--hrb-background);
    box-shadow: var(--hrb-shadow);
}

.hrb-payment-method:hover {
    border-color: var(--hrb-primary);
    box-shadow: var(--hrb-shadow-md);
    transform: translateY(-2px);
}

.hrb-payment-method input[type="radio"] {
    display: none;
}

.hrb-payment-method input[type="radio"]:checked ~ .hrb-payment-method-content,
.hrb-payment-method input[type="radio"]:checked + .hrb-payment-method-content {
    background: #dc3545 !important;
    color: white !important;
    border-left: 4px solid #dc3545 !important;
}

.hrb-payment-method:has(input[type="radio"]:checked) {
    background: #dc3545 !important;
    color: white !important;
    border-color: #dc3545 !important;
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.hrb-payment-method:has(input[type="radio"]:checked):hover {
    background: #c82333 !important;
    color: white !important;
}

.hrb-payment-method:has(input[type="radio"]:checked) strong {
    color: white !important;
}

.hrb-payment-method:has(input[type="radio"]:checked) p {
    color: rgba(255, 255, 255, 0.9) !important;
}

.hrb-payment-method:has(input[type="radio"]:checked) .hrb-payment-method-content {
    color: white !important;
}

.hrb-payment-method:has(input[type="radio"]:checked) .hrb-payment-method-content div {
    color: white !important;
}

/* Smooth transition for payment method selection */
.hrb-payment-method .hrb-radio-label {
    transition: all 0.3s ease;
}

.hrb-payment-method-content {
    display: flex;
    align-items: center;
    padding: 20px;
    gap: 15px;
    transition: var(--hrb-transition);
}

/* Messages & Alerts */
.hrb-alert {
    padding: 12px 16px;
    border-radius: var(--hrb-radius);
    margin-bottom: 20px;
    font-size: 14px;
    border: 1px solid transparent;
    border-left-width: 4px;
}

.hrb-alert-error {
    background: var(--hrb-error-light);
    color: var(--hrb-error-dark);
    border-color: var(--hrb-error);
}

.hrb-alert-success {
    background: var(--hrb-success-light);
    color: var(--hrb-success-dark);
    border-color: var(--hrb-success);
}

.hrb-alert-warning {
    background: var(--hrb-warning-light);
    color: var(--hrb-warning);
    border-color: var(--hrb-warning);
}

.hrb-alert-info {
    background: rgba(99, 102, 241, 0.1);
    color: var(--hrb-primary-dark);
    border-color: var(--hrb-primary);
}

/* Validation Error */
.hrb-validation-error {
    background: var(--hrb-error-light);
    color: var(--hrb-error-dark);
    padding: 12px 16px;
    border: 1px solid var(--hrb-error);
    border-left-width: 4px;
    border-radius: var(--hrb-radius);
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 500;
    box-shadow: var(--hrb-shadow);
    display: flex;
    align-items: center;
    animation: slideDown 0.3s ease;
}

.hrb-validation-error::before {
    content: "⚠️";
    margin-right: 8px;
    font-size: 16px;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Loading States */
.hrb-loading,
.hrb-loading-message {
    text-align: center;
    padding: 40px 20px;
    color: var(--hrb-text-light);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}

.hrb-loading-spinner {
    width: 35px;
    height: 35px;
    border: 2px solid var(--hrb-border);
    border-radius: 50%;
    border-top-color: var(--hrb-primary);
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

.hrb-loading-text {
    font-size: 14px;
    color: var(--hrb-text-light);
    font-weight: 500;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Form Actions */
.hrb-form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--hrb-border);
}

/* Row & Column Layout */
.hrb-row {
    display: flex;
    gap: 20px;
    margin: 0px;
}

.hrb-col-6 {
    flex: 1;
}

/* Headings */
.hrb-heading-sm,
.hrb-heading-xs {
    font-weight: 600;
    color: var(--hrb-text);
    margin: 0 0 15px 0;
}

.hrb-heading-sm {
    font-size: 18px;
}

.hrb-heading-xs {
    font-size: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--hrb-primary);
}

/* Helpers */
.hrb-form-help {
    font-size: 12px;
    color: var(--hrb-text-light);
    margin-top: 6px;
}

.hrb-no-slots,
.hrb-no-extras,
.hrb-error-message {
    text-align: center;
    padding: 20px;
    border-radius: var(--hrb-radius);
    margin-top: 15px;
}

.hrb-no-slots,
.hrb-no-extras {
    background: var(--hrb-warning-light);
    color: var(--hrb-warning);
    border: 1px solid var(--hrb-warning);
}

.hrb-error-message {
    background: var(--hrb-error-light);
    color: var(--hrb-error-dark);
    border: 1px solid var(--hrb-error);
}

/* Contact Display */
.hrb-contact-display {
    background: rgba(99, 102, 241, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.15);
    color: var(--hrb-text);
    font-weight: 500;
    padding: 12px 16px;
    border-radius: var(--hrb-radius);
    min-height: 44px;
    display: flex;
    align-items: center;
}

/* Verification Container */
.hrb-verification-container {
    margin-top: 30px;
    padding: 20px;
    background: var(--hrb-background-light);
    border: 1px solid var(--hrb-border);
    border-radius: var(--hrb-radius);
}

.hrb-verification-section {
    position: relative;
    background: rgba(99, 102, 241, 0.02);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: var(--hrb-radius);
    padding: 20px;
    margin: 20px 0;
}

.hrb-verification-loading {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--hrb-radius);
    z-index: 10;
}

.hrb-verification-success,
.hrb-verification-error {
    padding: 12px 16px;
    border-radius: var(--hrb-radius);
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    border-left-width: 4px;
}

.hrb-verification-success {
    background: var(--hrb-success-light);
    color: var(--hrb-success-dark);
    border: 1px solid var(--hrb-success);
}

.hrb-verification-error {
    background: var(--hrb-error-light);
    color: var(--hrb-error-dark);
    border: 1px solid var(--hrb-error);
}

/* Payment Processing */
.hrb-payment-processing {
    position: relative;
    pointer-events: none;
}

.hrb-payment-processing::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
    backdrop-filter: blur(2px);
    z-index: 1000;
    border-radius: var(--hrb-radius);
    animation: fadeIn 0.3s ease;
}

.hrb-payment-processing::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 50px;
    height: 50px;
    border: 4px solid rgba(99, 102, 241, 0.1);
    border-radius: 50%;
    border-top-color: var(--hrb-primary);
    border-right-color: var(--hrb-secondary);
    animation: spin 1.2s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
    z-index: 1001;
}
.hrb-btn-accent {
    background: var(--hrb-accent);
    color: white;
}
.hrb-badge-important {
    background: var(--hrb-accent);
    color: white;
}
.some-element:hover {
    border-color: var(--hrb-accent);
}
.hrb-payment-method-content img {
    width: 48px;
    height: 48px;
    object-fit: contain;
    border-radius: 8px;
    background: #f8f9fa;
    padding: 8px;
    border: 1px solid #e9ecef;
}

.hrb-payment-method-content p {
    color: var(--hrb-text-light);
    margin: 0;
    font-size: 16px;
    line-height: 1.4;
}
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Responsive Design */
@media screen and (max-width: 768px) {
    .hrb-booking-form-wrapper {
        margin: 20px 0;
    }

    .hrb-booking-form {
        padding: 20px;
    }

    .hrb-room-info {
        margin: -20px -20px 30px -20px;
        padding: 30px 20px;
    }

    .hrb-room-info h2.hrb-heading-md {
        font-size: 2rem;
    }

    .hrb-form-steps {
        padding: 15px 20px;
        margin-bottom: 25px;
    }

    .hrb-step {
        padding: 6px 8px;
        min-width: 50px;
        gap: 6px;
    }

    .hrb-step-icon {
        width: 28px;
        height: 28px;
        font-size: 14px;
    }

    .hrb-step-label {
        font-size: 10px;
        letter-spacing: 0.2px;
    }

    .hrb-time-slots,
    .hrb-time-slots-grid {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 10px;
    }

    .hrb-time-slot {
        min-height: 60px;
        padding: 10px 6px;
    }

    .hrb-time-slot-time {
        font-size: 14px;
    }

    .hrb-time-slot-status,
    .hrb-time-slot-price {
        font-size: 10px;
    }

    .hrb-row {
        flex-direction: column;
        gap: 0;
    }

    .hrb-extra-content {
        padding: 12px;
        gap: 10px;
    }

    .hrb-extra-icon {
        width: 36px;
        height: 36px;
        font-size: 16px;
    }

    .hrb-extra-title {
        font-size: 14px;
    }

    .hrb-extra-price {
        font-size: 13px;
        position: absolute;
        right: -12px;
        top: -9px;
    }

    .hrb-extra-description {
        font-size: 13px;
        padding: 6px 10px;
    }

    .hrb-payment-method-content {
        padding: 15px;
        gap: 12px;
    }

    .hrb-payment-method-content img {
        width: 40px;
        height: 40px;
        padding: 6px;
    }

    .hrb-payment-method-content strong {
        font-size: 15px;
    }

    .hrb-payment-method-content p {
        font-size: 13px;
    }
}

@media screen and (max-width: 600px) {
    .hrb-form-steps {
        padding: 12px 15px;
        margin-bottom: 20px;
    }

    .hrb-step {
        padding: 4px 6px;
        min-width: 40px;
        gap: 4px;
    }

    .hrb-step-icon {
        width: 24px;
        height: 24px;
        font-size: 12px;
    }

    .hrb-step-label {
        display: none;
    }
}

@media screen and (max-width: 480px) {
    .hrb-time-slots,
    .hrb-time-slots-grid {
        gap: 8px;
    }

    .hrb-time-slot {
        min-height: 55px;
        padding: 8px 4px;
    }

    .hrb-time-slot-time {
        font-size: 12px;
    }

    .hrb-loading-spinner {
        width: 32px;
        height: 32px;
    }

    .hrb-loading-text {
        font-size: 13px;
    }
}
</style>

<div class="">


    <div class="hrb-booking-form-wrapper">
        <form class="hrb-booking-form hrb-form" id="hrb-booking-form-<?php echo esc_attr($room_id); ?>" data-room-id="<?php echo esc_attr($room_id); ?>" data-room-name="<?php echo esc_attr($room->name); ?>" data-local-pricing="true">
            <!-- Step Progress Indicator -->
            <div class="hrb-form-steps">
                <div class="hrb-step active" data-step="1">
                    <div class="hrb-step-icon">📅</div>
                    <div class="hrb-step-label"><?php _e('Date & Time', 'hourly-room-booking'); ?></div>
                </div>
                <div class="hrb-step" data-step="2">
                    <div class="hrb-step-icon">⭐</div>
                    <div class="hrb-step-label"><?php _e('Extras', 'hourly-room-booking'); ?></div>
                </div>
                <div class="hrb-step" data-step="3">
                    <div class="hrb-step-icon">👤</div>
                    <div class="hrb-step-label"><?php _e('Details', 'hourly-room-booking'); ?></div>
                </div>
                <div class="hrb-step" data-step="4">
                    <div class="hrb-step-icon">💳</div>
                    <div class="hrb-step-label"><?php _e('Payment', 'hourly-room-booking'); ?></div>
                </div>
                <div class="hrb-step" data-step="5">
                    <div class="hrb-step-icon">✅</div>
                    <div class="hrb-step-label"><?php _e('Confirm', 'hourly-room-booking'); ?></div>
                </div>
            </div>

            <!-- Step 1: Date & Time Selection -->
            <div class="hrb-form-step-content active" data-step="1">
                <h3 class="hrb-heading-sm"><?php _e('Select Date & Time', 'hourly-room-booking'); ?></h3>

                <div class="hrb-row">
                    <div class="hrb-col-6">
                        <div class="hrb-form-group">
                            <label class="hrb-form-label required" for="booking-date-<?php echo $room_id; ?>">
                                <?php echo esc_html($label_booking_date); ?>
                            </label>
                            <input type="date"
                                class="hrb-form-control"
                                id="booking-date-<?php echo $room_id; ?>"
                                name="booking_date"
                                value="<?php echo esc_attr($prefill_date); ?>"
                                required
                                min="<?php echo date('Y-m-d'); ?>"
                                max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                        </div>
                    </div>
                    <div class="hrb-col-6">
                        <div class="hrb-form-group">
                            <label class="hrb-form-label required">
                                <?php echo esc_html($label_duration); ?>
                            </label>
                            <select class="hrb-form-control hrb-form-select" name="duration" id="duration-<?php echo $room_id; ?>" required>
                                <option value=""><?php echo esc_html($label_select_duration); ?></option>
                                <?php for ($hours = 2; $hours <= 12; $hours++): ?>
                                    <?php $selected = ($prefill_duration == $hours) ? 'selected' : ''; ?>
                                    <?php
                                    // Calculate price using new pricing system
                                    $price = 0;
                                    $use_room_price = false;

                                    if ($hours == 2 && $room->price_2_hours > 0) {
                                        $price = floatval($room->price_2_hours);
                                        $use_room_price = true;
                                    } elseif ($hours == 3 && $room->price_3_hours > 0) {
                                        $price = floatval($room->price_3_hours);
                                        $use_room_price = true;
                                    } elseif ($hours == 4 && $room->price_4_hours > 0) {
                                        $price = floatval($room->price_4_hours);
                                        $use_room_price = true;
                                    } elseif ($hours > 4) {
                                        // For durations > 4 hours, use 4-hour price + extra hours
                                        if ($room->price_4_hours > 0) {
                                            $price = floatval($room->price_4_hours);
                                            $use_room_price = true;

                                            // Add extra hours using room-specific or global extra hour price
                                            $extra_hours = $hours - 4;
                                            $extra_hour_price = $room->price_extra_hour > 0 ?
                                                floatval($room->price_extra_hour) :
                                                floatval(get_option('hrb_price_extra_hour', 0));

                                            if ($extra_hour_price > 0) {
                                                $price += $extra_hours * $extra_hour_price;
                                            }
                                        }
                                    }

                                    // If room has specific pricing, use it
                                    if ($use_room_price) {
                                        // Price already calculated above
                                    } else {
                                        // Fallback to global pricing
                                        $global_price = 0;
                                        if ($hours == 2) {
                                            $global_price = floatval(get_option('hrb_price_2_hours', 0));
                                        } elseif ($hours == 3) {
                                            $global_price = floatval(get_option('hrb_price_3_hours', 0));
                                        } elseif ($hours == 4) {
                                            $global_price = floatval(get_option('hrb_price_4_hours', 0));
                                        } elseif ($hours > 4) {
                                            $global_price = floatval(get_option('hrb_price_4_hours', 0));
                                            $extra_hours = $hours - 4;
                                            $extra_hour_price = floatval(get_option('hrb_price_extra_hour', 0));
                                            if ($extra_hour_price > 0) {
                                                $global_price += $extra_hours * $extra_hour_price;
                                            }
                                        }

                                        // If global pricing is available, use it
                                        if ($global_price > 0) {
                                            $price = $global_price;
                                        } else {
                                            // No pricing found
                                            $price = 0;
                                        }
                                    }
                                    ?>
                                    <option value="<?php echo $hours; ?>" <?php echo $selected; ?>>
                                        <?php echo $hours; ?> <?php _e('hours', 'hourly-room-booking'); ?> -
                                        <?php echo hrb_format_amount($price); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="hrb-form-group">
                    <label class="hrb-form-label required">
                        <?php _e('Available Time Slots', 'hourly-room-booking'); ?>
                    </label>
                    <div class="hrb-timezone-notice">
                        <small style="color: #666; font-style: italic;">
                            <?php _e('Bitte beachten Sie: Die verfügbaren Zeiten sind in der Zeitzone von Berlin angegeben.', 'hourly-room-booking'); ?>
                        </small>
                    </div>
                    <div class="hrb-time-slots" id="time-slots-<?php echo $room_id; ?>">
                        <!-- Time slots will be loaded via AJAX -->
                        <div class="hrb-loading-message">
                            <div class="hrb-loading-text"><?php _e('Please select a date and duration first', 'hourly-room-booking'); ?></div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                <input type="hidden" name="start_time" id="start-time-<?php echo $room_id; ?>">
                <input type="hidden" name="end_time" id="end-time-<?php echo $room_id; ?>">

                <div class="hrb-form-actions">
                    <button type="button" class="hrb-btn hrb-btn-primary hrb-step-next">
                        <?php echo esc_html($label_next_button); ?>
                    </button>
                </div>
            </div>

            <!-- Step 2: Extras & Additional People -->
            <div class="hrb-form-step-content" data-step="2">
                <h3 class="hrb-heading-sm"><?php _e('Extras & Additional People', 'hourly-room-booking'); ?></h3>

                <div class="hrb-form-group">
                    <label class="hrb-form-label">
                        <?php _e('Available Extras', 'hourly-room-booking'); ?>
                    </label>
                    <div class="hrb-extras-list">
                        <?php if (!empty($available_extras)): ?>
                            <?php foreach ($available_extras as $extra): ?>
                                <div class="hrb-extra-item">
                                    
                                    <div class="hrb-extra-content">
                                        <div class="hrb-extra-header">
                                        <input type="checkbox"
                                        name="extras[]"
                                        value="<?php echo esc_attr($extra->id); ?>"
                                        data-price="<?php echo esc_attr($extra->price); ?>"
                                        data-name="<?php echo esc_attr($extra->name); ?>" class="hrb-extra-checkbox">
                                            <div class="hrb-extra-icon">
                                                <?php if (!empty($extra->image_url)): ?>
                                                    <img src="<?php echo esc_url($extra->image_url); ?>" alt="<?php echo esc_attr($extra->name); ?>">
                                                <?php else: ?>
                                                    ⭐
                                                <?php endif; ?>
                                            </div>
                                            <div class="hrb-extra-details">
                                                <div class="hrb-extra-title"><?php echo esc_html($extra->name); ?></div>
                                            </div>
                                            <div class="hrb-extra-price">
                                                +<?php echo hrb_format_amount($extra->price); ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($extra->description)): ?>
                                            <div class="hrb-extra-description"><?php echo esc_html($extra->description); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="hrb-no-extras">
                                <?php _e('No extras available at this time.', 'hourly-room-booking'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="hrb-form-group">
                    <label class="hrb-form-label" for="extra-people-<?php echo $room_id; ?>">
                        <?php echo esc_html($label_extra_people); ?>
                    </label>
                    <div class="hrb-people-counter">
                        <button type="button" class="hrb-btn hrb-btn-secondary btn-minus">-</button>
                        <input type="number"
                            class="hrb-form-control"
                            id="extra-people-<?php echo $room_id; ?>"
                            name="extra_people"
                            value="0"
                            min="0"
                            max="10">
                        <button type="button" class="hrb-btn hrb-btn-secondary btn-plus">+</button>
                    </div>
                    <div class="hrb-form-help">
                        <?php printf(__('%s per additional person (max 10)', 'hourly-room-booking'), hrb_format_amount($extra_people_price)); ?>
                    </div>
                </div>

                <div class="hrb-form-group">
                    <label class="hrb-form-label" for="special-requests-<?php echo $room_id; ?>">
                        <?php echo esc_html($label_special_requests); ?>
                    </label>
                    <textarea class="hrb-form-control"
                        id="special-requests-<?php echo $room_id; ?>"
                        name="special_requests"
                        rows="3"
                        placeholder="<?php _e('Any special requirements or requests...', 'hourly-room-booking'); ?>"></textarea>
                </div>

                <div class="hrb-form-actions">
                    <button type="button" class="hrb-btn hrb-btn-secondary hrb-step-prev">
                        <?php echo esc_html($label_previous_button); ?>
                    </button>
                    <button type="button" class="hrb-btn hrb-btn-primary hrb-step-next">
                        <?php echo esc_html($label_next_button); ?>
                    </button>
                </div>
            </div>

            <!-- Step 3: Customer Details -->
            <div class="hrb-form-step-content" data-step="3">
                <h3 class="hrb-heading-sm"><?php _e('Your Details', 'hourly-room-booking'); ?></h3>
                
                <!-- Anonymous Booking Option (only for bookings < 4 hours) -->
                <div id="hrb-anonymous-booking-option" class="hrb-anonymous-booking-option" style="display: none;">
                    <div class="hrb-form-group1">
                        <label class="hrb-checkbox-label">
                            <input type="checkbox" id="hrb-anonymous-booking" name="is_anonymous" value="1">
                            <span class="hrb-checkbox-custom"></span>
                            <span class="hrb-checkbox-text">
                                <?php _e('Ich möchte eine 100 % anonyme Buchung vornehmen und bin mir bewusst, dass ich keine Nachrichten zu dieser Buchung erhalten werde.', 'hourly-room-booking'); ?>
                                <?php _e('Anonyme Buchungen sind nur für Buchungen unter 4 Stunden verfügbar.', 'hourly-room-booking'); ?>
                                
                            </span>
                        </label>
                    </div>
                </div>
                
                <!-- Anonymous Booking Information -->
                <div class="hrb-static-info" style="margin-bottom: 20px; display: none !important;">
                    <h4><?php _e('Note on anonymous booking', 'hourly-room-booking'); ?></h4>
                    <p><?php _e('You can complete this booking anonymously. The "Last Name" field is therefore not a mandatory field – it is sufficient if you enter a synonym or an abbreviation.', 'hourly-room-booking'); ?></p>
                    <p><?php _e('Please note, however: To successfully complete the booking, an email verification is required. We will send a confirmation code to the email address you provided, which you must enter in the next step.', 'hourly-room-booking'); ?></p>
                </div>

                <?php
                // Auto-fill user details for logged-in users using booking-specific meta fields
                $current_user = wp_get_current_user();
                $user_first_name = '';
                $user_last_name = '';
                $user_email = '';
                $user_phone = '';
                $user_company = '';

                if (is_user_logged_in()) {
                    // Use WordPress user data for auto-fill (not booking-specific meta fields)
                    // This ensures each booking starts fresh with account data, not previous booking data
                    $user_first_name = $current_user->first_name;
                    $user_last_name = $current_user->last_name;
                    $user_email = $current_user->user_email;
                    $user_phone = get_user_meta($current_user->ID, 'phone', true);
                    $user_company = get_user_meta($current_user->ID, 'company', true);

                    // If still no first/last name, try to extract from display name
                    if (empty($user_first_name) && empty($user_last_name) && !empty($current_user->display_name)) {
                        $name_parts = explode(' ', $current_user->display_name, 2);
                        $user_first_name = $name_parts[0];
                        $user_last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                    }
                }
                ?>

                <?php if (is_user_logged_in()): ?>
                    <div class="hrb-static-info" style="margin-bottom: 20px;">
                        <strong><?php _e('Welcome back!', 'hourly-room-booking'); ?></strong>
                        <?php _e('Your details have been pre-filled from your account. You can modify them if needed.', 'hourly-room-booking'); ?>
                    </div>
                <?php endif; ?>

                <div class="hrb-row">
                    <div class="hrb-col-6 hrb-name-field">
                        <div class="hrb-form-group">
                            <label class="hrb-form-label required" for="first-name-<?php echo $room_id; ?>">
                                <?php _e('First Name', 'hourly-room-booking'); ?>
                            </label>
                            <input type="text"
                                class="hrb-form-control"
                                id="first-name-<?php echo $room_id; ?>"
                                name="first_name"
                                value="<?php echo esc_attr($user_first_name); ?>"
                                required>
                        </div>
                    </div>
                    <div class="hrb-col-6 hrb-name-field">
                        <div class="hrb-form-group">
                            <label class="hrb-form-label" for="last-name-<?php echo $room_id; ?>">
                                <?php _e('Last Name', 'hourly-room-booking'); ?>
                            </label>
                            <input type="text"
                                class="hrb-form-control"
                                id="last-name-<?php echo $room_id; ?>"
                                name="last_name"
                                value="<?php echo esc_attr($user_last_name); ?>">
                        </div>
                    </div>
                </div>

                <div class="hrb-row">
                    <div class="hrb-col-6 hrb-email-field">
                        <div class="hrb-form-group">
                            <label class="hrb-form-label required" for="email-<?php echo $room_id; ?>">
                                <?php _e('Email Address', 'hourly-room-booking'); ?>
                            </label>
                            <input type="email"
                                class="hrb-form-control"
                                id="email-<?php echo $room_id; ?>"
                                name="email"
                                value="<?php echo esc_attr($user_email); ?>"
                                required>
                        </div>
                    </div>
                    <div class="hrb-col-6 hrb-phone-field">
                        <div class="hrb-form-group">
                            <label class="hrb-form-label" for="phone-<?php echo $room_id; ?>">
                                <?php _e('Phone Number', 'hourly-room-booking'); ?>
                            </label>
                            <input type="tel"
                                class="hrb-form-control"
                                id="phone-<?php echo $room_id; ?>"
                                name="phone"
                                value="<?php echo esc_attr($user_phone); ?>"
                                placeholder="+49 123 456 789">
                        </div>
                    </div>
                </div>


                <!-- Contact Verification Section -->
                <div class="hrb-verification-container hrb-verification-field">
                    <h4 class="hrb-heading-xs"><?php _e('Contact Verification', 'hourly-room-booking'); ?></h4>

                    <?php
                    // Check admin notification settings
                    $email_notifications = get_option('hrb_email_notifications', 1);
                    $sms_notifications = get_option('hrb_sms_notifications', 0);
                    $both_enabled = $email_notifications && $sms_notifications;
                    ?>

                    <div class="hrb-verification-alert">
                        <strong><?php _e('Verification Required', 'hourly-room-booking'); ?></strong>
                        <?php if ($both_enabled): ?>
                            <?php _e('Please verify your contact information before proceeding. Choose your preferred verification method.', 'hourly-room-booking'); ?>
                        <?php elseif ($sms_notifications): ?>
                            <?php _e('Please verify your phone number before proceeding. We will send you a verification code via SMS.', 'hourly-room-booking'); ?>
                        <?php else: ?>
                            <?php _e('Please verify your email address before proceeding. We will send you a verification code.', 'hourly-room-booking'); ?>
                        <?php endif; ?>
                    </div>

                    <div class="hrb-verification-section">
                        <?php if ($both_enabled): ?>
                            <!-- Verification Method Selection -->
                            <div class="hrb-form-group">
                                <label class="hrb-form-label"><?php _e('Choose Verification Method:', 'hourly-room-booking'); ?></label>
                                <div class="hrb-verification-methods">
                                    <label class="hrb-verification-method">
                                        <input type="radio" name="verification_method" value="email" checked>
                                        <span class="hrb-verification-method-content">
                                            <span class="hrb-verification-icon">📧</span>
                                            <div>
                                                <strong><?php _e('Email Verification', 'hourly-room-booking'); ?></strong>
                                                <p><?php _e('Send verification code to your email address', 'hourly-room-booking'); ?></p>
                                            </div>
                                        </span>
                                    </label>
                                    <label class="hrb-verification-method">
                                        <input type="radio" name="verification_method" value="sms">
                                        <span class="hrb-verification-method-content">
                                            <span class="hrb-verification-icon">📱</span>
                                            <div>
                                                <strong><?php _e('SMS Verification', 'hourly-room-booking'); ?></strong>
                                                <p><?php _e('Send verification code to your phone number', 'hourly-room-booking'); ?></p>
                                            </div>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Contact Information Display -->
                        <div class="hrb-form-group">
                            <div class="hrb-contact-info">
                                <?php if ($email_notifications): ?>
                                    <div class="hrb-contact-item" id="email-verification-info">
                                        <label class="hrb-form-label"><?php _e('Email to verify:', 'hourly-room-booking'); ?></label>
                                        <div class="hrb-form-control hrb-contact-display" id="verification-email-display">
                                            <!-- Email will be populated by JavaScript -->
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($sms_notifications): ?>
                                    <div class="hrb-contact-item" id="sms-verification-info" <?php echo $both_enabled ? 'style="display: none;"' : ''; ?>>
                                        <label class="hrb-form-label"><?php _e('Phone to verify:', 'hourly-room-booking'); ?></label>
                                        <div class="hrb-form-control hrb-contact-display" id="verification-phone-display">
                                            <!-- Phone will be populated by JavaScript -->
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Change Email Button -->
                        <div class="hrb-form-group">
                            <button type="button" class="hrb-btn hrb-btn-secondary hrb-btn-sm" id="change-email-button">
                                <?php _e('Change Email', 'hourly-room-booking'); ?>
                            </button>
                            <div class="hrb-form-help">
                                <span><?php _e('Need to use a different email address?', 'hourly-room-booking'); ?></span>
                            </div>
                        </div>

                        <!-- Send Code Button -->
                        <div class="hrb-form-group">
                            <button type="button" class="hrb-btn hrb-btn-primary" id="send-verification-code">
                                <span id="send-button-text"><?php _e('Send Email Code', 'hourly-room-booking'); ?></span>
                            </button>
                            <div class="hrb-form-help">
                                <span id="send-help-text"><?php _e('A 6-digit code will be sent to your email address', 'hourly-room-booking'); ?></span>
                            </div>
                        </div>

                        <div class="hrb-form-group hrb-verification-code-section" style="display: none;">
                            <label class="hrb-form-label required" for="verification-code-<?php echo $room_id; ?>">
                                <?php _e('Verification Code', 'hourly-room-booking'); ?>
                            </label>
                            <input type="text"
                                class="hrb-form-control"
                                id="verification-code-<?php echo $room_id; ?>"
                                name="verification_code"
                                maxlength="6"
                                placeholder="<?php _e('Enter 6-digit code', 'hourly-room-booking'); ?>"
                                pattern="[0-9]{6}">
                            <div class="hrb-form-help">
                                <?php _e('Code expires in 15 minutes. Check your spam folder if you don\'t see the email.', 'hourly-room-booking'); ?>
                            </div>
                        </div>

                        <div class="hrb-resend-section" style="display: none;">
                            <p class="hrb-form-help">
                                <?php _e('Didn\'t receive the code?', 'hourly-room-booking'); ?>
                                <button type="button" class="hrb-btn hrb-btn-secondary hrb-btn-sm" id="resend-verification-code">
                                    <?php _e('Resend Code', 'hourly-room-booking'); ?>
                                </button>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="hrb-form-actions">
                    <button type="button" class="hrb-btn hrb-btn-secondary hrb-step-prev">
                        <?php echo esc_html($label_previous_button); ?>
                    </button>
                    <button type="button" class="hrb-btn hrb-btn-primary hrb-step-next" id="details-and-verification-next" disabled>
                        <?php _e('Verify & Continue', 'hourly-room-booking'); ?>
                    </button>
                </div>
            </div>

            <!-- Step 4: Payment Method -->
            <div class="hrb-form-step-content" data-step="4">
                <h3 class="hrb-heading-sm"><?php _e('Payment Method', 'hourly-room-booking'); ?></h3>

                <div class="hrb-payment-methods">
                    <div class="hrb-payment-method">
                        <label class="hrb-radio-label">
                            <input type="radio" name="payment_method" value="paypal">
                            <span class="hrb-payment-method-content">
                                <img src="<?php echo HRB_PLUGIN_URL; ?>assets/images/payment-methods/paypal.png" alt="PayPal">
                                <div>
                                    <strong><?php _e('PayPal', 'hourly-room-booking'); ?></strong>
                                    <p><?php _e('Secure online payment with PayPal (+3% fee)', 'hourly-room-booking'); ?></p>
                                </div>
                            </span>
                        </label>
                    </div>

                    <div class="hrb-payment-method" id="onsite-payment-option">
                        <label class="hrb-radio-label">
                            <input type="radio" name="payment_method" value="onsite">
                            <span class="hrb-payment-method-content">
                                <img src="<?php echo HRB_PLUGIN_URL; ?>assets/images/payment-methods/cash.png" alt="On-site Payment">
                                <div>
                                    <strong><?php _e('On-site Payment', 'hourly-room-booking'); ?></strong>
                                    <p><?php _e('Only cash payments are possible on site.', 'hourly-room-booking'); ?></p>
                                </div>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="hrb-payment-notice hrb-info-box- hrb-alert-info-">
                    <strong><?php _e('Payment Policy:', 'hourly-room-booking'); ?></strong>
                    <ul>
                        <li><?php _e('For bookings of 4 hours or more, payment via PayPal in advance is required. Please note that no refunds are possible in this case.', 'hourly-room-booking'); ?></li>
                        <li><?php _e('For bookings under 4 hours, you can pay either via PayPal or on site.', 'hourly-room-booking'); ?></li>
                        <li><?php _e('There is a 3% processing fee for PayPal payments.', 'hourly-room-booking'); ?></li>
                    </ul>
                </div>

                <div class="hrb-form-actions">
                    <button type="button" class="hrb-btn hrb-btn-secondary hrb-step-prev">
                        <?php echo esc_html($label_previous_button); ?>
                    </button>
                    <button type="button" class="hrb-btn hrb-btn-primary hrb-step-next">
                        <?php echo esc_html($label_next_button); ?>
                    </button>
                </div>
            </div>

            <!-- Step 5: Confirmation -->
            <div class="hrb-form-step-content" data-step="5">
                <h3 class="hrb-heading-sm"><?php _e('Booking overview', 'hourly-room-booking'); ?></h3>

                <div class="hrb-booking-review">
                    <div class="hrb-row">
                        <div class="hrb-col-6">
                            <h4><?php _e('Booking Details', 'hourly-room-booking'); ?></h4>
                            <div class="hrb-review-item">
                                <strong><?php _e('Room:', 'hourly-room-booking'); ?></strong>
                                <span id="review-room-name"><?php echo esc_html($room->name); ?></span>
                            </div>
                            <div class="hrb-review-item">
                                <strong><?php _e('Date:', 'hourly-room-booking'); ?></strong>
                                <span id="review-date">-</span>
                            </div>
                            <div class="hrb-review-item">
                                <strong><?php _e('Time:', 'hourly-room-booking'); ?></strong>
                                <span id="review-time">-</span>
                            </div>
                            <div class="hrb-review-item">
                                <strong><?php _e('Duration:', 'hourly-room-booking'); ?></strong>
                                <span id="review-duration">-</span>
                            </div>
                        </div>
                        <div class="hrb-col-6">
                            <h4><?php _e('Customer Details', 'hourly-room-booking'); ?></h4>
                            <div class="hrb-review-item">
                                <strong><?php _e('Name:', 'hourly-room-booking'); ?></strong>
                                <span id="review-customer-name">-</span>
                            </div>
                            <div class="hrb-review-item">
                                <strong><?php _e('Email:', 'hourly-room-booking'); ?></strong>
                                <span id="review-email">-</span>
                            </div>
                            <div class="hrb-review-item">
                                <strong><?php _e('Phone:', 'hourly-room-booking'); ?></strong>
                                <span id="review-phone">-</span>
                            </div>
                            <div class="hrb-review-item">
                                <strong><?php _e('Payment:', 'hourly-room-booking'); ?></strong>
                                <span id="review-payment-method">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hrb-terms-acceptance">
                    <label class="hrb-checkbox-label">
                        <input type="checkbox" name="accept_terms">
                        <span class="hrb-checkbox-custom"></span>
                        <span class="hrb-checkbox-text">
                            <?php 
                            // Get Terms & Conditions and Privacy Policy page URLs from settings
                            $terms_page_id = $settings->get('hrb_terms_page', '');
                            $privacy_page_id = $settings->get('hrb_privacy_page', '');
                            
                            $terms_url = !empty($terms_page_id) ? get_permalink($terms_page_id) : '#';
                            $privacy_url = !empty($privacy_page_id) ? get_permalink($privacy_page_id) : '#';
                            ?>
                            <?php _e('I accept the', 'hourly-room-booking'); ?>
                            <a href="<?php echo esc_url($terms_url); ?>" target="_blank"><?php _e('Terms & Conditions', 'hourly-room-booking'); ?></a>
                            <?php _e('and', 'hourly-room-booking'); ?>
                            <a href="<?php echo esc_url($privacy_url); ?>" target="_blank"><?php _e('Privacy Policy', 'hourly-room-booking'); ?></a>
                        </span>
                    </label>
                </div>

                <!-- PayPal Payment Warning -->
                <div id="hrb-paypal-warning" class="hrb-paypal-warning" style="display: none;">
                    <div class="hrb-alert hrb-alert-warning">
                        <strong>⚠️ <?php _e('Important:', 'hourly-room-booking'); ?></strong>
                        <?php _e('After clicking on', 'hourly-room-booking'); ?>
                       <strong>„<?php _e('Complete booking', 'hourly-room-booking'); ?>“</strong>
                       <?php _e('Please make the PayPal payment directly. Otherwise, the transaction may be canceled.', 'hourly-room-booking'); ?>
                    </div>
                </div>

                <div class="hrb-alert hrb-alert-success">
                    <strong><?php _e('Email Verified!', 'hourly-room-booking'); ?></strong>
                    <?php _e('Your email has been successfully verified. You can now complete your booking.', 'hourly-room-booking'); ?>
                </div>

                <div class="hrb-form-actions">
                    <button type="button" class="hrb-btn hrb-btn-secondary hrb-step-prev">
                        <?php echo esc_html($label_previous_button); ?>
                    </button>
                    <button type="submit" class="hrb-btn hrb-btn-success hrb-btn-lg">
                        <?php _e('Complete Booking', 'hourly-room-booking'); ?>
                    </button>
                </div>
            </div>

            <!-- Price Summary (Sticky Sidebar) -->
            <div class="hrb-booking-summary" id="booking-summary-<?php echo $room_id; ?>">
                <div class="hrb-summary-title"><?php _e('Booking Summary', 'hourly-room-booking'); ?></div>
                <div class="hrb-summary-placeholder">
                    <?php _e('Please complete the form to see pricing details', 'hourly-room-booking'); ?>
                </div>
            </div>
        </form>

        <!-- PayPal Container -->
        <div id="paypal-button-container" style="display: none;"></div>
    </div>
</div>

<!-- Anonymous Booking Confirmation Modal (Outside form container) -->
<div id="hrb-anonymous-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-overlay"></div>
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h3><?php _e('Wichtiger Hinweis zur anonymen Buchung', 'hourly-room-booking'); ?></h3>
            <button type="button" class="hrb-modal-close">&times;</button>
        </div>
        <div class="hrb-modal-body">
            <p><strong><?php _e('Du hast eine 100 % anonyme Buchung gewählt.', 'hourly-room-booking'); ?></strong></p>
            <p><?php _e('Das bedeutet:', 'hourly-room-booking'); ?></p>
            <ul>
                <li><?php _e('Du erhältst keine Buchungsbestätigung, Erinnerungen oder Änderungsmöglichkeiten per E-Mail.', 'hourly-room-booking'); ?></li>
                <li><?php
                $hrb_contact_email = get_option('hrb_company_email', get_option('admin_email'));
                printf(
                    /* translators: %s: contact email address, rendered as a mailto link */
                    __('Du kannst deine Buchung nicht online bearbeiten oder stornieren. Solltest du nach einer Buchung diese wieder stornieren wollen, ist dies nur mit der Buchungs-ID telefonisch oder per E-Mail an: %s möglich.', 'hourly-room-booking'),
                    '<a href="mailto:' . esc_attr($hrb_contact_email) . '">' . esc_html($hrb_contact_email) . '</a>'
                );
                ?></li>
                <li><?php _e('Die einmalig angezeigte Buchungs-ID dient als einziger Nachweis deiner Buchung.', 'hourly-room-booking'); ?></li>
            </ul>
            <p><?php _e('Bitte notiere oder speichere diese ID nach Abschluss deiner Buchung, da sie nicht erneut angezeigt oder per E-Mail gesendet wird.', 'hourly-room-booking'); ?></p>
        </div>
        <div class="hrb-modal-footer">
            <button type="button" class="hrb-btn hrb-btn-secondary" id="hrb-anonymous-cancel">
                <?php _e('Abbrechen', 'hourly-room-booking'); ?>
            </button>
            <button type="button" class="hrb-btn hrb-btn-primary" id="hrb-anonymous-continue">
                <?php _e('Anonyme Buchung fortsetzen', 'hourly-room-booking'); ?>
            </button>
        </div>
    </div>
</div>

<style>
    /* Payment Loading States */
    .hrb-payment-processing {
        position: relative;
        pointer-events: none;
    }

    .hrb-payment-processing::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
        backdrop-filter: blur(2px);
        z-index: 1000;
        border-radius: 8px;
        animation: hrb-fade-in 0.3s ease;
    }

    .hrb-payment-processing::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 50px;
        height: 50px;
        border: 4px solid rgba(59, 130, 246, 0.1);
        border-radius: 50%;
        border-top-color: var(--hrb-loading-primary);
        border-right-color: #8b5cf6;
        animation: hrb-spin-beautiful 1.2s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
        z-index: 1001;
    }

    .hrb-submit-btn.hrb-loading {
        position: relative;
        pointer-events: none;
        opacity: 0.9;
        background: linear-gradient(135deg, var(--hrb-loading-primary), var(--hrb-alert-info-text));
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        transform: scale(0.98);
        transition: all 0.3s ease;
    }

    .hrb-submit-btn.hrb-loading::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        animation: hrb-button-shimmer 1.5s infinite;
        border-radius: inherit;
    }

    .hrb-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: var(--hrb-ffffff);
        animation: hrb-spin 1s linear infinite;
        margin-right: 8px;
    }


    @keyframes hrb-spin {
        to {
            transform: rotate(360deg);
        }
    }

    @keyframes hrb-spin-beautiful {
        0% {
            transform: rotate(0deg) scale(1);
        }

        50% {
            transform: rotate(180deg) scale(1.05);
        }

        100% {
            transform: rotate(360deg) scale(1);
        }
    }

    @keyframes hrb-spin-reverse {
        0% {
            transform: rotate(360deg);
        }

        100% {
            transform: rotate(0deg);
        }
    }

    @keyframes hrb-spin-smooth {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    @keyframes hrb-pulse-ring {
        0% {
            transform: scale(0.8);
            opacity: 1;
        }

        50% {
            transform: scale(1.2);
            opacity: 0.3;
        }

        100% {
            transform: scale(1.5);
            opacity: 0;
        }
    }

    @keyframes hrb-button-shimmer {
        0% {
            transform: translateX(-100%);
        }

        100% {
            transform: translateX(100%);
        }
    }

    @keyframes hrb-fade-in {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .hrb-payment-redirect-message {
        background: linear-gradient(135deg, var(--hrb-0073aa), var(--hrb-005a87));
        color: white;
        padding: 30px;
        border-radius: 12px;
        text-align: center;
        margin-bottom: 20px;
        box-shadow: 0 4px 20px rgba(0, 115, 170, 0.3);
        animation: hrb-fadeIn 0.5s ease-in-out;
    }

    .hrb-redirect-icon {
        font-size: 48px;
        margin-bottom: 15px;
        animation: hrb-pulse 2s ease-in-out infinite;
    }

    .hrb-payment-redirect-message h3 {
        margin: 0 0 15px 0;
        font-size: 24px;
        font-weight: 600;
    }

    .hrb-payment-redirect-message p {
        margin: 0 0 10px 0;
        font-size: 16px;
        opacity: 0.9;
    }

    .hrb-redirect-note {
        font-size: 14px !important;
        opacity: 0.7 !important;
        font-style: italic;
    }

    @keyframes hrb-fadeIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes hrb-pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }
    }

    /* Disabled state for form elements during payment */
    .hrb-payment-processing input,
    .hrb-payment-processing select,
    .hrb-payment-processing textarea,
    .hrb-payment-processing button {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Loading button styles */
    .hrb-submit-btn:disabled {
        background-color: var(--hrb-text-muted) !important;
        cursor: not-allowed !important;
        opacity: 0.7 !important;
    }

    .hrb-submit-btn.hrb-loading:disabled {
        background-color: var(--hrb-0073aa) !important;
        cursor: not-allowed !important;
        opacity: 1 !important;
    }

    /* Anonymous Booking Styles */
    .hrb-anonymous-booking-option {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
        border-left: 4px solid var(--hrb-primary);
        background: rgba(99, 102, 241, 0.1);
        color: var(--hrb-primary-dark);
        border-color: var(--hrb-primary);
        opacity: 1 !important;
    }

    .hrb-checkbox-label {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        cursor: pointer;
        font-size: 14px;
        line-height: 1.5;
        color: var(--hrb-text);
    }

    .hrb-checkbox-custom {
        width: 20px;
        height: 20px;
        border: 2px solid var(--hrb-border);
        border-radius: 4px;
        background: var(--hrb-background);
        position: relative;
        flex-shrink: 0;
        transition: var(--hrb-transition);
    }

    .hrb-checkbox-label input[type="checkbox"] {
        display: none;
    }

    .hrb-checkbox-label input[type="checkbox"]:checked + .hrb-checkbox-custom {
        background: var(--hrb-primary);
        border-color: var(--hrb-primary);
    }

    .hrb-checkbox-label input[type="checkbox"]:checked + .hrb-checkbox-custom::after {
        content: '✓';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: white;
        font-weight: bold;
        font-size: 12px;
    }

    .hrb-checkbox-text {
        flex: 1;
        font-weight: 500;
    }

    /* Modal Styles - Anonymous Booking Modal Only */
    #hrb-anonymous-modal.hrb-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
        margin: 0;
    }

    #hrb-anonymous-modal.hrb-modal.show {
        display: flex;
    }

    #hrb-anonymous-modal .hrb-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
    }

    #hrb-anonymous-modal .hrb-modal-content {
        position: relative;
        background: var(--hrb-background);
        border-radius: var(--hrb-radius-xl);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        max-width: 500px;
        width: 90%;
        max-height: 85vh;
        overflow-y: auto;
        animation: modalSlideIn 0.3s ease;
        margin: auto;
        transform: translateY(0);
        z-index: 999999;
        display: flex;
        flex-direction: column;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: scale(0.9) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #hrb-anonymous-modal .hrb-modal-header {
        padding: 20px 25px;
        border-bottom: 1px solid var(--hrb-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        /* background: linear-gradient(135deg, var(--hrb-primary), var(--hrb-primary-dark)); */
        color: white;
    }

    #hrb-anonymous-modal .hrb-modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }

    #hrb-anonymous-modal .hrb-modal-close {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: var(--hrb-transition);
    }

    #hrb-anonymous-modal .hrb-modal-close:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    #hrb-anonymous-modal .hrb-modal-body {
        padding: 20px 25px;
        line-height: 1.6;
        flex: 1;
        overflow-y: auto;
    }

    #hrb-anonymous-modal .hrb-modal-body p {
        margin-bottom: 15px;
    }

    #hrb-anonymous-modal .hrb-modal-body ul {
        margin: 15px 0;
        padding-left: 20px;
    }

    #hrb-anonymous-modal .hrb-modal-body li {
        margin-bottom: 8px;
    }

    #hrb-anonymous-modal .hrb-modal-footer {
        padding: 20px 25px;
        border-top: 1px solid var(--hrb-border);
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        background: var(--hrb-background-light);
        flex-shrink: 0;
        margin-top: auto;
    }

    /* Email field hiding for anonymous bookings */
    .hrb-anonymous-booking-active .hrb-email-field {
        display: none !important;
    }

    .hrb-anonymous-booking-active .hrb-phone-field {
        display: none !important;
    }

    .hrb-anonymous-booking-active .hrb-verification-field {
        display: none !important;
    }

    .hrb-anonymous-booking-active .hrb-name-field {
        display: none !important;
    }

    /* Responsive adjustments for anonymous modal */
    @media (max-height: 600px) {
        #hrb-anonymous-modal .hrb-modal-content {
            max-height: 95vh;
            margin: 10px;
        }
        
        #hrb-anonymous-modal .hrb-modal-body {
            padding: 15px 20px;
        }
        
        #hrb-anonymous-modal .hrb-modal-footer {
            padding: 15px 20px;
        }
    }

    @media (max-width: 480px) {
        #hrb-anonymous-modal .hrb-modal-content {
            width: 95%;
            margin: 10px;
        }
        
        #hrb-anonymous-modal .hrb-modal-header {
            padding: 15px 20px;
        }
        
        #hrb-anonymous-modal .hrb-modal-body {
            padding: 15px 20px;
        }
        
        #hrb-anonymous-modal .hrb-modal-footer {
            padding: 15px 20px;
            flex-direction: column;
            gap: 10px;
        }
        
        #hrb-anonymous-modal .hrb-modal-footer button {
            width: 100%;
        }
    }
</style>

<script>
    jQuery(document).ready(function($) {
        const form = $('#hrb-booking-form-<?php echo $room_id; ?>');
        const extraPeoplePrice = <?php echo floatval($extra_people_price); ?>;
        // Hourly price removed - using 2-4 hour pricing system
        const currencySymbol = '<?php echo esc_js($currency_symbol); ?>';

        // Auto-load time slots if default values are set
        const defaultDate = '<?php echo esc_js($prefill_date); ?>';
        const defaultDuration = '<?php echo esc_js($prefill_duration); ?>';
        
        if (defaultDate && defaultDuration) {
            // Load time slots automatically for default values
            loadTimeSlots(<?php echo $room_id; ?>, defaultDate, defaultDuration);
        }
        
        // Get booking time settings from database
        const bookingStartTime = '<?php echo esc_js(get_option('hrb_booking_start_time', '08:00')); ?>';
        const bookingEndTime = '<?php echo esc_js(get_option('hrb_booking_end_time', '20:00')); ?>';

        // Auto-load time slots if form is pre-filled from search
        const prefillDate = '<?php echo esc_js($prefill_date); ?>';
        const prefillTime = '<?php echo esc_js($prefill_time); ?>';
        const prefillDuration = '<?php echo esc_js($prefill_duration); ?>';

        if (prefillDate && prefillDuration) {
            // If time is also pre-filled, load time slots with callback
            if (prefillTime) {
                loadTimeSlots(<?php echo $room_id; ?>, prefillDate, prefillDuration, function() {
                    // Add a small delay to ensure DOM is fully rendered and scroll events are processed
                    setTimeout(function() {
                        // Auto-select the pre-filled time slot after time slots are loaded
                        const timeSlot = form.find('.hrb-time-slot').filter(function() {
                            const timeText = $(this).find('.hrb-time-slot-time').text();
                            const startTime = timeText.split('-')[0].trim();
                            const isAvailable = $(this).hasClass('available') && !$(this).hasClass('unavailable');
                            return startTime === prefillTime && isAvailable;
                        });
                        
                        if (timeSlot.length) {
                            // Directly trigger time slot selection instead of relying on click
                            const selectedSlot = timeSlot.first();
                            const startTime = selectedSlot.data('start-time');
                            const endTime = selectedSlot.data('end-time');
                            const roomId = <?php echo $room_id; ?>;

                            // Remove previous selection
                            form.find('.hrb-time-slot').removeClass('selected');
                            selectedSlot.addClass('selected');

                            // Update hidden fields
                            $('#start-time-' + roomId).val(startTime);
                            $('#end-time-' + roomId).val(endTime);

                            // Update booking summary
                            updateBookingSummary();
                        }
                    }, 200); // Small delay to ensure DOM is ready and scroll events are processed
                });
            } else {
                // Trigger time slot loading
                loadTimeSlots(<?php echo $room_id; ?>, prefillDate, prefillDuration);
            }
        }

        // Check initial verification status if email is pre-filled
        const initialEmail = form.find('input[name="email"]').val();
        if (initialEmail && initialEmail.trim() !== '') {
            // Small delay to ensure all elements are loaded
            setTimeout(function() {
                updateVerificationContactInfo();
            }, 500);
        }

        // Fallback showMessage function to prevent undefined errors
        window.showMessageFallback = function(type, message) {
            if (!message || message === 'undefined' || message === undefined) {
                return; // Don't show undefined messages
            }

            const alertClass = `hrb-alert hrb-alert-${type}`;
            const alert = $(`<div class="${alertClass}">${message}</div>`);

            // Remove existing alerts
            $('.hrb-alert').remove();

            // Add new alert to form
            form.prepend(alert);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                alert.fadeOut(() => alert.remove());
            }, 5000);
        };

        // Enhanced HRB utils check
        if (!window.HRB || !window.HRB.utils || !window.HRB.utils.showMessage) {
            /* removed debug warn: HRB.utils.showMessage fallback */
            window.HRB = window.HRB || {};
            window.HRB.utils = window.HRB.utils || {};
            window.HRB.utils.showMessage = window.showMessageFallback;
        }

        // Function declarations first
        // Global verification state
        let verificationCodeSent = false;
        let verificationVerified = false;
        let resendTimer = null;

        function validateStep(stepNumber) {
            switch (stepNumber) {
                case 1:
                    // Validate date, duration, and time slot selection
                    const date = form.find('input[name="booking_date"]').val();
                    const duration = form.find('select[name="duration"]').val();
                    const startTime = form.find('input[name="start_time"]').val();

                    if (!date) {
                        showValidationError('<?php _e('Please select a booking date', 'hourly-room-booking'); ?>');
                        return false;
                    }

                    if (!duration) {
                        showValidationError('<?php _e('Please select a duration', 'hourly-room-booking'); ?>');
                        return false;
                    }

                    if (!startTime) {
                        showValidationError('<?php _e('Please select a time slot', 'hourly-room-booking'); ?>');
                        return false;
                    }
                    break;

                case 2:
                    // Extras step - no required validation
                    break;

                case 3:
                    // Check if this is an anonymous booking
                    const isAnonymous = $('#hrb-anonymous-booking').is(':checked');
                    
                    if (isAnonymous) {
                        // For anonymous bookings, validate first_name is required (same as admin)
                        const firstName = form.find('input[name="first_name"]').val();
                        
                        if (!firstName || !firstName.trim()) {
                            // Ensure name field is visible for validation
                            form.find('.hrb-name-field').show();
                            form.find('input[name="first_name"]').focus();
                            showValidationError('<?php _e('Bitte geben Sie einen Namen ein', 'hourly-room-booking'); ?>');
                            return false;
                        }
                    } else {
                        // For regular bookings, validate all customer details
                        const firstName = form.find('input[name="first_name"]').val();
                        const lastName = form.find('input[name="last_name"]').val();
                        const email = form.find('input[name="email"]').val();
                        const phone = form.find('input[name="phone"]').val();

                        if (!firstName.trim()) {
                            showValidationError('<?php _e('Please enter your first name', 'hourly-room-booking'); ?>');
                            return false;
                        }

                        if (!email.trim() || !isValidEmail(email)) {
                            showValidationError('<?php _e('Please enter a valid email address', 'hourly-room-booking'); ?>');
                            return false;
                        }

                        // Validate verification
                        if (!verificationVerified) {
                            showValidationError('<?php _e('Please verify your contact information to continue', 'hourly-room-booking'); ?>');
                            return false;
                        }
                    }

                    break;

                case 4:
                    // Validate payment method
                    const paymentMethod = form.find('input[name="payment_method"]:checked').val();
                    if (!paymentMethod) {
                        showValidationError('<?php _e('Please select a payment method', 'hourly-room-booking'); ?>');
                        return false;
                    }
                    break;
                    
                case 5:
                    // Validate terms acceptance
                    const termsAccepted = form.find('input[name="accept_terms"]').is(':checked');
                    if (!termsAccepted) {
                        showValidationError('<?php _e('Bitte akzeptieren Sie die AGB und Datenschutzbestimmungen, um fortzufahren.', 'hourly-room-booking'); ?>');
                        return false;
                    }
                    break;
            }

            return true;
        }

        function goToStep(stepNumber) {
            // Hide all steps
            form.find('.hrb-form-step-content').removeClass('active');
            form.find('.hrb-step').removeClass('active');

            // Show target step
            form.find('.hrb-form-step-content[data-step="' + stepNumber + '"]').addClass('active');
            form.find('.hrb-step[data-step="' + stepNumber + '"]').addClass('active');

            // Update step progress indicators
            form.find('.hrb-step').each(function() {
                const step = parseInt($(this).data('step'));
                if (step < stepNumber) {
                    $(this).addClass('completed');
                } else {
                    $(this).removeClass('completed');
                }
            });

            // Scroll to top of form
            if (form.length && form.get(0).scrollIntoView) {
                form.get(0).scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }

            // Update booking summary
            updateBookingSummary();

            // If this is step 3 (Details step), check verification status
            if (stepNumber === 3) {
                setTimeout(function() {
                    updateVerificationContactInfo();
                }, 100);
            }
        }

        function showValidationError(message) {
            // Remove any existing error messages
            form.find('.hrb-validation-error').remove();

            // Create and show error message
            const errorHtml = '<div class="hrb-validation-error">' + message + '</div>';
            const errorElement = $(errorHtml);
            form.find('.hrb-form-step-content.active').prepend(errorElement);

            // Check if we're in a modal and scroll accordingly
            const modalBody = form.closest('.hrb-modal-body');
            if (modalBody.length) {
                // We're in a modal - scroll to top of modal body to show error
                modalBody.animate({
                    scrollTop: 0
                }, 500);
            } else {
                // We're on a regular page - scroll the main window
                $('html, body').animate({
                    scrollTop: errorElement.offset().top - 100
                }, 500);
            }

            // Remove error after 5 seconds
            setTimeout(function() {
                form.find('.hrb-validation-error').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }


        // Function to apply payment method restriction
        function applyPaymentMethodRestriction() {
            const duration = parseInt(form.find('select[name="duration"]').val());
            const onsiteOption = $('#onsite-payment-option');

            if (duration >= 4) {
                onsiteOption.hide();
                form.find('input[name="payment_method"][value="paypal"]').prop('checked', true);
            } else {
                onsiteOption.show();
            }
        }

        // Apply payment method restriction on page load
        applyPaymentMethodRestriction();

        // Handle payment method restriction for long bookings
        form.find('select[name="duration"]').on('change', function() {
            applyPaymentMethodRestriction();
            updateBookingSummary();
            checkBookingDuration(); // Check if anonymous booking option should be shown
        });


        // Handle extras selection change
        form.find('input[name="extras[]"]').on('change', function() {
            updateBookingSummary();
        });

        // Handle clicking on extra items to toggle checkbox
        form.on('click', '.hrb-extra-item', function(e) {
            // Don't trigger if clicking on the checkbox itself
            if (e.target.type === 'checkbox') {
                return;
            }

            const checkbox = $(this).find('input[type="checkbox"]');
            checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
        });

        // Handle additional people input change
        form.find('input[name="extra_people"]').on('change', function() {
            updateBookingSummary();
        });

        // Handle payment method change
        form.find('input[name="payment_method"]').on('change', function() {
            updateBookingSummary();
        });

        // Handle clicking on payment method containers
        form.on('click', '.hrb-payment-method', function(e) {
            // Don't trigger if clicking on the radio button itself
            if (e.target.type === 'radio') {
                return;
            }

            const radio = $(this).find('input[type="radio"]');
            radio.prop('checked', true).trigger('change');
        });


        // Handle form submission
        form.on('submit', function(e) {
            e.preventDefault();

            // Validate the final step (terms acceptance)
            if (!validateStep(5)) {
                return;
            }

            const paymentMethod = form.find('input[name="payment_method"]:checked').val();

            if (paymentMethod === 'paypal') {
                // Handle PayPal payment
                handlePayPalPayment();
            } else {
                // Handle on-site payment
                handleOnsitePayment();
            }
        });

        // Handle step navigation
        $(document).on('click', '.hrb-step-next', function() {
            const currentStep = $(this).closest('.hrb-form-step-content');
            const currentStepNumber = parseInt(currentStep.data('step'));

            // Validate current step before proceeding
            if (!validateStep(currentStepNumber)) {
                return;
            }

            // Move to next step
            goToStep(currentStepNumber + 1);

            // Update review section when moving to confirmation step
            if (currentStepNumber === 4) {
                updateReviewSection();
            }
        });

        $(document).on('click', '.hrb-step-prev', function() {
            const currentStep = $(this).closest('.hrb-form-step-content');
            const currentStepNumber = parseInt(currentStep.data('step'));

            // Move to previous step
            goToStep(currentStepNumber - 1);
        });

        // Enhanced Step Navigation - Click on completed steps to go back
        $(document).on('click', '.hrb-step.completed', function() {
            const targetStep = parseInt($(this).data('step'));
            const currentStep = parseInt($('.hrb-form-step-content.active').data('step'));

            // Only allow clicking on previous completed steps
            if (targetStep < currentStep) {
                goToStep(targetStep);
            }
        });

        // Add visual feedback for clickable steps
        $(document).on('mouseenter', '.hrb-step.completed', function() {
            const targetStep = parseInt($(this).data('step'));
            const currentStep = parseInt($('.hrb-form-step-content.active').data('step'));

            if (targetStep < currentStep) {
                $(this).addClass('hrb-step-clickable');
            }
        });

        $(document).on('mouseleave', '.hrb-step.completed', function() {
            $(this).removeClass('hrb-step-clickable');
        });

        function loadTimeSlots(roomId, date, duration, callback) {
            const container = $('#time-slots-' + roomId);

            if (!date || !duration) {
                container.html(`
                <div class="hrb-loading-message">
                    <div class="hrb-loading-text"><?php _e('Please select a date and duration first', 'hourly-room-booking'); ?></div>
                </div>
            `);
                if (callback) callback();
                return;
            }

            container.html(`
            <div class="hrb-loading-message">
                <div class="hrb-loading-spinner"></div>
            </div>
        `);

            // AJAX call to get available time slots
            $.ajax({
                url: hrbAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hrb_get_available_time_slots',
                    nonce: hrbAjax.nonce,
                    room_id: roomId,
                    date: date,
                    duration: duration
                },
                success: function(response) {
                    
                    if (response.success && response.data.slots) {
                        displayAvailableTimeSlots(response.data.slots, roomId);
                    } else {
                        container.html('<div class="hrb-no-slots"><?php _e('No available time slots for this date and duration', 'hourly-room-booking'); ?></div>');
                    }
                    // Execute callback after time slots are loaded
                    if (callback) callback();
                },
                error: function() {
                    container.html('<div class="hrb-error-message"><?php _e('Error loading time slots. Please try again.', 'hourly-room-booking'); ?></div>');
                    // Execute callback even on error
                    if (callback) callback();
                }
            });
        }

        function displayAvailableTimeSlots(slots, roomId) {
            const container = $('#time-slots-' + roomId);
            let html = '';

            if (slots.length === 0) {
                html = '<div class="hrb-no-slots"><?php _e('No available time slots for this date and duration', 'hourly-room-booking'); ?></div>';
            } else {
                html = '<div class="hrb-time-slots-grid">';
                slots.forEach(function(slot) {
                    const isAvailable = slot.available;
                    const statusClass = isAvailable ? 'available' : 'unavailable';
                    const statusText = isAvailable ? '<?php _e('Available', 'hourly-room-booking'); ?>' : '<?php _e('Unavailable', 'hourly-room-booking'); ?>';
                    
                    html += `
                    <div class="hrb-time-slot ${statusClass}" data-start-time="${slot.start_time}" data-end-time="${slot.end_time}" data-available="${isAvailable}">
                        <div class="hrb-time-slot-time">${slot.label}</div>
                        <div class="hrb-time-slot-status">${statusText}</div>
                    </div>
                `;
                });
                html += '</div>';
            }

            container.html(html);

            // Bind time slot selection
            container.find('.hrb-time-slot.available').on('click', function() {
                const selectedSlot = $(this);
                const startTime = selectedSlot.data('start-time');
                const endTime = selectedSlot.data('end-time');

                // Remove previous selection
                container.find('.hrb-time-slot').removeClass('selected');
                selectedSlot.addClass('selected');

                // Update hidden fields
                $('#start-time-' + roomId).val(startTime);
                $('#end-time-' + roomId).val(endTime);

                // Update booking summary
                updateBookingSummary();
            });
        }

        // Event handlers for date and duration changes
        form.find('input[name="booking_date"], select[name="duration"]').on('change', function() {
            const date = form.find('input[name="booking_date"]').val();
            const duration = form.find('select[name="duration"]').val();
            const roomId = <?php echo $room_id; ?>;

            loadTimeSlots(roomId, date, duration);
            updateBookingSummary();
            checkBookingDuration(); // Check if anonymous booking option should be shown
        });

        function generateTimeSlots(duration) {
            const slots = [];
            const durationHours = parseInt(duration);
            
            // Parse start and end times from settings
            const startHour = parseInt(bookingStartTime.split(':')[0]);
            const startMinute = parseInt(bookingStartTime.split(':')[1]);
            const endHour = parseInt(bookingEndTime.split(':')[0]);
            const endMinute = parseInt(bookingEndTime.split(':')[1]);
            
            // Convert to minutes for easier calculation
            const startTimeMinutes = startHour * 60 + startMinute;
            const endTimeMinutes = endHour * 60 + endMinute;
            const durationMinutes = durationHours * 60;
            
            // Generate time slots in 30-minute intervals
            for (let timeMinutes = startTimeMinutes; timeMinutes <= endTimeMinutes - durationMinutes; timeMinutes += 30) {
                const startHourSlot = Math.floor(timeMinutes / 60);
                const startMinuteSlot = timeMinutes % 60;
                const endTimeMinutesSlot = timeMinutes + durationMinutes;
                const endHourSlot = Math.floor(endTimeMinutesSlot / 60);
                const endMinuteSlot = endTimeMinutesSlot % 60;
                
                // Skip this slot if it exceeds the end time
                if (endTimeMinutesSlot > endTimeMinutes) {
                    continue;
                }
                
                const startTime = String(startHourSlot).padStart(2, '0') + ':' + String(startMinuteSlot).padStart(2, '0');
                const endTime = (endHourSlot === 24 && endMinuteSlot === 0)
                    ? '24:00'
                    : String(endHourSlot).padStart(2, '0') + ':' + String(endMinuteSlot).padStart(2, '0');
                const display = startTime + ' - ' + endTime;

                // Calculate price based on duration
                let price = 45; // Default 2 hours
                if (durationHours === 3) price = 50;
                else if (durationHours === 4) price = 60;
                else if (durationHours > 4) price = 60 + ((durationHours - 4) * 10);

                slots.push({
                    start: startTime,
                    end: endTime,
                    display: display,
                    price: price
                });
            }

            return slots;
        }


        function updateReviewSection() {
            const formData = HRB.utils.getFormData(form);
            const extraPeople = parseInt(form.find('input[name="extra_people"]').val()) || 0;
            const selectedExtras = form.find('input[name="extras[]"]:checked');

            // Format date to German format (d.m.Y)
            const dateParts = formData.booking_date.split('-');
            const formattedDate = dateParts[2] + '.' + dateParts[1] + '.' + dateParts[0];
            $('#review-date').text(formattedDate);
            
            // Format time to 24-hour format (H:i)
            const startTime = formData.start_time.substring(0, 5); // Remove seconds
            const endTime = formData.end_time.substring(0, 5); // Remove seconds
            $('#review-time').text(startTime + ' - ' + endTime);
            $('#review-duration').text(formData.duration + ' <?php _e('hours', 'hourly-room-booking'); ?>');
            $('#review-customer-name').text(formData.first_name + ' ' + formData.last_name);
            $('#review-email').text(formData.email);
            $('#review-phone').text(formData.phone);
            $('#review-payment-method').text(
                formData.payment_method === 'paypal' ? 'PayPal' : '<?php _e('On-site Payment', 'hourly-room-booking'); ?>'
            );

            // Add extras and additional people info to confirmation section
            const bookingDetails = $('.hrb-booking-review .hrb-col-6').first();

            // Remove existing extras/people info
            bookingDetails.find('.hrb-review-extras, .hrb-review-people').remove();

            // Add additional people if any
            if (extraPeople > 0) {
                bookingDetails.append(`
                <div class="hrb-review-item hrb-review-people">
                    <strong><?php _e('Additional People:', 'hourly-room-booking'); ?></strong>
                    <span>${extraPeople}</span>
                </div>
            `);
            }

            // Add selected extras if any
            if (selectedExtras.length > 0) {
                let extrasText = [];
                selectedExtras.each(function() {
                    extrasText.push($(this).data('name'));
                });

                bookingDetails.append(`
                <div class="hrb-review-item hrb-review-extras">
                    <strong><?php _e('Extras:', 'hourly-room-booking'); ?></strong>
                    <span>${extrasText.join(', ')}</span>
                </div>
            `);
            }
        }

        function handlePayPalPayment() {
            // Validate anonymous booking name before payment
            const isAnonymous = $('#hrb-anonymous-booking').is(':checked');
            if (isAnonymous) {
                const firstName = form.find('input[name="first_name"]').val();
                
                if (!firstName || !firstName.trim()) {
                    // Ensure name field is visible and focusable
                    form.find('.hrb-name-field').show();
                    $('html, body').animate({
                        scrollTop: form.find('input[name="first_name"]').offset().top - 100
                    }, 500);
                    form.find('input[name="first_name"]').focus();
                    showValidationError('<?php _e('Bitte geben Sie einen Namen ein', 'hourly-room-booking'); ?>');
                    return;
                }
            }

            // Get form data
            const formData = getFormData();

            // Validate required fields before creating PayPal order
            if (!formData.first_name || (!isAnonymous && !formData.email)) {
                showValidationError('Please fill in all required customer details before proceeding with payment.');
                return;
            }

            // Show loading state with spinner
            showPaymentLoading();

            // Create PayPal order
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'hrb_create_paypal_order',
                    nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>',
                    booking_data: JSON.stringify(formData)
                },
                success: function(response) {
                    if (response.success) {
                        // Show redirect message
                        showPaymentRedirect();
                        // Redirect to PayPal for payment
                        setTimeout(function() {
                            window.location.href = response.data.approval_url;
                        }, 1500);
                    } else {
                        hidePaymentLoading();
                        showValidationError(response.data || 'Failed to create PayPal order');
                    }
                },
                error: function() {
                    hidePaymentLoading();
                    showValidationError('Network error occurred. Please try again.');
                }
            });
        }

        function handleOnsitePayment() {
            // Validate anonymous booking name before payment
            const isAnonymous = $('#hrb-anonymous-booking').is(':checked');
            if (isAnonymous) {
                const firstName = form.find('input[name="first_name"]').val();
                
                if (!firstName || !firstName.trim()) {
                    // Ensure name field is visible and focusable
                    form.find('.hrb-name-field').show();
                    $('html, body').animate({
                        scrollTop: form.find('input[name="first_name"]').offset().top - 100
                    }, 500);
                    form.find('input[name="first_name"]').focus();
                    showValidationError('<?php _e('Bitte geben Sie einen Namen ein', 'hourly-room-booking'); ?>');
                    return;
                }
            }
            
            // Show loading state
            showPaymentLoading();
            submitBooking();
        }

        function getFormData() {
            const formData = {
                room_id: form.find('input[name="room_id"]').val(),
                booking_date: form.find('input[name="booking_date"]').val(),
                start_time: form.find('input[name="start_time"]').val(),
                end_time: form.find('input[name="end_time"]').val(),
                extra_people: form.find('input[name="extra_people"]').val() || 0,
                extras: [],
                payment_method: form.find('input[name="payment_method"]:checked').val(),
                special_requests: form.find('textarea[name="special_requests"]').val(),
                first_name: form.find('input[name="first_name"]').val(),
                last_name: form.find('input[name="last_name"]').val(),
                email: form.find('input[name="email"]').val(),
                phone: form.find('input[name="phone"]').val(),
                is_anonymous: form.find('input[name="is_anonymous"]:checked').val() || '0',
            };

            // Get selected extras
            form.find('input[name="extras[]"]:checked').each(function() {
                formData.extras.push($(this).val());
            });

            return formData;
        }

        function showPaymentLoading() {
            const submitBtn = form.find('.hrb-submit-btn');
            const originalText = submitBtn.text();

            // Disable button and show loading state
            submitBtn.prop('disabled', true)
                .addClass('hrb-loading-message')
                .html('<div class="hrb-loading-spinner"></div> Processing Payment...');

            // Store original text for restoration
            submitBtn.data('original-text', originalText);

            // Add loading overlay to form
            form.addClass('hrb-payment-processing');

            // Disable all form inputs
            form.find('input, select, textarea, button').prop('disabled', true);
        }

        function hidePaymentLoading() {
            const submitBtn = form.find('.hrb-submit-btn');
            const originalText = submitBtn.data('original-text') || '<?php _e('Book Now', 'hourly-room-booking'); ?>';

            // Restore button state
            submitBtn.prop('disabled', false)
                .removeClass('hrb-loading-spinner')
                .text(originalText);

            // Remove loading overlay
            form.removeClass('hrb-payment-processing');

            // Re-enable all form inputs
            form.find('input, select, textarea, button').prop('disabled', false);
        }

        function showPaymentRedirect() {
            const submitBtn = form.find('.hrb-submit-btn');
            submitBtn.html('<span class="hrb-loading-spinner"></span> Redirecting to PayPal...');

            // Show redirect message
            const redirectMessage = $('<div class="hrb-payment-redirect-message">' +
                '<div class="hrb-redirect-icon">🔄</div>' +
                '<h3>Redirecting to PayPal</h3>' +
                '<p>Please wait while we redirect you to PayPal to complete your payment...</p>' +
                '<p class="hrb-redirect-note">Do not close this window or refresh the page.</p>' +
                '</div>');

            form.prepend(redirectMessage);
        }

        function submitBooking() {
            // Validate anonymous booking name before submission
            const isAnonymous = $('#hrb-anonymous-booking').is(':checked');
            if (isAnonymous) {
                const firstName = form.find('input[name="first_name"]').val();
                
                if (!firstName || !firstName.trim()) {
                    // Ensure name field is visible and focusable
                    form.find('.hrb-name-field').show();
                    // Remove required attribute temporarily to prevent browser validation error
                    form.find('input[name="first_name"]').prop('required', false);
                    // Scroll to name field
                    $('html, body').animate({
                        scrollTop: form.find('input[name="first_name"]').offset().top - 100
                    }, 500);
                    form.find('input[name="first_name"]').focus();
                    // Re-add required attribute
                    form.find('input[name="first_name"]').prop('required', true);
                    
                    hidePaymentLoading();
                    showValidationError('<?php _e('Bitte geben Sie einen Namen ein', 'hourly-room-booking'); ?>');
                    return false;
                }
            }
            
            HRB.app.submitBooking(form).then(response => {
                // Redirect to success page
                window.location.href = '<?php echo site_url('/booking-success/'); ?>?ref=' + response.booking_reference;
            }).catch(error => {
                // Hide loading state on error
                hidePaymentLoading();

                let errorMessage;
                if (error && typeof error === 'string' && error !== 'undefined') {
                    errorMessage = error;
                } else if (error && error.message && error.message !== 'undefined') {
                    errorMessage = error.message;
                } else {
                    errorMessage = '<?php _e('Booking failed. Please try again.', 'hourly-room-booking'); ?>';
                }
                HRB.utils.showMessage('error', errorMessage);
            });
        }

        function updateAvailableExtras() {
            const bookingDate = form.find('input[name="booking_date"]').val();
            const startTime = form.find('input[name="start_time"]').val();
            const endTime = form.find('input[name="end_time"]').val();

            if (!bookingDate || !startTime || !endTime) {
                return;
            }

            // Show loading state
            const extrasContainer = form.find('.hrb-extras-list');
            const originalContent = extrasContainer.html();
            extrasContainer.html(`
            <div class="hrb-loading-message">
                <div class="hrb-loading-spinner"></div>
                <div class="hrb-loading-text"><?php _e('Updating available extras...', 'hourly-room-booking'); ?></div>
            </div>
        `);

            // AJAX call to get available extras based on stock
            $.ajax({
                url: hrbAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hrb_get_available_extras',
                    nonce: hrbAjax.nonce,
                    booking_date: bookingDate,
                    start_time: startTime,
                    end_time: endTime
                },
                success: function(response) {
                    if (response.success && response.data.extras) {
                        displayAvailableExtras(response.data.extras);
                    } else {
                        extrasContainer.html('<div class="hrb-no-extras"><?php _e('No extras available for the selected date and time.', 'hourly-room-booking'); ?></div>');
                    }
                },
                error: function() {
                    // Restore original content on error
                    extrasContainer.html(originalContent);
                }
            });
        }

        function displayAvailableExtras(extras) {
            const extrasContainer = form.find('.hrb-extras-list');
            let html = '';

            if (extras.length === 0) {
                html = '<div class="hrb-no-extras"><?php _e('No extras available for the selected date and time.', 'hourly-room-booking'); ?></div>';
            } else {
                extras.forEach(function(extra) {
                    const maxQuantity = extra.available_quantity || 999;
                    const stockInfo = extra.track_stock ?
                        (maxQuantity > 0 ? ` (${maxQuantity} available)` : ' (Out of Stock)') : '';

                    if (maxQuantity > 0) {
                        html += `
                        <div class="hrb-extra-item">
                           
                            <div class="hrb-extra-content">
                             
                                <div class="hrb-extra-header">
                                <input type="checkbox"
                                   name="extras[]"
                                   value="${extra.id}"
                                   data-price="${extra.price}"
                                   data-name="${extra.name}"
                                   data-max-quantity="${maxQuantity}" class="hrb-extra-checkbox">
                                    <div class="hrb-extra-icon">
                                        ${extra.image_url ? `<img src="${extra.image_url}" alt="${extra.name}">` : '⭐'}
                                    </div>
                                    <div class="hrb-extra-details">
                                        <div class="hrb-extra-title">${extra.name}</div>
                                    </div>
                                    <div class="hrb-extra-price">+${window.HRB.utils.formatPrice(extra.price)}</div>
                                </div>
                                ${extra.description ? `<div class="hrb-extra-description">${extra.description}</div>` : ''}
                            </div>
                        </div>`;
                    }
                });
            }

            extrasContainer.html(html);

            // Re-bind change event handlers for new checkboxes
            extrasContainer.find('input[name="extras[]"]').on('change', function() {
                updateBookingSummary();
            });
        }

        // Calculate duration price using new pricing system
        function calculateDurationPrice(duration) {
            const roomData = <?php echo json_encode([
                                    'price_2_hours' => floatval($room->price_2_hours ?? 0),
                                    'price_3_hours' => floatval($room->price_3_hours ?? 0),
                                    'price_4_hours' => floatval($room->price_4_hours ?? 0),
                                    'price_extra_hour' => floatval($room->price_extra_hour ?? 0)
                                ]); ?>;

            const globalPrices = {
                price_2_hours: <?php echo floatval(get_option('hrb_price_2_hours', 0)); ?>,
                price_3_hours: <?php echo floatval(get_option('hrb_price_3_hours', 0)); ?>,
                price_4_hours: <?php echo floatval(get_option('hrb_price_4_hours', 0)); ?>,
                price_extra_hour: <?php echo floatval(get_option('hrb_price_extra_hour', 0)); ?>
            };

            let price = 0;
            let useRoomPrice = false;

            if (duration == 2 && roomData.price_2_hours > 0) {
                price = roomData.price_2_hours;
                useRoomPrice = true;
            } else if (duration == 3 && roomData.price_3_hours > 0) {
                price = roomData.price_3_hours;
                useRoomPrice = true;
            } else if (duration == 4 && roomData.price_4_hours > 0) {
                price = roomData.price_4_hours;
                useRoomPrice = true;
            } else if (duration > 4) {
                // For durations > 4 hours, use 4-hour price + extra hours
                if (roomData.price_4_hours > 0) {
                    price = roomData.price_4_hours;
                    useRoomPrice = true;

                    // Add extra hours using room-specific or global extra hour price
                    const extraHours = duration - 4;
                    const extraHourPrice = roomData.price_extra_hour > 0 ?
                        roomData.price_extra_hour :
                        globalPrices.price_extra_hour;

                    if (extraHourPrice > 0) {
                        price += extraHours * extraHourPrice;
                    }
                }
            }

            // If room has specific pricing, use it
            if (useRoomPrice) {
                return price;
            }

            // Fallback to global pricing
            let globalPrice = 0;
            if (duration == 2) {
                globalPrice = globalPrices.price_2_hours;
            } else if (duration == 3) {
                globalPrice = globalPrices.price_3_hours;
            } else if (duration == 4) {
                globalPrice = globalPrices.price_4_hours;
            } else if (duration > 4) {
                globalPrice = globalPrices.price_4_hours;
                const extraHours = duration - 4;
                const extraHourPrice = globalPrices.price_extra_hour;
                if (extraHourPrice > 0) {
                    globalPrice += extraHours * extraHourPrice;
                }
            }

            // If global pricing is available, use it
            if (globalPrice > 0) {
                return globalPrice;
            }

            // No pricing found - return 0
            return 0;
        }

        function updateBookingSummary() {
            const selectedSlot = form.find('.hrb-time-slot.selected');
            const extraPeople = parseInt(form.find('input[name="extra_people"]').val()) || 0;
            const selectedExtras = form.find('input[name="extras[]"]:checked');
            const duration = parseInt(form.find('select[name="duration"]').val()) || 0;
            const paymentMethod = form.find('input[name="payment_method"]:checked').val();

            // Update booking summary


            if (!selectedSlot.length || !duration) {
                $('#booking-summary-<?php echo $room_id; ?>').html(`
                <div class="hrb-summary-title"><?php _e('Booking Summary', 'hourly-room-booking'); ?></div>
                <div class="hrb-summary-placeholder">
                    <?php _e('Please complete the form to see pricing details', 'hourly-room-booking'); ?>
                </div>
            `);
                return;
            }

            // Calculate base price using new pricing system
            const basePrice = calculateDurationPrice(duration);

            // Calculate extras price
            let extrasPrice = 0;
            let extrasDetails = [];
            selectedExtras.each(function() {
                const price = parseFloat($(this).data('price')) || 0;
                const name = $(this).data('name') || '';
                extrasPrice += price;
                extrasDetails.push(
                    `<span class="hrb-extra-summary-name">${name}</span> <span class="hrb-extra-summary-price">${window.HRB.utils.formatPrice(price)}</span>`
                );
            });

            // Calculate additional people price
            const additionalPeoplePrice = extraPeople * extraPeoplePrice;

            // Calculate subtotal
            const subtotal = basePrice + extrasPrice + additionalPeoplePrice;

            // Calculate PayPal fee only if PayPal is explicitly selected
            let paypalFee = 0;
            if (paymentMethod && paymentMethod === 'paypal') {
                paypalFee = subtotal * 0.03; // 3% fee
            }

            // Calculate VAT (19% in Germany)
            const taxRate = <?php echo floatval(get_option('hrb_tax_rate', 19)); ?>;
            const taxAmount = subtotal * (taxRate / 100);
            
            // Calculate total
            const total = subtotal + taxAmount + paypalFee;

            // Build summary HTML
            let summaryHtml = `
            <div class="hrb-summary-title"><?php _e('Booking Summary', 'hourly-room-booking'); ?></div>
            <div class="hrb-summary-content">
                <div class="hrb-summary-item">
                    <span><?php echo esc_html($room->name); ?> (${duration}h)</span>
                    <span>${window.HRB.utils.formatPrice(basePrice)}</span>
                </div>
        `;

            if (extraPeople > 0) {
                summaryHtml += `
                <div class="hrb-summary-item">
                    <span><?php _e('Additional People', 'hourly-room-booking'); ?> (${extraPeople})</span>
                    <span>${window.HRB.utils.formatPrice(additionalPeoplePrice)}</span>
                </div>
            `;
            }

            if (extrasDetails.length > 0) {
                summaryHtml += `<div class="hrb-summary-section"><strong><?php _e('Extras', 'hourly-room-booking'); ?></strong></div>`;
                extrasDetails.forEach(detail => {
                    summaryHtml += `<div class="hrb-summary-item hrb-summary-extra">${detail}</div>`;
                });
            }

            // Add subtotal line
            summaryHtml += `
                <div class="hrb-summary-item hrb-summary-subtotal">
                    <span><strong>Zwischensumme</strong></span>
                    <span><strong>${window.HRB.utils.formatPrice(subtotal)}</strong></span>
                </div>
            `;

            // Add VAT line only if tax rate is greater than 0
            if (taxRate > 0) {
                summaryHtml += `
                    <div class="hrb-summary-item hrb-summary-vat">
                        <span>zzgl. ${taxRate}% MwSt.</span>
                        <span>${window.HRB.utils.formatPrice(taxAmount)}</span>
                    </div>
                `;
            }

            if (paypalFee > 0 && paymentMethod === 'paypal') {
                summaryHtml += `
                <div class="hrb-summary-item hrb-summary-fee">
                    <span><?php _e('PayPal Fee (3%)', 'hourly-room-booking'); ?></span>
                    <span>${window.HRB.utils.formatPrice(paypalFee)}</span>
                </div>
            `;
            }

            summaryHtml += `
                <div class="hrb-summary-item hrb-summary-total">
                    <span><strong><?php _e('Total', 'hourly-room-booking'); ?></strong></span>
                    <span><strong>${window.HRB.utils.formatPrice(total)}</strong></span>
                </div>
            `;
            
            // Add VAT message if tax rate is 0 or blank
            if (taxRate === 0) {
                summaryHtml += `
                    <div class="hrb-vat-message">
                        <small><?php _e('Preise verstehen sich inkl. der gesetzlichen Mehrwertsteuer.', 'hourly-room-booking'); ?></small>
                    </div>
                `;
            }
            
            summaryHtml += `</div>`;

            $('#booking-summary-<?php echo $room_id; ?>').html(summaryHtml);
        }

        // Handle time slot selection
        $(document).on('click', '.hrb-time-slot', function() {
            form.find('.hrb-time-slot').removeClass('selected');
            $(this).addClass('selected');

            const startTime = $(this).data('start-time');
            const endTime = $(this).data('end-time');

            form.find('#start-time-<?php echo $room_id; ?>').val(startTime);
            form.find('#end-time-<?php echo $room_id; ?>').val(endTime);

            // Update available extras based on stock
            updateAvailableExtras();

            updateBookingSummary();
        });

        // Update verification displays when email/phone changes
        form.find('input[name="email"], input[name="phone"]').on('input', function() {
            // If email is being changed, reset verification state
            if ($(this).attr('name') === 'email') {
                resetVerificationState();
            }
            updateVerificationContactInfo();
        });

        // Update verification contact info on step load
        updateVerificationContactInfo();

        // Verification method change handler
        $('input[name="verification_method"]').on('change', function() {
            const method = $(this).val();
            updateVerificationMethodDisplay(method);
        });

        // Verification handlers
        $('#send-verification-code').on('click', function() {
            sendVerificationCode();
        });

        $('#resend-verification-code').on('click', function() {
            sendVerificationCode(true);
        });

        $('#verification-code-<?php echo $room_id; ?>').on('input', function() {
            const code = $(this).val();
            if (code.length === 6) {
                verifyCode(code);
            }
        });

        // Change Email button handler
        $('#change-email-button').on('click', function() {
            // Scroll to the email field in the customer details section
            const emailField = form.find('input[name="email"]');
            if (emailField.length) {
                // Scroll to the email field with smooth animation
                $('html, body').animate({
                    scrollTop: emailField.offset().top - 100
                }, 500);
                
                // Focus on the email field after a short delay
                setTimeout(function() {
                    emailField.focus();
                    emailField.select(); // Select all text for easy replacement
                }, 600);
            }
        });

        function updateVerificationContactInfo() {
            const email = form.find('input[name="email"]').val();
            const phone = form.find('input[name="phone"]').val();
            $('#verification-email-display').text(email);
            $('#verification-phone-display').text(phone);

            // Check verification status if email is provided
            if (email && isValidEmail(email)) {
                checkVerificationStatus(email);
            } else {
                // Reset verification form to default state for invalid/empty email
                resetVerificationState();
            }
        }

        function resetVerificationState() {
            // Clear any existing verification messages
            $('.hrb-alert-success').remove();
            $('.hrb-alert.hrb-alert-info').show();
            $('.hrb-verification-section').show();
            $('#send-verification-code').show();
            $('.hrb-verification-code-section').hide();
            $('.hrb-resend-section').hide();
            
            // Clear verification code input
            $('#verification-code-<?php echo $room_id; ?>').val('');
            
            // Reset verification state variables
            verificationCompleted = false;
            verificationVerified = false;

            // Reset button to verification state
            $('#details-and-verification-next').prop('disabled', true);
            $('#details-and-verification-next').text('<?php _e('Verify & Continue', 'hourly-room-booking'); ?>');
            
            // Re-enable send verification button
            $('#send-verification-code').prop('disabled', false);
        }

        function checkVerificationStatus(email) {
            // Check if this is an anonymous booking
            const isAnonymous = $('#hrb-anonymous-booking').is(':checked');
            
            if (isAnonymous) {
                // For anonymous bookings, skip verification entirely
                verificationCompleted = true;
                verificationVerified = true;
                $('#details-and-verification-next').prop('disabled', false);
                $('#details-and-verification-next').text('<?php _e('Continue', 'hourly-room-booking'); ?>');
                return;
            }
            
            // Clear previous status messages
            $('.hrb-verification-success, .hrb-verification-error').remove();

            $.ajax({
                url: hrbAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hrb_check_verification_status',
                    nonce: hrbAjax.nonce,
                    email: email
                },
                success: function(response) {
                    if (response.success && response.data.is_verified) {
                        // User is already verified - hide entire verification form
                        $('.hrb-verification-alert').hide();
                        $('.hrb-verification-section').hide();

                        // Remove any existing success messages and add new one
                        $('.hrb-alert-success').remove();
                        $('.hrb-verification-container').prepend('<div class="hrb-alert hrb-alert-success"><strong>✅ ' + response.data.message + '</strong></div>');

                        // Mark as verified and enable next step button
                        verificationCompleted = true;
                        verificationVerified = true;
                        $('#details-and-verification-next').prop('disabled', false);
                        $('#details-and-verification-next').text('<?php _e('Continue', 'hourly-room-booking'); ?>');
                    } else {
                        // User needs verification - show all verification elements
                        $('.hrb-alert-success').remove();
                        $('.hrb-alert.hrb-alert-info').show();
                        $('.hrb-verification-section').show();
                        $('#send-verification-code').show();
                        $('.hrb-verification-code-section').hide();
                        $('.hrb-resend-section').hide();
                        verificationCompleted = false;
                        verificationVerified = false;

                        // Reset button to verification state
                        $('#details-and-verification-next').prop('disabled', true);
                        $('#details-and-verification-next').text('<?php _e('Verify & Continue', 'hourly-room-booking'); ?>');
                    }
                },
                error: function() {
                    // On error, show send button by default
                    $('#send-verification-code').show();
                }
            });
        }

        function updateVerificationMethodDisplay(method) {
            if (method === 'sms') {
                $('#email-verification-info').hide();
                $('#sms-verification-info').show();
                $('#send-button-text').text('<?php _e('Send SMS Code', 'hourly-room-booking'); ?>');
                $('#send-help-text').text('<?php _e('A 6-digit code will be sent to your phone number', 'hourly-room-booking'); ?>');
            } else {
                $('#email-verification-info').show();
                $('#sms-verification-info').hide();
                $('#send-button-text').text('<?php _e('Send Email Code', 'hourly-room-booking'); ?>');
                $('#send-help-text').text('<?php _e('A 6-digit code will be sent to your email address', 'hourly-room-booking'); ?>');
            }
        }

        function sendVerificationCode(isResend = false) {
            const email = form.find('input[name="email"]').val();
            const phone = form.find('input[name="phone"]').val();

            // Get selected verification method
            const verificationMethod = $('input[name="verification_method"]:checked').val() || 'email';

            // Validate required fields based on method
            if (verificationMethod === 'sms') {
                if (!phone) {
                    showValidationError('<?php _e('Phone number is required for SMS verification', 'hourly-room-booking'); ?>');
                    return;
                }
            } else {
                if (!email) {
                    showValidationError('<?php _e('Email address is required', 'hourly-room-booking'); ?>');
                    return;
                }
            }

            const $button = isResend ? $('#resend-verification-code') : $('#send-verification-code');
            const $section = $('.hrb-verification-section');

            // Show loading
            $section.append('<div class="hrb-verification-loading"><div class="hrb-loading-spinner"></div></div>');
            $button.prop('disabled', true);

            // Clear previous messages
            $('.hrb-verification-success, .hrb-verification-error').remove();

            $.ajax({
                url: hrbAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hrb_send_verification_code',
                    nonce: hrbAjax.nonce,
                    email: email,
                    phone: phone,
                    type: verificationMethod
                },
                success: function(response) {
                    $('.hrb-verification-loading').remove();

                    if (response.success) {
                        // Check if customer is already verified
                        if (response.data && response.data.includes('already verified')) {
                            // User is already verified - hide entire verification form
                            $('.hrb-alert.hrb-alert-info').hide();
                            $('.hrb-verification-section').hide();

                            // Remove any existing success messages and add new one
                            $('.hrb-alert-success').remove();
                            $('.hrb-alert.hrb-alert-info').after('<div class="hrb-alert hrb-alert-success"><strong>✅ <?php _e('Customer is already verified! You can proceed with booking.', 'hourly-room-booking'); ?></strong></div>');

                            // Mark as verified and enable next step button
                            verificationCompleted = true;
                            verificationVerified = true;
                            $('#details-and-verification-next').prop('disabled', false);
                            $('#details-and-verification-next').text('<?php _e('Continue', 'hourly-room-booking'); ?>');
                            return;
                        }

                        $('.hrb-verification-code-section').show();
                        $('.hrb-resend-section').show();
                        $button.hide();

                        verificationCodeSent = true;

                        // Dynamic success message based on verification method
                        const successMessage = verificationMethod === 'sms' ?
                            '<?php _e('SMS verification code sent! Please check your phone.', 'hourly-room-booking'); ?>' :
                            '<?php _e('Email verification code sent! Please check your email.', 'hourly-room-booking'); ?>';

                        $section.prepend('<div class="hrb-verification-success">' + successMessage + '</div>');

                        // Start countdown timer
                        startResendTimer();

                        setTimeout(() => {
                            $('.hrb-verification-success').fadeOut(() => $('.hrb-verification-success').remove());
                        }, 5000);
                    } else {
                        $section.prepend('<div class="hrb-verification-error">' + (response.data || '<?php _e('Failed to send verification code. Please try again.', 'hourly-room-booking'); ?>') + '</div>');
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    $('.hrb-verification-loading').remove();
                    $section.prepend('<div class="hrb-verification-error"><?php _e('Network error. Please check your connection and try again.', 'hourly-room-booking'); ?></div>');
                    $button.prop('disabled', false);
                }
            });
        }

        function verifyCode(code) {
            const email = form.find('input[name="email"]').val();
            const phone = form.find('input[name="phone"]').val();
            const $section = $('.hrb-verification-section');

            // Show loading
            $section.append('<div class="hrb-verification-loading"><div class="hrb-loading-spinner"></div></div>');

            // Clear previous messages
            $('.hrb-verification-success, .hrb-verification-error').remove();

            $.ajax({
                url: hrbAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hrb_verify_code',
                    nonce: hrbAjax.nonce,
                    email: email,
                    phone: phone,
                    code: code
                },
                success: function(response) {
                    $('.hrb-verification-loading').remove();

                    if (response.success) {
                        verificationVerified = true;
                        $('#details-and-verification-next').prop('disabled', false);
                        $('#details-and-verification-next').text('<?php _e('Continue', 'hourly-room-booking'); ?>');

                        $section.prepend('<div class="hrb-verification-success"><?php _e('Verification successful!', 'hourly-room-booking'); ?></div>');

                        // Hide verification sections
                        $('.hrb-verification-code-section, .hrb-resend-section').hide();
                        $('#verification-code-<?php echo $room_id; ?>').prop('disabled', true);

                        // Clear countdown timer
                        if (resendTimer) {
                            clearInterval(resendTimer);
                        }
                    } else {
                        $section.prepend('<div class="hrb-verification-error">' + (response.data || '<?php _e('Invalid verification code. Please try again.', 'hourly-room-booking'); ?>') + '</div>');
                        $('#verification-code-<?php echo $room_id; ?>').val('').focus();

                        setTimeout(() => {
                            $('.hrb-verification-error').fadeOut(() => $('.hrb-verification-error').remove());
                        }, 5000);
                    }
                },
                error: function() {
                    $('.hrb-verification-loading').remove();
                    $section.prepend('<div class="hrb-verification-error"><?php _e('Network error. Please check your connection and try again.', 'hourly-room-booking'); ?></div>');
                    $('#verification-code-<?php echo $room_id; ?>').val('').focus();
                }
            });
        }

        function startResendTimer() {
            let timeLeft = 60; // 60 seconds cooldown
            const $resendButton = $('#resend-verification-code');
            const $resendSection = $('.hrb-resend-section');

            $resendButton.prop('disabled', true);

            resendTimer = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(resendTimer);
                    $resendButton.prop('disabled', false);
                    $('.hrb-countdown-timer').remove();
                } else {
                    $resendButton.prop('disabled', true);

                    // Update or create countdown display
                    let $timer = $('.hrb-countdown-timer');
                    if ($timer.length === 0) {
                        $resendSection.append('<div class="hrb-countdown-timer"></div>');
                        $timer = $('.hrb-countdown-timer');
                    }

                    $timer.text('<?php _e('Resend available in', 'hourly-room-booking'); ?> ' + timeLeft + ' <?php _e('seconds', 'hourly-room-booking'); ?>');
                    timeLeft--;
                }
            }, 1000);
        }

        // Anonymous Booking Logic
        function checkBookingDuration() {
            const duration = parseInt(form.find('select[name="duration"]').val());
            const anonymousOption = $('#hrb-anonymous-booking-option');
            
            if (duration && duration < 4) {
                anonymousOption.show();
            } else {
                anonymousOption.hide();
                // Uncheck anonymous booking if duration is 4+ hours
                $('#hrb-anonymous-booking').prop('checked', false);
                handleAnonymousBookingChange();
            }
        }

        function setAnonymousFieldRequirements(isAnonymous) {
            // For anonymous bookings: keep name fields visible and make first_name required
            // Hide email, phone, and verification fields
            const emailPhoneVerificationInputs = form.find('.hrb-email-field input, .hrb-phone-field input, .hrb-verification-field input');
            const nameInputs = form.find('.hrb-name-field input');
            
            if (isAnonymous) {
                // Disable email, phone, and verification fields
                emailPhoneVerificationInputs.each(function() {
                    const $input = $(this);
                    if ($input.prop('required')) {
                        $input.data('hrb-required-was', true);
                    }
                    $input.prop('required', false).attr('aria-required', 'false').prop('disabled', true);
                });
                
                // Keep name fields enabled and make first_name required (same as admin)
                nameInputs.each(function() {
                    const $input = $(this);
                    $input.prop('disabled', false);
                    // Make first_name required for anonymous bookings
                    if ($input.attr('name') === 'first_name') {
                        $input.prop('required', true).attr('aria-required', 'true');
                    }
                });
            } else {
                // Re-enable all fields for regular bookings
                emailPhoneVerificationInputs.each(function() {
                    const $input = $(this);
                    $input.prop('disabled', false);
                    if ($input.data('hrb-required-was')) {
                        $input.prop('required', true).attr('aria-required', 'true');
                    }
                });
                
                nameInputs.each(function() {
                    const $input = $(this);
                    $input.prop('disabled', false);
                    if ($input.data('hrb-required-was')) {
                        $input.prop('required', true).attr('aria-required', 'true');
                    }
                });
            }
        }

        function handleAnonymousBookingChange() {
            const isAnonymous = $('#hrb-anonymous-booking').is(':checked');
            const formContainer = form.closest('.hrb-booking-form-container');
            
            if (isAnonymous) {
                // Show modal
                $('#hrb-anonymous-modal').show().addClass('show');
            } else {
                // Show all fields for regular bookings
                formContainer.removeClass('hrb-anonymous-booking-active');
                form.find('.hrb-email-field, .hrb-phone-field, .hrb-verification-field, .hrb-name-field').show();
                setAnonymousFieldRequirements(false);
                applyAnonymousPaymentVisibility(false);
            }
        }

        // Check duration on page load and when duration changes
        checkBookingDuration();
        
        // Listen for duration changes (using select, not input)
        form.find('select[name="duration"]').on('change', function() {
            checkBookingDuration();
        });

        // Handle anonymous booking checkbox change
        $(document).on('change', '#hrb-anonymous-booking', function() {
            if ($(this).is(':checked')) {
                handleAnonymousBookingChange();
                // Enable the button immediately for anonymous bookings
                verificationCompleted = true;
                verificationVerified = true;
                $('#details-and-verification-next').prop('disabled', false);
                $('#details-and-verification-next').text('<?php _e('Continue', 'hourly-room-booking'); ?>');
            } else {
                handleAnonymousBookingChange();
                // Reset verification state for regular bookings
                resetVerificationState();
            }
        });

        // Handle modal buttons
        $(document).on('click', '#hrb-anonymous-continue', function() {
            $('#hrb-anonymous-modal').hide().removeClass('show');
            const formContainer = form.closest('.hrb-booking-form-container');
            formContainer.addClass('hrb-anonymous-booking-active');
            
            // Hide email, phone, and verification fields, but keep name fields visible
            form.find('.hrb-email-field, .hrb-phone-field, .hrb-verification-field').hide();
            form.find('.hrb-name-field').show(); // Keep name fields visible
            
            setAnonymousFieldRequirements(true);
            applyAnonymousPaymentVisibility(true);
            
            // Enable the button for anonymous bookings
            verificationCompleted = true;
            verificationVerified = true;
            $('#details-and-verification-next').prop('disabled', false);
            $('#details-and-verification-next').text('<?php _e('Continue', 'hourly-room-booking'); ?>');
        });

        $(document).on('click', '#hrb-anonymous-cancel', function() {
            $('#hrb-anonymous-modal').hide().removeClass('show');
            $('#hrb-anonymous-booking').prop('checked', false);
            handleAnonymousBookingChange();
            // Reset verification state when canceling anonymous booking
            resetVerificationState();
        });

        // Handle modal close
        $(document).on('click', '.hrb-modal-close, .hrb-modal-overlay', function() {
            $('#hrb-anonymous-modal').hide().removeClass('show');
        });

        // Prevent native validation errors on hidden inputs across steps
        form.on('submit', function() {
            const isAnonymous = $('#hrb-anonymous-booking').is(':checked');
            form.find(':input[required]').each(function() {
                const $input = $(this);
                // For anonymous bookings, keep first_name required even if hidden temporarily
                // We'll validate it manually and show it if needed
                if (!$input.is(':visible')) {
                    // Don't remove required from first_name for anonymous bookings
                    if (isAnonymous && $input.attr('name') === 'first_name') {
                        // Keep it required, we'll handle validation manually
                        return;
                    }
                    $input.data('hrb-required-was', true).prop('required', false).prop('disabled', true);
                }
            });
        });

        // Hide/show PayPal option depending on anonymous booking
        function applyAnonymousPaymentVisibility(isAnonymous) {
            const paypalOption = form.find('input[name="payment_method"][value="paypal"]').closest('.hrb-payment-method');
            const onsiteOption = form.find('#onsite-payment-option');
            if (isAnonymous) {
                paypalOption.hide();
                onsiteOption.show();
                form.find('input[name="payment_method"][value="onsite"]').prop('checked', true).trigger('change');
                updateBookingSummary();
            } else {
                paypalOption.show();
                // Re-apply standard duration-based restriction
                const duration = parseInt(form.find('select[name="duration"]').val());
                if (duration >= 4) {
                    onsiteOption.hide();
                    form.find('input[name="payment_method"][value="paypal"]').prop('checked', true).trigger('change');
                } else {
                    onsiteOption.show();
                }
                updateBookingSummary();
            }
        }

    });
</script>