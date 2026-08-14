<?php
/**
 * Plugin Name: Science180 Review Requests and Endorsements
 * Description: Book review copy requests, email-verified endorsements, moderation, public endorsement pages, and admin book cover management for Science180.
 * Version: 1.0.0
 * Author: Science180
 * Text Domain: science180-review-endorsements
 */

if (!defined('ABSPATH')) {
    exit;
}

define('S180RE_VERSION', '1.0.0');
define('S180RE_PLUGIN_FILE', __FILE__);
define('S180RE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('S180RE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once S180RE_PLUGIN_DIR . 'includes/class-s180re-plugin.php';

register_activation_hook(__FILE__, array('S180RE_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('S180RE_Plugin', 'deactivate'));

S180RE_Plugin::instance();
