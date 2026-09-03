<?php
// admin/partials/analytics-overview.php
if (!defined('ABSPATH')) exit;

$tracking_class = new AdvNews_Tracking();
$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '30days';
$analytics = $tracking_class->get_system_analytics($period);

// Get campaign counts - FIXED: Count ALL campaigns, not just sent
$campaign_class = new AdvNews_Campaign();
$total_campaigns = $campaign_class->count_campaigns(); // ALL campaigns
$sent_campaigns = $campaign_class->count_campaigns(array('status' => 'sent'));
$draft_campaigns = $campaign_class->count_campaigns(array('status' => 'draft'));
$scheduled_campaigns = $campaign_class->count_campaigns(array('status' => 'scheduled'));

// Get top campaigns
$top_campaigns = $campaign_class->get_campaigns(array(
    'status' => 'sent',
    'limit' => 5,
    'orderby' => 'open_rate',
    'order' => 'DESC'
));

// Get geographic and device data
global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;

// Set date range for queries
$end_date = current_time('mysql');
switch ($period) {
    case '7days':
        $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        break;
    case '30days':
        $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        break;
    case '90days':
        $start_date = date('Y-m-d H:i:s', strtotime('-90 days'));
        break;
    case 'year':
        $start_date = date('Y-m-d H:i:s', strtotime('-1 year'));
        break;
    default:
        $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
}

$ip_search = isset($_GET['ip_search']) ? sanitize_text_field(wp_unslash($_GET['ip_search'])) : '';
$ip_address = isset($_GET['ip_address']) ? sanitize_text_field(wp_unslash($_GET['ip_address'])) : '';
$ip_campaign = isset($_GET['ip_campaign']) ? absint($_GET['ip_campaign']) : 0;
$ip_country = isset($_GET['ip_country']) ? sanitize_text_field(wp_unslash($_GET['ip_country'])) : '';
$ip_city = isset($_GET['ip_city']) ? sanitize_text_field(wp_unslash($_GET['ip_city'])) : '';
$geo_country = isset($_GET['geo_country']) ? sanitize_text_field(wp_unslash($_GET['geo_country'])) : '';
$geo_city = isset($_GET['geo_city']) ? sanitize_text_field(wp_unslash($_GET['geo_city'])) : '';
$geo_page = isset($_GET['geo_page']) ? max(1, absint($_GET['geo_page'])) : 1;
$geo_per_page = 50;

// Top Countries from clicks. Open-pixel locations can be email proxy locations.
$top_countries = $wpdb->get_results($wpdb->prepare(
    "SELECT country, country_code, COUNT(*) as opens, COUNT(DISTINCT subscriber_id) as unique_visitors
    FROM {$wpdb->prefix}{$table_prefix}tracking_clicks
    WHERE clicked_at BETWEEN %s AND %s
    AND country != '' AND country != 'Local' AND country != 'Unknown'
    GROUP BY country, country_code
    ORDER BY opens DESC
    LIMIT 10",
    $start_date,
    $end_date
));

// Top Cities from clicks. Open-pixel locations can be email proxy locations.
$top_cities = $wpdb->get_results($wpdb->prepare(
    "SELECT city, country, country_code, COUNT(*) as opens
    FROM {$wpdb->prefix}{$table_prefix}tracking_clicks
    WHERE clicked_at BETWEEN %s AND %s
    AND city != '' AND city != 'Local' AND city != 'Unknown'
    GROUP BY city, country, country_code
    ORDER BY opens DESC
    LIMIT 10",
    $start_date,
    $end_date
));

// Device data from clicks. Open-pixel user agents are often email proxy/scanner agents.
$device_data = $wpdb->get_results($wpdb->prepare(
    "SELECT device_type, browser, platform, COUNT(*) as opens
    FROM {$wpdb->prefix}{$table_prefix}tracking_clicks
    WHERE clicked_at BETWEEN %s AND %s
    AND device_type != ''
    GROUP BY device_type, browser, platform
    ORDER BY opens DESC
    LIMIT 20",
    $start_date,
    $end_date
));

$table_clicks = $wpdb->prefix . $table_prefix . 'tracking_clicks';
$table_subscribers = $wpdb->prefix . $table_prefix . 'subscribers';
$table_campaigns = $wpdb->prefix . $table_prefix . 'campaigns';

$ip_where = array('tc.clicked_at BETWEEN %s AND %s', "tc.ip_address != ''");
$ip_params = array($start_date, $end_date);
if ($ip_search !== '') {
    $like = '%' . $wpdb->esc_like($ip_search) . '%';
    $ip_where[] = '(tc.ip_address LIKE %s OR s.email LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s OR c.name LIKE %s)';
    array_push($ip_params, $like, $like, $like, $like, $like);
}
if ($ip_address !== '') {
    $ip_where[] = 'tc.ip_address = %s';
    $ip_params[] = $ip_address;
}
if ($ip_campaign > 0) {
    $ip_where[] = 'tc.campaign_id = %d';
    $ip_params[] = $ip_campaign;
}
if ($ip_country !== '') {
    $ip_where[] = 'tc.country = %s';
    $ip_params[] = $ip_country;
}
if ($ip_city !== '') {
    $ip_where[] = 'tc.city = %s';
    $ip_params[] = $ip_city;
}
$ip_where_sql = implode(' AND ', $ip_where);

// IP Address Data from clicks for a closer recipient signal than open-pixel proxy IPs.
$ip_query_params = array_merge($ip_params, array(50));
$ip_data = $wpdb->get_results($wpdb->prepare(
    "SELECT tc.ip_address, tc.subscriber_id, tc.country, tc.country_code, tc.city,
            tc.device_type, tc.browser, tc.platform, tc.clicked_at AS event_at,
            tc.campaign_id, s.email AS subscriber_email, c.name AS campaign_name
     FROM {$table_clicks} tc
     LEFT JOIN {$table_subscribers} s ON tc.subscriber_id = s.id
     LEFT JOIN {$table_campaigns} c ON tc.campaign_id = c.id
     WHERE {$ip_where_sql}
     ORDER BY tc.clicked_at DESC
     LIMIT %d",
    $ip_query_params
));

$ip_total = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*)
     FROM {$table_clicks} tc
     LEFT JOIN {$table_subscribers} s ON tc.subscriber_id = s.id
     LEFT JOIN {$table_campaigns} c ON tc.campaign_id = c.id
     WHERE {$ip_where_sql}",
    $ip_params
));

