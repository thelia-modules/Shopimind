<?php
namespace Shopimind\SdkShopimind;

use Shopimind\SdkShopimind\Http\Client as HttpClient;

class SpmUtils
{
    /**
     * @param string $apiVersion
     * @param string $apiKey
     * @param array  $headers
     * @return HttpClient
     */
    public static function getClient($apiVersion, $apiKey, $headers = array())
    {
        $defaultHeaders = array(
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'spm-api-key'  => $apiKey,
        );

        // Allow caller (e.g. PS debug override) to redirect SDK traffic by
        // exporting SHOPIMIND_CORE_API_BASE in the environment before this
        // call. Falls back to production URL otherwise.
        $envOverride = getenv('SHOPIMIND_CORE_API_BASE');
        $baseUrl = ($envOverride !== false && $envOverride !== '') ? $envOverride : 'https://core.shopimind.com';
        $baseUrl = rtrim($baseUrl, '/') . '/' . $apiVersion . '/';

        $mergedHeaders = array_merge($defaultHeaders, $headers);

        return new HttpClient(array(
            'base_uri' => $baseUrl,
            'headers'  => $mergedHeaders,
        ));
    }
}
