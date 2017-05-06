<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Model;

use Magento\Framework\Pricing\Helper\Data;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\BlockFactory;
use Magento\Framework\View\Element\Context;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Estimate Model
 * 
 * Model used to handle estimates requested from product page.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Estimate extends \Magento\Framework\View\Element\AbstractBlock
{
    /**
     * __construct
     */
    public function __construct(
        Context $context,
        ProductFactory $productFactory,
        QuoteFactory $quoteFactory,
        StoreManagerInterface $storeManager,
        BlockFactory $blockFactory,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        Data $currencyHelper,
        array $data = []
    ) {
        $this->_productFactory = $productFactory;
        $this->_quoteFactory = $quoteFactory;
        $this->_blockFactory = $blockFactory;
        $this->_storeManager = $storeManager;
        $this->_logger = $logger;
        $this->_scopeConfig = $scopeConfig;
        $this->_currencyHelper = $currencyHelper;
        parent::__construct($context, $data);
    }
    
    /**
     * Estimate Quote
     * 
     * This method considers only one product, i.e., it does not consider the whole cart.
     * @param int $postcode Receiver postcode
     * @param int $productId The product ID
     * @param int $productQty The product quantity
     * @param boolean $params The way to create a Varien_Object
     * @return array
     */
    public function getEstimate($postcode, $productId, $productQty, $params = false)
    {
        // set country code to Brazil, as this method will work only there
        $countryCode = 'BR';
        $postcode = sprintf('%08d', $postcode);
        
        // get product object
        $_product = $this->_productFactory->create()->load($productId);
        
        // prepare quote
        $quote = $this->_quoteFactory->create();
        $quote->setStore($this->_storeManager->getStore());
        $quote->addProduct($_product, $productQty);
        $quote->getShippingAddress()->setCountryId($countryCode)->setPostcode($postcode);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->getShippingAddress()->collectShippingRates();
        
        $groups = $quote->getShippingAddress()->getGroupedAllShippingRates();
        
        // handle result and return as array
        $shippingEstimate = false;
        
        foreach($groups as $code => $_rates){
            $shippingEstimate[$code] = array(
                'name' => $this->_scopeConfig->getValue('carriers/' . $code . '/title'),
            );
            $shippingEstimate[$code]['methods'] = array();
            $i = 1;
            foreach ($_rates as $_rate) {
                $array = array(
                    'id' => $code . '-' . $i,
                    'title' => $_rate->getMethodTitle(),
                    'price' => $this->_currencyHelper->currency($_rate->getPrice(), true, false),
                );
                array_push($shippingEstimate[$code]['methods'], $array);
                $i++;
            }
        }
        
        if ($shippingEstimate) {
            return $shippingEstimate;
        } else {
            return false;
        }
    }
}
