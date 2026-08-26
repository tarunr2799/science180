<?php
if (!defined('ABSPATH')) {
    exit;
}

class S180EN_Plugin
{
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', array($this, 'maybe_upgrade'), 5);
        add_action('init', array($this, 'register_rewrites'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_filter('document_title_parts', array($this, 'detail_title_parts'));
        add_filter('render_block', array($this, 'normalize_main_site_navigation_hrefs'), 20, 2);
        add_action('template_redirect', array($this, 'handle_verification_and_detail_routes'));
        add_action('template_redirect', array($this, 'render_shortcode_page_fallback'), 20);

        add_shortcode('science180_endorsement_form', array($this, 'render_endorsement_form_shortcode'));
        add_shortcode('science180_endorsements', array($this, 'render_endorsements_shortcode'));

        add_action('admin_post_nopriv_s180re_endorsement_submit', array($this, 'handle_endorsement_submission'));
        add_action('admin_post_s180re_endorsement_submit', array($this, 'handle_endorsement_submission'));
        add_action('admin_post_nopriv_s180re_endorsement_verify_code', array($this, 'handle_endorsement_code_verification'));
        add_action('admin_post_s180re_endorsement_verify_code', array($this, 'handle_endorsement_code_verification'));

        add_action('admin_post_s180re_moderate_endorsement', array($this, 'handle_moderate_endorsement'));
        add_action('admin_post_s180re_bulk_endorsements', array($this, 'handle_bulk_endorsements'));
        add_action('admin_post_s180re_delete_endorsement', array($this, 'handle_delete_endorsement'));
        add_action('admin_post_s180re_save_endorsement', array($this, 'handle_save_endorsement'));
        add_action('admin_post_s180en_save_settings', array($this, 'handle_save_settings'));

        add_action('s180re_daily_endorsement_notice', array($this, 'send_daily_endorsement_notice'));
    }

    public static function activate()
    {
        self::create_tables();
        self::seed_options();
        self::maybe_create_pages();
        self::schedule_daily_notice();
        self::register_rewrites_static();
        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook('s180re_daily_endorsement_notice');
        flush_rewrite_rules();
    }

    public function maybe_upgrade()
    {
        if (get_option('s180en_version') === S180EN_VERSION) {
            return;
        }

        self::create_tables();
        self::seed_options();
        self::maybe_create_pages();
        self::register_rewrites_static();
        flush_rewrite_rules(false);
        update_option('s180en_version', S180EN_VERSION);
    }

    private static function create_tables()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $endorsements = self::table_static('endorsements');

        $sql_endorsements = "CREATE TABLE {$endorsements} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(190) NOT NULL,
            first_name varchar(120) NOT NULL,
            last_name varchar(120) NOT NULL,
            country_origin varchar(120) NOT NULL,
            country_residence varchar(120) NOT NULL,
            organization varchar(255) NOT NULL,
            comment longtext NOT NULL,
            photo_id bigint(20) unsigned DEFAULT 0,
            photo_url text NULL,
            address text NULL,
            ip_address varchar(45) DEFAULT '',
            ip_city varchar(120) DEFAULT '',
            ip_country varchar(120) DEFAULT '',
            user_agent varchar(255) DEFAULT '',
            device_type varchar(40) DEFAULT '',
            status varchar(40) NOT NULL DEFAULT 'pending_verification',
            verification_token varchar(80) NOT NULL,
            token_expires datetime NOT NULL,
            slug varchar(220) DEFAULT '',
            verified_at datetime DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY verification_token (verification_token),
            KEY status (status),
            KEY slug (slug),
            KEY created_at (created_at)
        ) {$charset};";

