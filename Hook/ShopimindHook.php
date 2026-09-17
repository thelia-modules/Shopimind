<?php

namespace Shopimind\Hook;

use Shopimind\lib\SpmTag;
use Shopimind\lib\Utils;
use Shopimind\Model\Base\ShopimindQuery;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\OrderQuery;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Tools\URL;

class ShopimindHook extends BaseHook
{
    /**
     * Vues du tunnel où l'option « Masquer le tag dans le tunnel de commande » s'applique : livraison et
     * facturation, dans les gabarits default et modern de Thelia 2.5. La page panier reste suivie.
     */
    const CHECKOUT_VIEWS = [ 'order-delivery', 'order-invoice' ];

    /** Page de paiement rendue par un module de paiement pendant /order/pay, sans attribut _view. */
    const CHECKOUT_ROUTES = [ 'order.payment.process' ];

    /** Injection unique par page, que le tag soit rendu par main.head-bottom ou par main.body-bottom. */
    protected static $alreadyDisplayed = false;

    /** Moteur de taxes de Thelia (service thelia.taxEngine), injecté par Config/config.xml. */
    protected $taxEngine = null;

    /**
     * Montants TTC du panier calculés avec le pays de taxe du front. Paramètre non typé : un service absent ou
     * inattendu laisse le calcul de repli au lieu d'empêcher la création du hook.
     *
     * @param TaxEngine|null $taxEngine
     */
    public function setTaxEngine( $taxEngine = null ): void
    {
        $this->taxEngine = $taxEngine instanceof TaxEngine ? $taxEngine : null;
    }

    /**
     * Injection du tag ShopiMind.
     *
     * @param HookRenderEvent $event
     */
    public function addScriptTag( HookRenderEvent $event ){
        try {
            $this->_addScriptTag($event);
        } catch (\Throwable $e) {
            Utils::handleHookException(static::class . '::addScriptTag', $e);
        }
    }

    protected function _addScriptTag( HookRenderEvent $event ){
        if ( self::$alreadyDisplayed ) return;

        $config = ShopimindQuery::create()->findOne();

        if ( empty( $config ) || !$config->getIsConnected() || !$config->getScriptTag() ) return ;

        $request = $this->getRequest();
        if ( Utils::hideTagOnCheckout() && self::isCheckoutPage( $request ) ) return;

        self::$alreadyDisplayed = true;

        $cacheWorkaround = Utils::useCacheWorkaround();
        $baseUrl = rtrim( (string) Utils::getScriptUrl(), '/' );
        $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $urlFlags = $jsonFlags | JSON_UNESCAPED_SLASHES;

        // Données du tag (_spmq et paramètres de v5.js). Avec le contournement du cache, le client et le panier
        // n'y figurent pas : /shopimind/spmq les fournit.
        $spmUserInfos = SpmTag::getCurrentUserInfos(
            $request,
            $cacheWorkaround ? null : $this->getCart(),
            true,
            self::getConfirmCartToOrder( $request ),
            $this->taxEngine
        );

        // JSON encodé avec JSON_HEX_* et paramètres de v5.js encodés par http_build_query : les paramètres
        // venus de l'URL ne peuvent pas sortir des chaînes JavaScript.
        $spmUserInfosJson = json_encode( $spmUserInfos, $jsonFlags );
        $v5UrlJson = json_encode( $baseUrl . '/v5.js?' . http_build_query( $spmUserInfos ), $urlFlags );
        $cartDataUrlJson = json_encode( URL::getInstance()->absoluteUrl( '/shopimind/cart-data' ), $urlFlags );
        $csUrlJson = json_encode( $baseUrl . '/cs', $urlFlags );
        $spmqUrlJson = json_encode( URL::getInstance()->absoluteUrl( '/shopimind/spmq' ), $urlFlags );
        $cartScript = self::getCartScript();
        $cartScriptTag = $cartScript !== '' ? "<script type=\"text/javascript\">\n" . $cartScript . "</script>" : '';

        // v5.js est chargé une fois le DOM prêt, pour lire la déclinaison choisie sur la fiche produit.
        if ( $cacheWorkaround ) {
            $script = <<<HTML
<script type="text/javascript">
    var _spmq = {};
    var _spmq_nc = $spmUserInfosJson;
    _spmq_nc.url = window.location.href;

    window.SpmCartConfig = {
        dataUrl: $cartDataUrlJson,
        csUrl: $csUrlJson
    };

    var _spm_id_combination = function() {
        var element = document.getElementById('pse-id');
        return element ? element.value : '';
    };
    (function() {
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange = function() {
            if (xmlhttp.readyState !== 4 || xmlhttp.status !== 200) return;
            var data, _spmq_c;
            try {
                data = JSON.parse(xmlhttp.responseText);
                _spmq_c = JSON.parse(data.spm_user_infos);
            } catch (err) {
                return;
            }
            Object.keys(_spmq_nc).forEach(function(key) { _spmq[key] = _spmq_nc[key]; });
            Object.keys(_spmq_c).forEach(function(key) { _spmq[key] = _spmq_c[key]; });
            var _spmq_encode = data.spm_user_infos_encode;
            var spmLoad = function() {
                var spm = document.createElement('script');
                spm.type = 'text/javascript';
                spm.defer = true;
                spm.src = $v5UrlJson + (typeof _spmq_encode === 'string' && _spmq_encode ? '&' + _spmq_encode : '') + '&url=' + encodeURIComponent(window.location.href) + '&id_combination=' + encodeURIComponent(_spm_id_combination());
                var s_spm = document.getElementsByTagName('script')[0];
                s_spm.parentNode.insertBefore(spm, s_spm);
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', spmLoad);
            } else {
                spmLoad();
            }
        };
        xmlhttp.open('GET', $spmqUrlJson, true);
        xmlhttp.send();
    })();
</script>
$cartScriptTag
HTML;
        } else {
            $script = <<<HTML
<script type="text/javascript">
    var _spmq = $spmUserInfosJson;
    _spmq.url = window.location.href;

    window.SpmCartConfig = {
        dataUrl: $cartDataUrlJson,
        csUrl: $csUrlJson
    };

    var _spm_id_combination = function() {
        var element = document.getElementById('pse-id');
        return element ? element.value : '';
    };
    (function() {
        var spmLoad = function() {
            var spm = document.createElement('script');
            spm.type = 'text/javascript';
            spm.defer = true;
            spm.src = $v5UrlJson + '&url=' + encodeURIComponent(window.location.href) + '&id_combination=' + encodeURIComponent(_spm_id_combination());
            var s_spm = document.getElementsByTagName('script')[0];
            s_spm.parentNode.insertBefore(spm, s_spm);
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', spmLoad);
        } else {
            spmLoad();
        }
    })();
</script>
$cartScriptTag
HTML;
        }

        $event->add( $script );
    }

