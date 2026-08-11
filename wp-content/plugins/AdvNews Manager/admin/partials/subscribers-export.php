<?php
// admin/partials/subscribers-export.php
if (!defined('ABSPATH')) exit;

$categories = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}{$this->table_prefix}categories ORDER BY name");
?>

<div class="wrap">
    <h1><?php _e('Export Subscribers', 'advnews-manager'); ?></h1>

    <div class="postbox">
        <div class="inside">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="advnews-export-form">
                <input type="hidden" name="action" value="advnews_export_subscribers">
                <?php AdvNews_Security::create_nonce_field('advnews_export_subscribers'); ?>

                <h3><?php _e('Export Filters', 'advnews-manager'); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="export_status"><?php _e('Status:', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <select id="export_status" name="status">
                                <option value=""><?php _e('All Statuses', 'advnews-manager'); ?></option>
                                <option value="active"><?php _e('Active', 'advnews-manager'); ?></option>
                                <option value="unsubscribed"><?php _e('Unsubscribed', 'advnews-manager'); ?></option>
                                <option value="bounced"><?php _e('Bounced', 'advnews-manager'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="export_category"><?php _e('Category:', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <select id="export_category" name="category_id">
                                <option value=""><?php _e('All Categories', 'advnews-manager'); ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->id); ?>">
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="export_search"><?php _e('Search:', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="export_search" name="search"
                                   placeholder="<?php _e('Search by email or name...', 'advnews-manager'); ?>"
                                   class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="export_date_from"><?php _e('Date Range:', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="export_date_from" name="date_from"
                                   placeholder="<?php _e('From', 'advnews-manager'); ?>">
                            <input type="date" id="export_date_to" name="date_to"
                                   placeholder="<?php _e('To', 'advnews-manager'); ?>">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php _e('Fields to Export:', 'advnews-manager'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="fields[]" value="email" checked disabled>
                                <?php _e('Email (required)', 'advnews-manager'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="fields[]" value="first_name" checked>
                                <?php _e('First Name', 'advnews-manager'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="fields[]" value="last_name" checked>
                                <?php _e('Last Name', 'advnews-manager'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="fields[]" value="organization" checked>
                                <?php _e('Organization', 'advnews-manager'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="fields[]" value="categories">
                                <?php _e('Categories', 'advnews-manager'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="fields[]" value="status">
                                <?php _e('Status', 'advnews-manager'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="fields[]" value="subscribed_date">
                                <?php _e('Subscribed Date', 'advnews-manager'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="fields[]" value="open_rate">
                                <?php _e('Open Rate', 'advnews-manager'); ?>
                            </label><br>

                            <label>
                                <input type="checkbox" name="fields[]" value="click_rate">
                                <?php _e('Click Rate', 'advnews-manager'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="export_format"><?php _e('Export Format:', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <select id="export_format" name="format">
                                <option value="csv"><?php _e('CSV (Comma Separated Values)', 'advnews-manager'); ?></option>
                                <option value="json"><?php _e('JSON', 'advnews-manager'); ?></option>
                                <option value="excel"><?php _e('Excel (XLSX)', 'advnews-manager'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="export_filename"><?php _e('Filename (optional):', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="export_filename" name="filename"
                                   placeholder="<?php _e('subscribers-export', 'advnews-manager'); ?>"
                                   class="regular-text">
                            <p class="description">
                                <?php _e('If left empty, a timestamp will be added automatically.', 'advnews-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3><?php _e('Schedule Export (Optional)', 'advnews-manager'); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="schedule_export"><?php _e('Schedule:', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="schedule_export" name="schedule_export" value="1">
                                <?php _e('Schedule this export to run automatically', 'advnews-manager'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr class="schedule-options" style="display:none;">
                        <th scope="row">
                            <label for="schedule_frequency"><?php _e('Frequency:', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <select id="schedule_frequency" name="schedule_frequency">
                                <option value="daily"><?php _e('Daily', 'advnews-manager'); ?></option>
                                <option value="weekly"><?php _e('Weekly', 'advnews-manager'); ?></option>
                                <option value="monthly"><?php _e('Monthly', 'advnews-manager'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr class="schedule-options" style="display:none;">
                        <th scope="row">
                            <label for="schedule_email"><?php _e('Send to Email:', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="schedule_email" name="schedule_email"
                                   value="<?php echo esc_attr(get_option('admin_email')); ?>"
                                   class="regular-text">
                            <p class="description">
                                <?php _e('The exported file will be emailed to this address.', 'advnews-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e('Export Now', 'advnews-manager'); ?>">
                    <button type="button" class="button" id="preview-export"><?php _e('Preview First 10 Rows', 'advnews-manager'); ?></button>
                </p>
            </form>

            <div id="export-preview" style="display:none; margin-top:20px;">
                <h3><?php _e('Preview (First 10 Rows)', 'advnews-manager'); ?></h3>
                <div class="preview-table-container" style="overflow-x:auto;">
                    <table class="wp-list-table widefat fixed striped" id="preview-table">
                        <thead>
                            <tr>
                                <th><?php _e('Email', 'advnews-manager'); ?></th>
                                <th><?php _e('Name', 'advnews-manager'); ?></th>
                                <th><?php _e('Organization', 'advnews-manager'); ?></th>
                                <th><?php _e('Status', 'advnews-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4"><?php _e('Click preview to load data...', 'advnews-manager'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide schedule options
    $('#schedule_export').on('change', function() {
        if ($(this).is(':checked')) {
            $('.schedule-options').show();
        } else {
            $('.schedule-options').hide();
        }
    });

    // Preview export
    $('#preview-export').on('click', function() {
        var button = $(this);
        var originalText = button.text();

        button.prop('disabled', true).text('<?php _e('Loading...', 'advnews-manager'); ?>');

        var formData = $('#advnews-export-form').serialize();
        formData += '&action=advnews_preview_export&nonce=' + advnews_ajax.nonce;

        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    var tbody = $('#preview-table tbody');
                    tbody.empty();

                    if (response.data.length === 0) {
                        tbody.append('<tr><td colspan="4"><?php _e('No subscribers found.', 'advnews-manager'); ?></td></tr>');
                    } else {
                        $.each(response.data, function(i, row) {
                            tbody.append(
                                '<tr>' +
                                '<td>' + (row.email || '') + '</td>' +
                                '<td>' + (row.first_name || '') + ' ' + (row.last_name || '') + '</td>' +
                                '<td>' + (row.organization || '') + '</td>' +
                                '<td>' + (row.status || '') + '</td>' +
                                '</tr>'
                            );
                        });
                    }

                    $('#export-preview').show();
                } else {
                    alert(response.data.message || '<?php _e('Error loading preview.', 'advnews-manager'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('An error occurred.', 'advnews-manager'); ?>');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>
