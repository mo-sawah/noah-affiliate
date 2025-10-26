<?php
/**
 * Background Processor Class
 * Handles queued background tasks like auto-linking and product refresh
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Background_Processor {
    
    private static $instance = null;
    private $queue_option = 'noah_affiliate_queue';
    private $processing_option = 'noah_affiliate_processing';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        
        // Register cron hooks
        add_action('noah_affiliate_process_queue', array($this, 'process_queue'));
        add_action('noah_affiliate_refresh_products', array($this, 'refresh_products'));
        add_action('noah_affiliate_cleanup', array($this, 'cleanup'));
        
        // Schedule cron events if not scheduled
        if (!wp_next_scheduled('noah_affiliate_process_queue')) {
            wp_schedule_event(time(), 'noah_every_5_minutes', 'noah_affiliate_process_queue');
        }
        
        if (!wp_next_scheduled('noah_affiliate_refresh_products')) {
            wp_schedule_event(time(), 'hourly', 'noah_affiliate_refresh_products');
        }
        
        if (!wp_next_scheduled('noah_affiliate_cleanup')) {
            wp_schedule_event(time(), 'daily', 'noah_affiliate_cleanup');
        }
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['noah_every_5_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'noah-affiliate')
        );
        
        return $schedules;
    }
    
    /**
     * Push task to queue
     */
    public function push_to_queue($task) {
        $queue = get_option($this->queue_option, array());
        
        $queue[] = array(
            'task' => $task,
            'added_at' => time()
        );
        
        update_option($this->queue_option, $queue);
        
        return $this;
    }
    
    /**
     * Save queue
     */
    public function save() {
        return $this;
    }
    
    /**
     * Dispatch queue processing
     */
    public function dispatch() {
        // Trigger immediate processing if not already processing
        if (!get_transient($this->processing_option)) {
            wp_schedule_single_event(time(), 'noah_affiliate_process_queue');
        }
        
        return $this;
    }
    
    /**
     * Process queue
     */
    public function process_queue() {
        // Check if already processing
        if (get_transient($this->processing_option)) {
            return;
        }
        
        // Set processing flag (5 minutes)
        set_transient($this->processing_option, true, 300);
        
        $queue = get_option($this->queue_option, array());
        
        if (empty($queue)) {
            delete_transient($this->processing_option);
            return;
        }
        
        $processed = 0;
        $max_per_batch = 5;
        
        foreach ($queue as $index => $item) {
            if ($processed >= $max_per_batch) {
                break;
            }
            
            $this->process_task($item['task']);
            
            unset($queue[$index]);
            $processed++;
        }
        
        // Re-index array
        $queue = array_values($queue);
        
        // Update queue
        update_option($this->queue_option, $queue);
        
        // Release processing flag
        delete_transient($this->processing_option);
        
        // If more items in queue, schedule next batch
        if (!empty($queue)) {
            wp_schedule_single_event(time() + 60, 'noah_affiliate_process_queue');
        }
    }
    
    /**
     * Process individual task
     */
    private function process_task($task) {
        if (!isset($task['action'])) {
            return false;
        }
        
        switch ($task['action']) {
            case 'auto_link':
                if (isset($task['post_id'])) {
                    $auto_linker = Noah_Affiliate_Auto_Linker::get_instance();
                    return $auto_linker->process_post($task['post_id']);
                }
                break;
                
            case 'refresh_product':
                if (isset($task['post_id']) && isset($task['instance_id'])) {
                    $product_manager = Noah_Affiliate_Product_Manager::get_instance();
                    return $product_manager->refresh_product_data($task['post_id'], $task['instance_id']);
                }
                break;
                
            default:
                // Allow custom task processing
                do_action('noah_affiliate_process_task', $task);
                break;
        }
        
        return false;
    }
    
    /**
     * Refresh product data
     */
    public function refresh_products() {
        global $wpdb;
        
        // Find all posts with affiliate products
        $posts = $wpdb->get_col("
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_noah_affiliate_products'
            LIMIT 50
        ");
        
        if (empty($posts)) {
            return;
        }
        
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        
        foreach ($posts as $post_id) {
            $products = $product_manager->get_post_products($post_id);
            
            foreach ($products as $instance_id => $product) {
                // Check if product data is stale (>24 hours)
                $updated_at = isset($product['updated_at']) ? strtotime($product['updated_at']) : 0;
                $age_hours = ($updated_at > 0) ? (time() - $updated_at) / 3600 : 999;
                
                if ($age_hours >= 24) {
                    // Queue for refresh
                    $this->push_to_queue(array(
                        'action' => 'refresh_product',
                        'post_id' => $post_id,
                        'instance_id' => $instance_id
                    ));
                }
            }
        }
        
        $this->save()->dispatch();
    }
    
    /**
     * Cleanup old data
     */
    public function cleanup() {
        // Clean old click tracking data
        $tracker = Noah_Affiliate_Link_Tracker::get_instance();
        $tracker->clean_old_data();
        
        // Clean expired transients
        $this->clean_expired_transients();
    }
    
    /**
     * Clean expired transients
     */
    private function clean_expired_transients() {
        global $wpdb;
        
        $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_noah_%'
            OR option_name LIKE '_transient_timeout_noah_%'
        ");
    }
    
    /**
     * Get queue size
     */
    public function get_queue_size() {
        $queue = get_option($this->queue_option, array());
        return count($queue);
    }
    
    /**
     * Clear queue
     */
    public function clear_queue() {
        delete_option($this->queue_option);
        delete_transient($this->processing_option);
    }
}
