# Локальный WordPress Theobroma

Сайт запускается в Docker и доступен по адресу <http://localhost:8080>.

## Запуск

```powershell
docker compose up -d
```

Браузерные acceptance-тесты устанавливаются отдельно от runtime WordPress:

```powershell
npm install
npm run audit:cwv
```

Отчёты и снимки сохраняются в `output/playwright/` и не попадают в Git.

Админка: <http://localhost:8080/wp-admin/>.

Локальные письма WordPress/WooCommerce принимаются Mailpit и доступны по адресу <http://localhost:8025>. Проверка реального письма покупателю:

```powershell
docker compose exec -T wordpress php /opt/theobroma-scripts/verify-email-flow.php
```

На production задайте SMTP-переменные `THEOBROMA_SMTP_HOST`, `THEOBROMA_SMTP_PORT`, `THEOBROMA_SMTP_USERNAME`, `THEOBROMA_SMTP_PASSWORD`, `THEOBROMA_SMTP_ENCRYPTION`, `THEOBROMA_MAIL_FROM` и `THEOBROMA_MAIL_FROM_NAME`. Без `THEOBROMA_SMTP_HOST` внешний SMTP-транспорт не включается.

## Воспроизводимая настройка

После первого запуска или развёртывания в новом Docker volume выполните:

```powershell
docker exec food-wordpress-1 php /opt/theobroma-scripts/sync-catalog.php
docker exec food-wordpress-1 php /opt/theobroma-scripts/sync-pages.php
docker exec food-wordpress-1 php /opt/theobroma-scripts/sync-recipes.php
docker exec food-wordpress-1 php /opt/theobroma-scripts/sync-reviews.php
docker exec food-wordpress-1 php /opt/theobroma-scripts/configure-wordpress.php
docker exec food-wordpress-1 php /opt/theobroma-scripts/verify-wordpress.php
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

Создание атомарного дампа MySQL, архива uploads, SHA-256 checksums и manifest:

```powershell
.\scripts\backup-site.ps1
```

Полная проверка создаёт новую копию, восстанавливает dump в изолированную временную БД, сравнивает контрольные количества сущностей, проверяет uploads-архив и удаляет только созданную тестовую БД:

```powershell
.\scripts\verify-backup-restore.ps1
```

Для production рекомендуется ежедневная копия базы и uploads, хранение минимум 14 ежедневных, 8 еженедельных и 12 ежемесячных копий, а также перенос копий в отдельное зашифрованное хранилище. Планировщик должен запускать `backup-site.ps1` (или эквивалентный server-side wrapper) вне web-root; минимум раз в месяц нужно выполнять restore-test. Тема, плагины, Docker-конфигурация и скрипты синхронизации дополнительно сохраняются в Git.

Production должен обслуживаться только по HTTPS. Образ WordPress собирается командой `docker compose build wordpress` и включает Apache-модули `headers`/`expires`. Базовые browser-security headers заданы в `docker/wordpress/.htaccess`; если TLS завершается на reverse proxy/CDN, именно там необходимо включить HTTP→HTTPS redirect и HSTS (`max-age=31536000; includeSubDomains`) после проверки HTTPS на всех поддоменах. Проверка локальных заголовков: `npm run audit:security`.

## Управление контейнерами

```powershell
docker compose ps
docker compose logs -f wordpress
docker compose down
```

`docker compose down` не удаляет данные. Не используйте ключ `-v`, если не хотите удалить Docker volumes с WordPress и базой данных.
