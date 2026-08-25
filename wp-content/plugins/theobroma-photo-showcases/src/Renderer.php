<?php

declare(strict_types=1);

namespace Theobroma\PhotoShowcases;

final class Renderer
{
    /** @param array<string, array<string, mixed>> $settings */
    public function html(string $location, array $settings): string
    {
        if (!in_array($location, array('home', 'corporate'), true)) {
            return '';
        }

        $collection = $settings[$location] ?? array();
        if (!is_array($collection) || empty($collection['enabled'])) {
            return '';
        }

        $figures = $this->figures($location, $collection['images'] ?? array());
        if ($figures === '') {
            return '';
        }

        $titleId = 'theobroma-photo-showcase-' . $location . '-title';

        return sprintf(
            '<section class="theobroma-photo-showcase theobroma-photo-showcase--%1$s" aria-labelledby="%2$s">'
            . '<div class="theobroma-photo-showcase__shell">'
            . '<header class="theobroma-photo-showcase__intro"><p class="theobroma-photo-showcase__eyebrow">%3$s</p>'
            . '<h2 id="%2$s">%4$s</h2><p class="theobroma-photo-showcase__description">%5$s</p></header>'
            . '<div class="theobroma-photo-showcase__gallery" role="list" aria-label="Фотогалерея" tabindex="0">%6$s</div>'
            . '</div></section>',
            esc_attr($location),
            esc_attr($titleId),
            esc_html((string) ($collection['eyebrow'] ?? '')),
            esc_html((string) ($collection['title'] ?? '')),
            esc_html((string) ($collection['description'] ?? '')),
            $figures
        );
    }

    private function figures(string $location, mixed $images): string
    {
        if (!is_array($images)) {
            return '';
        }

        $html = '';
        $displayIndex = 0;

        foreach ($images as $image) {
            if (!is_array($image)) {
                continue;
            }

            $attachmentId = (int) ($image['attachment_id'] ?? 0);
            if ($attachmentId <= 0 || !wp_attachment_is_image($attachmentId)) {
                continue;
            }

            $alt = trim((string) ($image['alt'] ?? ''));
            if ($alt === '') {
                $alt = trim((string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true));
            }

            $imageHtml = wp_get_attachment_image($attachmentId, 'large', false, array(
                'class' => 'theobroma-photo-showcase__image',
                'alt' => $alt,
                'loading' => 'lazy',
                'decoding' => 'async',
                'fetchpriority' => 'low',
            ));
            if ($imageHtml === '') {
                continue;
            }

            $displayIndex++;
            $caption = trim((string) ($image['caption'] ?? ''));
            $number = str_pad((string) $displayIndex, 2, '0', STR_PAD_LEFT);
            $meta = $location === 'corporate'
                ? '<span aria-hidden="true">' . esc_html($number) . '</span>'
                : '<span class="theobroma-photo-showcase__index" aria-hidden="true">' . esc_html($number) . '</span>';
            if ($caption !== '') {
                $meta .= '<figcaption>' . esc_html($caption) . '</figcaption>';
            }

            $html .= sprintf(
                '<figure class="theobroma-photo-showcase__item theobroma-photo-showcase__item--%1$d" role="listitem">%2$s<div class="theobroma-photo-showcase__meta">%3$s</div></figure>',
                $displayIndex,
                $imageHtml,
                $meta
            );
        }

        return $html;
    }
}
