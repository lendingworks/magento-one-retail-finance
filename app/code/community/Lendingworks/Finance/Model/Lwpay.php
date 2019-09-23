<?php

class Lendingworks_Finance_Model_Lwpay extends Mage_Payment_Model_Method_Abstract
{
  // This is the identifier of our payment method
  protected $_code = 'lwfinance';
  protected $_isInitializeNeeded = true;
  protected $_canUseInternal = true;
  protected $_canUseForMultishipping = true;
  protected $_canUseCheckout = true;

  protected $_supportedCurrencyCodes = array('GBP');

  protected $_formBlockType = 'lendingworks_finance/form_paymentmethod';
  protected $_infoBlockType = 'lendingworks_finance/info_paymentmethod';

  /**
   * Check method for only British pound
   *
   * @param string $currencyCode
   * @return boolean
   */
  public function canUseForCurrency($currencyCode)
  {
    if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
      return false;
    }

    return true;
  }

}