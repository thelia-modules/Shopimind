<?php

namespace Shopimind\lib;

use Shopimind\Model\ShopimindQuery;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Country;
use Thelia\Model\Order;
use Thelia\Model\State;
use Thelia\TaxEngine\TaxEngine;

/**
 * Données du tag ShopiMind : les clés, leur ordre et leurs types sont fixes. Elles alimentent
 * _spmq, l'URL de v5.js, /shopimind/spmq et le panier envoyé à /cs par spm-cart.js.
 */
class SpmTag
{
    /** Clé de session de la commande en cours du tunnel (Session::setOrder de Thelia). */
    const SESSION_ORDER_KEY = 'thelia.order';

    /** Erreurs de calcul déjà journalisées pendant la requête : le panier est construit plusieurs fois par page. */
    protected static $loggedErrors = [];

    /**
     * _spmq de la page. Avec le contournement du cache, le client et le panier sont omis :
     * /shopimind/spmq les fournit après le chargement de la page.
     *
     * @param Request        $request
     * @param Cart|null      $cart
     * @param bool           $full               true : panier complet (_spmq) ; false : empreinte cart_hash
     * @param string|null    $confirmCartToOrder « id_panier;id_commande » sur la page de confirmation
     * @param TaxEngine|null $taxEngine          moteur de taxes de Thelia, pour les montants TTC du panier
     * @return array
     */
    public static function getCurrentUserInfos( Request $request, $cart, bool $full, ?string $confirmCartToOrder = null, ?TaxEngine $taxEngine = null ): array
    {
        $infos = self::getCurrentUserCustomerInfos( $request, false );

        $infos['id_product'] = self::requestValue( $request, 'product_id' );
        $infos['id_category'] = self::requestValue( $request, 'category_id' );
        $infos['id_manufacturer'] = self::requestValue( $request, 'brand_id' );

        $config = ShopimindQuery::create()->findOne();
        $infos['spm_ident'] = !empty( $config ) ? (string) $config->getApiId() : '';

        $language = self::getLanguage( $request );
        if ( $language !== '' ) {
            $infos['language'] = $language;
        }

        $infos = array_merge( $infos, self::getCurrentUserCartInfos( $request, $cart, false, $full, $taxEngine ) );

        if ( !empty( $confirmCartToOrder ) ) {
            $infos['confirm_cart_to_order'] = $confirmCartToOrder;
        }

        return $infos;
    }

    /**
     * Client et panier seuls, servis par /shopimind/spmq quand le contournement du cache est actif.
     *
     * @param Request        $request
     * @param Cart|null      $cart
     * @param bool           $full
     * @param TaxEngine|null $taxEngine
     * @return array
     */
    public static function getCurrentUserInfosCacheWorkaround( Request $request, $cart, bool $full, ?TaxEngine $taxEngine = null ): array
    {
        return array_merge(
            self::getCurrentUserCustomerInfos( $request, true ),
            self::getCurrentUserCartInfos( $request, $cart, true, $full, $taxEngine )
        );
    }

    /**
     * @param Request $request
     * @param bool    $callFromCacheWorkaround
     * @return array
     */
    public static function getCurrentUserCustomerInfos( Request $request, bool $callFromCacheWorkaround ): array
    {
        if ( !$callFromCacheWorkaround && Utils::useCacheWorkaround() ) {
            return [];
        }

        $customer = self::getCustomer( $request );

        return [
            'user' => !empty( $customer ) ? [ 'id_customer' => (string) $customer->getId() ] : null,
        ];
    }

    /**
     * @param Request        $request
     * @param Cart|null      $cart
     * @param bool           $callFromCacheWorkaround
     * @param bool           $full
     * @param TaxEngine|null $taxEngine
     * @return array
     */
    public static function getCurrentUserCartInfos( Request $request, $cart, bool $callFromCacheWorkaround, bool $full, ?TaxEngine $taxEngine = null ): array
    {
        if ( !$callFromCacheWorkaround && Utils::useCacheWorkaround() ) {
            return [];
        }

        $hasCart = !empty( $cart ) && $cart->getId();

        $infos = [];
        if ( $hasCart ) {
            $infos['id_cart'] = (string) $cart->getId();
            if ( $full ) {
                $infos['cart'] = self::getCurrentCart( $request, $cart, $taxEngine );
            }
        } else {
            $infos['id_cart'] = null;
        }

        if ( !$full ) {
            $infos['cart_hash'] = sha1( serialize( $hasCart ? self::getCurrentCart( $request, $cart, $taxEngine ) : [] ) );
        }

        return $infos;
    }

