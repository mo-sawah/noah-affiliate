<?php
/**
 * Skimlinks/Sovrn Commerce Network
 * Global auto-linking script implementation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Skimlinks extends Noah_Affiliate_Network_Base {
    
    protected $network_id = 'skimlinks';
    protected $network_name = 'Skimlinks';
    
    /**
     * Search products - Not applicable for Skimlinks (auto-linking only)
     */
    public function search_products($query, $args = array()) {
        return array();
    }
    
    /**
     * Get product - Not applicable for Skimlinks
     */
    public function get_product($product_id) {
        return false;
    }
    
    /**
     * Generate link - Not applicable for Skimlinks
     */
    public function generate_link($product_id, $product_data = array()) {
        return '';
    }
    
    /**
     * Test connection
     */
    public function test_connection() {
        $publisher_id = $this->get_setting('publisher_id');
        
        if (empty($publisher_id)) {
            return array(
                'success' => false,
                'message' => __('Publisher ID is missing', 'noah-affiliate')
            );
        }
        
        // Skimlinks doesn't have a test API, so we just validate the ID format
        if (is_numeric($publisher_id)) {
            return array(
                'success' => true,
                'message' => __('Skimlinks Publisher ID is valid', 'noah-affiliate')
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Invalid Publisher ID format', 'noah-affiliate')
        );
    }
    
    /**
     * Get Skimlinks script
     */
    public function get_script() {
        $publisher_id = $this->get_setting('publisher_id');
        
        if (empty($publisher_id)) {
            return '';
        }
        
        $script = "<script type=\"text/javascript\">\n";
        $script .= "  (function(){\n";
        $script .= "    var s = document.createElement('script');\n";
        $script .= "    s.async = true;\n";
        $script .= "    s.src = 'https://s.skimresources.com/js/" . esc_js($publisher_id) . ".skimlinks.js';\n";
        $script .= "    var x = document.getElementsByTagName('script')[0];\n";
        $script .= "    x.parentNode.insertBefore(s, x);\n";
        $script .= "  })();\n";
        $script .= "</script>";
        
        return $script;
    }
    
    /**
     * Check if Skimlinks should be loaded on current page
     */
    public function should_load_script() {
        if (!$this->is_enabled()) {
            return false;
        }
        
        // Check if disabled for current post
        if (is_singular()) {
            $disable_for_post = get_post_meta(get_the_ID(), '_noah_disable_skimlinks', true);
            if ($disable_for_post === '1') {
                return false;
            }
        }
        
        // Check excluded post types
        $excluded_types = $this->get_setting('excluded_post_types', array());
        if (is_singular() && in_array(get_post_type(), $excluded_types)) {
            return false;
        }
        
        return true;
    }
}
