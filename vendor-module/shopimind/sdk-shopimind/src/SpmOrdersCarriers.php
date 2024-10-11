<?php
namespace Shopimind\SdkShopimind;

class SpmOrdersCarriers
{
    use Traits\Methods;

    /**
     * Carrier identifier.
     * @var string
     */
    public $carrier_id;

    /**
     * Shop identifier if multiple shops are available.
     * @var string|null
     */
    public $shop_id;

    /**
     * Carrier name.
     * @var string
     */
    public $name;

    /**
     * Indicates if the carrier is active.
     * @var bool
     */
    public $is_active;

    /**
     * Creation date of the carrier in ISO 8601 format.
     * @var string
     */
    public $created_at;

    /**
     * Update date of the carrier in ISO 8601 format.
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
            'carrier_id' => $this->carrier_id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->shop_id) {
            $data['shop_id'] = $this->shop_id;
        }

        return $this->processSave( 'orders-carriers', $data );
    }

    public static function bulkSave( $auth, $data )
    {
        return self::processBulkSave( $auth, 'orders-carriers', $data );
    }

    public function update(){
        $data = [
            'carrier_id' => $this->carrier_id,
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

        return $this->processUpdate( 'orders-carriers', $updateData );
    }

    public static function bulkUpdate( $auth, $data )
    {
        return self::processBulkUpdate( $auth, 'orders-carriers', $data );
    }

    public static function delete( $auth, $id )
    {
        return self::processDelete( $auth, 'orders-carriers', $id );
    }

    public static function bulkDelete( $auth, $data )
    {
        $postData = [ 'carrier_ids' => $data ];
        $endpoint = 'orders-carriers/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }
}
 