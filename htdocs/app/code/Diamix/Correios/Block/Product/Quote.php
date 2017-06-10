<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Block\Product;

use Diamix\Correios\Helper\Data;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Psr\Log\LoggerInterface;

/**
 * Product Quote Block
 * 
 * Block for quotes on Catalog Product View page.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Quote extends Template
{
    /**
     * @var Registry
     */
    protected $registry;
    
    /**
     * @var Product
     */
    private $product;
    
    /**
     * __construct
     */
    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        Registry $registry,
        Configurable $configurable,
        Data $helper,
        array $data
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->registry = $registry;
        $this->configurable = $configurable;
        $this->helper = $helper;
        parent::__construct($context, $data);
        
        if (is_null($this->product)) {
            $this->product = $this->registry->registry('product');
        }
    }
    
    /**
     * showBox
     * 
     * Defines if the quote box can be displayed
     * @return bool
     */
    public function showBox()
    {
        // display estimate box if set to Yes
        $showBox = $this->helper->getConfigValue('estimate_quote_box');
        if (!$showBox) {
            return false;
        }
        
        // display only for products that can be shipped
        if ((!$this->product || $this->product->getTypeId() != 'downloadable' && $this->product->getTypeId() != 'virtual' && $this->product->getTypeId() != 'grouped' && $this->product->getTypeId() != 'bundle')) {
            return true;
        }
        return false;
    }
    
    /**
     * defineProduct
     * 
     * Defines what is the product to be quoted
     * @return int
     */
    public function defineProduct()
    {
        // if configurable, get the first child ID
        if ($this->product->getTypeId() == 'configurable') {
            $childProducts = $this->configurable->getUsedProducts(null, $this->product);
            
            foreach ($childProducts as $child) {
                $productId = $child->getId();
                if ($productId) {
                    break;
                }
            }
        } else {// else, get the product ID
            $productId = $this->product->getId();
        }
        return $productId;
    }
    
    /**
     * getCurrentProduct
     * 
     * Returns current product
     * @return Product
     */
    public function getCurrentProduct()
    {
        return $this->product;
    }   
}
