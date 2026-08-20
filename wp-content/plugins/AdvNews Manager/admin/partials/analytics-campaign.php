<?php
// admin/partials/analytics-campaign.php
if (!defined('ABSPATH')) {
    exit;
}

$campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
$campaign_class = new AdvNews_Campaign();
$tracking_class = new AdvNews_Tracking();
$campaign = $campaign_class->get_campaign($campaign_id);

if (!$campaign) {
    echo '<div class="notice notice-error"><p>' . __('Campaign not found.', 'advnews-manager') . '</p></div>';
    return;
}

$analytics = $tracking_class->get_campaign_analytics($campaign_id);
$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;
$table_logs = $wpdb->prefix . $table_prefix . 'campaign_logs';
$table_subscribers = $wpdb->prefix . $table_prefix . 'subscribers';
$table_opens = $wpdb->prefix . $table_prefix . 'tracking_opens';
$table_clicks = $wpdb->prefix . $table_prefix . 'tracking_clicks';
$recipient_details = $wpdb->get_results($wpdb->prepare(
    "SELECT
        l.id AS log_id,
        l.subscriber_id,
        l.email,
        l.status,
        l.sent_at,
        l.delivered_at,
        l.opened_at,
        l.clicked_at,
        s.first_name,
        s.last_name,
        (SELECT COUNT(*) FROM $table_opens o WHERE o.campaign_log_id = l.id) AS open_count,
        (SELECT COUNT(*) FROM $table_clicks c WHERE c.campaign_log_id = l.id) AS click_count,
        COALESCE(
            (SELECT c.clicked_at FROM $table_clicks c WHERE c.campaign_log_id = l.id ORDER BY c.clicked_at DESC LIMIT 1),
            (SELECT o.opened_at FROM $table_opens o WHERE o.campaign_log_id = l.id ORDER BY o.opened_at DESC LIMIT 1),
            l.clicked_at,
            l.opened_at,
            l.delivered_at,
            l.sent_at,
            l.created_at
        ) AS latest_activity,
        COALESCE(
            (SELECT c.ip_address FROM $table_clicks c WHERE c.campaign_log_id = l.id ORDER BY c.clicked_at DESC LIMIT 1),
            (SELECT o.ip_address FROM $table_opens o WHERE o.campaign_log_id = l.id ORDER BY o.opened_at DESC LIMIT 1)
        ) AS latest_ip,
        COALESCE(
            (SELECT c.country FROM $table_clicks c WHERE c.campaign_log_id = l.id ORDER BY c.clicked_at DESC LIMIT 1),
            (SELECT o.country FROM $table_opens o WHERE o.campaign_log_id = l.id ORDER BY o.opened_at DESC LIMIT 1)
        ) AS latest_country,
        COALESCE(
            (SELECT c.city FROM $table_clicks c WHERE c.campaign_log_id = l.id ORDER BY c.clicked_at DESC LIMIT 1),
            (SELECT o.city FROM $table_opens o WHERE o.campaign_log_id = l.id ORDER BY o.opened_at DESC LIMIT 1)
        ) AS latest_city,
        COALESCE(
            (SELECT c.device_type FROM $table_clicks c WHERE c.campaign_log_id = l.id ORDER BY c.clicked_at DESC LIMIT 1),
            (SELECT o.device_type FROM $table_opens o WHERE o.campaign_log_id = l.id ORDER BY o.opened_at DESC LIMIT 1)
        ) AS latest_device,
        COALESCE(
            (SELECT c.browser FROM $table_clicks c WHERE c.campaign_log_id = l.id ORDER BY c.clicked_at DESC LIMIT 1),
            (SELECT o.browser FROM $table_opens o WHERE o.campaign_log_id = l.id ORDER BY o.opened_at DESC LIMIT 1)
        ) AS latest_browser,
        COALESCE(
            (SELECT c.platform FROM $table_clicks c WHERE c.campaign_log_id = l.id ORDER BY c.clicked_at DESC LIMIT 1),
            (SELECT o.platform FROM $table_opens o WHERE o.campaign_log_id = l.id ORDER BY o.opened_at DESC LIMIT 1)
        ) AS latest_platform
    FROM $table_logs l
    LEFT JOIN $table_subscribers s ON s.id = l.subscriber_id
    WHERE l.campaign_id = %d
    ORDER BY click_count DESC, open_count DESC, latest_activity DESC
    LIMIT 500",
    $campaign_id
));
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php printf(__('Campaign Analytics: %s', 'advnews-manager'), esc_html($campaign->name)); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=advnews-campaigns&action=edit&id=' . $campaign_id); ?>" class="page-title-action">
        <?php _e('Edit Campaign', 'advnews-manager'); ?>
    </a>
    <hr class="wp-header-end">

    <nav class="nav-tab-wrapper advnews-analytics-tabs">
        <a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign_id . '&tab=overview'); ?>" class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Overview', 'advnews-manager'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign_id . '&tab=recipients'); ?>" class="nav-tab <?php echo $tab === 'recipients' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Recipients', 'advnews-manager'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign_id . '&tab=geographic'); ?>" class="nav-tab <?php echo $tab === 'geographic' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Geographic', 'advnews-manager'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign_id . '&tab=devices'); ?>" class="nav-tab <?php echo $tab === 'devices' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Devices', 'advnews-manager'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign_id . '&tab=links'); ?>" class="nav-tab <?php echo $tab === 'links' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Links', 'advnews-manager'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign_id . '&tab=timeline'); ?>" class="nav-tab <?php echo $tab === 'timeline' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Timeline', 'advnews-manager'); ?>
        </a>
    </nav>

    <div class="advnews-analytics-content">
        <?php switch ($tab):
            case 'overview': ?>
                <div class="analytics-section">
                    <div class="analytics-summary-grid">
                        <div class="summary-card">
                            <h3><?php _e('Delivery Summary', 'advnews-manager'); ?></h3>
                            <div class="summary-stats">
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Total Sent:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($analytics['overview']['total_recipients'] ?? 0); ?></span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Delivered:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($analytics['overview']['delivered_count'] ?? 0); ?></span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Delivery Rate:', 'advnews-manager'); ?></span>
                                    <span class="stat-value">
                                        <?php
                                        $total_recipients = $analytics['overview']['total_recipients'] ?? 0;
                                        $delivered_count = $analytics['overview']['delivered_count'] ?? 0;
                                        echo $total_recipients > 0 ? esc_html(round(($delivered_count / $total_recipients) * 100, 2)) : '0';
                                        ?>%
                                    </span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Bounced:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($analytics['overview']['bounce_count'] ?? 0); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <h3><?php _e('Engagement Summary', 'advnews-manager'); ?></h3>
                            <div class="summary-stats">
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Unique Opens:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($analytics['overview']['open_count'] ?? 0); ?></span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Open Rate:', 'advnews-manager'); ?></span>
                                    <span class="stat-value">
                                        <?php
                                        $delivered = $analytics['overview']['delivered_count'] ?? 0;
                                        $open_count = $analytics['overview']['open_count'] ?? 0;
                                        echo $delivered > 0 ? esc_html(round(($open_count / $delivered) * 100, 2)) : '0';
                                        ?>%
                                    </span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Unique Clicks:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($analytics['overview']['click_count'] ?? 0); ?></span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Click Rate:', 'advnews-manager'); ?></span>
                                    <span class="stat-value">
                                        <?php
                                        echo $delivered > 0 ? esc_html(round(($analytics['overview']['click_count'] ?? 0) / $delivered * 100, 2)) : '0';
                                        ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <h3><?php _e('Geographic Reach', 'advnews-manager'); ?></h3>
                            <div class="summary-stats">
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Countries:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($analytics['geographic_summary']->total_countries ?? 0); ?></span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Cities:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($analytics['geographic_summary']->total_cities ?? 0); ?></span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Tracked Countries:', 'advnews-manager'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($analytics['geographic_summary']->tracked_countries ?? 0); ?></span>
                                </div>
                                <div class="stat-row">
                                    <span class="stat-label"><?php _e('Top Region:', 'advnews-manager'); ?></span>
                                    <span class="stat-value">
                                        <?php
                                        if (!empty($analytics['geographic_map'])) {
                                            echo esc_html($analytics['geographic_map'][0]->country);
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="chart-container">
                        <canvas id="performanceComparisonChart"></canvas>
                    </div>

                    <div class="mini-map-preview">
                        <h3><?php _e('Top Countries', 'advnews-manager'); ?></h3>
                        <div class="mini-country-list">
                            <?php
                            $top_countries = array_slice($analytics['geographic_map'] ?? [], 0, 5);
                            $total_global_opens = array_sum(array_column($analytics['geographic_map'] ?? [], 'opens'));
                            foreach ($top_countries as $country):
                                $percentage = $total_global_opens > 0 ? round(($country->opens / $total_global_opens) * 100, 1) : 0;
                            ?>
                            <div class="mini-country-item">
                                <?php if (!empty($country->country_code)): ?>
                                    <img src="https://flagcdn.com/24x18/<?php echo strtolower($country->country_code); ?>.png" alt="<?php echo esc_attr($country->country); ?>" class="country-flag-small">
                                <?php endif; ?>
                                <span class="country-name"><?php echo esc_html($country->country); ?></span>
                                <span class="country-opens"><?php echo esc_html(number_format($country->opens)); ?></span>
                                <span class="country-percent"><?php echo esc_html($percentage); ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="view-more">
                            <a href="<?php echo admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign_id . '&tab=geographic'); ?>">
                                <?php _e('View full geographic report →', 'advnews-manager'); ?>
                            </a>
                        </p>
                    </div>

                    <div class="export-actions">
                        <h3><?php _e('Export Data', 'advnews-manager'); ?></h3>
                        <button type="button" class="button" data-export="overview"><?php _e('Export Overview (CSV)', 'advnews-manager'); ?></button>
                        <button type="button" class="button" data-export="geographic"><?php _e('Export Geographic (CSV)', 'advnews-manager'); ?></button>
                        <button type="button" class="button" data-export="full"><?php _e('Export Full Report (CSV)', 'advnews-manager'); ?></button>
                    </div>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    var ctx = document.getElementById('performanceComparisonChart');
                    if (ctx) {
                        var delivered = <?php echo esc_js($analytics['overview']['delivered_count'] ?? 0); ?>;
                        var openCount = <?php echo esc_js($analytics['overview']['open_count'] ?? 0); ?>;
                        var clickCount = <?php echo esc_js($analytics['overview']['click_count'] ?? 0); ?>;
                        var bounceCount = <?php echo esc_js($analytics['overview']['bounce_count'] ?? 0); ?>;
                        var unsubscribeCount = <?php echo esc_js($analytics['overview']['unsubscribe_count'] ?? 0); ?>;
                        var totalRecipients = <?php echo esc_js($analytics['overview']['total_recipients'] ?? 0); ?>;

                        var actualOpenRate = delivered > 0 ? (openCount / delivered) * 100 : 0;
                        var actualClickRate = delivered > 0 ? (clickCount / delivered) * 100 : 0;
                        var actualBounceRate = totalRecipients > 0 ? (bounceCount / totalRecipients) * 100 : 0;
                        var actualUnsubscribeRate = delivered > 0 ? (unsubscribeCount / delivered) * 100 : 0;

                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: ['Open Rate', 'Click Rate', 'Bounce Rate', 'Unsubscribe Rate'],
                                datasets: [
                                    {
                                        label: 'This Campaign',
                                        data: [actualOpenRate, actualClickRate, actualBounceRate, actualUnsubscribeRate],
                                        backgroundColor: '#2271b1'
                                    },
                                    {
                                        label: 'Industry Average',
                                        data: [20, 2.5, 2, 0.2],
                                        backgroundColor: '#646970'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        max: 100,
                                        ticks: {
                                            callback: function(value) { return value + '%'; },
                                            stepSize: 10
                                        }
                                    }
                                },
                                plugins: {
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + '%';
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }
                });
                </script>
            <?php break; ?>

        <?php case 'recipients': ?>
            <div class="analytics-section">
                <h2><?php _e('Recipient Activity', 'advnews-manager'); ?></h2>
                <p class="description"><?php _e('Shows delivery and engagement for each campaign recipient. Email addresses link to the subscriber profile.', 'advnews-manager'); ?></p>
                <table class="wp-list-table widefat striped advnews-recipient-analytics-table">
                    <thead>
                        <tr>
                            <th><?php _e('Recipient', 'advnews-manager'); ?></th>
                            <th><?php _e('Status', 'advnews-manager'); ?></th>
                            <th><?php _e('Opens', 'advnews-manager'); ?></th>
                            <th><?php _e('Clicks', 'advnews-manager'); ?></th>
                            <th><?php _e('Latest Activity', 'advnews-manager'); ?></th>
                            <th><?php _e('IP', 'advnews-manager'); ?></th>
                            <th><?php _e('Location', 'advnews-manager'); ?></th>
                            <th><?php _e('Device', 'advnews-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recipient_details)): ?>
                            <tr>
                                <td colspan="8"><?php _e('No recipient records found for this campaign yet.', 'advnews-manager'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recipient_details as $recipient): ?>
                                <?php
                                $subscriber_url = admin_url('admin.php?page=advnews-subscribers&action=view&id=' . (int) $recipient->subscriber_id);
                                $recipient_name = trim($recipient->first_name . ' ' . $recipient->last_name);
                                $location = trim(implode(', ', array_filter(array($recipient->latest_city, $recipient->latest_country))));
                                $device = trim(implode(' / ', array_filter(array($recipient->latest_device, $recipient->latest_browser, $recipient->latest_platform))));
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url($subscriber_url); ?>"><?php echo esc_html($recipient->email); ?></a>
                                        <?php if ($recipient_name): ?>
                                            <br><span class="description"><?php echo esc_html($recipient_name); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html(ucfirst($recipient->status)); ?></td>
                                    <td><?php echo esc_html((int) $recipient->open_count); ?></td>
                                    <td><?php echo esc_html((int) $recipient->click_count); ?></td>
                                    <td><?php echo $recipient->latest_activity ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($recipient->latest_activity))) : '&mdash;'; ?></td>
                                    <td><?php echo $recipient->latest_ip ? esc_html($recipient->latest_ip) : '&mdash;'; ?></td>
                                    <td><?php echo $location ? esc_html($location) : '&mdash;'; ?></td>
                                    <td><?php echo $device ? esc_html($device) : '&mdash;'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php break; ?>

        <?php case 'geographic': ?>
            <div class="analytics-section">
                <div class="geographic-header">
                    <h2><?php _e('Geographic Distribution', 'advnews-manager'); ?></h2>
                    <div class="geographic-actions">
                        <button type="button" class="button" id="export-geographic"><?php _e('Export Country Data', 'advnews-manager'); ?></button>
                        <button type="button" class="button" id="export-cities"><?php _e('Export City Data', 'advnews-manager'); ?></button>
                        <button type="button" class="button" id="export-map"><?php _e('Export Map Data', 'advnews-manager'); ?></button>
                    </div>
                </div>

                <div class="geographic-summary-grid">
                    <div class="geo-summary-card">
                        <div class="geo-icon">🌍</div>
                        <div class="geo-content">
                            <div class="geo-value"><?php echo esc_html($analytics['geographic_summary']->total_countries ?? 0); ?></div>
                            <div class="geo-label"><?php _e('Countries Reached', 'advnews-manager'); ?></div>
                        </div>
                    </div>
                    <div class="geo-summary-card">
                        <div class="geo-icon">🏙️</div>
                        <div class="geo-content">
                            <div class="geo-value"><?php echo esc_html($analytics['geographic_summary']->total_cities ?? 0); ?></div>
                            <div class="geo-label"><?php _e('Cities Reached', 'advnews-manager'); ?></div>
                        </div>
                    </div>
                    <div class="geo-summary-card">
                        <div class="geo-icon">👥</div>
                        <div class="geo-content">
                            <div class="geo-value"><?php echo esc_html(array_sum(array_column($analytics['geographic_map'] ?? [], 'unique_visitors'))); ?></div>
                            <div class="geo-label"><?php _e('Unique Clickers', 'advnews-manager'); ?></div>
                        </div>
                    </div>
                    <div class="geo-summary-card">
                        <div class="geo-icon">📊</div>
                        <div class="geo-content">
                            <div class="geo-value"><?php echo esc_html(array_sum(array_column($analytics['geographic_map'] ?? [], 'opens'))); ?></div>
                            <div class="geo-label"><?php _e('Total Clicks', 'advnews-manager'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="map-container">
                    <h3><?php _e('Global Heatmap', 'advnews-manager'); ?></h3>
                    <div id="world-map" style="height: 400px; width: 100%; background: #f8f9fa; border-radius: 8px;"></div>
                </div>

                <div class="country-stats-section">
                    <h3><?php _e('Country Breakdown', 'advnews-manager'); ?></h3>
                    <div class="country-filters">
                        <input type="text" id="country-search" placeholder="<?php _e('Search country...', 'advnews-manager'); ?>" class="regular-text">
                        <select id="country-sort">
                        <option value="opens-desc"><?php _e('Sort by Clicks (High to Low)', 'advnews-manager'); ?></option>
                        <option value="opens-asc"><?php _e('Sort by Clicks (Low to High)', 'advnews-manager'); ?></option>
                            <option value="country"><?php _e('Sort by Country Name', 'advnews-manager'); ?></option>
                        </select>
                    </div>
                    <div class="country-grid" id="country-grid">
                        <?php
                        $total_global_opens = array_sum(array_column($analytics['geographic_map'] ?? [], 'opens'));
                        foreach ($analytics['geographic_map'] ?? [] as $country):
                            $percentage = $total_global_opens > 0 ? round(($country->opens / $total_global_opens) * 100, 1) : 0;
                        ?>
                        <div class="country-card" data-country="<?php echo esc_attr($country->country); ?>" data-opens="<?php echo esc_attr($country->opens); ?>">
                            <div class="country-flag">
                                <?php if (!empty($country->country_code)): ?>
                                    <img src="https://flagcdn.com/48x36/<?php echo strtolower($country->country_code); ?>.png" alt="<?php echo esc_attr($country->country); ?>" class="country-flag-img">
                                <?php else: ?>
                                    <div class="country-flag-placeholder">🌍</div>
                                <?php endif; ?>
                            </div>
                            <div class="country-info">
                                <h4><?php echo esc_html($country->country); ?></h4>
                                <div class="country-stats">
                                    <div class="stat" title="<?php _e('Clicks', 'advnews-manager'); ?>">
                                        <span class="stat-icon">👁️</span>
                                        <span class="stat-value"><?php echo esc_html(number_format($country->opens)); ?></span>
                                    </div>
                                    <div class="stat" title="<?php _e('Unique Clickers', 'advnews-manager'); ?>">
                                        <span class="stat-icon">👤</span>
                                        <span class="stat-value"><?php echo esc_html(number_format($country->unique_visitors)); ?></span>
                                    </div>
                                    <div class="stat" title="<?php _e('Cities', 'advnews-manager'); ?>">
                                        <span class="stat-icon">🏙️</span>
                                        <span class="stat-value"><?php echo esc_html($country->cities_count); ?></span>
                                    </div>
                                </div>
                                <div class="country-progress">
                                    <div class="progress-bar" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                    <span class="progress-label"><?php echo esc_html($percentage); ?>%</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($analytics['cities'])): ?>
                <div class="city-stats-section">
                    <h3><?php _e('Top Cities', 'advnews-manager'); ?></h3>
                    <table class="wp-list-table widefat fixed striped" id="city-table">
                        <thead>
                            <tr>
                                <th><?php _e('City', 'advnews-manager'); ?></th>
                                <th><?php _e('Country', 'advnews-manager'); ?></th>
                                <th><?php _e('Clicks', 'advnews-manager'); ?></th>
                                <th><?php _e('Unique Clickers', 'advnews-manager'); ?></th>
                                <th><?php _e('First Click', 'advnews-manager'); ?></th>
                                <th><?php _e('Last Click', 'advnews-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['cities'] as $city): ?>
                            <tr>
                                <td><strong><?php echo esc_html($city->city); ?></strong></td>
                                <td>
                                    <?php if (!empty($city->country_code)): ?>
                                        <img src="https://flagcdn.com/16x12/<?php echo strtolower($city->country_code); ?>.png" alt="<?php echo esc_attr($city->country); ?>" style="vertical-align: middle; margin-right: 5px;">
                                    <?php endif; ?>
                                    <?php echo esc_html($city->country); ?>
                                </td>
                                <td><?php echo esc_html(number_format($city->opens)); ?></td>
                                <td><?php echo esc_html($city->unique_visitors); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($city->first_open))); ?></td>
                                <td><?php echo esc_html(human_time_diff(strtotime($city->last_open), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="detailed-country-table">
                    <h3><?php _e('Detailed Country Report', 'advnews-manager'); ?></h3>
                    <table class="wp-list-table widefat fixed striped" id="country-table">
                        <thead>
                            <tr>
                                <th><?php _e('Country', 'advnews-manager'); ?></th>
                                <th><?php _e('Code', 'advnews-manager'); ?></th>
                                <th><?php _e('Clicks', 'advnews-manager'); ?></th>
                                <th><?php _e('Unique Clickers', 'advnews-manager'); ?></th>
                                <th><?php _e('Cities', 'advnews-manager'); ?></th>
                                <th><?php _e('Avg Hour', 'advnews-manager'); ?></th>
                                <th><?php _e('Days Active', 'advnews-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($analytics['geographic'])): ?>
                            <tr>
                                <td colspan="7"><?php _e('No click-based geographic data available.', 'advnews-manager'); ?></td>
                            </tr>
                            <?php else:
                                $current_country = '';
                                foreach ($analytics['geographic'] as $geo): ?>
                                <tr class="<?php echo $current_country !== $geo->country ? 'country-group' : 'city-row'; ?>">
                                    <?php if ($current_country !== $geo->country):
                                        $current_country = $geo->country;
                                    ?>
                                    <td rowspan="<?php
                                        $country_rows = array_filter($analytics['geographic'], function($item) use ($geo) {
                                            return $item->country === $geo->country;
                                        });
                                        echo count($country_rows);
                                    ?>">
                                        <strong><?php echo esc_html($geo->country); ?></strong>
                                    </td>
                                    <td rowspan="<?php echo count($country_rows); ?>">
                                        <?php if (!empty($geo->country_code)): ?>
                                            <img src="https://flagcdn.com/24x18/<?php echo strtolower($geo->country_code); ?>.png" alt="<?php echo esc_attr($geo->country); ?>" style="vertical-align: middle;">
                                            <?php echo esc_html($geo->country_code); ?>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td><?php echo esc_html($geo->city ?: __('(unknown city)', 'advnews-manager')); ?></td>
                                    <td><?php echo esc_html($geo->opens); ?></td>
                                    <td><?php echo esc_html($geo->unique_opens); ?></td>
                                    <td><?php echo esc_html($geo->days_active); ?></td>
                                    <td><?php echo $geo->avg_hour ? round($geo->avg_hour, 1) . ':00' : '—'; ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
            <script>
            jQuery(document).ready(function($) {
                var map = L.map('world-map').setView([20, 0], 2);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                var geoData = <?php echo json_encode($analytics['geographic_map'] ?? []); ?>;
                geoData.forEach(function(country) {
                    if (country.country_code) {
                        // Use the new combined function
                        var coords = getCountryCoords(country.country_code);
                        var lat = coords[0];
                        var lng = coords[1];

                        // Marker will now appear for any country in the list below
                        if (lat !== null && lng !== null) {
                            var circle = L.circleMarker([lat, lng], {
                                radius: Math.sqrt(country.opens) * 2,
                                color: '#2271b1',
                                fillColor: '#2271b1',
                                fillOpacity: 0.6
                            }).addTo(map);

                            circle.bindPopup(
                                '<strong>' + country.country + '</strong><br>' +
                                'Clicks: ' + country.opens + '<br>' +
                                'Clickers: ' + country.unique_visitors + '<br>' +
                                'Cities: ' + country.cities_count
                            );
                        }
                    }
                });

                // This single function replaces getCountryLat and getCountryLng
                function getCountryCoords(code) {
                    var c = code.toUpperCase();
                    var coords = {
                        "US":[39.8283,-98.5795], "CA":[56.1304,-106.3468], "GB":[55.3781,-3.4360], "DE":[51.1657,10.4515],
                        "FR":[46.6034,1.8883], "IT":[41.8719,12.5674], "ES":[40.4637,-3.7492], "AU":[-25.2744,133.7751],
                        "JP":[36.2048,138.2529], "CN":[35.8617,104.1954], "IN":[20.5937,78.9629], "BR":[-14.2350,-51.9253],
                        "MX":[23.6345,-102.5528], "ZA":[-30.5595,22.9375], "RU":[61.5240,105.3188], "AR":[-38.4161,-63.6167],
                        "EG":[26.8206,30.8025], "NG":[9.0820,8.6753], "KE":[-0.0236,37.9062], "SA":[23.8859,45.0792],
                        "AE":[23.4241,53.8478], "TR":[38.9637,35.2433], "SE":[60.1282,18.6435], "NO":[60.4720,8.4689],
                        "PL":[51.9194,19.1451], "NL":[52.1326,5.2913], "BE":[50.5039,4.4699], "CH":[46.8182,8.2275],
                        "AT":[47.5162,14.5501], "PT":[39.3999,-8.2245], "GR":[39.0742,21.8243], "IL":[31.0461,34.8516],
                        "TH":[15.8700,100.9925], "VN":[14.0583,108.2772], "ID":[-0.7893,113.9213], "PH":[12.8797,121.7740],
                        "MY":[4.2105,101.9758], "SG":[1.3521,103.8198], "KR":[35.9078,127.7669], "TW":[23.6978,120.9605],
                        "HK":[22.3964,114.1095], "NZ":[-40.9006,174.8860], "CO":[4.5709,-74.2973], "CL":[-35.6751,-71.5430],
                        "PE":[-9.1900,-75.0152], "VE":[6.4238,-66.5897], "UA":[48.3794,31.1656], "RO":[45.9432,24.9668],
                        "CZ":[49.8175,15.4730], "HU":[47.1625,19.5033], "BG":[42.7339,25.4858], "HR":[45.1,15.2],
                        "RS":[44.0165,21.0059], "SK":[48.6690,19.6990], "FI":[61.9241,25.7482], "DK":[56.2639,9.5018],
                        "IE":[53.1424,-7.6921], "IS":[64.9631,-19.0208], "PK":[30.3753,69.3451], "BD":[23.6850,90.3563],
                        "LK":[7.8731,80.7718], "NP":[28.3949,84.1240], "MM":[21.9162,95.9560], "KH":[12.5657,104.9910],
                        "LA":[19.8563,102.4955], "ET":[9.1450,40.4897], "GH":[7.9465,-1.0232], "CI":[7.5400,-5.5471],
                        "SN":[14.4974,-14.4524], "CM":[7.3697,12.3547], "UG":[1.3733,32.2903], "TZ":[-6.3690,34.8888],
                        "MZ":[-18.6657,35.5296], "AO":[-11.2027,17.8739], "CD":[-4.0383,21.7587], "MA":[31.7917,-7.0926],
                        "TN":[33.8869,9.5375], "DZ":[28.0339,1.6596], "LY":[26.3351,17.2283], "SD":[12.8628,30.2176],
                        "JO":[30.5852,36.2384], "LB":[33.8547,35.8623], "SY":[34.8021,38.9968], "IQ":[33.2232,43.6793],
                        "IR":[32.4279,53.6880], "AF":[33.9391,67.7100], "UZ":[41.3775,64.5853], "KZ":[48.0196,66.9237],
                        "TM":[38.9697,59.5563], "TJ":[38.8610,71.2761], "KG":[41.2044,74.7661], "MN":[46.8625,103.8467],
                        "GE":[42.3154,43.3569], "AM":[40.0691,45.0382], "AZ":[40.1431,47.5769], "CY":[35.1264,33.4299],
                        "QA":[25.3548,51.1839], "BH":[25.9304,50.6378], "OM":[21.5126,55.9233], "KW":[29.3117,47.4818],
                        "BO":[-16.2902,-63.5887], "EC":[-1.8312,-78.1834], "UY":[-32.5228,-55.7658], "PY":[-23.4425,-58.4438],
                        "CR":[9.7489,-83.7534], "PA":[8.5380,-80.7821], "NI":[12.8654,-85.2072], "HN":[15.2000,-86.2419],
                        "GT":[15.7835,-90.2308], "BZ":[17.1899,-88.4976], "SV":[13.7942,-88.8965], "DO":[18.7357,-70.1627],
                        "HT":[18.9712,-72.2852], "JM":[18.1096,-77.2975], "TT":[10.6918,-61.2225], "BB":[13.1939,-59.5432],
                        "CU":[21.5218,-77.7812], "PR":[18.2208,-66.5901]
                    };
                    return c in coords ? coords[c] : [20, 0]; // Returns [Lat, Lng] or fallback [20, 0]
                }

                $('#country-search').on('keyup', function() {
                    var searchTerm = $(this).val().toLowerCase();
                    $('.country-card').each(function() {
                        var country = $(this).data('country').toLowerCase();
                        $(this).toggle(country.indexOf(searchTerm) > -1);
                    });
                });

                $('#country-sort').on('change', function() {
                    var sortBy = $(this).val();
                    var cards = $('.country-card').get();
                    cards.sort(function(a, b) {
                        if (sortBy === 'country') {
                            var aVal = $(a).data('country').toLowerCase();
                            var bVal = $(b).data('country').toLowerCase();
                            return aVal.localeCompare(bVal);
                        } else {
                            var aVal = parseInt($(a).data('opens'));
                            var bVal = parseInt($(b).data('opens'));
                            return sortBy === 'opens-desc' ? bVal - aVal : aVal - bVal;
                        }
                    });
                    $('#country-grid').html(cards);
                });

                $('#export-geographic').on('click', function() {
                    window.location.href = '<?php echo admin_url('admin-ajax.php?action=advnews_export_analytics&campaign_id=' . $campaign_id . '&type=geographic&nonce=' . wp_create_nonce('advnews_export')); ?>';
                });
                $('#export-cities').on('click', function() {
                    window.location.href = '<?php echo admin_url('admin-ajax.php?action=advnews_export_analytics&campaign_id=' . $campaign_id . '&type=cities&nonce=' . wp_create_nonce('advnews_export')); ?>';
                });
                $('#export-map').on('click', function() {
                    window.location.href = '<?php echo admin_url('admin-ajax.php?action=advnews_export_analytics&campaign_id=' . $campaign_id . '&type=map&nonce=' . wp_create_nonce('advnews_export')); ?>';
                });
            });
            </script>
        <?php break; ?>

    <?php case 'devices': ?>
        <div class="analytics-section">
            <h2><?php _e('Device & Browser Analytics', 'advnews-manager'); ?></h2>
            <div class="device-charts-grid">
                <div class="chart-container">
                    <h3><?php _e('Device Types', 'advnews-manager'); ?></h3>
                    <canvas id="deviceChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3><?php _e('Browser Distribution', 'advnews-manager'); ?></h3>
                    <canvas id="browserChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3><?php _e('Platform Distribution', 'advnews-manager'); ?></h3>
                    <canvas id="platformChart"></canvas>
                </div>
            </div>

            <div class="device-table">
                <h3><?php _e('Detailed Device Data', 'advnews-manager'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Device Type', 'advnews-manager'); ?></th>
                            <th><?php _e('Browser', 'advnews-manager'); ?></th>
                            <th><?php _e('Platform', 'advnews-manager'); ?></th>
                            <th><?php _e('Clicks', 'advnews-manager'); ?></th>
                            <th><?php _e('Percentage', 'advnews-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $device_total = array_sum(array_column($analytics['devices'] ?? [], 'opens'));
                        if (empty($analytics['devices'])): ?>
                            <tr><td colspan="5"><?php _e('No click-based device data available.', 'advnews-manager'); ?></td></tr>
                        <?php else:
                            foreach ($analytics['devices'] as $device):
                                $percentage = $device_total > 0 ? round(($device->opens / $device_total) * 100, 2) : 0;
                        ?>
                        <tr>
                            <td><?php echo esc_html($device->device_type); ?></td>
                            <td><?php echo esc_html($device->browser); ?></td>
                            <td><?php echo esc_html($device->platform); ?></td>
                            <td><?php echo esc_html($device->opens); ?></td>
                            <td><?php echo esc_html($percentage); ?>%</td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var deviceCtx = document.getElementById('deviceChart');
            if (deviceCtx) {
                var deviceData = {};
                <?php foreach ($analytics['devices'] ?? [] as $device): ?>
                if (deviceData['<?php echo esc_js($device->device_type); ?>']) {
                    deviceData['<?php echo esc_js($device->device_type); ?>'] += <?php echo esc_js($device->opens); ?>;
                } else {
                    deviceData['<?php echo esc_js($device->device_type); ?>'] = <?php echo esc_js($device->opens); ?>;
                }
                <?php endforeach; ?>
                new Chart(deviceCtx, {
                    type: 'pie',
                    data: {
                        labels: Object.keys(deviceData),
                        datasets: [{
                            data: Object.values(deviceData),
                            backgroundColor: ['#2271b1', '#00a32a', '#d63638', '#f0c33c', '#646970']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }

            var browserCtx = document.getElementById('browserChart');
            if (browserCtx) {
                var browserData = {};
                <?php foreach ($analytics['devices'] ?? [] as $device): ?>
                if (browserData['<?php echo esc_js($device->browser); ?>']) {
                    browserData['<?php echo esc_js($device->browser); ?>'] += <?php echo esc_js($device->opens); ?>;
                } else {
                    browserData['<?php echo esc_js($device->browser); ?>'] = <?php echo esc_js($device->opens); ?>;
                }
                <?php endforeach; ?>
                new Chart(browserCtx, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(browserData),
                        datasets: [{
                            data: Object.values(browserData),
                            backgroundColor: ['#2271b1', '#00a32a', '#d63638', '#f0c33c', '#646970', '#f56e28']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }

            var platformCtx = document.getElementById('platformChart');
            if (platformCtx) {
                var platformData = {};
                <?php foreach ($analytics['devices'] ?? [] as $device): ?>
                if (platformData['<?php echo esc_js($device->platform); ?>']) {
                    platformData['<?php echo esc_js($device->platform); ?>'] += <?php echo esc_js($device->opens); ?>;
                } else {
                    platformData['<?php echo esc_js($device->platform); ?>'] = <?php echo esc_js($device->opens); ?>;
                }
                <?php endforeach; ?>
                new Chart(platformCtx, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(platformData),
                        datasets: [{
                        label: 'Clicks',
                            data: Object.values(platformData),
                            backgroundColor: '#2271b1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true } },
                        plugins: { legend: { display: false } }
                    }
                });
            }
        });
        </script>
    <?php break; ?>

    <?php case 'links': ?>
        <div class="analytics-section">
            <h2><?php _e('Link Performance', 'advnews-manager'); ?></h2>
            <div class="links-analytics">
                <div class="summary-card">
                    <h3><?php _e('Click Heatmap', 'advnews-manager'); ?></h3>
                    <div class="email-heatmap">
                        <?php
                        $max_clicks = !empty($analytics['links']) ? max(array_column($analytics['links'], 'click_count')) : 1;
                        $links = $analytics['links'] ?? array();
                        ?>
                        <?php if (empty($links)): ?>
                            <p><?php _e('No tracked links were found for this campaign.', 'advnews-manager'); ?></p>
                        <?php else: ?>
                            <ul class="advnews-link-heatmap-list">
                                <?php foreach ($links as $link): ?>
                                    <?php
                                    $clicks = (int) $link->click_count;
                                    $intensity = $max_clicks > 0 ? $clicks / $max_clicks : 0;
                                    $color = sprintf('rgba(34, 113, 177, %.2f)', max(0.08, min(0.9, $intensity)));
                                    ?>
                                    <li style="background-color: <?php echo esc_attr($color); ?>;">
                                        <a href="<?php echo esc_url($link->original_url); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($link->original_url); ?>
                                        </a>
                                        <span><?php echo esc_html(sprintf(_n('%d click', '%d clicks', $clicks, 'advnews-manager'), $clicks)); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="summary-card">
                    <h3><?php _e('Link Details', 'advnews-manager'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('URL', 'advnews-manager'); ?></th>
                                <th><?php _e('Clicks', 'advnews-manager'); ?></th>
                                <th><?php _e('Unique Clicks', 'advnews-manager'); ?></th>
                                <th><?php _e('Unique Clickers', 'advnews-manager'); ?></th>
                                <th><?php _e('CTR', 'advnews-manager'); ?></th>
                                <th><?php _e('Countries', 'advnews-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $delivered = $analytics['overview']['delivered_count'] ?? 1;
                            foreach ($analytics['links'] ?? [] as $link):
                                $ctr = $delivered > 0 ? round(($link->unique_click_count / $delivered) * 100, 2) : 0;
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($link->original_url); ?>" target="_blank" title="<?php echo esc_attr($link->original_url); ?>">
                                        <?php echo esc_html(wp_trim_words($link->original_url, 8, '...')); ?>
                                    </a>
                                </td>
                                <td><strong><?php echo esc_html($link->click_count); ?></strong></td>
                                <td><?php echo esc_html($link->unique_click_count); ?></td>
                                <td><?php echo esc_html($link->clickers); ?></td>
                                <td><?php echo esc_html($ctr); ?>%</td>
                                <td>
                                    <?php if ($link->countries_reached): ?>
                                        <span title="<?php printf(__('Reached %d countries', 'advnews-manager'), $link->countries_reached); ?>">
                                            🌍 <?php echo esc_html($link->countries_reached); ?>
                                        </span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php break; ?>

    <?php case 'timeline': ?>
        <div class="analytics-section">
            <h2><?php _e('Engagement Timeline', 'advnews-manager'); ?></h2>
            <div class="timeline-filters">
                <select id="timeline-interval">
                    <option value="hour"><?php _e('Hourly', 'advnews-manager'); ?></option>
                    <option value="day"><?php _e('Daily', 'advnews-manager'); ?></option>
                </select>
            </div>
            <div class="chart-container">
                <canvas id="timelineChart"></canvas>
            </div>
            <div class="timeline-stats">
                <h3><?php _e('Best Performing Times', 'advnews-manager'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Hour', 'advnews-manager'); ?></th>
                            <th><?php _e('Opens', 'advnews-manager'); ?></th>
                            <th><?php _e('Clicks', 'advnews-manager'); ?></th>
                            <th><?php _e('Countries', 'advnews-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hourly_stats = array_fill(0, 24, ['opens' => 0, 'clicks' => 0, 'countries' => 0]);
                        foreach ($analytics['timeline'] ?? [] as $event) {
                            $hour = intval($event->hour);
                            $hourly_stats[$hour]['opens'] += $event->opens;
                            $hourly_stats[$hour]['countries'] += $event->countries;
                        }
                        $best_hours = array_filter($hourly_stats, function($stat) {
                            return $stat['opens'] > 0;
                        });
                        arsort($best_hours);
                        $top_hours = array_slice($best_hours, 0, 5, true);
                        foreach ($top_hours as $hour => $stats): ?>
                        <tr>
                            <td><strong><?php printf('%02d:00 - %02d:00', $hour, ($hour + 1) % 24); ?></strong></td>
                            <td><?php echo esc_html($stats['opens']); ?></td>
                            <td><?php echo esc_html($stats['clicks']); ?></td>
                            <td><?php echo esc_html($stats['countries']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var ctx = document.getElementById('timelineChart');
            if (ctx) {
                var labels = <?php echo json_encode(array_map(function($item) {
                    return $item->date . ' ' . $item->hour . ':00';
                }, $analytics['timeline'] ?? [])); ?>;
                var openData = <?php echo json_encode(array_column($analytics['timeline'] ?? [], 'opens')); ?>;

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Opens',
                            data: openData,
                            borderColor: '#2271b1',
                            backgroundColor: 'rgba(34, 113, 177, 0.1)',
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { ticks: { maxTicksLimit: 10 } },
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
        });
        </script>
    <?php break; ?>
    <?php endswitch; ?>
</div>
</div>

<style>
.advnews-analytics-tabs {
    margin-bottom: 20px;
}
.advnews-analytics-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}
.analytics-section {
    margin-bottom: 30px;
}
.analytics-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}
.summary-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
}
.summary-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 14px;
    color: #1d2327;
}
.summary-stats {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}
.stat-row:last-child {
    border-bottom: none;
}
.stat-label {
    font-size: 13px;
    color: #646970;
}
.stat-value {
    font-weight: 600;
    color: #2271b1;
}
.chart-container {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    height: 300px;
    max-height: 300px;
    overflow: hidden;
}
.chart-container canvas {
    max-width: 100% !important;
    max-height: 100% !important;
}
.mini-map-preview {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}
.mini-map-preview h3 {
    margin-top: 0;
    margin-bottom: 15px;
}
.mini-country-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 15px;
}
.mini-country-item {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #e9ecef;
}
.country-flag-small {
    width: 24px;
    height: 18px;
    border-radius: 2px;
}
.country-name {
    font-weight: 500;
}
.country-opens {
    color: #666;
    font-size: 13px;
}
.country-percent {
    color: #2271b1;
    font-weight: 600;
    font-size: 13px;
}
.export-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}
.export-actions h3 {
    margin-top: 0;
    margin-bottom: 15px;
}
.export-actions .button {
    margin-right: 10px;
}
.geographic-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.geographic-actions {
    display: flex;
    gap: 10px;
}
.geographic-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}
.geo-summary-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.geo-icon {
    font-size: 36px;
    width: 60px;
    height: 60px;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.geo-content {
    flex: 1;
}
.geo-value {
    font-size: 28px;
    font-weight: 600;
    color: #2271b1;
    line-height: 1.2;
}
.geo-label {
    color: #666;
    font-size: 13px;
}
.map-container {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}
.map-container h3 {
    margin-top: 0;
    margin-bottom: 15px;
}
.country-stats-section {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}
.country-filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}
.country-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}
.country-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    gap: 15px;
}
.country-flag {
    width: 60px;
    height: 45px;
    overflow: hidden;
    border-radius: 4px;
}
.country-flag-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.country-flag-placeholder {
    width: 100%;
    height: 100%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.country-info {
    flex: 1;
}
.country-info h4 {
    margin: 0 0 10px;
    font-size: 16px;
}
.country-stats {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
}
.stat {
    display: flex;
    align-items: center;
    gap: 5px;
}
.stat-icon {
    font-size: 14px;
}
.stat-value {
    font-weight: 600;
    color: #2271b1;
}
.country-progress {
    background: #e9ecef;
    height: 6px;
    border-radius: 3px;
    position: relative;
    margin-top: 10px;
}
.progress-bar {
    height: 100%;
    background: #2271b1;
    border-radius: 3px;
}
.progress-label {
    position: absolute;
    right: 0;
    top: -20px;
    font-size: 11px;
    color: #666;
}
.city-stats-section,
.detailed-country-table {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-top: 30px;
}
.city-stats-section h3,
.detailed-country-table h3 {
    margin-top: 0;
    margin-bottom: 20px;
}
.country-group td:first-child {
    background: #f0f7ff;
}
.device-charts-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}
.device-charts-grid .chart-container {
    height: 320px;
    max-height: none;
    overflow: visible;
}
.device-table {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
}
.device-table h3 {
    margin-top: 0;
    margin-bottom: 20px;
}
.links-analytics {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.links-analytics .summary-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
}
.links-analytics h3 {
    margin-top: 0;
    margin-bottom: 15px;
}
.email-heatmap {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 15px;
}
.advnews-link-heatmap-list {
    margin: 0;
}
.advnews-link-heatmap-list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0 0 8px;
    padding: 10px 12px;
    border-radius: 4px;
    word-break: break-word;
}
.advnews-link-heatmap-list a {
    color: #0a4b78;
    font-weight: 600;
}
.advnews-link-heatmap-list span {
    flex: 0 0 auto;
    color: #1d2327;
    font-size: 12px;
    font-weight: 600;
}
.timeline-filters {
    margin-bottom: 20px;
}
.timeline-filters select {
    height: 35px;
    min-width: 150px;
}
.timeline-stats {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-top: 30px;
}
.timeline-stats h3 {
    margin-top: 0;
    margin-bottom: 20px;
}
@media (max-width: 1200px) {
    .analytics-summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .geographic-summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .device-charts-grid {
        grid-template-columns: 1fr;
    }
    .links-analytics {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 782px) {
    .analytics-summary-grid {
        grid-template-columns: 1fr;
    }
    .geographic-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    .geographic-actions {
        flex-wrap: wrap;
    }
    .geographic-summary-grid {
        grid-template-columns: 1fr;
    }
    .country-filters {
        flex-direction: column;
    }
    .country-grid {
        grid-template-columns: 1fr;
    }
    .chart-container {
        height: 250px;
    }
}
</style>
