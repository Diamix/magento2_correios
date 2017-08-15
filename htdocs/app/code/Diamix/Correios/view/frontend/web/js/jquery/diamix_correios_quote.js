    /* Diamix_Correios: JS for getting quotes, on product page */
    
    function getQuote() {
        jQuery('#quoteResultsBox').html('').hide();
        jQuery('#estimateQuoteSubmit').attr('disabled', true);
        var url = jQuery('#quoteUrl').val();
        var postcode = jQuery('#postcode').val();
        var qty = jQuery('#qty').val();
        var productType = jQuery('#productType').val();
        var currentProduct = jQuery('#currentProduct').val();
        
        jQuery.ajax({
            type: 'POST',
            url: url,
            data: {currentProduct: currentProduct, qty: qty, postcode: postcode},
            dataType: 'json',
            success: (function(response) {
                if (response) {
                    var html = '';
                    jQuery.each(response, function(key, item) {
                        html += '<dt id="dt-' + key + '">' + item.name + '</dt><dd><ul id="ul-' + key + '">';
                        jQuery.each(item.methods, function(subkey, subitem) {
                            html += '<li id="' + subitem.id + '"><label>' + subitem.title + ': ' + subitem.price + '</label></li>';
                        });
                        html += '</ul>';
                    });
                    jQuery('#quoteResultsBox').html(html).show();
                    jQuery('#estimateQuoteSubmit').attr('disabled', false);
                }
            }),
            error: (function() {
               jQuery('#estimateQuoteSubmit').attr('disabled', false); 
            }),
        });
    }