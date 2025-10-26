<?php
/**
 * Settings Class
 * Handles plugin settings and network configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting('noah_affiliate_general', 'noah_affiliate_use_cloaking');
        register_setting('noah_affiliate_general', 'noah_affiliate_link_slug');
        register_setting('noah_affiliate_general', 'noah_affiliate_redirect_type');
        register_setting('noah_affiliate_general', 'noah_affiliate_cache_duration');
        register_setting('noah_affiliate_general', 'noah_affiliate_tracking_enabled');
        register_setting('noah_affiliate_general', 'noah_affiliate_track_ip');
        register_setting('noah_affiliate_general', 'noah_affiliate_track_user_agent');
        register_setting('noah_affiliate_general', 'noah_affiliate_data_retention');
        
        // Auto-linking settings
        register_setting('noah_affiliate_auto_link', 'noah_affiliate_auto_link_enabled');
        register_setting('noah_affiliate_auto_link', 'noah_affiliate_auto_link_post_types');
        register_setting('noah_affiliate_auto_link', 'noah_affiliate_auto_link_categories');
        register_setting('noah_affiliate_auto_link', 'noah_affiliate_auto_link_max_products');
        register_setting('noah_affiliate_auto_link', 'noah_affiliate_auto_link_min_spacing');
        
        // Network settings - Amazon
        register_setting('noah_affiliate_amazon', 'noah_affiliate_amazon_settings');
        
        // Network settings - Awin
        register_setting('noah_affiliate_awin', 'noah_affiliate_awin_settings');
        
        // Network settings - CJ
        register_setting('noah_affiliate_cj', 'noah_affiliate_cj_settings');
        
        // Network settings - Rakuten
        register_setting('noah_affiliate_rakuten', 'noah_affiliate_rakuten_settings');
        
        // Network settings - Skimlinks
        register_setting('noah_affiliate_skimlinks', 'noah_affiliate_skimlinks_settings');
        
        // Network settings - Firecrawl
        register_setting('noah_affiliate_firecrawl', 'noah_affiliate_firecrawl_settings');
    }
    
    /**
     * Render settings page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle form submission
        if (isset($_POST['noah_affiliate_save_settings'])) {
            check_admin_referer('noah_affiliate_settings');
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'noah-affiliate') . '</p></div>';
        }
        
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        
        ?>
        <div class="wrap noah-affiliate-settings">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=noah-affiliate&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'noah-affiliate'); ?>
                </a>
                <a href="?page=noah-affiliate&tab=networks" class="nav-tab <?php echo $active_tab == 'networks' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Networks', 'noah-affiliate'); ?>
                </a>
                <a href="?page=noah-affiliate&tab=auto-linking" class="nav-tab <?php echo $active_tab == 'auto-linking' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Auto-Linking', 'noah-affiliate'); ?>
                </a>
            </h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('noah_affiliate_settings'); ?>
                
                <?php
                switch ($active_tab) {
                    case 'networks':
                        $this->render_networks_tab();
                        break;
                    case 'auto-linking':
                        $this->render_auto_linking_tab();
                        break;
                    default:
                        $this->render_general_tab();
                }
                ?>
                
                <p class="submit">
                    <input type="submit" name="noah_affiliate_save_settings" class="button button-primary" value="<?php _e('Save Settings', 'noah-affiliate'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render General tab
     */
    private function render_general_tab() {
        $use_cloaking = get_option('noah_affiliate_use_cloaking', '1');
        $link_slug = get_option('noah_affiliate_link_slug', 'go');
        $redirect_type = get_option('noah_affiliate_redirect_type', '302');
        $cache_duration = get_option('noah_affiliate_cache_duration', '24');
        $tracking_enabled = get_option('noah_affiliate_tracking_enabled', '1');
        $track_ip = get_option('noah_affiliate_track_ip', '1');
        $track_user_agent = get_option('noah_affiliate_track_user_agent', '1');
        $data_retention = get_option('noah_affiliate_data_retention', '90');
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Link Cloaking', 'noah-affiliate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="noah_affiliate_use_cloaking" value="1" <?php checked($use_cloaking, '1'); ?>>
                        <?php _e('Enable link cloaking (pretty URLs)', 'noah-affiliate'); ?>
                    </label>
                    <p class="description"><?php _e('Convert affiliate links to: yoursite.com/go/product-name', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Link Slug', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_link_slug" value="<?php echo esc_attr($link_slug); ?>" class="regular-text">
                    <p class="description"><?php _e('The slug to use for cloaked links (e.g., "go", "recommends", "link")', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Redirect Type', 'noah-affiliate'); ?></th>
                <td>
                    <select name="noah_affiliate_redirect_type">
                        <option value="301" <?php selected($redirect_type, '301'); ?>>301 (Permanent)</option>
                        <option value="302" <?php selected($redirect_type, '302'); ?>>302 (Temporary)</option>
                        <option value="307" <?php selected($redirect_type, '307'); ?>>307 (Temporary)</option>
                    </select>
                    <p class="description"><?php _e('Recommended: 302 for better tracking', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Cache Duration', 'noah-affiliate'); ?></th>
                <td>
                    <input type="number" name="noah_affiliate_cache_duration" value="<?php echo esc_attr($cache_duration); ?>" min="1" max="168"> hours
                    <p class="description"><?php _e('How long to cache product data before refreshing', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Click Tracking', 'noah-affiliate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="noah_affiliate_tracking_enabled" value="1" <?php checked($tracking_enabled, '1'); ?>>
                        <?php _e('Enable click tracking', 'noah-affiliate'); ?>
                    </label>
                    <p class="description"><?php _e('Track affiliate link clicks for analytics', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Track IP Addresses', 'noah-affiliate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="noah_affiliate_track_ip" value="1" <?php checked($track_ip, '1'); ?>>
                        <?php _e('Store visitor IP addresses', 'noah-affiliate'); ?>
                    </label>
                    <p class="description"><?php _e('May have privacy implications depending on your location', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Track User Agents', 'noah-affiliate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="noah_affiliate_track_user_agent" value="1" <?php checked($track_user_agent, '1'); ?>>
                        <?php _e('Store visitor user agents', 'noah-affiliate'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Data Retention', 'noah-affiliate'); ?></th>
                <td>
                    <input type="number" name="noah_affiliate_data_retention" value="<?php echo esc_attr($data_retention); ?>" min="7" max="365"> days
                    <p class="description"><?php _e('Automatically delete tracking data older than this many days', 'noah-affiliate'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Networks tab
     */
    private function render_networks_tab() {
        $active_network = isset($_GET['network']) ? $_GET['network'] : 'amazon';
        
        ?>
        <div class="noah-networks-tabs">
            <ul class="subsubsub">
                <li><a href="?page=noah-affiliate&tab=networks&network=amazon" class="<?php echo $active_network == 'amazon' ? 'current' : ''; ?>">Amazon</a> | </li>
                <li><a href="?page=noah-affiliate&tab=networks&network=awin" class="<?php echo $active_network == 'awin' ? 'current' : ''; ?>">Awin</a> | </li>
                <li><a href="?page=noah-affiliate&tab=networks&network=cj" class="<?php echo $active_network == 'cj' ? 'current' : ''; ?>">CJ</a> | </li>
                <li><a href="?page=noah-affiliate&tab=networks&network=rakuten" class="<?php echo $active_network == 'rakuten' ? 'current' : ''; ?>">Rakuten</a> | </li>
                <li><a href="?page=noah-affiliate&tab=networks&network=skimlinks" class="<?php echo $active_network == 'skimlinks' ? 'current' : ''; ?>">Skimlinks</a> | </li>
                <li><a href="?page=noah-affiliate&tab=networks&network=firecrawl" class="<?php echo $active_network == 'firecrawl' ? 'current' : ''; ?>">Firecrawl</a></li>
            </ul>
            
            <div class="clear"></div>
            
            <?php
            switch ($active_network) {
                case 'awin':
                    $this->render_awin_settings();
                    break;
                case 'cj':
                    $this->render_cj_settings();
                    break;
                case 'rakuten':
                    $this->render_rakuten_settings();
                    break;
                case 'skimlinks':
                    $this->render_skimlinks_settings();
                    break;
                case 'firecrawl':
                    $this->render_firecrawl_settings();
                    break;
                default:
                    $this->render_amazon_settings();
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Render Amazon settings
     */
    private function render_amazon_settings() {
        $settings = get_option('noah_affiliate_amazon_settings', array());
        $enabled = isset($settings['enabled']) ? $settings['enabled'] : '0';
        $access_key = isset($settings['access_key']) ? $settings['access_key'] : '';
        $secret_key = isset($settings['secret_key']) ? $settings['secret_key'] : '';
        $associate_tag = isset($settings['associate_tag']) ? $settings['associate_tag'] : '';
        $default_locale = isset($settings['default_locale']) ? $settings['default_locale'] : 'US';
        
        $locales = array('US', 'UK', 'DE', 'FR', 'IT', 'ES', 'CA', 'JP', 'AU', 'IN', 'BR', 'MX');
        
        ?>
        <h3><?php _e('Amazon Product Advertising API', 'noah-affiliate'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Amazon', 'noah-affiliate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="noah_affiliate_amazon_settings[enabled]" value="1" <?php checked($enabled, '1'); ?>>
                        <?php _e('Enable Amazon Associates integration', 'noah-affiliate'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Access Key', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_amazon_settings[access_key]" value="<?php echo esc_attr($access_key); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Amazon PA-API Access Key', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Secret Key', 'noah-affiliate'); ?></th>
                <td>
                    <input type="password" name="noah_affiliate_amazon_settings[secret_key]" value="<?php echo esc_attr($secret_key); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Amazon PA-API Secret Key', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Default Locale', 'noah-affiliate'); ?></th>
                <td>
                    <select name="noah_affiliate_amazon_settings[default_locale]">
                        <?php foreach ($locales as $locale): ?>
                            <option value="<?php echo $locale; ?>" <?php selected($default_locale, $locale); ?>><?php echo $locale; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            
            <?php foreach ($locales as $locale): 
                $tag = isset($settings['associate_tag_' . $locale]) ? $settings['associate_tag_' . $locale] : '';
            ?>
            <tr>
                <th scope="row"><?php echo sprintf(__('Associate Tag (%s)', 'noah-affiliate'), $locale); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_amazon_settings[associate_tag_<?php echo $locale; ?>]" value="<?php echo esc_attr($tag); ?>" class="regular-text">
                </td>
            </tr>
            <?php endforeach; ?>
            
            <tr>
                <th scope="row"><?php _e('Test Connection', 'noah-affiliate'); ?></th>
                <td>
                    <button type="button" class="button noah-test-connection" data-network="amazon"><?php _e('Test Amazon API', 'noah-affiliate'); ?></button>
                    <span class="noah-test-result"></span>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Awin settings
     */
    private function render_awin_settings() {
        $settings = get_option('noah_affiliate_awin_settings', array());
        $enabled = isset($settings['enabled']) ? $settings['enabled'] : '0';
        $publisher_id = isset($settings['publisher_id']) ? $settings['publisher_id'] : '';
        $api_token = isset($settings['api_token']) ? $settings['api_token'] : '';
        
        ?>
        <h3><?php _e('Awin Affiliate Network', 'noah-affiliate'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Awin', 'noah-affiliate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="noah_affiliate_awin_settings[enabled]" value="1" <?php checked($enabled, '1'); ?>>
                        <?php _e('Enable Awin integration', 'noah-affiliate'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Publisher ID', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_awin_settings[publisher_id]" value="<?php echo esc_attr($publisher_id); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('API Token', 'noah-affiliate'); ?></th>
                <td>
                    <input type="password" name="noah_affiliate_awin_settings[api_token]" value="<?php echo esc_attr($api_token); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Test Connection', 'noah-affiliate'); ?></th>
                <td>
                    <button type="button" class="button noah-test-connection" data-network="awin"><?php _e('Test Awin API', 'noah-affiliate'); ?></button>
                    <span class="noah-test-result"></span>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render CJ settings
     */
    private function render_cj_settings() {
        $settings = get_option('noah_affiliate_cj_settings', array());
        $enabled = isset($settings['enabled']) ? $settings['enabled'] : '0';
        $website_id = isset($settings['website_id']) ? $settings['website_id'] : '';
        $api_token = isset($settings['api_token']) ? $settings['api_token'] : '';
        
        ?>
        <h3><?php _e('CJ (Commission Junction)', 'noah-affiliate'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable CJ', 'noah-affiliate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="noah_affiliate_cj_settings[enabled]" value="1" <?php checked($enabled, '1'); ?>>
                        <?php _e('Enable CJ integration', 'noah-affiliate'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Website ID', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_cj_settings[website_id]" value="<?php echo esc_attr($website_id); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('API Token', 'noah-affiliate'); ?></th>
                <td>
                    <input type="password" name="noah_affiliate_cj_settings[api_token]" value="<?php echo esc_attr($api_token); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Test Connection', 'noah-affiliate'); ?></th>
                <td>
                    <button type="button" class="button noah-test-connection" data-network="cj"><?php _e('Test CJ API', 'noah-affiliate'); ?></button>
                    <span class="noah-test-result"></span>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Rakuten settings
     */
    private function render_rakuten_settings() {
        $settings = get_option('noah_affiliate_rakuten_settings', array());
        $enabled = isset($settings['enabled']) ? $settings['enabled'] : '0';
        $sid = isset($settings['sid']) ? $settings['sid'] : '';
        $api_token = isset($settings['api_token']) ? $settings['api_token'] : '';
        
        ?>
        <h3><?php _e('Rakuten Advertising', 'noah-affiliate'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Rakuten', 'noah-affiliate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="noah_affiliate_rakuten_settings[enabled]" value="1" <?php checked($enabled, '1'); ?>>
                        <?php _e('Enable Rakuten integration', 'noah-affiliate'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('SID (Site ID)', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_rakuten_settings[sid]" value="<?php echo esc_attr($sid); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('API Token', 'noah-affiliate'); ?></th>
                <td>
                    <input type="password" name="noah_affiliate_rakuten_settings[api_token]" value="<?php echo esc_attr($api_token); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Test Connection', 'noah-affiliate'); ?></th>
                <td>
                    <button type="button" class="button noah-test-connection" data-network="rakuten"><?php _e('Test Rakuten API', 'noah-affiliate'); ?></button>
                    <span class="noah-test-result"></span>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Skimlinks settings
     */
    private function render_skimlinks_settings() {
        $settings = get_option('noah_affiliate_skimlinks_settings', array());
        $enabled = isset($settings['enabled']) ? $settings['enabled'] : '0';
        $publisher_id = isset($settings['publisher_id']) ? $settings['publisher_id'] : '';
        $excluded_post_types = isset($settings['excluded_post_types']) ? $settings['excluded_post_types'] : array();
        
        ?>
        <h3><?php _e('Skimlinks/Sovrn Commerce', 'noah-affiliate'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Skimlinks', 'noah-affiliate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="noah_affiliate_skimlinks_settings[enabled]" value="1" <?php checked($enabled, '1'); ?>>
                        <?php _e('Enable Skimlinks auto-linking', 'noah-affiliate'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Publisher ID', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_skimlinks_settings[publisher_id]" value="<?php echo esc_attr($publisher_id); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Skimlinks Publisher ID', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Excluded Post Types', 'noah-affiliate'); ?></th>
                <td>
                    <?php
                    $post_types = get_post_types(array('public' => true), 'objects');
                    foreach ($post_types as $post_type):
                        $checked = in_array($post_type->name, $excluded_post_types);
                    ?>
                        <label>
                            <input type="checkbox" name="noah_affiliate_skimlinks_settings[excluded_post_types][]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked($checked); ?>>
                            <?php echo esc_html($post_type->label); ?>
                        </label><br>
                    <?php endforeach; ?>
                    <p class="description"><?php _e('Skimlinks will not load on these post types', 'noah-affiliate'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Firecrawl settings
     */
    private function render_firecrawl_settings() {
        $settings = get_option('noah_affiliate_firecrawl_settings', array());
        $enabled = isset($settings['enabled']) ? $settings['enabled'] : '0';
        $api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
        $base_url = isset($settings['base_url']) ? $settings['base_url'] : '';
        $search_url_template = isset($settings['search_url_template']) ? $settings['search_url_template'] : '';
        $test_url = isset($settings['test_url']) ? $settings['test_url'] : 'https://www.example.com';
        
        // Affiliate link parameters
        $affiliate_param_name = isset($settings['affiliate_param_name']) ? $settings['affiliate_param_name'] : 'ref';
        $affiliate_param_value = isset($settings['affiliate_param_value']) ? $settings['affiliate_param_value'] : '';
        
        // CSS Selectors
        $search_title_selector = isset($settings['search_title_selector']) ? $settings['search_title_selector'] : 'h2 a, h3 a';
        $search_price_selector = isset($settings['search_price_selector']) ? $settings['search_price_selector'] : '.price, [class*="price"]';
        $search_image_selector = isset($settings['search_image_selector']) ? $settings['search_image_selector'] : 'img';
        $search_link_selector = isset($settings['search_link_selector']) ? $settings['search_link_selector'] : 'a[href*="/product/"]';
        
        $product_title_selector = isset($settings['product_title_selector']) ? $settings['product_title_selector'] : 'h1';
        $product_price_selector = isset($settings['product_price_selector']) ? $settings['product_price_selector'] : '.price';
        $product_description_selector = isset($settings['product_description_selector']) ? $settings['product_description_selector'] : '.description';
        $product_image_selector = isset($settings['product_image_selector']) ? $settings['product_image_selector'] : '.product-image img';
        
        ?>
        <h3><?php _e('Firecrawl Web Scraping', 'noah-affiliate'); ?></h3>
        <p class="description"><?php _e('Use Firecrawl API to scrape product information from any website. Perfect for when you don\'t have API access.', 'noah-affiliate'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Firecrawl', 'noah-affiliate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="noah_affiliate_firecrawl_settings[enabled]" value="1" <?php checked($enabled, '1'); ?>>
                        <?php _e('Enable Firecrawl integration', 'noah-affiliate'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('API Key', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[api_key]" value="<?php echo esc_attr($api_key); ?>" class="regular-text" placeholder="fc-xxxxxxxxxxxxx">
                    <p class="description">
                        <?php _e('Your Firecrawl API key. Get one at', 'noah-affiliate'); ?> 
                        <a href="https://firecrawl.dev" target="_blank">firecrawl.dev</a>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Base URL', 'noah-affiliate'); ?></th>
                <td>
                    <input type="url" name="noah_affiliate_firecrawl_settings[base_url]" value="<?php echo esc_attr($base_url); ?>" class="regular-text" placeholder="https://www.example.com">
                    <p class="description"><?php _e('The base URL of the website you want to scrape (e.g., https://www.ebay.com)', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Search URL Template', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[search_url_template]" value="<?php echo esc_attr($search_url_template); ?>" class="large-text" placeholder="https://www.example.com/search?q={query}">
                    <p class="description"><?php _e('Search URL with {query} placeholder. Example: https://www.ebay.com/sch/i.html?_nkw={query}', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Test URL', 'noah-affiliate'); ?></th>
                <td>
                    <input type="url" name="noah_affiliate_firecrawl_settings[test_url]" value="<?php echo esc_attr($test_url); ?>" class="regular-text">
                    <p class="description"><?php _e('URL to test the connection (any page from the target website)', 'noah-affiliate'); ?></p>
                </td>
            </tr>
        </table>
        
        <h4><?php _e('Affiliate Link Settings', 'noah-affiliate'); ?></h4>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Affiliate Parameter Name', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[affiliate_param_name]" value="<?php echo esc_attr($affiliate_param_name); ?>" class="regular-text" placeholder="ref">
                    <p class="description"><?php _e('URL parameter name for your affiliate ID (e.g., "ref", "tag", "affiliate_id")', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Affiliate Parameter Value', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[affiliate_param_value]" value="<?php echo esc_attr($affiliate_param_value); ?>" class="regular-text" placeholder="your-affiliate-id">
                    <p class="description"><?php _e('Your affiliate ID or tracking code', 'noah-affiliate'); ?></p>
                </td>
            </tr>
        </table>
        
        <h4><?php _e('CSS Selectors (Advanced)', 'noah-affiliate'); ?></h4>
        <p class="description"><?php _e('Customize these selectors to match the target website structure. Leave default if unsure.', 'noah-affiliate'); ?></p>
        
        <table class="form-table">
            <tr>
                <th colspan="2"><strong><?php _e('Search Results Page Selectors', 'noah-affiliate'); ?></strong></th>
            </tr>
            <tr>
                <th scope="row"><?php _e('Title Selector', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[search_title_selector]" value="<?php echo esc_attr($search_title_selector); ?>" class="large-text">
                    <p class="description"><?php _e('CSS selector for product titles in search results', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Price Selector', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[search_price_selector]" value="<?php echo esc_attr($search_price_selector); ?>" class="large-text">
                    <p class="description"><?php _e('CSS selector for product prices in search results', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Image Selector', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[search_image_selector]" value="<?php echo esc_attr($search_image_selector); ?>" class="large-text">
                    <p class="description"><?php _e('CSS selector for product images in search results', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Link Selector', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[search_link_selector]" value="<?php echo esc_attr($search_link_selector); ?>" class="large-text">
                    <p class="description"><?php _e('CSS selector for product links in search results', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th colspan="2"><strong><?php _e('Product Page Selectors', 'noah-affiliate'); ?></strong></th>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Title Selector', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[product_title_selector]" value="<?php echo esc_attr($product_title_selector); ?>" class="large-text">
                    <p class="description"><?php _e('CSS selector for product title on product page', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Price Selector', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[product_price_selector]" value="<?php echo esc_attr($product_price_selector); ?>" class="large-text">
                    <p class="description"><?php _e('CSS selector for product price on product page', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Description Selector', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[product_description_selector]" value="<?php echo esc_attr($product_description_selector); ?>" class="large-text">
                    <p class="description"><?php _e('CSS selector for product description', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Image Selector', 'noah-affiliate'); ?></th>
                <td>
                    <input type="text" name="noah_affiliate_firecrawl_settings[product_image_selector]" value="<?php echo esc_attr($product_image_selector); ?>" class="large-text">
                    <p class="description"><?php _e('CSS selector for product main image', 'noah-affiliate'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Auto-Linking tab
     */
    private function render_auto_linking_tab() {
        $enabled = get_option('noah_affiliate_auto_link_enabled', '0');
        $post_types = get_option('noah_affiliate_auto_link_post_types', array('post'));
        $categories = get_option('noah_affiliate_auto_link_categories', array());
        $max_products = get_option('noah_affiliate_auto_link_max_products', '5');
        $min_spacing = get_option('noah_affiliate_auto_link_min_spacing', '3');
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable Auto-Linking', 'noah-affiliate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="noah_affiliate_auto_link_enabled" value="1" <?php checked($enabled, '1'); ?>>
                        <?php _e('Automatically add relevant products to posts', 'noah-affiliate'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Post Types', 'noah-affiliate'); ?></th>
                <td>
                    <?php
                    $all_post_types = get_post_types(array('public' => true), 'objects');
                    foreach ($all_post_types as $post_type):
                        $checked = in_array($post_type->name, $post_types);
                    ?>
                        <label>
                            <input type="checkbox" name="noah_affiliate_auto_link_post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked($checked); ?>>
                            <?php echo esc_html($post_type->label); ?>
                        </label><br>
                    <?php endforeach; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Categories', 'noah-affiliate'); ?></th>
                <td>
                    <?php
                    $all_categories = get_categories(array('hide_empty' => false));
                    foreach ($all_categories as $category):
                        $checked = in_array($category->term_id, $categories);
                    ?>
                        <label>
                            <input type="checkbox" name="noah_affiliate_auto_link_categories[]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked($checked); ?>>
                            <?php echo esc_html($category->name); ?>
                        </label><br>
                    <?php endforeach; ?>
                    <p class="description"><?php _e('Leave empty to auto-link all categories', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Max Products Per Post', 'noah-affiliate'); ?></th>
                <td>
                    <input type="number" name="noah_affiliate_auto_link_max_products" value="<?php echo esc_attr($max_products); ?>" min="1" max="20">
                    <p class="description"><?php _e('Maximum number of products to automatically insert', 'noah-affiliate'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Minimum Paragraph Spacing', 'noah-affiliate'); ?></th>
                <td>
                    <input type="number" name="noah_affiliate_auto_link_min_spacing" value="<?php echo esc_attr($min_spacing); ?>" min="1" max="10">
                    <p class="description"><?php _e('Minimum paragraphs between auto-inserted products', 'noah-affiliate'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        // Save general settings
        update_option('noah_affiliate_use_cloaking', isset($_POST['noah_affiliate_use_cloaking']) ? '1' : '0');
        update_option('noah_affiliate_link_slug', sanitize_text_field($_POST['noah_affiliate_link_slug']));
        update_option('noah_affiliate_redirect_type', sanitize_text_field($_POST['noah_affiliate_redirect_type']));
        update_option('noah_affiliate_cache_duration', absint($_POST['noah_affiliate_cache_duration']));
        update_option('noah_affiliate_tracking_enabled', isset($_POST['noah_affiliate_tracking_enabled']) ? '1' : '0');
        update_option('noah_affiliate_track_ip', isset($_POST['noah_affiliate_track_ip']) ? '1' : '0');
        update_option('noah_affiliate_track_user_agent', isset($_POST['noah_affiliate_track_user_agent']) ? '1' : '0');
        update_option('noah_affiliate_data_retention', absint($_POST['noah_affiliate_data_retention']));
        
        // Save network settings
        if (isset($_POST['noah_affiliate_amazon_settings'])) {
            update_option('noah_affiliate_amazon_settings', array_map('sanitize_text_field', $_POST['noah_affiliate_amazon_settings']));
        }
        
        if (isset($_POST['noah_affiliate_awin_settings'])) {
            update_option('noah_affiliate_awin_settings', array_map('sanitize_text_field', $_POST['noah_affiliate_awin_settings']));
        }
        
        if (isset($_POST['noah_affiliate_cj_settings'])) {
            update_option('noah_affiliate_cj_settings', array_map('sanitize_text_field', $_POST['noah_affiliate_cj_settings']));
        }
        
        if (isset($_POST['noah_affiliate_rakuten_settings'])) {
            update_option('noah_affiliate_rakuten_settings', array_map('sanitize_text_field', $_POST['noah_affiliate_rakuten_settings']));
        }
        
        if (isset($_POST['noah_affiliate_skimlinks_settings'])) {
            $skimlinks = $_POST['noah_affiliate_skimlinks_settings'];
            if (isset($skimlinks['excluded_post_types']) && is_array($skimlinks['excluded_post_types'])) {
                $skimlinks['excluded_post_types'] = array_map('sanitize_text_field', $skimlinks['excluded_post_types']);
            }
            update_option('noah_affiliate_skimlinks_settings', $skimlinks);
        }
        
        if (isset($_POST['noah_affiliate_firecrawl_settings'])) {
            update_option('noah_affiliate_firecrawl_settings', array_map('sanitize_text_field', $_POST['noah_affiliate_firecrawl_settings']));
        }
        
        // Save auto-linking settings
        update_option('noah_affiliate_auto_link_enabled', isset($_POST['noah_affiliate_auto_link_enabled']) ? '1' : '0');
        
        if (isset($_POST['noah_affiliate_auto_link_post_types'])) {
            update_option('noah_affiliate_auto_link_post_types', array_map('sanitize_text_field', $_POST['noah_affiliate_auto_link_post_types']));
        }
        
        if (isset($_POST['noah_affiliate_auto_link_categories'])) {
            update_option('noah_affiliate_auto_link_categories', array_map('absint', $_POST['noah_affiliate_auto_link_categories']));
        }
        
        update_option('noah_affiliate_auto_link_max_products', absint($_POST['noah_affiliate_auto_link_max_products']));
        update_option('noah_affiliate_auto_link_min_spacing', absint($_POST['noah_affiliate_auto_link_min_spacing']));
        
        // Flush rewrite rules if link slug changed
        flush_rewrite_rules();
    }
}
