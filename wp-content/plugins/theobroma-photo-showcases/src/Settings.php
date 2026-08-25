<?php

declare(strict_types=1);

namespace Theobroma\PhotoShowcases;

final class Settings
{
    public const OPTION = 'theobroma_photo_showcases';
    public const MAX_IMAGES = 8;

    /** @return array<string, array<string, mixed>> */
    public function defaults(): array
    {
        return array(
            'home' => array(
                'enabled' => true,
                'title' => 'Шоколад, который хочется рассмотреть ближе',
                'description' => 'Живые фактуры, натуральные ингредиенты и ручная работа — в каждом кадре и каждом кусочке.',
                'images' => array(),
            ),
            'corporate' => array(
                'enabled' => true,
                'title' => 'Подарки, которые запоминают',
                'description' => 'От первого эскиза до готового набора: оформление, шоколад и детали складываются в цельный подарок.',
                'images' => array(),
            ),
        );
    }

    /** @return array<string, array<string, mixed>> */
    public function sanitize(mixed $value): array
    {
        $defaults = $this->defaults();
        $input = is_array($value) ? $value : array();
        $result = array();

        foreach ($defaults as $location => $locationDefaults) {
            $candidate = isset($input[$location]) && is_array($input[$location])
                ? $input[$location]
                : array();

            $result[$location] = array(
                'enabled' => array_key_exists('enabled', $candidate)
                    ? in_array($candidate['enabled'], array(1, '1', true, 'on'), true)
                    : (bool) $locationDefaults['enabled'],
                'title' => $this->text($candidate, 'title', (string) $locationDefaults['title']),
                'description' => $this->textarea($candidate, 'description', (string) $locationDefaults['description']),
                'images' => $this->images($candidate['images'] ?? array()),
            );
        }

        return $result;
    }

    /** @param array<string, mixed> $input */
    private function text(array $input, string $key, string $fallback): string
    {
        $value = array_key_exists($key, $input) ? (string) $input[$key] : $fallback;

        return sanitize_text_field($value);
    }

    /** @param array<string, mixed> $input */
    private function textarea(array $input, string $key, string $fallback): string
    {
        $value = array_key_exists($key, $input) ? (string) $input[$key] : $fallback;

        return sanitize_textarea_field($value);
    }

    /** @return list<array{attachment_id: int, alt: string, caption: string}> */
    private function images(mixed $rows): array
    {
        if (!is_array($rows)) {
            return array();
        }

        $result = array();
        $seen = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $attachmentId = (int) ($row['attachment_id'] ?? 0);
            if ($attachmentId <= 0 || isset($seen[$attachmentId])) {
                continue;
            }

            $result[] = array(
                'attachment_id' => $attachmentId,
                'alt' => sanitize_text_field((string) ($row['alt'] ?? '')),
                'caption' => sanitize_text_field((string) ($row['caption'] ?? '')),
            );
            $seen[$attachmentId] = true;

            if (count($result) >= self::MAX_IMAGES) {
                break;
            }
        }

        return $result;
    }
}
