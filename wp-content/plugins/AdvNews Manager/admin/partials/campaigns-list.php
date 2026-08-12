<?php
// admin/partials/campaigns-list.php
if (!defined('ABSPATH')) exit;

$campaign_class = new AdvNews_Campaign();

// Get filter parameters
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

$args = array(
    'status' => $status,
    'category_id' => $category_id,
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
            <select name="category_id">
                <option value=""><?php _e('All Categories', 'advnews-manager'); ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo esc_attr($category->id); ?>" <?php selected($category_id, $category->id); ?>>
                        <?php echo esc_html($category->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="s" placeholder="<?php _e('Search campaigns...', 'advnews-manager'); ?>"
                   value="<?php echo esc_attr($search); ?>">
            <input type="submit" class="button" value="<?php _e('Filter', 'advnews-manager'); ?>">
            <?php if ($status || $category_id || $search): ?>
                <a href="<?php echo admin_url('admin.php?page=advnews-campaigns'); ?>" class="button">
                    <?php _e('Clear Filters', 'advnews-manager'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div id="campaign-list-result" style="display:none; margin-bottom:20px;"></div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
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
                    <td colspan="9"><?php _e('No campaigns found.', 'advnews-manager'); ?></td>
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
    </table>

    <?php if ($total > 20): ?>
        <div class="tablenav">
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
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
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
    .category-badge {
        display: inline-block;
        font-weight: 500;
    }
</style>
