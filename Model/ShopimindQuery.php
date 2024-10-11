<?php

namespace Shopimind\Model;
use Propel\Runtime\Propel;

use Shopimind\Model\Base\ShopimindQuery as BaseShopimindQuery;

class ShopimindQuery extends BaseShopimindQuery
{

    const TABLE_NAME_TRUNCATE = 'shopimind';

    /**
     * Clears the table.
     *
     * @return void
     */
    public static function clearTable()
    {
        try {
            $con = Propel::getConnection();
            $sql = 'TRUNCATE TABLE ' . self::TABLE_NAME_TRUNCATE;
            $stmt = $con->prepare($sql);
            $stmt->execute();
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
} 

