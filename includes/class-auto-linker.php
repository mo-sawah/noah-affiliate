<?php
/**
 * Auto-Linker Class
 * Analyzes post content and automatically inserts relevant affiliate products
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Auto_Linker {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into post save for auto-linking
        add_action('publish_post', array($this, 'maybe_auto_link'), 10, 2);
        add_action('save_post', array($this, 'maybe_auto_link'), 10, 2);
    }
    
    /**
     * Maybe auto-link post
     */
    public function maybe_auto_link($post_id, $post) {
        // Check if auto-linking is enabled
        if (get_option('noah_affiliate_auto_link_enabled', '0') !== '1') {
            return;
        }
        
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check if post type is allowed
        $allowed_post_types = get_option('noah_affiliate_auto_link_post_types', array('post'));
        if (!in_array($post->post_type, $allowed_post_types)) {
            return;
        }
        
        // Check if category is allowed
        $allowed_categories = get_option('noah_affiliate_auto_link_categories', array());
        if (!empty($allowed_categories)) {
            $post_categories = wp_get_post_categories($post_id);
            $has_allowed_cat = false;
            
            foreach ($post_categories as $cat_id) {
                if (in_array($cat_id, $allowed_categories)) {
                    $has_allowed_cat = true;
                    break;
                }
            }
            
            if (!$has_allowed_cat) {
                return;
            }
        }
        
        // Check if already auto-linked
        $already_linked = get_post_meta($post_id, '_noah_auto_linked', true);
        if ($already_linked === '1') {
            return;
        }
        
        // Queue for background processing
        $processor = Noah_Affiliate_Background_Processor::get_instance();
        $processor->push_to_queue(array(
            'action' => 'auto_link',
            'post_id' => $post_id
        ));
        $processor->save()->dispatch();
    }
    
    /**
     * Process auto-linking for a post
     */
    public function process_post($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return false;
        }
        
        // Extract keywords from post
        $keywords = $this->extract_keywords($post);
        
        if (empty($keywords)) {
            return false;
        }
        
        // Search for products
        $products = $this->search_relevant_products($keywords);
        
        if (empty($products)) {
            return false;
        }
        
        // Analyze content structure
        $content_structure = $this->analyze_content_structure($post->post_content);
        
        // Determine insertion points
        $insertion_points = $this->determine_insertion_points($content_structure, count($products));
        
        // Insert products
        $this->insert_products($post_id, $products, $insertion_points);
        
        // Mark as auto-linked
        update_post_meta($post_id, '_noah_auto_linked', '1');
        update_post_meta($post_id, '_noah_auto_linked_at', current_time('mysql'));
        
        return true;
    }
    
    /**
     * Extract keywords from post
     */
    private function extract_keywords($post) {
        $keywords = array();
        
        // Get title words
        $title_words = $this->tokenize_text($post->post_title);
        
        // Get content words
        $content = wp_strip_all_tags($post->post_content);
        $content_words = $this->tokenize_text($content);
        
        // Get tags
        $tags = wp_get_post_tags($post->ID, array('fields' => 'names'));
        
        // Combine and filter
        $all_words = array_merge($title_words, $content_words, $tags);
        
        // Remove stop words
        $all_words = $this->remove_stop_words($all_words);
        
        // Get word frequency
        $word_freq = array_count_values($all_words);
        
        // Sort by frequency
        arsort($word_freq);
        
        // Get top keywords (up to 10)
        $keywords = array_keys(array_slice($word_freq, 0, 10));
        
        // Add title as priority keyword
        array_unshift($keywords, $post->post_title);
        
        return array_unique($keywords);
    }
    
    /**
     * Tokenize text into words
     */
    private function tokenize_text($text) {
        // Remove special characters but keep spaces and hyphens
        $text = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $text);
        
        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filter out short words (< 3 characters)
        $words = array_filter($words, function($word) {
            return strlen($word) >= 3;
        });
        
        // Convert to lowercase
        $words = array_map('strtolower', $words);
        
        return $words;
    }
    
    /**
     * Remove common stop words
     */
    private function remove_stop_words($words) {
        $stop_words = array(
            'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'her', 'was', 'one',
            'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'man', 'new', 'now', 'old',
            'see', 'two', 'way', 'who', 'boy', 'did', 'its', 'let', 'put', 'say', 'she', 'too',
            'use', 'with', 'this', 'that', 'from', 'they', 'have', 'been', 'what', 'when',
            'your', 'more', 'will', 'than', 'these', 'those', 'into', 'very', 'about', 'there'
        );
        
        return array_diff($words, $stop_words);
    }
    
    /**
     * Search for relevant products based on keywords
     */
    private function search_relevant_products($keywords) {
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        $max_products = intval(get_option('noah_affiliate_auto_link_max_products', '5'));
        
        $all_products = array();
        
        // Search with top keywords
        foreach (array_slice($keywords, 0, 3) as $keyword) {
            $results = $product_manager->search_all_networks($keyword, array('limit' => 3));
            
            foreach ($results as $network_id => $products) {
                foreach ($products as $product) {
                    $product['search_keyword'] = $keyword;
                    $product['relevance_score'] = $this->calculate_relevance($product, $keywords);
                    $all_products[] = $product;
                }
            }
        }
        
        // Sort by relevance
        usort($all_products, function($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });
        
        // Return top products
        return array_slice($all_products, 0, $max_products);
    }
    
    /**
     * Calculate product relevance score
     */
    private function calculate_relevance($product, $keywords) {
        $score = 0;
        
        $product_text = strtolower($product['title'] . ' ' . $product['description']);
        
        foreach ($keywords as $index => $keyword) {
            // Higher score for earlier keywords
            $weight = (10 - $index) / 10;
            
            if (stripos($product_text, strtolower($keyword)) !== false) {
                $score += $weight;
            }
        }
        
        // Bonus for availability
        if ($product['availability']) {
            $score += 0.5;
        }
        
        // Bonus for ratings
        if ($product['rating'] > 4) {
            $score += 0.3;
        }
        
        return $score;
    }
    
    /**
     * Analyze content structure
     */
    private function analyze_content_structure($content) {
        $structure = array(
            'paragraphs' => array(),
            'headings' => array(),
            'total_length' => strlen(wp_strip_all_tags($content))
        );
        
        // Split by paragraphs
        $paragraphs = explode('</p>', $content);
        
        foreach ($paragraphs as $index => $paragraph) {
            if (trim(wp_strip_all_tags($paragraph))) {
                $structure['paragraphs'][] = array(
                    'index' => $index,
                    'content' => $paragraph,
                    'length' => strlen(wp_strip_all_tags($paragraph))
                );
            }
        }
        
        // Extract headings
        preg_match_all('/<h([2-4])[^>]*>(.*?)<\/h\1>/i', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $structure['headings'][] = array(
                'level' => $match[1],
                'text' => wp_strip_all_tags($match[2]),
                'full' => $match[0]
            );
        }
        
        return $structure;
    }
    
    /**
     * Determine insertion points
     */
    private function determine_insertion_points($structure, $product_count) {
        $points = array();
        $total_paragraphs = count($structure['paragraphs']);
        
        if ($total_paragraphs < 3) {
            // Too short, insert at end only
            return array(array('position' => 'end'));
        }
        
        $min_spacing = intval(get_option('noah_affiliate_auto_link_min_spacing', '3'));
        
        // Calculate even distribution
        $step = max($min_spacing, floor($total_paragraphs / ($product_count + 1)));
        
        for ($i = 0; $i < $product_count; $i++) {
            $para_index = ($i + 1) * $step;
            
            if ($para_index < $total_paragraphs) {
                $points[] = array(
                    'position' => 'after_paragraph',
                    'paragraph_index' => $para_index
                );
            }
        }
        
        // If we couldn't fit all products, add to end
        while (count($points) < $product_count) {
            $points[] = array('position' => 'end');
        }
        
        return $points;
    }
    
    /**
     * Insert products into post
     */
    private function insert_products($post_id, $products, $insertion_points) {
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        
        foreach ($products as $index => $product) {
            if (!isset($insertion_points[$index])) {
                break;
            }
            
            $point = $insertion_points[$index];
            
            // Prepare product data
            $product_data = array(
                'product_id' => $product['id'],
                'network' => $product['network'],
                'position' => $point,
                'layout' => 'card',
                'auto_inserted' => true
            );
            
            // Add to post
            $product_manager->add_product_to_post($post_id, array_merge($product, $product_data));
        }
        
        return true;
    }
    
    /**
     * Reset auto-linking for a post
     */
    public function reset_post($post_id) {
        delete_post_meta($post_id, '_noah_auto_linked');
        delete_post_meta($post_id, '_noah_auto_linked_at');
        
        // Remove auto-inserted products
        $products = Noah_Affiliate_Product_Manager::get_instance()->get_post_products($post_id);
        
        foreach ($products as $instance_id => $product) {
            if (isset($product['auto_inserted']) && $product['auto_inserted']) {
                Noah_Affiliate_Product_Manager::get_instance()->remove_product_from_post($post_id, $instance_id);
            }
        }
        
        return true;
    }
}
