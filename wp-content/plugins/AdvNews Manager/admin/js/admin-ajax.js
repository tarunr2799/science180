/**
 * AdvNews Manager - Admin AJAX Handlers
 * Version: 1.0.0
 */
(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initCampaignActions();
        initSubscriberActions();
        initTemplateActions();
        initQueueActions();
        initAnalyticsActions();
        initBulkActions();
        initImportExport();
        initDashboardWidgets();
        initSettingsActions();
    });

    /**
     * Campaign Actions
     */
    function initCampaignActions() {
        // Send test email
        $('#send-test').on('click', function() {
            var testEmail = $('#test_email').val();
            if (!testEmail) {
                alert(advnews_ajax.i18n.enter_email);
                return;
            }

            var button = $(this);
            var resultDiv = $('#test_result');

            button.prop('disabled', true).text(advnews_ajax.i18n.sending);
            resultDiv.hide();

            var formData = {
                action: 'advnews_send_test',
                nonce: advnews_ajax.nonce,
                test_email: testEmail,
                subject: $('#subject').val(),
                content: getEditorContent()
            };

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showMessage(resultDiv, response.data.message, 'success');
                    } else {
                        showMessage(resultDiv, response.data.message, 'error');
                    }
                },
                error: function() {
                    showMessage(resultDiv, advnews_ajax.i18n.error, 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text(advnews_ajax.i18n.send_test);
                }
            });
        });

        // Send campaign now
        $('#send-campaign-now').on('click', function() {
            if (!confirm(advnews_ajax.i18n.confirm_send)) {
                return;
            }

            var button = $(this);
            var campaignId = button.data('campaign-id');

            button.prop('disabled', true).text(advnews_ajax.i18n.sending);

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_send_campaign',
                    campaign_id: campaignId,
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                        button.prop('disabled', false).text(advnews_ajax.i18n.send_now);
                    }
                },
                error: function() {
                    alert(advnews_ajax.i18n.error);
                    button.prop('disabled', false).text(advnews_ajax.i18n.send_now);
                }
            });
        });

        // Pause campaign
        $('#pause-campaign').on('click', function() {
            if (!confirm(advnews_ajax.i18n.confirm_pause)) {
                return;
            }

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_pause_campaign',
                    campaign_id: $(this).data('campaign-id'),
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        // Resume campaign
        $('#resume-campaign').on('click', function() {
            if (!confirm(advnews_ajax.i18n.confirm_resume)) {
                return;
            }

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_resume_campaign',
                    campaign_id: $(this).data('campaign-id'),
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        // Duplicate campaign
        $('#duplicate-campaign').on('click', function() {
            if (!confirm(advnews_ajax.i18n.confirm_duplicate)) {
                return;
            }

            window.location.href = $(this).data('url');
        });

        // Load template content
        $('#template_id').on('change', function() {
            var templateId = $(this).val();
            if (!templateId) return;

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
                        setEditorContent(response.data.content);
                    }
                }
            });
        });

        // Calculate recipients
        $('#category_id').on('change', function() {
            var categoryId = $(this).val();
            if (!categoryId) {
                $('#recipient-count').text('0');
                return;
            }

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_count_recipients',
                    category_id: categoryId,
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#recipient-count').text(response.data.count);
                    }
                }
            });
        });
    }

    /**
     * Subscriber Actions
     */
    function initSubscriberActions() {
        // Quick edit subscriber
        $('.quick-edit-subscriber').on('click', function(e) {
            e.preventDefault();
            var subscriberId = $(this).data('id');
            var row = $(this).closest('tr');

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_get_subscriber',
                    subscriber_id: subscriberId,
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showQuickEditForm(row, response.data);
                    }
                }
            });
        });

        // Save quick edit
        $(document).on('click', '.save-quick-edit', function() {
            var form = $(this).closest('.quick-edit-form');
            var data = {
                action: 'advnews_update_subscriber',
                nonce: advnews_ajax.nonce,
                subscriber_id: form.data('id'),
                email: form.find('[name="email"]').val(),
                first_name: form.find('[name="first_name"]').val(),
                last_name: form.find('[name="last_name"]').val(),
                organization: form.find('[name="organization"]').val()
            };

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        // Cancel quick edit
        $(document).on('click', '.cancel-quick-edit', function() {
            $(this).closest('.quick-edit-form').remove();
            location.reload();
        });

        // Search subscribers
        var searchTimeout;
        $('#subscriber-search').on('keyup', function() {
            clearTimeout(searchTimeout);
            var searchTerm = $(this).val();

            searchTimeout = setTimeout(function() {
                if (searchTerm.length >= 3 || searchTerm.length === 0) {
                    filterSubscribers(searchTerm);
                }
            }, 500);
        });
    }

    /**
     * Template Actions
     */
    function initTemplateActions() {
        // Preview template
        $('.preview-template').on('click', function(e) {
            e.preventDefault();
            var templateId = $(this).data('id');
            var previewWindow = window.open('about:blank', 'template-preview', 'width=800,height=600');

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_preview_template',
                    template_id: templateId,
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        previewWindow.document.write(response.data.html);
                    } else {
                        previewWindow.close();
                        alert(response.data.message);
                    }
                }
            });
        });

        // Test template variables
        $('#test-template-vars').on('click', function() {
            var content = getEditorContent();
            var testData = {
                first_name: 'John',
                last_name: 'Doe',
                email: 'john@example.com',
                company: 'ACME Inc'
            };

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_test_template',
                    content: content,
                    test_data: testData,
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showPreviewModal(response.data.rendered);
                    }
                }
            });
        });
    }

    /**
     * Queue Actions
     */
    function initQueueActions() {
        // Refresh queue status
        $('#refresh-queue').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text(advnews_ajax.i18n.refreshing);

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_get_queue_status',
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateQueueDisplay(response.data.status);
                    }
                },
                complete: function() {
                    button.prop('disabled', false).text(advnews_ajax.i18n.refresh);
                }
            });
        });

        // Pause queue
        $('#pause-queue').on('click', function() {
            if (!confirm(advnews_ajax.i18n.confirm_pause_queue)) {
                return;
            }

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_pause_queue',
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

        // Resume queue
        $('#resume-queue').on('click', function() {
            if (!confirm(advnews_ajax.i18n.confirm_resume_queue)) {
                return;
            }

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_resume_queue',
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

        // Clear stuck emails
        $('#clear-stuck-queue').on('click', function() {
            if (!confirm(advnews_ajax.i18n.confirm_clear_stuck)) {
                return;
            }

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_clear_stuck_queue',
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

        // Retry failed
        $('#retry-failed-queue').on('click', function() {
            if (!confirm(advnews_ajax.i18n.confirm_retry_failed)) {
                return;
            }

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_retry_failed_queue',
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

        // REMOVED: Process queue now handler (now handled in settings-cron.php)
        // This prevents duplicate handlers
    }

    /**
     * Analytics Actions
     */
    function initAnalyticsActions() {
        // Export analytics
        $('.export-analytics').on('click', function() {
            var type = $(this).data('export');
            var campaignId = $(this).data('campaign-id');
            var period = $('#analytics-period').val();

            window.location.href = advnews_ajax.ajax_url +
                '?action=advnews_export_analytics' +
                '&type=' + type +
                '&campaign_id=' + (campaignId || '') +
                '&period=' + (period || '') +
                '&nonce=' + advnews_ajax.nonce;
        });

        // Load more analytics data
        $('.load-more-analytics').on('click', function() {
            var button = $(this);
            var type = button.data('type');
            var offset = button.data('offset') || 0;

            button.prop('disabled', true).text(advnews_ajax.i18n.loading);

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_load_more_analytics',
                    type: type,
                    offset: offset,
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        appendAnalyticsData(response.data);
                        button.data('offset', offset + response.data.count);

                        if (!response.data.has_more) {
                            button.remove();
                        }
                    }
                },
                complete: function() {
                    button.prop('disabled', false).text(advnews_ajax.i18n.load_more);
                }
            });
        });

        // Date range selector
        $('#analytics-date-range').on('apply.daterangepicker', function(ev, picker) {
            var startDate = picker.startDate.format('YYYY-MM-DD');
            var endDate = picker.endDate.format('YYYY-MM-DD');

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_update_analytics_range',
                    start_date: startDate,
                    end_date: endDate,
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        });
    }

    /**
     * Bulk Actions
     */
    function initBulkActions() {
        // Select all checkboxes
        $('#cb-select-all-1, #cb-select-all-2').on('click', function() {
            var checkboxes = $(this).closest('table').find('tbody input[type="checkbox"]');
            checkboxes.prop('checked', $(this).is(':checked'));
        });

        // Apply bulk action
        $('.bulkactions .action').on('click', function(e) {
            var selectedAction = $(this).closest('.bulkactions').find('select').val();

            if (!selectedAction) {
                alert(advnews_ajax.i18n.select_action);
                e.preventDefault();
                return false;
            }

            var selectedItems = $(this).closest('form').find('input[type="checkbox"]:checked').length;

            if (selectedItems === 0) {
                alert(advnews_ajax.i18n.select_items);
                e.preventDefault();
                return false;
            }

            if (!confirm(advnews_ajax.i18n.confirm_bulk_action)) {
                e.preventDefault();
                return false;
            }

            return true;
        });

        // Bulk edit categories
        $('#bulk-edit-categories').on('click', function() {
            var selectedIds = getSelectedIds();
            if (selectedIds.length === 0) {
                alert(advnews_ajax.i18n.select_subscribers);
                return;
            }

            showCategoryModal(selectedIds);
        });
    }

    /**
     * Import/Export Actions
     */
    function initImportExport() {
        // CSV import
        $('#import-csv').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);
            var button = $(this).find('input[type="submit"]');
            var progress = $('#import-progress');

            button.prop('disabled', true).val(advnews_ajax.i18n.importing);
            progress.show();

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showImportResults(response.data);
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert(advnews_ajax.i18n.import_error);
                },
                complete: function() {
                    button.prop('disabled', false).val(advnews_ajax.i18n.import);
                    progress.hide();
                }
            });
        });

        // Export preview
        $('#preview-export').on('click', function() {
            var formData = $('#export-form').serialize();

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: formData + '&action=advnews_preview_export&nonce=' + advnews_ajax.nonce,
                success: function(response) {
                    if (response.success) {
                        showExportPreview(response.data);
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        // Schedule export
        $('#schedule-export').on('change', function() {
            if ($(this).is(':checked')) {
                $('.schedule-options').slideDown();
            } else {
                $('.schedule-options').slideUp();
            }
        });
    }

    /**
     * Dashboard Widgets
     */
    function initDashboardWidgets() {
        // Refresh dashboard stats
        setInterval(function() {
            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_refresh_dashboard',
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateDashboardStats(response.data);
                    }
                }
            });
        }, 60000); // Refresh every minute
    }

    /**
     * Settings Actions - REMOVED process-queue-now handler
     */
    function initSettingsActions() {
        // Test SMTP
        $('#test-smtp').on('click', function() {
            var testEmail = $('#test-email').val();
            if (!testEmail) {
                alert(advnews_ajax.i18n.enter_email);
                return;
            }

            var button = $(this);
            var spinner = $('#test-spinner');
            var result = $('#test-result');

            button.prop('disabled', true);
            spinner.addClass('is-active');
            result.hide();

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_test_smtp',
                    test_email: testEmail,
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(result, response.data.message, 'success');
                    } else {
                        showMessage(result, response.data.message, 'error');
                    }
                },
                error: function() {
                    showMessage(result, advnews_ajax.i18n.smtp_error, 'error');
                },
                complete: function() {
                    button.prop('disabled', false);
                    spinner.removeClass('is-active');
                }
            });
        });

        // Test cron
        $('#test-cron').on('click', function() {
            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_test_cron',
                    nonce: advnews_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });

        // Save settings via AJAX
        $('.ajax-save-settings').on('click', function() {
            var form = $(this).closest('form');
            var data = form.serialize() + '&action=advnews_save_settings&nonce=' + advnews_ajax.nonce;

            $.ajax({
                url: advnews_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        showSaveNotification(response.data.message, 'success');
                    } else {
                        showSaveNotification(response.data.message, 'error');
                    }
                }
            });
        });

        // REMOVED: Process queue now handler (now handled in settings-cron.php)
    }

    /**
     * Helper Functions
     */
    function getEditorContent() {
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
            return tinyMCE.get('content').getContent();
        }
        return $('#content').val();
    }

    function setEditorContent(content) {
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
            tinyMCE.get('content').setContent(content);
        } else {
            $('#content').val(content);
        }
    }

    function showMessage(element, message, type) {
        element.removeClass('success error').addClass(type).html('<p>' + message + '</p>').show();
    }

    function updateQueueDisplay(status) {
        $('.queue-stat[data-stat="queued"]').text(status.queued);
        $('.queue-stat[data-stat="sending"]').text(status.sending);
        $('.queue-stat[data-stat="delivered"]').text(status.delivered);
        $('.queue-stat[data-stat="failed"]').text(status.failed);
        $('.queue-stat[data-stat="opened"]').text(status.opened);
        $('.queue-stat[data-stat="clicked"]').text(status.clicked);
    }

    function updateDashboardStats(stats) {
        $('.stat-total-subscribers').text(stats.total_subscribers);
        $('.stat-active-campaigns').text(stats.active_campaigns);
        $('.stat-emails-today').text(stats.emails_sent_today);
        $('.stat-open-rate').text(stats.avg_open_rate + '%');
        $('.stat-click-rate').text(stats.avg_click_rate + '%');
    }

    function getSelectedIds() {
        var ids = [];
        $('input[type="checkbox"]:checked').each(function() {
            var id = $(this).val();
            if (id && id !== 'on') {
                ids.push(id);
            }
        });
        return ids;
    }

    function showQuickEditForm(row, data) {
        var form = `
            <tr class="quick-edit-form" data-id="${data.id}">
                <td colspan="7">
                    <div class="quick-edit-wrapper">
                        <h4><?php _e('Quick Edit Subscriber', 'advnews-manager'); ?></h4>
                        <div class="quick-edit-fields">
                            <input type="email" name="email" value="${data.email}" placeholder="<?php _e('Email', 'advnews-manager'); ?>">
                            <input type="text" name="first_name" value="${data.first_name}" placeholder="<?php _e('First Name', 'advnews-manager'); ?>">
                            <input type="text" name="last_name" value="${data.last_name}" placeholder="<?php _e('Last Name', 'advnews-manager'); ?>">
                            <input type="text" name="organization" value="${data.organization}" placeholder="<?php _e('Organization', 'advnews-manager'); ?>">
                        </div>
                        <div class="quick-edit-actions">
                            <button type="button" class="button button-primary save-quick-edit"><?php _e('Save', 'advnews-manager'); ?></button>
                            <button type="button" class="button cancel-quick-edit"><?php _e('Cancel', 'advnews-manager'); ?></button>
                        </div>
                    </div>
                </td>
            </tr>
        `;

        row.after(form);
        row.hide();
    }

    function filterSubscribers(searchTerm) {
        $.ajax({
            url: advnews_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_filter_subscribers',
                search: searchTerm,
                nonce: advnews_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#subscribers-table tbody').html(response.data.html);
                }
            }
        });
    }

    function showPreviewModal(html) {
        var modal = `
            <div id="preview-modal" style="display:none;">
                <div class="preview-modal-content">
                    <div class="preview-modal-header">
                        <h3><?php _e('Template Preview', 'advnews-manager'); ?></h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="preview-modal-body">
                        <iframe srcdoc="${escapeHtml(html)}"></iframe>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modal);
        $('#preview-modal').fadeIn();

        $('.close-modal').on('click', function() {
            $('#preview-modal').fadeOut(function() {
                $(this).remove();
            });
        });
    }

    function showImportResults(results) {
        var html = `
            <div class="import-results">
                <h4><?php _e('Import Complete', 'advnews-manager'); ?></h4>
                <ul>
                    <li><strong><?php _e('Imported:', 'advnews-manager'); ?></strong> ${results.imported}</li>
                    <li><strong><?php _e('Updated:', 'advnews-manager'); ?></strong> ${results.updated}</li>
                    <li><strong><?php _e('Skipped:', 'advnews-manager'); ?></strong> ${results.skipped}</li>
                </ul>
                ${results.errors.length > 0 ? '<div class="import-errors"><h5><?php _e('Errors:', 'advnews-manager'); ?></h5><ul>' + results.errors.map(e => '<li>' + e + '</li>').join('') + '</ul></div>' : ''}
            </div>
        `;

        $('#import-results').html(html).show();
    }

    function showExportPreview(data) {
        var html = '<table class="wp-list-table widefat fixed striped"><thead><tr>';

        if (data.length > 0) {
            // Headers
            $.each(Object.keys(data[0]), function(i, key) {
                html += '<th>' + key + '</th>';
            });
            html += '</tr></thead><tbody>';

            // Rows
            $.each(data, function(i, row) {
                html += '<tr>';
                $.each(row, function(key, value) {
                    html += '<td>' + (value || '') + '</td>';
                });
                html += '</tr>';
            });
        } else {
            html += '<th><?php _e('No data', 'advnews-manager'); ?></th></tr></thead><tbody><tr><td><?php _e('No subscribers found.', 'advnews-manager'); ?></td></tr>';
        }

        html += '</tbody></table>';
        $('#export-preview').html(html).show();
    }

    function appendAnalyticsData(data) {
        var html = '';
        $.each(data.items, function(i, item) {
            html += '<tr>';
            html += '<td>' + (item.date || '') + '</td>';
            html += '<td>' + (item.opens || 0) + '</td>';
            html += '<td>' + (item.clicks || 0) + '</td>';
            html += '<td>' + (item.open_rate || 0) + '%</td>';
            html += '<td>' + (item.click_rate || 0) + '%</td>';
            html += '</tr>';
        });

        $('#analytics-table tbody').append(html);
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
                    var modal = `
                        <div id="category-modal" style="display:none;">
                            <div class="category-modal-content">
                                <div class="category-modal-header">
                                    <h3><?php _e('Assign Categories', 'advnews-manager'); ?></h3>
                                    <button class="close-modal">&times;</button>
                                </div>
                                <div class="category-modal-body">
                                    <p><?php _e('Select categories to assign to selected subscribers:', 'advnews-manager'); ?></p>
                                    <div class="category-list">
                                        ${response.data.categories.map(c => `
                                            <label>
                                                <input type="checkbox" value="${c.id}"> ${c.name}
                                            </label>
                                        `).join('')}
                                    </div>
                                </div>
                                <div class="category-modal-footer">
                                    <button type="button" class="button button-primary" id="assign-categories"><?php _e('Assign', 'advnews-manager'); ?></button>
                                    <button type="button" class="button close-modal"><?php _e('Cancel', 'advnews-manager'); ?></button>
                                </div>
                            </div>
                        </div>
                    `;

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

    function showSaveNotification(message, type) {
        var notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notification);

        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
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
})(jQuery);
