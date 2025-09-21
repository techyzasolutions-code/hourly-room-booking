<?php
/**
 * Room Search Form Template
 * Displays search and filter form with room results
 */

if (!defined('ABSPATH')) {
    exit;
}

$show_filters = isset($atts['show_filters']) ? $atts['show_filters'] === 'true' : true;
$rooms_per_page = isset($atts['rooms_per_page']) ? intval($atts['rooms_per_page']) : 6;

// Get settings for currency
$settings = HRB_Settings::getInstance();
$currency_symbol = $settings->get('hrb_currency_symbol', '�');

// Get room manager
$room_manager = HRB_Room_Manager::getInstance();
$all_rooms = $room_manager->get_all_rooms();

// Filter active rooms only
$rooms = array_filter($all_rooms, function($room) {
    return $room->is_active;
});
?>

<div class="hrb-search-container">
    <?php if ($show_filters): ?>
    <div class="hrb-search-form">
        <form class="hrb-filters-form" id="hrb-room-search-form">
            <div class="hrb-search-row">
                <div class="hrb-search-col">
                    <label for="hrb-search-date"><?php _e('Date', 'hourly-room-booking'); ?></label>
                    <input type="date"
                           id="hrb-search-date"
                           name="date"
                           min="<?php echo date('Y-m-d'); ?>"
                           max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                </div>

                <div class="hrb-search-col">
                    <label for="hrb-search-time"><?php _e('Time', 'hourly-room-booking'); ?></label>
                    <select id="hrb-search-time" name="time">
                        <option value=""><?php _e('Any time', 'hourly-room-booking'); ?></option>
                        <option value="08:00">08:00</option>
                        <option value="09:00">09:00</option>
                        <option value="10:00">10:00</option>
                        <option value="11:00">11:00</option>
                        <option value="12:00">12:00</option>
                        <option value="13:00">13:00</option>
                        <option value="14:00">14:00</option>
                        <option value="15:00">15:00</option>
                        <option value="16:00">16:00</option>
                        <option value="17:00">17:00</option>
                        <option value="18:00">18:00</option>
                        <option value="19:00">19:00</option>
                        <option value="20:00">20:00</option>
                    </select>
                </div>

                <div class="hrb-search-col">
                    <label for="hrb-search-duration"><?php _e('Duration', 'hourly-room-booking'); ?></label>
                    <select id="hrb-search-duration" name="duration">
                        <option value=""><?php _e('Any duration', 'hourly-room-booking'); ?></option>
                        <option value="1">1 <?php _e('hour', 'hourly-room-booking'); ?></option>
                        <option value="2">2 <?php _e('hours', 'hourly-room-booking'); ?></option>
                        <option value="3">3 <?php _e('hours', 'hourly-room-booking'); ?></option>
                        <option value="4">4 <?php _e('hours', 'hourly-room-booking'); ?></option>
                        <option value="5">5 <?php _e('hours', 'hourly-room-booking'); ?></option>
                        <option value="6">6 <?php _e('hours', 'hourly-room-booking'); ?></option>
                        <option value="8">8+ <?php _e('hours', 'hourly-room-booking'); ?></option>
                    </select>
                </div>

                <div class="hrb-search-col">
                    <button type="submit" class="hrb-btn hrb-btn-primary">
                        <?php _e('Search Rooms', 'hourly-room-booking'); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="hrb-search-results">
        <div class="hrb-results-header">
            <h3 class="hrb-results-title">
                <?php printf(__('Available Rooms (%d)', 'hourly-room-booking'), count($rooms)); ?>
            </h3>
            <div class="hrb-results-controls">
                <select id="hrb-sort-rooms">
                    <option value="name"><?php _e('Sort by Name', 'hourly-room-booking'); ?></option>
                    <option value="price"><?php _e('Sort by Price', 'hourly-room-booking'); ?></option>
                </select>
            </div>
        </div>

        <div class="hrb-rooms-container" id="hrb-rooms-container">
            <?php if (empty($rooms)): ?>
                <div class="hrb-no-results">
                    <p><?php _e('No rooms available matching your criteria.', 'hourly-room-booking'); ?></p>
                </div>
            <?php else: ?>
                <div class="hrb-rooms-grid">
                    <?php
                    $displayed_rooms = array_slice($rooms, 0, $rooms_per_page);
                    foreach ($displayed_rooms as $room):
                    ?>
                        <div class="hrb-room-card" data-room-id="<?php echo $room->id; ?>">
                            <div class="hrb-room-image">
                                <?php
                                $images = $room_manager->get_room_images($room->id);
                                if (!empty($images)):
                                ?>
                                    <img src="<?php echo esc_url($images[0]); ?>" alt="<?php echo esc_attr($room->name); ?>">
                                <?php else: ?>
                                    <div class="hrb-room-placeholder">
                                        <i class="hrb-icon-room"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="hrb-room-price">
                                    <span class="hrb-price">
                                        <?php echo $currency_symbol; ?><?php echo number_format($room->hourly_price, 2); ?>
                                    </span>
                                    <span class="hrb-price-label"><?php _e('per hour', 'hourly-room-booking'); ?></span>
                                </div>
                            </div>

                            <div class="hrb-room-content">
                                <h3 class="hrb-room-title"><?php echo esc_html($room->name); ?></h3>

                                <?php if (!empty($room->description)): ?>
                                    <p class="hrb-room-description">
                                        <?php echo esc_html(wp_trim_words($room->description, 20)); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="hrb-room-details">
                                    <div class="hrb-room-detail">
                                        <i class="hrb-icon-people"></i>
                                        <span><?php printf(__('Up to %d people', 'hourly-room-booking'), $room->capacity); ?></span>
                                    </div>

                                    <?php
                                    $amenities = $room_manager->get_room_amenities($room->id);
                                    if (!empty($amenities)):
                                    ?>
                                        <div class="hrb-room-detail">
                                            <i class="hrb-icon-amenities"></i>
                                            <span>
                                                <?php echo implode(', ', array_slice($amenities, 0, 3)); ?>
                                                <?php if (count($amenities) > 3): ?>
                                                    <span class="hrb-more-amenities">+<?php echo count($amenities) - 3; ?> <?php _e('more', 'hourly-room-booking'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
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

                <?php if (count($rooms) > $rooms_per_page): ?>
                    <div class="hrb-pagination">
                        <button class="hrb-btn hrb-btn-secondary" id="hrb-load-more"
                                data-page="1"
                                data-total="<?php echo count($rooms); ?>"
                                data-per-page="<?php echo $rooms_per_page; ?>">
                            <?php _e('Load More Rooms', 'hourly-room-booking'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.hrb-search-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.hrb-search-form {
    background: #f8f9fa;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.hrb-search-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: end;
}

.hrb-search-col label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.hrb-search-col input,
.hrb-search-col select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.hrb-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.hrb-results-title {
    margin: 0;
    color: #333;
}

.hrb-results-controls select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.hrb-rooms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
    margin-bottom: 30px;
}

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
    font-size: 16px;
}

