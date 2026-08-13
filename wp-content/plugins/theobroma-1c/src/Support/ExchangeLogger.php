<?php
declare(strict_types=1);

namespace Theobroma\OneC\Support;

final class ExchangeLogger
{
    /** @param array<string, int|string|bool> $context */
    public function info(string $event, array $context = []): void
    {
        $safe = array_intersect_key($context, array_flip(['mode', 'result', 'order_count']));
        wc_get_logger()->info($event, ['source' => 'theobroma-1c'] + $safe);

        $entries = (array) get_option('theobroma_1c_recent_log', []);
        array_unshift($entries, ['time' => gmdate('c'), 'event' => $event] + $safe);
        update_option('theobroma_1c_recent_log', array_slice($entries, 0, 20), false);
    }
}
