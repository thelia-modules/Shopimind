<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopimind\Hook;

use Shopimind\lib\Utils;
use Shopimind\Model\Base\ShopimindQuery;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Country;
use Thelia\Model\OrderQuery;

class ShopimindHook extends BaseHook
{
    /**
     * Add script tag.
     */
    public function addScriptTagFooter(HookRenderEvent $event): void
    {
        $config = ShopimindQuery::create()->findOne();

        if (empty($config) || !$config->getIsConnected() || !$config->getScriptTag()) {
            return;
        }

        $country = Country::getShopLocation();

        $url = $this->getRequest()->getUri();
        $url = str_replace('https://', '//', $url);
        $url = str_replace('http://', '//', $url);

        $spmIdent = $config->getApiId();
        $productId = $this->getRequest()->get('product_id');
        $idCategory = '';
        $idManufacturer = '';

        $user = $this->getCustomer() ? ['customer_id' => $this->getCustomer()->getId()] : null;
        $customerId = $this->getCustomer() ? $this->getCustomer()->getId() : null;
        $query = ((array) $this->getRequest()->query);
        foreach ($query as $value) {
            $idCategory = \array_key_exists('category_id', $value) ? $value['category_id'] : null;
            $idManufacturer = \array_key_exists('brand_id', $value) ? $value['brand_id'] : null;
        }

        $cart = $this->getCart();
        $idCart = $cart->getId();

        $confirm_cart_to_order = '';
        if ($idCart) {
            $order = OrderQuery::create()->findOneByCartId($idCart);
            $confirm_cart_to_order = !empty($order) ? $idCart.';'.$order->getId() : null;
        }

        $currentUserInfos = [
            'url' => $url,
            'id_product' => $productId,
            'id_category' => $idCategory,
            'id_manufacturer' => $idManufacturer,
            'spm_ident' => $spmIdent,
            'user' => $user,
            'id_cart' => $idCart,
            'cart' => [
                'id_customer' => !empty($idCart) ? $cart->getCustomerId() : null,
                'id_cart' => $idCart,
                'date_add' => !empty($idCart) ? $cart->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z') : null,
                'date_upd' => !empty($idCart) ? $cart->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z') : null,
                'amount' => !empty($idCart) ? $cart->getTaxedAmount($country) : null,
                'tax_rate' => !empty($idCart) ? $cart->getCurrency()->getCurrentTranslation() : null,
                'currency' => !empty($idCart) ? $cart->getCurrency()->getCode() : null,
                'voucher_used' => null, // Après commande
                'voucher_amount' => null, // Après commande
                'products' => !empty($idCart) ? self::getProducts($cart->getId()) : null,
            ],
            'confirm_cart_to_order' => $confirm_cart_to_order,
        ];

        $base_url = 'https://app-spm.com/app.js';
        $currentUserInfos = json_encode($currentUserInfos);
        $src = $base_url.'?url='.$url.'&id_product='.$productId.'&id_customer='.$customerId.'&id_category='.$idCategory.'&id_manufacturer='.$idManufacturer.'&spm_ident='.$spmIdent;
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
     */
    public static function getProducts(int $cartId): array
    {
        $response = [];

        $country = Country::getShopLocation();

        $cartItems = CartItemQuery::create()
                        ->filterByCartId($cartId)
                        ->find();

        foreach ($cartItems as $cartItem) {
            $response[] = [
                'id_product' => $cartItem->getProductId(),
                'id_combination' => $cartItem->getProductSaleElementsId(),
                'id_manufacturer' => $cartItem->getProduct()->getBrandId(),
                'qty' => $cartItem->getQuantity(),
                'price' => $cartItem->getRealTaxedPrice($country),
                'price_without_tax' => Utils::formatNumber($cartItem->getRealPrice()),
            ];
        }

        return $response;
    }
}
