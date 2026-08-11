# Fluid REM Responsive Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Перевести весь пользовательский интерфейс Theobroma на управляемую `rem`-систему, сохранив текущий вид на 390, 768 и 1440 px, обеспечив плавное масштабирование от 320 до 2560 px и фиксированный масштаб на более широких экранах.

**Architecture:** Три существующих структурных режима сохраняются и продолжают переключаться media query в физических CSS-пикселях. Размер `1rem` остаётся равным 16 px в контрольном диапазоне 390–1440 px, плавно уменьшается до 15 px на 320 px, плавно увеличивается до 20 px между 1440 и 2560 px и далее не растёт. Масштабируемые размеры переводятся из `px` в `rem` с коэффициентом `16`; однопиксельные линии, растровые координаты, media query и обязательные минимальные touch-target остаются в `px` либо получают явное ограничение.

**Tech Stack:** WordPress theme PHP, CSS, Node.js 22, Playwright 1.62.1, `node:assert/strict`.

## Global Constraints

- Охват: главная, каталог, карточки товаров, рецепты, медиа, юридические и служебные страницы, хедер, футер, формы, WooCommerce, личный кабинет, корзина, checkout, избранное и все модальные состояния.
- Не выполнять заметный редизайн; контрольные ширины 390, 768 и 1440 px должны сохранить текущую композицию и пропорции.
- Поддерживаемый диапазон: 320–2560 px без горизонтального переполнения; при ширине более 2560 px размер `rem` равен размеру на 2560 px.
- Структурные media query остаются в `px`; единицы `rem` применяются к масштабируемой геометрии и типографике.
- Значения `1px` и `2px` для границ, outline и hairline-декора не конвертируются.
- Минимальная интерактивная область не становится меньше текущего размера на 390 px; критические контролы получают нижнюю границу в физических `px`.
- Не добавлять новые npm-зависимости и не менять PHP/WordPress-контракты.
- Не запускать миграции, синхронизацию данных или команды, изменяющие базу.

---

### Task 1: Зафиксировать контракт fluid-rem тестами

**Files:**
- Create: `scripts/verify-fluid-rem-responsive.spec.js`
- Modify: `package.json`
- Reuse: `scripts/pairwise-audit.config.json`

**Interfaces:**
- Consumes: `THEOBROMA_URL`, по умолчанию `http://localhost:8080`; существующие маршруты из `pairwise-audit.config.json`.
- Produces: команда `npm run test:fluid-rem`; проверка масштаба root, отсутствия горизонтального overflow и стабильности масштаба после 2560 px.

- [ ] **Step 1: Написать падающий браузерный тест root scale**

Создать `scripts/verify-fluid-rem-responsive.spec.js` с профилями:

```js
const profiles = [
  { width: 320, expectedRoot: 15 },
  { width: 390, expectedRoot: 16 },
  { width: 768, expectedRoot: 16 },
  { width: 1440, expectedRoot: 16 },
  { width: 1920, expectedRoot: 17.7143 },
  { width: 2560, expectedRoot: 20 },
  { width: 3200, expectedRoot: 20 },
  { width: 3840, expectedRoot: 20 },
];
```

Для каждого профиля открыть `/`, получить `parseFloat(getComputedStyle(document.documentElement).fontSize)` и проверить отклонение не более `0.02`. Дополнительно сравнить computed-размеры `.brand`, `.button` и `.footer-shell` на 2560 и 3200 px: свойства, выраженные в `rem`, должны совпадать с допуском `0.5px`, если элемент не ограничен шириной viewport.

- [ ] **Step 2: Добавить проверку representative routes без overflow**

Проверить ширины `320, 390, 600, 768, 900, 1199, 1200, 1440, 1920, 2560, 3200` на маршрутах:

```js
const routes = [
  '/',
  '/catalog/',
  '/product/theobroma-100-70/',
  '/recipes/',
  '/recipe/classic/',
  '/delivery/',
  '/media/',
  '/chto-oznachayut-protsenty-na-plitke-shokolada/',
  '/policy/',
  '/corporate-gifts/',
  '/my-account/',
  '/cart/',
  '/checkout/',
];
```

Для каждой комбинации проверять HTTP 200, отсутствие page errors и `scrollWidth - clientWidth <= 1`. На маршрутах, которые перенаправляют неавторизованного пользователя, принимать конечный HTTP 200 и проверять отсутствие ухода за пределы origin.

- [ ] **Step 3: Подключить команду теста**

Добавить в `package.json`:

```json
"test:fluid-rem": "node scripts/verify-fluid-rem-responsive.spec.js"
```

- [ ] **Step 4: Запустить тест и подтвердить RED**

Run: `npm run test:fluid-rem`

Expected: FAIL на первой проверке root scale, потому что текущий `html` не задаёт fluid `font-size`.

