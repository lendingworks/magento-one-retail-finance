<?php

class Lendingworks_Finance_OrderController extends Mage_Core_Controller_Front_Action
{

  protected $_jsonResultFactory;

  const ORDER_SESSION_KEY = 'lw-rf-order-id';
  const OVERRIDE_SCRIPT_SOURCE_KEY = 'script_source';



  public function createLwOrderAction()
  {
    if (!$this->getRequest()->isPost()) {
      return $this->result(405, 'Invalid request');
    }

    $quote = $this->_getCheckoutSession()->getQuote();

    /** @var Lendingworks_Finance_Helper_Data $helper */
    $helper = Mage::helper('lendingworks_finance');

    if (!$quote) {
      return $this->result(404, "Order not found");
    }

    $connect = Mage::getModel('lendingworks_finance/connect');

    $postData = json_encode($connect->buildProductsData($quote), JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return $this->result(400, 'Unable to encode createOrder message body'. json_last_error_msg());
    }

    $hash = hash('sha256', $postData . $quote->getCustomerEmail());

    $sessionData = $this->_getCheckoutSession()->getData($helper::ORDER_SESSION_KEY);

    if ($sessionData && !empty($sessionData[$hash])) {
      $additionalData = array(
        'token' => $sessionData[$hash],
        'script_url' => $helper->getCheckoutScriptSourceForEnvironment(),
      );
      return $this->result(200, 'Order token successfully loaded', $additionalData);
    }


    $result = $connect->createOrder($postData);

    if (empty($result) || !isset($result['token'])) {
      return $this->result($result['code'], 'no token found in response', $result['body']);
    }

    $this->_getCheckoutSession()->setData($helper::ORDER_SESSION_KEY, array($hash => $result['token']));

    $additionalData = array(
      'token' => $result['token'],
      'script_url' => $helper->getCheckoutScriptSourceForEnvironment(),
    );

    return $this->result(200, 'Order successfully created', $additionalData);
  }

  /**
   * @param int $statusCode
   * @param string $message
   * @param array $additionalData
   *
   * @return
   */
  protected function result($statusCode, $message, $additionalData = array())
  {
    $data = array_merge($additionalData, array('message' => __($message)));

    $return = $this->getResponse();
    $return->setHttpResponseCode($statusCode);

    $return->setHeader('Content-type', 'application/json', true);
    $return->setBody(json_encode($data));

    return $return;
  }

  public function callbackAction()
  {

    if (!$this->getRequest()->isPost()) {
      return $this->result(405, 'Invalid request');
    }

    $data = json_decode(file_get_contents('php://input'));

    $requiredKeys = array('reference', 'status');
    foreach ($requiredKeys as $key) {
      if (empty($data->$key)) {
        return $this->result(400, 'The request body is invalid.');
      }
    }

    /** @var Lendingworks_Finance_Helper_Data $helper */
    $helper = Mage::helper('lendingworks_finance');
    $remoteStatus = $data->status;
    if (!$helper::isValidStatus($remoteStatus)) {
      return $this->result(400, 'Invalid order status received: ' . $remoteStatus);
    }

    $reference = $data->reference;

    try {
      $order = $this->getOrderByLWID($reference);

      if ($order === null) {
        return $this->result(400, 'Unable to find Order with reference: '.$reference);
      }

      $quoteId = $order->getQuoteId();

      $quote = Mage::getModel('sales/quote')->load($quoteId);
      $quote->setData($helper::ORDER_STATUS_ATTRIBUTE_KEY, $remoteStatus);
      $quote->save();

      $order->setData($helper::ORDER_STATUS_ATTRIBUTE_KEY, $remoteStatus);
      $order->save();
    } catch (Exception $exception){
      return $this->result(400, 'Something Went wrong, Please try again later  ' . $exception->getMessage());
    }

    return $this->result(200, 'Updated order status using callback api!');

  }


