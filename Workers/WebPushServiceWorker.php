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

namespace Shopimind\Workers;

use Symfony\Component\HttpFoundation\Response;

class WebPushServiceWorker
{
    /**
     * @return Response
     */
    public static function serveFile(): Response
    {
        $filePath = \dirname(__DIR__).'/Workers/Scripts/web-push-service-worker.js';

        if (file_exists($filePath)) {
            $response = new Response(file_get_contents($filePath));
            $response->headers->set('Content-Type', 'application/javascript');

            return $response;
        } else {
            return new Response('File not found', 404);
        }
    }
}
