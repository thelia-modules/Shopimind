<?php

namespace Shopimind\lib;

use Shopimind\Model\Base\ShopimindQuery;

/**
 * Journal du module : rotation, lecture de la fin du fichier et purge, utilisées par la route /shopimind/logs.
 */
class SpmLogs
{
    /** Au-delà, le fichier devient shopimind.log.1 (une seule archive conservée). */
    const MAX_FILE_BYTES = 10485760;

    const DEFAULT_LINES = 500;

    const MAX_LINES = 5000;

    /** Volume maximal renvoyé par un appel, archive comprise. */
    const MAX_READ_BYTES = 1048576;

    /** Écart toléré, en secondes, entre l'horodatage signé testConnection et l'heure du serveur. */
    const MAX_REQUEST_AGE = 900;

    public static function isEnabled(): bool
    {
        return Utils::getLogLevel() > Utils::LOG_LEVEL_OFF;
    }

    /**
     * Horodatage signé récent : une requête interceptée ne peut pas être rejouée plus tard.
     *
     * @param array    $body
     * @param int|null $now
     * @return bool
     */
    public static function isFresh( array $body, ?int $now = null ): bool
    {
        $timestamp = ( isset( $body['testConnection'] ) && is_scalar( $body['testConnection'] ) ) ? (string) $body['testConnection'] : '';
        if ( $timestamp === '' || !ctype_digit( $timestamp ) ) {
            return false;
        }

        return abs( ( $now ?? time() ) - (int) $timestamp ) <= self::MAX_REQUEST_AGE;
    }

    public static function rotateIfNeeded( string $file ): void
    {
        if ( self::size( $file ) >= self::MAX_FILE_BYTES ) {
            @rename( $file, $file . '.1' );
        }
    }

    /**
     * Dernières lignes du journal, complétées par la fin de l'archive si le fichier courant n'en contient pas assez.
     *
     * @param int $lines
     * @return array
     */
    public static function read( int $lines = self::DEFAULT_LINES ): array
    {
        $lines = max( 1, min( self::MAX_LINES, $lines ) );
        $file = Utils::resolveLogFile();
        $collected = [];
        $truncated = false;
        $budget = self::MAX_READ_BYTES;

        foreach ( $file === null ? [] : [ $file, $file . '.1' ] as $path ) {
            if ( self::size( $path ) === 0 ) {
                continue;
            }
            if ( count( $collected ) >= $lines || $budget <= 0 ) {
                $truncated = true;
                break;
            }
            $tail = self::tail( $path, $lines - count( $collected ), $budget );
            $collected = array_merge( $tail['lines'], $collected );
            $budget -= $tail['bytes'];
            if ( $tail['truncated'] ) {
                $truncated = true;
                break;
            }
        }

        return [
            'log_level' => Utils::getLogLevel(),
            'file' => $file !== null ? basename( $file ) : null,
            'size' => $file !== null ? self::size( $file ) : 0,
            'archive_size' => $file !== null ? self::size( $file . '.1' ) : 0,
            'updated_at' => ( $file !== null && is_file( $file ) ) ? date( DATE_ATOM, (int) filemtime( $file ) ) : null,
            'lines' => count( $collected ),
            'truncated' => $truncated,
            'content' => self::redact( Utils::toUtf8( implode( "\n", $collected ) ) ),
        ];
    }

    /**
     * Vide le fichier courant et supprime l'archive. La purge est elle-même journalisée.
     *
     * @return int octets supprimés
     */
    public static function clear(): int
    {
        $file = Utils::resolveLogFile();
        if ( $file === null ) {
            return 0;
        }

        $cleared = self::size( $file ) + self::size( $file . '.1' );
        if ( is_file( $file . '.1' ) && !@unlink( $file . '.1' ) ) {
            throw new \RuntimeException( 'The log archive could not be deleted.' );
        }
        if ( is_file( $file ) && false === @file_put_contents( $file, '', LOCK_EX ) ) {
            throw new \RuntimeException( 'The log file could not be cleared.' );
        }
        clearstatcache();

        Utils::logInfo( 'Support', 'Logs', 'log file cleared by ShopiMind', [ 'cleared_bytes' => $cleared ] );

        return $cleared;
    }

    /**
     * Masque le mot de passe API et sa partie secrète s'ils apparaissent dans le journal.
     *
     * @param string $content
     * @return string
     */
    public static function redact( string $content ): string
    {
        try {
            $config = ShopimindQuery::create()->findOne();
            $apiPassword = !empty( $config ) ? (string) $config->getApiPassword() : '';
        } catch ( \Throwable $e ) {
            $apiPassword = '';
        }

        $parts = explode( '.', $apiPassword );
        $secrets = array_values( array_filter( array_unique( [ $apiPassword, isset( $parts[1] ) ? $parts[1] : '' ] ), function ( $secret ) {
            return strlen( $secret ) >= 8;
        } ) );
        usort( $secrets, function ( $a, $b ) {
            return strlen( $b ) - strlen( $a );
        } );

        return empty( $secrets ) ? $content : str_replace( $secrets, '[redacted]', $content );
    }

    /**
     * @param string $path
     * @param int    $maxLines
     * @param int    $maxBytes
     * @return array lines, bytes lus, truncated
     */
    protected static function tail( string $path, int $maxLines, int $maxBytes ): array
    {
        $size = self::size( $path );
        $handle = ( $size > 0 && is_readable( $path ) ) ? @fopen( $path, 'rb' ) : false;
        if ( $handle === false ) {
            return [ 'lines' => [], 'bytes' => 0, 'truncated' => false ];
        }

        $buffer = '';
        $position = $size;
        while ( $position > 0 && strlen( $buffer ) < $maxBytes && substr_count( $buffer, "\n" ) <= $maxLines ) {
            $length = (int) min( 65536, $position, $maxBytes - strlen( $buffer ) );
            $position -= $length;
            fseek( $handle, $position );
            $buffer = (string) fread( $handle, $length ) . $buffer;
        }
        fclose( $handle );

        $truncated = $position > 0;
        $text = rtrim( $buffer, "\r\n" );
        $lines = $text === '' ? [] : explode( "\n", $text );
        if ( $truncated && count( $lines ) > 1 ) {
            // Première ligne coupée par la limite de lecture.
            array_shift( $lines );
        }
        if ( count( $lines ) > $maxLines ) {
            $lines = array_slice( $lines, -$maxLines );
            $truncated = true;
        }

        return [
            'lines' => array_map( function ( $line ) {
                return rtrim( $line, "\r" );
            }, $lines ),
            'bytes' => strlen( $buffer ),
            'truncated' => $truncated,
        ];
    }

    protected static function size( string $path ): int
    {
        clearstatcache( true, $path );

        return is_file( $path ) ? (int) @filesize( $path ) : 0;
    }
}
