<?php

/**
 * @category  SchumacherFM
 * @package   SchumacherFM_FastIndexer
 * @copyright Copyright (c) http://www.schumacher.fm
 * @license   see LICENSE.md file
 * @author    Cyrill at Schumacher dot fm @SchumacherFM
 */
class SchumacherFM_FastIndexer_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * path in the app/etc/local.xml to the current database name
     */
    const CONFIG_DB_NAME = 'global/resources/default_setup/connection/dbname';

    /**
     * @var boolean
     */
    protected $_isPdoFastIndexerInstance = null;

    /**
     * @var boolean
     */
    protected $_isEnabled = null;

    /**
     * @var string
     */
    protected $_shadowDbName = null;

    /**
     * @var string
     */
    protected $_currentDbName = null;

    /**
     * @var Mage_Core_Model_Config
     */
    protected $_config = null;

    /**
     * @var array
     */
    protected $_store = array();

    /**
     * @param Mage_Core_Model_Config $config
     * @param Mage_Core_Model_Store  $store
     */
    public function __construct(Mage_Core_Model_Config $config = null, Mage_Core_Model_Store $store = null)
    {
        $this->_config    = $config;
        $this->_store[-1] = $store;
    }

    /**
     * @return Mage_Core_Model_Config
     */
    public function getConfig()
    {
        if (null === $this->_config) {
            $this->_config = Mage::getConfig();
        }
        return $this->_config;
    }

    /**
     * due to phpunit ... because when running phpunit the event resource_get_tablename
     * is fired before the stores/websites has been initialized so then Mage::app()->getStore() will fail
     * due to not finding any stores.
     * My model mock_store is a temp work around until a the phpunit event phpunit_suite_start_after has been
     * fired to notify this helper that the stores has been loaded
     *
     * @param int $id
     *
     * @return Mage_Core_Model_Store
     */
    public function getStore($id = null)
    {
        $index = null === $id ? -1 : (int)$id;
        if (false === isset($this->_store[$index])) {
            try {
                $this->_store[$index] = Mage::app()->getStore($id);
            } catch (Mage_Core_Model_Store_Exception $e) {
                $this->_store[$index] = Mage::getModel('schumacherfm_fastindexer/mock_store');
            }
        }
        return $this->_store[$index];
    }

    /**
     * @param string $path The config path
     * @param int    $id
     *
     * @return bool
     */
    public function getStoreConfigFlag($path, $id = null)
    {
        return (int)$this->getStore($id)->getConfig($path) === 1;
    }

    /**
     * notifier
     * @fire phpunit_suite_start_after
     */
    public function reinitHelper()
    {
        $this->_store                    = array();
        $this->_isEnabled                = null;
        $this->_shadowDbName             = null;
        $this->_currentDbName            = null;
        $this->_isPdoFastIndexerInstance = null;
    }

    /**
     * @param int $index
     *
     * @return mixed
     */
    public function getShadowDbName($index = 1)
    {
        if (true === (boolean)Mage::app()->getUpdateMode()) {
            return '';
        }
        if (null === $this->_shadowDbName) {
            $this->_shadowDbName = $this->getStore()->getConfig('fastindexer/databases/dbName' . $index);
            if (empty($this->_shadowDbName)) {
                Mage::throwException(__METHOD__ . ': Shadow DB Name cannot be empty!');
            }
        }
        return $this->_shadowDbName;
    }

    /**
     * @return string
     */
    public function getDefaultSetupDbName()
    {
        if (null === $this->_currentDbName) {
            $this->_currentDbName = (string)$this->getConfig()->getNode(self::CONFIG_DB_NAME);
            if (empty($this->_currentDbName)) {
                Mage::throwException('Current DB Name cannot be empty!');
            }
        }
        return $this->_currentDbName;
    }

    /**
     * This method will be executed each time resource_get_tablename is called and that is pretty often.
     *
     * @return bool
     */
    public function isEnabled()
    {
        if (null === $this->_isEnabled) {
            $enabled          = $this->getStoreConfigFlag('fastindexer/general/is_active');
            $this->_isEnabled = $this->isPdoFastIndexerInstance() && true === $enabled;
        }
        return $this->_isEnabled;
    }

    /**
     * @return bool
     */
    public function isPdoFastIndexerInstance()
    {
        if (null === $this->_isPdoFastIndexerInstance) {
            $connection                      = Mage::getSingleton('core/resource')->getConnection(Mage_Core_Model_Resource::DEFAULT_SETUP_RESOURCE);
            $this->_isPdoFastIndexerInstance = $connection instanceof SchumacherFM_FastIndexer_Model_Db_Adapter_Pdo_Mysql;
        }
        return $this->_isPdoFastIndexerInstance;
    }

    /**
     * @return bool
     */
    public function dropOldTable()
    {
        return $this->getStoreConfigFlag('fastindexer/general/dropOldTable');
    }

    /**
     * @return bool
     */
    public function optimizeTables()
    {
        return $this->getStoreConfigFlag('fastindexer/general/optimizeTables');
    }

    /**
     * @return bool
     */
    public function enableUrlRewriteCopyCustom()
    {
        return $this->getStoreConfigFlag('fastindexer/url_indexer/urlRewriteCopyCustom');
    }

    /**
     * @return int
     */
    public function getLockThreshold()
    {
        return (int)Mage::getStoreConfig('fastindexer/indexer/lock_threshold');
    }

    public function isCronAutoIndexEnabled()
    {
        return $this->getStoreConfigFlag('fastindexer/indexer/enable_cron_index');
    }

    /**
     * @param int $store
     *
     * @return bool
     */
    public function excludeDisabledProducts($store = null)
    {
        return $this->isEnabled() && $this->getStoreConfigFlag('fastindexer/url_indexer/exclude_disabled_products', $store);
    }

    /**
     * @param int $store
     *
     * @return bool
     */
    public function excludeNotVisibleProducts($store = null)
    {
        return $this->isEnabled() && $this->getStoreConfigFlag('fastindexer/url_indexer/exclude_not_visible_products', $store);
    }

    /**
     * @param int $store
     *
     * @return bool
     */
    public function excludeDisabledCategories($store = null)
    {
        return $this->isEnabled() && $this->getStoreConfigFlag('fastindexer/url_indexer/exclude_disabled_categories', $store);
    }

    /**
     * @param int $store
     *
     * @return bool
     */
    public function excludeCategoryPathInProductUrl($store = null)
    {
        return $this->isEnabled() && $this->getStoreConfigFlag('fastindexer/url_indexer/exclude_category_path_in_product_url', $store);
    }

    /**
     * @return bool
     */
    public function disableAllCategoriesInUrlRewrite()
    {
        return $this->isEnabled() && $this->getStoreConfigFlag('fastindexer/url_indexer/disable_all_categories_in_url_rewrite');
    }

    /**
     * Category Flat Tables must be enabled
     *
     * @return bool
     */
    public function optimizeUrlRewriteFlatCategory()
    {
        return $this->isEnabled() &&
        $this->getStoreConfigFlag('fastindexer/url_indexer/optimize_url_rewrite_flat_category') &&
        $this->getStoreConfigFlag('catalog/frontend/flat_catalog_category');
    }

    /**
     * Category Flat Tables must be enabled and Magento <= 1.7
     *
     * @return bool
     */
    public function optimizeUrlRewriteFlatCategory17()
    {
        return
            version_compare(Mage::getVersion(), '1.7.9.9') < 0 &&
            $this->optimizeUrlRewriteFlatCategory();
    }

    /**
     * @param      $file
     * @param      $line
     * @param      $msg
     * @param bool $die
     */
    public static function csdebug($file, $line, $msg, $die = false)
    {
        echo '<div style="text-align:left; border: 1px solid red; margin:0.5em; padding:0.5em; background-color:lightgreen; color:black;">
        ' . $file . ':' . $line . '<br>' . PHP_EOL . $msg . '
        </div>';
    }
}