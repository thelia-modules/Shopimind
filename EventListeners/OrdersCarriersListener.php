<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Shopimind\Data\OrdersCarriersData;
use Shopimind\lib\Utils;
use Shopimind\SdkShopimind\SpmOrdersCarriers;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

class OrdersCarriersListener
{
    /**
     * Envoie en un seul appel les transporteurs créés ou modifiés pendant la requête. Les modules sont
     * relus au flush de fin de requête : Thelia active ou désactive un module dans une transaction, son
     * état final n'est connu qu'après le commit.
     *
     * @param array $moduleIds
     */
    public static function syncCarriers( array $moduleIds ): void
    {
        $moduleIds = array_values( array_unique( array_filter( array_map( 'intval', $moduleIds ) ) ) );
        if ( empty( $moduleIds ) ) {
            return;
        }

        $carriers = ModuleQuery::create()
            ->filterById( $moduleIds )
            ->filterByType( BaseModule::DELIVERY_MODULE_TYPE )
            ->find();

        $data = [];
        foreach ( $carriers as $carrier ) {
            $data[] = OrdersCarriersData::formatOrdersCarrier( $carrier );
        }

        if ( empty( $data ) ) {
            return;
        }

        $response = SpmOrdersCarriers::bulkSave( Utils::getAuth(), $data );

        Utils::log( 'OrderCarrier', 'Save', json_encode( $response ) );
    }
}
