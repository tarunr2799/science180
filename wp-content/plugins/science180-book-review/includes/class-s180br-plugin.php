<?php
if (!defined('ABSPATH')) {
    exit;
}

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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('template_redirect', array($this, 'render_shortcode_page_fallback'), 20);

        add_shortcode('science180_review_request', array($this, 'render_review_request_shortcode'));

        add_action('admin_post_nopriv_s180re_review_request', array($this, 'handle_review_request_submission'));
        add_action('admin_post_s180re_review_request', array($this, 'handle_review_request_submission'));

        add_action('admin_post_s180re_save_book', array($this, 'handle_save_book'));
        add_action('admin_post_s180re_toggle_book', array($this, 'handle_toggle_book'));
        add_action('admin_post_s180re_update_request_status', array($this, 'handle_update_request_status'));
        add_action('admin_post_s180br_save_settings', array($this, 'handle_save_settings'));
    }

    public static function activate()
    {
        self::create_tables();
        self::seed_options();
        self::seed_default_books();
        self::maybe_create_pages();
        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        flush_rewrite_rules();
    }

    private static function create_tables()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $books = self::table_static('books');
        $requests = self::table_static('review_requests');

        $sql_books = "CREATE TABLE {$books} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            slug varchar(200) NOT NULL,
            description text NULL,
            cover_id bigint(20) unsigned DEFAULT 0,
            cover_url text NULL,
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
            postal_code varchar(80) NOT NULL,
            country varchar(120) NOT NULL,
            qualifications text NULL,
            audience text NULL,
            message text NULL,
            status varchar(40) NOT NULL DEFAULT 'new',
            ip_hash varchar(64) DEFAULT '',
            user_agent varchar(255) DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY book_email (book_id,email),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        dbDelta($sql_books);
        dbDelta($sql_requests);
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
        add_option('s180re_recipient_email', get_option('admin_email'));
        add_option('s180re_from_name', get_bloginfo('name'));
        add_option('s180re_from_email', '');
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
                    'is_active' => 1,
                    'sort_order' => ($index + 1) * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s')
            );
        }
    }

    private static function maybe_create_pages()
    {
        self::create_page_if_missing(
            'review-copy-request',
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

    public function render_review_request_shortcode()
    {
        $books = $this->get_books(true);
        $selected = !empty($books) ? $books[0] : null;

        ob_start();
        $this->render_public_notice('review');
        ?>
        <section class="s180re-shell s180re-review-shell" data-s180re-review>
            <div class="s180re-public-heading">
                <p class="s180re-eyebrow"><?php esc_html_e('Professional reviewer request', 'science180-book-review'); ?></p>
                <h1><?php esc_html_e("Review Copy Request for Dr. Nathanael-Israel Israel's Book(s)", 'science180-book-review'); ?></h1>
                <div class="s180re-page-actions">
                    <a class="s180re-link-button s180re-link-button-secondary" href="<?php echo esc_url($this->endorsement_page_url()); ?>"><?php esc_html_e('View approved endorsements', 'science180-book-review'); ?></a>
                    <a class="s180re-link-button" href="<?php echo esc_url($this->endorsement_page_url()); ?>#s180re-endorsement-form"><?php esc_html_e('Share an endorsement', 'science180-book-review'); ?></a>
                </div>
            </div>

            <?php if (empty($books)) : ?>
                <div class="s180re-message s180re-message-warning"><?php esc_html_e('No books are available for review requests yet.', 'science180-book-review'); ?></div>
            <?php else : ?>
                <div class="s180re-book-strip" role="radiogroup" aria-label="<?php esc_attr_e('Choose one book', 'science180-book-review'); ?>">
                    <?php foreach ($books as $index => $book) : ?>
                        <label class="s180re-book-choice<?php echo $index === 0 ? ' is-selected' : ''; ?>">
                            <input type="radio" name="book_choice_preview" value="<?php echo esc_attr($book->id); ?>" data-cover="<?php echo esc_url($this->book_cover_url($book)); ?>" data-title="<?php echo esc_attr($book->title); ?>" <?php checked($index, 0); ?>>
                            <span class="s180re-book-cover-wrap">
                                <?php if ($this->book_cover_url($book)) : ?>
                                    <img src="<?php echo esc_url($this->book_cover_url($book)); ?>" alt="<?php echo esc_attr($book->title); ?>">
                                <?php else : ?>
                                    <span class="s180re-cover-placeholder"><?php esc_html_e('Cover', 'science180-book-review'); ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="s180re-book-title"><?php echo esc_html($book->title); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

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
                                    <option value="<?php echo esc_attr($book->id); ?>" data-cover="<?php echo esc_url($this->book_cover_url($book)); ?>" data-title="<?php echo esc_attr($book->title); ?>" <?php selected($index, 0); ?>><?php echo esc_html($book->title); ?></option>
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
                            <input id="s180re-review-website" type="url" name="website" placeholder="https://">
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
                                <label for="s180re-review-postal"><?php esc_html_e('Postal code', 'science180-book-review'); ?> <span>*</span></label>
                                <input id="s180re-review-postal" type="text" name="postal_code" required autocomplete="postal-code">
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
                    </aside>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
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
            'website' => esc_url_raw($this->post_raw('website')),
            'phone' => $this->post_text('phone', false),
            'address_line1' => $this->post_text('address_line1', true),
            'address_line2' => $this->post_text('address_line2', false),
            'city' => $this->post_text('city', true),
            'state_region' => $this->post_text('state_region', false),
            'postal_code' => $this->post_text('postal_code', true),
            'country' => $this->post_text('country', true),
            'qualifications' => $this->post_textarea('qualifications', true),
            'audience' => $this->post_textarea('audience', false),
            'message' => $this->post_textarea('message', false),
            'status' => 'new',
            'ip_hash' => $this->ip_hash(),
            'user_agent' => $this->user_agent(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        if ($this->has_empty_required($data, array('first_name', 'last_name', 'address_line1', 'city', 'postal_code', 'country', 'qualifications'))) {
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

        $inserted = $wpdb->insert(
            $this->table('review_requests'),
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$inserted) {
            $this->redirect_back('review_error');
        }

        $request_id = (int) $wpdb->insert_id;
        $this->send_review_request_email($request_id, $data, $book);
        $this->redirect_back('review_success');
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
        $is_review_page = ($review_page_id > 0 && is_page($review_page_id)) || is_page('review-copy-request');

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

    private function send_review_request_email($request_id, $data, $book)
    {
        $address = $this->format_mailing_address($data);
        $raw_rows = '';
        foreach ($this->review_request_labels() as $key => $label) {
            if (!isset($data[$key])) {
                continue;
            }
            $raw_rows .= '<tr><th align="left" style="padding:8px;border-bottom:1px solid #ddd;">' . esc_html($label) . '</th><td style="padding:8px;border-bottom:1px solid #ddd;">' . nl2br(esc_html($data[$key])) . '</td></tr>';
        }

        $message = '<h2>Mailing address ready to copy and print</h2>';
        $message .= '<pre style="font:16px/1.5 monospace;background:#f6f7f7;border:1px solid #ddd;padding:16px;white-space:pre-wrap;">' . esc_html($address) . '</pre>';
        $message .= '<h2>Raw form data</h2>';
        $message .= '<table style="border-collapse:collapse;width:100%;">' . $raw_rows . '</table>';
        $message .= '<p>Request ID: ' . esc_html($request_id) . '</p>';

        $subject = sprintf('Review copy request: %s', $book->title);
        wp_mail(
            $this->recipient_email(),
            $subject,
            $message,
            $this->mail_headers($data['email'], trim($data['first_name'] . ' ' . $data['last_name']))
        );
    }

    private function send_review_request_status_email($request, $status)
    {
        $status_label = $this->review_request_status_label($status);
        $first_name = !empty($request->first_name) ? $request->first_name : __('there', 'science180-book-review');
        $subject = sprintf('Your Science180 review copy request is %s', strtolower($status_label));
        $message = '<p>' . sprintf(esc_html__('Hello %s,', 'science180-book-review'), esc_html($first_name)) . '</p>';

        if ($status === 'qualified') {
            $subject = 'Your Science180 review copy request was approved';
            $message .= '<p>' . esc_html__('Your review copy request has been approved.', 'science180-book-review') . '</p>';
        } elseif ($status === 'declined') {
            $subject = 'Your Science180 review copy request was reviewed';
            $message .= '<p>' . esc_html__('Thank you for your interest. After review, your request was not approved at this time.', 'science180-book-review') . '</p>';
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

        $sent = wp_mail($request->email, $subject, $message, $this->mail_headers());
        if (!$sent) {
            $this->log_mail_failure('review request status', $request->email);
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
            $candidate = sanitize_email($candidate);
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
        $email = sanitize_email(get_option('s180re_recipient_email'));
        if (!$email || !is_email($email)) {
            $email = sanitize_email(get_option('admin_email'));
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

                    <label><?php esc_html_e('Sort order', 'science180-book-review'); ?></label>
                    <input type="number" name="sort_order" value="<?php echo esc_attr($book ? (int) $book->sort_order : 10); ?>">

                    <label class="s180re-check"><input type="checkbox" name="is_active" value="1" <?php checked(!$book || (int) $book->is_active === 1); ?>> <?php esc_html_e('Available on public form', 'science180-book-review'); ?></label>

                    <p><button type="submit" class="button button-primary"><?php esc_html_e('Save book', 'science180-book-review'); ?></button></p>
                </form>

                <div class="s180re-admin-panel s180re-admin-panel-wide">
                    <h2><?php esc_html_e('Current books', 'science180-book-review'); ?></h2>
                    <table class="widefat striped">
                        <thead><tr><th><?php esc_html_e('Cover', 'science180-book-review'); ?></th><th><?php esc_html_e('Title', 'science180-book-review'); ?></th><th><?php esc_html_e('Status', 'science180-book-review'); ?></th><th><?php esc_html_e('Actions', 'science180-book-review'); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($books as $item) : ?>
                                <tr>
                                    <td class="s180re-table-cover"><?php if ($this->book_cover_url($item)) : ?><img src="<?php echo esc_url($this->book_cover_url($item)); ?>" alt=""><?php endif; ?></td>
                                    <td><?php echo esc_html($item->title); ?></td>
                                    <td><?php echo (int) $item->is_active === 1 ? esc_html__('Active', 'science180-book-review') : esc_html__('Hidden', 'science180-book-review'); ?></td>
                                    <td>
                                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=s180br-books&edit=' . (int) $item->id)); ?>"><?php esc_html_e('Edit', 'science180-book-review'); ?></a>
                                        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=s180re_toggle_book&book_id=' . (int) $item->id), 's180re_toggle_book')); ?>"><?php echo (int) $item->is_active === 1 ? esc_html__('Hide', 'science180-book-review') : esc_html__('Show', 'science180-book-review'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_review_requests_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'science180-book-review'));
        }

        global $wpdb;
        $view_id = isset($_GET['view']) ? absint($_GET['view']) : 0;
        if ($view_id) {
            $this->render_review_request_detail($view_id);
            return;
        }

        $table = $this->table('review_requests');
        $items = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200");
        ?>
        <div class="wrap s180re-admin">
            <h1><?php esc_html_e('Review Copy Requests', 'science180-book-review'); ?></h1>
            <?php $this->render_admin_notice(); ?>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Date', 'science180-book-review'); ?></th><th><?php esc_html_e('Book', 'science180-book-review'); ?></th><th><?php esc_html_e('Applicant', 'science180-book-review'); ?></th><th><?php esc_html_e('Status', 'science180-book-review'); ?></th><th><?php esc_html_e('Actions', 'science180-book-review'); ?></th></tr></thead>
                <tbody>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="5"><?php esc_html_e('No review copy requests yet.', 'science180-book-review'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item->created_at); ?></td>
                            <td><?php echo esc_html($item->book_title); ?></td>
                            <td><?php echo esc_html($item->first_name . ' ' . $item->last_name); ?><br><a href="mailto:<?php echo esc_attr($item->email); ?>"><?php echo esc_html($item->email); ?></a></td>
                            <td><?php echo esc_html($this->review_request_status_label($item->status)); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=s180br-review-requests&view=' . (int) $item->id)); ?>"><?php esc_html_e('View', 'science180-book-review'); ?></a>
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

        $data = (array) $item;
        ?>
        <div class="wrap s180re-admin">
            <h1><?php esc_html_e('Review Copy Request Details', 'science180-book-review'); ?></h1>
            <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=s180br-review-requests')); ?>"><?php esc_html_e('Back to requests', 'science180-book-review'); ?></a></p>

            <div class="s180re-admin-layout">
                <div class="s180re-admin-panel">
                    <h2><?php esc_html_e('Clean address', 'science180-book-review'); ?></h2>
                    <pre class="s180re-address-block"><?php echo esc_html($this->format_mailing_address($data)); ?></pre>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="s180re_update_request_status">
                        <input type="hidden" name="request_id" value="<?php echo esc_attr($item->id); ?>">
                        <?php wp_nonce_field('s180re_update_request_status'); ?>
                        <label><?php esc_html_e('Status', 'science180-book-review'); ?></label>
                        <select name="status">
                            <?php foreach (array('new', 'reviewing', 'qualified', 'sent', 'declined') as $status) : ?>
                                <option value="<?php echo esc_attr($status); ?>" <?php selected($item->status, $status); ?>><?php echo esc_html($this->review_request_status_label($status)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button button-primary" type="submit"><?php esc_html_e('Update', 'science180-book-review'); ?></button>
                    </form>
                </div>
                <div class="s180re-admin-panel s180re-admin-panel-wide">
                    <h2><?php esc_html_e('Raw data', 'science180-book-review'); ?></h2>
                    <table class="widefat striped">
                        <tbody>
                            <?php foreach ($this->review_request_labels() as $key => $label) : ?>
                                <?php if (isset($data[$key])) : ?>
                                    <tr><th><?php echo esc_html($label); ?></th><td><?php echo nl2br(esc_html($data[$key])); ?></td></tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
                <label><?php esc_html_e('Request/notice recipient email', 'science180-book-review'); ?></label>
                <input class="regular-text" type="email" name="recipient_email" value="<?php echo esc_attr($this->recipient_email()); ?>" required>

                <label><?php esc_html_e('From name', 'science180-book-review'); ?></label>
                <input class="regular-text" type="text" name="from_name" value="<?php echo esc_attr(get_option('s180re_from_name')); ?>">

                <label><?php esc_html_e('From email override', 'science180-book-review'); ?></label>
                <input class="regular-text" type="email" name="from_email" value="<?php echo esc_attr(get_option('s180re_from_email')); ?>" placeholder="<?php echo esc_attr($this->sender_email()); ?>">
                <p class="description"><?php esc_html_e('Leave empty to use the existing AdvNews SMTP sender or the WordPress admin email. The plugin never stores SMTP passwords.', 'science180-book-review'); ?></p>

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
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 10,
            'updated_at' => $now,
        );

        if ($book_id > 0) {
            $wpdb->update($this->table('books'), $data, array('id' => $book_id), array('%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s'), array('%d'));
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($this->table('books'), $data, array('%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s'));
        }

        $this->admin_redirect('s180br-books', 'book_saved');
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
        $allowed = array('new', 'reviewing', 'qualified', 'sent', 'declined');
        if (!in_array($status, $allowed, true)) {
            $status = 'new';
        }

        $request = $this->get_review_request($request_id);
        if (!$request) {
            $this->admin_redirect('s180br-review-requests', 'request_missing');
        }

        $wpdb->update($this->table('review_requests'), array('status' => $status, 'updated_at' => current_time('mysql')), array('id' => $request_id), array('%s', '%s'), array('%d'));

        $notice = 'request_updated';
        if ($request->status !== $status && in_array($status, array('reviewing', 'qualified', 'sent', 'declined'), true)) {
            $updated_request = $this->get_review_request($request_id);
            $notice = $updated_request && $this->send_review_request_status_email($updated_request, $status) ? 'request_updated_notified' : 'request_updated_email_failed';
        }

        wp_safe_redirect(admin_url('admin.php?page=s180br-review-requests&view=' . $request_id . '&s180re_admin_status=' . rawurlencode($notice)));
        exit;
    }

    public function handle_save_settings()
    {
        $this->require_admin_post('s180br_save_settings');
        $recipient = sanitize_email($this->post_raw('recipient_email'));
        if ($recipient && is_email($recipient)) {
            update_option('s180re_recipient_email', $recipient);
        }
        update_option('s180re_from_name', $this->post_text('from_name', false));
        $from_email = sanitize_email($this->post_raw('from_email'));
        update_option('s180re_from_email', $from_email && is_email($from_email) ? $from_email : '');
        $this->admin_redirect('s180br-settings', 'settings_saved');
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

    private function render_public_notice($context)
    {
        if (empty($_GET['s180re_status'])) {
            return;
        }

        $status = sanitize_key($_GET['s180re_status']);
        $messages = array(
            'review_success' => array('success', __('Your review copy request was submitted successfully.', 'science180-book-review')),
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

        echo '<div class="s180re-message s180re-message-' . esc_attr($messages[$status][0]) . '">' . esc_html($messages[$status][1]) . '</div>';
    }

    private function render_admin_notice()
    {
        if (empty($_GET['s180re_admin_status'])) {
            return;
        }

        $status = sanitize_key($_GET['s180re_admin_status']);
        $messages = array(
            'book_saved' => __('Book saved.', 'science180-book-review'),
            'book_missing' => __('Book title is required.', 'science180-book-review'),
            'request_updated' => __('Request status updated.', 'science180-book-review'),
            'request_updated_notified' => __('Request status updated and the applicant was notified.', 'science180-book-review'),
            'request_updated_email_failed' => __('Request status updated, but the applicant email could not be sent.', 'science180-book-review'),
            'request_missing' => __('Review copy request not found.', 'science180-book-review'),
            'settings_saved' => __('Settings saved.', 'science180-book-review'),
        );

        if (isset($messages[$status])) {
            $notice_class = in_array($status, array('book_missing', 'request_missing', 'request_updated_email_failed'), true) ? 'notice-warning' : 'notice-success';
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

        return home_url('/review-copy-request/');
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
            'created_at' => 'Submitted at',
            'status' => 'Status',
        );
    }

    private function review_request_status_label($status)
    {
        $labels = array(
            'new' => __('New', 'science180-book-review'),
            'reviewing' => __('Reviewing', 'science180-book-review'),
            'qualified' => __('Approved', 'science180-book-review'),
            'sent' => __('Sent', 'science180-book-review'),
            'declined' => __('Rejected', 'science180-book-review'),
        );

        $status = sanitize_key($status);
        return isset($labels[$status]) ? $labels[$status] : ucwords(str_replace('_', ' ', $status));
    }

    private function format_mailing_address($data)
    {
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
        $email = sanitize_email($this->post_raw($key));
        return ($email && is_email($email)) ? strtolower($email) : '';
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
