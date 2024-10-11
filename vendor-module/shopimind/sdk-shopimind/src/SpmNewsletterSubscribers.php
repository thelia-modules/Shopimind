<?php
namespace Shopimind\SdkShopimind;

class SpmNewsletterSubscribers
{
    use Traits\Methods;

    /**
     * Shop identifier if multiple shops are available.
     * @var string|null
     */
    public $shop_id;

    /**
     * Subscriber email address.
     * @var string
     */
    public $email;

    /**
     * Indicates if the subscriber is subscribed to the newsletter.
     * @var boolean
     */
    public $is_subscribed;

    /**
     * Subscriber first name.
     * @var string
     */
    public $first_name;

    /**
     * Subscriber last name.
     * @var string
     */
    public $last_name;

    /**
     * The postal code of the address.
     * @var string
     */
    public $postal_code;

    /**
     * Language of the subscriber in ISO 639-1 format.
     * @var string
     */
    public $lang;

    /**
     * Update date of newsletter subscriber in ISO 8601 format.
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
            'email' => $this->email,
            'is_subscribed' => $this->is_subscribed,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'postal_code' => $this->postal_code,
            'lang' => $this->lang,
            'updated_at' => $this->updated_at
        ];

        if ($this->shop_id) {
            $data['shop_id'] = $this->shop_id;
        }

        return $this->processSave( 'newsletter-subscribers', $data );
    }

    public static function bulkSave( $auth, $data )
    {
        return self::processBulkSave( $auth, 'newsletter-subscribers', $data );
    }

    public function update(){
        $data = [
            'shop_id' => $this->shop_id,
            'email' => $this->email,
            'is_subscribed' => $this->is_subscribed,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'postal_code' => $this->postal_code,
            'lang' => $this->lang,
            'updated_at' => $this->updated_at
        ];

        $updateData = [];
        foreach ($data as $key => $value) {
            if ( !empty( $value ) ) {
                $updateData[$key] = $value;
            }
        }

        return $this->processUpdate( 'newsletter-subscribers', $updateData );
    }

    public static function bulkUpdate( $auth, $data )
    {
        return self::processBulkUpdate( $auth, 'newsletter-subscribers', $data );
    }
}
