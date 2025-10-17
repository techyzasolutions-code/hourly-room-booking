<?php
/**
 * Currency Helper Functions
 * Provides easy-to-use functions for currency operations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get currency symbol
 */
function hrb_get_currency_symbol(): string {
    return HRB_Currency_Manager::getInstance()->get_currency_symbol();
}

/**
 * Format amount with currency
 */
function hrb_format_amount(float $amount, bool $show_symbol = true): string {
    return HRB_Currency_Manager::getInstance()->format_amount($amount, $show_symbol);
}

/**
 * Get currency code
 */
function hrb_get_currency_code(): string {
    return HRB_Currency_Manager::getInstance()->get_currency_code();
}

/**
 * Get currency data
 */
function hrb_get_currency_data(string $currency_code = null): array {
    return HRB_Currency_Manager::getInstance()->get_currency_data($currency_code);
}

/**
 * Get translated payment method label
 */
function hrb_get_payment_method_label(string $payment_method): string {
    $method_labels = array(
        'paypal' => __('PayPal', 'hourly-room-booking'),
        'onsite' => __('On-site Payment', 'hourly-room-booking'),
        'stripe' => __('Stripe', 'hourly-room-booking'),
        'bank_transfer' => __('Bank Transfer', 'hourly-room-booking'),
        'cash' => __('Cash', 'hourly-room-booking')
    );
    
    return isset($method_labels[$payment_method]) ? $method_labels[$payment_method] : ucfirst(str_replace('_', ' ', $payment_method));
}

/**
 * Get translated payment status label
 */
function hrb_get_payment_status_label(string $status): string {
    $status_labels = array(
        'pending' => __('Pending', 'hourly-room-booking'),
        'completed' => __('Completed', 'hourly-room-booking'),
        'cancelled' => __('Cancelled', 'hourly-room-booking'),
        'failed' => __('Failed', 'hourly-room-booking'),
        'refunded' => __('Refunded', 'hourly-room-booking'),
        'partially_refunded' => __('Partially Refunded', 'hourly-room-booking')
    );
    
    return isset($status_labels[$status]) ? $status_labels[$status] : ucfirst(str_replace('_', ' ', $status));
}

/**
 * Get translated booking status label
 */
function hrb_get_booking_status_label(string $status): string {
    $status_labels = array(
        'pending' => __('Pending', 'hourly-room-booking'),
        'confirmed' => __('Confirmed', 'hourly-room-booking'),
        'completed' => __('Completed', 'hourly-room-booking'),
        'cancelled' => __('Cancelled', 'hourly-room-booking'),
        'no_show' => __('No Show', 'hourly-room-booking')
    );
    
    return isset($status_labels[$status]) ? $status_labels[$status] : ucfirst(str_replace('_', ' ', $status));
}

/**
 * Get German day abbreviation
 */
function hrb_get_german_day_abbreviation(string $date): string {
    $day_number = date('w', strtotime($date)); // 0 = Sunday, 1 = Monday, etc.
    
    $german_days = array(
        0 => 'So', // Sunday
        1 => 'Mo', // Monday
        2 => 'Di', // Tuesday
        3 => 'Mi', // Wednesday
        4 => 'Do', // Thursday
        5 => 'Fr', // Friday
        6 => 'Sa'  // Saturday
    );
    
    return $german_days[$day_number] ?? 'So';
}
