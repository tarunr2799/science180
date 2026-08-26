(function ($) {
    $(function () {
        var coverFrame;
        var pdfFrame;

        $('#s180re-select-cover').on('click', function (event) {
            event.preventDefault();

            if (coverFrame) {
                coverFrame.open();
                return;
            }

            coverFrame = wp.media({
                title: s180reAdmin.chooseCover,
                button: {
                    text: s180reAdmin.useCover
                },
                multiple: false
            });

            coverFrame.on('select', function () {
                var attachment = coverFrame.state().get('selection').first().toJSON();
                var url = attachment.sizes && attachment.sizes.large ? attachment.sizes.large.url : attachment.url;

                $('#s180re-cover-id').val(attachment.id);
                $('#s180re-cover-url').val(url);
                $('#s180re-cover-preview').html('<img src="' + url + '" alt="">');
            });

            coverFrame.open();
        });

        $('#s180re-select-pdf').on('click', function (event) {
            event.preventDefault();

            if (pdfFrame) {
                pdfFrame.open();
                return;
            }

            pdfFrame = wp.media({
                title: s180reAdmin.choosePdf,
                button: {
                    text: s180reAdmin.usePdf
                },
                library: {
                    type: 'application/pdf'
                },
                multiple: false
            });

            pdfFrame.on('select', function () {
                var attachment = pdfFrame.state().get('selection').first().toJSON();

                $('#s180re-pdf-id').val(attachment.id);
                $('#s180re-pdf-url').val(attachment.url);
            });

            pdfFrame.open();
        });
    });
}(jQuery));
