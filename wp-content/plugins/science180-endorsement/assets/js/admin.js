(function ($) {
    $(function () {
        $('#s180re-select-all').on('change', function () {
            $('.s180re-bulk-check').prop('checked', this.checked);
        });

        $('.s180re-bulk-check').on('change', function () {
            var total = $('.s180re-bulk-check').length;
            var checked = $('.s180re-bulk-check:checked').length;
            $('#s180re-select-all').prop('checked', total > 0 && total === checked);
        });



        var photoFrame;
        var $lastZoomTrigger = $();
        var $photoZoom = $('<div class="s180re-photo-zoom" role="dialog" aria-modal="true" hidden>' +
            '<button type="button" class="s180re-photo-zoom-close" aria-label="' + s180reAdmin.closePhoto + '">&times;</button>' +
            '<img src="" alt="">' +
            '</div>').appendTo('body');

        function prepareZoomablePhotos() {
            $('.s180re-admin-photo').attr({
                role: 'button',
                tabindex: '0',
                title: s180reAdmin.viewPhoto
            });
        }

        function openPhotoZoom($photo) {
            $lastZoomTrigger = $photo;
            $photoZoom.find('img').attr({
                src: $photo.attr('src'),
                alt: $photo.attr('alt') || ''
            });
            $photoZoom.removeAttr('hidden');
            $('body').addClass('s180re-photo-zoom-open');
            $photoZoom.find('.s180re-photo-zoom-close').trigger('focus');
        }

        function closePhotoZoom() {
            if ($photoZoom.is('[hidden]')) {
                return;
            }

            $photoZoom.attr('hidden', 'hidden');
            $photoZoom.find('img').attr('src', '');
            $('body').removeClass('s180re-photo-zoom-open');
            $lastZoomTrigger.trigger('focus');
        }

        prepareZoomablePhotos();

        $(document).on('click', '.s180re-admin-photo', function () {
            openPhotoZoom($(this));
        });

        $(document).on('keydown', '.s180re-admin-photo', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openPhotoZoom($(this));
            }
        });

        $photoZoom.on('click', function (event) {
            if (event.target === this || $(event.target).closest('.s180re-photo-zoom-close').length) {
                closePhotoZoom();
            }
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                closePhotoZoom();
            }
        });

        $('#s180re-select-endorsement-photo').on('click', function (event) {
            event.preventDefault();

            if (photoFrame) {
                photoFrame.open();
                return;
            }

            photoFrame = wp.media({
                title: s180reAdmin.choosePhoto,
                button: {
                    text: s180reAdmin.usePhoto
                },
                multiple: false
            });

            photoFrame.on('select', function () {
                var attachment = photoFrame.state().get('selection').first().toJSON();
                var url = attachment.url;

                $('#s180re-endorsement-photo-id').val(attachment.id);
                $('#s180re-endorsement-photo-url').val(url);
                $('#s180re-remove-endorsement-photo').val('0');
                $('#s180re-endorsement-photo-preview').html(
                    '<button type="button" class="s180re-remove-photo" aria-label="Remove photo from endorsement" title="Remove photo">&times;</button>' +
                    '<img class="s180re-admin-photo" src="' + url + '" alt="">' +
                    '<p class="description s180re-photo-help">' + s180reAdmin.viewPhoto + '.</p>'
                );
                prepareZoomablePhotos();
            });

            photoFrame.open();
        });

        $(document).on('click', '.s180re-remove-photo', function () {
            $('#s180re-endorsement-photo-id').val('0');
            $('#s180re-endorsement-photo-url').val('');
            $('#s180re-remove-endorsement-photo').val('1');
            $('#s180re-endorsement-photo-preview').empty();
        });

        $('.s180re-bulk-form').on('submit', function (event) {
            var submitter = event.originalEvent && event.originalEvent.submitter;
            if (submitter && submitter.name === 's180re_single_action') {
                return true;
            }

            if ($('#s180re-bulk-action').val() !== 'delete') {
                return true;
            }

            return window.confirm('Delete the selected endorsements permanently?');
        });
    });
}(jQuery));
