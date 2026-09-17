<?php

namespace Shopimind\Data;

use Shopimind\lib\Utils;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Module\BaseModule;

class OrdersCarriersData
{
    /**
     * Transporteur au format ShopiMind.
     * Un transporteur Thelia est un module de livraison : son identifiant est celui que porte
     * order.delivery_module_id. Nom lu dans la langue par défaut, repli sur le code du module.
     *
     * @param Module $module
     * @return array
     */
    public static function formatOrdersCarrier( Module $module ): array
    {
        $now = new \DateTime();
        $defaultLang = LangQuery::create()->findOneByByDefault( 1 );
        $title = !empty( $defaultLang ) ? $module->setLocale( $defaultLang->getLocale() )->getTitle() : null;

        $data = [
            'carrier_id' => strval( $module->getId() ),
            'name' => !empty( $title ) ? $title : $module->getCode(),
            'is_active' => (int) $module->getActivate() === BaseModule::IS_ACTIVATED,
            'created_at' => ( $module->getCreatedAt() ?? $now )->format( 'Y-m-d\TH:i:s.uP' ),
            'updated_at' => ( $module->getUpdatedAt() ?? $now )->format( 'Y-m-d\TH:i:s.uP' ),
        ];

        $data['source_label'] = Utils::getSourceLabel();
        return $data;
    }
}