- [ ] **Step 5: Commit**

```bash
git add package.json scripts/verify-fluid-rem-responsive.spec.js
git commit -m "test: define fluid rem responsive contract"
```

---

### Task 2: Добавить корневую шкалу и общие размерные токены

**Files:**
- Modify: `wp-content/themes/theobroma/style.css:15-24`
- Test: `scripts/verify-fluid-rem-responsive.spec.js`

**Interfaces:**
- Consumes: стандартный CSS `clamp()` и текущие color tokens.
- Produces: root scale `15px@320 → 16px@390–1440 → 20px@2560+`; общие токены контейнеров и touch target.

- [ ] **Step 1: Реализовать root scale с точными якорями**

Расширить `:root` и `html`:

```css
:root {
  --ink:#241d19;
  --paper:#fcf9f7;
  --cream:#f3ebe4;
  --gold:#b0903d;
  --line:#dfd2c3;
  --muted:#766c64;
  --layout-wide:80rem;
  --layout-content:72.5rem;
  --touch-min:44px;
}

html {
  overflow-x:hidden;
  scroll-behavior:smooth;
  font-size:clamp(15px,calc(10.4286px + 1.4286vw),16px);
}

@media (min-width:1441px) {
  html { font-size:clamp(16px,calc(10.8571px + .357143vw),20px); }
}
```

Media query выше не меняет структуру, а только включает второй отрезок шкалы. `clamp()` гарантирует 20 px при 2560 px и выше.

- [ ] **Step 2: Защитить системные и доступные размеры**

Оставить `.screen-reader-text` с `1px`, границы и outline с `1px/2px`. Для кнопок закрытия, burger, cart/account controls и checkbox, которые после масштабирования могут стать меньше допустимого, использовать `min-width:min(var(--touch-min), 100%)` или сохранить существующий физический minimum там, где макет уже плотный. Не изменять SVG data URI и media query.

- [ ] **Step 3: Запустить root-scale часть теста**

Run: `npm run test:fluid-rem`

Expected: проверки root scale PASS; общий тест всё ещё может FAIL на 320/2560 из-за несконвертированной геометрии.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/theobroma/style.css
git commit -m "feat: add capped fluid rem scale"
```

---

### Task 3: Конвертировать общую оболочку, хедер и футер

**Files:**
- Modify: `wp-content/themes/theobroma/style.css` selectors `body`, `.site-header`, `.shipping`, `.nav`, `.brand`, `.floating-actions`, `.button`, `.section`, `.site-footer`, `.footer-shell`, `.copyright`, `.mobile-menu`
- Modify: `scripts/verify-responsive-header.spec.js`
- Test: `scripts/verify-fluid-rem-responsive.spec.js`
- Test: `scripts/verify-responsive-header.spec.js`

**Interfaces:**
- Consumes: root scale и `--layout-wide`/`--layout-content` из Task 2.
- Produces: масштабируемая общая оболочка для всех шаблонов и header/footer во всех трёх структурных режимах.

- [ ] **Step 1: Обновить тест хедера под rem scale**

В `verify-responsive-header.spec.js` вычислять `rootScale = parseFloat(getComputedStyle(document.documentElement).fontSize) / 16` и проверять desktop-размеры как базовый размер, умноженный на `rootScale`. Проверки видимости, центрирования, focus trap и overflow оставить абсолютными. Запустить тест до CSS-конвертации и подтвердить, что ожидание масштабирования на 2295 px падает.

- [ ] **Step 2: Конвертировать общие размеры по правилу `px / 16`**

Примеры обязательных замен:

```css
.nav {
  width:min(80rem,calc(100vw - 9.375rem));
  min-height:7rem;
  gap:2.125rem;
}

.brand,
.brand img { width:13.125rem; height:5.520625rem; }

.section { max-width:77.5rem; padding:5.75rem 1.75rem; }

.button {
  min-width:9.25rem;
  min-height:2.625rem;
  padding-inline:1.5rem;
  border-radius:62.4375rem;
}

.footer-shell { width:72.5rem; height:40.625rem; }
```

Значения `1px/2px`, media query и viewport units не менять. В `calc()` конвертировать только px-часть. Размеры touch controls меньше 44 px не уменьшать относительно 390 px.

- [ ] **Step 3: Проверить общую оболочку**

Run: `npm run test:header`

Expected: PASS на desktop, compact desktop, tablet и mobile.

Run: `node scripts/audit-local-pages.js --widths=390,768,1440,2560,3840`

Expected: PASS; общие страницы не получают горизонтального overflow.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/theobroma/style.css scripts/verify-responsive-header.spec.js
git commit -m "refactor: scale shared site shell with rem"
```

---

### Task 4: Конвертировать главную и контентные шаблоны

