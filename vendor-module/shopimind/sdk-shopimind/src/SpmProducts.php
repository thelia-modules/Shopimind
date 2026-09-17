<?php
namespace Shopimind\SdkShopimind;

class SpmProducts
{
    use Traits\Methods;

    /**
     * Product identifier.
     * @var string
     */
    public $product_id;

    /**
     * Shop identifier if multiple shops are available.
     * @var string|null
     */
    public $shop_id;

    /**
     * Language associated with the product in ISO 639-1 format.
     * @var string
     */
    public $lang;

    /**
     * Name of the product.
     * @var string
     */
    public $name;

    /**
     * Reference of the product, null if no reference.
     * @var string|null
     */
    public $reference;

    /**
     * EAN13 code of the product, null if no EAN13.
     * @var string|null
     */
    public $ean13;

    /**
     * Description of the product.
     * @var string
     */
    public $description;

    /**
     * Short description of the product, null if no short description.
     * @var string|null
     */
    public $description_short;

    /**
     * Link to the product.
     * @var string
     */
    public $link;

    /**
     * Array of category identifiers of the product.
     * @var string[]|null
     */
    public $category_ids;

    /**
     * Manufacturer identifier of the product, null if no manufacturer.
     * @var string|null
     */
    public $manufacturer_id;

    /**
     * The currency code of the product in ISO 4217 format.
     * @var string
     */
    public $currency;

    /**
     * Price of the product with 2 decimal places maximum.
     * @var float
     */
    public $price;

    /**
     * Discount price of the product with 2 decimal places maximum, null if no discount.
     * @var float|null
     */
    public $price_discount;

    /**
     * Quantity remaining of the product in stock.
     * @var int
     */
    public $quantity_remaining;

    /**
     * Indicates if the product is active.
     * @var bool
     */
    public $is_active;

    /**
     * Creation date of the product in ISO 8601 format.
     * @var string
     */
    public $created_at;

    /**
     * Update date of the product in ISO 8601 format.
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
            'product_id' => $this->product_id,
            'lang' => $this->lang,
            'name' => $this->name,
            'reference' => $this->reference,
            'ean13' => $this->ean13,
            'description' => $this->description,
            'description_short' => $this->description_short,
            'link' => $this->link,
            'category_ids' => $this->category_ids,
            'manufacturer_id' => $this->manufacturer_id,
            'currency' => $this->currency,
            'price' => $this->price,
            'price_discount' => $this->price_discount,
            'quantity_remaining' => $this->quantity_remaining,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->shop_id) {
            $data['shop_id'] = $this->shop_id;
        }

        return $this->processSave( 'products', $data );
    }

    public static function bulkSave( $auth, $data )
    {
        return self::processBulkSave( $auth, 'products', $data );
    }

    public function update(){
        $data = [
            'product_id' => $this->product_id,
            'shop_id' => $this->shop_id,
            'lang' => $this->lang,
            'name' => $this->name,
            'reference' => $this->reference,
            'ean13' => $this->ean13,
            'description' => $this->description,
            'description_short' => $this->description_short,
            'link' => $this->link,
            'category_ids' => $this->category_ids,
            'manufacturer_id' => $this->manufacturer_id,
            'currency' => $this->currency,
            'price' => $this->price,
            'price_discount' => $this->price_discount,
            'quantity_remaining' => $this->quantity_remaining,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        $updateData = [];
        foreach ($data as $key => $value) {
            if ( !empty( $value ) ) {
                $updateData[$key] = $value;
            }
        }

        return $this->processUpdate( 'products', $updateData );
    }

    public static function bulkUpdate( $auth, $data )
    {
        return self::processBulkUpdate( $auth, 'products', $data );
    }

    public static function delete( $auth, $id )
    {
        return self::processDelete( $auth, 'products', $id );
    }

    public static function bulkDelete( $auth, $data )
    {
        $postData = [ 'product_ids' => $data ];
        $endpoint = 'products/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }
}
