<?php
// admin/partials/campaigns-view.php
if (!defined('ABSPATH')) exit;

$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$campaign_class = new AdvNews_Campaign();
$tracking_class = new AdvNews_Tracking();
$campaign = $campaign_class->get_campaign($campaign_id);

if (!$campaign) {
    echo '<div class="notice notice-error"><p>' . esc_html__('Campaign not found.', 'advnews-manager') . '</p></div>';
    return;
}

$stats = $campaign_class->get_campaign_stats($campaign_id);
$analytics = $tracking_class->get_campaign_analytics($campaign_id);
$recipients = $campaign_class->get_campaign_recipients($campaign_id, '', 50, 0);

global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;
$campaign_categories = $wpdb->get_results($wpdb->prepare(
    "SELECT c.name, c.color
    FROM {$wpdb->prefix}{$table_prefix}campaign_categories cc
    INNER JOIN {$wpdb->prefix}{$table_prefix}categories c ON cc.category_id = c.id
    WHERE cc.campaign_id = %d
    ORDER BY c.name",
    $campaign_id
));
?>
<div class="wrap advnews-campaign-view">
    <h1 class="wp-heading-inline"><?php echo esc_html($campaign->name); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=advnews-campaigns&action=edit&id=' . $campaign_id); ?>" class="page-title-action">
        <?php _e('Edit Campaign', 'advnews-manager'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign_id); ?>" class="page-title-action">
        <?php _e('View Full Analytics', 'advnews-manager'); ?>
    </a>
    <hr class="wp-header-end">

    <div class="advnews-campaign-stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo esc_html($stats['total']); ?></div>
            <div class="stat-label"><?php _e('Total Recipients', 'advnews-manager'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo esc_html($stats['delivered']); ?></div>
            <div class="stat-label"><?php _e('Delivered', 'advnews-manager'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo esc_html($stats['open_rate']); ?>%</div>
            <div class="stat-label"><?php _e('Open Rate', 'advnews-manager'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo esc_html($stats['click_rate']); ?>%</div>
            <div class="stat-label"><?php _e('Click Rate', 'advnews-manager'); ?></div>
        </div>
    </div>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div id="post-body-content">
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Campaign Details', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Subject:', 'advnews-manager'); ?></th>
                                <td><?php echo esc_html($campaign->subject); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Categories:', 'advnews-manager'); ?></th>
                                <td>
                                    <?php if (!empty($campaign_categories)): ?>
                                        <?php foreach ($campaign_categories as $category): ?>
                                            <span class="category-badge" style="background-color: <?php echo esc_attr($category->color); ?>;">
                                                <?php echo esc_html($category->name); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="category-badge is-muted"><?php _e('Uncategorized', 'advnews-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Status:', 'advnews-manager'); ?></th>
                                <td>
                                    <span class="campaign-status status-<?php echo esc_attr($campaign->status); ?>">
                                        <?php echo esc_html(ucfirst($campaign->status)); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Created:', 'advnews-manager'); ?></th>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->created_at))); ?></td>
                            </tr>
                            <?php if ($campaign->scheduled_for): ?>
                                <tr>
                                    <th><?php _e('Scheduled For:', 'advnews-manager'); ?></th>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->scheduled_for))); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($campaign->sent_at): ?>
                                <tr>
                                    <th><?php _e('Sent At:', 'advnews-manager'); ?></th>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->sent_at))); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle"><?php _e('Recipients', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <table class="wp-list-table widefat fixed striped advnews-recipient-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Name', 'advnews-manager'); ?></th>
                                    <th><?php _e('Email', 'advnews-manager'); ?></th>
                                    <th><?php _e('Status', 'advnews-manager'); ?></th>
                                    <th><?php _e('Date Received', 'advnews-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recipients)): ?>
                                    <tr>
                                        <td colspan="4"><?php _e('No recipients have been queued for this campaign yet.', 'advnews-manager'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recipients as $recipient): ?>
                                        <?php
                                        $name = trim($recipient->first_name . ' ' . $recipient->last_name);
                                        $received_at = $recipient->delivered_at ?: ($recipient->sent_at ?: $recipient->created_at);
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($name ?: __('Subscriber', 'advnews-manager')); ?></td>
                                            <td><?php echo esc_html($recipient->email); ?></td>
                                            <td><?php echo esc_html(ucfirst($recipient->status)); ?></td>
                                            <td><?php echo $received_at ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($received_at))) : '&mdash;'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign_id); ?>" class="button button-small">
                                <?php _e('View Full Report', 'advnews-manager'); ?>
                            </a>
                        </p>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle"><?php _e('Email Preview', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <iframe id="campaign-preview" width="100%" height="500" style="border:1px solid #ddd;"></iframe>
                    </div>
                </div>
            </div>

            <div id="postbox-container-1" class="postbox-container">
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Quick Actions', 'advnews-manager'); ?></h2>
                    <div class="inside">
                        <div class="advnews-action-buttons">
                            <?php if (in_array($campaign->status, array('draft', 'sent'), true)): ?>
                                <button type="button" class="button button-primary button-large button-block" id="send-campaign-now">
                                    <?php echo $campaign->status === 'sent' ? esc_html__('Queue New Recipients', 'advnews-manager') : esc_html__('Send Now', 'advnews-manager'); ?>
                                </button>
                            <?php endif; ?>
                            <?php if (in_array($campaign->status, array('scheduled', 'sending'), true)): ?>
                                <button type="button" class="button button-primary button-large button-block" id="pause-campaign">
                                    <?php _e('Pause Campaign', 'advnews-manager'); ?>
                                </button>
                            <?php endif; ?>
                            <?php if ($campaign->status === 'paused'): ?>
                                <button type="button" class="button button-primary button-large button-block" id="resume-campaign">
                                    <?php _e('Resume Campaign', 'advnews-manager'); ?>
                                </button>
                            <?php endif; ?>
                            <?php if (in_array($campaign->status, array('scheduled', 'sending', 'paused'), true)): ?>
                                <button type="button" class="button button-large button-block" id="end-campaign">
                                    <?php _e('End Campaign', 'advnews-manager'); ?>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="button button-large button-block" id="duplicate-campaign" data-campaign-id="<?php echo esc_attr($campaign_id); ?>">
                                <?php _e('Duplicate Campaign', 'advnews-manager'); ?>
                            </button>
                            <button type="button" class="button button-link-delete button-large button-block" id="delete-campaign" data-campaign-id="<?php echo esc_attr($campaign_id); ?>">
                                <?php _e('Delete Campaign', 'advnews-manager'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (in_array($campaign->status, array('sent', 'sending', 'scheduled', 'paused'), true)): ?>
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Add Recipient', 'advnews-manager'); ?></h2>
                        <div class="inside">
                            <form id="add-recipient-form">
                                <p>
                                    <input type="email" id="recipient-email" class="widefat" placeholder="<?php esc_attr_e('subscriber@example.com', 'advnews-manager'); ?>" required>
                                </p>
                                <button type="submit" class="button button-secondary button-block">
                                    <?php _e('Add to Queue', 'advnews-manager'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="postbox">
                    <h2 class="hndle"><?php _e('Recipient Summary', 'advnews-manager'); ?></h2>
                    <div class="inside advnews-recipient-summary">
                        <p><strong><?php _e('Total:', 'advnews-manager'); ?></strong> <?php echo esc_html($stats['total']); ?></p>
                        <p><strong><?php _e('Opened:', 'advnews-manager'); ?></strong> <?php echo esc_html($stats['opened']); ?></p>
                        <p><strong><?php _e('Clicked:', 'advnews-manager'); ?></strong> <?php echo esc_html($stats['clicked']); ?></p>
                        <p><strong><?php _e('Bounced:', 'advnews-manager'); ?></strong> <?php echo esc_html($stats['bounced']); ?></p>
                        <p><strong><?php _e('Unsubscribed:', 'advnews-manager'); ?></strong> <?php echo esc_html($stats['unsubscribed']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="campaign-action-result" style="display:none; margin-top:20px;"></div>
</div>

<script>
jQuery(document).ready(function($) {
    var previewFrame = document.getElementById('campaign-preview');
    if (previewFrame) {
        var content = <?php echo wp_json_encode($campaign->content); ?>;
        previewFrame.contentDocument.open();
        previewFrame.contentDocument.write(content);
        previewFrame.contentDocument.close();
    }

    function showResult(type, message) {
        $('#campaign-action-result')
            .removeClass('updated error')
            .addClass(type)
            .html('<p>' + message + '</p>')
            .show();
    }

    $('#send-campaign-now').on('click', function() {
        if (!confirm('<?php _e('Queue this campaign now?', 'advnews-manager'); ?>')) {
            return;
        }
        $.post(advnews_ajax.ajax_url, {
            action: 'advnews_send_campaign',
            campaign_id: <?php echo $campaign_id; ?>,
            nonce: advnews_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });

    $('#pause-campaign').on('click', function() {
        if (!confirm('<?php _e('Pause this campaign?', 'advnews-manager'); ?>')) {
            return;
        }
        $.post(advnews_ajax.ajax_url, {
            action: 'advnews_pause_campaign',
            campaign_id: <?php echo $campaign_id; ?>,
            nonce: advnews_ajax.nonce
        }, function(response) {
            alert(response.data.message);
            if (response.success) {
                location.reload();
            }
        });
    });

    $('#resume-campaign').on('click', function() {
        if (!confirm('<?php _e('Resume this campaign?', 'advnews-manager'); ?>')) {
            return;
        }
        $.post(advnews_ajax.ajax_url, {
            action: 'advnews_resume_campaign',
            campaign_id: <?php echo $campaign_id; ?>,
            nonce: advnews_ajax.nonce
        }, function(response) {
            alert(response.data.message);
            if (response.success) {
                location.reload();
            }
        });
    });

    $('#end-campaign').on('click', function() {
        if (!confirm('<?php _e('End this campaign and cancel queued recipients?', 'advnews-manager'); ?>')) {
            return;
        }
        $.post(advnews_ajax.ajax_url, {
            action: 'advnews_end_campaign',
            campaign_id: <?php echo $campaign_id; ?>,
            nonce: advnews_ajax.nonce
        }, function(response) {
            alert(response.data.message);
            if (response.success) {
                location.reload();
            }
        });
    });

    $('#add-recipient-form').on('submit', function(e) {
        e.preventDefault();
        var email = $('#recipient-email').val();
        $.post(advnews_ajax.ajax_url, {
            action: 'advnews_add_campaign_recipient',
            campaign_id: <?php echo $campaign_id; ?>,
            email: email,
            nonce: advnews_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });

    $('#duplicate-campaign').on('click', function() {
        if (!confirm('<?php _e('Duplicate this campaign?', 'advnews-manager'); ?>')) {
            return;
        }
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Duplicating...', 'advnews-manager'); ?>');
        $.post(advnews_ajax.ajax_url, {
            action: 'advnews_duplicate_campaign',
            campaign_id: <?php echo $campaign_id; ?>,
            nonce: advnews_ajax.nonce
        }, function(response) {
            if (response.success) {
                showResult('updated', response.data.message);
                window.location.href = response.data.redirect_url;
            } else {
                showResult('error', response.data.message);
                button.prop('disabled', false).text('<?php _e('Duplicate Campaign', 'advnews-manager'); ?>');
            }
        });
    });

    $('#delete-campaign').on('click', function() {
        if (!confirm('<?php _e('Delete this campaign? This removes its analytics data.', 'advnews-manager'); ?>')) {
            return;
        }
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Deleting...', 'advnews-manager'); ?>');
        $.post(advnews_ajax.ajax_url, {
            action: 'advnews_delete_campaign',
            campaign_id: <?php echo $campaign_id; ?>,
            nonce: advnews_ajax.nonce
        }, function(response) {
            if (response.success) {
                showResult('updated', response.data.message);
                window.location.href = response.data.redirect_url;
            } else {
                showResult('error', response.data.message);
                button.prop('disabled', false).text('<?php _e('Delete Campaign', 'advnews-manager'); ?>');
            }
        });
    });
});
</script>

<style>
.advnews-campaign-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
    margin: 20px 0;
}
.stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}
.stat-value {
    font-size: 32px;
    font-weight: 600;
    color: #2271b1;
    line-height: 1;
    margin-bottom: 10px;
}
.stat-label {
    font-size: 14px;
    color: #646970;
}
.button-block {
    display: block;
    width: 100%;
    text-align: center;
    margin-bottom: 10px;
}
.button-block:last-child {
    margin-bottom: 0;
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
.advnews-recipient-table td,
.advnews-recipient-table th {
    vertical-align: middle;
}
.advnews-recipient-summary p {
    margin: 5px 0;
    padding: 5px 0;
    border-bottom: 1px solid #f0f0f0;
}
.advnews-recipient-summary p:last-child {
    border-bottom: none;
}
#campaign-action-result.updated {
    background: #d4edda;
    border-left: 4px solid #00a32a;
    padding: 15px;
}
#campaign-action-result.error {
    background: #f8d7da;
    border-left: 4px solid #d63638;
    padding: 15px;
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
@media (max-width: 782px) {
    .advnews-campaign-stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>
