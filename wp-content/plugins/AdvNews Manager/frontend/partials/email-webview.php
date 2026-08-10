<?php
// frontend/partials/email-webview.php
if (!defined('ABSPATH')) exit;

$campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
$subscriber_id = isset($_GET['subscriber_id']) ? intval($_GET['subscriber_id']) : 0;
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

// Verify token
$expected_token = AdvNews_Security::generate_hash($campaign_id . $subscriber_id . 'webview');
if ($token !== $expected_token) {
    wp_die(__('Invalid access token.', 'advnews-manager'));
}

$campaign_class = new AdvNews_Campaign();
$subscriber_class = new AdvNews_Subscriber();

$campaign = $campaign_class->get_campaign($campaign_id);
$subscriber = $subscriber_class->get_subscriber($subscriber_id);

if (!$campaign || !$subscriber) {
    wp_die(__('Campaign or subscriber not found.', 'advnews-manager'));
}

// Process content with subscriber data
$subscriber_data = array(
    'email' => $subscriber->email,
    'first_name' => $subscriber->first_name,
    'last_name' => $subscriber->last_name,
    'full_name' => trim($subscriber->first_name . ' ' . $subscriber->last_name),
    'organization' => $subscriber->organization
);

$content = $campaign_class->process_merge_tags($campaign->content, $subscriber_data);
$company_name = get_option('advnews_company_name', get_bloginfo('name'));
$current_year = date('Y');
$unsubscribe_link = add_query_arg(array(
    'email' => urlencode($subscriber->email),
    'token' => AdvNews_Security::generate_hash($subscriber->email . 'unsubscribe')
), get_permalink(get_option('advnews_unsubscribe_page_id')));
$preferences_link = add_query_arg(array(
    'email' => urlencode($subscriber->email),
    'token' => AdvNews_Security::generate_hash($subscriber->email . 'preferences')
), get_permalink(get_option('advnews_management_page_id')));
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($campaign->subject); ?> - <?php echo esc_html($company_name); ?></title>

    <style>
        /* Reset Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
            color: #333;
        }

        /* Webview Header */
        .webview-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .webview-header h1 {
            margin: 0 0 10px;
            font-size: 28px;
            font-weight: 600;
        }

        .webview-meta {
            font-size: 14px;
            opacity: 0.9;
        }

        .webview-meta span {
            display: inline-block;
            margin: 0 10px;
        }

        /* Email Container */
        .email-container {
            max-width: 600px;
            margin: 30px auto;
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .email-content {
            padding: 40px;
        }

        /* Email Content Styles */
        .email-content img {
            max-width: 100%;
            height: auto;
        }

        .email-content table {
            max-width: 100%;
            border-collapse: collapse;
        }

        .email-content a {
            color: #667eea;
            text-decoration: none;
        }

        .email-content a:hover {
            text-decoration: underline;
        }

        .email-content h1,
        .email-content h2,
        .email-content h3,
        .email-content h4,
        .email-content h5,
        .email-content h6 {
            margin: 1.5em 0 0.5em;
            font-weight: 600;
            line-height: 1.3;
        }

        .email-content h1 { font-size: 28px; }
        .email-content h2 { font-size: 24px; }
        .email-content h3 { font-size: 20px; }
        .email-content h4 { font-size: 18px; }
        .email-content h5 { font-size: 16px; }
        .email-content h6 { font-size: 14px; }

        .email-content p {
            margin-bottom: 1.5em;
        }

        .email-content ul,
        .email-content ol {
            margin: 1em 0 1.5em 2em;
        }

        .email-content blockquote {
            margin: 1.5em 0;
            padding: 15px 20px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            font-style: italic;
        }

        .email-content .button {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: #fff;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .email-content .button:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        /* Webview Footer */
        .webview-footer {
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            background: #f8f9fa;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            text-align: center;
            font-size: 13px;
        }

        .footer-links {
            margin-bottom: 15px;
        }

        .footer-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        .footer-info {
            color: #999;
            line-height: 1.6;
        }

        .footer-info p {
            margin: 5px 0;
        }

        .footer-credit {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
        }

        .footer-credit a {
            color: #999;
            text-decoration: none;
        }

        .footer-credit a:hover {
            color: #667eea;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .email-container,
            .webview-footer {
                margin: 15px;
            }

            .email-content {
                padding: 20px;
            }

            .webview-meta span {
                display: block;
                margin: 5px 0;
            }

            .footer-links a {
                display: inline-block;
                margin: 5px 10px;
            }
        }

        /* Print Styles */
        @media print {
            body {
                background: #fff;
            }

            .webview-header,
            .webview-footer {
                display: none;
            }

            .email-container {
                margin: 0;
                border: none;
                box-shadow: none;
            }
        }
    </style>

    <?php
    // Add custom CSS from template if any
    $template = $campaign_class->get_template($campaign->template_id);
    if ($template && !empty($template->css)) {
        echo '<style>' . wp_strip_all_tags($template->css) . '</style>';
    }
    ?>
</head>
<body>
    <div class="webview-header">
        <h1><?php echo esc_html($company_name); ?></h1>
        <div class="webview-meta">
            <span class="subject"><?php echo esc_html($campaign->subject); ?></span>
            <span class="date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($campaign->sent_at))); ?></span>
            <span class="recipient"><?php printf(__('For: %s', 'advnews-manager'), esc_html($subscriber->email)); ?></span>
        </div>
    </div>

    <div class="email-container">
        <div class="email-content">
            <?php echo $content; ?>
        </div>
    </div>

    <div class="webview-footer">
        <div class="footer-links">
            <a href="<?php echo esc_url($unsubscribe_link); ?>"><?php _e('Unsubscribe', 'advnews-manager'); ?></a>
            <a href="<?php echo esc_url($preferences_link); ?>"><?php _e('Manage Preferences', 'advnews-manager'); ?></a>
            <a href="<?php echo esc_url(home_url()); ?>"><?php _e('Visit Website', 'advnews-manager'); ?></a>
        </div>

        <div class="footer-info">
            <p>&copy; <?php echo esc_html($current_year); ?> <?php echo esc_html($company_name); ?>. <?php _e('All rights reserved.', 'advnews-manager'); ?></p>
            <?php if (get_option('advnews_company_address')): ?>
            <p><?php echo nl2br(esc_html(get_option('advnews_company_address'))); ?></p>
            <?php endif; ?>
        </div>

        <?php if (get_option('advnews_show_credit_link', true)): ?>
        <div class="footer-credit">
            <a href="https://example.com/advnews-manager" target="_blank" rel="noopener">
                <?php _e('Powered by AdvNews Manager', 'advnews-manager'); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
