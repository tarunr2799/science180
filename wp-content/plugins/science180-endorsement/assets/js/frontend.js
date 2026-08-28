(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    ready(function () {
        var triggers = document.querySelectorAll('.s180re-photo-lightbox-trigger');
        if (!triggers.length) {
            return;
        }

        var dialog = document.createElement('div');
        dialog.className = 's180re-photo-lightbox';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-label', 'Enlarged endorsement photo');
        dialog.hidden = true;
        dialog.innerHTML = '<button type="button" class="s180re-photo-lightbox-close" aria-label="Close enlarged photo">&times;</button><img class="s180re-photo-lightbox-image" src="" alt="">';
        document.body.appendChild(dialog);

        var image = dialog.querySelector('.s180re-photo-lightbox-image');
        var closeButton = dialog.querySelector('.s180re-photo-lightbox-close');
        var lastTrigger = null;

        function openLightbox(trigger) {
            var source = trigger.querySelector('img');
            if (!source) {
                return;
            }
            lastTrigger = trigger;
            image.src = source.currentSrc || source.src;
            image.alt = source.alt || '';
            dialog.hidden = false;
            document.body.classList.add('s180re-lightbox-open');
            closeButton.focus();
        }

        function closeLightbox() {
            if (dialog.hidden) {
                return;
            }
            dialog.hidden = true;
            image.src = '';
            document.body.classList.remove('s180re-lightbox-open');
            if (lastTrigger) {
                lastTrigger.focus();
            }
        }

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                openLightbox(trigger);
            });
        });

        closeButton.addEventListener('click', closeLightbox);
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                closeLightbox();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !dialog.hidden) {
                closeLightbox();
            }
        });
    });
}());
