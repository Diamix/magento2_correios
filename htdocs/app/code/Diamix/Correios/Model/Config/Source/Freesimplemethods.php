<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Model\Config\Source;

/**
 * Simple Methods List.
 * 
 * Lists simple methods (without contract), including 'none' option, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Freesimplemethods implements \Magento\Framework\Option\ArrayInterface
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
    		array('value' => '41106', 'label' => 'PAC (41106)'),
            array('value' => '40010', 'label' => 'Sedex (40010)'),
		);
    }
}
