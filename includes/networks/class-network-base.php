<?php
/**
 * Network Base Class
 * Abstract class for all affiliate network integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Noah_Affiliate_Network_Base {
    
    protected $network_id;
    protected $network_name;
    protected $settings = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_settings();
    }
    
    /**
     * Load network settings
     */
    protected function load_settings() {
        $this->settings = get_option('noah_affiliate_' . $this->network_id . '_settings', array());
    }
    
    /**
     * Check if network is enabled
     */
    public function is_enabled() {
        return isset($this->settings['enabled']) && $this->settings['enabled'] === '1';
    }
    
    /**
     * Get network ID
     */
    public function get_id() {
        return $this->network_id;
    }
    
    /**
     * Get network name
     */
    public function get_name() {
        return $this->network_name;
    }
    
    /**
     * Search for products
     * @param string $query Search query
     * @param array $args Additional arguments
     * @return array Array of products
     */
    abstract public function search_products($query, $args = array());
    
    /**
     * Get product details
     * @param string $product_id Product ID
     * @return array|bool Product data or false
     */
    abstract public function get_product($product_id);
    
    /**
     * Generate affiliate link
     * @param string $product_id Product ID
     * @param array $product_data Product data
     * @return string Affiliate URL
     */
    abstract public function generate_link($product_id, $product_data = array());
    
    /**
     * Test API connection
     * @return array Status array with 'success' and 'message'
     */
    abstract public function test_connection();
    
    /**
     * Normalize product data
     */
    protected function normalize_product($data) {
        return array(
            'id' => isset($data['id']) ? $data['id'] : '',
            'title' => isset($data['title']) ? $data['title'] : '',
            'description' => isset($data['description']) ? $data['description'] : '',
            'price' => isset($data['price']) ? $data['price'] : '',
            'currency' => isset($data['currency']) ? $data['currency'] : 'USD',
            'image' => isset($data['image']) ? $data['image'] : '',
            'url' => isset($data['url']) ? $data['url'] : '',
            'rating' => isset($data['rating']) ? $data['rating'] : 0,
            'reviews' => isset($data['reviews']) ? $data['reviews'] : 0,
            'availability' => isset($data['availability']) ? $data['availability'] : true,
            'merchant' => isset($data['merchant']) ? $data['merchant'] : '',
            'network' => $this->network_id
        );
    }
    
    /**
     * Cache product data
     */
    protected function cache_product($product_id, $data, $expiration = null) {
        if ($expiration === null) {
            $expiration = intval(get_option('noah_affiliate_cache_duration', '24')) * HOUR_IN_SECONDS;
        }
        
        $cache_key = 'noah_product_' . $this->network_id . '_' . md5($product_id);
        set_transient($cache_key, $data, $expiration);
    }
    
    /**
     * Get cached product
     */
    protected function get_cached_product($product_id) {
        $cache_key = 'noah_product_' . $this->network_id . '_' . md5($product_id);
        return get_transient($cache_key);
    }
    
    /**
     * Make HTTP request
     */
    protected function make_request($url, $args = array()) {
        $defaults = array(
            'timeout' => 30,
            'headers' => array()
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            return array(
                'success' => false,
                'message' => 'HTTP Error: ' . $code
            );
        }
        
        return array(
            'success' => true,
            'data' => $body
        );
    }
    
    /**
     * Parse JSON response
     */
    protected function parse_json_response($response) {
        if (!$response['success']) {
            return $response;
        }
        
        $data = json_decode($response['data'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => 'JSON Parse Error: ' . json_last_error_msg()
            );
        }
        
        return array(
            'success' => true,
            'data' => $data
        );
    }
    
    /**
     * Get setting value
     */
    protected function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Log error
     */
    protected function log_error($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Noah Affiliate - %s] %s | Context: %s',
                $this->network_name,
                $message,
                json_encode($context)
            ));
        }
    }
}
