<?php
/**
 * Extras Management View
 * Displays the admin interface for managing extras
 */

if (!defined('ABSPATH')) {
    exit;
}

$extras_manager = HRB_Extras::getInstance();
$extras = $extras_manager->get_extras('all'); // Get all extras including inactive

// Handle filtering
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
if ($filter_status !== 'all') {
    $active_only = $filter_status;
    $extras = $extras_manager->get_extras($active_only);
}

// Get extras statistics
$stats = $extras_manager->get_extras_stats();
?>

<div class="hrb-admin-page">
    <div class="hrb-page-header">
        <div class="hrb-page-title">
            <h1><?php _e('Manage Extras', 'hourly-room-booking'); ?></h1>
            <p class="description"><?php _e('Add, edit, and manage booking extras. Extras are optional items customers can add to their bookings.', 'hourly-room-booking'); ?></p>
        </div>
        <div class="hrb-page-actions">
            <button type="button" class="button button-primary" onclick="showAddExtraModal()">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Add New Extra', 'hourly-room-booking'); ?>
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="hrb-stats-grid" style="margin-bottom: 20px;">
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php echo esc_html($stats['total_active'] ?? 0); ?></div>
            <div class="hrb-stat-label"><?php _e('Active Extras', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php echo esc_html($stats['total_inactive'] ?? 0); ?></div>
            <div class="hrb-stat-label"><?php _e('Inactive Extras', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php echo hrb_format_amount($stats['monthly_revenue'] ?? 0); ?></div>
            <div class="hrb-stat-label"><?php _e('Monthly Revenue', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php echo esc_html($stats['total_booked_today'] ?? 0); ?></div>
            <div class="hrb-stat-label"><?php _e('Booked Today', 'hourly-room-booking'); ?></div>
            </div>
        </div>
        <!-- <div class="hrb-stat-card">
            <div class="hrb-stat-content">
            <div class="hrb-stat-number"><?php echo esc_html($stats['popular_extra']->name ?? '-'); ?></div>
            <div class="hrb-stat-label"><?php _e('Most Popular', 'hourly-room-booking'); ?></div>
            </div>
        </div> -->
    </div>

    <!-- Filters -->
    <div class="hrb-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="hrb-extras">

            <select name="status" onchange="this.form.submit()">
                <option value="all" <?php selected($filter_status, 'all'); ?>><?php _e('All Extras', 'hourly-room-booking'); ?></option>
                <option value="active" <?php selected($filter_status, 'active'); ?>><?php _e('Active Only', 'hourly-room-booking'); ?></option>
                <option value="inactive" <?php selected($filter_status, 'inactive'); ?>><?php _e('Inactive Only', 'hourly-room-booking'); ?></option>
            </select>

            <button type="submit" class="button"><?php _e('Filter', 'hourly-room-booking'); ?></button>
        </form>
    </div>

    <!-- Extras Table -->
    <div class="hrb-table-container">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col" class="column-image"><?php _e('Image', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-name"><?php _e('Name', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-description"><?php _e('Description', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-price"><?php _e('Price', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-stock"><?php _e('Stock', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-availability"><?php _e('Current Availability', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-sort"><?php _e('Sort Order', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-status"><?php _e('Status', 'hourly-room-booking'); ?></th>
                    <th scope="col" class="column-actions"><?php _e('Actions', 'hourly-room-booking'); ?></th>
                </tr>
            </thead>
            <tbody id="sortable-extras">
                <?php if (empty($extras)): ?>
                    <tr>
                        <td colspan="9" class="hrb-no-data">
                            <div class="hrb-empty-state">
                                <span class="dashicons dashicons-cart"></span>
                                <h3><?php _e('No extras found', 'hourly-room-booking'); ?></h3>
                                <p><?php _e('Start by adding your first extra item.', 'hourly-room-booking'); ?></p>
                                <button type="button" class="button button-primary" onclick="showAddExtraModal()">
                                    <?php _e('Add First Extra', 'hourly-room-booking'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($extras as $extra): ?>
                        <tr data-extra-id="<?php echo $extra->id; ?>" class="<?php echo $extra->is_active ? 'active' : 'inactive'; ?>">
                            <td class="column-image">
                                <?php if (!empty($extra->image_url)): ?>
                                    <img src="<?php echo esc_url($extra->image_url); ?>"
                                         alt="<?php echo esc_attr($extra->name); ?>"
                                         class="hrb-extra-image"
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div class="hrb-no-image">
                                        <span class="dashicons dashicons-camera"></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="column-name">
                                <strong><?php echo esc_html($extra->name); ?></strong>
                            </td>
                            <td class="column-description">
                                <?php echo wp_trim_words(esc_html($extra->description), 10); ?>
                            </td>
                            <td class="column-price">
                                <strong><?php echo hrb_format_amount($extra->price); ?></strong>
                            </td>
                            <td class="column-stock">
                                <?php if (isset($extra->track_stock) && $extra->track_stock): ?>
                                    <span class="hrb-stock-info">
                                        <strong><?php echo intval($extra->stock_quantity ?? 0); ?></strong>
                                        <?php if ($extra->stock_quantity <= 0): ?>
                                            <span class="hrb-stock-status out-of-stock"><?php _e('Out of Stock', 'hourly-room-booking'); ?></span>
                                        <?php elseif ($extra->stock_quantity <= 5): ?>
                                            <span class="hrb-stock-status low-stock"><?php _e('Low Stock', 'hourly-room-booking'); ?></span>
                                        <?php else: ?>
                                            <span class="hrb-stock-status in-stock"><?php _e('In Stock', 'hourly-room-booking'); ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="hrb-stock-unlimited"><?php _e('Unlimited', 'hourly-room-booking'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-availability">
                                <?php
                                $stock_manager = HRB_Extra_Stock_Manager::getInstance();
                                $today = date('Y-m-d');
                                $now = date('H:i:s');
                                $end_of_day = '23:59:59';
                                
                                if (isset($extra->track_stock) && $extra->track_stock) {
                                    $availability = $stock_manager->check_availability($extra->id, $today, $now, $end_of_day, 1);
                                    echo '<span class="hrb-availability-info">';
                                    echo '<strong>' . $availability['available_quantity'] . '</strong> / ' . $availability['total_stock'];
                                    if ($availability['available_quantity'] > 0) {
                                        echo '<span class="hrb-availability-status available">' . __('Available', 'hourly-room-booking') . '</span>';
                                    } else {
                                        echo '<span class="hrb-availability-status unavailable">' . __('Unavailable', 'hourly-room-booking') . '</span>';
                                    }
                                    echo '</span>';
                                } else {
                                    echo '<span class="hrb-availability-unlimited">' . __('Always Available', 'hourly-room-booking') . '</span>';
                                }
                                ?>
                            </td>
                            <td class="column-sort">
                                <span class="sort-handle">⋮⋮</span>
                                <?php echo esc_html($extra->sort_order); ?>
                            </td>
                            <td class="column-status">
                                <span class="hrb-status hrb-status-<?php echo $extra->is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $extra->is_active ? __('Active', 'hourly-room-booking') : __('Inactive', 'hourly-room-booking'); ?>
                                </span>
                            </td>
                            <td class="column-actions">
                                <div class="hrb-actions">
                                    <button type="button" class="button button-small" onclick="editExtra(<?php echo $extra->id; ?>)" title="<?php _e('Edit Extra', 'hourly-room-booking'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>

                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('hrb_admin_action', '_wpnonce'); ?>
                                        <input type="hidden" name="action" value="toggle_extra_status">
                                        <input type="hidden" name="extra_id" value="<?php echo $extra->id; ?>">
                                        <button type="submit" class="button button-small <?php echo $extra->is_active ? 'hrb-toggle-inactive' : 'hrb-toggle-active'; ?>" title="<?php echo $extra->is_active ? __('Deactivate Extra', 'hourly-room-booking') : __('Activate Extra', 'hourly-room-booking'); ?>">
                                            <?php if ($extra->is_active): ?>
                                                <span class="dashicons dashicons-hidden"></span>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-visibility"></span>
                                            <?php endif; ?>
                                        </button>
                                    </form>

                                    <button type="button" class="button button-small hrb-delete-btn" onclick="deleteExtra(<?php echo $extra->id; ?>, '<?php echo esc_js($extra->name); ?>')" title="<?php _e('Delete Extra', 'hourly-room-booking'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Extra Modal -->
<div id="extra-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h2 id="modal-title"><?php _e('Add New Extra', 'hourly-room-booking'); ?></h2>
            <span class="hrb-modal-close" onclick="closeExtraModal()">&times;</span>
        </div>

        <form id="extra-form" method="post">
            <?php wp_nonce_field('hrb_admin_action', '_wpnonce'); ?>
            <input type="hidden" name="action" id="form-action" value="create_extra">
            <input type="hidden" name="extra_id" id="form-extra-id" value="">

            <div class="hrb-modal-body">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="extra_name"><?php _e('Name', 'hourly-room-booking'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" name="extra_name" id="extra_name" class="regular-text" required>
                            <p class="description"><?php _e('Enter the name of the extra item.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="extra_description"><?php _e('Description', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <textarea name="extra_description" id="extra_description" rows="3" class="large-text"></textarea>
                            <p class="description"><?php _e('Optional description of the extra item.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="extra_price"><?php printf(__('Price (%s)', 'hourly-room-booking'), hrb_get_currency_symbol()); ?> *</label>
                        </th>
                        <td>
                            <input type="number" name="extra_price" id="extra_price" min="0" step="0.01" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="track_stock"><?php _e('Stock Management', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <label for="track_stock">
                                <input type="checkbox" name="track_stock" id="track_stock" value="1" checked onchange="toggleStockQuantity()">
                                <?php _e('Enable stock management for this extra', 'hourly-room-booking'); ?>
                            </label>
                            <p class="description"><?php _e('When enabled, you can set a limited quantity. When disabled, unlimited quantity is available.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr id="stock_quantity_row">
                        <th scope="row">
                            <label for="stock_quantity"><?php _e('Stock Quantity', 'hourly-room-booking'); ?> *</label>
                        </th>
                        <td>
                            <input type="number" name="stock_quantity" id="stock_quantity" min="0" class="regular-text" value="0" required>
                            <p class="description"><?php _e('Total available quantity for this extra (e.g., 3 for 3 projectors).', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="extra_image_url"><?php _e('Image URL', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="extra_image_url" id="extra_image_url" class="regular-text">
                            <button type="button" class="button" onclick="uploadExtraImage()"><?php _e('Upload Image', 'hourly-room-booking'); ?></button>
                            <button type="button" class="button button-secondary" onclick="clearExtraImage()"><?php _e('Clear Image', 'hourly-room-booking'); ?></button>
                            <p class="description"><?php _e('Optional image URL for this extra item.', 'hourly-room-booking'); ?></p>
                            <div id="image_preview"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="extra_sort_order"><?php _e('Sort Order', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="extra_sort_order" id="extra_sort_order" min="0" class="regular-text" value="0">
                            <p class="description"><?php _e('Lower numbers appear first. Leave 0 for automatic ordering.', 'hourly-room-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="extra_is_active"><?php _e('Status', 'hourly-room-booking'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="extra_is_active" id="extra_is_active" checked>
                                <?php _e('Active (visible to customers)', 'hourly-room-booking'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="hrb-modal-footer">
                <button type="button" class="button" onclick="closeExtraModal()"><?php _e('Cancel', 'hourly-room-booking'); ?></button>
                <button type="submit" class="button button-primary" id="submit-button"><?php _e('Add Extra', 'hourly-room-booking'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirmation form -->
<form id="delete-extra-form" method="post" style="display: none;">
    <?php wp_nonce_field('hrb_admin_action', '_wpnonce'); ?>
    <input type="hidden" name="action" value="delete_extra">
    <input type="hidden" name="extra_id" id="delete-extra-id" value="">
</form>

<style>
/* Modern Professional Extras Management Styling */
.hrb-admin-page {
    background: #f8f9fa;
    min-height: 100vh;
    padding: 24px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.hrb-page-header {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    padding: 32px;
    border-radius: 16px;
    margin-bottom: 32px;
    box-shadow: 0 8px 32px rgba(245, 158, 11, 0.15);
    position: relative;
    overflow: hidden;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.hrb-page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -30px;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    backdrop-filter: blur(20px);
}

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
    background: linear-gradient(90deg, #f59e0b, #10b981);
}

.hrb-stat-card:nth-child(2)::before {
    background: linear-gradient(90deg, #10b981, #3b82f6);
}

.hrb-stat-card:nth-child(3)::before {
    background: linear-gradient(90deg, #3b82f6, #ef4444);
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
    border-color: #f59e0b;
    outline: none;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
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
    width: 200px;
}

.column-description {
    width: 250px;
    color: #6b7280;
    font-size: 0.9rem;
    line-height: 1.4;
}

.column-price {
    width: 100px;
}

.column-sort {
    width: 100px;
    text-align: center;
}

.column-status {
    width: 100px;
}

.column-actions {
    width: 200px;
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

.hrb-extra-image {
    border-radius: 8px !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease;
}

.hrb-extra-image:hover {
    transform: scale(1.05);
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

.hrb-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.hrb-actions .button {
    font-size: 0.8rem;
    padding: 6px 12px;
    line-height: 1.4;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.hrb-actions .button:hover {
    transform: translateY(-1px);
}

.hrb-actions .button-link-delete {
    color: #dc2626;
    border-color: #dc2626;
}

.hrb-actions .button-link-delete:hover {
    background: #dc2626;
    color: white;
}

.sort-handle {
    cursor: move;
    color: #9ca3af;
    margin-right: 8px;
    font-size: 1.2rem;
    transition: color 0.2s ease;
}

.sort-handle:hover {
    color: #6b7280;
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
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-color: #8b5cf6;
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
    border-color: #f59e0b;
    outline: none;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
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
    background: #f59e0b;
    border-color: #f59e0b;
}

#image_preview {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

#image_preview img {
    max-width: 120px;
    max-height: 120px;
    width: auto;
    height: auto;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
    object-fit: cover;
    cursor: pointer;
}

#image_preview img:hover {
    border-color: #8b5cf6;
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.3);
}

#image_preview img:active {
    transform: scale(0.95);
}

#sortable-extras tr.ui-sortable-helper {
    background: #f8f9fa;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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

/* Stock Management Styles */
.hrb-stock-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.hrb-stock-status {
    font-size: 11px;
    font-weight: 500;
    padding: 2px 6px;
    border-radius: 3px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
}

.hrb-stock-status.in-stock {
    background: #d4edda;
    color: #155724;
}

.hrb-stock-status.low-stock {
    background: #fff3cd;
    color: #856404;
}

.hrb-stock-status.out-of-stock {
    background: #f8d7da;
    color: #721c24;
}

.hrb-stock-unlimited {
    color: #6c757d;
    font-style: italic;
    font-size: 12px;
}

#stock_quantity_row {
    display: table-row;
}

/* Availability Column Styles */
.column-availability {
    width: 150px;
    text-align: center;
}

.hrb-availability-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.hrb-availability-status {
    font-size: 11px;
    font-weight: 500;
    padding: 2px 6px;
    border-radius: 3px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hrb-availability-status.available {
    background: #d4edda;
    color: #155724;
}

.hrb-availability-status.unavailable {
    background: #f8d7da;
    color: #721c24;
}

.hrb-availability-unlimited {
    color: #6c757d;
    font-style: italic;
    font-size: 12px;
}
</style>

<script>
function showAddExtraModal() {
    document.getElementById('modal-title').textContent = '<?php _e('Add New Extra', 'hourly-room-booking'); ?>';
    document.getElementById('form-action').value = 'create_extra';
    document.getElementById('form-extra-id').value = '';
    document.getElementById('submit-button').textContent = '<?php _e('Add Extra', 'hourly-room-booking'); ?>';

    // Reset form
    document.getElementById('extra-form').reset();
    document.getElementById('extra_is_active').checked = true;
    document.getElementById('track_stock').checked = true;
    document.getElementById('stock_quantity').value = '0';
    document.getElementById('image_preview').innerHTML = '';
    toggleStockQuantity(); // Initialize stock quantity field visibility

    document.getElementById('extra-modal').style.display = 'flex';
}

function editExtra(extraId) {
    // Show loading state
    document.getElementById('modal-title').textContent = '<?php _e('Edit Extra', 'hourly-room-booking'); ?>';
    document.getElementById('form-action').value = 'update_extra';
    document.getElementById('form-extra-id').value = extraId;
    document.getElementById('submit-button').textContent = '<?php _e('Update Extra', 'hourly-room-booking'); ?>';

    // Reset form fields
    document.getElementById('extra_name').value = '';
    document.getElementById('extra_description').value = '';
    document.getElementById('extra_price').value = '';
    document.getElementById('stock_quantity').value = '';
    document.getElementById('track_stock').checked = true;
    document.getElementById('extra_image_url').value = '';
    document.getElementById('extra_is_active').checked = false;
    document.getElementById('extra_sort_order').value = '';
    
    // Clear image preview
    document.getElementById('image_preview').innerHTML = '';

    // Show modal
    document.getElementById('extra-modal').style.display = 'flex';

    // Fetch extra data via AJAX
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'hrb_get_extra_details',
            nonce: '<?php echo wp_create_nonce('hrb_admin_nonce'); ?>',
            extra_id: extraId
        },
        success: function(response) {
            if (response.success) {
                const extra = response.data;

                // Populate form fields
                document.getElementById('extra_name').value = extra.name || '';
                document.getElementById('extra_description').value = extra.description || '';
                document.getElementById('extra_price').value = extra.price || '';
                document.getElementById('stock_quantity').value = extra.stock_quantity || '';
                document.getElementById('track_stock').checked = extra.track_stock == 1;
                document.getElementById('extra_image_url').value = extra.image_url || '';
                document.getElementById('extra_is_active').checked = extra.is_active == 1;
                document.getElementById('extra_sort_order').value = extra.sort_order || '';

                // Load image preview if image URL exists
                updateExtraImagePreview(extra.image_url);

                // Update stock quantity field visibility
                toggleStockQuantity();
            } else {
                alert('<?php _e('Error loading extra details:', 'hourly-room-booking'); ?> ' + response.data);
                closeExtraModal();
            }
        },
        error: function() {
            alert('<?php _e('Error loading extra details. Please try again.', 'hourly-room-booking'); ?>');
            closeExtraModal();
        }
    });
}

function closeExtraModal() {
    document.getElementById('extra-modal').style.display = 'none';
}

function deleteExtra(extraId, extraName) {
    if (confirm('<?php _e('Are you sure you want to delete this extra?', 'hourly-room-booking'); ?>\n\n' + extraName)) {
        document.getElementById('delete-extra-id').value = extraId;
        document.getElementById('delete-extra-form').submit();
    }
}

function toggleStockQuantity() {
    const trackStock = document.getElementById('track_stock');
    const stockQuantityRow = document.getElementById('stock_quantity_row');
    const stockQuantityInput = document.getElementById('stock_quantity');

    if (trackStock.checked) {
        stockQuantityRow.style.display = 'table-row';
        stockQuantityInput.required = true;
    } else {
        stockQuantityRow.style.display = 'none';
        stockQuantityInput.required = false;
        stockQuantityInput.value = '0';
    }
}

function uploadExtraImage() {
    
    // WordPress media uploader
    if (typeof wp !== 'undefined' && wp.media) {
        const mediaUploader = wp.media({
            title: '<?php _e('Choose Image', 'hourly-room-booking'); ?>',
            button: {
                text: '<?php _e('Use this image', 'hourly-room-booking'); ?>'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            document.getElementById('extra_image_url').value = attachment.url;
            updateExtraImagePreview(attachment.url);
        });

        mediaUploader.open();
    } else {
        alert('<?php _e('Media library not available. Please refresh the page and try again.', 'hourly-room-booking'); ?>');
    }
}

// Close modal when clicking outside
document.getElementById('extra-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeExtraModal();
    }
});

// Update extra image preview
function updateExtraImagePreview(imageUrl) {
    const preview = document.getElementById('image_preview');
    if (imageUrl) {
        preview.innerHTML = `
            <div style="position: relative; display: inline-block;">
                <img src="${imageUrl}" alt="Preview" onerror="this.style.display='none'">
                <button type="button" onclick="clearExtraImage()" style="position: absolute; top: 5px; right: 5px; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center;">×</button>
            </div>
        `;
    } else {
        preview.innerHTML = '';
    }
}

// Clear extra image
function clearExtraImage() {
    document.getElementById('extra_image_url').value = '';
    document.getElementById('image_preview').innerHTML = '';
}

// Image preview on URL change
document.getElementById('extra_image_url').addEventListener('input', function() {
    const url = this.value;
    updateExtraImagePreview(url);
});

// Initialize sortable if jQuery UI is available
jQuery(document).ready(function($) {
    if ($.fn.sortable) {
        $('#sortable-extras').sortable({
            handle: '.sort-handle',
            update: function(event, ui) {
                const extraIds = [];
                $('#sortable-extras tr[data-extra-id]').each(function(index) {
                    extraIds.push($(this).data('extra-id'));
                });

                // Send AJAX request to update sort order
                $.post(ajaxurl, {
                    action: 'hrb_update_extras_sort_order',
                    extra_ids: extraIds,
                    nonce: '<?php echo wp_create_nonce('hrb_admin_action'); ?>'
                }, function(response) {
                    if (response.success) {
                        console.log('Sort order updated');
                    }
                });
            }
        });
    }
});
</script>