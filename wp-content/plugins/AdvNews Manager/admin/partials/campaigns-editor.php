<?php
// admin/partials/campaigns-editor.php
if (!defined('ABSPATH')) exit;
$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$campaign_class = new AdvNews_Campaign();
$campaign = $campaign_id ? $campaign_class->get_campaign($campaign_id) : null;
$can_add_manual_recipient = $campaign && in_array($campaign->status, array('sent', 'sending', 'scheduled', 'paused'), true);
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
        case 'campaign_scheduled':
            $message_text = __('Campaign scheduled successfully.', 'advnews-manager');
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

                                <tr>
                                    <th><label><?php _e('Categories', 'advnews-manager'); ?> <span class="required">*</span></label></th>
                                    <td>
                                        <div class="advnews-multiselect" data-placeholder="<?php esc_attr_e('Select categories', 'advnews-manager'); ?>" data-selected-singular="<?php esc_attr_e('category selected', 'advnews-manager'); ?>" data-selected-plural="<?php esc_attr_e('categories selected', 'advnews-manager'); ?>">
                                            <button type="button" class="advnews-multiselect-toggle" aria-haspopup="listbox" aria-expanded="false">
                                                <span class="advnews-multiselect-label"><?php _e('Select categories', 'advnews-manager'); ?></span>
                                                <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                            </button>
                                            <div class="advnews-multiselect-menu" role="listbox" aria-multiselectable="true">
                                                <?php if (empty($categories)): ?>
                                                    <p class="advnews-multiselect-empty">
                                                        <?php _e('No categories found.', 'advnews-manager'); ?>
                                                        <a href="<?php echo admin_url('admin.php?page=advnews-categories&action=add'); ?>"><?php _e('Create one now', 'advnews-manager'); ?></a>
                                                    </p>
                                                <?php else: ?>
                                                    <label class="advnews-multiselect-option advnews-multiselect-select-all">
                                                        <input type="checkbox" class="advnews-multiselect-select-all-input">
                                                        <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                                        <span class="advnews-multiselect-text"><?php _e('Select / unselect all categories', 'advnews-manager'); ?></span>
                                                    </label>
                                                    <?php foreach ($categories as $category): ?>
                                                        <label class="advnews-multiselect-option">
                                                            <input type="checkbox" name="category_ids[]" value="<?php echo esc_attr($category->id); ?>"
                                                                <?php checked(in_array($category->id, $assigned_category_ids)); ?>>
                                                            <span class="advnews-multiselect-check" aria-hidden="true"></span>
                                                            <span class="advnews-category-swatch" style="background-color: <?php echo esc_attr($category->color); ?>;" aria-hidden="true"></span>
                                                            <span class="advnews-multiselect-text"><?php echo esc_html($category->name); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($categories)): ?>
                                            <div class="advnews-selected-summary" aria-live="polite"></div>
                                            <button type="button" class="button-link advnews-clear-categories"><?php _e('Clear selected categories', 'advnews-manager'); ?></button>
                                        <?php endif; ?>
                                        <p class="description">
                                            <?php _e('Click one or more categories. Subscribers in any selected category will receive this campaign once.', 'advnews-manager'); ?>
                                        </p>
                                    </td>
                                </tr>

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
                                    'force_br_newlines' => false,
                                    'force_p_newlines' => true,
                                    'forced_root_block' => 'p',
                                    'paste_remove_spans' => false,
                                    'paste_remove_styles' => false,
                                    'paste_retain_style_properties' => 'all',
                                    'paste_word_valid_elements' => '*[*]',
                                    'paste_auto_cleanup_on_paste' => false,
                                    'remove_empty_elements' => false,
                                    'remove_empty_span' => false,
                                    'preformatted' => false,
                                    'convert_newlines_to_brs' => false,
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
                                        <input type="submit" name="schedule_campaign" class="button button-secondary"
                                            value="<?php esc_attr_e('Schedule a Message', 'advnews-manager'); ?>">
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

                    <?php if ($can_add_manual_recipient): ?>
                        <!-- Manual Recipient Queue Box -->
                        <div class="postbox">
                            <h2 class="hndle"><?php _e('Add Recipient to Queue', 'advnews-manager'); ?></h2>
                            <div class="inside">
                                <p>
                                    <input type="email"
                                           id="edit-recipient-email"
                                           class="widefat"
                                           placeholder="<?php esc_attr_e('subscriber@example.com', 'advnews-manager'); ?>">
                                </p>
                                <button type="button" class="button button-secondary button-block" id="add-recipient-to-campaign">
                                    <?php _e('Add to Queue', 'advnews-manager'); ?>
                                </button>
                                <p class="description">
                                    <?php _e('Adds an existing active subscriber to this campaign queue and respects cooldown settings.', 'advnews-manager'); ?>
                                </p>
                                <div id="edit-add-recipient-result" style="display:none; margin-top:10px;"></div>
                            </div>
                        </div>
                    <?php endif; ?>

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
    function getAdvNewsMultiSelectOptions($select) {
        return $select.find('input[type="checkbox"]').not(':disabled').not('.advnews-multiselect-select-all-input');
    }

    function updateAdvNewsMultiSelect($select) {
        var options = getAdvNewsMultiSelectOptions($select);
        var checked = options.filter(':checked');
        var label = $select.find('.advnews-multiselect-label');
        var summary = $select.next('.advnews-selected-summary');
        var placeholder = $select.data('placeholder') || '';
        var singular = $select.data('selected-singular') || 'selected';
        var plural = $select.data('selected-plural') || 'selected';
        var selectAll = $select.find('.advnews-multiselect-select-all-input');
        var names = checked.map(function() {
            return $.trim($(this).closest('.advnews-multiselect-option').find('.advnews-multiselect-text').first().text());
        }).get();

        if (selectAll.length) {
            selectAll.prop('checked', options.length > 0 && checked.length === options.length);
            selectAll.prop('indeterminate', checked.length > 0 && checked.length < options.length);
        }

        if (!checked.length) {
            label.text(placeholder);
            summary.empty();
            return;
        }

        if (checked.length === 1) {
            label.text(names[0]);
        } else {
            label.text(checked.length + ' ' + plural);
        }

        summary.text(names.join(', '));
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
        var $select = $(this).closest('.advnews-multiselect');
        if ($(this).hasClass('advnews-multiselect-select-all-input')) {
            getAdvNewsMultiSelectOptions($select).prop('checked', $(this).is(':checked'));
        }
        updateAdvNewsMultiSelect($select);
        updateRecipientCount();
    });

    $('.advnews-clear-categories').on('click', function() {
        var $select = $(this).siblings('.advnews-multiselect');
        getAdvNewsMultiSelectOptions($select).prop('checked', false);
        updateAdvNewsMultiSelect($select);
        updateRecipientCount();
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
        $('.advnews-multiselect').each(function() {
            updateAdvNewsMultiSelect($(this));
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

    $('#add-recipient-to-campaign').on('click', function() {
        var button = $(this);
        var email = $('#edit-recipient-email').val().trim();
        var result = $('#edit-add-recipient-result');

        if (!email) {
            result.removeClass('updated error').addClass('error')
                .html('<p><?php echo esc_js(__('Please enter a subscriber email address.', 'advnews-manager')); ?></p>')
                .show();
            return;
        }

        button.prop('disabled', true).text('<?php echo esc_js(__('Adding...', 'advnews-manager')); ?>');
        result.hide();

        $.post(advnews_ajax.ajax_url, {
            action: 'advnews_add_campaign_recipient',
            campaign_id: <?php echo intval($campaign_id); ?>,
            email: email,
            nonce: advnews_ajax.nonce
        }, function(response) {
            if (response.success) {
                result.removeClass('error').addClass('updated')
                    .html('<p>' + response.data.message + '</p>')
                    .show();
                $('#edit-recipient-email').val('');
                setTimeout(function() {
                    location.reload();
                }, 900);
            } else {
                result.removeClass('updated').addClass('error')
                    .html('<p>' + response.data.message + '</p>')
                    .show();
            }
        }).fail(function() {
            result.removeClass('updated').addClass('error')
                .html('<p><?php echo esc_js(__('An error occurred. Please try again.', 'advnews-manager')); ?></p>')
                .show();
        }).always(function() {
            button.prop('disabled', false).text('<?php echo esc_js(__('Add to Queue', 'advnews-manager')); ?>');
        });
    });

    // Form validation
    $('#campaign-form').on('submit', function(e) {
        if (typeof tinyMCE !== 'undefined') {
            var contentEditor = tinyMCE.get('content');
            if (contentEditor) {
                contentEditor.save();
            }
        }

        var name = $('#campaign_name').val();
        var subject = $('#subject').val();
        var categories = $('input[name="category_ids[]"]:checked').length;
        var submitter = e.originalEvent && e.originalEvent.submitter ? e.originalEvent.submitter : document.activeElement;
        var isSchedule = submitter && submitter.name === 'schedule_campaign';

        if (!name || !subject || categories === 0) {
            e.preventDefault();
            alert('<?php _e('Please fill in all required fields and select at least one category.', 'advnews-manager'); ?>');
            return false;
        }

        if (isSchedule && !$('#scheduled_for').val()) {
            e.preventDefault();
            alert('<?php _e('Select a date and time before scheduling this campaign.', 'advnews-manager'); ?>');
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
#publishing-action {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
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
.advnews-multiselect-select-all {
    border-bottom: 1px solid #dcdcde;
    font-weight: 600;
    margin-bottom: 4px;
    padding-bottom: 8px;
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
.advnews-category-swatch {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    flex: 0 0 auto;
}
.advnews-multiselect-text {
    line-height: 1.3;
}
.advnews-multiselect-empty {
    margin: 6px;
}
.advnews-selected-summary {
    max-width: 420px;
    margin-top: 6px;
    color: #50575e;
    font-size: 12px;
    line-height: 1.4;
}
.advnews-clear-categories {
    margin-top: 4px;
}
</style>
