# Theobroma 1C Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Реализовать автономный WordPress-плагин `theobroma-1c`, безопасно выдающий 1С оплаченные и последующие изменённые заказы WooCommerce в CommerceML и управляющий внешними идентификаторами товаров.

**Architecture:** Плагин разделён на чистое доменное ядро (идентификаторы, DTO, XML, TSV) и WordPress-адаптеры (настройки, endpoint, lifecycle, admin). Endpoint реализует pull-протокол CommerceML; состояние ревизий хранится в метаданных заказа, а данные Ozon — в независимых метаполях товара.

**Tech Stack:** PHP 8.1, WordPress 6.8+, WooCommerce public CRUD/HPOS APIs, XMLWriter, собственный PHP test runner проекта.

## Global Constraints

- Не изменять существующий внутренний WooCommerce SKU `theobroma-*`.
- Не импортировать и не изменять каталог из 1С в первой версии.
- Экспортировать впервые только оплаченные заказы; отмены и возвраты ранее выгруженных заказов экспортировать новой ревизией.
- Не хранить открытый пароль и не писать секреты/персональные данные в журнал.
- Не изменять сторонний EDI; удалить его из обязательной установки после готовности собственного плагина.
- Все изменения выполнять TDD и проверять полным test runner плагина.

---

### Task 1: Каркас и идентификаторы товаров

**Files:**
- Create: `wp-content/plugins/theobroma-1c/theobroma-1c.php`
- Create: `wp-content/plugins/theobroma-1c/src/Plugin.php`
- Create: `wp-content/plugins/theobroma-1c/src/Products/ProductIdentifiers.php`
- Create: `wp-content/plugins/theobroma-1c/src/Products/ProductIdentifierResolver.php`
- Create: `wp-content/plugins/theobroma-1c/tests/ProductIdentifierResolverTest.php`
- Create: `wp-content/plugins/theobroma-1c/tests/run.php`

**Interfaces:**
- Produces: `ProductIdentifiers`, `ProductIdentifierResolver::resolve(ProductIdentifiers): ResolvedProductIdentifier`.

- [ ] Написать тесты приоритета GUID → 1C article → client article → Woo SKU и ошибки пустого набора.
- [ ] Запустить `php wp-content/plugins/theobroma-1c/tests/run.php`, получить FAIL из-за отсутствующих классов.
- [ ] Реализовать immutable value objects и resolver без WordPress-зависимостей.
- [ ] Повторить test runner и получить PASS.
- [ ] Закоммитить `feat: add 1C product identifier core`.

### Task 2: CommerceML заказа

**Files:**
- Create: `wp-content/plugins/theobroma-1c/src/Orders/OrderExportData.php`
- Create: `wp-content/plugins/theobroma-1c/src/Orders/OrderLineData.php`
- Create: `wp-content/plugins/theobroma-1c/src/Orders/RefundData.php`
- Create: `wp-content/plugins/theobroma-1c/src/CommerceMl/OrderWriter.php`
- Create: `wp-content/plugins/theobroma-1c/tests/OrderWriterTest.php`

**Interfaces:**
- Consumes: `ProductIdentifiers`, `ProductIdentifierResolver`.
- Produces: `OrderWriter::write(list<OrderExportData>): string`.

- [ ] Написать fixture-тест оплаченного заказа со скидкой, доставкой, бонусами, возвратом и XML-экранированием.
- [ ] Получить ожидаемый FAIL.
- [ ] Реализовать DTO в строковых денежных значениях и потоковую запись CommerceML 2.05 через XMLWriter.
- [ ] Проверить точный XML contract и PASS.
- [ ] Закоммитить `feat: build CommerceML order export`.

### Task 3: Ревизии и отбор заказов

**Files:**
- Create: `wp-content/plugins/theobroma-1c/src/Orders/ExportState.php`
- Create: `wp-content/plugins/theobroma-1c/src/Orders/ExportPolicy.php`
- Create: `wp-content/plugins/theobroma-1c/src/Orders/WooOrderMapper.php`
- Create: `wp-content/plugins/theobroma-1c/src/Orders/WooOrderRepository.php`
- Create: `wp-content/plugins/theobroma-1c/src/Orders/OrderLifecycle.php`
- Create: `wp-content/plugins/theobroma-1c/tests/ExportPolicyTest.php`

**Interfaces:**
- Produces: `ExportPolicy::shouldQueue(bool $paid, bool $exportedBefore): bool`; repository `pending(int): array`, `acknowledge(array): void`.

