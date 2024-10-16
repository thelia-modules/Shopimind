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

namespace Shopimind\Form;

use Shopimind\lib\Utils;
use Shopimind\Model\Base\ShopimindQuery;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints;
use Thelia\Form\BaseForm;

/**
 * Class ShopimindForm.
 *
 * @author shopimind
 */
class ShopimindForm extends BaseForm
{
    /**
     * Build the form.
     */
    protected function buildForm(): void
    {
        $config = ShopimindQuery::create()->findOne();

        $apiId = !empty($config) ? $config->getApiId() : '';
        $apiPassword = !empty($config) ? $config->getApiPassword() : '';
        $realTimeSynchronization = !empty($config) ? $config->getRealTimeSynchronization() : '';
        $nominativeReductions = !empty($config) ? $config->getNominativeReductions() : '';
        $cumulativeVouchers = !empty($config) ? $config->getCumulativeVouchers() : '';
        $outOfStockProductDisabling = !empty($config) ? $config->getOutOfStockProductDisabling() : '';
        $scriptTag = !empty($config) ? $config->getScriptTag() : 1;
        $log = !empty($config) ? $config->getLog() : '';

        $session = new Session();
        $flashbag = $session->getFlashBag();
        $successMsg = '';
        $errorMsg = '';
        foreach ($flashbag->get('success') as $message) {
            $successMsg = $message;
        }

        $isNotConnected = '';
        if (!Utils::isConnected()) {
            $isNotConnected = 'Your module is not connected.';
        }

        $this->formBuilder
        ->add('logView', TextType::class, [
            'mapped' => false,
            'required' => false,
            'label' => '',
            'data' => $this->printLog(),
        ])
        ->add('isNotConnected', TextType::class, [
            'mapped' => false,
            'required' => false,
            'label' => '',
            'data' => $isNotConnected,
        ])
        ->add('successMsg', TextType::class, [
            'mapped' => false,
            'required' => false,
            'label' => '',
            'data' => $successMsg,
        ])
        ->add('errorMsg', TextType::class, [
            'mapped' => false,
            'required' => false,
            'label' => '',
            'data' => $errorMsg,
        ])
        ->add('api-id', TextType::class, [
            'required' => true,
            'label' => 'Identifiant de l\'api',
            'data' => $apiId,
            'constraints' => [
                new Constraints\NotBlank(),
            ],
        ])
        ->add('api-password', TextType::class, [
            'required' => true,
            'label' => 'Mot de passe api',
            'data' => $apiPassword,
            'constraints' => [
                new Constraints\NotBlank(),
            ],
            'attr' => [
                'class' => 'password',
            ],
        ])
        ->add('real-time-synchronization', CheckboxType::class, [
            'required' => false,
            'label' => 'Utiliser la synchronisation en temps réel',
            'data' => (bool) $realTimeSynchronization,
        ])
        ->add('nominative-reductions', CheckboxType::class, [
            'required' => false,
            'label' => 'Générer des codes de réduction nominatifs',
            'data' => (bool) $nominativeReductions,
        ])
        ->add('cumulative-vouchers', CheckboxType::class, [
            'required' => false,
            'label' => 'Codes de réduction cumulables avec d\'autre codes ?',
            'data' => (bool) $cumulativeVouchers,
        ])
        ->add('out-of-stock-product-disabling', CheckboxType::class, [
            'required' => false,
            'label' => 'Désactiver les produits en rupture de stock',
            'data' => (bool) $outOfStockProductDisabling,
        ])
        ->add('script-tag', CheckboxType::class, [
            'required' => false,
            'label' => 'Script tag',
            'data' => (bool) $scriptTag,
        ])
        ->add('log', CheckboxType::class, [
            'required' => false,
            'label' => 'Log',
            'data' => (bool) $log,
        ])
        ->add('submit', SubmitType::class, [
            'label' => 'valider',
        ]);
    }

    /**
     * Print Log.
     */
    private function printLog(): string
    {
        $filepath = THELIA_LOG_DIR.'/shopimind.log';
        if (file_exists($filepath)) {
            $f = fopen($filepath, 'r');
            if ($f === false) {
                return false;
            }

            fseek($f, 0, \SEEK_END);
            $output = '';
            $currentLine = 0;

            for ($pos = ftell($f); $pos > 0; --$pos) {
                fseek($f, $pos - 1);
                $char = fread($f, 1);

                if ($char === "\n") {
                    ++$currentLine;
                    if ($currentLine === 20) {
                        break;
                    }
                }
                $output = $char.$output;
            }

            fclose($f);

            return nl2br(htmlspecialchars($output));
        }

        return '';
    }
}
