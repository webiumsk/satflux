# Translation guide (satflux.io)

How translations are organized and how to add or update locales.

## Overview

- **Backend (Laravel):** PHP files under `lang/{locale}/`
- **Frontend (Vue):** JSON files under `resources/js/locales/`

## Supported locales (current)

The product UI is wired for:

| Code | Language | Frontend JSON | Backend `lang/` |
| ---- | -------- | ------------- | --------------- |
| `en` | English  | `en.json`     | `lang/en/`      |
| `sk` | Slovak   | `sk.json`     | `lang/sk/`      |
| `es` | Spanish  | `es.json`     | `lang/es/`      |

Allowed locale codes are defined in:

- `app/Http/Middleware/SetLocale.php` - `$supportedLocales`
- `app/Http/Controllers/LocaleController.php` - `SUPPORTED_LOCALES`

## Adding a new locale

### Backend

1. Create `lang/{code}/` and copy from `lang/en/` (e.g. `auth.php`, `validation.php`, `messages.php`).
2. Add the code to `SetLocale.php` and `LocaleController::SUPPORTED_LOCALES`.

### Frontend

1. Copy `resources/js/locales/en.json` to `resources/js/locales/{code}.json`.
2. Translate values; keep the same key structure as `en.json`.

## Using translations

**PHP:** `__('messages.key')` with optional parameters.

**Vue:** `useI18n()` → `t('section.key')` or `$t(...)` in templates.

## Conventions

- Treat **English** as the source of truth; update `en` / `en.json` first, then mirror keys in other locales.
- Use stable, descriptive keys (e.g. `auth.sign_in`, not `btn1`).
- After changes, smoke-test the locale switcher and a few main flows in the new language.

## Contributing translations

Fork the repo, add or update locale files on a branch, and open a pull request. Thank you for helping make satflux.io accessible in more languages.
