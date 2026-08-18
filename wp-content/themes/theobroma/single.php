<?php
if (!have_posts()) {
    status_header(404);
    exit;
}

the_post();
$article_link = (string) get_post_meta(get_the_ID(), '_theobroma_article_link', true);
$related_product_ids = array_values(array_filter(array_map('absint', (array) get_post_meta(get_the_ID(), '_theobroma_product_ids', true))));
$related_articles = function_exists('theobroma_related_media_posts') ? theobroma_related_media_posts(get_the_ID(), 3) : array();
?><!doctype html>
<html lang="ru-RU">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('media-article-view'); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#theobroma-main">Перейти к основному содержимому</a>
<header class="media-article-header">
    <a class="media-article-back" href="<?php echo esc_url(theobroma_page_url('Медиа')); ?>" aria-label="Вернуться в раздел Медиа">‹</a>
    <span>Пища богов</span>
</header>
<span class="skip-target" id="theobroma-main" tabindex="-1"></span>
<main class="media-article">
    <h1><?php the_title(); ?></h1>
    <?php if (has_post_thumbnail()) : ?>
        <figure><?php the_post_thumbnail('full', array('loading' => 'eager', 'fetchpriority' => 'high')); ?></figure>
    <?php endif; ?>
    <div class="media-article-copy"><?php echo wp_kses_post(get_the_content()); ?></div>
    <?php if ($article_link !== '') : ?>
        <a class="media-article-source" href="<?php echo esc_url($article_link); ?>" target="_blank" rel="noopener noreferrer">Читать статью</a>
    <?php endif; ?>
    <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('d.m.Y')); ?></time>
    <?php if ($related_product_ids && function_exists('wc_get_product')) : ?>
        <?php $related_products = array_filter(array_map('wc_get_product', $related_product_ids)); ?>
        <?php if ($related_products) : ?>
            <section class="media-article-products" aria-labelledby="media-article-products-title">
                <h2 id="media-article-products-title">Шоколад по теме статьи</h2>
                <div class="media-article-products-grid home-product-grid">
                    <?php foreach ($related_products as $product) : ?>
                        <?php get_template_part('template-parts/home/product-card', null, array('product' => $product)); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($related_articles) : ?>
        <section class="media-article-related" aria-labelledby="media-article-related-title">
            <h2 id="media-article-related-title">Похожие статьи</h2>
            <div class="media-article-related-grid">
                <?php foreach ($related_articles as $related_article) : ?>
                    <article>
                        <a class="media-article-related-image" href="<?php echo esc_url(get_permalink($related_article)); ?>" aria-label="<?php echo esc_attr(sprintf('Открыть статью: %s', get_the_title($related_article))); ?>">
                            <?php echo get_the_post_thumbnail($related_article, 'theobroma-media-card', array('loading' => 'lazy', 'decoding' => 'async')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                        <h3><a href="<?php echo esc_url(get_permalink($related_article)); ?>"><?php echo esc_html(get_the_title($related_article)); ?></a></h3>
                        <time datetime="<?php echo esc_attr(get_the_date('c', $related_article)); ?>"><?php echo esc_html(get_the_date('d.m.Y', $related_article)); ?></time>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