    /**
     * Panier au format attendu par /cs : dates « Y-m-d H:i:s », montants arrondis, identifiants en chaînes.
     * Montants calculés comme les totaux du panier sur le front, avec le même pays de taxe.
     *
     * @param Request        $request
     * @param Cart           $cart
     * @param TaxEngine|null $taxEngine
     * @return array
     */
    public static function getCurrentCart( Request $request, Cart $cart, ?TaxEngine $taxEngine = null ): array
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        [ $country, $state ] = self::getTaxLocation( $request, $taxEngine );

        // Frais de port inclus dès que le transporteur est choisi dans le tunnel (commande en session).
        $postage = 0.0;
        $postageTax = 0.0;
        $sessionOrder = self::getSessionOrder( $request );
        if ( null !== $sessionOrder && $sessionOrder->getDeliveryModuleId() ) {
            $postage = (float) $sessionOrder->getPostage();
            $postageTax = (float) $sessionOrder->getPostageTax();
        }

        $vouchers = ( null !== $session && method_exists( $session, 'getConsumedCoupons' ) ) ? (array) $session->getConsumedCoupons() : [];
        $vouchers = array_values( array_filter( array_map( 'strval', $vouchers ) ) );

        $currency = $cart->getCurrency();

        // TTC : remise TTC déduite. HT : remise hors taxe du même pays (sans pays, Thelia déduirait la remise TTC).
        $amount = null;
        $amountWithoutTax = null;
        if ( null !== $country ) {
            try {
                $amount = (float) $cart->getTaxedAmount( $country, true, $state );
                try {
                    $amountWithoutTax = (float) $cart->getTotalAmount( true, $country, $state );
                } catch ( \DivisionByZeroError $e ) {
                    // Aucune taxe pour ce pays : la remise hors taxe est la remise TTC.
                    $amountWithoutTax = (float) $cart->getTotalAmount();
                }
            } catch ( \Throwable $e ) {
                // Taxe incalculable (produit sans règle de taxe, par exemple) : montants hors taxe.
                self::logCartError( 'CartAmount', $e, $cart->getId() );
                $country = null;
                $state = null;
                $amount = null;
            }
        }
        if ( null === $amount ) {
            $amount = (float) $cart->getTotalAmount();
            $amountWithoutTax = $amount;
        }

        $products = self::getCartProducts( (int) $cart->getId(), $country, $state );

