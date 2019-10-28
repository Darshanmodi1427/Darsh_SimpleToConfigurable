<?php
namespace Darsh\SimpleToConfigurable\Helper\Catalog;

class Product extends \Magento\Catalog\Helper\Product
{
    public function initProduct($productId, $controller, $params = null)
    {
        // Prepare data for routine
        if (!$params) {
            $params = new \Magento\Framework\DataObject();
        }

        // Init and load product
        $this->_eventManager->dispatch(
            'catalog_controller_product_init_before',
            ['controller_action' => $controller, 'params' => $params]
        );

        if (!$productId) {
            return false;
        }

        try {
            $product = $this->productRepository->getById($productId, false, $this->_storeManager->getStore()->getId());
        } catch (NoSuchEntityException $e) {
            return false;
        }
                
        if (!$params->getOverrideVisibility()) {
            if (!$this->canShow($product)) {
                return false;
            }
        } else {
            if (!$product->isVisibleInCatalog()) {
                return false;
            }
        }        

        if (!in_array($this->_storeManager->getStore()->getWebsiteId(), $product->getWebsiteIds())) {
            return false;
        }
        
        $categoryId = $params->getCategoryId();
        if (!$categoryId && $categoryId !== false) {
            $lastId = $this->_catalogSession->getLastVisitedCategoryId();
            if ($product->canBeShowInCategory($lastId)) {
                $categoryId = $lastId;
            }
        } elseif (!$product->canBeShowInCategory($categoryId)) {
            $categoryId = null;
        }

        if ($categoryId) {
            try {
                $category = $this->categoryRepository->get($categoryId);
            } catch (NoSuchEntityException $e) {
                $category = null;
            }
            if ($category) {
                $product->setCategory($category);
                $this->_coreRegistry->register('current_category', $category);
            }
        }
        
        if (!$this->_coreRegistry->registry('current_product')) {            
            $this->_coreRegistry->register('current_product', $product);
        }

        if (!$this->_coreRegistry->registry('product')) {            
            $this->_coreRegistry->register('product', $product);
        }       
        

        try {
            $this->_eventManager->dispatch(
                'catalog_controller_product_init_after',
                ['product' => $product, 'controller_action' => $controller]
            );
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->_logger->critical($e);
            return false;
        }

        return $product;
    }
}
