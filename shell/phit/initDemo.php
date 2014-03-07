<?php

require_once dirname(__FILE__) . '/abstract.php';

/**
 * Magento Demo Initialization Shell Script
 *
 * @category  Phit
 * @package   Phit_Demo
 * @author    Guillaume Maïssa <guillaume@maissa.fr>
 * @copyright 2014 Phit
 */
class Phit_Shell_InitDemo extends Phit_Shell_Abstract
{
    /**
     * Magento configuration
     * @var array $_config
     */
    protected $_config = array();

    /**
     * Generate the data for websites creation
     *
     * @param integer $nbWebsites number of websites to create
     *
     * @return array
     * @throws Mage_Core_Exception
     */
    protected function _createConfig($nbWebsites)
    {
        if (!is_int($nbWebsites) || $nbWebsites < 1) {
            throw Mage::exception(
                'Mage_Core',
                'Wrong value provided for option --nbWebsites :' . $this->getArg('nbWebsites')
            );
        } else {
            $this->_outputMsg('Generating configuration for websites / stores creation ...', self::MSG_INFO);
            for ($i = 1; $i <= $nbWebsites; $i++) {
                $config[] = array(
                    'data'         => array(
                        'name' => 'Website ' . $i,
                        'code' => 'web'.$i,
                    ),
                    'store_groups' => array(
                        array(
                            'data'         => array(
                                'name' => 'Web' . $i . ' Group ' . $i
                            ),
                            'stores'       => array(
                                array(
                                    'data'   => array(
                                        'name' => 'Web ' . $i . ' Group ' . $i . ' Store ' . $i,
                                        'code' => 'web' . $i . '_store' . $i,
                                    ),
                                    'config' => array()
                                )
                            ),
                            'rootCategory' => 'Demo Root Category'
                        )
                    )
                );
            }
            $this->_outputMsg('Done', self::MSG_SUCCESS);

            return $config;
        }
    }

    /**
     * Store the websites / store groups / stores in DB
     *
     * @param array $websites list of websites
     *
     * @return void
     */
    protected function _createWebsites($websites)
    {
        $this->_outputMsg('Creating websites / stores ...', self::MSG_INFO);
        foreach ($websites as $websiteKey => $website) {
            $websiteModel = Mage::getModel('core/website');
            $websiteModel->setData($website['data']);
            if (!array_key_exists('website_id', $website['data']) || $website['data']['website_id'] == '') {
                $websiteModel->setId(null);
            }
            $websiteModel->setSortOrder($websiteKey);
            $websiteModel->save();
            $websiteId = $websiteModel->getId();

            if (array_key_exists('config', $website) && count($website['config'])) {
                $this->_config['websites'][$websiteId] = $website['config'];
            }

            $this->_createStoreGroups($website['store_groups'], $websiteModel);
        }
        $this->_outputMsg('Done', self::MSG_SUCCESS);
    }

    /**
     * Store the store groups for a given website
     *
     * @param array                   $storeGroups list of store groups
     * @param Mage_Core_Model_Website $website     website object
     *
     * @return void
     */
    protected function _createStoreGroups($storeGroups, $website)
    {
        $defaultGroupId = null;

        foreach ($storeGroups as $storeGroup) {
            $rootCategoryId  = $this->_getRootCategoryId($storeGroup['rootCategory']);
            $storeGroupModel = Mage::getModel('core/store_group');
            $storeGroup['data']['website_id']       = $website->getId();
            $storeGroup['data']['root_category_id'] = $rootCategoryId;
            $storeGroupModel->setData($storeGroup['data']);
            if (!array_key_exists('group_id', $storeGroup['data']) || $storeGroup['data']['group_id'] == '') {
                $storeGroupModel->setId(null);
            }
            $storeGroupModel->save();
            $storeGroupId = $storeGroupModel->getId();

            if (is_null($defaultGroupId)) {
                $defaultGroupId = $storeGroupId;
            }

            $this->_createStores($storeGroup['stores'], $storeGroupModel);
        }

        $website->setDefaultGroupId($defaultGroupId);
        $website->save();
    }

