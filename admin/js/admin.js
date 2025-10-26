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
                    console.log('Search response:', response);
                    
                    if (response.success && response.data.products && response.data.products.length > 0) {
                        var html = '';
                        $.each(response.data.products, function(index, product) {
                            console.log('Product ' + index + ':', product);
                            
                            html += '<div class="noah-search-result-item" data-product=\'' + JSON.stringify(product) + '\'>';
                            if (product.image) {
                                html += '<img src="' + product.image + '" class="noah-result-image" onerror="this.style.display=\'none\'">';
                            }
                            html += '<div class="noah-result-content">';
                            html += '<div class="noah-result-title">' + product.title + '</div>';
                            if (product.price) {
                                html += '<div class="noah-result-price">' + product.price + '</div>';
                            }
                            if (product.description) {
                                html += '<div class="noah-result-description" style="font-size: 12px; color: #666; margin-top: 5px;">' + product.description + '</div>';
                            }
                            if (product.rating && product.rating > 0) {
                                html += '<div class="noah-result-rating" style="font-size: 12px; color: #f90; margin-top: 3px;">★ ' + product.rating + '</div>';
                            }
                            html += '</div>';
                            html += '<button type="button" class="button noah-add-product-btn" data-product-index="' + index + '">Add</button>';
                            html += '</div>';
                        });
                        $results.html(html);
                        
                        // Store products data for later use
                        $results.data('products', response.data.products);
                    } else {
                        var message = 'No products found.';
                        if (response.data && response.data.message) {
                            message += ' Error: ' + response.data.message;
                        }
                        $results.html('<p>' + message + '</p>');
                        console.log('No products or error:', response);
                    }
                },
                error: function() {
                    $results.html('<p>Error searching products. Please try again.</p>');
                }
            });
        },
        
        initProductList: function() {
            var self = this;
            
            if ($.fn.sortable) {
                $('#noah-products-list').sortable({ handle: '.noah-drag-handle' });
            }
            
            // Remove product
            $(document).on('click', '.noah-remove-product', function() {
                $(this).closest('.noah-product-item').remove();
                self.updateProductsData();
            });
            
            // Toggle settings
            $(document).on('click', '.noah-toggle-settings', function() {
                $(this).closest('.noah-product-item').find('.noah-product-settings').slideToggle();
            });
            
            // Add product from search results
            $(document).on('click', '.noah-add-product-btn', function() {
                var $btn = $(this);
                var $resultItem = $btn.closest('.noah-search-result-item');
                var productData = $resultItem.data('product');
                
                if (!productData) {
                    // Fallback: get from results data
                    var index = $btn.data('product-index');
                    var products = $('.noah-search-results').data('products');
                    if (products && products[index]) {
                        productData = products[index];
                    }
                }
                
                if (productData) {
                    self.addProductToList(productData);
                    $btn.text('Added!').prop('disabled', true);
                }
            });
            
            // Update data on setting changes
            $(document).on('change', '.noah-layout-select, .noah-badge-select, .noah-custom-title', function() {
                self.updateProductsData();
            });
        },
        
        addProductToList: function(product) {
            var instanceId = 'product_' + Date.now();
            var $list = $('#noah-products-list');
            
            // Remove "no products" message
            $list.find('.noah-no-products').remove();
            
            var html = '<div class="noah-product-item" data-instance-id="' + instanceId + '">';
            html += '<div class="noah-product-header">';
            html += '<span class="noah-drag-handle" style="cursor: move;">☰</span>';
            html += '<strong>' + product.title + '</strong>';
            html += '<div class="noah-product-controls">';
            html += '<button type="button" class="button-small noah-toggle-settings">Settings</button>';
            html += '<button type="button" class="button-small noah-remove-product" style="color: #a00;">Remove</button>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="noah-product-body">';
            if (product.image) {
                html += '<img src="' + product.image + '" class="noah-product-image">';
            }
            html += '<div class="noah-product-details">';
            html += '<div><strong>Network:</strong> ' + (product.network || 'Firecrawl') + '</div>';
            if (product.price) {
                html += '<div><strong>Price:</strong> ' + product.price + '</div>';
            }
            if (product.merchant) {
                html += '<div><strong>Merchant:</strong> ' + product.merchant + '</div>';
            }
            html += '<div><strong>Product ID:</strong> ' + (product.id || product.url) + '</div>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="noah-product-settings" style="display: none;">';
            html += '<div class="noah-setting-row">';
            html += '<label>Layout:</label>';
            html += '<select class="noah-layout-select">';
            html += '<option value="card">Card</option>';
            html += '<option value="inline">Inline</option>';
            html += '<option value="comparison">Comparison Row</option>';
            html += '</select>';
            html += '</div>';
            html += '<div class="noah-setting-row">';
            html += '<label>Badge:</label>';
            html += '<select class="noah-badge-select">';
            html += '<option value="">None</option>';
            html += '<option value="best-overall">Best Overall</option>';
            html += '<option value="best-budget">Best Budget</option>';
            html += '<option value="best-premium">Best Premium</option>';
            html += '<option value="editors-choice">Editor\'s Choice</option>';
            html += '</select>';
            html += '</div>';
            html += '<div class="noah-setting-row">';
            html += '<label>Custom Title (optional):</label>';
            html += '<input type="text" class="widefat noah-custom-title">';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            $list.append(html);
            
            // Store product data
            $list.find('.noah-product-item[data-instance-id="' + instanceId + '"]').data('product', product);
            
            this.updateProductsData();
        },
        
        updateProductsData: function() {
            var products = {};
            
            $('#noah-products-list .noah-product-item').each(function() {
                var $item = $(this);
                var instanceId = $item.data('instance-id');
                var productData = $item.data('product');
                
                if (productData) {
                    products[instanceId] = {
                        instance_id: instanceId,
                        title: productData.title,
                        description: productData.description || '',
                        price: productData.price || '',
                        image: productData.image || '',
                        url: productData.url || '',
                        product_id: productData.id || productData.url,
                        network: productData.network || 'firecrawl',
                        merchant: productData.merchant || '',
                        rating: productData.rating || 0,
                        reviews: productData.reviews || 0,
                        country: productData.country || '',
                        preset: productData.preset || '',
                        layout: $item.find('.noah-layout-select').val() || 'card',
                        badge: $item.find('.noah-badge-select').val() || '',
                        custom_title: $item.find('.noah-custom-title').val() || ''
                    };
                }
            });
            
            $('#noah-products-data').val(JSON.stringify(products));
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
