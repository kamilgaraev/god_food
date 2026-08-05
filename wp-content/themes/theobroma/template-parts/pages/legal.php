<?php
$legal_title = get_the_title();
$legal_slug = (string) get_post_field('post_name', get_the_ID());
?>
<main class="legal-page legal-page-<?php echo esc_attr($legal_slug); ?>">
    <nav class="legal-breadcrumb" aria-label="Хлебные крошки"><a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span>/</span><strong><?php echo esc_html($legal_title); ?></strong></nav>
    <h1><?php echo esc_html($legal_title); ?></h1>
    <div class="legal-content"><?php echo wp_kses_post((string) get_post_field('post_content', get_the_ID())); ?></div>
</main>
