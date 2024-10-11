<?php

namespace Shopimind\Workers;

use Symfony\Component\HttpFoundation\Response;

class Platform
{
    public static function serveFile(){
        $filePath = THELIA_MODULE_DIR. '/Shopimind/Workers/Scripts/platform.js';

        if ( file_exists( $filePath ) ) {
            $response = new Response( file_get_contents( $filePath ) );
            $response->headers->set('Content-Type', 'application/javascript');
            return $response;
        } else {
            return new Response('File not found', 404);
        }
    }
}