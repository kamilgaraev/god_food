# Theobroma 1C Safe Directions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Реализовать безопасный импорт цен, остатков и статусов поверх существующего экспорта заказов.

**Architecture:** Pure XML parsers produce immutable update DTOs. WordPress adapters resolve existing WooCommerce entities and apply only explicitly enabled fields through CRUD APIs. The HTTP endpoint implements authenticated CommerceML file/import modes over isolated bounded temporary storage.

**Tech Stack:** PHP 8.1, WordPress, WooCommerce CRUD/HPOS, CommerceML, XMLReader/SimpleXML.

## Global Constraints

- Входящий импорт выключен по умолчанию.
- Никакого создания или удаления сущностей.
- XML максимум 10 МБ, без DTD/ENTITY и внешней сети.
- Сопоставление только по стабильным идентификаторам, никогда по названию.

---

### Task 1: Настройки направлений

**Files:** Modify `src/Settings/Settings.php`, `src/Admin/SettingsPage.php`, `src/Admin/Diagnostics.php`; test `tests/ExchangeOptionsTest.php`.

- [ ] Добавить failing tests безопасных defaults и нормализации.
- [ ] Реализовать `ExchangeOptions` и сохранение четырёх переключателей.
- [ ] Вывести направления и предупреждения в admin UI.
- [ ] Запустить tests и PHP lint.

### Task 2: Pure CommerceML parsers

**Files:** Create `src/Import/*`; tests `tests/CatalogImportParserTest.php`, `tests/OrderStatusImportParserTest.php`.

- [ ] Добавить fixtures/tests для цены, суммы складов, статуса и запрета DTD.
- [ ] Реализовать DTO и bounded XML parsers.
- [ ] Проверить, что данные каталога/заказа вне allowlist игнорируются.

### Task 3: WooCommerce safe appliers

**Files:** Create `src/Import/ProductMatcher.php`, `CatalogUpdateService.php`, `OrderStatusUpdateService.php`; tests `tests/ProductMatcherTest.php`.

- [ ] Тестами зафиксировать unique/unknown/ambiguous matching без названий.
- [ ] Реализовать обновление только существующих CRUD objects и разрешённых полей.
- [ ] Добавить безопасные агрегированные результаты.

### Task 4: Входящий CommerceML endpoint

**Files:** Create `src/Http/ExchangeFileStore.php`, `IncomingExchangeService.php`; modify `WordPressExchangeEndpoint.php`; tests `tests/IncomingExchangeServiceTest.php`.

- [ ] Тестами зафиксировать disabled/file/import/error paths.
- [ ] Реализовать safe filename, chunk append, size limit, parsing/apply/delete.
- [ ] Подключить `catalog` и входящий `sale`, сохранив текущий экспорт.
- [ ] Журналировать только технические счётчики.

### Task 5: Проверка и публикация

- [ ] Выполнить plugin suite, commerce suite, install/config tests, PHP lint и diff check.
- [ ] Сверить с `origin/main`, commit и fast-forward push `HEAD:main`.
