<?php
// admin/partials/templates-list.php
if (!defined('ABSPATH')) exit;
global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;
$templates_table = $wpdb->prefix . $table_prefix . 'templates';
$categories_table = $wpdb->prefix . $table_prefix . 'categories';

// Get all templates with their multiple category information
$templates = $wpdb->get_results("
    SELECT t.*, GROUP_CONCAT(c.name) as category_names, GROUP_CONCAT(c.color) as category_colors
    FROM $templates_table t
    LEFT JOIN {$wpdb->prefix}{$table_prefix}template_categories tc ON t.id = tc.template_id
    LEFT JOIN {$wpdb->prefix}{$table_prefix}categories c ON tc.category_id = c.id
    GROUP BY t.id
    ORDER BY t.name
");

// Debug: Log if templates are found
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Templates list - Templates found: ' . count($templates));
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Email Templates', 'advnews-manager'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=advnews-templates&action=add'); ?>" class="page-title-action">
        <?php _e('Add New Template', 'advnews-manager'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                if ($_GET['message'] == 'template_created') {
                    _e('Template created successfully.', 'advnews-manager');
                } elseif ($_GET['message'] == 'template_updated') {
                    _e('Template updated successfully.', 'advnews-manager');
                } elseif ($_GET['message'] == 'template_deleted') {
                    _e('Template deleted successfully.', 'advnews-manager');
                } elseif ($_GET['message'] == 'template_duplicated') {
                    _e('Template duplicated successfully.', 'advnews-manager');
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (empty($templates)): ?>
        <div class="notice notice-info">
            <p><?php _e('No templates found. Create your first template to get started.', 'advnews-manager'); ?></p>
            <p><a href="<?php echo admin_url('admin.php?page=advnews-templates&action=add'); ?>" class="button button-primary"><?php _e('Create Template', 'advnews-manager'); ?></a></p>
        </div>
    <?php else: ?>
        <div class="advnews-templates-grid">
            <?php foreach ($templates as $template): ?>
                <div class="advnews-template-card">
                    <div class="template-header">
                        <h3><?php echo esc_html($template->name); ?></h3>
                        <span class="template-status <?php echo $template->is_active ? 'active' : 'inactive'; ?>">
                            <?php echo $template->is_active ? __('Active', 'advnews-manager') : __('Inactive', 'advnews-manager'); ?>
                        </span>
                    </div>
                    <div class="template-preview">
                        <div class="preview-subject">
                            <strong><?php _e('Subject:', 'advnews-manager'); ?></strong>
                            <?php echo esc_html($template->subject); ?>
                        </div>

                        <!-- UPDATED: Multi-category display -->
                        <?php if (!empty($template->category_names)): ?>
                            <div class="preview-category">
                                <strong><?php _e('Category:', 'advnews-manager'); ?></strong>
                                <?php
                                $cat_names = explode(',', $template->category_names);
                                $cat_colors = explode(',', $template->category_colors);
                                foreach ($cat_names as $idx => $name):
                                    $color = isset($cat_colors[$idx]) ? $cat_colors[$idx] : '#6c757d';
                                ?>
                                    <span class="category-badge" style="background-color: <?php echo esc_attr($color); ?>; color: #fff; padding: 2px 8px; border-radius: 3px; display: inline-block; font-size: 11px; margin: 2px;">
                                        <?php echo esc_html($name); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="preview-category">
                                <strong><?php _e('Category:', 'advnews-manager'); ?></strong>
                                <span class="category-badge" style="background-color: #6c757d; color: #fff; padding: 2px 8px; border-radius: 3px; display: inline-block; font-size: 11px;">
                                    <?php _e('Uncategorized', 'advnews-manager'); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="preview-content">
                            <?php
                            // Strip HTML tags and show first 100 characters as preview
                            $plain_text = wp_strip_all_tags($template->content);
                            echo esc_html(wp_trim_words($plain_text, 20, '...'));
                            ?>
                        </div>
                    </div>
                    <div class="template-meta">
                        <span class="meta-item" title="<?php _e('Times used in campaigns', 'advnews-manager'); ?>">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <?php printf(__('Used %d times', 'advnews-manager'), $template->usage_count); ?>
                        </span>
                        <span class="meta-item" title="<?php _e('Last modified', 'advnews-manager'); ?>">
                            <span class="dashicons dashicons-calendar"></span>
                            <?php echo esc_html(human_time_diff(strtotime($template->updated_at), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?>
                        </span>
                    </div>
                    <div class="template-actions">
                        <a href="<?php echo admin_url('admin.php?page=advnews-templates&action=edit&id=' . $template->id); ?>" class="button button-small" title="<?php _e('Edit Template', 'advnews-manager'); ?>">
                            <span class="dashicons dashicons-edit"></span> <?php _e('Edit', 'advnews-manager'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=advnews-templates&action=preview&id=' . $template->id); ?>" class="button button-small" target="_blank" title="<?php _e('Preview Template', 'advnews-manager'); ?>">
                            <span class="dashicons dashicons-visibility"></span> <?php _e('Preview', 'advnews-manager'); ?>
                        </a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-templates&action=duplicate&id=' . $template->id), 'advnews_duplicate_template'); ?>" class="button button-small" title="<?php _e('Duplicate Template', 'advnews-manager'); ?>" onclick="return confirm('<?php _e('Are you sure you want to duplicate this template?', 'advnews-manager'); ?>');">
                            <span class="dashicons dashicons-admin-page"></span> <?php _e('Duplicate', 'advnews-manager'); ?>
                        </a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-templates&action=delete&id=' . $template->id), 'advnews_delete_template'); ?>"
                           class="button button-small button-link-delete"
                           title="<?php _e('Delete Template', 'advnews-manager'); ?>"
                           onclick="return confirm('<?php _e('Are you sure you want to delete this template? This action cannot be undone.', 'advnews-manager'); ?>');">
                            <span class="dashicons dashicons-trash"></span> <?php _e('Delete', 'advnews-manager'); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Templates Stats Summary -->
        <div class="advnews-templates-stats" style="margin-top: 30px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px; display: flex; gap: 30px; flex-wrap: wrap;">
            <div>
                <strong><?php _e('Total Templates:', 'advnews-manager'); ?></strong>
                <?php echo count($templates); ?>
            </div>
            <div>
                <strong><?php _e('Active Templates:', 'advnews-manager'); ?></strong>
                <?php echo count(array_filter($templates, function($t) { return $t->is_active; })); ?>
            </div>
            <div>
                <strong><?php _e('Inactive Templates:', 'advnews-manager'); ?></strong>
                <?php echo count(array_filter($templates, function($t) { return !$t->is_active; })); ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<style>
.advnews-templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.advnews-template-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}
.advnews-template-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.template-header {
    background: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.template-header h3 {
    margin: 0;
    font-size: 16px;
    color: #1d2327;
    font-weight: 600;
}
.template-status {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.template-status.active {
    background: #d4edda;
    color: #155724;
}
.template-status.inactive {
    background: #f8d7da;
    color: #721c24;
}
.template-preview {
    padding: 15px;
    background: #fff;
    min-height: 120px;
    flex: 1;
}
.preview-subject {
    margin-bottom: 8px;
    color: #2271b1;
    font-size: 14px;
    font-weight: 500;
    word-break: break-word;
}
.preview-category {
    margin-bottom: 10px;
    font-size: 12px;
}
.category-badge {
    display: inline-block;
    font-weight: 500;
}
.preview-content {
    color: #646970;
    font-size: 13px;
    line-height: 1.6;
    margin-top: 10px;
    word-break: break-word;
}
.template-meta {
    padding: 10px 15px;
    background: #f8f9fa;
    border-top: 1px solid #f0f0f0;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: #666;
}
.meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
}
.meta-item .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}
.template-actions {
    padding: 15px;
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    background: #fff;
}
.template-actions .button {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    flex: 1;
    justify-content: center;
    font-size: 12px;
    padding: 4px 8px;
    min-height: 30px;
}
.template-actions .button .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}
.button-link-delete {
    color: #d63638;
    border-color: #d63638;
}
.button-link-delete:hover {
    background: #d63638;
    color: #fff;
    border-color: #d63638;
}
/* Tooltip styles */
.meta-item[title] {
    cursor: help;
}
/* Responsive */
@media (max-width: 782px) {
    .advnews-templates-grid {
        grid-template-columns: 1fr;
    }
    .template-actions {
        flex-direction: column;
    }
    .template-actions .button {
        width: 100%;
    }
    .advnews-templates-stats {
        flex-direction: column;
        gap: 10px;
    }
}
</style>
