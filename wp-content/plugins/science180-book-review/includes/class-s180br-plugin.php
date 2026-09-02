<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once S180BR_PLUGIN_DIR . 'includes/class-s180br-pdf.php';

class S180BR_Plugin
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
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('template_redirect', array($this, 'handle_review_verification_route'), 5);
        add_action('template_redirect', array($this, 'handle_pdf_open_route'), 6);
        add_action('template_redirect', array($this, 'handle_pdf_download_route'), 7);
        add_action('template_redirect', array($this, 'render_book_review_route'), 10);
        add_action('template_redirect', array($this, 'render_shortcode_page_fallback'), 20);
        add_action('s180br_daily_review_notice', array($this, 'send_daily_review_notice'));
        add_action('s180br_daily_review_notice', array($this, 'send_pdf_followups'));
        add_action('s180br_pending_verification_reminders', array($this, 'send_pending_verification_reminders'));

        add_shortcode('science180_review_request', array($this, 'render_review_request_shortcode'));

        add_action('admin_post_nopriv_s180re_review_request', array($this, 'handle_review_request_submission'));
        add_action('admin_post_s180re_review_request', array($this, 'handle_review_request_submission'));

        add_action('admin_post_s180re_save_book', array($this, 'handle_save_book'));
        add_action('admin_post_s180re_toggle_book', array($this, 'handle_toggle_book'));
        add_action('admin_post_s180br_delete_book', array($this, 'handle_delete_book'));
        add_action('admin_post_s180re_update_request_status', array($this, 'handle_update_request_status'));
        add_action('admin_post_s180br_send_pdf', array($this, 'handle_send_pdf'));
        add_action('admin_post_s180br_delete_request', array($this, 'handle_delete_request'));
        add_action('admin_post_s180br_save_settings', array($this, 'handle_save_settings'));
    }

    public static function activate()
    {
        self::create_tables();
        self::seed_options();
        self::migrate_default_options();
        self::seed_default_books();
        self::maybe_create_pages();
        self::register_rewrites_static();
        self::schedule_daily_notice();
        self::schedule_verification_reminders();
        update_option('s180br_version', S180BR_VERSION);
        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook('s180br_daily_review_notice');
        wp_clear_scheduled_hook('s180br_pending_verification_reminders');
        flush_rewrite_rules();
    }

    public function maybe_upgrade()
    {
        if (get_option('s180br_version') === S180BR_VERSION) {
            self::schedule_verification_reminders();
            return;
        }

        self::create_tables();
        self::seed_options();
        self::migrate_default_options();
        self::maybe_create_pages();
        self::schedule_daily_notice();
        self::schedule_verification_reminders();
        self::register_rewrites_static();
        flush_rewrite_rules(false);
        update_option('s180br_version', S180BR_VERSION);
    }

    private static function create_tables()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $books = self::table_static('books');
        $requests = self::table_static('review_requests');
        $deliveries = self::table_static('pdf_deliveries');

        $sql_books = "CREATE TABLE {$books} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(200) NOT NULL,
            description text NULL,
            cover_id bigint(20) unsigned DEFAULT 0,
            cover_url text NULL,
            pdf_id bigint(20) unsigned DEFAULT 0,
            pdf_url text NULL,
            margin_message text NULL,
            margin_color varchar(7) NOT NULL DEFAULT '#7030A0',
            margin_position varchar(10) NOT NULL DEFAULT 'top',
            margin_font_size int(11) NOT NULL DEFAULT 7,
            footer_font_size int(11) NOT NULL DEFAULT 8,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) {$charset};";

        $sql_requests = "CREATE TABLE {$requests} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            book_id bigint(20) unsigned NOT NULL,
            book_title varchar(255) NOT NULL,
            email varchar(190) NOT NULL,
            first_name varchar(120) NOT NULL,
            last_name varchar(120) NOT NULL,
            organization varchar(255) DEFAULT '',
            reviewer_role varchar(255) DEFAULT '',
            website varchar(255) DEFAULT '',
            phone varchar(80) DEFAULT '',
            address_line1 varchar(255) NOT NULL,
            address_line2 varchar(255) DEFAULT '',
            city varchar(120) NOT NULL,
            state_region varchar(120) DEFAULT '',
            postal_code varchar(80) DEFAULT '',
            country varchar(120) NOT NULL,
            qualifications text NULL,
            audience text NULL,
            message text NULL,
            status varchar(40) NOT NULL DEFAULT 'new',
            delivery_type varchar(40) NOT NULL DEFAULT '',
            verification_token varchar(80) DEFAULT '',
            token_expires datetime DEFAULT NULL,
            verified_at datetime DEFAULT NULL,
            ip_hash varchar(64) DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            ip_city varchar(120) DEFAULT '',
            ip_country varchar(120) DEFAULT '',
            device_type varchar(40) DEFAULT '',
            user_agent varchar(255) DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY book_email (book_id,email),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        $sql_deliveries = "CREATE TABLE {$deliveries} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_id bigint(20) unsigned NOT NULL,
            book_id bigint(20) unsigned NOT NULL,
            token_hash varchar(64) NOT NULL,
            token_value varchar(100) DEFAULT '',
            personalized tinyint(1) NOT NULL DEFAULT 1,
            file_path text NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'sent',
            emailed_at datetime DEFAULT NULL,
            email_opened_at datetime DEFAULT NULL,
            open_ip_address varchar(45) DEFAULT '',
            open_ip_city varchar(120) DEFAULT '',
            open_ip_country varchar(120) DEFAULT '',
            open_device_type varchar(40) DEFAULT '',
            open_user_agent varchar(255) DEFAULT '',
            downloaded_at datetime DEFAULT NULL,
            download_attempts int(11) NOT NULL DEFAULT 0,
            ip_address varchar(45) DEFAULT '',
            ip_city varchar(120) DEFAULT '',
            ip_country varchar(120) DEFAULT '',
            device_type varchar(40) DEFAULT '',
            user_agent varchar(255) DEFAULT '',
            reminder_sent_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY request_id (request_id),
            KEY book_id (book_id),
            KEY downloaded_at (downloaded_at)
        ) {$charset};";

        dbDelta($sql_books);
        dbDelta($sql_requests);
        dbDelta($sql_deliveries);
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
        add_option('s180br_daily_notice_subject', '[{site_name}] Book review requests waiting for review');
        add_option('s180br_daily_notice_intro', 'These verified book review copy requests are waiting for approval or rejection.');
        add_option('s180br_daily_notice_hour', '09:00');
        add_option('s180br_approval_subject', 'Your Science180 review copy request was approved');
        add_option('s180br_approval_body', "Hello {full_name},\n\nYour application to get a review copy of the book \"{book_title}\" is APPROVED. You will be receiving the copy of the book very soon.\n\nKind regards,\nScience180 Team");
        add_option('s180br_verification_subject', 'Verify your Science180 review copy request');
        add_option('s180br_paperback_subject', 'Your Science180 paperback review copy has been mailed');
        add_option('s180br_paperback_body', "Hello {full_name},\n\nYour review copy has been mailed to the address below, which you submitted during your application.\n\n{mailing_address}\n\nKind regards,\nScience180 Team");
        add_option('s180br_pdf_subject', '{book_title} is ready to download');
        add_option('s180br_pdf_body', "Hello {full_name},\n\n{book_title} is ready for you to download, read and review.\n\n{download_url}\n\nNote: This is a private, one-time download link for you only. If you share it, you may not be able to download your own copy.\n\nWhen finished, please leave your review at {endorsement_url}.\n\nKind regards,\nScience180 Team");
        add_option('s180br_followup_days', 30);
        add_option('s180br_followup_subject', 'A reminder to review {book_title}');
        add_option('s180br_followup_body', "Hello {full_name},\n\nThank you for requesting a review copy of {book_title}. It has been {days} days since we sent you a copy. We would like to remind you to submit your review if you have not done so yet.\n\nSubmit your review at {endorsement_url}.\n\nFor any questions or comments, please contact us.\n\nKind regards,\nScience180 Team");
        add_option('s180br_verification_reminder_hours', 12);
    }

    private static function migrate_default_options()
    {
        $pdf_body = (string) get_option('s180br_pdf_body', '');
        $duplicate_download_intro = "CLICK HERE TO DOWNLOAD THE BOOK:\n{download_url}";
        if (strpos($pdf_body, $duplicate_download_intro) !== false) {
            update_option('s180br_pdf_body', str_replace($duplicate_download_intro, '{download_url}', $pdf_body));
        }
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

    private static function seed_default_books()
    {
        global $wpdb;

        $books = self::table_static('books');
        $existing = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$books}");

        if ($existing > 0) {
            return;
        }

        $defaults = array(
            array(
                'title' => 'Boldest Scientific Formula of God and Creation',
                'file' => '2026/06/Boldest-Scientific-Formula-of-God-and-Creation.png',
            ),
            array(
                'title' => 'Turbulent Origin of the Universe',
                'file' => '2026/05/Turbulent-Origin-of-the-Universe-frontcover.png',
            ),
            array(
                'title' => 'Turbulent Origin of Life',
                'file' => '2026/05/Turbulent-Origin-of-Life-frontcover-600x903-1.png',
            ),
            array(
                'title' => 'Turbulent Origin of Chemical Particles',
                'file' => '2026/05/Turbulent-Origin-of-Chemical-Particles-frontcover-600x903-1.png',
            ),
            array(
                'title' => 'Science180 Accurate Scientific Proof of God',
                'file' => '2026/05/Science180-Accurate-Scientific-Proof-of-God-frontcover-600x901-1.png',
            ),
            array(
                'title' => 'Reconciling Science and Creation Accurately',
                'file' => '2026/05/Reconciling-Science-and-Creation-Accurately-frontcover-600x899-1.png',
            ),
            array(
                'title' => 'Origin of the Spiritual World',
                'file' => '2026/05/Origin-SpirituaOrigin-of-the-Spiritual-World-frontcover-600x902-1.png',
            ),
            array(
                'title' => 'How God Created Baby Universe',
                'file' => '2026/05/How-God-Created-Baby-Universe-frontcover-600x902-1.png',
            ),
            array(
                'title' => 'How Baby Universe Was Born',
                'file' => '2026/05/How-Baby-Universe-Was-Born-frontcover-600x907-1.png',
            ),
            array(
                'title' => "From Science to Bible's Conclusions",
                'file' => '2026/05/From-Science-to-Bibles-Conclusions-frontcover-600x904-1.png',
            ),
        );

        $now = current_time('mysql');
        foreach ($defaults as $index => $book) {
            $wpdb->insert(
                $books,
                array(
                    'title' => $book['title'],
                    'slug' => sanitize_title($book['title']),
                    'description' => '',
                    'cover_id' => 0,
                    'cover_url' => content_url('uploads/' . $book['file']),
                    'pdf_id' => 0,
                    'pdf_url' => '',
                    'margin_message' => '',
                    'margin_color' => '#7030A0',
                    'margin_position' => 'top',
                    'margin_font_size' => 7,
                    'footer_font_size' => 8,
                    'is_active' => 1,
                    'sort_order' => ($index + 1) * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s')
            );
        }
    }

    private static function maybe_create_pages()
    {
        self::create_page_if_missing(
            'BookReviewRequest',
            "Review Copy Request for Dr. Nathanael-Israel Israel's Book(s)",
            '[science180_review_request]',
            's180re_review_page_id'
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
        if (wp_next_scheduled('s180br_daily_review_notice')) {
            return;
        }

        wp_schedule_event(self::daily_notice_timestamp(), 'daily', 's180br_daily_review_notice');
    }

    private static function schedule_verification_reminders()
    {
        if (!wp_next_scheduled('s180br_pending_verification_reminders')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 's180br_pending_verification_reminders');
        }
    }

    private static function reschedule_daily_notice()
    {
        wp_clear_scheduled_hook('s180br_daily_review_notice');
        wp_schedule_event(self::daily_notice_timestamp(), 'daily', 's180br_daily_review_notice');
    }

    private static function daily_notice_timestamp()
    {
        $time = get_option('s180br_daily_notice_hour', '09:00');
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
        add_rewrite_rule('^bookreviewrequest/([^/]+)/?$', 'index.php?s180br_book_slug=$matches[1]', 'top');
        add_rewrite_rule('^BookReviewRequest/([^/]+)/?$', 'index.php?s180br_book_slug=$matches[1]', 'top');
    }

    public function register_query_vars($vars)
    {
        $vars[] = 's180br_book_slug';
        $vars[] = 's180br_pdf_download';
        $vars[] = 's180br_pdf_open';
        return $vars;
    }

    public function enqueue_frontend_assets()
    {
        wp_enqueue_style('s180re-frontend', S180BR_PLUGIN_URL . 'assets/css/frontend.css', array(), S180BR_VERSION);
        wp_enqueue_script('s180re-frontend', S180BR_PLUGIN_URL . 'assets/js/frontend.js', array(), S180BR_VERSION, true);
    }

    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 's180br') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('s180re-admin', S180BR_PLUGIN_URL . 'assets/css/admin.css', array(), S180BR_VERSION);
        wp_enqueue_script('s180re-admin', S180BR_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), S180BR_VERSION, true);
        wp_localize_script(
            's180re-admin',
            's180reAdmin',
            array(
                'chooseCover' => __('Choose book cover', 'science180-book-review'),
                'useCover' => __('Use this cover', 'science180-book-review'),
                'choosePdf' => __('Choose book PDF', 'science180-book-review'),
                'usePdf' => __('Use this PDF', 'science180-book-review'),
                'copied' => __('Copied', 'science180-book-review'),
                'copyFailed' => __('Copy failed', 'science180-book-review'),
            )
        );
    }

    public function register_admin_menu()
    {
        add_menu_page(
            __('Book Review', 'science180-book-review'),
            __('Book Review', 'science180-book-review'),
            'manage_options',
            's180br-books',
            array($this, 'render_books_page'),
            'dashicons-testimonial',
            26
        );

        add_submenu_page('s180br-books', __('Books', 'science180-book-review'), __('Books', 'science180-book-review'), 'manage_options', 's180br-books', array($this, 'render_books_page'));
        add_submenu_page('s180br-books', __('Review Requests', 'science180-book-review'), __('Review Requests', 'science180-book-review'), 'manage_options', 's180br-review-requests', array($this, 'render_review_requests_page'));
        add_submenu_page('s180br-books', __('Settings', 'science180-book-review'), __('Settings', 'science180-book-review'), 'manage_options', 's180br-settings', array($this, 'render_settings_page'));
    }

    public function render_review_request_shortcode($atts = array())
    {
        $atts = shortcode_atts(array('book' => '', 'book_id' => 0), (array) $atts, 'science180_review_request');
        $selected_slug = sanitize_title($atts['book']);
        $selected_book_id = absint($atts['book_id']);
        if (!$selected_book_id && isset($_GET['s180br_book_id'])) {
            $selected_book_id = absint($_GET['s180br_book_id']);
        }

        if ($selected_book_id) {
            $books = array_filter(array($this->get_book_for_public_selection($selected_book_id)));
        } elseif ($selected_slug) {
            $books = array_filter(array($this->get_book_by_slug($selected_slug)));
        } else {
            $books = $this->get_books(true);
        }
        $selected = !empty($books) ? $books[0] : null;
        $is_single_book_page = $selected_book_id > 0 || $selected_slug !== '';

        ob_start();
        $this->render_public_notice('review');
        ?>
        <section class="s180re-shell s180re-review-shell" data-s180re-review>
            <div class="s180re-public-heading">
                <p class="s180re-eyebrow"><?php esc_html_e('Professional reviewer request', 'science180-book-review'); ?></p>
                <h1><?php esc_html_e("Review Copy Request for Dr. Nathanael-Israel Israel's Book(s)", 'science180-book-review'); ?></h1>
                <p class="s180re-book-note"><?php echo $is_single_book_page ? esc_html__('Please fill this form to request a copy of this book', 'science180-book-review') : esc_html__('Please click on the book cover page to see the details', 'science180-book-review'); ?></p>
            </div>

            <?php if (empty($books)) : ?>
                <div class="s180re-message s180re-message-warning"><?php esc_html_e('No books are available for review requests yet.', 'science180-book-review'); ?></div>
            <?php else : ?>
                <?php if (!$is_single_book_page) : ?>
                <div class="s180re-book-strip" role="radiogroup" aria-label="<?php esc_attr_e('Choose one book', 'science180-book-review'); ?>">
                    <?php foreach ($books as $index => $book) : ?>
                        <label class="s180re-book-choice<?php echo $index === 0 ? ' is-selected' : ''; ?>">
                            <input type="radio" name="book_choice_preview" value="<?php echo esc_attr($book->id); ?>" data-cover="<?php echo esc_url($this->book_cover_url($book)); ?>" data-title="<?php echo esc_attr($book->title); ?>" data-description="<?php echo esc_attr($book->description); ?>" <?php checked($index, 0); ?>>
                            <span class="s180re-book-cover-wrap">
                                <?php if ($this->book_cover_url($book)) : ?>
                                    <img src="<?php echo esc_url($this->book_cover_url($book)); ?>" alt="<?php echo esc_attr($book->title); ?>">
                                <?php else : ?>
                                    <span class="s180re-cover-placeholder"><?php esc_html_e('Cover', 'science180-book-review'); ?></span>
                                <?php endif; ?>
                            </span>
                            <a class="s180re-book-title" href="<?php echo esc_url($this->book_review_url($book)); ?>"><?php echo esc_html($book->title); ?></a>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="s180re-form-layout">
                    <form class="s180re-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-s180re-review-form>
                        <input type="hidden" name="action" value="s180re_review_request">
                        <input type="hidden" name="form_started" value="<?php echo esc_attr(time()); ?>">
                        <input type="text" name="company_website" value="" class="s180re-hp" tabindex="-1" autocomplete="off">
                        <?php wp_nonce_field('s180re_review_request', 's180re_nonce'); ?>

                        <div class="s180re-field s180re-field-full">
                            <label for="s180re-book-id"><?php esc_html_e('Book requested', 'science180-book-review'); ?></label>
                            <select id="s180re-book-id" name="book_id" required data-s180re-book-select>
                                <?php foreach ($books as $index => $book) : ?>
                                    <option value="<?php echo esc_attr($book->id); ?>" data-cover="<?php echo esc_url($this->book_cover_url($book)); ?>" data-title="<?php echo esc_attr($book->title); ?>" data-description="<?php echo esc_attr($book->description); ?>" <?php selected($index, 0); ?>><?php echo esc_html($book->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="s180re-field">
                            <label for="s180re-review-email"><?php esc_html_e('Email', 'science180-book-review'); ?> <span>*</span></label>
                            <input id="s180re-review-email" type="email" name="email" required autocomplete="email">
                        </div>
                        <div class="s180re-field">
                            <label for="s180re-review-phone"><?php esc_html_e('Phone', 'science180-book-review'); ?></label>
                            <input id="s180re-review-phone" type="tel" name="phone" autocomplete="tel">
                        </div>
                        <div class="s180re-field">
                            <label for="s180re-review-first-name"><?php esc_html_e('First name', 'science180-book-review'); ?> <span>*</span></label>
                            <input id="s180re-review-first-name" type="text" name="first_name" required autocomplete="given-name">
                        </div>
                        <div class="s180re-field">
                            <label for="s180re-review-last-name"><?php esc_html_e('Last name', 'science180-book-review'); ?> <span>*</span></label>
                            <input id="s180re-review-last-name" type="text" name="last_name" required autocomplete="family-name">
                        </div>
                        <div class="s180re-field">
                            <label for="s180re-review-organization"><?php esc_html_e('Organization / publication', 'science180-book-review'); ?></label>
                            <input id="s180re-review-organization" type="text" name="organization" autocomplete="organization">
                        </div>
                        <div class="s180re-field">
                            <label for="s180re-review-role"><?php esc_html_e('Reviewer role / title', 'science180-book-review'); ?></label>
                            <input id="s180re-review-role" type="text" name="reviewer_role">
                        </div>
                        <div class="s180re-field s180re-field-full">
                            <label for="s180re-review-website"><?php esc_html_e('Website / reviewer profile', 'science180-book-review'); ?></label>
                            <div class="s180re-url-input">
                                <span class="s180re-url-prefix" aria-hidden="true">https://</span>
                                <input id="s180re-review-website" type="text" name="website" placeholder="science180.com/profile" autocomplete="url" inputmode="url" aria-describedby="s180re-review-website-note" data-s180br-url-input>
                            </div>
                            <p id="s180re-review-website-note" class="s180re-field-note"><?php esc_html_e('Enter the part after https:// (for example, science180.com/profile). You may also paste a complete URL; it will be corrected automatically.', 'science180-book-review'); ?></p>
                        </div>

                        <fieldset class="s180re-fieldset">
                            <legend><?php esc_html_e('Mailing address ready for paperback delivery', 'science180-book-review'); ?></legend>
                            <div class="s180re-field s180re-field-full">
                                <label for="s180re-review-address1"><?php esc_html_e('Address line 1', 'science180-book-review'); ?> <span>*</span></label>
                                <input id="s180re-review-address1" type="text" name="address_line1" required autocomplete="address-line1">
                            </div>
                            <div class="s180re-field s180re-field-full">
                                <label for="s180re-review-address2"><?php esc_html_e('Address line 2', 'science180-book-review'); ?></label>
                                <input id="s180re-review-address2" type="text" name="address_line2" autocomplete="address-line2">
                            </div>
                            <div class="s180re-field">
                                <label for="s180re-review-city"><?php esc_html_e('City', 'science180-book-review'); ?> <span>*</span></label>
                                <input id="s180re-review-city" type="text" name="city" required autocomplete="address-level2">
                            </div>
                            <div class="s180re-field">
                                <label for="s180re-review-state"><?php esc_html_e('State / region', 'science180-book-review'); ?></label>
                                <input id="s180re-review-state" type="text" name="state_region" autocomplete="address-level1">
                            </div>
                            <div class="s180re-field">
                                <label for="s180re-review-postal"><?php esc_html_e('Postal code', 'science180-book-review'); ?></label>
                                <input id="s180re-review-postal" type="text" name="postal_code" autocomplete="postal-code">
                            </div>
                            <div class="s180re-field">
                                <label for="s180re-review-country"><?php esc_html_e('Country', 'science180-book-review'); ?> <span>*</span></label>
                                <?php echo $this->country_select('country', 's180re-review-country', '', true); ?>
                            </div>
                        </fieldset>

                        <div class="s180re-field s180re-field-full">
                            <label for="s180re-review-qualifications"><?php esc_html_e('Professional review qualifications', 'science180-book-review'); ?> <span>*</span></label>
                            <textarea id="s180re-review-qualifications" name="qualifications" rows="4" required></textarea>
                        </div>
                        <div class="s180re-field s180re-field-full">
                            <label for="s180re-review-audience"><?php esc_html_e('Audience / review outlet', 'science180-book-review'); ?></label>
                            <textarea id="s180re-review-audience" name="audience" rows="3"></textarea>
                        </div>
                        <div class="s180re-field s180re-field-full">
                            <label for="s180re-review-message"><?php esc_html_e('Additional message', 'science180-book-review'); ?></label>
                            <textarea id="s180re-review-message" name="message" rows="3"></textarea>
                        </div>

                        <button class="s180re-button" type="submit"><?php esc_html_e('Submit Review Copy Request', 'science180-book-review'); ?></button>
                    </form>

                    <aside class="s180re-selected-book" aria-live="polite">
                        <p class="s180re-eyebrow"><?php esc_html_e('Selected book', 'science180-book-review'); ?></p>
                        <div class="s180re-selected-cover">
                            <?php if ($selected && $this->book_cover_url($selected)) : ?>
                                <img data-s180re-selected-cover src="<?php echo esc_url($this->book_cover_url($selected)); ?>" alt="<?php echo esc_attr($selected->title); ?>">
                            <?php else : ?>
                                <span data-s180re-selected-cover class="s180re-cover-placeholder"><?php esc_html_e('Cover', 'science180-book-review'); ?></span>
                            <?php endif; ?>
                        </div>
                        <h2 data-s180re-selected-title><?php echo $selected ? esc_html($selected->title) : ''; ?></h2>
                        <div class="s180re-selected-description" data-s180re-selected-description><?php echo $selected && !empty($selected->description) ? wpautop(esc_html($selected->description)) : ''; ?></div>
                    </aside>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private function normalize_https_url($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('#^(?:(?:https?):/*)+#i', '', $value);
        $value = ltrim($value, "/\\ \t\n\r\0\x0B");
        if ($value === '') {
            return '';
        }

        return esc_url_raw('https://' . $value, array('https'));
    }

    public function handle_review_request_submission()
    {
        if (!$this->public_form_is_valid('s180re_review_request')) {
            $this->redirect_back('review_invalid');
        }

        global $wpdb;

        $book_id = isset($_POST['book_id']) ? absint($_POST['book_id']) : 0;
        $book = $this->get_book($book_id);
        if (!$book || (int) $book->is_active !== 1) {
            $this->redirect_back('review_invalid_book');
        }

        $email = $this->clean_email_from_post('email');
        if (!$email) {
            $this->redirect_back('review_invalid_email');
        }

        $data = array(
            'book_id' => $book_id,
            'book_title' => $book->title,
            'email' => $email,
            'first_name' => $this->post_text('first_name', true),
            'last_name' => $this->post_text('last_name', true),
            'organization' => $this->post_text('organization', false),
            'reviewer_role' => $this->post_text('reviewer_role', false),
            'website' => $this->normalize_https_url($this->post_raw('website')),
            'phone' => $this->post_text('phone', false),
            'address_line1' => $this->post_text('address_line1', true),
            'address_line2' => $this->post_text('address_line2', false),
            'city' => $this->post_text('city', true),
            'state_region' => $this->post_text('state_region', false),
            'postal_code' => $this->post_text('postal_code', false),
            'country' => $this->post_text('country', true),
            'qualifications' => $this->post_textarea('qualifications', true),
            'audience' => $this->post_textarea('audience', false),
            'message' => $this->post_textarea('message', false),
            'status' => 'pending_verification',
            'ip_hash' => $this->ip_hash(),
            'ip_address' => $this->client_ip(),
            'ip_city' => '',
            'ip_country' => '',
            'device_type' => $this->device_type(),
            'user_agent' => $this->user_agent(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $geo = $this->ip_geolocation($data['ip_address']);
        $data['ip_city'] = $geo['city'];
        $data['ip_country'] = $geo['country'];

        if ($this->has_empty_required($data, array('first_name', 'last_name', 'address_line1', 'city', 'country', 'qualifications'))) {
            $this->redirect_back('review_missing');
        }

        $requests_table = $this->table('review_requests');
        $exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$requests_table} WHERE book_id = %d AND email = %s",
                $book_id,
                $email
            )
        );

        if ($exists > 0) {
            $this->redirect_back('review_duplicate');
        }

        $token = $this->generate_token();
        if (!$this->save_pending_review($token, $data)) {
            $this->redirect_back('review_error');
        }

        if (!$this->send_review_verification_email($data, $book, $token)) {
            $this->delete_pending_review($token);
            $this->redirect_back('review_email_failed');
        }

        $this->redirect_back('review_check_email');
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

    public function render_shortcode_page_fallback()
    {
        if (is_admin() || !is_page()) {
            return;
        }

        $review_page_id = (int) get_option('s180re_review_page_id');
        $is_review_page = ($review_page_id > 0 && is_page($review_page_id)) || is_page('BookReviewRequest');

        if (!$is_review_page) {
            return;
        }

        $shortcode = '[science180_review_request]';

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

    public function render_book_review_route()
    {
        if (is_admin()) {
            return;
        }

        $book_slug = sanitize_title(get_query_var('s180br_book_slug'));
        if ($book_slug === '') {
            return;
        }

        $shortcode = '[science180_review_request book="' . esc_attr($book_slug) . '"]';
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

    public function handle_review_verification_route()
    {
        if (empty($_GET['s180br_verify_review'])) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET['s180br_verify_review']));
        $data = $this->load_pending_review($token);
        if (!$data) {
            $this->redirect_to_review_page('review_verify_invalid');
        }

        $book = $this->get_book((int) $data['book_id']);
        if (!$book || (int) $book->is_active !== 1) {
            $this->delete_pending_review($token);
            $this->redirect_to_review_page('review_invalid_book');
        }

        global $wpdb;
        $requests_table = $this->table('review_requests');
        $exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$requests_table} WHERE book_id = %d AND email = %s",
                (int) $data['book_id'],
                $data['email']
            )
        );

        if ($exists > 0) {
            $this->delete_pending_review($token);
            $this->redirect_to_review_page('review_duplicate');
        }

        $now = current_time('mysql');
        $data['status'] = 'email_verified';
        $data['verification_token'] = '';
        $data['token_expires'] = null;
        $data['verified_at'] = $now;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $inserted = $wpdb->insert(
            $requests_table,
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        $this->delete_pending_review($token);

        if (!$inserted) {
            $this->redirect_to_review_page('review_error');
        }

        $this->redirect_to_review_page('review_verified');
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

    private function send_review_verification_email($data, $book, $token, $is_reminder = false)
    {
        $verify_url = add_query_arg('s180br_verify_review', rawurlencode($token), $this->review_request_page_url());
        $full_name = trim($data['first_name'] . ' ' . $data['last_name']);
        $message = '<p>' . sprintf(esc_html__('Hello %s,', 'science180-book-review'), esc_html($data['first_name'])) . '</p>';
        if ($is_reminder) {
            $message .= '<p><strong>' . esc_html__('Reminder: your review copy request is still waiting for email verification.', 'science180-book-review') . '</strong></p>';
        }
        $message .= '<p>' . esc_html__('Please verify your email address before we submit your review copy request.', 'science180-book-review') . '</p>';
        $message .= '<p><a href="' . esc_url($verify_url) . '" style="display:inline-block;background:#0f766e;color:#fff;padding:12px 18px;border-radius:6px;text-decoration:none;font-weight:700;">' . esc_html__('Verify My Email', 'science180-book-review') . '</a></p>';
        $message .= '<p>' . esc_html__('If the button does not work, copy and paste this link into your browser:', 'science180-book-review') . '<br><a href="' . esc_url($verify_url) . '">' . esc_html($verify_url) . '</a></p>';
        $message .= '<p><strong>' . esc_html__('Book:', 'science180-book-review') . '</strong> ' . esc_html($book->title) . '</p>';

        $sent = $this->send_mail(
            $data['email'],
            ($is_reminder ? __('Reminder: ', 'science180-book-review') : '') . get_option('s180br_verification_subject', 'Verify your Science180 review copy request'),
            $message,
            $this->mail_headers('', $full_name)
        );

        if (!$sent) {
            $this->log_mail_failure('review verification', $data['email']);
        }

        return $sent;
    }

    public function send_pending_verification_reminders()
    {
        $directory = $this->pending_review_dir();
        if (!is_dir($directory)) {
            return;
        }

        $reminder_after = max(1, (int) get_option('s180br_verification_reminder_hours', 12)) * HOUR_IN_SECONDS;
        $now = time();
        $processed = 0;
        foreach ((array) glob(trailingslashit($directory) . '*.json') as $path) {
            if ($processed >= 100) {
                break;
            }
            if (!is_readable($path)) {
                continue;
            }
            $payload = json_decode((string) file_get_contents($path), true);
            if (!is_array($payload)) {
                continue;
            }
            if (!empty($payload['expires']) && (int) $payload['expires'] < $now) {
                wp_delete_file($path);
                continue;
            }
            if (empty($payload['data']) || empty($payload['token'])) {
                continue;
            }
            $created = isset($payload['created_at']) ? (int) $payload['created_at'] : 0;
            if ($created < 1 || ($created + $reminder_after) > $now || !empty($payload['reminder_sent_at'])) {
                continue;
            }

            $book = $this->get_book((int) $payload['data']['book_id']);
            if (!$book) {
                continue;
            }
            if ($this->send_review_verification_email($payload['data'], $book, $payload['token'], true)) {
                $payload['reminder_sent_at'] = $now;
                if (is_file($path)) {
                    file_put_contents($path, wp_json_encode($payload), LOCK_EX);
                }
            }
            $processed++;
        }
    }

    public function send_daily_review_notice()
    {
        global $wpdb;
        $table = $this->table('review_requests');
        $items = $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'email_verified' ORDER BY created_at ASC LIMIT 200");
        if (empty($items)) {
            return;
        }

        $rows = '';
        foreach ($items as $item) {
            $view_url = admin_url('admin.php?page=s180br-review-requests&s180br_view=' . (int) $item->id);
            $rows .= '<tr>';
            $rows .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . esc_html($item->created_at) . '</td>';
            $rows .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . esc_html($item->book_title) . '</td>';
            $rows .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . esc_html(trim($item->first_name . ' ' . $item->last_name)) . '</td>';
            $rows .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . esc_html($item->email) . '</td>';
            $rows .= '<td style="padding:8px;border-bottom:1px solid #ddd;">' . esc_html($item->verified_at ? __('Verified', 'science180-book-review') : __('Not verified', 'science180-book-review')) . '</td>';
            $rows .= '<td style="padding:8px;border-bottom:1px solid #ddd;"><a href="' . esc_url($view_url) . '">' . esc_html__('Review request', 'science180-book-review') . '</a></td>';
            $rows .= '</tr>';
        }

        $subject = $this->format_template(get_option('s180br_daily_notice_subject', '[{site_name}] Book review requests waiting for review'), array());
        $intro = $this->format_template(get_option('s180br_daily_notice_intro', 'These verified book review copy requests are waiting for approval or rejection.'), array());
        $message = '<p>' . nl2br(esc_html($intro)) . '</p>';
        $message .= '<table style="border-collapse:collapse;width:100%;"><thead><tr><th align="left">Date</th><th align="left">Book</th><th align="left">Applicant</th><th align="left">Email</th><th align="left">Email verification</th><th align="left">Action</th></tr></thead><tbody>' . $rows . '</tbody></table>';

        $sent = $this->send_mail($this->recipient_email(), $subject, $message, $this->mail_headers());
        if (!$sent) {
            $this->log_mail_failure('daily review notice', $this->recipient_email());
        }
    }

    private function send_review_request_status_email($request, $status)
    {
        $status_label = $this->review_request_status_label($status);
        $first_name = !empty($request->first_name) ? $request->first_name : __('there', 'science180-book-review');
        $subject = sprintf('Your Science180 review copy request is %s', strtolower($status_label));
        $message = '<p>' . sprintf(esc_html__('Hello %s,', 'science180-book-review'), esc_html($first_name)) . '</p>';

        if ($status === 'qualified') {
            $subject = $this->format_template(get_option('s180br_approval_subject', 'Your Science180 review copy request was approved'), (array) $request);
            $body = $this->format_template(get_option('s180br_approval_body', "Hello {full_name},\n\nYour application to get a review copy of the book \"{book_title}\" is APPROVED. You will be receiving the copy of the book very soon.\n\nKind regards,\nScience180 Team"), (array) $request);
            $message = wpautop(wp_kses_post($body));
        } elseif ($status === 'declined') {
            $subject = 'Your Science180 review copy request was reviewed';
            $message .= '<p>' . esc_html__('Thank you for your interest. After review, your request was not approved at this time.', 'science180-book-review') . '</p>';
        } elseif ($status === 'sent' && isset($request->delivery_type) && $request->delivery_type === 'paperback') {
            $email_data = (array) $request;
            $email_data['mailing_address'] = $this->format_mailing_address($email_data);
            $subject = $this->format_template(get_option('s180br_paperback_subject', 'Your Science180 paperback review copy has been mailed'), $email_data);
            $body = $this->format_template(get_option('s180br_paperback_body', "Hello {full_name},\n\nYour review copy has been mailed to the address below, which you submitted during your application.\n\n{mailing_address}\n\nKind regards,\nScience180 Team"), $email_data);
            $message = wpautop(wp_kses_post($body));
        } elseif ($status === 'sent') {
            $subject = 'Your Science180 review copy has been sent';
            $message .= '<p>' . esc_html__('Your review copy has been marked as sent.', 'science180-book-review') . '</p>';
        } else {
            $message .= '<p>' . sprintf(esc_html__('Your review copy request status is now: %s.', 'science180-book-review'), esc_html($status_label)) . '</p>';
        }

        if (!empty($request->book_title)) {
            $message .= '<p><strong>' . esc_html__('Book:', 'science180-book-review') . '</strong> ' . esc_html($request->book_title) . '</p>';
        }

        $message .= '<p>' . esc_html__('Thank you for your interest in Science180.', 'science180-book-review') . '</p>';

        $sent = $this->send_mail($request->email, $subject, $message, $this->mail_headers());
        if (!$sent) {
            $this->log_mail_failure('review request status', $request->email);
        }

        return $sent;
    }

    private function format_template($template, $data)
    {
        $full_name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $replacements = array(
            '{site_name}' => get_bloginfo('name'),
            '{first_name}' => $data['first_name'] ?? '',
            '{full_name}' => $full_name,
            '{book_title}' => $data['book_title'] ?? '',
            '{download_url}' => $data['download_url'] ?? '',
            '{endorsement_url}' => $data['endorsement_url'] ?? home_url('/endorsement/'),
            '{mailing_address}' => $data['mailing_address'] ?? $this->format_mailing_address($data),
            '{delivery_method}' => $data['delivery_method'] ?? $this->delivery_type_label($data['delivery_type'] ?? ''),
            '{days}' => isset($data['days']) ? (string) $data['days'] : '',
        );

        return strtr((string) $template, $replacements);
    }

    private function send_mail($to, $subject, $message, $headers = array())
    {
        $from_email = $this->sender_email();
        $from_name = $this->sender_name();
        $from_override = null;

        if ($from_email && is_email($from_email)) {
            $from_override = function ($phpmailer) use ($from_email, $from_name) {
                if (method_exists($phpmailer, 'setFrom')) {
                    $phpmailer->setFrom($from_email, $from_name, false);
                } else {
                    $phpmailer->From = $from_email;
                    $phpmailer->FromName = $from_name;
                }
            };
            add_action('phpmailer_init', $from_override, PHP_INT_MAX);
        }

        try {
            return wp_mail($to, $subject, $message, $headers);
        } finally {
            if ($from_override) {
                remove_action('phpmailer_init', $from_override, PHP_INT_MAX);
            }
        }
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

    private function format_mailbox($email, $name)
    {
        $email = sanitize_email($email);
        $name = trim(str_replace(array('"', "\r", "\n"), '', wp_strip_all_tags((string) $name)));
        if ($name === '') {
            return $email;
        }

        return $name . ' <' . $email . '>';
    }

    public function render_books_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'science180-book-review'));
        }

        $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $book = $edit_id ? $this->get_book($edit_id) : null;
        $books = $this->get_books(false);
        ?>
        <div class="wrap s180re-admin">
            <h1><?php esc_html_e('Books for Review Copy Requests', 'science180-book-review'); ?></h1>
            <?php $this->render_admin_notice(); ?>
            <p class="subsubsub">
                <a href="<?php echo esc_url(admin_url('admin.php?page=s180br-books')); ?>"><?php esc_html_e('All', 'science180-book-review'); ?></a> |
                <a href="<?php echo esc_url(admin_url('admin.php?page=s180br-review-requests&status=email_verified')); ?>"><?php esc_html_e('Needs review', 'science180-book-review'); ?></a> |
                <a href="<?php echo esc_url(admin_url('admin.php?page=s180br-review-requests&status=qualified')); ?>"><?php esc_html_e('Approved', 'science180-book-review'); ?></a> |
                <a href="<?php echo esc_url(admin_url('admin.php?page=s180br-review-requests&status=declined')); ?>"><?php esc_html_e('Rejected', 'science180-book-review'); ?></a> |
                <a href="<?php echo esc_url($this->review_request_page_url()); ?>" target="_blank" rel="noopener"><?php esc_html_e('PUBLIC URL', 'science180-book-review'); ?></a>
            </p>
            <br class="clear">

            <div class="s180re-admin-layout">
                <form class="s180re-admin-panel" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <h2><?php echo $book ? esc_html__('Edit book', 'science180-book-review') : esc_html__('Add book', 'science180-book-review'); ?></h2>
                    <input type="hidden" name="action" value="s180re_save_book">
                    <input type="hidden" name="book_id" value="<?php echo esc_attr($book ? $book->id : 0); ?>">
                    <?php wp_nonce_field('s180re_save_book'); ?>

                    <label><?php esc_html_e('Title', 'science180-book-review'); ?></label>
                    <input class="regular-text" type="text" name="title" value="<?php echo esc_attr($book ? $book->title : ''); ?>" required>

                    <label><?php esc_html_e('Description', 'science180-book-review'); ?></label>
                    <textarea class="large-text" name="description" rows="4"><?php echo esc_textarea($book ? $book->description : ''); ?></textarea>

                    <label><?php esc_html_e('Cover', 'science180-book-review'); ?></label>
                    <input type="hidden" name="cover_id" id="s180re-cover-id" value="<?php echo esc_attr($book ? (int) $book->cover_id : 0); ?>">
                    <input class="regular-text" type="url" name="cover_url" id="s180re-cover-url" value="<?php echo esc_url($book ? $book->cover_url : ''); ?>" placeholder="https://">
                    <p><button type="button" class="button" id="s180re-select-cover"><?php esc_html_e('Upload / select cover', 'science180-book-review'); ?></button></p>
                    <div id="s180re-cover-preview" class="s180re-cover-preview">
                        <?php if ($book && $this->book_cover_url($book)) : ?>
                            <img src="<?php echo esc_url($this->book_cover_url($book)); ?>" alt="">
                        <?php endif; ?>
                    </div>

                    <label><?php esc_html_e('Private review PDF', 'science180-book-review'); ?></label>
                    <input type="hidden" name="pdf_id" id="s180re-pdf-id" value="<?php echo esc_attr($book && isset($book->pdf_id) ? (int) $book->pdf_id : 0); ?>">
                    <input class="regular-text" type="url" name="pdf_url" id="s180re-pdf-url" value="<?php echo esc_url($book && isset($book->pdf_url) ? $book->pdf_url : ''); ?>" placeholder="https://">
                    <p><button type="button" class="button" id="s180re-select-pdf"><?php esc_html_e('Upload / select PDF', 'science180-book-review'); ?></button></p>
                    <p class="description"><?php esc_html_e('Stored for admin use only. This file is not shown on the public review request form.', 'science180-book-review'); ?></p>

                    <label><?php esc_html_e('Message to put on book margin', 'science180-book-review'); ?></label>
                    <textarea class="large-text" name="margin_message" rows="4"><?php echo esc_textarea($book && isset($book->margin_message) ? $book->margin_message : ''); ?></textarea>

                    <label><?php esc_html_e('Margin message color', 'science180-book-review'); ?></label>
                    <input type="color" name="margin_color" value="<?php echo esc_attr($book && !empty($book->margin_color) ? $book->margin_color : '#7030A0'); ?>">

                    <label><?php esc_html_e('Margin message font size', 'science180-book-review'); ?></label>
                    <input type="number" min="5" max="24" name="margin_font_size" value="<?php echo esc_attr($book && isset($book->margin_font_size) ? (int) $book->margin_font_size : 7); ?>">

                    <label><?php esc_html_e('Name and email footer font size', 'science180-book-review'); ?></label>
                    <input type="number" min="5" max="24" name="footer_font_size" value="<?php echo esc_attr($book && isset($book->footer_font_size) ? (int) $book->footer_font_size : 8); ?>">

                    <label><?php esc_html_e('Margin message position', 'science180-book-review'); ?></label>
                    <select name="margin_position">
                        <?php $margin_position = $book && !empty($book->margin_position) ? $book->margin_position : 'top'; ?>
                        <option value="top" <?php selected($margin_position, 'top'); ?>><?php esc_html_e('Top margin', 'science180-book-review'); ?></option>
                        <option value="left" <?php selected($margin_position, 'left'); ?>><?php esc_html_e('Left margin', 'science180-book-review'); ?></option>
                        <option value="right" <?php selected($margin_position, 'right'); ?>><?php esc_html_e('Right margin', 'science180-book-review'); ?></option>
                    </select>

                    <label><?php esc_html_e('Sort order', 'science180-book-review'); ?></label>
                    <input type="number" name="sort_order" value="<?php echo esc_attr($book ? (int) $book->sort_order : 10); ?>">

                    <label class="s180re-check"><input type="checkbox" name="is_active" value="1" <?php checked(!$book || (int) $book->is_active === 1); ?>> <?php esc_html_e('Available on public form', 'science180-book-review'); ?></label>

                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Save book', 'science180-book-review'); ?></button></p>
                </form>

                <div class="s180re-admin-panel s180re-admin-panel-wide">
                    <h2><?php esc_html_e('Current books', 'science180-book-review'); ?></h2>
                    <table class="widefat striped s180br-books-table">
                        <thead><tr><th><?php esc_html_e('Cover', 'science180-book-review'); ?></th><th><?php esc_html_e('Title', 'science180-book-review'); ?></th><th><?php esc_html_e('Status', 'science180-book-review'); ?></th><th><?php esc_html_e('Actions', 'science180-book-review'); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($books as $item) : ?>
                                <tr>
                                    <td class="s180re-table-cover"><?php if ($this->book_cover_url($item)) : ?><img src="<?php echo esc_url($this->book_cover_url($item)); ?>" alt=""><?php endif; ?></td>
                                    <td class="s180br-book-title-cell"><a href="<?php echo esc_url($this->book_review_url($item)); ?>" target="_blank" rel="noopener"><?php echo esc_html($item->title); ?></a></td>
                                    <td><?php echo (int) $item->is_active === 1 ? esc_html__('Active', 'science180-book-review') : esc_html__('Hidden', 'science180-book-review'); ?></td>
                                    <td class="s180br-book-actions">
                                        <a class="button" href="<?php echo esc_url($this->book_review_url($item)); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'science180-book-review'); ?></a>
                                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=s180br-books&edit=' . (int) $item->id)); ?>"><?php esc_html_e('Edit', 'science180-book-review'); ?></a>
                                        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=s180re_toggle_book&book_id=' . (int) $item->id), 's180re_toggle_book')); ?>"><?php echo (int) $item->is_active === 1 ? esc_html__('Hide', 'science180-book-review') : esc_html__('Show', 'science180-book-review'); ?></a>
                                        <a class="button s180re-delete-button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=s180br_delete_book&book_id=' . (int) $item->id), 's180br_delete_book')); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this book?', 'science180-book-review')); ?>');"><?php esc_html_e('Delete', 'science180-book-review'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($book) : ?>
                <?php $this->render_delivery_table((int) $book->id); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_review_requests_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'science180-book-review'));
        }

        global $wpdb;
        $view_id = isset($_GET['s180br_view']) ? absint($_GET['s180br_view']) : (isset($_GET['view']) ? absint($_GET['view']) : 0);
        if ($view_id) {
            $this->render_review_request_detail($view_id);
            return;
        }

        $table = $this->table('review_requests');
        $books = $this->get_books(false);
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $book_id = isset($_GET['book_id']) ? absint($_GET['book_id']) : 0;
        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $where = array('1=1');
        $params = array();

        if ($status !== '' && array_key_exists($status, $this->review_request_statuses())) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        if ($book_id > 0) {
            $where[] = 'book_id = %d';
            $params[] = $book_id;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $where[] = 'created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $where[] = 'created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }
        if ($search !== '') {
            $where[] = '(book_title LIKE %s OR email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR organization LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 200";
        $items = $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);
        $return_url = $this->current_review_requests_url();
        ?>
        <div class="wrap s180re-admin">
            <h1><?php esc_html_e('Review Copy Requests', 'science180-book-review'); ?></h1>
            <?php $this->render_admin_notice(); ?>
            <p class="subsubsub">
                <a href="<?php echo esc_url(admin_url('admin.php?page=s180br-review-requests')); ?>"><?php esc_html_e('All', 'science180-book-review'); ?></a>
                <?php foreach ($this->review_request_statuses() as $status_key => $status_label) : ?>
                    | <a href="<?php echo esc_url(admin_url('admin.php?page=s180br-review-requests&status=' . $status_key)); ?>"><?php echo esc_html($status_label); ?></a>
                <?php endforeach; ?>
            </p>
            <br class="clear">
            <form class="s180re-filter-form" method="get">
                <input type="hidden" name="page" value="s180br-review-requests">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search name, email, book, organization', 'science180-book-review'); ?>">
                <select name="book_id">
                    <option value="0"><?php esc_html_e('All books', 'science180-book-review'); ?></option>
                    <?php foreach ($books as $book) : ?>
                        <option value="<?php echo esc_attr($book->id); ?>" <?php selected($book_id, (int) $book->id); ?>><?php echo esc_html($book->title); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value=""><?php esc_html_e('All statuses', 'science180-book-review'); ?></option>
                    <?php foreach ($this->review_request_statuses() as $status_key => $status_label) : ?>
                        <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status, $status_key); ?>><?php echo esc_html($status_label); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                <button class="button" type="submit"><?php esc_html_e('Filter', 'science180-book-review'); ?></button>
            </form>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Date', 'science180-book-review'); ?></th><th><?php esc_html_e('Book', 'science180-book-review'); ?></th><th><?php esc_html_e('Applicant', 'science180-book-review'); ?></th><th><?php esc_html_e('Status', 'science180-book-review'); ?></th><th><?php esc_html_e('Actions', 'science180-book-review'); ?></th></tr></thead>
                <tbody>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="5"><?php esc_html_e('No review copy requests yet.', 'science180-book-review'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item->created_at); ?></td>
                            <td><a href="<?php echo esc_url($this->book_review_url($item)); ?>" target="_blank" rel="noopener"><?php echo esc_html($item->book_title); ?></a></td>
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=s180br-review-requests&s180br_view=' . (int) $item->id . '&return_url=' . rawurlencode($return_url))); ?>"><?php echo esc_html($item->first_name . ' ' . $item->last_name); ?></a><br><a href="mailto:<?php echo esc_attr($item->email); ?>"><?php echo esc_html($item->email); ?></a></td>
                            <td><?php echo esc_html($this->review_request_status_label($item->status, $item)); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=s180br-review-requests&s180br_view=' . (int) $item->id . '&return_url=' . rawurlencode($return_url))); ?>"><?php esc_html_e('View', 'science180-book-review'); ?></a>
                                <a class="button s180re-delete-button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=s180br_delete_request&request_id=' . (int) $item->id . '&return_url=' . rawurlencode($return_url)), 's180br_delete_request')); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this request?', 'science180-book-review')); ?>');"><?php esc_html_e('Delete', 'science180-book-review'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_review_request_detail($request_id)
    {
        global $wpdb;
        $table = $this->table('review_requests');
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $request_id));
        if (!$item) {
            wp_die(esc_html__('Request not found.', 'science180-book-review'));
        }

        if ($this->backfill_request_telemetry($item)) {
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $request_id));
        }

        $data = (array) $item;
        $return_url = $this->request_return_url();
        ?>
        <div class="wrap s180re-admin">
            <h1><?php esc_html_e('Review Copy Request Details', 'science180-book-review'); ?></h1>
            <?php $this->render_admin_notice(); ?>
            <p>
                <a class="button" href="<?php echo esc_url($return_url); ?>"><?php esc_html_e('Back to requests', 'science180-book-review'); ?></a>
                <a class="button" href="<?php echo esc_url($this->book_review_url($item)); ?>" target="_blank" rel="noopener"><?php esc_html_e('View book public page', 'science180-book-review'); ?></a>
            </p>

            <div class="s180re-admin-layout">
                <div class="s180re-admin-panel">
                    <h2><?php esc_html_e('Clean address', 'science180-book-review'); ?></h2>
                    <p><strong><?php esc_html_e('Email verification:', 'science180-book-review'); ?></strong> <?php echo $item->verified_at ? esc_html__('Verified', 'science180-book-review') . ' (' . esc_html($item->verified_at) . ')' : esc_html__('Not verified', 'science180-book-review'); ?></p>
                    <p><strong><?php esc_html_e('Current status:', 'science180-book-review'); ?></strong> <?php echo esc_html($this->review_request_status_label($item->status, $item)); ?></p>
                    <pre class="s180re-address-block"><?php echo esc_html($this->format_mailing_address($data)); ?></pre>
                </div>
                <div class="s180re-admin-panel s180re-admin-panel-wide">
                    <h2><?php esc_html_e('Raw data', 'science180-book-review'); ?></h2>
                    <table class="widefat striped">
                        <tbody>
                            <?php foreach ($this->review_request_labels() as $key => $label) : ?>
                                <?php if (isset($data[$key]) && ($key !== 'delivery_type' || $data[$key] !== '')) : ?>
                                    <tr><th class="s180br-request-detail-label"><?php echo esc_html($label); ?></th><td><?php echo nl2br(esc_html($this->review_request_field_value($key, $data[$key], $item))); ?></td></tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="s180re-admin-panel">
                <h2><?php esc_html_e('Review actions', 'science180-book-review'); ?></h2>
                <div class="s180re-row-actions">
                    <form class="s180re-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="s180re_update_request_status">
                        <input type="hidden" name="request_id" value="<?php echo esc_attr($item->id); ?>">
                        <input type="hidden" name="return_url" value="<?php echo esc_url($return_url); ?>">
                        <?php wp_nonce_field('s180re_update_request_status'); ?>
                        <button class="button button-primary" type="submit" name="status" value="qualified"><?php esc_html_e('Approve', 'science180-book-review'); ?></button>
                        <button class="button" type="submit" name="status" value="declined"><?php esc_html_e('Reject', 'science180-book-review'); ?></button>
                        <button class="button" type="submit" name="status" value="sent"><?php esc_html_e('Mark paperback sent', 'science180-book-review'); ?></button>
                    </form>
                    <?php $request_book = $this->get_book((int) $item->book_id); ?>
                    <?php if ($request_book && (!empty($request_book->pdf_id) || !empty($request_book->pdf_url))) : ?>
                        <form class="s180re-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="s180br_send_pdf">
                            <input type="hidden" name="request_id" value="<?php echo esc_attr($item->id); ?>">
                            <input type="hidden" name="return_url" value="<?php echo esc_url($return_url); ?>">
                            <?php wp_nonce_field('s180br_send_pdf'); ?>
                            <button class="button button-primary" type="submit" name="delivery_mode" value="personalized"><?php esc_html_e('Send personalized PDF', 'science180-book-review'); ?></button>
                            <button class="button" type="submit" name="delivery_mode" value="original"><?php esc_html_e('Send original PDF', 'science180-book-review'); ?></button>
                        </form>
                    <?php else : ?>
                        <span class="s180re-status-note"><?php esc_html_e('Upload a PDF on the book page before sending.', 'science180-book-review'); ?></span>
                    <?php endif; ?>
                    <a class="button s180re-delete-button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=s180br_delete_request&request_id=' . (int) $item->id . '&return_url=' . rawurlencode($return_url)), 's180br_delete_request')); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this request?', 'science180-book-review')); ?>');"><?php esc_html_e('Delete', 'science180-book-review'); ?></a>
                </div>
            </div>
            <?php $this->render_delivery_table((int) $item->book_id, (int) $item->id); ?>
        </div>
        <?php
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'science180-book-review'));
        }
        ?>
        <div class="wrap s180re-admin">
            <h1><?php esc_html_e('Book Review Settings', 'science180-book-review'); ?></h1>
            <?php $this->render_admin_notice(); ?>
            <form class="s180re-admin-panel" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="s180br_save_settings">
                <?php wp_nonce_field('s180br_save_settings'); ?>
                <label><?php esc_html_e('Admin notification recipient email', 'science180-book-review'); ?></label>
                <input class="regular-text" type="email" name="recipient_email" value="<?php echo esc_attr($this->recipient_email()); ?>" required>
                <p class="description"><?php esc_html_e('Receives new review copy requests and daily pending-review notices.', 'science180-book-review'); ?></p>

                <label><?php esc_html_e('From name', 'science180-book-review'); ?></label>
                <input class="regular-text" type="text" name="from_name" value="<?php echo esc_attr(get_option('s180re_from_name')); ?>">

                <label><?php esc_html_e('From email override', 'science180-book-review'); ?></label>
                <input class="regular-text" type="email" name="from_email" value="<?php echo esc_attr(get_option('s180re_from_email')); ?>" placeholder="<?php echo esc_attr($this->sender_email()); ?>">
                <p class="description"><?php esc_html_e('Leave empty to use the existing Science180 Mail SMTP sender or the WordPress admin email. The plugin never stores SMTP passwords.', 'science180-book-review'); ?></p>

                <h2><?php esc_html_e('Daily pending-review notice', 'science180-book-review'); ?></h2>
                <p class="description">
                    <?php
                    $next = wp_next_scheduled('s180br_daily_review_notice');
                    echo $next ? esc_html(sprintf(__('Next scheduled run: %s', 'science180-book-review'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), $next))) : esc_html__('Daily notice is not scheduled yet; it will be scheduled automatically on the next plugin upgrade check.', 'science180-book-review');
                    ?>
                </p>
                <label><?php esc_html_e('Daily notice time', 'science180-book-review'); ?></label>
                <input type="time" name="daily_notice_hour" value="<?php echo esc_attr(get_option('s180br_daily_notice_hour', '09:00')); ?>">
                <p class="description"><?php esc_html_e('This uses the WordPress site timezone and sends one daily bulk notice for verified requests waiting for review.', 'science180-book-review'); ?></p>

                <label><?php esc_html_e('Daily notice subject', 'science180-book-review'); ?></label>
                <input class="large-text" type="text" name="daily_notice_subject" value="<?php echo esc_attr(get_option('s180br_daily_notice_subject')); ?>">

                <label><?php esc_html_e('Daily notice message', 'science180-book-review'); ?></label>
                <textarea class="large-text" name="daily_notice_intro" rows="4"><?php echo esc_textarea(get_option('s180br_daily_notice_intro')); ?></textarea>

                <h2><?php esc_html_e('Pending email verification reminder', 'science180-book-review'); ?></h2>
                <p class="description"><?php esc_html_e('An hourly cron checks pending applications and sends one reminder before the verification link expires after 48 hours.', 'science180-book-review'); ?></p>
                <label><?php esc_html_e('Send reminder after hours', 'science180-book-review'); ?></label>
                <input type="number" min="1" max="47" name="verification_reminder_hours" value="<?php echo esc_attr((int) get_option('s180br_verification_reminder_hours', 12)); ?>">

                <h2><?php esc_html_e('Letter sent after approval', 'science180-book-review'); ?></h2>
                <p class="description"><?php esc_html_e('Available placeholders: {first_name}, {full_name}, {book_title}, {site_name}.', 'science180-book-review'); ?></p>
                <label><?php esc_html_e('Approval email subject', 'science180-book-review'); ?></label>
                <input class="large-text" type="text" name="approval_subject" value="<?php echo esc_attr(get_option('s180br_approval_subject')); ?>">

                <label><?php esc_html_e('Approval email body', 'science180-book-review'); ?></label>
                <textarea class="large-text" name="approval_body" rows="8"><?php echo esc_textarea(get_option('s180br_approval_body')); ?></textarea>

                <h2><?php esc_html_e('Paperback sent message', 'science180-book-review'); ?></h2>
                <p class="description"><?php esc_html_e('Sent when the Mark paperback sent action is used. Available placeholders: {first_name}, {full_name}, {book_title}, {mailing_address}, {site_name}.', 'science180-book-review'); ?></p>
                <label><?php esc_html_e('Paperback sent subject', 'science180-book-review'); ?></label>
                <input class="large-text" type="text" name="paperback_subject" value="<?php echo esc_attr(get_option('s180br_paperback_subject')); ?>">

                <label><?php esc_html_e('Paperback sent message', 'science180-book-review'); ?></label>
                <textarea class="large-text" name="paperback_body" rows="8"><?php echo esc_textarea(get_option('s180br_paperback_body')); ?></textarea>

                <h2><?php esc_html_e('PDF delivery message', 'science180-book-review'); ?></h2>
                <p class="description"><?php esc_html_e('Available placeholders: {first_name}, {full_name}, {book_title}, {download_url}, {endorsement_url}, {site_name}. The email-open tracking pixel is added automatically.', 'science180-book-review'); ?></p>
                <label><?php esc_html_e('PDF delivery subject', 'science180-book-review'); ?></label>
                <input class="large-text" type="text" name="pdf_subject" value="<?php echo esc_attr(get_option('s180br_pdf_subject')); ?>">

                <label><?php esc_html_e('PDF delivery message', 'science180-book-review'); ?></label>
                <textarea class="large-text" name="pdf_body" rows="12"><?php echo esc_textarea(get_option('s180br_pdf_body')); ?></textarea>

                <h2><?php esc_html_e('Follow up message after sending the PDF', 'science180-book-review'); ?></h2>
                <p class="description"><?php esc_html_e('One reminder is sent automatically for each PDF delivery. Set days to 0 to disable reminders. Available placeholders: {first_name}, {full_name}, {book_title}, {days}, {endorsement_url}, {site_name}.', 'science180-book-review'); ?></p>
                <label><?php esc_html_e('Days after sending', 'science180-book-review'); ?></label>
                <input type="number" min="0" max="3650" name="followup_days" value="<?php echo esc_attr((int) get_option('s180br_followup_days', 30)); ?>">

                <label><?php esc_html_e('Follow-up subject', 'science180-book-review'); ?></label>
                <input class="large-text" type="text" name="followup_subject" value="<?php echo esc_attr(get_option('s180br_followup_subject')); ?>">

                <label><?php esc_html_e('Follow-up message', 'science180-book-review'); ?></label>
                <textarea class="large-text" name="followup_body" rows="10"><?php echo esc_textarea(get_option('s180br_followup_body')); ?></textarea>

                <p><button type="submit" class="button button-primary"><?php esc_html_e('Save settings', 'science180-book-review'); ?></button></p>
            </form>
        </div>
        <?php
    }

    public function handle_save_book()
    {
        $this->require_admin_post('s180re_save_book');
        global $wpdb;

        $book_id = isset($_POST['book_id']) ? absint($_POST['book_id']) : 0;
        $title = $this->post_text('title', true);
        if ($title === '') {
            $this->admin_redirect('s180br-books', 'book_missing');
        }

        $now = current_time('mysql');
        $data = array(
            'title' => $title,
            'slug' => sanitize_title($title),
            'description' => $this->post_textarea('description', false),
            'cover_id' => isset($_POST['cover_id']) ? absint($_POST['cover_id']) : 0,
            'cover_url' => esc_url_raw($this->post_raw('cover_url')),
            'pdf_id' => isset($_POST['pdf_id']) ? absint($_POST['pdf_id']) : 0,
            'pdf_url' => esc_url_raw($this->post_raw('pdf_url')),
            'margin_message' => $this->post_textarea('margin_message', false),
            'margin_color' => preg_match('/^#[0-9a-fA-F]{6}$/', $this->post_raw('margin_color')) ? $this->post_raw('margin_color') : '#7030A0',
            'margin_position' => in_array($this->post_text('margin_position', false), array('top', 'left', 'right'), true) ? $this->post_text('margin_position', false) : 'top',
            'margin_font_size' => min(24, max(5, isset($_POST['margin_font_size']) ? absint($_POST['margin_font_size']) : 7)),
            'footer_font_size' => min(24, max(5, isset($_POST['footer_font_size']) ? absint($_POST['footer_font_size']) : 8)),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 10,
            'updated_at' => $now,
        );

        if ($book_id > 0) {
            $wpdb->update($this->table('books'), $data, array('id' => $book_id));
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($this->table('books'), $data);
        }

        $this->admin_redirect('s180br-books', 'book_saved');
    }

    public function handle_delete_book()
    {
        $this->require_admin_get('s180br_delete_book');
        global $wpdb;
        $book_id = isset($_GET['book_id']) ? absint($_GET['book_id']) : 0;
        if ($book_id > 0) {
            $wpdb->delete($this->table('books'), array('id' => $book_id), array('%d'));
        }

        $this->admin_redirect('s180br-books', 'book_deleted');
    }

    public function handle_toggle_book()
    {
        $this->require_admin_get('s180re_toggle_book');
        global $wpdb;
        $book_id = isset($_GET['book_id']) ? absint($_GET['book_id']) : 0;
        $book = $this->get_book($book_id);
        if ($book) {
            $wpdb->update(
                $this->table('books'),
                array('is_active' => (int) $book->is_active === 1 ? 0 : 1, 'updated_at' => current_time('mysql')),
                array('id' => $book_id),
                array('%d', '%s'),
                array('%d')
            );
        }
        $this->admin_redirect('s180br-books', 'book_saved');
    }

    public function handle_update_request_status()
    {
        $this->require_admin_post('s180re_update_request_status');
        global $wpdb;
        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'new';
        $allowed = array_keys($this->review_request_statuses());
        if (!in_array($status, $allowed, true)) {
            $status = 'new';
        }

        $request = $this->get_review_request($request_id);
        if (!$request) {
            $this->admin_redirect('s180br-review-requests', 'request_missing');
        }

        $request_update = array('status' => $status, 'updated_at' => current_time('mysql'));
        if ($status === 'sent') {
            $request_update['delivery_type'] = 'paperback';
        } elseif ($request->status !== $status) {
            $request_update['delivery_type'] = '';
        }
        $wpdb->update($this->table('review_requests'), $request_update, array('id' => $request_id));

        $notice = 'request_updated';
        if ($request->status !== $status && in_array($status, array('reviewing', 'qualified', 'sent', 'declined'), true)) {
            $updated_request = $this->get_review_request($request_id);
            $notice = $updated_request && $this->send_review_request_status_email($updated_request, $status) ? 'request_updated_notified' : 'request_updated_email_failed';
        }

        $this->redirect_request_detail($request_id, $notice);
    }

    public function handle_send_pdf()
    {
        $this->require_admin_post('s180br_send_pdf');
        global $wpdb;

        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        $mode = isset($_POST['delivery_mode']) && sanitize_key($_POST['delivery_mode']) === 'original' ? 'original' : 'personalized';
        $request = $this->get_review_request($request_id);
        $book = $request ? $this->get_book((int) $request->book_id) : null;
        if (!$request || !$book) {
            $this->admin_redirect('s180br-review-requests', 'request_missing');
        }

        $source = $this->book_pdf_path($book);
        if (!$source) {
            $this->redirect_request_detail($request_id, 'pdf_missing');
        }

        try {
            $directory = $this->private_pdf_dir();
            $token = $this->generate_token();
            $filename = 'review-copy-' . (int) $request_id . '-' . wp_generate_password(12, false, false) . '.pdf';
            $destination = trailingslashit($directory) . $filename;
            if ($mode === 'original') {
                if (!copy($source, $destination)) {
                    throw new RuntimeException(__('The original PDF could not be copied.', 'science180-book-review'));
                }
            } else {
                S180BR_PDF::generate(
                    $source,
                    $destination,
                    array('name' => trim($request->first_name . ' ' . $request->last_name), 'email' => $request->email),
                    isset($book->margin_message) ? $book->margin_message : '',
                    isset($book->margin_color) ? $book->margin_color : '#7030A0',
                    isset($book->margin_position) ? $book->margin_position : 'top',
                    true,
                    isset($book->margin_font_size) ? (int) $book->margin_font_size : 7,
                    isset($book->footer_font_size) ? (int) $book->footer_font_size : 8
                );
            }

            $now = current_time('mysql');
            $delivery_table = $this->table('pdf_deliveries');
            $inserted = $wpdb->insert($delivery_table, array(
                'request_id' => $request_id,
                'book_id' => (int) $book->id,
                'token_hash' => hash('sha256', $token),
                'token_value' => $token,
                'personalized' => $mode === 'personalized' ? 1 : 0,
                'file_path' => $destination,
                'status' => 'sending',
                'created_at' => $now,
                'updated_at' => $now,
            ));
            if (!$inserted) {
                throw new RuntimeException(__('The delivery record could not be created.', 'science180-book-review'));
            }

            $delivery_id = (int) $wpdb->insert_id;
            $download_url = add_query_arg('s180br_pdf_download', rawurlencode($token), home_url('/'));
            $open_url = add_query_arg('s180br_pdf_open', rawurlencode($token), home_url('/'));
            $email_data = (array) $request;
            $email_data['download_url'] = '<a href="' . esc_url($download_url) . '">' . esc_html__('CLICK HERE TO DOWNLOAD THE BOOK', 'science180-book-review') . '</a>';
            $email_data['endorsement_url'] = '<a href="' . esc_url(home_url('/endorsement/')) . '">' . esc_html(home_url('/endorsement/')) . '</a>';
            $subject = $this->format_template(get_option('s180br_pdf_subject'), $email_data);
            $body = $this->format_template(get_option('s180br_pdf_body'), $email_data);
            $message = wpautop(wp_kses_post($body));
            $message .= '<img src="' . esc_url($open_url) . '" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px;">';

            if (!$this->send_mail($request->email, $subject, $message, $this->mail_headers())) {
                $wpdb->update($delivery_table, array('status' => 'email_failed', 'updated_at' => current_time('mysql')), array('id' => $delivery_id));
                $this->log_mail_failure('PDF delivery', $request->email);
                $this->redirect_request_detail($request_id, 'pdf_email_failed');
            }

            $wpdb->update($delivery_table, array('status' => 'sent', 'emailed_at' => current_time('mysql'), 'updated_at' => current_time('mysql')), array('id' => $delivery_id));
            $wpdb->query($wpdb->prepare("UPDATE {$delivery_table} SET status = 'revoked', updated_at = %s WHERE request_id = %d AND id <> %d AND downloaded_at IS NULL", current_time('mysql'), $request_id, $delivery_id));
            $wpdb->update($this->table('review_requests'), array('status' => 'sent', 'delivery_type' => $mode === 'original' ? 'original_pdf' : 'personalized_pdf', 'updated_at' => current_time('mysql')), array('id' => $request_id));
            $this->redirect_request_detail($request_id, 'pdf_sent');
        } catch (Throwable $error) {
            if (!empty($destination) && is_file($destination)) {
                @unlink($destination);
            }
            error_log('Science180 Book Review PDF error: ' . $error->getMessage());
            $this->redirect_request_detail($request_id, 'pdf_generation_failed');
        }
    }

    public function handle_pdf_open_route()
    {
        $token = get_query_var('s180br_pdf_open');
        if (!$token && isset($_GET['s180br_pdf_open'])) {
            $token = sanitize_text_field(wp_unslash($_GET['s180br_pdf_open']));
        }
        if (!$token) {
            return;
        }
        global $wpdb;
        $table = $this->table('pdf_deliveries');
        $delivery = $wpdb->get_row($wpdb->prepare("SELECT id, email_opened_at FROM {$table} WHERE token_hash = %s LIMIT 1", hash('sha256', $token)));
        if ($delivery && empty($delivery->email_opened_at)) {
            $ip = $this->client_ip();
            $geo = $this->ip_geolocation($ip);
            $wpdb->update($table, array(
                'email_opened_at' => current_time('mysql'),
                'open_ip_address' => $ip,
                'open_ip_city' => $geo['city'],
                'open_ip_country' => $geo['country'],
                'open_device_type' => $this->device_type(),
                'open_user_agent' => $this->user_agent(),
                'updated_at' => current_time('mysql'),
            ), array('id' => (int) $delivery->id));
        }
        nocache_headers();
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
        exit;
    }

    public function handle_pdf_download_route()
    {
        $token = get_query_var('s180br_pdf_download');
        if (!$token && isset($_GET['s180br_pdf_download'])) {
            $token = sanitize_text_field(wp_unslash($_GET['s180br_pdf_download']));
        }
        if (!$token) {
            return;
        }

        global $wpdb;
        $table = $this->table('pdf_deliveries');
        $delivery = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE token_hash = %s LIMIT 1", hash('sha256', $token)));
        if (!$delivery || !empty($delivery->downloaded_at) || $delivery->status === 'revoked') {
            status_header(410);
            wp_die(esc_html__('This private download link has expired or has already been used.', 'science180-book-review'), esc_html__('Download unavailable', 'science180-book-review'), array('response' => 410));
        }
        if (!$this->is_private_delivery_file($delivery->file_path) || !is_readable($delivery->file_path)) {
            status_header(404);
            wp_die(esc_html__('The requested PDF is unavailable. Please contact Science180.', 'science180-book-review'), esc_html__('PDF unavailable', 'science180-book-review'), array('response' => 404));
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['s180br_pdf_confirm'])) {
            $this->render_pdf_download_confirmation($delivery, $token);
        }

        $ip = $this->client_ip();
        $geo = $this->ip_geolocation($ip);
        $now = current_time('mysql');
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET downloaded_at = %s, status = 'downloaded', download_attempts = download_attempts + 1, ip_address = %s, ip_city = %s, ip_country = %s, device_type = %s, user_agent = %s, updated_at = %s WHERE id = %d AND downloaded_at IS NULL",
            $now, $ip, $geo['city'], $geo['country'], $this->device_type(), $this->user_agent(), $now, (int) $delivery->id
        ));
        if ($updated !== 1) {
            status_header(410);
            wp_die(esc_html__('This private download link has already been used.', 'science180-book-review'), esc_html__('Download unavailable', 'science180-book-review'), array('response' => 410));
        }

        $request = $this->get_review_request((int) $delivery->request_id);
        $download_name = sanitize_file_name(($request ? $request->book_title : 'science180-review-copy') . '.pdf');
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        header('Content-Length: ' . filesize($delivery->file_path));
        header('X-Content-Type-Options: nosniff');
        readfile($delivery->file_path);
        exit;
    }

    private function render_pdf_download_confirmation($delivery, $token)
    {
        $request = $this->get_review_request((int) $delivery->request_id);
        nocache_headers();
        get_header();
        ?>
        <main class="s180re-shell s180re-download-shell">
            <div class="s180re-public-heading">
                <p class="s180re-eyebrow"><?php esc_html_e('Private review copy', 'science180-book-review'); ?></p>
                <h1><?php echo esc_html($request && !empty($request->book_title) ? $request->book_title : __('Science180 review copy', 'science180-book-review')); ?></h1>
                <p class="s180re-book-note"><?php esc_html_e('Please confirm below to download your private PDF review copy.', 'science180-book-review'); ?></p>
            </div>
            <form class="s180re-form s180re-download-form" method="post">
                <input type="hidden" name="s180br_pdf_confirm" value="1">
                <button class="s180re-button" type="submit"><?php esc_html_e('CLICK HERE TO DOWNLOAD THE BOOK', 'science180-book-review'); ?></button>
            </form>
        </main>
        <?php
        get_footer();
        exit;
    }

    public function handle_delete_request()
    {
        $this->require_admin_get('s180br_delete_request');
        global $wpdb;
        $request_id = isset($_GET['request_id']) ? absint($_GET['request_id']) : 0;
        if ($request_id > 0) {
            $wpdb->delete($this->table('review_requests'), array('id' => $request_id), array('%d'));
        }

        wp_safe_redirect(add_query_arg('s180re_admin_status', 'request_deleted', $this->request_return_url()));
        exit;
    }

    public function handle_save_settings()
    {
        $this->require_admin_post('s180br_save_settings');
        $recipient = self::normalize_email_domain($this->post_raw('recipient_email'));
        if ($recipient && is_email($recipient)) {
            update_option('s180re_recipient_email', $recipient);
        }
        update_option('s180re_from_name', $this->post_text('from_name', false));
        $from_email = self::normalize_email_domain($this->post_raw('from_email'));
        update_option('s180re_from_email', $from_email && is_email($from_email) ? $from_email : '');
        $notice_hour = $this->post_text('daily_notice_hour', false);
        if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $notice_hour)) {
            $notice_hour = '09:00';
        }
        $previous_notice_hour = get_option('s180br_daily_notice_hour', '09:00');
        update_option('s180br_daily_notice_hour', $notice_hour);
        update_option('s180br_daily_notice_subject', $this->post_text('daily_notice_subject', false));
        update_option('s180br_daily_notice_intro', $this->post_textarea('daily_notice_intro', false));
        update_option('s180br_verification_reminder_hours', min(47, max(1, isset($_POST['verification_reminder_hours']) ? absint($_POST['verification_reminder_hours']) : 12)));
        update_option('s180br_approval_subject', $this->post_text('approval_subject', false));
        update_option('s180br_approval_body', $this->post_textarea('approval_body', false));
        update_option('s180br_paperback_subject', $this->post_text('paperback_subject', false));
        update_option('s180br_paperback_body', $this->post_textarea('paperback_body', false));
        update_option('s180br_pdf_subject', $this->post_text('pdf_subject', false));
        update_option('s180br_pdf_body', $this->post_textarea('pdf_body', false));
        update_option('s180br_followup_days', min(3650, max(0, isset($_POST['followup_days']) ? absint($_POST['followup_days']) : 30)));
        update_option('s180br_followup_subject', $this->post_text('followup_subject', false));
        update_option('s180br_followup_body', $this->post_textarea('followup_body', false));
        if ($notice_hour !== $previous_notice_hour || !wp_next_scheduled('s180br_daily_review_notice')) {
            self::reschedule_daily_notice();
        }
        $this->admin_redirect('s180br-settings', 'settings_saved');
    }

    public function send_pdf_followups()
    {
        $days = (int) get_option('s180br_followup_days', 30);
        if ($days < 1) {
            return;
        }

        global $wpdb;
        $deliveries = $this->table('pdf_deliveries');
        $requests = $this->table('review_requests');
        $cutoff = wp_date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id AS delivery_id, r.* FROM {$deliveries} d INNER JOIN {$requests} r ON r.id = d.request_id WHERE d.emailed_at IS NOT NULL AND d.emailed_at <= %s AND d.reminder_sent_at IS NULL AND d.status IN ('sent','downloaded') ORDER BY d.emailed_at ASC LIMIT 100",
            $cutoff
        ));

        foreach ($items as $item) {
            $data = (array) $item;
            $data['days'] = $days;
            $data['endorsement_url'] = '<a href="' . esc_url(home_url('/endorsement/')) . '">' . esc_html(home_url('/endorsement/')) . '</a>';
            $subject = $this->format_template(get_option('s180br_followup_subject'), $data);
            $body = wpautop(wp_kses_post($this->format_template(get_option('s180br_followup_body'), $data)));
            if ($this->send_mail($item->email, $subject, $body, $this->mail_headers())) {
                $wpdb->update($deliveries, array('reminder_sent_at' => current_time('mysql'), 'updated_at' => current_time('mysql')), array('id' => (int) $item->delivery_id));
            } else {
                $this->log_mail_failure('PDF follow-up', $item->email);
            }
        }
    }

    private function render_delivery_table($book_id, $request_id = 0)
    {
        global $wpdb;
        $deliveries = $this->table('pdf_deliveries');
        $requests = $this->table('review_requests');
        $where = 'd.book_id = %d';
        $params = array($book_id);
        if ($request_id > 0) {
            $where .= ' AND d.request_id = %d';
            $params[] = $request_id;
        }
        $sql = "SELECT d.*, r.first_name, r.last_name, r.email FROM {$deliveries} d INNER JOIN {$requests} r ON r.id = d.request_id WHERE {$where} ORDER BY d.created_at DESC LIMIT 500";
        $items = $wpdb->get_results($wpdb->prepare($sql, $params));
        ?>
        <div class="s180re-admin-panel s180re-delivery-report">
            <h2><?php esc_html_e('PDF delivery and download statistics', 'science180-book-review'); ?></h2>
            <?php if (!$items) : ?>
                <p><?php esc_html_e('No PDF links have been sent for this book yet.', 'science180-book-review'); ?></p>
            <?php else : ?>
                <div class="s180re-table-scroll">
                    <table class="widefat striped">
                        <thead><tr>
                            <th><?php esc_html_e('Recipient', 'science180-book-review'); ?></th>
                            <th><?php esc_html_e('Link', 'science180-book-review'); ?></th>
                            <th><?php esc_html_e('PDF type', 'science180-book-review'); ?></th>
                            <th><?php esc_html_e('PDF email sent', 'science180-book-review'); ?></th>
                            <th><?php esc_html_e('Viewed', 'science180-book-review'); ?></th>
                            <th><?php esc_html_e('Downloaded', 'science180-book-review'); ?></th>
                            <th><?php esc_html_e('Download IP', 'science180-book-review'); ?></th>
                            <th><?php esc_html_e('City', 'science180-book-review'); ?></th>
                            <th><?php esc_html_e('Country', 'science180-book-review'); ?></th>
                            <th><?php esc_html_e('Device / browser user agent', 'science180-book-review'); ?></th>
                            <th><?php esc_html_e('Follow-up', 'science180-book-review'); ?></th>
                            <th><?php esc_html_e('Action', 'science180-book-review'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($items as $delivery) : ?>
                            <?php $profile_url = admin_url('admin.php?page=s180br-review-requests&s180br_view=' . (int) $delivery->request_id); ?>
                            <?php $delivery_url = !empty($delivery->token_value) ? add_query_arg('s180br_pdf_download', rawurlencode($delivery->token_value), home_url('/')) : ''; ?>
                            <tr>
                                <td><a href="<?php echo esc_url($profile_url); ?>"><?php echo esc_html(trim($delivery->first_name . ' ' . $delivery->last_name)); ?></a><br><a href="<?php echo esc_url($profile_url); ?>"><?php echo esc_html($delivery->email); ?></a></td>
                                <td class="s180br-delivery-link">
                                    <?php if ($delivery_url !== '') : ?>
                                        <input class="regular-text s180br-copy-source" type="text" value="<?php echo esc_attr($delivery_url); ?>" readonly>
                                        <button class="button-link s180br-copy-link" type="button"><?php esc_html_e('Copy', 'science180-book-review'); ?></button>
                                    <?php else : ?>
                                        <span class="s180re-status-note"><?php esc_html_e('Historical link unavailable', 'science180-book-review'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int) $delivery->personalized === 1 ? esc_html__('Personalized', 'science180-book-review') : esc_html__('Original', 'science180-book-review'); ?></td>
                                <td><?php echo esc_html($delivery->emailed_at ?: __('Email failed', 'science180-book-review')); ?></td>
                                <td><span class="s180br-state <?php echo $delivery->email_opened_at ? 'is-complete' : 'is-pending'; ?>" aria-label="<?php echo esc_attr($delivery->email_opened_at ? __('Viewed', 'science180-book-review') : __('Not viewed', 'science180-book-review')); ?>"><?php echo $delivery->email_opened_at ? '&#10003;' : '&mdash;'; ?></span><?php if ($delivery->email_opened_at) : ?><br><small><?php echo esc_html($delivery->email_opened_at); ?><br><?php echo esc_html(trim((string) $delivery->open_ip_address . ' ' . (string) $delivery->open_ip_city . ' ' . (string) $delivery->open_ip_country . ' ' . (string) $delivery->open_device_type)); ?></small><?php endif; ?></td>
                                <td><span class="s180br-state <?php echo $delivery->downloaded_at ? 'is-complete' : 'is-pending'; ?>" aria-label="<?php echo esc_attr($delivery->downloaded_at ? __('Downloaded', 'science180-book-review') : __('Not downloaded', 'science180-book-review')); ?>"><?php echo $delivery->downloaded_at ? '&#10003;' : '&mdash;'; ?></span><?php if ($delivery->downloaded_at) : ?><br><small><?php echo esc_html($delivery->downloaded_at); ?></small><?php endif; ?></td>
                                <td><?php echo esc_html($delivery->ip_address ?: __('Not available', 'science180-book-review')); ?></td>
                                <td><?php echo esc_html($delivery->ip_city ?: __('Unknown', 'science180-book-review')); ?></td>
                                <td><?php echo esc_html($delivery->ip_country ?: __('Unknown', 'science180-book-review')); ?></td>
                                <td><?php echo esc_html(trim((string) $delivery->device_type . ' ' . (string) $delivery->user_agent) ?: __('Not available', 'science180-book-review')); ?></td>
                                <td><?php echo esc_html($delivery->reminder_sent_at ?: __('Pending', 'science180-book-review')); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="s180br_send_pdf">
                                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $delivery->request_id); ?>">
                                        <input type="hidden" name="delivery_mode" value="<?php echo (int) $delivery->personalized === 1 ? 'personalized' : 'original'; ?>">
                                        <?php wp_nonce_field('s180br_send_pdf'); ?>
                                        <button class="button" type="submit"><?php esc_html_e('Resend', 'science180-book-review'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function book_pdf_path($book)
    {
        if (!empty($book->pdf_id)) {
            $path = get_attached_file((int) $book->pdf_id);
            if ($path && is_readable($path)) {
                return $path;
            }
        }
        if (!empty($book->pdf_url)) {
            $uploads = wp_upload_dir();
            if (strpos($book->pdf_url, $uploads['baseurl']) === 0) {
                $relative = ltrim(substr($book->pdf_url, strlen($uploads['baseurl'])), '/');
                $candidate = wp_normalize_path(trailingslashit($uploads['basedir']) . $relative);
                if (is_readable($candidate)) {
                    return $candidate;
                }
            }
        }
        return '';
    }

    private function private_pdf_dir()
    {
        $uploads = wp_upload_dir();
        $directory = trailingslashit($uploads['basedir']) . 's180br-private';
        if (!wp_mkdir_p($directory)) {
            throw new RuntimeException(__('The private PDF directory could not be created.', 'science180-book-review'));
        }
        if (!file_exists(trailingslashit($directory) . '.htaccess')) {
            file_put_contents(trailingslashit($directory) . '.htaccess', "Require all denied\nDeny from all\n");
        }
        if (!file_exists(trailingslashit($directory) . 'index.php')) {
            file_put_contents(trailingslashit($directory) . 'index.php', "<?php\n// Silence is golden.\n");
        }
        return $directory;
    }

    private function is_private_delivery_file($path)
    {
        $uploads = wp_upload_dir();
        $base = wp_normalize_path(trailingslashit($uploads['basedir']) . 's180br-private/');
        $candidate = wp_normalize_path((string) $path);
        return strpos($candidate, $base) === 0 && strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function redirect_request_detail($request_id, $notice)
    {
        $args = array(
            'page' => 's180br-review-requests',
            's180br_view' => absint($request_id),
            's180re_admin_status' => $notice,
        );
        $return_url = $this->request_return_url();
        if ($return_url !== $this->review_requests_base_url()) {
            $args['return_url'] = $return_url;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function review_requests_base_url()
    {
        return admin_url('admin.php?page=s180br-review-requests');
    }

    private function current_review_requests_url()
    {
        $args = array('page' => 's180br-review-requests');
        foreach (array('status', 'book_id', 'date_from', 'date_to', 's') as $key) {
            if (!isset($_GET[$key]) || $_GET[$key] === '') {
                continue;
            }
            $args[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    private function request_return_url()
    {
        $fallback = $this->review_requests_base_url();
        if (isset($_REQUEST['return_url']) && $_REQUEST['return_url'] !== '') {
            return wp_validate_redirect(esc_url_raw(wp_unslash($_REQUEST['return_url'])), $fallback);
        }

        return $fallback;
    }

    private function require_admin_post($nonce_action)
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'science180-book-review'));
        }

        check_admin_referer($nonce_action);
    }

    private function require_admin_get($nonce_action)
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'science180-book-review'));
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

    private function redirect_to_review_page($status)
    {
        wp_safe_redirect(add_query_arg('s180re_status', rawurlencode($status), $this->review_request_page_url()));
        exit;
    }

    private function generate_token()
    {
        return bin2hex(random_bytes(24));
    }

    private function pending_token_hash($token)
    {
        return hash('sha256', $token . wp_salt('auth'));
    }

    private function pending_review_dir()
    {
        $uploads = wp_upload_dir(null, false);
        return trailingslashit($uploads['basedir']) . 'science180-review-requests';
    }

    private function pending_review_path($token)
    {
        return trailingslashit($this->pending_review_dir()) . $this->pending_token_hash($token) . '.json';
    }

    private function save_pending_review($token, $data)
    {
        $dir = $this->pending_review_dir();
        if (!wp_mkdir_p($dir)) {
            return false;
        }

        if (!file_exists(trailingslashit($dir) . '.htaccess')) {
            file_put_contents(trailingslashit($dir) . '.htaccess', "Require all denied\nDeny from all\n");
        }
        if (!file_exists(trailingslashit($dir) . 'index.php')) {
            file_put_contents(trailingslashit($dir) . 'index.php', "<?php\n// Silence is golden.\n");
        }

        $payload = array(
            'expires' => time() + (2 * DAY_IN_SECONDS),
            'created_at' => time(),
            'reminder_sent_at' => 0,
            'token' => $token,
            'data' => $data,
        );

        return (bool) file_put_contents($this->pending_review_path($token), wp_json_encode($payload), LOCK_EX);
    }

    private function load_pending_review($token)
    {
        $path = $this->pending_review_path($token);
        if (!is_readable($path)) {
            return false;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload) || empty($payload['data']) || empty($payload['expires'])) {
            return false;
        }

        if ((int) $payload['expires'] < time()) {
            $this->delete_pending_review($token);
            return false;
        }

        return is_array($payload['data']) ? $payload['data'] : false;
    }

    private function delete_pending_review($token)
    {
        $path = $this->pending_review_path($token);
        if (is_file($path)) {
            wp_delete_file($path);
        }
    }

    private function render_public_notice($context)
    {
        if (empty($_GET['s180re_status'])) {
            return;
        }

        $status = sanitize_key($_GET['s180re_status']);
        $messages = array(
            'review_success' => array('success', __('Your review copy request was submitted successfully.', 'science180-book-review')),
            'review_check_email' => array('success', __('Please, go to your email inbox to verify your email before we can submit your request. This step helps us fight spams and better serve you.', 'science180-book-review')),
            'review_verified' => array('success', __('Your email has been verified and your review copy request has been submitted for review.', 'science180-book-review')),
            'review_email_failed' => array('warning', __('The verification email could not be sent. Please try again later or contact the site owner.', 'science180-book-review')),
            'review_verify_invalid' => array('warning', __('This verification link is invalid or has expired. Please submit the request again.', 'science180-book-review')),
            'review_duplicate' => array('warning', __('This email has already requested this book.', 'science180-book-review')),
            'review_invalid' => array('warning', __('The form could not be submitted. Please try again.', 'science180-book-review')),
            'review_invalid_book' => array('warning', __('Please choose an available book.', 'science180-book-review')),
            'review_invalid_email' => array('warning', __('Please enter a valid email address.', 'science180-book-review')),
            'review_missing' => array('warning', __('Please complete all required fields.', 'science180-book-review')),
            'review_error' => array('warning', __('The request could not be saved. Please try again.', 'science180-book-review')),
        );

        if (!isset($messages[$status])) {
            return;
        }

        if ($context === 'review' && strpos($status, 'review_') !== 0) {
            return;
        }

        echo '<div class="s180re-message s180re-message-' . esc_attr($messages[$status][0]) . ' s180re-message-' . esc_attr($status) . '">' . esc_html($messages[$status][1]) . '</div>';
    }

    private function render_admin_notice()
    {
        if (empty($_GET['s180re_admin_status'])) {
            return;
        }

        $status = sanitize_key($_GET['s180re_admin_status']);
        $messages = array(
            'book_saved' => __('Book saved.', 'science180-book-review'),
            'book_deleted' => __('Book deleted.', 'science180-book-review'),
            'book_missing' => __('Book title is required.', 'science180-book-review'),
            'request_updated' => __('Request status updated.', 'science180-book-review'),
            'request_updated_notified' => __('Request status updated and the applicant was notified.', 'science180-book-review'),
            'request_updated_email_failed' => __('Request status updated, but the applicant email could not be sent.', 'science180-book-review'),
            'request_missing' => __('Review copy request not found.', 'science180-book-review'),
            'request_deleted' => __('Review copy request deleted.', 'science180-book-review'),
            'settings_saved' => __('Settings saved.', 'science180-book-review'),
            'pdf_sent' => __('The private one-time PDF link was emailed successfully.', 'science180-book-review'),
            'pdf_missing' => __('This book does not have a readable PDF. Upload it on the book page first.', 'science180-book-review'),
            'pdf_generation_failed' => __('The PDF could not be generated. Check the server error log for details.', 'science180-book-review'),
            'pdf_email_failed' => __('The PDF was prepared, but its email could not be sent.', 'science180-book-review'),
        );

        if (isset($messages[$status])) {
            $notice_class = in_array($status, array('book_missing', 'request_missing', 'request_updated_email_failed', 'pdf_missing', 'pdf_generation_failed', 'pdf_email_failed'), true) ? 'notice-warning' : 'notice-success';
            echo '<div class="notice ' . esc_attr($notice_class) . '"><p>' . esc_html($messages[$status]) . '</p></div>';
        }
    }

    private function get_books($active_only)
    {
        global $wpdb;
        $table = $this->table('books');
        if ($active_only) {
            return $wpdb->get_results("SELECT * FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC, title ASC");
        }

        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY sort_order ASC, title ASC");
    }

    private function get_book($book_id)
    {
        global $wpdb;
        $table = $this->table('books');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $book_id));
    }

    private function get_book_by_slug($slug)
    {
        global $wpdb;
        $table = $this->table('books');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s AND is_active = 1", sanitize_title($slug)));
    }

    private function get_book_for_public_selection($book_id)
    {
        $book = $this->get_book($book_id);
        if (!$book) {
            return null;
        }

        if ((int) $book->is_active === 1 || current_user_can('manage_options')) {
            return $book;
        }

        return null;
    }

    private function get_review_request($request_id)
    {
        global $wpdb;
        $table = $this->table('review_requests');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $request_id));
    }

    private function book_cover_url($book)
    {
        if (!$book) {
            return '';
        }

        if (!empty($book->cover_id)) {
            $url = wp_get_attachment_image_url((int) $book->cover_id, 'large');
            if ($url) {
                return $url;
            }
        }

        return !empty($book->cover_url) ? $book->cover_url : '';
    }

    private function review_request_page_url()
    {
        $page_id = (int) get_option('s180re_review_page_id');
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        return home_url('/BookReviewRequest/');
    }

    private function book_review_url($book)
    {
        if (!$book) {
            return $this->review_request_page_url();
        }

        $slug = !empty($book->slug) ? sanitize_title($book->slug) : '';
        if ($slug === '' && !empty($book->book_id)) {
            $stored_book = $this->get_book((int) $book->book_id);
            $slug = $stored_book && !empty($stored_book->slug) ? sanitize_title($stored_book->slug) : '';
        }
        if ($slug === '' && !empty($book->book_title)) {
            $slug = sanitize_title($book->book_title);
        }
        if ($slug === '' && !empty($book->title)) {
            $slug = sanitize_title($book->title);
        }

        return $slug !== '' ? home_url('/BookReviewRequest/' . $slug . '/') : $this->review_request_page_url();
    }

    private function review_request_labels()
    {
        return array(
            'book_title' => 'Book requested',
            'email' => 'Email',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'organization' => 'Organization / publication',
            'reviewer_role' => 'Reviewer role / title',
            'website' => 'Website / reviewer profile',
            'phone' => 'Phone',
            'address_line1' => 'Address line 1',
            'address_line2' => 'Address line 2',
            'city' => 'City',
            'state_region' => 'State / region',
            'postal_code' => 'Postal code',
            'country' => 'Country',
            'qualifications' => 'Professional review qualifications',
            'audience' => 'Audience / review outlet',
            'message' => 'Additional message',
            'ip_address' => 'IP address',
            'ip_city' => 'IP city',
            'ip_country' => 'IP country',
            'device_type' => 'Device type',
            'user_agent' => 'Device / browser user agent',
            'verified_at' => 'Email verified at',
            'created_at' => 'Submitted at',
            'delivery_type' => 'Delivery method',
            'status' => 'Status',
        );
    }

    private function review_request_statuses()
    {
        return array(
            'email_verified' => __('Needs review', 'science180-book-review'),
            'new' => __('New', 'science180-book-review'),
            'reviewing' => __('Reviewing', 'science180-book-review'),
            'qualified' => __('Accepted', 'science180-book-review'),
            'sent' => __('Sent', 'science180-book-review'),
            'declined' => __('Rejected', 'science180-book-review'),
        );
    }

    private function review_request_status_label($status, $request = null)
    {
        $labels = $this->review_request_statuses();

        $status = sanitize_key($status);
        if ($status === 'sent' && $request && !empty($request->delivery_type)) {
            return sprintf(__('Sent: %s', 'science180-book-review'), $this->delivery_type_label($request->delivery_type));
        }

        return isset($labels[$status]) ? $labels[$status] : ucwords(str_replace('_', ' ', $status));
    }

    private function delivery_type_label($delivery_type)
    {
        $labels = array(
            'paperback' => __('Mark paperback sent', 'science180-book-review'),
            'personalized_pdf' => __('Send personalized PDF', 'science180-book-review'),
            'original_pdf' => __('Send original PDF', 'science180-book-review'),
        );
        $delivery_type = sanitize_key($delivery_type);

        return isset($labels[$delivery_type]) ? $labels[$delivery_type] : '';
    }

    private function review_request_field_value($key, $value, $request)
    {
        if ($key === 'status') {
            return $this->review_request_status_label($value, $request);
        }
        if ($key === 'delivery_type') {
            return $this->delivery_type_label($value);
        }

        return $value;
    }

    private function format_mailing_address($data)
    {
        $data = wp_parse_args((array) $data, array(
            'first_name' => '',
            'last_name' => '',
            'organization' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state_region' => '',
            'postal_code' => '',
            'country' => '',
            'phone' => '',
        ));
        $lines = array();
        $name = trim($data['first_name'] . ' ' . $data['last_name']);
        if ($name !== '') {
            $lines[] = $name;
        }
        if (!empty($data['organization'])) {
            $lines[] = $data['organization'];
        }
        $lines[] = $data['address_line1'];
        if (!empty($data['address_line2'])) {
            $lines[] = $data['address_line2'];
        }

        $city_line = trim($data['city'] . (!empty($data['state_region']) ? ', ' . $data['state_region'] : '') . ' ' . $data['postal_code']);
        $lines[] = $city_line;
        $lines[] = $data['country'];
        if (!empty($data['phone'])) {
            $lines[] = 'Phone: ' . $data['phone'];
        }

        return implode("\n", array_filter($lines));
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
        $email = self::normalize_email_domain($this->post_raw($key));
        return ($email && is_email($email)) ? strtolower($email) : '';
    }


    private function client_ip()
    {
        $candidates = array();
        foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP') as $key) {
            if (!empty($_SERVER[$key])) {
                $candidates[] = wp_unslash($_SERVER[$key]);
            }
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
            $candidates = array_merge($candidates, $forwarded);
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = wp_unslash($_SERVER['REMOTE_ADDR']);
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return '';
    }

    private function device_type()
    {
        return $this->device_type_from_agent($this->user_agent());
    }

    private function device_type_from_agent($user_agent)
    {
        $agent = strtolower((string) $user_agent);
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

    private function backfill_request_telemetry($request)
    {
        global $wpdb;
        $updates = array();
        if (!empty($request->ip_address) && (empty($request->ip_city) || empty($request->ip_country))) {
            $geo = $this->ip_geolocation($request->ip_address);
            if (empty($request->ip_city) && !empty($geo['city'])) {
                $updates['ip_city'] = $geo['city'];
            }
            if (empty($request->ip_country) && !empty($geo['country'])) {
                $updates['ip_country'] = $geo['country'];
            }
        }
        if (empty($request->device_type) && !empty($request->user_agent)) {
            $updates['device_type'] = $this->device_type_from_agent($request->user_agent);
        }
        if (!$updates) {
            return false;
        }
        $updates['updated_at'] = current_time('mysql');
        return $wpdb->update($this->table('review_requests'), $updates, array('id' => (int) $request->id)) !== false;
    }

    private function ip_geolocation($ip)
    {
        $location = array(
            'city' => '',
            'country' => '',
        );

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

        if (($location['city'] === '' || $location['country'] === '') && class_exists('AdvNews_Geolocation') && defined('ADVNEWS_TABLE_PREFIX')) {
            $advnews_location = (new AdvNews_Geolocation())->get_location($ip);
            if (is_array($advnews_location)) {
                $location['city'] = $location['city'] ?: sanitize_text_field($advnews_location['city'] ?? '');
                $location['country'] = $location['country'] ?: sanitize_text_field($advnews_location['country'] ?? '');
            }
        }

        return $location;
    }

    private function ip_hash()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        return $ip ? hash('sha256', $ip . wp_salt('auth')) : '';
    }

    private function user_agent()
    {
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        return substr($agent, 0, 255);
    }

    private function country_select($name, $id, $selected, $required)
    {
        $html = '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . ($required ? ' required' : '') . '>';
        $html .= '<option value="">' . esc_html__('Select country', 'science180-book-review') . '</option>';
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
