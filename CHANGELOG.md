# Changelog

All notable changes to the Hourly Room Booking System plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2026-06-18

### Added
- **Cancellation fee (€15):** A flat €15 fee is charged when a cash/on-site booking is cancelled within the cancellation window (default 24h before start). PayPal/online bookings are excluded, and the fee is **not** charged if the booking was already fully paid. The fee is shown on the booking detail view, as a badge in the All Bookings list, in the cancellation email, and as a labelled, pending row in the Payments screen (payable on-site).
- **Payments "Pending" widget:** Replaces the always-empty "Pending Refunds" stat with the total amount still awaiting collection (respects the active filters).
- **"Cancelled" payment-status filter** on the Payments screen.
- **Branded email templates bundled with the plugin:** all 13 German email templates are now shipped in code (`includes/email-templates-data.php`) and synced into the database automatically on update via a one-time, version-gated migration — no reactivation required. Later manual edits in the admin editor are preserved.
- **German (de_DE) translations** for all newly added strings.

### Fixed
- **Room availability search now honors locks for a selected time + duration.** Previously, when a specific time was selected, the search only checked existing bookings and ignored both master locks and room locks — so a master lock affecting all rooms left most rooms bookable. All availability decisions now run through the single lock-aware engine (master locks, room locks, bookings, cooldown, booking window).
- **No-refund policy on cancellation:** cancelling a booking no longer cancels or refunds an already-**completed** payment — the money is kept and the payment stays `completed`, keeping Total Revenue accurate. Only **pending** (uncollected) payments are cancelled. Removed the automatic refund call on cancellation (the manual per-payment refund button remains).
- **Cancellation fee is no longer counted as part of a booking's payment.** A cancelled + unpaid booking with a collected fee no longer shows its payment as "Pending"; the booking's own payment status/total ignore the fee (it still appears as income in the Payments screen).
- **Marking a cancellation fee as paid** no longer re-confirms the cancelled booking, regenerates an invoice, or sends a payment-confirmation email — it marks only that fee row as collected.
- **Search loading overlay** is now scoped to the results section instead of covering the entire screen.
- **Email logo:** all templates now use the production logo URL (previously several pointed at a `localhost` URL that rendered as a broken image in real emails).

### Changed
- Removed the unused **Refunded / Partially Refunded** options from the Payments status filter (no refunds offered).
- Restyled the booking-confirmation, payment-confirmation and booking-modified emails to match the unified branded German design.

### Database
- Added a `cancellation_fee` column to the bookings table (auto-migrated on update).
