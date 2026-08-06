<?php
get_header();
if (is_page('Рецепты')) {
    get_template_part('template-parts/pages/recipes');
    get_footer();
    return;
}
if (is_page('Маркетплейсы')) {
    get_template_part('template-parts/pages/marketplace');
    get_footer();
    return;
}
if (is_page('Где купить')) {
    get_template_part('template-parts/pages/buy');
    get_footer();
    return;
}
if (is_page('Сотрудничество')) {
    get_template_part('template-parts/pages/cooperation');
    get_footer();
    return;
}
if (is_page('Корпоративные подарки')) {
    get_template_part('template-parts/pages/corporate-gifts');
    get_footer();
    return;
}
if (is_page('Доставка и оплата')) {
    get_template_part('template-parts/pages/delivery');
    get_footer();
    return;
}
if (is_page('Медиа')) {
    get_template_part('template-parts/pages/media');
    get_footer();
    return;
}
if (is_page(array('Политика конфиденциальности', 'Пользовательское соглашение', 'Публичная оферта', 'Согласие на обработку персональных данных'))) {
    while (have_posts()) {
        the_post();
        get_template_part('template-parts/pages/legal');
    }
    get_footer();
    return;
}
?>
<main class="shop-page"><div class="shop-shell">
<?php while (have_posts()) { the_post(); if (!function_exists('is_cart') || (!is_cart() && !is_checkout() && !is_account_page())) { echo '<h1 class="page-title">' . esc_html(get_the_title()) . '</h1>'; } the_content(); } ?>
</div></main>
<?php get_footer();
