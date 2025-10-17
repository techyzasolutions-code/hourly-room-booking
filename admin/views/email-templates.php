<?php
/**
 * Email Templates Admin View
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap hrb-admin-email-templates">
    <h1 class="wp-heading-inline">
        <?php _e('Email Templates', 'hourly-room-booking'); ?>
    </h1>
    <div class="hrb-template-tabs">
        <button class="hrb-tab-button active" data-tab="user"><?php _e('User Templates', 'hourly-room-booking'); ?></button>
        <button class="hrb-tab-button" data-tab="admin"><?php _e('Admin Templates', 'hourly-room-booking'); ?></button>
    </div>

    <div class="hrb-templates-container">
        <?php if (empty($templates)): ?>
            <div class="hrb-no-templates">
                <div class="hrb-empty-state">
                    <span class="dashicons dashicons-email-alt"></span>
                    <h3><?php _e('No email templates found', 'hourly-room-booking'); ?></h3>
                    <p><?php _e('Email templates will be created automatically when you activate the plugin.', 'hourly-room-booking'); ?></p>
                </div>
            </div>
        <?php else: ?>
            <!-- User Templates -->
            <div class="hrb-templates-grid hrb-tab-content" id="user-templates">
                <?php 
                $user_templates = array_filter($templates, function($template) {
                    return $template->template_type === 'user';
                });
                foreach ($user_templates as $template): 
                ?>
                    <div class="hrb-template-card <?php echo $template->is_active ? 'active' : 'inactive'; ?>">
                        <div class="hrb-template-header">
                            <h3><?php echo esc_html($template->template_name); ?></h3>
                            <div class="hrb-template-actions">
                                <button type="button" class="button button-small hrb-view-template" 
                                        data-template-id="<?php echo $template->id; ?>">
                                    <?php _e('View', 'hourly-room-booking'); ?>
                                </button>
                                <button type="button" class="button button-small hrb-toggle-template" 
                                        data-template-id="<?php echo $template->id; ?>" 
                                        data-is-active="<?php echo $template->is_active; ?>">
                                    <?php echo $template->is_active ? __('Deactivate', 'hourly-room-booking') : __('Activate', 'hourly-room-booking'); ?>
                                </button>
                                <button type="button" class="button button-primary button-small hrb-edit-template" 
                                        data-template-id="<?php echo $template->id; ?>">
                                    <?php _e('Edit', 'hourly-room-booking'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="hrb-template-content">
                            <div class="hrb-template-info">
                                <p><strong><?php _e('Subject:', 'hourly-room-booking'); ?></strong> <?php echo esc_html($template->subject); ?></p>
                                <p><strong><?php _e('Heading:', 'hourly-room-booking'); ?></strong> <?php echo esc_html($template->heading); ?></p>
                                <p><strong><?php _e('Status:', 'hourly-room-booking'); ?></strong> 
                                    <span class="hrb-status-badge <?php echo $template->is_active ? 'active' : 'inactive'; ?>">
                                        <?php echo $template->is_active ? __('Active', 'hourly-room-booking') : __('Inactive', 'hourly-room-booking'); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Admin Templates -->
            <div class="hrb-templates-grid hrb-tab-content" id="admin-templates" style="display: none;">
                <?php 
                $admin_templates = array_filter($templates, function($template) {
                    return $template->template_type === 'admin';
                });
                foreach ($admin_templates as $template): 
                ?>
                    <div class="hrb-template-card <?php echo $template->is_active ? 'active' : 'inactive'; ?>">
                        <div class="hrb-template-header">
                            <h3><?php echo esc_html($template->template_name); ?></h3>
                            <div class="hrb-template-actions">
                                <button type="button" class="button button-small hrb-view-template" 
                                        data-template-id="<?php echo $template->id; ?>">
                                    <?php _e('View', 'hourly-room-booking'); ?>
                                </button>
                                <button type="button" class="button button-small hrb-edit-template" 
                                        data-template-id="<?php echo $template->id; ?>">
                                    <?php _e('Edit', 'hourly-room-booking'); ?>
                                </button>
                                <button type="button" class="button button-small hrb-toggle-template" 
                                        data-template-id="<?php echo $template->id; ?>" 
                                        data-is-active="<?php echo $template->is_active; ?>">
                                    <?php echo $template->is_active ? __('Deactivate', 'hourly-room-booking') : __('Activate', 'hourly-room-booking'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="hrb-template-content">
                            <div class="hrb-template-info">
                                <p><strong><?php _e('Subject:', 'hourly-room-booking'); ?></strong> <?php echo esc_html($template->subject); ?></p>
                                <p><strong><?php _e('Heading:', 'hourly-room-booking'); ?></strong> <?php echo esc_html($template->heading); ?></p>
                                <p><strong><?php _e('Status:', 'hourly-room-booking'); ?></strong> 
                                    <span class="hrb-status-badge <?php echo $template->is_active ? 'active' : 'inactive'; ?>">
                                        <?php echo $template->is_active ? __('Active', 'hourly-room-booking') : __('Inactive', 'hourly-room-booking'); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Template Modal -->
<div id="hrb-edit-template-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-content">
        <div class="hrb-modal-header">
            <h3><?php _e('Edit Email Template', 'hourly-room-booking'); ?></h3>
            <span class="hrb-modal-close">&times;</span>
        </div>
        
        <form method="POST" id="hrb-template-form">
            <?php wp_nonce_field('hrb_email_template_action', 'hrb_nonce'); ?>
            <input type="hidden" name="action" value="update_template">
            <input type="hidden" name="template_id" id="template_id">
            
            <div class="hrb-modal-body">
                <div class="hrb-form-row_c">
                    <label for="template_name"><?php _e('Template Name', 'hourly-room-booking'); ?></label>
                    <input type="text" id="template_name" name="template_name" class="regular-text" required>
                </div>
                
                <div class="hrb-form-row_c">
                    <label for="template_type"><?php _e('Template Type', 'hourly-room-booking'); ?></label>
                    <select id="template_type" name="template_type" class="regular-text" required>
                        <option value="user"><?php _e('User Template', 'hourly-room-booking'); ?></option>
                        <option value="admin"><?php _e('Admin Template', 'hourly-room-booking'); ?></option>
                    </select>
                    <p class="description"><?php _e('User templates are sent to customers, admin templates are sent to administrators.', 'hourly-room-booking'); ?></p>
                </div>
                
                <div class="hrb-form-row_c">
                    <label for="subject"><?php _e('Email Subject', 'hourly-room-booking'); ?></label>
                    <input type="text" id="subject" name="subject" class="regular-text" required>
                    <p class="description"><?php _e('Use variables like {booking_reference}, {customer_name}, etc.', 'hourly-room-booking'); ?></p>
                </div>
                
                <div class="hrb-form-row_c">
                    <label for="heading"><?php _e('Email Heading', 'hourly-room-booking'); ?></label>
                    <input type="text" id="heading" name="heading" class="regular-text" required>
                </div>
                
                <div class="hrb-form-row_c">
                    <label for="message"><?php _e('Message Content', 'hourly-room-booking'); ?></label>
                    <textarea id="message" name="message" rows="4" class="large-text" required></textarea>
                    <p class="description"><?php _e('This is the main message content that will appear in the email.', 'hourly-room-booking'); ?></p>
                </div>
                
                <div class="hrb-form-row_c">
                    <label for="html_content"><?php _e('HTML Content', 'hourly-room-booking'); ?></label>
                    <textarea id="html_content" name="html_content" rows="20" class="large-text code" required></textarea>
                    <p class="description">
                        <?php _e('Full HTML template. Available variables:', 'hourly-room-booking'); ?>
                        <code>{company_name}</code>, <code>{customer_name}</code>, <code>{customer_first_name}</code>, 
                        <code>{booking_reference}</code>, <code>{room_name}</code>, <code>{booking_date}</code>, 
                        <code>{start_time}</code>, <code>{end_time}</code>, <code>{duration}</code>, 
                        <code>{total_amount}</code>, <code>{payment_method}</code>, <code>{booking_status}</code>, 
                        <code>{booking_url}</code>, <code>{company_email}</code>, <code>{company_phone}</code>
                    </p>
                </div>
                
                <div class="hrb-form-row_c">
                    <label>
                        <input type="checkbox" id="is_active" name="is_active" value="1">
                        <?php _e('Active Template', 'hourly-room-booking'); ?>
                    </label>
                    <p class="description"><?php _e('Only active templates will be used for sending emails.', 'hourly-room-booking'); ?></p>
                </div>
            </div>
            
            <div class="hrb-modal-footer">
                <button type="button" class="button" onclick="closeTemplateModal()"><?php _e('Cancel', 'hourly-room-booking'); ?></button>
                <button type="submit" class="button button-primary"><?php _e('Update Template', 'hourly-room-booking'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- View Template Modal -->
<div id="hrb-view-template-modal" class="hrb-modal" style="display: none;">
    <div class="hrb-modal-content hrb-view-modal">
        <div class="hrb-modal-header">
            <h3><?php _e('Email Template Preview', 'hourly-room-booking'); ?></h3>
            <span class="hrb-modal-close">&times;</span>
        </div>
        
        <div class="hrb-modal-body">
            <div class="hrb-template-preview-info">
                <div class="hrb-preview-meta">
                    <p><strong><?php _e('Template Name:', 'hourly-room-booking'); ?></strong> <span id="view_template_name"></span></p>
                    <p><strong><?php _e('Subject:', 'hourly-room-booking'); ?></strong> <span id="view_subject"></span></p>
                    <p><strong><?php _e('Heading:', 'hourly-room-booking'); ?></strong> <span id="view_heading"></span></p>
                    <p><strong><?php _e('Status:', 'hourly-room-booking'); ?></strong> <span id="view_status"></span></p>
                </div>
            </div>
            
            <div class="hrb-template-preview-content">
                <h4><?php _e('Message Content:', 'hourly-room-booking'); ?></h4>
                <div class="hrb-message-preview">
                    <div id="view_message"></div>
                </div>
                
                <h4><?php _e('HTML Email Preview:', 'hourly-room-booking'); ?></h4>
                <div class="hrb-html-preview">
                    <iframe id="view_html_content" width="100%" height="500" style="border: 1px solid #ddd; border-radius: 4px;"></iframe>
                </div>
            </div>
        </div>
        
        <div class="hrb-modal-footer">
            <button type="button" class="button" onclick="closeViewModal()"><?php _e('Close', 'hourly-room-booking'); ?></button>
        </div>
    </div>
</div>

<!-- Toggle Template Form -->
<form id="hrb-toggle-form" method="POST" style="display: none;">
    <?php wp_nonce_field('hrb_email_template_action', 'hrb_nonce'); ?>
    <input type="hidden" name="action" value="toggle_template">
    <input type="hidden" name="template_id" id="toggle_template_id">
    <input type="hidden" name="is_active" id="toggle_is_active">
</form>

<style>
.hrb-admin-email-templates {
    max-width: 1200px;
}

.hrb-template-tabs {
    margin: 20px 0;
    border-bottom: 1px solid #ddd;
}

.hrb-tab-button {
    background: none;
    border: none;
    padding: 10px 20px;
    margin-right: 5px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    font-size: 14px;
    color: #666;
}

.hrb-tab-button.active {
    color: #0073aa;
    border-bottom-color: #0073aa;
    font-weight: 600;
}

.hrb-tab-button:hover {
    color: #0073aa;
}

.hrb-tab-content {
    margin-top: 20px;
}

.hrb-templates-container {
    margin-top: 20px;
}

.hrb-templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.hrb-template-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.hrb-template-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.hrb-template-card.inactive {
    opacity: 0.7;
    border-color: #ccc;
}

.hrb-template-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.hrb-template-header h3 {
    margin: 0;
    color: #333;
}

.hrb-template-actions {
    display: flex;
    gap: 8px;
}

.hrb-template-info p {
    margin: 8px 0;
    font-size: 14px;
}

.hrb-status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.hrb-status-badge.active {
    background: #d4edda;
    color: #155724;
}

.hrb-status-badge.inactive {
    background: #f8d7da;
    color: #721c24;
}

/* View Modal Styles */
.hrb-view-modal .hrb-modal-content {
    max-width: 1000px;
    width: 95%;
}

