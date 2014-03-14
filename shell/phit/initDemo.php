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
     * Number of websites to create
     * @var int $_nbWebsites
     */
    protected $_nbWebsites = 2;

    /**
     * Number of subcategories to create in the root category
     * @var int $_nbCategories
     */
    protected $_nbCategories = 10;

    /**
     * Number of products to create
     * @var int $_nbProducts
     */
    protected $_nbProducts = 100;

    /**
     * CSV file content
     * @var resource $_csvFile
     */
    protected $_csvFile;

    /**
     * Websites id (for website name)
     * @var array $_websiteIds
     */
    protected $_websiteIds;

    /**
     * Product line template with all product information
     * @var string $_productLineFull
     */
    protected $_productLineFull;

    /**
     * Product line template with partial information
     * @var string $_productLinePart
     */
    protected $_productLinePart;

    /**
     * Products CSV filename
     * @var string $_csvFilename
     */
    protected $_csvFilename = 'demo-products.csv';

    /**
     * Class constructor to initialize the data
     */
    public function __construct()
    {
        parent::__construct();

        $this->_initProductLines();
    }

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
                'Wrong value provided for option --nbWebsites :' . $nbWebsites
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

            $this->_websiteIds = range(1, $nbWebsites);
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
        for ($i = 1; $i <= $this->_nbCategories; $i++) {
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
        $setup = new Mage_Core_Model_Resource_setup('init-demo');
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
     * Initialize product lines templates
     *
     * @return void
     */
    protected function _initProductLines()
    {
        $this->_productLineFull = "##SKU##,,Default,simple,Category ##CATID##,Demo Root Category,web##WEBSITEID##,,,,"
                                ."2014-03-13 14:57:39,,,,,description du produit ##PRODUCTID##,,,,,0,no_selection,,"
                                . "Utiliser config,,,,,,,,Utiliser config,Utiliser config,Produit ##PRODUCTID##,,,"
                                . "Bloc après Colonne Info,,##PRICE##,,,0,description courte du produit ##PRODUCTID##,"
                                . "no_selection,,,,,1,2,no_selection,,2014-03-13 16:41:12,,,##URLKEY##,,4,10.0000,"
                                . "1000,0.0000,1,0,0,1,1.0000,1,0.0000,1,1,,1,0,1,0,1,0.0000,1,0,0,,,,,,,,,,,"
                                . ",,,,,,,,,,";
        $this->_productLinePart = ",,,,,,web##WEBSITEID##,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,##PRICE##,,,,,,,,,,,,,,,,,,,,,"
                                . ",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,";
    }

    /**
     * Initialize product information
     *
     * @param integer $productId product id
     *
     * @return array
     */
    protected function _initProductInfo($productId)
    {
        $price       = rand(10, 200);
        $categoryId  = rand(1, $this->_nbCategories);
        $productInfo = array(
            '/##SKU##/'       => 'product-' . $productId,
            '/##WEBSITEID##/' => '',
            '/##CATID##/'     => $categoryId,
            '/##PRODUCTID##/' => $productId,
            '/##PRICE##/'     => $price,
            '/##URLKEY##/'    => 'product' . $productId
        );

        return $productInfo;
    }

    /**
     * Initialize CSV file
     *
     * @return void
     */
    protected function _initCsvFile()
    {
        $fileHeader     = "sku,_store,_attribute_set,_type,_category,_root_category,_product_websites,color,cost,"
                          . "country_of_manufacture,created_at,custom_design,custom_design_from,custom_design_to,"
                          . "custom_layout_update,description,gallery,gift_message_available,gift_wrapping_available,"
                          . "gift_wrapping_price,has_options,image,image_label,is_returnable,manufacturer,"
                          . "media_gallery,meta_description,meta_keyword,meta_title,minimal_price,msrp,"
                          . "msrp_display_actual_price_type,msrp_enabled,name,news_from_date,news_to_date,"
                          . "options_container,page_layout,price,related_tgtr_position_behavior,"
                          . "related_tgtr_position_limit,required_options,short_description,small_image,"
                          . "small_image_label,special_from_date,special_price,special_to_date,status,tax_class_id,"
                          . "thumbnail,thumbnail_label,updated_at,upsell_tgtr_position_behavior,"
                          . "upsell_tgtr_position_limit,url_key,url_path,visibility,weight,qty,min_qty,"
                          . "use_config_min_qty,is_qty_decimal,backorders,use_config_backorders,min_sale_qty,"
                          . "use_config_min_sale_qty,max_sale_qty,use_config_max_sale_qty,is_in_stock,"
                          . "notify_stock_qty,use_config_notify_stock_qty,manage_stock,use_config_manage_stock,"
                          . "stock_status_changed_auto,use_config_qty_increments,qty_increments,"
                          . "use_config_enable_qty_inc,enable_qty_increments,is_decimal_divided,_links_related_sku,"
                          . "_links_related_position,_links_crosssell_sku,_links_crosssell_position,_links_upsell_sku"
                          . ",_links_upsell_position,_associated_sku,_associated_default_qty,_associated_position,"
                          . "_tier_price_website,_tier_price_customer_group,_tier_price_qty,_tier_price_price,"
                          . "_group_price_website,_group_price_customer_group,_group_price_price,_media_attribute_id,"
                          . "_media_image,_media_lable,_media_position,_media_is_disabled";

        $this->_csvFile = fopen(Mage::getBaseDir('var') . DS . 'import' . DS . $this->_csvFilename, 'w');

        fwrite($this->_csvFile, $fileHeader . "\n");
    }

    /**
     * Complete the CSV file with product data
     *
     * @return void
     */
    protected function _fillCsvFile()
    {
        $this->_outputMsg(
            'Generate CSV for ' . $this->_nbProducts . ' products ...',
            self::MSG_INFO
        );

        for ($i = 1; $i <= $this->_nbProducts; $i++) {
            $productInfo     = $this->_initProductInfo($i);
            $nbWebsites      = rand(1, $this->_nbWebsites);
            $productWebsites = array_rand($this->_websiteIds, $nbWebsites);

            foreach ($productWebsites as $key => $websiteKey) {
                $productInfo['/##WEBSITEID##/'] = $this->_websiteIds[$websiteKey];

                if ($key == 0) {
                    $prodLine = preg_replace(array_keys($productInfo), $productInfo, $this->_productLineFull);
                } else {
                    $productInfo['/##PRICE##/'] += round($productInfo['/##PRICE##/'] * (rand(-5, 5) / 100), 2);
                    $prodLine = preg_replace(array_keys($productInfo), $productInfo, $this->_productLinePart);
                }
                fwrite($this->_csvFile, $prodLine . "\n");
            }
        }
        fclose($this->_csvFile);
    }

    /**
     * Check the command parameters
     *
     * @return array
     */
    protected function _checkParams()
    {
        $errors = array();
        if ($this->getArg('nbWebsites')) {
            if ((int)$this->getArg('nbWebsites') < 1) {
                $errors[] = 'Please provide a number of websites to be created : ' . $this->getArg('nbWebsites');
            } else {
                $this->_nbWebsites = (int)$this->getArg('nbWebsites');
            }
        }
        if ($this->getArg('nbCategories')) {
            if ((int)$this->getArg('nbCategories') < 1) {
                $errors[] = 'Please provide a number of categories available : ' . $this->getArg('nbCategories');
            } else {
                $this->_nbCategories = (int)$this->getArg('nbCategories');
            }
        }

        if ($this->getArg('nbProducts')) {
            if ((int)$this->getArg('nbProducts') < 1) {
                $errors[] = 'Please provide a number of products to be created : ' . $this->getArg('nbProducts');
            } else {
                $this->_nbProducts = (int)$this->getArg('nbProducts');
            }
        }

        if ($this->getArg('csvFilename')) {
            if ($this->getArg('csvFilename') == '') {
                $errors[] = 'Please provide a valid name for the CSV file : ' . $this->getArg('csvFilename');
            } else {
                $this->_csvFilename = $this->getArg('csvFilename');
            }
        }

        return $errors;
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
            $errors = $this->_checkParams();

            if (count($errors)) {
                throw Mage::exception(
                    'Mage_Core',
                    implode("\n           ", $errors)
                );

            } else {
                $this->_outputMsg(
                    'Demo Initialization with ' . $this->_nbWebsites . ' stores ...',
                    self::MSG_INFO
                );
                $websites = $this->_createConfig($this->_nbWebsites);
                $this->_config = array(
                    'default' => array(
                        array(
                            'web/url/use_store'             => 1,
                            'catalog/price/scope'           => 1
                        )
                    ),
                    'websites' => array(),
                    'stores' => array()
                );
                $this->_createWebsites($websites);
                $this->_massConfigDataUpdate($this->_config);
                $this->_initCsvFile();
                $this->_fillCsvFile();
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

  --nbWebsites <integer>   Number of websites (and store) to create (default $this->_nbWebsites)
  --nbCategories <integer> Number of subcategories to create in the root category (default $this->_nbCategories)
  --nbProducts <integer>   Number of products to create (default $this->_nbProducts)
  --csvFilename <string>   Product csv filename (default $this->_csvFilename)
  --quiet                  Do not output messages other than warnings and errors
  --color                  Output the messages in color
  help                     This help

USAGE;
    }
}

$shell = new Phit_Shell_InitDemo();
$shell->run();
