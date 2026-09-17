<?php

namespace Shopimind\Workers;

use Symfony\Component\HttpFoundation\Response;

class SpmCart
{
    public static function serveFile()
    {
        $filePath = __DIR__ . '/Scripts/spm-cart.js';

        if (file_exists($filePath)) {
            $response = new Response(file_get_contents($filePath));
            $response->headers->set('Content-Type', 'application/javascript');
            return $response;
        }

        return new Response('File not found', 404);
    }
}
