/**
 * Noah Affiliate Admin JavaScript
 */

(function($) {
    'use strict';
    
    var NoahAffiliateAdmin = {
        init: function() {
            this.initProductSearch();
            this.initProductList();
            this.initTestConnections();
        },
        
        initProductSearch: function() {
            var self = this;
            
            // Handle network change to show/hide country selector
            $('#noah-network-select').on('change', function() {
                var network = $(this).val();
                var $countrySelect = $('#noah-country-select');
                
                if (network === 'firecrawl') {
                    // Load available countries for Firecrawl
                    $.ajax({
                        url: noahAffiliateAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'noah_get_firecrawl_countries',
                            nonce: noahAffiliateAdmin.nonce
                        },
                        success: function(response) {
                            if (response.success && response.data.countries) {
                                var options = '<option value="">Select Country</option>';
                                $.each(response.data.countries, function(code, name) {
                                    options += '<option value="' + code + '">' + name + '</option>';
                                });
                                $countrySelect.html(options).show();
                            } else {
                                $countrySelect.hide();
                            }
                        }
                    });
                } else {
                    $countrySelect.hide();
                }
            });
            
            $('.noah-search-button').on('click', function(e) {
                e.preventDefault();
                self.searchProducts();
            });
        },
        
        searchProducts: function() {
            var network = $('#noah-network-select').val();
            var country = $('#noah-country-select').val();
            var query = $('#noah-product-search').val();
            var $results = $('.noah-search-results');
            
            if (!network || !query) {
                alert('Please select a network and enter search query');
                return;
            }
            
            // Check if Firecrawl and country is required
            if (network === 'firecrawl' && $('#noah-country-select').is(':visible') && !country) {
                alert('Please select a country');
                return;
            }
            
            $results.html('<p>Searching...</p>');
            
            $.ajax({
                url: noahAffiliateAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'noah_search_products',
                    nonce: noahAffiliateAdmin.nonce,
                    network: network,
                    query: query,
                    country: country
                },
                success: function(response) {
                    if (response.success && response.data.products) {
                        var html = '';
                        $.each(response.data.products, function(index, product) {
                            html += '<div class="noah-search-result-item">';
                            if (product.image) html += '<img src="' + product.image + '" class="noah-result-image">';
                            html += '<div class="noah-result-content">';
                            html += '<div class="noah-result-title">' + product.title + '</div>';
                            html += '<div class="noah-result-price">' + product.price + '</div>';
                            html += '</div>';
                            html += '<button type="button" class="button noah-add-product-btn">Add</button>';
                            html += '</div>';
                        });
                        $results.html(html);
                    } else {
                        $results.html('<p>No products found.</p>');
                    }
                },
                error: function() {
                    $results.html('<p>Error searching products. Please try again.</p>');
                }
            });
        },
        
        initProductList: function() {
            if ($.fn.sortable) {
                $('#noah-products-list').sortable({ handle: '.noah-drag-handle' });
            }
            $(document).on('click', '.noah-remove-product', function() {
                $(this).closest('.noah-product-item').remove();
            });
        },
        
        initTestConnections: function() {
            $('.noah-test-connection').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var network = $btn.data('network');
                $btn.prop('disabled', true).text('Testing...');
                $.ajax({
                    url: noahAffiliateAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'noah_test_connection',
                        nonce: noahAffiliateAdmin.nonce,
                        network: network
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Test Connection');
                    }
                });
            });
        }
    };
    
    $(document).ready(function() {
        NoahAffiliateAdmin.init();
    });
    
})(jQuery);
