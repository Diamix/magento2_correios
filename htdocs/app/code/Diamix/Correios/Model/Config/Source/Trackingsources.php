<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Model\Config\Source;

/**
 * Tracking Sources List
 * 
 * Lists tracking sources, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Trackingsources implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
    		array('value' => 'correios', 'label' => __('Correios Webservice')),
            /*array('value' => 'agenciaideias', 'label' => __('API Agência Ideias')),*/
		);
    }
}
