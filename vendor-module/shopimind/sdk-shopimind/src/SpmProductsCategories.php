<?php
namespace Shopimind\SdkShopimind;

class SpmProductsCategories
{
    use Traits\Methods;

    /**
     * Product category identifier.
     * @var string
     */
    public $category_id;

    /**
     * Shop identifier if multiple shops are available.
     * @var string|null
     */
    public $shop_id;

    /**
     * Language associated with the category in ISO 639-1 format.
     * @var string
     */
    public $lang;

    /**
     * Name of the category.
     * @var string
     */
    public $name;

    /**
     * Description of the category.
     * @var string
     */
    public $description;

    /**
     * Parent category identifier, null if no parent.
     * @var string|null
     */
    public $parent_category_id;

    /**
     * Link to the category.
     * @var string
     */
    public $link;

    /**
     * Indicates if the category is active.
     * @var bool
     */
    public $is_active;

    /**
     * Creation date of the category in ISO 8601 format.
     * @var string
     */
    public $created_at;

    /**
     * Update date of the category in ISO 8601 format.
     * @var string
     */
    public $updated_at;


    protected $auth;
    private $updateData = [];

    public function __construct($auth) {
        $this->auth = $auth;
    }

    public function save()
    {
        $data = [
            'category_id' => $this->category_id,
            'lang' => $this->lang,
            'name' => $this->name,
            'description' => $this->description,
            'parent_category_id' => $this->parent_category_id,
            'link' => $this->link,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->shop_id) {
            $data['shop_id'] = $this->shop_id;
        }

        return $this->processSave( 'products-categories', $data );
    }

    public static function bulkSave( $auth, $data )
    {
        return self::processBulkSave( $auth, 'products-categories', $data );
    }

    public function update(){
        $data = [
            'category_id' => $this->category_id,
            'shop_id' => $this->shop_id,
            'lang' => $this->lang,
            'name' => $this->name,
            'description' => $this->description,
            'parent_category_id' => $this->parent_category_id,
            'link' => $this->link,
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

        return $this->processUpdate( 'products-categories', $updateData );
    }

    public static function bulkUpdate( $auth, $data )
    {
        return self::processBulkUpdate( $auth, 'products-categories', $data );
    }

    public static function delete( $auth, $id )
    {
        return self::processDelete( $auth, 'products-categories', $id );
    }

    public static function bulkDelete( $auth, $data )
    {
        $postData = [ 'category_ids' => $data ];
        $endpoint = 'products-categories/bulk-delete';
        return self::processBulkDelete( $auth, $endpoint, $postData );
    }
}
 