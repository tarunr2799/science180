<?php
// File: includes/class-database.php
class AdvNews_Database
{
    private $wpdb;
    private $charset_collate;
    private $table_prefix;
    private $db_version_option = 'advnews_db_version';
    // BUMP VERSION TO FORCE UPGRADE
    private $current_db_version = '1.0.6';

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = ADVNEWS_TABLE_PREFIX;
        $this->charset_collate = $wpdb->get_charset_collate();
    }

    /**
     * Create all database tables
     */
    public function create_tables()
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $installed_version = get_option($this->db_version_option, '0');

        // Create all base tables
        $this->create_categories_table();
        $this->create_subscribers_table();
        $this->create_subscriber_categories_table();
        $this->create_campaigns_table();
        $this->create_campaign_logs_table();
        $this->create_tracking_opens_table();
        $this->create_tracking_clicks_table();
        $this->create_templates_table();
        $this->create_links_table();
        $this->create_settings_table();
        $this->create_geolocation_cache_table();
        $this->create_suppression_list_table();
        $this->create_email_previews_table();
        $this->create_activity_log_table();
        $this->create_template_categories_table();

        // NEW: Create campaign categories junction table
        $this->create_campaign_categories_table();

        // Run version-specific upgrades
        if (version_compare($installed_version, '1.0.1', '<')) {
            $this->upgrade_to_1_0_1();
        }
        if (version_compare($installed_version, '1.0.2', '<')) {
            $this->upgrade_to_1_0_2();
        }
        if (version_compare($installed_version, '1.0.3', '<')) {
            $this->upgrade_to_1_0_3();
        }
        if (version_compare($installed_version, '1.0.4', '<')) {
            $this->upgrade_to_1_0_4();
        }
        // Run 1.0.5 upgrade if needed (for multi-category campaigns)
        if (version_compare($installed_version, '1.0.5', '<')) {
            $this->upgrade_to_1_0_5();
        }
        // Run 1.0.6 upgrade (Force check for junction table integrity)
        if (version_compare($installed_version, '1.0.6', '<')) {
            $this->upgrade_to_1_0_6();
        }

        // Add default settings
        $this->add_default_settings();

        // Add foreign keys
        $this->add_foreign_keys();

        // Update database version
        update_option($this->db_version_option, $this->current_db_version);
    }

    private function create_categories_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'categories';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT,
            color VARCHAR(7) DEFAULT '#3498db',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY name (name)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_subscribers_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'subscribers';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            organization VARCHAR(255),
            status ENUM('active', 'unsubscribed', 'bounced') DEFAULT 'active',
            subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            unsubscribed_at DATETIME NULL,
            unsubscribe_reason VARCHAR(255),
            last_email_sent DATETIME NULL,
            last_activity_at DATETIME NULL,
            total_opens INT DEFAULT 0,
            total_clicks INT DEFAULT 0,
            open_rate DECIMAL(5,2) DEFAULT 0,
            click_rate DECIMAL(5,2) DEFAULT 0,
            engagement_score INT DEFAULT 0,
            timezone VARCHAR(50),
            language VARCHAR(10),
            ip_address VARCHAR(45),
            email_verified TINYINT(1) DEFAULT 0,
            confirmation_token VARCHAR(100),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY subscribed_at (subscribed_at),
            KEY last_activity (last_activity_at),
            KEY engagement_score (engagement_score),
            KEY email_status (email, status)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_subscriber_categories_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscriber_id BIGINT(20) UNSIGNED NOT NULL,
            category_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY subscriber_category (subscriber_id, category_id),
            KEY subscriber_id (subscriber_id),
            KEY category_id (category_id)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_campaigns_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            preview_hash VARCHAR(32) NULL,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            category_id BIGINT(20) UNSIGNED NULL,
            content LONGTEXT NOT NULL,
            template_id BIGINT(20) UNSIGNED NULL,
            from_name VARCHAR(255),
            from_email VARCHAR(255),
            reply_to VARCHAR(255),
            status ENUM('draft', 'scheduled', 'sending', 'sent', 'paused') DEFAULT 'draft',
            scheduled_for DATETIME NULL,
            sent_at DATETIME NULL,
            total_recipients INT DEFAULT 0,
            sent_count INT DEFAULT 0,
            delivered_count INT DEFAULT 0,
            open_count INT DEFAULT 0,
            click_count INT DEFAULT 0,
            bounce_count INT DEFAULT 0,
            unsubscribe_count INT DEFAULT 0,
            open_rate DECIMAL(5,2) DEFAULT 0,
            click_rate DECIMAL(5,2) DEFAULT 0,
            spam_score DECIMAL(5,2) NULL,
            spam_report TEXT NULL,
            priority ENUM('high', 'normal', 'low') DEFAULT 'normal',
            track_opens TINYINT(1) DEFAULT 1,
            track_clicks TINYINT(1) DEFAULT 1,
            respect_cooldown TINYINT(1) DEFAULT 1,
            is_ab_test TINYINT(1) DEFAULT 0,
            ab_test_id BIGINT(20) UNSIGNED NULL,
            test_winner_id BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY category_id (category_id),
            KEY scheduled_for (scheduled_for),
            KEY sent_at (sent_at),
            KEY preview_hash (preview_hash)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    /**
     * Create campaign_logs table - UPDATED with send_after column
     */
    private function create_campaign_logs_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT(20) UNSIGNED NOT NULL,
            subscriber_id BIGINT(20) UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            status ENUM('queued', 'sent', 'delivered', 'opened', 'clicked', 'bounced', 'unsubscribed', 'failed') DEFAULT 'queued',
            send_after DATETIME NULL,
            sent_at DATETIME NULL,
            delivered_at DATETIME NULL,
            opened_at DATETIME NULL,
            clicked_at DATETIME NULL,
            bounced_at DATETIME NULL,
            unsubscribe_at DATETIME NULL,
            bounce_type ENUM('hard', 'soft', 'blocked', 'spam') NULL,
            bounce_code VARCHAR(50) NULL,
            bounce_message TEXT NULL,
            retry_count INT DEFAULT 0,
            preview_count INT DEFAULT 0,
            last_preview_at DATETIME NULL,
            device_info TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_subscriber (campaign_id, subscriber_id),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY status (status),
            KEY send_after (send_after),
            KEY sent_at (sent_at),
            KEY bounce_type (bounce_type),
            KEY campaign_status (campaign_id, status)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_tracking_opens_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'tracking_opens';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_log_id BIGINT(20) UNSIGNED NOT NULL,
            subscriber_id BIGINT(20) UNSIGNED NOT NULL,
            campaign_id BIGINT(20) UNSIGNED NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            device_type VARCHAR(50),
            browser VARCHAR(100),
            platform VARCHAR(100),
            country VARCHAR(100),
            country_code VARCHAR(2),
            city VARCHAR(100),
            region VARCHAR(100),
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            timezone VARCHAR(50),
            opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_log_id (campaign_log_id),
            KEY subscriber_id (subscriber_id),
            KEY campaign_id (campaign_id),
            KEY opened_at (opened_at),
            KEY country_code (country_code),
            KEY city (city),
            KEY device_type (device_type),
            KEY idx_campaign_country (campaign_id, country_code)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_tracking_clicks_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_log_id BIGINT(20) UNSIGNED NOT NULL,
            subscriber_id BIGINT(20) UNSIGNED NOT NULL,
            campaign_id BIGINT(20) UNSIGNED NOT NULL,
            link_id BIGINT(20) UNSIGNED NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            device_type VARCHAR(50),
            browser VARCHAR(100),
            platform VARCHAR(100),
            country VARCHAR(100),
            country_code VARCHAR(2),
            city VARCHAR(100),
            region VARCHAR(100),
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            conversion_value DECIMAL(10,2) NULL,
            conversion_type VARCHAR(50) NULL,
            is_conversion TINYINT(1) DEFAULT 0,
            clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_log_id (campaign_log_id),
            KEY subscriber_id (subscriber_id),
            KEY campaign_id (campaign_id),
            KEY link_id (link_id),
            KEY clicked_at (clicked_at),
            KEY country_code (country_code),
            KEY is_conversion (is_conversion),
            KEY idx_campaign_country (campaign_id, country_code)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_templates_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'templates';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            css LONGTEXT,
            category_id BIGINT(20) UNSIGNED NULL,
            thumbnail VARCHAR(255) NULL,
            is_responsive TINYINT(1) DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            usage_count INT DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY category_id (category_id),
            FULLTEXT KEY search (name, content)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_links_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'links';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT(20) UNSIGNED NOT NULL,
            original_url TEXT NOT NULL,
            tracking_hash VARCHAR(32) NOT NULL,
            click_count INT DEFAULT 0,
            unique_click_count INT DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tracking_hash (tracking_hash),
            KEY campaign_id (campaign_id),
            KEY click_count (click_count)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_settings_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'settings';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            option_key VARCHAR(255) NOT NULL,
            option_value LONGTEXT,
            autoload TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY option_key (option_key)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_geolocation_cache_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'geolocation_cache';
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
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_suppression_list_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'suppression_list';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            reason ENUM('bounce', 'complaint', 'unsubscribe', 'manual') NOT NULL,
            source VARCHAR(100),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY reason (reason),
            KEY created_at (created_at)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_email_previews_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'email_previews';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT(20) UNSIGNED NOT NULL,
            subscriber_id BIGINT(20) UNSIGNED NULL,
            preview_hash VARCHAR(32) NOT NULL,
            viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            device_type VARCHAR(50) NULL,
            browser VARCHAR(100) NULL,
            platform VARCHAR(100) NULL,
            country VARCHAR(100),
            country_code VARCHAR(2),
            city VARCHAR(100),
            PRIMARY KEY (id),
            UNIQUE KEY preview_hash (preview_hash),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY viewed_at (viewed_at)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    private function create_activity_log_table()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'activity_log';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscriber_id BIGINT(20) UNSIGNED NOT NULL,
            activity_type ENUM('subscribed', 'unsubscribed', 'opened', 'clicked', 'bounced', 'complained', 'updated') NOT NULL,
            campaign_id BIGINT(20) UNSIGNED NULL,
            link_id BIGINT(20) UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            country VARCHAR(100),
            country_code VARCHAR(2),
            city VARCHAR(100),
            metadata LONGTEXT NULL COMMENT 'JSON metadata',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subscriber_id (subscriber_id),
            KEY activity_type (activity_type),
            KEY campaign_id (campaign_id),
            KEY created_at (created_at),
            KEY country_code (country_code),
            KEY idx_activity_composite (subscriber_id, activity_type, created_at)
        ) $this->charset_collate;";
        dbDelta($sql);
    }

    /**
     * NEW: Create campaign_categories junction table
     */
    private function create_campaign_categories_table() {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaign_categories';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT(20) UNSIGNED NOT NULL,
            category_id BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_category (campaign_id, category_id),
            KEY campaign_id (campaign_id),
            KEY category_id (category_id)
        ) $this->charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private function add_foreign_keys()
    {
        // Subscriber categories foreign keys
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories',
            'fk_sub_cat_subscriber',
            'subscriber_id',
            $this->wpdb->prefix . $this->table_prefix . 'subscribers',
            'id',
            'CASCADE'
        );
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories',
            'fk_sub_cat_category',
            'category_id',
            $this->wpdb->prefix . $this->table_prefix . 'categories',
            'id',
            'CASCADE'
        );

        // Campaigns foreign key (Keep for backward compatibility, but logic moves to junction)
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'campaigns',
            'fk_campaign_category',
            'category_id',
            $this->wpdb->prefix . $this->table_prefix . 'categories',
            'id',
            'SET NULL'
        );

        // Campaign logs foreign keys
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'campaign_logs',
            'fk_log_campaign',
            'campaign_id',
            $this->wpdb->prefix . $this->table_prefix . 'campaigns',
            'id',
            'CASCADE'
        );
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'campaign_logs',
            'fk_log_subscriber',
            'subscriber_id',
            $this->wpdb->prefix . $this->table_prefix . 'subscribers',
            'id',
            'CASCADE'
        );

        // Tracking opens foreign key
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'tracking_opens',
            'fk_open_campaign_log',
            'campaign_log_id',
            $this->wpdb->prefix . $this->table_prefix . 'campaign_logs',
            'id',
            'CASCADE'
        );

        // Tracking clicks foreign key
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'tracking_clicks',
            'fk_click_campaign_log',
            'campaign_log_id',
            $this->wpdb->prefix . $this->table_prefix . 'campaign_logs',
            'id',
            'CASCADE'
        );

        // Links foreign key
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'links',
            'fk_link_campaign',
            'campaign_id',
            $this->wpdb->prefix . $this->table_prefix . 'campaigns',
            'id',
            'CASCADE'
        );

        // Templates foreign key
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'templates',
            'fk_template_category',
            'category_id',
            $this->wpdb->prefix . $this->table_prefix . 'categories',
            'id',
            'SET NULL'
        );

        // Email previews foreign key
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'email_previews',
            'fk_preview_campaign',
            'campaign_id',
            $this->wpdb->prefix . $this->table_prefix . 'campaigns',
            'id',
            'CASCADE'
        );

        // Activity log foreign key
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'activity_log',
            'fk_activity_subscriber',
            'subscriber_id',
            $this->wpdb->prefix . $this->table_prefix . 'subscribers',
            'id',
            'CASCADE'
        );

        // NEW: Campaign Categories foreign keys
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'campaign_categories',
            'fk_camp_cat_campaign',
            'campaign_id',
            $this->wpdb->prefix . $this->table_prefix . 'campaigns',
            'id',
            'CASCADE'
        );
        $this->add_foreign_key_if_not_exists(
            $this->wpdb->prefix . $this->table_prefix . 'campaign_categories',
            'fk_camp_cat_category',
            'category_id',
            $this->wpdb->prefix . $this->table_prefix . 'categories',
            'id',
            'CASCADE'
        );
    }

    private function add_foreign_key_if_not_exists($table, $constraint, $column, $ref_table, $ref_column, $on_delete = 'CASCADE')
    {
        $result = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = %s
            AND CONSTRAINT_NAME = %s",
            $table,
            $constraint
        ));

        if (empty($result)) {
            $sql = "ALTER TABLE $table ADD CONSTRAINT $constraint
            FOREIGN KEY ($column) REFERENCES $ref_table($ref_column) ON DELETE $on_delete";
            $this->wpdb->query($sql);
        }
    }

    private function add_default_settings()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'settings';
        $default_settings = array(
            array('db_version', $this->current_db_version, 1),
            array('installed_at', current_time('mysql'), 1),
            array('tracking_opens_count', '0', 1),
            array('tracking_clicks_count', '0', 1)
        );

        foreach ($default_settings as $setting) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM $table_name WHERE option_key = %s",
                $setting[0]
            ));

            if (!$exists) {
                $this->wpdb->insert(
                    $table_name,
                    array(
                        'option_key' => $setting[0],
                        'option_value' => $setting[1],
                        'autoload' => $setting[2]
                    )
                );
            }
        }
    }

    private function upgrade_to_1_0_1()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'templates';
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) return;

        $old_column = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'category'");
        if (!empty($old_column)) {
            $new_column = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'category_id'");
            if (empty($new_column)) {
                $this->wpdb->query("ALTER TABLE $table_name CHANGE COLUMN `category` `category_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL");
            } else {
                $this->wpdb->query("ALTER TABLE $table_name DROP COLUMN `category`");
            }
        } else {
            $new_column = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'category_id'");
            if (empty($new_column)) {
                $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN `category_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `css`");
            }
        }
    }

    private function upgrade_to_1_0_2()
    {
        $template_cats_table = $this->wpdb->prefix . $this->table_prefix . 'template_categories';
        $main_cats_table = $this->wpdb->prefix . $this->table_prefix . 'categories';
        $templates_table = $this->wpdb->prefix . $this->table_prefix . 'templates';

        $template_cats_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$template_cats_table'") == $template_cats_table;
        if ($template_cats_exists) {
            $template_cats = $this->wpdb->get_results("SELECT * FROM $template_cats_table");
            foreach ($template_cats as $cat) {
                $exists = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT id FROM $main_cats_table WHERE slug = %s",
                    $cat->slug
                ));
                if (!$exists) {
                    $this->wpdb->insert(
                        $main_cats_table,
                        array(
                            'name' => $cat->name,
                            'slug' => $cat->slug,
                            'description' => $cat->description,
                            'color' => '#3498db'
                        )
                    );
                    $main_cat_id = $this->wpdb->insert_id;
                } else {
                    $main_cat_id = $exists;
                }

                $this->wpdb->update(
                    $templates_table,
                    array('category_id' => $main_cat_id),
                    array('category_id' => $cat->id)
                );
            }
            $this->wpdb->query("DROP TABLE IF EXISTS $template_cats_table");
        }

        $this->wpdb->query("ALTER TABLE $templates_table DROP FOREIGN KEY IF EXISTS `fk_template_category`");
        $this->add_foreign_key_if_not_exists(
            $templates_table,
            'fk_template_category',
            'category_id',
            $main_cats_table,
            'id',
            'SET NULL'
        );
    }

    /**
     * Upgrade to 1.0.3 - Add send_after column for cooldown delay
     */
    private function upgrade_to_1_0_3()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaign_logs';
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            return;
        }

        // Check if column already exists
        $column_exists = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'send_after'");
        if (empty($column_exists)) {
            // Add the column
            $this->wpdb->query("ALTER TABLE $table_name ADD COLUMN `send_after` DATETIME NULL AFTER `status`");
            $this->wpdb->query("ALTER TABLE $table_name ADD KEY `send_after` (`send_after`)");
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AdvNews DB] Added send_after column to campaign_logs for cooldown support.');
            }
        }
    }

    public function tables_exist()
    {
        $tables = array(
            'categories', 'subscribers', 'subscriber_categories', 'campaigns',
            'campaign_logs', 'tracking_opens', 'tracking_clicks', 'templates',
            'links', 'settings', 'geolocation_cache', 'suppression_list',
            'email_previews', 'activity_log', 'campaign_categories'
        );

        foreach ($tables as $table) {
            $table_name = $this->wpdb->prefix . $this->table_prefix . $table;
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                return false;
            }
        }
        return true;
    }

    /**
     * Create template_categories junction table
     */
    private function create_template_categories_table() {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'template_categories';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id BIGINT(20) UNSIGNED NOT NULL,
            category_id BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY template_category (template_id, category_id),
            KEY template_id (template_id),
            KEY category_id (category_id)
        ) $this->charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Upgrade to 1.0.4 - Migrate single category_id to junction table for templates
     */
    private function upgrade_to_1_0_4() {
        $this->create_template_categories_table();
        $table_templates = $this->wpdb->prefix . $this->table_prefix . 'templates';
        $table_rel = $this->wpdb->prefix . $this->table_prefix . 'template_categories';

        // Check if old category_id column exists and migrate data
        $col_exists = $this->wpdb->get_results("SHOW COLUMNS FROM $table_templates LIKE 'category_id'");
        if (!empty($col_exists)) {
            $rows = $this->wpdb->get_results("SELECT id, category_id FROM $table_templates WHERE category_id IS NOT NULL AND category_id > 0");
            foreach ($rows as $row) {
                $this->wpdb->insert($table_rel, array(
                    'template_id' => intval($row->id),
                    'category_id' => intval($row->category_id)
                ));
            }
        }
        update_option('advnews_db_version', '1.0.4');
    }

    /**
     * NEW: Upgrade to 1.0.5 - Migrate single category_id to junction table for campaigns
     */
    private function upgrade_to_1_0_5() {
        $this->create_campaign_categories_table();
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $table_rel = $this->wpdb->prefix . $this->table_prefix . 'campaign_categories';

        // Check if old category_id column exists and migrate data
        $col_exists = $this->wpdb->get_results("SHOW COLUMNS FROM $table_campaigns LIKE 'category_id'");
        if (!empty($col_exists)) {
            $rows = $this->wpdb->get_results("SELECT id, category_id FROM $table_campaigns WHERE category_id IS NOT NULL AND category_id > 0");
            foreach ($rows as $row) {
                // Check if already migrated to avoid duplicates
                $exists = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_rel WHERE campaign_id = %d AND category_id = %d",
                    $row->id,
                    $row->category_id
                ));
                if (!$exists) {
                    $this->wpdb->insert($table_rel, array(
                        'campaign_id' => intval($row->id),
                        'category_id' => intval($row->category_id)
                    ));
                }
            }
        }
        update_option('advnews_db_version', '1.0.5');
    }

    /**
     * NEW: Upgrade to 1.0.6 - Ensure junction table exists and clean up
     */
    private function upgrade_to_1_0_6() {
        // Force recreate junction table to ensure structure is correct
        $this->create_campaign_categories_table();

        // Update version
        update_option('advnews_db_version', '1.0.6');
    }

    public function get_table($table_name)
    {
        return $this->wpdb->prefix . $this->table_prefix . $table_name;
    }

    public function column_exists($table, $column)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . $table;
        $result = $this->wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE '$column'");
        return !empty($result);
    }

    public function get_db_version()
    {
        return get_option($this->db_version_option, '0');
    }

    public function check_upgrade()
    {
        $installed_version = get_option($this->db_version_option, '0');
        if (version_compare($installed_version, $this->current_db_version, '<')) {
            $this->create_tables();
        }
    }
}
