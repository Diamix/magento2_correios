<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Model\Config\Source;

/**
 * Dimension Units List
 * 
 * Lists dimension units, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Dimensionunits implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
    		array('value' => 'cm', 'label' => __('centimeters')),
            array('value' => 'mm', 'label' => __('milimeters')),
            array('value' => 'm', 'label' => __('meters')),
		);
    }
}
