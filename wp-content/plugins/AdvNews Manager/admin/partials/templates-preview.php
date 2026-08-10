<?php
// admin/partials/templates-preview.php
if (!defined('ABSPATH')) exit;

$template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$template = $this->get_template($template_id);

if (!$template) {
    wp_die(__('Template not found.', 'advnews-manager'));
}

// Process merge tags with sample data for preview
$sample_data = array(
    'first_name' => 'John',
    'last_name' => 'Doe',
    'full_name' => 'John Doe',
    'email' => 'john.doe@example.com',
    'organization' => 'ACME Inc',
    'current_date' => date_i18n(get_option('date_format')),
    'current_year' => date('Y'),
    'site_name' => get_bloginfo('name'),
    'site_url' => home_url(),
    'unsubscribe_link' => '#'
);

$content = $template->content;
foreach ($sample_data as $tag => $value) {
    $content = str_replace('[' . $tag . ']', $value, $content);
}

$css = $template->css;
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($template->name); ?> - <?php _e('Preview', 'advnews-manager'); ?></title>
    <style>
        /* Preview frame styles */
        body {
            margin: 0;
            padding: 20px;
            background: #f0f0f1;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }

        .preview-header {
            max-width: 600px;
            margin: 0 auto 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 15px 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }

        .preview-header h1 {
            margin: 0 0 10px;
            font-size: 18px;
            color: #1d2327;
        }

        .preview-header p {
            margin: 5px 0;
            color: #646970;
            font-size: 13px;
        }

        .preview-header .note {
            background: #f8d7da;
            color: #721c24;
            padding: 8px 12px;
            border-radius: 3px;
            margin-top: 10px;
        }

        .preview-device-bar {
            max-width: 600px;
            margin: 0 auto 10px;
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }

        .device-btn {
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
            border-radius: 3px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 12px;
        }

        .device-btn.active {
            background: #2271b1;
            color: #fff;
            border-color: #2271b1;
        }

        .preview-container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: max-width 0.3s ease;
        }

        .preview-iframe {
            width: 100%;
            height: 600px;
            border: none;
            display: block;
        }

        .preview-footer {
            max-width: 600px;
            margin: 20px auto 0;
            text-align: center;
            color: #646970;
            font-size: 12px;
        }

        .preview-footer a {
            color: #2271b1;
            text-decoration: none;
        }

        .preview-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="preview-header">
        <h1><?php echo esc_html($template->name); ?></h1>
        <p><strong><?php _e('Subject:', 'advnews-manager'); ?></strong> <?php echo esc_html($template->subject); ?></p>
        <p><strong><?php _e('Created:', 'advnews-manager'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($template->created_at))); ?></p>
        <p><strong><?php _e('Last Modified:', 'advnews-manager'); ?></strong> <?php echo esc_html(human_time_diff(strtotime($template->updated_at), current_time('timestamp')) . ' ' . __('ago', 'advnews-manager')); ?></p>
        <p class="note">
            <strong><?php _e('Preview Note:', 'advnews-manager'); ?></strong>
            <?php _e('This preview uses sample data. Actual emails will use real subscriber information.', 'advnews-manager'); ?>
        </p>
    </div>

    <div class="preview-device-bar">
        <button type="button" class="device-btn active" data-width="600"><?php _e('Desktop', 'advnews-manager'); ?></button>
        <button type="button" class="device-btn" data-width="768"><?php _e('Tablet', 'advnews-manager'); ?></button>
        <button type="button" class="device-btn" data-width="375"><?php _e('Mobile', 'advnews-manager'); ?></button>
    </div>

    <div class="preview-container" id="preview-container">
        <iframe class="preview-iframe" id="preview-iframe"
                title="<?php esc_attr_e('Template Preview', 'advnews-manager'); ?>"
                srcdoc="<?php
                    // Build the HTML content for the iframe
                    $iframe_content = '<!DOCTYPE html>';
                    $iframe_content .= '<html>';
                    $iframe_content .= '<head>';
                    $iframe_content .= '<meta charset="UTF-8">';
                    $iframe_content .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';

                    // Add template CSS inside style tags - THIS IS THE KEY FIX
                    if (!empty($css)) {
                        $iframe_content .= '<style>' . $css . '</style>';
                    }

                    $iframe_content .= '</head>';
                    $iframe_content .= '<body>' . $content . '</body>';
                    $iframe_content .= '</html>';

                    echo esc_attr($iframe_content);
                ?>"></iframe>
    </div>

    <div class="preview-footer">
        <p>
            <a href="<?php echo admin_url('admin.php?page=advnews-templates&action=edit&id=' . $template_id); ?>">
                <?php _e('Edit Template', 'advnews-manager'); ?>
            </a> |
            <a href="<?php echo admin_url('admin.php?page=advnews-templates'); ?>">
                <?php _e('Back to Templates', 'advnews-manager'); ?>
            </a>
        </p>
    </div>

    <script>
        document.querySelectorAll('.device-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.device-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                var width = this.dataset.width;
                document.getElementById('preview-container').style.maxWidth = width + 'px';
            });
        });
    </script>
</body>
</html>
<?php
exit;
