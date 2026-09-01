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
            '<section class="theobroma-photo-showcase theobroma-photo-showcase--%1$s" aria-labelledby="%2$s" data-photo-showcase>'
            . '<div class="theobroma-photo-showcase__shell">'
            . '<header class="theobroma-photo-showcase__intro"><h2 id="%2$s">%3$s</h2>'
            . '<p class="theobroma-photo-showcase__description">%4$s</p></header>'
            . '<div class="theobroma-photo-showcase__gallery" role="list" aria-label="Фотогалерея" tabindex="0">%5$s</div>'
            . '</div>%6$s</section>',
            esc_attr($location),
            esc_attr($titleId),
            esc_html((string) ($collection['title'] ?? '')),
            esc_html((string) ($collection['description'] ?? '')),
            $figures,
            $this->lightbox()
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

            $fullImageUrl = wp_get_attachment_image_url($attachmentId, 'full');
            if (!is_string($fullImageUrl) || $fullImageUrl === '') {
                continue;
            }

            $displayIndex++;
            $caption = trim((string) ($image['caption'] ?? ''));
            $number = str_pad((string) $displayIndex, 2, '0', STR_PAD_LEFT);
            $meta = '';
            if ($caption !== '') {
                $meta .= '<figcaption>' . esc_html($caption) . '</figcaption>';
            }

            $html .= sprintf(
                '<figure class="theobroma-photo-showcase__item theobroma-photo-showcase__item--%1$d" role="listitem">'
                . '<button class="theobroma-photo-showcase__trigger" type="button" data-photo-lightbox-trigger '
                . 'data-photo-src="%2$s" data-photo-alt="%3$s" data-photo-caption="%4$s" aria-label="%5$s">%6$s</button>'
                . '<div class="theobroma-photo-showcase__meta">%7$s</div></figure>',
                $displayIndex,
                esc_attr($fullImageUrl),
                esc_attr($alt),
                esc_attr($caption),
                esc_attr('Открыть фотографию ' . $number . ($alt !== '' ? ': ' . $alt : '')),
                $imageHtml,
                $meta
            );
        }

        return $html;
    }

    private function lightbox(): string
    {
        return '<div class="theobroma-photo-lightbox" data-photo-lightbox hidden aria-hidden="true" role="dialog" aria-modal="true" aria-label="Просмотр фотогалереи">'
            . '<button class="theobroma-photo-lightbox__backdrop" type="button" data-photo-lightbox-close tabindex="-1" aria-label="Закрыть просмотр"></button>'
            . '<div class="theobroma-photo-lightbox__panel">'
            . '<button class="theobroma-photo-lightbox__close" type="button" data-photo-lightbox-close aria-label="Закрыть просмотр"></button>'
            . '<button class="theobroma-photo-lightbox__nav theobroma-photo-lightbox__nav--previous" type="button" data-photo-lightbox-previous aria-label="Предыдущая фотография">←</button>'
            . '<figure><img data-photo-lightbox-image alt=""><figcaption data-photo-lightbox-caption hidden></figcaption></figure>'
            . '<button class="theobroma-photo-lightbox__nav theobroma-photo-lightbox__nav--next" type="button" data-photo-lightbox-next aria-label="Следующая фотография">→</button>'
            . '</div></div>';
    }
}
