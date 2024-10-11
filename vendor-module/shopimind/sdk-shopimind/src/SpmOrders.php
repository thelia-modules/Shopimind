<?php
namespace Shopimind\SdkShopimind;

class OrderProductsDTO {
    /**
     * Product identifier.
     * @var string
     */
    public $id_product;

    /**
     * Product variation identifier, null if is a simple product.
     * @var string|null
     */
    public $id_product_variation;

    /**
     * Price paid for the product with 2 decimal places maximum.
     * @var float
     */
    public $price;

    /**
     * Price paid for the product without tax with 2 decimal places maximum.
     * @var float
     */
    public $price_without_tax;

    /**
     * Manufacturer identifier of the product, null if no manufacturer.
     * @var string|null
     */
    public $id_manufacturer;

    /**
     * Quantity of the product in the order.
     * @var int
     */
    public $quantity;
}

class SpmOrders
{
    use Traits\Methods;

    /**
     * Order identifier.
     * @var string
     */
    public $order_id;

    /**
     * Shop identifier if multiple shops are available.
     * @var string|null
     */
    public $shop_id;

    /**
     * Language associated with the order in ISO 639-1 format.
     * @var string
     */
    public $lang;

    /**
     * Reference of the order, null if no reference.
     * @var string|null
     */
    public $reference;

    /**
     * Carrier identifier of the order, null if no carrier.
     * @var string|null
     */
    public $carrier_id;

    /**
     * Status identifier of the order.
     * @var string
     */
    public $status_id;

    /**
     * Address delivery identifier.
     * @var string
     */
    public $address_delivery_id;

    /**
     * Address invoice identifier.
     * @var string
     */
    public $address_invoice_id;

    /**
     * Customer email address associated with the order.
     * @var string
     */
    public $email_customer;

    /**
     * Array of order products.
     * @var OrderProductsDTO[]
     */
    public $products;

    /**
     * Cart identifier associated with the order.
     * @var string
     */
    public $cart_id;

    /**
     * Update date of the cart in ISO 8601 format.
     * @var string
     */
    public $cart_updated_at;

    /**
     * Total price of the order with 2 decimal places maximum.
     * @var float
     */
    public $amount;

    /**
     * Total price of the order without tax with 2 decimal places maximum.
     * @var float
     */
    public $amount_without_tax;

    /**
     * Shipping costs of the order with 2 decimal places maximum.
     * @var float
     */
    public $shipping_costs;

    /**
     * Shipping costs of the order without tax with 2 decimal places maximum.
     * @var float
     */
    public $shipping_costs_without_tax;

    /**
     * Shipping number associated with the order, null if no number.
     * @var string|null
     */
    public $shipping_number;

    /**
     * The currency code associated with the order in ISO 4217 format.
     * @var string
     */
    public $currency;

    /**
     * Voucher code used in the order, null if no voucher.
     * @var string|null
     */
    public $voucher_used;

    /**
     * Voucher value applied to the order (amount or percentage).
     * @var string|null
     */
    public $voucher_value;

    /**
     * Indicates if the order is confirmed (paid).
     * @var bool
     */
    public $is_confirmed;

    /**
     * Creation date of the order in ISO 8601 format.
     * @var string
     */
    public $created_at;

    /**
     * Update date of the order in ISO 8601 format.
     * @var string
     */
    public $updated_at;

    protected $auth;

    public function __construct($auth) {
        $this->auth = $auth;
    }

    public function save()
    {
        $data = [
            'order_id' => $this->order_id,
            'lang' => $this->lang,
            'reference' => $this->reference,
            'carrier_id' => $this->carrier_id,
            'status_id' => $this->status_id,
            'address_delivery_id' => $this->address_delivery_id,
            'address_invoice_id' => $this->address_invoice_id,
            'email_customer' => $this->email_customer,
            'products' => $this->products,
            'cart_id' => $this->cart_id,
            'cart_updated_at' => $this->cart_updated_at,
            'amount' => $this->amount,
            'amount_without_tax' => $this->amount_without_tax,
            'shipping_costs' => $this->shipping_costs,
            'shipping_costs_without_tax' => $this->shipping_costs_without_tax,
            'shipping_number' => $this->shipping_number,
            'currency' => $this->currency,
            'voucher_used' => $this->voucher_used,
            'voucher_value' => $this->voucher_value,
            'is_confirmed' => $this->is_confirmed,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];


        if ($this->shop_id) {
            $data['shop_id'] = $this->shop_id;
        }

        return $this->processSave( 'orders', $data );
    }

    public static function bulkSave( $auth, $data )
    {
        return self::processBulkSave( $auth, 'orders', $data );
    }

    public function update(){
        $data = [
            'order_id' => $this->order_id,
            'shop_id' => $this->shop_id,
            'lang' => $this->lang,
            'reference' => $this->reference,
            'carrier_id' => $this->carrier_id,
            'status_id' => $this->status_id,
            'address_delivery_id' => $this->address_delivery_id,
            'address_invoice_id' => $this->address_invoice_id,
            'email_customer' => $this->email_customer,
            'products' => $this->products,
            'cart_id' => $this->cart_id,
            'cart_updated_at' => $this->cart_updated_at,
            'amount' => $this->amount,
            'amount_without_tax' => $this->amount_without_tax,
            'shipping_costs' => $this->shipping_costs,
            'shipping_costs_without_tax' => $this->shipping_costs_without_tax,
            'shipping_number' => $this->shipping_number,
            'currency' => $this->currency,
            'voucher_used' => $this->voucher_used,
            'voucher_value' => $this->voucher_value,
            'is_confirmed' => $this->is_confirmed,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        $updateData = [];
        foreach ($data as $key => $value) {
            if ( !empty( $value ) ) {
                $updateData[$key] = $value;
            }
        }

        return $this->processUpdate( 'orders', $updateData );
    }

    public static function bulkUpdate( $auth, $data )
    {
        return self::processBulkUpdate( $auth, 'orders', $data );
    }

    public static function delete( $auth, $id )
    {
        return self::processDelete( $auth, 'orders', $id );
    }

    public static function bulkDelete( $auth, $data )
    {
        $postData = [ 'order_ids' => $data ];
        $endpoint = 'orders/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }
}
 