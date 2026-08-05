<?php
if (!have_posts()) {
    status_header(404);
    exit;
}

the_post();
$article_link = (string) get_post_meta(get_the_ID(), '_theobroma_article_link', true);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('media-article-view'); ?>>
<?php wp_body_open(); ?>
<header class="media-article-header">
    <a class="media-article-back" href="<?php echo esc_url(theobroma_page_url('Медиа')); ?>" aria-label="Вернуться в раздел Медиа">‹</a>
    <span>Пища богов</span>
</header>
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
</main>
<?php wp_footer(); ?>
</body>
</html>
