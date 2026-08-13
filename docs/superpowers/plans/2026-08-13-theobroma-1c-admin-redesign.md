# Theobroma 1C Admin Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Сделать страницу интеграции с 1С компактной, понятной и адаптивной, отдельно объяснив назначение Ozon.

**Architecture:** `SettingsPage` остаётся серверным WordPress renderer и регистрирует CSS только для собственного admin hook. Разметка делится на смысловые карточки, а отдельный stylesheet отвечает только за презентацию.

**Tech Stack:** PHP 8.1, WordPress Admin API, WooCommerce, CSS.

## Global Constraints

- Не менять протокол CommerceML и формат `theobroma_1c_settings`.
- Не добавлять внешние frontend-зависимости.
- Сохранять nonce, capability checks и экранирование WordPress.
- Ozon не должен описываться как синхронизация заказов или API-интеграция.

---

### Task 1: Проверяемая структура страницы

**Files:**
- Modify: `wp-content/plugins/theobroma-1c/src/Admin/SettingsPage.php`
- Create: `wp-content/plugins/theobroma-1c/tests/SettingsPageMarkupTest.php`

- [ ] Добавить тест, который требует semantic class names, пояснение Ozon и пустое состояние журнала.
- [ ] Запустить тест и получить FAIL на старой разметке.
- [ ] Разделить render на hero, connection, diagnostics, history и Ozon card; сохранить существующие forms/nonces.
- [ ] Запустить plugin tests и получить PASS.

### Task 2: Изолированные admin-стили

**Files:**
- Create: `wp-content/plugins/theobroma-1c/assets/admin.css`
- Modify: `wp-content/plugins/theobroma-1c/src/Admin/SettingsPage.php`
- Modify: `wp-content/plugins/theobroma-1c/tests/SettingsPageMarkupTest.php`

- [ ] Потребовать тестом регистрацию `admin_enqueue_scripts` и scoped stylesheet.
- [ ] Подключить CSS только для `woocommerce_page_theobroma-1c`.
- [ ] Реализовать max-width, cards, status badges, responsive grid, focus-visible и mobile layout.
- [ ] Выполнить PHP lint, plugin tests и `git diff --check`.

### Task 3: Публикация

**Files:**
- Verify all changed files.

- [ ] Выполнить полный свежий набор проверок.
- [ ] Сверить ветку с актуальным `origin/main`.
- [ ] Commit и fast-forward push `HEAD:main`.
