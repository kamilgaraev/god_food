<?php
get_header();
$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/catalog/');
?>
<main id="theobroma-main" class="site-not-found">
    <div class="site-not-found__inner">
        <span class="site-not-found__number" aria-hidden="true">404</span>
        <h1>Страница не найдена</h1>
        <p>Возможно, адрес изменился. Вернитесь на главную или загляните в каталог.</p>
        <div class="site-not-found__actions">
            <a class="site-not-found__button" href="<?php echo esc_url(home_url('/')); ?>">На главную</a>
            <a class="site-not-found__link" href="<?php echo esc_url($shop_url); ?>">Открыть каталог</a>
        </div>
    </div>
</main>
<?php get_footer();
