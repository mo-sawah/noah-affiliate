<?php
/**
 * CJ (Commission Junction) Affiliate Network
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_CJ extends Noah_Affiliate_Network_Base {
    
    protected $network_id = 'cj';
    protected $network_name = 'CJ Affiliate';
    
    private $api_base = 'https://product-search.api.cj.com/v2';
    
    /**
     * Search products
     */
    public function search_products($query, $args = array()) {
        $defaults = array(
            'limit' => 10,
            'advertiser_ids' => '',
            'low_price' => '',
            'high_price' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Check cache
        $cache_key = 'noah_cj_search_' . md5($query . serialize($args));
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $website_id = $this->get_setting('website_id');
        $api_token = $this->get_setting('api_token');
        
        if (empty($website_id) || empty($api_token)) {
            return array();
        }
        
        // Build API URL
        $url = $this->api_base . '/product-search';
        
        $params = array(
            'website-id' => $website_id,
            'keywords' => $query,
            'records-per-page' => $args['limit']
        );
        
        if (!empty($args['advertiser_ids'])) {
            $params['advertiser-ids'] = $args['advertiser_ids'];
        }
        
        if (!empty($args['low_price'])) {
            $params['low-price'] = $args['low_price'];
        }
        
        if (!empty($args['high_price'])) {
            $params['high-price'] = $args['high_price'];
        }
        
        $url .= '?' . http_build_query($params);
        
        $response = $this->make_request($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_token
            )
        ));
        
        if (!$response['success']) {
            $this->log_error('CJ search failed', array('query' => $query, 'error' => $response['message']));
            return array();
        }
        
        $data = json_decode($response['data'], true);
        $products = array();
        
        if (isset($data['products'])) {
            foreach ($data['products'] as $item) {
                $products[] = $this->format_product($item);
            }
        }
        
        // Cache for 12 hours
        set_transient($cache_key, $products, 12 * HOUR_IN_SECONDS);
        
        return $products;
    }
    
    /**
     * Get product details
     */
    public function get_product($product_id) {
        // Check cache
        $cached = $this->get_cached_product($product_id);
        if ($cached !== false) {
            return $cached;
        }
        
        // CJ doesn't have a direct product lookup, so we search by SKU/UPC
        $results = $this->search_products($product_id, array('limit' => 1));
        
        if (!empty($results)) {
            $product = $results[0];
            $this->cache_product($product_id, $product);
            return $product;
        }
        
        return false;
    }
    
    /**
     * Generate affiliate link
     */
    public function generate_link($product_id, $product_data = array()) {
        $website_id = $this->get_setting('website_id');
        
        if (empty($website_id) || empty($product_data['url'])) {
            return '';
        }
        
        // CJ uses click tracking URL
        $click_url = 'https://www.anrdoezrs.net/links/' . $website_id . '/type/dlg/';
        
        // Extract advertiser ID from product data
        $advertiser_id = isset($product_data['advertiser_id']) ? $product_data['advertiser_id'] : '';
        
        if ($advertiser_id) {
            $click_url .= $advertiser_id . '/';
        }
        
        $click_url .= urlencode($product_data['url']);
        
        return $click_url;
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $response = $this->search_products('test', array('limit' => 1));
        
        if (!empty($response)) {
            return array(
                'success' => true,
                'message' => __('Successfully connected to CJ API', 'noah-affiliate')
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to connect. Please check your API credentials.', 'noah-affiliate')
        );
    }
    
    /**
     * Format product data
     */
    private function format_product($item) {
        $product = array(
            'id' => isset($item['sku']) ? $item['sku'] : (isset($item['upc']) ? $item['upc'] : ''),
            'title' => isset($item['name']) ? $item['name'] : '',
            'description' => isset($item['description']) ? $item['description'] : '',
            'price' => '',
            'currency' => isset($item['currency']) ? $item['currency'] : 'USD',
            'image' => isset($item['image-url']) ? $item['image-url'] : '',
            'url' => isset($item['buy-url']) ? $item['buy-url'] : '',
            'rating' => 0,
            'reviews' => 0,
            'availability' => isset($item['in-stock']) ? ($item['in-stock'] == 'yes') : true,
            'merchant' => isset($item['advertiser-name']) ? $item['advertiser-name'] : '',
            'network' => 'cj',
            'advertiser_id' => isset($item['advertiser-id']) ? $item['advertiser-id'] : ''
        );
        
        // Format price
        if (isset($item['price'])) {
            $product['price'] = $item['price'];
        } elseif (isset($item['retail-price'])) {
            $product['price'] = $item['retail-price'];
        }
        
        return $this->normalize_product($product);
    }
}
