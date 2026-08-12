<?php
// admin/partials/campaigns-editor.php
if (!defined('ABSPATH')) exit;
$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$campaign_class = new AdvNews_Campaign();
$campaign = $campaign_id ? $campaign_class->get_campaign($campaign_id) : null;
global $wpdb;
$table_prefix = ADVNEWS_TABLE_PREFIX;
// Get all categories for checkboxes
$categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}{$table_prefix}categories ORDER BY name");
// Get assigned category IDs for this campaign
$assigned_category_ids = array();
if ($campaign) {
    if (!empty($campaign->category_ids)) {
        $assigned_category_ids = $campaign->category_ids;
    } elseif (!empty($campaign->category_id)) {
        // Fallback for legacy campaigns
        $assigned_category_ids = array($campaign->category_id);
    }
}
// Get templates
$templates = $wpdb->get_results("
    SELECT t.id, t.name, t.subject, GROUP_CONCAT(tc.category_id) as category_ids
    FROM {$wpdb->prefix}{$table_prefix}templates t
    LEFT JOIN {$wpdb->prefix}{$table_prefix}template_categories tc ON t.id = tc.template_id
    WHERE t.is_active = 1
    GROUP BY t.id
    ORDER BY t.name
");
// Check for messages
if (isset($_GET['message'])) {
    $message = sanitize_text_field($_GET['message']);
    $message_text = '';
    $message_class = 'notice-success';
    switch ($message) {
        case 'campaign_saved':
            $message_text = __('Campaign saved successfully.', 'advnews-manager');
            break;
        case 'campaign_updated':
            $message_text = __('Campaign updated successfully.', 'advnews-manager');
            break;
        case 'campaign_sent':
            $message_text = __('Campaign queued for sending.', 'advnews-manager');
            break;
        case 'error':
            $message_text = __('An error occurred. Please try again.', 'advnews-manager');
            $message_class = 'notice-error';
            break;
    }
    if ($message_text) {
        echo '<div class="notice ' . $message_class . ' is-dismissible"><p>' . esc_html($message_text) . '</p></div>';
    }
}
?>
<div class="wrap">
    <h1><?php echo $campaign_id ? __('Edit Campaign', 'advnews-manager') : __('Add New Campaign', 'advnews-manager'); ?></h1>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="campaign-form">
        <input type="hidden" name="action" value="advnews_save_campaign">
        <?php wp_nonce_field('advnews_save_campaign'); ?>
        <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>">

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content">

                    <!-- Campaign Details Box -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Campaign Details', 'advnews-manager'); ?></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th><label for="campaign_name"><?php _e('Campaign Name', 'advnews-manager'); ?> <span class="required">*</span></label></th>
                                    <td>
                                        <input type="text" id="campaign_name" name="name"
                                            value="<?php echo $campaign ? esc_attr($campaign->name) : ''; ?>"
                                            class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="subject"><?php _e('Subject Line', 'advnews-manager'); ?> <span class="required">*</span></label></th>
                                    <td>
                                        <input type="text" id="subject" name="subject"
                                            value="<?php echo $campaign ? esc_attr($campaign->subject) : ''; ?>"
                                            class="regular-text" required>
                                        <p class="description">
                                            <?php _e('Available merge tags:', 'advnews-manager'); ?>
                                            <code>[first_name]</code>, <code>[last_name]</code>, <code>[full_name]</code>,
                                            <code>[email]</code>, <code>[organization]</code>, <code>[current_date]</code>
                                        </p>
                                    </td>
                                </tr>

                                <!-- UPDATED: Multi-category Checkboxes -->
                                <tr>
                                    <th><label><?php _e('Categories', 'advnews-manager'); ?> <span class="required">*</span></label></th>
                                    <td>
                                        <div class="advnews-categories-checkbox-group" style="max-height: 200px; overflow-y: auto; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                                            <?php if (empty($categories)): ?>
                                                <p><?php _e('No categories found.', 'advnews-manager'); ?>
                                                    <a href="<?php echo admin_url('admin.php?page=advnews-categories&action=add'); ?>"><?php _e('Create one now', 'advnews-manager'); ?></a>
                                                </p>
                                            <?php else: ?>
                                                <?php foreach ($categories as $category): ?>
                                                    <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                                                        <input type="checkbox" name="category_ids[]" value="<?php echo esc_attr($category->id); ?>"
                                                            <?php checked(in_array($category->id, $assigned_category_ids)); ?>>
                                                        <span style="display: inline-block; width: 12px; height: 12px; background-color: <?php echo esc_attr($category->color); ?>; border-radius: 3px; margin-right: 5px;"></span>
                                                        <?php echo esc_html($category->name); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <p class="description">
                                            <?php _e('Select one or more categories. Subscribers in any of the selected categories will receive this campaign.', 'advnews-manager'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <!-- END UPDATED SECTION -->

                                <tr>
                                    <th><label for="template_id"><?php _e('Use Template', 'advnews-manager'); ?></label></th>
                                    <td>
                                        <select id="template_id" name="template_id" class="template-select">
                                            <option value=""><?php _e('— Start from scratch —', 'advnews-manager'); ?></option>
                                            <?php if (empty($templates)): ?>
                                                <option value="" disabled><?php _e('No templates available', 'advnews-manager'); ?></option>
                                            <?php else: ?>
                                                <?php foreach ($templates as $template):
                                                    $cat_ids = $template->category_ids ? explode(',', $template->category_ids) : array();
                                                    $selected = ($campaign && $campaign->template_id == $template->id) ? 'selected' : '';
                                                ?>
                                                    <option value="<?php echo esc_attr($template->id); ?>"
                                                        data-categories="<?php echo esc_attr(implode(',', $cat_ids)); ?>"
                                                        <?php echo $selected; ?>>
                                                        <?php echo esc_html($template->name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <p class="description">
                                            <?php _e('Select a template to pre-fill the email content. Template categories can preselect campaign categories without limiting your choices.', 'advnews-manager'); ?>
                                        </p>
                                        <div id="template-preview-loading" style="display:none; margin-top:10px;">
                                            <span class="spinner is-active"></span> <?php _e('Loading template...', 'advnews-manager'); ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="from_name"><?php _e('From Name (Optional)', 'advnews-manager'); ?></label></th>
                                    <td>
                                        <input type="text" id="from_name" name="from_name"
                                            value="<?php echo $campaign ? esc_attr($campaign->from_name) : ''; ?>"
                                            class="regular-text" placeholder="<?php echo esc_attr(get_option('advnews_from_name')); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="from_email"><?php _e('From Email (Optional)', 'advnews-manager'); ?></label></th>
                                    <td>
                                        <input type="email" id="from_email" name="from_email"
                                            value="<?php echo $campaign ? esc_attr($campaign->from_email) : ''; ?>"
                                            class="regular-text" placeholder="<?php echo esc_attr(get_option('advnews_from_email')); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="reply_to"><?php _e('Reply-To Email (Optional)', 'advnews-manager'); ?></label></th>
                                    <td>
                                        <input type="email" id="reply_to" name="reply_to"
                                            value="<?php echo $campaign ? esc_attr($campaign->reply_to) : ''; ?>"
                                            class="regular-text" placeholder="<?php echo esc_attr(get_option('advnews_reply_to')); ?>">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Email Content Box -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Email Content', 'advnews-manager'); ?></h2>
                        <div class="inside">
                            <?php
                            $content = $campaign ? $campaign->content : '';
                            wp_editor($content, 'content', array(
                                'textarea_name' => 'content',
                                'editor_height' => 400,
                                'media_buttons' => true,
                                'teeny' => false,
                                'tinymce' => array(
                                    'setup' => 'function(ed) {
                                        ed.on("change", function(e) {
                                            ed.save();
                                        });
                                    }',
                                    'extended_valid_elements' => 'table[align|bgcolor|border|cellpadding|cellspacing|width|height|style|class|id],tr[align|bgcolor|valign|style|class|id],td[align|bgcolor|valign|colspan|rowspan|width|height|style|class|id],th[align|bgcolor|valign|colspan|rowspan|width|height|style|class|id],*[align|bgcolor|width|height],br[*],span[*]',
                                    'valid_children' => '+body[style],+span[style],+p[style],+div[style],+br',
                                    'valid_elements' => '*[*]',
                                    'cleanup_on_startup' => false,
                                    'convert_fonts_to_spans' => true,
                                    'remove_script_host' => false,
                                    'remove_trailing_brs' => false,
                                    'force_br_newlines' => true,
                                    'force_p_newlines' => false,
                                    'forced_root_block' => '',
                                    'paste_remove_spans' => false,
                                    'paste_remove_styles' => false,
                                    'paste_retain_style_properties' => 'all',
                                    'paste_word_valid_elements' => '*[*]',
                                    'paste_auto_cleanup_on_paste' => false,
                                    'remove_empty_elements' => false,
                                    'remove_empty_span' => false,
                                    'preformatted' => true,
                                    'convert_newlines_to_brs' => true,
                                    'remove_linebreaks' => false,
                                    'apply_source_formatting' => false,
                                ),
                                'quicktags' => array(
                                    'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'
                                )
                            ));
                            ?>
                        </div>
                    </div>
                </div>

                <div id="postbox-container-1" class="postbox-container">

                    <!-- Publish Box -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Publish', 'advnews-manager'); ?></h2>
                        <div class="inside">
                            <div class="submitbox">
                                <div id="minor-publishing">
                                    <div class="misc-pub-section">
                                        <label for="status"><?php _e('Status:', 'advnews-manager'); ?></label>
                                        <select id="status" name="status">
                                            <option value="draft" <?php selected($campaign ? $campaign->status : 'draft', 'draft'); ?>>
                                                <?php _e('Draft', 'advnews-manager'); ?>
                                            </option>
                                            <option value="scheduled" <?php selected($campaign ? $campaign->status : '', 'scheduled'); ?>>
                                                <?php _e('Scheduled', 'advnews-manager'); ?>
                                            </option>
                                            <option value="sending" <?php selected($campaign ? $campaign->status : '', 'sending'); ?>>
                                                <?php _e('Sending', 'advnews-manager'); ?>
                                            </option>
                                            <option value="sent" <?php selected($campaign ? $campaign->status : '', 'sent'); ?>>
                                                <?php _e('Sent', 'advnews-manager'); ?>
                                            </option>
                                            <option value="paused" <?php selected($campaign ? $campaign->status : '', 'paused'); ?>>
                                                <?php _e('Paused', 'advnews-manager'); ?>
                                            </option>
                                        </select>
                                    </div>
                                    <div class="misc-pub-section">
                                        <label for="scheduled_for"><?php _e('Schedule for:', 'advnews-manager'); ?></label>
                                        <input type="datetime-local" id="scheduled_for" name="scheduled_for"
                                            value="<?php echo $campaign && $campaign->scheduled_for ? esc_attr(get_date_from_gmt($campaign->scheduled_for, 'Y-m-d\TH:i')) : ''; ?>"
                                            class="advnews-datetimepicker" step="1">
                                        <p class="description">
                                            <?php _e('Click the calendar icon to select date and time.', 'advnews-manager'); ?>
                                        </p>
                                    </div>
                                    <div class="misc-pub-section">
                                        <label for="priority"><?php _e('Priority:', 'advnews-manager'); ?></label>
                                        <select id="priority" name="priority">
                                            <option value="low" <?php selected($campaign ? $campaign->priority : 'normal', 'low'); ?>>
                                                <?php _e('Low', 'advnews-manager'); ?>
                                            </option>
                                            <option value="normal" <?php selected($campaign ? $campaign->priority : 'normal', 'normal'); ?>>
                                                <?php _e('Normal', 'advnews-manager'); ?>
                                            </option>
                                            <option value="high" <?php selected($campaign ? $campaign->priority : 'normal', 'high'); ?>>
                                                <?php _e('High', 'advnews-manager'); ?>
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div id="major-publishing-actions">
                                    <div id="publishing-action">
                                        <input type="submit" name="save" class="button button-primary button-large"
                                            value="<?php _e('Save Campaign', 'advnews-manager'); ?>">
                                        <input type="submit" name="send_now" class="button button-secondary"
                                            value="<?php echo $campaign && $campaign->status === 'sent' ? esc_attr__('Queue New Recipients', 'advnews-manager') : esc_attr__('Send Now', 'advnews-manager'); ?>">
                                    </div>
                                    <div class="clear"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tracking Settings Box -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Tracking Settings', 'advnews-manager'); ?></h2>
                        <div class="inside">
                            <p>
                                <label>
                                    <input type="checkbox" name="track_opens" value="1"
                                        <?php checked($campaign ? $campaign->track_opens : 1, 1); ?>>
                                    <?php _e('Track email opens', 'advnews-manager'); ?>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="track_clicks" value="1"
                                        <?php checked($campaign ? $campaign->track_clicks : 1, 1); ?>>
                                    <?php _e('Track link clicks', 'advnews-manager'); ?>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="respect_cooldown" value="1"
                                        <?php checked($campaign ? $campaign->respect_cooldown : 1, 1); ?>>
                                    <?php _e('Respect cooldown period', 'advnews-manager'); ?>
                                    <span class="dashicons dashicons-editor-help" title="<?php _e('Ensure subscribers wait the configured number of days between emails', 'advnews-manager'); ?>"></span>
                                </label>
                            </p>
                        </div>
                    </div>

                    <!-- Recipient Estimate Box -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Recipient Estimate', 'advnews-manager'); ?></h2>
                        <div class="inside">
                            <div id="recipient-count-display">
                                <?php if ($campaign): ?>
                                    <p style="font-size: 24px; font-weight: bold; text-align: center; color: #2271b1;">
                                        <?php echo esc_html($campaign->total_recipients ?? 0); ?>
                                    </p>
                                    <p style="text-align: center;"><?php _e('active subscribers (unique)', 'advnews-manager'); ?></p>
                                <?php else: ?>
                                    <p style="text-align: center; color: #999;">
                                        <?php _e('Select categories to see estimated recipients', 'advnews-manager'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Test Send Box REMOVED as requested -->

                </div>
            </div>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {

    function filterTemplatesByCategory() {
        $('#template_id').find('option').show();
    }

    function applyTemplateCategories(categoryIds) {
        if (!categoryIds || !categoryIds.length) {
            return;
        }

        $('input[name="category_ids[]"]').prop('checked', false);
        categoryIds.forEach(function(categoryId) {
            $('input[name="category_ids[]"][value="' + categoryId + '"]').prop('checked', true);
        });
        updateRecipientCount();
    }

    // Update recipient count via AJAX for multiple categories
    function updateRecipientCount() {
        var selectedCategories = $('input[name="category_ids[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedCategories.length === 0) {
            $('#recipient-count-display').html(
                '<p style="text-align: center; color: #999;"><?php _e('Select categories to see estimated recipients', 'advnews-manager'); ?></p>'
            );
            return;
        }

        // Use a custom AJAX action to count unique subscribers across multiple categories
        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_count_recipients_multiple',
                category_ids: selectedCategories,
                nonce: advnews_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#recipient-count-display').html(
                        '<p style="font-size: 24px; font-weight: bold; text-align: center; color: #2271b1;">' +
                        response.data.count +
                        '</p><p style="text-align: center;"><?php _e('active subscribers (unique)', 'advnews-manager'); ?></p>'
                    );
                } else {
                     $('#recipient-count-display').html(
                        '<p style="font-size: 18px; font-weight: bold; text-align: center; color: #2271b1;">' +
                        '<?php _e('Multiple Categories Selected', 'advnews-manager'); ?>' +
                        '</p>'
                    );
                }
            },
            error: function() {
                 $('#recipient-count-display').html(
                    '<p style="font-size: 18px; font-weight: bold; text-align: center; color: #2271b1;">' +
                    '<?php _e('Multiple Categories Selected', 'advnews-manager'); ?>' +
                    '</p>'
                );
            }
        });
    }

    // Bind change event to checkboxes
    $('input[name="category_ids[]"]').on('change', function() {
        updateRecipientCount();
    });

    // Initialize on page load
    if ($('input[name="category_ids[]"]:checked').length > 0) {
        filterTemplatesByCategory();
        updateRecipientCount();
    }

    // Load template content
    $('#template_id').on('change', function() {
        var templateId = $(this).val();
        if (!templateId) return;

        var editor = tinyMCE.get('content');
        var currentContent = editor ? editor.getContent() : $('#content').val();

        if (currentContent.trim() !== '') {
            if (!confirm(advnews_ajax.i18n.confirm_template_load)) {
                $(this).val('');
                return;
            }
        }

        $('#template-preview-loading').show();
        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_get_template',
                template_id: templateId,
                nonce: advnews_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var responseCategories = response.data.category_ids || [];
                    if (!responseCategories.length) {
                        var optionCategories = $('#template_id option:selected').data('categories');
                        responseCategories = optionCategories ? optionCategories.toString().split(',') : [];
                    }
                    applyTemplateCategories(responseCategories.filter(function(id) { return id !== '' && id !== '0'; }));

                    if (!$('#subject').val().trim() && response.data.subject) {
                        $('#subject').val(response.data.subject);
                    }

                    if (editor) {
                        editor.setContent(response.data.content);
                    } else {
                        $('#content').val(response.data.content);
                    }
                }
            },
            complete: function() {
                $('#template-preview-loading').hide();
            }
        });
    });

    // Send test email code REMOVED as requested

    // Form validation
    $('#campaign-form').on('submit', function(e) {
        var name = $('#campaign_name').val();
        var subject = $('#subject').val();
        var categories = $('input[name="category_ids[]"]:checked').length;

        if (!name || !subject || categories === 0) {
            e.preventDefault();
            alert('<?php _e('Please fill in all required fields and select at least one category.', 'advnews-manager'); ?>');
            return false;
        }
    });
});
</script>

<style>
.required {
    color: #d63638;
}
/* Removed test_result styles as the element is gone */
#template-preview-loading {
    display: flex;
    align-items: center;
    gap: 10px;
}
#template-preview-loading .spinner {
    float: none;
    margin: 0;
}
.advnews-datetimepicker {
    width: 100%;
    max-width: 250px;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 3px;
}
.advnews-datetimepicker::-webkit-calendar-picker-indicator {
    cursor: pointer;
    padding: 5px;
}
</style>
