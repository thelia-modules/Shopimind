<?php
namespace Shopimind\SdkShopimind;

use GuzzleHttp\Client as GuzzleClient;

class SpmUtils
{
    /**
     * @param $apiVersion
     * @param $apiKey
     * @param array $headers
     * @return GuzzleClient
     */
    public static function getClient( $apiVersion, $apiKey, array $headers = [] ): GuzzleClient
    {
        $defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'spm-api-key' => $apiKey,
            'client-version' => '4.0.0',
            'current-build' => 1,
        ];
        $baseUrl = 'https://api.shopimind.com';
        $baseUrl = rtrim($baseUrl, '/') . '/' . $apiVersion . '/';

        return new GuzzleClient([
            'base_uri' => $baseUrl,
            'headers' => array_merge( $defaultHeaders, $headers ),
        ]);
    }
}
