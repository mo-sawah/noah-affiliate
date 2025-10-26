<?php
/**
 * Awin Affiliate Network
 * Product search and link generation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Awin extends Noah_Affiliate_Network_Base {
    
    protected $network_id = 'awin';
    protected $network_name = 'Awin';
    
    private $api_base = 'https://api.awin.com';
    
    /**
     * Search products
     */
    public function search_products($query, $args = array()) {
        $defaults = array(
            'limit' => 10,
            'advertiser_id' => '',
            'category' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Check cache
        $cache_key = 'noah_awin_search_' . md5($query . serialize($args));
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $publisher_id = $this->get_setting('publisher_id');
        $api_token = $this->get_setting('api_token');
        
        if (empty($publisher_id) || empty($api_token)) {
            return array();
        }
        
        // Build API URL
        $url = $this->api_base . '/publishers/' . $publisher_id . '/productfeeds';
        
        $params = array(
            'fq' => 'productName:' . urlencode($query),
            'rows' => $args['limit']
        );
        
        if (!empty($args['advertiser_id'])) {
            $params['fq'] .= ' AND advertiserId:' . $args['advertiser_id'];
        }
        
        if (!empty($args['category'])) {
            $params['fq'] .= ' AND categoryName:' . urlencode($args['category']);
        }
        
        $url .= '?' . http_build_query($params);
        
        $response = $this->make_request($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_token
            )
        ));
        
        if (!$response['success']) {
            $this->log_error('Awin search failed', array('query' => $query, 'error' => $response['message']));
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
        
        $publisher_id = $this->get_setting('publisher_id');
        $api_token = $this->get_setting('api_token');
        
        if (empty($publisher_id) || empty($api_token)) {
            return false;
        }
        
        $url = $this->api_base . '/publishers/' . $publisher_id . '/productfeeds';
        $url .= '?fq=id:' . urlencode($product_id);
        
        $response = $this->make_request($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_token
            )
        ));
        
        if (!$response['success']) {
            return false;
        }
        
        $data = json_decode($response['data'], true);
        
        if (!isset($data['products'][0])) {
            return false;
        }
        
        $product = $this->format_product($data['products'][0]);
        
        // Cache product
        $this->cache_product($product_id, $product);
        
        return $product;
    }
    
    /**
     * Generate affiliate link
     */
    public function generate_link($product_id, $product_data = array()) {
        $publisher_id = $this->get_setting('publisher_id');
        
        if (empty($publisher_id) || empty($product_data['url'])) {
            return '';
        }
        
        // Awin uses click tracking URL
        $click_url = 'https://www.awin1.com/cread.php';
        
        $params = array(
            'awinmid' => isset($product_data['advertiser_id']) ? $product_data['advertiser_id'] : '',
            'awinaffid' => $publisher_id,
            'clickref' => '',
            'ued' => urlencode($product_data['url'])
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
                'message' => __('Successfully connected to Awin API', 'noah-affiliate')
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
            'id' => isset($item['id']) ? $item['id'] : '',
            'title' => isset($item['product_name']) ? $item['product_name'] : '',
            'description' => isset($item['description']) ? $item['description'] : '',
            'price' => '',
            'currency' => isset($item['currency']) ? $item['currency'] : 'USD',
            'image' => isset($item['image_url']) ? $item['image_url'] : '',
            'url' => isset($item['merchant_deep_link']) ? $item['merchant_deep_link'] : '',
            'rating' => 0,
            'reviews' => 0,
            'availability' => isset($item['in_stock']) ? ($item['in_stock'] == 1) : true,
            'merchant' => isset($item['merchant_name']) ? $item['merchant_name'] : '',
            'network' => 'awin',
            'advertiser_id' => isset($item['advertiser_id']) ? $item['advertiser_id'] : ''
        );
        
        // Format price
        if (isset($item['search_price'])) {
            $product['price'] = $item['search_price'];
        } elseif (isset($item['rrp_price'])) {
            $product['price'] = $item['rrp_price'];
        }
        
        return $this->normalize_product($product);
    }
}