  public function addLWOrderDetailsAction()
  {
    if (!$this->getRequest()->isPost()) {
      return $this->result(405, 'Invalid request');
    }

    /** @var Lendingworks_Finance_Helper_Data $helper */
    $helper = Mage::helper('lendingworks_finance');
    $orderId = $this->getRequest()->getPost('lw_order_id');
    if ($orderId === null) {
      return $this->result(400, 'Missing lw_order_id POST data');
    }

    $orderStatus = $this->getRequest()->getPost('lw_order_status');
    if ($orderStatus === null || !$helper::isValidStatus($orderStatus)) {
      return $this->result(400, 'Invalid lw_order_status received: ' . $orderStatus);
    }

    $quote = $this->_getCheckoutSession()->getQuote();

    try {
      $quote->setData($helper::ORDER_ID_ATTRIBUTE_KEY, $orderId);
      $quote->setData($helper::ORDER_STATUS_ATTRIBUTE_KEY, $orderStatus);
      $quote->setData($helper::ORDER_FULFILMENT_STATUS_ATTRIBUTE_KEY, $helper::ORDER_FULFILMENT_STATUS_UNFULFILLED);
      $quote->save();
    } catch (Exception $exception){
      return $this->result(400, 'Something Went wrong, Please try again later  ' . $exception->getMessage());
    }

    return $this->result(200, 'Application successful!', array('quote_id' => $quote->getId()));

  }



  /**
   * Return checkout session instance
   *
   * @return Mage_Checkout_Model_Session
   */
  protected function _getCheckoutSession()
  {
    return Mage::getSingleton('checkout/session');
  }

  public function fulfillOrderAction()
  {
    if (!$this->getRequest()->isPost()) {
      return $this->result(405, 'Invalid request');
    }

    $lwID = $this->getRequest()->getParam('lw_id');

    if (!$lwID) {
      Mage::log('Missing lw_id param');
      return $this->_redirectReferer();
    }

    $order = $this->getOrderByLWID($lwID);

    if (!$order) {
      return $this->_redirectReferer();
    }

    $postData = json_encode(array('reference' => $lwID), JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return $this->result(400, 'Unable to encode fulfillOrder message body: ' . json_last_error_msg());
    }

    /** @var Lendingworks_Finance_Helper_Data $helper */
    $helper = Mage::helper('lendingworks_finance');
    /** @var Lendingworks_Finance_Model_Connect $connect */
    $connect = Mage::getModel('lendingworks_finance/connect');

    $response = $connect->fulfillOrder($postData);
    $message = 'There was an unexpected error - please try again';


    if (empty($response)) {
      Mage::log($message);
      return $this->_redirectReferer();
    }

    /** @var Mage_Customer_Model_Session $session */
    $session = Mage::getSingleton('customer/session');

    $fulfilmentStatus = $helper::ORDER_FULFILMENT_STATUS_ERROR;
    switch ($response['code']) {
      case 403:
        $message = 'Invalid credentials';
        $session->addError($message);
          break;
      case 400:
        $message = 'Order is either already fulfilled or in a state that cannot be fulfilled yet';
        $session->addError($message);
          break;
      case 204:
      case 200:
        $message = 'Order ' . $lwID . ' successfully fulfilled';
        $session->addSuccess($message);
        $fulfilmentStatus = $helper::ORDER_FULFILMENT_STATUS_COMPLETE;
          break;
      default:
        $session->addSuccess($message);
          break;
    }

    Mage::log($response->getStatusCode() . ': ' . $message);
    $order->setData($helper::ORDER_FULFILMENT_STATUS_ATTRIBUTE_KEY, $fulfilmentStatus);
    $order->save();
    return $this->_redirectReferer();
  }


  /**
   * Retrieve order instance by specified Lendingworks Order id
   * @param string $lwID
   *
   * @return Mage_Sales_Model_Order|null
   */
  protected function getOrderByLWID($lwID)
  {
    $model = Mage::getModel('sales/order');
    /** @var $order Mage_Sales_Model_Order */
    $order = $model->load($lwID, 'lendingworks_order_id');
    if ($order->getId() !== null) {
      return $order;
    }

    return null;
  }




}