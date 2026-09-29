<?php
declare(strict_types=1);

namespace Theobroma\Seo;

final class LlmsRenderer
{
    public function render(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        return "# Theobroma — Пища Богов\n\n"
            . "> Официальный сайт шоколадной фабрики Theobroma. Натуральный кусковый пористый шоколад без белого сахара.\n\n"
            . "## Основные страницы\n\n"
            . "- [Главная]($baseUrl/): о фабрике и продукции.\n"
            . "- [Каталог]($baseUrl/catalog/): актуальный ассортимент; цены, состав и наличие указаны на страницах товаров.\n"
            . "- [Корпоративные подарки]($baseUrl/corporate-gifts/): наборы, персонализация и ответы на вопросы.\n"
            . "- [Где купить]($baseUrl/buy/): бутики и партнёры.\n"
            . "- [Доставка и оплата]($baseUrl/delivery/): актуальные условия заказа и доставки.\n"
            . "- [Сотрудничество]($baseUrl/cooperation/): оптовые поставки и контакты.\n"
            . "- [Рецепты]($baseUrl/recipes/): рецепты с шоколадом и какао.\n"
            . "- [Статьи]($baseUrl/media/): материалы о шоколаде и какао.\n\n"
            . "## Дополнительно\n\n"
            . "- [Карта сайта]($baseUrl/wp-sitemap.xml): индекс публичных страниц и товаров.\n"
            . "- [Политика персональных данных]($baseUrl/policy/).\n"
            . "- [Оферта]($baseUrl/oferta/).\n";
    }
}
