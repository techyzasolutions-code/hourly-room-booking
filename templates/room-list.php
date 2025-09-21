<?php
/**
 * Room List Template
 * Displays a list/grid of available rooms
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure we have the required variables
if (!isset($rooms) || !isset($atts)) {
    return;
}

$columns = intval($atts['columns']);
$show_price = $atts['show_price'] === 'true';
$show_capacity = $atts['show_capacity'] === 'true';
$show_amenities = $atts['show_amenities'] === 'true';

// Get currency symbol from settings
$settings = HRB_Settings::getInstance();
$currency_symbol = $settings->get('hrb_currency_symbol', '�');
?>

<div class="hrb-room-list">
    <?php if (empty($rooms)): ?>
        <div class="hrb-no-rooms">
            <p><?php _e('No rooms available at this time.', 'hourly-room-booking'); ?></p>
        </div>
    <?php else: ?>
        <div class="hrb-rooms-grid hrb-columns-<?php echo esc_attr($columns); ?>">
            <?php foreach ($rooms as $room): ?>
                <?php if (!$room->is_active) continue; ?>

                <div class="hrb-room-card">
                    <div class="hrb-room-image">
                        <?php
                        $room_manager = HRB_Room_Manager::getInstance();
                        $images = $room_manager->get_room_images($room->id);
                        if (!empty($images)):
                        ?>
                            <img src="<?php echo esc_url($images[0]); ?>" alt="<?php echo esc_attr($room->name); ?>">
                        <?php else: ?>
                            <div class="hrb-room-placeholder">
                                <i class="hrb-icon-room"></i>
                            </div>
                        <?php endif; ?>

                        <?php if ($show_price): ?>
                            <div class="hrb-room-price">
                                <span class="hrb-price">
                                    <?php echo $currency_symbol; ?><?php echo number_format($room->hourly_price, 2); ?>
                                </span>
                                <span class="hrb-price-label"><?php _e('per hour', 'hourly-room-booking'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="hrb-room-content">
                        <h3 class="hrb-room-title"><?php echo esc_html($room->name); ?></h3>

                        <?php if (!empty($room->description)): ?>
                            <p class="hrb-room-description">
                                <?php echo esc_html(wp_trim_words($room->description, 20)); ?>
                            </p>
                        <?php endif; ?>

                        <div class="hrb-room-details">
                            <?php if ($show_capacity): ?>
                                <div class="hrb-room-detail">
                                    <i class="hrb-icon-people"></i>
                                    <span><?php printf(__('Up to %d people', 'hourly-room-booking'), $room->capacity); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($show_amenities): ?>
                                <?php
                                $amenities = $room_manager->get_room_amenities($room->id);
                                if (!empty($amenities)):
                                ?>
                                    <div class="hrb-room-detail">
                                        <i class="hrb-icon-amenities"></i>
                                        <span><?php echo implode(', ', array_slice($amenities, 0, 3)); ?>
                                        <?php if (count($amenities) > 3): ?>
                                            <span class="hrb-more-amenities">+<?php echo count($amenities) - 3; ?> <?php _e('more', 'hourly-room-booking'); ?></span>
                                        <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div class="hrb-room-actions">
                            <a href="#" class="hrb-btn hrb-btn-primary hrb-view-room" data-room-id="<?php echo $room->id; ?>">
                                <?php _e('View Details', 'hourly-room-booking'); ?>
                            </a>
                            <a href="#" class="hrb-btn hrb-btn-secondary hrb-book-room" data-room-id="<?php echo $room->id; ?>">
                                <?php _e('Book Now', 'hourly-room-booking'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Professional Design Variables */
:root {
    --hrb-primary: #0073aa;
    --hrb-secondary: #005a87;
    --hrb-accent: #0078d4;
    --hrb-success: #107c10;
    --hrb-text: #333333;
    --hrb-text-light: #666666;
    --hrb-border: #e1e1e1;
    --hrb-background: #ffffff;
    --hrb-background-light: #f8f9fa;
    --hrb-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    --hrb-shadow-hover: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.hrb-room-list {
    margin: 20px 0;
    background: var(--hrb-background);
    border-radius: 8px;
    padding: 30px;
    border: 1px solid var(--hrb-border);
    box-shadow: var(--hrb-shadow);
}

.hrb-rooms-grid {
    display: grid;
    gap: 30px;
    margin-bottom: 30px;
    position: relative;
    z-index: 1;
}

.hrb-rooms-grid.hrb-columns-1 { grid-template-columns: 1fr; }
.hrb-rooms-grid.hrb-columns-2 { grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); }
.hrb-rooms-grid.hrb-columns-3 { grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); }
.hrb-rooms-grid.hrb-columns-4 { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }

