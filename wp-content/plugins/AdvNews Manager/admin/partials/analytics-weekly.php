<?php
// admin/partials/analytics-weekly.php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;
$campaigns_table = $wpdb->prefix . $table_prefix . 'campaigns';
$subscribers_table = $wpdb->prefix . $table_prefix . 'subscribers';
$timezone = wp_timezone();
$current_week_start = new DateTimeImmutable('monday this week 00:00:00', $timezone);
$last_report_week = (string) get_option('advnews_last_weekly_report_week', '');
$last_report_sent_at = (string) get_option('advnews_last_weekly_report_sent_at', '');
$weekly_rows = array();

for ($week_index = 0; $week_index < 12; $week_index++) {
    $week_end = $current_week_start->modify('-' . $week_index . ' weeks');
    $week_start = $week_end->modify('-7 days');
    $start_sql = $week_start->format('Y-m-d H:i:s');
    $end_sql = $week_end->format('Y-m-d H:i:s');

    $campaign_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS total_campaigns,
                COALESCE(SUM(total_recipients), 0) AS total_emails,
                COALESCE(AVG(open_rate), 0) AS avg_open_rate,
                COALESCE(AVG(click_rate), 0) AS avg_click_rate
         FROM {$campaigns_table}
         WHERE status = 'sent' AND sent_at >= %s AND sent_at < %s",
        $start_sql,
        $end_sql
    ));

    $subscriber_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS total_subscribers,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_subscribers,
                SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) AS unsubscribed,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) AS bounced,
                SUM(CASE WHEN subscribed_at >= %s AND subscribed_at < %s THEN 1 ELSE 0 END) AS new_subscribers
         FROM {$subscribers_table}
         WHERE subscribed_at < %s",
        $start_sql,
        $end_sql,
        $end_sql
    ));

    $report_run_week = $week_end->format('o-W');
    $weekly_rows[] = array(
        'start' => $week_start,
        'end' => $week_end,
        'campaigns' => (int) ($campaign_stats->total_campaigns ?? 0),
        'emails' => (int) ($campaign_stats->total_emails ?? 0),
        'open_rate' => (float) ($campaign_stats->avg_open_rate ?? 0),
        'click_rate' => (float) ($campaign_stats->avg_click_rate ?? 0),
        'new_subscribers' => (int) ($subscriber_stats->new_subscribers ?? 0),
        'total_subscribers' => (int) ($subscriber_stats->total_subscribers ?? 0),
        'active' => (int) ($subscriber_stats->active_subscribers ?? 0),
        'unsubscribed' => (int) ($subscriber_stats->unsubscribed ?? 0),
        'bounced' => (int) ($subscriber_stats->bounced ?? 0),
        'emailed' => $last_report_week !== '' && $last_report_week === $report_run_week,
    );
}
?>
<div class="wrap advnews-weekly-reports">
    <h1 class="wp-heading-inline"><?php esc_html_e('Weekly Reports', 'advnews-manager'); ?></h1>

    <nav class="nav-tab-wrapper advnews-analytics-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=advnews-analytics')); ?>" class="nav-tab"><?php esc_html_e('Overview', 'advnews-manager'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=advnews-analytics&action=weekly')); ?>" class="nav-tab nav-tab-active"><?php esc_html_e('Weekly Reports', 'advnews-manager'); ?></a>
    </nav>

    <p><?php esc_html_e('Completed weekly periods are shown from Monday 00:00 up to, but not including, the following Monday. Campaign figures use sent campaigns; subscriber figures include subscribers created before the end of each period.', 'advnews-manager'); ?></p>
    <p><strong><?php esc_html_e('Automatic schedule:', 'advnews-manager'); ?></strong> <?php esc_html_e('Monday at 8:00 AM in the WordPress site timezone.', 'advnews-manager'); ?></p>

    <div class="advnews-weekly-table-wrap">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Report period', 'advnews-manager'); ?></th>
                    <th><?php esc_html_e('Email status', 'advnews-manager'); ?></th>
                    <th><?php esc_html_e('Campaigns sent', 'advnews-manager'); ?></th>
                    <th><?php esc_html_e('Emails sent', 'advnews-manager'); ?></th>
                    <th><?php esc_html_e('Avg open rate', 'advnews-manager'); ?></th>
                    <th><?php esc_html_e('Avg click rate', 'advnews-manager'); ?></th>
                    <th><?php esc_html_e('New subscribers', 'advnews-manager'); ?></th>
                    <th><?php esc_html_e('Total subscribers', 'advnews-manager'); ?></th>
                    <th><?php esc_html_e('Active', 'advnews-manager'); ?></th>
                    <th><?php esc_html_e('Unsubscribed', 'advnews-manager'); ?></th>
                    <th><?php esc_html_e('Bounced', 'advnews-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($weekly_rows as $row) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($row['start']->format('Y-m-d') . ' to ' . $row['end']->format('Y-m-d')); ?></strong></td>
                        <td>
                            <?php if ($row['emailed']) : ?>
                                <span class="advnews-weekly-status is-sent"><?php esc_html_e('Sent', 'advnews-manager'); ?></span>
                                <?php if ($last_report_sent_at !== '') : ?><br><small><?php echo esc_html($last_report_sent_at); ?></small><?php endif; ?>
                            <?php else : ?>
                                <span class="advnews-weekly-status"><?php esc_html_e('Not the emailed period', 'advnews-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(number_format_i18n($row['campaigns'])); ?></td>
                        <td><?php echo esc_html(number_format_i18n($row['emails'])); ?></td>
                        <td><?php echo esc_html(number_format_i18n($row['open_rate'], 2)); ?>%</td>
                        <td><?php echo esc_html(number_format_i18n($row['click_rate'], 2)); ?>%</td>
                        <td><?php echo esc_html(number_format_i18n($row['new_subscribers'])); ?></td>
                        <td><?php echo esc_html(number_format_i18n($row['total_subscribers'])); ?></td>
                        <td><?php echo esc_html(number_format_i18n($row['active'])); ?></td>
                        <td><?php echo esc_html(number_format_i18n($row['unsubscribed'])); ?></td>
                        <td><?php echo esc_html(number_format_i18n($row['bounced'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.advnews-weekly-reports .nav-tab-wrapper { margin: 16px 0; }
.advnews-weekly-table-wrap { overflow-x: auto; margin-top: 18px; }
.advnews-weekly-table-wrap table { min-width: 1450px; }
.advnews-weekly-table-wrap th { font-weight: 600; }
.advnews-weekly-status { display: inline-block; padding: 3px 8px; border-radius: 999px; background: #f0f0f1; color: #50575e; font-weight: 600; }
.advnews-weekly-status.is-sent { background: #edfaef; color: #087423; }
</style>
