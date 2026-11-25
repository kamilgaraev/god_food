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
    wp_enqueue_script('theobroma-videos', plugins_url('assets/videos.js', __FILE__), [], filemtime(__DIR__ . '/assets/videos.js'), true);
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
            <div class="theobroma-player__progress" data-video-progress aria-hidden="true"></div>
            <header class="theobroma-player__header">
                <span>Theobroma</span>
                <button type="button" data-video-close aria-label="Закрыть видео" autofocus>×</button>
            </header>
            <div class="theobroma-player__stage">
                <video playsinline preload="none" data-video-element></video>
                <button type="button" class="theobroma-player__retry" data-video-retry hidden>Повторить загрузку</button>
                <p class="theobroma-player__status" role="status" data-video-status></p>
                <button type="button" class="theobroma-player__prev" data-video-direction="-1" aria-label="Предыдущее видео">↑</button>
                <button type="button" class="theobroma-player__next" data-video-direction="1" aria-label="Следующее видео">↓</button>
            </div>
            <div class="theobroma-player__details">
                <h2 id="theobroma-player-title" data-video-heading></h2>
                <p data-video-caption></p>
                <div class="theobroma-player__controls">
                    <button type="button" data-video-pause aria-label="Приостановить видео">Пауза</button>
                    <span data-video-counter></span>
                    <button type="button" data-video-mute aria-label="Включить звук" aria-pressed="true">Без звука</button>
                </div>
                <a class="theobroma-player__button" data-video-cta hidden></a>
            </div>
        </div>
    </dialog>
    <?php
}, 10);
