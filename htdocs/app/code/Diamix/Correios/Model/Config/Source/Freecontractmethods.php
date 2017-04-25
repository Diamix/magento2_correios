<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Model\Config\Source;

/**
 * Contract Methods List
 * 
 * Lists contract methods, including 'none' option, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Freecontractmethods implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'none', 'label' => __('None')),
    		array('value' => 'onlypac', 'label' => __('Only PAC')),
            array('value' => 'firstpacthensedex', 'label' => __('First PAC, then Sedex')),
		);
    }
}
