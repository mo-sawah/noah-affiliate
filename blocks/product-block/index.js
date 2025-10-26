/**
 * Noah Affiliate Product Block
 * Basic implementation - can be enhanced with a build process
 */

(function(wp) {
    var el = wp.element.createElement;
    var __ = wp.i18n.__;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var SelectControl = wp.components.SelectControl;
    
    registerBlockType('noah-affiliate/product', {
        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            
            return [
                el(InspectorControls, {},
                    el(PanelBody, {
                        title: __('Product Settings', 'noah-affiliate'),
                        initialOpen: true
                    },
                        el(SelectControl, {
                            label: __('Network', 'noah-affiliate'),
                            value: attributes.network,
                            options: [
                                { label: __('Select Network', 'noah-affiliate'), value: '' },
                                { label: 'Amazon', value: 'amazon' },
                                { label: 'Awin', value: 'awin' },
                                { label: 'CJ', value: 'cj' },
                                { label: 'Rakuten', value: 'rakuten' }
                            ],
                            onChange: function(value) {
                                setAttributes({ network: value });
                            }
                        }),
                        el(TextControl, {
                            label: __('Product ID', 'noah-affiliate'),
                            value: attributes.productId,
                            onChange: function(value) {
                                setAttributes({ productId: value });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Layout', 'noah-affiliate'),
                            value: attributes.layout,
                            options: [
                                { label: __('Card', 'noah-affiliate'), value: 'card' },
                                { label: __('Inline', 'noah-affiliate'), value: 'inline' },
                                { label: __('Comparison', 'noah-affiliate'), value: 'comparison' }
                            ],
                            onChange: function(value) {
                                setAttributes({ layout: value });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Badge', 'noah-affiliate'),
                            value: attributes.badge,
                            options: [
                                { label: __('None', 'noah-affiliate'), value: '' },
                                { label: __('Best Overall', 'noah-affiliate'), value: 'best-overall' },
                                { label: __('Best Budget', 'noah-affiliate'), value: 'best-budget' },
                                { label: __('Best Premium', 'noah-affiliate'), value: 'best-premium' },
                                { label: __('Editor\'s Choice', 'noah-affiliate'), value: 'editors-choice' }
                            ],
                            onChange: function(value) {
                                setAttributes({ badge: value });
                            }
                        })
                    )
                ),
                el('div', {
                    className: 'noah-product-block-editor',
                    style: {
                        padding: '20px',
                        border: '2px dashed #ccc',
                        background: '#f9f9f9',
                        textAlign: 'center'
                    }
                },
                    el('div', {
                        style: { fontSize: '48px', marginBottom: '10px' }
                    }, '🛒'),
                    el('h3', {}, __('Affiliate Product', 'noah-affiliate')),
                    el('p', {},
                        attributes.network && attributes.productId
                            ? attributes.network.toUpperCase() + ': ' + attributes.productId
                            : __('Configure product in the sidebar', 'noah-affiliate')
                    )
                )
            ];
        },
        
        save: function() {
            // Render on PHP side
            return null;
        }
    });
})(window.wp);
