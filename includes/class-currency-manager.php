<?php
/**
 * Currency Manager Class
 * Handles all currency-related operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Currency_Manager {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Get available currencies
     */
    public function get_available_currencies(): array {
        return [
            'USD' => [
                'code' => 'USD',
                'symbol' => '$',
                'name' => __('US Dollar', 'hourly-room-booking'),
                'position' => 'before' // Symbol position relative to amount
            ],
            'EUR' => [
                'code' => 'EUR',
                'symbol' => '€',
                'name' => __('Euro', 'hourly-room-booking'),
                'position' => 'after'
            ]
        ];
    }
    
    /**
     * Get current currency from settings
     */
    public function get_current_currency(): string {
        return get_option('hrb_currency', 'EUR');
    }
    
    /**
     * Get current currency symbol
     */
    public function get_currency_symbol(): string {
        $currency = $this->get_current_currency();
        $currencies = $this->get_available_currencies();
        
        return $currencies[$currency]['symbol'] ?? '€';
    }
    
    /**
     * Get currency code for payments
     */
    public function get_currency_code(): string {
        return $this->get_current_currency();
    }
    
    /**
     * Format amount with currency symbol
     */
    public function format_amount(float $amount, bool $show_symbol = true): string {
        $currency = $this->get_current_currency();
        $currencies = $this->get_available_currencies();
        $currency_data = $currencies[$currency] ?? $currencies['EUR'];
        
        $formatted_amount = number_format($amount, 2);
        
        if (!$show_symbol) {
            return $formatted_amount;
        }
        
        if ($currency_data['position'] === 'before') {
            return $currency_data['symbol'] . $formatted_amount;
        } else {
            return $formatted_amount . ' ' . $currency_data['symbol'];
        }
    }
    
    /**
     * Get currency data
     */
    public function get_currency_data(string $currency_code = null): array {
        if ($currency_code === null) {
            $currency_code = $this->get_current_currency();
        }
        
        $currencies = $this->get_available_currencies();
        return $currencies[$currency_code] ?? $currencies['EUR'];
    }
    
    /**
     * Initialize default currency setting
     */
    public function initialize_default_currency(): void {
        if (!get_option('hrb_currency')) {
            update_option('hrb_currency', 'EUR');
        }
    }
    
    /**
     * Get currency for PayPal payments
     */
    public function get_paypal_currency(): string {
        $currency = $this->get_current_currency();
        
        // PayPal supports both USD and EUR
        if (in_array($currency, ['USD', 'EUR'])) {
            return $currency;
        }
        
        // Default to EUR if unsupported currency
        return 'EUR';
    }
    
    /**
     * Validate currency code
     */
    public function is_valid_currency(string $currency_code): bool {
        $currencies = $this->get_available_currencies();
        return isset($currencies[$currency_code]);
    }
    
    /**
     * Get currency display name
     */
    public function get_currency_name(string $currency_code = null): string {
        if ($currency_code === null) {
            $currency_code = $this->get_current_currency();
        }
        
        $currency_data = $this->get_currency_data($currency_code);
        return $currency_data['name'] ?? $currency_code;
    }
}
