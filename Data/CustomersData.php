<?php

namespace Shopimind\Data;

use Thelia\Model\NewsletterQuery;
use Thelia\Model\Base\Customer;
use Thelia\Model\AddressQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MetaData;
use Thelia\Model\MetaDataQuery;
use CustomerFamily\Model\Base\CustomerCustomerFamilyQuery;
use Shopimind\lib\Utils;

class CustomersData
{
    /**
     * Formats the customer data to match the ShopiMind format.
     *
     * @param Customer $customer
     * @return array
     */
    public static function formatCustomer( Customer $customer ): array
    {
        $address = AddressQuery::create()->findOneByCustomerId( $customer->getId() );

        $phone = ( !empty( $address ) && $address->getPhone() ) ? $address->getPhone() : null;

        $defaultLang = LangQuery::create()->findOneByByDefault( 1 );
        $langQuery = LangQuery::create()->findOneById( $customer->getLangId() );
        $lang = ( !empty( $langQuery ) && $langQuery->getCode() ) ? $langQuery->getCode() : $defaultLang->getCode();

        $groupIds = null;
        if ( Utils::isCustomerFamilyActive() ) {
            $groupIds = CustomerCustomerFamilyQuery::create()->filterByCustomerId( $customer->getId() )->select('CustomerFamilyId')->find()->toArray();        
            $groupIds = array_map( 'strval', $groupIds );
        }

        $data = [
            "customer_id" => strval( $customer->getId() ),
            "email" => $customer->getEmail(),
            "phone_number" => $phone,
            "first_name" => $customer->getFirstname(),
            "last_name" => $customer->getLastname(),
            "gender" => self::resolveGender( $customer ),
            "birth_date" => self::getBirthDate( $customer ),
            "is_newsletter_subscribed" => self::isNewsletterSubscribed( $customer->getEmail() ),
            "lang" => $lang,
            "group_ids" => !empty( $groupIds ) ? $groupIds : null,
            "is_active" => self::isActive( $customer ),
            "created_at" => $customer->getCreatedAt()->format('Y-m-d\TH:i:s.uP'),
            "updated_at" => $customer->getUpdatedAt()->format('Y-m-d\TH:i:s.uP')
        ];

        $data['source_label'] = Utils::getSourceLabel();
        return $data;
    }

    /**
     * Verifies whether a customer is subscribed to the newsletter.
     *
     * @param string $customerEmail The customer's email address.
     * @return bool True if the customer is subscribed, false otherwise.
     */
    public static function isNewsletterSubscribed( string $customerEmail ) : bool{
        $newsletter = NewsletterQuery::create()->findOneByEmail( $customerEmail );
        if ( !empty($newsletter) ) {
            return ! $newsletter->getUnsubscribed();
        }else {
            return false;
        }
    }

    /**
     * Map a Thelia customer's title_id to the ShopiMind gender convention
     * (0 = unknown, 1 = Mr, 2 = Mme) using the admin-side mapping stored
     * in `shopimind_gender_mapping` (Thelia generic config table).
     *
     * Mapping shape: { "1": [titleId, ...], "2": [titleId, ...] }. A
     * customer_title not listed in either bucket maps to 0. Mme wins over
     * Mr if a title appears in both lists (defensive — the admin form
     * already strips Mr-only when the same title is in Mme).
     *
     * @param Customer $customer
     * @return int 0|1|2
     */
    public static function resolveGender( Customer $customer ): int
    {
        $titleId = (int) $customer->getTitleId();
        if ( $titleId <= 0 ) {
            return 0;
        }
        $map = self::getGenderMapping();
        $mme = isset( $map['2'] ) && is_array( $map['2'] ) ? array_map( 'intval', $map['2'] ) : [];
        if ( in_array( $titleId, $mme, true ) ) {
            return 2;
        }
        $mr = isset( $map['1'] ) && is_array( $map['1'] ) ? array_map( 'intval', $map['1'] ) : [];
        if ( in_array( $titleId, $mr, true ) ) {
            return 1;
        }
        return 0;
    }

