<?php


class Lendingworks_Finance_Model_Observer extends Varien_Object
{
  public function setLWDataOnOrder(Varien_Event_Observer $observer)
  {
    /** @var Lendingworks_Finance_Helper_Data $helper */
    $helper = Mage::helper('lendingworks_finance');

    /** @var Mage_Sales_Model_Quote $quote */
    $quote = $observer->getQuote();
    $method = $quote->getPayment()->getMethod();

    if ($method === $helper::PAYMENT_CODE) {
      /** @var $order Mage_Sales_Model_Order */
      $order = $observer->getOrder();

      $orderId = $quote->getData($helper::ORDER_ID_ATTRIBUTE_KEY);

      if ($orderId === null) {
        throw new Mage_Core_Exception('Lending Works order could not be placed');
      }

      if ($quote->getData($helper::ORDER_ID_ATTRIBUTE_KEY) != '') {
        $order->setData($helper::ORDER_STATUS_ATTRIBUTE_KEY, $orderId);
      }

      $orderStatus = $quote->getData($helper::ORDER_STATUS_ATTRIBUTE_KEY);
      if ($quote->getData($helper::ORDER_STATUS_ATTRIBUTE_KEY) != '') {
        $order->setData($helper::ORDER_STATUS_ATTRIBUTE_KEY, $orderStatus);
      }

      $orderFulfillStatus = $quote->getData($helper::ORDER_FULFILMENT_STATUS_ATTRIBUTE_KEY);
      if ($quote->getData($helper::ORDER_FULFILMENT_STATUS_ATTRIBUTE_KEY) != '') {
        $order->setData($helper::ORDER_FULFILMENT_STATUS_ATTRIBUTE_KEY, $orderFulfillStatus);
      }
    }

    return $this;
  }
}