$ip_filter_countries = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT country FROM {$table_clicks}
     WHERE clicked_at BETWEEN %s AND %s
       AND country != '' AND country != 'Local' AND country != 'Unknown'
     ORDER BY country",
    $start_date,
    $end_date
));
$ip_filter_cities = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT city FROM {$table_clicks}
     WHERE clicked_at BETWEEN %s AND %s
       AND city != '' AND city != 'Local' AND city != 'Unknown'
     ORDER BY city",
    $start_date,
    $end_date
));
$ip_filter_campaigns = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT c.id, c.name
     FROM {$table_clicks} tc
     INNER JOIN {$table_campaigns} c ON tc.campaign_id = c.id
     WHERE tc.clicked_at BETWEEN %s AND %s
     ORDER BY c.name",
    $start_date,
    $end_date
));

$ip_filter_addresses = $wpdb->get_col($wpdb->prepare(
    "SELECT ip_address
     FROM {$table_clicks}
     WHERE clicked_at BETWEEN %s AND %s
       AND ip_address != ''
     GROUP BY ip_address
     ORDER BY MAX(clicked_at) DESC
     LIMIT 500",
    $start_date,
    $end_date
));

$geo_recipients = array();
$geo_total = 0;
$geo_pages = 0;
if ($geo_country !== '') {
    $geo_where = array('tc.clicked_at BETWEEN %s AND %s', 'tc.country = %s');
    $geo_params = array($start_date, $end_date, $geo_country);
    if ($geo_city !== '') {
        $geo_where[] = 'tc.city = %s';
        $geo_params[] = $geo_city;
    }
    $geo_where_sql = implode(' AND ', $geo_where);
    $geo_total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM (
            SELECT tc.subscriber_id, tc.campaign_id
            FROM {$table_clicks} tc
            WHERE {$geo_where_sql}
            GROUP BY tc.subscriber_id, tc.campaign_id, tc.country, tc.city
        ) engagements",
        $geo_params
    ));
    $geo_pages = (int) ceil($geo_total / $geo_per_page);
    $geo_page = min($geo_page, max(1, $geo_pages));
    $geo_offset = ($geo_page - 1) * $geo_per_page;
    $geo_query_params = array_merge($geo_params, array($geo_per_page, $geo_offset));
    $geo_recipients = $wpdb->get_results($wpdb->prepare(
        "SELECT tc.subscriber_id, tc.campaign_id, tc.country, tc.city,
                s.email, s.first_name, s.last_name, c.name AS campaign_name,
                COUNT(*) AS click_count, MAX(tc.clicked_at) AS last_clicked_at
         FROM {$table_clicks} tc
         LEFT JOIN {$table_subscribers} s ON tc.subscriber_id = s.id
         LEFT JOIN {$table_campaigns} c ON tc.campaign_id = c.id
         WHERE {$geo_where_sql}
         GROUP BY tc.subscriber_id, tc.campaign_id, tc.country, tc.city,
                  s.email, s.first_name, s.last_name, c.name
         ORDER BY last_clicked_at DESC
         LIMIT %d OFFSET %d",
        $geo_query_params
    ));
}

