<?php
// admin/partials/email-logs-list.php
if (!defined('ABSPATH')) exit;

$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
$logs_per_page = (int) get_user_option('advnews_email_logs_per_page', get_current_user_id());
$logs_per_page = $logs_per_page > 0 ? min(500, $logs_per_page) : 20;

// Fetch campaigns for the dropdown
global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;
$all_campaigns = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}{$table_prefix}campaigns ORDER BY created_at DESC");
?>
<div class="wrap">
    <h1><?php _e('Email Delivery Logs', 'advnews-manager'); ?></h1>
    <p class="description"><?php _e('View the status of all emails sent or queued for your campaigns.', 'advnews-manager'); ?></p>

    <div class="advnews-filters" style="background: #f6f7f7; border: 1px solid #c3c4c7; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <form id="email-logs-filter-form" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- NEW: Campaign Filter Dropdown -->
            <select name="campaign_id" id="filter-campaign" style="height: 35px; min-width: 200px;">
                <option value=""><?php _e('All Campaigns', 'advnews-manager'); ?></option>
                <?php foreach ($all_campaigns as $camp): ?>
                    <option value="<?php echo esc_attr($camp->id); ?>" <?php selected($campaign_id, $camp->id); ?>>
                        <?php echo esc_html($camp->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status" id="filter-status" style="height: 35px;">
                <option value=""><?php _e('All Statuses', 'advnews-manager'); ?></option>
                <option value="queued" <?php selected($status_filter, 'queued'); ?>><?php _e('Queued (Waiting)', 'advnews-manager'); ?></option>
                <option value="sent" <?php selected($status_filter, 'sent'); ?>><?php _e('Sent (Processing)', 'advnews-manager'); ?></option>
                <option value="delivered" <?php selected($status_filter, 'delivered'); ?>><?php _e('Delivered', 'advnews-manager'); ?></option>
                <option value="opened" <?php selected($status_filter, 'opened'); ?>><?php _e('Opened', 'advnews-manager'); ?></option>
                <option value="clicked" <?php selected($status_filter, 'clicked'); ?>><?php _e('Clicked', 'advnews-manager'); ?></option>
                <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php _e('Failed', 'advnews-manager'); ?></option>
                <option value="bounced" <?php selected($status_filter, 'bounced'); ?>><?php _e('Bounced', 'advnews-manager'); ?></option>
            </select>

            <input type="text" name="s" id="filter-search" placeholder="<?php _e('Search email, name...', 'advnews-manager'); ?>" value="<?php echo esc_attr($search); ?>" style="height: 35px; min-width: 250px;">
            <label for="filter-per-page" style="font-weight: 600;"><?php _e('Logs per page', 'advnews-manager'); ?></label>
            <input type="number" name="per_page" id="filter-per-page" min="1" max="500" step="1" value="<?php echo esc_attr($logs_per_page); ?>" style="height: 35px; width: 110px;" aria-describedby="filter-per-page-help">
            <span id="filter-per-page-help" class="description"><?php _e('1-500', 'advnews-manager'); ?></span>
            <button type="submit" class="button button-primary"><?php _e('Filter', 'advnews-manager'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=advnews-email-logs'); ?>" class="button"><?php _e('Clear', 'advnews-manager'); ?></a>
        </form>
    </div>

    <div id="email-logs-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Status', 'advnews-manager'); ?></th>
                    <th><?php _e('Recipient', 'advnews-manager'); ?></th>
                    <th><?php _e('Campaign', 'advnews-manager'); ?></th>
                    <th><?php _e('Subject', 'advnews-manager'); ?></th>
                    <th><?php _e('Sent Date', 'advnews-manager'); ?></th>
                    <th><?php _e('Delivered Date', 'advnews-manager'); ?></th>
                    <th><?php _e('Opened Date', 'advnews-manager'); ?></th>
                    <th><?php _e('Clicked Date', 'advnews-manager'); ?></th>
                </tr>
            </thead>
            <tbody id="email-logs-body">
                <tr>
                    <td colspan="8" style="text-align:center; padding: 40px;">
                        <span class="spinner is-active"></span> <?php _e('Loading logs...', 'advnews-manager'); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="tablenav bottom" id="email-logs-pagination" style="display:none; margin-top: 10px;">
            <div class="tablenav-pages">
                <span class="displaying-num" id="logs-count-display"></span>
                <span class="paging-input">
                    <span id="current-page-display"></span>
                </span>
                <button class="button" id="prev-page" disabled>&laquo;</button>
                <button class="button" id="next-page">&raquo;</button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let currentPage = 1;
    let totalPages = 1;

    // Initialize filters from URL parameters (PHP rendered)
    let currentFilter = {
        status: '<?php echo esc_js($status_filter); ?>',
        search: '<?php echo esc_js($search); ?>',
        campaign_id: '<?php echo esc_js($campaign_id); ?>',
        per_page: <?php echo (int) $logs_per_page; ?>
    };

    function loadLogs(page) {
        const tbody = $('#email-logs-body');
        tbody.html('<tr><td colspan="8" style="text-align:center; padding: 40px;"><span class="spinner is-active"></span> Loading...</td></tr>');

        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_get_email_logs',
                nonce: advnews_ajax.nonce,
                paged: page,
                status: currentFilter.status,
                search: currentFilter.search,
                campaign_id: currentFilter.campaign_id,
                per_page: currentFilter.per_page
            },
            success: function(response) {
                if (response.success) {
                    renderTable(response.data.items);
                    updatePagination(response.data.page, response.data.total_pages, response.data.total);
                    currentPage = response.data.page;
                    totalPages = response.data.total_pages;
                } else {
                    tbody.html('<tr><td colspan="8" style="text-align:center; color:red;">' + (response.data.message || 'Error loading data') + '</td></tr>');
                }
            },
            error: function() {
                tbody.html('<tr><td colspan="8" style="text-align:center; color:red;">Error loading data.</td></tr>');
            }
        });
    }

    function renderTable(items) {
        const tbody = $('#email-logs-body');
        tbody.empty();

        if (items.length === 0) {
            tbody.html('<tr><td colspan="8" style="text-align:center;">' + (advnews_ajax.i18n?.no_data || 'No logs found.') + '</td></tr>');
            return;
        }

        items.forEach(item => {
            let statusClass = '';
            let statusLabel = item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : 'Unknown';

            switch(item.status) {
                case 'delivered':
                case 'opened':
                case 'clicked':
                    statusClass = 'style="color:#00a32a; font-weight:bold;"';
                    break;
                case 'failed':
                case 'bounced':
                    statusClass = 'style="color:#d63638; font-weight:bold;"';
                    break;
                case 'queued':
                    statusClass = 'style="color:#f0c33c; font-weight:bold;"';
                    break;
                default:
                    statusClass = 'style="color:#2271b1;"';
            }

            // Handle missing campaign data gracefully
            const campaignName = item.campaign_name ? item.campaign_name : '<span style="color:#999; font-style:italic;">Campaign Deleted</span>';
            const campaignLink = item.campaign_id ?
                `<a href="?page=advnews-campaigns&action=edit&id=${item.campaign_id}" target="_blank" rel="noopener noreferrer">${campaignName}</a>` :
                campaignName;

            // Handle missing subject data gracefully
            const subjectLine = item.campaign_subject ?
                `<small>${escHtml(item.campaign_subject)}</small>` :
                '<small style="color:#999; font-style:italic;">—</small>';

            const bounceMessage = item.bounce_message ?
                `<div style="color:#d63638; font-size:11px; margin-top:4px;">${escHtml(item.bounce_message)}</div>` : '';

            const recipientName = item.first_name || item.last_name ?
                `${item.first_name || ''} ${item.last_name || ''}`.trim() : '';

            // NEW: Add retry button for failed emails
            const retryButton = item.status === 'failed' ? `
                <button type="button" class="button button-small retry-email-btn"
                    data-log-id="${item.id}"
                    data-campaign-id="${item.campaign_id}"
                    style="margin-left:5px; font-size:10px; padding:2px 6px; background:#f0c33c; border-color:#f0c33c; color:#333;">
                    🔄 Retry
                </button>
            ` : '';

            const subscriberUrl = item.subscriber_id ? `?page=advnews-subscribers&action=view&id=${item.subscriber_id}` : '';
            const emailHtml = subscriberUrl ? `<a href="${subscriberUrl}" target="_blank" rel="noopener noreferrer"><strong>${escHtml(item.email)}</strong></a>` : `<strong>${escHtml(item.email)}</strong>`;
            const row = `
                <tr>
                    <td ${statusClass}>${statusLabel}${retryButton}${bounceMessage}</td>
                    <td>
                        ${emailHtml}<br>
                        <small style="color:#666;">${escHtml(recipientName)}</small>
                    </td>
                    <td>${campaignLink}</td>
                    <td>${subjectLine}</td>
                    <td><small>${escHtml(item.sent_at || '—')}</small></td>
                    <td><small>${escHtml(item.delivered_at || '—')}</small></td>
                    <td><small>${escHtml(item.opened_at || '—')}</small></td>
                    <td><small>${escHtml(item.clicked_at || '—')}</small></td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    // Helper to escape HTML to prevent XSS
    function escHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function updatePagination(page, total, count) {
        const nav = $('#email-logs-pagination');
        if (total > 0) {
            nav.show();
            $('#logs-count-display').text(`${count} items`);
            $('#current-page-display').text(`Page ${page} of ${total}`);
            $('#prev-page').prop('disabled', page <= 1);
            $('#next-page').prop('disabled', page >= total);
        } else {
            nav.hide();
        }
    }

    $('#email-logs-filter-form').on('submit', function(e) {
        e.preventDefault();
        currentFilter.status = $('#filter-status').val();
        currentFilter.search = $('#filter-search').val();
        currentFilter.campaign_id = $('#filter-campaign').val(); // Capture Campaign ID
        currentFilter.per_page = Math.max(1, Math.min(500, parseInt($('#filter-per-page').val(), 10) || 20));
        $('#filter-per-page').val(currentFilter.per_page);
        currentPage = 1;
        loadLogs(1);
    });

    $('#prev-page').on('click', function() {
        if (currentPage > 1) loadLogs(currentPage - 1);
    });

    $('#next-page').on('click', function() {
        if (currentPage < totalPages) loadLogs(currentPage + 1);
    });

    // NEW: Retry failed email handler
    $(document).on('click', '.retry-email-btn', function() {
        if (!confirm('<?php _e('Retry this failed email? The campaign status will be set to "sending" again.', 'advnews-manager'); ?>')) {
            return;
        }
        var button = $(this);
        var logId = button.data('log-id');
        var campaignId = button.data('campaign-id');
        var originalText = button.html();

        button.prop('disabled', true).html('⏳ Retrying...');

        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_retry_failed_email',
                nonce: advnews_ajax.nonce,
                log_id: logId,
                campaign_id: campaignId
            },
            success: function(response) {
                if (response.success) {
                    button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                    alert('<?php _e('Email queued for retry. Campaign status set to "sending".', 'advnews-manager'); ?>');
                } else {
                    alert(response.data.message || '<?php _e('Failed to retry email.', 'advnews-manager'); ?>');
                    button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('<?php _e('An error occurred.', 'advnews-manager'); ?>');
                button.prop('disabled', false).html(originalText);
            }
        });
    });

    // Initial Load
    loadLogs(1);
});
</script>

<style>
.wp-list-table td {
    vertical-align: top;
}
.wp-list-table small {
    color: #646970;
    display: block;
    margin-top: 2px;
}
.pagination-links button {
    margin: 0 2px;
}
.pagination-links button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.tablenav-pages {
    display: flex;
    align-items: center;
    gap: 5px;
}
.tablenav-pages .button {
    padding: 2px 8px;
    height: auto;
}
/* NEW: Retry button styles */
.retry-email-btn {
    background: #f0c33c;
    border-color: #f0c33c;
    color: #333;
    cursor: pointer;
    transition: all 0.2s;
}
.retry-email-btn:hover {
    background: #e5b830;
    border-color: #e5b830;
}
.retry-email-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
