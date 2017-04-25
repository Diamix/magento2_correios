<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Model\Config\Source;

/**
 * Weight Units List
 * 
 * Lists weight units, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Weightunits implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
    		array('value' => 'kg', 'label' => __('Kilos')),
            array('value' => 'g', 'label' => __('Grams')),
		);
    }
}
