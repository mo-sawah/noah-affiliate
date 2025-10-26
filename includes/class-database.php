<?php
/**
 * Database Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Noah_Affiliate_Database {
    
    /**
     * Create plugin tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'noah_clicks';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            product_id varchar(255) NOT NULL,
            network varchar(50) NOT NULL,
            clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
            user_ip varchar(45),
            user_agent text,
            referrer text,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY network (network),
            KEY clicked_at (clicked_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Update version
        update_option('noah_affiliate_db_version', '1.0');
    }
    
    /**
     * Log a click
     */
    public static function log_click($post_id, $product_id, $network) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'noah_clicks';
        
        // Get tracking settings
        $track_ip = get_option('noah_affiliate_track_ip', '1');
        $track_user_agent = get_option('noah_affiliate_track_user_agent', '1');
        
        $data = array(
            'post_id' => absint($post_id),
            'product_id' => sanitize_text_field($product_id),
            'network' => sanitize_text_field($network),
            'clicked_at' => current_time('mysql')
        );
        
        if ($track_ip === '1') {
            $data['user_ip'] = self::get_user_ip();
        }
        
        if ($track_user_agent === '1') {
            $data['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';
        }
        
        $data['referrer'] = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        
        $wpdb->insert($table_name, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get user IP address
     */
    private static function get_user_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Validate IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        
        return '';
    }
    
    /**
     * Get click statistics
     */
    public static function get_stats($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => null,
            'date_to' => null,
            'post_id' => null,
            'network' => null,
            'limit' => 100
        );
        
        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . 'noah_clicks';
        
        $where = array('1=1');
        
        if ($args['date_from']) {
            $where[] = $wpdb->prepare('clicked_at >= %s', $args['date_from']);
        }
        
        if ($args['date_to']) {
            $where[] = $wpdb->prepare('clicked_at <= %s', $args['date_to']);
        }
        
        if ($args['post_id']) {
            $where[] = $wpdb->prepare('post_id = %d', $args['post_id']);
        }
        
        if ($args['network']) {
            $where[] = $wpdb->prepare('network = %s', $args['network']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM $table_name WHERE $where_clause ORDER BY clicked_at DESC LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $args['limit']));
    }
    
    /**
     * Get total clicks count
     */
    public static function get_total_clicks($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => null,
            'date_to' => null,
            'post_id' => null,
            'network' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . 'noah_clicks';
        
        $where = array('1=1');
        
        if ($args['date_from']) {
            $where[] = $wpdb->prepare('clicked_at >= %s', $args['date_from']);
        }
        
        if ($args['date_to']) {
            $where[] = $wpdb->prepare('clicked_at <= %s', $args['date_to']);
        }
        
        if ($args['post_id']) {
            $where[] = $wpdb->prepare('post_id = %d', $args['post_id']);
        }
        
        if ($args['network']) {
            $where[] = $wpdb->prepare('network = %s', $args['network']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Get clicks by network
     */
    public static function get_clicks_by_network($date_from = null, $date_to = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'noah_clicks';
        
        $where = array('1=1');
        
        if ($date_from) {
            $where[] = $wpdb->prepare('clicked_at >= %s', $date_from);
        }
        
        if ($date_to) {
            $where[] = $wpdb->prepare('clicked_at <= %s', $date_to);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT network, COUNT(*) as count FROM $table_name WHERE $where_clause GROUP BY network ORDER BY count DESC";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get top performing posts
     */
    public static function get_top_posts($limit = 10, $date_from = null, $date_to = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'noah_clicks';
        
        $where = array('1=1');
        
        if ($date_from) {
            $where[] = $wpdb->prepare('clicked_at >= %s', $date_from);
        }
        
        if ($date_to) {
            $where[] = $wpdb->prepare('clicked_at <= %s', $date_to);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "SELECT post_id, COUNT(*) as count FROM $table_name WHERE $where_clause GROUP BY post_id ORDER BY count DESC LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $limit));
    }
    
    /**
     * Clean old tracking data
     */
    public static function clean_old_data($days = 90) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'noah_clicks';
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE clicked_at < %s",
            $date
        ));
        
        return $wpdb->rows_affected;
    }
}
