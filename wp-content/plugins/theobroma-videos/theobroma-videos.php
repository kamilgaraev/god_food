<?php
/**
 * Plugin Name: Theobroma Видео и сторис
 * Description: Настраиваемые сторис, вертикальные видео и общий проигрыватель.
 * Version: 1.0.0
 * Requires PHP: 8.1
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/admin.php';

add_action('wp_enqueue_scripts', function () {
    if (!is_front_page()) {
        return;
    }
    wp_enqueue_style('theobroma-videos', plugins_url('assets/videos.css', __FILE__), [], filemtime(__DIR__ . '/assets/videos.css'));
    wp_enqueue_script('theobroma-video-player', plugins_url('assets/player.js', __FILE__), [], filemtime(__DIR__ . '/assets/player.js'), true);
    wp_enqueue_script('theobroma-videos', plugins_url('assets/videos.js', __FILE__), ['theobroma-video-player'], filemtime(__DIR__ . '/assets/videos.js'), true);
});

function theobroma_videos_section(string $kind): void {
    $settings = theobroma_videos_settings();
    if (!isset($settings[$kind]) || !$settings[$kind]['enabled']) {
        return;
    }
    $section = $settings[$kind];
    $items = array_values(array_filter($section['items'], static fn($item) => $item['enabled'] && $item['video'] !== ''));
    if (!$items) {
        return;
    }
    ?>
    <section class="theobroma-videos theobroma-videos--<?php echo esc_attr($kind); ?>" data-video-collection="<?php echo esc_attr($kind); ?>" aria-labelledby="video-title-<?php echo esc_attr($kind); ?>">
        <div class="theobroma-videos__heading">
            <div>
                <h2 id="video-title-<?php echo esc_attr($kind); ?>"><?php echo esc_html($section['title']); ?></h2>
                <?php if ($section['description']) : ?><p><?php echo esc_html($section['description']); ?></p><?php endif; ?>
            </div>
            <div class="theobroma-videos__arrows" aria-label="Прокрутить ленту" hidden>
                <button type="button" data-video-scroll="-1" aria-label="Прокрутить назад"><svg viewBox="0 0 24 32" aria-hidden="true" focusable="false"><path d="M16 6 8 16l8 10"/></svg></button>
                <button type="button" data-video-scroll="1" aria-label="Прокрутить вперёд"><svg viewBox="0 0 24 32" aria-hidden="true" focusable="false"><path d="m8 6 8 10-8 10"/></svg></button>
            </div>
        </div>
        <div class="theobroma-videos__track">
            <?php foreach ($items as $index => $item) :
                $poster = $item['poster_id'] ? wp_get_attachment_image_url($item['poster_id'], 'large') : '';
                $button = $item['button_enabled'] && $item['button_text'] && $item['button_url'];
                ?>
                <button type="button" class="theobroma-videos__card" data-video-open="<?php echo (int) $index; ?>"
                    data-video-key="<?php echo esc_attr(hash('sha256', wp_json_encode([$item['video'], $item['poster_id'], $item['title']]))); ?>"
                    data-video-src="<?php echo esc_url($item['video']); ?>"
                    data-video-poster="<?php echo esc_url($poster ?: ''); ?>"
                    data-video-title="<?php echo esc_attr($item['title']); ?>"
                    data-video-description="<?php echo esc_attr($item['description']); ?>"
                    data-video-button-text="<?php echo esc_attr($button ? $item['button_text'] : ''); ?>"
                    data-video-button-url="<?php echo esc_url($button ? $item['button_url'] : ''); ?>"
                    aria-label="<?php echo esc_attr('Смотреть ' . ($item['title'] ?: 'видео ' . ($index + 1))); ?>" aria-haspopup="dialog">
                    <span class="theobroma-videos__cover">
                        <?php if ($poster) : ?><img src="<?php echo esc_url($poster); ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
                        <?php if ($kind === 'reels') : ?><span class="theobroma-videos__play" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M8 5v14l11-7Z"/></svg></span><?php endif; ?>
                    </span>
                    <span class="theobroma-videos__name"><?php echo esc_html($item['title']); ?></span>
                    <?php if ($kind === 'reels' && $item['description']) : ?><span class="theobroma-videos__description"><?php echo esc_html($item['description']); ?></span><?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

add_action('wp_footer', function () {
    if (!is_front_page()) {
        return;
    }
    ?>
    <dialog class="theobroma-player" data-video-player aria-labelledby="theobroma-player-title">
        <div class="theobroma-player__shell">
            <div class="theobroma-player__progress" data-video-progress aria-hidden="true" hidden></div>
            <header class="theobroma-player__header">
                <span id="theobroma-player-title">Theobroma</span>
                <div class="theobroma-player__actions">
                    <button type="button" data-video-mute aria-label="Включить звук" aria-pressed="true">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="m11 5-5 4H3v6h3l5 4Z"/>
                            <g data-icon-muted><path d="m16 9 5 6m0-6-5 6"/></g>
                            <g data-icon-sound><path d="M15 8a6 6 0 0 1 0 8m3-11a10 10 0 0 1 0 14"/></g>
                        </svg>
                    </button>
                    <button type="button" data-video-close aria-label="Закрыть видео" autofocus>
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </div>
            </header>
            <div class="theobroma-player__viewport" data-player-viewport tabindex="0" role="group" aria-label="Видео"></div>
            <p class="theobroma-player__announcement" data-video-announcement role="status" aria-live="polite"></p>
        </div>
        <template data-player-slide-template>
            <article class="theobroma-player__slide" data-player-slide>
                <video playsinline preload="none" disablepictureinpicture data-video-element></video>
                <button type="button" class="theobroma-player__zone theobroma-player__zone--previous" data-story-direction="-1" aria-label="Предыдущая сторис" tabindex="-1"></button>
                <button type="button" class="theobroma-player__zone theobroma-player__zone--next" data-story-direction="1" aria-label="Следующая сторис" tabindex="-1"></button>
                <div class="theobroma-player__feedback" data-video-ui>
                    <span class="theobroma-player__spinner" data-video-loading role="status" aria-label="Загрузка видео" hidden></span>
                    <p data-video-status></p>
                    <button type="button" data-video-retry hidden>Повторить загрузку</button>
                </div>
                <div class="theobroma-player__details">
                    <h2 data-video-heading></h2>
                    <p data-video-caption></p>
                    <a class="theobroma-player__button" data-video-cta hidden></a>
                </div>
            </article>
        </template>
    </dialog>
    <?php
}, 10);
