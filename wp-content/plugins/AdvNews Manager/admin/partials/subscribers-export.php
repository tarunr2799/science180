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
                            <div class="advnews-multiselect" data-placeholder="<?php esc_attr_e('Select fields', 'advnews-manager'); ?>" data-selected-plural="<?php esc_attr_e('fields selected', 'advnews-manager'); ?>">
                                <button type="button" id="export_fields" class="advnews-multiselect-toggle" aria-haspopup="listbox" aria-expanded="false">
                                    <span class="advnews-multiselect-label"><?php _e('Select fields', 'advnews-manager'); ?></span>
                                    <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                </button>
                                <div class="advnews-multiselect-menu" role="listbox" aria-multiselectable="true">
                                    <label class="advnews-multiselect-option is-disabled">
                                        <input type="checkbox" name="fields[]" value="email" checked disabled>
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('Email (required)', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="first_name" checked>
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('First Name', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="last_name" checked>
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('Last Name', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="organization" checked>
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('Organization', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="title" checked>
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('Title/Role', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="website_url" checked>
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('URL/Website', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="description" checked>
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('Description', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="country" checked>
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('Country', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="categories">
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('Categories', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="status">
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('Status', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="subscribed_date">
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('Subscribed Date', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="open_rate">
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('Open Rate', 'advnews-manager'); ?></span>
                                    </label>
                                    <label class="advnews-multiselect-option">
                                        <input type="checkbox" name="fields[]" value="click_rate">
                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                        <span class="advnews-multiselect-text"><?php _e('Click Rate', 'advnews-manager'); ?></span>
                                    </label>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="export_format"><?php _e('Export Format:', 'advnews-manager'); ?></label>
                        </th>
                        <td>
                            <select id="export_format" name="format">
                                <option value="csv"><?php _e('CSV (Comma Separated Values)', 'advnews-manager'); ?></option>
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
    function updateAdvNewsMultiSelect($select) {
        var checked = $select.find('input[type="checkbox"]:checked');
        var label = $select.find('.advnews-multiselect-label');
        var placeholder = $select.data('placeholder') || '';
        var plural = $select.data('selected-plural') || 'selected';

        if (checked.length === 0) {
            label.text(placeholder);
            return;
        }

        if (checked.length === 1) {
            label.text($.trim(checked.closest('.advnews-multiselect-option').find('.advnews-multiselect-text').first().text()));
            return;
        }

        label.text(checked.length + ' ' + plural);
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
<style>
.advnews-multiselect {
    position: relative;
    max-width: 420px;
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
.advnews-multiselect-toggle:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: 2px solid transparent;
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
.advnews-multiselect-option.is-disabled {
    color: #646970;
    cursor: default;
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
.advnews-multiselect-text {
    line-height: 1.3;
}
</style>
