<?php
/**
 * Rooms Management View
 * Displays the admin interface for managing rooms
 */

if (!defined('ABSPATH')) {
    exit;
}

$room_manager = HRB_Room_Manager::getInstance();
$rooms = $room_manager->get_all_rooms('all'); // Get all rooms including inactive

// Handle filtering
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
if ($filter_status !== 'all') {
    $active_only = $filter_status;
    $rooms = $room_manager->get_all_rooms($active_only);
}
?>

<div class="hrb-admin-page">
    <div class="hrb-page-header">
        <div class="hrb-page-title">
            <h1><?php _e('Manage Rooms', 'hourly-room-booking'); ?></h1>
            <p class="description"><?php _e('Add, edit, and manage your bookable rooms and meeting spaces.', 'hourly-room-booking'); ?></p>
        </div>
        <div class="hrb-page-actions">
            <button type="button" class="button button-primary" onclick="showAddRoomModal()">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Add New Room', 'hourly-room-booking'); ?>
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="hrb-stats-grid" style="margin-bottom: 20px;">
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php echo count(array_filter($rooms, function($r) { return $r->is_active; })); ?></div>
            <div class="hrb-stat-label"><?php _e('Active Rooms', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php echo count(array_filter($rooms, function($r) { return !$r->is_active; })); ?></div>
            <div class="hrb-stat-label"><?php _e('Inactive Rooms', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php echo count($rooms); ?></div>
            <div class="hrb-stat-label"><?php _e('Total Rooms', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php 
            $room_manager = HRB_Room_Manager::getInstance();
            $total_min_price = 0;
            foreach ($rooms as $room) {
                $price_range = $room_manager->get_room_price_range($room);
                $total_min_price += $price_range['min'];
            }
            echo hrb_format_amount($total_min_price); 
            ?></div>
            <div class="hrb-stat-label"><?php _e('Starting from', 'hourly-room-booking'); ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="hrb-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="hrb-rooms">

            <select name="status" onchange="this.form.submit()">
                <option value="all" <?php selected($filter_status, 'all'); ?>><?php _e('All Rooms', 'hourly-room-booking'); ?></option>
                <option value="active" <?php selected($filter_status, 'active'); ?>><?php _e('Active Only', 'hourly-room-booking'); ?></option>
                <option value="inactive" <?php selected($filter_status, 'inactive'); ?>><?php _e('Inactive Only', 'hourly-room-booking'); ?></option>
            </select>

            <button type="submit" class="button"><?php _e('Filter', 'hourly-room-booking'); ?></button>
        </form>
    </div>

    <!-- Rooms Table -->
    <div class="hrb-table-container">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col" class="column-image"><?php _e('Image', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-name"><?php _e('Room Name', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-id"><?php _e('Room ID', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-capacity"><?php _e('Capacity', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-price"><?php _e('Hourly Price', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-amenities"><?php _e('Amenities', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-color"><?php _e('Color', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-status"><?php _e('Status', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-actions"><?php _e('Actions', 'hourly-room-booking'); ?></th>
                </tr>
            </thead>
            <tbody id="sortable-rooms">
                <?php if (empty($rooms)): ?>
                    <tr>
                        <td colspan="9" class="hrb-no-data">
                            <div class="hrb-empty-state">
                                <span class="dashicons dashicons-admin-multisite"></span>
                                <h3><?php _e('No rooms found', 'hourly-room-booking'); ?></h3>
                                <p><?php _e('Start by adding your first room.', 'hourly-room-booking'); ?></p>
                                <button type="button" class="button button-primary" onclick="showAddRoomModal()">
                                    <?php _e('Add First Room', 'hourly-room-booking'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                        <tr data-room-id="<?php echo esc_attr($room->id); ?>" class="<?php echo esc_attr($room->is_active ? 'active' : 'inactive'); ?>">
                            <td class="column-image">
                                <?php
                                $images = !empty($room->images) ? json_decode($room->images, true) : [];
                                if (empty($images) && !empty($room->images) && strpos($room->images, ',') !== false) {
                                    // Handle legacy comma-separated format
                                    $images = array_filter(array_map('trim', explode(',', $room->images)));
                                }
                                if (!empty($images) && is_array($images)): ?>
                                    <img src="<?php echo esc_url($images[0]); ?>" 
                                         alt="<?php echo esc_attr($room->name); ?>"
                                         class="hrb-room-image"
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    <?php if (count($images) > 1): ?>
                                        <div class="image-count">+<?php echo count($images) - 1; ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="hrb-no-image">
                                        <span class="dashicons dashicons-camera"></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="column-name">
                                <strong><?php echo esc_html($room->name); ?></strong>
                                <?php if (!empty($room->description)): ?>
                                    <div class="room-description"><?php echo wp_trim_words(esc_html($room->description), 10); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="column-id">
                                <div class="hrb-room-id-display">
                                    <span class="hrb-room-id-value" onclick="copyRoomIdToClipboard('<?php echo $room->id; ?>')" title="<?php _e('Click to copy', 'hourly-room-booking'); ?>">
                                        <?php echo $room->id; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="column-capacity">
                                <span class="capacity-badge"><?php echo esc_html($room->capacity); ?> <?php _e('people', 'hourly-room-booking'); ?></span>
                            </td>
                            <td class="column-price">
                                <?php 
                                $room_manager = HRB_Room_Manager::getInstance();
                                $price_range = $room_manager->get_room_price_range($room);
                                ?>
                                <strong><?php echo $price_range['formatted']; ?></strong>
                            </td>
                            <td class="column-amenities">
                                <?php
                                $amenities = !empty($room->amenities) ? json_decode($room->amenities, true) : [];
                                if (!empty($amenities)) {
                                    echo '<div class="amenities-list">';
                                    $display_amenities = array_slice($amenities, 0, 3);
                                    foreach ($display_amenities as $amenity) {
                                        echo '<span class="amenity-tag">' . esc_html($amenity) . '</span>';
                                    }
                                    if (count($amenities) > 3) {
                                        echo '<span class="amenity-tag more">+' . (count($amenities) - 3) . ' ' . __('more', 'hourly-room-booking') . '</span>';
                                    }
                                    echo '</div>';
                                } else {
                                    echo '<span class="no-amenities">' . __('No amenities', 'hourly-room-booking') . '</span>';
                                }
                                ?>
                            </td>
                            <td class="column-color">
                                <div class="room-color-display">
                                    <span class="color-preview" style="background-color: <?php echo esc_attr($room->color ?? '#3498db'); ?>; width: 20px; height: 20px; border-radius: 50%; display: inline-block; border: 2px solid #ddd;"></span>
                                    <span class="color-code"><?php echo esc_html($room->color ?? '#3498db'); ?></span>
                                </div>
                            </td>
                            <td class="column-status">
                                <span class="hrb-status hrb-status-<?php echo $room->is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $room->is_active ? __('Active', 'hourly-room-booking') : __('Inactive', 'hourly-room-booking'); ?>
                                </span>
                            </td>
                            <td class="column-actions">
                                <div class="hrb-actions">
                                    <?php if (current_user_can('hrb_manage_rooms')): ?>
                                    <button type="button" class="button button-small" onclick="editRoom(<?php echo esc_js($room->id); ?>)" title="<?php _e('Edit Room', 'hourly-room-booking'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>

                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('hrb_admin_action', '_wpnonce'); ?>
                                        <input type="hidden" name="action" value="toggle_room_status">
                                        <input type="hidden" name="room_id" value="<?php echo esc_attr($room->id); ?>">
                                        <button type="submit" class="button button-small <?php echo esc_attr($room->is_active ? 'hrb-toggle-inactive' : 'hrb-toggle-active'); ?>" title="<?php echo esc_attr($room->is_active ? __('Deactivate Room', 'hourly-room-booking') : __('Activate Room', 'hourly-room-booking')); ?>">
                                            <?php if ($room->is_active): ?>
                                                <span class="dashicons dashicons-hidden"></span>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-visibility"></span>
                                            <?php endif; ?>
                                        </button>
                                    </form>

                                    <button type="button" class="button button-small hrb-delete-btn" onclick="deleteRoom(<?php echo esc_js($room->id); ?>, '<?php echo esc_js($room->name); ?>')" title="<?php _e('Delete Room', 'hourly-room-booking'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Room Modal -->
<div id="room-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h2 id="modal-title"><?php _e('Add New Room', 'hourly-room-booking'); ?></h2>
            <span class="hrb-modal-close" onclick="closeRoomModal()">&times;</span>
        </div>

        <form id="room-form" method="post">
            <?php wp_nonce_field('hrb_admin_action', '_wpnonce'); ?>
            <input type="hidden" name="action" id="form-action" value="create_room">
            <input type="hidden" name="room_id" id="form-room-id" value="">

            <div class="hrb-modal-body">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="room_name"><?php _e('Room Name', 'hourly-room-booking'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" name="room_name" id="room_name" class="regular-text" required>
                            <p class="description"><?php _e('Enter the name of the room or meeting space.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_description"><?php _e('Description', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="room_description" id="room_description" rows="3" class="large-text"></textarea>
                            <p class="description"><?php _e('Optional description of the room and its features.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_capacity"><?php _e('Capacity', 'hourly-room-booking'); ?> *</label>
                        </th>
                        <td>
                            <input type="number" name="room_capacity" id="room_capacity" min="1" class="regular-text" required>
                            <p class="description"><?php _e('Maximum number of people this room can accommodate.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_price_2_hours"><?php printf(__('2 Hours Price (%s)', 'hourly-room-booking'), hrb_get_currency_symbol()); ?></label>
                        </th>
                        <td>
                            <input type="number" name="room_price_2_hours" id="room_price_2_hours" min="0" step="0.01" class="regular-text">
                            <p class="description"><?php _e('Optional 2-hour rate. Leave 0 to use global default.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_price_3_hours"><?php printf(__('3 Hours Price (%s)', 'hourly-room-booking'), hrb_get_currency_symbol()); ?></label>
                        </th>
                        <td>
                            <input type="number" name="room_price_3_hours" id="room_price_3_hours" min="0" step="0.01" class="regular-text">
                            <p class="description"><?php _e('Optional 3-hour rate. Leave 0 to use global default.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_price_4_hours"><?php printf(__('4 Hours Price (%s)', 'hourly-room-booking'), hrb_get_currency_symbol()); ?></label>
                        </th>
                        <td>
                            <input type="number" name="room_price_4_hours" id="room_price_4_hours" min="0" step="0.01" class="regular-text">
                            <p class="description"><?php _e('Optional 4-hour rate. Leave 0 to use global default.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_price_extra_hour"><?php printf(__('Extra Hour Price (%s)', 'hourly-room-booking'), hrb_get_currency_symbol()); ?></label>
                        </th>
                        <td>
                            <input type="number" name="room_price_extra_hour" id="room_price_extra_hour" min="0" step="0.01" class="regular-text">
                            <p class="description"><?php _e('Optional extra hour rate. Leave 0 to use global default.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_amenities"><?php _e('Amenities', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="room_amenities" id="room_amenities" class="regular-text">
                            <p class="description"><?php _e('Comma-separated list of amenities (e.g., WiFi, Projector, Whiteboard).', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_color"><?php _e('Room Color', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <div class="color-picker-container">
                                <input type="color" name="room_color" id="room_color" value="#3498db" class="color-picker">
                                <input type="text" name="room_color_text" id="room_color_text" value="#3498db" class="color-text-input" placeholder="#3498db">
                                <div class="color-preview" id="color-preview" style="width: 30px; height: 30px; border-radius: 4px; display: inline-block; border: 2px solid #ddd; background-color: #3498db;"></div>
                            </div>
                            <p class="description"><?php _e('Choose a color for this room to help identify it in the calendar and booking views.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_external_link"><?php _e('External Link', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="room_external_link" id="room_external_link" class="regular-text" placeholder="https://example.com/room-details">
                            <p class="description"><?php _e('Optional external link for room details. When provided, "View Details" button will redirect to this link instead of showing popup.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_images"><?php _e('Images', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="room_images" id="room_images" class="regular-text" readonly>
                            <button type="button" class="button" onclick="uploadRoomImages()"><?php _e('Select Images', 'hourly-room-booking'); ?></button>
                            <button type="button" class="button button-secondary" onclick="clearRoomImages()"><?php _e('Clear All', 'hourly-room-booking'); ?></button>
                            <p class="description"><?php _e('Select images from media library to showcase this room.', 'hourly-room-booking'); ?></p>
                            <div id="room_images_preview" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 10px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="room_is_active"><?php _e('Status', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="room_is_active" id="room_is_active" checked>
                                <?php _e('Active (available for booking)', 'hourly-room-booking'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="hrb-modal-footer">
                <button type="button" class="button" onclick="closeRoomModal()"><?php _e('Cancel', 'hourly-room-booking'); ?></button>
                <button type="submit" class="button button-primary" id="submit-button"><?php _e('Add Room', 'hourly-room-booking'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirmation form -->
<form id="delete-room-form" method="post" style="display: none;">
    <?php wp_nonce_field('hrb_admin_action', '_wpnonce'); ?>
    <input type="hidden" name="action" value="delete_room">
    <input type="hidden" name="room_id" id="delete-room-id" value="">
    <input type="hidden" name="force_delete" value="1">
</form>

<style>
/* Modern Professional Rooms Management Styling */
.hrb-admin-page {
    background: #f8f9fa;
    min-height: 100vh;
    padding: 24px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.hrb-page-header {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white ;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 32px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 32px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

/* .hrb-page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -30px;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    backdrop-filter: blur(20px);
} */

.hrb-page-title h1 {
    margin: 0 0 8px 0;
    font-size: 2.5rem;
    font-weight: 700;
    letter-spacing: -0.025em;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    color: white;
}

.hrb-page-title .description {
    margin: 0;
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    font-weight: 400;
}

.hrb-page-actions {
    z-index: 2;
}

.hrb-page-actions .button-primary {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    font-weight: 600;
    padding: 12px 24px;
    border-radius: 8px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    text-shadow: none;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.hrb-page-actions .button-primary:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.15);
}

.hrb-page-actions .dashicons {
    margin-right: 8px;
}

/* Modern Stats Grid */
.hrb-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.hrb-stat-card {
    background: white;
    border-radius: 16px;
    /* padding: 32px; */
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.hrb-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3b82f6, #10b981);
}

.hrb-stat-card:nth-child(2)::before {
    background: linear-gradient(90deg, #10b981, #f59e0b);
}

.hrb-stat-card:nth-child(3)::before {
    background: linear-gradient(90deg, #f59e0b, #ef4444);
}

.hrb-stat-card:nth-child(4)::before {
    background: linear-gradient(90deg, #ef4444, #8b5cf6);
}

.hrb-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.12);
}

.hrb-stat-number {
    font-size: 32px;
    font-weight: 800;
    color: #1f2937;
    line-height: 1;
    margin-bottom: 8px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hrb-stat-label {
    font-size: 0.95rem;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Modern Filters */
.hrb-filters {
    background: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.hrb-filters select {
    padding: 8px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    margin-right: 12px;
    font-size: 0.9rem;
    background: white;
    transition: border-color 0.2s ease;
}

.hrb-filters select:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.hrb-filters .button {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 500;
}

/* Modern Table Container */
.hrb-table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.wp-list-table {
    border: none;
}

.wp-list-table thead th {
    background: #f8f9fa;
    color: #374151;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    padding: 20px 16px;
    border-bottom: 2px solid #e5e7eb;
}

.wp-list-table tbody td {
    padding: 20px 16px;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.wp-list-table tbody tr {
    transition: background-color 0.2s ease;
}

.wp-list-table tbody tr:hover {
    background: #f8f9fa;
}

.column-image {
    width: 80px;
}

.column-name {
    width: 25%;
}

.column-capacity {
    width: 12%;
    text-align: center;
}

.column-price {
    width: 15%;
}

.column-amenities {
    width: 20%;
}

.column-color {
    width: 15%;
    text-align: center;
}

.room-color-display {
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
}

.color-picker-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.color-picker {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.color-text-input {
    width: 100px;
    padding: 5px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
}

.color-preview {
    cursor: pointer;
    transition: transform 0.2s ease;
}

.color-preview:hover {
    transform: scale(1.1);
}

.column-status {
    width: 10%;
    text-align: center;
}

.column-actions {
    width: 13%;
}

.room-description {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 4px;
    line-height: 1.4;
}

.capacity-badge {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1d4ed8;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.weekend-price {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 4px;
    font-weight: 500;
}

.amenities-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.amenity-tag {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    color: #1e40af;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
}

.amenity-tag.more {
    background: linear-gradient(135deg, #f9fafb, #f3f4f6);
    color: #6b7280;
}

.no-amenities {
    color: #9ca3af;
    font-style: italic;
    font-size: 0.85rem;
}

.hrb-room-image {
    border-radius: 8px !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease;
    position: relative;
}

.hrb-room-image:hover {
    transform: scale(1.05);
}

.image-count {
    position: absolute;
    top: -5px;
    right: -5px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.hrb-no-image {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}

#room_images_preview img {
    max-width: 80px;
    max-height: 80px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}

#room_images_preview img:hover {
    border-color: #8b5cf6;
    transform: scale(1.05);
}

.hrb-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: inline-block;
}

.hrb-status-active {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
}

.hrb-status-inactive {
    background: linear-gradient(135deg, #fecaca, #fca5a5);
    color: #7f1d1d;
}

/* Enhanced Actions - Matching Bookings Page Style */
.hrb-actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

.hrb-actions .button {
    padding: 8px;
    border-radius: 8px;
    border: none;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hrb-actions .button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.hrb-actions .button-small {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: white;
}

.hrb-actions .hrb-toggle-active {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.hrb-actions .hrb-toggle-inactive {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.hrb-actions .hrb-delete-btn {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

/* Room ID Display Styles */
.hrb-room-id-display {
    display: flex;
    align-items: center;
    justify-content: center;
}

.hrb-room-id-value {
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    color: #374151;
    padding: 6px 12px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid #d1d5db;
    user-select: all;
    min-width: 40px;
    text-align: center;
}

.hrb-room-id-value:hover {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    border-color: #3b82f6;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
}

.column-id {
    width: 80px;
    text-align: center;
}

/* Animation for copy notifications */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.hrb-empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.hrb-empty-state .dashicons {
    font-size: 64px;
    color: #d1d5db;
    margin-bottom: 16px;
}

.hrb-empty-state h3 {
    margin: 0 0 12px 0;
    font-size: 1.5rem;
    color: #374151;
    font-weight: 600;
}

.hrb-empty-state p {
    margin-bottom: 24px;
    font-size: 1rem;
}

.hrb-empty-state .button-primary {
    background: #3b82f6;
    border-color: #3b82f6;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
}

/* Modern Modal */
.hrb-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.hrb-modal-content {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 90%;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hrb-modal-header {
    padding: 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    border-radius: 16px 16px 0 0;
}

.hrb-modal-header h2 {
    margin: 0;
    color: #1f2937;
    font-weight: 600;
    font-size: 1.5rem;
}

.hrb-modal-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    cursor: pointer;
    color: #6b7280;
    background: white;
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
}

.hrb-modal-close:hover {
    background: #f3f4f6;
    color: #374151;
    transform: scale(1.05);
}

.hrb-modal-body {
    padding: 24px;
}

.hrb-modal-body .form-table th {
    padding: 16px 0;
    font-weight: 600;
    color: #374151;
}

.hrb-modal-body .form-table td {
    padding: 16px 0;
}

.hrb-modal-body input,
.hrb-modal-body textarea,
.hrb-modal-body select {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 8px 12px;
    transition: border-color 0.2s ease;
}

.hrb-modal-body input:focus,
.hrb-modal-body textarea:focus,
.hrb-modal-body select:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.hrb-modal-footer {
    padding: 24px;
    border-top: 1px solid #e5e7eb;
    text-align: right;
    background: #f8f9fa;
    border-radius: 0 0 16px 16px;
}

.hrb-modal-footer .button {
    margin-left: 12px;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
}

.hrb-modal-footer .button-primary {
    background: #3b82f6;
    border-color: #3b82f6;
}

.inactive {
    opacity: 0.7;
}

/* Responsive Design */
@media (max-width: 768px) {
    .hrb-admin-page {
        padding: 16px;
        margin: -10px;
    }

    .hrb-page-header {
        flex-direction: column;
        gap: 16px;
        padding: 24px;
    }

    .hrb-page-title h1 {
        font-size: 2rem;
    }

    .hrb-stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }

    .hrb-stat-card {
        padding: 20px;
    }

    .hrb-stat-number {
        font-size: 2rem;
    }

    .hrb-actions {
        flex-direction: column;
    }

    .hrb-modal-content {
        width: 95%;
        margin: 20px;
    }
}

/* Print Styles */
@media print {
    .hrb-admin-page {
        background: white;
        margin: 0;
        padding: 0;
    }

    .hrb-page-header {
        background: white !important;
        color: black !important;
        box-shadow: none;
    }

    .hrb-page-actions,
    .hrb-filters,
    .hrb-actions,
    .column-actions {
        display: none;
    }
}
</style>

<script>
function showAddRoomModal() {
    // Ensure modal elements exist before trying to access them
    const modalTitle = document.getElementById('modal-title');
    const formAction = document.getElementById('form-action');
    const formRoomId = document.getElementById('form-room-id');
    const submitButton = document.getElementById('submit-button');
    const roomForm = document.getElementById('room-form');
    const roomIsActive = document.getElementById('room_is_active');
    const roomModal = document.getElementById('room-modal');
    
    if (!modalTitle || !formAction || !formRoomId || !submitButton || !roomForm || !roomIsActive || !roomModal) {
        console.error('Modal elements not found. Please refresh the page.');
        return;
    }
    
    modalTitle.textContent = '<?php _e('Add New Room', 'hourly-room-booking'); ?>';
    formAction.value = 'create_room';
    formRoomId.value = '';
    submitButton.textContent = '<?php _e('Add Room', 'hourly-room-booking'); ?>';

    // Reset form
    roomForm.reset();
    roomIsActive.checked = true;
    
    // Clear images for new room
    clearRoomImages();

    roomModal.style.display = 'flex';
}

function editRoom(roomId) {
    // Ensure modal elements exist before trying to access them
    const modalTitle = document.getElementById('modal-title');
    const formAction = document.getElementById('form-action');
    const formRoomId = document.getElementById('form-room-id');
    const submitButton = document.getElementById('submit-button');
    
    if (!modalTitle || !formAction || !formRoomId || !submitButton) {
        console.error('Modal elements not found. Please refresh the page.');
        return;
    }
    
    modalTitle.textContent = '<?php _e('Edit Room', 'hourly-room-booking'); ?>';
    formAction.value = 'update_room';
    formRoomId.value = roomId;
    submitButton.textContent = '<?php _e('Update Room', 'hourly-room-booking'); ?>';

    // Fetch room data via AJAX
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'hrb_get_room_details',
            room_id: roomId,
            nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>'
        },
        success: function(response) {
            if (response.success) {
                const room = response.data;
                /* removed debug log */

                // Populate all form fields with safety checks
                const roomName = document.getElementById('room_name');
                const roomDescription = document.getElementById('room_description');
                const roomCapacity = document.getElementById('room_capacity');
                const roomPrice2Hours = document.getElementById('room_price_2_hours');
                const roomPrice3Hours = document.getElementById('room_price_3_hours');
                const roomPrice4Hours = document.getElementById('room_price_4_hours');
                const roomPriceExtraHour = document.getElementById('room_price_extra_hour');
                const roomAmenities = document.getElementById('room_amenities');
                const roomColor = document.getElementById('room_color');
                const roomColorText = document.getElementById('room_color_text');
                const colorPreview = document.getElementById('color-preview');
                const roomExternalLink = document.getElementById('room_external_link');
                const roomIsActive = document.getElementById('room_is_active');
                const roomModal = document.getElementById('room-modal');

                if (roomName) roomName.value = room.name || '';
                if (roomDescription) roomDescription.value = room.description || '';
                if (roomCapacity) roomCapacity.value = room.capacity || '';
                if (roomPrice2Hours) roomPrice2Hours.value = room.price_2_hours || '';
                if (roomPrice3Hours) roomPrice3Hours.value = room.price_3_hours || '';
                if (roomPrice4Hours) roomPrice4Hours.value = room.price_4_hours || '';
                if (roomPriceExtraHour) roomPriceExtraHour.value = room.price_extra_hour || '';
                if (roomAmenities) roomAmenities.value = room.amenities || '';
                if (roomColor) roomColor.value = room.color || '#3498db';
                if (roomColorText) roomColorText.value = room.color || '#3498db';
                if (colorPreview) colorPreview.style.backgroundColor = room.color || '#3498db';
                if (roomExternalLink) roomExternalLink.value = room.external_link || '';
                if (roomIsActive) roomIsActive.checked = parseInt(room.is_active) === 1;
                
                // Load existing images
                loadRoomImages(room.images);

                // Show the modal
                if (roomModal) roomModal.style.display = 'flex';
            } else {
                alert('Failed to load room data');
            }
        },
        error: function() {
            alert('Error loading room data');
        }
    });
}

function closeRoomModal() {
    // Hide the modal
    document.getElementById('room-modal').style.display = 'none';
    
    // Reset the form to clear any data
    document.getElementById('room-form').reset();
    
    // Clear any room images
    clearRoomImages();
    
    // Reset form action and room ID
    document.getElementById('form-action').value = 'create_room';
    document.getElementById('form-room-id').value = '';
    
    // Reset modal title and button text
    document.getElementById('modal-title').textContent = '<?php _e('Add New Room', 'hourly-room-booking'); ?>';
    document.getElementById('submit-button').textContent = '<?php _e('Add Room', 'hourly-room-booking'); ?>';
    
    // Reset room active checkbox
    document.getElementById('room_is_active').checked = true;
}

function deleteRoom(roomId, roomName) {
    // Use custom alert dialog with danger type
    window.hrbShowAlertDialog(
        <?php echo json_encode(__('Are you sure you want to permanently delete this room?', 'hourly-room-booking')); ?>,
        {
            warningMessage: <?php echo json_encode(__('This action cannot be undone!', 'hourly-room-booking')); ?>,
            title: <?php echo json_encode(__('Delete Room', 'hourly-room-booking')); ?>,
            details: [
                {
                    label: <?php echo json_encode(__('Room:', 'hourly-room-booking')); ?>,
                    value: roomName,
                    class: 'original'
                }
            ],
            confirmText: <?php echo json_encode(__('Delete', 'hourly-room-booking')); ?>,
            cancelText: <?php echo json_encode(__('Cancel', 'hourly-room-booking')); ?>,
            type: 'danger'
        },
        function() {
            // User confirmed - submit the form
            const roomIdField = document.getElementById('delete-room-id');
            const form = document.getElementById('delete-room-form');
            
            if (roomIdField && form) {
                roomIdField.value = roomId;
                form.submit();
            }
        }
    );
}

// Room image upload functions
function uploadRoomImages() {
    
    if (typeof wp !== 'undefined' && wp.media) {
        const mediaUploader = wp.media({
            title: 'Select Room Images',
            button: {
                text: 'Use selected images'
            },
            multiple: true,
            library: {
                type: 'image'
            }
        });

        mediaUploader.on('select', function() {
            const attachments = mediaUploader.state().get('selection').toJSON();
            const imageUrls = attachments.map(attachment => attachment.url);
            
            document.getElementById('room_images').value = imageUrls.join(',');
            updateRoomImagesPreview(imageUrls);
        });

        mediaUploader.open();
    } else {
        alert('Media library not available. Please refresh the page and try again.');
    }
}

function clearRoomImages() {
    document.getElementById('room_images').value = '';
    document.getElementById('room_images_preview').innerHTML = '';
}

function updateRoomImagesPreview(imageUrls) {
    const preview = document.getElementById('room_images_preview');
    preview.innerHTML = '';
    
    imageUrls.forEach(url => {
        const img = document.createElement('img');
        img.src = url;
        img.style.width = '80px';
        img.style.height = '80px';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '8px';
        img.style.border = '2px solid #e5e7eb';
        img.style.cursor = 'pointer';
        img.title = '<?php _e('Click to remove', 'hourly-room-booking'); ?>';
        
        img.addEventListener('click', function() {
            removeRoomImage(url);
        });
        
        preview.appendChild(img);
    });
}

function removeRoomImage(urlToRemove) {
    const currentImages = document.getElementById('room_images').value.split(',').filter(url => url.trim() !== '');
    const updatedImages = currentImages.filter(url => url !== urlToRemove);
    
    document.getElementById('room_images').value = updatedImages.join(',');
    updateRoomImagesPreview(updatedImages);
}

// Load existing images when editing
function loadRoomImages(imagesJson) {
    if (imagesJson) {
        try {
            const images = JSON.parse(imagesJson);
            if (Array.isArray(images) && images.length > 0) {
                document.getElementById('room_images').value = images.join(',');
                updateRoomImagesPreview(images);
            }
        } catch (e) {
            // Handle legacy comma-separated format
            if (imagesJson.includes(',')) {
                const images = imagesJson.split(',').map(url => url.trim()).filter(url => url);
                document.getElementById('room_images').value = images.join(',');
                updateRoomImagesPreview(images);
            }
        }
    }
}

// Close modal when clicking outside
document.getElementById('room-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRoomModal();
    }
});

// Function to copy Room ID to clipboard
function copyRoomIdToClipboard(roomId) {
    if (navigator.clipboard && window.isSecureContext) {
        // Use modern clipboard API
        navigator.clipboard.writeText(roomId).then(function() {
            showCopySuccess(roomId);
        }).catch(function(err) {
            fallbackCopyTextToClipboard(roomId);
        });
    } else {
        // Fallback for older browsers
        fallbackCopyTextToClipboard(roomId);
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopySuccess(text);
        } else {
            showCopyError();
        }
    } catch (err) {
        showCopyError();
    }
    
    document.body.removeChild(textArea);
}

function showCopySuccess(roomId) {
    // Create temporary success message
    const successMsg = document.createElement('div');
    successMsg.innerHTML = '<?php _e('Room ID', 'hourly-room-booking'); ?> ' + roomId + ' <?php _e('copied to clipboard!', 'hourly-room-booking'); ?>';
    successMsg.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        z-index: 10001;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        animation: slideInRight 0.3s ease-out;
        max-width: 300px;
    `;
    
    document.body.appendChild(successMsg);
    
    setTimeout(function() {
        successMsg.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(function() {
            if (document.body.contains(successMsg)) {
                document.body.removeChild(successMsg);
            }
        }, 300);
    }, 3000);
}

function showCopyError() {
    // Create temporary error message
    const errorMsg = document.createElement('div');
    errorMsg.innerHTML = '<?php _e('Failed to copy. Please select and copy manually.', 'hourly-room-booking'); ?>';
    errorMsg.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #ef4444;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        z-index: 10001;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        animation: slideInRight 0.3s ease-out;
        max-width: 300px;
    `;
    
    document.body.appendChild(errorMsg);
    
    setTimeout(function() {
        errorMsg.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(function() {
            if (document.body.contains(errorMsg)) {
                document.body.removeChild(errorMsg);
            }
        }, 300);
    }, 4000);
}

// Color picker functionality
document.addEventListener('DOMContentLoaded', function() {
    const colorPicker = document.getElementById('room_color');
    const colorText = document.getElementById('room_color_text');
    const colorPreview = document.getElementById('color-preview');
    
    if (colorPicker && colorText && colorPreview) {
        // Update text input when color picker changes
        colorPicker.addEventListener('input', function() {
            colorText.value = this.value;
            colorPreview.style.backgroundColor = this.value;
        });
        
        // Update color picker when text input changes
        colorText.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-Fa-f]{6}$/)) {
                colorPicker.value = this.value;
                colorPreview.style.backgroundColor = this.value;
            }
        });
        
        // Update preview when text input loses focus
        colorText.addEventListener('blur', function() {
            if (this.value.match(/^#[0-9A-Fa-f]{6}$/)) {
                colorPicker.value = this.value;
                colorPreview.style.backgroundColor = this.value;
            } else {
                // Reset to color picker value if invalid
                this.value = colorPicker.value;
                colorPreview.style.backgroundColor = colorPicker.value;
            }
        });
    }
});
</script>