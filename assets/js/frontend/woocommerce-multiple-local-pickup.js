
jQuery( function( $ ) {
    
    var WCMultipleLocalPickup = {
        
        init: function(){
            $( document.body ).on('change', '#multiple-pickup-locations-list input[type="radio"]', function(){
                var parent = $(this).closest('#multiple-pickup-locations-list').closest('li').find('.shipping_method').prop('checked', true);
                WCMultipleLocalPickup.update_location();
            });
        },
        
        update_location: function(){
            WCMultipleLocalPickup.block( $('div.cart_totals, form.checkout, form#order_review') );
            
            // recuperar apenas o radio do MultipleLocalPickup
            var shipping_methods = {};
            var mpl_radio = $('#multiple-pickup-locations-list').closest('li').find('.shipping_method');
            shipping_methods[ mpl_radio.data('index') ] = mpl_radio.val();
            
            var data = {
                shipping_method : shipping_methods,
                location: $('#multiple-pickup-locations-list input[name="pickup-location"]:checked').val()
            };
            
            // Gets the address.
            $.ajax({
                type: 'post',
                url: WCMultipleLocalPickupParams.url,
                data:     data,
                dataType: 'html',
                success:  function( response ) {
                    
                },
                complete: function() {
                    WCMultipleLocalPickup.unblock( $('div.cart_totals, form.checkout, form#order_review') );
                }
            });
        },

        /**
         * Check if a node is blocked for processing.
         *
         * @param {JQuery Object} $node
         * @return {bool} True if the DOM Element is UI Blocked, false if not.
         */
        is_blocked: function( $node ) {
            return $node.is( '.processing' ) || $node.parents( '.processing' ).length;
        },

        /**
         * Block a node visually for processing.
         *
         * @param {JQuery Object} $node
         */
        block: function( $node ) {
            if ( ! WCMultipleLocalPickup.is_blocked( $node ) ) {
                $node.addClass( 'processing' ).block( {
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                } );
            }
        },

        /**
         * Unblock a node after processing is complete.
         *
         * @param {JQuery Object} $node
         */
        unblock: function( $node ) {
            $node.removeClass( 'processing' ).unblock();
        },
        
    };
    
    WCMultipleLocalPickup.init();
    
});

