# Catalog Tabs And Hover Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Сделать hover изображения товара плавным и переключать группы каталога без перезагрузки документа.

**Architecture:** Серверные ссылки и WooCommerce-фильтрация остаются источником истины. Маленький клиентский контроллер перехватывает только обычные клики по фильтрам, получает готовую серверную страницу, заменяет каталог и синхронизирует History API; при ошибке остаётся обычная навигация.

**Tech Stack:** WordPress/PHP, WooCommerce, vanilla JavaScript, CSS, Playwright.

## Global Constraints

- Не менять серверный контракт `product_group`.
- Сохранить progressive enhancement обычных ссылок.
- Не затрагивать логику корзины и модальных окон.
- Реализовать изменение через TDD и проверить браузерное поведение.

---

### Task 1: Регрессионный браузерный контракт

**Files:**
- Create: `scripts/verify-catalog-interactions.spec.js`
- Modify: `package.json`

**Interfaces:**
- Consumes: `.catalog-page`, `.catalog-filters a`, WooCommerce `ul.products`.
- Produces: команда `npm run test:catalog-interactions`.

- [ ] **Step 1: Написать падающий Playwright-тест**

Тест загружает реальный CSS и скрипт темы в контролируемую DOM-страницу, подменяет сетевой ответ вторым каталогом, кликает фильтр и проверяет отсутствие `framenavigated`, обновление DOM/URL и промежуточное значение transform во время hover.

- [ ] **Step 2: Подтвердить корректное падение**

Run: `npm run test:catalog-interactions`

Expected: FAIL, потому что у изображения duration равен `0s`, а скрипт каталога ещё отсутствует.

### Task 2: Минимальная реализация

**Files:**
- Create: `wp-content/themes/theobroma/assets/js/catalog-filters.js`
- Modify: `wp-content/themes/theobroma/functions.php`
- Modify: `wp-content/themes/theobroma/style.css`

**Interfaces:**
- Consumes: URL существующих ссылок и HTML ответа `.catalog-page`.
- Produces: in-place обновление каталога, `history.pushState`, обработка `popstate` и `theobroma:catalog-updated`.

- [ ] **Step 1: Добавить минимальный клиентский контроллер**

Обработать обычный click, `fetch`, `DOMParser`, замену содержимого/классов каталога, отмену устаревшего запроса, fallback через `location.assign` и `popstate`.

- [ ] **Step 2: Подключить скрипт только в каталоге**

Использовать `wp_enqueue_script` с `filemtime`, `strategy => defer` и `in_footer => true` при `is_shop() || is_product_category()`.

- [ ] **Step 3: Добавить плавный transform**

Задать изображению карточки `transition: transform 650ms cubic-bezier(.22,.61,.36,1)` и отключить движение при `prefers-reduced-motion: reduce`.

- [ ] **Step 4: Подтвердить зелёный тест и полный релевантный набор**

Run: `npm run test:catalog-interactions`

Expected: PASS.

Run: `node scripts/verify-catalog-responsive.spec.js && node scripts/verify-catalog-spacing-product-copy-scale.spec.js && npm run test:catalog-interactions`

Expected: все команды завершаются с кодом 0.

- [ ] **Step 5: Закоммитить и интегрировать**

Commit production-код и тест, затем влить ветку в свежий `main`, повторно выполнить проверки и запушить `origin/main`.