.hrb-template-preview-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.hrb-preview-meta p {
    margin: 8px 0;
    font-size: 14px;
}

.hrb-template-preview-content h4 {
    margin: 20px 0 10px 0;
    color: #333;
    border-bottom: 2px solid #007cba;
    padding-bottom: 5px;
}

.hrb-message-preview {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #007cba;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.6;
}

.hrb-html-preview {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
}

.hrb-no-templates {
    text-align: center;
    padding: 60px 20px;
}

.hrb-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}

.hrb-empty-state .dashicons {
    font-size: 48px;
    color: #ccc;
}

.hrb-empty-state h3 {
    margin: 0;
    color: #666;
}

.hrb-empty-state p {
    margin: 0;
    color: #999;
}

/* Modal Styles */
.hrb-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    z-index: 999999;
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
    border-radius: 12px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: scale(0.9);
    animation: modalIn 0.3s ease forwards;
}

@keyframes modalIn {
    to {
        transform: scale(1);
    }
}

.hrb-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
}

.hrb-modal-header h3 {
    margin: 0;
    color: #333;
}

.hrb-modal-close {
    font-size: 24px;
    cursor: pointer;
    color: #999;
    transition: color 0.3s ease;
}

.hrb-modal-close:hover {
    color: #333;
}

.hrb-modal-body {
    padding: 30px;
    max-height: 60vh;
    overflow-y: auto;
}

