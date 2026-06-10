<?php

namespace Shopimind\Hook;

use Shopimind\Form\ShopimindForm;
use Shopimind\lib\Utils;
use Shopimind\Model\Base\ShopimindQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Country;
use Thelia\Model\OrderQuery;

class ShopimindHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
            'main.content-bottom' => [
                ['type' => 'front', 'method' => 'addScriptTagFooter'],
            ],
        ];
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $config = ShopimindQuery::create()->findOne();

        $request = $this->getRequest();
        $successMsg = '';
        $errorMsg = '';
        if ($request !== null && $request->hasSession()) {
            $flashBag = $request->getSession()->getFlashBag();
            foreach ($flashBag->get('success') as $message) {
                $successMsg = $message;
            }
            foreach ($flashBag->get('error') as $message) {
                $errorMsg = $message;
            }
        }

        $form = $this->formFactory->createForm(ShopimindForm::getName());
        $formView = $form->createView()->getView();

        $event->add($this->render('Shopimind/module_configuration.html.twig', [
            'form' => $formView,
            'isConnected' => $config !== null && $config->getIsConnected(),
            'logView' => $this->getLogContent(),
            'successMsg' => $successMsg,
            'errorMsg' => $errorMsg,
        ]));
    }

    /**
     * Add script tag to front-office footer.
     */
    public function addScriptTagFooter(HookRenderEvent $event): void
    {
        $config = ShopimindQuery::create()->findOne();

        if (empty($config) || !$config->getIsConnected() || !$config->getScriptTag()) {
            return;
        }

        $request = $this->getRequest();
        if ($request === null) {
            return;
        }

        $country = Country::getShopLocation();

        $url = $request->getUri();
        $url = str_replace('https://', '//', $url);
        $url = str_replace('http://', '//', $url);

        $spmIdent = $config->getApiId();
        $productId = $request->query->get('product_id');
        $idCategory = '';
        $idManufacturer = '';

        $user = $this->getCustomer() ? ['customer_id' => $this->getCustomer()->getId()] : null;
        $customerId = $this->getCustomer() ? $this->getCustomer()->getId() : null;

        $query = ((array) $request->query);
        foreach ($query as $value) {
            $idCategory = array_key_exists('category_id', $value) ? $value['category_id'] : null;
            $idManufacturer = array_key_exists('brand_id', $value) ? $value['brand_id'] : null;
        }

        $cart = $this->getCart();
        $idCart = $cart->getId();

        $confirm_cart_to_order = '';
        if ($idCart) {
            $order = OrderQuery::create()->findOneByCartId($idCart);
            $confirm_cart_to_order = !empty($order) ? $idCart . ';' . $order->getId() : null;
        }

        $currentUserInfos = [
            'url' => $url,
            'id_product' => $productId,
            'id_category' => $idCategory,
            'id_manufacturer' => $idManufacturer,
            'spm_ident' => $spmIdent,
            'user' => $user,
            'id_cart' => $idCart,
            'cart' => [
                'id_customer' => !empty($idCart) ? $cart->getCustomerId() : null,
                'id_cart' => $idCart,
                'date_add' => !empty($idCart) ? $cart->getCreatedAt()->format('Y-m-d\TH:i:s.u\Z') : null,
                'date_upd' => !empty($idCart) ? $cart->getUpdatedAt()->format('Y-m-d\TH:i:s.u\Z') : null,
                'amount' => !empty($idCart) ? $cart->getTaxedAmount($country) : null,
                'tax_rate' => !empty($idCart) ? $cart->getCurrency()->getCurrentTranslation() : null,
                'currency' => !empty($idCart) ? $cart->getCurrency()->getCode() : null,
                'voucher_used' => null,
                'voucher_amount' => null,
                'products' => !empty($idCart) ? self::getProducts($cart->getId()) : null,
            ],
            'confirm_cart_to_order' => $confirm_cart_to_order,
        ];

        $base_url = 'https://app-spm.com/app.js';
        $currentUserInfos = json_encode($currentUserInfos);
        $src = $base_url . '?url=' . $url . '&id_product=' . $productId . '&id_customer=' . $customerId . '&id_category=' . $idCategory . '&id_manufacturer=' . $idManufacturer . '&spm_ident=' . $spmIdent;
        $event->add(
            <<<HTML
            <script>
                var _spmq = $currentUserInfos;
                var _spm_id_combination = document.getElementById('pse-id') ? document.getElementById('pse-id').value : '';
                (function() {
                    document.addEventListener('DOMContentLoaded', function() {
                        var spm = document.createElement('script');
                        spm.type = 'text/javascript';
                        spm.defer = true;
                        spm.src = '$src&id_combination='+_spm_id_combination;
                        var head = document.head;
                        head.appendChild( spm );
                    });
                })();
            </script>
        HTML
        );
    }

    /**
     * Retrieves cart products.
     */
    public static function getProducts(int $cartId): array
    {
        $response = [];

        $country = Country::getShopLocation();

        $cartItems = CartItemQuery::create()
            ->filterByCartId($cartId)
            ->find();

        foreach ($cartItems as $cartItem) {
            $response[] = [
                'id_product' => $cartItem->getProductId(),
                'id_combination' => $cartItem->getProductSaleElementsId(),
                'id_manufacturer' => $cartItem->getProduct()->getBrandId(),
                'qty' => $cartItem->getQuantity(),
                'price' => $cartItem->getRealTaxedPrice($country),
                'price_without_tax' => Utils::formatNumber($cartItem->getRealPrice()),
            ];
        }

        return $response;
    }

    /**
     * Read the last lines of the module log file.
     */
    private function getLogContent(int $lines = 20): string
    {
        $filepath = THELIA_MODULE_DIR . '/Shopimind/logs/module.log';
        if (!file_exists($filepath)) {
            return '';
        }

        $f = fopen($filepath, 'rb');
        if ($f === false) {
            return '';
        }

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

        return $output;
    }
}
