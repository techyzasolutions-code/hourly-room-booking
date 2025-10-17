/**
 * Hourly Room Booking Plugin - Frontend JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Global HRB object
    window.HRB = window.HRB || {};

    /**
     * Main HRB App Class
     */
    class HRBApp {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.initComponents();
            this.loadTranslations();
        }

        bindEvents() {
            $(document).ready(() => {
                this.onDocumentReady();
            });

            // Global AJAX error handler
            $(document).ajaxError((event, jqXHR, ajaxSettings, thrownError) => {
                console.error('HRB AJAX Error:', thrownError);
                const ajaxObj = window.hrbAjax || window.hrb_ajax || {};
                const strings = ajaxObj.strings || {};
                const errorMessage = (strings && strings.booking_error) || 'An error occurred. Please try again.';

                // Only show error if it's not undefined or empty
                if (errorMessage && errorMessage !== 'undefined' && errorMessage.toString().trim() !== '') {
                    this.showMessage('error', errorMessage);
                }
            });
        }

        onDocumentReady() {
            this.initDatePickers();
            this.initFormValidation();
            this.initBookingForms();
            this.initSearchForms();
            this.initCalendars();
        }

        initComponents() {
            // Initialize all HRB components
            if (typeof HRBBookingForm !== 'undefined') {
                window.HRB.bookingForm = new HRBBookingForm();
            }
            if (typeof HRBRoomSearch !== 'undefined') {
                window.HRB.roomSearch = new HRBRoomSearch();
            }
            // HRBCalendar is handled separately via FullCalendar
        }

        loadTranslations() {
            // Check both possible AJAX object names
            const ajaxObj = window.hrbAjax || window.hrb_ajax || {};
            this.strings = ajaxObj.strings || {};
            this.ajaxUrl = ajaxObj.ajaxUrl || ajaxObj.ajax_url || '';
            this.nonce = ajaxObj.nonce || '';
        }

        initDatePickers() {
            $('.hrb-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0,
                maxDate: '+1y',
                showOtherMonths: true,
                selectOtherMonths: true,
                changeMonth: true,
                changeYear: true,
                beforeShowDay: (date) => {
                    // Disable past dates
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    return [date >= today, ''];
                }
            });
        }

        initFormValidation() {
            $('.hrb-form').on('submit', function(e) {
                const form = $(this);
                const isValid = HRB.app.validateForm(form);
                
                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
            });
        }

        validateForm(form) {
            let isValid = true;
            const requiredFields = form.find('[required]');
            const self = this;

            requiredFields.each(function() {
                const field = $(this);
                const value = field.val().trim();

                if (!value) {
                    isValid = false;
                    field.addClass('error');
                    self.showFieldError(field, 'This field is required');
                } else {
                    field.removeClass('error');
                    self.hideFieldError(field);
                }
            });

            // Email validation
            form.find('input[type="email"]').each(function() {
                const field = $(this);
                const email = field.val().trim();

                if (email && !self.isValidEmail(email)) {
                    isValid = false;
                    field.addClass('error');
                    self.showFieldError(field, 'Please enter a valid email address');
                }
            });

            // Phone validation
            form.find('input[type="tel"]').each(function() {
                const field = $(this);
                const phone = field.val().trim();

                if (phone && !self.isValidPhone(phone)) {
                    isValid = false;
                    field.addClass('error');
                    self.showFieldError(field, 'Please enter a valid phone number');
                }
            });

            return isValid;
        }

        showFieldError(field, message) {
            field.removeClass('success').addClass('error');
            
            let errorElement = field.siblings('.hrb-form-error');
            if (errorElement.length === 0) {
                errorElement = $('<div class="hrb-form-error"></div>');
                field.after(errorElement);
            }
            errorElement.text(message).show();
        }

        hideFieldError(field) {
            field.removeClass('error');
            field.siblings('.hrb-form-error').hide();
        }

        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        isValidPhone(phone) {
            const phoneRegex = /^[\+]?[\d\s\-\(\)]{10,}$/;
            return phoneRegex.test(phone);
        }

        initBookingForms() {
            // Initialize booking form components
            this.initTimeSlotPickers();
            this.initPriceCalculator();
            this.initMultiStepForms();
        }

        initSearchForms() {
            $('.hrb-room-search-form').on('submit', (e) => {
                e.preventDefault();
                this.searchRooms($(e.target));
            });
        }

        initCalendars() {
            $('.hrb-calendar').each(function() {
                const calendar = $(this);
                const roomId = calendar.data('room-id');
                
                if (roomId) {
                    HRB.app.loadCalendar(calendar, roomId);
                }
            });
        }

        initTimeSlotPickers() {
            const self = this;
            $(document).on('click', '.hrb-time-slot:not(.unavailable)', function() {
                const slot = $(this);
                const container = slot.closest('.hrb-time-slots');

                // Single selection mode
                container.find('.hrb-time-slot').removeClass('selected');
                slot.addClass('selected');

                // Update hidden fields
                const startTime = slot.data('start-time');
                const endTime = slot.data('end-time');
                const price = slot.data('price');

                container.closest('form').find('input[name="start_time"]').val(startTime);
                container.closest('form').find('input[name="end_time"]').val(endTime);

                // Trigger price calculation
                self.calculatePrice(slot.closest('form'));
            });
        }

        initPriceCalculator() {
            // Auto-calculate price when form values change (but not for forms with local pricing)
            $(document).on('change', '.hrb-booking-form input, .hrb-booking-form select', function() {
                const form = $(this).closest('.hrb-booking-form');
                // Skip AJAX pricing if form has local pricing enabled
                const localPricing = form.data('local-pricing');
                console.log('Form change detected, local-pricing:', localPricing, typeof localPricing);
                if (localPricing === true || localPricing === 'true') {
                    console.log('Skipping AJAX pricing - using local pricing');
                    return; // Let the local function handle it
                }
                console.log('Using AJAX pricing');
                HRB.app.calculatePrice(form);
            });

            // Extra people counter
            $(document).on('click', '.hrb-people-counter .btn-minus', function() {
                const input = $(this).siblings('input');
                const current = parseInt(input.val()) || 0;
                if (current > 0) {
                    input.val(current - 1).trigger('change');
                }
            });

            $(document).on('click', '.hrb-people-counter .btn-plus', function() {
                const input = $(this).siblings('input');
                const current = parseInt(input.val()) || 0;
                const max = parseInt(input.attr('max')) || 10;
                if (current < max) {
                    input.val(current + 1).trigger('change');
                }
            });
        }

        initMultiStepForms() {
            // Multi-step form navigation is handled by individual booking form templates
            // This method is kept for compatibility but doesn't interfere with custom form logic
            console.log('Multi-step forms initialized (handled by templates)');
        }

        showStep(step) {
            const form = step.closest('.hrb-booking-form');
            const stepNumber = step.data('step');
            
            // Hide all steps
            form.find('.hrb-form-step-content').removeClass('active');
            step.addClass('active');
            
            // Update step indicators
            form.find('.hrb-step').removeClass('active completed');
            form.find(`.hrb-step[data-step="${stepNumber}"]`).addClass('active');
            form.find(`.hrb-step[data-step="${stepNumber}"]`).prevAll('.hrb-step').addClass('completed');
        }

        validateStep(step) {
            const requiredFields = step.find('[required]');
            let isValid = true;

            requiredFields.each(function() {
                if (!$(this).val().trim()) {
                    isValid = false;
                    $(this).addClass('error');
                    HRB.app.showFieldError($(this), 'This field is required');
                }
            });

            return isValid;
        }

        // AJAX Methods
        checkAvailability(roomId, date, startTime, endTime) {
            return new Promise((resolve, reject) => {
                const ajaxObj = window.hrbAjax || window.hrb_ajax || {};
                $.ajax({
                    url: ajaxObj.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'hrb_check_availability',
                        nonce: ajaxObj.nonce,
                        room_id: roomId,
                        date: date,
                        start_time: startTime,
                        end_time: endTime
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(response.data);
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(error);
                    }
                });
            });
        }

        calculatePrice(form) {
            const formData = this.getFormData(form);
            
            if (!formData.room_id || !formData.booking_date || !formData.start_time || !formData.end_time) {
                return;
            }

            this.showLoading(form.find('.hrb-booking-summary'));

            const ajaxObj = window.hrbAjax || window.hrb_ajax || {};
            $.ajax({
                url: ajaxObj.ajax_url,
                type: 'POST',
                data: {
                    action: 'hrb_calculate_price',
                    nonce: ajaxObj.nonce,
                    ...formData
                },
                success: (response) => {
                    this.hideLoading(form.find('.hrb-booking-summary'));
                    
                    if (response.success) {
                        this.updatePriceSummary(form, response.data);
                    } else {
                        this.showMessage('error', response.data);
                    }
                },
                error: () => {
                    this.hideLoading(form.find('.hrb-booking-summary'));
                    this.showMessage('error', 'Failed to calculate price');
                }
            });
        }

        searchRooms(form) {
            const formData = this.getFormData(form);
            const resultsContainer = form.siblings('.hrb-search-results');
            
            //this.showLoading(resultsContainer);

            const ajaxObj = window.hrbAjax || window.hrb_ajax || {};
            $.ajax({
                url: ajaxObj.ajax_url,
                type: 'POST',
                data: {
                    action: 'hrb_search_rooms',
                    nonce: ajaxObj.nonce,
                    ...formData
                },
                success: (response) => {
                    this.hideLoading(resultsContainer);
                    
                    if (response.success) {
                        this.displaySearchResults(resultsContainer, response.data);
                    } else {
                        this.showMessage('error', response.data);
                    }
                },
                error: () => {
                    this.hideLoading(resultsContainer);
                    this.showMessage('error', 'Search failed');
                }
            });
        }

        submitBooking(form) {
            const formData = this.getFormData(form);
            
            this.showLoading(form);

            return new Promise((resolve, reject) => {
                const ajaxObj = window.hrbAjax || window.hrb_ajax || {};
                $.ajax({
                    url: ajaxObj.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'hrb_submit_booking',
                        nonce: ajaxObj.nonce,
                        ...formData
                    },
                    success: (response) => {
                        this.hideLoading(form);

                        if (response.success) {
                            resolve(response.data);
                        } else {
                            const errorMessage = response.data || 'Booking submission failed';
                            reject(errorMessage);
                        }
                    },
                    error: (xhr, status, error) => {
                        this.hideLoading(form);
                        const errorMessage = error || status || 'Network error occurred';
                        reject(errorMessage);
                    }
                });
            });
        }

        sendOTP(email, phone, type) {
            return new Promise((resolve, reject) => {
                const ajaxObj = window.hrbAjax || window.hrb_ajax || {};
                $.ajax({
                    url: ajaxObj.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'hrb_send_otp',
                        nonce: ajaxObj.nonce,
                        email: email,
                        phone: phone,
                        type: type
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(response.data);
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(error);
                    }
                });
            });
        }

        verifyOTP(email, phone, otpCode) {
            return new Promise((resolve, reject) => {
                const ajaxObj = window.hrbAjax || window.hrb_ajax || {};
                $.ajax({
                    url: ajaxObj.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'hrb_verify_otp',
                        nonce: ajaxObj.nonce,
                        email: email,
                        phone: phone,
                        otp_code: otpCode
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(response.data);
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(error);
                    }
                });
            });
        }

        loadCalendar(container, roomId) {
            const ajaxObj = window.hrbAjax || window.hrb_ajax || {};
            $.ajax({
                url: ajaxObj.ajax_url,
                type: 'POST',
                data: {
                    action: 'hrb_get_calendar_events',
                    nonce: ajaxObj.nonce,
                    room_id: roomId,
                    start: moment().startOf('month').format('YYYY-MM-DD'),
                    end: moment().endOf('month').format('YYYY-MM-DD')
                },
                success: (response) => {
                    if (response.success) {
                        this.renderCalendar(container, response.data);
                    }
                }
            });
        }

        // Utility Methods
        getFormData(form) {
            const data = {};
            form.find('input, select, textarea').each(function() {
                const field = $(this);
                const name = field.attr('name');
                const value = field.val();
                
                if (name) {
                    if (field.is(':checkbox') || field.is(':radio')) {
                        if (field.is(':checked')) {
                            // Handle multiple checkboxes with same name (like extras[])
                            if (name.endsWith('[]')) {
                                if (!data[name]) {
                                    data[name] = [];
                                }
                                data[name].push(value);
                            } else {
                                data[name] = value;
                            }
                        }
                    } else {
                        data[name] = value;
                    }
                }
            });
            return data;
        }

        updatePriceSummary(form, pricing) {
            const summary = form.find('.hrb-booking-summary');
            
            if (summary.length === 0) return;

            let html = '<div class="hrb-summary-title">Buchungsübersicht</div>';

            html += `<div class="hrb-summary-item">
                <span class="hrb-summary-label">${this.strings.base_price || 'Base Price'}</span>
                <span class="hrb-summary-value">${this.formatPrice(pricing.base_price)}</span>
            </div>`;


            if (pricing.extra_people_cost > 0) {
                html += `<div class="hrb-summary-item">
                    <span class="hrb-summary-label">Additional People</span>
                    <span class="hrb-summary-value">${this.formatPrice(pricing.extra_people_cost)}</span>
                </div>`;
            }

            if (pricing.extras_cost > 0) {
                html += `<div class="hrb-summary-item">
                    <span class="hrb-summary-label">Extras</span>
                    <span class="hrb-summary-value">${this.formatPrice(pricing.extras_cost)}</span>
                </div>`;
            }

            // Add subtotal line
            html += `<div class="hrb-summary-item hrb-summary-subtotal">
                <span class="hrb-summary-label"><strong>Zwischensumme</strong></span>
                <span class="hrb-summary-value"><strong>${this.formatPrice(pricing.subtotal)}</strong></span>
            </div>`;

            // Add VAT line
            if (pricing.tax_amount > 0) {
                html += `<div class="hrb-summary-item hrb-summary-vat">
                    <span class="hrb-summary-label">zzgl. ${pricing.tax_rate}% MwSt.</span>
                    <span class="hrb-summary-value">${this.formatPrice(pricing.tax_amount)}</span>
                </div>`;
            }

            // Only show PayPal fee if PayPal payment method is selected
            const paymentMethod = form.find('input[name="payment_method"]:checked').val();
            if (pricing.paypal_fee > 0 && paymentMethod === 'paypal') {
                html += `<div class="hrb-summary-item">
                    <span class="hrb-summary-label">${this.strings.paypal_fee || 'PayPal Fee (3%)'}</span>
                    <span class="hrb-summary-value">${this.formatPrice(pricing.paypal_fee)}</span>
                </div>`;
            }

            html += `<div class="hrb-summary-item hrb-summary-total">
                <span class="hrb-summary-label"><strong>${this.strings.total || 'Total'}</strong></span>
                <span class="hrb-summary-value"><strong>${this.formatPrice(pricing.total_amount)}</strong></span>
            </div>`;

            summary.html(html);
        }

        displaySearchResults(container, rooms) {
            let html = '';

            if (rooms.length === 0) {
                html = '<div class="hrb-alert hrb-alert-info">No available rooms found for the selected time.</div>';
            } else {
                html = '<div class="hrb-rooms-grid">';

                rooms.forEach(room => {
                    html += `<div class="hrb-room-card">
                        <div class="hrb-room-content">
                            <h3 class="hrb-room-title">${room.name}</h3>
                            <p class="hrb-room-description">${room.description}</p>
                            <div class="hrb-room-features">
                                <span class="hrb-room-feature">👥 ${room.capacity} People</span>
                            </div>
                            <div class="hrb-room-price">${room.formatted_price} <span class="hrb-room-price-unit">/ hour</span></div>
                            <button class="hrb-btn hrb-btn-primary hrb-btn-block hrb-select-room" data-room-id="${room.id}">
                                Select Room
                            </button>
                        </div>
                    </div>`;
                });
                
                html += '</div>';
            }

            container.html(html);
        }

        renderCalendar(container, events) {
            // Basic calendar rendering - you can integrate with FullCalendar here
            console.log('Calendar events:', events);
        }

        showLoading(element) {
            // Remove any existing loading states
            this.hideLoading(element);
            
            // Create overlay loading
            const overlay = $(`
                <div class="hrb-loading-overlay">
                    <div class="hrb-loading-message">
                        <div class="hrb-loading-spinner"></div>
                    </div>
                </div>
            `);
            
            element.append(overlay);
        }

        hideLoading(element) {
            element.find('.hrb-loading-overlay, .hrb-loading-message').remove();
        }

        showMessage(type, message) {
            // Don't show undefined, null, or empty messages
            if (!message || message === 'undefined' || message === undefined || message.toString().trim() === '') {
                console.warn('HRB: Attempted to show undefined/empty message:', message);
                return;
            }

            const alertClass = `hrb-alert-${type}`;
            const alert = $(`<div class="hrb-alert ${alertClass}">${message}</div>`);

            // Remove existing alerts
            $('.hrb-alert').remove();

            // Add new alert
            $('.hrb-booking-form').first().prepend(alert);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                alert.fadeOut(() => alert.remove());
            }, 5000);
        }

        formatPrice(amount) {
            const ajaxData = window.hrbAjax || {};
            const currencySymbol = ajaxData.currency_symbol || '$';
            const currencyCode = ajaxData.currency_code || 'EUR';
            
            const formattedAmount = parseFloat(amount).toFixed(2);
            
            // Currency positioning logic
            if (currencyCode === 'USD') {
                // USD: symbol before amount
                return currencySymbol + formattedAmount;
            } else {
                // EUR and others: symbol after amount
                return formattedAmount + ' ' + currencySymbol;
            }
        }

        formatDate(date) {
            return new Intl.DateTimeFormat('de-DE').format(new Date(date));
        }

        formatTime(time) {
            return time.substring(0, 5); // HH:MM format
        }
    }

    // Initialize the app
    window.HRB.app = new HRBApp();

    // Expose utility methods globally
    window.HRB.utils = {
        formatPrice: (amount) => window.HRB.app.formatPrice(amount),
        formatDate: (date) => window.HRB.app.formatDate(date),
        formatTime: (time) => window.HRB.app.formatTime(time),
        showMessage: (type, message) => window.HRB.app.showMessage(type, message),
        getFormData: (form) => window.HRB.app.getFormData(form)
    };

})(jQuery);