.hrb-form-row_c {
    margin-bottom: 20px;
}

.hrb-form-row_c label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.hrb-form-row_c input,
.hrb-form-row_c textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.hrb-form-row_c input:focus,
.hrb-form-row_c textarea:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
}

.hrb-form-row_c .description {
    margin-top: 5px;
    font-size: 12px;
    color: #666;
    line-height: 1.4;
}

.hrb-form-row_c code {
    background: #f1f1f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 11px;
}

.hrb-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 30px;
    border-top: 1px solid #eee;
    background: #f8f9fa;
}

/* Responsive */
@media (max-width: 768px) {
    .hrb-templates-grid {
        grid-template-columns: 1fr;
    }
    
    .hrb-template-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .hrb-template-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .hrb-modal-content {
        width: 95%;
        margin: 20px;
    }
    
    .hrb-modal-body {
        padding: 20px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // View template
    $('.hrb-view-template').on('click', function() {
        var templateId = $(this).data('template-id');
        
        // Get template data via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hrb_get_template',
                template_id: templateId,
                nonce: '<?php echo wp_create_nonce('hrb_get_template'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var template = response.data;
                    
                    // Populate view modal
                    $('#view_template_name').text(template.template_name);
                    $('#view_subject').text(template.subject);
                    $('#view_heading').text(template.heading);
                    $('#view_status').html(template.is_active == 1 ? 
                        '<span class="hrb-status-badge active"><?php _e('Active', 'hourly-room-booking'); ?></span>' : 
                        '<span class="hrb-status-badge inactive"><?php _e('Inactive', 'hourly-room-booking'); ?></span>');
                    $('#view_message').html(template.message);
                    
                    // Load HTML content in iframe
                    var iframe = document.getElementById('view_html_content');
                    var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    iframeDoc.open();
                    iframeDoc.write(template.html_content);
                    iframeDoc.close();
                    
                    $('#hrb-view-template-modal').show();
                } else {
                    alert('Error loading template: ' + response.data);
                }
            },
            error: function() {
                alert('Error loading template data.');
            }
        });
    });
    
    // Edit template
    $('.hrb-edit-template').on('click', function() {
        var templateId = $(this).data('template-id');
        
        // Get template data via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hrb_get_template',
                template_id: templateId,
                nonce: '<?php echo wp_create_nonce('hrb_get_template'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var template = response.data;
                    $('#template_id').val(template.id);
                    $('#template_name').val(template.template_name);
                    $('#subject').val(template.subject);
                    $('#heading').val(template.heading);
                    $('#message').val(template.message);
                    $('#html_content').val(template.html_content);
                    $('#is_active').prop('checked', template.is_active == 1);
                    
                    $('#hrb-edit-template-modal').show();
                } else {
                    alert('Error loading template: ' + response.data);
                }
            },
            error: function() {
                alert('Error loading template data.');
            }
        });
    });
    
    // Toggle template status
    $('.hrb-toggle-template').on('click', function() {
        var templateId = $(this).data('template-id');
        var isActive = $(this).data('is-active');
        var newStatus = isActive ? 0 : 1;
        
        if (confirm('<?php _e('Are you sure you want to', 'hourly-room-booking'); ?> ' + (newStatus ? '<?php _e('activate', 'hourly-room-booking'); ?>' : '<?php _e('deactivate', 'hourly-room-booking'); ?>') + ' <?php _e('this template?', 'hourly-room-booking'); ?>')) {
            $('#toggle_template_id').val(templateId);
            $('#toggle_is_active').val(newStatus);
            $('#hrb-toggle-form').submit();
        }
    });
    
    // Close modal
    $('.hrb-modal-close, .hrb-modal').on('click', function(e) {
        if (e.target === this) {
            closeTemplateModal();
            closeViewModal();
        }
    });
    
    // Prevent modal close when clicking inside
    $('.hrb-modal-content').on('click', function(e) {
        e.stopPropagation();
    });
});

