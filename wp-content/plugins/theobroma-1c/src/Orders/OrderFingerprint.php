<?php
declare(strict_types=1);

namespace Theobroma\OneC\Orders;

final class OrderFingerprint
{
    /** @param array<string, mixed> $snapshot */
    public static function hash(array $snapshot): string
    {
        foreach (array_keys($snapshot) as $key) {
            if (str_starts_with((string) $key, '_theobroma_1c_')) {
                unset($snapshot[$key]);
            }
        }

        self::sortRecursively($snapshot);
        return hash('sha256', (string) json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<mixed> $value */
    private static function sortRecursively(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::sortRecursively($item);
            }
        }
        unset($item);

        if (!array_is_list($value)) {
            ksort($value);
        }
    }
}
