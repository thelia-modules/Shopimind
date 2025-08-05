<?php

namespace Shopimind\SpmWebHook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Model\Coupon;
use Thelia\Model\CouponI18n;
use Thelia\Model\CouponModule;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponQuery;
use Thelia\Model\CouponCountryQuery;
use Thelia\Model\CouponModuleQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\LangQuery;
use Shopimind\Model\Base\ShopimindQuery;
use Shopimind\lib\Utils;

class SpmVouchers
{
    /**
     * Create coupon
     *
     * @param Request $request
     */
    public static function createVoucher( Request $request )
    {
        $requestValidation = Utils::validateSpmRequest( $request );
        if ( !empty( $requestValidation ) ) return $requestValidation;

        $config = ShopimindQuery::create()->findOne();
        $content = $request->getContent();
        parse_str($content, $body);

        $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(true)->getCode();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();

        $emails = ( array_key_exists('voucherEmails', $body) ) ? $body['voucherEmails'] : '';
        $voucherInfos = ( array_key_exists('voucherInfos', $body) ) ? $body['voucherInfos'] : '';

        $errors = self::validate( $voucherInfos );
        if ( !empty( $errors ) ) {
            return $errors;
        }
        
        $type = $voucherInfos['type'];
        $amount = $voucherInfos['amount'];
        $currency = ( array_key_exists('amountCurrency', $voucherInfos ) ) ? $voucherInfos['amountCurrency'] : '';
        $minimumOrder = ( array_key_exists('minimumOrder', $voucherInfos ) ) ? $voucherInfos['minimumOrder'] : '';
        $nbDayValidate = $voucherInfos['nbDayValidate'];
        $code = $voucherInfos['codeToGenerate'];
        $duplicateCode = ( array_key_exists('duplicateCode', $voucherInfos ) ) ? $voucherInfos['duplicateCode'] : '';
        // $dynamicPrefix = ( array_key_exists('dynamicPrefix', $voucherInfos ) ) ? $voucherInfos['dynamicPrefix'] : '';

        $isRemovingPostage = 0;
        $typeFormat = "";
        $effects = [];
        $isCumulative = ( $config->getCumulativeVouchers() ) ? 1 : 0;
        $condition = [];
        $couponToDuplicate = null;
        switch ( $type ) {
            case 'percent':
                $effects = [
                    'percentage' => $amount
                ];
                $typeFormat = "thelia.coupon.type.remove_x_percent";
                break;
            
            case 'amount':
                $effects = [
                    'amount' => $amount
                ];
                $typeFormat = "thelia.coupon.type.remove_x_amount";
                break;

            case 'shipping':
                $effects = [
                    'amount' => 0,
                ];
                $typeFormat = "thelia.coupon.type.remove_x_amount";
                $isRemovingPostage = 1;
                break;

            case 'duplicateCode':
                $couponToDuplicate = CouponQuery::create()
                    ->filterByCode( $duplicateCode )
                    ->findOne();
                
                if ( empty( $couponToDuplicate ) ) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'No coupon found for the code: ' . $duplicateCode,
                    ]);
                }

                $currency = self::getCurreny( $couponToDuplicate->getSerializedConditions() );
                $typeFormat = $couponToDuplicate->getType();
                $effects = $couponToDuplicate->getEffects();
                $isRemovingPostage = $couponToDuplicate->getIsRemovingPostage();
                $isCumulative = $couponToDuplicate->isCumulative();
                $condition = self::getCondition( $couponToDuplicate->getSerializedConditions() );
                break;

            default : 
                $effects = [
                    'amount' => $amount
                ];
                $typeFormat = "thelia.coupon.type.remove_x_amount";
                break;
        }

        $minimuOrderCondition = "";
        if ( !empty( $minimumOrder ) ) {
            $minimuOrderCondition = [
                'conditionServiceId' => 'thelia.condition.match_for_total_amount',
                'operators' => [
                    'price' => '>=',
                    'currency' => '=='
                ],
                'values' => [
                    'price' => $minimumOrder,
                    'currency' => $currency ? $currency : $defaultCurrency
                ]
            ];
            array_push( $condition, $minimuOrderCondition );
        }

        $customerCondition = "";
        $customersIds = [];
        if ( !empty( $emails ) ) {
            foreach ( $emails as $value ) {
                if ( array_key_exists('email', $value ) ) {
                    $customer = CustomerQuery::create()->findOneByEmail( $value['email'] );
                    $customerId = !empty( $customer ) ? $customer->getId() : "";
                    if ( !empty( $customerId ) ) {
                        array_push( $customersIds, $customerId );
                    }
                }
            }
        }
        if ( !empty( $customersIds ) && $config->getNominativeReductions() ) {
            $customerCondition = [
                'conditionServiceId' => 'thelia.condition.for_some_customers',
                'operators' => [
                    'customers' => 'in'
                ],
                'values' => [
                    'customers' => $customersIds
                ]
            ];
        }else {
            $customerCondition = [
                'conditionServiceId' => 'thelia.condition.match_for_everyone',
                'operators' => [],
                'values' => []
            ];
        }
        array_push( $condition, $customerCondition );

        $startDate = date('Y-m-d H:i:s');
        $expirationDate = date(
            'Y-m-d 23:59:59',
            mktime(
                date("H"),
                date("i"),
                date("s"),
                date("m"),
                date("d") + $nbDayValidate,
                date("Y")
            )
        );

        $description = "Discount Code";
        if ( !empty( $emails ) ) {
            foreach ( $emails as $value ) {
                if ( array_key_exists('description', $value ) ) {
                    $description = $value['description'];
                }
            }
        }

        $coupon = new Coupon();
        $coupon->setCode( $code );
        $coupon->setType( $typeFormat );
        $coupon->setSerializedEffects( json_encode( $effects ) );
        $coupon->setIsEnabled( 1 );
        $coupon->setStartDate( $startDate );
        $coupon->setExpirationDate( $expirationDate );
        $coupon->setIsCumulative( $isCumulative );
        $coupon->setIsRemovingPostage( $isRemovingPostage ); 
        $coupon->setIsAvailableOnSpecialOffers( 0 );
        $coupon->setIsUsed( 0 );
        $coupon->setSerializedConditions( base64_encode( json_encode( $condition ) ) );
        // voucher can be used once
        $coupon->setMaxUsage( 1 );
        $coupon->setPerCustomerUsageCount( 0 );
        $coupon->setCreatedAt( new \DateTime() );
        $coupon->setUpdatedAt( new \DateTime() );
        $coupon->setVersion( 0 );
        $coupon->setVersionCreatedAt( new \DateTime() );
        $coupon->setVersionCreatedBy( NULL );
        $coupon->setDescription( $description );
        $coupon->setShortDescription( $description );
        $coupon->setTitle( $description );
        $coupon->setLocale( $defaultLocal );

        try {
            $coupon->save();

            if ( $type === 'duplicateCode' && !empty( $couponToDuplicate ) ) {
                self::duplicateCouponCountryAssociations( $couponToDuplicate->getId(), $coupon->getId() );
                self::duplicateCouponModuleAssociations( $couponToDuplicate->getId(), $coupon->getId() );
            }

            self::generateTranslation( $coupon, $description );
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' =>  false,
                'message' => $th->getMessage(),
            ]);
        }

        $vouchersToReturn = array();

        if ( !empty( $emails ) ) {
            foreach ($emails as $value) {
                $vouchersToReturn[$value['email']] = array(
                    'voucher_number' => $code,
                    'voucher_date_limit' => $expirationDate,
                );
            }
        }

        return new JsonResponse([
            'vouchers' =>  $vouchersToReturn,
            'success' => true,
        ]);
    }

    /**
     * Validate coupon creation parameters.
     *
     * @param array $params An array containing the coupon creation parameters.
     */
    public static function validate( $params ) 
    {
        $message = "";

        $requiredParams = [
            'type',
            'amount',
            'nbDayValidate',
            'codeToGenerate'
        ];

        if ( is_array( $params ) ) {
            foreach ( $requiredParams as $param ) {
                if ( empty( $params[$param] ) ) {
                    $message = $param . ' is required.';
                }
            }
        }else {
            $message = 'voucherInfos must be of type array';
        }
        

        if ( !empty( $message ) ) {
            $response = new JsonResponse([
                'success' => false,
                'message' => $message,
            ]);
    
            return $response;
        }
    }

    /**
     * Generate translation for coupon
     *
     * @param Coupon $coupon
     * @param string $description
     * @return void
     */
    public static function generateTranslation( Coupon $coupon, string $description )
    {
        $langsQuery = LangQuery::create()->filterByActive( 1 )->find();
        foreach ( $langsQuery as $lang ) {
            $translation = new CouponI18n();

            $translation->setCoupon( $coupon );
            $translation->setLocale( $lang->getLocale() );
            $translation->setTitle( $description );
            $translation->setShortDescription( $description );
            $translation->setDescription( $description );

            try {
                $translation->save();
            } catch (\Throwable $th) {
                //throw $th;
            }
        }
    }

    /**
     * Retrieve the currency of a voucher
     *
     * @param string $serializedConditions
     * @return string | null
     */
    public static function getCurreny( string $serializedConditions )
    {
        $condition = json_decode( base64_decode( $serializedConditions ), true );

        if ( $condition !== null ) {
            $currency = $condition[0]['values']['currency'] ?? null;

            if ( $currency !== null ) {
                return $currency;
            }
        } 

        return 'EUR';
    }

    /**
     * Excluding specific condition types
     *
     * @param string $serializedConditions
     * @return array
     */
    public static function getCondition( string $serializedConditions )
    {
        $conditionToExlude = [
            "thelia.condition.match_for_total_amount",
            "thelia.condition.match_for_everyone",
            "thelia.condition.for_some_customers"
        ];
        
        $condition = json_decode( base64_decode( $serializedConditions ), true );

        if ( $condition !== null ) {
            $condition = array_filter($condition, function ( $data ) use ( $conditionToExlude ) {
                return !in_array( $data['conditionServiceId'], $conditionToExlude );
            });
            
            return $condition;
        } 

        return [];
    }

    /**
     * Duplicate coupon-country associations for a new coupon.
     *
     * @param int $couponToDuplicate The ID of the coupon to duplicate.
     * @param int $newCouponId The ID of the new coupon.
     * @return void
     */
    public static function duplicateCouponCountryAssociations( int $couponToDuplicate, int $newCouponId )
    {
        $associations = CouponCountryQuery::create()
            ->filterByCouponId( $couponToDuplicate )
            ->find();

        if ($associations->isEmpty()) {
            return;
        }

        foreach ( $associations as $association ) {
            $newAssociation = new CouponCountry();
            $newAssociation
                ->setCouponId( $newCouponId )
                ->setCountryId( $association->getCountryId() );
            $newAssociation->save();
        }
    }

    /**
     * Duplicate coupon-module associations for a new coupon.
     *
     * @param int $couponToDuplicate The ID of the coupon to duplicate.
     * @param int $newCouponId The ID of the new coupon.
     * @return void
     */
    public static function duplicateCouponModuleAssociations(int $couponToDuplicate, int $newCouponId)
    {
        $associations = CouponModuleQuery::create()
            ->filterByCouponId($couponToDuplicate)
            ->find();

        if ( $associations->isEmpty() ) {
            return;
        }

        foreach ( $associations as $association ) {
            $newAssociation = new CouponModule();
            $newAssociation
                ->setCouponId( $newCouponId )
                ->setModuleId( $association->getModuleId() );
            $newAssociation->save();
        }
    }
}
