<?php
/**
 * Public Class
 * Handles frontend display, scripts, and content filtering
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Public {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Content filtering
        add_filter('the_content', array($this, 'inject_products'), 20);
        
        // Skimlinks script
        add_action('wp_footer', array($this, 'add_skimlinks_script'));
        
        // Shortcodes
        add_shortcode('noah_product', array($this, 'product_shortcode'));
        add_shortcode('noah_comparison', array($this, 'comparison_shortcode'));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on singular posts
        if (!is_singular()) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'noah-affiliate',
            NOAH_AFFILIATE_URL . 'public/css/noah-affiliate.css',
            array(),
            NOAH_AFFILIATE_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'noah-affiliate',
            NOAH_AFFILIATE_URL . 'public/js/noah-affiliate.js',
            array('jquery'),
            NOAH_AFFILIATE_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('noah-affiliate', 'noahAffiliate', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('noah-affiliate/v1'),
            'nonce' => wp_create_nonce('noah_affiliate_track'),
            'postId' => get_the_ID(),
            'trackingEnabled' => get_option('noah_affiliate_tracking_enabled', '1')
        ));
    }
    
    /**
     * Inject products into content
     */
    public function inject_products($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        $post_id = get_the_ID();
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        $products = $product_manager->get_post_products($post_id);
        
        if (empty($products)) {
            return $content;
        }
        
        // Sort products by position
        uasort($products, array($this, 'sort_by_position'));
        
        // Process content
        $modified_content = $content;
        $paragraphs = explode('</p>', $content);
        $insertions = array();
        
        foreach ($products as $instance_id => $product) {
            if (!isset($product['position'])) {
                continue;
            }
            
            $position = $product['position'];
            $product_html = $this->render_product($product, $post_id);
            
            if ($position === 'end' || $position === 'bottom') {
                // Add to end
                $modified_content .= $product_html;
            } elseif ($position === 'top' || $position === 'start') {
                // Add to beginning
                $modified_content = $product_html . $modified_content;
            } elseif (isset($position['position']) && $position['position'] === 'after_paragraph') {
                $para_index = isset($position['paragraph_index']) ? $position['paragraph_index'] : 0;
                
                if (!isset($insertions[$para_index])) {
                    $insertions[$para_index] = array();
                }
                
                $insertions[$para_index][] = $product_html;
            }
        }
        
        // Insert products at paragraph positions
        if (!empty($insertions)) {
            krsort($insertions); // Reverse order to maintain indices
            
            foreach ($insertions as $para_index => $products_html) {
                if (isset($paragraphs[$para_index])) {
                    $paragraphs[$para_index] .= '</p>' . implode('', $products_html);
                }
            }
            
            $modified_content = implode('</p>', $paragraphs);
        }
        
        return $modified_content;
    }
    
    /**
     * Sort products by position
     */
    private function sort_by_position($a, $b) {
        $pos_a = isset($a['position']['paragraph_index']) ? $a['position']['paragraph_index'] : 999;
        $pos_b = isset($b['position']['paragraph_index']) ? $b['position']['paragraph_index'] : 999;
        
        return $pos_a - $pos_b;
    }
    
    /**
     * Render single product
     */
    private function render_product($product, $post_id) {
        $layout = isset($product['layout']) ? $product['layout'] : 'card';
        
        // Generate affiliate link
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        $affiliate_url = $product_manager->generate_link(
            $product['network'],
            $product['product_id'],
            $product,
            $post_id
        );
        
        $product['affiliate_url'] = $affiliate_url;
        
        // Load template
        ob_start();
        $this->load_template('product-' . $layout, $product);
        return ob_get_clean();
    }
    
    /**
     * Load template file
     */
    private function load_template($template_name, $args = array()) {
        extract($args);
        
        $template_path = NOAH_AFFILIATE_PATH . 'public/templates/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            $template_path = NOAH_AFFILIATE_PATH . 'public/templates/product-card.php';
        }
        
        include $template_path;
    }
    
    /**
     * Add Skimlinks script
     */
    public function add_skimlinks_script() {
        $skimlinks = Noah_Affiliate_Product_Manager::get_instance()->get_network('skimlinks');
        
        if ($skimlinks && $skimlinks->should_load_script()) {
            echo $skimlinks->get_script();
        }
    }
    
    /**
     * Product shortcode
     */
    public function product_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'network' => '',
            'layout' => 'card'
        ), $atts);
        
        if (empty($atts['id']) || empty($atts['network'])) {
            return '';
        }
        
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        $product = $product_manager->get_product($atts['network'], $atts['id']);
        
        if (!$product) {
            return '';
        }
        
        $product['layout'] = $atts['layout'];
        
        return $this->render_product($product, get_the_ID());
    }
    
    /**
     * Comparison table shortcode
     */
    public function comparison_shortcode($atts, $content = null) {
        // Parse product IDs from content or attributes
        // Format: [noah_comparison ids="amazon:B08N5WRWNW,awin:12345"]
        
        $atts = shortcode_atts(array(
            'ids' => ''
        ), $atts);
        
        if (empty($atts['ids'])) {
            return '';
        }
        
        $product_ids = explode(',', $atts['ids']);
        $products = array();
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        
        foreach ($product_ids as $full_id) {
            $parts = explode(':', trim($full_id));
            
            if (count($parts) === 2) {
                $network = $parts[0];
                $product_id = $parts[1];
                
                $product = $product_manager->get_product($network, $product_id);
                
                if ($product) {
                    $products[] = $product;
                }
            }
        }
        
        if (empty($products)) {
            return '';
        }
        
        ob_start();
        $this->load_template('comparison-table', array('products' => $products));
        return ob_get_clean();
    }
}
