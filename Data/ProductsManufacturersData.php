<?php

namespace Shopimind\Data;

use Shopimind\lib\Utils;
use Thelia\Model\Brand;
use Thelia\Model\LangQuery;

class ProductsManufacturersData
{
    /**
     * Formats the product manufacturer data to match the ShopiMind format.
     *
     * @param Brand $brand
     * @return array
     */
    public static function formatProductmanufacturer( Brand $brand ): array
    {
        $data = [
            'manufacturer_id' => strval( $brand->getId() ),
            'name' => self::getName( $brand ),
            'is_active' => ( bool ) $brand->getVisible(),
            "created_at" => $brand->getCreatedAt()->format('Y-m-d\TH:i:s.uP'),
            "updated_at" => $brand->getUpdatedAt()->format('Y-m-d\TH:i:s.uP')
        ];

        $data['source_label'] = Utils::getSourceLabel();
        return $data;
    }

    /**
     * Titre de la marque dans la langue par défaut de la boutique, sinon le premier titre renseigné, sinon « #id ».
     * Sans locale, getTitle() lit la traduction en_US, vide sur une boutique qui ne l'a pas saisie.
     *
     * @param Brand $brand
     * @return string
     */
    public static function getName( Brand $brand ): string
    {
        $titles = [];
        foreach ( $brand->getBrandI18ns() as $translation ) {
            $title = trim( (string) $translation->getTitle() );
            if ( $title !== '' ) {
                $titles[ (string) $translation->getLocale() ] = $title;
            }
        }

        $defaultLang = LangQuery::create()->findOneByByDefault( true );
        if ( !empty( $defaultLang ) && isset( $titles[ $defaultLang->getLocale() ] ) ) {
            return $titles[ $defaultLang->getLocale() ];
        }

        return !empty( $titles ) ? reset( $titles ) : '#' . $brand->getId();
    }
}
