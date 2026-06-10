<?php
namespace Shopimind\Form;

use Shopimind\Model\Base\ShopimindQuery;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints;
use Thelia\Form\BaseForm;


/**
 * Class ShopimindForm.
 *
 * @author shopimind
 */
class ShopimindForm extends BaseForm
{
    public static function getName(): string
    {
        return 'shopimind_form_shopimind_form';
    }

    protected function buildForm(): void
    {
        $config = ShopimindQuery::create()->findOne();
        
        $apiId = !empty($config) ? $config->getApiId() : "";
        $apiPassword = !empty($config) ? $config->getApiPassword() : "";
        $realTimeSynchronization = !empty($config) ? $config->getRealTimeSynchronization() : "";
        $nominativeReductions = !empty($config) ? $config->getNominativeReductions() : "";
        $cumulativeVouchers = !empty($config) ? $config->getCumulativeVouchers() : "";
        $outOfStockProductDisabling = !empty($config) ? $config->getOutOfStockProductDisabling() : "";
        $scriptTag = !empty($config) ? $config->getScriptTag() : 1;
        $log = !empty($config) ? $config->getLog() : "";

        $this->formBuilder
        ->add('api-id', TextType::class, [
            'required' => true,
            'label' => 'Identifiant de l\'api',
            'data' => $apiId,
            'constraints' => [
                new Constraints\NotBlank(),
                // new Constraints\Callback(
                //     [$this, 'verifyApiId']
                // ),
            ],
        ])
        ->add('api-password', TextType::class, [
            'required' => true,
            'label' => 'Mot de passe api',
            'data' => $apiPassword,
            'constraints' => [
                new Constraints\NotBlank(),
                // new Constraints\Callback(
                //     [$this, 'verifyApiId']
                // ),
            ],
            'attr' => [
                'class' => 'password'
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


}
