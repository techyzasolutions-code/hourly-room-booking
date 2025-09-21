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
