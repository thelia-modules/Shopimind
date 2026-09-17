<?php
namespace Shopimind\SdkShopimind;

class SpmProductsManufacturers
{
    use Traits\Methods;

    /**
     * Manufacturer identifier.
     * @var string
     */
    public $manufacturer_id;

    /**
     * Shop identifier if multiple shops are available.
     * @var string|null
     */
    public $shop_id;

    /**
     * Name of the manufacturer.
     * @var string
     */
    public $name;

    /**
     * Indicates if the manufacturer is active.
     * @var bool
     */
    public $is_active;

    /**
     * Creation date of the manufacturer in ISO 8601 format.
     * @var string
     */
    public $created_at;

    /**
     * Update date of the manufacturer in ISO 8601 format.
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
            'manufacturer_id' => $this->manufacturer_id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->shop_id) {
            $data['shop_id'] = $this->shop_id;
        }

        return $this->processSave( 'products-manufacturers', $data );
    }

    public static function bulkSave( $auth, $data )
    {
        return self::processBulkSave( $auth, 'products-manufacturers', $data );
    }

    public function update(){
        $data = [
            'manufacturer_id' => $this->manufacturer_id,
            'shop_id' => $this->shop_id,
            'name' => $this->name,
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

        return $this->processUpdate( 'products-manufacturers', $updateData );
    }

    public static function bulkUpdate( $auth, $data )
    {
        return self::processBulkUpdate( $auth, 'products-manufacturers', $data );
    }

    public static function delete( $auth, $id )
    {
        return self::processDelete( $auth, 'products-manufacturers', $id );
    }

    public static function bulkDelete( $auth, $data )
    {
        $postData = [ 'manufacturer_ids' => $data ];
        $endpoint = 'products-manufacturers/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }
}