    /**
     * Store the stores for a given store group
     *
     * @param array                       $stores     list of stores
     * @param Mage_Core_Model_Store_Group $storeGroup store group object
     *
     * @return void
     */
    protected function _createStores($stores, $storeGroup)
    {
        $defaultStoreId = null;

        foreach ($stores as $storeKey => $store) {
            $storeModel = Mage::getModel('core/store');
            $store['data']['website_id'] = $storeGroup->getWebsiteId();
            $store['data']['group_id']   = $storeGroup->getId();
            $store['data']['sort_order'] = $storeKey;
            $store['data']['is_active']  = 1;
            $storeModel->setData($store['data']);
            if (!array_key_exists('store_id', $store['data']) || $store['data']['store_id'] == '') {
                $storeModel->setId(null);
            }
            $storeModel->save();
            $storeModelId = $storeModel->getId();

            if (is_null($defaultStoreId)) {
                $defaultStoreId = $storeModelId;
            }

            if (array_key_exists('config', $store) && count($store['config'])) {
                $this->_config['stores'][$storeModelId] = $store['config'];
            }
        }

        $storeGroup->setDefaultStoreId($defaultStoreId);
        $storeGroup->save();
    }

    /**
     * Create children categories under a root category
     *
     * @param integer $parentCategoryId root category id under which the
     *                                  children categories should be created
     *
     * @return void
     */
    protected function _createChildrenCategories($parentCategoryId)
    {
        $categoriesToSetup = array();
        for ($i = 1; $i < 10; $i++) {
            $categoriesToSetup[] = array(
                'name'            => 'Category ' . $i,
                'include_in_menu' => true,
                'is_active'       => true
            );
        }

        foreach ($categoriesToSetup as $categoryData) {
            $category = Mage::getModel('catalog/category');

            $category->addData($categoryData);
            $category->setAttributeSetId($category->getDefaultAttributeSetId());
            $category->save();
            $category->move($parentCategoryId, null);
        }
    }

    /**
     * Retrieve a category id from its name and create it if it does not exist
     *
     * @param string $categoryName category name to retrieve
     *
     * @return string
     */
    protected function _getRootCategoryId($categoryName)
    {
        $category = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToFilter('name', $categoryName)
            ->setPage(1, 1)
            ->getFirstItem();

        if (!$category->getId()) {
            /* @var $category Mage_Catalog_Model_Category */
            $category = Mage::getModel('catalog/category');
            $category->setName($categoryName)
                ->setDisplayMode('PAGE')
                ->setAttributeSetId($category->getDefaultAttributeSetId())
                ->setIsActive(1)
                ->setPath('1')
                ->setInitialSetupFlag(true)
                ->save();

            $this->_createChildrenCategories($category->getId());
        }
        $categoryId = $category->getId();

        return $categoryId;
    }

    /**
     * Mass configuration data update
     *
     * @param array $config configuration array to be applied
     *
     * @return void
     */
    protected function _massConfigDataUpdate(array $config)
    {
        $this->_outputMsg('Saving configuration ...', self::MSG_INFO);
        $setup = Mage::getResourceModel('core/setup');
        foreach ($config as $scope => $items) {
            foreach ($items as $scopeId => $item) {
                if ($scopeId !== false) {
                    foreach ($item as $path => $value) {
                        $setup->setConfigData($path, $value, $scope, $scopeId);
                    }
                }
            }
        }
        $this->_outputMsg('Done', self::MSG_SUCCESS);
    }

    /**
     * Run script
     *
     * @return void
     * @throws Mage_Core_Exception
     */
    public function run()
    {
        try {
            if (!$this->getArg('nbWebsites') || !is_integer((int)$this->getArg('nbWebsites'))) {
                throw Mage::exception(
                    'Mage_Core',
                    'Please provide a number of websites to be created : ' . $this->getArg('nbWebsites')
                );
            } else {
                $this->_outputMsg(
                    'Demo Initialization with ' . $this->getArg('nbWebsites') . ' stores ...',
                    self::MSG_INFO
                );
                $websites = $this->_createConfig((int)$this->getArg('nbWebsites'));
                $this->_config = array(
                    'default' => array(
                        'web/url/use_store'             => 1,
                        'general/region/state_required' => 'DE,AT,CA,ES,EE,US,FI,LV,LT,RO,CH',
                        'catalog/price/scope'           => 1
                    ),
                    'websites' => array(),
                    'stores' => array()
                );
                $this->_createWebsites($websites);
                $this->_massConfigDataUpdate($this->_config);
            }
        } catch (Mage_Core_Exception $exception) {
            $this->_outputMsg($exception->getMessage(), self::MSG_WARNING);
        } catch (Exception $exception) {
            Mage::logException($exception);
            $this->_outputMsg($exception->getMessage(), self::MSG_ERROR);
        }
    }

    /**
     * Retrieve Usage Help Message
     *
     * @return string
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f phit/initDemo.php -- [options]

  --nbWebsites <nb_websites> Initialize N websites
  --quiet                    Do not output messages other than warnings and errors
  --color                    Output the messages in color
  help                       This help

USAGE;
    }
}

$shell = new Phit_Shell_InitDemo();
$shell->run();
