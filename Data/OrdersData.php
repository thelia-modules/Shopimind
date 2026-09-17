<?php

namespace Shopimind\Data;

use Thelia\Model\CartItemQuery;
use Shopimind\lib\Utils;
use Thelia\Model\OrderCouponQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\Order;
use Shopimind\Model\Base\ShopimindQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\ProductSaleElementsQuery;

class OrdersData
{
    /** Longueur maximale de la liste des codes promo acceptée par ShopiMind. */
    const VOUCHER_CODES_MAX_LENGTH = 256;

    /**
     * Formats the order data to match the ShopiMind format.
     *
     * @param Order $order
     * @return array
     */
    public static function formatOrder( Order $order ): array
    {
        $voucherCodes = self::getVoucherCodes( $order );
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
                'created_at' => $customerOrder->getCreatedAt()->format('Y-m-d\TH:i:s.uP'),
            ];
        }

        $tax = 0;
        $totalWithTax = $order->getTotalAmount( $tax );
        $totalWithoutTax = $totalWithTax - $tax;

        $data = [
            'order_id' => strval( $order->getId() ),
            'lang' => $order->getLang()->getCode(),
            'reference' => $order->getRef(),
            // Transporteur = module de livraison de la commande, synchronisé par le type orders_carriers :
            // ShopiMind exige une chaîne et rattache la commande au transporteur déjà synchronisé.
            'carrier_id' => self::getCarrierId( $order ),
            'status_id' => strval( $order->getOrderStatus()->getId() ),
            'address_delivery_id' => strval( $order->getDeliveryOrderAddressId() ),
            'address_invoice_id' => strval( $order->getInvoiceOrderAddressId() ),
            'customer' => $customer,
            'products' => self::getProducts( $order->getId() ),
            'cart_id' => strval( $order->getCartId() ),
            // Date ISO 8601 exigée par ShopiMind : la chaîne vide (panier purgé) faisait rejeter la commande.
            'cart_updated_at' => ( !empty($cart) && $cart->getUpdatedAt() ) ? $cart->getUpdatedAt()->format('Y-m-d\TH:i:s.uP') : $order->getCreatedAt()->format('Y-m-d\TH:i:s.uP'),
            'amount' => Utils::formatNumber( $totalWithTax ?? 0 ),
            'amount_without_tax' => Utils::formatNumber( $totalWithoutTax ?? 0 ),
            'shipping_costs' => Utils::formatNumber( $order->getPostage() ),
            'shipping_costs_without_tax' => Utils::formatNumber( $order->getPostage() - $order->getPostageTax() ),
            'shipping_number' => self::getShippingNumber( $order ),
            'currency' => $order->getCurrency()->getCode(),
            // Tous les codes promo de la commande, ceux de ShopiMind en premier.
            'voucher_used' => $voucherCodes,
            // Remise totale TTC de la commande, tous codes confondus.
            'voucher_value' => self::getVoucherValue( $order, $voucherCodes ),
            'is_confirmed' => $isConfirmed,
            'created_at' => $order->getCreatedAt()->format('Y-m-d\TH:i:s.uP'),
            'updated_at' => $order->getUpdatedAt()->format('Y-m-d\TH:i:s.uP')
        ];

        $data['source_label'] = Utils::getSourceLabel();
        return $data;
    }

    /**
     * Transporteur de la commande : identifiant du module de livraison (order.delivery_module_id),
     * en chaîne comme l'exige ShopiMind, null s'il manque.
     *
     * @param Order $order
     * @return string|null
     */
    public static function getCarrierId( Order $order ): ?string
    {
        $deliveryModuleId = (int) $order->getDeliveryModuleId();

        return $deliveryModuleId > 0 ? (string) $deliveryModuleId : null;
    }

    /**
     * Numéro de suivi : référence de livraison
     * saisie en back-office sur la fiche commande (order.delivery_ref, texte libre), null si vide. Sa mise à
     * jour déclenche OrderEvent::POST_UPDATE, donc un nouvel envoi de la commande en temps réel.
     *
     * @param Order $order
     * @return string|null
     */
    public static function getShippingNumber( Order $order ): ?string
    {
        $deliveryRef = trim( (string) $order->getDeliveryRef() );

        return $deliveryRef !== '' ? $deliveryRef : null;
    }

    /**
     * Codes promo de la commande séparés par une virgule : Thelia enregistre une ligne order_coupon par code utilisé,
     * plusieurs quand les codes sont cumulables. Les codes commençant par SPM- ou SPM_ passent en premier : ShopiMind
     * les cherche en tête de liste, et la limite de longueur ne les écarte jamais. Les autres suivent dans l'ordre
     * d'enregistrement.
     * null si la commande n'a aucun code.
     *
     * @param Order $order
     * @return string|null
     */
    public static function getVoucherCodes( Order $order ): ?string
    {
        $orderCoupons = [];
        foreach ( OrderCouponQuery::create()->findByOrderId( $order->getId() ) as $orderCoupon ) {
            $orderCoupons[] = $orderCoupon;
        }

        // Ordre d'enregistrement, quel que soit l'ordre renvoyé par la base.
        usort( $orderCoupons, function ( $a, $b ) {
            return (int) $a->getId() <=> (int) $b->getId();
        } );

        $spmCodes = [];
        $otherCodes = [];
        foreach ( $orderCoupons as $orderCoupon ) {
            $code = trim( (string) $orderCoupon->getCode() );
            if ( $code === '' || in_array( $code, $spmCodes, true ) || in_array( $code, $otherCodes, true ) ) {
                continue;
            }

            if ( preg_match( '/^SPM[-_]/i', $code ) ) {
                $spmCodes[] = $code;
            } else {
                $otherCodes[] = $code;
            }
        }

        $voucherCodes = '';
        $droppedCodes = [];
        foreach ( array_merge( $spmCodes, $otherCodes ) as $code ) {
            $candidate = $voucherCodes === '' ? $code : $voucherCodes . ',' . $code;

            // Au-delà, la liste ne tiendrait pas dans le champ de ShopiMind : le code est écarté.
            if ( strlen( $candidate ) > self::VOUCHER_CODES_MAX_LENGTH ) {
                $droppedCodes[] = $code;
                continue;
            }

            $voucherCodes = $candidate;
        }

        if ( !empty( $droppedCodes ) ) {
            Utils::logWarn( 'Sync', 'Order', 'discount codes dropped, the list is too long', [ 'codes' => $droppedCodes ], $order->getId() );
        }

        return $voucherCodes !== '' ? $voucherCodes : null;
    }

    /**
     * Remise totale TTC de la commande (order.discount : somme des codes, plafonnée au total du panier et arrondie
     * par Thelia). La livraison offerte n'y figure pas. null sans code promo.
     *
     * @param Order       $order
     * @param string|null $voucherCodes résultat de getVoucherCodes()
     * @return string|null
     */
    public static function getVoucherValue( Order $order, ?string $voucherCodes ): ?string
    {
        if ( null === $voucherCodes ) {
            return null;
        }

        return strval( Utils::formatNumber( max( 0.0, (float) $order->getDiscount() ) ) );
    }

    /**
     * Retrieves order products.
     *
     * @param integer $orderId
     * @return array
     */
    public static function getProducts( int $orderId ): array
    {
        $response = [];

        $orderProducts = OrderProductQuery::create()->filterByOrderId( $orderId )->find();

        foreach ( $orderProducts as $orderProduct ) {
            $productSaleElementsId = (int) $orderProduct->getProductSaleElementsId();
            $product = null;

            if ( $productSaleElementsId > 0 ) {
                $productSaleElement = ProductSaleElementsQuery::create()->findOneById( $productSaleElementsId );
                if ( $productSaleElement ) {
                    $product = $productSaleElement->getProduct();
                }
            }

            // Déclinaison supprimée depuis la commande : produit retrouvé par sa référence,
            // figée dans la ligne de commande.
            if ( empty( $product ) && $orderProduct->getProductRef() ) {
                $product = ProductQuery::create()->findOneByRef( $orderProduct->getProductRef() );
            }

            // product_id est un entier obligatoire pour ShopiMind : une ligne sans produit faisait rejeter
            // toute la commande. Elle est écartée, le montant total de la commande ne change pas.
            if ( empty( $product ) ) {
                Utils::logWarn( 'Sync', 'Order', 'ligne de commande sans produit retrouvé, ignorée', [ 'order_product_id' => $orderProduct->getId(), 'product_ref' => $orderProduct->getProductRef() ], $orderId );
                continue;
            }

            // Même calcul que Order::getTotalAmount() de Thelia 2.5 : prix unitaire HT arrondi à
            // 2 décimales (prix promo si la ligne était en promotion), plus la somme des taxes
            // arrondies de la ligne (table order_product_tax). OrderProduct n'a pas de getTax() :
            // la taxe valait 0 et le prix « TTC » envoyé était le prix HT hors promotion.
            $isInPromo = (int) $orderProduct->getWasInPromo() === 1;
            $priceWithoutTax = round( (float) ( $isInPromo ? $orderProduct->getPromoPrice() : $orderProduct->getPrice() ), 2 );

            $tax = 0.0;
            $orderProductTaxes = OrderProductTaxQuery::create()->filterByOrderProductId( $orderProduct->getId() )->find();
            foreach ( $orderProductTaxes as $orderProductTax ) {
                $tax += round( (float) ( $isInPromo ? $orderProductTax->getPromoAmount() : $orderProductTax->getAmount() ), 2 );
            }

            $response[] = [
                'product_id' => intval( $product->getId() ),
                'product_variation_id' => $productSaleElementsId > 0 ? $productSaleElementsId : null,
                'price' => Utils::formatNumber( $priceWithoutTax + $tax ),
                'price_without_tax' => Utils::formatNumber( $priceWithoutTax ),
                'manufacturer_id' => !empty( $product->getBrandId() ) ? strval( $product->getBrandId() ) : null,
                // Entier exigé par ShopiMind : Thelia stocke la quantité en FLOAT.
                'quantity' => (int) $orderProduct->getQuantity(),
            ];
        }

        return $response;
    }
}
