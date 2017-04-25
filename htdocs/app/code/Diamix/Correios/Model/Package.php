<?php
/**
 * Diamix_Correios Module
 */

namespace Diamix\Correio\Model;

/**
 * Package Model
 * 
 * Simple package model, used to split products into packages according with Correios maximum dimensions. This version is not prepared to handle smart packages, only to prevent dimensions sum that blows maximums dimensions.
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 */
class Package
{
    /**
     * Package Length
     * @access protected
     */
    protected $packageLength = 0;
    
    /**
     * Package Width
     * @access protected
     */
    protected $packageWidth = 0;
    
    /**
     * Package Height
     * @access protected
     */
    protected $packageHeight = 0;
    
    /**
     * Package Weight
     * @access protected
     */
    protected $packageWeight = 0;
    
    /**
     * Package Value
     * @access protected
     */
    protected $packageValue = 0;
    
    /**
     * Package Items
     * 
     * Store items ID.
     * @access protected
     */
    protected $items = array();
    
    /**
     * Add length
     * 
     * @param int $itemLength Item length
     * @return int
     */
    public function addLength($itemLength)
    {
        return $this->packageLength += $itemLength;
    }
    
    /**
     * Add width
     * 
     * @param int $itemWidth Item width
     * @return int
     */
    public function addWidth($itemWidth)
    {
        return $this->packageWidth += $itemWidth;
    }
    
    /**
     * Add height
     * 
     * @param int $itemHeight Item height
     * @return int
     */
    public function addHeight($itemHeight)
    {
        return $this->packageHeight += $itemHeight;
    }
    
    /**
     * Add weight
     * 
     * @param float $itemWeight Item weight
     * @return float
     */
    public function addWeight($itemWeight)
    {
        return $this->packageWeight += $itemWeight;
    }
    
    /**
     * Add value
     * 
     * @param float $itemValue Item value
     * @return float
     */
    public function addValue($itemValue)
    {
        return $this->packageValue += $itemValue;
    }
    
    /**
     * Add item
     * 
     * @param Mage_Catalog_Model_Product $product
     * @return int
     */
    public function addItem($product)
    {
        array_push($this->items, $product->getId());
        return count($this->items);
    }
    
    /**
     * Get lenght
     * 
     * @return float
     */
    public function getLength()
    {
        return $this->packageLength;
    }
    
    /**
     * Get width
     * 
     * @return float
     */
    public function getWidth()
    {
        return $this->packageWidth;
    }
    
    /**
     * Get height
     * 
     * @return float
     */
    public function getHeight()
    {
        return $this->packageHeight;
    }
    
    /**
     * Get sum
     * 
     * @return float
     */
    public function getSum()
    {
        $sum = $this->packageHeight + $this->packageWidth + $this->packageLength;
        return $sum;
    }
    
    /**
     * Get weight
     * 
     * @return float
     */
    public function getWeight()
    {
        return $this->packageWeight;
    }
    
    /**
     * Get value
     * 
     * @return float
     */
    public function getValue()
    {
        return $this->packageValue;
    }
    
    /**
     * Get items
     * 
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }
    
    /**
     * Get itens quantity
     * 
     * @return int
     */
    public function getItemsQty()
    {
        return count($this->items);
    }    
}
