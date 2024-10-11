<?php
namespace Shopimind\SdkShopimind;

use GuzzleHttp\Client as GuzzleClient;

class SpmShopConnection
{
    /**
     * Save the configuration to a persistent
     *
     */
    public static function saveConfiguration( $httpClient, $data){
        $endpoint = 'shop/connection';

        try {
            $response = $httpClient->post( $endpoint, [ 'json' => $data ]);
            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->hasResponse()) {
                $responseBody = (string) $e->getResponse()->getBody();
                return json_decode($responseBody, true);
            } else {
                return ['error' => 'Unknown error'];
            }
        } catch (\Exception $e) {
            return ['error' => 'Unknown error'];
        }
    }
}
