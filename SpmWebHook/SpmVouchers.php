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

namespace Shopimind\SpmWebHook;

use Shopimind\lib\Utils;
use Shopimind\Model\Base\ShopimindQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Coupon;
use Thelia\Model\CouponI18n;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\LangQuery;

class SpmVouchers
{
    /**
     * Create coupon.
     */
    public static function createVoucher(Request $request)
    {
        $requestValidation = Utils::validateSpmRequest($request);
        if (!empty($requestValidation)) {
            return $requestValidation;
        }

        $config = ShopimindQuery::create()->findOne();
        $content = $request->getContent();
        parse_str($content, $body);

        $defaultCurrency = CurrencyQuery::create()->findOneByByDefault(true)->getCode();
        $defaultLocal = LangQuery::create()->findOneByByDefault(true)->getLocale();

        $status = true;
        $message = 'Voucher created successfully.';

        $emails = (\array_key_exists('voucherEmails', $body)) ? $body['voucherEmails'] : '';
        $voucherInfos = (\array_key_exists('voucherInfos', $body)) ? $body['voucherInfos'] : '';

        $errors = self::validate($voucherInfos);
        if (!empty($errors)) {
            return $errors;
        }

        $type = $voucherInfos['type'];
        $amount = $voucherInfos['amount'];
        $currency = (\array_key_exists('amountCurrency', $voucherInfos)) ? $voucherInfos['amountCurrency'] : '';
        $minimumOrder = (\array_key_exists('minimumOrder', $voucherInfos)) ? $voucherInfos['minimumOrder'] : '';
        $nbDayValidate = $voucherInfos['nbDayValidate'];
        $code = $voucherInfos['codeToGenerate'];
        // $duplicateCode = ( array_key_exists('duplicateCode', $voucherInfos ) ) ? $voucherInfos['duplicateCode'] : '';
        // $dynamicPrefix = ( array_key_exists('dynamicPrefix', $voucherInfos ) ) ? $voucherInfos['dynamicPrefix'] : '';

        $isRemovingPostage = 0;
        $typeFormat = '';
        $effects = [];
        switch ($type) {
            case 'percent':
                $effects = [
                    'percentage' => $amount,
                ];
                $typeFormat = 'thelia.coupon.type.remove_x_percent';
                break;

            case 'amount':
                $effects = [
                    'amount' => $amount,
                ];
                $typeFormat = 'thelia.coupon.type.remove_x_amount';
                break;

            case 'free_shipping':
                $effects = [
                    'amount' => $amount,
                ];
                $typeFormat = 'thelia.coupon.type.remove_x_amount';
                $isRemovingPostage = 1;
                break;
            default:
                $effects = [
                    'amount' => $amount,
                ];
                $typeFormat = 'thelia.coupon.type.remove_x_amount';
                break;
        }

        $condition = [];
        $minimuOrderCondition = '';
        if (!empty($minimumOrder)) {
            $minimuOrderCondition = [
                'conditionServiceId' => 'thelia.condition.match_for_total_amount',
                'operators' => [
                    'price' => '>=',
                    'currency' => '==',
                ],
                'values' => [
                    'price' => $minimumOrder,
                    'currency' => $currency ? $currency : $defaultCurrency,
                ],
            ];
            $condition[] = $minimuOrderCondition;
        }

        $customerCondition = '';
        $customersIds = [];
        if (!empty($emails)) {
            foreach ($emails as $value) {
                if (\array_key_exists('email', $value)) {
                    $customer = CustomerQuery::create()->findOneByEmail($value['email']);
                    $customerId = !empty($customer) ? $customer->getId() : '';
                    if (!empty($customerId)) {
                        $customersIds[] = $customerId;
                    }
                }
            }
        }
        if (!empty($customersIds) && $config->getNominativeReductions()) {
            $customerCondition = [
                'conditionServiceId' => 'thelia.condition.for_some_customers',
                'operators' => [
                    'customers' => 'in',
                ],
                'values' => [
                    'customers' => $customersIds,
                ],
            ];
        } else {
            $customerCondition = [
                'conditionServiceId' => 'thelia.condition.match_for_everyone',
                'operators' => [],
                'values' => [],
            ];
        }

        $condition[] = $customerCondition;

        $startDate = date('Y-m-d H:i:s');
        $expirationDate = date(
            'Y-m-d 23:59:59',
            mktime(
                date('H'),
                date('i'),
                date('s'),
                date('m'),
                date('d') + $nbDayValidate,
                date('Y')
            )
        );

        $description = 'Code de réduction';

        if (!empty($emails)) {
            foreach ($emails as $value) {
                if (\array_key_exists('description', $value)) {
                    $description = $value['description'];
                }
            }
        }

        $isCumulative = ($config->getCumulativeVouchers()) ? 1 : 0;

        $coupon = new Coupon();
        $coupon->setCode($code);
        $coupon->setType($typeFormat);
        $coupon->setSerializedEffects(json_encode($effects));
        $coupon->setIsEnabled(1);
        $coupon->setStartDate($startDate);
        $coupon->setExpirationDate($expirationDate);
        $coupon->setMaxUsage(1);
        $coupon->setIsCumulative($isCumulative);
        $coupon->setIsRemovingPostage($isRemovingPostage);
        $coupon->setIsAvailableOnSpecialOffers(0);
        $coupon->setIsUsed(0);
        $coupon->setSerializedConditions(base64_encode(json_encode($condition)));
        $coupon->setPerCustomerUsageCount(1);
        $coupon->setCreatedAt(new \DateTime());
        $coupon->setUpdatedAt(new \DateTime());
        $coupon->setVersion(0);
        $coupon->setVersionCreatedAt(new \DateTime());
        $coupon->setVersionCreatedBy(null);
        $coupon->setDescription($description);
        $coupon->setShortDescription($description);
        $coupon->setTitle($description);
        $coupon->setLocale($defaultLocal);

        try {
            $coupon->save();

            self::generateTranslation($coupon, $description);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => $th->getMessage(),
            ]);
        }

        $vouchersToReturn = [];

        if (!empty($emails)) {
            foreach ($emails as $value) {
                $vouchersToReturn[$value['email']] = [
                    'voucher_number' => $code,
                    'voucher_date_limit' => $expirationDate,
                ];
            }
        }

        return new JsonResponse([
            'vouchers' => $vouchersToReturn,
            'success' => true,
        ]);
    }

    /**
     * Validate coupon creation parameters.
     *
     * @param array $params an array containing the coupon creation parameters
     */
    public static function validate($params)
    {
        $message = '';

        $requiredParams = [
            'type',
            'amount',
            'nbDayValidate',
            'codeToGenerate',
        ];

        if (\is_array($params)) {
            foreach ($requiredParams as $param) {
                if (empty($params[$param])) {
                    $message = $param.' is required.';
                }
            }
        } else {
            $message = 'voucherInfos must be of type array';
        }

        if (!empty($message)) {
            $response = new JsonResponse([
                'success' => false,
                'message' => $message,
            ]);

            return $response;
        }
    }

    /**
     * Generate translation for coupon.
     *
     * @return void
     */
    public static function generateTranslation(Coupon $coupon, string $description): void
    {
        $langsQuery = LangQuery::create()->filterByActive(1)->find();
        foreach ($langsQuery as $lang) {
            $translation = new CouponI18n();

            $translation->setCoupon($coupon);
            $translation->setLocale($lang->getLocale());
            $translation->setTitle($description);
            $translation->setShortDescription($description);
            $translation->setDescription($description);

            try {
                $translation->save();
            } catch (\Throwable $th) {
                // throw $th;
            }
        }
    }
}
