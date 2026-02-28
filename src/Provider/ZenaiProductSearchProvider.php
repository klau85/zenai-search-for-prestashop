<?php

use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;

class ZenaiProductSearchProvider implements ProductSearchProviderInterface
{
    /**
     * @var ZenaiSearchRunner
     */
    private $runner;

    public function __construct(ZenaiSearchRunner $runner)
    {
        $this->runner = $runner;
    }

    public function runQuery(ProductSearchContext $context, ProductSearchQuery $query)
    {
        return $this->runner->runQuery($context, $query);
    }
}
