<?php

declare(strict_types=1);

namespace Theobroma\Seo;

final class SeoMetaBox
{
    private const NONCE_ACTION = 'theobroma_seo_save';
    private const NONCE_NAME = 'theobroma_seo_nonce';

    public function register(): void
    {
        add_action('init', [$this, 'registerMeta']);
        add_action('add_meta_boxes', [$this, 'add']);
        add_action('save_post', [$this, 'save']);
    }

    public function registerMeta(): void
    {
        foreach (['post', 'page', 'product'] as $postType) {
            register_post_meta($postType, '_theobroma_seo_title', $this->metaArgs('sanitize_text_field'));
            register_post_meta($postType, '_theobroma_seo_description', $this->metaArgs('sanitize_textarea_field'));
            register_post_meta($postType, '_theobroma_seo_og_image', $this->metaArgs('esc_url_raw'));
        }
    }

    public function add(): void
    {
        foreach (['post', 'page', 'product'] as $postType) {
            add_meta_box(
                'theobroma-seo',
                __('SEO и соцсети', 'theobroma-seo'),
                [$this, 'render'],
                $postType,
                'normal',
                'default'
            );
        }
    }

    public function render(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $title = (string) get_post_meta($post->ID, '_theobroma_seo_title', true);
        $description = (string) get_post_meta($post->ID, '_theobroma_seo_description', true);
        $image = (string) get_post_meta($post->ID, '_theobroma_seo_og_image', true);
        ?>
        <p><label for="theobroma-seo-title"><strong><?php esc_html_e('SEO-заголовок', 'theobroma-seo'); ?></strong></label></p>
        <input class="widefat" id="theobroma-seo-title" name="theobroma_seo_title" type="text" maxlength="70" value="<?php echo esc_attr($title); ?>" placeholder="<?php esc_attr_e('По умолчанию используется название записи', 'theobroma-seo'); ?>">
        <p><label for="theobroma-seo-description"><strong><?php esc_html_e('Описание для поиска и соцсетей', 'theobroma-seo'); ?></strong></label></p>
        <textarea class="widefat" id="theobroma-seo-description" name="theobroma_seo_description" rows="4" maxlength="320" placeholder="<?php esc_attr_e('Рекомендуемая длина — 120–160 символов', 'theobroma-seo'); ?>"><?php echo esc_textarea($description); ?></textarea>
        <p><label for="theobroma-seo-og-image"><strong><?php esc_html_e('URL изображения Open Graph', 'theobroma-seo'); ?></strong></label></p>
        <input class="widefat" id="theobroma-seo-og-image" name="theobroma_seo_og_image" type="url" value="<?php echo esc_attr($image); ?>" placeholder="<?php esc_attr_e('Если пусто, используется изображение записи или товара', 'theobroma-seo'); ?>">
        <?php
    }

    public function save(int $postId): void
    {
        if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
            return;
        }
        $nonce = isset($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION) || !current_user_can('edit_post', $postId)) {
            return;
        }

        $values = [
            '_theobroma_seo_title' => isset($_POST['theobroma_seo_title']) ? sanitize_text_field(wp_unslash($_POST['theobroma_seo_title'])) : '',
            '_theobroma_seo_description' => isset($_POST['theobroma_seo_description']) ? sanitize_textarea_field(wp_unslash($_POST['theobroma_seo_description'])) : '',
            '_theobroma_seo_og_image' => isset($_POST['theobroma_seo_og_image']) ? esc_url_raw(wp_unslash($_POST['theobroma_seo_og_image'])) : '',
        ];
        foreach ($values as $key => $value) {
            if ($value === '') {
                delete_post_meta($postId, $key);
            } else {
                update_post_meta($postId, $key, $value);
            }
        }
    }

    /** @return array<string, mixed> */
    private function metaArgs(string $sanitizeCallback): array
    {
        return [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => $sanitizeCallback,
            'auth_callback' => static fn(bool $allowed, string $metaKey, int $postId): bool => current_user_can('edit_post', $postId),
        ];
    }
}
