<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopimind\Model;

use Propel\Runtime\Propel;
use Shopimind\Model\Base\ShopimindQuery as BaseShopimindQuery;

class ShopimindQuery extends BaseShopimindQuery
{
    public const TABLE_NAME_TRUNCATE = 'shopimind';

    /**
     * Clears the table.
     *
     * @return void
     */
    public static function clearTable(): void
    {
        try {
            $con = Propel::getConnection();
            $sql = 'TRUNCATE TABLE '.self::TABLE_NAME_TRUNCATE;
            $stmt = $con->prepare($sql);
            $stmt->execute();
        } catch (\Throwable $th) {
            // throw $th;
        }
    }
}
