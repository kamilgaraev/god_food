# Configurable Contact Forms Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Добавить WordPress-плагин для независимой настройки полей и получателей форм главной страницы и «Сотрудничества».

**Architecture:** Плагин предоставляет чистые классы настроек, рендеринга и обработки отправки, а bootstrap связывает их с Settings API и глобальным API интеграции. Тема вызывает API плагина с fallback и сохраняет общий HTTP/антиспам workflow.

**Tech Stack:** PHP 8, WordPress Settings API, существующая тема Theobroma, standalone PHP tests.

**Spec:** `docs/superpowers/specs/2026-08-25-configurable-contact-forms-design.md`

## Global Constraints

- Плагин управляет только формами `home` и `cooperation`.
- Корпоративная форма и её письмо не меняются.
- По умолчанию включены имя, обязательный телефон и комментарий; e-mail выключен.
- При отключённом плагине формы и обработчик остаются работоспособными.
- Все production-изменения выполняются после падающего теста.

---

### Task 1: Модель настроек и отправки

**Files:**
- Create: `wp-content/plugins/theobroma-contact-forms/src/Settings.php`
- Create: `wp-content/plugins/theobroma-contact-forms/src/Submission.php`
- Create: `wp-content/plugins/theobroma-contact-forms/tests/run.php`

**Interfaces:**
- Produces: `Settings::defaults(string $fallbackEmail): array`, `Settings::sanitize(array $input, string $fallbackEmail): array`, `Submission::isValid(array $values, array $definition): bool`, `Submission::lines(array $values, array $definition): array`.

- [ ] **Step 1: Write failing tests** for defaults, invalid recipient fallback, required-implies-enabled, required field validation and omission of disabled fields from notification lines.
- [ ] **Step 2: Run `php wp-content/plugins/theobroma-contact-forms/tests/run.php`** and confirm failure because classes do not exist.
- [ ] **Step 3: Implement Settings and Submission** with allowlists `home|cooperation` and `name|phone|email|message`.
- [ ] **Step 4: Run the test runner** and confirm all assertions pass.
- [ ] **Step 5: Commit** model and tests with message `Add configurable contact form model`.

### Task 2: Рендеринг и административная страница

**Files:**
- Create: `wp-content/plugins/theobroma-contact-forms/src/FieldRenderer.php`
- Create: `wp-content/plugins/theobroma-contact-forms/src/SettingsPage.php`
- Create: `wp-content/plugins/theobroma-contact-forms/src/Plugin.php`
- Create: `wp-content/plugins/theobroma-contact-forms/theobroma-contact-forms.php`
- Modify: `wp-content/plugins/theobroma-contact-forms/tests/run.php`

**Interfaces:**
- Consumes: sanitized settings from Task 1.
- Produces: `FieldRenderer::render(array $definition): string`, plugin registration, and global functions `theobroma_contact_forms_definition`, `theobroma_contact_forms_render_fields`, `theobroma_contact_forms_validate`, `theobroma_contact_forms_recipient`, `theobroma_contact_forms_notification_lines`.

- [ ] **Step 1: Add failing tests** asserting correct input types, required attributes, configured placeholders, settings sanitization callback registration and admin page markers.
- [ ] **Step 2: Run the plugin test runner** and confirm the renderer/bootstrap assertions fail.
- [ ] **Step 3: Implement renderer, Settings API page and bootstrap** using `register_setting`, `add_options_page`, escaped output and capability `manage_options`.
- [ ] **Step 4: Run the plugin test runner** and confirm all assertions pass.
- [ ] **Step 5: Commit** with message `Add contact form settings plugin`.

### Task 3: Интеграция темы и доставка писем

**Files:**
- Modify: `wp-content/themes/theobroma/template-parts/contact-section.php`
- Modify: `wp-content/themes/theobroma/template-parts/pages/cooperation.php`
- Modify: `wp-content/themes/theobroma/functions.php`
- Modify: `scripts/verify-lead-form-simplification.spec.php`
- Modify: `scripts/verify-contact-request-validation.spec.php`

**Interfaces:**
- Consumes: global plugin API from Task 2.
- Produces: hidden `form_id`, configurable fields, form-aware validation, persistence metadata and `wp_mail()` delivery.

- [ ] **Step 1: Change contract tests first** to expect `form_id`, default message fields and form-aware validation/recipient behavior.
- [ ] **Step 2: Run `npm run test:lead-forms`** and confirm failure against hardcoded templates and handler.
- [ ] **Step 3: Replace hardcoded fields with plugin calls plus fallback**, then update handler to sanitize only supported fields, validate configured requirements, save `_theobroma_form_id`, and send standard-form notifications.
- [ ] **Step 4: Run `npm run test:lead-forms`, `npm run test:phone-input`, and `npm run test:consent-checkbox`** and confirm success.
- [ ] **Step 5: Commit** with message `Connect configurable contact forms to theme`.

### Task 4: Установка и выпуск

**Files:**
- Modify: `scripts/configure-wordpress.php`
- Modify: `scripts/verify-wordpress.php`
- Modify: `README.md`
- Modify: `package.json`

**Interfaces:**
- Consumes: plugin entry file from Task 2.
- Produces: automatic activation in reproducible setup and documented admin path.

- [ ] **Step 1: Add failing verification expectations** for the plugin in required and active plugin lists.
- [ ] **Step 2: Update configure/verify scripts, README and package script** `test:contact-forms-plugin`.
- [ ] **Step 3: Run plugin tests, lead-form tests, PHP lint and existing form UI tests**; `git diff --check` must pass.
- [ ] **Step 4: Commit** with message `Activate configurable contact forms plugin`.
- [ ] **Step 5: Push branch, create PR, merge into `main`, fetch `origin/main` and verify the merge commit contains the plugin entry file.**
