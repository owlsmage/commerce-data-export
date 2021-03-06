<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CatalogDataExporter\Model\Provider\Product\ProductOptions;

use Magento\CatalogDataExporter\Model\Provider\Product\Downloadable\SampleUrlProvider;
use Magento\CatalogDataExporter\Model\Query\ProductDownloadableLinksQuery;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\StoreRepositoryInterface;

/**
 * Product downloadable links data provider
 */
class DownloadableLinks
{
    /**
     * @var array
     */
    private $linkOptions = [];

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var ProductDownloadableLinksQuery
     */
    private $productDownloadableLinksQuery;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var DownloadableLinksOptionUid
     */
    private $downloadableLinksOptionUid;

    /**
     * @var SampleUrlProvider
     */
    private $sampleUrlProvider;

    /**
     * @param ResourceConnection $resourceConnection
     * @param ProductDownloadableLinksQuery $productDownloadableLinksQuery
     * @param StoreRepositoryInterface $storeRepository
     * @param DownloadableLinksOptionUid $downloadableLinksOptionUid
     * @param SampleUrlProvider $sampleUrlProvider
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        ProductDownloadableLinksQuery $productDownloadableLinksQuery,
        StoreRepositoryInterface $storeRepository,
        DownloadableLinksOptionUid $downloadableLinksOptionUid,
        SampleUrlProvider $sampleUrlProvider
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->productDownloadableLinksQuery = $productDownloadableLinksQuery;
        $this->storeRepository = $storeRepository;
        $this->downloadableLinksOptionUid = $downloadableLinksOptionUid;
        $this->sampleUrlProvider = $sampleUrlProvider;
    }

    /**
     * Get downloadable links with link values
     *
     * @param array $values
     * @return array
     * @throws \Exception
     */
    public function get(array $values): array
    {
        $productIds = [];
        $storeViewCode = current($values)['storeViewCode'];
        foreach ($values as $value) {
            if ($value['type'] == DownloadableLinksOptionUid::OPTION_TYPE) {
                $productIds[] = $value['productId'];
            }
        }
        if (!empty($productIds)) {
            $storeId = (int)$this->storeRepository->get($storeViewCode)->getId();
            $downloadableLinksSelect = $this->productDownloadableLinksQuery->getQuery($productIds, $storeId);
            $downloadableLinksQuery = $this->resourceConnection->getConnection()->query($downloadableLinksSelect);
            $this->linkOptions = $downloadableLinksQuery->fetchAll();
            return $this->format(
                $this->buildProductAttributes($values),
                $storeViewCode
            );
        }
        return [];
    }

    /**
     * Format provider data
     *
     * @param array $attributes
     * @param string $storeViewCode
     *
     * @return array
     *
     * @throws NoSuchEntityException
     */
    private function format(array $attributes, string $storeViewCode): array
    {
        $output = [];
        $products = array_keys($attributes);
        foreach ($products as $productId) {
            $key = (string)$productId . $storeViewCode;
            $output[$key] = [
                'productId' => (string)$productId,
                'storeViewCode' => $storeViewCode,
                'productOptions' => [
                    'id' => 'link:' . (string)$productId,
                    'label' => $attributes[(string)$productId]['links_title'],
                    'type' => DownloadableLinksOptionUid::OPTION_TYPE,
                    'values' => $this->processOptionValues((string)$productId, $storeViewCode)
                ]
            ];
        }
        return $output;
    }

    /**
     * Process option values.
     *
     * @param string $productId
     * @param string $storeViewCode
     *
     * @return array
     *
     * @throws NoSuchEntityException
     */
    private function processOptionValues(string $productId, string $storeViewCode): array
    {
        $values = [];
        foreach ($this->linkOptions as $key => $option) {
            if ($productId == $option['entity_id']) {
                $values[] = [
                    'id' => $this->downloadableLinksOptionUid->resolve($option['link_id']),
                    'label' => $option['title'],
                    'sort_order' => $option['sort_order'],
                    'info_url' => $this->getLinkSampleUrl($option, $storeViewCode),
                    'price' => (float)$option['price']
                ];
                unset($this->linkOptions[$key]);
            }
        }
        return $values;
    }

    /**
     * Retrieve link sample url
     *
     * @param array $option
     * @param string $storeViewCode
     *
     * @return string|null
     *
     * @throws NoSuchEntityException
     */
    private function getLinkSampleUrl(array $option, string $storeViewCode): ?string
    {
        if (null !== $option['sample_url']) {
            return $this->sampleUrlProvider->getBaseSampleUrlByStoreViewCode($storeViewCode) . $option['link_id'];
        }

        return null;
    }

    /**
     * Build the downloadable product attributes
     *
     * @param array $products
     * @return array
     */
    private function buildProductAttributes(array $products): array
    {
        $attributes = [];

        foreach ($products as $attribute) {
            $attributes[$attribute['productId']] = ['links_title' => $attribute['linksTitle']];
        }
        return $attributes;
    }
}
