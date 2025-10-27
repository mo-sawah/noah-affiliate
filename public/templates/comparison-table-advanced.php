<?php
/**
 * Comparison Table Template
 * Side-by-side product comparison
 */

if (!defined('ABSPATH')) {
    exit;
}

// $products array is passed in
if (empty($products) || !is_array($products)) {
    return;
}
?>

<div class="noah-comparison-table-wrapper">
    <div class="noah-comparison-table">
        <div class="noah-comparison-row noah-comparison-images">
            <?php foreach ($products as $product): ?>
                <div class="noah-comparison-cell">
                    <?php if (!empty($product['badge'])): ?>
                        <span class="noah-badge noah-badge-<?php echo esc_attr($product['badge']); ?>">
                            <?php 
                            $badges = array(
                                'best-overall' => 'Best Overall',
                                'best-budget' => 'Best Value',
                                'best-premium' => 'Premium',
                                'editors-choice' => 'Editor\'s Choice'
                            );
                            echo esc_html($badges[$product['badge']] ?? '');
                            ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['image'])): ?>
                        <img src="<?php echo esc_url($product['image']); ?>" 
                             alt="<?php echo esc_attr($product['title']); ?>"
                             class="noah-comparison-image">
                    <?php else: ?>
                        <div class="noah-no-image">
                            <svg width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="#f0f0f0"/><text x="50" y="50" text-anchor="middle" dy=".3em" fill="#999">No Image</text></svg>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="noah-comparison-row noah-comparison-titles">
            <?php foreach ($products as $product): ?>
                <div class="noah-comparison-cell">
                    <h3><?php echo esc_html(!empty($product['custom_title']) ? $product['custom_title'] : $product['title']); ?></h3>
                    <?php if (!empty($product['merchant'])): ?>
                        <span class="noah-merchant"><?php echo esc_html($product['merchant']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="noah-comparison-row noah-comparison-ratings">
            <?php foreach ($products as $product): ?>
                <div class="noah-comparison-cell">
                    <?php if (!empty($product['rating']) && $product['rating'] > 0): ?>
                        <div class="noah-rating">
                            <?php
                            $rating = floatval($product['rating']);
                            $full_stars = floor($rating);
                            $half_star = ($rating - $full_stars) >= 0.5;
                            
                            for ($i = 0; $i < $full_stars; $i++) {
                                echo '<span class="star-full">★</span>';
                            }
                            if ($half_star) {
                                echo '<span class="star-half">★</span>';
                            }
                            for ($i = 0; $i < (5 - $full_stars - ($half_star ? 1 : 0)); $i++) {
                                echo '<span class="star-empty">☆</span>';
                            }
                            ?>
                            <span class="rating-number"><?php echo number_format($rating, 1); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="noah-comparison-row noah-comparison-prices">
            <?php foreach ($products as $product): ?>
                <div class="noah-comparison-cell">
                    <?php if (!empty($product['price'])): ?>
                        <div class="noah-price"><?php echo esc_html($product['price']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="noah-comparison-row noah-comparison-descriptions">
            <?php foreach ($products as $product): ?>
                <div class="noah-comparison-cell">
                    <?php if (!empty($product['description'])): ?>
                        <p><?php echo esc_html(wp_trim_words($product['description'], 20)); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="noah-comparison-row noah-comparison-buttons">
            <?php foreach ($products as $product): ?>
                <div class="noah-comparison-cell">
                    <?php if (!empty($product['affiliate_url'])): ?>
                        <a href="<?php echo esc_url($product['affiliate_url']); ?>" 
                           class="noah-btn noah-btn-primary"
                           target="_blank" 
                           rel="nofollow sponsored"
                           data-product-id="<?php echo esc_attr($product['product_id']); ?>">
                            <?php _e('BUY NOW', 'noah-affiliate'); ?>
                        </a>
                        <?php if (!empty($product['merchant'])): ?>
                            <small class="noah-merchant-link">at <?php echo esc_html($product['merchant']); ?></small>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <p class="noah-disclosure">
        <small><em><?php _e('I may earn a commission at no cost to you.', 'noah-affiliate'); ?></em></small>
    </p>
</div>
