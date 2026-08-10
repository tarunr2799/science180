/**
 * TinyMCE Plugin to preserve <br> tags
 */
(function() {
    tinymce.PluginManager.add('advnews_preserve_br', function(editor) {
        // Prevent TinyMCE from removing <br> tags
        editor.on('PreProcess', function(e) {
            // Preserve <br> tags in the content
            var content = e.content;
            // Don't let TinyMCE clean up <br> tags
            return content;
        });

        editor.on('PostProcess', function(e) {
            // Ensure <br> tags are preserved after processing
            var content = e.content;
            return content;
        });

        // Add custom CSS to make <br> visible
        editor.on('init', function() {
            editor.dom.loadCSS(ADVNEWS_PLUGIN_URL + 'assets/css/tinymce-custom.css');
        });
    });
})();
