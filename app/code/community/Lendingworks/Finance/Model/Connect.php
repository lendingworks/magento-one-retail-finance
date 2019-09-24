<?php

class Lendingworks_Finance_Model_Connect
{

  public function toOptionArray()
  {
    return array(
      array('value'=>'int', 'label'=>'Integration/Testing'),
      array('value'=>'prod', 'label'=>'Production'),
    );
  }

  const PRODUCTION = 'prod';
  const PRODUCTION_LABEL = 'Production';
  const TESTING = 'int';
  const TESTING_LABEL = 'Integration/Testing';


  const ORDER_SESSION_KEY = 'lw-rf-order-id';
  const ORDER_ID_ATTRIBUTE_KEY = 'lendingworks_order_id';
  const ORDER_STATUS_ATTRIBUTE_KEY = 'lendingworks_order_status';
  const ORDER_FULFILMENT_STATUS_ATTRIBUTE_KEY = 'lendingworks_order_fulfilment_status';

  const OVERRIDES_KEY = 'lendingworks_retailfinance_overrides';
  const OVERRIDE_BASE_URL_KEY = 'base_url';
  const OVERRIDE_SCRIPT_SOURCE_KEY = 'script_source';
  const OVERRIDE_API_KEY_KEY = 'api_key';
  const OVERRIDE_MOCK_API_RESPONSE_KEY = 'mock_successful_api_response';

  const API_CREATE_ORDER_ENDPOINT = 'orders';
  const API_FULFILL_ORDER_ENDPOINT = 'loan-requests/fulfill';

  /**
   *
   * @param Mage_Sales_Model_Quote $quote
   *
   * builds array of product data
   *
   * @return array
   */
  public function buildProductsData($quote)
  {
    $products = array();
    $totalDiscount = 0.0000;
    $totalTax = 0.0000;


    /** @var Mage_Sales_Model_Quote_Item $item */
    foreach ($quote->getAllItems() as $item) {
      $quantity = $item->getQty();
      $basePrice = $item->getPrice();
      $taxPercent = $item->getTaxPercent();

      $products[] = array(
        'cost' => $basePrice,
        'quantity' => $quantity,
        'description' => $item->getDescription() ?: $item->getName(),
      );

      if ($item->getDiscountAmount() > 0) {
        $totalDiscount -= $item->getDiscountAmount();
      }

      if ($taxPercent && $taxPercent > 0) {
        $totalTax += $basePrice * ($taxPercent / 100) * $quantity;
      }
    }

    // Add shipping data
    $products[] = array(
      'cost' => $quote->getShippingAddress()->getShippingAmount(),
      'quantity' => 1.0,
      'description' => 'Shipping: ' . $quote->getShippingAddress()->getShippingDescription(),
    );

    // Add any discount
    if ($totalDiscount < 0) {
      $products[] = array(
        'cost' => number_format($totalDiscount, 4, '.', ''),
        'quantity' => 1.0,
        'description' => 'Discount',
      );
    }

    // Add any tax
    if ($totalTax > 0) {
      $products[] = array(
        'cost' => number_format($totalTax, 4, '.', ''),
        'quantity' => 1.0,
        'description' => 'Total tax applied',
      );
    }

    return array(
      'amount' => $quote->getGrandTotal(),
      'products' => $products,
    );
  }