.hrb-room-detail:last-child {
    margin-bottom: 0;
}

.hrb-more-amenities {
    color: var(--hrb-text-light);
    font-size: 12px;
}

.hrb-room-actions {
    display: flex;
    gap: 10px;
}

.hrb-btn {
        flex: 1;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
    font-size: 14px;
}

.hrb-btn-primary {
    background: var(--hrb-primary);
    color: var(--hrb-background);
}

.hrb-btn-primary:hover {
    background: var(--hrb-secondary);
    transform: translateY(-1px);
}

.hrb-btn-secondary {
    background: var(--hrb-background-light);
    color: var(--hrb-text);
    border: 1px solid var(--hrb-border);
}

.hrb-btn-secondary:hover {
    background: var(--hrb-border);
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
    background: rgba(0, 0, 0, 0.5);
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
    width: 30px;
    height: 30px;
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
    padding: 40px 20px;
    color: var(--hrb-text-light);
}

.hrb-error {
    background: #fef2f2;
    color: #dc2626;
    padding: 12px 16px;
    border: 1px solid #fecaca;
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

.hrb-room-content {
    padding: 20px;
}

.hrb-room-title {
    margin: 0 0 10px 0;
    font-size: 18px;
    font-weight: 600;
}

.hrb-room-description {
    margin: 0 0 15px 0;
    color: #666;
    line-height: 1.4;
}

.hrb-room-meta {
    margin-bottom: 15px;
    color: #555;
    font-size: 14px;
}

.hrb-icon-people:before,
.hrb-icon-room:before {
    margin-right: 5px;
}

.hrb-pagination {
    text-align: center;
    margin-top: 30px;
}

.hrb-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.hrb-btn-primary {
    background: #0073aa;
    color: white;
}

.hrb-btn-primary:hover {
    background: #005a87;
}

.hrb-btn-secondary {
    background: #f8f9fa;
    color: #0073aa;
    border: 1px solid #0073aa;
}

.hrb-btn-secondary:hover {
    background: #0073aa;
    color: white;
}

.hrb-no-results {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

/* Icons */
.hrb-icon-room:before { content: "🏢"; }
.hrb-icon-people:before { content: "👥"; }
.hrb-icon-amenities:before { content: "⭐"; }

@media (max-width: 768px) {
    .hrb-search-row {
        grid-template-columns: 1fr;
    }

    .hrb-results-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }

    .hrb-rooms-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Translation strings
    const hrbTranslations = {
        perHour: '<?php _e('per hour', 'hourly-room-booking'); ?>',
        bookNow: '<?php _e('Book Now', 'hourly-room-booking'); ?>',
        viewDetails: '<?php _e('View Details', 'hourly-room-booking'); ?>',
        upToPeople: '<?php _e('Up to %d people', 'hourly-room-booking'); ?>',
        more: '<?php _e('more', 'hourly-room-booking'); ?>'
    };
    // Handle search form submission
    $('#hrb-room-search-form').on('submit', function(e) {
        e.preventDefault();
        filterRooms();
    });

    // Handle sort change
    $('#hrb-sort-rooms').on('change', function() {
        sortRooms($(this).val());
    });

    // Handle book room button (for search results and initial load)
    $(document).on('click', '.hrb-book-room', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');
        console.log('Book room clicked:', roomId);

        // Show booking form in modal (same as room-list.php)
        showBookingModal(roomId);
    });

    // Handle view room details button
    $(document).on('click', '.hrb-view-room', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');
        console.log('View room clicked:', roomId);

        // Show room details in modal (same as room-list.php)
        showRoomDetailsModal(roomId);
    });

    // Handle legacy book now button (for backward compatibility)
    $(document).on('click', '.hrb-book-now', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');
        showBookingModal(roomId);
    });

    // Handle "Book This Room" button inside room details modal
    $(document).on('click', '.hrb-modal-body .hrb-btn-primary', function(e) {
        e.preventDefault();
        const roomId = $(this).data('room-id');
        if (roomId) {
            console.log('Book This Room clicked from modal:', roomId);
            // Close the room details modal first
            $('.hrb-modal-overlay').remove();
            // Open booking modal
            showBookingModal(roomId);
        }
    });

    // Handle any button with "book" in the text or class inside modals
    $(document).on('click', '.hrb-modal-body button[data-room-id], .hrb-modal-body a[data-room-id]', function(e) {
        const buttonText = $(this).text().toLowerCase();
        if (buttonText.includes('book')) {
            e.preventDefault();
            const roomId = $(this).data('room-id');
            if (roomId) {
                console.log('Book button clicked from modal:', roomId);
                // Close any existing modal first
                $('.hrb-modal-overlay').remove();
                // Open booking modal
                showBookingModal(roomId);
            }
        }
    });

    // Handle load more
    $('#hrb-load-more').on('click', function() {
        loadMoreRooms();
    });

    function filterRooms() {
        const formData = {
            date: $('#hrb-search-date').val(),
            time: $('#hrb-search-time').val(),
            duration: $('#hrb-search-duration').val()
        };

        // Show loading state
        $('.hrb-search-results').append('<div class="hrb-loading"><?php _e('Searching rooms...', 'hourly-room-booking'); ?></div>');

        // Calculate start and end times from time and duration
        let startTime = formData.time || '09:00';
        let endTime = startTime;

        // Default to 1 hour if no duration specified
        const duration = formData.duration || '1';
        const startHour = parseInt(startTime.split(':')[0]);
        const endHour = startHour + parseInt(duration);
        endTime = endHour.toString().padStart(2, '0') + ':00';

        // Make AJAX call to filter rooms based on availability
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'hrb_search_rooms',
                date: formData.date,
                start_time: startTime,
                end_time: endTime,
                min_capacity: 1,
                max_price: 999999,
                nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>'
            },
            success: function(response) {
                $('.hrb-loading').remove();
                if (response.success && Array.isArray(response.data)) {
                    // Generate HTML from room data
                    let roomsHtml = '';
                    if (response.data.length > 0) {
                        roomsHtml = '<div class="hrb-rooms-grid hrb-columns-2">';
                        response.data.forEach(function(room) {
                            roomsHtml += generateRoomCardHtml(room);
                        });
                        roomsHtml += '</div>';
                    } else {
                        roomsHtml = '<div class="hrb-no-rooms"><p><?php _e('No rooms available for the selected criteria.', 'hourly-room-booking'); ?></p></div>';
                    }

                    $('#hrb-rooms-container').html(roomsHtml);
                    $('.hrb-results-title').text('Available Rooms (' + response.data.length + ')');
                } else {
                    console.error('Search failed:', response.data);
                    // Fallback to showing all rooms
                    showAllRooms();
                }
            },
            error: function() {
                $('.hrb-loading').remove();
                console.error('Search request failed');
                // Fallback to showing all rooms
                showAllRooms();
            }
        });

        console.log('Searching rooms with:', formData);
    }

    function showAllRooms() {
        const allRooms = $('.hrb-room-card');
        allRooms.show();
        $('.hrb-results-title').text('Available Rooms (' + allRooms.length + ')');
    }

    function generateRoomCardHtml(room) {

        const imageHtml = room.images && room.images.length > 0
            ? '<img src="' + room.images[0] + '" alt="' + room.name + '">'
            : '<div class="hrb-room-placeholder"><i class="hrb-icon-room"></i></div>';

        // Generate amenities section if available
        let amenitiesHtml = '';
        if (room.amenities && Array.isArray(room.amenities) && room.amenities.length > 0) {
            const displayAmenities = room.amenities.slice(0, 3);
            let amenitiesText = displayAmenities.join(', ');
            if (room.amenities.length > 3) {
                amenitiesText += ' <span class="hrb-more-amenities">+' + (room.amenities.length - 3) + ' ' + hrbTranslations.more + '</span>';
            }
            amenitiesHtml = `
                <div class="hrb-room-detail">
                    <i class="hrb-icon-amenities"></i>
                    <span>${amenitiesText}</span>
                </div>
            `;
        }

        // Description with word limit like room-list
        const description = room.description ? room.description.split(' ').slice(0, 20).join(' ') : '';
        const descriptionHtml = description ? '<p class="hrb-room-description">' + description + '</p>' : '';

        return `
            <div class="hrb-room-card">
                <div class="hrb-room-image">
                    ${imageHtml}
                    <div class="hrb-room-price">
                        <span class="hrb-price">${room.formatted_price}</span>
                        <span class="hrb-price-label">${hrbTranslations.perHour}</span>
                    </div>
                </div>
                <div class="hrb-room-content">
                    <h3 class="hrb-room-title">${room.name}</h3>
                    ${descriptionHtml}
                    <div class="hrb-room-details">
                        <div class="hrb-room-detail">
                            <i class="hrb-icon-people"></i>
                            <span>Up to ${room.capacity} people</span>
                        </div>
                        ${amenitiesHtml}
                    </div>
                    <div class="hrb-room-actions">
                        <a href="#" class="hrb-btn hrb-btn-primary hrb-view-room" data-room-id="${room.id}">
                            ${hrbTranslations.viewDetails}
                        </a>
                        <a href="#" class="hrb-btn hrb-btn-secondary hrb-book-room" data-room-id="${room.id}">
                            ${hrbTranslations.bookNow}
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    function sortRooms(sortBy) {
        const container = $('#hrb-rooms-container .hrb-rooms-grid');
        if (container.length === 0) {
            // Try alternative container selector
            const altContainer = $('#hrb-rooms-container');
            if (altContainer.find('.hrb-room-card').length > 0) {
                sortRoomsInContainer(altContainer, sortBy);
                return;
            }
            console.log('Sort container not found');
            return;
        }

        sortRoomsInContainer(container, sortBy);
    }

    function sortRoomsInContainer(container, sortBy) {
        const rooms = container.find('.hrb-room-card').detach();
        if (rooms.length === 0) {
            console.log('No room cards found to sort');
            return;
        }

        rooms.sort(function(a, b) {
            let aVal, bVal;

            switch(sortBy) {
                case 'price':
                    // Check for both possible price structures
                    let priceA = $(a).find('.hrb-room-price .hrb-price').text();
                    if (!priceA) priceA = $(a).find('.hrb-room-price').text();
                    aVal = parseFloat(priceA.replace(/[^\d.]/g, '')) || 0;

                    let priceB = $(b).find('.hrb-room-price .hrb-price').text();
                    if (!priceB) priceB = $(b).find('.hrb-room-price').text();
                    bVal = parseFloat(priceB.replace(/[^\d.]/g, '')) || 0;
                    break;
                case 'name':
                    // Look for name in .hrb-room-title element
                    aVal = $(a).find('.hrb-room-title').text().toLowerCase().trim() || '';
                    bVal = $(b).find('.hrb-room-title').text().toLowerCase().trim() || '';
                    break;
                default:
                    return 0;
            }

            if (sortBy === 'price') {
                return aVal - bVal; // Numeric sort for price
            } else {
                // String sort for name
                if (aVal < bVal) return -1;
                if (aVal > bVal) return 1;
                return 0;
            }
        });

        // Append back to the correct container
        if (container.hasClass('hrb-rooms-grid')) {
            container.append(rooms);
        } else {
            // If container doesn't have the grid class, it might need to wrap in grid
            container.html('<div class="hrb-rooms-grid">' + rooms.map(function() { return this.outerHTML; }).get().join('') + '</div>');
        }

        console.log('Sorted', rooms.length, 'rooms by:', sortBy);
    }

    function loadMoreRooms() {
        const loadMoreBtn = $('#hrb-load-more');
        const currentPage = parseInt(loadMoreBtn.data('page')) || 1;
        const totalRooms = parseInt(loadMoreBtn.data('total')) || 0;
        const perPage = parseInt(loadMoreBtn.data('per-page')) || 6;
        const nextPage = currentPage + 1;

        console.log('Loading more rooms...', {
            currentPage,
            nextPage,
            totalRooms,
            perPage
        });

        // Calculate if there are more rooms to load
        const loadedRooms = currentPage * perPage;
        if (loadedRooms >= totalRooms) {
            loadMoreBtn.text('<?php _e('No More Rooms', 'hourly-room-booking'); ?>').prop('disabled', true);
            return;
        }

        // Show loading state
        const originalText = loadMoreBtn.text();
        loadMoreBtn.text('<?php _e('Loading...', 'hourly-room-booking'); ?>').prop('disabled', true);

        // Load more rooms via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'hrb_load_more_rooms',
                page: nextPage,
                per_page: perPage,
                nonce: '<?php echo wp_create_nonce('hrb_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.rooms_html) {
                    // Append new rooms to the grid
                    $('.hrb-rooms-grid').append(response.data.rooms_html);

                    // Update button state
                    loadMoreBtn.data('page', nextPage);
                    loadMoreBtn.text(originalText).prop('disabled', false);

                    // Check if we've loaded all rooms
                    const newLoadedCount = nextPage * perPage;
                    if (newLoadedCount >= totalRooms) {
                        loadMoreBtn.text('<?php _e('No More Rooms', 'hourly-room-booking'); ?>').prop('disabled', true);
                    }

                    console.log('Loaded more rooms successfully');
                } else {
                    console.error('Failed to load more rooms:', response.data);
                    loadMoreBtn.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                console.error('AJAX request failed');
                loadMoreBtn.text(originalText).prop('disabled', false);
            }
        });
    }

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