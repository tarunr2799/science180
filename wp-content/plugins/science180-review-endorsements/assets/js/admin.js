(function ($) {
    $(function () {
        var frame;

        $('#s180re-select-cover').on('click', function (event) {
            event.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: s180reAdmin.chooseCover,
                button: {
                    text: s180reAdmin.useCover
                },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment.sizes && attachment.sizes.large ? attachment.sizes.large.url : attachment.url;

                $('#s180re-cover-id').val(attachment.id);
                $('#s180re-cover-url').val(url);
                $('#s180re-cover-preview').html('<img src="' + url + '" alt="">');
            });

            frame.open();
        });

        $('#s180re-select-all').on('change', function () {
            $('.s180re-bulk-check').prop('checked', this.checked);
        });

        $('.s180re-bulk-check').on('change', function () {
            var total = $('.s180re-bulk-check').length;
            var checked = $('.s180re-bulk-check:checked').length;
            $('#s180re-select-all').prop('checked', total > 0 && total === checked);
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
