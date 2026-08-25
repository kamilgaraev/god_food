<?php
$showcase_products = function_exists('wc_get_products') ? wc_get_products(array(
    'status' => 'publish',
    'limit' => 3,
    'orderby' => 'menu_order',
    'order' => 'ASC',
    'return' => 'objects',
)) : array();
?>
<main class="corporate-gifts-page">
    <section class="corporate-gifts-hero">
        <h1><?php echo esc_html(theobroma_content('corporate_hero_title')); ?><br><em><?php echo esc_html(theobroma_content('corporate_hero_accent')); ?></em></h1>
        <p><?php echo esc_html(theobroma_content('corporate_intro')); ?></p>
        <a class="button" href="#corporate-request">Обсудить проект</a>
    </section>
    <section class="corporate-gifts-showcase" aria-labelledby="corporate-showcase-title">
        <header><h2 id="corporate-showcase-title">Основа вашего подарка</h2><p>Выберите шоколад — оформление, состав и тираж обсудим индивидуально.</p></header>
        <div class="corporate-gifts-showcase-grid home-product-grid">
            <?php foreach ($showcase_products as $product) : ?>
                <?php if (!$product instanceof WC_Product) { continue; } ?>
                <?php get_template_part('template-parts/home/product-card', null, array('product' => $product)); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="corporate-gifts-branding" aria-labelledby="corporate-branding-title">
        <header><h2 id="corporate-branding-title">Варианты брендирования</h2></header>
        <div>
            <?php for ($index = 1; $index <= 3; $index++) : ?>
                <article><span>0<?php echo esc_html((string) $index); ?></span><h3><?php echo esc_html(theobroma_content('corporate_branding_' . $index . '_title')); ?></h3><p><?php echo esc_html(theobroma_content('corporate_branding_' . $index . '_text')); ?></p></article>
            <?php endfor; ?>
        </div>
    </section>
    <?php if (function_exists('theobroma_photo_showcase_html')) { echo theobroma_photo_showcase_html('corporate'); } ?>
    <section class="corporate-gifts-cases" aria-labelledby="corporate-cases-title">
        <header><h2 id="corporate-cases-title">Для разных задач бизнеса</h2></header>
        <div>
            <?php for ($index = 1; $index <= 3; $index++) : ?>
                <article><h3><?php echo esc_html(theobroma_content('corporate_case_' . $index . '_title')); ?></h3><p><?php echo esc_html(theobroma_content('corporate_case_' . $index . '_text')); ?></p></article>
            <?php endfor; ?>
        </div>
    </section>
    <section class="corporate-gifts-minimum" aria-labelledby="corporate-minimum-title">
        <div><h2 id="corporate-minimum-title">Минимальный заказ</h2></div>
        <p><?php echo esc_html(theobroma_content('corporate_minimum')); ?></p>
        <a class="button" href="#corporate-request">Получить расчёт</a>
    </section>
    <section class="corporate-gifts-benefits" aria-label="Преимущества корпоративных подарков">
        <article><h2>Натуральный состав</h2><p>Шоколад без белого сахара и искусственных добавок.</p></article>
        <article><h2>Ваше брендирование</h2><p>Подберём оформление, открытки и формат набора под задачу.</p></article>
        <article><h2>Тираж и логистика</h2><p>Согласуем минимальный заказ, сроки производства и доставку.</p></article>
    </section>
    <section class="corporate-gifts-request" id="corporate-request" aria-labelledby="corporate-request-title">
        <div><h2 id="corporate-request-title">Расскажите о вашем проекте</h2><p>Менеджер свяжется с вами, уточнит детали и подготовит предложение.</p></div>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <input type="hidden" name="action" value="theobroma_contact"><input type="hidden" name="request_type" value="corporate_gift">
            <?php wp_nonce_field('theobroma_contact', 'theobroma_contact_nonce'); ?>
            <?php theobroma_contact_antispam_fields(); ?>
            <input name="name" required placeholder="Имя" autocomplete="name" aria-label="Имя"><input type="tel" name="phone" required placeholder="Номер телефона" inputmode="tel" autocomplete="tel" maxlength="18" aria-label="Телефон"><textarea name="message" placeholder="Коротко о задаче — необязательно" aria-label="Комментарий"></textarea>
            <label class="consent"><input type="checkbox" name="consent" value="1" required><span>Я согласен на обработку персональных данных</span></label><button class="button" type="submit">Отправить заявку</button>
        </form>
    </section>
</main>
