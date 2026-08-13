<?php
// admin/partials/campaigns-list.php
if (!defined('ABSPATH')) exit;

$campaign_class = new AdvNews_Campaign();

// Get filter parameters
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
$category_ids = isset($_GET['category_ids']) ? array_filter(array_map('intval', (array) $_GET['category_ids'])) : array();
if (empty($category_ids) && $category_id) {
    $category_ids = array($category_id);
}
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

$args = array(
    'status' => $status,
    'category_ids' => $category_ids,
    'search' => $search,
    'limit' => 20,
    'offset' => ($paged - 1) * 20
);

$campaigns = $campaign_class->get_campaigns($args);
$total = $campaign_class->count_campaigns($args);

// Get categories for filter
global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;
$categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}{$table_prefix}categories ORDER BY name");
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Campaigns', 'advnews-manager'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=advnews-campaigns&action=add'); ?>" class="page-title-action">
        <?php _e('Add New', 'advnews-manager'); ?>
    </a>
    <hr class="wp-header-end">

    <?php
    $campaign_notice_messages = array(
        'campaign_saved',
        'campaign_updated',
        'campaign_sent',
        'campaign_scheduled',
        'bulk_campaigns_deleted',
        'bulk_campaigns_none',
        'bulk_action_missing'
    );
    ?>
    <?php if (isset($_GET['message']) && in_array(sanitize_key($_GET['message']), $campaign_notice_messages, true)): ?>
        <?php
        $message = sanitize_key($_GET['message']);
        $processed = isset($_GET['processed']) ? max(0, intval($_GET['processed'])) : 0;
        $notice_class = in_array($message, array('bulk_action_missing', 'bulk_campaigns_none'), true) ? 'notice-warning' : 'notice-success';
        ?>
        <div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible">
            <p>
                <?php
                if ($message === 'campaign_saved') {
                    esc_html_e('Campaign saved successfully.', 'advnews-manager');
                } elseif ($message === 'campaign_updated') {
                    esc_html_e('Campaign updated successfully.', 'advnews-manager');
                } elseif ($message === 'campaign_sent') {
                    esc_html_e('Campaign queued for sending.', 'advnews-manager');
                } elseif ($message === 'campaign_scheduled') {
                    esc_html_e('Campaign scheduled successfully.', 'advnews-manager');
                } elseif ($message === 'bulk_campaigns_deleted') {
                    printf(
                        esc_html(_n('%s campaign deleted.', '%s campaigns deleted.', $processed, 'advnews-manager')),
                        esc_html(number_format_i18n($processed))
                    );
                } elseif ($message === 'bulk_campaigns_none') {
                    esc_html_e('Select at least one campaign before applying a bulk action.', 'advnews-manager');
                } elseif ($message === 'bulk_action_missing') {
                    esc_html_e('Select a bulk action before clicking Apply.', 'advnews-manager');
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="advnews-filters">
        <form method="get">
            <input type="hidden" name="page" value="advnews-campaigns">
            <select name="status">
                <option value=""><?php _e('All Statuses', 'advnews-manager'); ?></option>
                <option value="draft" <?php selected($status, 'draft'); ?>><?php _e('Draft', 'advnews-manager'); ?></option>
                <option value="scheduled" <?php selected($status, 'scheduled'); ?>><?php _e('Scheduled', 'advnews-manager'); ?></option>
                <option value="sending" <?php selected($status, 'sending'); ?>><?php _e('Sending', 'advnews-manager'); ?></option>
                <option value="paused" <?php selected($status, 'paused'); ?>><?php _e('Paused', 'advnews-manager'); ?></option>
                <option value="sent" <?php selected($status, 'sent'); ?>><?php _e('Sent', 'advnews-manager'); ?></option>
            </select>
            <div class="advnews-multiselect" data-placeholder="<?php esc_attr_e('All Categories', 'advnews-manager'); ?>" data-selected-singular="<?php esc_attr_e('category selected', 'advnews-manager'); ?>" data-selected-plural="<?php esc_attr_e('categories selected', 'advnews-manager'); ?>">
                <button type="button" class="advnews-multiselect-toggle" aria-haspopup="listbox" aria-expanded="false">
                    <span class="advnews-multiselect-label"><?php _e('All Categories', 'advnews-manager'); ?></span>
                    <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                </button>
                <div class="advnews-multiselect-menu" role="listbox" aria-multiselectable="true">
                    <?php if (empty($categories)): ?>
                        <p class="advnews-multiselect-empty"><?php _e('No categories found.', 'advnews-manager'); ?></p>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
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
            <input type="text" name="s" placeholder="<?php _e('Search campaigns...', 'advnews-manager'); ?>"
                   value="<?php echo esc_attr($search); ?>">
            <input type="submit" class="button" value="<?php _e('Filter', 'advnews-manager'); ?>">
            <?php if ($status || !empty($category_ids) || $search): ?>
                <a href="<?php echo admin_url('admin.php?page=advnews-campaigns'); ?>" class="button">
                    <?php _e('Clear Filters', 'advnews-manager'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div id="campaign-list-result" style="display:none; margin-bottom:20px;"></div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="advnews-campaigns-bulk-form">
        <input type="hidden" name="action" value="advnews_bulk_campaigns">
        <input type="hidden" name="selected_bulk_action" value="">
        <?php wp_nonce_field('advnews_bulk_campaigns'); ?>

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="advnews-campaigns-bulk-action-top" class="screen-reader-text"><?php _e('Select bulk action', 'advnews-manager'); ?></label>
                <select name="bulk_action" id="advnews-campaigns-bulk-action-top">
                    <option value=""><?php _e('Bulk Actions', 'advnews-manager'); ?></option>
                    <option value="delete"><?php _e('Delete', 'advnews-manager'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'advnews-manager'); ?>">
            </div>
            <?php if ($total > 20): ?>
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => ceil($total / 20),
                        'current' => $paged
                    ));
                    ?>
                </div>
            <?php endif; ?>
        </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" id="advnews-campaigns-select-all-top">
                </td>
                <th><?php _e('ID', 'advnews-manager'); ?></th>
                <th><?php _e('Name', 'advnews-manager'); ?></th>
                <th><?php _e('Status', 'advnews-manager'); ?></th>
                <th><?php _e('Categories', 'advnews-manager'); ?></th>
                <th><?php _e('Recipients', 'advnews-manager'); ?></th>
                <th><?php _e('Open Rate', 'advnews-manager'); ?></th>
                <th><?php _e('Click Rate', 'advnews-manager'); ?></th>
                <th><?php _e('Scheduled', 'advnews-manager'); ?></th>
                <th><?php _e('Actions', 'advnews-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($campaigns)): ?>
                <tr>
                    <td colspan="10"><?php _e('No campaigns found.', 'advnews-manager'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($campaigns as $campaign): ?>
                    <?php
                    // Fetch ALL categories for this campaign from the junction table
                    $campaign_cats = $wpdb->get_results($wpdb->prepare(
                        "SELECT c.name, c.color
                         FROM {$wpdb->prefix}{$table_prefix}campaign_categories cc
                         INNER JOIN {$wpdb->prefix}{$table_prefix}categories c ON cc.category_id = c.id
                         WHERE cc.campaign_id = %d",
                        $campaign->id
                    ));
                    ?>
                    <tr id="campaign-row-<?php echo esc_attr($campaign->id); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="campaign_ids[]" value="<?php echo esc_attr($campaign->id); ?>">
                        </th>
                        <td><?php echo esc_html($campaign->id); ?></td>
                        <td>
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=advnews-campaigns&action=edit&id=' . $campaign->id); ?>">
                                    <?php echo esc_html($campaign->name); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <span class="campaign-status status-<?php echo esc_attr($campaign->status); ?>">
                                <?php echo esc_html(ucfirst($campaign->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($campaign_cats)): ?>
                                <?php foreach ($campaign_cats as $cat): ?>
                                    <span class="category-badge" style="background-color: <?php echo esc_attr($cat->color); ?>; color: #fff; padding: 2px 8px; border-radius: 3px; display: inline-block; font-size: 11px; margin: 2px;">
                                        <?php echo esc_html($cat->name); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: #999;"><?php _e('Uncategorized', 'advnews-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong title="<?php
                            if ($campaign->status === 'draft') {
                                _e('Estimated count based on current active subscribers.', 'advnews-manager');
                            } else {
                                _e('Actual count of emails queued for sending.', 'advnews-manager');
                            }
                            ?>">
                                <?php echo esc_html($campaign->total_recipients ?? 0); ?>
                            </strong>
                            <?php if ($campaign->status === 'draft'): ?>
                                <span class="dashicons dashicons-info-outline" style="color:#646970; font-size:14px;" title="<?php _e('This is an estimate. The final count will be calculated when you send or schedule the campaign.', 'advnews-manager'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($campaign->open_rate ?? 0); ?>%</td>
                        <td><?php echo esc_html($campaign->click_rate ?? 0); ?>%</td>
                        <td>
                            <?php if ($campaign->scheduled_for): ?>
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($campaign->scheduled_for))); ?>
                            <?php elseif ($campaign->status === 'sent'): ?>
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($campaign->sent_at))); ?>
                            <?php else: ?>
                                <?php _e('Immediate', 'advnews-manager'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="<?php echo admin_url('admin.php?page=advnews-campaigns&action=edit&id=' . $campaign->id); ?>">
                                    <?php _e('Edit', 'advnews-manager'); ?>
                                </a> |
                                <a href="<?php echo admin_url('admin.php?page=advnews-campaigns&action=view&id=' . $campaign->id); ?>">
                                    <?php _e('Recipients', 'advnews-manager'); ?>
                                </a> |
                                <a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign->id); ?>">
                                    <?php _e('Stats', 'advnews-manager'); ?>
                                </a> |
                                <a href="#" class="duplicate-campaign-link" data-campaign-id="<?php echo esc_attr($campaign->id); ?>">
                                    <?php _e('Duplicate', 'advnews-manager'); ?>
                                </a> |
                                <a href="#" class="delete-campaign-link" data-campaign-id="<?php echo esc_attr($campaign->id); ?>" style="color:#d63638;">
                                    <?php _e('Delete', 'advnews-manager'); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" id="advnews-campaigns-select-all-bottom">
                </td>
                <th><?php _e('ID', 'advnews-manager'); ?></th>
                <th><?php _e('Name', 'advnews-manager'); ?></th>
                <th><?php _e('Status', 'advnews-manager'); ?></th>
                <th><?php _e('Categories', 'advnews-manager'); ?></th>
                <th><?php _e('Recipients', 'advnews-manager'); ?></th>
                <th><?php _e('Open Rate', 'advnews-manager'); ?></th>
                <th><?php _e('Click Rate', 'advnews-manager'); ?></th>
                <th><?php _e('Scheduled', 'advnews-manager'); ?></th>
                <th><?php _e('Actions', 'advnews-manager'); ?></th>
            </tr>
        </tfoot>
    </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="advnews-campaigns-bulk-action-bottom" class="screen-reader-text"><?php _e('Select bulk action', 'advnews-manager'); ?></label>
                <select name="bulk_action2" id="advnews-campaigns-bulk-action-bottom">
                    <option value=""><?php _e('Bulk Actions', 'advnews-manager'); ?></option>
                    <option value="delete"><?php _e('Delete', 'advnews-manager'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'advnews-manager'); ?>">
            </div>
            <?php if ($total > 20): ?>
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => ceil($total / 20),
                    'current' => $paged
                ));
                ?>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

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

    function syncCampaignBulkSelectAll() {
        var $items = $('#advnews-campaigns-bulk-form tbody input[name="campaign_ids[]"]');
        var checkedCount = $items.filter(':checked').length;
        var allChecked = $items.length > 0 && checkedCount === $items.length;
        $('#advnews-campaigns-select-all-top, #advnews-campaigns-select-all-bottom').prop('checked', allChecked);
    }

    $('#advnews-campaigns-select-all-top, #advnews-campaigns-select-all-bottom').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('#advnews-campaigns-bulk-form tbody input[name="campaign_ids[]"]').prop('checked', isChecked);
        $('#advnews-campaigns-select-all-top, #advnews-campaigns-select-all-bottom').prop('checked', isChecked);
    });

    $(document).on('change', '#advnews-campaigns-bulk-form tbody input[name="campaign_ids[]"]', syncCampaignBulkSelectAll);

    $('#advnews-campaigns-bulk-form .bulkactions .action').on('click', function() {
        var selectedAction = $(this).closest('.bulkactions').find('select').val();
        $('#advnews-campaigns-bulk-form input[name="selected_bulk_action"]').val(selectedAction);
    });

    $('#advnews-campaigns-bulk-form').on('submit', function(e) {
        var $form = $(this);
        var selectedAction = $form.find('input[name="selected_bulk_action"]').val() || $form.find('select[name="bulk_action"]').val() || $form.find('select[name="bulk_action2"]').val();
        var selectedItems = $form.find('tbody input[name="campaign_ids[]"]:checked').length;

        if (!selectedAction) {
            alert('<?php esc_html_e('Please select a bulk action.', 'advnews-manager'); ?>');
            e.preventDefault();
            return false;
        }

        if (!selectedItems) {
            alert('<?php esc_html_e('Please select at least one campaign.', 'advnews-manager'); ?>');
            e.preventDefault();
            return false;
        }

        if (selectedAction === 'delete' && !confirm('<?php esc_html_e('Are you sure you want to delete the selected campaigns?', 'advnews-manager'); ?>')) {
            e.preventDefault();
            return false;
        }

        return true;
    });

    // Duplicate campaign
    $('.duplicate-campaign-link').on('click', function(e) {
        e.preventDefault();
        var campaignId = $(this).data('campaign-id');
        if (confirm('<?php _e('Are you sure you want to duplicate this campaign?', 'advnews-manager'); ?>')) {
            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_duplicate_campaign',
                    campaign_id: campaignId,
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#campaign-list-result').addClass('updated').html('<p>' + response.data.message + '</p>').show();
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1500);
                    } else {
                        $('#campaign-list-result').addClass('error').html('<p>' + response.data.message + '</p>').show();
                    }
                },
                error: function() {
                    $('#campaign-list-result').addClass('error').html('<p><?php _e('An error occurred.', 'advnews-manager'); ?></p>').show();
                }
            });
        }
    });

    // Delete campaign - NOW VISIBLE FOR ALL STATUSES
    $('.delete-campaign-link').on('click', function(e) {
        e.preventDefault();
        var campaignId = $(this).data('campaign-id');
        if (confirm('<?php _e('Are you sure you want to delete this campaign? This action cannot be undone and will remove all analytics data.', 'advnews-manager'); ?>')) {
            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_delete_campaign',
                    campaign_id: campaignId,
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#campaign-list-result').addClass('updated').html('<p>' + response.data.message + '</p>').show();
                        $('#campaign-row-' + campaignId).fadeOut(500, function() {
                            $(this).remove();
                            syncCampaignBulkSelectAll();
                        });
                    } else {
                        $('#campaign-list-result').addClass('error').html('<p>' + response.data.message + '</p>').show();
                    }
                },
                error: function() {
                    $('#campaign-list-result').addClass('error').html('<p><?php _e('An error occurred.', 'advnews-manager'); ?></p>').show();
                }
            });
        }
    });
});
</script>

<style>
    #campaign-list-result.updated {
        background: #d4edda;
        border-left: 4px solid #00a32a;
        padding: 15px;
    }
    #campaign-list-result.error {
        background: #f8d7da;
        border-left: 4px solid #d63638;
        padding: 15px;
    }
    .row-actions {
        color: #ddd;
        font-size: 12px;
    }
    .row-actions a {
        text-decoration: none;
    }
    .row-actions a:hover {
        text-decoration: underline;
    }
    .delete-campaign-link {
        color: #d63638;
        font-weight: 600;
    }
    .delete-campaign-link:hover {
        color: #b32d2e;
        text-decoration: underline;
    }
    #advnews-campaigns-bulk-form .column-cb {
        width: 2.2em;
    }
    #advnews-campaigns-bulk-form .tablenav {
        margin: 8px 0;
    }
    .category-badge {
        display: inline-block;
        font-weight: 500;
    }
    .advnews-filters form {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
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
    @media screen and (max-width: 782px) {
        .advnews-filters form > *,
        .advnews-filters .advnews-multiselect {
            width: 100%;
        }
    }
</style>
