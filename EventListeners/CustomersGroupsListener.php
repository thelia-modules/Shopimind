<?php
namespace Shopimind\EventListeners;

require_once __DIR__ . '/../vendor-module/autoload.php';

use CustomerFamily\Event\CustomerFamilyEvent;
use Shopimind\lib\Utils;
use Shopimind\Data\CustomersGroupsData;
use Shopimind\SdkShopimind\SpmCustomersGroups;
use Thelia\Model\Base\LangQuery;


class CustomersGroupsListener
{
    /**
     * Synchronizes data after a customer group is inserted.
     *
     * @param CustomerFamilyEvent $event The event object triggering the action.
     */
    public static function postCustomerGroupInsert(CustomerFamilyEvent $event): void
    {
        // $customerGroup = $event->getCustomerFamily();

        // $langs = LangQuery::create()->filterByActive( 1 )->find();
        // $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        // $customerGroupDefault = $customerGroup->getTranslation( $defaultLocal );

        // $data = [];

        // foreach ( $langs as $lang ) {
        //     $customerGroupTranslated = $customerGroup->getTranslation( $lang->getLocale() );

        //     $data[] = CustomersGroupsData::formatCustomerGroup( $customerGroup, $customerGroupTranslated, $customerGroupDefault );
        // }

        // $response = SpmCustomersGroups::bulkSave( Utils::getAuth(), $data );
        
        // Utils::handleResponse( $response );

        // Utils::log( 'CustomerGroup', 'Save', json_encode( $data ), $customerGroup->getId() );
    }

    /**
     * Synchronizes data after a customer group is updated.
     *
     * @param CustomerFamilyEvent $event The event object triggering the action.
     */
    public static function postCustomerGroupUpdate(CustomerFamilyEvent $event): void
    {
        $customerGroup = $event->getCustomerFamily();

        $langs = LangQuery::create()->filterByActive( 1 )->find();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();
        $customerGroupDefault = $customerGroup->getTranslation( $defaultLocal );

        $data = [];

        foreach ( $langs as $lang ) {
            $customerGroupTranslated = $customerGroup->getTranslation( $lang->getLocale() );

            $data[] = CustomersGroupsData::formatCustomerGroup( $customerGroup, $customerGroupTranslated, $customerGroupDefault );
        }

        $response = SpmCustomersGroups::bulkUpdate( Utils::getAuth(), $data );
        
        Utils::handleResponse( $response );

        Utils::log( 'CustomerGroup', 'Update', json_encode( $response ), $customerGroup->getId() );
    }

    /**
     * Synchronizes data after a customer group is deleted.
     *
     * @param CustomerFamilyEvent $event The event object triggering the action.
     */
    public static function postCustomerGroupDelete(CustomerFamilyEvent $event): void
    {
        $customerGroup = $event->getCustomerFamily()->getid();
        $response = SpmCustomersGroups::delete( Utils::getAuth(), $customerGroup );
        
        Utils::handleResponse( $response );

        Utils::log( 'CustomerGroup', 'Delete', json_encode( $response ), $customerGroup );
    }
}
