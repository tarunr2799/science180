<?php
/**
 * Plugin Name: Science180 Endorsement
 * Description: Email-verified public endorsements, moderation, public endorsement pages, and daily admin review notices for Science180.
 * Version: 1.0.22
 * Author: Science180
 * Text Domain: science180-endorsement
 */

if (!defined('ABSPATH')) {
    exit;
}

define('S180EN_VERSION', '1.0.22');
define('S180EN_PLUGIN_FILE', __FILE__);
define('S180EN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('S180EN_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once S180EN_PLUGIN_DIR . 'includes/class-s180en-plugin.php';

register_activation_hook(__FILE__, array('S180EN_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('S180EN_Plugin', 'deactivate'));

S180EN_Plugin::instance();
