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

class ZenaiApiClient
{
    private const API_BASE_URL = 'https://zenaisoftware.com';
    private const SEARCH_ENDPOINT = '/api/search';

    /**
     * @return int[]
     */
    public function fetchRecommendedProductIds($searchQuery, $apiToken)
    {
        $query = trim((string) $searchQuery);
        $token = trim((string) $apiToken);

        if ($query === '' || $token === '' || !function_exists('curl_init')) {
            return [];
        }

        $url = self::API_BASE_URL . self::SEARCH_ENDPOINT . '?q=' . rawurlencode($query);
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'x-token: ' . $token,
            ],
        ]);

        $body = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($body) || $body === '' || $httpCode < 200 || $httpCode >= 300) {
            return [];
        }

        $payload = json_decode($body, true);
        if (!is_array($payload) || !isset($payload['results']) || !is_array($payload['results'])) {
            return [];
        }

        $ids = [];
        foreach ($payload['results'] as $result) {
            if (!is_array($result)) {
                continue;
            }

            $productId = $result['product_id'] ?? null;
            $idProduct = (int) $productId;
            if ($idProduct > 0) {
                $ids[] = $idProduct;
            }
        }

        return array_values(array_unique($ids));
    }
}
