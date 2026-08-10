// File: assets/js/admin.js

jQuery(document).ready(function($) {

    console.log('AdvNews Admin JS loaded');

    // =====================================================
    //  TEMPLATE SELECTION FOR CAMPAIGNS
    // =====================================================

    // Cache selectors
    var $categorySelect = $('#campaign_category, #category_id');
    var $templateSelect = $('#template_id, #template_select');
    var $useTemplateBtn = $('.advnews-use-template, .use-template, button:contains("Use Template"), input[value="Use Template"]');

    /**
     * Load templates when category changes
     */
    $categorySelect.on('change', function() {
        var categoryId = $(this).val();

        console.log('Category changed to:', categoryId);

        if (!categoryId) {
            $templateSelect.html('<option value="">Select a category first</option>').prop('disabled', true);
            return;
        }

        // Show loading
        $templateSelect.html('<option value="">Loading templates...</option>').prop('disabled', true);

        // AJAX request to get templates
        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_get_templates_by_category',
                category_id: categoryId,
                nonce: advnews_ajax.nonce
            },
            success: function(response) {
                console.log('Templates response:', response);

                if (response.success && response.data.html) {
                    $templateSelect.html(response.data.html).prop('disabled', false);
                } else {
                    $templateSelect.html('<option value="">No templates found</option>').prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading templates:', error);
                $templateSelect.html('<option value="">Error loading templates</option>').prop('disabled', true);
            }
        });
    });

    /**
     * Load template content when template is selected or use button is clicked
     */
    function loadTemplateContent() {
        var templateId = $templateSelect.val();

        if (!templateId) {
            alert('Please select a template first');
            return;
        }

        console.log('Loading template content for ID:', templateId);

        // Show loading state
        $useTemplateBtn.prop('disabled', true).text('Loading...');

        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_get_template',
                template_id: templateId,
                nonce: advnews_ajax.nonce
            },
            success: function(response) {
                console.log('Template content response:', response);

                if (response.success) {
                    // Fill in the form fields
                    if (response.data.subject) {
                        $('#subject, #email_subject').val(response.data.subject);
                    }

                    if (response.data.content) {
                        $('#content, #email_content').val(response.data.content);

                        // Update TinyMCE if it exists
                        if (typeof tinyMCE !== 'undefined') {
                            if (tinyMCE.get('content')) {
                                tinyMCE.get('content').setContent(response.data.content);
                            }
                            if (tinyMCE.get('email_content')) {
                                tinyMCE.get('email_content').setContent(response.data.content);
                            }
                        }
                    }

                    if (response.data.name) {
                        $('#campaign_name').val(response.data.name);
                    }

                    alert('Template loaded successfully!');
                } else {
                    alert(response.data.message || 'Error loading template');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading template content:', error);
                alert('Error loading template content');
            },
            complete: function() {
                $useTemplateBtn.prop('disabled', false).text('Use Template');
            }
        });
    }

    // Bind to template select change
    $templateSelect.on('change', function() {
        console.log('Template selected:', $(this).val());
    });

    // Bind to use template button
    $useTemplateBtn.on('click', function(e) {
        e.preventDefault();
        loadTemplateContent();
    });

    // If there's a specific button for template selection, also bind to it
    $(document).on('click', '.advnews-load-template, [data-action="load-template"]', function(e) {
        e.preventDefault();
        loadTemplateContent();
    });

    // =====================================
    // CAMPAIGN FORM VALIDATION
    // =====================================

    $('.advnews-campaign-form').on('submit', function(e) {
        var name = $('#campaign_name').val();
        var subject = $('#subject').val();
        var category = $categorySelect.val();
        var template = $templateSelect.val();

        if (!name || !subject || !category || !template) {
            e.preventDefault();
            alert(advnews_ajax.i18n?.missing_fields || 'Please fill in all required fields');
            return false;
        }

        $(this).find('.button-primary').prop('disabled', true).addClass('advnews-loading');
    });

    // ======================================
    // PREVIEW TEMPLATE
    // ======================================

    $('.advnews-preview-template').on('click', function(e) {
        e.preventDefault();

        var templateId = $templateSelect.val();

        if (!templateId) {
            alert('Please select a template first');
            return;
        }

        var previewWindow = window.open('', '_blank');
        previewWindow.document.write('Loading preview...');

        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_preview_template',
                template_id: templateId,
                nonce: advnews_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    previewWindow.document.open();
                    previewWindow.document.write(response.data.html);
                    previewWindow.document.close();
                }
            },
            error: function() {
                previewWindow.document.write('Error loading preview');
            }
        });
    });

    console.log('AdvNews Admin JS initialization complete');
});

