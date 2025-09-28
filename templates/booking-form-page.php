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

// Debug logging
error_log('HRB Template: Using pre-fill values - Date: ' . $prefill_date . ', Time: ' . $prefill_time . ', Duration: ' . $prefill_duration);

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
    :root {
        --hrb-primary: #6366f1;
        --hrb-primary-dark: #4f46e5;
        --hrb-secondary: #8b5cf6;
        --hrb-accent: #06b6d4;
        --hrb-success: #10b981;
        --hrb-warning: #f59e0b;
        --hrb-error: #ef4444;
        --hrb-text: #1f2937;
        --hrb-text-light: #6b7280;
        --hrb-text-muted: #9ca3af;
        --hrb-border: #e5e7eb;
        --hrb-border-light: #f3f4f6;
        --hrb-background: #ffffff;
        --hrb-background-light: #f8fafc;
        --hrb-background-dark: #f1f5f9;
        --hrb-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
        --hrb-shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07), 0 2px 4px rgba(0, 0, 0, 0.06);
        --hrb-shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
        --hrb-shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
        --hrb-radius: 8px;
        --hrb-radius-lg: 12px;
        --hrb-radius-xl: 16px;
        --hrb-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Enhanced Main Container */
    .hrb-booking-form-wrapper {
        background: var(--hrb-background);
        border-radius: var(--hrb-radius-xl);
        padding: 0;
        margin: 10px 0;
        box-shadow: var(--hrb-shadow-xl);
        border: 1px solid var(--hrb-border-light);
        overflow: hidden;
        position: relative;
        backdrop-filter: blur(10px);
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.95) 100%);
    }

    .hrb-booking-form {
        padding: 30px 10px;
        position: relative;
    }

    /* Add subtle animation */
    .hrb-booking-form-wrapper {
        animation: slideInUp 0.6s ease-out;
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

    /* Enhanced Room Info Header */
    .hrb-room-info {
        background: linear-gradient(135deg, var(--hrb-primary) 0%, var(--hrb-secondary) 100%);
        color: white;
        padding: 40px;
        margin: -40px -40px 40px -40px;
        border-radius: var(--hrb-radius-xl) var(--hrb-radius-xl) 0 0;
        position: relative;
        overflow: hidden;
    }

    .hrb-room-info::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        opacity: 0.3;
        pointer-events: none;
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

    /* Container Styles */
    .hrb-container {
        background: var(--hrb-background);
        border-radius: 12px;
        padding: 30px;
        margin: 20px 0;
        border: 1px solid var(--hrb-border);
        box-shadow: var(--hrb-shadow);
        transition: box-shadow 0.3s ease;
    }

    .hrb-container:hover {
        box-shadow: var(--hrb-shadow-hover);
    }

    /* Compact Step Navigation */
    .hrb-form-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        padding: 20px 25px;
        position: relative;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 15px;
        border: 1px solid #e2e8f0;
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
        background: linear-gradient(90deg, #e2e8f0 0%, #cbd5e1 50%, #e2e8f0 100%);
        transform: translateY(-50%);
        z-index: 1;
        border-radius: 2px;
    }

    .hrb-step {
        display: flex;
        /* flex-direction: row; */
        align-items: center;
        position: relative;
        z-index: 2;
        background: transparent;
        padding: 8px 12px;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
        background: #f1f5f9;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid #e2e8f0;
        font-size: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        position: relative;
        z-index: 2;
        flex-shrink: 0;
    }

    .hrb-step-icon::before {
        content: '';
        position: absolute;
        top: -3px;
        left: -3px;
        right: -3px;
        bottom: -3px;
        background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.3) 50%, transparent 100%);
        border-radius: 50%;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .hrb-step:hover .hrb-step-icon::before {
        opacity: 1;
    }

    .hrb-step-label {
        font-size: 12px;
        color: #64748b;
        text-align: left;
        font-weight: 600;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        margin: 0;
        transition: all 0.3s ease;
        line-height: 1.2;
        white-space: nowrap;
    }

    /* Active Step */
    .hrb-step.active .hrb-step-icon {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        border-color: #6366f1;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        transform: scale(1.1);
        animation: activePulse 2s infinite;
    }

    @keyframes activePulse {

        0%,
        100% {
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        50% {
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.6), 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
    }

    .hrb-step.active .hrb-step-label {
        color: #6366f1;
        font-weight: 700;
        transform: scale(1.02);
    }

    .hrb-step.active {
        background: rgba(99, 102, 241, 0.08);
        border-radius: 8px;
    }

    /* Completed Step */
    .hrb-step.completed .hrb-step-icon {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-color: #10b981;
        box-shadow: 0 3px 12px rgba(16, 185, 129, 0.4);
        transform: scale(1.05);
    }

    /* Removed checkmark overlay since we're using icons */

    .hrb-step.completed .hrb-step-label {
        color: #10b981;
        font-weight: 700;
    }

    .hrb-step.completed {
        background: rgba(16, 185, 129, 0.08);
        border-radius: 8px;
    }

    /* Progress Line Animation */
    .hrb-form-steps::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 8%;
        height: 4px;
        background: linear-gradient(90deg, #10b981 0%, #6366f1 100%);
        transform: translateY(-50%);
        z-index: 1;
        border-radius: 2px;
        transition: width 0.6s ease;
        width: 0%;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
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

        .hrb-form-steps::before,
        .hrb-form-steps::after {
            left: 10%;
            right: 10%;
        }
    }

    @media (max-width: 600px) {
        .hrb-form-steps {
            padding: 12px 15px;
            margin-bottom: 20px;
            display: flex !important;
            justify-content: space-between !important;
        }

        .hrb-step {
            padding: 4px 6px;
            min-width: 40px;
            gap: 4px;
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
        }

        .hrb-step-icon {
            width: 24px;
            height: 24px;
            font-size: 12px;
        }

        .hrb-step-label {
            display: none !important;
            /* Hide text labels on mobile */
        }
    }

    /* Enhanced Form Step Content */
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

    /* Simplified Form Sections */
    .hrb-form-step-content>div,
    .hrb-form-step-content>section {
        background: transparent;
        padding: 0;
        margin-bottom: 35px;
        border: none;
        box-shadow: none;
    }

    /* Fixed Form Groups and Alignment */
    .hrb-form-group {
        margin-bottom: 20px;
        position: relative;
    }

    .hrb-form-group:last-child {
        margin-bottom: 0;
    }

    /* Fix label alignment issues */
    .hrb-form-step-content h3,
    .hrb-form-step-content h4,
    .hrb-heading-sm {
        font-size: 18px !important;
        font-weight: 600 !important;
        color: var(--hrb-text) !important;
        margin: 0 0 15px 0 !important;
        padding: 0 !important;
        line-height: 1.3 !important;
    }

    /* Ensure consistent section spacing */
    .hrb-form-step-content>div,
    .hrb-form-step-content>section {
        margin-bottom: 25px !important;
    }

    /* Fix specific alignment issues */
    .hrb-time-slots-grid,
    .hrb-time-slots {
        margin-top: 10px !important;
    }

    .hrb-extras-list {
        margin-top: 10px !important;
    }

    .hrb-people-counter {
        margin-top: 10px !important;
        align-items: center !important;
    }

    .hrb-verification-methods {
        margin-top: 10px !important;
    }

    .hrb-payment-methods {
        margin-top: 10px !important;
    }

    /* Validation Error Styles */
    .hrb-validation-error {
        background: #fef2f2 !important;
        color: #dc2626 !important;
        padding: 12px 16px !important;
        border: 1px solid #fecaca !important;
        border-left: 4px solid #dc2626 !important;
        border-radius: 6px !important;
        margin-bottom: 20px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        box-shadow: 0 2px 4px rgba(220, 38, 38, 0.1) !important;
        display: flex !important;
        align-items: center !important;
        animation: slideDown 0.3s ease !important;
    }

    .hrb-validation-error::before {
        content: "⚠️" !important;
        margin-right: 8px !important;
        font-size: 16px !important;
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

    /* Enhanced Time Slots Grid Styling */
    .hrb-time-slots {
        /* display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); */
        gap: 12px;
        margin-top: 15px;
        max-width: 100%;
    }

    .hrb-time-slots-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(35%, 1fr));
        gap: 12px;
        margin-top: 15px;
        max-width: 100%;
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
        border-radius: 8px;
        cursor: pointer;
        text-align: center;
        transition: all 0.3s ease;
        color: var(--hrb-text);
        word-wrap: break-word;
        overflow: hidden;
    }

    .hrb-time-slot.available {
        border-color: #10b981;
        background: #f0fdf4;
        color: #065f46;
    }

    .hrb-time-slot.available:hover {
        border-color: #059669;
        background: #dcfce7;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    }

    .hrb-time-slot.selected {
        border-color: var(--hrb-primary);
        background: var(--hrb-primary);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 115, 170, 0.3);
    }

    .hrb-time-slot.unavailable {
        background: var(--hrb-background-light);
        color: var(--hrb-text-light);
        cursor: not-allowed;
        opacity: 0.6;
        border-color: #ef4444;
        background: #fef2f2;
        color: #991b1b;
    }

    .hrb-time-slot.unavailable:hover {
        border-color: #ef4444;
        background: #fef2f2;
        transform: none;
        box-shadow: none;
    }

    .hrb-time-slot-time {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 4px;
        color: inherit;
        line-height: 1.2;
    }

    .hrb-time-slot-status {
        font-size: 11px;
        opacity: 0.8;
        line-height: 1.2;
    }

    .hrb-time-slot-price {
        font-size: 12px;
        opacity: 0.8;
        font-weight: 500;
        line-height: 1.2;
    }

    /* Responsive Grid Adjustments */
    @media (max-width: 768px) {

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
            font-size: 13px;
        }

        .hrb-time-slot-status,
        .hrb-time-slot-price {
            font-size: 10px;
        }
    }

    @media (max-width: 480px) {

        .hrb-time-slots,
        .hrb-time-slots-grid {
            /* grid-template-columns: repeat(2, 1fr); */
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

    .hrb-loading {
        text-align: center;
        padding: 40px 20px;
        color: var(--hrb-text-light);
        font-style: italic;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16px;
    }

    .hrb-loading::before {
        content: '';
        display: inline-block;
        width: 32px;
        height: 32px;
        border: 3px solid rgba(59, 130, 246, 0.1);
        border-radius: 50%;
        border-top-color: #3b82f6;
        border-right-color: #8b5cf6;
        animation: hrb-spin-beautiful 1.2s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
        margin-bottom: 0;
    }

    .hrb-loading-spinner {
        width: 35px;
        height: 35px;
        border: 2px solid #e5e7eb;
        border-radius: 50%;
        border-top-color: #6366f1;
        animation: hrb-spin 1s linear infinite;
        margin: 0 auto 16px;
    }


    .hrb-loading-text {
        font-size: 16px;
        color: #64748b;
        font-weight: 500;
        letter-spacing: 0.025em;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Form Group and Input Styles */
    .hrb-form-group {
        margin-bottom: 20px;
    }

    .hrb-form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--hrb-text);
        font-size: 16px;
    }

    .hrb-form-input,
    .hrb-form-select,
    .hrb-form-control {
        width: 100%;
        padding: 16px 20px;
        border: 2px solid var(--hrb-border);
        border-radius: var(--hrb-radius);
        font-size: 16px;
        font-weight: 500;
        transition: var(--hrb-transition);
        background: var(--hrb-background);
        color: var(--hrb-text);
        box-sizing: border-box;
        box-shadow: var(--hrb-shadow);
        line-height: 1.5;
    }

    .hrb-form-input:hover,
    .hrb-form-select:hover,
    .hrb-form-control:hover {
        border-color: var(--hrb-primary);
        box-shadow: var(--hrb-shadow-md);
        transform: translateY(-1px);
    }

    .hrb-form-input::placeholder {
        color: var(--hrb-text-light);
    }

    .hrb-form-input:focus,
    .hrb-form-select:focus,
    .hrb-form-control:focus {
        outline: none;
        border-color: var(--hrb-primary);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1), var(--hrb-shadow-lg);
        transform: translateY(-2px);
        background: var(--hrb-background);
    }

    .hrb-form-input.error {
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    }

    .hrb-form-select {
        cursor: pointer;
    }

    .hrb-form-select option {
        background: var(--hrb-background);
        color: var(--hrb-text);
    }

    /* Enhanced Button Styles */
    .hrb-btn {
        padding: 16px 32px;
        border: none;
        border-radius: var(--hrb-radius);
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--hrb-transition);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        width: 100% !important;
        position: relative;
        overflow: hidden;
        box-shadow: var(--hrb-shadow);
        letter-spacing: 0.025em;
        border: 1px solid transparent;
    }



    .hrb-btn:hover::before {
        left: 100%;
    }

    .hrb-btn-primary {
        background: var(--hrb-primary);
        color: white;
        border-color: var(--hrb-primary);
    }

    .hrb-btn-primary:hover {
        background: linear-gradient(135deg, var(--hrb-primary-dark), var(--hrb-secondary));
        border-color: var(--hrb-secondary);
        transform: translateY(-3px);
        box-shadow: var(--hrb-shadow-xl);
    }

    .hrb-btn-secondary {
        background: var(--hrb-background);
        color: var(--hrb-primary);
        border-color: var(--hrb-primary);
    }

    .hrb-btn-secondary:hover {
        background: var(--hrb-primary);
        color: white;
        transform: translateY(-1px);
        box-shadow: var(--hrb-shadow-hover);
    }

    .hrb-btn-success {
        background: var(--hrb-success);
        color: white;
        border-color: var(--hrb-success);
    }

    .hrb-btn-success:hover {
        background: #0d5a0d;
        border-color: #0d5a0d;
        transform: translateY(-1px);
        box-shadow: var(--hrb-shadow-hover);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .hrb-container {
            padding: 20px;
            margin: 10px;
        }

        .hrb-form-steps {
            flex-wrap: wrap;
            gap: 10px;
            padding: 15px 0;
        }

        .hrb-step {
            flex: 1;
            /* min-width: calc(50% - 5px); */
        }

        .hrb-step-icon {
            width: 35px;
            height: 35px;
            font-size: 16px;
        }

        .hrb-step-label {
            font-size: 11px;
        }

        .hrb-time-slots {
            /* grid-template-columns: 1fr 1fr; */
            gap: 10px;
        }
    }

    /* Fixed Row Alignment to Match Form Steps */
    .hrb-row {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        padding: 0 !important;
        /* Ensure no extra padding */
        margin-left: 0 !important;
        /* Align with form steps */
        margin-right: 0 !important;
        /* Align with form steps */
    }

    .hrb-col-6 {
        flex: 1;
    }

    @media (max-width: 768px) {
        .hrb-row {
            flex-direction: column;
            gap: 0;
        }
    }

    .hrb-form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid var(--hrb-border);
    }

    .hrb-heading-sm {
        font-size: 20px;
        font-weight: 600;
        color: var(--hrb-text);
        margin-bottom: 20px;
    }

    .required::after {
        content: ' *';
        color: #dc2626;
    }

    /* People Counter Styles */
    .hrb-people-counter {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 8px;
    }
    

    .hrb-people-counter .hrb-btn {
        padding: 8px 12px;
        font-size: 16px;
        line-height: 1;
        min-width: 40px;
        flex: none;
    }

    .hrb-people-counter input {
        text-align: center;
        max-width: 80px;
        flex: none;
    }

    .hrb-form-help {
        font-size: 12px;
        color: var(--hrb-text-light);
        margin-top: 6px;
    }

    /* Time Slots Styles */
    .hrb-time-slots-grid {
        /* display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); */
        gap: 12px;
        margin-top: 15px;
    }

    .hrb-time-slot {
        border: 2px solid var(--hrb-border);
        border-radius: 8px;
        padding: 12px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: var(--hrb-background);
        position: relative;
    }

    .hrb-time-slot.available {
        border-color: #10b981;
        background: #f0fdf4;
    }

    .hrb-time-slot.available:hover {
        border-color: #059669;
        background: #dcfce7;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    }

    .hrb-time-slot.selected {
        border-color: var(--hrb-primary);
        background: var(--hrb-accent);
        color: white;
    }

    .hrb-time-slot.unavailable {
        border-color: #ef4444;
        background: #fef2f2;
        color: #991b1b;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .hrb-time-slot-time {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .hrb-time-slot-status {
        font-size: 12px;
        opacity: 0.8;
    }

    .hrb-no-slots,
    .hrb-loading-message,
    .hrb-error-message {
        text-align: center;
        padding: 20px;
        border-radius: 6px;
        margin-top: 15px;
    }

    .hrb-no-slots {
        background: #fef3cd;
        color: #996633;
        border: 1px solid #fde68a;
    }

    .hrb-loading-message {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        color: var(--hrb-text-light);
        border: 1px solid rgba(59, 130, 246, 0.1);
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px;
        text-align: center;
    }

    @keyframes hrb-shimmer {
        0% {
            left: -100%;
        }

        100% {
            left: 100%;
        }
    }

    .hrb-error-message {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    /* Extras List Styles */
    /* .hrb-extras-list {
    border: 1px solid var(--hrb-border);
    border-radius: 6px;
    background: var(--hrb-background);
} */

    .hrb-extra-item {
        border: 1px solid var(--hrb-border);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        /* overflow: hidden; */
        background: var(--hrb-background);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        margin-bottom: 15px;
        position: relative;
    }

    .hrb-extra-item:hover {
        border-color: var(--hrb-primary);
        box-shadow: 0 4px 12px rgba(0, 115, 170, 0.15);
        transform: translateY(-2px);
    }

    .hrb-extra-item input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: var(--hrb-primary);
        cursor: pointer;
        flex-shrink: 0;
        margin: 0;
    }

    .hrb-extra-item input[type="checkbox"]:checked~.hrb-extra-content {
        background: linear-gradient(135deg, #f0f8ff, #e6f3ff);
        border-color: var(--hrb-primary);
    }

    .hrb-extra-item input[type="checkbox"]:checked {
        background: linear-gradient(135deg, #10b981, #059669);
        border-color: #10b981;
        box-shadow: 0 3px 12px rgba(16, 185, 129, 0.4);
    }

    .hrb-extra-item input[type="checkbox"]:checked+.hrb-extra-content {
        background: linear-gradient(135deg, #f0f8ff, #e6f3ff);
        border-color: var(--hrb-primary);
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
    }

    .hrb-extra-item input[type="checkbox"]:checked~.hrb-extra-content .hrb-extra-icon {
        border-color: var(--hrb-primary);
        background: #f0f8ff;
    }

    /* Selected extra item styling */
    .hrb-extra-item:has(input[type="checkbox"]:checked) {
        background: linear-gradient(135deg, #f0f8ff, #e6f3ff);
        border-color: var(--hrb-primary);
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
        transform: translateY(-1px);
    }

    .hrb-extra-item:has(input[type="checkbox"]:checked) .hrb-extra-header {
        background: rgba(99, 102, 241, 0.1);
        border-radius: 8px;
        padding: 8px;
        margin: -8px;
    }

    .hrb-extra-item:has(input[type="checkbox"]:checked) .hrb-extra-icon {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: white;
        border-color: #6366f1;
        box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
    }

    .hrb-extra-item:has(input[type="checkbox"]:checked) .hrb-extra-title {
        color: #6366f1;
        font-weight: 700;
    }

    .hrb-extra-item:has(input[type="checkbox"]:checked) .hrb-extra-price {
        color: #6366f1;
        font-weight: 700;
    }

    .hrb-extra-content {
        display: flex;
        flex-direction: column;
        padding: 16px;
        gap: 12px;
        transition: all 0.3s ease;
    }

    /* First row: Icon + Title + Price */
    .hrb-extra-header {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
    }

    .hrb-extra-icon {
        width: 40px;
        height: 40px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
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
        /* white-space: nowrap; */
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .hrb-extra-price {
        /* flex: none;
    font-weight: 600;
    color: var(--hrb-primary);
    font-size: 16px;
    white-space: nowrap; */
        flex: none;
        font-weight: bold;
        color: #059669;
        font-size: 16px;
        background: #d1fae5;
        padding: 1px 5px;
        border-radius: 4px;
        border: 1px solid #10b981;
       
    }

    /* Second row: Description */
    .hrb-extra-description {
        color: var(--hrb-text-light);
        margin: 0;
        font-size: 16px;
        line-height: 1.4;
        padding: 8px 12px;
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border-radius: 6px;
        border-left: 3px solid var(--hrb-primary);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    input.hrb-extra-checkbox {
            display: none;
        }
        .hrb-extra-price {
            font-size: 13px;
            position: absolute;
            right: -5px;
            top: -10px;
            }
    /* Mobile responsive adjustments */
    @media (max-width: 768px) {

        .hrb-extra-content {
            padding: 12px;
            gap: 10px;
        }

        .hrb-time-slots-grid {
            grid-template-columns: repeat(2, minmax(135px, 1fr));
        }

        input.hrb-extra-checkbox {
            display: none;
        }

        .hrb-extra-header {
            gap: 10px;
        }

        .hrb-extra-icon {
            width: 36px;
            height: 36px;
            font-size: 16px;
        }

        .hrb-extra-title {
            font-size: 16px;
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
    }

    .hrb-extra-item input[type="checkbox"]:checked+.hrb-extra-content .hrb-extra-icon {
        border-color: var(--hrb-primary);
        background: #f0f8ff;
    }


    .hrb-extra-title {
        font-weight: 700;
        color: var(--hrb-text);
        margin: 0 0 8px 0;
        font-size: 15px;
        letter-spacing: 0.025em;
    }

    .hrb-extra-description {
        color: var(--hrb-text-muted);
        font-size: 12px;
        margin: 4px 0 0 0;
        line-height: 1.5;
        font-style: italic;
        padding: 8px 12px;
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border-radius: 6px;
        border-left: 3px solid var(--hrb-primary);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
    }

    /* .hrb-extra-price {
    flex: none;
    font-weight: 600;
    color: var(--hrb-primary);
    font-size: 14px;
} */

    .hrb-no-extras {
        text-align: center;
        padding: 20px;
        color: var(--hrb-text-light);
        font-style: italic;
    }

    /* Form Textarea */
    .hrb-form-textarea {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--hrb-border);
        border-radius: 6px;
        font-size: 16px;
        transition: all 0.3s ease;
        background: var(--hrb-background);
        color: var(--hrb-text);
        box-sizing: border-box;
        resize: vertical;
        min-height: 80px;
        font-family: inherit;
    }

    .hrb-form-textarea:focus {
        outline: none;
        border-color: var(--hrb-primary);
        box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
    }

    /* Alert Styles */
    .hrb-alert {
        padding: 12px 16px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 16px;
        border: 1px solid transparent;
    }

    .hrb-alert-error {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .hrb-alert-success {
        background: #f0fdf4;
        color: #166534;
        border-color: #bbf7d0;
    }

    .hrb-alert-warning {
        background: #fefce8;
        color: #a16207;
        border-color: #fef08a;
    }

    .hrb-alert-info {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #dbeafe;
    }

    .hrb-alert-success {
        background: #dcfce7;
        color: #15803d;
        border-color: #bbf7d0;
    }

    /* Enhanced Verification Section Styles */
    .hrb-verification-methods {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin: 15px 0;
    }

    .hrb-verification-method {
        border: 2px solid var(--hrb-border);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        overflow: hidden;
        margin: 0;
    }

    .hrb-verification-method:hover {
        border-color: var(--hrb-primary);
        box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1);
    }

    .hrb-verification-method input[type="radio"] {
        display: none;
    }

    .hrb-verification-method input[type="radio"]:checked+.hrb-verification-method-content {
        background: #f0f8ff;
        border-left: 4px solid var(--hrb-primary);
    }

    .hrb-verification-method-content {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 16px;
        transition: all 0.3s ease;
    }

    .hrb-verification-icon {
        font-size: 24px;
        flex-shrink: 0;
    }

    .hrb-verification-method-content div {
        flex: 1;
    }

    .hrb-verification-method-content strong {
        display: block;
        font-size: 16px;
        font-weight: 600;
        color: var(--hrb-text);
        margin-bottom: 4px;
    }

    .hrb-verification-method-content p {
        margin: 0;
        font-size: 12px;
        color: var(--hrb-text-light);
        line-height: 1.4;
    }

    .hrb-contact-display {
        background: var(--hrb-background-light);
        color: var(--hrb-text);
        font-weight: 600;
        padding: 12px 16px;
        border: 2px solid var(--hrb-border);
        border-radius: 6px;
        display: flex;
        align-items: center;
    }

    .hrb-contact-display::before {
        margin-right: 8px;
    }

    #verification-email-display::before {
        content: '✉️';
    }

    #verification-phone-display::before {
        content: '📱';
    }

    .hrb-contact-info {
        margin: 15px 0;
    }

    .hrb-contact-item {
        margin-bottom: 15px;
    }

    .hrb-contact-item:last-child {
        margin-bottom: 0;
    }

    /* Verification Container Styles */
    .hrb-verification-container {
        margin-top: 30px;
        padding: 20px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
    }

    .hrb-heading-xs {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--hrb-text);
        margin: 0 0 15px 0;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--hrb-primary);
    }

    .hrb-verification-code-section input {
        text-align: center;
        font-size: 18px;
        font-weight: 600;
        letter-spacing: 3px;
        font-family: monospace;
    }

    .hrb-verification-section {
        position: relative;
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
        border-radius: 6px;
        z-index: 10;
    }

    .hrb-verification-loading .hrb-spinner {
        width: 24px;
        height: 24px;
        border: 2px solid #e5e7eb;
        border-radius: 50%;
        border-top-color: #6366f1;
        animation: hrb-spin 1s linear infinite;
    }


    .hrb-btn-sm {
        padding: 8px 16px;
        font-size: 12px;
    }

    /* Enhanced Payment Method Styles */
    .hrb-payment-methods {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin: 20px 0;
    }

    .hrb-payment-method {
        border: 2px solid var(--hrb-border);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        overflow: hidden;
        background: var(--hrb-background);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .hrb-payment-method:hover {
        border-color: var(--hrb-primary);
        box-shadow: 0 4px 12px rgba(0, 115, 170, 0.15);
        transform: translateY(-2px);
    }

    .hrb-payment-method input[type="radio"] {
        display: none;
    }

    .hrb-payment-method input[type="radio"]:checked+.hrb-payment-method-content {
        background: linear-gradient(135deg, #f0f8ff, #e6f3ff);
        border-color: var(--hrb-primary);
    }

    .hrb-payment-method-content {
        display: flex;
        align-items: center;
        padding: 20px;
        gap: 15px;
        transition: all 0.3s ease;
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

    .hrb-payment-method-content div {
        flex: 1;
    }

    .hrb-payment-method-content strong {
        display: block;
        color: var(--hrb-text);
        font-size: 16px;
        margin-bottom: 4px;
        font-weight: 600;
    }

    .hrb-payment-method-content p {
        color: var(--hrb-text-light);
        margin: 0;
        font-size: 16px;
        line-height: 1.4;
    }

    .hrb-payment-method input[type="radio"]:checked+.hrb-payment-method-content img {
        border-color: var(--hrb-primary);
        background: #f0f8ff;
    }

    /* Enhanced Step Navigation with Click Support */
    .hrb-step {
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .hrb-step:hover .hrb-step-icon {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0, 115, 170, 0.2);
    }

    .hrb-step.completed {
        cursor: pointer;
    }

    .hrb-step.completed:hover .hrb-step-icon {
        background: #0d5a0d;
        transform: scale(1.1);
    }

    .hrb-step.active:hover .hrb-step-icon {
        background: var(--hrb-secondary);
        transform: scale(1.05);
    }

    .hrb-step-icon {
        position: relative;
        overflow: hidden;
    }

    .hrb-step-icon::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at center, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .hrb-step:hover .hrb-step-icon::before {
        opacity: 1;
    }

    .hrb-step-clickable .hrb-step-icon {
        background: #0d5a0d !important;
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(16, 124, 16, 0.3);
    }

    .hrb-step-clickable .hrb-step-label {
        color: #0d5a0d !important;
        font-weight: 700;
    }

    /* Payment Method Responsive Design */
    @media (max-width: 768px) {
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

    .hrb-countdown-timer {
        font-size: 12px;
        color: var(--hrb-text-light);
        margin-top: 5px;
    }

    .hrb-verification-success {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
        padding: 12px 16px;
        border-radius: 6px;
        margin: 15px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .hrb-verification-success::before {
        content: '✅';
    }

    .hrb-verification-error {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
        padding: 12px 16px;
        border-radius: 6px;
        margin: 15px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .hrb-verification-error::before {
        content: '❌';
    }

    /* Fixed Form Input Styling */
    .hrb-form-label {
        font-weight: 600 !important;
        font-size: 14px !important;
        letter-spacing: 0.01em;
        line-height: 1.4;
        margin-bottom: 8px !important;
        display: block !important;
    }

    .hrb-form-label.required::after {
        content: '*' !important;
        color: #dc2626;
        margin-left: 4px;
        font-weight: 700;
    }

    .hrb-form-input,
    .hrb-form-select,
    .hrb-form-control {
        padding: 12px 16px !important;
        border-radius: 6px !important;
        font-size: 14px !important;
        font-weight: 400;
        background: #ffffff !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
        transition: all 0.2s ease !important;
        line-height: 1.4;
        height: auto !important;
        min-height: 44px !important;
    }

    .hrb-form-input:hover,
    .hrb-form-select:hover,
    .hrb-form-control:hover {
        border-color: rgba(0, 115, 170, 0.3) !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08) !important;
    }

    .hrb-form-input:focus,
    .hrb-form-select:focus,
    .hrb-form-control:focus {
        border-color: var(--hrb-primary) !important;
        box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1) !important;
        background: #ffffff !important;
    }

    .hrb-form-input.error,
    .hrb-form-select.error,
    .hrb-form-control.error {
        border-color: #dc2626 !important;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
        background: #fef2f2 !important;
    }

    .hrb-form-input::placeholder {
        font-weight: 400;
        opacity: 0.6;
    }

    /* Fixed Select Styling */
    .hrb-form-select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") !important;
        background-position: right !important;
        background-repeat: no-repeat !important;
        background-size: 40px !important;
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
    }

    .hrb-form-select:focus {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%230073aa' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right !important;
    }

    /* Fixed Textarea */
    textarea.hrb-form-control {
        resize: vertical !important;
        min-height: 100px !important;
        font-family: inherit !important;
        line-height: 1.5 !important;
        padding: 12px 16px !important;
    }

    /* Fixed Contact Display Fields */
    .hrb-contact-display {
        background: rgba(0, 115, 170, 0.03) !important;
        border: 1px solid rgba(0, 115, 170, 0.15) !important;
        color: var(--hrb-text) !important;
        font-weight: 500 !important;
        padding: 12px 16px !important;
        border-radius: 6px !important;
        min-height: 44px !important;
        display: flex !important;
        align-items: center !important;
    }

    /* Simplified Button Styles */
    .hrb-btn {
        padding: 12px 24px !important;
        border: none !important;
        border-radius: 8px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        text-decoration: none !important;
        display: inline-block !important;
        text-align: center !important;
        border: 1px solid transparent !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08) !important;
    }

    .hrb-btn:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12) !important;
    }

    .hrb-btn-primary {
        background: var(--hrb-primary) !important;
        color: white !important;
        border-color: var(--hrb-primary) !important;
    }

    .hrb-btn-primary:hover {
        background: var(--hrb-secondary) !important;
        border-color: var(--hrb-secondary) !important;
        color: white !important;
        text-decoration: none !important;
    }

    .hrb-btn-secondary {
        background: #6c757d !important;
        color: white !important;
        border-color: #6c757d !important;
    }

    .hrb-btn-secondary:hover {
        background: #5a6268 !important;
        border-color: #5a6268 !important;
        color: white !important;
        text-decoration: none !important;
    }

    .hrb-btn-success {
        background: var(--hrb-success) !important;
        color: white !important;
        border-color: var(--hrb-success) !important;
    }

    .hrb-btn-success:hover {
        background: #0d5a0d !important;
        border-color: #0d5a0d !important;
        color: white !important;
        text-decoration: none !important;
    }

    .hrb-btn:disabled,
    .hrb-btn[disabled] {
        background: #e9ecef !important;
        color: #6c757d !important;
        border-color: #e9ecef !important;
        cursor: not-allowed !important;
        transform: none !important;
        box-shadow: none !important;
        opacity: 0.6 !important;
    }

    .hrb-btn:disabled:hover,
    .hrb-btn[disabled]:hover {
        background: #e9ecef !important;
        color: #6c757d !important;
        transform: none !important;
        box-shadow: none !important;
    }

    /* Simplified Time Slot Buttons */
    .hrb-time-slot {
        border: 1px solid var(--hrb-border) !important;
        border-radius: 6px !important;
        padding: 12px !important;
        text-align: center !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        background: #ffffff !important;
    }

    .hrb-time-slot.available {
        border-color: #10b981 !important;
        background: #f0fdf4 !important;
    }

    .hrb-time-slot.available:hover {
        border-color: #059669 !important;
        background: #dcfce7 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2) !important;
    }

    .hrb-time-slot.selected {
        border-color: var(--hrb-primary) !important;
        background: var(--hrb-primary) !important;
        color: white !important;
        box-shadow: 0 2px 8px rgba(0, 115, 170, 0.3) !important;
    }

    .hrb-time-slot.unavailable {
        border-color: #ef4444 !important;
        background: #fef2f2 !important;
        color: #991b1b !important;
        cursor: not-allowed !important;
        opacity: 0.7 !important;
    }

    /* Fixed People Counter Buttons */
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

    .hrb-people-counter .hrb-btn:hover {
        background: var(--hrb-secondary) !important;
        border-color: var(--hrb-secondary) !important;
    }

    .hrb-people-counter input {
        max-width: 60px !important;
        text-align: center !important;
        padding: 8px 12px !important;
        min-height: 32px !important;
    }

    /* Simplified Visual Effects */

    /* Simplified Extras List */
    /* .hrb-extras-list {
    border: 1px solid rgba(0, 115, 170, 0.15) !important;
    border-radius: 8px !important;
    background: #ffffff !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
    overflow: hidden !important;
} */

    .hrb-extra-item {
        border-bottom: 1px solid rgba(0, 115, 170, 0.1) !important;
        transition: background-color 0.2s ease !important;
    }

    .hrb-extra-item:hover {
        background: rgba(0, 115, 170, 0.03) !important;
    }

    .hrb-checkbox-label {
        padding: 20px !important;
        gap: 16px !important;
    }

    .hrb-checkbox-label input[type="checkbox"] {
        width: 20px !important;
        height: 20px !important;
        accent-color: var(--hrb-primary) !important;
        position: relative !important;
    }

    /* Simplified Verification Section */
    .hrb-verification-section {
        background: rgba(0, 115, 170, 0.02) !important;
        border: 1px solid rgba(0, 115, 170, 0.1) !important;
        border-radius: 8px !important;
        padding: 20px !important;
        margin: 20px 0 !important;
    }

    /* Simplified Success/Error Messages */
    .hrb-verification-success,
    .hrb-alert-success {
        background: #f0fdf4 !important;
        border: 1px solid #10b981 !important;
        border-radius: 6px !important;
        padding: 12px 16px !important;
        border-left: 4px solid #10b981 !important;
    }

    .hrb-verification-error,
    .hrb-alert-error {
        background: #fef2f2 !important;
        border: 1px solid #ef4444 !important;
        border-radius: 6px !important;
        padding: 12px 16px !important;
        border-left: 4px solid #ef4444 !important;
    }

    /* Simplified Payment Methods */
    .hrb-payment-methods {
        gap: 15px !important;
        margin: 20px 0 !important;
    }

    .hrb-payment-method {
        border: 1px solid rgba(0, 115, 170, 0.15) !important;
        border-radius: 8px !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05) !important;
        transition: all 0.2s ease !important;
        overflow: hidden !important;
    }

    .hrb-payment-method:hover {
        border-color: var(--hrb-primary) !important;
        box-shadow: 0 4px 12px rgba(0, 115, 170, 0.1) !important;
    }

    .hrb-payment-method input[type="radio"]:checked+.hrb-payment-method-content {
        background: #f0f8ff !important;
        border-left: 3px solid var(--hrb-primary) !important;
    }

    /* Simplified Room Info */

    /* Enhanced Responsive Design Elements */
    @media (max-width: 768px) {
        .hrb-booking-form {
            padding: 20px !important;
        }

        .hrb-room-info {
            margin: -20px -20px 30px -20px !important;
            padding: 30px 20px !important;
        }

        .hrb-room-info h2.hrb-heading-md {
            font-size: 2rem !important;
        }

        .hrb-form-steps {
            padding: 20px 15px !important;
            margin-bottom: 30px !important;
        }

        .hrb-step-icon {
            width: 35px !important;
            height: 35px !important;
            font-size: 14px !important;
        }

        .hrb-step-label {
            font-size: 12px !important;
        }

        .hrb-btn {
            padding: 14px 24px !important;
            font-size: 14px !important;
        }
    }
    .hrb-people-counter .hrb-btn {
        width: unset !important;
    }
</style>

<div class="">


    <div class="hrb-booking-form-wrapper">
        <form class="hrb-booking-form hrb-form" id="hrb-booking-form-<?php echo esc_attr($room_id); ?>" data-room-id="<?php echo esc_attr($room_id); ?>" data-local-pricing="true">
            <!-- Step Progress Indicator -->
            <div class="hrb-form-steps" style="display: none;">
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
                <!-- <h3 class="hrb-heading-sm"><?php _e('Select Date & Time', 'hourly-room-booking'); ?></h3> -->

                <div class="hrb-row">
                    <div class="hrb-col-12">
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
                    <div class="hrb-col-12">
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

                <?php
                // Auto-fill user details for logged-in users using booking-specific meta fields
                $current_user = wp_get_current_user();
                $user_first_name = '';
                $user_last_name = '';
                $user_email = '';
                $user_phone = '';
                $user_company = '';

                if (is_user_logged_in()) {
                    // Use booking-specific meta fields for auto-fill
                    $booking_first_name = get_user_meta($current_user->ID, 'hrb_booking_first_name', true);
                    $booking_last_name = get_user_meta($current_user->ID, 'hrb_booking_last_name', true);
                    $booking_email = get_user_meta($current_user->ID, 'hrb_booking_email', true);
                    $booking_phone = get_user_meta($current_user->ID, 'hrb_booking_phone', true);
                    $booking_company = get_user_meta($current_user->ID, 'hrb_booking_company', true);

                    // Use booking meta if available, fallback to user data if booking meta is empty
                    $user_first_name = !empty($booking_first_name) ? $booking_first_name : $current_user->first_name;
                    $user_last_name = !empty($booking_last_name) ? $booking_last_name : $current_user->last_name;
                    $user_email = !empty($booking_email) ? $booking_email : $current_user->user_email;
                    $user_phone = !empty($booking_phone) ? $booking_phone : get_user_meta($current_user->ID, 'phone', true);
                    $user_company = !empty($booking_company) ? $booking_company : get_user_meta($current_user->ID, 'company', true);

                    // If still no first/last name, try to extract from display name
                    if (empty($user_first_name) && empty($user_last_name) && !empty($current_user->display_name)) {
                        $name_parts = explode(' ', $current_user->display_name, 2);
                        $user_first_name = $name_parts[0];
                        $user_last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                    }
                }
                ?>

                <?php if (is_user_logged_in()): ?>
                    <div class="hrb-alert hrb-alert-info" style="margin-bottom: 20px;">
                        <strong><?php _e('Welcome back!', 'hourly-room-booking'); ?></strong>
                        <?php _e('Your details have been pre-filled from your account. You can modify them if needed.', 'hourly-room-booking'); ?>
                    </div>
                <?php endif; ?>

                <div class="hrb-row">
                    <div class="hrb-col-12">
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
                    <div class="hrb-col-12">
                        <div class="hrb-form-group">
                            <label class="hrb-form-label required" for="last-name-<?php echo $room_id; ?>">
                                <?php _e('Last Name', 'hourly-room-booking'); ?>
                            </label>
                            <input type="text"
                                class="hrb-form-control"
                                id="last-name-<?php echo $room_id; ?>"
                                name="last_name"
                                value="<?php echo esc_attr($user_last_name); ?>"
                                required>
                        </div>
                    </div>
                </div>

                <div class="hrb-row">
                    <div class="hrb-col-12">
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
                    <div class="hrb-col-12">
                        <div class="hrb-form-group">
                            <label class="hrb-form-label required" for="phone-<?php echo $room_id; ?>">
                                <?php _e('Phone Number', 'hourly-room-booking'); ?>
                            </label>
                            <input type="tel"
                                class="hrb-form-control"
                                id="phone-<?php echo $room_id; ?>"
                                name="phone"
                                value="<?php echo esc_attr($user_phone); ?>"
                                required
                                placeholder="+49 123 456 789">
                        </div>
                    </div>
                </div>

                <div class="hrb-form-group">
                    <label class="hrb-form-label" for="company-<?php echo $room_id; ?>">
                        <?php _e('Company (Optional)', 'hourly-room-booking'); ?>
                    </label>
                    <input type="text"
                        class="hrb-form-control"
                        id="company-<?php echo $room_id; ?>"
                        name="company"
                        value="<?php echo esc_attr($user_company); ?>">
                </div>

                <!-- Contact Verification Section -->
                <div class="hrb-verification-container">
                    <h4 class="hrb-heading-xs"><?php _e('Contact Verification', 'hourly-room-booking'); ?></h4>

                    <?php
                    // Check admin notification settings
                    $email_notifications = get_option('hrb_email_notifications', 1);
                    $sms_notifications = get_option('hrb_sms_notifications', 0);
                    $both_enabled = $email_notifications && $sms_notifications;
                    ?>

                    <div class="hrb-alert hrb-alert-info">
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
                                    <p><?php _e('Pay cash or card at the location', 'hourly-room-booking'); ?></p>
                                </div>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="hrb-payment-notice hrb-alert hrb-alert-info">
                    <strong><?php _e('Payment Policy:', 'hourly-room-booking'); ?></strong>
                    <ul>
                        <li><?php _e('Bookings 4+ hours: PayPal payment required, full amount upfront, no refunds', 'hourly-room-booking'); ?></li>
                        <li><?php _e('Bookings under 4 hours: PayPal or on-site payment available', 'hourly-room-booking'); ?></li>
                        <li><?php _e('PayPal payments include a 3% processing fee', 'hourly-room-booking'); ?></li>
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
                <h3 class="hrb-heading-sm"><?php _e('Booking Confirmation', 'hourly-room-booking'); ?></h3>

                <div class="hrb-booking-review">
                    <div class="hrb-row">
                        <div class="hrb-col-12">
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
                        <div class="hrb-col-12">
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
                        <input type="checkbox" name="accept_terms" required>
                        <span>
                            <?php _e('I accept the', 'hourly-room-booking'); ?>
                            <a href="#" target="_blank"><?php _e('Terms & Conditions', 'hourly-room-booking'); ?></a>
                            <?php _e('and', 'hourly-room-booking'); ?>
                            <a href="#" target="_blank"><?php _e('Privacy Policy', 'hourly-room-booking'); ?></a>
                        </span>
                    </label>
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
        border-top-color: #3b82f6;
        border-right-color: #8b5cf6;
        animation: hrb-spin-beautiful 1.2s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
        z-index: 1001;
    }

    .hrb-submit-btn.hrb-loading {
        position: relative;
        pointer-events: none;
        opacity: 0.9;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
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
        border-top-color: #ffffff;
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
        background: linear-gradient(135deg, #0073aa, #005a87);
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
        background-color: #6c757d !important;
        cursor: not-allowed !important;
        opacity: 0.7 !important;
    }

    .hrb-submit-btn.hrb-loading:disabled {
        background-color: #0073aa !important;
        cursor: not-allowed !important;
        opacity: 1 !important;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        const form = $('#hrb-booking-form-<?php echo $room_id; ?>');
        const extraPeoplePrice = <?php echo floatval($extra_people_price); ?>;
        // Hourly price removed - using 2-4 hour pricing system
        const currencySymbol = '<?php echo esc_js($currency_symbol); ?>';

        // Auto-load time slots if form is pre-filled from search
        const prefillDate = '<?php echo esc_js($prefill_date); ?>';
        const prefillTime = '<?php echo esc_js($prefill_time); ?>';
        const prefillDuration = '<?php echo esc_js($prefill_duration); ?>';

        if (prefillDate && prefillDuration) {
            // Trigger time slot loading
            loadTimeSlots(<?php echo $room_id; ?>, prefillDate, prefillDuration);

            // If time is also pre-filled, select the corresponding time slot
            if (prefillTime) {
                setTimeout(function() {
                    const timeSlot = form.find('.hrb-time-slot').filter(function() {
                        return $(this).find('.hrb-time-slot-time').text().trim() === prefillTime;
                    });
                    if (timeSlot.length) {
                        timeSlot.click();
                    }
                }, 1000); // Wait for time slots to load
            }
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
            console.warn('HRB.utils.showMessage not available, using fallback');
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
                    // Validate customer details
                    const firstName = form.find('input[name="first_name"]').val();
                    const lastName = form.find('input[name="last_name"]').val();
                    const email = form.find('input[name="email"]').val();
                    const phone = form.find('input[name="phone"]').val();

                    if (!firstName.trim()) {
                        showValidationError('<?php _e('Please enter your first name', 'hourly-room-booking'); ?>');
                        return false;
                    }

                    if (!lastName.trim()) {
                        showValidationError('<?php _e('Please enter your last name', 'hourly-room-booking'); ?>');
                        return false;
                    }

                    if (!email.trim() || !isValidEmail(email)) {
                        showValidationError('<?php _e('Please enter a valid email address', 'hourly-room-booking'); ?>');
                        return false;
                    }

                    if (!phone.trim()) {
                        showValidationError('<?php _e('Please enter your phone number', 'hourly-room-booking'); ?>');
                        return false;
                    }

                    // Validate verification
                    if (!verificationVerified) {
                        showValidationError('<?php _e('Please verify your contact information to continue', 'hourly-room-booking'); ?>');
                        return false;
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


        // Handle payment method restriction for long bookings
        form.find('select[name="duration"]').on('change', function() {
            const duration = parseInt($(this).val());
            const onsiteOption = $('#onsite-payment-option');

            if (duration >= 4) {
                onsiteOption.hide();
                form.find('input[name="payment_method"][value="paypal"]').prop('checked', true);
            } else {
                onsiteOption.show();
            }

            updateBookingSummary();
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


        // Handle form submission
        form.on('submit', function(e) {
            e.preventDefault();

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

        function loadTimeSlots(roomId, date, duration) {
            const container = $('#time-slots-' + roomId);

            if (!date || !duration) {
                container.html(`
                <div class="hrb-loading-message">
                    <div class="hrb-loading-text"><?php _e('Please select a date and duration first', 'hourly-room-booking'); ?></div>
                </div>
            `);
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
                },
                error: function() {
                    container.html('<div class="hrb-error-message"><?php _e('Error loading time slots. Please try again.', 'hourly-room-booking'); ?></div>');
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
        });

        function generateTimeSlots(duration) {
            const slots = [];
            const startHour = 8;
            const endHour = 20;
            const durationHours = parseInt(duration);

            for (let hour = startHour; hour <= endHour - durationHours; hour++) {
                const startTime = String(hour).padStart(2, '0') + ':00';
                const endTime = String(hour + durationHours).padStart(2, '0') + ':00';
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

            $('#review-date').text(formData.booking_date);
            $('#review-time').text(formData.start_time + ' - ' + formData.end_time);
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
            console.log('Processing PayPal payment...');

            // Get form data
            const formData = getFormData();

            // Validate required fields before creating PayPal order
            if (!formData.first_name || !formData.last_name || !formData.email || !formData.phone) {
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
                company: form.find('input[name="company"]').val()
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
                                        <div class="hrb-extra-title">${extra.name}${stockInfo}</div>
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

            // Calculate total
            const total = subtotal + paypalFee;

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
            </div>
        `;

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
        }

        function checkVerificationStatus(email) {
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
                        $('.hrb-alert.hrb-alert-info').hide();
                        $('.hrb-verification-section').hide();

                        // Remove any existing success messages and add new one
                        $('.hrb-alert-success').remove();
                        $('.hrb-alert.hrb-alert-info').after('<div class="hrb-alert hrb-alert-success"><strong>✅ ' + response.data.message + '</strong></div>');

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
    });
</script>