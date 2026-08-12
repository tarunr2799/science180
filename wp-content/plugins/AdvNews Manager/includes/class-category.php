<?php
// File: includes/class-category.php

if (!defined('ABSPATH')) {
    exit;
}

class AdvNews_Category
{
    private $wpdb;
    private $table_prefix;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = ADVNEWS_TABLE_PREFIX;
    }

    /**
     * Get all categories
     */
    public function get_all_categories()
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'categories';

        return $this->wpdb->get_results("SELECT * FROM $table_name ORDER BY name");
    }

    /**
     * Get category by ID
     */
    public function get_category($id)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'categories';

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
    }

    /**
     * Get category by slug
     */
    public function get_category_by_slug($slug)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'categories';

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE slug = %s",
            $slug
        ));
    }

    /**
     * Create category
     */
    public function create_category($data)
    {
        $data = AdvNews_Security::sanitize_array($data);

        if (empty($data['name'])) {
            return new WP_Error('missing_name', __('Category name is required.', 'advnews-manager'));
        }

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'categories';

        $slug = isset($data['slug']) ? $data['slug'] : sanitize_title($data['name']);
        $color = isset($data['color']) ? $data['color'] : '#3498db';

        // Check if slug exists
        $existing = $this->get_category_by_slug($slug);
        if ($existing) {
            $slug = $slug . '-' . uniqid();
        }

        $result = $this->wpdb->insert(
            $table_name,
            array(
                'name' => $data['name'],
                'slug' => $slug,
                'description' => isset($data['description']) ? $data['description'] : '',
                'color' => $color
            )
        );

        if (!$result) {
            return new WP_Error('db_error', __('Failed to create category.', 'advnews-manager'));
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update category
     */
    public function update_category($id, $data)
    {
        $data = AdvNews_Security::sanitize_array($data);

        $table_name = $this->wpdb->prefix . $this->table_prefix . 'categories';

        $update_data = array();

        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
            $update_data['slug'] = sanitize_title($data['name']);
        }

        if (isset($data['description'])) {
            $update_data['description'] = $data['description'];
        }

        if (isset($data['color'])) {
            $update_data['color'] = $data['color'];
        }

        if (empty($update_data)) {
            return true;
        }

        $result = $this->wpdb->update(
            $table_name,
            $update_data,
            array('id' => $id)
        );

        return $result !== false;
    }

    /**
     * Delete category
     */
    public function delete_category($id)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'categories';

        // Check if category is in use
        $sub_cat_table = $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories';
        $in_use = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $sub_cat_table WHERE category_id = %d",
            $id
        ));

        if ($in_use > 0) {
            return new WP_Error('category_in_use',
                sprintf(__('Cannot delete category. It is used by %d subscribers.', 'advnews-manager'), $in_use)
            );
        }

        $campaign_table = $this->wpdb->prefix . $this->table_prefix . 'campaign_categories';
        $campaigns_using = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $campaign_table WHERE category_id = %d",
            $id
        ));

        if ($campaigns_using > 0) {
            return new WP_Error('category_in_use',
                sprintf(__('Cannot delete category. It is used by %d campaigns.', 'advnews-manager'), $campaigns_using)
            );
        }

        $result = $this->wpdb->delete($table_name, array('id' => $id));

        return $result !== false;
    }

    /**
     * Get category subscriber count
     */
    public function get_subscriber_count($category_id)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'subscriber_categories';

        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE category_id = %d",
            $category_id
        ));
    }

    /**
     * Get category campaign count
     */
    public function get_campaign_count($category_id)
    {
        $table_name = $this->wpdb->prefix . $this->table_prefix . 'campaign_categories';

        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT campaign_id) FROM $table_name WHERE category_id = %d",
            $category_id
        ));
    }

    /**
     * Get category statistics
     */
    public function get_category_stats($category_id)
    {
        $subscriber_count = $this->get_subscriber_count($category_id);
        $campaign_count = $this->get_campaign_count($category_id);

        // Get open rate for this category's campaigns
        $table_campaigns = $this->wpdb->prefix . $this->table_prefix . 'campaigns';
        $table_campaign_categories = $this->wpdb->prefix . $this->table_prefix . 'campaign_categories';
        $avg_open_rate = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT AVG(c.open_rate)
            FROM $table_campaigns c
            INNER JOIN $table_campaign_categories cc ON c.id = cc.campaign_id
            WHERE cc.category_id = %d AND c.status = 'sent'",
            $category_id
        ));

        $avg_click_rate = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT AVG(c.click_rate)
            FROM $table_campaigns c
            INNER JOIN $table_campaign_categories cc ON c.id = cc.campaign_id
            WHERE cc.category_id = %d AND c.status = 'sent'",
            $category_id
        ));

        return array(
            'subscribers' => intval($subscriber_count),
            'campaigns' => intval($campaign_count),
            'avg_open_rate' => round(floatval($avg_open_rate), 2),
            'avg_click_rate' => round(floatval($avg_click_rate), 2)
        );
    }
}
