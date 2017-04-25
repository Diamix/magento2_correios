<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Model\Config\Source;

/**
 * Contract Methods List
 * 
 * Lists contract methods, to be used on system.xml.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Contractmethods implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * toOptionArray
     * 
     * @return Array
     */
    public function toOptionArray()
    {
        return array(
    		array('value' => '04669', 'label' => 'PAC (04669)'),
            /*array('value' => '41300', 'label' => 'PAC Grandes Formatos (41300)'),*/
    		array('value' => '04162', 'label' => 'Sedex (04162)'),
    		array('value' => '81019', 'label' => 'E-Sedex (81019)'),
    		array('value' => '40215', 'label' => 'Sedex 10 (40215)'),
    		array('value' => '40290', 'label' => 'Sedex HOJE (40290)'),
    		array('value' => '40045', 'label' => 'Sedex a Cobrar (40045)'),
            array('value' => '41068', 'label' => 'PAC - Contratos Antigos (41068)'),
            array('value' => '40096', 'label' => 'Sedex - Contratos Antigos (40096)'),
		);
    }
}
