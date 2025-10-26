<?php
/**
 * Link Cloaker Class
 * Handles pretty URL redirects for affiliate links
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Link_Cloaker {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('template_redirect', array($this, 'handle_redirect'));
        add_filter('query_vars', array($this, 'add_query_vars'));
    }
    
    /**
     * Add rewrite rules
     */
    public static function add_rewrite_rules() {
        $slug = get_option('noah_affiliate_link_slug', 'go');
        add_rewrite_rule(
            '^' . $slug . '/([^/]+)/?$',
            'index.php?noah_affiliate_redirect=$matches[1]',
            'top'
        );
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'noah_affiliate_redirect';
        return $vars;
    }
    
    /**
     * Handle redirect
     */
    public function handle_redirect() {
        $redirect_key = get_query_var('noah_affiliate_redirect');
        
        if (!$redirect_key) {
            return;
        }
        
        // Get link data from transient
        $link_data = get_transient('noah_link_' . $redirect_key);
        
        if (!$link_data) {
            // Try to find in post meta as fallback
            $link_data = $this->find_link_by_key($redirect_key);
        }
        
        if (!$link_data || !isset($link_data['url'])) {
            wp_redirect(home_url(), 302);
            exit;
        }
        
        // Track the click
        if (isset($link_data['post_id']) && isset($link_data['product_id']) && isset($link_data['network'])) {
            Noah_Affiliate_Database::log_click(
                $link_data['post_id'],
                $link_data['product_id'],
                $link_data['network']
            );
        }
        
        // Perform redirect
        $redirect_type = get_option('noah_affiliate_redirect_type', '302');
        wp_redirect($link_data['url'], intval($redirect_type));
        exit;
    }
    
    /**
     * Create cloaked link
     */
    public static function create_link($url, $post_id, $product_id, $network, $slug = '') {
        // Generate unique key
        if (empty($slug)) {
            $slug = self::generate_slug($product_id, $network);
        } else {
            $slug = sanitize_title($slug);
        }
        
        // Store link data in transient (7 days)
        $link_data = array(
            'url' => $url,
            'post_id' => $post_id,
            'product_id' => $product_id,
            'network' => $network,
            'created' => time()
        );
        
        set_transient('noah_link_' . $slug, $link_data, 7 * DAY_IN_SECONDS);
        
        // Also store in post meta for backup
        $existing_links = get_post_meta($post_id, '_noah_cloaked_links', true);
        if (!is_array($existing_links)) {
            $existing_links = array();
        }
        
        $existing_links[$slug] = $link_data;
        update_post_meta($post_id, '_noah_cloaked_links', $existing_links);
        
        // Generate cloaked URL
        $link_slug = get_option('noah_affiliate_link_slug', 'go');
        return home_url($link_slug . '/' . $slug);
    }
    
    /**
     * Generate unique slug
     */
    private static function generate_slug($product_id, $network) {
        // Create a readable slug
        $base_slug = $network . '-' . substr(md5($product_id), 0, 8);
        
        // Ensure uniqueness
        $slug = $base_slug;
        $counter = 1;
        
        while (get_transient('noah_link_' . $slug) !== false) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Find link by key (fallback search)
     */
    private function find_link_by_key($key) {
        global $wpdb;
        
        // Search in post meta
        $query = $wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
            WHERE meta_key = '_noah_cloaked_links' 
            AND meta_value LIKE %s 
            LIMIT 1",
            '%' . $wpdb->esc_like($key) . '%'
        );
        
        $result = $wpdb->get_row($query);
        
        if ($result) {
            $links = maybe_unserialize($result->meta_value);
            if (isset($links[$key])) {
                return $links[$key];
            }
        }
        
        return false;
    }
    
    /**
     * Delete link
     */
    public static function delete_link($slug, $post_id = null) {
        delete_transient('noah_link_' . $slug);
        
        if ($post_id) {
            $existing_links = get_post_meta($post_id, '_noah_cloaked_links', true);
            if (is_array($existing_links) && isset($existing_links[$slug])) {
                unset($existing_links[$slug]);
                update_post_meta($post_id, '_noah_cloaked_links', $existing_links);
            }
        }
    }
    
    /**
     * Get all links for a post
     */
    public static function get_post_links($post_id) {
        $links = get_post_meta($post_id, '_noah_cloaked_links', true);
        return is_array($links) ? $links : array();
    }
    
    /**
     * Refresh expired links
     */
    public static function refresh_links($post_id) {
        $links = self::get_post_links($post_id);
        
        foreach ($links as $slug => $data) {
            // Re-set transient
            set_transient('noah_link_' . $slug, $data, 7 * DAY_IN_SECONDS);
        }
    }
}
