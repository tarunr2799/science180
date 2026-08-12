<?php
// admin/partials/templates-list.php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;
$templates_table = $wpdb->prefix . $table_prefix . 'templates';
$template_categories_table = $wpdb->prefix . $table_prefix . 'template_categories';
$categories_table = $wpdb->prefix . $table_prefix . 'categories';

$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 50;
$offset = ($paged - 1) * $per_page;

$where = array('1=1');
if ($status_filter === 'active') {
    $where[] = 't.is_active = 1';
} elseif ($status_filter === 'inactive') {
    $where[] = 't.is_active = 0';
}

if ($search !== '') {
    $like = '%' . $wpdb->esc_like($search) . '%';
    $where[] = $wpdb->prepare('(t.name LIKE %s OR t.subject LIKE %s)', $like, $like);
}

$where_clause = 'WHERE ' . implode(' AND ', $where);
$total = intval($wpdb->get_var("SELECT COUNT(*) FROM $templates_table t $where_clause"));

$templates = $wpdb->get_results($wpdb->prepare(
    "SELECT t.*,
        GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR '||') as category_names,
        GROUP_CONCAT(c.color ORDER BY c.name SEPARATOR '||') as category_colors
    FROM $templates_table t
    LEFT JOIN $template_categories_table tc ON t.id = tc.template_id
    LEFT JOIN $categories_table c ON tc.category_id = c.id
    $where_clause
    GROUP BY t.id
    ORDER BY t.updated_at DESC, t.name ASC
    LIMIT %d OFFSET %d",
    $per_page,
    $offset
));
?>
<div class="wrap advnews-templates-list">
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

    <form method="get" class="advnews-template-filters">
        <input type="hidden" name="page" value="advnews-templates">
        <select name="status">
            <option value=""><?php _e('All Statuses', 'advnews-manager'); ?></option>
            <option value="active" <?php selected($status_filter, 'active'); ?>><?php _e('Active', 'advnews-manager'); ?></option>
            <option value="inactive" <?php selected($status_filter, 'inactive'); ?>><?php _e('Inactive', 'advnews-manager'); ?></option>
        </select>
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search templates...', 'advnews-manager'); ?>">
        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'advnews-manager'); ?>">
        <?php if ($status_filter || $search): ?>
            <a href="<?php echo admin_url('admin.php?page=advnews-templates'); ?>" class="button">
                <?php _e('Clear Filters', 'advnews-manager'); ?>
            </a>
        <?php endif; ?>
    </form>

    <table class="wp-list-table widefat fixed striped advnews-template-table">
        <thead>
            <tr>
                <th class="column-title"><?php _e('Title', 'advnews-manager'); ?></th>
                <th><?php _e('Subject', 'advnews-manager'); ?></th>
                <th><?php _e('Categories', 'advnews-manager'); ?></th>
                <th><?php _e('Status', 'advnews-manager'); ?></th>
                <th><?php _e('Used', 'advnews-manager'); ?></th>
                <th><?php _e('Updated', 'advnews-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($templates)): ?>
                <tr>
                    <td colspan="6"><?php _e('No templates found.', 'advnews-manager'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td class="template-title column-title">
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=advnews-templates&action=edit&id=' . $template->id); ?>">
                                    <?php echo esc_html($template->name); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo admin_url('admin.php?page=advnews-templates&action=edit&id=' . $template->id); ?>"><?php _e('Edit', 'advnews-manager'); ?></a> |
                                </span>
                                <span class="view">
                                    <a href="<?php echo admin_url('admin.php?page=advnews-templates&action=preview&id=' . $template->id); ?>" target="_blank"><?php _e('Preview', 'advnews-manager'); ?></a> |
                                </span>
                                <span class="duplicate">
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-templates&action=duplicate&id=' . $template->id), 'advnews_duplicate_template'); ?>" onclick="return confirm('<?php esc_attr_e('Duplicate this template?', 'advnews-manager'); ?>');"><?php _e('Duplicate', 'advnews-manager'); ?></a> |
                                </span>
                                <span class="trash">
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-templates&action=delete&id=' . $template->id), 'advnews_delete_template'); ?>" class="submitdelete" onclick="return confirm('<?php esc_attr_e('Delete this template?', 'advnews-manager'); ?>');"><?php _e('Delete', 'advnews-manager'); ?></a>
                                </span>
                            </div>
                        </td>
                        <td class="template-subject"><?php echo esc_html($template->subject); ?></td>
                        <td>
                            <?php
                            $cat_names = $template->category_names ? explode('||', $template->category_names) : array();
                            $cat_colors = $template->category_colors ? explode('||', $template->category_colors) : array();
                            ?>
                            <?php if (!empty($cat_names)): ?>
                                <?php foreach ($cat_names as $idx => $name): ?>
                                    <span class="category-badge" style="background-color: <?php echo esc_attr($cat_colors[$idx] ?? '#6c757d'); ?>;">
                                        <?php echo esc_html($name); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="category-badge is-muted"><?php _e('Uncategorized', 'advnews-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="template-status <?php echo $template->is_active ? 'active' : 'inactive'; ?>">
                                <?php echo $template->is_active ? esc_html__('Active', 'advnews-manager') : esc_html__('Inactive', 'advnews-manager'); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(intval($template->usage_count)); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($template->updated_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total > $per_page): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => ceil($total / $per_page),
                    'current' => $paged
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.advnews-template-filters {
    margin: 16px 0;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.advnews-template-table .template-title strong,
.advnews-template-table .template-subject {
    display: block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.category-badge {
    display: inline-block;
    margin: 2px 3px 2px 0;
    padding: 2px 8px;
    border-radius: 3px;
    color: #fff;
    font-size: 11px;
    line-height: 1.6;
}
.category-badge.is-muted {
    background: #6c757d;
}
.template-status {
    display: inline-block;
    padding: 2px 8px;
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
</style>