    /**
     * Civilités installées par Thelia (setup/insert.sql : 1 Monsieur, 2 Madame, 3 Mademoiselle),
     * utilisées tant que l'administrateur n'a jamais enregistré de correspondance : sans elles,
     * tous les clients partaient avec gender = 0.
     */
    const DEFAULT_GENDER_MAPPING = [ '1' => [ 1 ], '2' => [ 2, 3 ] ];

    /**
     * Correspondance civilité → genre enregistrée dans la configuration du module, ou
     * correspondance par défaut si elle n'a jamais été enregistrée.
     *
     * @return array { "1": [titleId, ...], "2": [titleId, ...] }
     */
    public static function getGenderMapping(): array
    {
        $raw = ConfigQuery::read( 'shopimind_gender_mapping', null );
        if ( $raw === null || $raw === '' ) {
            return self::DEFAULT_GENDER_MAPPING;
        }

        $map = json_decode( $raw, true );

        return is_array( $map ) ? $map : [];
    }

    /**
     * Clés de métadonnée client lues pour la date de naissance, par ordre de priorité. Le cœur de Thelia 2.5
     * ne stocke aucune date de naissance : seule une métadonnée (table meta_data, élément « customer »)
     * posée par un module ou un développement spécifique peut en fournir une.
     */
    const BIRTH_DATE_META_KEYS = [ 'birth_date', 'birthdate', 'birthday', 'date_of_birth' ];

    /**
     * Date de naissance au format AAAA-MM-JJ attendu par ShopiMind, lue dans les
     * métadonnées du client. null si aucune métadonnée n'existe ou si la valeur n'est pas une date AAAA-MM-JJ
     * valide : une date JJ/MM/AAAA serait lue comme MM/JJ/AAAA, elle est donc ignorée.
     *
     * @param Customer $customer
     * @return string|null
     */
    public static function getBirthDate( Customer $customer ): ?string
    {
        try {
            $metaData = MetaDataQuery::create()
                ->filterByElementKey( MetaData::CUSTOMER_KEY )
                ->filterByElementId( (int) $customer->getId() )
                ->filterByMetaKey( self::BIRTH_DATE_META_KEYS )
                ->find();

            $values = [];
            foreach ( $metaData as $item ) {
                $values[ $item->getMetaKey() ] = $item->getValue();
            }

            foreach ( self::BIRTH_DATE_META_KEYS as $key ) {
                if ( !isset( $values[ $key ] ) ) {
                    continue;
                }
                $value = $values[ $key ];
                if ( $value instanceof \DateTimeInterface ) {
                    return $value->format( 'Y-m-d' );
                }
                if ( is_string( $value ) && preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', trim( $value ), $matches )
                    && checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
                    return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                }
            }
        } catch ( \Throwable $e ) {
            Utils::logWarn( 'Sync', 'Customer', 'date de naissance illisible', [ 'error' => $e->getMessage() ], $customer->getId() );
        }

        return null;
    }

    /**
     * Statut actif selon la règle de Thelia 2.5 à la connexion (UsernamePasswordFormAuthenticator) : un client
     * n'est bloqué que si la confirmation d'e-mail est exigée (customer_email_confirmation), qu'un jeton de
     * confirmation existe et que le compte n'est pas confirmé. La colonne enable seule vaut 0 pour tous les
     * clients quand la confirmation est désactivée, réglage par défaut, ainsi que pour les clients antérieurs
     * à Thelia 2.3.4 ; Thelia n'a ni désactivation de client en back-office ni suppression logique.
     *
     * @param Customer $customer
     * @return bool
     */
    public static function isActive( Customer $customer ): bool
    {
        try {
            if ( !ConfigQuery::isCustomerEmailConfirmationEnable() ) {
                return true;
            }

            return !( null !== $customer->getConfirmationToken() && !$customer->getEnable() );
        } catch ( \Throwable $e ) {
            return true;
        }
    }
}
