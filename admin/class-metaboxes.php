<?php
/**
 * Metaboxes Class
 * Classic Editor meta boxes for product management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Metaboxes {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        $post_types = get_post_types(array('public' => true));
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'noah_affiliate_products',
                __('Affiliate Products', 'noah-affiliate'),
                array($this, 'render_products_metabox'),
                $post_type,
                'normal',
                'high'
            );
            
            add_meta_box(
                'noah_affiliate_settings',
                __('Affiliate Settings', 'noah-affiliate'),
                array($this, 'render_settings_metabox'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render products metabox
     */
    public function render_products_metabox($post) {
        wp_nonce_field('noah_affiliate_metabox', 'noah_affiliate_metabox_nonce');
        
        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
        $products = $product_manager->get_post_products($post->ID);
        $enabled_networks = $product_manager->get_enabled_networks();
        
        ?>
        <div class="noah-products-metabox">
            <!-- Product Search -->
            <div class="noah-product-search">
                <h4><?php _e('Add Products', 'noah-affiliate'); ?></h4>
                
                <div class="noah-search-form">
                    <select id="noah-network-select" class="noah-network-select">
                        <option value=""><?php _e('Select Network', 'noah-affiliate'); ?></option>
                        <?php foreach ($enabled_networks as $id => $network): 
                            if ($id === 'skimlinks') continue;
                        ?>
                            <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($network->get_name()); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="noah-country-select" class="noah-country-select" style="display: none;">
                        <option value=""><?php _e('Select Country', 'noah-affiliate'); ?></option>
                    </select>
                    
                    <input type="text" id="noah-product-search" class="noah-product-search-input" placeholder="<?php _e('Search for products...', 'noah-affiliate'); ?>">
                    <button type="button" class="button noah-search-button"><?php _e('Search', 'noah-affiliate'); ?></button>
                </div>
                
                <div class="noah-search-results"></div>
            </div>
            
            <!-- Added Products -->
            <div class="noah-added-products">
                <h4><?php _e('Added Products', 'noah-affiliate'); ?></h4>
                
                <div class="noah-products-list" id="noah-products-list">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $instance_id => $product): ?>
                            <?php $this->render_product_item($product, $instance_id); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="noah-no-products"><?php _e('No products added yet.', 'noah-affiliate'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Data (Hidden) -->
            <input type="hidden" name="noah_affiliate_products_data" id="noah-products-data" value="<?php echo esc_attr(json_encode($products)); ?>">
        </div>
        
        <style>
            .noah-product-search { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; }
            .noah-search-form { display: flex; gap: 10px; margin-top: 10px; }
            .noah-network-select { flex: 1; }
            .noah-country-select { flex: 1; }
            .noah-product-search-input { flex: 2; }
            .noah-search-results { margin-top: 15px; max-height: 300px; overflow-y: auto; }
            .noah-search-result-item { padding: 10px; background: white; border: 1px solid #ddd; margin-bottom: 10px; display: flex; gap: 10px; }
            .noah-result-image { width: 60px; height: 60px; object-fit: cover; }
            .noah-result-content { flex: 1; }
            .noah-result-title { font-weight: bold; margin-bottom: 5px; }
            .noah-result-price { color: #0073aa; font-size: 14px; }
            .noah-products-list { margin-top: 10px; }
            .noah-product-item { padding: 15px; background: white; border: 1px solid #ddd; margin-bottom: 10px; position: relative; }
            .noah-product-item.ui-sortable-helper { opacity: 0.8; cursor: move; }
            .noah-product-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
            .noah-product-controls { display: flex; gap: 5px; }
            .noah-product-body { display: flex; gap: 15px; }
            .noah-product-image { width: 80px; height: 80px; object-fit: cover; }
            .noah-product-details { flex: 1; }
            .noah-product-settings { margin-top: 10px; padding: 10px; background: #f9f9f9; border-top: 1px solid #ddd; }
            .noah-setting-row { margin-bottom: 10px; }
            .noah-setting-row label { display: block; margin-bottom: 5px; font-weight: bold; }
            .noah-badge-select { padding: 5px; }
        </style>
        <?php
    }
    
    /**
     * Render single product item
     */
    private function render_product_item($product, $instance_id) {
        $layout = isset($product['layout']) ? $product['layout'] : 'card';
        $badge = isset($product['badge']) ? $product['badge'] : '';
        $custom_title = isset($product['custom_title']) ? $product['custom_title'] : '';
        
        ?>
        <div class="noah-product-item" data-instance-id="<?php echo esc_attr($instance_id); ?>">
            <div class="noah-product-header">
                <span class="noah-drag-handle" style="cursor: move;">☰</span>
                <strong><?php echo esc_html($product['title']); ?></strong>
                <div class="noah-product-controls">
                    <button type="button" class="button-small noah-toggle-settings"><?php _e('Settings', 'noah-affiliate'); ?></button>
                    <button type="button" class="button-small noah-remove-product" style="color: #a00;"><?php _e('Remove', 'noah-affiliate'); ?></button>
                </div>
            </div>
            
            <div class="noah-product-body">
                <?php if (!empty($product['image'])): ?>
                    <img src="<?php echo esc_url($product['image']); ?>" class="noah-product-image">
                <?php endif; ?>
                
                <div class="noah-product-details">
                    <div><strong><?php _e('Network:', 'noah-affiliate'); ?></strong> <?php echo esc_html(ucfirst($product['network'])); ?></div>
                    <div><strong><?php _e('Price:', 'noah-affiliate'); ?></strong> <?php echo esc_html($product['price']); ?></div>
                    <div><strong><?php _e('Product ID:', 'noah-affiliate'); ?></strong> <?php echo esc_html($product['product_id']); ?></div>
                </div>
            </div>
            
            <div class="noah-product-settings" style="display: none;">
                <div class="noah-setting-row">
                    <label><?php _e('Layout:', 'noah-affiliate'); ?></label>
                    <select class="noah-layout-select">
                        <option value="card" <?php selected($layout, 'card'); ?>><?php _e('Card', 'noah-affiliate'); ?></option>
                        <option value="inline" <?php selected($layout, 'inline'); ?>><?php _e('Inline', 'noah-affiliate'); ?></option>
                        <option value="comparison" <?php selected($layout, 'comparison'); ?>><?php _e('Comparison Row', 'noah-affiliate'); ?></option>
                    </select>
                </div>
                
                <div class="noah-setting-row">
                    <label><?php _e('Badge:', 'noah-affiliate'); ?></label>
                    <select class="noah-badge-select">
                        <option value="" <?php selected($badge, ''); ?>><?php _e('None', 'noah-affiliate'); ?></option>
                        <option value="best-overall" <?php selected($badge, 'best-overall'); ?>><?php _e('Best Overall', 'noah-affiliate'); ?></option>
                        <option value="best-budget" <?php selected($badge, 'best-budget'); ?>><?php _e('Best Budget', 'noah-affiliate'); ?></option>
                        <option value="best-premium" <?php selected($badge, 'best-premium'); ?>><?php _e('Best Premium', 'noah-affiliate'); ?></option>
                        <option value="editors-choice" <?php selected($badge, 'editors-choice'); ?>><?php _e('Editor\'s Choice', 'noah-affiliate'); ?></option>
                    </select>
                </div>
                
                <div class="noah-setting-row">
                    <label><?php _e('Custom Title (optional):', 'noah-affiliate'); ?></label>
                    <input type="text" class="widefat noah-custom-title" value="<?php echo esc_attr($custom_title); ?>">
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings metabox
     */
    public function render_settings_metabox($post) {
        $disable_skimlinks = get_post_meta($post->ID, '_noah_disable_skimlinks', true);
        $disable_auto_link = get_post_meta($post->ID, '_noah_disable_auto_link', true);
        
        ?>
        <div class="noah-post-settings">
            <p>
                <label>
                    <input type="checkbox" name="noah_disable_skimlinks" value="1" <?php checked($disable_skimlinks, '1'); ?>>
                    <?php _e('Disable Skimlinks on this post', 'noah-affiliate'); ?>
                </label>
            </p>
            
            <p>
                <label>
                    <input type="checkbox" name="noah_disable_auto_link" value="1" <?php checked($disable_auto_link, '1'); ?>>
                    <?php _e('Disable auto-linking on this post', 'noah-affiliate'); ?>
                </label>
            </p>
        </div>
        <?php
    }
    
    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id) {
        // Security checks
        if (!isset($_POST['noah_affiliate_metabox_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['noah_affiliate_metabox_nonce'], 'noah_affiliate_metabox')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save products data
        if (isset($_POST['noah_affiliate_products_data'])) {
            $products_data = json_decode(stripslashes($_POST['noah_affiliate_products_data']), true);
            if (is_array($products_data)) {
                update_post_meta($post_id, '_noah_affiliate_products', $products_data);
            }
        }
        
        // Save settings
        update_post_meta($post_id, '_noah_disable_skimlinks', isset($_POST['noah_disable_skimlinks']) ? '1' : '0');
        update_post_meta($post_id, '_noah_disable_auto_link', isset($_POST['noah_disable_auto_link']) ? '1' : '0');
    }
}
