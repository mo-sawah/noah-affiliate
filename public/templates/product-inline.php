<?php
/**
 * Product Inline Template
 * Compact inline product display
 */

if (!defined('ABSPATH')) {
    exit;
}

$title = !empty($custom_title) ? $custom_title : $title;
?>

<div class="noah-product-inline" data-product-id="<?php echo esc_attr($product_id); ?>" data-network="<?php echo esc_attr($network); ?>">
    <?php if ($image): ?>
        <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>" class="noah-product-image">
    <?php endif; ?>
    
    <div class="noah-product-content">
        <h4 class="noah-product-title"><?php echo esc_html($title); ?></h4>
        
        <?php if ($price): ?>
            <span class="noah-product-price"><?php echo esc_html($price); ?></span>
        <?php endif; ?>
    </div>
    
    <?php if ($affiliate_url): ?>
        <a href="<?php echo esc_url($affiliate_url); ?>" 
           class="noah-affiliate-link" 
           data-product-id="<?php echo esc_attr($product_id); ?>"
           data-network="<?php echo esc_attr($network); ?>"
           target="_blank" 
           rel="nofollow sponsored">
            <?php _e('View', 'noah-affiliate'); ?>
        </a>
    <?php endif; ?>
</div>
