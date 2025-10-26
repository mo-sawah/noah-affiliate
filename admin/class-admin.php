<?php
/**
 * Admin Class
 * Main admin interface handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers for product search
        add_action('wp_ajax_noah_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_noah_get_product', array($this, 'ajax_get_product'));
        add_action('wp_ajax_noah_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_noah_get_firecrawl_countries', array($this, 'ajax_get_firecrawl_countries'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Noah Affiliate', 'noah-affiliate'),
            __('Noah Affiliate', 'noah-affiliate'),
            'manage_options',
            'noah-affiliate',
            array($this, 'settings_page'),
            'dashicons-cart',
            30
        );
        
        add_submenu_page(
            'noah-affiliate',
            __('Settings', 'noah-affiliate'),
            __('Settings', 'noah-affiliate'),
            'manage_options',
            'noah-affiliate',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'noah-affiliate',
            __('Analytics', 'noah-affiliate'),
            __('Analytics', 'noah-affiliate'),
            'manage_options',
            'noah-affiliate-analytics',
            array($this, 'analytics_page')
        );
    }
    
    /**
     * Settings page callback
     */
    public function settings_page() {
        Noah_Affiliate_Settings::get_instance()->render_page();
    }
    
    /**
     * Analytics page callback
     */
    public function analytics_page() {
        Noah_Affiliate_Analytics::get_instance()->render_page();
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Admin CSS
        wp_enqueue_style(
            'noah-affiliate-admin',
            NOAH_AFFILIATE_URL . 'admin/css/admin.css',
            array(),
            NOAH_AFFILIATE_VERSION
        );
        
        // Only on post edit pages and plugin pages
        if (in_array($hook, array('post.php', 'post-new.php')) || strpos($hook, 'noah-affiliate') !== false) {
            // Select2 for better dropdowns
            wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
            wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));
            
            // Admin JS
            wp_enqueue_script(
                'noah-affiliate-admin',
                NOAH_AFFILIATE_URL . 'admin/js/admin.js',
                array('jquery', 'select2', 'jquery-ui-sortable'),
                NOAH_AFFILIATE_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script('noah-affiliate-admin', 'noahAffiliateAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('noah_affiliate_admin'),
                'strings' => array(
                    'searchPlaceholder' => __('Search products...', 'noah-affiliate'),
                    'searching' => __('Searching...', 'noah-affiliate'),
                    'noResults' => __('No products found', 'noah-affiliate'),
                    'error' => __('An error occurred', 'noah-affiliate'),
                    'confirmDelete' => __('Are you sure you want to remove this product?', 'noah-affiliate')
                )
            ));
        }
    }
    
    /**
     * AJAX: Search products
     */
    public function ajax_search_products() {
        check_ajax_referer('noah_affiliate_admin', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $network = isset($_POST['network']) ? sanitize_text_field($_POST['network']) : '';
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        
        if (empty($query) || empty($network)) {
            wp_send_json_error(array('message' => 'Missing parameters'));
        }
        
        $args = array('limit' => 10);
        if (!empty($country)) {
            $args['country'] = $country;
        }
        
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        $products = $product_manager->search_products($network, $query, $args);
        
        wp_send_json_success(array('products' => $products));
    }
    
    /**
     * AJAX: Get single product
     */
    public function ajax_get_product() {
        check_ajax_referer('noah_affiliate_admin', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';
        $network = isset($_POST['network']) ? sanitize_text_field($_POST['network']) : '';
        
        if (empty($product_id) || empty($network)) {
            wp_send_json_error(array('message' => 'Missing parameters'));
        }
        
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        $product = $product_manager->get_product($network, $product_id);
        
        if ($product) {
            wp_send_json_success(array('product' => $product));
        }
        
        wp_send_json_error(array('message' => 'Product not found'));
    }
    
    /**
     * AJAX: Test network connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('noah_affiliate_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $network = isset($_POST['network']) ? sanitize_text_field($_POST['network']) : '';
        
        if (empty($network)) {
            wp_send_json_error(array('message' => 'Missing network parameter'));
        }
        
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        $result = $product_manager->test_network($network);
        
        if ($result['success']) {
            wp_send_json_success($result);
        }
        
        wp_send_json_error($result);
    }
    
    /**
     * AJAX: Get Firecrawl available countries
     */
    public function ajax_get_firecrawl_countries() {
        check_ajax_referer('noah_affiliate_admin', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        $firecrawl = $product_manager->get_network('firecrawl');
        
        if (!$firecrawl) {
            wp_send_json_error(array('message' => 'Firecrawl network not found'));
        }
        
        $countries = $firecrawl->get_available_countries();
        
        wp_send_json_success(array('countries' => $countries));
    }
}
