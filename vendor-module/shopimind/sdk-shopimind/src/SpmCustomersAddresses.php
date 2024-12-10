<?php
namespace Shopimind\SdkShopimind;

class SpmCustomersAddresses
{
    use Traits\Methods;

    /**
     * Customer address identifier
     * @var string
     */
    public $address_id;

    /**
     * Customer identifier
     * @var string
     */
    public $customer_id;

    /**
     * First name associated with the address
     * @var string
     */
    public $first_name;

    /**
     * Last name associated with the address
     * @var string
     */
    public $last_name;

    /**
     * Primary phone number associated with the address, null if not provided
     * @var string|null
     */
    public $primary_phone;

    /**
     * Secondary phone number associated with the address, null if not provided
     * @var string|null
     */
    public $secondary_phone;

    /**
     * Company name associated with the address, null if not provided
     * @var string|null
     */
    public $company;

    /**
     * Address line 1
     * @var string
     */
    public $address_line_1;

    /**
     * Address line 2, null if not provided
     * @var string|null
     */
    public $address_line_2;

    /**
     * The postal code of the address
     * @var string
     */
    public $postal_code;

    /**
     * City of the address
     * @var string
     */
    public $city;

    /**
     * Country of the address
     * @var string
     */
    public $country;

    /**
     * Indicates if the address is active
     * @var boolean
     */
    public $is_active;

    protected $auth;

    public function __construct( $auth, $customerId ) {
        $this->auth = $auth;
        $this->customer_id = $customerId;
    }

    public function save()
    {
        $data = [
            'address_id' => $this->address_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'primary_phone' => $this->primary_phone,
            'secondary_phone' => $this->secondary_phone,
            'company' => $this->company,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'country' => $this->country,
            'is_active' => $this->is_active
        ];

        $endpoint = 'customers/' . $this->customer_id . '/addresses';
        return $this->processSave( $endpoint, $data );
    }

    public static function bulkSave( $auth, $customerId, $data )
    {
        $endpoint = 'customers/' . $customerId . '/addresses';
        return self::processBulkSave( $auth, $endpoint, $data );
    }

    public function update()
    {
        $data = [
            'address_id' => $this->address_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'primary_phone' => $this->primary_phone,
            'secondary_phone' => $this->secondary_phone,
            'company' => $this->company,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'country' => $this->country,
            'is_active' => $this->is_active
        ];

        $updateData = [];
        foreach ($data as $key => $value) {
            if ( !empty( $value ) ) {
                $updateData[$key] = $value;
            }
        }

        $endpoint = 'customers/' . $this->customer_id . '/addresses';
        return $this->processUpdate( $endpoint, $updateData );
    }

    public static function bulkUpdate( $auth, $customerId, $data )
    {
        $endpoint = 'customers/' . $customerId . '/addresses';
        return self::processBulkUpdate( $auth, $endpoint, $data );
    }

    public static function delete( $auth, $customerId, $id )
    {
        $endpoint = 'customers/' . $customerId . '/addresses';
        return self::processDelete( $auth, $endpoint, $id );
    }

    public static function bulkDelete( $auth, $customerId, $data )
    {
        $postData = [ 'address_ids' => $data ];
        $endpoint = 'customers/' . $customerId . '/addresses/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }

}
