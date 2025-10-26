/**
 * Noah Affiliate Frontend JavaScript
 * Handles click tracking and user interactions
 */

(function($) {
    'use strict';
    
    var NoahAffiliate = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Track affiliate link clicks
            $(document).on('click', '.noah-affiliate-link', this.trackClick.bind(this));
            
            // Track comparison table clicks
            $(document).on('click', '.noah-comparison-link', this.trackClick.bind(this));
        },
        
        /**
         * Track click
         */
        trackClick: function(e) {
            if (noahAffiliate.trackingEnabled !== '1') {
                return true;
            }
            
            var $link = $(e.currentTarget);
            var productId = $link.data('product-id');
            var network = $link.data('network');
            
            if (!productId || !network) {
                return true;
            }
            
            // Use beacon API for non-blocking tracking
            if (navigator.sendBeacon) {
                this.trackWithBeacon(productId, network);
            } else {
                this.trackWithAjax(productId, network);
            }
            
            return true;
        },
        
        /**
         * Track with Beacon API (preferred)
         */
        trackWithBeacon: function(productId, network) {
            var data = new FormData();
            data.append('action', 'noah_track_click');
            data.append('nonce', noahAffiliate.nonce);
            data.append('post_id', noahAffiliate.postId);
            data.append('product_id', productId);
            data.append('network', network);
            
            navigator.sendBeacon(noahAffiliate.ajaxUrl, data);
        },
        
        /**
         * Track with AJAX (fallback)
         */
        trackWithAjax: function(productId, network) {
            $.ajax({
                url: noahAffiliate.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'noah_track_click',
                    nonce: noahAffiliate.nonce,
                    post_id: noahAffiliate.postId,
                    product_id: productId,
                    network: network
                },
                async: true
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        NoahAffiliate.init();
    });
    
})(jQuery);
