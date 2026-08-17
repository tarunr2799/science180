<?php
/**
 * Plugin Name: Science180 Book Review
 * Description: Book review copy requests, admin book cover management, reviewer status notifications, and public review request pages for Science180.
 * Version: 1.0.5
 * Author: Science180
 * Text Domain: science180-book-review
 */

if (!defined('ABSPATH')) {
    exit;
}

define('S180BR_VERSION', '1.0.5');
define('S180BR_PLUGIN_FILE', __FILE__);
define('S180BR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('S180BR_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once S180BR_PLUGIN_DIR . 'includes/class-s180br-plugin.php';

register_activation_hook(__FILE__, array('S180BR_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('S180BR_Plugin', 'deactivate'));

S180BR_Plugin::instance();
