<?php

declare(strict_types=1);

require_once '/var/www/html/wp-load.php';

$descriptions = array(
    'catalog' => 'Каталог натурального пористого шоколада, какао и семян чиа Theobroma. Доставка заказов по России.',
    'recipes' => 'Рецепты десертов, завтраков и напитков с натуральным шоколадом и какао Theobroma.',
    'marketplace' => 'Официальные магазины Theobroma на маркетплейсах. Выбирайте натуральный шоколад «Пища Богов» у проверенных продавцов.',
    'buy' => 'Где купить натуральный шоколад Theobroma: фирменный интернет-магазин и розничные партнёры бренда.',
    'cooperation' => 'Оптовые поставки и сотрудничество с Theobroma. Контакты фабрики натурального шоколада «Пища Богов».',
    'corporate-gifts' => 'Корпоративные подарки Theobroma: брендированный натуральный шоколад для клиентов, команды и партнёров.',
    'delivery' => 'Условия доставки и оплаты заказов Theobroma. Бесплатная доставка от 2 500 ₽, отправка по России и в соседние страны.',
    'media' => 'Статьи о натуральном шоколаде, составе продуктов и культуре какао от редакции Theobroma.',
    'policy' => 'Политика обработки персональных данных интернет-магазина Theobroma «Пища Богов».',
    'agreement' => 'Пользовательское соглашение интернет-магазина Theobroma «Пища Богов».',
    'oferta' => 'Публичная оферта интернет-магазина Theobroma «Пища Богов»: условия заказа, оплаты и доставки товаров.',
);

foreach ($descriptions as $slug => $description) {
    $page = get_page_by_path($slug, OBJECT, 'page');
    if (!$page instanceof WP_Post) {
        throw new RuntimeException(sprintf('Required page /%s/ was not found.', $slug));
    }
    update_post_meta($page->ID, '_theobroma_seo_description', $description);
    echo sprintf("seo-description=%s|%d\n", $slug, $page->ID);
}

update_option(
    'blogdescription',
    'Натуральный пористый шоколад Theobroma — интернет-магазин «Пища Богов».'
);
echo "seo-home-description=updated\n";