        dbDelta($sql_endorsements);
    }

    private static function table_static($name)
    {
        global $wpdb;
        return $wpdb->prefix . 's180re_' . $name;
    }

    private function table($name)
    {
        return self::table_static($name);
    }

    private static function seed_options()
    {
        add_option('s180re_recipient_email', self::normalize_email_domain(get_option('admin_email')));
        add_option('s180re_from_name', get_bloginfo('name'));
        add_option('s180re_from_email', '');
        add_option('s180re_daily_notice_hour', '09:00');
        add_option('s180en_form_intro', 'This is the official form to submit your review or critique about the discovery of Dr. Nathanael-Israel Israel (www.Israel120.com). If you have anything to share with the world about his work, please fill the form below. Thank you.');
        add_option('s180en_daily_notice_subject', '[{site_name}] Endorsements waiting for review');
        add_option('s180en_daily_notice_intro', 'These verified endorsements are waiting for approval or rejection.');
        add_option('s180en_approval_subject', 'Your Science180 endorsement was approved');
        add_option('s180en_approval_body', "Hello {full_name},\n\nThank you for taking the time to submit your endorsement to Science180. We are pleased to inform you that your endorsement has been reviewed, approved, and successfully published on our website.\n\n{view_endorsement_link}\n\nThank you for your valuable contribution and continued support of Science180.\n\nKind regards,\nScience180 Team\nwww.Science180.com\n\nFollow us: X, Linkedin, Facebook");
    }

    private static function normalize_email_domain($email)
    {
        $email = sanitize_email($email);
        if ($email === '') {
            return '';
        }

        $email = preg_replace('/@science\.net$/i', '@science180.net', $email);
        return is_email($email) ? $email : '';
    }

    private static function maybe_create_pages()
    {
        self::create_page_if_missing(
            'endorsement',
            'Endorsement',
            '[science180_endorsement_form]',
            's180re_endorsement_page_id'
        );

        self::create_page_if_missing(
            'published-endorsements',
            'Published Endorsements',
            '[science180_endorsements display="list" per_page="100"]',
            's180re_published_endorsements_page_id'
        );
    }

    private static function create_page_if_missing($slug, $title, $content, $option_name)
    {
        $existing = get_page_by_path($slug);
        if ($existing instanceof WP_Post) {
            update_option($option_name, $existing->ID);
            return;
        }

        $page_id = wp_insert_post(
            array(
                'post_title' => $title,
                'post_name' => $slug,
                'post_content' => $content,
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            )
        );

        if (!is_wp_error($page_id) && $page_id) {
            update_option($option_name, $page_id);
        }
    }

    private static function schedule_daily_notice()
    {
        if (wp_next_scheduled('s180re_daily_endorsement_notice')) {
            return;
        }

        wp_schedule_event(self::daily_notice_timestamp(), 'daily', 's180re_daily_endorsement_notice');
    }

    private static function reschedule_daily_notice()
    {
        wp_clear_scheduled_hook('s180re_daily_endorsement_notice');
        wp_schedule_event(self::daily_notice_timestamp(), 'daily', 's180re_daily_endorsement_notice');
    }

    private static function daily_notice_timestamp()
    {
        $time = get_option('s180re_daily_notice_hour', '09:00');
        if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', (string) $time)) {
            $time = '09:00';
        }

        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        $target = DateTimeImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $time, $timezone);
        if (!$target) {
            return time() + DAY_IN_SECONDS;
        }

        if ($target->getTimestamp() <= time()) {
            $target = $target->modify('+1 day');
        }

        return $target->getTimestamp();
    }

    public function register_rewrites()
    {
        self::register_rewrites_static();
    }

    private static function register_rewrites_static()
    {
        add_rewrite_rule('^endorsement/([0-9]{4})/([^/]+)/?$', 'index.php?s180re_endorsement_year=$matches[1]&s180re_endorsement_slug=$matches[2]', 'top');
        add_rewrite_rule('^endorsement/([0-9]+)/([^/]+)/?$', 'index.php?s180re_endorsement_id=$matches[1]', 'top');
    }

    public function register_query_vars($vars)
    {
        $vars[] = 's180re_endorsement_id';
        $vars[] = 's180re_endorsement_year';
        $vars[] = 's180re_endorsement_slug';
        return $vars;
    }

    public function enqueue_frontend_assets()
    {
        wp_enqueue_style('s180en-frontend', S180EN_PLUGIN_URL . 'assets/css/frontend.css', array(), S180EN_VERSION);
    }

    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 's180en') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('s180en-admin', S180EN_PLUGIN_URL . 'assets/css/admin.css', array(), S180EN_VERSION);
        wp_enqueue_script('s180en-admin', S180EN_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), S180EN_VERSION, true);
        wp_localize_script(
            's180en-admin',
            's180reAdmin',
            array(
                'choosePhoto' => __('Choose endorsement photo', 'science180-endorsement'),
                'usePhoto' => __('Use this photo', 'science180-endorsement'),
                'closePhoto' => __('Close photo preview', 'science180-endorsement'),
            )
        );
    }

    public function normalize_main_site_navigation_hrefs($block_content, $block)
    {
        if (is_admin() || !is_string($block_content) || strpos($block_content, 'https://science180.net/') === false) {
            return $block_content;
        }

        return strtr($block_content, self::main_site_href_replacements());
    }

    private static function main_site_href_replacements()
    {
        $paths = array(
            'features' => 'features',
            'about' => 'about',
            'mediakit' => 'mediakit',
            'shop' => 'shop',
            'publishing' => 'publishing',
            'services' => 'services',
            'review-copy-request' => 'books',
            'books' => 'books',
            'blog' => 'blog',
            'newsletter' => 'newsletter',
            'contact' => 'contact',
        );

        $replacements = array(
            'href="https://science180.net/"' => 'href="https://science180.com/"',
            "href='https://science180.net/'" => "href='https://science180.com/'",
        );

        foreach ($paths as $from => $to) {
            $replacements['href="https://science180.net/' . $from . '/"'] = 'href="https://science180.com/' . $to . '/"';
            $replacements["href='https://science180.net/" . $from . "/'"] = "href='https://science180.com/" . $to . "/'";
        }

        return $replacements;
    }

    public function register_admin_menu()
    {
        add_menu_page(
            __('Endorsement', 'science180-endorsement'),
            __('Endorsement', 'science180-endorsement'),
            'manage_options',
            's180en-endorsements',
            array($this, 'render_endorsements_admin_page'),
            'dashicons-testimonial',
            26
        );

        add_submenu_page('s180en-endorsements', __('Endorsements', 'science180-endorsement'), __('Endorsements', 'science180-endorsement'), 'manage_options', 's180en-endorsements', array($this, 'render_endorsements_admin_page'));
        add_submenu_page('s180en-endorsements', __('Settings', 'science180-endorsement'), __('Settings', 'science180-endorsement'), 'manage_options', 's180en-settings', array($this, 'render_settings_page'));
    }

    public function render_endorsement_form_shortcode()
    {
        ob_start();
        $this->render_public_notice('endorsement');
        $this->render_endorsement_code_form();
        ?>
        <section id="s180re-endorsement-form" class="s180re-shell s180re-endorsement-form-shell">
            <?php $this->render_endorsement_nav('submit'); ?>
            <div class="s180re-public-heading">
                <p class="s180re-eyebrow"><?php esc_html_e('Public endorsement', 'science180-endorsement'); ?></p>
                <h1><?php esc_html_e('Share an Endorsement', 'science180-endorsement'); ?></h1>
            </div>

            <form class="s180re-form s180re-form-compact" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="s180re_endorsement_submit">
                <input type="hidden" name="form_started" value="<?php echo esc_attr(time()); ?>">
                <input type="text" name="company_website" value="" class="s180re-hp" tabindex="-1" autocomplete="off">
                <?php wp_nonce_field('s180re_endorsement_submit', 's180re_nonce'); ?>

                <div class="s180re-field">
                    <label for="s180re-endorsement-email"><?php esc_html_e('Email', 'science180-endorsement'); ?> <span>*</span></label>
                    <input id="s180re-endorsement-email" type="email" name="email" required autocomplete="email">
                </div>
                <div class="s180re-field">
                    <label for="s180re-endorsement-first"><?php esc_html_e('First name', 'science180-endorsement'); ?> <span>*</span></label>
                    <input id="s180re-endorsement-first" type="text" name="first_name" required autocomplete="given-name">
                </div>
                <div class="s180re-field">
                    <label for="s180re-endorsement-last"><?php esc_html_e('Last name', 'science180-endorsement'); ?> <span>*</span></label>
                    <input id="s180re-endorsement-last" type="text" name="last_name" required autocomplete="family-name">
                </div>
                <div class="s180re-field">
                    <label for="s180re-endorsement-origin"><?php esc_html_e('Country of origin', 'science180-endorsement'); ?> <span>*</span></label>
                    <?php echo $this->country_select('country_origin', 's180re-endorsement-origin', '', true); ?>
                </div>
                <div class="s180re-field">
                    <label for="s180re-endorsement-residence"><?php esc_html_e('Country of residence', 'science180-endorsement'); ?> <span>*</span></label>
                    <?php echo $this->country_select('country_residence', 's180re-endorsement-residence', '', true); ?>
                </div>
                <div class="s180re-field">
                    <label for="s180re-endorsement-org"><?php esc_html_e('Organization', 'science180-endorsement'); ?> <span>*</span></label>
                    <input id="s180re-endorsement-org" type="text" name="organization" required placeholder="<?php esc_attr_e('If none, put your name', 'science180-endorsement'); ?>">
                </div>
                <div class="s180re-field s180re-field-full">
                    <label for="s180re-endorsement-comment"><?php esc_html_e('Endorsement description / comment', 'science180-endorsement'); ?> <span>*</span></label>
                    <textarea id="s180re-endorsement-comment" name="comment" rows="6" required></textarea>
                </div>
                <div class="s180re-field s180re-field-full">
                    <label for="s180re-endorsement-address"><?php esc_html_e('Address', 'science180-endorsement'); ?> <small><?php esc_html_e('(optional)', 'science180-endorsement'); ?></small></label>
                    <textarea id="s180re-endorsement-address" name="address" rows="3" autocomplete="street-address"></textarea>
                </div>
                <div class="s180re-field s180re-field-full">
                    <label for="s180re-endorsement-photo"><?php esc_html_e('Photo', 'science180-endorsement'); ?></label>
                    <input id="s180re-endorsement-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                </div>

                <button class="s180re-button" type="submit"><?php esc_html_e('Submit', 'science180-endorsement'); ?></button>
                <?php if (trim((string) get_option('s180en_form_intro')) !== '') : ?>
                    <p class="s180re-form-intro s180re-form-intro-after-submit"><?php echo esc_html(get_option('s180en_form_intro')); ?></p>
                <?php endif; ?>
            </form>
        </section>
        <?php
        return ob_get_clean();
    }

    private function render_endorsement_code_form()
    {
        if (!$this->should_show_endorsement_code_form()) {
            return;
        }
        ?>
        <section class="s180re-shell s180re-code-shell">
            <div class="s180re-public-heading">
                <p class="s180re-eyebrow"><?php esc_html_e('Email verification', 'science180-endorsement'); ?></p>
                <h2><?php esc_html_e('Verify Your Endorsement', 'science180-endorsement'); ?></h2>
            </div>
            <form class="s180re-form s180re-form-compact s180re-code-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="s180re_endorsement_verify_code">
                <?php wp_nonce_field('s180re_endorsement_verify_code', 's180re_nonce'); ?>

                <div class="s180re-field">
                    <label for="s180re-code-email"><?php esc_html_e('Email', 'science180-endorsement'); ?> <span>*</span></label>
                    <input id="s180re-code-email" type="email" name="email" required autocomplete="email">
                </div>
                <div class="s180re-field">
                    <label for="s180re-code-value"><?php esc_html_e('Verification code', 'science180-endorsement'); ?> <span>*</span></label>
                    <input id="s180re-code-value" type="text" name="verification_code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code">
                </div>

                <button class="s180re-button" type="submit"><?php esc_html_e('Verify Endorsement', 'science180-endorsement'); ?></button>
            </form>
        </section>
        <?php
    }

    private function should_show_endorsement_code_form()
    {
        return false;
    }

    public function render_endorsements_shortcode($atts)
    {
        global $wpdb;

        $atts = shortcode_atts(array('per_page' => 50, 'display' => 'cards'), $atts, 'science180_endorsements');
        $per_page = max(1, min(100, absint($atts['per_page'])));
        $display = $atts['display'] === 'list' ? 'list' : 'cards';
        $paged = isset($_GET['s180re_page']) ? max(1, absint($_GET['s180re_page'])) : 1;
        $offset = ($paged - 1) * $per_page;
        $table = $this->table('endorsements');
        $search = isset($_GET['s180re_search']) ? sanitize_text_field(wp_unslash($_GET['s180re_search'])) : '';
        $country_filter = isset($_GET['s180re_country']) ? sanitize_text_field(wp_unslash($_GET['s180re_country'])) : '';

        $where = array('status = %s');
        $params = array('approved');

        if ($display === 'list' && $search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(first_name LIKE %s OR last_name LIKE %s OR organization LIKE %s OR country_origin LIKE %s OR country_residence LIKE %s OR comment LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        if ($display === 'list' && $country_filter !== '') {
            $where[] = '(country_origin = %s OR country_residence = %s)';
            array_push($params, $country_filter, $country_filter);
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where);
        $query_params = array_merge($params, array($per_page, $offset));
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where_sql} ORDER BY reviewed_at DESC, created_at DESC LIMIT %d OFFSET %d",
                $query_params
            )
        );
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where_sql}", $params));
        $pages = (int) ceil($total / $per_page);
        $countries = array();

        if ($display === 'list') {
            $country_rows = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT country_name FROM (
                        SELECT country_origin AS country_name FROM {$table} WHERE status = %s AND country_origin != ''
                        UNION
                        SELECT country_residence AS country_name FROM {$table} WHERE status = %s AND country_residence != ''
                    ) countries ORDER BY country_name ASC",
                    'approved',
                    'approved'
                )
            );
            $countries = array_filter(array_map('trim', (array) $country_rows));
        }

        ob_start();
        ?>
        <section id="s180re-approved-endorsements" class="s180re-shell s180re-listing-shell">
            <?php $this->render_endorsement_nav('published'); ?>
            <div class="s180re-public-heading">
                <p class="s180re-eyebrow"><?php esc_html_e('Public proof', 'science180-endorsement'); ?></p>
                <h2><?php esc_html_e('Published Endorsements', 'science180-endorsement'); ?></h2>
            </div>

            <?php if ($display === 'list') : ?>
                <form class="s180re-directory-tools" method="get" action="<?php echo esc_url($this->published_endorsements_url()); ?>">
                    <label for="s180re-directory-search"><?php esc_html_e('Search', 'science180-endorsement'); ?></label>
                    <input id="s180re-directory-search" type="search" name="s180re_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search name, country, organization, comment', 'science180-endorsement'); ?>">
                    <label for="s180re-directory-country"><?php esc_html_e('Country', 'science180-endorsement'); ?></label>
                    <select id="s180re-directory-country" name="s180re_country">
                        <option value=""><?php esc_html_e('All countries', 'science180-endorsement'); ?></option>
                        <?php foreach ($countries as $country) : ?>
                            <option value="<?php echo esc_attr($country); ?>" <?php selected($country_filter, $country); ?>><?php echo esc_html($country); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="s180re-button s180re-button-small" type="submit"><?php esc_html_e('Filter', 'science180-endorsement'); ?></button>
                    <?php if ($search !== '' || $country_filter !== '') : ?>
                        <a class="s180re-text-link" href="<?php echo esc_url($this->published_endorsements_url()); ?>"><?php esc_html_e('Reset', 'science180-endorsement'); ?></a>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <?php if (empty($items)) : ?>
                <div class="s180re-message"><?php esc_html_e('No endorsements match your filters yet.', 'science180-endorsement'); ?></div>
            <?php else : ?>
                <?php if ($display === 'list') : ?>
                    <div class="s180re-directory-summary">
                        <?php echo esc_html(sprintf(_n('%d published endorsement', '%d published endorsements', $total, 'science180-endorsement'), $total)); ?>
                    </div>
                    <div class="s180re-directory-table-wrap">
                        <table class="s180re-directory-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Title', 'science180-endorsement'); ?></th>
                                    <th><?php esc_html_e('Name', 'science180-endorsement'); ?></th>
                                    <th><?php esc_html_e('Country', 'science180-endorsement'); ?></th>
                                    <th><?php esc_html_e('Organization', 'science180-endorsement'); ?></th>
                                    <th><?php esc_html_e('Date', 'science180-endorsement'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item) : ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url($this->endorsement_permalink($item)); ?>"><?php echo esc_html($this->endorsement_public_title($item)); ?></a></td>
                                        <td><?php echo esc_html($this->endorsement_person_name($item)); ?></td>
                                        <td><?php echo esc_html($item->country_origin); ?></td>
                                        <td><?php echo esc_html($item->organization); ?></td>
                                        <td><?php echo esc_html($item->reviewed_at ? date_i18n(get_option('date_format'), strtotime($item->reviewed_at)) : date_i18n(get_option('date_format'), strtotime($item->created_at))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="s180re-endorsement-grid">
                    <?php foreach ($items as $item) : ?>
                        <article class="s180re-endorsement-item">
                            <?php if (!empty($item->photo_url)) : ?>
                                <img class="s180re-endorsement-photo" src="<?php echo esc_url($item->photo_url); ?>" alt="<?php echo esc_attr($this->endorsement_person_name($item)); ?>">
                            <?php else : ?>
                                <span class="s180re-endorsement-avatar" aria-hidden="true"><?php echo esc_html($this->endorsement_initials($item)); ?></span>
                            <?php endif; ?>
                            <div class="s180re-endorsement-body">
                                <p class="s180re-card-kicker"><?php esc_html_e('Endorsement', 'science180-endorsement'); ?></p>
                                <h3><a href="<?php echo esc_url($this->endorsement_permalink($item)); ?>"><?php echo esc_html($this->endorsement_person_name($item)); ?></a></h3>
                                <p class="s180re-endorsement-meta"><?php echo esc_html($this->endorsement_card_meta($item)); ?></p>
                                <p class="s180re-endorsement-quote"><?php echo esc_html(wp_trim_words($item->comment, 34)); ?></p>
                                <a class="s180re-text-link" href="<?php echo esc_url($this->endorsement_permalink($item)); ?>"><?php esc_html_e('View full endorsement', 'science180-endorsement'); ?></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($pages > 1) : ?>
                    <nav class="s180re-pagination" aria-label="<?php esc_attr_e('Endorsements pagination', 'science180-endorsement'); ?>">
                        <?php for ($i = 1; $i <= $pages; $i++) : ?>
                            <?php
                            $page_url = add_query_arg(array_filter(array(
                                's180re_page' => $i,
                                's180re_search' => $search !== '' ? $search : null,
                                's180re_country' => $country_filter !== '' ? $country_filter : null,
                            )), $display === 'list' ? $this->published_endorsements_url() : null);
                            ?>
                            <a class="<?php echo $i === $paged ? 'is-current' : ''; ?>" href="<?php echo esc_url($page_url); ?>"><?php echo esc_html($i); ?></a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }
    public function handle_endorsement_submission()
    {
        if (!$this->public_form_is_valid('s180re_endorsement_submit')) {
            $this->redirect_back('endorsement_invalid');
        }

        $email = $this->clean_email_from_post('email');
        if (!$email) {
            $this->redirect_back('endorsement_invalid_email');
        }

        $first_name = $this->post_text('first_name', true);
        $last_name = $this->post_text('last_name', true);
        $organization = $this->post_text('organization', true);
        if ($organization === '') {
            $organization = trim($first_name . ' ' . $last_name);
        }

        $token = $this->generate_token();
        $token_hash = $this->pending_token_hash($token);
        $verification_code = $this->generate_verification_code();
        $now = current_time('mysql');
        $data = array(
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'country_origin' => $this->post_text('country_origin', true),
            'country_residence' => $this->post_text('country_residence', true),
            'organization' => $organization,
            'address' => $this->post_textarea('address', false),
            'comment' => $this->post_textarea('comment', true),
            'verification_code_hash' => $this->verification_code_hash($verification_code),
            'token_expires' => gmdate('Y-m-d H:i:s', time() + WEEK_IN_SECONDS),
            'created_at' => $now,
        );

        $tracking = $this->submission_tracking_data();
        $data = array_merge($data, $tracking);

        if ($this->has_empty_required($data, array('first_name', 'last_name', 'country_origin', 'country_residence', 'organization', 'comment'))) {
            $this->redirect_back('endorsement_missing');
        }

        $this->cleanup_expired_pending_endorsements();

        $photo = $this->handle_pending_photo_upload($token_hash);
        if (is_wp_error($photo)) {
            $this->redirect_back('endorsement_photo_error');
        }

        $data['photo'] = $photo;

        if (!$this->save_pending_endorsement($token_hash, $data)) {
            $this->delete_pending_photo($photo);
            $this->redirect_back('endorsement_error');
        }

        if (!$this->send_endorsement_verification_email($email, $first_name, $token)) {
            $this->delete_pending_endorsement(array(
                'path' => $this->pending_endorsement_path($token_hash),
                'data' => $data,
            ));
            $this->redirect_back('endorsement_email_failed');
        }

        $this->redirect_back('endorsement_check_email');
    }

    private function public_form_is_valid($nonce_action)
    {
        if (!isset($_POST['s180re_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['s180re_nonce'])), $nonce_action)) {
            return false;
        }

        if (!empty($_POST['company_website'])) {
            return false;
        }

        $started = isset($_POST['form_started']) ? absint($_POST['form_started']) : 0;
        if ($started > 0 && (time() - $started) < 2) {
            return false;
        }

        return true;
    }

    private function handle_pending_photo_upload($token_hash)
    {
        if (empty($_FILES['photo']) || empty($_FILES['photo']['name'])) {
            return array();
        }

        $file = $_FILES['photo'];
        if (!empty($file['size']) && (int) $file['size'] > 5 * MB_IN_BYTES) {
            return new WP_Error('s180re_file_large', __('Photo is too large.', 'science180-endorsement'));
        }

        $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $allowed_types = array('image/jpeg', 'image/png', 'image/webp');
        if (empty($checked['type']) || !in_array($checked['type'], $allowed_types, true)) {
            return new WP_Error('s180re_file_type', __('Photo type is not supported.', 'science180-endorsement'));
        }

        $pending_dir = $this->pending_endorsement_dir();
        if (!$pending_dir) {
            return new WP_Error('s180re_pending_dir', __('The pending upload folder is not writable.', 'science180-endorsement'));
        }

        $original_name = sanitize_file_name(wp_basename($file['name']));
        if ($original_name === '') {
            $original_name = 'endorsement-photo';
        }

        $filename = wp_unique_filename($pending_dir, $token_hash . '-' . $original_name);
        $target = trailingslashit($pending_dir) . $filename;

        if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $target)) {
            return new WP_Error('s180re_photo_move_failed', __('The photo could not be stored for verification.', 'science180-endorsement'));
        }

        @chmod($target, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);

        return array(
            'path' => $target,
            'name' => $original_name,
            'type' => $checked['type'],
        );
    }

    private function pending_token_hash($token)
    {
        return hash_hmac('sha256', (string) $token, wp_salt('auth'));
    }

    private function pending_endorsement_dir()
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error']) || empty($upload_dir['basedir'])) {
            return '';
        }

        $dir = trailingslashit($upload_dir['basedir']) . 's180re-pending-endorsements';
        if (!wp_mkdir_p($dir)) {
            return '';
        }

        $index_file = trailingslashit($dir) . 'index.html';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '');
        }

        $htaccess_file = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Deny from all\n");
        }

        return $dir;
    }

    private function pending_endorsement_path($token_hash)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $token_hash)) {
            return '';
        }

        $dir = $this->pending_endorsement_dir();
        if (!$dir) {
            return '';
        }

        return trailingslashit($dir) . $token_hash . '.json';
    }

    private function save_pending_endorsement($token_hash, $data)
    {
        $path = $this->pending_endorsement_path($token_hash);
        if (!$path) {
            return false;
        }

        $payload = array(
            'version' => 1,
            'token_hash' => $token_hash,
            'data' => $data,
        );

        $saved = file_put_contents($path, wp_json_encode($payload), LOCK_EX);
        if ($saved === false) {
            return false;
        }

        @chmod($path, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);
        return true;
    }

    private function load_pending_endorsement($token)
    {
        $token_hash = $this->pending_token_hash($token);
        $path = $this->pending_endorsement_path($token_hash);
        if (!$path || !file_exists($path)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload) || empty($payload['token_hash']) || empty($payload['data']) || !hash_equals((string) $payload['token_hash'], $token_hash)) {
            return null;
        }

        return array(
            'hash' => $token_hash,
            'path' => $path,
            'data' => $payload['data'],
        );
    }

    private function load_pending_endorsement_by_code($email, $code)
    {
        $email = strtolower(sanitize_email($email));
        $code_hash = $this->verification_code_hash($code);
        if (!$email || !$code_hash) {
            return null;
        }

        $dir = $this->pending_endorsement_dir();
        if (!$dir) {
            return null;
        }

        $files = glob(trailingslashit($dir) . '*.json');
        if (!is_array($files)) {
            return null;
        }

        foreach ($files as $path) {
            $payload = json_decode((string) file_get_contents($path), true);
            if (!is_array($payload) || empty($payload['token_hash']) || empty($payload['data']) || !is_array($payload['data'])) {
                continue;
            }

            $data = $payload['data'];
            $pending_email = isset($data['email']) ? strtolower(sanitize_email($data['email'])) : '';
            $pending_code_hash = isset($data['verification_code_hash']) ? (string) $data['verification_code_hash'] : '';
            if ($pending_email !== $email || !$pending_code_hash || !hash_equals($pending_code_hash, $code_hash)) {
                continue;
            }

            return array(
                'hash' => (string) $payload['token_hash'],
                'path' => $path,
                'data' => $data,
            );
        }

        return null;
    }

    private function cleanup_expired_pending_endorsements()
    {
        $dir = $this->pending_endorsement_dir();
        if (!$dir) {
            return;
        }

        $files = glob(trailingslashit($dir) . '*.json');
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $path) {
            $payload = json_decode((string) file_get_contents($path), true);
            $expires = isset($payload['data']['token_expires']) ? strtotime($payload['data']['token_expires'] . ' UTC') : 0;
            if ($expires && $expires >= time()) {
                continue;
            }

            $photo = isset($payload['data']['photo']) && is_array($payload['data']['photo']) ? $payload['data']['photo'] : array();
            $this->delete_pending_photo($photo);
            @unlink($path);
        }
    }

    private function delete_pending_endorsement($pending)
    {
        if (!empty($pending['data']['photo']) && is_array($pending['data']['photo'])) {
            $this->delete_pending_photo($pending['data']['photo']);
        }

        if (!empty($pending['path'])) {
            @unlink($pending['path']);
        }
    }

    private function delete_pending_photo($photo)
    {
        if (empty($photo['path'])) {
            return;
        }

        $pending_dir = $this->pending_endorsement_dir();
        $real_dir = $pending_dir ? realpath($pending_dir) : false;
        $real_path = realpath($photo['path']);
        if (!$real_dir || !$real_path || strpos(wp_normalize_path($real_path), trailingslashit(wp_normalize_path($real_dir))) !== 0) {
            return;
        }

        @unlink($real_path);
    }

    private function create_attachment_from_pending_photo($photo)
    {
        if (empty($photo['path'])) {
            return array('id' => 0, 'url' => '');
        }

        $pending_dir = $this->pending_endorsement_dir();
        $real_dir = $pending_dir ? realpath($pending_dir) : false;
        $real_path = realpath($photo['path']);
        if (!$real_dir || !$real_path || strpos(wp_normalize_path($real_path), trailingslashit(wp_normalize_path($real_dir))) !== 0 || !is_readable($real_path)) {
            return new WP_Error('s180re_pending_photo_missing', __('The pending photo could not be found.', 'science180-endorsement'));
        }

        $contents = file_get_contents($real_path);
        if ($contents === false) {
            return new WP_Error('s180re_pending_photo_read', __('The pending photo could not be read.', 'science180-endorsement'));
        }

        $filename = !empty($photo['name']) ? sanitize_file_name($photo['name']) : wp_basename($real_path);
        if ($filename === '') {
            $filename = 'endorsement-photo';
        }

        $upload = wp_upload_bits($filename, null, $contents);
        if (!empty($upload['error'])) {
            return new WP_Error('s180re_photo_publish', $upload['error']);
        }

        $filetype = wp_check_filetype($upload['file']);
        $attachment_id = wp_insert_attachment(
            array(
                'post_mime_type' => $filetype['type'] ? $filetype['type'] : (isset($photo['type']) ? $photo['type'] : ''),
                'post_title' => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit',
            ),
            $upload['file']
        );

        if (is_wp_error($attachment_id)) {
            @unlink($upload['file']);
            return $attachment_id;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        if (!is_wp_error($metadata) && !empty($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        return array(
            'id' => (int) $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
        );
    }

    public function handle_verification_and_detail_routes()
    {
        if (isset($_GET['s180re_preview_endorsement'])) {
            $this->handle_admin_public_preview();
            return;
        }

        if (isset($_GET['s180re_verify_endorsement'])) {
            $this->handle_endorsement_verification();
            return;
        }

        $endorsement_id = absint(get_query_var('s180re_endorsement_id'));
        if ($endorsement_id > 0) {
            $this->render_endorsement_detail_page($endorsement_id);
        }

        $endorsement_year = absint(get_query_var('s180re_endorsement_year'));
        $endorsement_slug = sanitize_title(get_query_var('s180re_endorsement_slug'));
        if ($endorsement_year > 0 && $endorsement_slug !== '') {
            $this->render_endorsement_detail_page_by_year_slug($endorsement_year, $endorsement_slug);
        }
    }

    public function render_shortcode_page_fallback()
    {
        if (is_admin() || !is_page()) {
            return;
        }

        $endorsement_page_id = (int) get_option('s180re_endorsement_page_id');
        $is_endorsement_page = ($endorsement_page_id > 0 && is_page($endorsement_page_id)) || is_page('endorsement');
        $published_page_id = (int) get_option('s180re_published_endorsements_page_id');
        $is_published_page = ($published_page_id > 0 && is_page($published_page_id)) || is_page('published-endorsements');

        if (!$is_endorsement_page && !$is_published_page) {
            return;
        }

        $shortcode = $is_published_page
            ? '[science180_endorsements display="list" per_page="100"]'
            : '[science180_endorsement_form]';

        status_header(200);

        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            $this->render_block_theme_shortcode_page($shortcode);
        } else {
            get_header();
            $this->render_shortcode_page_main($shortcode);
            get_footer();
        }

        exit;
    }

    private function render_shortcode_page_main($shortcode)
    {
        echo '<main id="primary" class="site-main s180re-template-main">';
        echo do_shortcode($shortcode);
        echo '</main>';
    }

    private function render_block_theme_shortcode_page($shortcode)
    {
        $this->render_block_theme_page(function () use ($shortcode) {
            $this->render_shortcode_page_main($shortcode);
        });
    }

    private function render_block_theme_page($render_main)
    {
        ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(wp_get_document_title()); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('s180re-plugin-page'); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
    <?php
    if (function_exists('block_template_part')) {
        block_template_part('header');
    }

    call_user_func($render_main);

    if (function_exists('block_template_part')) {
        block_template_part('footer');
    }
    ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
        <?php
    }

    private function handle_endorsement_verification()
    {
        global $wpdb;

        $token = sanitize_text_field(wp_unslash($_GET['s180re_verify_endorsement']));
        $target = $this->endorsement_page_url();
        $pending = $this->load_pending_endorsement($token);
        if (!$pending) {
            wp_safe_redirect(add_query_arg('s180re_status', 'endorsement_verify_invalid', $target));
            exit;
        }

        $data = $pending['data'];
        if (empty($data['token_expires']) || strtotime($data['token_expires'] . ' UTC') < time()) {
            $this->delete_pending_endorsement($pending);
            wp_safe_redirect(add_query_arg('s180re_status', 'endorsement_verify_expired', $target));
            exit;
        }

        $result = $this->save_verified_endorsement($pending);
        if (is_wp_error($result) && $result->get_error_code() === 's180re_photo_error') {
            wp_safe_redirect(add_query_arg('s180re_status', 'endorsement_photo_error', $target));
            exit;
        }

        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('s180re_status', 'endorsement_error', $target));
            exit;
        }

        wp_safe_redirect(add_query_arg('s180re_status', 'endorsement_verified', $target));
        exit;
    }

    public function handle_endorsement_code_verification()
    {
        if (!$this->public_form_is_valid('s180re_endorsement_verify_code')) {
            $this->redirect_back('endorsement_code_invalid');
        }

        $target = $this->endorsement_page_url();
        $email = $this->clean_email_from_post('email');
        $code = isset($_POST['verification_code']) ? preg_replace('/\D+/', '', sanitize_text_field(wp_unslash($_POST['verification_code']))) : '';
        if (!$email || strlen($code) !== 6) {
            wp_safe_redirect(add_query_arg('s180re_status', 'endorsement_code_invalid', $target));
            exit;
        }

        $this->cleanup_expired_pending_endorsements();
        $pending = $this->load_pending_endorsement_by_code($email, $code);
        if (!$pending) {
            wp_safe_redirect(add_query_arg('s180re_status', 'endorsement_code_invalid', $target));
            exit;
        }

        $data = $pending['data'];
        if (empty($data['token_expires']) || strtotime($data['token_expires'] . ' UTC') < time()) {
            $this->delete_pending_endorsement($pending);
            wp_safe_redirect(add_query_arg('s180re_status', 'endorsement_verify_expired', $target));
            exit;
        }

        $result = $this->save_verified_endorsement($pending);
        if (is_wp_error($result) && $result->get_error_code() === 's180re_photo_error') {
            wp_safe_redirect(add_query_arg('s180re_status', 'endorsement_photo_error', $target));
            exit;
        }

        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('s180re_status', 'endorsement_error', $target));
            exit;
        }

        wp_safe_redirect(add_query_arg('s180re_status', 'endorsement_verified', $target));
        exit;
    }

    private function save_verified_endorsement($pending)
    {
        global $wpdb;

        $data = $pending['data'];
        $photo = $this->create_attachment_from_pending_photo(isset($data['photo']) && is_array($data['photo']) ? $data['photo'] : array());
        if (is_wp_error($photo)) {
            return new WP_Error('s180re_photo_error', $photo->get_error_message());
        }

        $now = current_time('mysql');
        $table = $this->table('endorsements');
        $inserted = $wpdb->insert(
            $table,
            array(
                'email' => sanitize_email($data['email']),
                'first_name' => sanitize_text_field($data['first_name']),
                'last_name' => sanitize_text_field($data['last_name']),
                'country_origin' => sanitize_text_field($data['country_origin']),
                'country_residence' => sanitize_text_field($data['country_residence']),
                'organization' => sanitize_text_field($data['organization']),
                'address' => sanitize_textarea_field($data['address'] ?? ''),
                'comment' => sanitize_textarea_field($data['comment']),
                'photo_id' => isset($photo['id']) ? (int) $photo['id'] : 0,
                'photo_url' => isset($photo['url']) ? esc_url_raw($photo['url']) : '',
                'ip_address' => sanitize_text_field($data['ip_address'] ?? ''),
                'ip_city' => sanitize_text_field($data['ip_city'] ?? ''),
                'ip_country' => sanitize_text_field($data['ip_country'] ?? ''),
                'user_agent' => sanitize_text_field($data['user_agent'] ?? ''),
                'device_type' => sanitize_text_field($data['device_type'] ?? ''),
                'status' => 'verified',
                'verification_token' => $pending['hash'],
                'token_expires' => $data['token_expires'],
                'slug' => '',
                'verified_at' => $now,
                'reviewed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$inserted) {
            return new WP_Error('s180re_save_error', 'Endorsement could not be saved.');
        }

        $this->delete_pending_endorsement($pending);

        return true;
    }

    public function detail_title_parts($parts)
    {
        $endorsement_id = absint(get_query_var('s180re_endorsement_id'));
        $endorsement_year = absint(get_query_var('s180re_endorsement_year'));
        $endorsement_slug = sanitize_title(get_query_var('s180re_endorsement_slug'));
        if ($endorsement_id <= 0 && ($endorsement_year <= 0 || $endorsement_slug === '')) {
            return $parts;
        }

        $endorsement = $endorsement_id > 0 ? $this->get_endorsement($endorsement_id) : $this->get_endorsement_by_year_slug($endorsement_year, $endorsement_slug);
        if ($endorsement && $endorsement->status === 'approved') {
            $parts['title'] = $this->endorsement_public_title($endorsement);
        }

        return $parts;
    }

    private function render_endorsement_detail_page_by_year_slug($year, $slug)
    {
        $endorsement = $this->get_endorsement_by_year_slug($year, $slug);
        $this->render_endorsement_detail($endorsement);
    }

    private function render_endorsement_detail_page($endorsement_id)
    {
        $endorsement = $this->get_endorsement($endorsement_id);
        $this->render_endorsement_detail($endorsement);
    }

    private function handle_admin_public_preview()
    {
        $endorsement_id = absint($_GET['s180re_preview_endorsement']);
        if (!$endorsement_id || !current_user_can('manage_options') || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 's180re_preview_endorsement_' . $endorsement_id)) {
            wp_die(esc_html__('You do not have permission to preview this endorsement.', 'science180-endorsement'));
        }

        $endorsement = $this->get_endorsement($endorsement_id);
        $this->render_endorsement_detail($endorsement, true);
    }

    private function render_endorsement_detail($endorsement, $allow_preview = false)
    {
        if (!$endorsement || (!$allow_preview && $endorsement->status !== 'approved')) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            $render_not_found = function () {
                echo '<main class="s180re-shell"><div class="s180re-message s180re-message-warning">' . esc_html__('Endorsement not found.', 'science180-endorsement') . '</div></main>';
            };

            if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
                $this->render_block_theme_page($render_not_found);
            } else {
                get_header();
                call_user_func($render_not_found);
                get_footer();
            }
            exit;
        }

        status_header(200);
        $render_detail = function () use ($endorsement, $allow_preview) {
            $this->render_endorsement_detail_main($endorsement, $allow_preview);
        };

        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            $this->render_block_theme_page($render_detail);
        } else {
            get_header();
            call_user_func($render_detail);
            get_footer();
        }
        exit;
    }

    private function render_endorsement_detail_main($endorsement, $is_preview = false)
    {
        ?>
        <main class="s180re-shell s180re-detail-shell">
            <?php $this->render_endorsement_nav('published'); ?>
            <?php if ($is_preview) : ?>
                <div class="s180re-message s180re-message-warning"><?php esc_html_e('Admin preview: this endorsement is not necessarily published yet.', 'science180-endorsement'); ?></div>
            <?php endif; ?>
            <article class="s180re-detail">
                <div class="s180re-public-heading">
                    <p class="s180re-eyebrow"><?php esc_html_e('Endorsement', 'science180-endorsement'); ?></p>
                    <h1><?php echo esc_html($this->endorsement_person_name($endorsement)); ?></h1>
                    <p class="s180re-detail-meta"><?php echo esc_html($this->endorsement_card_meta($endorsement)); ?></p>
                </div>
                <div class="s180re-detail-grid">
                    <?php if (!empty($endorsement->photo_url)) : ?>
                        <img class="s180re-detail-photo" src="<?php echo esc_url($endorsement->photo_url); ?>" alt="<?php echo esc_attr($this->endorsement_person_name($endorsement)); ?>">
                    <?php else : ?>
                        <span class="s180re-detail-avatar" aria-hidden="true"><?php echo esc_html($this->endorsement_initials($endorsement)); ?></span>
                    <?php endif; ?>
                    <div class="s180re-detail-copy">
                        <?php if (!empty($endorsement->address)) : ?>
                            <p class="s180re-detail-address"><strong><?php esc_html_e('Address:', 'science180-endorsement'); ?></strong><br><?php echo nl2br(esc_html($endorsement->address)); ?></p>
                        <?php endif; ?>
                        <p><?php echo nl2br(esc_html($endorsement->comment)); ?></p>
                        <a class="s180re-text-link" href="<?php echo esc_url($this->published_endorsements_url()); ?>"><?php esc_html_e('Back to published endorsements', 'science180-endorsement'); ?></a>
                    </div>
                </div>
            </article>
        </main>
        <?php
    }

    public function send_daily_endorsement_notice()
    {
        global $wpdb;

        $this->cleanup_expired_pending_endorsements();

        $today = current_time('Y-m-d');
        if (get_option('s180re_last_notice_date') === $today) {
            return;
        }

        $table = $this->table('endorsements');
        $pending = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY verified_at ASC, created_at ASC", 'verified'));
        if (empty($pending)) {
            update_option('s180re_last_notice_date', $today);
            return;
        }

        $admin_url = admin_url('admin.php?page=s180en-endorsements&status=verified');
        $rows = '';
        foreach ($pending as $item) {
            $rows .= '<tr>';
            $rows .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . esc_html($item->created_at) . '</td>';
            $rows .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . esc_html($this->endorsement_person_name($item)) . '</td>';
            $rows .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . esc_html($item->country_origin) . '</td>';
            $rows .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . esc_html($item->email) . '</td>';
            $rows .= '</tr>';
        }

        $message = '<p>' . esc_html(get_option('s180en_daily_notice_intro', 'These verified endorsements are waiting for approval or rejection.')) . '</p>';
        $message .= '<table style="border-collapse:collapse;width:100%;">';
        $message .= '<thead><tr><th align="left" style="padding:8px;border-bottom:2px solid #222;">Date</th><th align="left" style="padding:8px;border-bottom:2px solid #222;">Name</th><th align="left" style="padding:8px;border-bottom:2px solid #222;">Country</th><th align="left" style="padding:8px;border-bottom:2px solid #222;">Email</th></tr></thead>';
        $message .= '<tbody>' . $rows . '</tbody></table>';
        $message .= '<p><a href="' . esc_url($admin_url) . '">' . esc_html__('Review endorsements', 'science180-endorsement') . '</a></p>';

        wp_mail(
            $this->recipient_email(),
            $this->format_template(get_option('s180en_daily_notice_subject', '[{site_name}] Endorsements waiting for review'), array('site_name' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES))),
            $message,
            $this->mail_headers()
        );

        update_option('s180re_last_notice_date', $today);
    }

    private function send_endorsement_verification_email($email, $first_name, $token)
    {
        $verification_url = add_query_arg('s180re_verify_endorsement', rawurlencode($token), $this->endorsement_page_url());
        $message = '<p>' . sprintf(esc_html__('Hello %s,', 'science180-endorsement'), esc_html($first_name)) . '</p>';
        $message .= '<p>' . esc_html__('Please verify your email address before your endorsement is sent for review.', 'science180-endorsement') . '</p>';
        $message .= '<p><a href="' . esc_url($verification_url) . '" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:6px;font-weight:700;">' . esc_html__('Verify My Email', 'science180-endorsement') . '</a></p>';
        $message .= '<p>' . esc_html__('If the button does not work, copy and paste this link into your browser:', 'science180-endorsement') . '<br><a href="' . esc_url($verification_url) . '">' . esc_html($verification_url) . '</a></p>';
        $message .= '<p>' . esc_html__('If you did not submit this endorsement, you can ignore this message.', 'science180-endorsement') . '</p>';

        $sent = wp_mail($email, 'Verify your Science180 endorsement', $message, $this->mail_headers());
        if (!$sent) {
            $this->log_mail_failure('endorsement verification', $email);
            $plain_message = sprintf(
                "Hello %s,\n\nPlease verify your email address before your endorsement is sent for review.\n\nVerify your email here:\n%s\n\nIf you did not submit this endorsement, you can ignore this message.",
                wp_strip_all_tags((string) $first_name),
                esc_url_raw($verification_url)
            );
            $sent = wp_mail(
                $email,
                'Verify your Science180 endorsement',
                $plain_message,
                array('Content-Type: text/plain; charset=UTF-8')
            );

            if (!$sent) {
                $this->log_mail_failure('endorsement verification fallback', $email);
            }
        }

        return $sent;
    }

    private function log_mail_failure($context, $email)
    {
        global $phpmailer;

        $message = sprintf(
            '[Science180 Review Endorsements] Failed to send %s email to %s.',
            wp_strip_all_tags((string) $context),
            sanitize_email($email)
        );

        if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
            $message .= ' PHPMailer: ' . wp_strip_all_tags($phpmailer->ErrorInfo);
        }

        error_log($message);
    }

    private function send_moderation_result_email($endorsement, $status)
    {
        $approved = $status === 'approved';
        $subject = $approved ? get_option('s180en_approval_subject', 'Your Science180 endorsement was approved') : 'Your Science180 endorsement was reviewed';

        if ($approved) {
            $message = $this->format_template(get_option('s180en_approval_body'), array(
                'first_name' => $endorsement->first_name,
                'full_name' => $this->endorsement_person_name($endorsement),
                'site_name' => get_bloginfo('name'),
                'endorsement_url' => $this->endorsement_permalink($endorsement),
                'view_endorsement_link' => '<a href="' . esc_url($this->endorsement_permalink($endorsement)) . '" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:6px;font-weight:700;">' . esc_html__('View Your Endorsement', 'science180-endorsement') . '</a>',
            ));
            $message = wpautop($message);
        } else {
            $message = '<p>' . sprintf(esc_html__('Hello %s,', 'science180-endorsement'), esc_html($endorsement->first_name)) . '</p>';
            $message .= '<p>' . esc_html__('Thank you for taking the time to submit an endorsement. After review, it was not approved for publication.', 'science180-endorsement') . '</p>';
        }

        $sent = wp_mail($endorsement->email, $subject, $message, $this->mail_headers());
        if (!$sent) {
            $this->log_mail_failure('endorsement moderation result', $endorsement->email);
        }

        return $sent;
    }

    private function mail_headers($reply_to_email = '', $reply_to_name = '')
    {
        $headers = array('Content-Type: text/html; charset=UTF-8', 'MIME-Version: 1.0');
        $from_email = $this->sender_email();
        $from_name = $this->sender_name();

        if ($from_email) {
            $headers[] = 'From: ' . $this->format_mailbox($from_email, $from_name);
        }

        if ($reply_to_email && is_email($reply_to_email)) {
            $headers[] = 'Reply-To: ' . $this->format_mailbox($reply_to_email, $reply_to_name);
        }

        return $headers;
    }

    private function sender_email()
    {
        $candidates = array(
            get_option('s180re_from_email'),
            get_option('advnews_smtp_from_email'),
            get_option('advnews_from_email'),
            get_option('admin_email'),
        );

        foreach ($candidates as $candidate) {
            $candidate = self::normalize_email_domain($candidate);
            if ($candidate && is_email($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function sender_name()
    {
        $candidates = array(
            get_option('s180re_from_name'),
            get_option('advnews_smtp_from_name'),
            get_option('advnews_from_name'),
            get_bloginfo('name'),
        );

        foreach ($candidates as $candidate) {
            $candidate = trim(wp_strip_all_tags((string) $candidate));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return 'Science180';
    }

    private function recipient_email()
    {
        $email = self::normalize_email_domain(get_option('s180re_recipient_email'));
        if (!$email || !is_email($email)) {
            $email = self::normalize_email_domain(get_option('admin_email'));
        }

        return $email;
    }

    private function format_template($template, $values)
    {
        $replacements = array();
        foreach ($values as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return strtr((string) $template, $replacements);
    }

    private function format_mailbox($email, $name)
    {
        $email = sanitize_email($email);
        $name = trim(str_replace(array('"', "\r", "\n"), '', wp_strip_all_tags((string) $name)));
        if ($name === '') {
            return $email;
        }

        return $name . ' <' . $email . '>';
    }

    public function render_endorsements_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'science180-endorsement'));
        }

        global $wpdb;
        $view_id = isset($_GET['view']) ? absint($_GET['view']) : 0;
        $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        if ($edit_id) {
            $this->render_endorsement_admin_edit($edit_id);
            return;
        }

        if ($view_id) {
            $this->render_endorsement_admin_detail($view_id);
            return;
        }

        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $allowed = array('pending_verification', 'verified', 'approved', 'rejected');
        if (!in_array($status, $allowed, true)) {
            $status = '';
        }

        $search = isset($_GET['s180re_search']) ? sanitize_text_field(wp_unslash($_GET['s180re_search'])) : '';
        $table = $this->table('endorsements');

        $where = array();
        $params = array();
        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR country_origin LIKE %s OR country_residence LIKE %s OR organization LIKE %s OR address LIKE %s OR comment LIKE %s)';
            $params = array_merge($params, array($like, $like, $like, $like, $like, $like, $like, $like));
        }

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 300';
        $items = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);

        $status_options = $this->endorsement_status_filter_options();
        ?>
        <div class="wrap s180re-admin">
            <h1><?php esc_html_e('Endorsements', 'science180-endorsement'); ?></h1>
            <?php $this->render_admin_notice(); ?>
            <p class="subsubsub s180re-status-links">
                <?php
                $view_links = array(
                    '' => __('All', 'science180-endorsement'),
                    'verified' => __('Needs review', 'science180-endorsement'),
                    'approved' => __('Approved', 'science180-endorsement'),
                    'rejected' => __('Rejected', 'science180-endorsement'),
                );
                $separator = '';
                foreach ($view_links as $view_status => $label) {
                    $args = array('page' => 's180en-endorsements');
                    if ($view_status !== '') {
                        $args['status'] = $view_status;
                    }
                    if ($search !== '') {
                        $args['s180re_search'] = $search;
                    }

                    echo wp_kses_post($separator);
                    echo '<a class="' . esc_attr($status === $view_status ? 'current' : '') . '" href="' . esc_url(add_query_arg($args, admin_url('admin.php'))) . '">' . esc_html($label) . '</a>';
                    $separator = ' | ';
                }
                ?>
            </p>
            <br class="clear">

            <form class="s180re-filter-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="s180en-endorsements">
                <label class="screen-reader-text" for="s180re-endorsement-status-filter"><?php esc_html_e('Filter by status', 'science180-endorsement'); ?></label>
                <select id="s180re-endorsement-status-filter" name="status">
                    <?php foreach ($status_options as $option_status => $label) : ?>
                        <option value="<?php echo esc_attr($option_status); ?>" <?php selected($status, $option_status); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="screen-reader-text" for="s180re-endorsement-search"><?php esc_html_e('Search endorsements', 'science180-endorsement'); ?></label>
                <input id="s180re-endorsement-search" type="search" name="s180re_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search name, email, country, organization, comment', 'science180-endorsement'); ?>">
                <button class="button" type="submit"><?php esc_html_e('Filter', 'science180-endorsement'); ?></button>
                <?php if ($status !== '' || $search !== '') : ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=s180en-endorsements')); ?>"><?php esc_html_e('Reset', 'science180-endorsement'); ?></a>
                <?php endif; ?>
            </form>

            <form class="s180re-bulk-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="s180re_bulk_endorsements">
                <?php wp_nonce_field('s180re_bulk_endorsements'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label class="screen-reader-text" for="s180re-bulk-action"><?php esc_html_e('Select bulk action', 'science180-endorsement'); ?></label>
                        <select id="s180re-bulk-action" name="bulk_action">
                            <option value=""><?php esc_html_e('Bulk actions', 'science180-endorsement'); ?></option>
                            <option value="approved"><?php esc_html_e('Approve', 'science180-endorsement'); ?></option>
                            <option value="rejected"><?php esc_html_e('Reject', 'science180-endorsement'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'science180-endorsement'); ?></option>
                        </select>
                        <button class="button action" type="submit"><?php esc_html_e('Apply', 'science180-endorsement'); ?></button>
                    </div>
                    <br class="clear">
                </div>

                <table class="wp-list-table widefat fixed striped table-view-list s180re-admin-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column"><input id="s180re-select-all" type="checkbox" aria-label="<?php esc_attr_e('Select all endorsements', 'science180-endorsement'); ?>"></td>
                            <th scope="col"><?php esc_html_e('Date', 'science180-endorsement'); ?></th>
                            <th scope="col"><?php esc_html_e('Title', 'science180-endorsement'); ?></th>
                            <th scope="col"><?php esc_html_e('Email', 'science180-endorsement'); ?></th>
                            <th scope="col"><?php esc_html_e('Status', 'science180-endorsement'); ?></th>
                            <th scope="col"><?php esc_html_e('Actions', 'science180-endorsement'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)) : ?>
                            <tr><td colspan="6"><?php esc_html_e('No endorsements found.', 'science180-endorsement'); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <th scope="row" class="check-column"><input class="s180re-bulk-check" type="checkbox" name="s180re_endorsement_ids[]" value="<?php echo esc_attr((int) $item->id); ?>" aria-label="<?php echo esc_attr(sprintf(__('Select %s', 'science180-endorsement'), $this->endorsement_public_title($item))); ?>"></th>
                                <td><?php echo esc_html($item->created_at); ?></td>
                                <td><a href="<?php echo esc_url(admin_url('admin.php?page=s180en-endorsements&view=' . (int) $item->id)); ?>"><?php echo esc_html($this->endorsement_public_title($item)); ?></a></td>
                                <td><a href="mailto:<?php echo esc_attr($item->email); ?>"><?php echo esc_html($item->email); ?></a></td>
                                <td><span class="s180re-status-badge s180re-status-<?php echo esc_attr(sanitize_html_class($item->status)); ?>"><?php echo esc_html($this->endorsement_status_label($item->status)); ?></span></td>
                                <td class="s180re-row-actions-cell"><?php $this->render_endorsement_list_actions($item); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }

    private function render_endorsement_admin_detail($endorsement_id)
    {
        $item = $this->get_endorsement($endorsement_id);
        if (!$item) {
            wp_die(esc_html__('Endorsement not found.', 'science180-endorsement'));
        }
        ?>
        <div class="wrap s180re-admin">
            <h1><?php echo esc_html($this->endorsement_public_title($item)); ?></h1>
            <p>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=s180en-endorsements')); ?>"><?php esc_html_e('Back to endorsements', 'science180-endorsement'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=s180en-endorsements&edit=' . (int) $item->id)); ?>"><?php esc_html_e('Edit', 'science180-endorsement'); ?></a>
                <a class="button" href="<?php echo esc_url($this->endorsement_preview_url($item)); ?>" target="_blank" rel="noopener"><?php echo $item->status === 'approved' ? esc_html__('View public page', 'science180-endorsement') : esc_html__('Preview public page', 'science180-endorsement'); ?></a>
            </p>
            <div class="s180re-admin-layout">
                <div class="s180re-admin-panel">
                    <?php if (!empty($item->photo_url)) : ?>
                        <img class="s180re-admin-photo" src="<?php echo esc_url($item->photo_url); ?>" alt="">
                    <?php endif; ?>
                    <p><strong><?php esc_html_e('Date:', 'science180-endorsement'); ?></strong> <?php echo esc_html($item->created_at); ?></p>
                    <p><strong><?php esc_html_e('Status:', 'science180-endorsement'); ?></strong> <?php echo esc_html($this->endorsement_status_label($item->status)); ?></p>
                    <p><strong><?php esc_html_e('Email:', 'science180-endorsement'); ?></strong> <a href="mailto:<?php echo esc_attr($item->email); ?>"><?php echo esc_html($item->email); ?></a></p>
                    <?php $this->render_moderation_buttons($item); ?>
                </div>
                <div class="s180re-admin-panel s180re-admin-panel-wide">
                    <table class="widefat striped">
                        <tbody>
                            <tr><th><?php esc_html_e('First name', 'science180-endorsement'); ?></th><td><?php echo esc_html($item->first_name); ?></td></tr>
                            <tr><th><?php esc_html_e('Last name', 'science180-endorsement'); ?></th><td><?php echo esc_html($item->last_name); ?></td></tr>
                            <tr><th><?php esc_html_e('Country of origin', 'science180-endorsement'); ?></th><td><?php echo esc_html($item->country_origin); ?></td></tr>
                            <tr><th><?php esc_html_e('Country of residence', 'science180-endorsement'); ?></th><td><?php echo esc_html($item->country_residence); ?></td></tr>
                            <tr><th><?php esc_html_e('Organization', 'science180-endorsement'); ?></th><td><?php echo esc_html($item->organization); ?></td></tr>
                            <tr><th><?php esc_html_e('Address', 'science180-endorsement'); ?></th><td><?php echo $item->address !== '' ? nl2br(esc_html($item->address)) : esc_html__('Not provided', 'science180-endorsement'); ?></td></tr>
                            <tr><th><?php esc_html_e('Comment', 'science180-endorsement'); ?></th><td><?php echo nl2br(esc_html($item->comment)); ?></td></tr>
                            <tr><th><?php esc_html_e('IP address', 'science180-endorsement'); ?></th><td><?php echo esc_html($item->ip_address ?? ''); ?></td></tr>
                            <tr><th><?php esc_html_e('IP location', 'science180-endorsement'); ?></th><td><?php echo esc_html(trim((string) ($item->ip_city ?? '') . ((($item->ip_city ?? '') && ($item->ip_country ?? '')) ? ', ' : '') . (string) ($item->ip_country ?? ''))); ?></td></tr>
                            <tr><th><?php esc_html_e('Device', 'science180-endorsement'); ?></th><td><?php echo esc_html($item->device_type ?? ''); ?></td></tr>
                            <tr><th><?php esc_html_e('User agent', 'science180-endorsement'); ?></th><td><?php echo esc_html($item->user_agent ?? ''); ?></td></tr>
                            <tr><th><?php esc_html_e('Public page URL', 'science180-endorsement'); ?></th><td><a href="<?php echo esc_url($item->status === 'approved' ? $this->endorsement_permalink($item) : $this->endorsement_preview_url($item)); ?>" target="_blank"><?php echo esc_html($this->endorsement_permalink($item)); ?></a></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_endorsement_admin_edit($endorsement_id)
    {
        $item = $this->get_endorsement($endorsement_id);
        if (!$item) {
            wp_die(esc_html__('Endorsement not found.', 'science180-endorsement'));
        }
        ?>
        <div class="wrap s180re-admin">
            <h1><?php esc_html_e('Edit Endorsement', 'science180-endorsement'); ?></h1>
            <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=s180en-endorsements&view=' . (int) $item->id)); ?>"><?php esc_html_e('Back to endorsement', 'science180-endorsement'); ?></a></p>
            <form class="s180re-admin-panel s180re-admin-edit-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="s180re_save_endorsement">
                <input type="hidden" name="endorsement_id" value="<?php echo esc_attr((int) $item->id); ?>">
                <?php wp_nonce_field('s180re_save_endorsement_' . (int) $item->id); ?>

                <label for="s180re-edit-email"><?php esc_html_e('Email', 'science180-endorsement'); ?></label>
                <input id="s180re-edit-email" class="regular-text" type="email" name="email" value="<?php echo esc_attr($item->email); ?>" required>

                <label for="s180re-edit-first"><?php esc_html_e('First name', 'science180-endorsement'); ?></label>
                <input id="s180re-edit-first" class="regular-text" type="text" name="first_name" value="<?php echo esc_attr($item->first_name); ?>" required>

                <label for="s180re-edit-last"><?php esc_html_e('Last name', 'science180-endorsement'); ?></label>
                <input id="s180re-edit-last" class="regular-text" type="text" name="last_name" value="<?php echo esc_attr($item->last_name); ?>" required>

                <label for="s180re-edit-origin"><?php esc_html_e('Country of origin', 'science180-endorsement'); ?></label>
                <?php echo $this->country_select('country_origin', 's180re-edit-origin', $item->country_origin, true); ?>

                <label for="s180re-edit-residence"><?php esc_html_e('Country of residence', 'science180-endorsement'); ?></label>
                <?php echo $this->country_select('country_residence', 's180re-edit-residence', $item->country_residence, true); ?>

                <label for="s180re-edit-org"><?php esc_html_e('Organization', 'science180-endorsement'); ?></label>
                <input id="s180re-edit-org" class="regular-text" type="text" name="organization" value="<?php echo esc_attr($item->organization); ?>" required>

                <label for="s180re-edit-address"><?php esc_html_e('Address', 'science180-endorsement'); ?></label>
                <textarea id="s180re-edit-address" class="large-text" name="address" rows="3"><?php echo esc_textarea($item->address); ?></textarea>

                <label><?php esc_html_e('Photo', 'science180-endorsement'); ?></label>
                <input type="hidden" name="photo_id" id="s180re-endorsement-photo-id" value="<?php echo esc_attr((int) $item->photo_id); ?>">
                <input class="regular-text" type="url" name="photo_url" id="s180re-endorsement-photo-url" value="<?php echo esc_url($item->photo_url); ?>" placeholder="https://">
                <p><button type="button" class="button" id="s180re-select-endorsement-photo"><?php esc_html_e('Upload / select photo', 'science180-endorsement'); ?></button></p>
                <div id="s180re-endorsement-photo-preview" class="s180re-photo-preview">
                    <?php if (!empty($item->photo_url)) : ?>
                        <img class="s180re-admin-photo" src="<?php echo esc_url($item->photo_url); ?>" alt="">
                    <?php endif; ?>
                </div>

                <label for="s180re-edit-comment"><?php esc_html_e('Endorsement description / comment', 'science180-endorsement'); ?></label>
                <textarea id="s180re-edit-comment" class="large-text" name="comment" rows="8" required><?php echo esc_textarea($item->comment); ?></textarea>

                <p><button type="submit" class="button button-primary"><?php esc_html_e('Save endorsement', 'science180-endorsement'); ?></button></p>
            </form>
        </div>
        <?php
    }

    private function render_moderation_buttons($item)
    {
        ?>
        <div class="s180re-row-actions">
            <?php if ($this->endorsement_is_reviewable($item)) : ?>
                <form class="s180re-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="s180re_moderate_endorsement">
                    <input type="hidden" name="endorsement_id" value="<?php echo esc_attr($item->id); ?>">
                    <?php wp_nonce_field('s180re_moderate_endorsement'); ?>
                    <button class="button button-primary" type="submit" name="moderation" value="approved"><?php esc_html_e('Approve', 'science180-endorsement'); ?></button>
                    <button class="button" type="submit" name="moderation" value="rejected"><?php esc_html_e('Reject', 'science180-endorsement'); ?></button>
                </form>
            <?php else : ?>
                <span class="s180re-status-note"><?php echo esc_html($this->endorsement_status_label($item->status)); ?></span>
            <?php endif; ?>
            <?php $this->render_delete_endorsement_form($item); ?>
        </div>
        <?php
    }

    private function render_endorsement_list_actions($item)
    {
        ?>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=s180en-endorsements&view=' . (int) $item->id)); ?>"><?php esc_html_e('View', 'science180-endorsement'); ?></a>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=s180en-endorsements&edit=' . (int) $item->id)); ?>"><?php esc_html_e('Edit', 'science180-endorsement'); ?></a>
        <a class="button" href="<?php echo esc_url($this->endorsement_preview_url($item)); ?>" target="_blank" rel="noopener"><?php echo $item->status === 'approved' ? esc_html__('Public', 'science180-endorsement') : esc_html__('Preview', 'science180-endorsement'); ?></a>
        <?php
        if ($this->endorsement_is_reviewable($item)) {
            ?>
            <button class="button button-primary" type="submit" name="s180re_single_action" value="<?php echo esc_attr('approved:' . (int) $item->id); ?>"><?php esc_html_e('Approve', 'science180-endorsement'); ?></button>
            <button class="button" type="submit" name="s180re_single_action" value="<?php echo esc_attr('rejected:' . (int) $item->id); ?>"><?php esc_html_e('Reject', 'science180-endorsement'); ?></button>
            <?php
        }
        ?>
        <button class="button s180re-delete-button" type="submit" name="s180re_single_action" value="<?php echo esc_attr('delete:' . (int) $item->id); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this endorsement permanently?', 'science180-endorsement')); ?>');"><?php esc_html_e('Delete', 'science180-endorsement'); ?></button>
        <?php
    }

    private function render_delete_endorsement_form($item)
    {
        ?>
        <form class="s180re-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this endorsement permanently?', 'science180-endorsement')); ?>');">
            <input type="hidden" name="action" value="s180re_delete_endorsement">
            <input type="hidden" name="endorsement_id" value="<?php echo esc_attr((int) $item->id); ?>">
            <?php wp_nonce_field('s180re_delete_endorsement_' . (int) $item->id); ?>
            <button class="button s180re-delete-button" type="submit"><?php esc_html_e('Delete', 'science180-endorsement'); ?></button>
        </form>
        <?php
    }

    private function endorsement_is_reviewable($item)
    {
        return $item && $item->status === 'verified';
    }

    private function endorsement_status_filter_options()
    {
        return array(
            '' => __('All statuses', 'science180-endorsement'),
            'pending_verification' => __('Pending email verification', 'science180-endorsement'),
            'verified' => __('Needs review', 'science180-endorsement'),
            'approved' => __('Approved', 'science180-endorsement'),
            'rejected' => __('Rejected', 'science180-endorsement'),
        );
    }

    private function endorsement_status_label($status)
    {
        $labels = $this->endorsement_status_filter_options();
        $status = sanitize_key($status);
        return isset($labels[$status]) ? $labels[$status] : ucwords(str_replace('_', ' ', $status));
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'science180-endorsement'));
        }
        ?>
        <div class="wrap s180re-admin">
            <h1><?php esc_html_e('Endorsement Settings', 'science180-endorsement'); ?></h1>
            <?php $this->render_admin_notice(); ?>
            <form class="s180re-admin-panel" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="s180en_save_settings">
                <?php wp_nonce_field('s180en_save_settings'); ?>
                <label><?php esc_html_e('Request/notice recipient email', 'science180-endorsement'); ?></label>
                <input class="regular-text" type="email" name="recipient_email" value="<?php echo esc_attr($this->recipient_email()); ?>" required>

                <label><?php esc_html_e('From name', 'science180-endorsement'); ?></label>
                <input class="regular-text" type="text" name="from_name" value="<?php echo esc_attr(get_option('s180re_from_name')); ?>">

                <label><?php esc_html_e('From email override', 'science180-endorsement'); ?></label>
                <input class="regular-text" type="email" name="from_email" value="<?php echo esc_attr(get_option('s180re_from_email')); ?>" placeholder="<?php echo esc_attr($this->sender_email()); ?>">
                <p class="description"><?php esc_html_e('Leave empty to use the existing Science180 Mail SMTP sender or the WordPress admin email. The plugin never stores SMTP passwords.', 'science180-endorsement'); ?></p>

                <label for="s180en-form-intro"><?php esc_html_e('Public form description', 'science180-endorsement'); ?></label>
                <textarea id="s180en-form-intro" class="large-text" name="form_intro" rows="4"><?php echo esc_textarea(get_option('s180en_form_intro')); ?></textarea>

                <h2><?php esc_html_e('Daily pending-review notice', 'science180-endorsement'); ?></h2>
                <p class="description">
                    <?php
                    $next_notice = wp_next_scheduled('s180re_daily_endorsement_notice');
                    echo esc_html($next_notice ? sprintf(__('Next scheduled run: %s', 'science180-endorsement'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_notice)) : __('Not scheduled. Deactivate and reactivate the plugin to reschedule it.', 'science180-endorsement'));
                    ?>
                </p>
                <label for="s180en-daily-notice-hour"><?php esc_html_e('Daily notice time', 'science180-endorsement'); ?></label>
                <input id="s180en-daily-notice-hour" type="time" name="daily_notice_hour" value="<?php echo esc_attr(get_option('s180re_daily_notice_hour', '09:00')); ?>">
                <p class="description"><?php esc_html_e('Uses the WordPress site timezone.', 'science180-endorsement'); ?></p>
                <label for="s180en-daily-subject"><?php esc_html_e('Daily notice subject', 'science180-endorsement'); ?></label>
                <input id="s180en-daily-subject" class="regular-text" type="text" name="daily_notice_subject" value="<?php echo esc_attr(get_option('s180en_daily_notice_subject')); ?>">
                <label for="s180en-daily-intro"><?php esc_html_e('Daily notice intro text', 'science180-endorsement'); ?></label>
                <textarea id="s180en-daily-intro" class="large-text" name="daily_notice_intro" rows="4"><?php echo esc_textarea(get_option('s180en_daily_notice_intro')); ?></textarea>

                <h2><?php esc_html_e('Letter sent after approval', 'science180-endorsement'); ?></h2>
                <p class="description"><?php esc_html_e('Available placeholders: {first_name}, {full_name}, {site_name}, {endorsement_url}, {view_endorsement_link}.', 'science180-endorsement'); ?></p>
                <label for="s180en-approval-subject"><?php esc_html_e('Approval email subject', 'science180-endorsement'); ?></label>
                <input id="s180en-approval-subject" class="regular-text" type="text" name="approval_subject" value="<?php echo esc_attr(get_option('s180en_approval_subject')); ?>">
                <label for="s180en-approval-body"><?php esc_html_e('Approval email body', 'science180-endorsement'); ?></label>
                <textarea id="s180en-approval-body" class="large-text code" name="approval_body" rows="10"><?php echo esc_textarea(get_option('s180en_approval_body')); ?></textarea>

                <p><button type="submit" class="button button-primary"><?php esc_html_e('Save settings', 'science180-endorsement'); ?></button></p>
            </form>
        </div>
        <?php
    }

    public function handle_moderate_endorsement()
    {
        $this->require_admin_post('s180re_moderate_endorsement');

        $endorsement_id = isset($_POST['endorsement_id']) ? absint($_POST['endorsement_id']) : 0;
        $moderation = isset($_POST['moderation']) ? sanitize_key($_POST['moderation']) : '';
        if (!in_array($moderation, array('approved', 'rejected'), true)) {
            $this->admin_redirect('s180en-endorsements', 'endorsement_invalid');
        }

        $result = $this->update_endorsement_moderation($endorsement_id, $moderation, true);
        if (is_wp_error($result)) {
            $this->admin_redirect('s180en-endorsements', $result->get_error_code());
        }

        if (!empty($result['status_changed']) && empty($result['email_sent'])) {
            $this->admin_redirect('s180en-endorsements', 'endorsement_email_failed');
        }

        $this->admin_redirect('s180en-endorsements', $moderation === 'approved' ? 'endorsement_approved' : 'endorsement_rejected');
    }

    public function handle_bulk_endorsements()
    {
        $this->require_admin_post('s180re_bulk_endorsements');

        $single = isset($_POST['s180re_single_action']) ? sanitize_text_field(wp_unslash($_POST['s180re_single_action'])) : '';
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_key($_POST['bulk_action']) : '';
        $ids = array();
        $action = $bulk_action;
        $is_single_action = false;

        if ($single !== '' && preg_match('/^(approved|rejected|delete):([0-9]+)$/', $single, $matches)) {
            $action = $matches[1];
            $ids = array(absint($matches[2]));
            $is_single_action = true;
        } elseif (!empty($_POST['s180re_endorsement_ids']) && is_array($_POST['s180re_endorsement_ids'])) {
            $ids = array_map('absint', wp_unslash($_POST['s180re_endorsement_ids']));
            $ids = array_values(array_filter(array_unique($ids)));
        }

        if (!in_array($action, array('approved', 'rejected', 'delete'), true)) {
            $this->admin_redirect('s180en-endorsements', 'endorsement_invalid');
        }

        if (empty($ids)) {
            $this->admin_redirect('s180en-endorsements', 'endorsement_none_selected');
        }

        $changed = 0;
        foreach ($ids as $endorsement_id) {
            if ($action === 'delete') {
                if ($this->delete_endorsement($endorsement_id)) {
                    $changed++;
                }
                continue;
            }

            $result = $this->update_endorsement_moderation($endorsement_id, $action, true);
            if (!is_wp_error($result)) {
                $changed++;
            }
        }

        if ($changed < 1) {
            $this->admin_redirect('s180en-endorsements', 'endorsement_missing');
        }

        if ($action === 'delete') {
            $this->admin_redirect('s180en-endorsements', $is_single_action ? 'endorsement_deleted' : 'endorsements_deleted');
        }

        if ($is_single_action) {
            $this->admin_redirect('s180en-endorsements', $action === 'approved' ? 'endorsement_approved' : 'endorsement_rejected');
        }

        $this->admin_redirect('s180en-endorsements', 'endorsements_updated');
    }

    public function handle_delete_endorsement()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'science180-endorsement'));
        }

        $endorsement_id = isset($_POST['endorsement_id']) ? absint($_POST['endorsement_id']) : 0;
        check_admin_referer('s180re_delete_endorsement_' . $endorsement_id);

        if (!$this->delete_endorsement($endorsement_id)) {
            $this->admin_redirect('s180en-endorsements', 'endorsement_missing');
        }

        $this->admin_redirect('s180en-endorsements', 'endorsement_deleted');
    }

    private function update_endorsement_moderation($endorsement_id, $moderation, $notify)
    {
        if (!in_array($moderation, array('approved', 'rejected'), true)) {
            return new WP_Error('endorsement_invalid');
        }

        global $wpdb;
        $endorsement = $this->get_endorsement($endorsement_id);
        if (!$endorsement) {
            return new WP_Error('endorsement_missing');
        }

        $slug = $endorsement->slug;
        if ($moderation === 'approved' && $slug === '') {
            $slug = $this->unique_endorsement_slug($this->endorsement_public_title($endorsement), $endorsement_id, $endorsement->created_at);
        }

        $updated = $wpdb->update(
            $this->table('endorsements'),
            array(
                'status' => $moderation,
                'slug' => $slug,
                'reviewed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $endorsement_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($updated === false) {
            return new WP_Error('endorsement_invalid');
        }

        $status_changed = $endorsement->status !== $moderation;
        $email_sent = true;
        $refreshed = $this->get_endorsement($endorsement_id);
        if ($notify && $refreshed && $status_changed) {
            $email_sent = $this->send_moderation_result_email($refreshed, $moderation);
        }

        return array(
            'email_sent' => $email_sent,
            'status_changed' => $status_changed,
        );
    }

    private function delete_endorsement($endorsement_id)
    {
        global $wpdb;

        $endorsement = $this->get_endorsement($endorsement_id);
        if (!$endorsement) {
            return false;
        }

        if (!empty($endorsement->photo_id)) {
            wp_delete_attachment((int) $endorsement->photo_id, true);
        }

        return (bool) $wpdb->delete($this->table('endorsements'), array('id' => $endorsement_id), array('%d'));
    }

    public function handle_save_endorsement()
    {
        $endorsement_id = isset($_POST['endorsement_id']) ? absint($_POST['endorsement_id']) : 0;
        $this->require_admin_post('s180re_save_endorsement_' . $endorsement_id);

        $endorsement = $this->get_endorsement($endorsement_id);
        if (!$endorsement) {
            $this->admin_redirect('s180en-endorsements', 'endorsement_missing');
        }

        $email = self::normalize_email_domain($this->post_raw('email'));
        if (!$email) {
            $this->admin_redirect('s180en-endorsements', 'endorsement_invalid');
        }

        $data = array(
            'email' => $email,
            'first_name' => $this->post_text('first_name', true),
            'last_name' => $this->post_text('last_name', true),
            'country_origin' => $this->post_text('country_origin', true),
            'country_residence' => $this->post_text('country_residence', true),
            'organization' => $this->post_text('organization', true),
            'address' => $this->post_textarea('address', false),
            'comment' => $this->post_textarea('comment', true),
            'photo_id' => isset($_POST['photo_id']) ? absint($_POST['photo_id']) : 0,
            'photo_url' => esc_url_raw($this->post_raw('photo_url')),
            'updated_at' => current_time('mysql'),
        );

        if ($this->has_empty_required($data, array('first_name', 'last_name', 'country_origin', 'country_residence', 'organization', 'comment'))) {
            $this->admin_redirect('s180en-endorsements', 'endorsement_invalid');
        }

        if ($endorsement->status === 'approved') {
            $title = sprintf('Endorsement by "%s" From "%s"', trim($data['first_name'] . ' ' . $data['last_name']), $data['country_origin']);
            $data['slug'] = $this->unique_endorsement_slug($title, $endorsement_id, $endorsement->created_at);
        }

        global $wpdb;
        $formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s');
        if (isset($data['slug'])) {
            $formats[] = '%s';
        }

        $updated = $wpdb->update($this->table('endorsements'), $data, array('id' => $endorsement_id), $formats, array('%d'));
        if ($updated === false) {
            $this->admin_redirect('s180en-endorsements', 'endorsement_invalid');
        }

        wp_safe_redirect(admin_url('admin.php?page=s180en-endorsements&view=' . $endorsement_id . '&s180re_admin_status=endorsement_saved'));
        exit;
    }

    public function handle_save_settings()
    {
        $this->require_admin_post('s180en_save_settings');
        $recipient = self::normalize_email_domain($this->post_raw('recipient_email'));
        if ($recipient && is_email($recipient)) {
            update_option('s180re_recipient_email', $recipient);
        }
        update_option('s180re_from_name', $this->post_text('from_name', false));
        $from_email = self::normalize_email_domain($this->post_raw('from_email'));
        update_option('s180re_from_email', $from_email && is_email($from_email) ? $from_email : '');
        update_option('s180en_form_intro', $this->post_textarea('form_intro', false));
        $notice_hour = $this->post_text('daily_notice_hour', false);
        if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $notice_hour)) {
            $notice_hour = '09:00';
        }
        if ($notice_hour !== get_option('s180re_daily_notice_hour', '09:00')) {
            update_option('s180re_daily_notice_hour', $notice_hour);
            self::reschedule_daily_notice();
        } elseif (!wp_next_scheduled('s180re_daily_endorsement_notice')) {
            self::reschedule_daily_notice();
        }
        update_option('s180en_daily_notice_subject', $this->post_text('daily_notice_subject', false));
        update_option('s180en_daily_notice_intro', $this->post_textarea('daily_notice_intro', false));
        update_option('s180en_approval_subject', $this->post_text('approval_subject', false));
        update_option('s180en_approval_body', $this->post_textarea('approval_body', false));
        $this->admin_redirect('s180en-settings', 'settings_saved');
    }

    private function require_admin_post($nonce_action)
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'science180-endorsement'));
        }

        check_admin_referer($nonce_action);
    }

    private function require_admin_get($nonce_action)
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'science180-endorsement'));
        }

        check_admin_referer($nonce_action);
    }

    private function admin_redirect($page, $status)
    {
        wp_safe_redirect(admin_url('admin.php?page=' . rawurlencode($page) . '&s180re_admin_status=' . rawurlencode($status)));
        exit;
    }

    private function redirect_back($status)
    {
        $referer = wp_get_referer();
        if (!$referer) {
            $referer = home_url('/');
        }

        wp_safe_redirect(add_query_arg('s180re_status', rawurlencode($status), $referer));
        exit;
    }

    private function render_public_notice($context)
    {
        if (empty($_GET['s180re_status'])) {
            return;
        }

        $status = sanitize_key($_GET['s180re_status']);
        $messages = array(
            'endorsement_check_email' => array('success', __('Please check your email and click the verification link. Your endorsement will be sent for review after your email is verified.', 'science180-endorsement')),
            'endorsement_email_failed' => array('warning', __('The verification email could not be sent. Please try again later or contact the site owner.', 'science180-endorsement')),
            'endorsement_verified' => array('success', __('Your email is verified. Your endorsement is now waiting for review.', 'science180-endorsement')),
            'endorsement_code_invalid' => array('warning', __('The verification details are invalid. Please check the email and try again.', 'science180-endorsement')),
            'endorsement_verify_invalid' => array('warning', __('This verification link is invalid or already used.', 'science180-endorsement')),
            'endorsement_verify_expired' => array('warning', __('This verification link expired. Please submit the endorsement again.', 'science180-endorsement')),
            'endorsement_photo_error' => array('warning', __('The photo could not be uploaded. Please use a JPG, PNG, or WebP image under 5 MB.', 'science180-endorsement')),
            'endorsement_invalid_email' => array('warning', __('Please enter a valid email address.', 'science180-endorsement')),
            'endorsement_missing' => array('warning', __('Please complete all required fields.', 'science180-endorsement')),
            'endorsement_error' => array('warning', __('The endorsement could not be saved. Please try again.', 'science180-endorsement')),
            'endorsement_invalid' => array('warning', __('The form could not be submitted. Please try again.', 'science180-endorsement')),
        );

        if (!isset($messages[$status])) {
            return;
        }

        if ($context === 'endorsement' && strpos($status, 'endorsement_') !== 0) {
            return;
        }

        echo '<div class="s180re-message s180re-message-' . esc_attr($messages[$status][0]) . '">' . esc_html($messages[$status][1]) . '</div>';
    }

    private function render_admin_notice()
    {
        if (empty($_GET['s180re_admin_status'])) {
            return;
        }

        $status = sanitize_key($_GET['s180re_admin_status']);
        $messages = array(
            'endorsement_approved' => __('Endorsement approved and the submitter was notified.', 'science180-endorsement'),
            'endorsement_rejected' => __('Endorsement rejected and the submitter was notified.', 'science180-endorsement'),
            'endorsement_email_failed' => __('Endorsement status updated, but the submitter email could not be sent.', 'science180-endorsement'),
            'endorsement_deleted' => __('Endorsement deleted.', 'science180-endorsement'),
            'endorsement_saved' => __('Endorsement saved.', 'science180-endorsement'),
            'endorsements_deleted' => __('Selected endorsements deleted.', 'science180-endorsement'),
            'endorsements_updated' => __('Selected endorsements updated.', 'science180-endorsement'),
            'endorsement_none_selected' => __('Please select at least one endorsement.', 'science180-endorsement'),
            'settings_saved' => __('Settings saved.', 'science180-endorsement'),
            'endorsement_invalid' => __('Invalid moderation action.', 'science180-endorsement'),
            'endorsement_missing' => __('Endorsement not found.', 'science180-endorsement'),
        );

        if (isset($messages[$status])) {
            $notice_class = in_array($status, array('endorsement_email_failed', 'endorsement_invalid', 'endorsement_missing', 'endorsement_none_selected'), true) ? 'notice-warning' : 'notice-success';
            echo '<div class="notice ' . esc_attr($notice_class) . '"><p>' . esc_html($messages[$status]) . '</p></div>';
        }
    }

    private function get_endorsement($endorsement_id)
    {
        global $wpdb;
        $table = $this->table('endorsements');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $endorsement_id));
    }

    private function get_endorsement_by_year_slug($year, $slug)
    {
        global $wpdb;
        $table = $this->table('endorsements');
        $year = absint($year);
        $slug = sanitize_title($slug);

        if ($year <= 0 || $slug === '') {
            return null;
        }

        $endorsement = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND slug = %s AND YEAR(created_at) = %d LIMIT 1",
                'approved',
                $slug,
                $year
            )
        );

        if ($endorsement) {
            return $endorsement;
        }

        $legacy_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND (slug = '' OR slug IS NULL) AND YEAR(created_at) = %d ORDER BY reviewed_at DESC, created_at DESC",
                'approved',
                $year
            )
        );

        foreach ($legacy_items as $item) {
            if (sanitize_title($this->endorsement_public_title($item)) === $slug) {
                return $item;
            }
        }

        return null;
    }


    private function render_endorsement_published_link()
    {
        $published_url = $this->published_endorsements_url();
        ?>
        <nav class="s180re-public-nav" aria-label="<?php esc_attr_e('Endorsement navigation', 'science180-endorsement'); ?>">
            <a href="<?php echo esc_url($published_url); ?>"><?php esc_html_e('View published endorsement/review', 'science180-endorsement'); ?></a>
        </nav>
        <?php
    }
    private function render_endorsement_nav($active)
    {
        $submit_url = $this->endorsement_page_url();
        $published_url = $this->published_endorsements_url();
        ?>
        <nav class="s180re-public-nav" aria-label="<?php esc_attr_e('Endorsement navigation', 'science180-endorsement'); ?>">
            <a class="<?php echo $active === 'submit' ? 'is-active' : ''; ?>" href="<?php echo esc_url($submit_url); ?>"><?php esc_html_e('Submit a review', 'science180-endorsement'); ?></a>
            <a class="<?php echo $active === 'published' ? 'is-active' : ''; ?>" href="<?php echo esc_url($published_url); ?>"><?php esc_html_e('View published endorsement/review', 'science180-endorsement'); ?></a>
        </nav>
        <?php
    }

    private function published_endorsements_url()
    {
        $page_id = (int) get_option('s180re_published_endorsements_page_id');
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        return home_url('/published-endorsements/');
    }

    private function endorsement_person_name($endorsement)
    {
        return trim($endorsement->first_name . ' ' . $endorsement->last_name);
    }

    private function endorsement_initials($endorsement)
    {
        $name = $this->endorsement_person_name($endorsement);
        $parts = preg_split('/\s+/', trim($name));
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $initials .= strtoupper(substr($part, 0, 1));
            if (strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : 'S';
    }

    private function endorsement_card_meta($endorsement)
    {
        $parts = array();

        if (!empty($endorsement->country_origin)) {
            $parts[] = sprintf(__('From %s', 'science180-endorsement'), $endorsement->country_origin);
        }

        if (!empty($endorsement->organization)) {
            $parts[] = $endorsement->organization;
        }

        return implode(' | ', $parts);
    }

    private function endorsement_public_title($endorsement)
    {
        return sprintf(
            'Endorsement by "%s" From "%s"',
            $this->endorsement_person_name($endorsement),
            $endorsement->country_origin
        );
    }

    private function endorsement_permalink($endorsement)
    {
        $slug = $endorsement->slug ? $endorsement->slug : sanitize_title($this->endorsement_public_title($endorsement));
        $year = $endorsement->created_at ? gmdate('Y', strtotime($endorsement->created_at)) : gmdate('Y');
        return home_url('/endorsement/' . $year . '/' . $slug . '/');
    }

    private function endorsement_preview_url($endorsement)
    {
        if ($endorsement->status === 'approved') {
            return $this->endorsement_permalink($endorsement);
        }

        return wp_nonce_url(
            add_query_arg('s180re_preview_endorsement', (int) $endorsement->id, $this->endorsement_page_url()),
            's180re_preview_endorsement_' . (int) $endorsement->id
        );
    }

    private function unique_endorsement_slug($title, $endorsement_id, $created_at)
    {
        global $wpdb;

        $base = sanitize_title($title);
        if ($base === '') {
            $base = 'endorsement';
        }

        $table = $this->table('endorsements');
        $year = $created_at ? gmdate('Y', strtotime($created_at)) : gmdate('Y');
        $slug = $base;
        $suffix = 2;

        while ((int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE id <> %d AND slug = %s AND YEAR(created_at) = %d",
                $endorsement_id,
                $slug,
                $year
            )
        ) > 0) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function endorsement_page_url()
    {
        $page_id = (int) get_option('s180re_endorsement_page_id');
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        return home_url('/endorsement/');
    }

    private function endorsement_page_anchor_url()
    {
        $url = $this->endorsement_page_url();
        $path = wp_parse_url($url, PHP_URL_PATH);
        $query = wp_parse_url($url, PHP_URL_QUERY);

        if (!$path) {
            $path = '/endorsement/';
        }

        $path = trailingslashit($path);
        if ($query) {
            $path .= '?' . $query;
        }

        return $path . '#s180re-approved-endorsements';
    }

    private function has_empty_required($data, $keys)
    {
        foreach ($keys as $key) {
            if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
                return true;
            }
        }

        return false;
    }

    private function post_raw($key)
    {
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    }

    private function post_text($key, $required)
    {
        $value = sanitize_text_field($this->post_raw($key));
        if ($required && $value === '') {
            return '';
        }

        return $value;
    }

    private function post_textarea($key, $required)
    {
        $value = sanitize_textarea_field($this->post_raw($key));
        if ($required && $value === '') {
            return '';
        }

        return $value;
    }

    private function clean_email_from_post($key)
    {
        $email = sanitize_email($this->post_raw($key));
        return ($email && is_email($email)) ? strtolower($email) : '';
    }

    private function submission_tracking_data()
    {
        $location = $this->ip_geolocation();

        return array(
            'ip_address' => $this->client_ip(),
            'ip_city' => $location['city'],
            'ip_country' => $location['country'],
            'user_agent' => $this->user_agent(),
            'device_type' => $this->device_type(),
        );
    }

    private function client_ip()
    {
        $headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );
        $candidates = array();

        foreach ($headers as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }

            $value = (string) wp_unslash($_SERVER[$header]);
            if ($header === 'HTTP_FORWARDED') {
                preg_match_all('/for="?([^;,\"]+)/i', $value, $matches);
                $parts = $matches[1] ?? array();
            } else {
                $parts = explode(',', $value);
            }

            foreach ($parts as $part) {
                $ip = trim((string) $part);
                $ip = trim(preg_replace('/^for=/i', '', $ip), "\"'[] ");
                if (substr_count($ip, ':') === 1 && strpos($ip, '.') !== false) {
                    $ip = preg_replace('/:\d+$/', '', $ip);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $candidates[] = $ip;
                }
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        return $candidates[0] ?? '';
    }

    private function ip_geolocation()
    {
        $location = array('city' => '', 'country' => '');

        if (!empty($_SERVER['HTTP_CF_IPCITY'])) {
            $location['city'] = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_IPCITY']));
        }
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $location['country'] = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_IPCOUNTRY']));
        }
        if (!empty($_SERVER['GEOIP_CITY'])) {
            $location['city'] = sanitize_text_field(wp_unslash($_SERVER['GEOIP_CITY']));
        }
        if (!empty($_SERVER['GEOIP_COUNTRY_NAME'])) {
            $location['country'] = sanitize_text_field(wp_unslash($_SERVER['GEOIP_COUNTRY_NAME']));
        }

        return $location;
    }

    private function device_type()
    {
        $agent = strtolower($this->user_agent());
        if ($agent === '') {
            return '';
        }
        if (strpos($agent, 'tablet') !== false || strpos($agent, 'ipad') !== false) {
            return 'tablet';
        }
        if (strpos($agent, 'mobile') !== false || strpos($agent, 'iphone') !== false || strpos($agent, 'android') !== false) {
            return 'mobile';
        }

        return 'desktop';
    }
    private function ip_hash()
    {
        $ip = $this->client_ip();
        return $ip ? hash('sha256', $ip . wp_salt('auth')) : '';
    }

    private function user_agent()
    {
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        return substr($agent, 0, 255);
    }

    private function generate_token()
    {
        return wp_hash(wp_generate_password(40, false) . microtime(true) . wp_rand());
    }

    private function generate_verification_code()
    {
        return (string) wp_rand(100000, 999999);
    }

    private function verification_code_hash($code)
    {
        $code = preg_replace('/\D+/', '', (string) $code);
        if (strlen($code) !== 6) {
            return '';
        }

        return hash_hmac('sha256', $code, wp_salt('auth'));
    }

    private function country_select($name, $id, $selected, $required)
    {
        $html = '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . ($required ? ' required' : '') . '>';
        $html .= '<option value="">' . esc_html__('Select country', 'science180-endorsement') . '</option>';
        foreach ($this->countries() as $country) {
            $html .= '<option value="' . esc_attr($country) . '"' . selected($selected, $country, false) . '>' . esc_html($country) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    private function countries()
    {
        return array(
            'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan',
            'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi',
            'Cabo Verde', 'Cambodia', 'Cameroon', 'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica', "Cote d'Ivoire", 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic',
            'Democratic Republic of the Congo', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic',
            'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia',
            'Fiji', 'Finland', 'France',
            'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana',
            'Haiti', 'Honduras', 'Hungary',
            'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy',
            'Jamaica', 'Japan', 'Jordan',
            'Kazakhstan', 'Kenya', 'Kiribati', 'Kuwait', 'Kyrgyzstan',
            'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg',
            'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar',
            'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'North Macedonia', 'Norway',
            'Oman',
            'Pakistan', 'Palau', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal',
            'Qatar',
            'Romania', 'Russia', 'Rwanda',
            'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria',
            'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu',
            'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan',
            'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam',
            'Yemen',
            'Zambia', 'Zimbabwe',
        );
    }
}
