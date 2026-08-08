<!doctype html>
<html lang="ru-RU">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#theobroma-main">Перейти к основному содержимому</a>
<?php
$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/#catalog');
$account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
$cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/');
$cart_count = function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
?>
<header class="site-header">
    <nav class="nav" aria-label="Основная навигация">
        <div class="nav-links nav-links-study">
            <a href="<?php echo esc_url($shop_url); ?>">Каталог</a>
            <a href="<?php echo esc_url(theobroma_page_url('Рецепты')); ?>">Рецепты</a>
            <a href="<?php echo esc_url(theobroma_page_url('Маркетплейсы')); ?>">Маркетплейсы</a>
            <a href="<?php echo esc_url(theobroma_page_url('Сотрудничество')); ?>">Сотрудничество</a>
        </div>
        <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Theobroma — Пища Богов, на главную">
            <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/logo.webp'); ?>" width="252" height="106" decoding="async" fetchpriority="high" alt="Theobroma — Пища Богов">
        </a>
        <div class="nav-links nav-links-transactional floating-actions">
            <a class="header-where" href="<?php echo esc_url(theobroma_page_url('Где купить')); ?>">Где купить</a>
            <a class="header-icon header-account" href="<?php echo esc_url($account_url); ?>"<?php echo !is_user_logged_in() ? ' data-account-trigger' : ''; ?> aria-label="Личный кабинет">
                <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/user.webp'); ?>" loading="lazy" decoding="async" fetchpriority="low" alt="">
            </a>
            <a class="header-icon header-cart" href="<?php echo esc_url($cart_url); ?>" data-commerce-cart-open aria-label="Корзина, товаров: <?php echo esc_attr((string) $cart_count); ?>">
                <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/cart.svg'); ?>" alt="">
                <span class="cart-count" aria-hidden="true"><?php echo esc_html((string) $cart_count); ?></span>
            </a>
        </div>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Открыть меню"><span></span><span></span><span></span></button>
    </nav>
</header>
<div class="mobile-menu" id="mobile-menu" aria-hidden="true">
    <button class="mobile-menu-close" type="button" aria-label="Закрыть меню"></button>
    <nav aria-label="Мобильная навигация">
        <p class="mobile-menu-label">О продукте</p>
        <ul>
            <li><a href="<?php echo esc_url($shop_url); ?>">Каталог</a></li>
            <li><a href="<?php echo esc_url(theobroma_page_url('Рецепты')); ?>">Рецепты</a></li>
            <li><a href="<?php echo esc_url(theobroma_page_url('Маркетплейсы')); ?>">Маркетплейсы</a></li>
            <li><a href="<?php echo esc_url(theobroma_page_url('Сотрудничество')); ?>">Сотрудничество</a></li>
        </ul>
        <p class="mobile-menu-label">Покупателям</p>
        <ul>
            <li><a href="<?php echo esc_url(theobroma_page_url('Где купить')); ?>">Где купить</a></li>
            <li><a href="<?php echo esc_url($account_url); ?>"<?php echo !is_user_logged_in() ? ' data-account-trigger' : ''; ?>>Личный кабинет</a></li>
            <li><a href="<?php echo esc_url(theobroma_page_url('Доставка и оплата')); ?>">Доставка и оплата</a></li>
            <li><a href="<?php echo esc_url(home_url('/#contacts')); ?>">Контакты</a></li>
        </ul>
    </nav>
</div>
<?php if (!is_front_page()) : ?><span class="skip-target" id="theobroma-main" tabindex="-1"></span><?php endif; ?>