    /**
     * Script de synchronisation du panier (Workers/Scripts/spm-cart.js), inclus dans le tag : avec la configuration nginx
     * recommandée par Thelia, une URL en .js est servie comme un fichier statique et répond 404 sans passer par Thelia.
     * Vide si le fichier est absent ou contient une balise de fermeture de script.
     *
     * @return string
     */
    protected static function getCartScript(): string
    {
        $path = dirname( __DIR__ ) . '/Workers/Scripts/spm-cart.js';
        $script = is_file( $path ) ? file_get_contents( $path ) : false;

        if ( !is_string( $script ) || stripos( $script, '</script' ) !== false ) {
            return '';
        }

        return rtrim( $script ) . "\n";
    }

    /**
     * Page du tunnel de commande : vues de livraison et de facturation, ou page de paiement rendue pendant
     * /order/pay.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return bool
     */
    public static function isCheckoutPage( $request ): bool
    {
        return in_array( (string) $request->attributes->get( '_view', '' ), self::CHECKOUT_VIEWS, true )
            || in_array( (string) $request->attributes->get( '_route', '' ), self::CHECKOUT_ROUTES, true );
    }

    /**
     * « id_panier;id_commande » sur la page de confirmation (route order.placed, vue order-placed). Thelia
     * vide le panier de session avant d'afficher cette page : la commande est relue par son identifiant, et
     * le contrôleur de Thelia a déjà vérifié qu'elle appartient au client connecté.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return string|null
     */
    public static function getConfirmCartToOrder( $request ): ?string
    {
        $isOrderPlaced = $request->attributes->get( '_view' ) === 'order-placed'
            || $request->attributes->get( '_route' ) === 'order.placed';
        $orderId = (int) $request->attributes->get( 'order_id' );

        if ( !$isOrderPlaced || $orderId <= 0 ) {
            return null;
        }

        $order = OrderQuery::create()->findPk( $orderId );

        return !empty( $order ) ? $order->getCartId() . ';' . $order->getId() : null;
    }
}
