<?php
// admin/partials/settings-general.php
if (!defined('ABSPATH')) exit;
?>

<h2><?php _e('General Settings', 'advnews-manager'); ?></h2>

<table class="form-table">
    <tr>
        <th scope="row">
            <label for="advnews_company_name"><?php _e('Company Name', 'advnews-manager'); ?></label>
        </th>
        <td>
            <input type="text" id="advnews_company_name" name="advnews_company_name"
                   value="<?php echo esc_attr(get_option('advnews_company_name', get_bloginfo('name'))); ?>"
                   class="regular-text">
            <p class="description"><?php _e('Your company name used in email footers.', 'advnews-manager'); ?></p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="advnews_from_name"><?php _e('From Name', 'advnews-manager'); ?></label>
        </th>
        <td>
            <input type="text" id="advnews_from_name" name="advnews_from_name"
                   value="<?php echo esc_attr(get_option('advnews_from_name', get_bloginfo('name'))); ?>"
                   class="regular-text">
            <p class="description"><?php _e('The name emails will be sent from.', 'advnews-manager'); ?></p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="advnews_from_email"><?php _e('From Email', 'advnews-manager'); ?></label>
        </th>
        <td>
            <input type="email" id="advnews_from_email" name="advnews_from_email"
                   value="<?php echo esc_attr(get_option('advnews_from_email', get_bloginfo('admin_email'))); ?>"
                   class="regular-text">
            <p class="description"><?php _e('The email address emails will be sent from.', 'advnews-manager'); ?></p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="advnews_reply_to"><?php _e('Reply-To Email', 'advnews-manager'); ?></label>
        </th>
        <td>
            <input type="email" id="advnews_reply_to" name="advnews_reply_to"
                   value="<?php echo esc_attr(get_option('advnews_reply_to', get_bloginfo('admin_email'))); ?>"
                   class="regular-text">
            <p class="description"><?php _e('The email address for replies.', 'advnews-manager'); ?></p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <label for="advnews_timezone"><?php _e('Timezone', 'advnews-manager'); ?></label>
        </th>
        <td>
            <?php
            $current_timezone = get_option('advnews_timezone', wp_timezone_string());
            $timezones = timezone_identifiers_list();
            ?>
            <select id="advnews_timezone" name="advnews_timezone">
                <?php foreach ($timezones as $timezone): ?>
                    <option value="<?php echo esc_attr($timezone); ?>" <?php selected($current_timezone, $timezone); ?>>
                        <?php echo esc_html(str_replace('_', ' ', $timezone)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Your local timezone for scheduling.', 'advnews-manager'); ?></p>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <?php _e('Show Credit Link', 'advnews-manager'); ?>
        </th>
        <td>
            <label>
                <input type="checkbox" name="advnews_show_credit_link" value="1"
                       <?php checked(get_option('advnews_show_credit_link', true), 1); ?>>
                <?php _e('Show "Powered by AdvNews" link in email footers', 'advnews-manager'); ?>
            </label>
        </td>
    </tr>

    <tr>
        <th scope="row">
            <?php _e('Debug Mode', 'advnews-manager'); ?>
        </th>
        <td>
            <label>
                <input type="checkbox" name="advnews_enable_debug_log" value="1"
                       <?php checked(get_option('advnews_enable_debug_log', false), 1); ?>>
                <?php _e('Enable debug logging', 'advnews-manager'); ?>
            </label>
            <p class="description"><?php _e('Log plugin events to WordPress debug log.', 'advnews-manager'); ?></p>
        </td>
    </tr>
</table>
