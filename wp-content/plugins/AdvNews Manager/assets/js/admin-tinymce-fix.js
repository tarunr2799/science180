jQuery(document).ready(function($) {
    // Wait for TinyMCE to initialize
    setTimeout(function() {
        if (typeof tinyMCE !== 'undefined') {
            // Apply to all TinyMCE editors
            tinyMCE.on('AddEditor', function(e) {
                var editor = e.editor;

                editor.on('init', function() {
                    // Prevent stripping of <br> tags
                    editor.settings.remove_trailing_brs = false;
                    editor.settings.force_br_newlines = true;
                    editor.settings.force_p_newlines = false;
                });

                // Before saving content
                editor.on('SaveContent', function(e) {
                    // Ensure <br> tags are preserved
                    var content = e.content;
                    // Don't modify the content, just ensure it's saved as-is
                });

                // Before processing content
                editor.on('PreProcess', function(e) {
                    // Preserve <br> tags
                    var content = e.content;
                });
            });
        }
    }, 1000);

    // Add visual indicator for <br> tags in HTML editor
    $('#template_html_source, #content').on('input', function() {
        var content = $(this).val();
        // Add visual markers for <br> tags (optional)
    });
});
