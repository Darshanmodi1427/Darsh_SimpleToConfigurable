<?php

namespace Darsh\SimpleToConfigurable\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SimpleToConfigurable implements ObserverInterface
{

    protected $_redirect;
    protected $_productTypeConfigurable;
    protected $_productRepository;
    protected $_storeManager;
    protected $objectFactory;
    protected $viewHelper;
    protected $jsonHelper;

    public function __construct(
        \Magento\Framework\App\Response\Http $redirect,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $productTypeConfigurable,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\DataObjectFactory $objectFactory,
        \Magento\Catalog\Helper\Product\View $viewHelper,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper        
    )
    {
        $this->_redirect = $redirect;
        $this->_productTypeConfigurable = $productTypeConfigurable;
        $this->_productRepository = $productRepository;
        $this->_storeManager = $storeManager;
        $this->objectFactory = $objectFactory;
        $this->viewHelper = $viewHelper;
        $this->resultPageFactory = $resultPageFactory;       
        $this->jsonHelper = $jsonHelper; 
    }

    public function execute(Observer $observer)
    {        
        $controller = $observer->getControllerAction();        
        $request = $observer->getEvent()->getRequest();
        $productId = $request->getParam('id');
        
        if (!$productId) {
            return;
        }

        $simpleProduct = $this->_productRepository->getById($productId, false, $this->_storeManager->getStore()->getId());

        if (!$simpleProduct || $simpleProduct->getTypeId() != \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE) {
            return;
        }

        $configProductId = $this->_productTypeConfigurable->getParentIdsByChild($productId);
        if (isset($configProductId[0])) {
            $configProduct = $this->_productRepository->getById($configProductId[0], false, $this->_storeManager->getStore()->getId());            

            $params = $this->objectFactory->create();
            $params->setCategoryId(false);
            $params->setConfigureMode(true);            

            $buyRequest = $this->objectFactory->create();
            $buyRequest->setSuperAttribute($this->generateConfigData($configProduct, $simpleProduct));
            $buyRequest->setSuperAttribute($this->generateConfigData($configProduct, $simpleProduct));
            $buyRequest->setQty(1);
            $params->setBuyRequest($buyRequest);
            $selectedOptions = $this->jsonHelper->jsonEncode($this->getSelectedOptions($configProduct, $simpleProduct));
            $observer->getRequest()->setParam('swatchOptions',$selectedOptions);            
            $observer->getRequest()->setParam('id',$productId);            
            
            $params->setProduct($configProductId[0]);
            $params->setSelectedConfigurableOption(true);
            $params->setHasConfigureMode(true);
            $params->hasPreconfiguredValues(true);
            $params->setOverrideVisibility(true);
            $params->setConfigureMode(true);

            $controller->getRequest()->setDispatched(true);

            try{
                $page = $this->resultPageFactory->create();
                $this->viewHelper->prepareAndRender($page, $configProductId[0], $controller, $params);                
            }catch(\Exception $e){
                echo $e->getMessage();exit;
            }           
        }
    }

    protected function generateConfigData(\Magento\Catalog\Model\Product $parentProduct,\Magento\Catalog\Model\Product $currentProduct)
    {        
        $typeInstance = $parentProduct->getTypeInstance();
        
        $configData = array();
        $attributes = $typeInstance->getUsedProductAttributes($parentProduct);

        foreach ($attributes as $code => $data) {
            $configData[$code] = $currentProduct->getData($data->getAttributeCode());
        }

        return $configData;
    }

    protected function getSelectedOptions(\Magento\Catalog\Model\Product $parentProduct,\Magento\Catalog\Model\Product $currentProduct)
    {
        /* @var $typeInstance Mage_Catalog_Model_Product_Type_Configurable */
        $typeInstance = $parentProduct->getTypeInstance();
        
        $configData = array();
        $attributes = $typeInstance->getUsedProductAttributes($parentProduct);

        foreach ($attributes as $code => $data) {
            $configData[$data->getAttributeCode()] = $currentProduct->getData($data->getAttributeCode());
        }

        return $configData;
    }
}