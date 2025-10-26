<?php
/**
 * Amazon Affiliate Network
 * Supports Amazon Product Advertising API 5.0 with multi-locale
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Amazon extends Noah_Affiliate_Network_Base {
    
    protected $network_id = 'amazon';
    protected $network_name = 'Amazon Associates';
    
    // Amazon PA-API endpoints by region
    private $endpoints = array(
        'US' => 'webservices.amazon.com',
        'UK' => 'webservices.amazon.co.uk',
        'DE' => 'webservices.amazon.de',
        'FR' => 'webservices.amazon.fr',
        'IT' => 'webservices.amazon.it',
        'ES' => 'webservices.amazon.es',
        'CA' => 'webservices.amazon.ca',
        'JP' => 'webservices.amazon.co.jp',
        'AU' => 'webservices.amazon.com.au',
        'IN' => 'webservices.amazon.in',
        'BR' => 'webservices.amazon.com.br',
        'MX' => 'webservices.amazon.com.mx'
    );
    
    private $domains = array(
        'US' => 'amazon.com',
        'UK' => 'amazon.co.uk',
        'DE' => 'amazon.de',
        'FR' => 'amazon.fr',
        'IT' => 'amazon.it',
        'ES' => 'amazon.es',
        'CA' => 'amazon.ca',
        'JP' => 'amazon.co.jp',
        'AU' => 'amazon.com.au',
        'IN' => 'amazon.in',
        'BR' => 'amazon.com.br',
        'MX' => 'amazon.com.mx'
    );
    
    /**
     * Search for products
     */
    public function search_products($query, $args = array()) {
        $defaults = array(
            'limit' => 10,
            'locale' => $this->get_setting('default_locale', 'US'),
            'category' => 'All'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Check cache first
        $cache_key = 'noah_amazon_search_' . md5($query . serialize($args));
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Prepare API request
        $params = array(
            'Keywords' => $query,
            'SearchIndex' => $args['category'],
            'ItemCount' => min($args['limit'], 10),
            'Resources' => array(
                'Images.Primary.Large',
                'ItemInfo.Title',
                'ItemInfo.Features',
                'Offers.Listings.Price',
                'Offers.Listings.Availability.Message'
            )
        );
        
        $response = $this->make_api_request('SearchItems', $params, $args['locale']);
        
        if (!$response['success']) {
            $this->log_error('Search failed', array('query' => $query, 'error' => $response['message']));
            return array();
        }
        
        $products = array();
        
        if (isset($response['data']['SearchResult']['Items'])) {
            foreach ($response['data']['SearchResult']['Items'] as $item) {
                $products[] = $this->format_product($item, $args['locale']);
            }
        }
        
        // Cache results for 12 hours
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
        
        $locale = $this->get_setting('default_locale', 'US');
        
        $params = array(
            'ItemIds' => array($product_id),
            'Resources' => array(
                'Images.Primary.Large',
                'ItemInfo.Title',
                'ItemInfo.Features',
                'ItemInfo.ByLineInfo',
                'Offers.Listings.Price',
                'Offers.Listings.Availability.Message',
                'CustomerReviews.StarRating',
                'CustomerReviews.Count'
            )
        );
        
        $response = $this->make_api_request('GetItems', $params, $locale);
        
        if (!$response['success'] || !isset($response['data']['ItemsResult']['Items'][0])) {
            return false;
        }
        
        $product = $this->format_product($response['data']['ItemsResult']['Items'][0], $locale);
        
        // Cache product
        $this->cache_product($product_id, $product);
        
        return $product;
    }
    
    /**
     * Generate affiliate link
     */
    public function generate_link($product_id, $product_data = array()) {
        $locale = isset($product_data['locale']) ? $product_data['locale'] : $this->get_setting('default_locale', 'US');
        $tag = $this->get_setting('associate_tag_' . $locale, $this->get_setting('associate_tag', ''));
        
        if (empty($tag)) {
            return '';
        }
        
        $domain = $this->domains[$locale];
        return "https://www.{$domain}/dp/{$product_id}/?tag={$tag}";
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $response = $this->search_products('test', array('limit' => 1));
        
        if (!empty($response)) {
            return array(
                'success' => true,
                'message' => __('Successfully connected to Amazon Product Advertising API', 'noah-affiliate')
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to connect. Please check your API credentials.', 'noah-affiliate')
        );
    }
    
    /**
     * Make Amazon PA-API request
     */
    private function make_api_request($operation, $params, $locale = 'US') {
        $access_key = $this->get_setting('access_key');
        $secret_key = $this->get_setting('secret_key');
        $partner_tag = $this->get_setting('associate_tag_' . $locale, $this->get_setting('associate_tag'));
        
        if (empty($access_key) || empty($secret_key) || empty($partner_tag)) {
            return array(
                'success' => false,
                'message' => 'Missing API credentials'
            );
        }
        
        $endpoint = $this->endpoints[$locale];
        $uri = '/paapi5/' . strtolower($operation);
        $host = $endpoint;
        
        $params['PartnerTag'] = $partner_tag;
        $params['PartnerType'] = 'Associates';
        $params['Marketplace'] = 'www.' . $this->domains[$locale];
        
        $payload = json_encode($params);
        
        // Generate AWS4 signature
        $headers = $this->generate_aws4_headers($host, $uri, $payload, $access_key, $secret_key);
        
        $url = 'https://' . $host . $uri;
        
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => $payload,
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            $error_data = json_decode($body, true);
            $message = isset($error_data['Errors'][0]['Message']) ? $error_data['Errors'][0]['Message'] : 'API Error';
            
            return array(
                'success' => false,
                'message' => $message
            );
        }
        
        $data = json_decode($body, true);
        
        return array(
            'success' => true,
            'data' => $data
        );
    }
    
    /**
     * Generate AWS4 signature headers
     */
    private function generate_aws4_headers($host, $uri, $payload, $access_key, $secret_key) {
        $service = 'ProductAdvertisingAPI';
        $region = 'us-east-1'; // PA-API always uses us-east-1
        
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        
        $canonical_headers = "content-type:application/json; charset=utf-8\n";
        $canonical_headers .= "host:{$host}\n";
        $canonical_headers .= "x-amz-date:{$timestamp}\n";
        $canonical_headers .= "x-amz-target:com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems\n";
        
        $signed_headers = 'content-type;host;x-amz-date;x-amz-target';
        
        $payload_hash = hash('sha256', $payload);
        
        $canonical_request = "POST\n{$uri}\n\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
        
        $credential_scope = "{$date}/{$region}/{$service}/aws4_request";
        
        $string_to_sign = "AWS4-HMAC-SHA256\n{$timestamp}\n{$credential_scope}\n" . hash('sha256', $canonical_request);
        
        $signing_key = $this->get_signature_key($secret_key, $date, $region, $service);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        
        $authorization = "AWS4-HMAC-SHA256 Credential={$access_key}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";
        
        return array(
            'Authorization' => $authorization,
            'Content-Type' => 'application/json; charset=utf-8',
            'Host' => $host,
            'X-Amz-Date' => $timestamp,
            'X-Amz-Target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems'
        );
    }
    
    /**
     * Get AWS signing key
     */
    private function get_signature_key($key, $date, $region, $service) {
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        
        return $kSigning;
    }
    
    /**
     * Format product data
     */
    private function format_product($item, $locale) {
        $product = array(
            'id' => isset($item['ASIN']) ? $item['ASIN'] : '',
            'title' => '',
            'description' => '',
            'price' => '',
            'currency' => 'USD',
            'image' => '',
            'url' => '',
            'rating' => 0,
            'reviews' => 0,
            'availability' => true,
            'merchant' => 'Amazon',
            'network' => 'amazon',
            'locale' => $locale
        );
        
        // Title
        if (isset($item['ItemInfo']['Title']['DisplayValue'])) {
            $product['title'] = $item['ItemInfo']['Title']['DisplayValue'];
        }
        
        // Description
        if (isset($item['ItemInfo']['Features']['DisplayValues'])) {
            $product['description'] = implode('. ', array_slice($item['ItemInfo']['Features']['DisplayValues'], 0, 3));
        }
        
        // Price
        if (isset($item['Offers']['Listings'][0]['Price'])) {
            $price_data = $item['Offers']['Listings'][0]['Price'];
            $product['price'] = isset($price_data['DisplayAmount']) ? $price_data['DisplayAmount'] : '';
            $product['currency'] = isset($price_data['Currency']) ? $price_data['Currency'] : 'USD';
        }
        
        // Image
        if (isset($item['Images']['Primary']['Large']['URL'])) {
            $product['image'] = $item['Images']['Primary']['Large']['URL'];
        }
        
        // URL
        if (isset($item['DetailPageURL'])) {
            $product['url'] = $item['DetailPageURL'];
        } else {
            $product['url'] = $this->generate_link($product['id'], array('locale' => $locale));
        }
        
        // Rating
        if (isset($item['CustomerReviews']['StarRating']['Value'])) {
            $product['rating'] = floatval($item['CustomerReviews']['StarRating']['Value']);
        }
        
        // Reviews count
        if (isset($item['CustomerReviews']['Count'])) {
            $product['reviews'] = intval($item['CustomerReviews']['Count']);
        }
        
        // Availability
        if (isset($item['Offers']['Listings'][0]['Availability']['Message'])) {
            $availability_msg = strtolower($item['Offers']['Listings'][0]['Availability']['Message']);
            $product['availability'] = (strpos($availability_msg, 'in stock') !== false);
        }
        
        return $this->normalize_product($product);
    }
}
