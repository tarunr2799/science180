<?php
// Run: php tests/mail-regressions.php path/to/wordpress [path/to/prlist-source.html]
$root = $argv[1] ?? '';
if (!is_file($root . '/wp-includes/formatting.php')) { exit("Provide a WordPress source directory.\n"); }
define('ABSPATH', rtrim($root, '/\\') . '/');
define('WPINC', 'wp-includes');
define('WP_DEBUG', false);
define('DAY_IN_SECONDS', 86400);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('ADVNEWS_TABLE_PREFIX', 'emails_advnews_');
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/compat.php';
require ABSPATH . 'wp-includes/formatting.php';
require ABSPATH . 'wp-includes/kses.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
require ABSPATH . 'wp-includes/class-wp-token-map.php';
require ABSPATH . 'wp-includes/html-api/class-wp-html-tag-processor.php';
foreach (glob(ABSPATH . 'wp-includes/html-api/*.php') as $file) { require_once $file; }
function get_bloginfo($key) { return 'UTF-8'; }
function add_shortcode($tag, $callback) {}
function home_url($path = '') { return 'https://science180.net' . ($path === '' ? '' : '/' . ltrim($path, '/')); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_allowed_protocols() { return array('http','https','mailto','tel','sms','ftp'); }
function wp_is_valid_utf8($text) { return preg_match('//u', $text) === 1; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function wp_next_scheduled($hook) { return $GLOBALS['next']; }
function wp_get_schedule($hook) { return 'daily'; }
function _get_cron_array() { return array($GLOBALS['next'] => array('advnews_update_maxmind_database' => array(array()))); }
function wp_clear_scheduled_hook($hook) { $GLOBALS['schedule_writes']++; }
function wp_schedule_event(...$args) { $GLOBALS['schedule_writes']++; return true; }
function get_transient($key) { return $GLOBALS['locked']; }
function wp_generate_password($length, $special = true) { return bin2hex(random_bytes(16)); }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function wp_timezone() { return new DateTimeZone('UTC'); }
function check($actual, $expected, $name) {
    if ($actual !== $expected) { throw new RuntimeException($name . ': ' . var_export($actual, true)); }
    $GLOBALS['checks']++;
}
$GLOBALS['checks'] = 0;
$GLOBALS['wpdb'] = new class {
    public $prefix = 'wp_';
    public $links = array();
    function prepare($query, ...$args) { return $query; }
    function get_row($query) { return null; }
    function insert($table, $data) { $this->links[] = $data; return true; }
};
$plugin = dirname(__DIR__) . '/wp-content/plugins/AdvNews Manager/includes/';
require $plugin . 'functions.php';
require $plugin . 'class-queue.php';
require $plugin . 'class-cron.php';
$cases = array(
    'https:www.globaldiasporanews.com/article/' => 'https://www.globaldiasporanews.com/article/',
    'https:science180.com/article/' => 'https://science180.com/article/',
    'http:/example.org/a?x=1&amp;y=2#part' => 'http://example.org/a?x=1&y=2#part',
    'https://example.org/a?x=1&y=2#part' => 'https://example.org/a?x=1&y=2#part',
    '//example.org/a' => 'https://example.org/a',
    'www.example.org/a' => 'https://www.example.org/a',
    'https://science180.net/www.globaldiasporanews.com/a?x=1#part' => 'https://www.globaldiasporanews.com/a?x=1#part',
    '/science180.com/a?x=1#part' => 'https://science180.com/a?x=1#part',
    '/download.pdf?x=1#part' => 'https://science180.net/download.pdf?x=1#part',
    'https://science180.net/download.php?x=1' => 'https://science180.net/download.php?x=1',
);
foreach ($cases as $input => $expected) { check(advnews_normalize_tracking_redirect_url($input), $expected, $input); }
$html = '<div style="color:red"><a HREF = https:science180.com/a>One</a><a href="mailto:test@example.org">Mail</a><a href="#section">Anchor</a><img src="/cover.jpg"></div>';
$fixed = advnews_normalize_email_links($html);
$p = new WP_HTML_Tag_Processor($fixed);
$p->next_tag('A'); check($p->get_attribute('href'), 'https://science180.com/a', 'HTML destination');
$p->next_tag('A'); check($p->get_attribute('href'), 'mailto:test@example.org', 'Mail unchanged');
$p->next_tag('A'); check($p->get_attribute('href'), '#section', 'Anchor unchanged');
check(strpos($fixed, '<img src="/cover.jpg">') !== false, true, 'Image unchanged');
$queue = new AdvNews_Queue();
$method = new ReflectionMethod($queue, 'replace_tracking_links');
$method->setAccessible(true);
$tracked = $method->invoke($queue, $html, 99999, 99999);
check(count($GLOBALS['wpdb']->links), 1, 'Only web links tracked');
check($GLOBALS['wpdb']->links[0]['original_url'], 'https://science180.com/a', 'Saved tracking destination');
if (isset($argv[2])) {
    $source = file_get_contents($argv[2]);
    $doc = new DOMDocument(); @$doc->loadHTML($source);
    $count = 0; $repaired = 0;
    foreach ($doc->getElementsByTagName('a') as $anchor) {
        $href = $anchor->getAttribute('href');
        if (preg_match('~^https?:~i', $href)) {
            $fixed = advnews_normalize_tracking_redirect_url($href);
            check((bool) parse_url($fixed, PHP_URL_HOST), true, 'Source link has a host');
            if ($fixed !== $href) { $repaired++; }
            $count++;
        }
    }
    echo "Source web links checked: $count; repaired: $repaired\n";
}
$GLOBALS['options'] = array('advnews_geolocation_service'=>'maxmind','advnews_maxmind_auto_update'=>true,'advnews_maxmind_license_key'=>'test','advnews_maxmind_last_update'=>time()-3600,'advnews_maxmind_last_attempt'=>123);
$GLOBALS['next'] = time()+86400;
$GLOBALS['schedule_writes'] = 0;
$GLOBALS['locked'] = false;
AdvNews_Cron::update_maxmind_database();
check(get_option('advnews_maxmind_last_attempt'), 123, 'Recent success skip leaves attempt unchanged');
check($GLOBALS['schedule_writes'], 0, 'Recent success keeps future schedule');
$GLOBALS['options']['advnews_maxmind_last_update'] = time()-3*86400;
$GLOBALS['next'] = time()-60;
AdvNews_Cron::ensure_maxmind_update_schedule();
check($GLOBALS['schedule_writes'], 0, 'Overdue event retained for cron');
$GLOBALS['locked'] = true;
AdvNews_Cron::update_maxmind_database();
check(get_option('advnews_maxmind_last_attempt'), 123, 'Locked skip leaves attempt unchanged');
echo "PASS: " . $GLOBALS['checks'] . " regression checks\n";
