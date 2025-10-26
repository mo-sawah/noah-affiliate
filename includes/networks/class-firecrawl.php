<?php
/**
 * Firecrawl Affiliate Network
 * Uses Firecrawl API to scrape product information from any website
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Firecrawl extends Noah_Affiliate_Network_Base {
    
    protected $network_id = 'firecrawl';
    protected $network_name = 'Firecrawl';
    
    private $api_endpoint = 'https://api.firecrawl.dev/v1';
    
    /**
     * Search for products by scraping search results
     */
    public function search_products($query, $args = array()) {
        $defaults = array(
            'limit' => 10,
            'search_url' => '', // Base search URL template
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Check cache first
        $cache_key = 'noah_firecrawl_search_' . md5($query . serialize($args));
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get search URL template from settings
        $search_url_template = $this->get_setting('search_url_template');
        
        if (empty($search_url_template)) {
            $this->log_error('No search URL template configured');
            return array();
        }
        
        // Replace {query} placeholder with actual search query
        $search_url = str_replace('{query}', urlencode($query), $search_url_template);
        
        // Scrape the search results page
        $response = $this->scrape_page($search_url, array(
            'formats' => array('markdown', 'html'),
            'onlyMainContent' => true
        ));
        
        if (!$response['success']) {
            $this->log_error('Search scraping failed', array('query' => $query, 'error' => $response['message']));
            return array();
        }
        
        $products = $this->parse_search_results($response['data'], $args['limit']);
        
        // Cache results for 6 hours
        set_transient($cache_key, $products, 6 * HOUR_IN_SECONDS);
        
        return $products;
    }
    
    /**
     * Get product details by scraping product page
     */
    public function get_product($product_id) {
        // Check cache
        $cached = $this->get_cached_product($product_id);
        if ($cached !== false) {
            return $cached;
        }
        
        // Product ID is the URL for Firecrawl
        $product_url = $product_id;
        
        $response = $this->scrape_page($product_url, array(
            'formats' => array('markdown', 'html'),
            'onlyMainContent' => true,
            'includeRawHtml' => true
        ));
        
        if (!$response['success']) {
            $this->log_error('Product scraping failed', array('url' => $product_url, 'error' => $response['message']));
            return false;
        }
        
        $product = $this->parse_product_page($response['data'], $product_url);
        
        if ($product) {
            // Cache product
            $this->cache_product($product_id, $product);
        }
        
        return $product;
    }
    
    /**
     * Generate affiliate link
     */
    public function generate_link($product_id, $product_data = array()) {
        // Get affiliate parameters from settings
        $param_name = $this->get_setting('affiliate_param_name', 'ref');
        $param_value = $this->get_setting('affiliate_param_value', '');
        
        if (empty($param_value)) {
            return $product_id; // Return original URL if no affiliate params
        }
        
        // Add affiliate parameter to URL
        $url_parts = parse_url($product_id);
        $query_params = array();
        
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $query_params);
        }
        
        $query_params[$param_name] = $param_value;
        
        $new_query = http_build_query($query_params);
        $scheme = isset($url_parts['scheme']) ? $url_parts['scheme'] : 'https';
        $host = isset($url_parts['host']) ? $url_parts['host'] : '';
        $path = isset($url_parts['path']) ? $url_parts['path'] : '';
        
        return $scheme . '://' . $host . $path . '?' . $new_query;
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $api_key = $this->get_setting('api_key');
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('API key is not configured', 'noah-affiliate')
            );
        }
        
        // Test with a simple scrape request
        $test_url = $this->get_setting('test_url', 'https://www.example.com');
        
        $response = $this->scrape_page($test_url, array('formats' => array('markdown')));
        
        if ($response['success']) {
            return array(
                'success' => true,
                'message' => __('Successfully connected to Firecrawl API', 'noah-affiliate')
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to connect. Error: ', 'noah-affiliate') . $response['message']
        );
    }
    
    /**
     * Scrape a page using Firecrawl API
     */
    private function scrape_page($url, $options = array()) {
        $api_key = $this->get_setting('api_key');
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => 'API key not configured'
            );
        }
        
        $endpoint = $this->api_endpoint . '/scrape';
        
        $body = array_merge(array(
            'url' => $url,
            'formats' => array('markdown')
        ), $options);
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 60
        );
        
        $response = wp_remote_request($endpoint, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            $error_data = json_decode($body_response, true);
            $message = isset($error_data['error']) ? $error_data['error'] : 'API Error: ' . $code;
            
            return array(
                'success' => false,
                'message' => $message
            );
        }
        
        $data = json_decode($body_response, true);
        
        if (!isset($data['data'])) {
            return array(
                'success' => false,
                'message' => 'Invalid API response'
            );
        }
        
        return array(
            'success' => true,
            'data' => $data['data']
        );
    }
    
    /**
     * Parse search results from scraped data
     */
    private function parse_search_results($data, $limit = 10) {
        $products = array();
        
        // Get custom selectors from settings
        $title_selector = $this->get_setting('search_title_selector', 'h2 a, h3 a');
        $price_selector = $this->get_setting('search_price_selector', '.price, [class*="price"]');
        $image_selector = $this->get_setting('search_image_selector', 'img');
        $link_selector = $this->get_setting('search_link_selector', 'a[href*="/product/"], a[href*="/item/"]');
        
        // Parse HTML if available
        if (isset($data['html'])) {
            $products = $this->parse_html_products($data['html'], array(
                'title' => $title_selector,
                'price' => $price_selector,
                'image' => $image_selector,
                'link' => $link_selector
            ), $limit);
        }
        
        // Fallback to markdown parsing if HTML parsing didn't yield results
        if (empty($products) && isset($data['markdown'])) {
            $products = $this->parse_markdown_products($data['markdown'], $limit);
        }
        
        return array_slice($products, 0, $limit);
    }
    
    /**
     * Parse products from HTML
     */
    private function parse_html_products($html, $selectors, $limit = 10) {
        $products = array();
        
        // Use DOMDocument to parse HTML
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        
        // Find product containers (common class names)
        $container_queries = array(
            '//*[contains(@class, "product")]',
            '//*[contains(@class, "item")]',
            '//article',
        );
        
        $containers = array();
        foreach ($container_queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes->length > 0) {
                $containers = $nodes;
                break;
            }
        }
        
        if (empty($containers) || $containers->length === 0) {
            return $products;
        }
        
        $count = 0;
        foreach ($containers as $container) {
            if ($count >= $limit) {
                break;
            }
            
            $product = array(
                'id' => '',
                'title' => '',
                'description' => '',
                'price' => '',
                'image' => '',
                'url' => '',
                'merchant' => '',
                'network' => 'firecrawl'
            );
            
            // Extract title
            $title_nodes = $xpath->query('.//*[self::h2 or self::h3 or self::h4]/a', $container);
            if ($title_nodes->length > 0) {
                $product['title'] = trim($title_nodes->item(0)->textContent);
                $product['url'] = $title_nodes->item(0)->getAttribute('href');
            }
            
            // Extract price
            $price_nodes = $xpath->query('.//*[contains(@class, "price") or contains(@class, "Price")]', $container);
            if ($price_nodes->length > 0) {
                $product['price'] = trim($price_nodes->item(0)->textContent);
            }
            
            // Extract image
            $image_nodes = $xpath->query('.//img', $container);
            if ($image_nodes->length > 0) {
                $product['image'] = $image_nodes->item(0)->getAttribute('src');
            }
            
            // Make URL absolute if it's relative
            if (!empty($product['url']) && !parse_url($product['url'], PHP_URL_SCHEME)) {
                $base_url = $this->get_setting('base_url', '');
                if (!empty($base_url)) {
                    $product['url'] = rtrim($base_url, '/') . '/' . ltrim($product['url'], '/');
                }
            }
            
            // Set ID as URL
            $product['id'] = $product['url'];
            
            if (!empty($product['title']) && !empty($product['url'])) {
                $products[] = $this->normalize_product($product);
                $count++;
            }
        }
        
        return $products;
    }
    
    /**
     * Parse products from markdown
     */
    private function parse_markdown_products($markdown, $limit = 10) {
        $products = array();
        
        // Extract links with titles from markdown
        preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $markdown, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            if (count($products) >= $limit) {
                break;
            }
            
            $title = $match[1];
            $url = $match[2];
            
            // Skip non-product links (like navigation)
            if (stripos($url, 'product') === false && 
                stripos($url, 'item') === false &&
                stripos($url, '/p/') === false &&
                stripos($url, '/dp/') === false) {
                continue;
            }
            
            $product = array(
                'id' => $url,
                'title' => $title,
                'url' => $url,
                'network' => 'firecrawl'
            );
            
            $products[] = $this->normalize_product($product);
        }
        
        return $products;
    }
    
    /**
     * Parse product page data
     */
    private function parse_product_page($data, $url) {
        $product = array(
            'id' => $url,
            'title' => '',
            'description' => '',
            'price' => '',
            'image' => '',
            'url' => $url,
            'merchant' => parse_url($url, PHP_URL_HOST),
            'network' => 'firecrawl'
        );
        
        // Parse from HTML if available
        if (isset($data['html'])) {
            $dom = new DOMDocument();
            @$dom->loadHTML($data['html'], LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($dom);
            
            // Extract title
            $title_selectors = array(
                $this->get_setting('product_title_selector', 'h1'),
                '//h1',
                '//*[@id="title"]',
                '//*[contains(@class, "product-title")]'
            );
            
            foreach ($title_selectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes && $nodes->length > 0) {
                    $product['title'] = trim($nodes->item(0)->textContent);
                    break;
                }
            }
            
            // Extract price
            $price_selectors = array(
                $this->get_setting('product_price_selector', '.price'),
                '//*[contains(@class, "price")]',
                '//*[@id="price"]',
                '//*[contains(@class, "Price")]'
            );
            
            foreach ($price_selectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes && $nodes->length > 0) {
                    $product['price'] = trim($nodes->item(0)->textContent);
                    break;
                }
            }
            
            // Extract description
            $desc_selectors = array(
                $this->get_setting('product_description_selector', '.description'),
                '//*[contains(@class, "description")]',
                '//*[@id="description"]',
                '//meta[@name="description"]/@content'
            );
            
            foreach ($desc_selectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes && $nodes->length > 0) {
                    $product['description'] = trim($nodes->item(0)->textContent);
                    if (strlen($product['description']) > 200) {
                        $product['description'] = substr($product['description'], 0, 200) . '...';
                    }
                    break;
                }
            }
            
            // Extract image
            $image_selectors = array(
                $this->get_setting('product_image_selector', '.product-image img'),
                '//img[contains(@class, "product")]',
                '//*[@id="main-image"]',
                '(//img)[1]'
            );
            
            foreach ($image_selectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes && $nodes->length > 0) {
                    $product['image'] = $nodes->item(0)->getAttribute('src');
                    if (empty($product['image'])) {
                        $product['image'] = $nodes->item(0)->getAttribute('data-src');
                    }
                    break;
                }
            }
        }
        
        // Fallback to metadata if available
        if (isset($data['metadata'])) {
            if (empty($product['title']) && isset($data['metadata']['title'])) {
                $product['title'] = $data['metadata']['title'];
            }
            if (empty($product['description']) && isset($data['metadata']['description'])) {
                $product['description'] = $data['metadata']['description'];
            }
            if (empty($product['image']) && isset($data['metadata']['ogImage'])) {
                $product['image'] = $data['metadata']['ogImage'];
            }
        }
        
        return $this->normalize_product($product);
    }
}
