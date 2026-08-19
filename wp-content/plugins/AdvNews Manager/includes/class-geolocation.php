<?php
/**
 * AdvNews Manager - Geolocation Tracking Class
 *
 * Handles IP geolocation for tracking opens and clicks
 *
 * @package AdvNews_Manager
 * @subpackage Tracking
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class AdvNews_Geolocation {

    /**
     * @var wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $table_prefix;

    /**
     * @var array Cache for geolocation data
     */
    private static $cache = array();

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = ADVNEWS_TABLE_PREFIX;
    }

    public function get_cached_location_from_db($ip) {
        return $this->get_cached_location($ip); // Reuses existing private method but made public access via wrapper if needed, or copy logic
    }


    /**
     * Get geolocation data for an IP address
     *
     * @param string $ip IP address
     * @return array Geolocation data (country, city, coordinates)
     */
    public function get_location($ip) {
        // Check cache first
        if (isset(self::$cache[$ip])) {
            return self::$cache[$ip];
        }

        // Skip local/private IPs
        if ($this->is_private_ip($ip)) {
            return array(
                'country' => 'Local',
                'country_code' => 'XX',
                'city' => 'Local',
                'region' => '',
                'latitude' => null,
                'longitude' => null,
                'timezone' => null
            );
        }

        // Try to get from database cache
        $cached = $this->get_cached_location($ip);
        if ($cached) {
            self::$cache[$ip] = $cached;
            return $cached;
        }

        // Get location from service
        $location = $this->get_location_from_service($ip);

        // Cache the result
        if ($location) {
            $this->cache_location($ip, $location);
            self::$cache[$ip] = $location;
        }

        return $location ?: array(
            'country' => 'Unknown',
            'country_code' => 'XX',
            'city' => 'Unknown',
            'region' => '',
            'latitude' => null,
            'longitude' => null,
            'timezone' => null
        );
    }

    /**
     * Check if IP is private/local
     *
     * @param string $ip
     * @return bool
     */
    private function is_private_ip($ip) {
        $private_ranges = array(
            '127.0.0.0/8',      // Loopback
            '10.0.0.0/8',       // Private
            '172.16.0.0/12',    // Private
            '192.168.0.0/16',   // Private
            '169.254.0.0/16',   // Link-local
            '::1/128',          // Localhost IPv6
            'fc00::/7',         // Unique Local Address IPv6
            'fe80::/10'         // Link-local IPv6
        );

        foreach ($private_ranges as $range) {
            if ($this->ip_in_range($ip, $range)) {
                return true;
            }
        }

        return false;
    }

        /**
        * Check if IP is in CIDR range
        *
        * @param string $ip
        * @param string $range CIDR range
        * @return bool
        */
        private function ip_in_range($ip, $range) {
            if (strpos($range, '/') === false) {
                return $ip === $range;
            }

            list($subnet, $bits) = explode('/', $range);
            $bits = (int)$bits;

            // Check IP versions to prevent mismatched calculations
            $is_ipv4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
            $subnet_is_ipv4 = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

            // If versions don't match, the IP cannot be in this range
            if ($is_ipv4 !== $subnet_is_ipv4) {
                return false;
            }

            if ($is_ipv4) {
                $ip_long = ip2long($ip);
                $subnet_long = ip2long($subnet);

                // Safely calculate mask without negative bit shift (PHP 8.1+ fix)
                $mask = $bits >= 32 ? -1 : (-1 << (32 - $bits));
                return ($ip_long & $mask) === ($subnet_long & $mask);
            } else {
                // IPv6 handling
                $ip_bin = inet_pton($ip);
                $subnet_bin = inet_pton($subnet);
                if ($ip_bin === false || $subnet_bin === false) {
                    return false;
                }

                // Build 16-byte binary mask safely
                $mask_bin = '';
                for ($i = 0; $i < 128; $i += 8) {
                    $byte_bits = min(8, max(0, $bits - $i));
                    if ($byte_bits === 8) {
                        $mask_bin .= chr(0xFF);
                    } elseif ($byte_bits === 0) {
                        $mask_bin .= chr(0x00);
                    } else {
                        $mask_bin .= chr((0xFF << (8 - $byte_bits)) & 0xFF);
                    }
                }

                return ($ip_bin & $mask_bin) === ($subnet_bin & $mask_bin);
            }
        }

    /**
    * Get cached location from database
    *
    * @param string $ip
    * @return array|false
    */
    private function get_cached_location($ip) {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'geolocation_cache';

        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            $this->create_cache_table();
        }

        $cached = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE ip_address = %s AND expires_at > NOW()",
            $ip
        ));

        if ($cached) {
            return array(
                'country' => $cached->country,
                'country_code' => $cached->country_code,
                'city' => $cached->city,
                'region' => $cached->region,
                'latitude' => $cached->latitude,
                'longitude' => $cached->longitude,
                'timezone' => $cached->timezone
            );
        }

        return false;
    }

    /**
     * Cache location in database
     *
     * @param string $ip
     * @param array $location
     */
    private function cache_location($ip, $location) {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'geolocation_cache';

        $this->wpdb->replace($table_name, array(
            'ip_address' => $ip,
            'country' => $location['country'],
            'country_code' => $location['country_code'],
            'city' => $location['city'],
            'region' => isset($location['region']) ? $location['region'] : '',
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'timezone' => $location['timezone'],
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'hits' => 1
        ));
    }

    /**
     * Get location from geolocation service
     *
     * @param string $ip
     * @return array|false
     */
    private function get_location_from_service($ip) {
        $service = get_option('advnews_geolocation_service', 'maxmind');
        $api_key = get_option('advnews_geolocation_api_key', '');

        switch ($service) {
            case 'ipapi':
                return $this->get_from_ipapi($ip);

            case 'ipstack':
                return $this->get_from_ipstack($ip, $api_key);

            case 'maxmind':
                return $this->get_from_maxmind($ip);

            case 'ipinfo':
                return $this->get_from_ipinfo($ip, $api_key);

            case 'abstract':
                return $this->get_from_abstract($ip, $api_key);

            default:
                return $this->get_from_maxmind($ip);
        }
    }

    /**
     * Get location from ip-api.com (free, no API key required)
     *
     * @param string $ip
     * @return array|false
     */
    private function get_from_ipapi($ip) {
        $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,regionName,city,lat,lon,timezone";

        $response = wp_remote_get($url, array(
            'timeout' => 5,
            'headers' => array(
                'User-Agent' => 'Science180 Mail/' . ADVNEWS_VERSION
            )
        ));

        if (is_wp_error($response)) {
            $this->log_error('ip-api.com error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && $data['status'] === 'success') {
            return array(
                'country' => $data['country'],
                'country_code' => $data['countryCode'],
                'city' => $data['city'],
                'region' => $data['regionName'],
                'latitude' => $data['lat'],
                'longitude' => $data['lon'],
                'timezone' => $data['timezone']
            );
        }

        return false;
    }

    /**
     * Get location from ipstack.com (requires API key)
     *
     * @param string $ip
     * @param string $api_key
     * @return array|false
     */
    private function get_from_ipstack($ip, $api_key) {
        if (empty($api_key)) {
            return $this->get_from_ipapi($ip); // Fallback to free service
        }

        $url = "http://api.ipstack.com/{$ip}?access_key={$api_key}&fields=country_name,country_code,city,latitude,longitude";

        $response = wp_remote_get($url, array('timeout' => 5));

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($data && !isset($data['error'])) {
            return array(
                'country' => $data['country_name'],
                'country_code' => $data['country_code'],
                'city' => $data['city'],
                'region' => '',
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'timezone' => null
            );
        }

        return false;
    }

    /**
     * Get location from ipinfo.io (requires API key)
     *
     * @param string $ip
     * @param string $api_key
     * @return array|false
     */
    private function get_from_ipinfo($ip, $api_key) {
        if (empty($api_key)) {
            return $this->get_from_ipapi($ip);
        }

        $url = "https://ipinfo.io/{$ip}/json?token={$api_key}";

        $response = wp_remote_get($url, array('timeout' => 5));

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($data && !isset($data['error'])) {
            // Parse location if available
            $loc = explode(',', $data['loc'] ?? '0,0');

            return array(
                'country' => $data['country'] ?? 'Unknown',
                'country_code' => $data['country'] ?? 'XX',
                'city' => $data['city'] ?? 'Unknown',
                'region' => $data['region'] ?? '',
                'latitude' => $loc[0] ?? null,
                'longitude' => $loc[1] ?? null,
                'timezone' => $data['timezone'] ?? null
            );
        }

        return false;
    }

    /**
     * Get location from AbstractAPI (requires API key)
     *
     * @param string $ip
     * @param string $api_key
     * @return array|false
     */
    private function get_from_abstract($ip, $api_key) {
        if (empty($api_key)) {
            return $this->get_from_ipapi($ip);
        }

        $url = "https://ipgeolocation.abstractapi.com/v1/?api_key={$api_key}&ip_address={$ip}";

        $response = wp_remote_get($url, array('timeout' => 5));

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($data && !isset($data['error'])) {
            return array(
                'country' => $data['country'] ?? 'Unknown',
                'country_code' => $data['country_code'] ?? 'XX',
                'city' => $data['city'] ?? 'Unknown',
                'region' => $data['region'] ?? '',
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'timezone' => $data['timezone'] ?? null
            );
        }

        return false;
    }

    /**
     * Get location from MaxMind GeoIP2
     */

    private function get_from_maxmind($ip) {
        $db_path = get_option('advnews_maxmind_db_path', '');
        if (empty($db_path) || !file_exists($db_path)) {
            $upload_dir = wp_upload_dir();
            $default_path = $upload_dir['basedir'] . '/advnews-maxmind/GeoLite2-City.mmdb';
            if (file_exists($default_path)) {
                $db_path = $default_path;
                update_option('advnews_maxmind_db_path', $default_path);
            }
        }

        if (empty($db_path) || !file_exists($db_path)) {
            return false;
        }

        // Load our self-contained reader
        require_once ADVNEWS_PLUGIN_DIR . 'includes/class-maxmind-reader.php';

        try {
            $reader = new AdvNews_MaxMind_Reader($db_path);
            $record = $reader->get($ip);

            if (!$record || !isset($record['country']['iso_code'])) {
                return false;
            }

            return array(
                'country'      => $record['country']['names']['en'] ?? 'Unknown',
                'country_code' => $record['country']['iso_code'] ?? 'XX',
                'city'         => $record['city']['names']['en'] ?? 'Unknown',
                'region'       => isset($record['subdivisions'][0]['names']['en']) ? $record['subdivisions'][0]['names']['en'] : '',
                'latitude'     => $record['location']['latitude'] ?? null,
                'longitude'    => $record['location']['longitude'] ?? null,
                'timezone'     => $record['location']['time_zone'] ?? null
            );
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews MaxMind] Error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Create geolocation cache table
     */
    private function create_cache_table() {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'geolocation_cache';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            country VARCHAR(100),
            country_code VARCHAR(2),
            city VARCHAR(100),
            region VARCHAR(100),
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            timezone VARCHAR(50),
            hits INT DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ip_address (ip_address),
            KEY expires_at (expires_at),
            KEY country_code (country_code),
            KEY city (city)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Log error
     *
     * @param string $message
     */
    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AdvNews Geolocation] ' . $message);
        }
    }

    /**
     * Get country statistics for analytics
     *
     * @param string $start_date Optional start date
     * @param string $end_date Optional end date
     * @return array
     */
    public function get_country_stats($start_date = null, $end_date = null) {
        $table_opens = $this->wpdb->prefix . $this->table_prefix . 'tracking_opens';
        $table_clicks = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';

        $open_where = array("country != ''", "country != 'Local'", "country != 'Unknown'");
        $click_where = array("country != ''", "country != 'Local'", "country != 'Unknown'");
        $city_where = array("country != ''", "country != 'Local'", "country != 'Unknown'", "city != ''", "city != 'Local'", "city != 'Unknown'");

        if ($start_date && $end_date) {
            $open_where[] = $this->wpdb->prepare(
                "opened_at BETWEEN %s AND %s",
                $start_date,
                $end_date
            );
            $click_where[] = $this->wpdb->prepare(
                "clicked_at BETWEEN %s AND %s",
                $start_date,
                $end_date
            );
            $city_where[] = $this->wpdb->prepare(
                "opened_at BETWEEN %s AND %s",
                $start_date,
                $end_date
            );
        }

        $open_where_clause = 'WHERE ' . implode(' AND ', $open_where);
        $click_where_clause = 'WHERE ' . implode(' AND ', $click_where);
        $city_where_clause = 'WHERE ' . implode(' AND ', $city_where);

        // Get opens by country
        $opens_by_country = $this->wpdb->get_results(
            "SELECT
                country,
                COUNT(*) as opens,
                COUNT(DISTINCT subscriber_id) as unique_visitors,
                COUNT(DISTINCT campaign_id) as campaigns
            FROM $table_opens
            $open_where_clause
            GROUP BY country
            ORDER BY opens DESC
            LIMIT 20"
        );

        // Get clicks by country
        $clicks_by_country = $this->wpdb->get_results(
            "SELECT
                country,
                COUNT(*) as clicks,
                COUNT(DISTINCT subscriber_id) as unique_clickers
            FROM $table_clicks
            $click_where_clause
            GROUP BY country
            ORDER BY clicks DESC
            LIMIT 20"
        );

        // Get city-level data
        $cities = $this->wpdb->get_results(
            "SELECT
                country,
                city,
                COUNT(*) as opens,
                COUNT(DISTINCT subscriber_id) as unique_visitors
            FROM $table_opens
            $city_where_clause
            GROUP BY country, city
            ORDER BY opens DESC
            LIMIT 30"
        );

        // Calculate percentages
        $total_opens = array_sum(array_column($opens_by_country, 'opens'));
        foreach ($opens_by_country as &$country) {
            $country->percentage = $total_opens > 0 ? round(($country->opens / $total_opens) * 100, 1) : 0;
        }

        return array(
            'countries' => $opens_by_country,
            'clicks' => $clicks_by_country,
            'cities' => $cities,
            'total_opens' => $total_opens
        );
    }

    /**
     * Get world map data for visualization
     *
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function get_map_data($start_date = null, $end_date = null) {
        $table_opens = $this->wpdb->prefix . $this->table_prefix . 'tracking_opens';

        $where = array("country_code != ''", "country_code != 'XX'");
        if ($start_date && $end_date) {
            $where[] = $this->wpdb->prepare(
                "opened_at BETWEEN %s AND %s",
                $start_date,
                $end_date
            );
        }
        $where_clause = 'WHERE ' . implode(' AND ', $where);

        return $this->wpdb->get_results(
            "SELECT
                country_code,
                country,
                COUNT(*) as opens,
                COUNT(DISTINCT subscriber_id) as unique_visitors
            FROM $table_opens
            $where_clause
            GROUP BY country_code, country
            ORDER BY opens DESC"
        );
    }


}

add_action('advnews_process_geolocation', function($ip) {
    $geo = new AdvNews_Geolocation();
    // Force refresh from service if not in DB
    $location = $geo->get_location($ip);
    // The get_location method already caches to DB if successful
});
