<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    ZenAI Software
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;

class ZenaiSearchRunner
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var ZenaiApiClient
     */
    private $apiClient;

    /**
     * @var string
     */
    private $apiToken;

    public function __construct(Context $context, ZenaiApiClient $apiClient, $apiToken)
    {
        $this->context = $context;
        $this->apiClient = $apiClient;
        $this->apiToken = (string) $apiToken;
    }

    public function runQuery(ProductSearchContext $searchContext, ProductSearchQuery $query)
    {
        $recommendedProductIds = $this->apiClient->fetchRecommendedProductIds(
            (string) $query->getSearchString(),
            $this->apiToken
        );

        $result = new ProductSearchResult();
        $relevanceSortOrder = (new SortOrder('product', 'position', 'desc'))->setLabel('Relevance');
        $result->setCurrentSortOrder($relevanceSortOrder);
        $result->setAvailableSortOrders([$relevanceSortOrder]);

        if ($recommendedProductIds === []) {
            $result->setProducts([]);
            $result->setTotalProductsCount(0);

            return $result;
        }

        $rawProducts = $this->fetchRawProductsByRecommendedIds($searchContext, $recommendedProductIds);
        if ($rawProducts === []) {
            $result->setProducts([]);
            $result->setTotalProductsCount(0);

            return $result;
        }

        try {
            $assembler = new ProductAssembler($this->context);
            $products = $assembler->assembleProducts($rawProducts);
        } catch (Exception $e) {
            $result->setProducts([]);
            $result->setTotalProductsCount(0);

            return $result;
        }

        $resultsPerPage = max((int) $query->getResultsPerPage(), 1);
        $page = max((int) $query->getPage(), 1);
        $offset = ($page - 1) * $resultsPerPage;

        $result->setProducts(array_slice($products, $offset, $resultsPerPage));
        $result->setTotalProductsCount(count($products));

        return $result;
    }

    /**
     * @param int[] $recommendedProductIds
     *
     * @return array<int, array<string, int>>
     */
    private function fetchRawProductsByRecommendedIds(ProductSearchContext $searchContext, array $recommendedProductIds)
    {
        $idShop = (int) $searchContext->getIdShop();

        $sql = new DbQuery();
        $sql->select('p.id_product');
        $sql->from('product', 'p');
        $sql->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product AND ps.id_shop = ' . $idShop);
        $sql->where('p.id_product IN (' . implode(',', $recommendedProductIds) . ')');
        $sql->where('ps.active = 1');
        $sql->where('ps.visibility IN ("both", "search")');
        $sql->where('ps.indexed = 1');
        $sql->orderBy('FIELD(p.id_product, ' . implode(',', $recommendedProductIds) . ')');

        $rawRows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if (!is_array($rawRows) || $rawRows === []) {
            return [];
        }

        return array_map(static function ($row) {
            return ['id_product' => (int) $row['id_product']];
        }, $rawRows);
    }
}
