<?php

namespace Shopimind\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Shopimind\Model\Base\ShopimindQuery;
use Shopimind\lib\Utils;
use Thelia\Model\Country;
use Thelia\Model\CartItemQuery;
use Thelia\Model\OrderQuery;



class ShopimindHook extends BaseHook
{

    /**
     * Add script tag
     *
     * @param HookRenderEvent $event
     */
    public function addScriptTagFooter( HookRenderEvent $event ){
        $config = ShopimindQuery::create()->findOne();

        if ( empty( $config ) || !$config->getIsConnected() || !$config->getScriptTag() ) return ; 

        $country = Country::getShopLocation();
        
        $url = $this->getRequest()->getUri();
        $url = str_replace('https://', '//', $url);
        $url = str_replace('http://', '//', $url);

        $spmIdent = $config->getApiId();
        $productId = $this->getRequest()->get('product_id');
        $idCategory = "";
        $idManufacturer = "";

        $user = $this->getCustomer() ? [ 'customer_id' => $this->getCustomer()->getId() ] : null;
        $customerId = $this->getCustomer() ? $this->getCustomer()->getId() : null;
        $query = ( ( array ) $this->getRequest()->query );
        foreach ($query as $value) {
            $idCategory = array_key_exists( 'category_id', $value ) ? $value['category_id'] : null;
            $idManufacturer = array_key_exists( 'brand_id', $value ) ? $value['brand_id'] : null;
        }


        $cart = $this->getCart();
        $idCart = $cart->getId();

        $confirm_cart_to_order = '';
        if ( $idCart ) {
            $order = OrderQuery::create()->findOneByCartId( $idCart );
            $confirm_cart_to_order = !empty($order) ? $idCart.';'.$order->getId() : null;
        }

        $cartData = [
            'id_customer' => !empty( $idCart ) ? $cart->getCustomerId()  : null,
            'id_cart' => $idCart,
            'date_add' => !empty( $idCart ) ? $cart->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z')  : null,
            'date_upd' => !empty( $idCart ) ? $cart->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z')  : null,
            'amount' => !empty( $idCart ) ? $cart->getTaxedAmount( $country )  : null,
            'tax_rate' => !empty( $idCart ) ? $cart->getCurrency()->getCurrentTranslation()  : null,
            'currency' => !empty( $idCart ) ? $cart->getCurrency()->getCode()  : null,
            'voucher_used' => null, //Après commande
            'voucher_amount' => null, //Après commande
            'products' => !empty( $idCart ) ? self::getProducts( $cart->getId() ) : null,
        ];

        $currentUserInfos = [
            'url' => $url,
            'id_product' => $productId,
            'id_category' => $idCategory,
            'id_manufacturer' => $idManufacturer,
            'spm_ident' => $spmIdent,
            'user' => $user,
            'id_cart' => $idCart,
            'cart' => $cartData,
            'confirm_cart_to_order' => $confirm_cart_to_order,
        ];
        try {
            $language = substr($this->getRequest()->getSession()->getLang()->getLocale(), 0, 2) ?: 'fr';
        } catch (\Exception $e) {
            $language = 'fr';
        }
        $base_url = 'https://app-spm.com/app.js';
        $currentUserInfos = json_encode( $currentUserInfos );
        $src = $base_url . '?url=' . $url . '&id_product=' . $productId . '&id_customer=' . $customerId .  '&id_category=' . $idCategory . '&id_manufacturer=' . $idManufacturer . '&spm_ident=' . $spmIdent . '&language=' . $language;
        if ($idCart) {
            $src .= '&id_cart=' . $idCart;
            $src .= '&cart_hash=' . sha1(serialize($cartData));
        }
        $event->add(
            <<<HTML
            <script>
                var _spmq = $currentUserInfos;
                var _spm_id_combination = document.getElementById('pse-id') ? document.getElementById('pse-id').value : '';
                (function() {
                    document.addEventListener('DOMContentLoaded', function() {
                        var spm = document.createElement('script');
                        spm.type = 'text/javascript';
                        spm.defer = true;
                        spm.src = '$src&id_combination='+_spm_id_combination;
                        var head = document.head;
                        head.appendChild( spm );
                    });
                })();
            </script>
        HTML
        );
    }

    /**
     * Retrieves cart products.
     *
     * @param integer $cartId
     */
    public static function getProducts( int $cartId ){
        $response = [];

        $country = Country::getShopLocation();

        $cartItems = CartItemQuery::create()
                        ->filterByCartId( $cartId )
                        ->find();

        foreach ($cartItems as $cartItem) {
            $response[] = [
                'id_product' => $cartItem->getProductId(),
                'id_combination' => $cartItem->getProductSaleElementsId(),
                'id_manufacturer' => $cartItem->getProduct()->getBrandId(),
                'qty' => $cartItem->getQuantity(),
                'price' => $cartItem->getRealTaxedPrice( $country ),
                'price_without_tax' => Utils::formatNumber( $cartItem->getRealPrice() ),
            ];
        }

        return $response;
    }

}
