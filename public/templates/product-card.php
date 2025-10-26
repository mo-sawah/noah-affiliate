<?php
/**
 * Product Card Template
 * Default product display layout
 */

if (!defined('ABSPATH')) {
    exit;
}

$title = !empty($custom_title) ? $custom_title : $title;
$badge_text = '';

switch ($badge) {
    case 'best-overall':
        $badge_text = __('Best Overall', 'noah-affiliate');
        break;
    case 'best-budget':
        $badge_text = __('Best Budget', 'noah-affiliate');
        break;
    case 'best-premium':
        $badge_text = __('Best Premium', 'noah-affiliate');
        break;
    case 'editors-choice':
        $badge_text = __('Editor\'s Choice', 'noah-affiliate');
        break;
}
?>

<div class="noah-product-card" data-product-id="<?php echo esc_attr($product_id); ?>" data-network="<?php echo esc_attr($network); ?>">
    
    <?php if ($badge_text): ?>
        <span class="noah-badge <?php echo esc_attr($badge); ?>"><?php echo esc_html($badge_text); ?></span>
    <?php endif; ?>
    
    <div class="noah-product-header">
        <?php if ($image): ?>
            <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>" class="noah-product-image">
        <?php endif; ?>
        
        <div class="noah-product-content">
            <h3 class="noah-product-title">
                <?php echo esc_html($title); ?>
                <span class="noah-network-badge"><?php echo esc_html(strtoupper($network)); ?></span>
            </h3>
            
            <?php if ($rating > 0): ?>
                <div class="noah-rating">
                    <?php
                    $full_stars = floor($rating);
                    $half_star = ($rating - $full_stars) >= 0.5;
                    
                    for ($i = 0; $i < $full_stars; $i++) {
                        echo '★';
                    }
                    
                    if ($half_star) {
                        echo '½';
                    }
                    
                    for ($i = 0; $i < (5 - $full_stars - ($half_star ? 1 : 0)); $i++) {
                        echo '☆';
                    }
                    ?>
                    <?php if ($reviews > 0): ?>
                        <span class="noah-rating-count">(<?php echo number_format($reviews); ?> reviews)</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($price): ?>
                <div class="noah-product-price"><?php echo esc_html($price); ?></div>
            <?php endif; ?>
            
            <?php if ($description): ?>
                <div class="noah-product-description">
                    <?php echo wp_kses_post(wpautop($description)); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($pros) || !empty($cons)): ?>
                <div class="noah-pros-cons">
                    <?php if (!empty($pros)): ?>
                        <div class="noah-pros">
                            <strong><?php _e('Pros:', 'noah-affiliate'); ?></strong>
                            <ul>
                                <?php foreach ($pros as $pro): ?>
                                    <li><?php echo esc_html($pro); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($cons)): ?>
                        <div class="noah-cons">
                            <strong><?php _e('Cons:', 'noah-affiliate'); ?></strong>
                            <ul>
                                <?php foreach ($cons as $con): ?>
                                    <li><?php echo esc_html($con); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($affiliate_url): ?>
                <a href="<?php echo esc_url($affiliate_url); ?>" 
                   class="noah-affiliate-link" 
                   data-product-id="<?php echo esc_attr($product_id); ?>"
                   data-network="<?php echo esc_attr($network); ?>"
                   target="_blank" 
                   rel="nofollow sponsored">
                    <?php echo esc_html($cta_text ?: __('Check Price on ' . ucfirst($merchant), 'noah-affiliate')); ?>
                </a>
            <?php endif; ?>
            
            <?php if (!$availability): ?>
                <p class="noah-availability-notice" style="color: #dc3232; margin-top: 10px;">
                    <?php _e('Currently unavailable', 'noah-affiliate'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
