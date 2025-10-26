<?php
/**
 * Rakuten Advertising Affiliate Network
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Rakuten extends Noah_Affiliate_Network_Base {
    
    protected $network_id = 'rakuten';
    protected $network_name = 'Rakuten';
    
    private $api_base = 'https://api.linksynergy.com/productsearch/1.0';
    
    /**
     * Search products
     */
    public function search_products($query, $args = array()) {
        $defaults = array(
            'limit' => 10,
            'mid' => '', // Merchant ID
            'category' => '',
            'min_price' => '',
            'max_price' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Check cache
        $cache_key = 'noah_rakuten_search_' . md5($query . serialize($args));
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $api_token = $this->get_setting('api_token');
        
        if (empty($api_token)) {
            return array();
        }
        
        // Build API URL
        $params = array(
            'token' => $api_token,
            'keyword' => $query,
            'pagenumber' => 1,
            'pagesize' => $args['limit']
        );
        
        if (!empty($args['mid'])) {
            $params['mid'] = $args['mid'];
        }
        
        if (!empty($args['category'])) {
            $params['cat'] = $args['category'];
        }
        
        if (!empty($args['min_price'])) {
            $params['minprice'] = $args['min_price'];
        }
        
        if (!empty($args['max_price'])) {
            $params['maxprice'] = $args['max_price'];
        }
        
        $url = $this->api_base . '?' . http_build_query($params);
        
        $response = $this->make_request($url);
        
        if (!$response['success']) {
            $this->log_error('Rakuten search failed', array('query' => $query, 'error' => $response['message']));
            return array();
        }
        
        // Rakuten returns XML
        $xml = simplexml_load_string($response['data']);
        $products = array();
        
        if ($xml && isset($xml->item)) {
            foreach ($xml->item as $item) {
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
        
        // Rakuten doesn't have direct product lookup, search by product ID
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
        $sid = $this->get_setting('sid'); // Site ID
        
        if (empty($sid) || empty($product_data['url'])) {
            return '';
        }
        
        // Rakuten link format
        $click_url = 'https://click.linksynergy.com/deeplink';
        
        $params = array(
            'id' => $sid,
            'mid' => isset($product_data['merchant_id']) ? $product_data['merchant_id'] : '',
            'murl' => $product_data['url']
        );
        
        return $click_url . '?' . http_build_query($params);
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $response = $this->search_products('test', array('limit' => 1));
        
        if (!empty($response)) {
            return array(
                'success' => true,
                'message' => __('Successfully connected to Rakuten API', 'noah-affiliate')
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to connect. Please check your API credentials.', 'noah-affiliate')
        );
    }
    
    /**
     * Format product data from XML
     */
    private function format_product($item) {
        $product = array(
            'id' => isset($item->productid) ? (string)$item->productid : '',
            'title' => isset($item->productname) ? (string)$item->productname : '',
            'description' => isset($item->description) ? (string)$item->description : '',
            'price' => '',
            'currency' => 'USD',
            'image' => '',
            'url' => isset($item->linkurl) ? (string)$item->linkurl : '',
            'rating' => 0,
            'reviews' => 0,
            'availability' => true,
            'merchant' => isset($item->merchantname) ? (string)$item->merchantname : '',
            'network' => 'rakuten',
            'merchant_id' => isset($item->mid) ? (string)$item->mid : ''
        );
        
        // Price
        if (isset($item->price)) {
            $price = (string)$item->price;
            $product['price'] = $price;
            
            // Extract currency if available
            if (isset($item->currency)) {
                $product['currency'] = (string)$item->currency;
            }
        }
        
        // Image - Rakuten can have multiple images
        if (isset($item->imageurl)) {
            $product['image'] = (string)$item->imageurl;
        } elseif (isset($item->thumbnail)) {
            $product['image'] = (string)$item->thumbnail;
        }
        
        return $this->normalize_product($product);
    }
}
