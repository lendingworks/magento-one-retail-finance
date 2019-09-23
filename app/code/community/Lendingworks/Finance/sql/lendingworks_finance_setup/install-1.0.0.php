<?php

/**
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

$installer->run(
    "
ALTER TABLE `sales_flat_order` 
ADD `lendingworks_order_id` VARCHAR( 255 ) NULL DEFAULT NULL,
ADD `lendingworks_order_status` VARCHAR( 255 )  NULL DEFAULT NULL,
ADD `lendingworks_order_fulfilment_status` VARCHAR( 255 ) NULL DEFAULT NULL;
  
ALTER TABLE `sales_flat_quote` 
ADD `lendingworks_order_id` VARCHAR( 255 ) NULL DEFAULT NULL,
ADD `lendingworks_order_status` VARCHAR( 255 ) NULL DEFAULT NULL,
ADD `lendingworks_order_fulfilment_status` VARCHAR( 255 ) NULL DEFAULT NULL;
"
);
$installer->endSetup();