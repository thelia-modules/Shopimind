<?php

namespace Shopimind\Controller;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Shopimind\Model\ShopimindQuery;
use Shopimind\lib\Utils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Front\BaseFrontController;
use Propel\Runtime\Propel;

class SpmConfigController extends BaseFrontController
{
    /**
     * Handle script URL configuration requests
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleRequest(Request $request): JsonResponse
    {
        // Appel signé de ShopiMind (setScriptUrl) : corps et signature contrôlés avant tout traitement, comme pour
        // les webhooks. Un corps vide ne porte pas de signature valide et reçoit la même réponse 401.
        $body = Utils::getSpmRequestBody($request);
        if (null !== $unauthorized = Utils::authorizeSpmRequest($request, $body)) {
            return $unauthorized;
        }

        try {
            return $this->dispatchAction($body);
        } catch (\Throwable $th) {
            return Utils::spmErrorResponse('Webhook', 'Config', $th);
        }
    }

    private function dispatchAction(array $body): JsonResponse
    {
        $action = isset($body['action']) ? $body['action'] : '';

        switch ($action) {
            case 'set_script_url':
                return $this->setScriptUrl($body);
            case 'get_script_url':
                return $this->getScriptUrl();
            case 'reset_script_url':
                return $this->resetScriptUrl();
            default:
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Action not supported. Available actions: set_script_url, get_script_url, reset_script_url'
                ]);
        }
    }

    /**
     * Set the script URL
     *
     * @param array $body
     * @return JsonResponse
     */
    private function setScriptUrl(array $body): JsonResponse
    {
        $validation = $this->validateSetUrl($body);
        if (!empty($validation)) {
            return new JsonResponse($validation);
        }

        $url = $body['script_url'];

        try {
            $con = Propel::getConnection();
            $stmt = $con->prepare("UPDATE shopimind SET script_url = :url WHERE id = 1");
            $stmt->bindValue(':url', $url);
            $stmt->execute();

            return new JsonResponse([
                'success' => true,
                'message' => 'Script URL updated successfully',
                'script_url' => $url
            ]);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to update URL: ' . $th->getMessage()
            ]);
        }
    }

    /**
     * Get the current script URL
     *
     * @return JsonResponse
     */
    private function getScriptUrl(): JsonResponse
    {
        $constants = include THELIA_MODULE_DIR . '/Shopimind/constants.php';
        $defaultUrl = isset($constants['api']['script_url']) ? $constants['api']['script_url'] : 'https://v2.app-spm.com';

        try {
            $con = Propel::getConnection();
            $stmt = $con->prepare("SELECT script_url FROM shopimind WHERE id = 1");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $currentUrl = $result ? $result['script_url'] : null;
        } catch (\Throwable $th) {
            $currentUrl = null;
        }

        $activeUrl = !empty($currentUrl) ? $currentUrl : $defaultUrl;

        return new JsonResponse([
            'success' => true,
            'current_url' => $currentUrl,
            'default_url' => $defaultUrl,
            'active_url' => $activeUrl,
            'is_custom' => !empty($currentUrl)
        ]);
    }

    /**
     * Reset the script URL to default
     *
     * @return JsonResponse
     */
    private function resetScriptUrl(): JsonResponse
    {
        try {
            $con = Propel::getConnection();
            $stmt = $con->prepare("UPDATE shopimind SET script_url = NULL WHERE id = 1");
            $stmt->execute();

            $constants = include THELIA_MODULE_DIR . '/Shopimind/constants.php';
            $defaultUrl = isset($constants['api']['script_url']) ? $constants['api']['script_url'] : 'https://v2.app-spm.com';

            return new JsonResponse([
                'success' => true,
                'message' => 'Script URL reset to default',
                'script_url' => $defaultUrl
            ]);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to reset URL: ' . $th->getMessage()
            ]);
        }
    }

    /**
     * Validate the URL parameters
     *
     * @param array $params
     * @return array
     */
    private function validateSetUrl(array $params): array
    {
        if (empty($params['script_url'])) {
            return [
                'success' => false,
                'message' => 'URL is required'
            ];
        }

        // Adresse du script injecté dans la boutique : HTTP ou HTTPS uniquement.
        $scheme = is_string($params['script_url']) ? strtolower((string) parse_url($params['script_url'], PHP_URL_SCHEME)) : '';
        if (!filter_var($params['script_url'], FILTER_VALIDATE_URL) || !in_array($scheme, ['https', 'http'], true)) {
            return [
                'success' => false,
                'message' => 'Invalid URL format'
            ];
        }

        return [];
    }
}
