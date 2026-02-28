<?php

class ZenaiProductExportService
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function exportCatalogCsv()
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        $sql = new DbQuery();
        $sql->select('
            p.id_product,
            pl.name,
            pl.description,
            pl.description_short,
            IFNULL(cl.name, "") AS category,
            IFNULL(m.name, "") AS brand,
            p.isbn,
            p.ean13,
            p.mpn,
            p.upc,
            p.price
        ');
        $sql->from('product', 'p');
        $sql->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . ' AND ps.active = 1');
        $sql->innerJoin('product_lang', 'pl', 'pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop);
        $sql->leftJoin('category_lang', 'cl', 'cl.id_category = ps.id_category_default AND cl.id_lang = ' . $idLang . ' AND cl.id_shop = ' . $idShop);
        $sql->leftJoin('manufacturer', 'm', 'm.id_manufacturer = p.id_manufacturer');
        $sql->orderBy('p.id_product ASC');

        $products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $productIds = [];

        if (is_array($products)) {
            foreach ($products as $product) {
                $productIds[] = (int) $product['id_product'];
            }
        }

        $featuresByProduct = $this->getFeaturesByProductIds($productIds, $idLang);
        $combinationsByProduct = $this->getCombinationsByProductIds($productIds, $idLang);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="zenai-product-export.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['product_id', 'title', 'description', 'category', 'price']);

        if (is_array($products)) {
            foreach ($products as $product) {
                $description = trim(strip_tags((string) ($product['description_short'] ?: $product['description'])));
                $descriptionParts = [];

                if ($description !== '') {
                    $descriptionParts[] = $description;
                }

                $brand = trim((string) $product['brand']);
                if ($brand !== '') {
                    $descriptionParts[] = 'Brand: ' . $brand;
                }

                $productFeatureLines = $featuresByProduct[(int) $product['id_product']] ?? [];
                foreach ($productFeatureLines as $featureLine) {
                    $descriptionParts[] = $featureLine;
                }

                $productCombinationLines = $combinationsByProduct[(int) $product['id_product']] ?? [];
                foreach ($productCombinationLines as $combinationLine) {
                    $descriptionParts[] = $combinationLine;
                }

                $isbn = trim((string) $product['isbn']);
                if ($isbn !== '') {
                    $descriptionParts[] = 'ISBN: ' . $isbn;
                }

                $gtin = trim((string) $product['ean13']);
                if ($gtin !== '') {
                    $descriptionParts[] = 'GTIN: ' . $gtin;
                }

                $mpn = trim((string) $product['mpn']);
                if ($mpn !== '') {
                    $descriptionParts[] = 'MPN: ' . $mpn;
                }

                $upc = trim((string) $product['upc']);
                if ($upc !== '') {
                    $descriptionParts[] = 'UPC: ' . $upc;
                }

                $finalDescription = implode('. ', $descriptionParts);

                fputcsv($output, [
                    (string) $product['id_product'],
                    (string) $product['name'],
                    $finalDescription,
                    (string) $product['category'],
                    (string) $product['price'],
                ]);
            }
        }

        fclose($output);
        exit;
    }

    /**
     * @param int[] $productIds
     *
     * @return array<int, string[]>
     */
    private function getFeaturesByProductIds(array $productIds, $idLang)
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }

        $sql = new DbQuery();
        $sql->select('fp.id_product, fl.name AS feature_name, fvl.value AS feature_value');
        $sql->from('feature_product', 'fp');
        $sql->innerJoin('feature_lang', 'fl', 'fl.id_feature = fp.id_feature AND fl.id_lang = ' . (int) $idLang);
        $sql->innerJoin('feature_value_lang', 'fvl', 'fvl.id_feature_value = fp.id_feature_value AND fvl.id_lang = ' . (int) $idLang);
        $sql->where('fp.id_product IN (' . implode(',', $productIds) . ')');
        $sql->orderBy('fp.id_product ASC, fl.name ASC');

        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $featuresByProduct = [];
        foreach ($rows as $row) {
            $productId = (int) $row['id_product'];
            $featureName = trim((string) $row['feature_name']);
            $featureValue = trim((string) $row['feature_value']);

            if ($featureName === '' || $featureValue === '') {
                continue;
            }

            $line = $featureName . ': ' . $featureValue;
            if (!isset($featuresByProduct[$productId])) {
                $featuresByProduct[$productId] = [];
            }

            if (!in_array($line, $featuresByProduct[$productId], true)) {
                $featuresByProduct[$productId][] = $line;
            }
        }

        return $featuresByProduct;
    }

    /**
     * @param int[] $productIds
     *
     * @return array<int, string[]>
     */
    private function getCombinationsByProductIds(array $productIds, $idLang)
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }

        $sql = new DbQuery();
        $sql->select('
            pa.id_product,
            pa.id_product_attribute,
            ag.position AS group_position,
            a.position AS attribute_position,
            agl.name AS group_name,
            al.name AS attribute_name
        ');
        $sql->from('product_attribute', 'pa');
        $sql->innerJoin('product_attribute_combination', 'pac', 'pac.id_product_attribute = pa.id_product_attribute');
        $sql->innerJoin('attribute', 'a', 'a.id_attribute = pac.id_attribute');
        $sql->innerJoin('attribute_lang', 'al', 'al.id_attribute = a.id_attribute AND al.id_lang = ' . (int) $idLang);
        $sql->innerJoin('attribute_group', 'ag', 'ag.id_attribute_group = a.id_attribute_group');
        $sql->innerJoin('attribute_group_lang', 'agl', 'agl.id_attribute_group = ag.id_attribute_group AND agl.id_lang = ' . (int) $idLang);
        $sql->where('pa.id_product IN (' . implode(',', $productIds) . ')');
        $sql->orderBy('pa.id_product ASC, pa.id_product_attribute ASC, ag.position ASC, a.position ASC');

        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $combinationPartsByProduct = [];
        foreach ($rows as $row) {
            $idProduct = (int) $row['id_product'];
            $idProductAttribute = (int) $row['id_product_attribute'];
            $groupName = trim((string) $row['group_name']);
            $attributeName = trim((string) $row['attribute_name']);

            if ($groupName === '' || $attributeName === '') {
                continue;
            }

            $pair = $groupName . ' ' . $attributeName;
            if (!isset($combinationPartsByProduct[$idProduct])) {
                $combinationPartsByProduct[$idProduct] = [];
            }
            if (!isset($combinationPartsByProduct[$idProduct][$idProductAttribute])) {
                $combinationPartsByProduct[$idProduct][$idProductAttribute] = [];
            }

            if (!in_array($pair, $combinationPartsByProduct[$idProduct][$idProductAttribute], true)) {
                $combinationPartsByProduct[$idProduct][$idProductAttribute][] = $pair;
            }
        }

        $combinationsByProduct = [];
        foreach ($combinationPartsByProduct as $idProduct => $partsByCombination) {
            $combinationsByProduct[$idProduct] = [];

            foreach ($partsByCombination as $parts) {
                if ($parts === []) {
                    continue;
                }

                $line = implode(', ', $parts);
                if (!in_array($line, $combinationsByProduct[$idProduct], true)) {
                    $combinationsByProduct[$idProduct][] = $line;
                }
            }
        }

        return $combinationsByProduct;
    }
}
