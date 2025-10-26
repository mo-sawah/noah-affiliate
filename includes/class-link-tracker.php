<?php
/**
 * Link Tracker Class
 * Handles click tracking and provides tracking interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Link_Tracker {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // REST API endpoint for tracking
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // AJAX endpoints for backward compatibility
        add_action('wp_ajax_noah_track_click', array($this, 'ajax_track_click'));
        add_action('wp_ajax_nopriv_noah_track_click', array($this, 'ajax_track_click'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('noah-affiliate/v1', '/track', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_track_click'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * REST API track click
     */
    public function rest_track_click($request) {
        $post_id = $request->get_param('post_id');
        $product_id = $request->get_param('product_id');
        $network = $request->get_param('network');
        
        if (!$post_id || !$product_id || !$network) {
            return new WP_Error('missing_params', 'Missing required parameters', array('status' => 400));
        }
        
        $click_id = Noah_Affiliate_Database::log_click($post_id, $product_id, $network);
        
        if ($click_id) {
            return array(
                'success' => true,
                'click_id' => $click_id
            );
        }
        
        return new WP_Error('tracking_failed', 'Failed to log click', array('status' => 500));
    }
    
    /**
     * AJAX track click
     */
    public function ajax_track_click() {
        check_ajax_referer('noah_affiliate_track', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';
        $network = isset($_POST['network']) ? sanitize_text_field($_POST['network']) : '';
        
        if (!$post_id || !$product_id || !$network) {
            wp_send_json_error(array('message' => 'Missing parameters'));
        }
        
        $click_id = Noah_Affiliate_Database::log_click($post_id, $product_id, $network);
        
        if ($click_id) {
            wp_send_json_success(array('click_id' => $click_id));
        }
        
        wp_send_json_error(array('message' => 'Failed to log click'));
    }
    
    /**
     * Get click statistics
     */
    public function get_stats($args = array()) {
        return Noah_Affiliate_Database::get_stats($args);
    }
    
    /**
     * Get total clicks
     */
    public function get_total_clicks($args = array()) {
        return Noah_Affiliate_Database::get_total_clicks($args);
    }
    
    /**
     * Get clicks by network
     */
    public function get_clicks_by_network($date_from = null, $date_to = null) {
        return Noah_Affiliate_Database::get_clicks_by_network($date_from, $date_to);
    }
    
    /**
     * Get top performing posts
     */
    public function get_top_posts($limit = 10, $date_from = null, $date_to = null) {
        return Noah_Affiliate_Database::get_top_posts($limit, $date_from, $date_to);
    }
    
    /**
     * Get post click rate
     */
    public function get_post_click_rate($post_id, $date_from = null, $date_to = null) {
        $clicks = $this->get_total_clicks(array(
            'post_id' => $post_id,
            'date_from' => $date_from,
            'date_to' => $date_to
        ));
        
        // Get post views (if analytics plugin available)
        $views = $this->get_post_views($post_id, $date_from, $date_to);
        
        if ($views > 0) {
            return round(($clicks / $views) * 100, 2);
        }
        
        return 0;
    }
    
    /**
     * Get post views (hook into popular analytics plugins)
     */
    private function get_post_views($post_id, $date_from = null, $date_to = null) {
        $views = 0;
        
        // Check for popular analytics plugins
        // WP Statistics
        if (function_exists('wp_statistics_pages')) {
            $stats = wp_statistics_pages('total', null, $post_id);
            $views = isset($stats['value']) ? $stats['value'] : 0;
        }
        // Post Views Counter
        elseif (function_exists('pvc_get_post_views')) {
            $views = pvc_get_post_views($post_id);
        }
        // Simple
        else {
            $views = get_post_meta($post_id, '_post_views', true);
            $views = $views ? intval($views) : 0;
        }
        
        return $views;
    }
    
    /**
     * Export clicks to CSV
     */
    public function export_to_csv($args = array()) {
        $clicks = $this->get_stats($args);
        
        if (empty($clicks)) {
            return false;
        }
        
        $filename = 'noah-affiliate-clicks-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, array('Date', 'Post ID', 'Post Title', 'Product ID', 'Network', 'IP Address'));
        
        // Data rows
        foreach ($clicks as $click) {
            $post_title = get_the_title($click->post_id);
            
            fputcsv($output, array(
                $click->clicked_at,
                $click->post_id,
                $post_title,
                $click->product_id,
                $click->network,
                $click->user_ip
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Clean old tracking data
     */
    public function clean_old_data() {
        $retention_days = intval(get_option('noah_affiliate_data_retention', '90'));
        return Noah_Affiliate_Database::clean_old_data($retention_days);
    }
}
