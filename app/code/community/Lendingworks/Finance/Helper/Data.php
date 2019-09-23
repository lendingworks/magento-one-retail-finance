<?php
class Lendingworks_Finance_Helper_Data extends Mage_Core_Helper_Abstract
{

  const ORDER_SESSION_KEY = 'lw-rf-order-id';


  const PRODUCTION = 'prod';
  const PRODUCTION_LABEL = 'Production';
  const TESTING = 'int';
  const TESTING_LABEL = 'Integration/Testing';

  const ORDER_STATUS_APPROVED = 'Approved';
  const ORDER_STATUS_ACCEPTED = 'Accepted';
  const ORDER_STATUS_CANCELLED = 'Cancelled';
  const ORDER_STATUS_REFERRED = 'Referred';
  const ORDER_STATUS_EXPIRED = 'Expired';
  const ORDER_STATUS_DECLINED = 'Declined';
  const ORDER_STATUS_FULFILLED = 'Fulfilled';

  const ORDER_FULFILMENT_STATUS_UNFULFILLED = 'Unfulfilled';
  const ORDER_FULFILMENT_STATUS_PENDING = 'Pending';
  const ORDER_FULFILMENT_STATUS_ERROR = 'Error';
  const ORDER_FULFILMENT_STATUS_COMPLETE = 'Complete';

  const OVERRIDE_SCRIPT_SOURCE_KEY = 'script_source';

  const ORDER_ID_ATTRIBUTE_KEY = 'lendingworks_order_id';
  const ORDER_STATUS_ATTRIBUTE_KEY = 'lendingworks_order_status';
  const ORDER_FULFILMENT_STATUS_ATTRIBUTE_KEY = 'lendingworks_order_fulfilment_status';

  const PAYMENT_CODE = 'lwfinance';

  /**
   * @param string $status
   *
   * @return bool
   */
  public static function isValidStatus($status)
  {
    return self::in_array_i(
        $status, array(
        self::ORDER_STATUS_APPROVED,
        self::ORDER_STATUS_ACCEPTED,
        self::ORDER_STATUS_CANCELLED,
        self::ORDER_STATUS_DECLINED,
        self::ORDER_STATUS_EXPIRED,
        self::ORDER_STATUS_EXPIRED,
        self::ORDER_STATUS_FULFILLED,
        self::ORDER_STATUS_REFERRED
        )
    );
  }

  /**
   * @param string $needle
   * @param array $haystack
   *
   * @return bool
   */
  protected static function in_array_i($needle, $haystack)
  {
    return in_array(strtolower($needle), array_map('strtolower', $haystack));
  }

  /**
   * @param $status
   *
   * @return bool
   */
  public static function isFulfillableStatus($status)
  {
    return self::in_array_i(
        $status, array(
        self::ORDER_FULFILMENT_STATUS_UNFULFILLED,
        self::ORDER_FULFILMENT_STATUS_ERROR
        )
    );
  }


  /**
   * Cancel last placed order with specified comment message
   *
   * @param string $comment Comment appended to order history
   * @return bool True if order cancelled, false otherwise
   */
  public function cancelCurrentOrder($comment)
  {
    $order = $this->_getCheckoutSession()->getLastRealOrder();
    if ($order->getId() && $order->getState() != Mage_Sales_Model_Order::STATE_CANCELED) {
      $order->registerCancellation($comment)->save();
      return true;
    }

    return false;
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

  /**
   * Return sales quote instance for specified ID
   *
   * @param int $quoteId Quote identifier
   * @return Mage_Sales_Model_Quote
   */
  protected function _getQuote($quoteId)
  {
    return Mage::getModel('sales/quote')->load($quoteId);
  }



  /**
   * @return string|null
   */
  public function getConnectEnvironment()
  {
    $environment = $this->getRFPaymentConfig('connect_select');
    switch ($environment) {
      case self::TESTING:
          return 'https://retail-sandbox.lendingworks.co.uk/api/v2/';
      case self::PRODUCTION:
          return 'https://www.lendingworks.co.uk/api/v2/';
      default:
          return null;
    }
  }

  /**
   * Helper for config values that checks for overrides
   *
   * @param string $key
   * @return mixed
   */
  public function getRFPaymentConfig($key)
  {
    return Mage::getStoreConfig('payment/'.self::PAYMENT_CODE . '/' . $key);
  }

  /**
   * @return string|null
   */
  public function getCheckoutScriptSourceForEnvironment()
  {
    $environment = $this->getRFPaymentConfig('connect_select');
    switch ($environment) {
      case self::TESTING:
          return 'https://retail-sandbox.secure.lendingworks.co.uk/checkout.js';
      case self::PRODUCTION:
          return 'https://secure.lendingworks.co.uk/checkout.js';
      default:
          return null;
    }
  }

}