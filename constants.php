<?php
/**
 * Definitions
 *
 * @author Shopimind <contact@shopimind.com>
 * @copyright Copyright (c) 2013 - IDVIVE SARL (http://www.idvive.com)
 * @license New BSD license (http://license.idvive.com)
 * @package Definitions
 */

return [
    'header' => [
        'client_id' => 'Shopimind-Client-Identifiant',
        'version' => 'Shopimind-Client-Version',
        'build' => 'Shopimind-Client-Build',
        'hmac' => 'Shopimind-Token',
        'promo_code' => 'Shopimind-PromoCode',
    ],
    'promo_code' => '',
    /*
     * URL conventions:
     *   - script_url           : base for browser-side calls (v5.js + ShopiMind routes
     *                            hit from the merchant's browser like /cs). Same
     *                            origin as the injected <script>, so XHR avoid CORS.
     *   - core_api_url         : base for server-to-server ShopiMind API calls (sync,
     *                            RT, passive) issued by the SDK. Not exposed to the browser.
     *   - preview_api_url      : email/template preview backend.
     *   - preview_ws_url       : WebSocket endpoint paired with preview_api_url.
     *   - register_action_url  : merchant signup URL surfaced by admin notices/forms.
     */
    'api' => [
        'salt' => 'zPz<j.[EaX,]$s3gzkCW;^N.2',
        'script_url' => 'https://v2.app-spm.com',
        'core_api_url' => 'https://core.shopimind.com',
        'preview_api_url' => 'https://v5.shopimind.com',
        'preview_ws_url' => 'wss://v5.shopimind.com/template/spm-preview',
        'register_action_url' => 'https://my.shopimind.com/auth/registration/speed',
    ],
    'sync' => [
        'source_label' => 'Web',
    ],
    'support_email' => 'contact@shopimind.com',
    'module' => [
        'name' => 'shopimind',
        'version' => '1.1.0',
        'client_version' => '5.0.0',
        'author' => 'ShopiMind',
    ],
];
