# WooCommerce Order UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Оформить страницы заказа WooCommerce в едином визуальном языке Theobroma и скрывать отсутствующий адрес доставки.

**Architecture:** Отдельный условно подключаемый CSS-файл оформляет нативную разметку WooCommerce на checkout/order endpoints и в личном кабинете. Один минимальный override шаблона клиентских данных сохраняет хуки WooCommerce, но не выводит пустой адрес доставки.

**Tech Stack:** WordPress, WooCommerce PHP templates, CSS, Node.js, Playwright.

**Spec:** `docs/superpowers/specs/2026-09-01-woocommerce-order-ui-design.md`

## Global Constraints

- Использовать только палитру и шрифты существующей темы Theobroma.
- Не использовать капс и искусственный letter-spacing.
- Не менять расчёты, статусы, оплату или жизненный цикл заказа.
- Не допускать горизонтальной прокрутки на ширине 390 px.
- Сохранить нативную семантику и хуки WooCommerce.

---

### Task 1: Visual contract and conditional stylesheet

**Files:**
- Create: `scripts/verify-woocommerce-order-ui.spec.js`
- Create: `wp-content/themes/theobroma/assets/css/woocommerce-order-ui.css`
- Modify: `wp-content/themes/theobroma/functions.php`
- Modify: `package.json`

**Interfaces:**
- Consumes: нативные классы WooCommerce `.woocommerce-order`, `.woocommerce-order-overview`, `.woocommerce-order-details`, `.woocommerce-customer-details`, `.woocommerce-orders-table`, `.woocommerce-order-pay`.
- Produces: условно подключаемый handle `theobroma-woocommerce-order-ui` и команда `npm run test:woocommerce-order-ui`.

- [ ] **Step 1: Write the failing browser contract**

Создать Playwright fixture с разметкой `order-received`, таблицей состава заказа, адресными колонками и таблицей списка заказов. Проверить литеральные результаты: фон карточки `rgb(243, 235, 228)`, отсутствие `text-transform: uppercase`, округлая основная кнопка, сетка адресов в две колонки на desktop, одна колонка на 390 px и `scrollWidth <= clientWidth`.

- [ ] **Step 2: Run the browser contract and confirm RED**

Run: `node scripts/verify-woocommerce-order-ui.spec.js`

Expected: FAIL, потому что `assets/css/woocommerce-order-ui.css` отсутствует.

- [ ] **Step 3: Implement the shared visual layer**

Создать `woocommerce-order-ui.css` с областями:

```css
.woocommerce-order,
.woocommerce-view-order,
.woocommerce-order-pay { color:#241d19; }

.woocommerce-order-overview,
.woocommerce-order-details,
.woocommerce-customer-details { background:#f3ebe4; }

@media (max-width:760px) {
  .woocommerce-orders-table tr { display:grid; }
}
```

Расширить эти правила до всех состояний из spec, используя `#fcf9f7`, `#f3ebe4`, `#b0903d`, `#714727`, Cormorant и Montserrat. В `functions.php` подключать файл с `filemtime` только при `is_checkout() || is_account_page()`. Добавить npm script:

```json
"test:woocommerce-order-ui": "node scripts/verify-woocommerce-order-ui.spec.js"
```

- [ ] **Step 4: Verify GREEN**

Run: `npm run test:woocommerce-order-ui`

Expected: PASS на desktop и mobile.

- [ ] **Step 5: Commit**

```bash
git add package.json scripts/verify-woocommerce-order-ui.spec.js wp-content/themes/theobroma/functions.php wp-content/themes/theobroma/assets/css/woocommerce-order-ui.css
git commit -m "feat: style WooCommerce order pages"
```

### Task 2: Customer details without an empty shipping card

**Files:**
- Create: `wp-content/themes/theobroma/woocommerce/order/order-details-customer.php`
- Create: `scripts/verify-order-customer-details.php`
- Modify: `package.json`

**Interfaces:**
- Consumes: `$order`, `wc_get_order()`, `woocommerce_order_details_after_customer_details` and upstream WooCommerce customer-details markup.
- Produces: billing card always when permitted; shipping card only when `get_formatted_shipping_address()` returns non-empty text.

- [ ] **Step 1: Write the failing PHP rendering contract**

Создать тестовый order double с заполненным billing address и пустым shipping address. Подключить theme override в output buffer и проверить:

```php
assert(str_contains($html, 'Платёжный адрес'));
assert(!str_contains($html, 'Адрес доставки'));
assert(!str_contains($html, 'Н/Д'));
```

Второй сценарий возвращает непустой shipping address и обязан вывести обе карточки.

- [ ] **Step 2: Run the renderer contract and confirm RED**

Run: `php scripts/verify-order-customer-details.php`

Expected: FAIL, потому что override отсутствует.

- [ ] **Step 3: Implement the minimal template override**

Скопировать актуальную структуру upstream `order/order-details-customer.php`, сохранить security guard и хуки. Вычислить `$shipping_address = $order->get_formatted_shipping_address()` и оборачивать shipping column условием `$show_shipping && $shipping_address !== ''`. Не подставлять `N/A`.

Добавить npm script:

```json
"test:woocommerce-order-customer": "php scripts/verify-order-customer-details.php"
```

- [ ] **Step 4: Verify GREEN**

Run: `npm run test:woocommerce-order-customer`

Expected: PASS для пустого и заполненного shipping address.

- [ ] **Step 5: Commit**

```bash
git add package.json scripts/verify-order-customer-details.php wp-content/themes/theobroma/woocommerce/order/order-details-customer.php
git commit -m "fix: hide empty shipping address on orders"
```

### Task 3: Regression and delivery

**Files:**
- Modify only if a regression is proven by the commands below.

**Interfaces:**
- Consumes: results of Tasks 1 and 2.
- Produces: verified commit suitable for fast-forwarding `origin/main`.

- [ ] **Step 1: Run syntax and focused tests**

```bash
php -l wp-content/themes/theobroma/functions.php
php -l wp-content/themes/theobroma/woocommerce/order/order-details-customer.php
npm run test:woocommerce-order-ui
npm run test:woocommerce-order-customer
npm run test:cart-checkout-ui
npm run test:account-profile
npm run test:account-delivery-address
```

Expected: all commands exit 0.

- [ ] **Step 2: Run diff hygiene checks**

```bash
git diff --check origin/main...HEAD
git status --short
```

Expected: no whitespace errors and no uncommitted files.

- [ ] **Step 3: Fast-forward remote main**

```bash
git fetch origin main
git merge-base --is-ancestor origin/main HEAD
git push origin HEAD:main
```

Expected: remote `main` advances without force push.
