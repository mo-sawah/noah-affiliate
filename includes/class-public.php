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
        add_shortcode('noah_products', array($this, 'products_shortcode'));
        add_shortcode('noah_comparison', array($this, 'comparison_shortcode'));
        add_shortcode('noah_grid', array($this, 'grid_shortcode'));
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
        
        wp_enqueue_style(
            'noah-affiliate-advanced',
            NOAH_AFFILIATE_URL . 'public/css/noah-affiliate-advanced.css',
            array('noah-affiliate'),
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
        
        // Don't auto-inject if shortcodes are present
        if (has_shortcode($content, 'noah_products') || 
            has_shortcode($content, 'noah_grid') || 
            has_shortcode($content, 'noah_comparison')) {
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
        
        // Render all products as grid at the end
        $products_with_urls = array();
        foreach ($products as $product) {
            $product['affiliate_url'] = $product_manager->generate_link(
                $product['network'],
                $product['product_id'],
                $product,
                $post_id
            );
            $products_with_urls[] = $product;
        }
        
        ob_start();
        $columns = 3;
        include NOAH_AFFILIATE_PATH . 'public/templates/product-grid.php';
        $products_html = ob_get_clean();
        
        // Add products at the end of content by default
        if (!empty($products_html)) {
            $content .= $products_html;
        }
        
        return $content;
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
    
    /**
     * Products shortcode - show all products from this post
     * Usage: [noah_products layout="grid|comparison" columns="3"]
     */
    public function products_shortcode($atts) {
        $atts = shortcode_atts(array(
            'layout' => 'grid',
            'columns' => '3'
        ), $atts);
        
        $post_id = get_the_ID();
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        $products = $product_manager->get_post_products($post_id);
        
        if (empty($products)) {
            return '';
        }
        
        // Add affiliate URLs to products
        foreach ($products as &$product) {
            $product['affiliate_url'] = $product_manager->generate_link(
                $product['network'],
                $product['product_id'],
                $product,
                $post_id
            );
        }
        
        if ($atts['layout'] === 'comparison') {
            return $this->render_comparison_table($products);
        } else {
            return $this->render_grid($products, $atts['columns']);
        }
    }
    
    /**
     * Grid shortcode
     * Usage: [noah_grid columns="3"]
     */
    public function grid_shortcode($atts) {
        $atts = shortcode_atts(array(
            'columns' => '3'
        ), $atts);
        
        $post_id = get_the_ID();
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        $products = $product_manager->get_post_products($post_id);
        
        if (empty($products)) {
            return '';
        }
        
        // Add affiliate URLs
        foreach ($products as &$product) {
            $product['affiliate_url'] = $product_manager->generate_link(
                $product['network'],
                $product['product_id'],
                $product,
                $post_id
            );
        }
        
        return $this->render_grid($products, $atts['columns']);
    }
    
    /**
     * Render comparison table
     */
    private function render_comparison_table($products) {
        ob_start();
        include NOAH_AFFILIATE_PATH . 'public/templates/comparison-table-advanced.php';
        return ob_get_clean();
    }
    
    /**
     * Render product grid
     */
    private function render_grid($products, $columns = 3) {
        ob_start();
        include NOAH_AFFILIATE_PATH . 'public/templates/product-grid.php';
        return ob_get_clean();
    }
}
