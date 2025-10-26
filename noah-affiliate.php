<?php
/**
 * Plugin Name: Noah Affiliate
 * Plugin URI: https://sawahsolutions.com
 * Description: Smart affiliate management for product reviews with auto-linking and manual product insertion
 * Version: 1.0.4
 * Author: Mohamed Sawah
 * Author URI: https://sawahsolutions.com
 * License: GPL v2 or later
 * Text Domain: noah-affiliate
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NOAH_AFFILIATE_VERSION', '1.0.4');
define('NOAH_AFFILIATE_PATH', plugin_dir_path(__FILE__));
define('NOAH_AFFILIATE_URL', plugin_dir_url(__FILE__));
define('NOAH_AFFILIATE_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation hook - must be called before class initialization
 */
function noah_affiliate_activate() {
    require_once NOAH_AFFILIATE_PATH . 'includes/class-database.php';
    Noah_Affiliate_Database::create_tables();
    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
function noah_affiliate_deactivate() {
    flush_rewrite_rules();
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'noah_affiliate_activate');
register_deactivation_hook(__FILE__, 'noah_affiliate_deactivate');

/**
 * Main Noah Affiliate Class
 */
class Noah_Affiliate {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once NOAH_AFFILIATE_PATH . 'includes/class-database.php';
        require_once NOAH_AFFILIATE_PATH . 'includes/class-link-cloaker.php';
        require_once NOAH_AFFILIATE_PATH . 'includes/class-product-manager.php';
        require_once NOAH_AFFILIATE_PATH . 'includes/class-auto-linker.php';
        require_once NOAH_AFFILIATE_PATH . 'includes/class-link-tracker.php';
        require_once NOAH_AFFILIATE_PATH . 'includes/processors/class-background-processor.php';
        
        // Network integrations
        require_once NOAH_AFFILIATE_PATH . 'includes/networks/class-network-base.php';
        require_once NOAH_AFFILIATE_PATH . 'includes/networks/class-skimlinks.php';
        require_once NOAH_AFFILIATE_PATH . 'includes/networks/class-amazon.php';
        require_once NOAH_AFFILIATE_PATH . 'includes/networks/class-awin.php';
        require_once NOAH_AFFILIATE_PATH . 'includes/networks/class-cj.php';
        require_once NOAH_AFFILIATE_PATH . 'includes/networks/class-rakuten.php';
        require_once NOAH_AFFILIATE_PATH . 'includes/networks/class-firecrawl.php';
        
        // Admin classes
        if (is_admin()) {
            require_once NOAH_AFFILIATE_PATH . 'admin/class-admin.php';
            require_once NOAH_AFFILIATE_PATH . 'admin/class-settings.php';
            require_once NOAH_AFFILIATE_PATH . 'admin/class-metaboxes.php';
            require_once NOAH_AFFILIATE_PATH . 'admin/class-analytics.php';
        }
        
        // Public classes
        require_once NOAH_AFFILIATE_PATH . 'includes/class-public.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'register_blocks'));
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'setup_rewrite_rules'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('noah-affiliate', false, dirname(NOAH_AFFILIATE_BASENAME) . '/languages');
        
        // Initialize classes
        if (is_admin()) {
            Noah_Affiliate_Admin::get_instance();
            Noah_Affiliate_Settings::get_instance();
            Noah_Affiliate_Metaboxes::get_instance();
            Noah_Affiliate_Analytics::get_instance();
        }
        
        Noah_Affiliate_Public::get_instance();
        Noah_Affiliate_Link_Cloaker::get_instance();
        Noah_Affiliate_Link_Tracker::get_instance();
        Noah_Affiliate_Background_Processor::get_instance();
    }
    
    /**
     * Register Gutenberg blocks
     */
    public function register_blocks() {
        if (function_exists('register_block_type')) {
            register_block_type(NOAH_AFFILIATE_PATH . 'blocks/product-block');
        }
    }
    
    /**
     * Register custom post types (for future use if needed)
     */
    public function register_post_types() {
        // Reserved for future extensions
    }
    
    /**
     * Setup rewrite rules for link cloaking
     */
    public function setup_rewrite_rules() {
        Noah_Affiliate_Link_Cloaker::add_rewrite_rules();
    }
}

/**
 * Get the main plugin instance
 */
function noah_affiliate() {
    return Noah_Affiliate::get_instance();
}

// Initialize the plugin
noah_affiliate();
