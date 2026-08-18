<footer class="site-footer" id="contacts">
    <div class="footer-shell">
        <div class="footer-map"><h3>Карта сайта</h3><ul><li><a href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/')); ?>">Каталог</a></li><li><a href="<?php echo esc_url(theobroma_page_url('Где купить')); ?>">Где купить</a></li><li><a href="<?php echo esc_url(theobroma_page_url('Рецепты')); ?>">Рецепты</a></li></ul><ul><li><a href="<?php echo esc_url(theobroma_page_url('Маркетплейсы')); ?>">Маркетплейсы</a></li><li><a href="<?php echo esc_url(theobroma_page_url('Сотрудничество')); ?>">Сотрудничество</a></li><li><a href="<?php echo esc_url(theobroma_page_url('Доставка и оплата')); ?>">Доставка и оплата</a></li></ul></div>
        <div class="footer-logo"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/logo.webp'); ?>" width="252" height="106" loading="lazy" decoding="async" alt="Theobroma — Пища богов"></div>
        <?php
        $footer_phone_1 = theobroma_content('footer_phone_1');
        $footer_phone_2 = theobroma_content('footer_phone_2');
        $footer_address = theobroma_content('footer_address');
        $footer_phone_1_href = preg_replace('/[^\d+]/', '', $footer_phone_1);
        $footer_phone_2_href = preg_replace('/[^\d+]/', '', $footer_phone_2);
        $footer_address_map_url = 'https://yandex.ru/maps/?text=' . rawurlencode($footer_address);
        ?>
        <div class="footer-phones"><a href="tel:<?php echo esc_attr($footer_phone_1_href); ?>"><?php echo esc_html($footer_phone_1); ?></a><a href="tel:<?php echo esc_attr($footer_phone_2_href); ?>"><?php echo esc_html($footer_phone_2); ?></a></div>
        <div class="footer-card footer-address"><a href="<?php echo esc_url($footer_address_map_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="Открыть адрес в Яндекс Картах"><?php echo nl2br(esc_html($footer_address)); ?></a></div>
        <div class="footer-media"><div class="social-icons"><a href="<?php echo esc_url(theobroma_content('social_vk')); ?>" aria-label="ВКонтакте"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/social-vk.svg'); ?>" alt=""></a><a href="<?php echo esc_url(theobroma_content('social_telegram')); ?>" aria-label="Telegram"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/social-telegram.svg'); ?>" alt=""></a><a href="<?php echo esc_url(theobroma_content('social_whatsapp')); ?>" aria-label="WhatsApp"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/social-whatsapp.svg'); ?>" alt=""></a><a href="<?php echo esc_url(theobroma_content('social_dzen')); ?>" aria-label="Дзен"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/social-star.svg'); ?>" alt=""></a></div><a class="footer-media-button" href="<?php echo esc_url(theobroma_page_url('Медиа')); ?>">Медиа о нас</a></div>
        <div class="footer-card footer-mail"><strong><a href="mailto:<?php echo esc_attr(theobroma_content('footer_info_email')); ?>"><?php echo esc_html(theobroma_content('footer_info_email')); ?></a></strong><small><?php echo nl2br(esc_html(theobroma_content('footer_info_note'))); ?></small></div>
        <div class="footer-card footer-mail"><strong><a href="mailto:<?php echo esc_attr(theobroma_content('footer_opt_email')); ?>"><?php echo esc_html(theobroma_content('footer_opt_email')); ?></a></strong><small><?php echo nl2br(esc_html(theobroma_content('footer_opt_note'))); ?></small></div>
        <div class="footer-card footer-mail"><strong><a href="mailto:<?php echo esc_attr(theobroma_content('footer_press_email')); ?>"><?php echo esc_html(theobroma_content('footer_press_email')); ?></a></strong><small><?php echo nl2br(esc_html(theobroma_content('footer_press_note'))); ?></small></div>
    </div>
    <div class="copyright"><span><?php echo nl2br(esc_html(theobroma_content('footer_company'))); ?></span><span><?php echo nl2br(esc_html(theobroma_content('footer_bank'))); ?></span><span><a href="<?php echo esc_url(theobroma_page_url('Политика конфиденциальности')); ?>">Политика конфиденциальности</a><br><a href="<?php echo esc_url(theobroma_page_url('Пользовательское соглашение')); ?>">Пользовательское соглашение</a><br><a href="<?php echo esc_url(theobroma_page_url('Публичная оферта')); ?>">Публичная оферта</a></span></div>
</footer>
<aside class="cookie-notice" aria-label="Уведомление о файлах cookie" hidden>
    <p>Используя данный сайт, вы даете <a href="<?php echo esc_url(theobroma_page_url('Политика конфиденциальности')); ?>">согласие на использование файлов cookie</a>, помогающих нам сделать его удобнее для вас</p>
    <button type="button">ОК, НЕ ПОКАЗЫВАТЬ СНОВА</button>
</aside>
<?php wp_footer(); ?>
</body>
</html>
