<?php
// admin/partials/templates-list.php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;
$templates_table = $wpdb->prefix . $table_prefix . 'templates';
$template_categories_table = $wpdb->prefix . $table_prefix . 'template_categories';
$categories_table = $wpdb->prefix . $table_prefix . 'categories';

$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$category_ids = isset($_GET['category_ids']) ? array_values(array_unique(array_filter(array_map('intval', (array) $_GET['category_ids'])))) : array();
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 50;
$offset = ($paged - 1) * $per_page;
$all_categories = $wpdb->get_results("SELECT id, name, color FROM $categories_table ORDER BY name");

$where = array('1=1');
if ($status_filter === 'active') {
    $where[] = 't.is_active = 1';
} elseif ($status_filter === 'inactive') {
    $where[] = 't.is_active = 0';
}

if (!empty($category_ids)) {
    $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
    $where[] = $wpdb->prepare(
        "EXISTS (SELECT 1 FROM $template_categories_table tcf WHERE tcf.template_id = t.id AND tcf.category_id IN ($placeholders))",
        $category_ids
    );
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

    <?php
    $template_notice_messages = array(
        'template_created',
        'template_updated',
        'template_deleted',
        'template_duplicated',
        'bulk_templates_deleted',
        'bulk_templates_activated',
        'bulk_templates_deactivated',
        'bulk_templates_none',
        'bulk_action_missing'
    );
    ?>
    <?php if (isset($_GET['message']) && in_array(sanitize_key($_GET['message']), $template_notice_messages, true)): ?>
        <?php
        $message = sanitize_key($_GET['message']);
        $processed = isset($_GET['processed']) ? max(0, intval($_GET['processed'])) : 0;
        $notice_class = in_array($message, array('bulk_action_missing', 'bulk_templates_none'), true) ? 'notice-warning' : 'notice-success';
        ?>
        <div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible">
            <p>
                <?php
                if ($message == 'template_created') {
                    _e('Template created successfully.', 'advnews-manager');
                } elseif ($message == 'template_updated') {
                    _e('Template updated successfully.', 'advnews-manager');
                } elseif ($message == 'template_deleted') {
                    _e('Template deleted successfully.', 'advnews-manager');
                } elseif ($message == 'template_duplicated') {
                    _e('Template duplicated successfully.', 'advnews-manager');
                } elseif ($message === 'bulk_templates_deleted') {
                    printf(
                        esc_html(_n('%s template deleted.', '%s templates deleted.', $processed, 'advnews-manager')),
                        esc_html(number_format_i18n($processed))
                    );
                } elseif ($message === 'bulk_templates_activated') {
                    printf(
                        esc_html(_n('%s template activated.', '%s templates activated.', $processed, 'advnews-manager')),
                        esc_html(number_format_i18n($processed))
                    );
                } elseif ($message === 'bulk_templates_deactivated') {
                    printf(
                        esc_html(_n('%s template deactivated.', '%s templates deactivated.', $processed, 'advnews-manager')),
                        esc_html(number_format_i18n($processed))
                    );
                } elseif ($message === 'bulk_templates_none') {
                    esc_html_e('Select at least one template before applying a bulk action.', 'advnews-manager');
                } elseif ($message === 'bulk_action_missing') {
                    esc_html_e('Select a bulk action before clicking Apply.', 'advnews-manager');
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
        <div class="advnews-multiselect" data-placeholder="<?php esc_attr_e('All Categories', 'advnews-manager'); ?>" data-selected-singular="<?php esc_attr_e('category selected', 'advnews-manager'); ?>" data-selected-plural="<?php esc_attr_e('categories selected', 'advnews-manager'); ?>">
            <button type="button" class="advnews-multiselect-toggle" aria-haspopup="listbox" aria-expanded="false">
                <span class="advnews-multiselect-label"><?php _e('All Categories', 'advnews-manager'); ?></span>
                <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
            </button>
            <div class="advnews-multiselect-menu" role="listbox" aria-multiselectable="true">
                <?php if (empty($all_categories)): ?>
                    <p class="advnews-multiselect-empty"><?php _e('No categories found.', 'advnews-manager'); ?></p>
                <?php else: ?>
                    <?php foreach ($all_categories as $category): ?>
                        <label class="advnews-multiselect-option">
                            <input type="checkbox" name="category_ids[]" value="<?php echo esc_attr($category->id); ?>" <?php checked(in_array(intval($category->id), $category_ids, true)); ?>>
                            <span class="advnews-multiselect-check" aria-hidden="true"></span>
                            <span class="advnews-category-swatch" style="background-color: <?php echo esc_attr($category->color); ?>;" aria-hidden="true"></span>
                            <span class="advnews-multiselect-text"><?php echo esc_html($category->name); ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search templates...', 'advnews-manager'); ?>">
        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'advnews-manager'); ?>">
        <?php if ($status_filter || !empty($category_ids) || $search): ?>
            <a href="<?php echo admin_url('admin.php?page=advnews-templates'); ?>" class="button">
                <?php _e('Clear Filters', 'advnews-manager'); ?>
            </a>
        <?php endif; ?>
    </form>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="advnews-templates-bulk-form">
        <input type="hidden" name="action" value="advnews_bulk_templates">
        <input type="hidden" name="selected_bulk_action" value="">
        <?php wp_nonce_field('advnews_bulk_templates'); ?>

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="advnews-templates-bulk-action-top" class="screen-reader-text"><?php _e('Select bulk action', 'advnews-manager'); ?></label>
                <select name="bulk_action" id="advnews-templates-bulk-action-top">
                    <option value=""><?php _e('Bulk Actions', 'advnews-manager'); ?></option>
                    <option value="delete"><?php _e('Delete', 'advnews-manager'); ?></option>
                    <option value="activate"><?php _e('Activate', 'advnews-manager'); ?></option>
                    <option value="deactivate"><?php _e('Deactivate', 'advnews-manager'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'advnews-manager'); ?>">
            </div>
            <?php if ($total > $per_page): ?>
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
            <?php endif; ?>
        </div>

    <table class="wp-list-table widefat fixed striped advnews-template-table">
        <thead>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" id="advnews-templates-select-all-top">
                </td>
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
                    <td colspan="7"><?php _e('No templates found.', 'advnews-manager'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="template_ids[]" value="<?php echo esc_attr($template->id); ?>">
                        </th>
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
        <tfoot>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" id="advnews-templates-select-all-bottom">
                </td>
                <th class="column-title"><?php _e('Title', 'advnews-manager'); ?></th>
                <th><?php _e('Subject', 'advnews-manager'); ?></th>
                <th><?php _e('Categories', 'advnews-manager'); ?></th>
                <th><?php _e('Status', 'advnews-manager'); ?></th>
                <th><?php _e('Used', 'advnews-manager'); ?></th>
                <th><?php _e('Updated', 'advnews-manager'); ?></th>
            </tr>
        </tfoot>
    </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="advnews-templates-bulk-action-bottom" class="screen-reader-text"><?php _e('Select bulk action', 'advnews-manager'); ?></label>
                <select name="bulk_action2" id="advnews-templates-bulk-action-bottom">
                    <option value=""><?php _e('Bulk Actions', 'advnews-manager'); ?></option>
                    <option value="delete"><?php _e('Delete', 'advnews-manager'); ?></option>
                    <option value="activate"><?php _e('Activate', 'advnews-manager'); ?></option>
                    <option value="deactivate"><?php _e('Deactivate', 'advnews-manager'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'advnews-manager'); ?>">
            </div>
            <?php if ($total > $per_page): ?>
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
            <?php endif; ?>
        </div>
    </form>
</div>

<style>
.advnews-template-filters {
    margin: 16px 0;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.advnews-multiselect {
    position: relative;
    width: 280px;
    max-width: 100%;
}
.advnews-multiselect-toggle {
    width: 100%;
    min-height: 36px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 0 10px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    background: #fff;
    color: #2c3338;
    cursor: pointer;
    text-align: left;
}
.advnews-multiselect-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.advnews-multiselect-menu {
    display: none;
    position: absolute;
    z-index: 1000;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 240px;
    overflow-y: auto;
    padding: 6px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    background: #fff;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
}
.advnews-multiselect.is-open .advnews-multiselect-menu {
    display: block;
}
.advnews-multiselect-option {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 30px;
    padding: 5px 6px;
    border-radius: 3px;
    cursor: pointer;
}
.advnews-multiselect-option:hover {
    background: #f0f6fc;
}
.advnews-multiselect-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.advnews-multiselect-check {
    width: 16px;
    height: 16px;
    border: 1px solid #8c8f94;
    border-radius: 3px;
    background: #fff;
    box-sizing: border-box;
    flex: 0 0 auto;
}
.advnews-multiselect-option input:checked + .advnews-multiselect-check {
    border-color: #2271b1;
    background: #2271b1;
}
.advnews-multiselect-option input:checked + .advnews-multiselect-check::after {
    content: "";
    display: block;
    width: 4px;
    height: 8px;
    margin: 1px 0 0 5px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
.advnews-category-swatch {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    flex: 0 0 auto;
}
.advnews-multiselect-text {
    line-height: 1.3;
}
.advnews-multiselect-empty {
    margin: 6px;
}
.advnews-template-table .template-title strong,
.advnews-template-table .template-subject {
    display: block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
#advnews-templates-bulk-form .column-cb {
    width: 2.2em;
}
#advnews-templates-bulk-form .tablenav {
    margin: 8px 0;
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
@media screen and (max-width: 782px) {
    .advnews-template-filters > *,
    .advnews-template-filters .advnews-multiselect {
        width: 100%;
    }
}
</style>
<script>
jQuery(document).ready(function($) {
    function updateAdvNewsMultiSelect($select) {
        var checked = $select.find('input[type="checkbox"]:checked:not(:disabled)');
        var label = $select.find('.advnews-multiselect-label');
        var placeholder = $select.data('placeholder') || '';
        var plural = $select.data('selected-plural') || 'selected';
        var names = checked.map(function() {
            return $.trim($(this).closest('.advnews-multiselect-option').find('.advnews-multiselect-text').first().text());
        }).get();

        if (!checked.length) {
            label.text(placeholder);
        } else if (checked.length === 1) {
            label.text(names[0]);
        } else {
            label.text(checked.length + ' ' + plural);
        }
    }

    $('.advnews-multiselect').each(function() {
        updateAdvNewsMultiSelect($(this));
    });

    $(document).on('click', '.advnews-multiselect-toggle', function(e) {
        e.preventDefault();
        var $select = $(this).closest('.advnews-multiselect');
        $('.advnews-multiselect').not($select).removeClass('is-open').find('.advnews-multiselect-toggle').attr('aria-expanded', 'false');
        $select.toggleClass('is-open');
        $(this).attr('aria-expanded', $select.hasClass('is-open') ? 'true' : 'false');
    });

    $(document).on('change', '.advnews-multiselect input[type="checkbox"]', function() {
        updateAdvNewsMultiSelect($(this).closest('.advnews-multiselect'));
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.advnews-multiselect').length) {
            $('.advnews-multiselect').removeClass('is-open').find('.advnews-multiselect-toggle').attr('aria-expanded', 'false');
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.advnews-multiselect').removeClass('is-open').find('.advnews-multiselect-toggle').attr('aria-expanded', 'false');
        }
    });

    function syncTemplateBulkSelectAll() {
        var $items = $('#advnews-templates-bulk-form tbody input[name="template_ids[]"]');
        var checkedCount = $items.filter(':checked').length;
        var allChecked = $items.length > 0 && checkedCount === $items.length;
        $('#advnews-templates-select-all-top, #advnews-templates-select-all-bottom').prop('checked', allChecked);
    }

    $('#advnews-templates-select-all-top, #advnews-templates-select-all-bottom').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('#advnews-templates-bulk-form tbody input[name="template_ids[]"]').prop('checked', isChecked);
        $('#advnews-templates-select-all-top, #advnews-templates-select-all-bottom').prop('checked', isChecked);
    });

    $(document).on('change', '#advnews-templates-bulk-form tbody input[name="template_ids[]"]', syncTemplateBulkSelectAll);

    $('#advnews-templates-bulk-form .bulkactions .action').on('click', function() {
        var selectedAction = $(this).closest('.bulkactions').find('select').val();
        $('#advnews-templates-bulk-form input[name="selected_bulk_action"]').val(selectedAction);
    });

    $('#advnews-templates-bulk-form').on('submit', function(e) {
        var $form = $(this);
        var selectedAction = $form.find('input[name="selected_bulk_action"]').val() || $form.find('select[name="bulk_action"]').val() || $form.find('select[name="bulk_action2"]').val();
        var selectedItems = $form.find('tbody input[name="template_ids[]"]:checked').length;

        if (!selectedAction) {
            alert('<?php esc_html_e('Please select a bulk action.', 'advnews-manager'); ?>');
            e.preventDefault();
            return false;
        }

        if (!selectedItems) {
            alert('<?php esc_html_e('Please select at least one template.', 'advnews-manager'); ?>');
            e.preventDefault();
            return false;
        }

        if (selectedAction === 'delete' && !confirm('<?php esc_html_e('Are you sure you want to delete the selected templates?', 'advnews-manager'); ?>')) {
            e.preventDefault();
            return false;
        }

        return true;
    });
});
</script>