        return [
            'id_customer' => (string) (int) $cart->getCustomerId(),
            'id_cart' => (string) $cart->getId(),
            'date_add' => $cart->getCreatedAt() ? $cart->getCreatedAt()->format( 'Y-m-d H:i:s' ) : '',
            'date_upd' => $cart->getUpdatedAt() ? $cart->getUpdatedAt()->format( 'Y-m-d H:i:s' ) : '',
            'amount' => Utils::formatNumber( $amount + $postage ),
            'amount_without_tax' => Utils::formatNumber( $amountWithoutTax + $postage - $postageTax ),
            'tax_rate' => !empty( $currency ) ? (string) $currency->getRate() : '',
            'currency' => !empty( $currency ) ? (string) $currency->getCode() : '',
            'voucher_used' => !empty( $vouchers ) ? $vouchers : '',
            'voucher_amount' => Utils::formatNumber( abs( (float) $cart->getDiscount() ) ),
            'products' => !empty( $products ) ? $products : '',
        ];
    }

    /**
     * Lignes du panier : price = prix catalogue TTC, price_discount = prix payé TTC,
     * price_without_tax = prix payé HT. Sans pays de taxe ou taxe incalculable, prix hors taxe.
     *
     * @param int          $cartId
     * @param Country|null $country
     * @param State|null   $state
     * @return array
     */
    public static function getCartProducts( int $cartId, ?Country $country = null, ?State $state = null ): array
    {
        $response = [];

        $cartItems = CartItemQuery::create()->filterByCartId( $cartId )->find();
        foreach ( $cartItems as $cartItem ) {
            $paidExclTax = (float) $cartItem->getRealPrice();
            $regularInclTax = (float) $cartItem->getPrice();
            $paidInclTax = $paidExclTax;
            if ( null !== $country ) {
                try {
                    $regularInclTax = (float) $cartItem->getTaxedPrice( $country, $state );
                    $paidInclTax = (float) $cartItem->getRealTaxedPrice( $country, $state );
                } catch ( \Throwable $e ) {
                    // Taxe incalculable pour cette ligne : prix hors taxe.
                    self::logCartError( 'CartProduct', $e, $cartItem->getId() );
                    $regularInclTax = (float) $cartItem->getPrice();
                    $paidInclTax = $paidExclTax;
                }
            }
            if ( $regularInclTax <= 0 ) {
                $regularInclTax = $paidInclTax;
            }

            $product = $cartItem->getProduct();

            $response[] = [
                'id_product' => (string) $cartItem->getProductId(),
                'id_combination' => (string) $cartItem->getProductSaleElementsId(),
                'id_manufacturer' => (string) (int) ( !empty( $product ) ? $product->getBrandId() : 0 ),
                'qty' => (string) $cartItem->getQuantity(),
                'price' => Utils::formatNumber( $regularInclTax ),
                'price_discount' => Utils::formatNumber( $paidInclTax ),
                'price_without_tax' => Utils::formatNumber( $paidExclTax ),
            ];
        }

        return $response;
    }

    /**
     * Pays et région de taxe des montants TTC, comme sur le front : adresse de livraison choisie dans le
     * tunnel, sinon adresse par défaut du client connecté, sinon pays par défaut. Aucune commande n'est
     * créée en session et aucune erreur n'est propagée.
     *
     * @param Request        $request
     * @param TaxEngine|null $taxEngine
     * @return array [ Country|null, State|null ]
     */
    public static function getTaxLocation( Request $request, ?TaxEngine $taxEngine = null ): array
    {
        $country = null;
        $state = null;

        try {
            $customer = self::getCustomer( $request );

            if ( null !== $taxEngine && ( empty( $customer ) || null !== self::getSessionOrder( $request ) ) ) {
                $country = $taxEngine->getDeliveryCountry();
                $state = $taxEngine->getDeliveryState();
            } elseif ( !empty( $customer ) && is_callable( [ $customer, 'getDefaultAddress' ] ) ) {
                // Client connecté sans commande en session : le moteur de taxes en créerait une vide pour
                // aboutir à l'adresse par défaut du client, lue ici directement.
                $address = $customer->getDefaultAddress();
                if ( !empty( $address ) ) {
                    $country = $address->getCountry();
                    $state = $address->getState();
                }
            }
        } catch ( \Throwable $e ) {
            self::logCartError( 'TaxLocation', $e );
            $country = null;
        }

        if ( !$country instanceof Country ) {
            return [ self::getDefaultTaxCountry(), null ];
        }

        return [ $country, $state instanceof State ? $state : null ];
    }

    /**
     * Pays par défaut de la boutique, dernier recours du moteur de taxes, sinon pays de la boutique.
     *
     * @return Country|null
     */
    protected static function getDefaultTaxCountry(): ?Country
    {
        try {
            $country = Country::getDefaultCountry();
            if ( $country instanceof Country ) {
                return $country;
            }
        } catch ( \Throwable $e ) {
            // Pas de pays par défaut : pays de la boutique.
        }

        try {
            $country = Country::getShopLocation();

            return $country instanceof Country ? $country : null;
        } catch ( \Throwable $e ) {
            // Aucun pays configuré : montants hors taxe.
            self::logCartError( 'TaxLocation', $e );

            return null;
        }
    }

    /**
     * Commande en cours du tunnel, lue sans Session::getOrder() qui en crée et enregistre une vide.
     *
     * @param Request $request
     * @return Order|null
     */
    protected static function getSessionOrder( Request $request ): ?Order
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        $order = ( null !== $session && method_exists( $session, 'get' ) ) ? $session->get( self::SESSION_ORDER_KEY ) : null;

        return $order instanceof Order ? $order : null;
    }

    /**
     * Journalise une erreur de calcul du panier une seule fois par requête, sans la pile d'appels.
     *
     * @param string          $object
     * @param \Throwable      $e
     * @param int|string|null $objectId
     */
    protected static function logCartError( string $object, \Throwable $e, $objectId = null ): void
    {
        $key = $object . '|' . get_class( $e ) . '|' . $objectId;
        if ( isset( self::$loggedErrors[ $key ] ) ) {
            return;
        }
        self::$loggedErrors[ $key ] = true;

        Utils::logError( 'Cart', $object, get_class( $e ) . ': ' . $e->getMessage(), [], $objectId );
    }

    /**
     * @param Request $request
     * @return mixed client connecté ou null
     */
    protected static function getCustomer( Request $request )
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        return ( null !== $session && method_exists( $session, 'getCustomerUser' ) ) ? $session->getCustomerUser() : null;
    }

    /**
     * @param Request $request
     * @return string code ISO 639-1 de la langue courante, ou chaîne vide
     */
    protected static function getLanguage( Request $request ): string
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        $lang = ( null !== $session && method_exists( $session, 'getLang' ) ) ? $session->getLang( true ) : null;

        return !empty( $lang ) ? (string) $lang->getCode() : '';
    }

    /**
     * @param Request $request
     * @param string  $key
     * @return string
     */
    protected static function requestValue( Request $request, string $key ): string
    {
        $value = $request->get( $key, '' );

        return is_scalar( $value ) ? (string) $value : '';
    }
}
