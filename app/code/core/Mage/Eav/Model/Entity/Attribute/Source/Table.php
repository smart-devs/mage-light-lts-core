<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Mage
 * @package     Mage_Eav
 * @copyright  Copyright (c) 2006-2016 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Mage_Eav_Model_Entity_Attribute_Source_Table extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * List of preloaded options per attribute
     *
     * @var array
     */
    protected static $_my_preloadedOptions = array();
    /**
     * List of stores where default values already preloaded
     *
     * @var array
     */
    protected static $_my_preloadedOptionsStores = array();
    /**
     * List of preloaded options for each option id
     *
     * @var array
     */
    protected static $_my_preloadedOptionHash = array();

    /**
     * Default values for option cache
     *
     * @var array
     */
    protected $_optionsDefault = array();

    /**
     * Retrieve store options from preloaded hashes
     *
     * @param  int    $storeId     Store ID
     * @param  int    $attributeId Attribute ID
     * @param  string $type        Type
     *
     * @return array
     */
    protected function _getPreloadedOptions($storeId, $attributeId, $type)
    {
        $this->_preloadOptions($storeId);
        $key = $this->_getCombinedKey($storeId, $attributeId, $type);
        if (true === isset(self::$_my_preloadedOptions[$key])) {
            return self::$_my_preloadedOptions[$key];
        }
        return array();
    }

    /**
     * Preloads values for option values on the first call
     *
     * @param int $storeId Store ID
     */
    protected function _preloadOptions($storeId)
    {
        if (true === isset(self::$_my_preloadedOptionsStores[$storeId])) {
            return;
        }
        self::$_my_preloadedOptionsStores[$storeId] = true;
        $collection = Mage::getResourceModel('eav/entity_attribute_option_collection')
            ->setPositionOrder('asc')
            ->setStoreFilter($storeId);
        // This one allows to limit selection of options, based on frontend criteria.
        // E.g. if not all the attribute options are needed for the current page
        Mage::dispatchEvent('eav_entity_attribute_source_table_preload_options', array(
            'collection' => $collection,
            'store_id' => $storeId
        ));
        $options = $collection->getData();
        foreach ($options as $option) {
            $optionKey = $this->_getCombinedKey($storeId, $option['option_id'], 'store');
            $storeKey = $this->_getCombinedKey($storeId, $option['attribute_id'], 'store');
            $defaultKey = $this->_getCombinedKey($storeId, $option['attribute_id'], 'default');
            self::$_my_preloadedOptionHash[$optionKey] = $option['value'];
            self::$_my_preloadedOptions[$storeKey][] = array(
                'value' => $option['option_id'],
                'label' => $option['value']
            );
            self::$_my_preloadedOptions[$defaultKey][] = array(
                'value' => $option['option_id'],
                'label' => $option['default_value']
            );
        }
    }

    /**
     * Returns option key for hash generation
     *
     * @param  int    $storeId  Store ID
     * @param  int    $optionId Option ID
     * @param  string $type     Type
     *
     * @return string
     */
    protected function _getCombinedKey($storeId, $optionId, $type)
    {
        return $storeId . '|' . $optionId . '|' . $type;
    }

    /**
     * Retrieve Full Option values array
     *
     * @param bool $withEmpty       Add empty option to array
     * @param bool $defaultValues
     * @return array
     */
    public function getAllOptions($withEmpty = true, $defaultValues = false)
    {
        $storeId = $this->getAttribute()->getStoreId();
        if (false === is_array($this->_options)) {
            $this->_options = array();
        }
        if (false === is_array($this->_optionsDefault)) {
            $this->_optionsDefault = array();
        }
        if (false === isset($this->_options[$storeId])) {
            $this->_options[$storeId] = self::_getPreloadedOptions(
                $storeId,
                $this->getAttribute()->getId(),
                'store'
            );
            $this->_optionsDefault[$storeId] = self::_getPreloadedOptions(
                $storeId,
                $this->getAttribute()->getId(),
                'default'
            );
        }
        $options = ($defaultValues ? $this->_optionsDefault[$storeId] : $this->_options[$storeId]);
        if (true === $withEmpty) {
            array_unshift($options, array('label' => '', 'value' => ''));
        }
        return $options;
    }

    /**
     * Get a text for option value
     *
     * @param string|integer $value
     * @return string
     */
    public function getOptionText($value)
    {
        $storeId = $this->getAttribute()->getStoreId();
        $this->_preloadOptions($storeId);
        $isMultiple = false;
        if (strpos($value, ',')) {
            $isMultiple = true;
            $value = explode(',', $value);
        }
        if (true === $isMultiple) {
            $values = array();
            foreach ($value as $item) {
                $key = $this->_getCombinedKey($storeId, $item, 'store');
                if (true === isset(self::$_my_preloadedOptionHash[$key])) {
                    $values[] = self::$_my_preloadedOptionHash[$key];
                }
            }
            return $values;
        }
        $key = $this->_getCombinedKey($storeId, $value, 'store');
        if (true === isset(self::$_my_preloadedOptionHash[$key])) {
            return self::$_my_preloadedOptionHash[$key];
        }
        return false;
    }

    /**
     * Add Value Sort To Collection Select
     *
     * @param Mage_Eav_Model_Entity_Collection_Abstract $collection
     * @param string $dir
     *
     * @return Mage_Eav_Model_Entity_Attribute_Source_Table
     */
    public function addValueSortToCollection($collection, $dir = Varien_Db_Select::SQL_ASC)
    {
        $valueTable1    = $this->getAttribute()->getAttributeCode() . '_t1';
        $valueTable2    = $this->getAttribute()->getAttributeCode() . '_t2';
        $collection->getSelect()
            ->joinLeft(
                array($valueTable1 => $this->getAttribute()->getBackend()->getTable()),
                "e.entity_id={$valueTable1}.entity_id"
                . " AND {$valueTable1}.attribute_id='{$this->getAttribute()->getId()}'"
                . " AND {$valueTable1}.store_id=0",
                array())
            ->joinLeft(
                array($valueTable2 => $this->getAttribute()->getBackend()->getTable()),
                "e.entity_id={$valueTable2}.entity_id"
                . " AND {$valueTable2}.attribute_id='{$this->getAttribute()->getId()}'"
                . " AND {$valueTable2}.store_id='{$collection->getStoreId()}'",
                array()
            );
        $valueExpr = $collection->getSelect()->getAdapter()
            ->getCheckSql("{$valueTable2}.value_id > 0", "{$valueTable2}.value", "{$valueTable1}.value");

        Mage::getResourceModel('eav/entity_attribute_option')
            ->addOptionValueToCollection($collection, $this->getAttribute(), $valueExpr);

        $collection->getSelect()
            ->order("{$this->getAttribute()->getAttributeCode()} {$dir}");

        return $this;
    }

    /**
     * Retrieve Column(s) for Flat
     *
     * @return array
     */
    public function getFlatColums()
    {
        $columns = array();
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $isMulti = $this->getAttribute()->getFrontend()->getInputType() == 'multiselect';

        if (Mage::helper('core')->useDbCompatibleMode()) {
            $columns[$attributeCode] = array(
                'type'      => $isMulti ? 'varchar(255)' : 'int',
                'unsigned'  => false,
                'is_null'   => true,
                'default'   => null,
                'extra'     => null
            );
            if (!$isMulti) {
                $columns[$attributeCode . '_value'] = array(
                    'type'      => 'varchar(255)',
                    'unsigned'  => false,
                    'is_null'   => true,
                    'default'   => null,
                    'extra'     => null
                );
            }
        } else {
            $type = ($isMulti) ? Varien_Db_Ddl_Table::TYPE_TEXT : Varien_Db_Ddl_Table::TYPE_INTEGER;
            $columns[$attributeCode] = array(
                'type'      => $type,
                'length'    => $isMulti ? '255' : null,
                'unsigned'  => false,
                'nullable'   => true,
                'default'   => null,
                'extra'     => null,
                'comment'   => $attributeCode . ' column'
            );
            if (!$isMulti) {
                $columns[$attributeCode . '_value'] = array(
                    'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
                    'length'    => 255,
                    'unsigned'  => false,
                    'nullable'  => true,
                    'default'   => null,
                    'extra'     => null,
                    'comment'   => $attributeCode . ' column'
                );
            }
        }

        return $columns;
    }

    /**
     * Retrieve Indexes for Flat
     *
     * @return array
     */
    public function getFlatIndexes()
    {
        $indexes = array();

        $index = sprintf('IDX_%s', strtoupper($this->getAttribute()->getAttributeCode()));
        $indexes[$index] = array(
            'type'      => 'index',
            'fields'    => array($this->getAttribute()->getAttributeCode())
        );

        $sortable   = $this->getAttribute()->getUsedForSortBy();
        if ($sortable && $this->getAttribute()->getFrontend()->getInputType() != 'multiselect') {
            $index = sprintf('IDX_%s_VALUE', strtoupper($this->getAttribute()->getAttributeCode()));

            $indexes[$index] = array(
                'type'      => 'index',
                'fields'    => array($this->getAttribute()->getAttributeCode() . '_value')
            );
        }

        return $indexes;
    }

    /**
     * Retrieve Select For Flat Attribute update
     *
     * @param int $store
     * @return Varien_Db_Select|null
     */
    public function getFlatUpdateSelect($store)
    {
        return Mage::getResourceModel('eav/entity_attribute_option')
            ->getFlatUpdateSelect($this->getAttribute(), $store);
    }
}
