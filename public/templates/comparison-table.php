<?php
/**
 * Comparison Table Template
 * Side-by-side product comparison
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="noah-comparison-table">
    <table>
        <thead>
            <tr>
                <th><?php _e('Product', 'noah-affiliate'); ?></th>
                <th><?php _e('Image', 'noah-affiliate'); ?></th>
                <th><?php _e('Price', 'noah-affiliate'); ?></th>
                <th><?php _e('Rating', 'noah-affiliate'); ?></th>
                <th><?php _e('Action', 'noah-affiliate'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($product['title']); ?></strong>
                        <?php if (!empty($product['description'])): ?>
                            <br><small><?php echo esc_html(wp_trim_words($product['description'], 15)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($product['image']): ?>
                            <img src="<?php echo esc_url($product['image']); ?>" alt="<?php echo esc_attr($product['title']); ?>">
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($product['price']); ?></td>
                    <td>
                        <?php if ($product['rating'] > 0): ?>
                            <?php echo esc_html($product['rating']); ?> ★
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $product_manager = Noah_Affiliate_Product_Manager::get_instance();
                        $affiliate_url = $product_manager->generate_link(
                            $product['network'],
                            $product['id'],
                            $product,
                            get_the_ID()
                        );
                        ?>
                        <?php if ($affiliate_url): ?>
                            <a href="<?php echo esc_url($affiliate_url); ?>" 
                               class="noah-affiliate-link noah-comparison-link" 
                               data-product-id="<?php echo esc_attr($product['id']); ?>"
                               data-network="<?php echo esc_attr($product['network']); ?>"
                               target="_blank" 
                               rel="nofollow sponsored">
                                <?php _e('View Deal', 'noah-affiliate'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
