<?php

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
