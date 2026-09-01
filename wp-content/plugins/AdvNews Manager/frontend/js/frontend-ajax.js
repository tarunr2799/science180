/**
 * Science180 Mail - Frontend AJAX Handlers
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initSubscriptionForms();
        initUnsubscribeForms();
        initPreferenceForms();
        initNewsletterArchive();
        initTracking();
    });

    /**
     * Subscription Forms
     */
    function initSubscriptionForms() {
        $('.advnews-subscription-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var button = form.find('input[type="submit"], button[type="submit"]');
            var responseDiv = form.find('.advnews-form-response');
            var originalText = button.val() || button.text();

            // Validate email
            var email = form.find('[name="email"]').val();
            if (!email || !isValidEmail(email)) {
                showFormMessage(form, advnews_frontend.i18n.invalid_email, 'error');
                return;
            }

            button.prop('disabled', true).val(advnews_frontend.i18n.subscribing).text(advnews_frontend.i18n.subscribing);
            responseDiv.hide().removeClass('success error');

            $.ajax({
                url: advnews_frontend.ajax_url,
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showFormMessage(form, response.data.message, 'success');
                        form[0].reset();

                        // Track subscription event
                        trackEvent('subscription', 'success', form.data('form-id'));

                        // Redirect if specified
                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 2000);
                        }
                    } else {
                        showFormMessage(form, response.data.message, 'error');
                        trackEvent('subscription', 'error', form.data('form-id'));
                    }
                },
                error: function(xhr, status, error) {
                    showFormMessage(form, advnews_frontend.i18n.error, 'error');
                    trackEvent('subscription', 'error', form.data('form-id'));
                    console.error('Subscription AJAX Error:', error);
                },
                complete: function() {
                    button.prop('disabled', false).val(originalText).text(originalText);
                }
            });
        });

        // Real-time email validation
        $('.advnews-subscription-form [name="email"]').on('blur', function() {
            var email = $(this).val();
            var parent = $(this).closest('.advnews-form-group');
            var existingMessage = parent.find('.advnews-validation-message');

            if (email && !isValidEmail(email)) {
                if (existingMessage.length === 0) {
                    parent.append('<div class="advnews-validation-message error">' + advnews_frontend.i18n.invalid_email + '</div>');
                }
            } else {
                existingMessage.remove();
            }
        });
    }

    /**
     * Unsubscribe Forms
     */
    function initUnsubscribeForms() {
        $('.advnews-unsubscribe-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var button = form.find('input[type="submit"], button[type="submit"]');
            var responseDiv = form.find('.advnews-form-response');
            var originalText = button.val() || button.text();

            button.prop('disabled', true).val(advnews_frontend.i18n.processing).text(advnews_frontend.i18n.processing);
            responseDiv.hide().removeClass('success error');

            $.ajax({
                url: advnews_frontend.ajax_url,
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showFormMessage(form, response.data.message, 'success');
                        trackEvent('unsubscribe', 'success', form.data('form-id'));

                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 2000);
                        } else if (response.data.reload) {
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        showFormMessage(form, response.data.message, 'error');
                        trackEvent('unsubscribe', 'error', form.data('form-id'));
                    }
                },
                error: function() {
                    showFormMessage(form, advnews_frontend.i18n.error, 'error');
                    trackEvent('unsubscribe', 'error', form.data('form-id'));
                },
                complete: function() {
                    button.prop('disabled', false).val(originalText).text(originalText);
                }
            });
        });

        // Handle reason selection
        $('#unsubscribe_reason').on('change', function() {
            if ($(this).val() === 'other') {
                $('.advnews-other-reason').slideDown();
            } else {
                $('.advnews-other-reason').slideUp();
            }
        });

        // Confirm unsubscribe
        $('.advnews-confirm-unsubscribe').on('click', function() {
            if (!confirm(advnews_frontend.i18n.confirm_unsubscribe)) {
                return false;
            }
        });
    }

    /**
     * Preference Forms
     */
    function initPreferenceForms() {
        $('#advnews-preferences-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var button = form.find('button[type="submit"]');
            var responseDiv = form.find('.advnews-form-response');
            var originalText = button.text();

            button.prop('disabled', true).text(advnews_frontend.i18n.saving);
            responseDiv.hide().removeClass('success error');

            $.ajax({
                url: advnews_frontend.ajax_url,
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showFormMessage(form, response.data.message, 'success');
                        trackEvent('preferences', 'update', form.data('form-id'));

                        // Update status badge if needed
                        if (response.data.status) {
                            updateSubscriberStatus(response.data.status);
                        }

                        // Hide success message after 5 seconds
                        setTimeout(function() {
                            responseDiv.fadeOut();
                        }, 5000);
                    } else {
                        showFormMessage(form, response.data.message, 'error');
                        trackEvent('preferences', 'error', form.data('form-id'));
                    }
                },
                error: function() {
                    showFormMessage(form, advnews_frontend.i18n.error, 'error');
                    trackEvent('preferences', 'error', form.data('form-id'));
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Toggle all categories
        $('#toggle-all-categories').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('input[name="categories[]"]').prop('checked', isChecked);
        });

        // Select/deselect all button
        $('#select-all-categories').on('click', function() {
            $('input[name="categories[]"]').prop('checked', true);
            $('#toggle-all-categories').prop('checked', true);
        });

        $('#deselect-all-categories').on('click', function() {
            $('input[name="categories[]"]').prop('checked', false);
            $('#toggle-all-categories').prop('checked', false);
        });
    }

    /**
     * Newsletter Archive
     */
    function initNewsletterArchive() {
        // Load more campaigns
        $('#load-more-archive').on('click', function() {
            var button = $(this);
            var page = button.data('page') || 1;
            var container = $('.advnews-archive-grid');

            button.prop('disabled', true).text(advnews_frontend.i18n.loading);

            $.ajax({
                url: advnews_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_load_archive',
                    page: page,
                    nonce: advnews_frontend.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        container.append(response.data.html);
                        button.data('page', page + 1);

                        if (!response.data.has_more) {
                            button.remove();
                        }
                    }
                },
                complete: function() {
                    button.prop('disabled', false).text(advnews_frontend.i18n.load_more);
                }
            });
        });

        // Filter archive by category
        $('#archive-category-filter').on('change', function() {
            var category = $(this).val();
            var container = $('.advnews-archive-grid');

            container.addClass('loading').html('<div class="advnews-loading-spinner"></div>');

            $.ajax({
                url: advnews_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_filter_archive',
                    category: category,
                    nonce: advnews_frontend.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        container.removeClass('loading').html(response.data.html);
                    }
                },
                error: function() {
                    container.removeClass('loading').html('<p class="advnews-error">' + advnews_frontend.i18n.error + '</p>');
                }
            });
        });

        // Search archive
        var searchTimeout;
        $('#archive-search').on('keyup', function() {
            clearTimeout(searchTimeout);
            var searchTerm = $(this).val();

            if (searchTerm.length < 3 && searchTerm.length > 0) {
                return;
            }

            searchTimeout = setTimeout(function() {
                var container = $('.advnews-archive-grid');
                container.addClass('loading').html('<div class="advnews-loading-spinner"></div>');

                $.ajax({
                    url: advnews_frontend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'advnews_search_archive',
                        search: searchTerm,
                        nonce: advnews_frontend.nonce
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            container.removeClass('loading').html(response.data.html);
                        }
                    },
                    error: function() {
                        container.removeClass('loading').html('<p class="advnews-error">' + advnews_frontend.i18n.error + '</p>');
                    }
                });
            }, 500);
        });
    }

    /**
     * Tracking Functions
     */
    function initTracking() {
        // Track link clicks in emails
        $('a[data-track="true"]').on('click', function(e) {
            var link = $(this);
            var href = link.attr('href');
            var campaignId = link.data('campaign-id');
            var subscriberId = link.data('subscriber-id');

            // Send tracking data in background
            $.ajax({
                url: advnews_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'advnews_track_click',
                    url: href,
                    campaign_id: campaignId,
                    subscriber_id: subscriberId,
                    nonce: advnews_frontend.nonce
                },
                async: true
            });
        });

        // Track email opens (via tracking pixel)
        if ($('.advnews-tracking-pixel').length) {
            var pixel = $('.advnews-tracking-pixel');
            var src = pixel.attr('src');

            // Load tracking pixel
            var img = new Image();
            img.src = src;
        }

        // Track custom events
        $(document).on('advnews:track', function(e, eventName, eventData) {
            trackEvent(eventName, eventData);
        });
    }

    /**
     * Helper Functions
     */
    function showFormMessage(form, message, type) {
        var responseDiv = form.find('.advnews-form-response');
        responseDiv.removeClass('success error').addClass(type).html('<p>' + message + '</p>').show();

        // Scroll to message
        if (responseDiv.is(':visible')) {
            $('html, body').animate({
                scrollTop: responseDiv.offset().top - 100
            }, 500);
        }
    }

    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function updateSubscriberStatus(status) {
        var badge = $('.advnews-status-badge');
        badge.removeClass('advnews-status-active advnews-status-unsubscribed advnews-status-bounced')
             .addClass('advnews-status-' + status)
             .text(status.charAt(0).toUpperCase() + status.slice(1));
    }

    function trackEvent(eventName, eventData, formId) {
        if (typeof gtag !== 'undefined') {
            gtag('event', eventName, {
                'event_category': 'Newsletter',
                'event_label': formId || window.location.pathname,
                'value': eventData
            });
        }

        if (typeof fbq !== 'undefined') {
            fbq('trackCustom', 'Newsletter' + eventName.charAt(0).toUpperCase() + eventName.slice(1), {
                content_name: document.title,
                content_category: 'Newsletter'
            });
        }

        // Send to our analytics
        $.ajax({
            url: advnews_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'advnews_track_event',
                event: eventName,
                data: eventData,
                nonce: advnews_frontend.nonce
            },
            async: true
        });
    }

    /**
     * Cookie Consent
     */
    function initCookieConsent() {
        if (!getCookie('advnews_consent') && $('.advnews-cookie-notice').length) {
            $('.advnews-cookie-notice').show();

            $('.advnews-accept-cookies').on('click', function() {
                setCookie('advnews_consent', 'accepted', 365);
                $('.advnews-cookie-notice').fadeOut();

                // Enable tracking
                enableTracking(true);
            });

            $('.advnews-decline-cookies').on('click', function() {
                setCookie('advnews_consent', 'declined', 365);
                $('.advnews-cookie-notice').fadeOut();

                // Disable tracking
                enableTracking(false);
            });
        }
    }

    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + (value || '') + expires + '; path=/';
    }

    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    function enableTracking(enabled) {
        if (enabled) {
            // Re-initialize tracking
            initTracking();
        } else {
            // Disable tracking scripts
            // This would depend on your analytics setup
        }
    }

    // Initialize cookie consent
    initCookieConsent();

})(jQuery);