// Helper functions from admin-ajax.js logic that were broken
function showQuickEditForm(row, data) {
    var form = `<tr class="quick-edit-form" data-id="${data.id}">
        <td colspan="7">
            <div class="quick-edit-wrapper">
                <h4>Quick Edit Subscriber</h4>
                <div class="quick-edit-fields">
                    <input type="email" name="email" value="${data.email}" placeholder="Email">
                    <input type="text" name="first_name" value="${data.first_name}" placeholder="First Name">
                    <input type="text" name="last_name" value="${data.last_name}" placeholder="Last Name">
                    <input type="text" name="organization" value="${data.organization}" placeholder="Organization">
                </div>
                <div class="quick-edit-actions">
                    <button type="button" class="button button-primary save-quick-edit">Save</button>
                    <button type="button" class="button cancel-quick-edit">Cancel</button>
                </div>
            </div>
        </td>
    </tr>`;
    row.after(form);
    row.hide();
}

function showPreviewModal(html) {
    var modal = `<div id="preview-modal" style="display:none;">
        <div class="preview-modal-content">
            <div class="preview-modal-header">
                <h3>Template Preview</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="preview-modal-body">
                <iframe srcdoc="${escapeHtml(html)}"></iframe>
            </div>
        </div>
    </div>`;
    $('body').append(modal);
    $('#preview-modal').fadeIn();

    $('.close-modal').on('click', function() {
        $('#preview-modal').fadeOut(function() {
            $(this).remove();
        });
    });
}

function showImportResults(results) {
    var html = `<div class="import-results">
        <h4>Import Complete</h4>
        <ul>
            <li><strong>Imported:</strong> ${results.imported}</li>
            <li><strong>Updated:</strong> ${results.updated}</li>
            <li><strong>Skipped:</strong> ${results.skipped}</li>
        </ul>
        ${results.errors.length > 0 ? '<div class="import-errors"><h5>Errors:</h5><ul>' + results.errors.map(e => '<li>' + e + '</li>').join('') + '</ul></div>' : ''}
    </div>`;
    $('#import-results').html(html).show();
}

function showCategoryModal(subscriberIds) {
    $.ajax({
        url: advnews_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'advnews_get_categories',
            nonce: advnews_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                var modal = `<div id="category-modal" style="display:none;">
                    <div class="category-modal-content">
                        <div class="category-modal-header">
                            <h3>Assign Categories</h3>
                            <button class="close-modal">&times;</button>
                        </div>
                        <div class="category-modal-body">
                            <p>Select categories to assign to selected subscribers:</p>
                            <div class="category-list">
                                ${response.data.categories.map(c => `<label><input type="checkbox" value="${c.id}"> ${c.name}</label>`).join('')}
                            </div>
                        </div>
                        <div class="category-modal-footer">
                            <button type="button" class="button button-primary" id="assign-categories">Assign</button>
                            <button type="button" class="button close-modal">Cancel</button>
                        </div>
                    </div>
                </div>`;
                $('body').append(modal);
                $('#category-modal').fadeIn();

                $('#assign-categories').on('click', function() {
                    var selectedCategories = [];
                    $('#category-modal .category-list input:checked').each(function() {
                        selectedCategories.push($(this).val());
                    });

                    $.ajax({
                        url: advnews_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'advnews_bulk_assign_categories',
                            subscriber_ids: subscriberIds,
                            category_ids: selectedCategories,
                            nonce: advnews_ajax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                location.reload();
                            } else {
                                alert(response.data.message);
                            }
                        }
                    });
                });

                $('.close-modal').on('click', function() {
                    $('#category-modal').fadeOut(function() {
                        $(this).remove();
                    });
                });
            }
        }
    });
}

function escapeHtml(unsafe) {
    return unsafe.replace(/[&<>"']/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        if (m === '"') return '&quot;';
        if (m === "'") return '&#039;';
        return m;
    });
}
