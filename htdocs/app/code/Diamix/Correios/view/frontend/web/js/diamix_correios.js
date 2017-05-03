    /* Diamix_Correios: JS for getting quotes, on product page */
    
    function getQuote() {
        $j('#quoteResultsBox').html('').hide();
        $j('#estimateQuoteSubmit').attr('disabled', true);
        var url = $j('#quoteUrl').val();
        var postcode = $j('#postcode').val();
        var qty = $j('#qty').val();
        var productType = $j('#productType').val();
        
        if (productType == 'configurable') {
            var productId = spConfig.getIdOfSelectedProduct();
            if (productId != undefined) {
                var currentProduct = productId;
            } else {
                var currentProduct = $j('#currentProduct').val();
            }
        } else {
            var currentProduct = $j('#currentProduct').val();
        }
        
        $j.ajax({
            type: 'POST',
            url: url,
            data: {currentProduct: currentProduct, qty: qty, postcode: postcode},
            dataType: 'json',
            success: (function(response) {
                if (response) {
                    var html = '';
                    $j.each(response, function(key, item) {
                        html += '<dt id="dt-' + key + '">' + item.name + '</dt><dd><ul id="ul-' + key + '">';
                        $j.each(item.methods, function(subkey, subitem) {
                            html += '<li id="' + subitem.id + '"><label>' + subitem.title + ': ' + subitem.price + '</label></li>';
                        });
                        html += '</ul>';
                    });
                    $j('#quoteResultsBox').html(html).show();
                    $j('#estimateQuoteSubmit').attr('disabled', false);
                }
            }),
            error: (function() {
               $j('#estimateQuoteSubmit').attr('disabled', false); 
            }),
        });
    }
    
    /* This function was originally published on Inchoo blog */
    Product.Config.prototype.getIdOfSelectedProduct = function()
    {
        var existingProducts = new Object();
        
        for (var i=this.settings.length - 1; i > 0; i--) {
            var selected = this.settings[i].options[this.settings[i].selectedIndex];
            if (selected.config) {
                for (var iproducts = 0; iproducts < selected.config.products.length; iproducts++) {
                    var usedAsKey = selected.config.products[iproducts] + '';
                    if (existingProducts[usedAsKey] == undefined) {
                        existingProducts[usedAsKey] = 1;
                    } else {
                        existingProducts[usedAsKey] = existingProducts[usedAsKey] + 1;
                    }
                }
            }
        }
        for (var keyValue in existingProducts) {
            for (var keyValueInner in existingProducts) {
                if (Number(existingProducts[keyValueInner]) < Number(existingProducts[keyValue])) {
                    delete ExistingProducts[keyValueInner];
                }
            }
        }
            
        var sizeOfExistingProducts = 0;
        var currentSimpleProductId = '';
        for (var keyValue in existingProducts) {
            currentSimpleProductId = keyValue;
            sizeOfExistingProducts = sizeOfExistingProducts + 1;
        }
        if (sizeOfExistingProducts == 1) {
            return currentSimpleProductId;
        }
    }