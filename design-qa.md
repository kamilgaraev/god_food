# Design QA — главная Theobroma

Дата финального прохода: 2026-08-08.

## Источник визуальной истины

- ТЗ: `C:\Users\kamilgaraev\Downloads\редизайн главной теоброма.docx`.
- Основной референс hero + каталог: `C:\Users\KAMILG~1\AppData\Local\Temp\codex-clipboard-18f958b8-5c15-44de-9ce4-73640d549c82.png` — 1200 × 1222 px.
- Основной референс picker + состав: `C:\Users\KAMILG~1\AppData\Local\Temp\codex-clipboard-54a04377-1613-4cd9-ab72-2df9e1708b86.png` — 1200 × 1222 px.
- Исходное проблемное состояние ultrawide:
  - `C:\Users\KAMILG~1\AppData\Local\Temp\codex-clipboard-827aea48-c36f-4440-85b2-940d421aa096.png`;
  - `C:\Users\KAMILG~1\AppData\Local\Temp\codex-clipboard-d17df6a8-1c98-4bf3-bfda-db8a43fadff5.png`;
  - `C:\Users\KAMILG~1\AppData\Local\Temp\codex-clipboard-b2dad327-ab9b-4de3-8ae3-8c4d174e4a32.png`.

Референсы используются как композиционный и стилистический ориентир, а не как пиксельный макет: состав страницы и реальные товары определяются ТЗ и WooCommerce.

## Реализация и нормализация

- URL: `http://localhost:8080/` в Docker, Chrome/Playwright, `deviceScaleFactor: 1`.
- Основные файлы реализации:
  - `wp-content/themes/theobroma/index.php`;
  - `wp-content/themes/theobroma/header.php`;
  - `wp-content/themes/theobroma/inc/homepage.php`;
  - `wp-content/themes/theobroma/template-parts/home/product-card.php`;
  - `wp-content/themes/theobroma/assets/css/home-redesign.css`;
  - `wp-content/themes/theobroma/assets/js/homepage.js`.
- Проверенные CSS viewport: 2295 × 1119, 1440 × 900, 1200 × 1222, переходный 1101 × 1000, 768 × 1024, 390 × 844.
- Все implementation screenshots сняты в CSS-размере viewport при DPR 1; source 1200 × 1222 сравнивался с implementation 1200 × 1222 без масштабирования плотности.
- Cookie notice перед финальными снимками закрыт штатной кнопкой, чтобы сравнивать основной интерфейс в одинаковом состоянии.

## Визуальные доказательства

### Совмещённые сравнения

- Hero + начало каталога: `output/playwright/design-qa/comparison-hero-1200.png`.
- Picker + следующий контент: `output/playwright/design-qa/comparison-picker-1200.png`.
- Ultrawide hero до/после: `output/playwright/design-qa/comparison-wide-hero-before-after.png`.
- Ultrawide каталог до/после: `output/playwright/design-qa/comparison-wide-catalog-before-after.png`.
- Ultrawide picker до/после: `output/playwright/design-qa/comparison-wide-picker-before-after.png`.

### Полные и фокусные снимки

- Полная страница desktop: `output/playwright/design-qa/home-full-1200x1222.png`.
- Полная страница mobile: `output/playwright/design-qa/home-full-390x844.png`.
- Для каждого viewport сохранены `home-top-*`, `catalog-*` и `cacao-*` в `output/playwright/design-qa/`.
- Переходный desktop/tablet breakpoint: `output/playwright/design-qa/cacao-1101x1000.png`.
- Runtime evidence: `output/playwright/design-qa/runtime-evidence.json`; горизонтальный overflow отсутствует на всех пяти viewport, browser console/page errors отсутствуют.

Фокусные снимки нужны, потому что на полной странице невозможно надёжно оценить мелкую типографику, обрезку глифов, размер карточек, sticky-header и фактический масштаб круга picker.

## Обязательные поверхности fidelity

### Типографика

- Использованы существующие Cormorant и Montserrat, без новых шрифтов и подмен.
- Display-иерархия соответствует референсам: крупный Cormorant для «ШОКОЛАД», заголовков каталога и picker; Montserrat для навигации, фактов и коммерческих подписей.
- На mobile фактический glyph-range слова «ШОКОЛАД» теперь равен x=17.1…372.9 px при viewport 390 px; последняя буква полностью видна.
- Трёхстрочный picker-title на 1200 px признан допустимой композицией: он уравновешивает круг 403 px, не пересекается и не создаёт overflow.

### Ритм и сетка

- Hero компактный: CTA и строка преимуществ находятся above the fold на 1440 × 900 и 390 × 844.
- Meaningful content ограничен shell-ами; на ultrawide страница больше не растягивает композицию по всей ширине.
- Каталог: 4 карточки desktop, 2 tablet, горизонтальная лента mobile; на 1200 px реальные поля по 48 px, на 2295 px grid 1432 px и карточки по 340 px.
- Picker: круг 403 px на 1200 и 420 px на 1440/2295; mobile 304 px. Композиционный акцент соответствует референсу.
- Sticky header после прокрутки остаётся 78 px desktop и 68 px mobile/tablet; логотип, корзина и бургер сохраняют одну строку.

### Цвета и токены

