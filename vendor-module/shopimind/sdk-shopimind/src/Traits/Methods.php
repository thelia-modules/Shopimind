<?php
namespace Shopimind\SdkShopimind\Traits;
use ReflectionClass;

trait Methods {
    private function processSave( $endpoint, $data ) {
        try {
            $response =  $this->auth->post( $endpoint, ['json' => [$data]]);
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
    
    private static function processBulkSave( $httpClient, $endpoint, $data ) {
        try {
            $response = $httpClient->post( $endpoint, ['json' => $data]);
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

    private function processUpdate( $endpoint, $data ) {
        try {
            $response = $this->auth->put($endpoint, ['json' => [$data]]);
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

    private static function processBulkUpdate( $httpClient, $endpoint, $data ) {
        try {
            $response = $httpClient->put( $endpoint, ['json' => $data]);
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

    private static function processDelete( $httpClient, $endpoint, $id ) {
        try {
            $response = $httpClient->delete($endpoint . '/' . $id);
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

    private static function processBulkDelete( $httpClient, $endpoint, $data ) {
        try {
            $response = $httpClient->post($endpoint, ['json' => $data]);
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
