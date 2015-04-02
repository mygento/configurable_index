<?php

class Mygento_ConfIndex_Helper_Data extends Mage_Core_Helper_Abstract
{

    const XML_PATH_ATTRIBUTE = 'confindex/general/attribute';

    public function addLog($text)
    {
        if (Mage::getStoreConfig('confindex/general/debug')) {
            Mage::log($text, null, 'confindex.log');
        }
    }

}
