<?php

namespace Shopimind\Controller;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Shopimind\lib\SpmTag;
use Shopimind\lib\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\TaxEngine\TaxEngine;

/**
 * Client et panier du tag quand le contournement du cache est actif : spm_user_infos (JSON complet)
 * et spm_user_infos_encode (paramètres de v5.js).
 *
 * Route: GET /shopimind/spmq
 */
class SpmqController extends BaseFrontController
{
    /**
     * Moteur de taxes injecté en argument de l'action, comme le dispatcher ; null s'il est indisponible.
     *
     * @param Request                  $request
     * @param EventDispatcherInterface $eventDispatcher
     * @param TaxEngine|null           $taxEngine
     * @return JsonResponse
     */
    public function getSpmq( Request $request, EventDispatcherInterface $eventDispatcher, ?TaxEngine $taxEngine = null ): JsonResponse
    {
        try {
            $cart = $request->hasSession() ? $request->getSession()->getSessionCart( $eventDispatcher ) : null;
            $data = [
                'spm_user_infos' => json_encode( SpmTag::getCurrentUserInfosCacheWorkaround( $request, $cart, true, $taxEngine ) ),
                'spm_user_infos_encode' => http_build_query( SpmTag::getCurrentUserInfosCacheWorkaround( $request, $cart, false, $taxEngine ) ),
            ];
        } catch ( \Throwable $e ) {
            // Visiteur traité comme anonyme : le tag charge quand même v5.js.
            Utils::logException( 'Cart', 'Spmq', $e );
            $data = [
                'spm_user_infos' => json_encode( [ 'user' => null, 'id_cart' => null ] ),
                'spm_user_infos_encode' => '',
            ];
        }

        $response = new JsonResponse( $data );
        $response->headers->set( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

        return $response;
    }
}