@media (max-width: 768px) {
    .hrb-rooms-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    .hrb-room-list {
        padding: 20px;
        margin: 10px;
    }
}

.hrb-room-card {
    background: var(--hrb-background);
    border: 1px solid var(--hrb-border);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: var(--hrb-shadow);
}

.hrb-room-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--hrb-shadow-hover);
    border-color: var(--hrb-primary);
}

.hrb-room-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.hrb-room-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.hrb-room-card:hover .hrb-room-image img {
    transform: scale(1.05);
}

.hrb-room-placeholder {
    width: 100%;
    height: 100%;
    background: var(--hrb-background-light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hrb-text-light);
    font-size: 32px;
}

.hrb-room-price {
    position: absolute;
    top: 15px;
    right: 15px;
    background: var(--hrb-background);
    color: var(--hrb-text);
    padding: 8px 12px;
    border-radius: 6px;
    text-align: center;
    border: 1px solid var(--hrb-border);
    box-shadow: var(--hrb-shadow);
}

.hrb-price {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: var(--hrb-primary);
}

.hrb-price-label {
    display: block;
    font-size: 11px;
    color: var(--hrb-text-light);
}

.hrb-room-content {
    padding: 20px;
}

.hrb-room-title {
    margin: 0 0 12px 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--hrb-text);
}

.hrb-room-description {
    margin: 0 0 16px 0;
    color: var(--hrb-text-light);
    line-height: 1.5;
    font-size: 14px;
}

.hrb-room-details {
    margin-bottom: 20px;
}

.hrb-room-detail {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-size: 14px;
    color: var(--hrb-text);
    background: var(--hrb-background-light);
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid var(--hrb-primary);
}

.hrb-room-detail i {
    margin-right: 8px;
    width: 16px;
    color: var(--hrb-primary);
    font-size: 14px;
}

.hrb-more-amenities {
    color: var(--hrb-text-light);
    font-size: 12px;
    font-weight: 500;
}

.hrb-room-actions {
    display: flex;
    gap: 15px;
}

