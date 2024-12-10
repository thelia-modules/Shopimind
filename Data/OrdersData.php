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

namespace Shopimind\Data;

use Shopimind\lib\Utils;
use Thelia\Model\Base\OrderStatusQuery;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Country;
use Thelia\Model\Order;
use Thelia\Model\OrderCouponQuery;

class OrdersData
{
    /**
     * Formats the order data to match the Shopimind format.
     */
    public function formatOrder(Order $order): array
    {
        $country = Country::getShopLocation();
        $orderCoupon = OrderCouponQuery::create()->findOneByOrderId($order->getId());
        $cart = CartQuery::create()->findOneById($order->getCartId());
        $paidStatus = OrderStatusQuery::create()->findOneByCode('paid');
        $paidStatusId = (!empty($paidStatus)) ? $paidStatus->getId() : null;
        $isConfirmed = false;
        if ($paidStatusId == $order->getOrderStatus()->getId()) {
            $isConfirmed = true;
        }

        $customerOrder = $order->getCustomer();
        $customer = [
            'customer_id' => '',
            'email' => '',
            'created_at' => null,
        ];

        if (!empty($customerOrder)) {
            $customer = [
                'customer_id' => (string) $customerOrder->getId(),
                'email' => $customerOrder->getEmail(),
                'created_at' => $customerOrder->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            ];
        }

        return [
            'order_id' => (string) $order->getId(),
            'lang' => $order->getLang()->getCode(),
            'reference' => $order->getRef(),
            'carrier_id' => null,
            'status_id' => $order->getOrderStatus()->getCode(),
            'address_delivery_id' => (string) $order->getDeliveryOrderAddressId(),
            'address_invoice_id' => (string) $order->getInvoiceOrderAddressId(),
            'customer' => $customer,
            'products' => self::getProducts($order->getCartId()),
            'cart_id' => (string) $order->getCartId(),
            'cart_updated_at' => !empty($cart) ? $cart->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z') : '',
            'amount' => !empty($cart) ? $cart->getTaxedAmount($country) : 0,
            'amount_without_tax' => !empty($cart) ? $cart->getTotalAmount() : '',
            'shipping_costs' => Utils::formatNumber($order->getPostage()),
            'shipping_costs_without_tax' => Utils::formatNumber($order->getPostageTax()),
            'shipping_number' => null,
            'currency' => $order->getCurrency()->getCode(),
            'voucher_used' => !empty($orderCoupon) ? $orderCoupon->getCode() : null,
            'voucher_value' => !empty($orderCoupon) ? (string) (Utils::formatNumber($orderCoupon->getAmount())) : null,
            'is_confirmed' => $isConfirmed,
            'created_at' => $order->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $order->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    /**
     * Retrieves order products.
     */
    public function getProducts(int $cartId): array
    {
        $response = [];

        $country = Country::getShopLocation();

        $cartItems = CartItemQuery::create()->filterByCartId($cartId)->find();

        foreach ($cartItems as $cartItem) {
            $response[] = [
                'product_id' => $cartItem->getProductId(),
                'product_variation_id' => $cartItem->getProductSaleElementsId(),
                'price' => Utils::formatNumber($cartItem->getRealTaxedPrice($country)),
                'price_without_tax' => Utils::formatNumber($cartItem->getRealPrice()),
                'manufacturer_id' => !empty($cartItem->getProduct()->getBrandId()) ? (string) ($cartItem->getProduct()->getBrandId()) : null,
                'quantity' => $cartItem->getQuantity(),
            ];
        }

        return $response;
    }
}
