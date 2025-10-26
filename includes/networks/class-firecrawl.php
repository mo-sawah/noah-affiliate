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
            'country' => '', // For Amazon/eBay presets
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Check cache first
        $cache_key = 'noah_firecrawl_search_' . md5($query . serialize($args));
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get preset and build search URL
        $preset = $this->get_setting('preset', 'custom');
        $search_url = '';
        
        if ($preset === 'amazon') {
            // For Amazon, if no country specified, get first available country
            $country = $args['country'];
            if (empty($country)) {
                $amazon_countries = $this->get_setting('amazon_countries', array());
                $available_countries = array_filter($amazon_countries);
                if (!empty($available_countries)) {
                    $country = key($available_countries);
                } else {
                    $this->log_error('No Amazon countries configured');
                    return array();
                }
            }
            $search_url = $this->get_amazon_search_url($query, $country);
        } elseif ($preset === 'ebay') {
            // For eBay, if no country specified, get first available country
            $country = $args['country'];
            if (empty($country)) {
                $ebay_countries = $this->get_setting('ebay_countries', array());
                $available_countries = array_filter($ebay_countries);
                if (!empty($available_countries)) {
                    $country = key($available_countries);
                } else {
                    $this->log_error('No eBay countries configured');
                    return array();
                }
            }
            $search_url = $this->get_ebay_search_url($query, $country);
        } else {
            // Custom URL template
            $search_url_template = $this->get_setting('search_url_template');
            if (empty($search_url_template)) {
                $this->log_error('No search URL template configured');
                return array();
            }
            $search_url = str_replace('{query}', urlencode($query), $search_url_template);
        }
        
        if (empty($search_url)) {
            return array();
        }
        
        // Scrape the search results page
        $response = $this->scrape_page($search_url, array(
            'formats' => array('markdown', 'html'),
            'onlyMainContent' => true
        ));
        
        if (!$response['success']) {
            $this->log_error('Search scraping failed', array('query' => $query, 'error' => $response['message']));
            return array();
        }
        
        $products = $this->parse_search_results($response['data'], $args['limit'], $args);
        
        // Add country info to products for affiliate link generation
        if (!empty($args['country'])) {
            $country = $args['country'];
        } elseif (isset($country)) {
            // Use the auto-detected country from above
            $args['country'] = $country;
        }
        
        if (!empty($args['country'])) {
            foreach ($products as &$product) {
                $product['country'] = $args['country'];
                $product['preset'] = $preset;
            }
        }
        
        // Cache results for 6 hours
        set_transient($cache_key, $products, 6 * HOUR_IN_SECONDS);
        
        return $products;
    }
    
    /**
     * Get Amazon search URL for country
     */
    private function get_amazon_search_url($query, $country) {
        $domains = array(
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
        );
        
        $domain = isset($domains[$country]) ? $domains[$country] : 'amazon.com';
        return 'https://www.' . $domain . '/s?k=' . urlencode($query);
    }
    
    /**
     * Get available countries based on preset
     */
    public function get_available_countries() {
        $preset = $this->get_setting('preset', 'custom');
        
        if ($preset === 'amazon') {
            $countries = $this->get_setting('amazon_countries', array());
            $labels = array(
                'US' => 'United States',
                'UK' => 'United Kingdom',
                'DE' => 'Germany',
                'FR' => 'France',
                'IT' => 'Italy',
                'ES' => 'Spain',
                'CA' => 'Canada',
                'JP' => 'Japan',
                'AU' => 'Australia',
                'IN' => 'India',
            );
        } elseif ($preset === 'ebay') {
            $countries = $this->get_setting('ebay_countries', array());
            $labels = array(
                'US' => 'United States',
                'UK' => 'United Kingdom',
                'DE' => 'Germany',
                'FR' => 'France',
                'IT' => 'Italy',
                'ES' => 'Spain',
                'CA' => 'Canada',
                'AU' => 'Australia',
            );
        } else {
            return array(); // No country selection for custom
        }
        
        $available = array();
        foreach ($countries as $code => $value) {
            if (!empty($value)) {
                $available[$code] = isset($labels[$code]) ? $labels[$code] : $code;
            }
        }
        
        return $available;
    }
    
    /**
     * Get eBay search URL for country
     */
    private function get_ebay_search_url($query, $country) {
        $domains = array(
            'US' => 'ebay.com',
            'UK' => 'ebay.co.uk',
            'DE' => 'ebay.de',
            'FR' => 'ebay.fr',
            'IT' => 'ebay.it',
            'ES' => 'ebay.es',
            'CA' => 'ebay.ca',
            'AU' => 'ebay.com.au',
        );
        
        $domain = isset($domains[$country]) ? $domains[$country] : 'ebay.com';
        return 'https://www.' . $domain . '/sch/i.html?_nkw=' . urlencode($query);
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
        $preset = $this->get_setting('preset', 'custom');
        $country = isset($product_data['country']) ? $product_data['country'] : '';
        
        // Handle Amazon preset
        if ($preset === 'amazon' && !empty($country)) {
            $amazon_countries = $this->get_setting('amazon_countries', array());
            $tag = isset($amazon_countries[$country]) ? $amazon_countries[$country] : '';
            
            if (empty($tag)) {
                return $product_id; // No tag for this country
            }
            
            // Parse URL and add tag
            $url_parts = parse_url($product_id);
            $query_params = array();
            
            if (isset($url_parts['query'])) {
                parse_str($url_parts['query'], $query_params);
            }
            
            $query_params['tag'] = $tag;
            
            $new_query = http_build_query($query_params);
            $scheme = isset($url_parts['scheme']) ? $url_parts['scheme'] : 'https';
            $host = isset($url_parts['host']) ? $url_parts['host'] : '';
            $path = isset($url_parts['path']) ? $url_parts['path'] : '';
            
            return $scheme . '://' . $host . $path . '?' . $new_query;
        }
        
        // Handle eBay preset
        if ($preset === 'ebay' && !empty($country)) {
            $ebay_countries = $this->get_setting('ebay_countries', array());
            $campaign_id = isset($ebay_countries[$country]) ? $ebay_countries[$country] : '';
            
            if (empty($campaign_id)) {
                return $product_id; // No campaign ID for this country
            }
            
            // Parse URL and add mkcid
            $url_parts = parse_url($product_id);
            $query_params = array();
            
            if (isset($url_parts['query'])) {
                parse_str($url_parts['query'], $query_params);
            }
            
            $query_params['mkcid'] = $campaign_id;
            
            $new_query = http_build_query($query_params);
            $scheme = isset($url_parts['scheme']) ? $url_parts['scheme'] : 'https';
            $host = isset($url_parts['host']) ? $url_parts['host'] : '';
            $path = isset($url_parts['path']) ? $url_parts['path'] : '';
            
            return $scheme . '://' . $host . $path . '?' . $new_query;
        }
        
        // Handle custom preset
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
    private function parse_search_results($data, $limit = 10, $args = array()) {
        $products = array();
        
        // Get preset to use appropriate selectors
        $preset = $this->get_setting('preset', 'custom');
        
        // Get selectors based on preset or custom
        if ($preset === 'amazon') {
            $selectors = array(
                'container' => '//div[@data-component-type="s-search-result"]',
                'title' => './/h2//span[@class="a-size-medium"]',
                'price' => './/span[@class="a-price"]//span[@class="a-offscreen"]',
                'image' => './/img[@class="s-image"]',
                'link' => './/h2//a[@class="a-link-normal"]',
                'rating' => './/span[@class="a-icon-alt"]',
            );
        } elseif ($preset === 'ebay') {
            $selectors = array(
                'container' => '//div[contains(@class, "s-item__wrapper")]',
                'title' => './/div[@class="s-item__title"]',
                'price' => './/span[@class="s-item__price"]',
                'image' => './/img[@class="s-item__image-img"]',
                'link' => './/a[@class="s-item__link"]',
            );
        } else {
            // Custom selectors
            $title_selector = $this->get_setting('search_title_selector', 'h2 a, h3 a');
            $price_selector = $this->get_setting('search_price_selector', '.price, [class*="price"]');
            $image_selector = $this->get_setting('search_image_selector', 'img');
            $link_selector = $this->get_setting('search_link_selector', 'a[href*="/product/"], a[href*="/item/"]');
            
            $selectors = array(
                'title' => $title_selector,
                'price' => $price_selector,
                'image' => $image_selector,
                'link' => $link_selector
            );
        }
        
        // Parse HTML if available
        if (isset($data['html'])) {
            $products = $this->parse_html_products($data['html'], $selectors, $limit, $preset, $args);
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
    private function parse_html_products($html, $selectors, $limit = 10, $preset = 'custom') {
        $products = array();
        
        // Use DOMDocument to parse HTML
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        
        // Find product containers
        if ($preset === 'amazon' || $preset === 'ebay') {
            // Use container XPath for presets
            $container_query = isset($selectors['container']) ? $selectors['container'] : '//*[contains(@class, "product")]';
            $containers = $xpath->query($container_query);
        } else {
            // Find containers for custom (common class names)
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
                'merchant' => $preset === 'amazon' ? 'Amazon' : ($preset === 'ebay' ? 'eBay' : ''),
                'network' => 'firecrawl',
                'rating' => 0,
                'reviews' => 0
            );
            
            if ($preset === 'amazon' || $preset === 'ebay') {
                // Extract using XPath for presets
                // Title
                $title_nodes = $xpath->query($selectors['title'], $container);
                if ($title_nodes->length > 0) {
                    $product['title'] = trim($title_nodes->item(0)->textContent);
                }
                
                // Link
                $link_nodes = $xpath->query($selectors['link'], $container);
                if ($link_nodes->length > 0) {
                    $product['url'] = $link_nodes->item(0)->getAttribute('href');
                }
                
                // Price
                $price_nodes = $xpath->query($selectors['price'], $container);
                if ($price_nodes->length > 0) {
                    $product['price'] = trim($price_nodes->item(0)->textContent);
                }
                
                // Image
                $image_nodes = $xpath->query($selectors['image'], $container);
                if ($image_nodes->length > 0) {
                    $img = $image_nodes->item(0);
                    $product['image'] = $img->getAttribute('src');
                    if (empty($product['image']) || strpos($product['image'], 'data:image') !== false) {
                        // Try data-src or srcset for lazy loaded images
                        $product['image'] = $img->getAttribute('data-src');
                        if (empty($product['image'])) {
                            $srcset = $img->getAttribute('srcset');
                            if (!empty($srcset)) {
                                // Get first image from srcset
                                $parts = explode(',', $srcset);
                                if (!empty($parts[0])) {
                                    $product['image'] = trim(explode(' ', $parts[0])[0]);
                                }
                            }
                        }
                    }
                }
                
                // Rating (for Amazon)
                if ($preset === 'amazon' && isset($selectors['rating'])) {
                    $rating_nodes = $xpath->query($selectors['rating'], $container);
                    if ($rating_nodes->length > 0) {
                        $rating_text = $rating_nodes->item(0)->textContent;
                        // Extract number from "4.5 out of 5 stars"
                        if (preg_match('/[\d\.]+/', $rating_text, $matches)) {
                            $product['rating'] = floatval($matches[0]);
                        }
                    }
                }
                
                // Description - try to get from container
                $desc_nodes = $xpath->query('.//*[contains(@class, "a-size-base")]', $container);
                if ($desc_nodes->length > 0) {
                    $desc_parts = array();
                    foreach ($desc_nodes as $node) {
                        $text = trim($node->textContent);
                        if (!empty($text) && strlen($text) > 20 && $text !== $product['title']) {
                            $desc_parts[] = $text;
                            if (count($desc_parts) >= 2) break;
                        }
                    }
                    if (!empty($desc_parts)) {
                        $product['description'] = implode('. ', $desc_parts);
                        if (strlen($product['description']) > 200) {
                            $product['description'] = substr($product['description'], 0, 200) . '...';
                        }
                    }
                }
            } else {
                // Extract using CSS-style selectors for custom
                // Title
                $title_nodes = $xpath->query('.//*[self::h2 or self::h3 or self::h4]/a', $container);
                if ($title_nodes->length > 0) {
                    $product['title'] = trim($title_nodes->item(0)->textContent);
                    $product['url'] = $title_nodes->item(0)->getAttribute('href');
                }
                
                // Price
                $price_nodes = $xpath->query('.//*[contains(@class, "price") or contains(@class, "Price")]', $container);
                if ($price_nodes->length > 0) {
                    $product['price'] = trim($price_nodes->item(0)->textContent);
                }
                
                // Image
                $image_nodes = $xpath->query('.//img', $container);
                if ($image_nodes->length > 0) {
                    $product['image'] = $image_nodes->item(0)->getAttribute('src');
                }
            }
            
            // Make URL absolute if it's relative
            if (!empty($product['url']) && !parse_url($product['url'], PHP_URL_SCHEME)) {
                if ($preset === 'amazon') {
                    // Amazon URLs start with /
                    $country = isset($args['country']) ? $args['country'] : 'US';
                    $domains = array(
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
                    );
                    $domain = isset($domains[$country]) ? $domains[$country] : 'amazon.com';
                    $product['url'] = 'https://www.' . $domain . $product['url'];
                } elseif ($preset === 'ebay') {
                    // eBay URLs might be relative
                    $country = isset($args['country']) ? $args['country'] : 'US';
                    $domains = array(
                        'US' => 'ebay.com',
                        'UK' => 'ebay.co.uk',
                        'DE' => 'ebay.de',
                        'FR' => 'ebay.fr',
                        'IT' => 'ebay.it',
                        'ES' => 'ebay.es',
                        'CA' => 'ebay.ca',
                        'AU' => 'ebay.com.au',
                    );
                    $domain = isset($domains[$country]) ? $domains[$country] : 'ebay.com';
                    $product['url'] = 'https://www.' . $domain . $product['url'];
                } else {
                    $base_url = $this->get_setting('base_url', '');
                    if (!empty($base_url)) {
                        $product['url'] = rtrim($base_url, '/') . '/' . ltrim($product['url'], '/');
                    }
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
     * Parse products from HTML
     */
    private function parse_html_products($html, $selectors, $limit = 10, $preset = 'custom', $args = array()) {
    
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
