<?php

/**
 *
 *
 * @category Mygento
 * @package Mygento_ConfIndex
 * @copyright Copyright © 2015 NKS LLC. (http://www.mygento.ru)
 */
class Mygento_ConfIndex_Model_Source_List {

    public function getAllOptions() {
        $attributes = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getAttributeCollection();

        // Localize attribute label (if you need it)
        //$attributes->addStoreLabel(Mage::app()->getStore()->getId());
        $attributes->setOrder('frontend_label', 'ASC');

        $_options = array();

        $_options[] = array(
            'label' => Mage::helper('confindex')->__('No usage'),
            'value' => 0
        );

        $attributes->addFilter('used_in_product_listing', '1');
        
        // Loop over all attributes
        foreach ($attributes as $attr) {

            $label = $attr->getStoreLabel() ? $attr->getStoreLabel() : $attr->getFrontendLabel();
            if ('' != $label) {
                $_options[] = array('label' => $label, 'value' => $attr->getAttributeCode());
            }
        }
        return $_options;
    }

    public function toOptionArray() {
        return $this->getAllOptions();
    }

}