- [ ] Написать тесты первого неоплаченного/оплаченного заказа и повторной выгрузки ранее экспортированного заказа.
- [ ] Получить FAIL.
- [ ] Реализовать policy, мета-ревизии, Woo mapper и lifecycle hooks для оплаты, статуса, сохранения и возврата.
- [ ] Проверить unit tests и PHP lint.
- [ ] Закоммитить `feat: track 1C export revisions`.

### Task 4: Настройки, auth и CommerceML endpoint

**Files:**
- Create: `wp-content/plugins/theobroma-1c/src/Settings/Settings.php`
- Create: `wp-content/plugins/theobroma-1c/src/Http/BasicAuthenticator.php`
- Create: `wp-content/plugins/theobroma-1c/src/Http/ExchangeController.php`
- Create: `wp-content/plugins/theobroma-1c/src/Support/ExchangeLogger.php`
- Create: `wp-content/plugins/theobroma-1c/tests/BasicAuthenticatorTest.php`
- Create: `wp-content/plugins/theobroma-1c/tests/ExchangeControllerTest.php`

**Interfaces:**
- Produces: handlers `checkauth`, `init`, `query`, `success`; password hashing through injected callables in unit tests.

- [ ] Написать тесты 401, disabled 503, bad mode 400 и полного checkauth→init→query→success.
- [ ] Получить FAIL.
- [ ] Реализовать controller, session-bound batch token, safe logger и rewrite/query-var endpoint.
- [ ] Проверить тесты и отсутствие секретов в test log.
- [ ] Закоммитить `feat: expose secure CommerceML endpoint`.

### Task 5: Поля товаров и Ozon TSV/CSV

**Files:**
- Create: `wp-content/plugins/theobroma-1c/src/Products/ProductFields.php`
- Create: `wp-content/plugins/theobroma-1c/src/Ozon/OzonRow.php`
- Create: `wp-content/plugins/theobroma-1c/src/Ozon/OzonTableParser.php`
- Create: `wp-content/plugins/theobroma-1c/src/Ozon/OzonMatchService.php`
- Create: `wp-content/plugins/theobroma-1c/src/Admin/OzonImportPage.php`
- Create: `wp-content/plugins/theobroma-1c/tests/OzonTableParserTest.php`
- Create: `wp-content/plugins/theobroma-1c/tests/OzonMatchServiceTest.php`

**Interfaces:**
- Produces: parser `parse(string): list<OzonRow>`; match result categories `matched|ambiguous|missing|invalid`.

- [ ] Написать тесты русского TSV, CSV delimiter, BOM, неверных ID, дубликатов и запрета match по названию.
- [ ] Получить FAIL.
- [ ] Реализовать parser/matcher, поля товара, preview/apply с nonce, capability и пределами 5 МБ/5000 строк.
- [ ] Проверить тесты на предоставленном заголовке как fixture.
- [ ] Закоммитить `feat: manage external product identifiers`.

### Task 6: Admin, диагностика, установка и smoke

**Files:**
- Create: `wp-content/plugins/theobroma-1c/src/Admin/SettingsPage.php`
- Create: `wp-content/plugins/theobroma-1c/src/Admin/Diagnostics.php`
- Create: `wp-content/plugins/theobroma-1c/tests/wordpress-smoke.php`
- Modify: `compose.yaml`
- Modify: `scripts/configure-wordpress.php`
- Modify: `scripts/install-required-plugins.sh`
- Modify: `scripts/test-install-required-plugins.sh`
- Modify: `scripts/verify-wordpress.php`
- Modify: `README.md`

**Interfaces:**
- Consumes: все компоненты Tasks 1–5.
- Produces: активируемый bundled plugin и воспроизводимую установку без стороннего EDI.

- [ ] Написать/обновить smoke и install-script test так, чтобы они требовали `theobroma-1c` и запрещали обязательный EDI.
- [ ] Получить ожидаемый FAIL.
- [ ] Зарегистрировать admin UI, диагностику, mount/activation и убрать EDI из установщика.
- [ ] Запустить полный suite `php wp-content/plugins/theobroma-1c/tests/run.php`, commerce suite, shell behavior test, PHP lint всех новых файлов и `git diff --check`.
- [ ] Запустить доступный WordPress smoke без запуска нового dev server; если окружение не поднято, зафиксировать это отдельно, не скрывая ограничение.
- [ ] Закоммитить `feat: integrate Theobroma 1C plugin`.

### Task 7: Финальная проверка и публикация

**Files:**
- Verify all changed files.

- [ ] Сверить diff с каждым разделом спецификации и устранить пробелы.
- [ ] Повторить все релевантные тесты свежим запуском.
- [ ] Убедиться, что рабочее дерево чистое и ветка основана на актуальном `origin/main`.
- [ ] Push fast-forward `HEAD:main`.
- [ ] Отметить цель выполненной и сообщить итоговые команды пользователю.
