    /* Diamix_Correios: JS for getting quotes, on product page */
    
    function getQuote() {
        $('#quoteResultsBox').html('').hide();
        $('#estimateQuoteSubmit').attr('disabled', true);
        var url = $('#quoteUrl').val();
        var postcode = $('#postcode').val();
        var qty = $('#qty').val();
        var productType = $('#productType').val();
        
        if (productType == 'configurable') {
            var productId = spConfig.getIdOfSelectedProduct();
            if (productId != undefined) {
                var currentProduct = productId;
            } else {
                var currentProduct = $('#currentProduct').val();
            }
        } else {
            var currentProduct = $('#currentProduct').val();
        }
        
        $.ajax({
            type: 'POST',
            url: url,
            data: {currentProduct: currentProduct, qty: qty, postcode: postcode},
            dataType: 'json',
            success: (function(response) {
                if (response) {
                    var html = '';
                    $.each(response, function(key, item) {
                        html += '<dt id="dt-' + key + '">' + item.name + '</dt><dd><ul id="ul-' + key + '">';
                        $.each(item.methods, function(subkey, subitem) {
                            html += '<li id="' + subitem.id + '"><label>' + subitem.title + ': ' + subitem.price + '</label></li>';
                        });
                        html += '</ul>';
                    });
                    $('#quoteResultsBox').html(html).show();
                    $('#estimateQuoteSubmit').attr('disabled', false);
                }
            }),
            error: (function() {
               $('#estimateQuoteSubmit').attr('disabled', false); 
            }),
        });
    }