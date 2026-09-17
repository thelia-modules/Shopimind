<?php

namespace Shopimind\Controller;

require_once __DIR__ . '/../vendor-module/autoload.php';

use Shopimind\lib\SpmLogs;
use Shopimind\lib\Utils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Front\BaseFrontController;

/**
 * Lecture et purge du journal du module par ShopiMind, quand les logs sont activés.
 *
 * Route: POST /shopimind/logs
 */
class SpmLogsController extends BaseFrontController
{
    public function handleRequest( Request $request ): JsonResponse
    {
        $body = Utils::getSpmRequestBody( $request );
        if ( null !== $unauthorized = Utils::authorizeSpmRequest( $request, $body ) ) {
            return self::noStore( $unauthorized );
        }

        try {
            if ( !SpmLogs::isFresh( $body ) ) {
                Utils::logWarn( 'Support', 'Logs', 'expired request refused' );

                return self::noStore( new JsonResponse( [
                    'success' => false,
                    'message' => 'Request expired.',
                    'server_time' => date( DATE_ATOM ),
                ], 401 ) );
            }

            if ( !SpmLogs::isEnabled() ) {
                return self::noStore( new JsonResponse( [
                    'success' => false,
                    'message' => 'Logs are disabled.',
                ], 403 ) );
            }

            $action = ( isset( $body['action'] ) && is_string( $body['action'] ) ) ? $body['action'] : 'get_logs';

            switch ( $action ) {
                case 'get_logs':
                    $lines = ( isset( $body['lines'] ) && is_scalar( $body['lines'] ) ) ? (int) $body['lines'] : SpmLogs::DEFAULT_LINES;

                    return self::noStore( new JsonResponse( [ 'success' => true ] + SpmLogs::read( $lines ) ) );
                case 'clear_logs':
                    return self::noStore( new JsonResponse( [
                        'success' => true,
                        'cleared_bytes' => SpmLogs::clear(),
                    ] ) );
                default:
                    return self::noStore( new JsonResponse( [
                        'success' => false,
                        'message' => 'Action not supported. Available actions: get_logs, clear_logs',
                    ], 400 ) );
            }
        } catch ( \Throwable $e ) {
            return self::noStore( Utils::spmErrorResponse( 'Support', 'Logs', $e ) );
        }
    }

    protected static function noStore( JsonResponse $response ): JsonResponse
    {
        $response->headers->set( 'Cache-Control', 'no-store, private' );

        return $response;
    }
}
