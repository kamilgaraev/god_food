# Theobroma Photo Showcases Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Добавить самостоятельный WordPress-плагин для управления фотоподборками Главной и корпоративных подарков и встроить две фирменные адаптивные композиции в тему.

**Architecture:** Плагин разделён на чистую нормализацию настроек, получение стартовых attachment ID, frontend renderer и административную страницу. Тема вызывает только публичную функцию `theobroma_photo_showcase_html(string $location): string`, поэтому выключение плагина безопасно.

**Tech Stack:** PHP 8.1, WordPress Settings API и Media Library, vanilla JavaScript, CSS Grid/Snap, существующий Node/PHP acceptance-контур.

**Spec:** `docs/superpowers/specs/2026-08-25-photo-showcases-design.md`

## Global Constraints

- Работать только в `codex/photo-showcases`, созданной от актуального `origin/main`.
- Не добавлять сторонние зависимости и не менять схему базы данных.
- Хранить не более 8 уникальных attachment ID на подборку.
- Плагин должен деградировать в пустую строку при отсутствии валидных изображений или неизвестном location.
- Тема должна оставаться работоспособной при выключенном плагине.

---

### Task 1: Модель настроек и стартовая подборка

**Files:**
- Create: `wp-content/plugins/theobroma-photo-showcases/src/Settings.php`
- Create: `wp-content/plugins/theobroma-photo-showcases/src/DefaultImages.php`
- Create: `wp-content/plugins/theobroma-photo-showcases/tests/run.php`

**Interfaces:**
- Produces: `Settings::sanitize(mixed $value): array`, `Settings::defaults(): array`, `DefaultImages::ids(int $limit = 5): array`.

- [ ] Написать тесты с литеральными ожидаемыми массивами: неизвестные подборки отбрасываются, текст очищается, ID приводятся к положительным уникальным числам, лимит равен 8, выключение сохраняется, WooCommerce-источник возвращает только валидные image ID.
- [ ] Запустить `php wp-content/plugins/theobroma-photo-showcases/tests/run.php` и подтвердить падение из-за отсутствующих классов.
- [ ] Реализовать минимальные классы `Settings` и `DefaultImages` с `declare(strict_types=1)`.
- [ ] Повторно запустить тест и получить PASS.

### Task 2: Renderer и публичный API

**Files:**
- Create: `wp-content/plugins/theobroma-photo-showcases/src/Renderer.php`
- Create: `wp-content/plugins/theobroma-photo-showcases/src/Plugin.php`
- Create: `wp-content/plugins/theobroma-photo-showcases/theobroma-photo-showcases.php`
- Modify: `wp-content/plugins/theobroma-photo-showcases/tests/run.php`

**Interfaces:**
- Consumes: нормализованные коллекции из `Settings` и fallback ID из `DefaultImages`.
- Produces: `Renderer::html(string $location, array $settings): string`, `theobroma_photo_showcase_html(string $location): string`.

- [ ] Добавить failing-тесты на пустой результат, обе BEM-модификации, aria-labelledby, подписи, alt fallback и responsive attachment markup.
- [ ] Запустить PHP-тест и подтвердить ожидаемое падение из-за отсутствующего Renderer/API.
- [ ] Реализовать renderer, boot плагина, frontend enqueue только на Главной и странице корпоративных подарков и публичную функцию.
- [ ] Запустить PHP-тест и получить PASS.

### Task 3: Управляемая админка

**Files:**
- Create: `wp-content/plugins/theobroma-photo-showcases/src/AdminPage.php`
- Create: `wp-content/plugins/theobroma-photo-showcases/assets/admin.js`
- Create: `wp-content/plugins/theobroma-photo-showcases/assets/admin.css`
- Modify: `wp-content/plugins/theobroma-photo-showcases/src/Plugin.php`
- Modify: `wp-content/plugins/theobroma-photo-showcases/tests/run.php`

**Interfaces:**
- Consumes: `Settings::OPTION`, `Settings::get()` и `Settings::sanitize()`.
- Produces: экран `toplevel_page_theobroma-photo-showcases`, Media Library rows с `data-photo-row`, синхронизированными hidden attachment IDs и доступными reorder-кнопками.

- [ ] Добавить failing-тесты регистрации hooks/settings/menu/assets и HTML двух вкладок, кнопки медиатеки, пустого состояния, полей alt/подписи и move/remove controls.
- [ ] Запустить PHP-тест и подтвердить ожидаемое падение.
- [ ] Реализовать capability-guarded Settings API page и enqueue `wp_enqueue_media()` только на собственном экране.
- [ ] Реализовать vanilla JS для multiple selection, карточек, удаления, клавиатурных кнопок, drag-and-drop, перенумерации и пустого состояния.
- [ ] Реализовать премиальный responsive CSS экрана и повторно запустить PHP-тест до PASS.

### Task 4: Фирменные frontend-композиции и интеграция темы

**Files:**
- Create: `wp-content/plugins/theobroma-photo-showcases/assets/frontend.css`
- Modify: `wp-content/themes/theobroma/index.php`
- Modify: `wp-content/themes/theobroma/template-parts/pages/corporate-gifts.php`
- Modify: `wp-content/themes/theobroma/style.css`
- Create: `scripts/verify-photo-showcases.spec.js`
- Modify: `package.json`

**Interfaces:**
- Consumes: `theobroma_photo_showcase_html('home'|'corporate')`.
- Produces: визуальные секции `.theobroma-photo-showcase--home` и `.theobroma-photo-showcase--corporate`.

- [ ] Написать Node acceptance-тест, который проверяет безопасные function guards в обеих точках интеграции, BEM-сетки, desktop/mobile layouts, focus-visible и reduced-motion.
- [ ] Запустить `node scripts/verify-photo-showcases.spec.js` и подтвердить падение из-за отсутствующей интеграции/CSS.
- [ ] Добавить guarded renderer calls в заданные позиции шаблонов и контейнер нового блока в shared layout selector темы.
- [ ] Реализовать frontend CSS мозаики, корпоративной ленты, snap-mobile, focus и reduced-motion.
- [ ] Добавить script `test:photo-showcases` и повторно запустить acceptance-тест до PASS.

### Task 5: Документация, полная проверка и интеграция

**Files:**
- Modify: `README.md`
- Verify: все созданные и изменённые PHP/JS/CSS файлы.

- [ ] Дополнить README инструкцией «Фотоподборки»: первая настройка, загрузка, порядок, подписи, публикация и стартовые изображения.
- [ ] Запустить `php -l` для каждого изменённого PHP-файла, PHP-тест плагина, `npm run test:photo-showcases`, `npm run test:home-visual`, `npm run test:home-containers`, `npm run test:corporate-gifts-cleanup` и `npm run test:focus-ring`.
- [ ] Если локальный WordPress доступен, активировать плагин штатным конфигуратором или WP-CLI, снять desktop/mobile скриншоты Главной, корпоративной страницы и админки, визуально проверить переполнение и состояния.
- [ ] Проверить `git diff --check`, status и итоговый diff по требованиям спецификации.
- [ ] Закоммитить feature branch, слить её в актуальный `main`, повторить ключевые тесты на merge result и отправить `origin/main` без force push.
