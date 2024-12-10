<?php
namespace Shopimind\SdkShopimind;

class SpmProductsVariations
{
    use Traits\Methods;

    /**
     * Product variation identifier.
     * @var string
     */
    public $variation_id;

    /**
     * Product identifier.
     * @var string
     */
    public $product_id;

    /**
     * Name of the product variation.
     * @var string
     */
    public $name;

    /**
     * Reference of the product variation, null if no reference.
     * @var string|null
     */
    public $reference;

    /**
     * EAN13 code of the product variation, null if no EAN13.
     * @var string|null
     */
    public $ean13;

    /**
     * Link to the product variation.
     * @var string
     */
    public $link;

    /**
     * Price of the product variation with 2 decimal places maximum.
     * @var float
     */
    public $price;

    /**
     * Discount price of the product variation with 2 decimal places maximum, null if no discount.
     * @var float|null
     */
    public $price_discount;

    /**
     * Quantity remaining of the product variation in stock.
     * @var int
     */
    public $quantity_remaining;

    /**
     * Indicates if the image is the default image of the product/variation.
     * @var bool
     */
    public $is_default;



    protected $auth;

    public function __construct( $auth, $product_id ) {
        $this->auth = $auth;
        $this->product_id = $product_id;
    }

    public function save()
    {
        $data = [
            'variation_id' => $this->variation_id,
            'name' => $this->name,
            'reference' => $this->reference,
            'ean13' => $this->ean13,
            'link' => $this->link,
            'price' => $this->price,
            'price_discount' => $this->price_discount,
            'quantity_remaining' => $this->quantity_remaining,
            'is_default' => $this->is_default,
        ];

        $endpoint = 'products/' . $this->product_id . '/variations';
        return $this->processSave( $endpoint, $data );
    }

    public static function bulkSave( $auth, $product_id, $data )
    {
        $endpoint = 'products/' . $product_id . '/variations';
        return self::processBulkSave( $auth, $endpoint, $data );
    }

    public function update(){
        $data = [
            'variation_id' => $this->variation_id,
            'name' => $this->name,
            'reference' => $this->reference,
            'ean13' => $this->ean13,
            'link' => $this->link,
            'price' => $this->price,
            'price_discount' => $this->price_discount,
            'quantity_remaining' => $this->quantity_remaining,
            'is_default' => $this->is_default,
        ];

        $updateData = [];
        foreach ($data as $key => $value) {
            if ( !empty( $value ) ) {
                $updateData[$key] = $value;
            }
        }

        $endpoint = 'products/' . $this->product_id . '/variations';
        return $this->processUpdate( $endpoint, $data );
    }

    public static function bulkUpdate( $auth, $product_id, $data )
    {
        $endpoint = 'products/' . $product_id . '/variations';
        return self::processBulkUpdate( $auth, $endpoint, $data );
    }

    public static function delete( $auth, $product_id, $id )
    {
        $endpoint = 'products/' . $product_id . '/variations';
        return self::processDelete( $auth, $endpoint, $id );
    }

    public static function bulkDelete( $auth, $product_id, $data )
    {
        $postData = [ 'variation_ids' => $data ];
        $endpoint = 'products/' . $product_id . '/variations/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }
}