function closeTemplateModal() {
    document.getElementById('hrb-edit-template-modal').style.display = 'none';
}

function closeViewModal() {
    document.getElementById('hrb-view-template-modal').style.display = 'none';
}

// Tab switching functionality
jQuery(document).ready(function($) {
    $('.hrb-tab-button').on('click', function() {
        var tab = $(this).data('tab');
        
        // Update button states
        $('.hrb-tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Show/hide content
        $('.hrb-tab-content').hide();
        $('#' + tab + '-templates').show();
    });
    
    // Update template editing to include template_type
    $('.hrb-edit-template').on('click', function() {
        var templateId = $(this).data('template-id');
        
        // Get template data via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hrb_get_template',
                template_id: templateId,
                nonce: '<?php echo wp_create_nonce('hrb_get_template'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var template = response.data;
                    $('#template_id').val(template.id);
                    $('#template_name').val(template.template_name);
                    $('#template_type').val(template.template_type);
                    $('#subject').val(template.subject);
                    $('#heading').val(template.heading);
                    $('#message').val(template.message);
                    $('#html_content').val(template.html_content);
                    $('#is_active').prop('checked', template.is_active == 1);
                    
                    $('#hrb-edit-template-modal').show();
                } else {
                    alert('Error loading template: ' + response.data);
                }
            },
            error: function() {
                alert('Error loading template data.');
            }
        });
    });
});
</script>

