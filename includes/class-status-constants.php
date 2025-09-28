<?php
/**
 * Status Constants for Hourly Room Booking Plugin
 * Centralized definition of all status values to avoid conflicts
 */

if (!defined('ABSPATH')) {
    exit;
}

class HRB_Status_Constants {
    
    /**
     * Booking Status Values
     */
    const BOOKING_STATUS_PENDING = 'pending';
    const BOOKING_STATUS_CONFIRMED = 'confirmed';
    const BOOKING_STATUS_COMPLETED = 'completed';
    const BOOKING_STATUS_CANCELLED = 'cancelled';
    const BOOKING_STATUS_NO_SHOW = 'no_show';
    
    /**
     * Payment Status Values
     */
    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_COMPLETED = 'completed';
    const PAYMENT_STATUS_CANCELLED = 'cancelled';
    const PAYMENT_STATUS_FAILED = 'failed';
    const PAYMENT_STATUS_REFUNDED = 'refunded';
    const PAYMENT_STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    
    /**
     * Get booking status labels
     */
    public static function get_booking_status_labels() {
        return [
            self::BOOKING_STATUS_PENDING => __('Pending', 'hourly-room-booking'),
            self::BOOKING_STATUS_CONFIRMED => __('Confirmed', 'hourly-room-booking'),
            self::BOOKING_STATUS_COMPLETED => __('Completed', 'hourly-room-booking'),
            self::BOOKING_STATUS_CANCELLED => __('Cancelled', 'hourly-room-booking'),
            self::BOOKING_STATUS_NO_SHOW => __('No Show', 'hourly-room-booking'),
        ];
    }
    
    /**
     * Get payment status labels
     */
    public static function get_payment_status_labels() {
        return [
            self::PAYMENT_STATUS_PENDING => __('Pending', 'hourly-room-booking'),
            self::PAYMENT_STATUS_COMPLETED => __('Completed', 'hourly-room-booking'),
            self::PAYMENT_STATUS_CANCELLED => __('Cancelled', 'hourly-room-booking'),
            self::PAYMENT_STATUS_FAILED => __('Failed', 'hourly-room-booking'),
            self::PAYMENT_STATUS_REFUNDED => __('Refunded', 'hourly-room-booking'),
            self::PAYMENT_STATUS_PARTIALLY_REFUNDED => __('Partially Refunded', 'hourly-room-booking'),
        ];
    }
    
    /**
     * Get all booking statuses
     */
    public static function get_booking_statuses() {
        return [
            self::BOOKING_STATUS_PENDING,
            self::BOOKING_STATUS_CONFIRMED,
            self::BOOKING_STATUS_COMPLETED,
            self::BOOKING_STATUS_CANCELLED,
            self::BOOKING_STATUS_NO_SHOW,
        ];
    }
    
    /**
     * Get all payment statuses
     */
    public static function get_payment_statuses() {
        return [
            self::PAYMENT_STATUS_PENDING,
            self::PAYMENT_STATUS_COMPLETED,
            self::PAYMENT_STATUS_CANCELLED,
            self::PAYMENT_STATUS_FAILED,
            self::PAYMENT_STATUS_REFUNDED,
            self::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ];
    }
}
