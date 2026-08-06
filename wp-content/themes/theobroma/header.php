<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
    <div class="shipping"><img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/truck-original.webp'); ?>" alt=""><span><?php echo esc_html(theobroma_content('shipping_text')); ?></span></div>
    <nav class="nav" aria-label="Основная навигация">
        <div class="nav-links"><a href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '#catalog'); ?>">Каталог</a><a href="<?php echo esc_url(theobroma_page_url('Рецепты')); ?>">Рецепты</a><a href="<?php echo esc_url(theobroma_page_url('Маркетплейсы')); ?>">Маркетплейсы</a></div>
        <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/logo.webp'); ?>" width="252" height="106" decoding="async" fetchpriority="high" alt="Theobroma — Пища богов"></a>
        <div class="nav-links"><a href="<?php echo esc_url(theobroma_page_url('Где купить')); ?>">Где купить</a><a href="<?php echo esc_url(theobroma_page_url('Сотрудничество')); ?>">Сотрудничество</a><a href="#contacts">Контакты</a></div>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Открыть меню"><span></span><span></span><span></span></button>
    </nav>
</header>
<aside class="floating-actions" aria-label="Быстрые действия">
    <a href="<?php echo esc_url(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/')); ?>" aria-label="Корзина"><img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/cart.svg'); ?>" alt=""><span class="cart-count">(<?php echo esc_html(function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : '0'); ?>)</span></a>
    <a href="#catalog" aria-label="Избранное"><img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/heart.svg'); ?>" alt=""><span>(0)</span></a>
    <a href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url()); ?>"<?php echo !is_user_logged_in() ? ' data-account-trigger' : ''; ?> aria-label="Личный кабинет"><img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/user.png'); ?>" alt=""><span>ЛК</span></a>
</aside>
<div class="mobile-menu" id="mobile-menu" aria-hidden="true">
    <button class="mobile-menu-close" type="button" aria-label="Закрыть меню"></button>
    <nav aria-label="Мобильная навигация"><ul>
        <li><a href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '#catalog'); ?>">Каталог</a></li>
        <li><a href="<?php echo esc_url(theobroma_page_url('Рецепты')); ?>">Рецепты</a></li>
        <li><a href="<?php echo esc_url(theobroma_page_url('Маркетплейсы')); ?>">Маркетплейсы</a></li>
        <li><a href="<?php echo esc_url(theobroma_page_url('Где купить')); ?>">Где купить</a></li>
        <li><a href="<?php echo esc_url(theobroma_page_url('Сотрудничество')); ?>">Сотрудничество</a></li>
        <li><a href="<?php echo esc_url(theobroma_page_url('Доставка и оплата')); ?>">Доставка и оплата</a></li>
        <li><a href="#contacts">Контакты</a></li>
    </ul></nav>
</div>
