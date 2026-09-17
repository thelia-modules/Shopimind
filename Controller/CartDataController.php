<?php

namespace Shopimind\Controller;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Shopimind\lib\SpmTag;
use Shopimind\lib\Utils;
use Shopimind\Model\ShopimindQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\TaxEngine\TaxEngine;

/**
 * Panier de la session au format attendu par la route /cs de ShopiMind.
 *
 * Route : GET /shopimind/cart-data
 */
class CartDataController extends BaseFrontController
{
    /**
     * Dispatcher et moteur de taxes injectés en arguments de l'action ; le moteur vaut null s'il est indisponible.
     *
     * @param Request                  $request
     * @param EventDispatcherInterface $eventDispatcher
     * @param TaxEngine|null           $taxEngine
     * @return JsonResponse
     */
    public function getCartData(Request $request, EventDispatcherInterface $eventDispatcher, ?TaxEngine $taxEngine = null): JsonResponse
    {
        // Appelée sur chaque page par le tag : une erreur renvoie un panier vide au lieu d'une page 500.
        try {
            return $this->buildCartData($request, $eventDispatcher, $taxEngine);
        } catch (\Throwable $e) {
            Utils::logException('Cart', 'CartData', $e);
            $response = new JsonResponse(array('spm_ident' => '', 'url' => '', 'lang' => '', 'cart' => null));
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

            return $response;
        }
    }

    protected function buildCartData(Request $request, EventDispatcherInterface $eventDispatcher, ?TaxEngine $taxEngine = null): JsonResponse
    {
        $config = ShopimindQuery::create()->findOne();

        $spmIdent = $config ? $config->getApiId() : '';

        // Les contrôleurs Thelia 2.5 n'ont pas de getCart() (seul BaseHook en a un) : l'appel
        // rappelait cette action sans argument et la route répondait 500. Panier de session
        // obtenu comme dans BaseHook, avec le dispatcher injecté en argument de l'action
        // (même mécanisme que RequestHandler::requestController).
        $cart = $request->hasSession() ? $request->getSession()->getSessionCart($eventDispatcher) : null;
        // Panier au même format que dans _spmq.
        $cartPayload = ( $cart && $cart->getId() ) ? SpmTag::getCurrentCart( $request, $cart, $taxEngine ) : null;

        $session = $request->hasSession() ? $request->getSession() : null;
        $lang = '';
        if ($session && $session->getLang()) {
            $lang = $session->getLang()->getCode();
        }

        $response = new JsonResponse(array(
            'spm_ident' => $spmIdent,
            'url'       => $request->headers->get('referer', ''),
            'lang'      => $lang,
            'cart'      => $cartPayload,
        ));
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
