/**
 * Calendar functionality for Hourly Room Booking Plugin
 *
 * @package HourlyRoomBooking
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Calendar object
     */
    var HRBCalendar = {

        calendars: {},

        /**
         * Initialize calendar
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            $(document).on('change', '.hrb-room-filter', this.handleRoomFilter.bind(this));
        },

        /**
         * Initialize specific calendar instance
         */
        initializeCalendar: function(calendarId) {
            var $calendar = $('#' + calendarId);
            if (!$calendar.length) {
                console.error('Calendar element not found:', calendarId);
                return;
            }

            var roomId = $calendar.data('room-id') || 0;
            var view = $calendar.data('view') || 'month';
            var height = $calendar.data('height') || 'auto';
            var selectable = $calendar.data('selectable') || false;
            var editable = $calendar.data('editable') || false;

            var calendarOptions = {
                initialView: view === 'month' ? 'dayGridMonth' : 'timeGridWeek',
                height: height,
                selectable: selectable,
                editable: editable,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                locale: $('html').attr('lang') === 'de-DE' ? 'de' : 'en',
                firstDay: 1,
                slotMinTime: '08:00:00',
                slotMaxTime: '20:00:00',
                allDaySlot: false,
                slotDuration: '01:00:00',
                businessHours: {
                    daysOfWeek: [1, 2, 3, 4, 5, 6, 0],
                    startTime: '08:00',
                    endTime: '20:00'
                },
                events: {
                    url: hrbCalendar.ajaxUrl,
                    method: 'POST',
                    extraParams: function() {
                        return {
                            action: 'hrb_get_calendar_events',
                            room_id: roomId,
                            nonce: hrbCalendar.nonce
                        };
                    },
                    failure: function() {
                        alert(hrbCalendar.strings.error);
                    }
                },
                eventClick: this.handleEventClick.bind(this),
                select: this.handleDateSelect.bind(this),
                eventDidMount: this.handleEventRender.bind(this),
                loading: this.handleLoading.bind(this)
            };

            // Initialize FullCalendar
            var calendar = new FullCalendar.Calendar($calendar[0], calendarOptions);
            calendar.render();

            // Store calendar instance
            this.calendars[calendarId] = {
                instance: calendar,
                roomId: roomId,
                element: $calendar
            };
        },

        /**
         * Handle event click
         */
        handleEventClick: function(info) {
            var event = info.event;
            var props = event.extendedProps;

            if (props.type === 'booking') {
                this.showBookingDetails(props);
            }
        },

        /**
         * Handle date/time selection
         */
        handleDateSelect: function(info) {
            var calendar = info.view.calendar;
            var calendarId = this.getCalendarId(calendar);
            var calendarData = this.calendars[calendarId];

            if (!calendarData) return;

            // Show booking form or redirect to booking page
            var bookingUrl = this.buildBookingUrl({
                room_id: calendarData.roomId,
                date: info.startStr.split('T')[0],
                time: info.startStr.split('T')[1] || '09:00:00'
            });

            if (bookingUrl) {
                window.location.href = bookingUrl;
            }
        },

        /**
         * Handle event rendering
         */
        handleEventRender: function(info) {
            var event = info.event;
            var props = event.extendedProps;

            // Add tooltip
            if (props.type === 'booking') {
                $(info.el).attr('title', this.buildTooltipText(props));
            }

            // Add custom classes
            if (props.type) {
                $(info.el).addClass('hrb-event-' + props.type);
            }

            if (props.status) {
                $(info.el).addClass('hrb-status-' + props.status);
            }
        },

        /**
         * Handle loading state
         */
        handleLoading: function(isLoading) {
            if (isLoading) {
                $('.hrb-calendar').addClass('hrb-loading');
            } else {
                $('.hrb-calendar').removeClass('hrb-loading');
            }
        },

        /**
         * Handle room filter change
         */
        handleRoomFilter: function(e) {
            var $filter = $(e.target);
            var roomId = parseInt($filter.val()) || 0;
            var $wrapper = $filter.closest('.hrb-calendar-wrapper');
            var $calendar = $wrapper.find('.hrb-calendar');

            if ($calendar.length) {
                var calendarId = $calendar.attr('id');
                var calendarData = this.calendars[calendarId];

                if (calendarData) {
                    calendarData.roomId = roomId;
                    calendarData.instance.refetchEvents();
                }
            }
        },

        /**
         * Show booking details modal
         */
        showBookingDetails: function(props) {
            var modal = this.createBookingModal(props);
            $('body').append(modal);
            $('#hrb-booking-modal').fadeIn();
        },

        /**
         * Create booking details modal
         */
        createBookingModal: function(props) {
            var statusClass = 'hrb-status-' + props.status;
            var statusText = this.getStatusText(props.status);

            var modal = `
                <div id="hrb-booking-modal" class="hrb-modal" style="display: none;">
                    <div class="hrb-modal-content">
                        <div class="hrb-modal-header">
                            <h3>${hrbCalendar.strings.bookingDetails || 'Booking Details'}</h3>
                            <span class="hrb-modal-close">&times;</span>
                        </div>
                        <div class="hrb-modal-body">
                            <div class="hrb-booking-info">
                                <div class="hrb-info-row">
                                    <strong>${hrbCalendar.strings.room || 'Room'}:</strong>
                                    <span>${props.roomName}</span>
                                </div>
                                <div class="hrb-info-row">
                                    <strong>${hrbCalendar.strings.customer || 'Customer'}:</strong>
                                    <span>${props.customerName}</span>
                                </div>
                                <div class="hrb-info-row">
                                    <strong>${hrbCalendar.strings.email || 'Email'}:</strong>
                                    <span>${props.customerEmail}</span>
                                </div>
                                <div class="hrb-info-row">
                                    <strong>${hrbCalendar.strings.phone || 'Phone'}:</strong>
                                    <span>${props.customerPhone}</span>
                                </div>
                                <div class="hrb-info-row">
                                    <strong>${hrbCalendar.strings.status || 'Status'}:</strong>
                                    <span class="hrb-status-badge ${statusClass}">${statusText}</span>
                                </div>
                                <div class="hrb-info-row">
                                    <strong>${hrbCalendar.strings.amount || 'Amount'}:</strong>
                                    <span>¬${parseFloat(props.totalAmount).toFixed(2)}</span>
                                </div>
                                ${props.extraPeople > 0 ? `
                                <div class="hrb-info-row">
                                    <strong>${hrbCalendar.strings.extraPeople || 'Extra People'}:</strong>
                                    <span>${props.extraPeople}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        <div class="hrb-modal-footer">
                            <button type="button" class="hrb-btn hrb-btn-secondary" data-dismiss="modal">
                                ${hrbCalendar.strings.close || 'Close'}
                            </button>
                        </div>
                    </div>
                </div>
            `;

            return modal;
        },

        /**
         * Build tooltip text
         */
        buildTooltipText: function(props) {
            var text = `${props.customerName}\n`;
            text += `${props.roomName}\n`;
            text += `¬${parseFloat(props.totalAmount).toFixed(2)}\n`;
            text += `${this.getStatusText(props.status)}`;

            return text;
        },

        /**
         * Get status text
         */
        getStatusText: function(status) {
            var statuses = {
                'pending': hrbCalendar.strings.pending || 'Pending',
                'confirmed': hrbCalendar.strings.confirmed || 'Confirmed',
                'completed': hrbCalendar.strings.completed || 'Completed',
                'cancelled': hrbCalendar.strings.cancelled || 'Cancelled',
                'no_show': hrbCalendar.strings.noShow || 'No Show'
            };

            return statuses[status] || status;
        },

        /**
         * Build booking URL
         */
        buildBookingUrl: function(params) {
            var url = window.location.origin + window.location.pathname;

            // Add room booking page logic here
            if (params.room_id) {
                url += '?room_id=' + params.room_id;
            }

            if (params.date) {
                url += (url.indexOf('?') > -1 ? '&' : '?') + 'date=' + params.date;
            }

            if (params.time) {
                url += (url.indexOf('?') > -1 ? '&' : '?') + 'time=' + params.time;
            }

            return url;
        },

        /**
         * Get calendar ID from FullCalendar instance
         */
        getCalendarId: function(calendar) {
            for (var id in this.calendars) {
                if (this.calendars[id].instance === calendar) {
                    return id;
                }
            }
            return null;
        },

        /**
         * Refresh calendar
         */
        refreshCalendar: function(calendarId) {
            if (this.calendars[calendarId]) {
                this.calendars[calendarId].instance.refetchEvents();
            }
        },

        /**
         * Get available time slots for a date
         */
        getTimeSlots: function(roomId, date, callback) {
            $.ajax({
                url: hrbCalendar.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hrb_get_time_slots',
                    room_id: roomId,
                    date: date,
                    nonce: hrbCalendar.nonce
                },
                success: function(response) {
                    if (response.success) {
                        callback(response.data);
                    } else {
                        console.error('Error getting time slots:', response.data);
                        callback([]);
                    }
                },
                error: function() {
                    console.error('AJAX error getting time slots');
                    callback([]);
                }
            });
        }
    };

    /**
     * Global function to initialize calendar
     */
    window.hrbInitializeCalendar = function(calendarId) {
        HRBCalendar.initializeCalendar(calendarId);
    };

    /**
     * Modal handling
     */
    $(document).on('click', '.hrb-modal-close, [data-dismiss="modal"]', function() {
        $('#hrb-booking-modal').fadeOut(function() {
            $(this).remove();
        });
    });

    $(document).on('click', '.hrb-modal', function(e) {
        if (e.target === this) {
            $(this).fadeOut(function() {
                $(this).remove();
            });
        }
    });

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        HRBCalendar.init();
    });

    /**
     * Expose HRBCalendar to global scope for external access
     */
    window.HRBCalendar = HRBCalendar;

})(jQuery);