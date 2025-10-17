<?php
/**
 * Room Details Page Template
 * Displays detailed information about a specific room
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get room ID from URL
$room_id = get_query_var('room_id') ?: (isset($_GET['room_id']) ? intval($_GET['room_id']) : 0);

if (!$room_id) {
    wp_redirect(home_url());
    exit;
}

// Get room manager
$room_manager = HRB_Room_Manager::getInstance();
$room = $room_manager->get_room($room_id);

if (!$room || !$room->is_active) {
    wp_redirect(home_url());
    exit;
}

// Get room amenities and images
$amenities = $room_manager->get_room_amenities($room_id);
$images = $room_manager->get_room_images($room_id);

// Get settings for pricing
$settings = HRB_Settings::getInstance();
$currency_symbol = hrb_get_currency_symbol();
$price_range = $room_manager->get_room_price_range($room);
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($room->name); ?> - <?php bloginfo('name'); ?></title>
    <style>
        /* Room Details Page Styles */
       

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--hrb-text);
            background: var(--hrb-background-light);
        }

        .hrb-room-details-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .hrb-room-header {
            background: var(--hrb-background);
            border-radius: var(--hrb-radius-lg);
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: var(--hrb-shadow-md);
        }

        .hrb-room-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--hrb-text);
            margin-bottom: 10px;
        }

        .hrb-room-subtitle {
            font-size: 1.2rem;
            color: var(--hrb-text-light);
            margin-bottom: 20px;
        }

        .hrb-room-description {
            font-size: 1.1rem;
            line-height: 1.7;
            color: var(--hrb-text);
            margin-bottom: 30px;
        }

        .hrb-room-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .hrb-room-images {
            background: var(--hrb-background);
            border-radius: var(--hrb-radius-lg);
            padding: 20px;
            box-shadow: var(--hrb-shadow-md);
        }

        .hrb-room-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: var(--hrb-radius);
            margin-bottom: 15px;
        }

        .hrb-room-info {
            background: var(--hrb-background);
            border-radius: var(--hrb-radius-lg);
            padding: 30px;
            box-shadow: var(--hrb-shadow-md);
        }

        .hrb-room-details {
            margin-bottom: 30px;
        }

        .hrb-room-detail {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background: var(--hrb-background-light);
            border-radius: var(--hrb-radius);
            border-left: 4px solid var(--hrb-primary);
        }

        .hrb-room-detail i {
            margin-right: 12px;
            font-size: 18px;
            color: var(--hrb-primary);
        }

        .hrb-room-detail span {
            font-weight: 500;
            color: var(--hrb-text);
        }

        .hrb-room-amenities {
            margin-bottom: 30px;
        }

        .hrb-amenities-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--hrb-text);
            margin-bottom: 15px;
        }

        .hrb-amenities-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }

        .hrb-amenity {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background: var(--hrb-background-light);
            border-radius: var(--hrb-radius);
            border: 1px solid var(--hrb-border);
        }

        .hrb-amenity i {
            margin-right: 8px;
            color: var(--hrb-success);
        }

        .hrb-booking-section {
            background: var(--hrb-background);
            border-radius: var(--hrb-radius-lg);
            padding: 30px;
            box-shadow: var(--hrb-shadow-md);
            text-align: center;
        }

        .hrb-price-range {
            font-size: 2rem;
            font-weight: 700;
            color: var(--hrb-primary);
            margin-bottom: 10px;
        }

        .hrb-price-note {
            color: var(--hrb-text-light);
            margin-bottom: 30px;
        }

        .hrb-book-btn {
            display: inline-block;
            background: var(--hrb-primary);
            color: white;
            padding: 15px 30px;
            border-radius: var(--hrb-radius);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: var(--hrb-transition);
            border: none;
            cursor: pointer;
        }

        .hrb-book-btn:hover {
            background: var(--hrb-primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--hrb-shadow-lg);
        }

        .hrb-back-btn {
            display: inline-block;
            background: var(--hrb-background);
            color: var(--hrb-text);
            padding: 10px 20px;
            border-radius: var(--hrb-radius);
            text-decoration: none;
            font-weight: 500;
            border: 1px solid var(--hrb-border);
            transition: var(--hrb-transition);
            margin-bottom: 20px;
        }

        .hrb-back-btn:hover {
            background: var(--hrb-background-light);
            border-color: var(--hrb-primary);
        }

        @media (max-width: 768px) {
            .hrb-room-grid {
                grid-template-columns: 1fr;
            }
            
            .hrb-room-title {
                font-size: 2rem;
            }
            
            .hrb-room-header {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="hrb-room-details-page">
        <a href="javascript:history.back()" class="hrb-back-btn">
            ← <?php _e('Back to Search', 'hourly-room-booking'); ?>
        </a>

        <div class="hrb-room-header">
            <h1 class="hrb-room-title"><?php echo esc_html($room->name); ?></h1>
            <p class="hrb-room-subtitle"><?php printf(__('Up to %d people', 'hourly-room-booking'), $room->capacity); ?></p>
            <?php if (!empty($room->description)): ?>
                <div class="hrb-room-description">
                    <?php echo wp_kses_post($room->description); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="hrb-room-grid">
            <div class="hrb-room-images">
                <?php if (!empty($images)): ?>
                    <?php foreach (array_slice($images, 0, 3) as $image): ?>
                        <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($room->name); ?>" class="hrb-room-image">
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="hrb-room-image" style="background: var(--hrb-background-light); display: flex; align-items: center; justify-content: center; color: var(--hrb-text-muted);">
                        <?php _e('No images available', 'hourly-room-booking'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="hrb-room-info">
                <div class="hrb-room-details">
                    <div class="hrb-room-detail">
                        <i>👥</i>
                        <span><?php printf(__('Capacity: %d people', 'hourly-room-booking'), $room->capacity); ?></span>
                    </div>
                    
                    <div class="hrb-room-detail">
                        <i>💰</i>
                        <span><?php echo esc_html($price_range['formatted']); ?></span>
                    </div>
                    
                    <div class="hrb-room-detail">
                        <i>⏰</i>
                        <span><?php _e('Flexible booking times', 'hourly-room-booking'); ?></span>
                    </div>
                </div>

                <?php if (!empty($amenities)): ?>
                    <div class="hrb-room-amenities">
                        <h3 class="hrb-amenities-title"><?php _e('Amenities & Features', 'hourly-room-booking'); ?></h3>
                        <div class="hrb-amenities-list">
                            <?php foreach ($amenities as $amenity): ?>
                                <div class="hrb-amenity">
                                    <i>✓</i>
                                    <span><?php echo esc_html($amenity); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="hrb-booking-section">
                    <div class="hrb-price-range"><?php echo esc_html($price_range['formatted']); ?></div>
                    <p class="hrb-price-note"><?php _e('Starting price for 2 hours', 'hourly-room-booking'); ?></p>
                    <a href="<?php echo home_url('/booking/?room_id=' . $room_id); ?>" class="hrb-book-btn">
                        <?php _e('Book This Room', 'hourly-room-booking'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
