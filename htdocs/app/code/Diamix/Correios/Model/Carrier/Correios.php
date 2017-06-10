<?php
/**
 * Diamix_Correios Module
 */
namespace Diamix\Correios\Model\Carrier;

use Diamix\Correios\Helper\Data;
use Diamix\Correios\Model\Package;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackingFactory;
use Magento\Shipping\Model\Tracking\Result\ErrorFactory as TrackingErrorFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory as TrackingStatusFactory;
use Psr\Log\LoggerInterface;

/**
 * Carrier main model
 * 
 * @author Andre Gugliotti <andre@gugliotti.com.br>
 * @version 0.1
 * @category Shipping
 * @package Diamix_Correios
 * @license GNU General Public License, version 3
 * @todo Tracking methods must be well tested
 * @todo Correios error checking and validation
 */
class Correios extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * Method unique code
     * @access protected
     */
    protected $_code = 'Diamix_Correios';
    
    /**
     * Quote result
     * @access protected
     */
    protected $_result = null;
    
    /**
     * From Zip
     * @access protected
     */
    protected $fromZip;
    
    /**
     * To Zip
     * @access protected
     */
    protected $toZip;
    
    /**
     * Package Weight
     * @access protected
     */
    protected $packageWeight;
    
    /**
     * Package Value
     * @access protected
     */
    protected $packageValue;
    
    /**
     * __construct
     * 
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        ProductFactory $productFactory,
        TrackingFactory $trackFactory,
        TrackingErrorFactory $trackErrorFactory,
        TrackingStatusFactory $trackStatusFactory,
        Data $helper,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->productFactory = $productFactory;
        $this->trackFactory = $trackFactory;
        $this->trackErrorFactory = $trackErrorFactory;
        $this->trackStatusFactory = $trackStatusFactory;
        $this->helper = $helper;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }
    
    /**
     * getAllowedMethods
     * 
     * @return array
     */
    public function getAllowedMethods()
    {
        return array($this->_code => $this->helper->getConfigValue('title'));
    }
    
    /**
     * Collect Rates
     * 
     * Receives shipping request and process it. If there are quotes, return them. Else, return false.
     * @param Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return array|bool
     */
    public function collectRates(RateRequest $request)
    {
        echo 'item<pre>';
        var_dump($request->getData());
        die;
        // double checking if this method is active
        if (!$this->getConfigFlag('active')) {
            return false;
        }
        
        // prepare items object according to environment
        if (!$this->helper->verifyIfIsAdmin()) {
            // quote on frontend
            if ($this->helper->getConfigValue('active_frontend') == 1) {
                $items = $request->getAllItems();
            } else {
                return false;
            }
        } else {
            // quote on backend
            $items = $request->getAllItems();
        }
        
        // perform initial validation
        $initialValidation = $this->performInitialValidation($request);
        if (!$initialValidation) {
            return false;
        }
        
        // perform validate dimensions
        $packages = $this->preparePackages($items, $this->helper->getConfigValue('validate_dimensions'));
        if (!$packages) {
            $this->_logger->info('Diamix_Correios: There was an unexpected error when preparing the packages.');
            return false;
        }
        
        // initialize quote result object
        $this->_result = $this->rateResultFactory->create();
        
        // get allowed methods, passing free shipping if allowed
        $this->getQuotes($packages, $request->getFreeShipping());
        return $this->_result;
    }
    
    /**
     * Initial validation
     * 
     * @param Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return bool
     */
    protected function performInitialValidation(RateRequest $request)
    {
        // verify sender and receiver countries as 'BR'
        $senderCountry = $this->_scopeConfig->getValue('shipping/origin/country_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        
        if (!$this->helper->verifyIfIsAdmin()) {
            // quote on frontend
            $receiverCountry = $request->getDestCountryId();
        } else {
            // quote on backend
            $receiverCountry = $request->getCountryId();
        }
        
        if ($senderCountry != 'BR') {
            $this->_logger->info('Diamix_Correios: This method is active but default store country is not set to Brazil');
            return false;
        }
        
        if ($receiverCountry != 'BR') {
            return false;
        }
        
        // prepare postcodes and verify them
        $this->fromZip = $this->helper->sanitizePostcode($this->_scopeConfig->getValue('shipping/origin/postcode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $this->toZip = $this->helper->sanitizePostcode($request->getDestPostcode());
        if (!preg_match("/^([0-9]{8})$/", $this->fromZip) || !preg_match("/^([0-9]{8})$/", $this->toZip)) {
            return false;
        }
        
        // prepare package weight and verify it; this module works with kilos as standard weight unit
        $this->packageWeight = $request->getPackageWeight();
        
        if ($this->helper->getConfigValue('weight_unit') != 'kg') {
            $this->packageWeight = $this->helper->changeWeightToKilos($this->packageWeight);
        }
        
        $minWeight = $this->helper->getConfigValue('min_order_weight') ? $this->helper->getConfigValue('min_order_weight') : $this->helper->getConfigValue('gateway_limits/min_weight');
        $maxWeight = $this->helper->getConfigValue('max_order_weight') ? $this->helper->getConfigValue('max_order_weight') : $this->helper->getConfigValue('gateway_limits/max_weight');
        
        if ($this->packageWeight < $minWeight || $this->packageWeight > $maxWeight) {
            return false;
        }
        
        // prepare package value and verify it
        $this->packageValue = $request->getBaseCurrency()->convert($request->getPackageValue(), $request->getPackageCurrency());
        $minValue = $this->helper->getConfigValue('min_order_value') ? $this->helper->getConfigValue('min_order_value') : $this->helper->getConfigValue('gateway_limits/min_value');
        $maxValue = $this->helper->getConfigValue('max_order_value') ? $this->helper->getConfigValue('max_order_value') : $this->helper->getConfigValue('gateway_limits/max_value');
        
        if ($this->packageValue < $minValue || $this->packageValue > $maxValue) {
            return false;
        }
        return true;
    }
    
    /**
     * Prepare Packages
     * 
     * Used to create packages, according to dimensions rules or not.
     * @param Mage_Checkout_Model_Cart $items
     * @param bool $validate Validate Dimensions. This allows to override store config, if needed
     * @return bool
     */
    protected function preparePackages($items, $validate = 0)
    {
        // get attribute codes
        $lengthCode = $this->helper->getConfigValue('attribute_length') != 'none' ? $this->helper->getConfigValue('attribute_length') : null;
        $widthCode = $this->helper->getConfigValue('attribute_width') != 'none' ? $this->helper->getConfigValue('attribute_width') : null;
        $heightCode = $this->helper->getConfigValue('attribute_height') != 'none' ? $this->helper->getConfigValue('attribute_height') : null;
        
        // if validate dimensions, use params; else set fictional params
        if ($validate == 1) {
            // define package min and max dimensions
            $minLength = $this->helper->getConfigValue('gateway_limits/min_length');
            $minWidth = $this->helper->getConfigValue('gateway_limits/min_width');
            $minHeight = $this->helper->getConfigValue('gateway_limits/min_height');
            $maxLength = $this->helper->getConfigValue('gateway_limits/max_length');
            $maxWidth = $this->helper->getConfigValue('gateway_limits/max_width');
            $maxHeight = $this->helper->getConfigValue('gateway_limits/max_height');
            $maxSum = $this->helper->getConfigValue('gateway_limits/max_sum');
            
            // define package min and max weight and value, comparing custom and standard values, always in centimeters
            $minWeight = ($this->helper->getConfigValue('min_order_weight') >= $this->helper->getConfigValue('gateway_limits/min_weight')) ? $this->helper->getConfigValue('min_order_weight') : $this->helper->getConfigValue('gateway_limits/min_weight');
            $maxWeight = ($this->helper->getConfigValue('max_order_weight') <= $this->helper->getConfigValue('gateway_limits/max_weight')) ? $this->helper->getConfigValue('max_order_weight') : $this->helper->getConfigValue('gateway_limits/max_weight');
            $minValue = ($this->helper->getConfigValue('min_order_value') >= $this->helper->getConfigValue('gateway_limits/min_value')) ? $this->helper->getConfigValue('min_order_value') : $this->helper->getConfigValue('gateway_limits/min_value');
            $maxValue = ($this->helper->getConfigValue('max_order_value') <= $this->helper->getConfigValue('gateway_limits/max_value')) ? $this->helper->getConfigValue('max_order_value') : $this->helper->getConfigValue('gateway_limits/max_value');
        } else {
            // hardcoded values to avoid undefined variable errors
            $minHeight = 0;
            $minWidth = 0;
            $minLenght = 0;
            $maxHeight = 10000; // 10.000 cm
            $maxWidth = 10000;
            $maxLength = 10000;
            $maxSum = 30000; // 30.000 cm
            $minWeight = 0;
            $maxWeight = 1000; // 1000 kg
            $minValue = 0;
            $maxValue = 100000; // $ 100.000
        }
        
        // define packages array and first package
        $packages = array();
        $firstPackage = new \Diamix\Correios\Model\Package();
        array_push($packages, $firstPackage);
        
        // loop through items to validate dimensions and define packages
        foreach ($items as $item) {
            // get product data
            $_product = $item->getProduct();
            $qty = $item->getQty();
            if ($qty == 0) {
                continue;
            }
            
            // these verifications take out from the packages products without weight or that are not simple products
            if (!$item->getWeight() || $item->getProductType() != 'simple') {
                continue;
            }
            
            for ($i = 0; $i < $qty; $i++) {
                // set item dimensions; if the custom dimension is less than minimum, use standard; if greater than maximum, log and return false for the whole quote
                $product = $this->productFactory->create()->load($_product->getId());
                
                // set item height
                if ($heightCode) {
                    $itemHeight = $product->getData($heightCode) ? $product->getData($heightCode) : $this->helper->getConfigValue('standard_height');
                    
                    // convert to centimeter, if needed
                    if ($this->helper->getConfigValue('dimension_unit') != 'cm') {
                        $itemHeight = $this->helper->changeDimensionToCentimeter($itemHeight);
                    }
                    
                    if ($itemHeight < $minHeight) {
                        $itemHeight = $minHeight;
                    }
                    if ($itemHeight > $maxHeight) {
                        $this->_logger->info('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect height set: ' . $itemHeight . '. Max height: ' . $maxHeight);
                        return false;
                    }
                } else {
                    $itemHeight = $this->helper->getConfigValue('standard_height');
                }
                
                // set item width
                if ($widthCode) {
                    $itemWidth = $product->getData($widthCode) ? $product->getData($widthCode) : $this->helper->getConfigValue('standard_height');
                    
                    // convert to centimeter, if needed
                    if ($this->helper->getConfigValue('dimension_unit') != 'cm') {
                        $itemWidth = $this->helper->changeDimensionToCentimeter($itemWidth);
                    }
                    
                    if ($itemWidth < $minWidth) {
                        $itemWidth = $minWidth;
                    }
                    if ($itemWidth > $maxWidth) {
                        $this->_logger->info('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect width set: ' . $itemWidth . '. Max width: ' . $maxWidth);
                        return false;
                    }
                } else {
                    $itemWidth = $this->helper->getConfigValue('standard_width');
                }
                
                // set item length
                if ($lengthCode) {
                    $itemLength = $product->getData($lengthCode) ? $product->getData($lengthCode) : $this->helper->getConfigValue('standard_height');
                    
                    // convert to centimeter, if needed
                    if ($this->helper->getConfigValue('dimension_unit') != 'cm') {
                        $itemLength = $this->helper->changeDimensionToCentimeter($itemLength);
                    }
                    
                    if ($itemLength < $minLength) {
                        $itemLength = $minLength;
                    }
                    if ($itemLength > $maxLength) {
                        $this->_logger->info('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect length set: ' . $itemLength . '. Max length: ' . $maxLength);
                        return false;
                    }
                } else {
                    $itemLength = $this->helper->getConfigValue('standard_length');
                }
                
                // verify dimensions sum
                $dimensionsSum = $itemHeight + $itemWidth + $itemLength;
                if ($dimensionsSum > $maxSum) {
                    $this->_logger->info('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect sum: ' . $dimensionsSum);
                    return false;
                }
                
                // get product weight
                $itemWeight = $_product->getWeight();
                if ($itemWeight < $minWeight) {
                    $itemWeight = $minWeight;
                }
                if ($itemWeight > $maxWeight) {
                    $this->_logger->info('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect weight set: ' . $itemWeight . '. Max weight: ' . $maxWeight);
                    return false;
                }
                
                // get product value
                $itemValue = $_product->getFinalPrice();
                if ($itemValue < $minValue || $itemValue > $maxValue) {
                    $this->_logger->info('Diamix_Correios: The product with SKU ' . $_product->getSku() . ' has an incorrect value set: ' . $itemValue);
                    return false;
                }
                
                // loop through created packages
                $packagesCount = count($packages);
                $loop = 1;
                
                foreach ($packages as $pa) {
                    // verify if there is enough space to this item within a given package
                    if (($pa->getLength() + $itemLength) <= $maxLength && ($pa->getWidth() + $itemWidth) <= $maxWidth && ($pa->getHeight() + $itemHeight) <= $maxHeight && (($pa->getValue() + $itemValue) <= $maxValue) && ($pa->getWeight() + $itemWeight) <= $maxWeight && ($pa->getSum() + $dimensionsSum) <= $maxSum) {
                        $pa->addLength($itemLength);
                        $pa->addWidth($itemWidth);
                        $pa->addHeight($itemHeight);
                        $pa->addWeight($itemWeight);
                        $pa->addValue($itemValue);
                        $pa->addItem($_product);
                        break;
                    } else {
                        // verify if there are more packages to test before creating a new one
                        if ($loop < $packagesCount) {
                            // if there are more packages, continue to loop
                            $loop++;
                            continue;
                        } else {
                            // create a new package and insert item
                            $pb = new \Diamix\Correios\Model\Package();
                            $pb->addLength($itemLength);
                            $pb->addWidth($itemWidth);
                            $pb->addHeight($itemHeight);
                            $pb->addWeight($itemWeight);
                            $pb->addValue($itemValue);
                            $pb->addItem($_product);
                            
                            array_push($packages, $pb);
                            $packagesCount++;
                            break;
                        }
                    }
                }
            }
        }
        return $packages;
    }
    
    /**
     * Get Quotes
     * 
     * @param array $packages Packages list
     * @param bool $freeShipping Determines if free shipping is available
     * @return array
     */
    protected function getQuotes($packages, $freeShipping = false)
    {        
        // get services
        if ($this->helper->getConfigValue('usecontract') == 1) {
            $services = $this->helper->getConfigValue('contractmethods');
        } else {
            $services = $this->helper->getConfigValue('simplemethods');
        }
        
        // loop through packages
        $finalQuotes = array();
        
        foreach ($packages as $package) {
            $params = array(
                'services' => $services,
                'zipFrom' => $this->fromZip,
                'zipTo' => $this->toZip,
                'weight' => $package->getWeight(),
                'length' => $package->getLength(),
                'width' => $package->getWidth(),
                'height' => $package->getHeight(),
                'value' => $package->getValue(),
            );
            
            if ($this->helper->getConfigValue('delivery_confirmation') == 1) {
                $params['sCdAvisoRecebimento'] = 'S';
            }
            if ($this->helper->getConfigValue('delivery_customer_himself') == 1) {
                $params['sCdMaoPropria'] = 'S';
            }
            if ($this->helper->getConfigValue('declared_value') == 1 && $package->getValue() >= $this->helper->getConfigValue('gateway_limits/min_declared_value')) {
                $params['nVlValorDeclarado'] = $package->getValue();
            }
            
            // send request to webservice
            $quoteRequest = $this->processGatewayRequest($params);
            
            if (!$quoteRequest) {
                $this->_logger->info('Diamix_Correios: There was an error when getting a quote for a package with following data. Weight: ' . $package->getWeight() . ', length: ' . $package->getLength() . ', width: ' . $package->getWidth() . ', height: ' . $package->getHeight() . ', value: ' . $package->getValue());
                return $this->_result;
            }
            
            // split package quote response, allowing different services to be put together
            $i = 0;
            foreach ($quoteRequest as $partialQuote) {
                // verify if this method already has values
                if (array_key_exists($partialQuote['id'], $finalQuotes)) {
                    $finalQuotes[$partialQuote['id']]['cost'] += $this->helper->convertCommaToDot($partialQuote['cost']);
                    if ($finalQuotes[$partialQuote['id']]['delivery'] < $partialQuote['delivery']) {
                        $finalQuotes[$partialQuote['id']]['delivery'] = $partialQuote['delivery'];
                    }
                } else {
                    $finalQuotes[$partialQuote['id']]['cost'] = $this->helper->convertCommaToDot($partialQuote['cost']);
                    $finalQuotes[$partialQuote['id']]['delivery'] = (int)$partialQuote['delivery'];
                }
                
                // verify data to prevent wrong values; if incorrect value is provided, all service will be shut down
                if ($partialQuote['cost'] <= 0) {
                    $finalQuotes[$partialQuote['id']]['cost'] = -100;
                    continue;
                }
                $i++;
            }
        }
        
        // for each service, append quote result
        $freeMethod = $this->helper->getFreeShippingMethod($finalQuotes);
        foreach ($finalQuotes as $key => $final) {
            $key = str_pad($key, 5, '0', STR_PAD_LEFT);
            if ($freeShipping == 1 && $freeMethod == $key) {
                $quoteCost = 0;
            } else {
                $quoteCost = $final['cost'];
            }
            $shippingMethod = $key;
            $shippingTitle = $this->helper->getConfigValue('serv_' . $key);
            $shippingCost = $quoteCost;
            $shippingDelivery = $final['delivery'];
            
            // append result to Magento quote
            $this->appendShippingReturn($shippingMethod, $shippingTitle, $shippingCost, $shippingDelivery, $freeShipping);
        }
        return $this->_result;
    }
    
    /**
     * Append shipping return
     * 
     * Used to process shipping return and append it to main object
     * @param string $shippingMethod Shipping method code
     * @param string $shippingTitle Shipping method title
     * @param float $shippingCost Cost of this method
     * @param int $shippingDelivery Estimate time to delivery
     * @return bool
     */
    protected function appendShippingReturn($shippingMethod, $shippingTitle, $shippingCost = 0, $shippingDelivery = 0, $freeShipping = false)
    {
        // preparing and populating the shipping method
        $method = $this->rateMethodFactory->create();
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->helper->getConfigValue('title'));
        $method->setMethod($shippingMethod);
        $method->setCost($shippingCost);
        
        // including estimate time of delivery
        if ($this->helper->getConfigValue('show_delivery_days')) {
            $shippingDelivery += $this->helper->getConfigValue('add_delivery_days');
            if ($shippingDelivery > 0) {
                $deliveryText = sprintf($this->helper->getConfigValue('delivery_message'), $shippingDelivery);
                $shippingTitle .= ' - ' . $deliveryText;
            }
        }
        $method->setMethodTitle($shippingTitle);
        
        // applying extra fee if required
        if ($freeShipping) {
            $shippingPrice = $shippingCost;
        } else {
            $shippingPrice = $shippingCost + $this->helper->getConfigValue('add_extra_fee');
        }
        
        $method->setPrice($shippingPrice);
        
        // verify if this is the method "a cobrar"; if yes, the cost will be zero and the value charged to the customer when receiving the package is added to the title
        if ($this->helper->getConfigValue('acobrar_code') == $shippingMethod) {
            $method->setMethodTitle($shippingTitle . ' - ' . $this->helper->__('Pay on delivery:') . ' ' . Mage::helper('core')->currency($shippingCost, true, false));
            $method->setCost(0);
            $method->setPrice(0);
        }        
        $this->_result->append($method);
    }
    
    /**
     * Is Tracking Available
     */
    public function isTrackingAvailable()
    {
        return true;
    }
    
    /**
     * Get Tracking Info
     * 
     * Method to be triggered when a tracking info is requested.
     * @param array $trackings Trackings
     * @return \Magento\Shipping\Model\Tracking\ResultFactory
     */
    public function getTrackingInfo($trackings)
    {
        // instatiate the object and get tracking results
        $this->_result = $this->trackFactory->create();
        foreach ((array) $trackings as $trackingCode) {
            $this->requestTrackingInfo($trackingCode);
        }
        return $this->_result;
    }
    
    /**
     * Request Tracking Info
     * 
     * Get data from the API regarding tracking code
     * @param string $trackingCode Tracking code
     * @return bool
     */
    protected function requestTrackingInfo($trackingCode)
    {
        // prepare data to connect to API
        $data = array(
            'tracking_code' => $trackingCode,
        );
        $trackingRequest = $this->processGatewayTrackingRequest($data);
        
        if (!$trackingRequest) {
            return false;
        }
        
        $track = $trackingRequest;
        $track['progressdetail'] = $trackingRequest;
        $tracking = $this->trackStatusFactory->create();
        $tracking->setTracking($trackingCode);
        $tracking->setCarrier($this->_code);
        $tracking->setCarrierTitle($this->getConfigData('title'));
        $tracking->addData($track);
        
        $this->_result->append($tracking);
        return true;
    }
    
    /**
     * Process Gateway Request
     * 
     * Connects to Correios' webserver and process return.
     * @param array $params Params to perform the quote {services, zipFrom, zipTo, weight, height, width, length, value}
     * @param boolean $logger Log errors
     * @return array
     * @see https://www.correios.com.br/para-voce/correios-de-a-a-z/pdf/calculador-remoto-de-precos-e-prazos/manual-de-implementacao-do-calculo-remoto-de-precos-e-prazos   Manual de Implementação do Cálculo Remoto de Preços e Prazos
     */
    protected function processGatewayRequest($params, $logger = true)
    {
        $url = $this->helper->getConfigValue('url_ws_correios');
        $username = $this->helper->getConfigValue('usecontract') ? $this->helper->getConfigValue('carrier_username') : '';
        $password = $this->helper->getConfigValue('usecontract') ? $this->helper->getConfigValue('carrier_password') : '';
        
        // verify mandatory data
        if (!array_key_exists('services', $params) || !array_key_exists('zipFrom', $params) || !array_key_exists('zipTo', $params) || !array_key_exists('weight', $params) || !array_key_exists('height', $params) || !array_key_exists('width', $params) || !array_key_exists('length', $params) || !array_key_exists('value', $params)) {
            if ($logger) {
                $this->_logger->info('Diamix_Correios: Missing mandatory data when triggering connection to Correios.');
            }
            return false;
        }
        
        // verify valid value and weight
        if ($params['value'] < $this->helper->getConfigValue('gateway_limits/min_value') || $params['value'] > $this->helper->getConfigValue('gateway_limits/max_value') || $params['weight'] < $this->helper->getConfigValue('gateway_limits/min_weight') || $params['weight'] > $this->helper->getConfigValue('gateway_limits/max_weight')) {
            if ($logger) {
                $this->_logger->info('Diamix_Correios: A package with incorrect value or weight was submitted to quote.');
            }
            return false;
        }
        
        // verify valid measurements
        if ($params['height'] < $this->helper->getConfigValue('gateway_limits/min_height') || $params['height'] > $this->helper->getConfigValue('gateway_limits/max_height') || $params['width'] < $this->helper->getConfigValue('gateway_limits/min_width') || $params['width'] > $this->helper->getConfigValue('gateway_limits/max_width') || $params['length'] < $this->helper->getConfigValue('gateway_limits/min_length') || $params['length'] > $this->helper->getConfigValue('gateway_limits/max_length')) {
            if ($logger) {
                $this->_logger->info('Diamix_Correios: A package with incorrect dimensions was submitted to quote.');
            }
            return false;
        }
        
        // fill missing data with standard params
        if (!array_key_exists('nCdFormato', $params) || $params['nCdFormato'] == '') {
            $params['nCdFormato'] = '1';
        }
        if (!array_key_exists('nVlDiametro', $params) || $params['nVlDiametro'] == '') {
            $params['nVlDiametro'] = '0';
        }
        if (!array_key_exists('sCdMaoPropria', $params) || $params['sCdMaoPropria'] == '') {
            $params['sCdMaoPropria'] = 'N';
        }
        if (!array_key_exists('nVlValorDeclarado', $params) || $params['nVlValorDeclarado'] == '') {
            $params['nVlValorDeclarado'] = '0';
        }
        if (!array_key_exists('sCdAvisoRecebimento', $params) || $params['sCdAvisoRecebimento'] == '') {
            $params['sCdAvisoRecebimento'] = 'N';
        }
        
        // prepare data according to Correios definitions
        $data = array(
            'nCdEmpresa' => $username,
            'sDsSenha' => $password,
            'nCdServico' => $params['services'],
            'sCepOrigem' => $params['zipFrom'],
            'sCepDestino' => $params['zipTo'],
            'nVlPeso' => $params['weight'],
            'nCdFormato' => $params['nCdFormato'],
            'nVlAltura' => $params['height'],
            'nVlLargura' => $params['width'],
            'nVlComprimento' => $params['length'],
            'nVlDiametro' => $params['nVlDiametro'],
            'sCdMaoPropria' => $params['sCdMaoPropria'],
            'nVlValorDeclarado' => $params['nVlValorDeclarado'],
            'sCdAvisoRecebimento' => $params['sCdAvisoRecebimento'],
        );
        
        // connect to Correios and verify if there are errors
        try {
            $ws = new \SoapClient($url, array('connection_timeout' => $this->helper->getConfigValue('ws_timeout')));
        } catch(Exception $e){
            if ($logger) {
                $this->_logger->info('Diamix_Correios: Error when connecting to Correios webserver: ' . $e->getMessage());
            }
            $errorMessage = $this->helper->getConfigValue('die_errors_message');
            $this->appendError($errorMessage);
            return false;
        }
        
        $correios = $ws->CalcPrecoPrazo($data);
        
        // return on connection error
        if (!$correios) {
            if ($logger) {
                $this->_logger->info('Diamix_Correios: Error when connecting to Correios webserver');
            }
            $errorMessage = $this->helper->getConfigValue('die_errors_message');
            $this->appendError($errorMessage);
            return false;
        }
        
        // logs Correios' return when this option is active; it must be used only as a maintenance mode, to avoid filling up all log file
        if ($this->helper->getConfigValue('save_quotes') == 1) {
            $this->_logger->info('Diamix_Correios: Maintenance mode, quote generated to ZIP: ' . $data['sCepDestino'] . '. Correios return: ' . var_export($correios, 1));
        }
        
        // verify return and process it
        $count = count($correios->CalcPrecoPrazoResult->Servicos->cServico);
        if ($count < 1) {
            if ($logger) {
                $this->_logger->info('Diamix_Correios: Error when processing Correios webserver return');
            }
            return false;
        } elseif ($count == 1) {
            $quote = $correios->CalcPrecoPrazoResult->Servicos->cServico;
            if ($quote->Erro != 0) {
                // validate according to error categories and process it
                $error = $this->processRequestError($quote->Erro, $quote->MsgErro);
                if (!$error) {
                    if ($logger) {
                        $this->_logger->info('Diamix_Correios: Correios webserver returned an error that is not included on current errors list: ' . $quote->Erro . '. Error message: ' . $quote->MsgErro);
                    }
                    return false;
                }
                
                // return value if error allow quotes
                if ($error['status'] == 'die') {
                    // error append
                    $this->appendError($error['message']);
                    return false;
                } elseif ($error['status'] == 'verify') {
                    if ($quote->Valor != 0) {
                        $response = array('id' => $quote->Codigo, 'cost' => $quote->Valor, 'delivery' => $quote->PrazoEntrega);
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }                
            } else {
                $response = array('id' => $quote->Codigo, 'cost' => $quote->Valor, 'delivery' => $quote->PrazoEntrega);
            }
        } else {
            $errors = false;
            $response = array();
            foreach ($correios->CalcPrecoPrazoResult->Servicos->cServico as $quote) {
                if ($quote->Erro != 0) {
                    // validate according to error categories and process it
                    $error = $this->processRequestError($quote->Erro, $quote->MsgErro);
                    if (!$error) {
                        if ($logger) {
                            $this->_logger->info('Diamix_Correios: Correios webserver returned an error that is not included on current errors list: ' . $quote->Erro . '. Error message: ' . $quote->MsgErro);
                        }
                        return false;
                    }
                    
                    // return value if error allow quotes
                    if ($error['status'] == 'die') {
                        // error append
                        $this->appendError($error['message']);
                        return false;
                    } elseif ($error['status'] == 'verify') {
                        if ($quote->Valor != 0) {
                            array_push($response, array('id' => $quote->Codigo, 'cost' => $quote->Valor, 'delivery' => $quote->PrazoEntrega));
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                } else {
                    array_push($response, array('id' => $quote->Codigo, 'cost' => $quote->Valor, 'delivery' => $quote->PrazoEntrega));
                }
            }
        }
        return $response;
	}
    
    /**
     * Process Gateway Tracking Request
     * 
     * Connects to Correios' webserver for tracking and process return.
     * @param array $params Params to perform the tracking {tracking_code}
     * @param boolean $logger Log errors
     * @return array
     * @see https://www.correios.com.br/para-voce/correios-de-a-a-z/pdf/rastreamento-de-objetos/manual_rastreamentoobjetosws.pdf    Guia técnico para implementação do Rastreamento de Objetos via WebService / SOAP
     */
    protected function processGatewayTrackingRequest($params, $logger = true)
    {
        $url = $this->helper->getConfigValue('url_ws_tracking_correios');
        $username = $this->helper->getConfigValue('usecontract') ? $this->helper->getConfigValue('carrier_username') : '';
        $password = $this->helper->getConfigValue('usecontract') ? $this->helper->getConfigValue('carrier_password') : '';
        
        // verify mandatory data
        if (!array_key_exists('tracking_code', $params)) {
            if ($logger) {
                $this->_logger->info('Diamix_Correios: Missing mandatory data when triggering connection to Correios tracking service.');
            }
            return false;
        }
        
        // fill missing data with standard params
        if (!array_key_exists('tipo', $params) || $params['tipo'] == '') {
            $params['tipo'] = 'L';
        }
        if (!array_key_exists('resultado', $params) || $params['resultado'] == '') {
            $params['resultado'] = 'T';
        }
        if (!array_key_exists('lingua', $params) || $params['lingua'] == '') {
            $params['lingua'] = '101';
        }
        
        // prepare data according to Correios definitions
        $data = array(
            'usuario' => $username,
            'senha' => $password,
            'tipo' => $params['tipo'],
            'resultado' => $params['resultado'],
            'lingua' => $params['lingua'],
            'objetos' => $params['tracking_code'],
        );
        
        // connect to Correios and verify if there are errors
        try {
            $ws = new \SoapClient($url, array('connection_timeout' => $this->helper->getConfigValue('ws_timeout')));
        } catch(Exception $e){
            if ($logger) {
                $this->_logger->info('Diamix_Correios: Error when connecting to Correios tracking webserver: ' . $e->getMessage());
            }
            return false;
        }
        
        $correios = $ws->buscaEventos($data);
        
        // return on connection error
        if (!$correios) {
            if ($logger) {
                $this->_logger->info('Diamix_Correios: Error when connecting to Correios webserver');
            }
            return false;
        }
        
        // process tracking data and prepare array
        $trackingData = $correios->return->objeto;
        $trackingResult = array();
        
        foreach ($trackingData->evento as $event) {
            $date = new \Zend_Date($event->data, 'dd/mm/YYYY');
            $tempArray = array(
                'deliverydate' => $date->toString('YYYY-mm-dd'),
                'deliverytime' => $event->hora,
                'deliverylocation' => trim($event->local) . ' - ' . trim($event->cidade) . ', ' . trim($event->uf),
                'status' => $event->status,
                'activity' => $event->descricao,
            );
            array_push($trackingResult, $tempArray);
            unset($date);
        }
        return $trackingResult;
	}
    
    /**
     * Process Request Error
     * 
     * Used to process errors when requesting data from Correios webservice
     * @param string $error The error code
     * @param string $errorMsg The error message
     * @return array {status, message}
     */
    protected function processRequestError($error, $errorMsg = 'No error message')
    {
        // check for fatal errors
        $dieErrors = explode(',', $this->helper->getConfigValue('die_errors'));
        if (in_array($error, $dieErrors)) {
            $this->_logger->info('Diamix_Correios: There was a fatal error when getting a quote from Correios webservice. Error ID: ' . $error . ', Correios message: ' . $errorMsg);
            return array(
                'status' => 'die',
                'message' => $this->helper->getConfigValue('die_errors_message'),
            );
        }
        
        // check for fake errors, when it is not a real error
        $fakeErrors = explode(',', $this->helper->getConfigValue('fake_errors'));
        if (in_array($error, $fakeErrors)) {
            return array(
                'status' => 'verify',
            );
        }
        
        // check for client errors
        $clientErrors = explode(',', $this->helper->getConfigValue('client_errors'));
        if (in_array($error, $clientErrors)) {
            return array(
                'status' => 'die',
                'message' => $this->helper->getConfigValue('client_errors_message'),
            );
        }
        
        // check for store misconfig errors
        $storeErrors = explode(',', $this->helper->getConfigValue('store_errors'));
        if (in_array($error, $storeErrors)) {
            $this->_logger->info('Diamix_Correios: There was an error when getting a quote from Correios webservice. This seems to be a misconfig on the store. Error ID: ' . $error . ', Correios message: ' . $errorMsg);
            return array(
                'status' => 'die',
                'message' => $this->helper->getConfigValue('store_errors_message'),
            );
        }
        $this->_logger->info('Diamix_Correios: An error has triggered the Process Request Error method but it was not possible to verify this error. Error: ' . $error);
        return false;
    }
    
    /**
     * Append Error
     * 
     * @param string $errorMessage The error message
     * @return boolean
     */
    public function appendError($errorMessage)
    {
        $error = $this->_rateErrorFactory->create();
        $error->setCarrier($this->_code);
        $error->setCarrierTitle($this->helper->getConfigValue('title'));
        $error->setErrorMessage($errorMessage);
        $this->_result->append($error);
        return true;
    }
}
