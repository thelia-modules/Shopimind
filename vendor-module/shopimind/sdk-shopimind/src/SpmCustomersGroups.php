<?php
namespace Shopimind\SdkShopimind;

class SpmCustomersGroups
{
    use Traits\Methods;

    /**
     * Customer group identifier
     * @var string
     */
    public $group_id;

    /**
     * Shop identifier if multiple shops are available, null if not provided
     * @var string|null
     */
    public $shop_id;

    /**
     * Language associated with the customer group in ISO 639-1 format
     * @var string
     */
    public $lang;

    /**
     * Customer group name
     * @var string
     */
    public $name;

    /**
     * Creation date of the customer group in ISO 8601 format
     * @var string
     */
    public $created_at;

    /**
     * Update date of the customer group in ISO 8601 format
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
            'group_id' => $this->group_id,
            'lang' => $this->lang,
            'name' => $this->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];

        if ($this->shop_id) {
            $data['shop_id'] = $this->shop_id;
        }

        return $this->processSave( 'customers-groups', $data );
    }

    public static function bulkSave( $auth, $data )
    {
        return self::processBulkSave( $auth, 'customers-groups', $data );
    }

    public function update(){
        $data = [
            'group_id' => $this->group_id,
            'shop_id' => $this->shop_id,
            'lang' => $this->lang,
            'name' => $this->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];

        $updateData = [];
        foreach ($data as $key => $value) {
            if ( !empty( $value ) ) {
                $updateData[$key] = $value;
            }
        }

        return $this->processUpdate( 'customers-groups', $updateData );
    }

    public static function bulkUpdate( $auth, $data )
    {
        return self::processBulkUpdate( $auth, 'customers-groups', $data );
    }

    public static function delete( $auth, $id )
    {
        return self::processDelete( $auth, 'customers-groups', $id );
    }

    public static function bulkDelete( $auth, $data )
    {
        $postData = [ 'group_ids' => $data ];
        $endpoint = 'customers-groups/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }
}
