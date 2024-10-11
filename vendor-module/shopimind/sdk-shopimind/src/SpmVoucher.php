<?php
namespace Shopimind\SdkShopimind;

class SpmVoucher
{
    use Traits\Methods;

    /**
     * Voucher identifier.
     * @var string
     */
    public $voucher_id;

    /**
     * Shop identifier if multiple shops are available.
     * @var string|null
     */
    public $shop_id;

    /**
     * Language associated with the voucher in ISO 639-1 format.
     * @var string
     */
    public $lang;

    /**
     * Voucher code.
     * @var string
     */
    public $code;

    /**
     * Description of the voucher.
     * @var string
     */
    public $description;

    /**
     * Discount voucher validity start date in ISO 8601 format.
     * @var string
     */
    public $started_at;

    /**
     * Discount voucher expiry date in ISO 8601 format, null if unlimited.
     * @var string|null
     */
    public $ended_at;

    /**
     * Customer identifier to whom the voucher is assigned. null if not assigned to any customer.
     * @var string|null
     */
    public $id_customer;

    /**
     * Type of the voucher.
     * @var string
     */
    public $type_voucher;

    /**
     * Discount voucher value, determined by amount or percentage, null if free shipping.
     * @var float|null
     */
    public $value;

    /**
     * Minimum amount required to use the voucher.
     * @var float
     */
    public $minimum_amount;

    /**
     * The currency code associated with the voucher in ISO 4217 format.
     * @var string
     */
    public $currency;

    /**
     * Indicates if the voucher is a tax reduction.
     * @var bool
     */
    public $reduction_tax;

    /**
     * Indicates if the voucher is used.
     * @var bool
     */
    public $is_used;

    /**
     * Indicates if the voucher is active.
     * @var bool
     */
    public $is_active;

    /**
     * Creation date of the voucher in ISO 8601 format.
     * @var string
     */
    public $created_at;

    /**
     * Update date of the voucher in ISO 8601 format.
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
            'voucher_id' => $this->voucher_id,
            'lang' => $this->lang,
            'code' => $this->code,
            'description' => $this->description,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'id_customer' => $this->id_customer,
            'type_voucher' => $this->type_voucher,
            'value' => $this->value,
            'minimum_amount' => $this->minimum_amount,
            'currency' => $this->currency,
            'reduction_tax' => $this->reduction_tax,
            'is_used' => $this->is_used,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->shop_id) {
            $data['shop_id'] = $this->shop_id;
        }


        return $this->processSave( 'vouchers', $data );
    }

    public static function bulkSave( $auth, $data )
    {
        return self::processBulkSave( $auth, 'vouchers', $data );
    }

    public function update(){
        $data = [
            'voucher_id' => $this->voucher_id,
            'shop_id' => $this->shop_id,
            'lang' => $this->lang,
            'code' => $this->code,
            'description' => $this->description,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'id_customer' => $this->id_customer,
            'type_voucher' => $this->type_voucher,
            'value' => $this->value,
            'minimum_amount' => $this->minimum_amount,
            'currency' => $this->currency,
            'reduction_tax' => $this->reduction_tax,
            'is_used' => $this->is_used,
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

        return $this->processUpdate( 'vouchers', $updateData );
    }

    public static function bulkUpdate( $auth, $data )
    {
        return self::processBulkUpdate( $auth, 'vouchers', $data );
    }

    public static function delete( $auth, $id )
    {
        return self::processDelete( $auth, 'vouchers', $id );
    }

    public static function bulkDelete( $auth, $data )
    {
        $postData = [ 'voucher_ids' => $data ];
        $endpoint = 'vouchers/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }
}
