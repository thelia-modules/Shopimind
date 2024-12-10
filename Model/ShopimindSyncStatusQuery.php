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
use Shopimind\Model\Base\ShopimindSyncStatusQuery as BaseShopimindSyncStatusQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'shopimind_sync_status' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ShopimindSyncStatusQuery extends BaseShopimindSyncStatusQuery
{
    public const TABLE_NAME_TRUNCATE = 'shopimind_sync_status';

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
