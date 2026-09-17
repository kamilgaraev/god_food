<?php
$asset_base = get_template_directory_uri() . '/assets/images/corporate/';
$catalog_url = esc_url(theobroma_content('corporate_catalog_url'), array('http', 'https'));
$gifts = array(
    array('name' => 'Знакомство', 'image' => 'discovery', 'description' => '3 батончика по 30 г разного процента + мини-открытка. Крафт-конверт с лентой.', 'price' => '750'),
    array('name' => 'Для неё', 'image' => 'for-her', 'description' => '3 шоколада: молочный 100 г, горький 70% 100 г, горький с корицей 65% 100 г + открытка. Коробка с ложементом.', 'price' => '2 500'),
    array('name' => 'Горький', 'image' => 'bitter', 'description' => 'Шоколад 70%, 80%, 90% и 99% по 100 г + карточка дегустации с описанием вкуса. Коробка с ложементом.', 'price' => '3 290'),
    array('name' => 'Для него', 'image' => 'for-him', 'description' => 'Два шоколада: 70% и 80% (100/200 г).', 'price' => '1 500'),
    array('name' => 'Премиум', 'image' => 'premium', 'description' => 'Набор в коробке, собранной под ваш запрос. Состав по согласованию. Коробка с тиснением логотипа.', 'price' => '5 000'),
);
$details = array(
    array('Открытка и вкладка', 'Ваш текст и логотип на вкладыше внутри коробки.', 'Можете прислать нам пример или мы предложим свой вариант.'),
    array('Лента и стикер', 'Фирменные цвета ленты, стикер-пломба с логотипом, тиснение на крафте.', 'Цвета и стили подбираются при участии арт-директора.'),
    array('Своя упаковка шоколада', 'Полноцветная печать упаковки по вашему дизайну или по нашему шаблону с вашим логотипом.', 'От вас потребуется макет в кривых.'),
    array('Персональное предложение', 'Мы обсудим предпочтения ваших сотрудников и создадим персональные наборы для них.', 'Можем сделать проект по другой рецептуре, которой нет в нашей линейке.'),
);
$questions = array(
    array('Какой минимальный тираж?', 'От 20 наборов. Для наборов со своей упаковкой — от 100, со своим вкусом — от 100.'),
    array('Можно ли посмотреть образец до заказа?', 'Оставьте заявку — менеджер расскажет о доступных образцах и согласует вариант перед оформлением тиража.'),
    array('Какой срок годности?', 'Срок годности зависит от выбранного шоколада и указан на упаковке. Уточним его при согласовании состава набора.'),
    array('Работаете с юрлицами?', 'Да. Укажите компанию в заявке — менеджер подготовит предложение и документы для вашего заказа.'),
    array('Можно отправить подарки сотрудникам по разным адресам?', 'Укажите это в комментарии к заявке. Возможность, стоимость и сроки такой доставки согласуем индивидуально.'),
    array('Что с макетом, если у нас нет дизайнера?', 'Предложим варианты оформления и поможем подобрать решение с вашим логотипом. Требования к материалам уточним перед печатью.'),
    array('Летом шоколад не поплывёт?', 'Условия перевозки зависят от погоды и направления доставки. Менеджер согласует подходящий способ отправки.'),
    array('Подойдёт ли тем, кто не ест сахар?', 'В линейке есть шоколад без добавленного сахара и варианты с кокосовым сахаром. Для каждого набора уточните состав выбранных вкусов.'),
);
$gallery = array(
    array('url' => $asset_base . 'gallery-chocolate-original.jpg', 'alt' => 'Кусочки шоколада Theobroma'),
    array('url' => $asset_base . 'gallery-packaging-original.jpg', 'alt' => 'Подарочная коллекция Theobroma на постаменте'),
);
$gallery_title = '';
$gallery_enabled = true;
// Honor the existing photo plugin's saved selection and visibility switch.
$saved_showcases = get_option('theobroma_photo_showcases', null);
if (is_array($saved_showcases) && class_exists('Theobroma\\PhotoShowcases\\Settings')) {
    $showcase = (new \Theobroma\PhotoShowcases\Settings())->sanitize($saved_showcases)['corporate'];
    $gallery_enabled = $showcase['enabled'];
    $gallery_title = $showcase['title'];
    $gallery = array();
    foreach ($showcase['images'] as $row) {
        $url = wp_get_original_image_url($row['attachment_id']);
        if ($url) {
            $gallery[] = array('url' => $url, 'alt' => $row['alt'] ?: (string) get_post_meta($row['attachment_id'], '_wp_attachment_image_alt', true));
        }
    }
}
$site_reviews = get_posts(array('post_type' => 'theobroma_review', 'post_status' => 'publish', 'numberposts' => 12, 'orderby' => array('menu_order' => 'ASC', 'date' => 'ASC')));
?>
<main class="corporate-gifts-page corporate-redesign" id="theobroma-main">
    <section class="cg-hero" aria-labelledby="cg-title">
        <img class="cg-hero-image" src="<?php echo esc_url($asset_base . 'hero-original.jpg'); ?>" width="1440" height="810" fetchpriority="high" alt="Подарочные коробки шоколада Theobroma">
        <div class="cg-shell cg-hero-content">
            <p class="cg-eyebrow">Корпоративные подарки · Своя фабрика</p>
            <h1 id="cg-title">Подарок,<br><em>который<br>запоминают</em></h1>
            <p class="cg-intro">Натуральный шоколад без белого сахара.<br>Приятно подарить и получить в подарок.</p>
            <div class="cg-hero-actions"><a class="button" href="#corporate-request">Рассчитать заказ</a><?php if ($catalog_url !== '') : ?><a class="button" href="<?php echo $catalog_url; ?>" target="_blank" rel="noopener">Скачать каталог PDF</a><?php else : ?><a class="button" href="#corporate-request" data-cg-request="Каталог PDF">Запросить каталог</a><?php endif; ?></div>
        </div>
    </section>
    <div class="cg-ribbon">
        <div class="cg-ribbon-track">
            <?php for ($copy = 0; $copy < 2; $copy++) : ?>
            <div class="cg-ribbon-group"<?php echo $copy ? ' aria-hidden="true"' : ''; ?>><span>Без белого сахара</span><span>Доставка по всей России</span><span>Только натуральные ингредиенты</span><span>Чистый состав</span></div>
            <?php endfor; ?>
        </div>
    </div>
    <section class="cg-solutions cg-shell" aria-labelledby="cg-solutions-title">
        <h2 id="cg-solutions-title">Готовые <em>решения</em></h2>
        <div class="cg-gift-grid">
            <?php foreach ($gifts as $index => $gift) : ?>
                <article class="cg-gift" data-cg-gift>
                    <a class="cg-gift-image" href="#corporate-request" data-cg-open="<?php echo esc_attr((string) $index); ?>" aria-label="Подробнее о наборе «<?php echo esc_attr($gift['name']); ?>»"><img src="<?php echo esc_url($asset_base . $gift['image'] . '-original.png'); ?>" alt="Подарочный набор «<?php echo esc_attr($gift['name']); ?>»" width="720" height="430" loading="lazy" decoding="async"></a>
                    <div class="cg-gift-copy"><h3>«<?php echo esc_html($gift['name']); ?>»</h3><p><?php echo esc_html($gift['description']); ?></p><span class="cg-price">от <?php echo esc_html($gift['price']); ?> руб.</span><a class="button" href="#corporate-request" data-cg-open="<?php echo esc_attr((string) $index); ?>">Смотреть<span class="screen-reader-text"> набор «<?php echo esc_html($gift['name']); ?>»</span></a></div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="cg-details cg-shell" aria-labelledby="cg-details-title">
        <div class="cg-details-intro"><h2 id="cg-details-title">Эстетика и <em>детали</em></h2><p>Мы делаем премиальное оформление со вкусом и чувством стиля.</p><img src="<?php echo esc_url($asset_base . 'personalization-original.png'); ?>" width="532" height="444" loading="lazy" decoding="async" alt="Фирменная открытка на подарочной коробке"></div>
        <div class="cg-detail-grid">
            <?php foreach ($details as $index => $detail) : ?><article><h3><?php echo esc_html($detail[0]); ?></h3><p><?php echo esc_html($detail[1]); ?></p><p class="cg-detail-note"><?php echo esc_html($detail[2]); ?></p><span aria-hidden="true">0<?php echo esc_html((string) ($index + 1)); ?></span></article><?php endforeach; ?>
        </div>
    </section>
    <?php if ($gallery_enabled && $gallery !== array()) : ?>
    <section class="cg-gallery cg-shell" aria-labelledby="cg-gallery-title" data-cg-carousel>
        <h2 id="cg-gallery-title"><?php if ($gallery_title !== '') : ?><?php echo esc_html($gallery_title); ?><?php else : ?>Шоколад, который точно<br><em>понравится и удивит</em><?php endif; ?></h2>
        <div class="cg-gallery-track" data-cg-track tabindex="0" aria-label="Фотографии шоколада и подарков">
            <?php foreach ($gallery as $photo) : ?><img src="<?php echo esc_url($photo['url']); ?>" alt="<?php echo esc_attr($photo['alt']); ?>" width="560" height="840" loading="lazy" decoding="async"><?php endforeach; ?>
        </div>
        <?php if (count($gallery) > 1) : ?><div class="cg-carousel-controls"><button type="button" data-cg-direction="-1" aria-label="Предыдущие фотографии">‹</button><button type="button" data-cg-direction="1" aria-label="Следующие фотографии">›</button></div><?php endif; ?>
    </section>
    <?php endif; ?>
    <section class="cg-season cg-shell" aria-labelledby="cg-season-title"><div><h2 id="cg-season-title">Чтобы успеть<br>к <em>Новому году</em></h2><p>Оставьте заявку до 15 ноября. После этой даты принимаем заказы при наличии производственных мощностей.</p><a class="button" href="#corporate-request">Оставить заявку</a></div><img src="<?php echo esc_url($asset_base . 'chocolate-piece-original.png'); ?>" width="376" height="368" loading="lazy" decoding="async" alt="Кусочек горького шоколада"></section>
    <section class="cg-faq cg-shell" aria-labelledby="cg-faq-title"><h2 id="cg-faq-title">Частые <em>вопросы</em></h2><div class="cg-faq-list">
        <?php foreach ($questions as $index => $question) : ?><details<?php echo $index === 0 ? ' open' : ''; ?>><summary><?php echo esc_html($question[0]); ?><img src="<?php echo esc_url($asset_base . 'chevron.svg'); ?>" width="23" height="12" alt=""></summary><p><?php echo esc_html($question[1]); ?></p></details><?php endforeach; ?>
    </div></section>
    <?php if ($site_reviews !== array()) : ?>
    <section class="cg-reviews cg-shell" aria-labelledby="cg-reviews-title" data-cg-carousel><h2 id="cg-reviews-title">Что говорят<br>о нашем <em>шоколаде</em></h2><div class="cg-review-track" data-cg-track tabindex="0" aria-label="Отзывы покупателей">
        <?php foreach ($site_reviews as $review) : ?><article><div class="cg-review-text"><?php echo wp_kses_post(wpautop($review->post_content)); ?></div><time datetime="<?php echo esc_attr(get_the_date('Y-m-d', $review)); ?>"><?php echo esc_html(get_the_date('d.m.Y', $review)); ?></time><h3><?php echo esc_html($review->post_title); ?></h3><img src="<?php echo esc_url($asset_base . 'quote.svg'); ?>" width="35" height="35" loading="lazy" alt=""></article><?php endforeach; ?>
    </div><div class="cg-review-controls"><button type="button" data-cg-direction="-1" aria-label="Предыдущие отзывы">‹</button><button type="button" data-cg-direction="1" aria-label="Следующие отзывы">›</button></div></section>
    <?php endif; ?>
    <section class="cg-request" id="corporate-request" aria-labelledby="cg-request-title"><div class="cg-shell cg-request-layout">
        <div><h2 id="cg-request-title"><em>Оставьте заявку</em><br>мы свяжемся<br>в течение дня</h2><address><a href="mailto:opt@theobroma.msk.ru">opt@theobroma.msk.ru</a><a href="tel:+79257555626">+7 925 755-56-26</a><span>пн–пт 09:00–18:00</span></address></div>
        <div id="contact-form">
            <?php if (($_GET['contact'] ?? '') === 'sent') : ?><p class="cg-form-status" role="status">Спасибо! Заявка отправлена. Мы свяжемся с вами.</p><?php elseif (($_GET['contact'] ?? '') === 'error') : ?><p class="cg-form-status" role="alert">Не удалось отправить заявку. Проверьте обязательные поля и согласие, затем попробуйте ещё раз.</p><?php endif; ?>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-cg-form>
                <input type="hidden" name="action" value="theobroma_contact"><input type="hidden" name="request_type" value="corporate_gift"><input type="hidden" name="form_id" value="corporate">
                <?php wp_nonce_field('theobroma_contact', 'theobroma_contact_nonce'); ?>
                <?php theobroma_contact_antispam_fields(); ?>
                <div class="form-grid">
                    <?php if (function_exists('theobroma_contact_forms_render_fields')) : ?><?php echo theobroma_contact_forms_render_fields('corporate'); ?><?php else : ?>
                        <input name="name" placeholder="Имя" autocomplete="name" aria-label="Имя"><input type="tel" name="phone" required placeholder="Номер телефона" autocomplete="tel" aria-label="Телефон"><input name="message" placeholder="Комментарий" aria-label="Комментарий">
                    <?php endif; ?>
                </div>
                <label class="consent"><input type="checkbox" name="consent" value="1" required><span>Отправляя форму, я даю <a href="<?php echo esc_url(theobroma_page_url('Согласие на обработку персональных данных')); ?>">согласие</a> на <a href="<?php echo esc_url(theobroma_page_url('Политика конфиденциальности')); ?>">обработку персональных данных</a></span></label><button class="button" type="submit">Отправить заявку</button>
            </form>
        </div>
    </div></section>
    <dialog class="cg-dialog" aria-labelledby="cg-dialog-title"><div class="cg-dialog-content"><header><h2 id="cg-dialog-title"></h2><button class="cg-dialog-close" type="button" aria-label="Закрыть просмотр набора" autofocus>×</button></header><img class="cg-dialog-image" alt="" width="850" height="500"><div class="cg-dialog-bottom"><p class="cg-dialog-description"></p><span class="cg-price"></span><button type="button" class="button" data-cg-order>Заказать</button></div></div></dialog>
</main>
