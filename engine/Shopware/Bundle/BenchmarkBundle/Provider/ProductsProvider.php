<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Bundle\BenchmarkBundle\Provider;

use Doctrine\DBAL\Connection;
use Shopware\Bundle\BenchmarkBundle\BenchmarkProviderInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class ProductsProvider implements BenchmarkProviderInterface
{
    /**
     * @var Connection
     */
    private $dbalConnection;

    /**
     * @var ShopContextInterface
     */
    private $shopContext;

    public function __construct(Connection $dbalConnection)
    {
        $this->dbalConnection = $dbalConnection;
    }

    public function getName()
    {
        return 'products';
    }

    /**
     * {@inheritdoc}
     */
    public function getBenchmarkData(ShopContextInterface $shopContext)
    {
        $this->shopContext = $shopContext;

        return [
            'list' => $this->getProductList(),
        ];
    }

    /**
     * @return array
     */
    private function getProductList()
    {
        $config = $this->getConfig();
        $batch = (int) $config['batch_size'];
        $lastProductId = (int) $config['last_product_id'];

        $productIds = $this->getProductIds($batch, $lastProductId);

        $basicProducts = $this->getBasicProductData($productIds);

        $productIds = array_keys($basicProducts);

        $variantsPerProduct = $this->getVariantsForProducts($productIds);
        $propertiesPerProduct = $this->getPropertiesPerProduct($productIds);
        $imagesPerProduct = $this->getImagesPerProduct($productIds);

        foreach ($basicProducts as $productId => &$basicProduct) {
            if (array_key_exists($productId, $variantsPerProduct)) {
                $basicProduct['variants'] = $variantsPerProduct[$productId];
            }

            if (array_key_exists($productId, $propertiesPerProduct)) {
                $basicProduct['properties'] = $propertiesPerProduct[$productId];
            }

            if (array_key_exists($productId, $imagesPerProduct)) {
                $basicProduct['images'] = $imagesPerProduct[$productId];
            }
        }

        $lastProductId = end($productIds);

        if ($lastProductId) {
            $this->updateLastProductId($lastProductId);
        }

        return array_values($basicProducts);
    }

    /**
     * @param array $productIds
     *
     * @return array
     */
    private function getBasicProductData(array $productIds)
    {
        $queryBuilder = $this->dbalConnection->createQueryBuilder();

        return $queryBuilder->select([
            'details.articleID',
            'details.active',
            'details.instock',
            'details.stockmin as instockMinimum',
            'details.lastStock as sale',
            'details.minpurchase as minPurchase',
            'details.maxpurchase as maxPurchase',
            'details.purchasesteps as purchaseSteps',
            'details.instock > 0 as shippingReady',
            'details.shippingfree as shippingFree',
            'productMain.pseudosales as pseudoSales',
            'productMain.topseller as topSeller',
            'productMain.notification as notificationEnabled',
            'details.shippingtime as shippingTime',
        ])
            ->from('s_articles_details', 'details')
            ->innerJoin('details', 's_articles', 'productMain', 'productMain.id = details.articleID')
            ->where('productMain.id IN (:productIds)')
            ->setParameter(':productIds', $productIds, Connection::PARAM_INT_ARRAY)
            ->execute()
            ->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
    }

    /**
     * @param array $productIds
     *
     * @return array
     */
    private function getVariantsForProducts(array $productIds)
    {
        $queryBuilder = $this->dbalConnection->createQueryBuilder();

        return $queryBuilder->select([
            'details.articleID',
            'details.active',
            'details.instock',
            'details.stockmin as instockMinimum',
            'details.lastStock as sale',
            'details.minpurchase as minPurchase',
            'details.maxpurchase as maxPurchase',
            'details.purchasesteps as purchaseSteps',
            'details.instock > 0 as shippingReady',
            'details.shippingfree as shippingFree',
            'details.shippingtime as shippingTime',
        ])
            ->from('s_articles_details', 'details')
            ->where('details.articleID IN (:productIds)')
            ->andWhere('details.kind = 2')
            ->setParameter(':productIds', $productIds, Connection::PARAM_INT_ARRAY)
            ->execute()
            ->fetchAll(\PDO::FETCH_GROUP);
    }

    /**
     * @param array $productIds
     *
     * @return array
     */
    private function getPropertiesPerProduct(array $productIds)
    {
        $properties = [];

        $valueIdsQueryBuilder = $this->dbalConnection->createQueryBuilder();

        $valueIds = $valueIdsQueryBuilder->select('filters.articleID, GROUP_CONCAT(filters.valueID)')
            ->from('s_filter_articles', 'filters')
            ->where('filters.articleID IN (:productIds)')
            ->setParameter(':productIds', $productIds, Connection::PARAM_INT_ARRAY)
            ->groupBy('filters.articleID')
            ->execute()
            ->fetchAll(\PDO::FETCH_KEY_PAIR);

        foreach ($valueIds as $productId => $valueIdString) {
            $valuesQueryBuilder = $this->dbalConnection->createQueryBuilder();
            $values = $valuesQueryBuilder->select('filterOptions.name, filterValues.value')
                ->from('s_filter_values', 'filterValues')
                ->innerJoin('filterValues', 's_filter_options', 'filterOptions', 'filterOptions.id = filterValues.optionID')
                ->where('filterValues.id IN (:filterValues)')
                ->setParameter(':filterValues', explode(',', $valueIdString), Connection::PARAM_INT_ARRAY)
                ->execute()
                ->fetchAll(\PDO::FETCH_GROUP);

            foreach ($values as $optionName => $valueArray) {
                $values[$optionName] = array_column($valueArray, 'value');
            }

            $properties[$productId] = $values;
        }

        return $properties;
    }

    /**
     * @param array $productIds
     *
     * @return array
     */
    private function getImagesPerProduct(array $productIds)
    {
        $productMedias = [];

        $queryBuilder = $this->dbalConnection->createQueryBuilder();

        $mediaIdArray = $queryBuilder->select('image.articleID, GROUP_CONCAT(image.media_id)')
            ->from('s_articles_img', 'image')
            ->where('image.articleID IN (:productIds)')
            ->groupBy('image.articleID')
            ->setParameter(':productIds', $productIds, Connection::PARAM_INT_ARRAY)
            ->execute()
            ->fetchAll(\PDO::FETCH_KEY_PAIR);

        foreach ($mediaIdArray as $productId => $mediaIds) {
            $mediaQueryBuilder = $this->dbalConnection->createQueryBuilder();
            $medias = $mediaQueryBuilder->select('media.width, media.height, media.extension, media.file_size as fileSize')
                ->from('s_media', 'media')
                ->where('media.id IN (:mediaIds)')
                ->setParameter(':mediaIds', explode(',', $mediaIds), Connection::PARAM_INT_ARRAY)
                ->execute()
                ->fetchAll();

            $productMedias[$productId] = $medias;
        }

        return $productMedias;
    }

    /**
     * @param int $batch
     * @param int $lastProductId
     *
     * @return array
     */
    private function getProductIds($batch, $lastProductId)
    {
        $categoryIds = $this->getPossibleCategoryIds();

        $queryBuilder = $this->dbalConnection->createQueryBuilder();

        return $queryBuilder->select('DISTINCT productCat.articleID')
            ->from('s_articles_categories', 'productCat')
            ->where('productCat.categoryID IN (:categoryIds)')
            ->andWhere('productCat.articleID > :lastProductId')
            ->setMaxResults($batch)
            ->setParameter(':categoryIds', $categoryIds, Connection::PARAM_INT_ARRAY)
            ->setParameter(':lastProductId', $lastProductId)
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @return array
     */
    private function getPossibleCategoryIds()
    {
        $categoryId = $this->shopContext->getShop()->getCategory()->getId();

        $queryBuilder = $this->dbalConnection->createQueryBuilder();

        return $queryBuilder->select('category.id')
            ->from('s_categories', 'category')
            ->where('category.path LIKE :categoryIdPath')
            ->orWhere('category.id = :categoryId')
            ->setParameter(':categoryId', $categoryId)
            ->setParameter(':categoryIdPath', '%|' . $categoryId . '|%')
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @return array
     */
    private function getConfig()
    {
        $configsQueryBuilder = $this->dbalConnection->createQueryBuilder();

        return $configsQueryBuilder->select('configs.*')
            ->from('s_benchmark_config', 'configs')
            ->where('configs.shop_id = :shopId')
            ->setParameter(':shopId', $this->shopContext->getShop()->getId())
            ->execute()
            ->fetch();
    }

    /**
     * @param string $lastProductId
     */
    private function updateLastProductId($lastProductId)
    {
        $queryBuilder = $this->dbalConnection->createQueryBuilder();
        $queryBuilder->update('s_benchmark_config')
            ->set('last_product_id', ':lastProductId')
            ->where('shop_id = :shopId')
            ->setParameter(':shopId', $this->shopContext->getShop()->getId())
            ->setParameter(':lastProductId', $lastProductId)
            ->execute();
    }
}
