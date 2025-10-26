<?php
/**
 * Analytics Class
 * Displays click tracking and performance analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Analytics {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Render analytics page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle export
        if (isset($_GET['action']) && $_GET['action'] === 'export') {
            check_admin_referer('noah_export_clicks');
            $tracker = Noah_Affiliate_Link_Tracker::get_instance();
            $tracker->export_to_csv();
            exit;
        }
        
        $tracker = Noah_Affiliate_Link_Tracker::get_instance();
        
        // Date range
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
        
        // Get statistics
        $total_clicks = $tracker->get_total_clicks(array(
            'date_from' => $date_from . ' 00:00:00',
            'date_to' => $date_to . ' 23:59:59'
        ));
        
        $clicks_by_network = $tracker->get_clicks_by_network(
            $date_from . ' 00:00:00',
            $date_to . ' 23:59:59'
        );
        
        $top_posts = $tracker->get_top_posts(10, $date_from . ' 00:00:00', $date_to . ' 23:59:59');
        
        ?>
        <div class="wrap noah-analytics">
            <h1><?php _e('Affiliate Analytics', 'noah-affiliate'); ?></h1>
            
            <!-- Date Filter -->
            <div class="noah-date-filter">
                <form method="get">
                    <input type="hidden" name="page" value="noah-affiliate-analytics">
                    <label><?php _e('From:', 'noah-affiliate'); ?></label>
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                    
                    <label><?php _e('To:', 'noah-affiliate'); ?></label>
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                    
                    <button type="submit" class="button"><?php _e('Filter', 'noah-affiliate'); ?></button>
                    
                    <a href="<?php echo wp_nonce_url(add_query_arg('action', 'export'), 'noah_export_clicks'); ?>" class="button" style="margin-left: 10px;">
                        <?php _e('Export CSV', 'noah-affiliate'); ?>
                    </a>
                </form>
            </div>
            
            <!-- Summary Cards -->
            <div class="noah-summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="noah-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h3><?php _e('Total Clicks', 'noah-affiliate'); ?></h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo number_format($total_clicks); ?></p>
                </div>
                
                <?php foreach ($clicks_by_network as $network_stat): ?>
                <div class="noah-card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h3><?php echo esc_html(ucfirst($network_stat->network)); ?></h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo number_format($network_stat->count); ?></p>
                    <p style="color: #666;"><?php echo round(($network_stat->count / $total_clicks) * 100, 1); ?>% of total</p>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Top Performing Posts -->
            <div class="noah-top-posts" style="background: white; padding: 20px; border: 1px solid #ccd0d4; margin: 20px 0;">
                <h2><?php _e('Top Performing Posts', 'noah-affiliate'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Post Title', 'noah-affiliate'); ?></th>
                            <th><?php _e('Clicks', 'noah-affiliate'); ?></th>
                            <th><?php _e('Actions', 'noah-affiliate'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_posts)): ?>
                            <?php foreach ($top_posts as $post_stat): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?php echo get_edit_post_link($post_stat->post_id); ?>">
                                                <?php echo esc_html(get_the_title($post_stat->post_id)); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?php echo number_format($post_stat->count); ?></td>
                                    <td>
                                        <a href="<?php echo get_permalink($post_stat->post_id); ?>" target="_blank">
                                            <?php _e('View', 'noah-affiliate'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3"><?php _e('No data available for this period.', 'noah-affiliate'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
