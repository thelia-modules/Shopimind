<?php

namespace Shopimind\Data;

use Thelia\Model\CartItemQuery;
use Thelia\Model\Country;
use Shopimind\lib\Utils;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Order;
use Shopimind\Model\Base\ShopimindQuery;

class OrdersData
{
    /**
     * Formats the order data to match the Shopimind format.
     *
     * @param Order $order
     * @return array
     */
    public static function formatOrder( Order $order ): array
    {
        $country = Country::getShopLocation();
        $orderCoupon = OrderCouponQuery::create()->findOneByOrderId( $order->getId() );
        $cart = CartQuery::create()->findOneById( $order->getCartId() );
        $config = ShopimindQuery::create()->findOne();
        $confirmedStatuses = !empty( $config ) && !empty( $config->getConfirmedStatuses() ) 
            ? json_decode( $config->getConfirmedStatuses(), true ) 
            : [];
        $isConfirmed = false;
        if ( in_array( $order->getOrderStatus()->getId(), $confirmedStatuses ) ) {
            $isConfirmed = true;
        }

        $customerOrder = $order->getCustomer();
        $customer = [
            'customer_id' => '',
            'email' => '',
            'created_at' => null,
        ];

        if ( !empty( $customerOrder ) ) {
            $customer = [
                'customer_id' => strval( $customerOrder->getId() ),
                'email' => $customerOrder->getEmail(),
                'created_at' => $customerOrder->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            ];
        }

        return [
            'order_id' => strval( $order->getId() ),
            'lang' => $order->getLang()->getCode(),
            'reference' => $order->getRef(),
            'carrier_id' => null,
            'status_id' => strval( $order->getOrderStatus()->getId() ),
            'address_delivery_id' => strval( $order->getDeliveryOrderAddressId() ),
            'address_invoice_id' => strval( $order->getInvoiceOrderAddressId() ),
            'customer' => $customer,
            'products' => self::getProducts( $order->getCartId() ),
            'cart_id' => strval( $order->getCartId() ),
            'cart_updated_at' => !empty($cart) ? $cart->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z') : '',
            'amount' => !empty($cart) ? $cart->getTaxedAmount( $country ) : 0,
            'amount_without_tax' => !empty($cart) ? $cart->getTotalAmount()  : '',
            'shipping_costs' => Utils::formatNumber( $order->getPostage() ),
            'shipping_costs_without_tax' => Utils::formatNumber( $order->getPostageTax() ),
            'shipping_number' => null,
            'currency' => $order->getCurrency()->getCode(),
            'voucher_used' => !empty($orderCoupon) ? $orderCoupon->getCode() : null,
            'voucher_value' => !empty($orderCoupon) ? strval(Utils::formatNumber( $orderCoupon->getAmount() )) : null,
            'is_confirmed' => $isConfirmed,
            'created_at' => $order->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'updated_at' => $order->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z')
        ];
    }

    /**
     * Retrieves order products.
     *
     * @param integer $cartId
     * @return array
     */
    public static function getProducts( int $cartId ): array
    {
        $response = [];

        $country = Country::getShopLocation();

        $cartItems = CartItemQuery::create()->filterByCartId( $cartId )->find();
        
        foreach ( $cartItems as $cartItem ) {
            $response[] = [
                'product_id' => intval( $cartItem->getProductId() ),
                'product_variation_id' => intval( $cartItem->getProductSaleElementsId() ),
                'price' => Utils::formatNumber( $cartItem->getRealTaxedPrice( $country ) ),
                'price_without_tax' => Utils::formatNumber( $cartItem->getRealPrice() ),
                'manufacturer_id' => !empty( $cartItem->getProduct()->getBrandId() ) ? strval( $cartItem->getProduct()->getBrandId() ) : null ,
                'quantity' => $cartItem->getQuantity(),
            ];
        }

        return $response;
    }
}