- Сохранена фирменная тёплая палитра: paper `#fbf7f1`, ink `#2a1a10`, sand `#f1e6d5`, peach `#c98268`, line `#ded0bd`.
- Контраст основных CTA, выбранного процента, корзины и focus states не потерян; accessibility audit прошёл 104/104 маршрутов.
- Декоративные эффекты ограничены лёгким blur/shadow хедера и не создают generic-card drift.

### Качество изображений

- В hero используется реальный существующий прозрачный ассет пористого шоколада; CSS-рисунки и placeholder-иллюстрации не применяются.
- Карточки и picker получают реальные WooCommerce images; `object-fit` сохраняет продукт без растяжения.
- На проверенных viewport нет обрезанных упаковок, transparency halos или размытого upscale, влияющего на восприятие.

### Контент

- Смысловые блоки и копирайт соответствуют ТЗ: натуральность, ГИ 35, преимущества, четыре реальные позиции, проценты 59/65/68/70/80, состав, существующие бренд/награды, отзывы, форма и футер.
- Коммерческие данные не дублируются в JS и берутся из `WC_Product`; отсутствующие группы/SKU имеют серверное пустое состояние.
- Значения сахара не выдумываются: используется реальное краткое описание продукта.

### Иконки, состояния и доступность

- Использованы существующие asset-иконки аккаунта/корзины; account target 42 px, cart 42 px desktop и 38 px mobile. Белая исходная account-иконка получила тёмный scoped filter и читается на светлом header.
- Проверены keyboard mobile menu, focus order, picker 70→80 без reload, add-to-cart и обновление счётчика, selected state, reduced motion, безопасный неизвестный `cacao_percentage`.
- `prefers-reduced-motion` отключает переходы; document width равен viewport на всех шести проверенных разрешениях.

## История итераций P0/P1/P2

1. **P1 — hero и ultrawide-композиция были viewport-driven.** Исходный hero занимал почти весь экран, заголовок и chocolate-object конфликтовали, каталог начинался слишком поздно. Исправлено в `2252a2e`: scoped reset legacy h1, hero shell до 1440 px, высота 420–470 px desktop, CTA above fold. Post-fix: `comparison-hero-1200.png`, `comparison-wide-hero-before-after.png`.
2. **P2 — каталог и picker чрезмерно растягивались на ultrawide.** Исправлено в `9736eff`: capped grid/shell и центрирование. Post-fix: `comparison-wide-catalog-before-after.png`, `comparison-wide-picker-before-after.png`.
3. **P2 — picker circle оставался 279–301 px, каталог на 1200 имел нулевые поля.** Исправлено в `6838380`: фактический круг 403–420 px, catalog gutters 48 px, добавлены lower/upper geometry gates. Повторное независимое ревью — APPROVED.
4. **P2 — mobile glyph clipping.** При 390 px range заголовка доходил до 456.6 px, хотя контейнер не создавал document overflow. Исправлено уменьшением mobile display-size до 19vw и отдельным glyph-range assertion; post-fix right=372.9 px.
5. **P2 — legacy sticky-header возвращался после scroll.** Высота становилась 102–112 px, а на 768 px элементы переносились на две строки; account/cart targets визуально схлопывались до 20–22 px. Добавлен scoped `.nav-sticky .nav` reset и специфичные правила действий; post-fix header 78/68 px, controls 42/38 px, единый ряд.
6. **P2 — белая account-иконка была почти невидима на светлом header.** Добавлен scoped dark filter с opacity 0.72 и автоматическая проверка, что filter не сброшен в `none`; post-fix: `home-top-1440x900.png`.
7. **P3 — узкий desktop 1101 px обрезал правый край picker и сжимал sticky-navigation.** Breakpoint picker поднят до 1168 px, legacy gaps/transforms/orders навигации сброшены специфичными правилами. Panel теперь x=44…804 px при viewport 1101, крайняя левая ссылка заканчивается на x=395 при logo x=471; post-fix: `cacao-1101x1000.png`.

После исправлений повторно сняты основные пять viewport, переходный 1101 px и совмещённые сравнения. Actionable P0/P1/P2 различий не осталось.

## Проверки

- `npm run test:home-visual` — PASS.
- `npm run test:header` — PASS.
- `node scripts/verify-home-redesign.spec.js` — PASS.
- `node scripts/verify-home-tablet.spec.js` — PASS.
- `node scripts/verify-catalog-responsive.spec.js` — PASS.
- `node scripts/verify-commerce-flow.spec.js` — PASS на 390/768/1440.
- `node scripts/audit-keyboard-navigation.js` — PASS на 390/1440.
- `node scripts/audit-accessibility.js` — 104/104 PASS.
- PHP syntax — PASS для восьми PHP-файлов redesign; homepage data contract — PASS.
- Core Web Vitals home:
  - desktop: LCP 680 ms, CLS 0, INP 0 ms, TTFB 318.9 ms;
  - mobile fast 4G: LCP 1924 ms, CLS 0, INP 64 ms, TTFB 260.9 ms.

## Findings

Actionable P0/P1/P2 findings: none.

## Follow-up polish

Отдельных P3-изменений перед handoff не требуется. Внутренние страницы и нижние существующие блоки сознательно не перерабатывались за пределами ТЗ.

final result: passed
