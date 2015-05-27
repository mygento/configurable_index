<?php

/**
 *
 *
 * @category Mygento
 * @package Mygento_ConfIndex
 * @copyright Copyright © 2015 NKS LLC. (http://www.mygento.ru)
 */
class Mygento_ConfIndex_Model_Indexer extends Mage_Index_Model_Indexer_Abstract
{

    /**
     * Data key for matching result to be saved in
     */
    const EVENT_MATCH_RESULT_KEY = 'confindex_match_result';

    /**
     * Data key for reindexing all result
     */
    const EVENT_REINDEX_ALL_KEY = 'confindex_match_reindex_all';

    protected $_matchedEntities = array(
        Mage_CatalogInventory_Model_Stock_Item::ENTITY => array(
            Mage_Index_Model_Event::TYPE_SAVE
        ),
        Mage_Core_Model_Config_Data::ENTITY => array(
            Mage_Index_Model_Event::TYPE_SAVE
        ),
    );

    public function getName()
    {
        return Mage::helper('confindex')->__('Mygento Configurable Index');
    }

    public function getDescription()
    {
        return Mage::helper('confindex')->__('Configurable stock status based on child products');
    }

    protected $_relatedConfigSettings = array(
        Mygento_ConfIndex_Helper_Data::XML_PATH_ATTRIBUTE,
    );

    public function matchEvent(Mage_Index_Model_Event $event)
    {
        $data = $event->getNewData();
        if (isset($data[self::EVENT_MATCH_RESULT_KEY])) {
            return $data[self::EVENT_MATCH_RESULT_KEY];
        }

        $entity = $event->getEntity();

        if ($entity == Mage_Core_Model_Config_Data::ENTITY) {
            $configData = $event->getDataObject();
            if ($configData && in_array($configData->getPath(), $this->_relatedConfigSettings)) {
                $result = $configData->isValueChanged();
            } else {
                $result = false;
            }
        } else {
            $result = parent::matchEvent($event);
        }

        $event->addNewData(self::EVENT_MATCH_RESULT_KEY, $result);

        return $result;
    }

    protected function _registerEvent(Mage_Index_Model_Event $event)
    {
        $event->addNewData(self::EVENT_MATCH_RESULT_KEY, true);
        $entity = $event->getEntity();
        switch ($entity) {
            case Mage_CatalogInventory_Model_Stock_Item::ENTITY:
                $this->_registerCatalogInventoryStockItemEvent($event);
                break;

            case Mage_Core_Model_Config_Data::ENTITY:
                $process = $event->getProcess();
                $process->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
                break;
        }
        return $this;
    }

    /**
     * Register data required by stock item save process in event object
     *
     * @param Mage_Index_Model_Event $event
     */
    protected function _registerCatalogInventoryStockItemEvent(Mage_Index_Model_Event $event)
    {
        /* @var $object Mage_CatalogInventory_Model_Stock_Item */
        $object = $event->getDataObject();
        if ($object->dataHasChangedFor('is_in_stock')) {
            $event->addNewData('rewrite_product_ids', array($object->getProductId()));
        }
    }

    protected function _processEvent(Mage_Index_Model_Event $event)
    {
        $data = $event->getNewData();
        if (!empty($data[self::EVENT_REINDEX_ALL_KEY])) {
            $this->reindexAll();
        }
        //print_r($data);
        if (isset($data['rewrite_product_ids'])) {
            //print_r($data['rewrite_product_ids']);
            $this->reindexAll();
        }
    }