// Get categories for distribution chart
$categories = $wpdb->get_results("
    SELECT c.name, COUNT(sc.subscriber_id) as count
    FROM {$wpdb->prefix}{$table_prefix}categories c
    LEFT JOIN {$wpdb->prefix}{$table_prefix}subscriber_categories sc ON c.id = sc.category_id
    GROUP BY c.id
    ORDER BY count DESC
    LIMIT 5
");

// Check if IP anonymization is enabled
$ip_anonymized = get_option('advnews_anonymize_ip', 1);

// Get performance data for charts - FIXED
$performance_data = $wpdb->get_results($wpdb->prepare(
    "SELECT DATE(sent_at) as date,
            COUNT(*) as campaigns_sent,
            SUM(total_recipients) as emails_sent,
            AVG(open_rate) as avg_open_rate,
            AVG(click_rate) as avg_click_rate
    FROM {$wpdb->prefix}{$table_prefix}campaigns
    WHERE sent_at BETWEEN %s AND %s
    AND status = 'sent'
    GROUP BY DATE(sent_at)
    ORDER BY date",
    $start_date,
    $end_date
));

// Get subscriber growth data - FIXED
$subscriber_growth_data = $wpdb->get_results($wpdb->prepare(
    "SELECT DATE(subscribed_at) as date, COUNT(*) as new_subscribers
    FROM {$wpdb->prefix}{$table_prefix}subscribers
    WHERE subscribed_at BETWEEN %s AND %s
    GROUP BY DATE(subscribed_at)
    ORDER BY date",
    $start_date,
    $end_date
));
?>
<div class="wrap advnews-analytics-overview">
    <h1 class="wp-heading-inline"><?php _e('Analytics Overview', 'advnews-manager'); ?></h1>

    <nav class="nav-tab-wrapper advnews-analytics-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=advnews-analytics')); ?>" class="nav-tab nav-tab-active"><?php esc_html_e('Overview', 'advnews-manager'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=advnews-analytics&action=weekly')); ?>" class="nav-tab"><?php esc_html_e('Weekly Reports', 'advnews-manager'); ?></a>
    </nav>

    <div class="advnews-period-selector">
        <select id="analytics-period" class="advnews-period-select">
            <option value="7days" <?php selected($period, '7days'); ?>><?php _e('Last 7 Days', 'advnews-manager'); ?></option>
            <option value="30days" <?php selected($period, '30days'); ?>><?php _e('Last 30 Days', 'advnews-manager'); ?></option>
            <option value="90days" <?php selected($period, '90days'); ?>><?php _e('Last 90 Days', 'advnews-manager'); ?></option>
            <option value="year" <?php selected($period, 'year'); ?>><?php _e('This Year', 'advnews-manager'); ?></option>
            <option value="all" <?php selected($period, 'all'); ?>><?php _e('All Time', 'advnews-manager'); ?></option>
        </select>
        <button type="button" class="button" id="export-overview"><?php _e('Export Report', 'advnews-manager'); ?></button>
    </div>

    <hr class="wp-header-end">

    <!-- Key Metrics Cards -->
    <div class="advnews-metrics-grid">
        <div class="metric-card metric-primary">
            <div class="metric-icon">
                <span class="dashicons dashicons-email-alt"></span>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo esc_html(number_format($total_campaigns)); ?></div>
                <div class="metric-label"><?php _e('Total Campaigns', 'advnews-manager'); ?></div>
                <div class="metric-trend">
                    <span style="color: #646970; font-size: 11px;">
                        <?php printf(__('%d sent, %d draft, %d scheduled', 'advnews-manager'),
                            $sent_campaigns, $draft_campaigns, $scheduled_campaigns); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="metric-card metric-success">
            <div class="metric-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo esc_html(number_format($analytics['subscribers']->active ?? 0)); ?></div>
                <div class="metric-label"><?php _e('Active Subscribers', 'advnews-manager'); ?></div>
                <div class="metric-trend">
                    <?php
                    $prev_subscribers = $analytics['previous']['subscribers'] ?? 0;
                    $trend = $prev_subscribers > 0 ? round((($analytics['subscribers']->active - $prev_subscribers) / $prev_subscribers) * 100, 1) : 0;
                    ?>
                    <span class="trend-<?php echo $trend >= 0 ? 'up' : 'down'; ?>">
                        <?php echo $trend >= 0 ? '+' : ''; ?><?php echo esc_html($trend); ?>%
                    </span>
                    <?php _e('vs previous period', 'advnews-manager'); ?>
                </div>
            </div>
        </div>

        <div class="metric-card metric-info">
            <div class="metric-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo esc_html(round($analytics['campaigns']->avg_open_rate ?? 0, 1)); ?>%</div>
                <div class="metric-label"><?php _e('Avg Open Rate', 'advnews-manager'); ?></div>
                <div class="metric-comparison">
                    <span class="vs-industry"><?php _e('Industry: 20%', 'advnews-manager'); ?></span>
                </div>
            </div>
        </div>

        <div class="metric-card metric-warning">
            <div class="metric-icon">
                <span class="dashicons dashicons-chart-area"></span>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo esc_html(round($analytics['campaigns']->avg_click_rate ?? 0, 1)); ?>%</div>
                <div class="metric-label"><?php _e('Avg Click Rate', 'advnews-manager'); ?></div>
                <div class="metric-comparison">
                    <span class="vs-industry"><?php _e('Industry: 2.5%', 'advnews-manager'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Charts - FIXED -->
    <div class="advnews-charts-grid">
        <div class="chart-card">
            <div class="chart-header">
                <h3><?php _e('Campaign Performance Over Time', 'advnews-manager'); ?></h3>
                <div class="chart-legend">
                    <span class="legend-item legend-open"><?php _e('Open Rate', 'advnews-manager'); ?></span>
                    <span class="legend-item legend-click"><?php _e('Click Rate', 'advnews-manager'); ?></span>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>
            <?php if (empty($performance_data)): ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                <?php _e('No campaign data available for this period. Create and send campaigns to see performance data.', 'advnews-manager'); ?>
            </p>
            <?php endif; ?>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <h3><?php _e('Subscriber Growth', 'advnews-manager'); ?></h3>
                <div class="chart-legend">
                    <span class="legend-item legend-growth"><?php _e('New Subscribers', 'advnews-manager'); ?></span>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="growthChart"></canvas>
            </div>
            <?php if (empty($subscriber_growth_data)): ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                <?php _e('No subscriber data available for this period.', 'advnews-manager'); ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Subscriber Distribution -->
    <div class="advnews-distribution-grid">
        <div class="distribution-card">
            <h3><?php _e('Subscriber Status Distribution', 'advnews-manager'); ?></h3>
            <div class="distribution-chart-container">
                <canvas id="statusChart"></canvas>
            </div>
            <div class="distribution-stats">
                <div class="stat-item">
                    <span class="stat-color active"></span>
                    <span class="stat-label"><?php _e('Active:', 'advnews-manager'); ?></span>
                    <span class="stat-value"><?php echo esc_html(number_format($analytics['subscribers']->active ?? 0)); ?></span>
                    <span class="stat-percentage">
                        <?php
                        $total = ($analytics['subscribers']->active ?? 0) + ($analytics['subscribers']->unsubscribed ?? 0) + ($analytics['subscribers']->bounced ?? 0);
                        echo $total > 0 ? round((($analytics['subscribers']->active ?? 0) / $total) * 100, 1) : 0; ?>%
                    </span>
                </div>
                <div class="stat-item">
                    <span class="stat-color unsubscribed"></span>
                    <span class="stat-label"><?php _e('Unsubscribed:', 'advnews-manager'); ?></span>
                    <span class="stat-value"><?php echo esc_html(number_format($analytics['subscribers']->unsubscribed ?? 0)); ?></span>
                    <span class="stat-percentage">
                        <?php echo $total > 0 ? round((($analytics['subscribers']->unsubscribed ?? 0) / $total) * 100, 1) : 0; ?>%
                    </span>
                </div>
                <div class="stat-item">
                    <span class="stat-color bounced"></span>
                    <span class="stat-label"><?php _e('Bounced:', 'advnews-manager'); ?></span>
                    <span class="stat-value"><?php echo esc_html(number_format($analytics['subscribers']->bounced ?? 0)); ?></span>
                    <span class="stat-percentage">
                        <?php echo $total > 0 ? round((($analytics['subscribers']->bounced ?? 0) / $total) * 100, 1) : 0; ?>%
                    </span>
                </div>
            </div>
        </div>

        <div class="distribution-card">
            <h3><?php _e('Category Distribution', 'advnews-manager'); ?></h3>
            <div class="distribution-chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
            <div class="distribution-stats">
                <?php foreach ($categories as $category): ?>
                <div class="stat-item">
                    <span class="stat-color" style="background-color: #<?php echo esc_attr(substr(md5($category->name), 0, 6)); ?>"></span>
                    <span class="stat-label"><?php echo esc_html($category->name); ?>:</span>
                    <span class="stat-value"><?php echo esc_html(number_format($category->count)); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Geographic Distribution -->
    <div class="advnews-geographic-distribution">
        <h3><?php _e('Geographic Distribution', 'advnews-manager'); ?></h3>
        <div class="geographic-grid">
            <div class="geo-card">
                <h4><?php _e('Top Countries', 'advnews-manager'); ?></h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Country', 'advnews-manager'); ?></th>
                            <th><?php _e('Clicks', 'advnews-manager'); ?></th>
                            <th><?php _e('Clickers', 'advnews-manager'); ?></th>
                            <th><?php _e('%', 'advnews-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_opens = array_sum(array_column($top_countries, 'opens'));
                        if (empty($top_countries)): ?>
                        <tr>
                            <td colspan="4"><?php _e('No click-based geographic data available. Click tracking and geolocation must be enabled.', 'advnews-manager'); ?></td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($top_countries as $country):
                            $percentage = $total_opens > 0 ? round(($country->opens / $total_opens) * 100, 1) : 0;
                            $country_url = add_query_arg(array(
                                'page' => 'advnews-analytics',
                                'tab' => 'overview',
                                'period' => $period,
                                'geo_country' => $country->country,
                            ), admin_url('admin.php')) . '#advnews-geographic-recipients';
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($country->country_code)): ?>
                                <img src="https://flagcdn.com/24x18/<?php echo esc_attr(strtolower($country->country_code)); ?>.png"
                                    alt="<?php echo esc_attr($country->country); ?>"
                                    style="vertical-align: middle; margin-right: 5px;">
                                <?php endif; ?>
                                <a href="<?php echo esc_url($country_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($country->country); ?></a>
                            </td>
                            <td><?php echo esc_html(number_format($country->opens)); ?></td>
                            <td><?php echo esc_html(number_format($country->unique_visitors ?? 0)); ?></td>
                            <td><?php echo esc_html($percentage); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="geo-card">
                <h4><?php _e('Top Cities', 'advnews-manager'); ?></h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('City', 'advnews-manager'); ?></th>
                            <th><?php _e('Country', 'advnews-manager'); ?></th>
                            <th><?php _e('Clicks', 'advnews-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_cities)): ?>
                        <tr>
                            <td colspan="3"><?php _e('No click-based city data available.', 'advnews-manager'); ?></td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($top_cities as $city):
                            $city_url = add_query_arg(array(
                                'page' => 'advnews-analytics',
                                'tab' => 'overview',
                                'period' => $period,
                                'geo_country' => $city->country,
                                'geo_city' => $city->city,
                            ), admin_url('admin.php')) . '#advnews-geographic-recipients';
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($city->country_code)): ?>
                                <img src="https://flagcdn.com/16x12/<?php echo esc_attr(strtolower($city->country_code)); ?>.png"
                                    alt="<?php echo esc_attr($city->country); ?>"
                                    style="vertical-align: middle; margin-right: 5px;">
                                <?php endif; ?>
                                <a href="<?php echo esc_url($city_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($city->city); ?></a>
                            </td>
                            <td><?php echo esc_html($city->country); ?></td>
                            <td><?php echo esc_html(number_format($city->opens)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($geo_country !== ''):
            $geo_label = $geo_city !== '' ? $geo_city . ', ' . $geo_country : $geo_country;
            $clear_geo_url = add_query_arg(array(
                'page' => 'advnews-analytics',
                'tab' => 'overview',
                'period' => $period,
            ), admin_url('admin.php'));
        ?>
        <section id="advnews-geographic-recipients" class="geo-recipient-results">
            <div class="geo-recipient-header">
                <div>
                    <h4><?php echo esc_html(sprintf(__('Recipients clicking from %s', 'advnews-manager'), $geo_label)); ?></h4>
                    <p class="description"><?php echo esc_html(sprintf(_n('%d recipient/campaign result', '%d recipient/campaign results', $geo_total, 'advnews-manager'), $geo_total)); ?></p>
                </div>
                <a class="button" href="<?php echo esc_url($clear_geo_url); ?>"><?php _e('Close results', 'advnews-manager'); ?></a>
            </div>
            <div class="geo-recipient-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'advnews-manager'); ?></th>
                            <th><?php _e('Email', 'advnews-manager'); ?></th>
                            <th><?php _e('City', 'advnews-manager'); ?></th>
                            <th><?php _e('Country', 'advnews-manager'); ?></th>
                            <th><?php _e('Campaign', 'advnews-manager'); ?></th>
                            <th><?php _e('Clicks', 'advnews-manager'); ?></th>
                            <th><?php _e('Last Click', 'advnews-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($geo_recipients)): ?>
                            <tr><td colspan="7"><?php _e('No matching recipients found for the selected period.', 'advnews-manager'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($geo_recipients as $recipient):
                                $recipient_name = trim((string) $recipient->first_name . ' ' . (string) $recipient->last_name);
                            ?>
                            <tr>
                                <td><?php echo esc_html($recipient_name !== '' ? $recipient_name : '-'); ?></td>
                                <td>
                                    <?php if ($recipient->subscriber_id && $recipient->email): ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=advnews-subscribers&action=view&id=' . (int) $recipient->subscriber_id)); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($recipient->email); ?></a>
                                    <?php else: ?>
                                        <span aria-label="<?php esc_attr_e('Unavailable', 'advnews-manager'); ?>">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($recipient->city ?: '-'); ?></td>
                                <td><?php echo esc_html($recipient->country ?: '-'); ?></td>
                                <td>
                                    <?php if ($recipient->campaign_id && $recipient->campaign_name): ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . (int) $recipient->campaign_id)); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($recipient->campaign_name); ?></a>
                                    <?php else: ?>
                                        <span aria-label="<?php esc_attr_e('Unavailable', 'advnews-manager'); ?>">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(number_format((int) $recipient->click_count)); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($recipient->last_clicked_at))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($geo_pages > 1):
                $geo_pagination_base = add_query_arg(array(
                    'page' => 'advnews-analytics',
                    'tab' => 'overview',
                    'period' => $period,
                    'geo_country' => $geo_country,
                    'geo_city' => $geo_city,
                    'geo_page' => 999999999,
                ), admin_url('admin.php'));
            ?>
                <nav class="geo-recipient-pagination" aria-label="<?php esc_attr_e('Geographic recipient results pages', 'advnews-manager'); ?>">
                    <span class="geo-page-summary">
                        <?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'advnews-manager'), $geo_page, $geo_pages)); ?>
                    </span>
                    <div class="geo-page-links">
                        <?php echo wp_kses_post(paginate_links(array(
                            'base' => str_replace('999999999', '%#%', $geo_pagination_base) . '#advnews-geographic-recipients',
                            'format' => '',
                            'current' => $geo_page,
                            'total' => $geo_pages,
                            'prev_text' => __('Previous', 'advnews-manager'),
                            'next_text' => __('Next', 'advnews-manager'),
                        ))); ?>
                    </div>
                </nav>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

    <!-- Device & Browser Analytics -->
    <div class="advnews-device-analytics">
        <h3><?php _e('Device & Browser Analytics', 'advnews-manager'); ?></h3>
        <div class="device-grid-wrapper">
            <div class="device-grid">
                <div class="device-card">
                    <h4><?php _e('Device Types', 'advnews-manager'); ?></h4>
                    <div class="device-chart-container">
                        <canvas id="deviceTypeChart"></canvas>
                    </div>
                </div>
                <div class="device-card">
                    <h4><?php _e('Browsers', 'advnews-manager'); ?></h4>
                    <div class="device-chart-container">
                        <canvas id="browserChart"></canvas>
                    </div>
                </div>
                <div class="device-card">
                    <h4><?php _e('Platforms', 'advnews-manager'); ?></h4>
                    <div class="device-chart-container">
                        <canvas id="platformChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="device-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Device Type', 'advnews-manager'); ?></th>
                        <th><?php _e('Browser', 'advnews-manager'); ?></th>
                        <th><?php _e('Platform', 'advnews-manager'); ?></th>
                        <th><?php _e('Clicks', 'advnews-manager'); ?></th>
                        <th><?php _e('%', 'advnews-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $device_total = array_sum(array_column($device_data ?? [], 'opens'));
                    if (empty($device_data)): ?>
                    <tr>
                        <td colspan="5"><?php _e('No click-based device data available. Click tracking must be enabled.', 'advnews-manager'); ?></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($device_data as $device):
                        $percentage = $device_total > 0 ? round(($device->opens / $device_total) * 100, 2) : 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html($device->device_type); ?></td>
                        <td><?php echo esc_html($device->browser); ?></td>
                        <td><?php echo esc_html($device->platform); ?></td>
                        <td><?php echo esc_html(number_format($device->opens)); ?></td>
                        <td><?php echo esc_html($percentage); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- IP Address Tracking - PRESERVED -->
    <div class="advnews-ip-tracking">
        <div class="ip-tracking-header">
            <h3><?php _e('IP Address Tracking', 'advnews-manager'); ?></h3>
            <div class="ip-tracking-actions">
                <span class="ip-status-badge <?php echo $ip_anonymized ? 'status-warning' : 'status-ok'; ?>">
                    <?php echo $ip_anonymized ? __('IP Anonymization: ON', 'advnews-manager') : __('IP Anonymization: OFF', 'advnews-manager'); ?>
                </span>
                <button type="button" class="button button-small" id="export-ip-data">
                    <?php _e('Export IP Data', 'advnews-manager'); ?>
                </button>
            </div>
        </div>
        <?php if ($ip_anonymized): ?>
        <div class="notice notice-warning inline" style="margin: 15px 0;">
            <p>
                <strong><?php _e('Privacy Notice:', 'advnews-manager'); ?></strong>
                <?php _e('IP addresses are anonymized for GDPR compliance. Last octet is removed from IPv4 addresses.', 'advnews-manager'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=advnews-settings&tab=tracking')); ?>" target="_blank" rel="noopener noreferrer">
                    <?php _e('Manage Settings', 'advnews-manager'); ?>
                </a>
            </p>
        </div>
        <?php endif; ?>

        <form class="advnews-ip-filters" method="get" action="<?php echo esc_url(admin_url('admin.php') . '#ip-tracking-table'); ?>">
            <input type="hidden" name="page" value="advnews-analytics">
            <input type="hidden" name="tab" value="overview">
            <input type="hidden" name="period" value="<?php echo esc_attr($period); ?>">
            <div class="advnews-ip-filter-field advnews-ip-filter-search">
                <label for="advnews-ip-search"><?php _e('Search', 'advnews-manager'); ?></label>
                <input id="advnews-ip-search" type="search" name="ip_search" value="<?php echo esc_attr($ip_search); ?>" placeholder="<?php esc_attr_e('Email, name, IP, or campaign', 'advnews-manager'); ?>">
            </div>
            <div class="advnews-ip-filter-field">
                <label for="advnews-ip-address"><?php _e('IP address', 'advnews-manager'); ?></label>
                <input id="advnews-ip-address" type="search" name="ip_address" value="<?php echo esc_attr($ip_address); ?>" list="advnews-ip-address-options" placeholder="<?php esc_attr_e('Select or enter an IP', 'advnews-manager'); ?>" autocomplete="off">
                <datalist id="advnews-ip-address-options">
                    <?php foreach ($ip_filter_addresses as $filter_ip): ?>
                        <option value="<?php echo esc_attr($filter_ip); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="advnews-ip-filter-field">
                <label for="advnews-ip-campaign"><?php _e('Campaign', 'advnews-manager'); ?></label>
                <select id="advnews-ip-campaign" name="ip_campaign">
                    <option value="0"><?php _e('All campaigns', 'advnews-manager'); ?></option>
                    <?php foreach ($ip_filter_campaigns as $filter_campaign): ?>
                        <option value="<?php echo esc_attr((int) $filter_campaign->id); ?>" <?php selected($ip_campaign, (int) $filter_campaign->id); ?>><?php echo esc_html($filter_campaign->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="advnews-ip-filter-field">
                <label for="advnews-ip-country"><?php _e('Country', 'advnews-manager'); ?></label>
                <select id="advnews-ip-country" name="ip_country">
                    <option value=""><?php _e('All countries', 'advnews-manager'); ?></option>
                    <?php foreach ($ip_filter_countries as $filter_country): ?>
                        <option value="<?php echo esc_attr($filter_country); ?>" <?php selected($ip_country, $filter_country); ?>><?php echo esc_html($filter_country); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="advnews-ip-filter-field">
                <label for="advnews-ip-city"><?php _e('City', 'advnews-manager'); ?></label>
                <select id="advnews-ip-city" name="ip_city">
                    <option value=""><?php _e('All cities', 'advnews-manager'); ?></option>
                    <?php foreach ($ip_filter_cities as $filter_city): ?>
                        <option value="<?php echo esc_attr($filter_city); ?>" <?php selected($ip_city, $filter_city); ?>><?php echo esc_html($filter_city); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="advnews-ip-filter-actions">
                <button class="button button-primary" type="submit"><?php _e('Filter', 'advnews-manager'); ?></button>
                <?php if ($ip_search !== '' || $ip_address !== '' || $ip_campaign > 0 || $ip_country !== '' || $ip_city !== ''): ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'advnews-analytics', 'tab' => 'overview', 'period' => $period), admin_url('admin.php')) . '#ip-tracking-table'); ?>"><?php _e('Reset', 'advnews-manager'); ?></a>
                <?php endif; ?>
            </div>
        </form>
        <div class="ip-table-container">
            <table class="wp-list-table widefat fixed striped" id="ip-tracking-table">
                <thead>
                    <tr>
                        <th><?php _e('IP Address', 'advnews-manager'); ?></th>
                        <th><?php _e('Subscriber', 'advnews-manager'); ?></th>
                        <th><?php _e('Location', 'advnews-manager'); ?></th>
                        <th><?php _e('Device', 'advnews-manager'); ?></th>
                        <th><?php _e('Browser', 'advnews-manager'); ?></th>
                        <th><?php _e('Campaign', 'advnews-manager'); ?></th>
                        <th><?php _e('Timestamp', 'advnews-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ip_data)): ?>
                    <tr>
                        <td colspan="7"><?php _e('No clicked IP tracking data available. Ensure click tracking is enabled in settings.', 'advnews-manager'); ?></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($ip_data as $ip_record):
                        $subscriber_email = $ip_record->subscriber_email ?: '-';
                        $campaign_name = $ip_record->campaign_name ?: '-';
                        // Format location
                        $location = trim($ip_record->city . ', ' . $ip_record->country, ', ');
                        if (empty($location)) {
                            $location = '—';
                        }
                    ?>
                    <tr>
                        <td>
                            <code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px;">
                                <?php echo esc_html($ip_record->ip_address); ?>
                            </code>
                            <?php if ($ip_anonymized): ?>
                            <span class="dashicons dashicons-lock" style="color: #f0c33c; font-size: 14px;"
                                title="<?php _e('IP address is anonymized', 'advnews-manager'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($subscriber_email && $subscriber_email !== '—'): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=advnews-subscribers&action=view&id=' . $ip_record->subscriber_id)); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html($subscriber_email); ?>
                            </a>
                            <?php else: ?>
                            <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($ip_record->country_code)): ?>
                            <img src="https://flagcdn.com/16x12/<?php echo strtolower($ip_record->country_code); ?>.png"
                                alt="<?php echo esc_attr($ip_record->country); ?>"
                                style="vertical-align: middle; margin-right: 5px;">
                            <?php endif; ?>
                            <?php echo esc_html($location); ?>
                        </td>
                        <td><?php echo esc_html($ip_record->device_type ?: '—'); ?></td>
                        <td><?php echo esc_html($ip_record->browser ?: '—'); ?></td>
                        <td>
                            <?php if ($campaign_name && $campaign_name !== '—'): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $ip_record->campaign_id)); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html($campaign_name); ?>
                            </a>
                            <?php else: ?>
                            <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span title="<?php echo esc_attr($ip_record->event_at); ?>">
                                <?php echo esc_html(human_time_diff(strtotime($ip_record->event_at), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="ip-tracking-footer">
            <p class="description">
                <?php echo esc_html(sprintf(
                    __('Showing %1$d of %2$d matching click records for the selected period.', 'advnews-manager'),
                    count($ip_data),
                    $ip_total
                )); ?>
            </p>
            <?php if (count($ip_data) < $ip_total): ?>
            <div class="ip-tracking-pagination">
                <button type="button" class="button button-small" id="load-more-ips" data-offset="<?php echo esc_attr(count($ip_data)); ?>">
                    <?php _e('Load More', 'advnews-manager'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Campaigns Table -->
    <div class="advnews-top-campaigns">
        <h3><?php _e('Top Performing Campaigns', 'advnews-manager'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Campaign', 'advnews-manager'); ?></th>
                    <th><?php _e('Sent Date', 'advnews-manager'); ?></th>
                    <th><?php _e('Recipients', 'advnews-manager'); ?></th>
                    <th><?php _e('Open Rate', 'advnews-manager'); ?></th>
                    <th><?php _e('Click Rate', 'advnews-manager'); ?></th>
                    <th><?php _e('Actions', 'advnews-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($top_campaigns)): ?>
                <tr>
                    <td colspan="6"><?php _e('No campaigns sent yet.', 'advnews-manager'); ?></td>
                </tr>
                <?php else: ?>
                <?php foreach ($top_campaigns as $campaign): ?>
                <tr>
                    <td>
                        <strong>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign->id)); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html($campaign->name); ?>
                            </a>
                        </strong>
                    </td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($campaign->sent_at))); ?></td>
                    <td><?php echo esc_html($campaign->total_recipients); ?></td>
                    <td>
                        <span class="rate-high"><?php echo esc_html($campaign->open_rate); ?>%</span>
                    </td>
                    <td>
                        <span class="rate-medium"><?php echo esc_html($campaign->click_rate); ?>%</span>
                    </td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=advnews-analytics&action=campaign&campaign_id=' . $campaign->id)); ?>" class="button button-small" target="_blank" rel="noopener noreferrer">
                            <?php _e('View Report', 'advnews-manager'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Activity Feed -->
    <div class="advnews-recent-activity">
        <h3><?php _e('Recent Activity', 'advnews-manager'); ?></h3>
        <div class="activity-feed">
            <?php
            $admin_class = new AdvNews_Admin();
            $recent_activity = $admin_class->get_recent_activity();
            foreach ($recent_activity as $activity): ?>
            <div class="activity-item activity-<?php echo esc_attr($activity['type']); ?>">
                <div class="activity-time">
                    <?php echo esc_html(human_time_diff(strtotime($activity['date']), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?>
                </div>
                <div class="activity-content">
                    <?php echo esc_html($activity['activity']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Period selector
    $('#analytics-period').on('change', function() {
        var period = $(this).val();
        window.location.href = '<?php echo admin_url('admin.php?page=advnews-analytics&tab=overview&period='); ?>' + period;
    });

    // Export button
    $('#export-overview').on('click', function() {
        var period = $('#analytics-period').val();
        window.location.href = '<?php echo admin_url('admin-ajax.php?action=advnews_export_analytics&type=overview&period='); ?>' + period + '&nonce=<?php echo wp_create_nonce('advnews_export'); ?>';
    });

    // Export IP Data
    $('#export-ip-data').on('click', function() {
        var filters = $('.advnews-ip-filters').serialize();
        var exportUrl = '<?php echo admin_url('admin-ajax.php?action=advnews_export_ip_data'); ?>'
            + '&nonce=<?php echo wp_create_nonce('advnews_export'); ?>';
        if (filters) {
            exportUrl += '&' + filters;
        }
        window.location.href = exportUrl;
    });

    // Load More IPs
    $('#load-more-ips').on('click', function() {
        var button = $(this);
        var offset = button.data('offset');
        button.prop('disabled', true).text('<?php _e('Loading...', 'advnews-manager'); ?>');
        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_load_more_ips',
                offset: offset,
                limit: 50,
                period: '<?php echo esc_js($period); ?>',
                search: '<?php echo esc_js($ip_search); ?>',
                ip_address: '<?php echo esc_js($ip_address); ?>',
                campaign_id: <?php echo (int) $ip_campaign; ?>,
                country: '<?php echo esc_js($ip_country); ?>',
                city: '<?php echo esc_js($ip_city); ?>',
                nonce: advnews_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    $('#ip-tracking-table tbody').append(response.data.html);
                    button.data('offset', offset + 50);
                    button.prop('disabled', false).text('<?php _e('Load More', 'advnews-manager'); ?>');
                    if (!response.data.has_more) {
                        button.hide();
                    }
                } else {
                    button.hide();
                }
            },
            error: function() {
                button.prop('disabled', false).text('<?php _e('Load More', 'advnews-manager'); ?>');
                alert('<?php _e('Error loading more IP data.', 'advnews-manager'); ?>');
            }
        });
    });

    // Performance Chart - FIXED SYNTAX
    var performanceCtx = document.getElementById('performanceChart');
    if (performanceCtx) {
        var performanceData = <?php echo json_encode($performance_data ?? []); ?>;

        if (performanceData && performanceData.length > 0) {
            var labels = performanceData.map(function(item) { return item.date; });
            var openData = performanceData.map(function(item) { return parseFloat(item.avg_open_rate) || 0; });
            var clickData = performanceData.map(function(item) { return parseFloat(item.avg_click_rate) || 0; });

            new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Open Rate',
                        data: openData,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        tension: 0.1,
                        fill: true
                    }, {
                        label: 'Click Rate',
                        data: clickData,
                        borderColor: '#00a32a',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        } else {
            performanceCtx.parentElement.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;"><?php _e('No campaign performance data available for this period.', 'advnews-manager'); ?></p>';
        }
    }

    // Growth Chart - FIXED SYNTAX
    var growthCtx = document.getElementById('growthChart');
    if (growthCtx) {
        var growthData = <?php echo json_encode($subscriber_growth_data ?? []); ?>;

        if (growthData && growthData.length > 0) {
            var labels = growthData.map(function(item) { return item.date; });
            var emailsData = growthData.map(function(item) { return parseInt(item.new_subscribers) || 0; });

            new Chart(growthCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'New Subscribers',
                        data: emailsData,
                        backgroundColor: '#f0c33c'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        } else {
            growthCtx.parentElement.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;"><?php _e('No subscriber growth data available for this period.', 'advnews-manager'); ?></p>';
        }
    }

    // Status Distribution Chart
    var statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        var activeCount = <?php echo esc_js($analytics['subscribers']->active ?? 0); ?>;
        var unsubscribedCount = <?php echo esc_js($analytics['subscribers']->unsubscribed ?? 0); ?>;
        var bouncedCount = <?php echo esc_js($analytics['subscribers']->bounced ?? 0); ?>;

        if (activeCount > 0 || unsubscribedCount > 0 || bouncedCount > 0) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Active', 'Unsubscribed', 'Bounced'],
                    datasets: [{
                        data: [activeCount, unsubscribedCount, bouncedCount],
                        backgroundColor: ['#00a32a', '#d63638', '#f0c33c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }

    // Category Chart
    var categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        var categoryLabels = <?php echo json_encode(array_column($categories, 'name')); ?>;
        var categoryData = <?php echo json_encode(array_column($categories, 'count')); ?>;

        if (categoryLabels && categoryLabels.length > 0) {
            new Chart(categoryCtx, {
                type: 'pie',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryData,
                        backgroundColor: categoryLabels.map(function(label) {
                            return '#' + md5(label).substring(0, 6);
                        })
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }

    // Device Type Chart
    var deviceTypeCtx = document.getElementById('deviceTypeChart');
    if (deviceTypeCtx) {
        var deviceTypeData = {};
        <?php foreach ($device_data ?? [] as $device): ?>
        if (deviceTypeData['<?php echo esc_js($device->device_type); ?>']) {
            deviceTypeData['<?php echo esc_js($device->device_type); ?>'] += <?php echo esc_js($device->opens); ?>;
        } else {
            deviceTypeData['<?php echo esc_js($device->device_type); ?>'] = <?php echo esc_js($device->opens); ?>;
        }
        <?php endforeach; ?>

        if (Object.keys(deviceTypeData).length > 0) {
            new Chart(deviceTypeCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(deviceTypeData),
                    datasets: [{
                        data: Object.values(deviceTypeData),
                        backgroundColor: ['#2271b1', '#00a32a', '#d63638', '#f0c33c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }

    // Browser Chart
    var browserCtx = document.getElementById('browserChart');
    if (browserCtx) {
        var browserData = {};
        <?php foreach ($device_data ?? [] as $device): ?>
        if (browserData['<?php echo esc_js($device->browser); ?>']) {
            browserData['<?php echo esc_js($device->browser); ?>'] += <?php echo esc_js($device->opens); ?>;
        } else {
            browserData['<?php echo esc_js($device->browser); ?>'] = <?php echo esc_js($device->opens); ?>;
        }
        <?php endforeach; ?>

        if (Object.keys(browserData).length > 0) {
            new Chart(browserCtx, {
                type: 'pie',
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
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }

    // Platform Chart
    var platformCtx = document.getElementById('platformChart');
    if (platformCtx) {
        var platformData = {};
        <?php foreach ($device_data ?? [] as $device): ?>
        if (platformData['<?php echo esc_js($device->platform); ?>']) {
            platformData['<?php echo esc_js($device->platform); ?>'] += <?php echo esc_js($device->opens); ?>;
        } else {
            platformData['<?php echo esc_js($device->platform); ?>'] = <?php echo esc_js($device->opens); ?>;
        }
        <?php endforeach; ?>

        if (Object.keys(platformData).length > 0) {
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
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }

    // Helper function for colors
    function md5(string) {
        var hash = 0;
        for (var i = 0; i < string.length; i++) {
            hash = ((hash << 5) - hash) + string.charCodeAt(i);
            hash = hash & hash;
        }
        return Math.abs(hash).toString(16);
    }
});
</script>

<style>
/* All original CSS styles preserved - same as original file */
.advnews-analytics-overview {
    padding: 20px 0;
}

.advnews-period-selector {
    float: right;
    margin-bottom: 20px;
}

.advnews-period-selector select {
    height: 35px;
    margin-right: 10px;
}

/* Metrics Grid */
.advnews-metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin: 20px 0 30px;
    clear: both;
}

.metric-card {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    border-left: 4px solid;
}

.metric-card.metric-primary { border-left-color: #2271b1; }
.metric-card.metric-success { border-left-color: #00a32a; }
.metric-card.metric-info { border-left-color: #72aee6; }
.metric-card.metric-warning { border-left-color: #f0c33c; }

.metric-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.metric-primary .metric-icon { background: rgba(34, 113, 177, 0.1); }
.metric-success .metric-icon { background: rgba(0, 163, 42, 0.1); }
.metric-info .metric-icon { background: rgba(114, 174, 230, 0.1); }
.metric-warning .metric-icon { background: rgba(240, 195, 60, 0.1); }

.metric-icon .dashicons {
    font-size: 30px;
    width: 30px;
    height: 30px;
}

.metric-primary .dashicons { color: #2271b1; }
.metric-success .dashicons { color: #00a32a; }
.metric-info .dashicons { color: #72aee6; }
.metric-warning .dashicons { color: #f0c33c; }

.metric-content {
    flex: 1;
}

.metric-value {
    font-size: 28px;
    font-weight: 600;
    line-height: 1.2;
    color: #1d2327;
}

.metric-label {
    font-size: 14px;
    color: #646970;
    margin-bottom: 5px;
}

.metric-trend {
    font-size: 12px;
}

.trend-up { color: #00a32a; }
.trend-down { color: #d63638; }

.metric-comparison {
    font-size: 12px;
    color: #646970;
}

.vs-industry {
    background: #f0f0f1;
    padding: 2px 8px;
    border-radius: 12px;
    display: inline-block;
}

/* Charts Grid */
.advnews-charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.chart-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    overflow: hidden;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.chart-header h3 {
    margin: 0;
    font-size: 16px;
}

.chart-legend {
    display: flex;
    gap: 15px;
}

.legend-item {
    font-size: 12px;
    padding-left: 20px;
    position: relative;
}

.legend-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 12px;
    height: 3px;
    border-radius: 2px;
}

.legend-open::before { background: #2271b1; }
.legend-click::before { background: #00a32a; }
.legend-growth::before { background: #f0c33c; height: 12px; }

.chart-container {
    position: relative;
    height: 300px;
    max-height: 300px;
    overflow: hidden;
}

.chart-container canvas {
    max-width: 100% !important;
    max-height: 100% !important;
}

/* Distribution Grid */
.advnews-distribution-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.distribution-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    overflow: hidden;
}

.distribution-card h3 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 16px;
}

.distribution-chart-container {
    height: 200px;
    max-height: 200px;
    margin-bottom: 20px;
    overflow: hidden;
}

.distribution-chart-container canvas {
    max-width: 100% !important;
    max-height: 100% !important;
}

.distribution-stats {
    border-top: 1px solid #f0f0f0;
    padding-top: 15px;
}

.stat-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    padding: 5px 0;
}

.stat-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    margin-right: 10px;
}

.stat-color.active { background: #00a32a; }
.stat-color.unsubscribed { background: #d63638; }
.stat-color.bounced { background: #f0c33c; }

.stat-label {
    flex: 1;
    font-size: 13px;
    color: #1d2327;
}

.stat-value {
    font-weight: 600;
    margin-right: 10px;
}

.stat-percentage {
    color: #646970;
    font-size: 12px;
    min-width: 45px;
    text-align: right;
}

/* Geographic Distribution */
.advnews-geographic-distribution {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin: 30px 0;
    overflow: hidden;
}

.advnews-geographic-distribution h3 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 16px;
}

.geographic-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.geo-card {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 15px;
}

.geo-card h4 {
    margin: 0 0 15px;
    font-size: 14px;
    color: #2271b1;
}

/* Device Analytics */
.advnews-device-analytics {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin: 30px 0;
    overflow: hidden;
}

.advnews-device-analytics h3 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 16px;
}

.device-grid-wrapper {
    width: 100%;
    overflow: visible;
    margin-bottom: 30px;
}

.device-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    width: 100%;
}

.device-card {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 15px;
    min-width: 0;
}

.device-card h4 {
    margin: 0 0 15px;
    font-size: 14px;
    color: #2271b1;
    text-align: center;
}

.device-chart-container {
    position: relative;
    height: 260px;
    min-height: 260px;
    width: 100%;
    overflow: visible;
}

.device-chart-container canvas {
    max-width: 100% !important;
    max-height: 100% !important;
}

.device-table-container {
    overflow-x: auto;
    width: 100%;
}

.geo-recipient-results {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ccd0d4;
}

.geo-recipient-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 12px;
}

.geo-recipient-header h4 {
    margin: 0 0 4px;
}

.geo-recipient-table {
    overflow-x: auto;
}

.geo-recipient-pagination {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 16px;
}

.geo-page-summary {
    color: #50575e;
    font-size: 13px;
    font-weight: 600;
}

.geo-page-links {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.geo-page-links .page-numbers {
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    min-height: 38px;
    padding: 0 12px;
    border: 1px solid #2271b1;
    border-radius: 4px;
    background: #fff;
    color: #135e96;
    font-size: 14px;
    font-weight: 600;
    line-height: 1;
    text-decoration: none;
}

.geo-page-links .page-numbers:hover,
.geo-page-links .page-numbers:focus {
    background: #f0f6fc;
    color: #0a4b78;
}

.geo-page-links .page-numbers.current {
    border-color: #2271b1;
    background: #2271b1;
    color: #fff;
}

.geo-page-links .page-numbers.prev,
.geo-page-links .page-numbers.next {
    min-width: auto;
}

@media screen and (max-width: 782px) {
    .geo-recipient-pagination {
        align-items: stretch;
        flex-direction: column;
    }

    .geo-page-links .page-numbers {
        min-height: 42px;
    }
}

.advnews-ip-filters {
    display: grid;
    grid-template-columns: minmax(220px, 1.4fr) repeat(3, minmax(150px, 1fr)) auto;
    align-items: end;
    gap: 12px;
    margin: 16px 0;
    padding: 14px 0;
    border-top: 1px solid #dcdcde;
    border-bottom: 1px solid #dcdcde;
}

.advnews-ip-filter-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.advnews-ip-filter-field input,
.advnews-ip-filter-field select {
    width: 100%;
    min-height: 32px;
}

.advnews-ip-filter-actions {
    display: flex;
    gap: 6px;
    padding-bottom: 1px;
}

/* IP Tracking */
.advnews-ip-tracking {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin: 30px 0;
    overflow: hidden;
}

.ip-tracking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.ip-tracking-header h3 {
    margin: 0;
    font-size: 16px;
}

.ip-tracking-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.ip-status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.ip-status-badge.status-ok {
    background: #d4edda;
    color: #155724;
}

.ip-status-badge.status-warning {
    background: #fff3cd;
    color: #856404;
}

.ip-table-container {
    overflow-x: auto;
    margin: 15px 0;
}

.ip-tracking-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
}

.ip-tracking-pagination {
    text-align: right;
}

/* Top Campaigns */
.advnews-top-campaigns {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    overflow: hidden;
}

.advnews-top-campaigns h3 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 16px;
}

.rate-high {
    color: #00a32a;
    font-weight: 600;
}

.rate-medium {
    color: #f0c33c;
    font-weight: 600;
}

/* Activity Feed */
.advnews-recent-activity {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    overflow: hidden;
}

.advnews-recent-activity h3 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 16px;
}

.activity-feed {
    max-height: 300px;
    overflow-y: auto;
}

.activity-item {
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    gap: 15px;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-time {
    min-width: 100px;
    font-size: 12px;
    color: #646970;
}

.activity-content {
    flex: 1;
    font-size: 13px;
}

.activity-campaign .activity-content { border-left: 3px solid #2271b1; padding-left: 10px; }
.activity-subscriber .activity-content { border-left: 3px solid #00a32a; padding-left: 10px; }

/* Responsive */
@media (max-width: 1200px) {
    .advnews-metrics-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .advnews-charts-grid,
    .advnews-distribution-grid,
    .geographic-grid {
        grid-template-columns: 1fr;
    }

    .device-grid {
        grid-template-columns: 1fr;
    }

    .advnews-ip-filters {
        grid-template-columns: repeat(2, minmax(180px, 1fr));
    }

    .advnews-ip-filter-actions {
        align-self: end;
    }

    .ip-tracking-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }

    .ip-tracking-actions {
        width: 100%;
        justify-content: space-between;
    }
}

@media (max-width: 782px) {
    .advnews-metrics-grid {
        grid-template-columns: 1fr;
    }

    .advnews-period-selector {
        float: none;
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }

    .advnews-period-selector select {
        flex: 1;
    }

    .activity-item {
        flex-direction: column;
        gap: 5px;
    }

    .activity-time {
        min-width: auto;
    }

    .device-chart-container,
    .chart-container,
    .distribution-chart-container {
        height: 250px;
    }

    .advnews-ip-filters {
        grid-template-columns: 1fr;
    }

    .geo-recipient-header {
        flex-direction: column;
    }

    .ip-tracking-footer {
        flex-direction: column;
        gap: 15px;
    }
}
</style>
