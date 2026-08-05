# Локальный WordPress Theobroma

Сайт запускается в Docker и доступен по адресу <http://localhost:8080>.

## Запуск

```powershell
docker compose up -d
```

Админка: <http://localhost:8080/wp-admin/>.

## Воспроизводимая настройка

После первого запуска или развёртывания в новом Docker volume выполните:

```powershell
docker exec food-wordpress-1 php /var/www/html/scripts/sync-catalog.php
docker exec food-wordpress-1 php /var/www/html/scripts/sync-pages.php
docker exec food-wordpress-1 php /var/www/html/scripts/sync-recipes.php
docker exec food-wordpress-1 php /var/www/html/scripts/sync-reviews.php
docker exec food-wordpress-1 php /var/www/html/scripts/configure-wordpress.php
docker exec food-wordpress-1 php /var/www/html/scripts/verify-wordpress.php
```

Скрипты можно запускать повторно: они обновляют существующие сущности, не создавая дубликатов.

- `sync-catalog.php` синхронизирует 27 товаров, категории, изображения и содержимое карточек.
- `sync-pages.php` синхронизирует юридические документы и раздел «Медиа».
- `sync-recipes.php` синхронизирует исходные рецепты, ингредиенты, шаги и изображения.
- `sync-reviews.php` синхронизирует исходные отзывы главной страницы.
- `configure-wordpress.php` настраивает WordPress/WooCommerce, красивые ссылки и активирует модуль управления сайтом.
- `verify-wordpress.php` проверяет конфигурацию, товары, материалы и административный модуль без изменения данных.

## Работа с контентом

После входа откройте пункт «Контент сайта» в верхней части меню админки. Там собраны переходы к товарам, рецептам, материалам «Медиа», общим блокам, страницам, заказам и изображениям.

У товара есть отдельный блок «Theobroma — содержимое карточки»: описание, состав, польза кокосового сахара и ссылки Wildberries/Ozon редактируются без изменения кода. У записей категории «Медиа» доступно поле внешней публикации; изображение меняется штатным механизмом «Изображение записи».

В разделе «Рецепты» можно создавать новые рецепты и задавать заголовок карточки, время приготовления, изображения, ингредиенты, шаги и связанные товары. После публикации карточка автоматически появляется на странице рецептов. Порядок карточек меняется полем «Порядок».

Отзывы главной редактируются в разделе «Отзывы сайта»: заголовок записи — имя автора, основной редактор — текст отзыва, дата публикации — дата на карточке, поле «Порядок» — позиция в ленте.

В разделе «Контент сайта → Общие блоки» редактируются верхняя плашка, первый экран главной, текст о компании, форма связи, адреса, email, реквизиты и социальные ссылки футера. Общий футер подключён штатно через `get_footer()` и используется всеми шаблонами.

## Резервная копия

Создайте каталог для копий:

```powershell
New-Item -ItemType Directory -Force backups
```

Сохраните базу данных и загруженные медиафайлы:

```powershell
docker compose exec -T db sh -c 'exec mysqldump --no-tablespaces -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > backups/theobroma.sql
docker cp food-wordpress-1:/var/www/html/wp-content/uploads backups/uploads
```

Тема, пользовательский модуль, Docker-конфигурация и скрипты синхронизации уже находятся в рабочей папке проекта и должны сохраняться вместе с ней.

## Управление контейнерами

```powershell
docker compose ps
docker compose logs -f wordpress
docker compose down
```

`docker compose down` не удаляет данные. Не используйте ключ `-v`, если не хотите удалить Docker volumes с WordPress и базой данных.
