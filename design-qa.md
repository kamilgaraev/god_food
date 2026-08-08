# Design QA — главная Theobroma

Дата прохода: 2026-08-08.

## Источник визуальной истины

- ТЗ: `C:\Users\kamilgaraev\Downloads\редизайн главной теоброма.docx`.
- Референс header / hero / каталог: `C:\Users\KAMILG~1\AppData\Local\Temp\codex-clipboard-18f958b8-5c15-44de-9ce4-73640d549c82.png`, 1200 × 1222 px.
- Референс selector / состав / promo: `C:\Users\KAMILG~1\AppData\Local\Temp\codex-clipboard-54a04377-1613-4cd9-ab72-2df9e1708b86.png`, 1200 × 1222 px.
- Последние уточнения пользователя сильнее прежних допущений: hero без декоративного куска шоколада; тексты hero и видимая шкала процентов должны совпадать с референсами.

## Реализация и нормализация

- Локальная реализация: `http://localhost:8080/` в Docker.
- Browser QA: Codex In-app Browser, фактический viewport 1280 × 720 CSS px, DPR 1.
- Responsive capture: Chrome/Playwright, DPR 1, 1440 × 900, 768 × 1024, 390 × 844.
- Для пяти попарных сравнений source шириной 1200 px пропорционально нормализован до 1280 px; implementation снят при ширине 1280 px. Сравнивались одинаковые секции без browser chrome и без cookie notice.
- Browser console: ошибок и предупреждений нет. `document.scrollWidth === document.clientWidth` на всех тестовых viewport.

## Попарные сравнения

1. Header + hero + начало каталога: `output/playwright/design-qa-v3/pair-01-hero.png`.
2. Каталог: `output/playwright/design-qa-v3/pair-02-catalog.png`.
3. «Ваш процент какао»: `output/playwright/design-qa-v3/pair-03-cacao.png`.
4. Состав: `output/playwright/design-qa-v3/pair-04-composition.png`.
5. «Подарок» + «Где купить»: `output/playwright/design-qa-v3/pair-05-promo.png`.

Responsive evidence:

- `output/playwright/design-qa-v3/responsive-top-1440x900.png`;
- `output/playwright/design-qa-v3/responsive-cacao-1440x900.png`;
- `output/playwright/design-qa-v3/responsive-top-768x1024.png`;
- `output/playwright/design-qa-v3/responsive-cacao-768x1024.png`;
- `output/playwright/design-qa-v3/responsive-top-390x844.png`;
- `output/playwright/design-qa-v3/responsive-cacao-390x844.png`.

Фокусные сравнения обязательны: мелкая типографика hero, значения шкалы, реальные карточки и компактные нижние блоки недостаточно читаемы на одном полном снимке.

## Проверенные поверхности fidelity

### Типографика и копирайт

- Сохранены Cormorant и Montserrat, фирменные веса и тёплая палитра темы.
- Hero теперь повторяет референс: «Абсолютно натуральный», «ШОКОЛАД», точный продуктовый текст, «Выберите свой вкус», «Подарочные наборы», `ГИ 35 / вместо 70`, `4,9 / 1 200 отзывов`.
- Видимая шкала повторяет референс: 55 / 72 / 85 / 92 / 99; по умолчанию выделено 72%; заголовок состояния — «Классический 72%».
- На 390 px глифы «ШОКОЛАД» не обрезаются; обе hero-кнопки полностью видны без прокрутки.

### Композиция и ритм

- Desktop hero, benefit strip и начало каталога совпадают с референсом по последовательности и общей высоте.
- Каталог сохраняет требуемые ТЗ четыре реальные позиции: поэтому карточки уже трёх референсных placeholder-карточек, но поля, вертикальный ритм и положение CTA соответствуют композиции.
- Selector повторяет двухколоночный ритм: шкала слева, крупный круг и продуктовая карточка справа. На mobile шкала превращается в горизонтальную ленту, selected 72% полностью виден.
- Убран пустой 700px mobile/tablet canvas, оставшийся после удаления hero-изображения: hero теперь 540–580 px; на 390 и 768 px CTA, trust и следующий блок распределены без провала.
- Состав и promo-карточки сверены отдельными парами; их иерархия, две колонки и фоновые зоны соответствуют source.

### Цвета и изображения

- Использованы только существующие paper / ink / sand / peach / line токены темы; новых цветов, шрифтов и внешних изображений нет.
- Декоративное hero-изображение удалено по последнему прямому замечанию пользователя.
- Каталог и selector используют реальные WooCommerce images. Фото selector отличается от пустого placeholder-круга референса намеренно: документ требует фото выбранного товара.
- Иконки header — существующие ассеты; account, cart и burger читаемы на светлой подложке.

### Коммерческие данные и состояния

- Видимые референсные проценты — presentation layer; цены, изображения, наличие, карточки и URL остаются серверными данными `WC_Product`.
- Переключение без reload обновляет фото, описание, цену и ссылку. Видимое 85% ведёт к реальной WooCommerce-группе 80%; URL фильтра остаётся `cacao_percentage=80`.
- Кнопка selector остаётся «Купить», а не «В корзину», потому что это прямое требование ТЗ.
- 4 товара, add-to-cart, счётчик корзины, keyboard navigation, reduced motion и безопасный неизвестный фильтр сохранены.

## История итераций текущего прохода

1. **P1 — hero содержал отсутствующий в референсе кусок шоколада и другой текст.** Удалён image-object, восстановлены точный текст, вторичная CTA и две trust-метрики. Post-fix: `pair-01-hero.png`.
2. **P1 — selector показывал 59 / 65 / 68 / 70 / 80 вместо 55 / 72 / 85 / 92 / 99.** Добавлена серверная presentation mapping поверх реальных WooCommerce-групп, default стал видимым 72%. Post-fix: `pair-03-cacao.png`.
3. **P2 — после удаления hero-image mobile/tablet сохраняли пустой холст высотой 620–700 px.** Высота ограничена 540–580 px, trust поднят, CTA остаются above the fold. Post-fix: `responsive-top-768x1024.png`, `responsive-top-390x844.png`.
4. **P2 — прежний QA был неполным: сравнивал только hero и picker.** Текущий проход содержит пять отдельных source/current pair images, включая каталог, состав и promo.
5. **P3 — desktop composition и promo были плотнее source.** Высота composition выровнена до 268–270 px, promo — до 331–332 px; `pair-04-composition.png` и `pair-05-promo.png` пересняты как element-to-element пары в масштабе 1:1.

## Допустимые различия, заданные ТЗ

- Header использует реальные пункты «Каталог / Рецепты / Маркетплейсы / Сотрудничество», account и cart; wishlist из референса удалён.
- Каталог показывает четыре реальные позиции, а не три placeholder-карточки.
- Selector показывает реальное фото товара, реальные описание и цену; reference-круг был placeholder.
- Нижние существующие блоки сайта после promo сохраняются без структурной переработки.

## Findings

Actionable P0/P1/P2 findings: none.

## Финальная верификация

- Core Web Vitals главной: desktop — LCP 1212 ms, CLS 0, INP 0 ms; mobile Fast 4G — LCP 2352 ms, CLS 0, INP 88 ms. Все принятые пороги пройдены.
- Пройдены visual layout, responsive header, homepage contract, tablet, responsive catalog, WooCommerce commerce flow и keyboard navigation проверки.
- PHP syntax check изменённых шаблонов, `git diff --check` и независимые design/code review пройдены; открытых P0/P1/P2/P3 замечаний нет.

## Final result

final result: passed
