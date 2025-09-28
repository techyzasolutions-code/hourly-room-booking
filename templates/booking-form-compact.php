<?php
/**
 * Compact Room Booking Form Template
 * Displays a compact booking form for sidebar use
 */

if (!defined('ABSPATH')) {
    exit;
}

$room_id = isset($atts['room_id']) ? intval($atts['room_id']) : 0;

if (!$room_id || !$room) {
    echo '<div class="hrb-alert hrb-alert-error">' . __('Room not found or inactive', 'hourly-room-booking') . '</div>';
    return;
}

$room_manager = HRB_Room_Manager::getInstance();
$extras_manager = HRB_Extras::getInstance();
$available_extras = $extras_manager->get_extras('active');

// Get settings for pricing
$settings = HRB_Settings::getInstance();
$extra_people_price = $settings->get('hrb_extra_person_price', 15);
$currency_symbol = hrb_get_currency_symbol();
$pricing_label = $settings->get('hrb_pricing_label', '');
?>

<style>
/* Compact Booking Form Styles */
.hrb-compact-booking-wrapper {
    background: #ffffff;
    border-radius: 12px;
    padding: 24px;
    margin: 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    max-width: 100%;
    width: 100%;
}

.hrb-compact-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
}

.hrb-compact-title {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
    font-family: Georgia, serif;
}

.hrb-compact-price {
    font-size: 16px;
    color: #6b7280;
    font-weight: 500;
}

.hrb-compact-price strong {
    color: #1f2937;
    font-size: 18px;
}

.hrb-compact-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.hrb-compact-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.hrb-compact-row.single {
    grid-template-columns: 1fr;
}

.hrb-compact-row.triple {
    grid-template-columns: 1fr 1fr 1fr;
}

.hrb-compact-field {
    display: flex;
    flex-direction: column;
}

.hrb-compact-label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hrb-compact-input {
    padding: 12px 16px;
    border: 2px solid #d1d5db;
    border-radius: 8px;
    font-size: 16px;
    color: #1f2937;
    background: #ffffff;
    transition: all 0.2s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 12px center;
    background-repeat: no-repeat;
    background-size: 16px;
    padding-right: 40px;
}

.hrb-compact-input:focus {
    outline: none;
    border-color: #d4af37;
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
}

.hrb-compact-input[type="date"] {
    background-image: none;
    padding-right: 16px;
}

.hrb-compact-input[type="number"] {
    background-image: none;
    padding-right: 16px;
}

.hrb-compact-section {
    margin-top: 24px;
}

.hrb-compact-section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 16px 0;
    font-family: Georgia, serif;
}

