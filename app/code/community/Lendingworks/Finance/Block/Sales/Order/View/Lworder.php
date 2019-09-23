<?php


class Lendingworks_finance_Block_Sales_Order_View_Lworder extends Mage_Core_Block_Template
{

  protected $_order;

  /**
   * Retrieve current order model instance
   *
   * @return Mage_Sales_Model_Order
   */
  public function getOrder()
  {
    if ($this->_order === null) {
      if (Mage::registry('current_order')) {
        $order = Mage::registry('current_order');
      } elseif (Mage::registry('order')) {
        $order = Mage::registry('order');
      } else {
        $order = new Varien_Object();
      }

      $this->_order = $order;
    }

    return $this->_order;
  }

  /**
   * @return string
   */
  public function getLWOrderID()
  {
    $helper = Mage::helper('lendingworks_finance');
    return $this->getOrder()->getData($helper::ORDER_ID_ATTRIBUTE_KEY) ?: '-';
  }

  /**
   * @return string
   */
  public function getLWStatus()
  {
    $helper = Mage::helper('lendingworks_finance');
    return $this->getOrder()->getData($helper::ORDER_STATUS_ATTRIBUTE_KEY) ?: '-';
  }

  /**
   * @return string
   */
  public function getLWFulfilmentStatus()
  {
    $helper = Mage::helper('lendingworks_finance');
    return $this->getOrder()->getData($helper::ORDER_FULFILMENT_STATUS_ATTRIBUTE_KEY)
      ?: $helper::ORDER_FULFILMENT_STATUS_UNFULFILLED;
  }

  public function canFulfill()
  {
    $helper = Mage::helper('lendingworks_finance');
    return Mage::getStoreConfig(
        'payment/' . $helper::PAYMENT_CODE . '/manual_fulfilment',
        Mage::app()->getStore()
    )
      && $helper::isFulfillableStatus($this->getLWFulfilmentStatus());
  }

  public function isFinancedByLw()
  {
    $method = $this->getOrder()->getPayment()->getMethod();
    return $method;
  }
}