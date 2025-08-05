<?php
namespace Shopimind\Form;

use Shopimind\Model\Base\ShopimindQuery;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints;
use Thelia\Form\BaseForm;
use Symfony\Component\HttpFoundation\Session\Session;
use Shopimind\lib\Utils;
use Thelia\Model\Base\OrderStatusQuery;
use Thelia\Model\OrderStatusI18nQuery;
use Thelia\Core\Translation\Translator;


/**
 * Class ShopimindForm.
 *
 * @author shopimind
 */
class ShopimindForm extends BaseForm
{
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

        $paidStatus = OrderStatusQuery::create()->findOneByCode('paid');
        $paidStatusId = ( !empty( $paidStatus ) ) ? $paidStatus->getId() : null;
        $confirmedStatuses = !empty($config) && !empty($config->getConfirmedStatuses())
            ? json_decode($config->getConfirmedStatuses(), true)
            : (!empty($paidStatusId) ? [$paidStatusId] : []);

        $session = new Session();
        $flashbag = $session->getFlashBag();
        $successMsg = "";
        foreach ( $flashbag->get('success') as $message ) {
            $successMsg = $message;
        }
        $errorMsg = "";
        foreach ( $flashbag->get('error') as $message ) {
            $errorMsg = $message;
        }

        $isNotConnected = "";
        if ( !Utils::isConnected() ) {
            $isNotConnected = "Your module is not connected.";
        }
        
        $orderStatuses = OrderStatusQuery::create()->find();
        $statusChoices = [];
        $lang = $this->getRequest()->getSession()->getLang();
        foreach ($orderStatuses as $status) {
            $statusI18n = OrderStatusI18nQuery::create()
                ->filterByLocale($lang->getLocale())
                ->filterById($status->getId())
                ->findOne();
            
            $title = $statusI18n ? $statusI18n->getTitle() : $status->getCode();
            $statusChoices[$title] = $status->getId();
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
        ->add('confirmed-statuses', ChoiceType::class, [
            'required' => false,
            'label' => 'Statuts de commande confirmés',
            'choices' => $statusChoices,
            'multiple' => true,
            'expanded' => true,
            'data' => $confirmedStatuses,
        ])
        ->add('submit', SubmitType::class, [
            'label' => 'valider',
        ]);
    }

    private function printLog( $lines = 20 )
    {
        $filepath = THELIA_MODULE_DIR . '/Shopimind/logs/module.log';
        if ( file_exists( $filepath ) ) {
            $f = fopen($filepath, "rb");
            if ($f === false) return false;

            fseek($f, 0, SEEK_END);
            $output = '';
            $currentLine = 0;

            for ($pos = ftell($f); $pos > 0; $pos--) {
                fseek($f, $pos - 1);
                $char = fread($f, 1);

                if ($char === "\n") {
                    $currentLine++;
                    if ($currentLine === $lines) {
                        break;
                    }
                }
                $output = $char . $output;
            }

            fclose($f);

            $output = nl2br(htmlspecialchars($output));

            return $output;
        }
    }
}