.hrb-compact-extras {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.hrb-compact-extra {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.hrb-compact-extra:last-child {
    border-bottom: none;
}

.hrb-compact-extra-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.hrb-compact-checkbox {
    width: 18px;
    height: 18px;
    accent-color: #d4af37;
    cursor: pointer;
}

.hrb-compact-extra-name {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.hrb-compact-extra-price {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.hrb-compact-quantity {
    display: flex;
    align-items: center;
    gap: 8px;
}

.hrb-compact-qty-input {
    width: 60px;
    padding: 6px 8px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    text-align: center;
    font-size: 14px;
    background-image: none;
    padding-right: 8px;
}

.hrb-compact-total {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 2px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hrb-compact-total-label {
    font-size: 16px;
    font-weight: 700;
    color: #1f2937;
    font-family: Georgia, serif;
}

.hrb-compact-total-amount {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
}

.hrb-compact-button {
    width: 100%;
    background: #1f2937;
    color: #ffffff;
    border: none;
    padding: 16px 24px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-top: 20px;
}

.hrb-compact-button:hover {
    background: #111827;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.hrb-compact-button:active {
    transform: translateY(0);
}

.hrb-compact-button:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Responsive Design */
@media (max-width: 480px) {
    .hrb-compact-row {
        grid-template-columns: 1fr;
    }
    
    .hrb-compact-row.triple {
        grid-template-columns: 1fr;
    }
    
    .hrb-compact-wrapper {
        padding: 16px;
    }
    
    .hrb-compact-title {
        font-size: 20px;
    }
}

/* Loading States */
.hrb-compact-loading {
    opacity: 0.6;
    pointer-events: none;
}

.hrb-compact-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: hrb-spin 1s ease-in-out infinite;
    margin-right: 8px;
}

@keyframes hrb-spin {
    to {
        transform: rotate(360deg);
    }
}

/* Error States */
.hrb-compact-error {
    color: #dc2626;
    font-size: 12px;
    margin-top: 4px;
    display: none;
}

.hrb-compact-field.error .hrb-compact-input {
    border-color: #dc2626;
}

.hrb-compact-field.error .hrb-compact-error {
    display: block;
}

/* Success States */
.hrb-compact-success {
    color: #059669;
    font-size: 12px;
    margin-top: 4px;
    display: none;
}

.hrb-compact-field.success .hrb-compact-input {
    border-color: #059669;
}

.hrb-compact-field.success .hrb-compact-success {
    display: block;
}
</style>

<div class="hrb-compact-booking-wrapper">
    <div class="hrb-compact-header">
        <h2 class="hrb-compact-title">RESERVE:</h2>
        <div class="hrb-compact-price">
            <?php 
            $price_range = $room_manager->get_room_price_range($room);
            echo '<strong>' . $price_range['formatted'] . '</strong>';
            if ($pricing_label) {
                echo ' ' . esc_html($pricing_label);
            }
            ?>
        </div>
    </div>

    <form class="hrb-compact-form" id="hrb-compact-booking-form-<?php echo esc_attr($room_id); ?>" data-room-id="<?php echo esc_attr($room_id); ?>">
        <!-- Booking Details -->
        <div class="hrb-compact-row">
            <div class="hrb-compact-field">
                <label class="hrb-compact-label" for="compact-booking-date-<?php echo $room_id; ?>">Check In</label>
                <input type="date" 
                       class="hrb-compact-input" 
                       id="compact-booking-date-<?php echo $room_id; ?>" 
                       name="booking_date" 
                       required
                       min="<?php echo date('Y-m-d'); ?>"
                       max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                <div class="hrb-compact-error"></div>
            </div>
            <div class="hrb-compact-field">
                <label class="hrb-compact-label" for="compact-booking-duration-<?php echo $room_id; ?>">Duration</label>
                <select class="hrb-compact-input" 
                        id="compact-booking-duration-<?php echo $room_id; ?>" 
                        name="duration" 
                        required>
                    <option value="">Select Duration</option>
                    <option value="2">2 Hours</option>
                    <option value="3">3 Hours</option>
                    <option value="4">4 Hours</option>
                </select>
                <div class="hrb-compact-error"></div>
            </div>
        </div>

        <div class="hrb-compact-row">
            <div class="hrb-compact-field">
                <label class="hrb-compact-label" for="compact-start-time-<?php echo $room_id; ?>">Start Time</label>
                <select class="hrb-compact-input" 
                        id="compact-start-time-<?php echo $room_id; ?>" 
                        name="start_time" 
                        required>
                    <option value="">Select Time</option>
                </select>
                <div class="hrb-compact-error"></div>
            </div>
            <div class="hrb-compact-field">
                <label class="hrb-compact-label" for="compact-extra-people-<?php echo $room_id; ?>">Extra People</label>
                <input type="number" 
                       class="hrb-compact-input" 
                       id="compact-extra-people-<?php echo $room_id; ?>" 
                       name="extra_people" 
                       min="0" 
                       max="10" 
                       value="0">
                <div class="hrb-compact-error"></div>
            </div>
        </div>

        <!-- Extra Services -->
        <?php if (!empty($available_extras)): ?>
        <div class="hrb-compact-section">
            <h3 class="hrb-compact-section-title">Extra Services</h3>
            <div class="hrb-compact-extras">
                <?php foreach ($available_extras as $extra): ?>
                <div class="hrb-compact-extra">
                    <div class="hrb-compact-extra-info">
                        <input type="checkbox" 
                               class="hrb-compact-checkbox" 
                               id="compact-extra-<?php echo $extra->id; ?>-<?php echo $room_id; ?>" 
                               name="extras[<?php echo $extra->id; ?>]" 
                               value="1"
                               data-price="<?php echo esc_attr($extra->price); ?>">
                        <label for="compact-extra-<?php echo $extra->id; ?>-<?php echo $room_id; ?>" class="hrb-compact-extra-name">
                            <?php echo esc_html($extra->name); ?>
                        </label>
                        <span class="hrb-compact-extra-price">
                            <?php echo hrb_format_amount($extra->price); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Customer Details -->
        <div class="hrb-compact-section">
            <h3 class="hrb-compact-section-title">Your Details</h3>
            <div class="hrb-compact-row single">
                <div class="hrb-compact-field">
                    <label class="hrb-compact-label" for="compact-customer-name-<?php echo $room_id; ?>">Full Name</label>
                    <input type="text" 
                           class="hrb-compact-input" 
                           id="compact-customer-name-<?php echo $room_id; ?>" 
                           name="customer_name" 
                           required>
                    <div class="hrb-compact-error"></div>
                </div>
            </div>
            <div class="hrb-compact-row">
                <div class="hrb-compact-field">
                    <label class="hrb-compact-label" for="compact-customer-email-<?php echo $room_id; ?>">Email</label>
                    <input type="email" 
                           class="hrb-compact-input" 
                           id="compact-customer-email-<?php echo $room_id; ?>" 
                           name="customer_email" 
                           required>
                    <div class="hrb-compact-error"></div>
                </div>
                <div class="hrb-compact-field">
                    <label class="hrb-compact-label" for="compact-customer-phone-<?php echo $room_id; ?>">Phone</label>
                    <input type="tel" 
                           class="hrb-compact-input" 
                           id="compact-customer-phone-<?php echo $room_id; ?>" 
                           name="customer_phone" 
                           required>
                    <div class="hrb-compact-error"></div>
                </div>
            </div>
        </div>

        <!-- Payment Method -->
        <div class="hrb-compact-section">
            <h3 class="hrb-compact-section-title">Payment Method</h3>
            <div class="hrb-compact-row single">
                <div class="hrb-compact-field">
                    <select class="hrb-compact-input" name="payment_method" required>
                        <option value="">Select Payment Method</option>
                        <option value="onsite">Pay On-Site</option>
                        <option value="paypal">PayPal</option>
                    </select>
                    <div class="hrb-compact-error"></div>
                </div>
            </div>
        </div>

        <!-- Total Cost -->
        <div class="hrb-compact-total">
            <span class="hrb-compact-total-label">Total Cost</span>
            <span class="hrb-compact-total-amount" id="compact-total-amount-<?php echo $room_id; ?>">
                <?php echo hrb_format_amount(0); ?>
            </span>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="hrb-compact-button" id="compact-submit-btn-<?php echo $room_id; ?>">
            <span class="hrb-compact-spinner" style="display: none;"></span>
            Book Your Stay Now
        </button>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    const form = $('#hrb-compact-booking-form-<?php echo $room_id; ?>');
    const roomId = <?php echo $room_id; ?>;
    const currencySymbol = '<?php echo $currency_symbol; ?>';
    const extraPeoplePrice = <?php echo $extra_people_price; ?>;
    
    // Auto-fill user details for logged-in users
    <?php if (is_user_logged_in()): ?>
    const currentUser = {
        name: '<?php echo esc_js(wp_get_current_user()->display_name); ?>',
        email: '<?php echo esc_js(wp_get_current_user()->user_email); ?>',
        phone: '<?php echo esc_js(get_user_meta(wp_get_current_user()->ID, 'phone', true)); ?>'
    };
    
    if (currentUser.name) form.find('[name="customer_name"]').val(currentUser.name);
    if (currentUser.email) form.find('[name="customer_email"]').val(currentUser.email);
    if (currentUser.phone) form.find('[name="customer_phone"]').val(currentUser.phone);
    <?php endif; ?>
    
    // Load time slots when date and duration change
    function loadTimeSlots() {
        const date = form.find('[name="booking_date"]').val();
        const duration = form.find('[name="duration"]').val();
        
        if (!date || !duration) {
            form.find('[name="start_time"]').html('<option value="">Select Time</option>');
            return;
        }
        
        $.ajax({
            url: hrbAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'hrb_get_available_time_slots',
                room_id: roomId,
                date: date,
                duration: duration,
                nonce: hrbAjax.nonce
            },
            beforeSend: function() {
                form.addClass('hrb-compact-loading');
                form.find('[name="start_time"]').html('<option value="">Loading time slots...</option>').prop('disabled', true);
            },
            success: function(response) {
                const timeSelect = form.find('[name="start_time"]');
                
                if (response.success && response.data.slots && response.data.slots.length > 0) {
                    timeSelect.html('<option value="">Select Time</option>');
                    
                    response.data.slots.forEach(function(slot) {
                        timeSelect.append(`<option value="${slot.start_time}">${slot.label}</option>`);
                    });
                } else {
                    timeSelect.html('<option value="">No available times</option>');
                }
                timeSelect.prop('disabled', false);
            },
            error: function() {
                form.find('[name="start_time"]').html('<option value="">Error loading times</option>').prop('disabled', false);
            },
            complete: function() {
                form.removeClass('hrb-compact-loading');
            }
        });
    }
    
    // Calculate total price
    function calculateTotal() {
        const duration = parseInt(form.find('[name="duration"]').val()) || 0;
        const extraPeople = parseInt(form.find('[name="extra_people"]').val()) || 0;
        
        // Get base price (this would need to be fetched from server)
        let total = 0;
        
        // Add extra people cost
        total += extraPeople * extraPeoplePrice;
        
        // Add extras cost
        form.find('[name^="extras"]:checked').each(function() {
            const price = parseFloat($(this).data('price')) || 0;
            total += price;
        });
        
        form.find('#compact-total-amount-<?php echo $room_id; ?>').text(window.HRB.utils.formatPrice(total));
    }
    
    // Event handlers
    form.find('[name="booking_date"], [name="duration"]').on('change', loadTimeSlots);
    form.find('[name="extra_people"], [name^="extras"]').on('change', calculateTotal);
    
    // Form submission
    form.on('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = form.find('[type="submit"]');
        const spinner = submitBtn.find('.hrb-compact-spinner');
        
        // Show loading state
        submitBtn.prop('disabled', true);
        spinner.show();
        form.addClass('hrb-compact-loading');
        
        // Collect form data
        const formData = {
            action: 'hrb_create_booking',
            room_id: roomId,
            booking_date: form.find('[name="booking_date"]').val(),
            start_time: form.find('[name="start_time"]').val(),
            duration: form.find('[name="duration"]').val(),
            extra_people: form.find('[name="extra_people"]').val(),
            customer_name: form.find('[name="customer_name"]').val(),
            customer_email: form.find('[name="customer_email"]').val(),
            customer_phone: form.find('[name="customer_phone"]').val(),
            payment_method: form.find('[name="payment_method"]').val(),
            nonce: hrbAjax.nonce
        };
        
        // Add extras
        const extras = {};
        form.find('[name^="extras"]:checked').each(function() {
            const extraId = $(this).attr('name').match(/\[(\d+)\]/)[1];
            extras[extraId] = $(this).val();
        });
        
        formData.extras = extras;
        
        // Submit booking
        $.ajax({
            url: hrbAjax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Redirect to payment or show success
                    if (response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        alert('Booking created successfully!');
                        form[0].reset();
                        calculateTotal();
                    }
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to create booking'));
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            },
            complete: function() {
                submitBtn.prop('disabled', false);
                spinner.hide();
                form.removeClass('hrb-compact-loading');
            }
        });
    });
    
    // Initial calculation
    calculateTotal();
});
</script>