  /**
   *
   * @param $postData
   *
   * @return array
   * @throws Mage_Core_Exception
   */
  public function createOrder($postData)
  {
    $helper = Mage::helper('lendingworks_finance');

    $apiURL = $helper->getConnectEnvironment() . self::API_CREATE_ORDER_ENDPOINT;

    $apiKey = Mage::getModel('core/encryption')->decrypt(
      Mage::getStoreConfig(
        'payment/' . $helper::PAYMENT_CODE . '/api_key',
        Mage::app()->getStore()
      )
    );

    $return = array();

    $headers = array(
      'Content-type: application/json',
      'Authorization: RetailApiKey ' . $apiKey,
    );
    try {
      $response = $this->call('POST', $apiURL, $headers, $postData);
    } catch (Mage_Core_Exception $e) {
      throw $e;
    }

    $code = Zend_Http_Response::extractCode($response);
    $body = Zend_Http_Response::extractBody($response);

    $return['code'] = $code;

    if ($code !== 200) {
      $message = sprintf(
        'Could not create order, non-200 HTTP code returned: %d - %s',
        $code,
        $body
      );
      $return['body'] = $message;
      return $return;
    }

    $result = json_decode($body, true);
    if ($result === null) {
      $return['code'] = 400;
      $return['body'] = 'Unable to decode API response';
    }

    $return['token'] = $result['token'];
    $return['body'] = 'Order successful';

    return $return;
  }


  public function fulfillOrder($postData)
  {
    /** @var Lendingworks_Finance_Helper_Data $helper */
    $helper = Mage::helper('lendingworks_finance');

    $apiURL = $helper->getConnectEnvironment() . self::API_FULFILL_ORDER_ENDPOINT;

    $apiKey = Mage::getModel('core/encryption')->decrypt(
      Mage::getStoreConfig(
        'payment/' . $helper::PAYMENT_CODE . '/api_key',
        Mage::app()->getStore()
      )
    );

    $return = array();

    $headers = array(
      'Content-type: application/json',
      'Authorization: RetailApiKey ' . $apiKey,
    );
    try {
      $response = $this->call('POST', $apiURL, $headers, $postData);
    } catch (Mage_Core_Exception $e) {
      Mage::logException($e);
      return $return;
    }

    $code = Zend_Http_Response::extractCode($response);
    $body = Zend_Http_Response::extractBody($response);

    $result = json_decode($body, true);
    if ($result === null) {
      $return['code'] = 400;
      $return['body'] = 'Unable to decode API response';
      return $return;
    }

    $return['code'] = $code;

    if ($code !== 200) {
      $message = sprintf(
        'Could not fulfill order, non-200 HTTP code returned: %d - %s',
        $code,
        $body
      );
      $return['body'] = $message;

      return $return;
    }

    $return['body'] = 'Fulfilled order successfully';

    return $return;
  }


  /**
   * Do the API call
   *
   * @param string $methodName
   * @param $apiURL
   * @param $headers
   * @param $requestBody
   *
   * @return string
   * @throws Mage_Core_Exception
   */
  public function call($methodName, $apiURL, $headers, $requestBody)
  {
    try {
      $http = new Varien_Http_Adapter_Curl();

      $http->write(
        $methodName,
        $apiURL,
        '1.1',
        $headers,
        $requestBody
      );
      $response = $http->read();
    } catch (Exception $e) {
      throw $e;
    }

    // handle transport error
    if ($http->getErrno()) {
      Mage::logException(
        new Exception(
          sprintf('Lendingworks api CURL connection error #%s: %s', $http->getErrno(), $http->getError())
        )
      );
      $http->close();

      Mage::throwException('Unable to communicate with the Lendingworks API.');
    }

    $this->_handleCallErrors($response);

    $http->close();

    return $response;

  }

  /**
   * Handle logical errors
   *
   * @param array $response
   * @throws Mage_Core_Exception
   */
  protected function _handleCallErrors($response)
  {

    $code =  Zend_Http_Response::extractCode($response);
    $body =  Zend_Http_Response::extractBody($response);

    if (!$code || $code == 200) {
      return;
    }

    if ($body != '') {
      $errorDetail = json_decode($body, true);
      if ($errorDetail === null) {
        $error['message'] = 'Could not create Lendingworks order, Unable to decode API response';
      }
    }

    $error['message'] = sprintf(
      'Non-200 HTTP code returned: %d - %s',
      $code,
      $body
    );

    $exception = new Mage_Core_Exception($error['message'], $code);
    Mage::logException($exception);

    throw $exception;
  }

}
