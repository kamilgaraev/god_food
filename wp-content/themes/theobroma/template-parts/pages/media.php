<?php
$media_posts = new WP_Query(array(
    'post_type' => 'post',
    'post_status' => 'publish',
    'category_name' => 'media',
    'posts_per_page' => 12,
    'orderby' => array('date' => 'DESC', 'menu_order' => 'ASC'),
    'no_found_rows' => true,
));
?>
<main class="media-page">
    <header class="media-intro">
        <nav class="media-breadcrumb" aria-label="Хлебные крошки"><a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span aria-hidden="true">/</span><strong aria-current="page">Медиа</strong></nav>
        <p class="media-kicker">Журнал о шоколаде</p>
        <h1>Медиа</h1>
        <p class="media-lead">Материалы СМИ, экспертные комментарии и&nbsp;авторские статьи бренда Theobroma «Пища Богов» о&nbsp;шоколаде и&nbsp;индустрии вкуса.</p>
    </header>
    <div class="media-grid">
        <?php $media_post_index = 0; ?>
        <?php while ($media_posts->have_posts()) : $media_posts->the_post();
            $article_url = get_permalink();
        ?>
            <article class="media-card">
                <a class="media-card-link" href="<?php echo esc_url($article_url); ?>">
                    <span class="media-card-image"><?php echo wp_get_attachment_image(get_post_thumbnail_id(), 'theobroma-media-card', false, array('loading' => $media_post_index === 0 ? 'eager' : 'lazy', 'fetchpriority' => $media_post_index === 0 ? 'high' : 'auto', 'decoding' => 'async', 'sizes' => '(max-width: 600px) calc(100vw - 40px), (max-width: 1199px) calc((100vw - 120px) / 2), 360px')); ?></span>
                    <span class="media-card-body">
                        <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('d.m.Y')); ?></time>
                        <h2><?php the_title(); ?></h2>
                        <span class="media-card-excerpt"><?php echo esc_html(get_the_excerpt()); ?></span>
                        <span class="media-card-arrow" aria-hidden="true">Читать <i>→</i></span>
                    </span>
                </a>
            </article>
        <?php $media_post_index++; endwhile; wp_reset_postdata(); ?>
    </div>
</main>
