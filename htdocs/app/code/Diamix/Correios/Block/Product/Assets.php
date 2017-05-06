<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Block\Product;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Page\Config;
use Psr\Log\LoggerInterface;

/**
 * Assets Block
 * 
 * Used to handle Magento 2 ifconfig limitation when adding assets.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Assets extends Template
{ 
    /**
     * __construct
     */
    public function __construct(
        Template\Context $context,
        Config $pageConfig,
        array $data
    ) {
        $this->pageConfig = $pageConfig;
        parent::__construct($context, $data);
        $this->addPageAsset($this->getPath());
    }
    
    /**
     * addPageAsset
     * 
     * Used to handle Magento 2 ifconfig limitation when adding assets
     * @param string $path The path for the asset to be included
     * @return bool
     */
    public function addPageAsset($path)
    {
        $this->pageConfig->addPageAsset($path);
    }
}
