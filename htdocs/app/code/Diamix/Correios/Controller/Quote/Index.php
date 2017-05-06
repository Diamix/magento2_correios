<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Controller\Quote;

use Diamix\Correios\Model\Estimate;
use Magento\Framework\App\Action\Context;

/**
 * Quote/Index Controller
 * 
 * Controller in charge of handling quotes requested from product page.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var $estimated
     */
    protected $estimate;
    
    /**
     * __construct
     * 
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Diamix\Correios\Model\Estimate $estimate
     */
    public function __construct(
        Context $context,
        Estimate $estimate
    ) {
        $this->estimate = $estimate;
        parent::__construct($context);
    }
        
    /**
     * Index action
     */
    public function execute()
    {
        // verify if a product has been sent
        $params = $this->getRequest()->getParams();
        if (!array_key_exists('postcode', $params) || !array_key_exists('currentProduct', $params) || !array_key_exists('qty', $params)) {
            die;
        }
        
        // prepare basic data
        $postcode = (int) str_replace('-', '', str_replace('.', '', $params['postcode']));
        $productId = (int) $params['currentProduct'];
        $productQty = (int) $params['qty'];
        if ($productQty == 0 || $productQty == null) {
            $productQty = 1;
        }
        
        // get estimate quote        
        $shippingHtml = $this->estimate->getEstimate($postcode, $productId, $productQty, $params);
        if ($shippingHtml) {
            echo json_encode($shippingHtml);
        }
    }
}
