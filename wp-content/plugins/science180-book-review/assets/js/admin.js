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
    });
}(jQuery));
