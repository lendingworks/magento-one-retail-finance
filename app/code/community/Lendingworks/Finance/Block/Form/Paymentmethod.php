<?php

class Lendingworks_Finance_Block_Form_Paymentmethod extends Mage_Payment_Block_Form
{
  protected function _construct()
  {
    parent::_construct();
    $this->setTemplate('lendingworks/finance/form/paymentmethod.phtml');
  }


  /**
   * Set method info
   *
   * @return Lendingworks_Finance_Block_Form_Paymentmethod
   */
  public function setMethodInfo()
  {
    $payment = Mage::getSingleton('checkout/type_onepage')
      ->getQuote()
      ->getPayment();
    $this->setMethod($payment->getMethodInstance());

    return $this;
  }
}