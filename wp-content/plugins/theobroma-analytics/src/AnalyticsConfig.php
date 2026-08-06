<?php

declare(strict_types=1);

namespace Theobroma\Analytics;

final class AnalyticsConfig
{
    /** @return array{counter_id:string,clickmap:bool,track_links:bool,accurate_bounce:bool,webvisor:bool} */
    public function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $counterId = trim((string) ($input['counter_id'] ?? ''));
        if ($counterId !== '' && !preg_match('/^[1-9][0-9]{0,14}$/', $counterId)) {
            $counterId = '';
        }

        return [
            'counter_id' => $counterId,
            'clickmap' => !empty($input['clickmap']),
            'track_links' => !empty($input['track_links']),
            'accurate_bounce' => !empty($input['accurate_bounce']),
            'webvisor' => !empty($input['webvisor']),
        ];
    }

    /** @return array{counter_id:string,clickmap:bool,track_links:bool,accurate_bounce:bool,webvisor:bool} */
    public function defaults(): array
    {
        return [
            'counter_id' => '',
            'clickmap' => true,
            'track_links' => true,
            'accurate_bounce' => true,
            'webvisor' => false,
        ];
    }
}
