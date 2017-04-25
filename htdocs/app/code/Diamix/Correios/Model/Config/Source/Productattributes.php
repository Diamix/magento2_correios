<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Model\Config\Source;

use Magento\Framework\ObjectManagerInterface;

/**
 * Product Attributes List
 * 
 * Get products attributes and return them as list, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Productattributes implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * __construct
     */
     public function __construct(ObjectManagerInterface $interface)
     {
        $this->objectManager = $interface;
     }
     
    /**
     * toOptionArray
     * 
     * Returns a list with user defined attributes, which means, custom attributes.
     * @return Array
     * @todo NOT YET IMPLEMENTED
     */
    public function toOptionArray()
    {
        $productAttributes = $this->objectManager->get('Magento\Catalog\Model\Attribute')->getCollection();
        
        $array = array(
            array(
                'value' => 'none',
                'label' => __('No attribute selected'),
            )
        );
        foreach ($productAttributes as $value => $label) {
            //if ($pa->getIsUserDefined() == 1) {
                $option = array(
                    'value' => $value,
                    'label' => $label,
                );
                array_push($array, $option);
                unset($option);
            //}
        }
        return $array;
    }
}
