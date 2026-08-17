<?php
/**
 * Plugin Name: Science180 Review Requests and Endorsements
 * Description: Book review copy requests, email-verified endorsements, moderation, public endorsement pages, and admin book cover management for Science180.
 * Version: 1.0.5
 * Author: Science180
 * Text Domain: science180-review-endorsements
 */

if (!defined('ABSPATH')) {
    exit;
}

define('S180RE_VERSION', '1.0.5');
define('S180RE_PLUGIN_FILE', __FILE__);
define('S180RE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('S180RE_PLUGIN_URL', plugin_dir_url(__FILE__));

function s180re_split_plugins_are_active()
{
    $active_plugins = (array) get_option('active_plugins', array());
    $network_plugins = is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', array())) : array();
    $active_plugins = array_merge($active_plugins, $network_plugins);

    return in_array('science180-book-review/science180-book-review.php', $active_plugins, true)
        && in_array('science180-endorsement/science180-endorsement.php', $active_plugins, true);
}

if (s180re_split_plugins_are_active()) {
    add_action(
        'admin_notices',
        function () {
            if (!current_user_can('activate_plugins')) {
                return;
            }
            echo '<div class="notice notice-info"><p>';
            echo esc_html__('Science180 Review Requests and Endorsements is now split into the separate Book Review and Endorsement plugins. This legacy combined plugin is inactive while both split plugins are active.', 'science180-review-endorsements');
            echo '</p></div>';
        }
    );
    return;
}

require_once S180RE_PLUGIN_DIR . 'includes/class-s180re-plugin.php';

register_activation_hook(__FILE__, array('S180RE_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('S180RE_Plugin', 'deactivate'));

S180RE_Plugin::instance();
