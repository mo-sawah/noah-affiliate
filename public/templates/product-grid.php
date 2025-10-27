<?php
/**
 * Product Grid Template
 * Display products in a responsive grid
 */

if (!defined('ABSPATH')) {
    exit;
}

// $products array is passed in
if (empty($products) || !is_array($products)) {
    return;
}

$columns = isset($columns) ? $columns : 3;
?>

<div class="noah-product-grid noah-grid-columns-<?php echo esc_attr($columns); ?>">
    <?php foreach ($products as $product): ?>
        <div class="noah-grid-item">
            <?php if (!empty($product['badge'])): ?>
                <span class="noah-badge noah-badge-<?php echo esc_attr($product['badge']); ?>">
                    <?php 
                    $badges = array(
                        'best-overall' => 'Best Overall',
                        'best-budget' => 'Best Value',
                        'best-premium' => 'PREMIUM',
                        'editors-choice' => 'Series 10'
                    );
                    echo esc_html($badges[$product['badge']] ?? '');
                    ?>
                </span>
            <?php endif; ?>
            
            <div class="noah-grid-image">
                <?php if (!empty($product['image'])): ?>
                    <img src="<?php echo esc_url($product['image']); ?>" 
                         alt="<?php echo esc_attr($product['title']); ?>">
                <?php else: ?>
                    <div class="noah-no-image">No Image</div>
                <?php endif; ?>
            </div>
            
            <div class="noah-grid-content">
                <?php if (!empty($product['merchant'])): ?>
                    <div class="noah-merchant">
                        <span class="merchant-icon">🛒</span> <?php echo esc_html($product['merchant']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($product['rating']) && $product['rating'] > 0): ?>
                    <div class="noah-rating">
                        <?php
                        $rating = floatval($product['rating']);
                        $full_stars = floor($rating);
                        $half_star = ($rating - $full_stars) >= 0.5;
                        
                        for ($i = 0; $i < $full_stars; $i++) {
                            echo '<span class="star">★</span>';
                        }
                        if ($half_star) {
                            echo '<span class="star">★</span>';
                        }
                        for ($i = 0; $i < (5 - $full_stars - ($half_star ? 1 : 0)); $i++) {
                            echo '<span class="star-empty">★</span>';
                        }
                        ?>
                        <span class="rating-number"><?php echo number_format($rating, 1); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($product['price'])): ?>
                    <div class="noah-price"><?php echo esc_html($product['price']); ?></div>
                <?php endif; ?>
                
                <h3 class="noah-grid-title"><?php echo esc_html(!empty($product['custom_title']) ? $product['custom_title'] : $product['title']); ?></h3>
                
                <?php if (!empty($product['description'])): ?>
                    <div class="noah-grid-description">
                        <ul>
                            <?php 
                            // Split description into bullet points or use first few sentences
                            $desc_lines = array_filter(explode('.', $product['description']));
                            $max_points = 4;
                            $count = 0;
                            foreach ($desc_lines as $line) {
                                if ($count >= $max_points) break;
                                $line = trim($line);
                                if (!empty($line) && strlen($line) > 10) {
                                    echo '<li>' . esc_html($line) . '</li>';
                                    $count++;
                                }
                            }
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($product['affiliate_url'])): ?>
                    <a href="<?php echo esc_url($product['affiliate_url']); ?>" 
                       class="noah-btn noah-btn-primary"
                       target="_blank" 
                       rel="nofollow sponsored"
                       data-product-id="<?php echo esc_attr($product['product_id']); ?>">
                        <?php _e('BUY NOW', 'noah-affiliate'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<p class="noah-disclosure">
    <small><em><?php 
    if (isset($disclosure_text) && !empty($disclosure_text)) {
        echo esc_html($disclosure_text);
    } else {
        echo sprintf(
            __('Amazon price updated: %s', 'noah-affiliate'),
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'))
        );
    }
    ?></em></small>
</p>
