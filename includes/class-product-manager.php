<?php
/**
 * Product Manager Class
 * Handles product fetching, caching, and management across all networks
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Product_Manager {
    
    private static $instance = null;
    private $networks = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_networks();
    }
    
    /**
     * Initialize network instances
     */
    private function init_networks() {
        $this->networks = array(
            'amazon' => new Noah_Affiliate_Amazon(),
            'awin' => new Noah_Affiliate_Awin(),
            'cj' => new Noah_Affiliate_CJ(),
            'rakuten' => new Noah_Affiliate_Rakuten(),
            'skimlinks' => new Noah_Affiliate_Skimlinks(),
            'firecrawl' => new Noah_Affiliate_Firecrawl()
        );
    }
    
    /**
     * Get network instance
     */
    public function get_network($network_id) {
        return isset($this->networks[$network_id]) ? $this->networks[$network_id] : null;
    }
    
    /**
     * Get all enabled networks
     */
    public function get_enabled_networks() {
        $enabled = array();
        
        foreach ($this->networks as $id => $network) {
            if ($network->is_enabled()) {
                $enabled[$id] = $network;
            }
        }
        
        return $enabled;
    }
    
    /**
     * Search products across all enabled networks
     */
    public function search_all_networks($query, $args = array()) {
        $results = array();
        
        $enabled_networks = $this->get_enabled_networks();
        
        foreach ($enabled_networks as $id => $network) {
            // Skip Skimlinks (auto-linking only)
            if ($id === 'skimlinks') {
                continue;
            }
            
            $products = $network->search_products($query, $args);
            
            if (!empty($products)) {
                $results[$id] = $products;
            }
        }
        
        return $results;
    }
    
    /**
     * Search products from specific network
     */
    public function search_products($network_id, $query, $args = array()) {
        $network = $this->get_network($network_id);
        
        if (!$network || !$network->is_enabled()) {
            return array();
        }
        
        return $network->search_products($query, $args);
    }
    
    /**
     * Get product from specific network
     */
    public function get_product($network_id, $product_id) {
        $network = $this->get_network($network_id);
        
        if (!$network || !$network->is_enabled()) {
            return false;
        }
        
        return $network->get_product($product_id);
    }
    
    /**
     * Generate affiliate link
     */
    public function generate_link($network_id, $product_id, $product_data = array(), $post_id = 0, $custom_slug = '') {
        $network = $this->get_network($network_id);
        
        if (!$network) {
            return '';
        }
        
        $direct_url = $network->generate_link($product_id, $product_data);
        
        if (empty($direct_url)) {
            return '';
        }
        
        // Check if link cloaking is enabled
        $use_cloaking = get_option('noah_affiliate_use_cloaking', '1');
        
        if ($use_cloaking === '1' && $post_id > 0) {
            return Noah_Affiliate_Link_Cloaker::create_link(
                $direct_url,
                $post_id,
                $product_id,
                $network_id,
                $custom_slug
            );
        }
        
        return $direct_url;
    }
    
    /**
     * Get products from post meta
     */
    public function get_post_products($post_id) {
        $products = get_post_meta($post_id, '_noah_affiliate_products', true);
        
        if (!is_array($products)) {
            return array();
        }
        
        return $products;
    }
    
    /**
     * Add product to post
     */
    public function add_product_to_post($post_id, $product_data) {
        $products = $this->get_post_products($post_id);
        
        // Generate unique ID for this product instance
        $instance_id = uniqid('product_');
        
        $product_data['instance_id'] = $instance_id;
        $product_data['added_at'] = current_time('mysql');
        
        $products[$instance_id] = $product_data;
        
        update_post_meta($post_id, '_noah_affiliate_products', $products);
        
        return $instance_id;
    }
    
    /**
     * Update product in post
     */
    public function update_post_product($post_id, $instance_id, $product_data) {
        $products = $this->get_post_products($post_id);
        
        if (!isset($products[$instance_id])) {
            return false;
        }
        
        $products[$instance_id] = array_merge($products[$instance_id], $product_data);
        $products[$instance_id]['updated_at'] = current_time('mysql');
        
        update_post_meta($post_id, '_noah_affiliate_products', $products);
        
        return true;
    }
    
    /**
     * Remove product from post
     */
    public function remove_product_from_post($post_id, $instance_id) {
        $products = $this->get_post_products($post_id);
        
        if (!isset($products[$instance_id])) {
            return false;
        }
        
        unset($products[$instance_id]);
        
        update_post_meta($post_id, '_noah_affiliate_products', $products);
        
        return true;
    }
    
    /**
     * Refresh product data (for background processing)
     */
    public function refresh_product_data($post_id, $instance_id) {
        $products = $this->get_post_products($post_id);
        
        if (!isset($products[$instance_id])) {
            return false;
        }
        
        $product = $products[$instance_id];
        
        if (!isset($product['network']) || !isset($product['product_id'])) {
            return false;
        }
        
        // Fetch fresh data from network
        $fresh_data = $this->get_product($product['network'], $product['product_id']);
        
        if ($fresh_data) {
            // Update price, availability, etc. but keep custom settings
            $keep_fields = array('instance_id', 'position', 'layout', 'custom_title', 'custom_description', 'badge', 'added_at');
            
            foreach ($keep_fields as $field) {
                if (isset($product[$field])) {
                    $fresh_data[$field] = $product[$field];
                }
            }
            
            $fresh_data['updated_at'] = current_time('mysql');
            
            $products[$instance_id] = $fresh_data;
            update_post_meta($post_id, '_noah_affiliate_products', $products);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Test network connection
     */
    public function test_network($network_id) {
        $network = $this->get_network($network_id);
        
        if (!$network) {
            return array(
                'success' => false,
                'message' => __('Network not found', 'noah-affiliate')
            );
        }
        
        return $network->test_connection();
    }
    
    /**
     * Get product display HTML
     */
    public function get_product_html($product_data, $layout = 'card') {
        $template_path = NOAH_AFFILIATE_PATH . 'public/templates/product-' . $layout . '.php';
        
        if (!file_exists($template_path)) {
            $template_path = NOAH_AFFILIATE_PATH . 'public/templates/product-card.php';
        }
        
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
}
