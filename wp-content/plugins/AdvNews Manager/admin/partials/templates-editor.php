<?php
// admin/partials/templates-editor.php
if (!defined('ABSPATH')) exit;

$template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$template = null;
$template_categories = array();

if ($template_id) {
    global $wpdb;
    $table_prefix = ADVNEWS_TABLE_PREFIX;
    $template = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}{$table_prefix}templates WHERE id = %d",
        $template_id
    ));

    // Get template categories
    $template_categories = $wpdb->get_col($wpdb->prepare(
        "SELECT category_id FROM {$wpdb->prefix}{$table_prefix}template_categories WHERE template_id = %d",
        $template_id
    ));
}

// Get all categories
$category_class = new AdvNews_Category();
$categories = $category_class->get_all_categories();
?>
<div class="wrap advnews-template-editor">
    <h1 class="wp-heading-inline">
        <?php echo $template_id ? __('Edit Template', 'advnews-manager') : __('Add New Template', 'advnews-manager'); ?>
    </h1>
    <?php if ($template_id): ?>
        <a href="<?php echo admin_url('admin.php?page=advnews-templates&action=preview&id=' . $template_id); ?>"
           class="page-title-action"
           target="_blank">
            <?php _e('Preview', 'advnews-manager'); ?>
        </a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="template-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="advnews_save_template">
        <?php wp_nonce_field('advnews_save_template', '_wpnonce', false, true); ?>
        <input type="hidden" name="template_id" value="<?php echo esc_attr($template_id); ?>">

        <div class="advnews-editor-wrapper">
            <!-- Main Content Area -->
            <div class="advnews-editor-main">
                <div class="postbox">
                    <h2 class="hndle"><span><?php _e('Template Details', 'advnews-manager'); ?></span></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="template_name"><?php _e('Template Name', 'advnews-manager'); ?> <span style="color:red">*</span></label>
                                </th>
                                <td>
                                    <input type="text"
                                           id="template_name"
                                           name="template_name"
                                           value="<?php echo esc_attr($template ? $template->name : ''); ?>"
                                           class="regular-text"
                                           required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="template_subject"><?php _e('Default Subject Line', 'advnews-manager'); ?> <span style="color:red">*</span></label>
                                </th>
                                <td>
                                    <input type="text"
                                           id="template_subject"
                                           name="template_subject"
                                           value="<?php echo esc_attr($template ? $template->subject : ''); ?>"
                                           class="large-text"
                                           required>
                                    <p class="description">
                                        <?php _e('Default subject line. Can be overridden when creating campaigns. Available merge tags:', 'advnews-manager'); ?>
                                        <code>[first_name]</code>, <code>[last_name]</code>, <code>[full_name]</code>, <code>[email]</code>, <code>[current_date]</code>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e('Categories', 'advnews-manager'); ?></label>
                                </th>
                                <td>
                                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                                        <?php if (empty($categories)): ?>
                                            <p class="description"><?php _e('No categories found. Please create categories first.', 'advnews-manager'); ?></p>
                                        <?php else: ?>
                                            <?php foreach ($categories as $category): ?>
                                                <div style="margin-bottom: 8px;">
                                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                                        <input type="checkbox"
                                                               name="template_categories[]"
                                                               value="<?php echo esc_attr($category->id); ?>"
                                                               <?php echo in_array($category->id, $template_categories) ? 'checked' : ''; ?>
                                                               style="margin-right: 8px;">
                                                        <span style="display: inline-block; width: 12px; height: 12px; background-color: <?php echo esc_attr($category->color); ?>; border-radius: 3px; margin-right: 8px;"></span>
                                                        <?php echo esc_html($category->name); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description"><?php _e('Select one or more categories this template should be available for.', 'advnews-manager'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e('Status', 'advnews-manager'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="template_active"
                                               value="1"
                                               <?php echo !$template || $template->is_active ? 'checked' : ''; ?>>
                                        <?php _e('Active (available for campaigns)', 'advnews-manager'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Template Content Editor -->
                <div class="postbox">
                    <h2 class="hndle"><span><?php _e('Template Content', 'advnews-manager'); ?></span></h2>
                    <div class="inside" style="padding: 12px;">
                        <div class="wp-editor-wrap">
                            <div id="template-editor-tabs" style="margin-bottom: 10px;">
                                <button type="button" class="button tab-button active" data-tab="visual">
                                    <?php _e('Visual', 'advnews-manager'); ?>
                                </button>
                                <button type="button" class="button tab-button" data-tab="html">
                                    <?php _e('HTML Source', 'advnews-manager'); ?>
                                </button>
                                <button type="button" class="button tab-button" data-tab="css">
                                    <?php _e('CSS Styles', 'advnews-manager'); ?>
                                </button>
                            </div>

                            <div id="template-editor-content">
                                <!-- Visual Editor Tab -->
                                <div id="tab-visual" class="editor-tab-content active">
                                    <?php
                                    $content = $template ? $template->content : '';
                                    $settings = array(
                                        'textarea_name' => 'template_html',
                                        'media_buttons' => true,
                                        'textarea_rows' => 15,
                                        'teeny' => false,
                                        'tinymce' => array(
                                            'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,alignleft,aligncenter,alignright,|,forecolor,backcolor,|,code',
                                            'toolbar2' => 'outdent,indent,|,undo,redo,|,wp_adv',
                                            'extended_valid_elements' => 'br[*],span[*],p[*],div[*],a[*],img[*],table[*],tr[*],td[*],th[*],ul[*],ol[*],li[*]',
                                            'valid_elements' => '*[*]',
                                            'valid_children' => '+body[style],+span[style],+p[style],+div[style],+br',
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
                                            'cleanup_on_startup' => false,
                                            'preformatted' => true,
                                            'convert_newlines_to_brs' => true,
                                            'remove_linebreaks' => false,
                                            'apply_source_formatting' => false,
                                        ),
                                        'quicktags' => array(
                                            'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'
                                        )
                                    );
                                    wp_editor($content, 'template_content_visual', $settings);
                                    ?>
                                </div>

                                <!-- HTML Source Tab -->
                                <div id="tab-html" class="editor-tab-content">
                                    <textarea id="template_html_source"
                                              name="template_html"
                                              class="large-text code"
                                              rows="20"
                                              style="font-family: monospace; width: 100%;"><?php echo esc_textarea($template ? $template->content : ''); ?></textarea>
                                </div>

                                <!-- CSS Styles Tab -->
                                <div id="tab-css" class="editor-tab-content">
                                    <textarea id="template_css"
                                              name="template_css"
                                              class="large-text code"
                                              rows="15"
                                              style="font-family: monospace; width: 100%;"><?php echo esc_textarea($template ? $template->css : ''); ?></textarea>
                                    <p class="description"><?php _e('Custom CSS styles for this template.', 'advnews-manager'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="advnews-editor-sidebar">
                <!-- Publish Box -->
                <div class="postbox advnews-sidebar-box">
                    <h2 class="hndle"><span><?php _e('Publish', 'advnews-manager'); ?></span></h2>
                    <div class="inside">
                        <div class="submitbox" id="submitpost">
                            <div id="major-publishing-actions">
                                <div id="publishing-action">
                                    <input type="submit"
                                           name="save_template"
                                           id="save_template"
                                           class="button button-primary button-large"
                                           value="<?php echo $template_id ? __('Update Template', 'advnews-manager') : __('Save Template', 'advnews-manager'); ?>">
                                    <input type="submit"
                                           name="send_template_now"
                                           id="send_template_now"
                                           class="button button-secondary button-large"
                                           value="<?php _e('Send Now', 'advnews-manager'); ?>">
                                </div>
                                <div class="clear"></div>
                            </div>
                        </div>

                        <?php if ($template_id): ?>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                                <a href="<?php echo admin_url('admin.php?page=advnews-templates&action=preview&id=' . $template_id); ?>"
                                   class="button button-secondary"
                                   style="width: 100%; margin-bottom: 8px;"
                                   target="_blank">
                                    <?php _e('Preview Template', 'advnews-manager'); ?>
                                </a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=advnews-templates&action=duplicate&id=' . $template_id), 'advnews_duplicate_template'); ?>"
                                   class="button button-secondary"
                                   style="width: 100%; margin-bottom: 8px;">
                                    <?php _e('Duplicate', 'advnews-manager'); ?>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=advnews-templates'); ?>"
                                   class="button button-secondary"
                                   style="width: 100%;">
                                    <?php _e('Cancel', 'advnews-manager'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Import HTML Section -->
                <div class="postbox advnews-sidebar-box">
                    <h2 class="hndle"><span><?php _e('Import HTML File', 'advnews-manager'); ?></span></h2>
                    <div class="inside">
                        <p class="description" style="margin-bottom: 12px;">
                            <?php _e('Upload a ready-made .html file to populate this template.', 'advnews-manager'); ?>
                        </p>
                        <input type="file"
                               id="advnews-html-import"
                               accept=".html,.htm"
                               style="display:none;">
                        <button type="button"
                                class="button button-secondary"
                                id="advnews-import-html-btn"
                                style="width: 100%; margin-bottom: 10px;">
                            <?php _e('Choose HTML File', 'advnews-manager'); ?>
                        </button>
                        <span id="import-html-spinner" class="spinner" style="float: none; margin: 0;"></span>
                        <div id="import-html-result" style="margin-top: 10px;"></div>
                    </div>
                </div>

                <!-- Merge Tags Box -->
                <div class="postbox advnews-sidebar-box">
                    <h2 class="hndle"><span><?php _e('Merge Tags', 'advnews-manager'); ?></span></h2>
                    <div class="inside">
                        <p class="description" style="margin-bottom: 12px;">
                            <?php _e('Click to insert:', 'advnews-manager'); ?>
                        </p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
                            <button type="button" class="button merge-tag-btn" data-tag="[first_name]">[first_name]</button>
                            <button type="button" class="button merge-tag-btn" data-tag="[last_name]">[last_name]</button>
                            <button type="button" class="button merge-tag-btn" data-tag="[full_name]">[full_name]</button>
                            <button type="button" class="button merge-tag-btn" data-tag="[email]">[email]</button>
                            <button type="button" class="button merge-tag-btn" data-tag="[organization]">[organization]</button>
                            <button type="button" class="button merge-tag-btn" data-tag="[unsubscribe_link]">[unsubscribe_link]</button>
                            <button type="button" class="button merge-tag-btn" data-tag="[current_date]">[current_date]</button>
                            <button type="button" class="button merge-tag-btn" data-tag="[site_name]">[site_name]</button>
                            <button type="button" class="button merge-tag-btn" data-tag="[site_url]">[site_url]</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.advnews-template-editor {
    max-width: 100%;
}

.advnews-editor-wrapper {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.advnews-editor-main {
    flex: 1;
    min-width: 0;
}

.advnews-editor-sidebar {
    width: 280px;
    flex-shrink: 0;
}

.advnews-sidebar-box {
    margin-bottom: 15px;
    border: 1px solid #c3c4c7;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
}

.advnews-sidebar-box .hndle {
    background: #f6f7f7;
    border-bottom: 1px solid #c3c4c7;
    padding: 8px 12px;
    font-size: 14px;
    font-weight: 600;
}

.advnews-sidebar-box .inside {
    padding: 12px;
}

/* Editor Tabs */
#template-editor-tabs {
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.tab-button {
    margin-right: 5px;
}

.tab-button.active {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}

.editor-tab-content {
    display: none;
}

.editor-tab-content.active {
    display: block;
}

/* Merge Tags Buttons */
.merge-tag-btn {
    font-size: 11px;
    padding: 4px 8px;
    height: auto;
    line-height: 1.4;
}

.merge-tag-btn:hover {
    background: #2271b1;
    color: #fff;
}

/* Responsive */
@media screen and (max-width: 1200px) {
    .advnews-editor-wrapper {
        flex-direction: column;
    }

    .advnews-editor-sidebar {
        width: 100%;
    }

    .advnews-sidebar-box {
        display: inline-block;
        width: calc(50% - 10px);
        vertical-align: top;
    }
}

@media screen and (max-width: 782px) {
    .advnews-sidebar-box {
        width: 100%;
    }
}

/* Form improvements */
.form-table th {
    padding: 20px 10px 20px 0;
    width: 200px;
    font-weight: 600;
}

.form-table td {
    padding: 15px 10px;
}

/* Category checkboxes */
input[type="checkbox"] {
    margin-right: 8px;
}

/* Spinner positioning */
.spinner {
    float: none;
    margin: 0 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.tab-button').on('click', function() {
        var tab = $(this).data('tab');

        // Update active tab button
        $('.tab-button').removeClass('active');
        $(this).addClass('active');

        // Show corresponding content
        $('.editor-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');

        // Sync content between tabs
        if (tab === 'html') {
            var visualContent = $('#template_content_visual').val();
            $('#template_html_source').val(visualContent);
        } else if (tab === 'visual') {
            var htmlContent = $('#template_html_source').val();
            if (typeof tinyMCE !== 'undefined') {
                var editor = tinyMCE.get('template_content_visual');
                if (editor) {
                    editor.setContent(htmlContent);
                }
            }
        }
    });

    // Merge tag insertion
    $('.merge-tag-btn').on('click', function() {
        var tag = $(this).data('tag');
        var activeTab = $('.tab-button.active').data('tab');

        if (activeTab === 'html') {
            insertAtCursor($('#template_html_source'), tag);
        } else if (activeTab === 'visual') {
            if (typeof tinyMCE !== 'undefined') {
                var editor = tinyMCE.get('template_content_visual');
                if (editor) {
                    editor.insertContent(tag);
                }
            }
        }
    });

    function insertAtCursor($textarea, text) {
        var textarea = $textarea[0];
        var startPos = textarea.selectionStart;
        var endPos = textarea.selectionEnd;
        var before = textarea.value.substring(0, startPos);
        var after = textarea.value.substring(endPos);
        textarea.value = before + text + after;
        textarea.selectionStart = textarea.selectionEnd = startPos + text.length;
        textarea.focus();
    }

    // HTML Import Handler
    $('#advnews-import-html-btn').on('click', function() {
        $('#advnews-html-import').trigger('click');
    });

    $('#advnews-html-import').on('change', function(e) {
        var fileInput = this;
        if (!fileInput.files || !fileInput.files[0]) {
            return;
        }

        var formData = new FormData();
        formData.append('action', 'advnews_import_template_html');
        formData.append('html_file', fileInput.files[0]);
        formData.append('_wpnonce', '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>');
        formData.append('nonce', '<?php echo wp_create_nonce('advnews_ajax_nonce'); ?>');

        var btn = $('#advnews-import-html-btn');
        var spinner = $('#import-html-spinner');
        var resultDiv = $('#import-html-result');

        btn.prop('disabled', true);
        spinner.addClass('is-active');
        resultDiv.html('');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Populate fields
                    if (response.data.name) {
                        $('#template_name').val(response.data.name);
                    }
                    if (response.data.content) {
                        $('#template_html_source').val(response.data.content);
                        if (typeof tinyMCE !== 'undefined') {
                            var editor = tinyMCE.get('template_content_visual');
                            if (editor) {
                                editor.setContent(response.data.content);
                            }
                        }
                    }
                    if (response.data.css) {
                        $('#template_css').val(response.data.css);
                    }

                    resultDiv.html('<div style="background: #d4edda; color: #155724; padding: 8px; border-radius: 4px; border-left: 4px solid #28a745;">' +
                    '<?php _e('HTML imported successfully!', 'advnews-manager'); ?>' +
                    '</div>');
                    // Switch to HTML tab manually to avoid overwriting content
                    $('.tab-button').removeClass('active');
                    $('.tab-button[data-tab="html"]').addClass('active');
                    $('.editor-tab-content').removeClass('active');
                    $('#tab-html').addClass('active');
                } else {
                    resultDiv.html('<div style="background: #f8d7da; color: #721c24; padding: 8px; border-radius: 4px; border-left: 4px solid #d63638;">' +
                                   (response.data.message || '<?php _e('Import failed.', 'advnews-manager'); ?>') +
                                   '</div>');
                }
            },
            error: function() {
                resultDiv.html('<div style="background: #f8d7da; color: #721c24; padding: 8px; border-radius: 4px; border-left: 4px solid #d63638;">' +
                               '<?php _e('Upload failed. Please try again.', 'advnews-manager'); ?>' +
                               '</div>');
            },
            complete: function() {
                btn.prop('disabled', false);
                spinner.removeClass('is-active');
                fileInput.value = '';
            }
        });
    });

    // Form validation
    $('#template-form').on('submit', function(e) {
        var name = $('#template_name').val().trim();
        var subject = $('#template_subject').val().trim();
        var submitter = e.originalEvent && e.originalEvent.submitter ? e.originalEvent.submitter : document.activeElement;
        var isSendNow = submitter && submitter.name === 'send_template_now';

        if (!name || !subject) {
            e.preventDefault();
            alert('<?php _e('Please fill in all required fields.', 'advnews-manager'); ?>');
            return false;
        }

        if (isSendNow && $('input[name="template_categories[]"]:checked').length === 0) {
            e.preventDefault();
            alert('<?php _e('Select at least one category before sending this template.', 'advnews-manager'); ?>');
            return false;
        }

        if (isSendNow && !confirm('<?php _e('Send this template now to active subscribers in the selected categories?', 'advnews-manager'); ?>')) {
            e.preventDefault();
            return false;
        }

        // Sync editor content before submit
        var activeTab = $('.tab-button.active').data('tab');
        if (activeTab === 'visual' && typeof tinyMCE !== 'undefined') {
            var editor = tinyMCE.get('template_content_visual');
            if (editor) {
                $('#template_html_source').val(editor.getContent());
            }
        }

        $('#save_template').prop('disabled', true);
        return true;
    });
});
</script>