.hrb-btn {
    flex: 1;
    padding: 10px 16px;
    border: 1px solid transparent;
    border-radius: 6px;
    text-decoration: none;
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.hrb-btn-primary {
    background: var(--hrb-primary);
    color: white;
    border-color: var(--hrb-primary);
}

.hrb-btn-primary:hover {
    background: var(--hrb-secondary);
    border-color: var(--hrb-secondary);
    color: white;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: var(--hrb-shadow-hover);
}

.hrb-btn-secondary {
    background: var(--hrb-background);
    color: var(--hrb-primary);
    border-color: var(--hrb-primary);
}

.hrb-btn-secondary:hover {
    background: var(--hrb-primary);
    color: white;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: var(--hrb-shadow-hover);
}

.hrb-no-rooms {
    text-align: center;
    padding: 40px;
    background: var(--hrb-background-light);
    border-radius: 8px;
    color: var(--hrb-text-light);
    border: 1px solid var(--hrb-border);
}

/* Icons using modern Unicode */
.hrb-icon-room:before { content: "🏢"; }
.hrb-icon-people:before { content: "👥"; }
.hrb-icon-amenities:before { content: "⭐"; }
/* Professional Modal Styles */
.hrb-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.hrb-modal-content {
    background: var(--hrb-background);
    border: 1px solid var(--hrb-border);
    border-radius: 8px;
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: var(--hrb-shadow-hover);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.hrb-modal-header {
    background: var(--hrb-background-light);
    padding: 20px 24px;
    border-bottom: 1px solid var(--hrb-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hrb-modal-header h3 {
    margin: 0;
    color: var(--hrb-text);
    font-size: 20px;
    font-weight: 600;
}

.hrb-modal-close {
    background: var(--hrb-background);
    border: 1px solid var(--hrb-border);
    font-size: 18px;
    color: var(--hrb-text);
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.hrb-modal-close:hover {
    background: var(--hrb-background-light);
    border-color: var(--hrb-primary);
}

.hrb-modal-body {
    padding: 24px;
    max-height: calc(90vh - 80px);
    overflow-y: auto;
}

.hrb-modal-body::-webkit-scrollbar {
    width: 6px;
}

.hrb-modal-body::-webkit-scrollbar-track {
    background: var(--hrb-background-light);
    border-radius: 3px;
}

.hrb-modal-body::-webkit-scrollbar-thumb {
    background: var(--hrb-border);
    border-radius: 3px;
}

.hrb-loading {
    text-align: center;
    padding: 40px;
    color: var(--hrb-text-light);
    font-style: italic;
}

.hrb-loading::before {
    content: '';
    display: inline-block;
    width: 24px;
    height: 24px;
    border: 3px solid var(--hrb-border);
    border-radius: 50%;
    border-top-color: var(--hrb-primary);
    animation: spin 1s linear infinite;
    margin-bottom: 12px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.hrb-error {
    color: #dc2626;
    background: #fef2f2;
    border: 1px solid #fecaca;
    padding: 16px;
    border-radius: 6px;
    text-align: center;
}

@media (max-width: 768px) {
    .hrb-modal-content {
        margin: 10px;
        max-width: calc(100% - 20px);
        border-radius: 15px;
    }

    .hrb-modal-header {
        padding: 20px 25px;
    }

    .hrb-modal-header h3 {
        font-size: 20px;
    }

    .hrb-modal-body {
        padding: 25px;
    }

    .hrb-modal-close {
        width: 35px;
        height: 35px;
        font-size: 18px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle room booking button
    $('.hrb-book-room').on('click', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');

        // Show booking form in modal
        showBookingModal(roomId);
    });

    // Handle view details button
    $('.hrb-view-room').on('click', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');

        // Show room details in modal
        showRoomDetailsModal(roomId);
    });

    function showBookingModal(roomId) {
        // Create modal overlay
        const modalHtml = `
            <div class="hrb-modal-overlay" id="hrb-booking-modal">
                <div class="hrb-modal-content">
                    <div class="hrb-modal-header">
                        <h3><?php _e('Book This Room', 'hourly-room-booking'); ?></h3>
                        <button class="hrb-modal-close">&times;</button>
                    </div>
                    <div class="hrb-modal-body">
                        <div class="hrb-loading"><?php _e('Loading booking form...', 'hourly-room-booking'); ?></div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);

        // Load booking form via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'hrb_get_booking_form',
                room_id: roomId,
                nonce: '<?php echo wp_create_nonce('hrb_booking_form_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#hrb-booking-modal .hrb-modal-body').html(response.data.html);
                } else {
                    $('#hrb-booking-modal .hrb-modal-body').html('<p class="hrb-error">' + response.data + '</p>');
                }
            },
            error: function() {
                $('#hrb-booking-modal .hrb-modal-body').html('<p class="hrb-error"><?php _e('Failed to load booking form', 'hourly-room-booking'); ?></p>');
            }
        });
    }

    function showRoomDetailsModal(roomId) {
        // Create modal overlay
        const modalHtml = `
            <div class="hrb-modal-overlay" id="hrb-details-modal">
                <div class="hrb-modal-content">
                    <div class="hrb-modal-header">
                        <h3><?php _e('Room Details', 'hourly-room-booking'); ?></h3>
                        <button class="hrb-modal-close">&times;</button>
                    </div>
                    <div class="hrb-modal-body">
                        <div class="hrb-loading"><?php _e('Loading room details...', 'hourly-room-booking'); ?></div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHtml);

        // Load room details via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'hrb_get_room_details_modal',
                room_id: roomId,
                nonce: '<?php echo wp_create_nonce('hrb_room_details_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#hrb-details-modal .hrb-modal-body').html(response.data.html);
                } else {
                    $('#hrb-details-modal .hrb-modal-body').html('<p class="hrb-error">' + response.data + '</p>');
                }
            },
            error: function() {
                $('#hrb-details-modal .hrb-modal-body').html('<p class="hrb-error"><?php _e('Failed to load room details', 'hourly-room-booking'); ?></p>');
            }
        });
    }

    // Handle "Book This Room" button in details modal
    $(document).on('click', '#hrb-details-modal .hrb-book-this-room', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');

        // Close details modal
        $('#hrb-details-modal').remove();

        // Open booking modal
        showBookingModal(roomId);
    });

    // Handle modal close
    $(document).on('click', '.hrb-modal-close, .hrb-modal-overlay', function(e) {
        if (e.target === this) {
            $(this).closest('.hrb-modal-overlay').remove();
        }
    });

    // Close modal on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.hrb-modal-overlay').remove();
        }
    });
});
</script>