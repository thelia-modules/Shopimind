<?php
namespace Shopimind\SdkShopimind;

class SpmOrdersStatus
{
    use Traits\Methods;
    

    /**
     * Order status identifier
     * @var string
     */
    public $status_id;

    /**
     * Shop identifier if multiple shops are available. (optional)
     * @var string|null
     */
    public $shop_id;

    /**
     * Language associated with the order status in ISO 639-1 format
     * @var string
     */
    public $lang;

    /**
     * Order status name
     * @var string
     */
    public $name;

    /**
     * Indicates if the order status is deleted
     * @var bool
     */
    public $is_deleted;

    /**
     * Creation date of the order status in ISO 8601 format
     * @var string
     */
    public $created_at;

    /**
     * Update date of the order status in ISO 8601 format
     * @var string
     */
    public $updated_at;

    /**
     * Client for authentication
     * @var GuzzleClient
     */
    protected $auth;

    public function __construct($auth) {
        $this->auth = $auth;
    }

    public function save()
    {
        $data = [
            'status_id' => $this->status_id,
            'lang' => $this->lang,
            'name' => $this->name,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->shop_id) {
            $data['shop_id'] = $this->shop_id;
        }

        return $this->processSave( 'orders-statuses', $data );
    }


    public static function bulkSave( $auth, $data )
    {
        return self::processBulkSave( $auth, 'orders-statuses', $data );
    }

    public function update(){
        $data = [
            'status_id' => $this->status_id,
            'lang' => $this->lang,
            'name' => $this->name,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        $updateData = [];
        foreach ($data as $key => $value) {
            if ( !empty( $value ) ) {
                $updateData[$key] = $value;
            }
        }

        return $this->processUpdate( 'orders-statuses', $updateData);
    }

    /**
     * @param $auth GuzzleClient
     * @return mixed
     */
    public static function bulkUpdate( $auth, $data )
    {
        return self::processBulkUpdate( $auth, 'orders-statuses', $data );
    }

    /**
     * @param $auth GuzzleClient
     * @param $id string
     * @return mixed
     */
    public static function delete( $auth, $id )
    {
        return self::processDelete( $auth, 'orders-statuses', $id );
    }

    /**
     * @param $auth GuzzleClient
     * @param string[] $data
     * @return mixed
     */
    public static function bulkDelete( $auth, $data )
    {
        $postData = [ 'status_ids' => $data ];
        $endpoint = 'orders-statuses/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }
}
