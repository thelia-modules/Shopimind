<?php
namespace Shopimind\Form;

use Shopimind\Model\Base\ShopimindQuery;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Form\BaseForm;
use Symfony\Component\HttpFoundation\Session\Session;
use Shopimind\lib\ShopConnection;
use Shopimind\lib\Utils;
use Shopimind\lib\SpmLogs;
use Shopimind\Data\CustomersData;
use Thelia\Model\Base\OrderStatusQuery;
use Thelia\Model\OrderStatusI18nQuery;
use Thelia\Model\Base\CustomerTitleQuery;
use Thelia\Model\CustomerTitleI18nQuery;
use Thelia\Model\ConfigQuery;
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
        // Table du module illisible (mise à jour interrompue) : la page s'affiche quand même, module déconnecté.
        try {
            $config = ShopimindQuery::create()->findOne();
        } catch (\Throwable $e) {
            Utils::logException('Admin', 'Configuration', $e);
            $config = null;
        }
        
        $apiId = !empty($config) ? $config->getApiId() : "";
        $apiPassword = !empty($config) ? $config->getApiPassword() : "";
        $realTimeSynchronization = !empty($config) ? $config->getRealTimeSynchronization() : 1;
        $nominativeReductions = !empty($config) ? $config->getNominativeReductions() : "";
        $cumulativeVouchers = !empty($config) ? $config->getCumulativeVouchers() : "";
        $outOfStockProductDisabling = !empty($config) ? $config->getOutOfStockProductDisabling() : "";
        $scriptTag = !empty($config) ? $config->getScriptTag() : 1;
        $logLevel = Utils::getLogLevel();
        $coreApiUrlOverride = Utils::readConfig('shopimind_core_api_url_override');
        $scriptUrlOverride = Utils::readConfig('shopimind_script_url_override');
        $hideOnCheckout = Utils::hideTagOnCheckout();
        $cacheWorkaround = Utils::useCacheWorkaround();

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

        // Module connecté dont la version installée n'a pas encore été déclarée à ShopiMind.
        $reconnectionNeeded = "";
        if ( ShopConnection::needsReconnection() ) {
            $reconnectionNeeded = "ShopiMind has not received the installed version of the module yet. Save the configuration to send it.";
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

        // Build the customer-title choice list once, used by both gender selects.
        $customerTitles = CustomerTitleQuery::create()->orderByPosition()->find();
        $titleChoices = [];
        foreach ($customerTitles as $title) {
            $titleI18n = CustomerTitleI18nQuery::create()
                ->filterByLocale($lang->getLocale())
                ->filterById($title->getId())
                ->findOne();
            $label = ($titleI18n && $titleI18n->getLong()) ? $titleI18n->getLong() : ('#' . $title->getId());
            $titleChoices[$label] = $title->getId();
        }

        // Read the current gender mapping. Stored as JSON in Thelia's
        // generic config table (no schema migration). Shape:
        // { "1": [titleId, ...], "2": [titleId, ...] }
        // Correspondance enregistrée, ou civilités par défaut de Thelia tant qu'elle ne l'a jamais été.
        $genderMapping = CustomersData::getGenderMapping();
        $genderMrTitles = isset($genderMapping['1']) && is_array($genderMapping['1'])
            ? array_map('intval', $genderMapping['1']) : [];
        $genderMmeTitles = isset($genderMapping['2']) && is_array($genderMapping['2'])
            ? array_map('intval', $genderMapping['2']) : [];

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
        ->add('reconnectionNeeded', TextType::class, [
            'mapped' => false,
            'required' => false,
            'label' => '',
            'data' => $reconnectionNeeded,
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
        ->add('hide-on-checkout', CheckboxType::class, [
            'required' => false,
            'label' => 'Masquer le tag dans le tunnel de commande',
            'data' => $hideOnCheckout,
        ])
        ->add('cache-workaround', CheckboxType::class, [
            'required' => false,
            'label' => 'Contournement du cache',
            'data' => $cacheWorkaround,
        ])
        ->add('log-level', ChoiceType::class, [
            'required' => false,
            'label' => 'Niveau de log',
            'choices' => [
                'Off' => Utils::LOG_LEVEL_OFF,
                'Errors only' => Utils::LOG_LEVEL_ERROR,
                'Info (recommended)' => Utils::LOG_LEVEL_INFO,
                'Debug (verbose)' => Utils::LOG_LEVEL_DEBUG,
            ],
            'multiple' => false,
            'expanded' => false,
            'data' => $logLevel,
        ])
        ->add('core-api-url-override', TextType::class, [
            'required' => false,
            'label' => 'URL de l\'API ShopiMind',
            'data' => $coreApiUrlOverride,
            'constraints' => [
                new Constraints\Callback( [ $this, 'checkOverrideUrl' ] ),
            ],
        ])
        ->add('script-url-override', TextType::class, [
            'required' => false,
            'label' => 'URL du script de suivi',
            'data' => $scriptUrlOverride,
            'constraints' => [
                new Constraints\Callback( [ $this, 'checkOverrideUrl' ] ),
            ],
        ])
        ->add('core-api-url', TextType::class, [
            'mapped' => false,
            'required' => false,
            'label' => '',
            'data' => Utils::getCoreApiUrl(),
        ])
        ->add('script-url', TextType::class, [
            'mapped' => false,
            'required' => false,
            'label' => '',
            'data' => Utils::getScriptUrl(),
        ])
        ->add('confirmed-statuses', ChoiceType::class, [
            'required' => false,
            'label' => 'Statuts de commande confirmés',
            'choices' => $statusChoices,
            'multiple' => true,
            'expanded' => true,
            'data' => $confirmedStatuses,
        ])
        ->add('gender-mr-titles', ChoiceType::class, [
            'required' => false,
            'label' => 'Civilités considérées comme Mr',
            'choices' => $titleChoices,
            'multiple' => true,
            'expanded' => false,
            'data' => $genderMrTitles,
        ])
        ->add('gender-mme-titles', ChoiceType::class, [
            'required' => false,
            'label' => 'Civilités considérées comme Mme',
            'choices' => $titleChoices,
            'multiple' => true,
            'expanded' => false,
            'data' => $genderMmeTitles,
        ])
        ->add('submit', SubmitType::class, [
            'label' => 'valider',
        ]);
    }

    /**
     * Surcharge d'URL du mode Debug : vide, ou URL http(s) complète sans identifiants, paramètres ni ancre.
     *
     * @param mixed $value
     * @param ExecutionContextInterface $context
     */
    public function checkOverrideUrl( $value, ExecutionContextInterface $context ): void
    {
        if ( null === Utils::sanitizeOverrideUrl( $value ) ) {
            $context->addViolation( Translator::getInstance()->trans( 'Enter a full URL starting with https:// or http://, without login, query string or anchor.', [], 'shopimind' ) );
        }
    }

    private function printLog( $lines = 20 )
    {
        try {
            $log = SpmLogs::read( $lines );
        } catch ( \Throwable $e ) {
            return '';
        }

        return nl2br( htmlspecialchars( $log['content'] ) );
    }
}
