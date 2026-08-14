<?php
// admin/partials/settings-tracking.php
if (!defined('ABSPATH')) exit;

// Get options
$enable_open_tracking = get_option('advnews_enable_open_tracking', true);
$enable_click_tracking = get_option('advnews_enable_click_tracking', true);
$track_geolocation = get_option('advnews_track_geolocation', true);
$track_device = get_option('advnews_track_device', true);
$anonymize_ip = get_option('advnews_anonymize_ip', false);
$geolocation_service = get_option('advnews_geolocation_service', 'ipapi'); // Default to ipapi
$geolocation_api_key = get_option('advnews_geolocation_api_key', '');
$retention_days = get_option('advnews_tracking_retention_days', 365);
$enable_utm_tracking = get_option('advnews_enable_utm_tracking', false);
$utm_parameters = get_option('advnews_utm_parameters', 'utm_source,utm_medium,utm_campaign,utm_term,utm_content');

// MaxMind Specific Options
$maxmind_license_key = get_option('advnews_maxmind_license_key', '');
$maxmind_auto_update = get_option('advnews_maxmind_auto_update', true);
$maxmind_db_path = get_option('advnews_maxmind_db_path', '');
$db_exists = !empty($maxmind_db_path) && file_exists($maxmind_db_path);
$db_date = $db_exists ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($maxmind_db_path)) : __('Not downloaded yet', 'advnews-manager');
?>

