/**
 * Real-time booking notification poller for admin + Room Booking Staff.
 *
 * - Polls hrb_check_new_bookings every N seconds.
 * - Shows a toast for each new customer-made booking.
 * - Plays a short chime (Web Audio API — no asset file).
 * - Audio is only unlocked after the first user interaction (browser
 *   autoplay restrictions). Until then, toasts still appear silently.
 * - Baseline is set on first load so existing bookings don't spam.
 */
(function ($) {
    'use strict';

    if (typeof hrbNotifications === 'undefined') return;

    var STORAGE_KEY = 'hrb_last_seen_booking_id_v1';
    var CONTAINER_ID = 'hrb-toast-container';
    var lastSeenId = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10);
    var audioCtx = null;
    var audioReady = false;

    function init() {
        ensureContainer();

        // Unlock audio on first user gesture (autoplay policy).
        $(document).one('click keydown touchstart', unlockAudio);

        // Initial bootstrap.
        checkNewBookings(true);

        // Tick on the configured interval, but only if the tab is visible.
        // Background tabs in modern browsers are throttled anyway, but
        // explicitly skipping the AJAX avoids running a server round-trip
        // for a user who isn't looking.
        setInterval(function () {
            if (document.visibilityState === 'hidden') return;
            checkNewBookings(false);
        }, hrbNotifications.pollInterval);

        // When the tab regains focus after being hidden, catch up immediately
        // instead of waiting for the next tick. Without this, a user who
        // switches back to the admin tab could miss a notification for up
        // to pollInterval ms.
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                checkNewBookings(false);
            }
        });
    }

    function ensureContainer() {
        if (!$('#' + CONTAINER_ID).length) {
            $('body').append('<div id="' + CONTAINER_ID + '" aria-live="polite"></div>');
        }
    }

    function unlockAudio() {
        try {
            var Ctor = window.AudioContext || window.webkitAudioContext;
            if (Ctor) {
                audioCtx = new Ctor();
                audioReady = true;
            }
        } catch (e) {
            audioReady = false;
        }
    }

    function checkNewBookings(isFirst) {
        $.ajax({
            url: hrbNotifications.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hrb_check_new_bookings',
                nonce: hrbNotifications.nonce,
                since_id: lastSeenId
            }
        }).done(function (resp) {
            if (!resp || !resp.success || !resp.data) return;
            var data = resp.data;

            // Establish baseline: first ever load, no notifications fired.
            if (isFirst && lastSeenId === 0) {
                lastSeenId = parseInt(data.latest_id || 0, 10);
                localStorage.setItem(STORAGE_KEY, lastSeenId);
                return;
            }

            if (data.bookings && data.bookings.length) {
                data.bookings.forEach(showToast);
                playChime();
            }

            var latest = parseInt(data.latest_id || 0, 10);
            if (latest > lastSeenId) {
                lastSeenId = latest;
                localStorage.setItem(STORAGE_KEY, lastSeenId);
            }
        });
    }

    function showToast(b) {
        var anonBadge = b.is_anonymous
            ? ' <span class="hrb-toast-badge">' + escapeHtml(hrbNotifications.strings.anonymous) + '</span>'
            : '';

        var $t = $(
            '<div class="hrb-toast" role="status">' +
              '<div class="hrb-toast-icon"><i class="bi bi-bell-fill"></i></div>' +
              '<div class="hrb-toast-body">' +
                '<div class="hrb-toast-title">' + escapeHtml(hrbNotifications.strings.newBooking) + '</div>' +
                '<div class="hrb-toast-customer">' + escapeHtml(b.customer_name) + anonBadge + '</div>' +
                '<div class="hrb-toast-meta">' +
                    '<span><i class="bi bi-door-closed-fill"></i> ' + escapeHtml(b.room_name) + '</span>' +
                    '<span><i class="bi bi-calendar-event-fill"></i> ' + escapeHtml(b.date) + '</span>' +
                    '<span><i class="bi bi-clock-fill"></i> ' + escapeHtml(b.time) + '</span>' +
                '</div>' +
                '<div class="hrb-toast-ref">' + escapeHtml(b.reference) + '</div>' +
              '</div>' +
              '<div class="hrb-toast-actions">' +
                '<a class="hrb-toast-view" href="' + b.edit_url + '">' + escapeHtml(hrbNotifications.strings.view) + '</a>' +
                '<button type="button" class="hrb-toast-close" aria-label="' + escapeHtml(hrbNotifications.strings.dismiss) + '">×</button>' +
              '</div>' +
            '</div>'
        );

        var dismiss = function () {
            $t.addClass('hrb-toast-leaving');
            setTimeout(function () { $t.remove(); }, 300);
        };

        $t.find('.hrb-toast-close').on('click', dismiss);
        $('#' + CONTAINER_ID).append($t);
        setTimeout(function () { $t.addClass('hrb-toast-visible'); }, 20);
        setTimeout(dismiss, 12000);
    }

    function playChime() {
        if (!audioReady || !audioCtx) return;
        try {
            var now = audioCtx.currentTime;
            // Two-tone ding: 880 Hz -> 660 Hz
            playTone(now,        880, 0.18, 0.22);
            playTone(now + 0.14, 660, 0.22, 0.18);
        } catch (e) { /* ignore */ }
    }

    function playTone(startTime, freq, gainPeak, duration) {
        var osc = audioCtx.createOscillator();
        var gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, startTime);
        gain.gain.setValueAtTime(0.0001, startTime);
        gain.gain.exponentialRampToValueAtTime(gainPeak, startTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start(startTime);
        osc.stop(startTime + duration + 0.02);
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    $(document).ready(init);
})(jQuery);