**Files:**
- Modify: `wp-content/themes/theobroma/style.css` selectors `.hero*`, `.home-*`, `#catalog`, `.feature`, `.about-*`, `.reviews-*`, `.contact*`, `.recipes-*`, `.recipe-*`, `.media-*`, `.article-*`, `.delivery-*`, `.marketplace-*`, `.buy-*`, `.cooperation-*`, `.corporate-gifts-*`, `.legal-*`
- Modify: `scripts/verify-responsive-transition.spec.js`
- Test: existing responsive scripts for each template.

**Interfaces:**
- Consumes: общая rem-шкала и оболочка Tasks 2–3.
- Produces: rem-геометрия всех публичных информационных страниц при неизменной структуре DOM.

- [ ] **Step 1: Расширить transition-тест крайними и промежуточными ширинами**

Изменить:

```js
const widths = [320, 390, 600, 768, 900, 901, 1024, 1159, 1199, 1200, 1280, 1440, 1920, 2560, 3200];
```

Добавить representative selectors для delivery, media article, recipe detail, legal и corporate gifts. Сохранить `assertInsideViewport`, дополнить общей проверкой document overflow. Запустить до конвертации и подтвердить RED хотя бы на одной ширине 320/2560.

- [ ] **Step 2: Конвертировать desktop-базы контентных секций**

Во всех перечисленных селекторах заменить масштабируемые `px` на точный `rem = px / 16`. Сохранить `%`, `vw`, `vh`, `dvh`, `fr`, `auto`, `minmax()` и aspect ratio. Для центрирования использовать существующий паттерн, меняя только фиксированную часть:

```css
left:calc(50vw - 36.25rem);
width:72.5rem;
```

Фиксированные высоты заменять на `min-height` там, где контент может увеличиваться при root scale и текущий DOM не требует artboard clipping. Декоративные artboard-секции сохраняют `height` в `rem` и `overflow:hidden`.

- [ ] **Step 3: Конвертировать mobile/tablet overrides**

Внутри существующих media query конвертировать геометрию тем же коэффициентом, не меняя границы `374/390/460/600/700/800/900/1199/1200px`. Устранить дублирующиеся overrides только если они задают одинаковое свойство одному селектору в одном диапазоне; не объединять разные структурные режимы.

- [ ] **Step 4: Запустить проверки публичных страниц**

Run:

```bash
node scripts/verify-responsive-transition.spec.js
node scripts/verify-home-tablet.spec.js
node scripts/verify-catalog-responsive.spec.js
node scripts/verify-recipes-listing-responsive.spec.js
node scripts/verify-recipe-responsive.spec.js
node scripts/verify-media-listing-responsive.spec.js
node scripts/verify-media-article.spec.js
node scripts/verify-delivery-responsive.spec.js
node scripts/verify-marketplace-responsive.spec.js
node scripts/verify-legal-responsive.spec.js
node scripts/verify-corporate-gifts.spec.js
node scripts/verify-cooperation-responsive.spec.js
```

Expected: все команды exit 0; контрольные ширины сохраняют существующие assertions, новые ширины не переполняются.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/theobroma/style.css scripts/verify-responsive-transition.spec.js
git commit -m "refactor: migrate public pages to fluid rem"
```

---

### Task 5: Конвертировать каталог, товар и коммерческие состояния

**Files:**
- Modify: `wp-content/themes/theobroma/style.css` selectors `.catalog-*`, `.product-*`, `.product-detail-*`, `.commerce-modal*`, `.commerce-cart*`, `.commerce-wishlist*`, `.commerce-cart-checkout*`, `.woocommerce-*`, `.account-modal*`, `.theobroma-loyalty-*`
- Modify: `scripts/verify-fluid-rem-responsive.spec.js`
- Test: existing catalog/product/cart/wishlist/account/checkout suites.

**Interfaces:**
- Consumes: rem-шкала Tasks 2–4 и существующие WooCommerce markup/contracts.
- Produces: масштабируемые product, account, cart, wishlist и checkout states без изменения бизнес-логики.

- [ ] **Step 1: Добавить state coverage в fluid-rem тест**

Для ширин `320, 390, 768, 1440, 2560, 3200` открыть и проверить:

- product modal через существующий commerce trigger;
- пустую и заполненную корзину;
- пустое и заполненное избранное;
- account modal/login;
- checkout form после существующего helper заполнения корзины.

Для каждого состояния проверять, что видимая panel входит во viewport, внутренний scroll доступен, close/back control видим, а document не получает горизонтальный overflow. Запустить до CSS-конвертации и подтвердить RED на широком или узком профиле.

- [ ] **Step 2: Конвертировать catalog/product geometry**

Перевести desktop/tablet/mobile размеры карточек, галерей, accordion, related products и CTA по `px / 16`. Ширины списков, которые намеренно скроллятся горизонтально, оставить `overflow-x:auto`, но ширины карточек выразить в `rem`. Изображения сохраняют `object-fit` и aspect ratio.

- [ ] **Step 3: Конвертировать modal/account/cart/wishlist geometry**

Размеры panel, padding, typography и offsets перевести в `rem`. `position:fixed`, `inset`, `100dvh`, centering transforms и z-index оставить без изменений. Close/back controls сохранить не меньше 44 физических px на touch-режимах.

- [ ] **Step 4: Конвертировать checkout и loyalty**

Перевести поля, labels, summaries, consent, totals и CTA в `rem`. Границы оставить `1px`; checkbox не меньше 20 физических px; основной submit не ниже текущих 60 px на 390 px. Не менять WooCommerce selectors, `!important`-контракты или JS data attributes.

- [ ] **Step 5: Запустить коммерческие проверки**

Run:

```bash
node scripts/verify-buy-responsive.spec.js
node scripts/verify-product-capabilities.spec.js
node scripts/verify-product-accordion-content.spec.js
node scripts/verify-commerce-flow.spec.js
node scripts/verify-wishlist.spec.js
node scripts/audit-account.spec.js
node scripts/audit-checkout.spec.js
node scripts/audit-interactive-states.spec.js
npm run test:fluid-rem
```

Expected: все команды exit 0; modal panels доступны на 320 px, масштабируются до 2560 px и не растут после него.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/theobroma/style.css scripts/verify-fluid-rem-responsive.spec.js
git commit -m "refactor: migrate commerce flows to fluid rem"
```

