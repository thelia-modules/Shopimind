<?php

namespace Shopimind\lib;

/**
 * HTTP Client with retry mechanism for ShopiMind SDK calls.
 * Compatible with Thelia
 */
class SyncHttpClient
{
    /**
     * Maximum number of retry attempts
     */
    const MAX_RETRIES = 3;

    /**
     * Base delay between retries in seconds
     */
    const BASE_RETRY_DELAY = 1;

    /**
     * HTTP timeout in seconds
     */
    const HTTP_TIMEOUT = 30;

    /**
     * Retryable HTTP status codes
     */
    const RETRYABLE_STATUS_CODES = [408, 429, 500, 502, 503, 504];

    /**
     * Execute a callable with retry mechanism
     *
     * @param callable $operation The operation to execute
     * @param string $context Context for logging (e.g., 'SyncCustomers')
     * @param int $maxRetries Maximum number of retries (default: MAX_RETRIES)
     * @return array Response from the operation
     */
    public static function executeWithRetry(callable $operation, $context = '', $maxRetries = null)
    {
        $maxRetries = $maxRetries !== null ? $maxRetries : self::MAX_RETRIES;
        $lastException = null;
        $lastResponse = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $operation();

                // Check if response indicates success
                if (self::isSuccessResponse($response)) {
                    return $response;
                }

                // Check if response status code is retryable
                if (self::isRetryableResponse($response)) {
                    $lastResponse = $response;
                    self::logRetry($context, $attempt, $maxRetries, 'Retryable status code: ' . ($response['statusCode'] ?? 'unknown'));
                    
                    if ($attempt < $maxRetries) {
                        self::waitBeforeRetry($attempt);
                        continue;
                    }
                }

                // Non-retryable error, return response as-is
                return $response;

            } catch (\Throwable $e) {
                $lastException = $e;

                // Check if exception is retryable (network errors, timeouts)
                if ($e instanceof \Exception && self::isRetryableException($e)) {
                    self::logRetry($context, $attempt, $maxRetries, $e->getMessage());

                    if ($attempt < $maxRetries) {
                        self::waitBeforeRetry($attempt);
                        continue;
                    }
                } else {
                    // Non-retryable exception, throw immediately
                    throw $e;
                }
            }
        }

        // All retries exhausted
        if ($lastException !== null) {
            self::logError($context, 'All retries exhausted', $lastException->getMessage());
            return [
                'statusCode' => 0,
                'success' => false,
                'message' => 'All retries exhausted: ' . $lastException->getMessage(),
                'retries' => $maxRetries
            ];
        }

        if ($lastResponse !== null) {
            self::logError($context, 'All retries exhausted', 'Last status code: ' . ($lastResponse['statusCode'] ?? 'unknown'));
            return $lastResponse;
        }

        return [
            'statusCode' => 0,
            'success' => false,
            'message' => 'Unknown error after retries',
            'retries' => $maxRetries
        ];
    }

    /**
     * Check if response indicates success
     *
     * @param mixed $response
     * @return bool
     */
    private static function isSuccessResponse($response)
    {
        if (!is_array($response)) {
            return false;
        }

        $statusCode = isset($response['statusCode']) ? (int)$response['statusCode'] : 0;
        return $statusCode >= 200 && $statusCode < 300;
    }

    /**
     * Check if response status code is retryable
     *
     * @param mixed $response
     * @return bool
     */
    private static function isRetryableResponse($response)
    {
        if (!is_array($response)) {
            return false;
        }

        $statusCode = isset($response['statusCode']) ? (int)$response['statusCode'] : 0;
        return in_array($statusCode, self::RETRYABLE_STATUS_CODES);
    }

    /**
     * Check if exception is retryable (network errors, timeouts)
     *
     * @param \Exception $e
     * @return bool
     */
    private static function isRetryableException(\Throwable $e)
    {
        $message = strtolower($e->getMessage());
        
        $retryablePatterns = [
            'timeout',
            'timed out',
            'connection refused',
            'connection reset',
            'could not resolve host',
            'network is unreachable',
            'ssl',
            'curl error',
            'failed to connect',
            'operation timed out',
            'connection timed out',
        ];

        foreach ($retryablePatterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wait before retry with exponential backoff
     *
     * @param int $attempt Current attempt number
     */
    private static function waitBeforeRetry($attempt)
    {
        // Exponential backoff: 1s, 2s, 4s, ...
        $delay = self::BASE_RETRY_DELAY * pow(2, $attempt - 1);
        
        // Add jitter (random 0-500ms) to prevent thundering herd
        $jitter = mt_rand(0, 500) / 1000;
        
        // Cap at 10 seconds
        $delay = min($delay + $jitter, 10);
        
        // Use usleep for sub-second precision
        usleep((int)($delay * 1000000));
    }

    /**
     * Log retry attempt
     *
     * @param string $context
     * @param int $attempt
     * @param int $maxRetries
     * @param string $reason
     */
    private static function logRetry($context, $attempt, $maxRetries, $reason)
    {
        $message = sprintf(
            '[%s] Retry attempt %d/%d - Reason: %s',
            $context,
            $attempt,
            $maxRetries,
            $reason
        );
        
        Utils::log('customers', 'HTTP Retry - ' . $context, $message);
    }

    /**
     * Log error
     *
     * @param string $context
     * @param string $error
     * @param string $details
     */
    private static function logError($context, $error, $details)
    {
        $message = sprintf('[%s] %s - %s', $context, $error, $details);
        Utils::log('customers', 'HTTP Error - ' . $context, $message);
    }

    /**
     * Batch execute with retry - processes items individually and aggregates results
     *
     * @param array $items Items to process
     * @param callable $operation Operation to execute for each item (receives item, returns response)
     * @param string $context Context for logging
     * @param int $batchSize Number of items per batch (for bulk operations)
     * @return array Aggregated results with success/failure counts
     */
    public static function batchExecuteWithRetry(array $items, callable $operation, $context = '', $batchSize = 20)
    {
        $results = [
            'success' => true,
            'total_count' => count($items),
            'sent_count' => 0,
            'failed_count' => 0,
            'errors' => [],
        ];

        if (empty($items)) {
            return $results;
        }

        // Process in batches
        $batches = array_chunk($items, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            try {
                $response = self::executeWithRetry(
                    function() use ($operation, $batch) {
                        return $operation($batch);
                    },
                    $context . ' (batch ' . ($batchIndex + 1) . ')'
                );

                if (self::isSuccessResponse($response)) {
                    $results['sent_count'] += count($batch);
                } else {
                    $results['failed_count'] += count($batch);
                    $results['success'] = false;
                    $results['errors'][] = [
                        'batch_index' => $batchIndex,
                        'count' => count($batch),
                        'error' => isset($response['message']) ? $response['message'] : 'Unknown error',
                        'status_code' => isset($response['statusCode']) ? $response['statusCode'] : 0,
                    ];
                }
            } catch (\Exception $e) {
                $results['failed_count'] += count($batch);
                $results['success'] = false;
                $results['errors'][] = [
                    'batch_index' => $batchIndex,
                    'count' => count($batch),
                    'error' => $e->getMessage(),
                    'status_code' => 0,
                ];
            }
        }

        return $results;
    }

    /**
     * Extract sent_count and rejected_count from SDK response.
     * Handles multiple response structures:
     *   - $response['body']['sent_count']         (body is array)
     *   - $response['body'] is JSON string         (body needs decoding)
     *   - $response['sent_count']                  (flat response)
     *
     * @param array $response   SDK response
     * @param int   $fallback   Fallback count (chunk size)
     * @return array{sent_count: int, rejected_count: int}
     */
    public static function extractCounters($response, $fallback)
    {
        $sentCount = null;
        $rejectedCount = null;

        // 1. Try body (array)
        if (isset($response['body']) && is_array($response['body'])) {
            $sentCount = $response['body']['sent_count'] ?? null;
            $rejectedCount = $response['body']['rejected_count'] ?? null;
        }

        // 2. Try body (JSON string)
        if ($sentCount === null && isset($response['body']) && is_string($response['body'])) {
            $decoded = json_decode($response['body'], true);
            if (is_array($decoded)) {
                $sentCount = $decoded['sent_count'] ?? null;
                $rejectedCount = $decoded['rejected_count'] ?? null;
            }
        }

        // 3. Try flat response (sent_count at top level)
        if ($sentCount === null) {
            $sentCount = $response['sent_count'] ?? null;
            $rejectedCount = $response['rejected_count'] ?? null;
        }

        return [
            'sent_count' => $sentCount !== null ? (int)$sentCount : $fallback,
            'rejected_count' => $rejectedCount !== null ? (int)$rejectedCount : 0,
        ];
    }
}
