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
                var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

                $('#s180re-endorsement-photo-id').val(attachment.id);
                $('#s180re-endorsement-photo-url').val(url);
                $('#s180re-endorsement-photo-preview').html('<img class="s180re-admin-photo" src="' + url + '" alt="">');
            });

            photoFrame.open();
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
