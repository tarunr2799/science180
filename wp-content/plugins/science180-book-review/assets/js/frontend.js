(function () {
    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function normalizeHttpsField(input) {
        var value = input.value.trim();
        if (!value) {
            return;
        }

        value = value.replace(/^(?:(?:https?):\/*)+/i, '').replace(/^\/+/, '');
        input.value = value;
    }

    function refreshReviewNonce(form) {
        if (!window.s180reFrontend || !window.s180reFrontend.ajaxUrl || !window.fetch) {
            return Promise.reject(new Error('The verification service is unavailable.'));
        }

        var requestUrl = window.s180reFrontend.ajaxUrl + (window.s180reFrontend.ajaxUrl.indexOf('?') === -1 ? '?' : '&') + 'action=s180br_review_nonce';
        return window.fetch(requestUrl, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) { return response.json(); })
            .then(function (response) {
                if (!response || !response.success || !response.data || !response.data.nonce) {
                    throw new Error('The verification service returned an invalid response.');
                }
                var nonceInput = form.querySelector('input[name="s180re_nonce"]');
                if (nonceInput) {
                    nonceInput.value = response.data.nonce;
                }
            });
    }

    function setSelected(shell, id, title, cover, description) {
        var select = shell.querySelector('[data-s180re-book-select]');
        var titleNode = shell.querySelector('[data-s180re-selected-title]');
        var coverNode = shell.querySelector('[data-s180re-selected-cover]');
        var descriptionNode = shell.querySelector('[data-s180re-selected-description]');

        shell.querySelectorAll('.s180re-book-choice').forEach(function (choice) {
            var input = choice.querySelector('input[type="radio"]');
            var isSelected = input && input.value === String(id);
            choice.classList.toggle('is-selected', isSelected);
            if (input) {
                input.checked = isSelected;
            }
        });

        if (select && select.value !== String(id)) {
            select.value = String(id);
        }

        if (titleNode) {
            titleNode.textContent = title || '';
        }

        if (descriptionNode) {
            descriptionNode.textContent = description || '';
        }

        if (coverNode && cover) {
            if (coverNode.tagName.toLowerCase() !== 'img') {
                var img = document.createElement('img');
                img.setAttribute('data-s180re-selected-cover', '');
                coverNode.replaceWith(img);
                coverNode = img;
            }
            coverNode.src = cover;
            coverNode.alt = title || '';
        }
    }

    function scrollToReviewForm(shell) {
        var form = shell.querySelector('[data-s180re-review-form]');
        if (!form) {
            return;
        }

        window.requestAnimationFrame(function () {
            var targetY = form.getBoundingClientRect().top + window.pageYOffset - 96;
            window.scrollTo({ top: Math.max(targetY, 0), behavior: 'smooth' });

            var select = shell.querySelector('[data-s180re-book-select]');
            if (select && typeof select.focus === 'function') {
                try {
                    select.focus({ preventScroll: true });
                } catch (error) {
                    select.focus();
                }
            }
        });
    }

    ready(function () {
        document.querySelectorAll('[data-s180re-review]').forEach(function (shell) {
            shell.querySelectorAll('.s180re-book-choice').forEach(function (choice) {
                choice.addEventListener('click', function (event) {
                    var input = choice.querySelector('input[type="radio"]');
                    if (!input) {
                        return;
                    }
                    if (event.target && event.target.closest && event.target.closest('.s180re-book-title')) {
                        event.preventDefault();
                    }
                    setSelected(shell, input.value, input.getAttribute('data-title'), input.getAttribute('data-cover'), input.getAttribute('data-description'));
                    scrollToReviewForm(shell);
                });
            });

            shell.querySelectorAll('.s180re-book-choice input[type="radio"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    setSelected(shell, input.value, input.getAttribute('data-title'), input.getAttribute('data-cover'), input.getAttribute('data-description'));
                    scrollToReviewForm(shell);
                });
            });

            var select = shell.querySelector('[data-s180re-book-select]');
            if (select) {
                select.addEventListener('change', function () {
                    var selected = select.options[select.selectedIndex];
                    setSelected(shell, select.value, selected.getAttribute('data-title') || selected.textContent, selected.getAttribute('data-cover'), selected.getAttribute('data-description'));
                });
            }

            var websiteInput = shell.querySelector('[data-s180br-url-input]');
            var reviewForm = shell.querySelector('[data-s180re-review-form]');
            if (websiteInput) {
                websiteInput.addEventListener('blur', function () {
                    normalizeHttpsField(websiteInput);
                });
            }
            if (reviewForm) {
                var isSubmitting = false;
                refreshReviewNonce(reviewForm).catch(function () {});
                reviewForm.addEventListener('submit', function (event) {
                    if (websiteInput) {
                        normalizeHttpsField(websiteInput);
                    }

                    if (isSubmitting) {
                        return;
                    }

                    event.preventDefault();
                    var submitButton = reviewForm.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                    }

                    refreshReviewNonce(reviewForm)
                        .then(function () {
                            isSubmitting = true;
                            reviewForm.submit();
                        })
                        .catch(function () {
                            if (submitButton) {
                                submitButton.disabled = false;
                            }
                            window.alert('Unable to verify the form right now. Please refresh the page and try again.');
                        });
                });
            }
        });
    });
}());