    public function reindexAll()
    {
        $attr = Mage::getStoreConfig('confindex/general/attribute');

        if (!($attr) || 0 === $attr || !(is_string($attr))) {
            return;
        }

        $write = Mage::getSingleton('core/resource')->getConnection('core_write');

        $attribute = Mage::getModel('catalog/product')->getResource()->getAttribute($attr);

        $attr_id = $attribute->getAttributeId();

        $attr_entity = $attribute->getEntityTypeId();
        $attr_table = $attribute->getBackendTable();

        $select = $write->select()
            ->from(array('s' => Mage::getSingleton('core/resource')->getTableName('cataloginventory/stock_status')), array(
                new Zend_Db_Expr($attr_entity . ' as `entity_type_id`'),
                new Zend_Db_Expr($attr_id . ' as `attribute_id`'),
                new Zend_Db_Expr(Mage::app()->getStore()->getStoreId() . ' as `store_id`'),
                new Zend_Db_Expr('cast(sum(`s`.`qty`) AS UNSIGNED) as `value`'),
                )
            )
            ->join(array('r' => Mage::getSingleton('core/resource')->getTableName('catalog/product_relation')), 's.product_id = r.child_id', array('entity_id' => 'parent_id'))
            ->group('r.parent_id');

        //echo $select->__toString()."\n";
        //die();

        $query = $write->insertFromSelect($select, $attr_table, array('entity_type_id', 'attribute_id', 'store_id', 'value', 'entity_id'), Varien_Db_Adapter_Interface::INSERT_ON_DUPLICATE);
        $result = $write->query($query);




        $attr2 = Mage::getStoreConfig('confindex/general/attribute2');

        if (!($attr2) || 0 === $attr2 || !(is_string($attr2))) {
            return;
        }


        $attribute2 = Mage::getModel('catalog/product')->getResource()->getAttribute($attr2);


        $attr2_id = $attribute2->getAttributeId();

        $attr2_entity = $attribute2->getEntityTypeId();
        $attr2_table = $attribute2->getBackendTable();

        $select2 = $write->select()
            ->from(array('l' => Mage::getSingleton('core/resource')->getTableName('catalog/product_link')), array(
                new Zend_Db_Expr($attr2_entity . ' as `entity_type_id`'),
                new Zend_Db_Expr($attr2_id . ' as `attribute_id`'),
                new Zend_Db_Expr(Mage::app()->getStore()->getStoreId() . ' as `store_id`'),
                'entity_id' => 'l.product_id',
                new Zend_Db_Expr('cast(sum(`v`.`value`) AS UNSIGNED) as `value`'),
                )
            )
            ->join(array('v' => $attr_table), 'l.linked_product_id = v.entity_id', array())
            ->where('`l`.`link_type_id` = 4')
            ->where('`v`.`attribute_id` = ' . $attr_id)
            ->group('l.product_id')
        ;
        //echo $select2->__toString()."\n";
        //die();
        $query2 = $write->insertFromSelect($select2, $attr2_table, array('entity_type_id', 'attribute_id', 'store_id', 'entity_id', 'value'), Varien_Db_Adapter_Interface::INSERT_ON_DUPLICATE);
        $result2 = $write->query($query2);





        $attr3 = Mage::getStoreConfig('confindex/general/attribute3');

        if (!($attr3) || 0 === $attr3 || !(is_string($attr3))) {
            return;
        }

        $attribute3 = Mage::getModel('catalog/product')->getResource()->getAttribute($attr3);

        $attr3_id = $attribute3->getAttributeId();

        $attr3_entity = $attribute3->getEntityTypeId();
        $attr3_table = $attribute3->getBackendTable();

        $attr4 = Mage::getStoreConfig('confindex/general/attribute4');

        if (!($attr4) || 0 === $attr4 || !(is_string($attr4))) {
            return;
        }

        $attribute4 = Mage::getModel('catalog/product')->getResource()->getAttribute($attr4);

        $attr4_id = $attribute4->getAttributeId();

        $attr4_entity = $attribute4->getEntityTypeId();
        $attr4_table = $attribute4->getBackendTable();


        $select3 = $write->select()
            ->from(array('p' => Mage::getSingleton('core/resource')->getTableName('catalog/product')), array(
                new Zend_Db_Expr($attr3_entity . ' as `entity_type_id`'),
                new Zend_Db_Expr($attr3_id . ' as `attribute_id`'),
                new Zend_Db_Expr(Mage::app()->getStore()->getStoreId() . ' as `store_id`'),
                'entity_id',
                new Zend_Db_Expr('IF ((`at1`.`value` + `at2`.`value`) > 0, `at4`.`value` + 50, `at4`.`value` ) as `value`'),
            ))
            ->join(array('at1' => $attr_table), 'p.entity_id = at1.entity_id', array())
            ->join(array('at2' => $attr2_table), 'p.entity_id = at2.entity_id', array())
            ->join(array('at4' => $attr4_table), 'p.entity_id = at4.entity_id', array())
            ->where('`at1`.`attribute_id` = ' . $attr_id)
            ->where('`at2`.`attribute_id` = ' . $attr2_id)
            ->where('`at4`.`attribute_id` = ' . $attr4_id)
        ;
        //echo $select3->__toString() . "\n";
        //die();
        $query3 = $write->insertFromSelect($select3, $attr3_table, array('entity_type_id', 'attribute_id', 'store_id', 'entity_id', 'value'), Varien_Db_Adapter_Interface::INSERT_ON_DUPLICATE);
        $result3 = $write->query($query3);
    }
}