---

### Task 6: Полная регрессия, unit audit и документация

**Files:**
- Modify: `README.md`
- Modify if required by verified regressions: `wp-content/themes/theobroma/style.css`
- Test: all responsive/a11y/local audit scripts.

**Interfaces:**
- Consumes: завершённая миграция Tasks 1–5.
- Produces: документированная система единиц и доказательство работы 320–3840 px.

- [ ] **Step 1: Провести статический unit audit**

Run:

```powershell
$css = Get-Content -Raw 'wp-content/themes/theobroma/style.css'
[pscustomobject]@{
  Px = ([regex]::Matches($css, '(?<![-\w])(?:\d*\.)?\d+px\b')).Count
  Rem = ([regex]::Matches($css, '(?<![-\w])(?:\d*\.)?\d+rem\b')).Count
  Media = ([regex]::Matches($css, '@media\b')).Count
}
```

Expected: `rem` используется для всей масштабируемой геометрии; оставшиеся `px` объясняются media query, root formula, hairline, touch minimum или растровой привязкой. Не использовать целевой числовой лимит как замену визуальной проверке.

- [ ] **Step 2: Запустить полный local responsive audit**

Run:

```bash
node scripts/audit-local-pages.js --widths=390,768,1440,1920,2560,3840
npm run test:fluid-rem
npm run test:header
node scripts/audit-accessibility.js
node scripts/audit-keyboard-navigation.js
```

Expected: exit 0, HTTP 200, нет browser/page errors, horizontal overflow не больше 1 px, accessibility/keyboard assertions проходят.

- [ ] **Step 3: Проверить контрольные визуальные якоря**

Снять screenshots 390, 768 и 1440 px через `audit-local-pages.js` и сравнить с последним baseline/парным source-аудитом. Допустимы только локальные изменения, вызванные исправлением overflow или согласованием масштаба. При заметной разнице исправить CSS и повторить соответствующий тест.

- [ ] **Step 4: Проверить wide cap**

Сравнить computed root size и размеры representative components на 2560 и 3840 px. Root обязан быть ровно 20 px на обеих ширинах; контейнеры не должны расти, кроме свободного пространства viewport вокруг них.

- [ ] **Step 5: Документировать правила CSS units**

Добавить в `README.md` раздел:

```markdown
## Адаптивные единицы

- 390–1440 px: `1rem = 16px`, поэтому контрольные макеты сохраняют исходные размеры.
- 320–390 px: root плавно уменьшается с 16 до 15 px.
- 1440–2560 px: root плавно увеличивается с 16 до 20 px.
- Выше 2560 px root остаётся 20 px.
- Media query и hairline-границы задаются в `px`; масштабируемая геометрия и типографика — в `rem`.
```

- [ ] **Step 6: Финальная проверка diff и commit**

Run:

```bash
git diff --check
git status --short
git diff --stat origin/main...HEAD
```

Expected: нет whitespace errors, изменены только план, CSS, responsive tests, package scripts и README.

```bash
git add README.md wp-content/themes/theobroma/style.css package.json scripts/verify-fluid-rem-responsive.spec.js scripts/verify-responsive-header.spec.js scripts/verify-responsive-transition.spec.js docs/superpowers/plans/2026-08-11-fluid-rem-responsive.md
git commit -m "docs: document fluid rem responsive system"
```
