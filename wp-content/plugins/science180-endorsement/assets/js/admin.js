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
