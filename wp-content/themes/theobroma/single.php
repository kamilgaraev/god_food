<?php
if (!have_posts()) {
    status_header(404);
    exit;
}

the_post();
$article_link = (string) get_post_meta(get_the_ID(), '_theobroma_article_link', true);
$related_product_ids = array_values(array_filter(array_map('absint', (array) get_post_meta(get_the_ID(), '_theobroma_product_ids', true))));
$related_articles = function_exists('theobroma_related_media_posts') ? theobroma_related_media_posts(get_the_ID(), 3) : array();
get_header();
?>
<main class="media-article-page">
<article class="media-article">
    <nav class="media-article-breadcrumb" aria-label="Хлебные крошки">
        <a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span aria-hidden="true">/</span><a href="<?php echo esc_url(theobroma_page_url('Медиа')); ?>">Медиа</a><span aria-hidden="true">/</span><strong aria-current="page">Статья</strong>
    </nav>
    <header class="media-article-hero">
        <p class="media-article-kicker">Редакция Theobroma</p>
        <h1><?php the_title(); ?></h1>
        <div class="media-article-meta">
            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('d.m.Y')); ?></time>
            <span aria-hidden="true"></span>
            <a href="<?php echo esc_url(theobroma_page_url('Медиа')); ?>">Все материалы</a>
        </div>
    </header>
    <?php if (has_post_thumbnail()) : ?>
        <figure class="media-article-cover"><?php the_post_thumbnail('full', array('loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async', 'sizes' => '(max-width: 1199px) 100vw, 1160px')); ?></figure>
    <?php endif; ?>
    <div class="media-article-copy"><?php echo wp_kses_post(get_the_content()); ?></div>
    <?php if ($article_link !== '') : ?>
        <a class="media-article-source" href="<?php echo esc_url($article_link); ?>" target="_blank" rel="noopener noreferrer">Читать в источнике <span aria-hidden="true">↗</span></a>
    <?php endif; ?>
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
</article>
</main>
<?php get_footer(); ?>