<div class="advnews-settings-section">
    <h2><?php _e('Tracking & Analytics Settings', 'advnews-manager'); ?></h2>

    <!-- Basic Tracking -->
    <div class="settings-group">
        <h3><?php _e('Basic Tracking', 'advnews-manager'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('Email Tracking', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_enable_open_tracking" value="1"
                            <?php checked($enable_open_tracking, 1); ?>>
                        <?php _e('Track email opens (uses tracking pixel)', 'advnews-manager'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Know when and how many times subscribers open your emails.', 'advnews-manager'); ?>
                    </p>
                    <label style="margin-top:10px; display:block;">
                        <input type="checkbox" name="advnews_enable_click_tracking" value="1"
                            <?php checked($enable_click_tracking, 1); ?>>
                        <?php _e('Track link clicks (rewrites URLs)', 'advnews-manager'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Track which links subscribers click and how often.', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php _e('UTM Tracking', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_enable_utm_tracking" value="1"
                            <?php checked($enable_utm_tracking, 1); ?>>
                        <?php _e('Automatically add UTM parameters to links', 'advnews-manager'); ?>
                    </label>
                    <div class="utm-parameters" style="margin-top:10px; <?php echo !$enable_utm_tracking ? 'display:none;' : ''; ?>">
                        <label for="advnews_utm_parameters"><?php _e('UTM Parameters:', 'advnews-manager'); ?></label>
                        <input type="text" id="advnews_utm_parameters" name="advnews_utm_parameters"
                            value="<?php echo esc_attr($utm_parameters); ?>" class="regular-text">
                        <p class="description">
                            <?php _e('Comma-separated list of UTM parameters to track.', 'advnews-manager'); ?>
                        </p>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Advanced Tracking -->
    <div class="settings-group">
        <h3><?php _e('Advanced Tracking', 'advnews-manager'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('Geolocation', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_track_geolocation" value="1"
                            <?php checked($track_geolocation, 1); ?>>
                        <?php _e('Track subscriber location (country, city)', 'advnews-manager'); ?>
                    </label>

                    <?php if ($track_geolocation): ?>
                    <div class="geolocation-settings" style="margin-top:15px; padding-left: 20px; border-left: 3px solid #2271b1;">
                        <h4><?php _e('Geolocation Service', 'advnews-manager'); ?></h4>
                        <select id="advnews_geolocation_service" name="advnews_geolocation_service" class="geolocation-service-select">
                            <option value="ipapi" <?php selected($geolocation_service, 'ipapi'); ?>>
                                <?php _e('ip-api.com (Free, no key required)', 'advnews-manager'); ?>
                            </option>
                            <option value="maxmind" <?php selected($geolocation_service, 'maxmind'); ?>>
                                <?php _e('MaxMind GeoIP2 (Local Database - Recommended)', 'advnews-manager'); ?>
                            </option>
                            <option value="ipstack" <?php selected($geolocation_service, 'ipstack'); ?>>
                                <?php _e('ipstack (Requires API Key)', 'advnews-manager'); ?>
                            </option>
                            <option value="ipinfo" <?php selected($geolocation_service, 'ipinfo'); ?>>
                                <?php _e('ipinfo.io (Requires API Key)', 'advnews-manager'); ?>
                            </option>
                        </select>

                        <!-- Generic API Key Field (Hidden for MaxMind/ipapi) -->
                        <div id="api-key-field" style="margin-top:10px; <?php echo in_array($geolocation_service, ['ipapi', 'maxmind']) ? 'display:none;' : ''; ?>">
                            <label for="advnews_geolocation_api_key"><?php _e('API Key:', 'advnews-manager'); ?></label>
                            <input type="text" id="advnews_geolocation_api_key" name="advnews_geolocation_api_key"
                                value="<?php echo esc_attr($geolocation_api_key); ?>" class="regular-text">
                        </div>

                        <!-- ✅ MAXMIND SPECIFIC SETTINGS -->
                        <div id="maxmind-settings-field" style="margin-top:15px; background:#f8f9fa; padding:15px; border-radius:4px; border:1px solid #e9ecef; <?php echo $geolocation_service !== 'maxmind' ? 'display:none;' : ''; ?>">
                            <h4 style="margin-top:0;"><?php _e('MaxMind Configuration', 'advnews-manager'); ?></h4>

                            <!-- 1. License Key Field -->
                            <div style="margin-bottom:15px;">
                                <label for="advnews_maxmind_license_key"><strong><?php _e('License Key:', 'advnews-manager'); ?></strong></label>
                                <input type="text" id="advnews_maxmind_license_key" name="advnews_maxmind_license_key"
                                    value="<?php echo esc_attr($maxmind_license_key); ?>"
                                    class="regular-text" placeholder="<?php _e('Enter your MaxMind License Key', 'advnews-manager'); ?>">
                                <p class="description">
                                    <?php _e('Required for downloading the GeoLite2 database. Get a free key at maxmind.com.', 'advnews-manager'); ?>
                                    <a href="https://www.maxmind.com/en/geolite2/signup" target="_blank"><?php _e('Get Free Key', 'advnews-manager'); ?></a>
                                </p>
                            </div>

                            <!-- 2. Auto Update Checkbox -->
                            <div style="margin-bottom:15px;">
                                <label>
                                    <input type="checkbox" name="advnews_maxmind_auto_update" value="1"
                                    <?php checked($maxmind_auto_update, 1); ?>>
                                    <?php _e('Automatically update database weekly via Cron', 'advnews-manager'); ?>
                                </label>
                            </div>

                            <!-- 3. Manual Update Button -->
                            <div>
                                <button type="button" class="button button-primary" id="update-maxmind-now">
                                    <?php _e('Download / Update Database Now', 'advnews-manager'); ?>
                                </button>
                                <span id="maxmind-update-spinner" class="spinner" style="float:none; margin-left:5px;"></span>
                                <span id="maxmind-update-result" style="margin-left:10px; font-weight:bold;"></span>

                                <p class="description" style="margin-top:10px;">
                                    <?php _e('Current DB Status: ', 'advnews-manager'); ?>
                                    <?php if ($db_exists): ?>
                                        <span style="color:green;">✔ <?php _e('Database Found', 'advnews-manager'); ?> (<?php echo $db_date; ?>)</span>
                                    <?php else: ?>
                                        <span style="color:red;">✘ <?php _e('No Database Found', 'advnews-manager'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <!-- END MAXMIND SETTINGS -->

                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php _e('Device Tracking', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_track_device" value="1"
                            <?php checked($track_device, 1); ?>>
                        <?php _e('Track device type, browser, and operating system', 'advnews-manager'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php _e('IP Anonymization', 'advnews-manager'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="advnews_anonymize_ip" value="1"
                            <?php checked($anonymize_ip, 1); ?>>
                        <?php _e('Anonymize IP addresses (GDPR compliance)', 'advnews-manager'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Remove the last octet of IPv4 addresses before storing.', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="advnews_tracking_retention_days"><?php _e('Data Retention', 'advnews-manager'); ?></label>
                </th>
                <td>
                    <input type="number" id="advnews_tracking_retention_days" name="advnews_tracking_retention_days"
                        value="<?php echo esc_attr($retention_days); ?>" class="small-text" min="30" max="3650" step="30">
                    <span class="description"><?php _e('days', 'advnews-manager'); ?></span>
                    <p class="description">
                        <?php _e('How long to keep tracking data before automatic cleanup.', 'advnews-manager'); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tracking Preview -->
    <div class="settings-group">
        <h3><?php _e('Tracking Preview', 'advnews-manager'); ?></h3>
        <div class="tracking-preview">
            <p><?php _e('This is how tracked links will appear in your emails:', 'advnews-manager'); ?></p>
            <div class="preview-box">
                <strong><?php _e('Original URL:', 'advnews-manager'); ?></strong><br>
                <code>https://example.com/product?offer=sale</code>
                <div style="margin:15px 0;">
                    <span class="dashicons dashicons-arrow-down-alt"></span>
                </div>
                <strong><?php _e('Tracked URL:', 'advnews-manager'); ?></strong><br>
                <code><?php echo esc_url(site_url('?advnews_track=1&hash=abc123&campaign=1&subscriber=123')); ?></code>
                <?php if ($enable_utm_tracking): ?>
                <div style="margin-top:10px;">
                    <strong><?php _e('With UTM Parameters:', 'advnews-manager'); ?></strong><br>
                    <code><?php echo esc_url(site_url('?advnews_track=1&hash=abc123&utm_source=newsletter&utm_medium=email&utm_campaign=summer_sale')); ?></code>
                </div>
                <?php endif; ?>
            </div>
            <p class="description">
                <?php _e('Tracked links redirect through our system to record clicks before sending users to the destination.', 'advnews-manager'); ?>
            </p>
        </div>
    </div>

    <!-- Tracking Stats -->
    <div class="settings-group">
        <h3><?php _e('Current Tracking Statistics', 'advnews-manager'); ?></h3>
        <?php
        global $wpdb;
        $table_prefix = ADVNEWS_TABLE_PREFIX;
        $open_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$table_prefix}tracking_opens");
        $click_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$table_prefix}tracking_clicks");
        $unique_openers = $wpdb->get_var("SELECT COUNT(DISTINCT subscriber_id) FROM {$wpdb->prefix}{$table_prefix}tracking_opens");
        $unique_clickers = $wpdb->get_var("SELECT COUNT(DISTINCT subscriber_id) FROM {$wpdb->prefix}{$table_prefix}tracking_clicks");
        $last_24h_opens = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$table_prefix}tracking_opens WHERE opened_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $last_24h_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$table_prefix}tracking_clicks WHERE clicked_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        ?>
        <div class="tracking-stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-visibility"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($open_count)); ?></div>
                    <div class="stat-label"><?php _e('Total Opens', 'advnews-manager'); ?></div>
                    <div class="stat-trend">+<?php echo esc_html($last_24h_opens); ?> <?php _e('last 24h', 'advnews-manager'); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-external"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($click_count)); ?></div>
                    <div class="stat-label"><?php _e('Total Clicks', 'advnews-manager'); ?></div>
                    <div class="stat-trend">+<?php echo esc_html($last_24h_clicks); ?> <?php _e('last 24h', 'advnews-manager'); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($unique_openers)); ?></div>
                    <div class="stat-label"><?php _e('Unique Openers', 'advnews-manager'); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo esc_html(number_format($unique_clickers)); ?></div>
                    <div class="stat-label"><?php _e('Unique Clickers', 'advnews-manager'); ?></div>
                </div>
            </div>
        </div>
        <div class="tracking-actions" style="margin-top:15px;">
            <button type="button" class="button" id="clear-old-tracking"><?php _e('Clear Old Tracking Data', 'advnews-manager'); ?></button>
            <button type="button" class="button" id="export-tracking-stats"><?php _e('Export Statistics', 'advnews-manager'); ?></button>
        </div>
        <div id="tracking-result" style="display:none; margin-top:15px;"></div>
    </div>

    <!-- Privacy Notice -->
    <div class="settings-group privacy-notice">
        <h3><?php _e('Privacy Information', 'advnews-manager'); ?></h3>
        <div class="notice notice-info inline">
            <p>
                <strong><?php _e('GDPR Compliance:', 'advnews-manager'); ?></strong>
                <?php _e('Tracking involves collecting personal data such as IP addresses and user behavior.', 'advnews-manager'); ?>
                <?php _e('Ensure you have proper consent and include this information in your privacy policy.', 'advnews-manager'); ?>
            </p>
        </div>
        <p>
            <a href="<?php echo admin_url('admin.php?page=advnews-settings&tab=gdpr'); ?>" class="button">
                <?php _e('Configure GDPR Settings', 'advnews-manager'); ?>
            </a>
        </p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide UTM parameters
    $('input[name="advnews_enable_utm_tracking"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('.utm-parameters').slideDown();
        } else {
            $('.utm-parameters').slideUp();
        }
    });

    // Show/hide Geolocation Fields based on Service
    $('#advnews_geolocation_service').on('change', function() {
        var service = $(this).val();

        // Handle Generic API Key Field
        if (service === 'ipapi' || service === 'maxmind') {
            $('#api-key-field').slideUp();
        } else {
            $('#api-key-field').slideDown();
        }

        // Handle MaxMind Specific Field
        if (service === 'maxmind') {
            $('#maxmind-settings-field').slideDown();
        } else {
            $('#maxmind-settings-field').slideUp();
        }
    });

    // ⚙️ MaxMind Manual Update Handler (Sends license_key directly to AJAX)
    $('#update-maxmind-now').on('click', function() {
        var btn = $(this);
        var spinner = $('#maxmind-update-spinner');
        var res = $('#maxmind-update-result');
        var licenseKey = $('#advnews_maxmind_license_key').val();

        if (!licenseKey) {
            res.html('<span style="color:#d63638;">✘ <?php _e('Please enter a License Key first.', 'advnews-manager'); ?></span>');
            return;
        }

        btn.prop('disabled', true);
        spinner.addClass('is-active');
        res.html('<?php _e('Downloading & Decompressing...', 'advnews-manager'); ?>');

        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_update_maxmind_db',
                nonce: advnews_ajax.nonce,
                license_key: licenseKey // Send key directly to avoid needing to click "Save Changes" first
            },
            success: function(response) {
                if (response.success) {
                    res.html('<span style="color:#00a32a;">✔ ' + response.data.message + '</span>');
                    // Reload page after 2 seconds to update "Database Found" status
                    setTimeout(function(){ location.reload(); }, 2000);
                } else {
                    res.html('<span style="color:#d63638;">✘ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                res.html('<span style="color:#d63638;">✘ <?php _e('Connection failed or server timeout.', 'advnews-manager'); ?></span>');
            },
            complete: function() {
                btn.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });

    // Clear old tracking data
    $('#clear-old-tracking').on('click', function() {
        var days = $('#advnews_tracking_retention_days').val();
        if (confirm('<?php _e('Are you sure you want to clear all tracking data older than ', 'advnews-manager'); ?>' + days + ' <?php _e('days?', 'advnews-manager'); ?>')) {
            var button = $(this);
            var resultDiv = $('#tracking-result');
            button.prop('disabled', true);
            resultDiv.hide();
            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_clear_tracking_data',
                    days: days,
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.removeClass('error').addClass('updated')
                            .html('<p>' + response.data.message + '</p>').show();
                    } else {
                        resultDiv.removeClass('updated').addClass('error')
                            .html('<p>' + response.data.message + '</p>').show();
                    }
                },
                error: function() {
                    resultDiv.removeClass('updated').addClass('error')
                        .html('<p><?php _e('An error occurred.', 'advnews-manager'); ?></p>').show();
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        }
    });
});
</script>

<style>
.tracking-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}
.tracking-stats-grid .stat-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.stat-icon {
    width: 50px;
    height: 50px;
    background: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stat-icon .dashicons {
    font-size: 30px;
    width: 30px;
    height: 30px;
    color: #2271b1;
}
.stat-content {
    flex: 1;
}
.stat-value {
    font-size: 24px;
    font-weight: 600;
    line-height: 1.2;
    color: #1d2327;
}
.stat-label {
    font-size: 13px;
    color: #646970;
    margin-bottom: 3px;
}
.stat-trend {
    font-size: 11px;
    color: #00a32a;
}
.preview-box {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 20px;
    font-family: monospace;
    word-break: break-all;
}
.privacy-notice .notice {
    margin: 0 0 15px;
}
#tracking-result.updated {
    background: #d4edda;
    border-left: 4px solid #00a32a;
    padding: 15px;
}
#tracking-result.error {
    background: #f8d7da;
    border-left: 4px solid #d63638;
    padding: 15px;
}
</style>
