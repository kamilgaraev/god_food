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
    <nav class="media-breadcrumb" aria-label="Хлебные крошки"><a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span>/</span><strong>Медиа</strong></nav>
    <h1>Медиа</h1>
    <p class="media-lead">Материалы СМИ, экспертные комментарии и авторские статьи бренда<br>Theobroma «Пища Богов» о шоколаде и индустрии вкуса.</p>
    <div class="media-grid">
        <?php while ($media_posts->have_posts()) : $media_posts->the_post();
            $article_url = get_permalink();
        ?>
            <article class="media-card">
                <a class="media-card-image" href="<?php echo esc_url($article_url); ?>" aria-label="<?php echo esc_attr(get_the_title()); ?>"><?php echo wp_get_attachment_image(get_post_thumbnail_id(), 'theobroma-media-card', false, array('loading' => 'eager', 'sizes' => '360px')); ?></a>
                <h2><a href="<?php echo esc_url($article_url); ?>"><?php the_title(); ?></a></h2>
                <p><?php echo esc_html(get_the_excerpt()); ?></p>
                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('d.m.Y')); ?></time>
            </article>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
</main>
