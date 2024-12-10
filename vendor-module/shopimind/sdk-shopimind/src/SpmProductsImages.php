<?php
namespace Shopimind\SdkShopimind;

class SpmProductsImages
{
    use Traits\Methods;

    /**
     * Image identifier.
     * @var string
     */
    public $image_id;

    /**
     * Product identifier.
     * @var string
     */
    public $product_id;

    /**
     * Product variation identifier associated with the image, null if no variation associated.
     * @var string|null
     */
    public $variation_id;

    /**
     * URL of the image.
     * @var string
     */
    public $url;

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
            'image_id' => $this->image_id,
            'variation_id' => $this->variation_id,
            'url' => $this->url,
            'is_default' => $this->is_default,
        ];

        $endpoint = 'products/' . $this->product_id . '/images';
        return $this->processSave( $endpoint, $data );
    }

    public static function bulkSave( $auth, $product_id, $data )
    {
        $endpoint = 'products/' . $product_id . '/images';
        return self::processBulkSave( $auth, $endpoint, $data );
    }

    public function update(){
        $data = [
            'image_id' => $this->image_id,
            'variation_id' => $this->variation_id,
            'url' => $this->url,
            'is_default' => $this->is_default,
        ];

        $updateData = [];
        foreach ($data as $key => $value) {
            if ( !empty( $value ) ) {
                $updateData[$key] = $value;
            }
        }

        $endpoint = 'products/' . $this->product_id . '/images';
        return $this->processUpdate( $endpoint, $updateData );
    }

    public static function bulkUpdate( $auth, $product_id, $data )
    {
        $endpoint = 'products/' . $product_id . '/images';
        return self::processBulkUpdate( $auth, $endpoint, $data );
    }

    public static function delete( $auth, $product_id, $id )
    {
        $endpoint = 'products/' . $product_id . '/images';
        return self::processDelete( $auth, $endpoint, $id );
    }

    public static function bulkDelete( $auth, $product_id, $data )
    {
        $postData = [ 'image_ids' => $data ];
        $endpoint = 'products/' . $product_id . '/images/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }
}
 