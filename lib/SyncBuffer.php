<?php

namespace Shopimind\lib;

/**
 * Request-scoped deduplication + batching buffer for real-time sync.
 *
 * Listeners enqueue (type, action, id) tuples; on PHP shutdown the buffer
 * groups entries by (type, action) and invokes a registered handler once
 * per group. Handlers are registered at bootstrap time (EventListeners
 * constructor) as closures that can capture the dispatcher when needed
 * for formatters that require it.
 */
class SyncBuffer
{
    protected static $buffer = [];
    protected static $handlers = [];
    protected static $registered = false;
    protected static $flushing = false;

    public static function registerHandler($type, $action, $handler)
    {
        self::$handlers[$type . ':' . $action] = $handler;
    }

    public static function enqueue($type, $action, $id, $meta = [])
    {
        if (self::$flushing) {
            return;
        }
        $key = $type . ':' . $action . ':' . $id;
        $metaArr = is_array($meta) ? $meta : [];
        if (isset(self::$buffer[$key])) {
            if (!empty($metaArr)) {
                self::$buffer[$key]['meta'] = array_merge(self::$buffer[$key]['meta'], $metaArr);
            }
            Utils::logDebug('RT', 'SyncBuffer', 'dedup ' . $key);
            return;
        }
        self::$buffer[$key] = [
            'type' => $type,
            'action' => $action,
            'id' => $id,
            'meta' => $metaArr,
        ];
        Utils::logDebug('RT', 'SyncBuffer', 'enqueued ' . $key);
        self::ensureRegistered();
    }

    protected static function ensureRegistered()
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        register_shutdown_function([__CLASS__, 'flush']);
    }

    public static function flush()
    {
        if (empty(self::$buffer) || self::$flushing) {
            return;
        }
        self::$flushing = true;
        try {
            $groups = [];
            foreach (self::$buffer as $entry) {
                $groupKey = $entry['type'] . ':' . $entry['action'];
                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [];
                }
                $groups[$groupKey][] = $entry;
            }
            self::$buffer = [];

            Utils::logInfo('RT', 'SyncBuffer', 'flush start groups=' . count($groups));

            foreach ($groups as $groupKey => $entries) {
                if (!isset(self::$handlers[$groupKey])) {
                    Utils::logWarn('RT', 'SyncBuffer', 'no handler for ' . $groupKey . ' (' . count($entries) . ' items dropped)');
                    continue;
                }
                try {
                    Utils::logInfo('RT', 'SyncBuffer', 'flush group=' . $groupKey . ' items=' . count($entries));
                    call_user_func(self::$handlers[$groupKey], $entries);
                } catch (\Throwable $e) {
                    Utils::logError('RT', 'SyncBuffer', 'handler exception for ' . $groupKey . ': ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }
            }

            Utils::logInfo('RT', 'SyncBuffer', 'flush done');
        } catch (\Throwable $e) {
            Utils::logError('RT', 'SyncBuffer', 'flush exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
        self::$flushing = false;
    }

    public static function size()
    {
        return count(self::$buffer);
    }

    public static function reset()
    {
        self::$buffer = [];
    }
}
