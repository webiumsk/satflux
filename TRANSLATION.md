# Translation Guide

This document explains how to add and maintain translations for the UZOL21 application.

## Overview

The application supports multiple languages:
- **Backend (Laravel)**: PHP translation files in `lang/` directory
- **Frontend (Vue.js)**: JSON translation files in `resources/js/locales/` directory

## Supported Languages

- **en** (English) - Default language
- **cz** (Čeština) - Czech
- **de** (Deutsch) - German
- **es** (Español) - Spanish
- **fr** (Français) - French
- **hu** (Magyar) - Hungarian
- **pl** (Polski) - Polish
- **sk** (Slovenčina) - Slovak

## Adding a New Language

### Backend Translations (Laravel)

1. Create a new directory in `lang/` with the language code (e.g., `lang/sk/`)

2. Copy the English translation files as a template:
   ```bash
   cp lang/en/auth.php lang/sk/auth.php
   cp lang/en/validation.php lang/sk/validation.php
   cp lang/en/messages.php lang/sk/messages.php
   ```

3. Translate all strings in the PHP files. The structure is:
   ```php
   <?php
   
   return [
       'key' => 'Translated text',
       'nested' => [
           'key' => 'Nested translated text',
       ],
   ];
   ```

4. Update `app/Http/Middleware/SetLocale.php` to include your language code in the `$supportedLocales` array if it's not already there.

5. Update `app/Http/Controllers/LocaleController.php` to add your language to the `SUPPORTED_LOCALES` array:
   ```php
   private const SUPPORTED_LOCALES = [
       'en' => 'English',
       'sk' => 'Slovenčina',
       // ... add your language here
   ];
   ```

### Frontend Translations (Vue.js)

1. Copy the English translation file:
   ```bash
   cp resources/js/locales/en.json resources/js/locales/sk.json
   ```

2. Translate all strings in the JSON file. Maintain the same structure:
   ```json
   {
     "common": {
       "loading": "Načítavam...",
       "cancel": "Zrušiť"
     },
     "auth": {
       "sign_in": "Prihlásiť sa"
     }
   }
   ```

3. The locale will be automatically loaded when a user selects it via the language switcher.

## Translation File Structure

### Backend (`lang/{locale}/`)

- **auth.php**: Authentication-related messages (login, registration, password reset)
- **validation.php**: Form validation error messages
- **messages.php**: General application messages (success, error, etc.)

### Frontend (`resources/js/locales/{locale}.json`)

Organized by feature areas:
- **common**: Common UI elements (buttons, labels, etc.)
- **auth**: Authentication pages
- **dashboard**: Dashboard page
- **stores**: Store management
- **account**: Account settings
- **header**: Header navigation
- **errors**: Error messages
- **validation**: Frontend validation messages

## Using Translations

### Backend (PHP)

Use the `__()` helper function:
```php
return response()->json([
    'message' => __('messages.login_successful'),
]);
```

With parameters:
```php
__('messages.logo_upload_failed', ['error' => $errorMessage])
```

### Frontend (Vue)

Use the `useI18n` composable:
```vue
<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
  <button>{{ t('auth.sign_in') }}</button>
</template>
```

Or use the `$t` function directly in templates:
```vue
<template>
  <button>{{ $t('auth.sign_in') }}</button>
</template>
```

With parameters:
```vue
{{ t('dashboard.welcome_back', { name: userName }) }}
```

## Best Practices

1. **Keep keys consistent**: Use the same key structure across all languages
2. **Use descriptive keys**: Keys should clearly indicate their purpose (e.g., `auth.sign_in` not `btn1`)
3. **Maintain context**: Group related translations together (auth, stores, etc.)
4. **Test thoroughly**: After adding translations, test the application in that language
5. **Keep English updated**: English (en) is the source of truth - update it first, then translate
6. **Handle pluralization**: Use Laravel's pluralization features when needed
7. **Escape HTML**: Be careful with HTML in translations - use proper escaping

## Checking Translation Completeness

To ensure all translations are complete:

1. Compare the structure of your translation file with `en.json` (frontend) or `en/*.php` (backend)
2. Use a JSON validator for frontend translations
3. Check for missing keys by comparing with the English version

## Language Switcher

The language switcher is available in the application header. Users can:
- Select their preferred language
- The preference is saved in the session (backend) and localStorage (frontend)
- The page reloads after language change to ensure all components use the new locale

## Contributing

When contributing translations:

1. Fork the repository
2. Create a new branch: `git checkout -b add-{language}-translations`
3. Add your translation files
4. Test the application in your language
5. Submit a pull request

## Questions?

If you have questions about translations, please:
- Check existing translation files for examples
- Review this documentation
- Open an issue on GitHub

Thank you for contributing to UZOL21!

