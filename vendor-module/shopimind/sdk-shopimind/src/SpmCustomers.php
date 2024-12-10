<?php
namespace Shopimind\SdkShopimind;

class SpmCustomers
{
    use Traits\Methods;

    /**
     * Customer identifier
     * @var string
     */
    public $id_customer;

    /**
     * Shop identifier if multiple shops are available.
     * @var string|null
     */
    public $shop_id;

    /**
     * Customer email address
     * @var string
     */
    public $email;

    /**
     * Customer phone number, null if not provided.
     * @var string|null
     */
    public $phone_number;

    /**
     * Customer first name
     * @var string
     */
    public $first_name;

    /**
     * Customer last name
     * @var string
     */
    public $last_name;

    /**
     * Customer's date of birth in YYYY-MM-DD format, null if not provided.
     * @var string|null
     */
    public $birth_date;

    /**
     * Indicates if the customer has opted in for marketing.
     * @var bool
     */
    public $is_opt_in;

    /**
     * Indicates if the customer has subscribed to the newsletter.
     * @var bool
     */
    public $is_newsletter_subscribed;

    /**
     * Language code of the customer in ISO 639-1 format.
     * @var string
     */
    public $lang;

    /**
     * Array of groups identifiers associated with the customer, null if not provided.
     * @var string[]|null
     */
    public $ids_groups;

    /**
     * Indicates if the customer is active.
     * @var bool
     */
    public $is_active;

    /**
     * Creation date of the customer in ISO 8601 format.
     * @var string
     */
    public $created_at;

    /**
     * Update date of the customer in ISO 8601 format.
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
            'customer_id' => $this->id_customer,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'birth_date' => $this->birth_date,
            'is_opt_in' => $this->is_opt_in,
            'is_newsletter_subscribed' => $this->is_newsletter_subscribed,
            'lang' => $this->lang,
            'ids_groups' => $this->ids_groups,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];

        if ($this->shop_id) {
            $data['shop_id'] = $this->shop_id;
        }

        return $this->processSave( 'customers', $data );
    }

    public static function bulkSave( $auth, $data )
    {
        return self::processBulkSave( $auth, 'customers', $data );
    }

    public function update()
    {
        $data = [
            'customer_id' => $this->id_customer,
            'shop_id' => $this->shop_id,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'birth_date' => $this->birth_date,
            'is_opt_in' => $this->is_opt_in,
            'is_newsletter_subscribed' => $this->is_newsletter_subscribed,
            'lang' => $this->lang,
            'ids_groups' => $this->ids_groups,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];

        $updateData = [];
        foreach ($data as $key => $value) {
            if ( !empty( $value ) ) {
                $updateData[$key] = $value;
            }
        }

        return $this->processUpdate( 'customers', $updateData );
    }

    public static function bulkUpdate( $auth, $data )
    {
        return self::processBulkUpdate( $auth, 'customers', $data );
    }

    public static function delete( $auth, $id )
    {
        return self::processDelete( $auth, 'customers', $id );
    }

    public static function bulkDelete( $auth, $data )
    {
        $postData = [ 'customer_ids' => $data ];
        $endpoint = 'customers/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }

}
