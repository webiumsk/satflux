<?php

namespace App\Http\Requests\Admin;

use App\Support\PlatformSettingsSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Flat JSON keys contain dots (e.g. guest.purge_enabled). Laravel's validator
     * treats dots as nesting, so we validate manually in validatedSettings().
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedSettings(): array
    {
        $fieldMap = PlatformSettingsSchema::fieldMap();
        $out = [];
        $errors = [];

        foreach ($this->all() as $key => $value) {
            if (! is_string($key) || ! isset($fieldMap[$key])) {
                continue;
            }

            $meta = $fieldMap[$key];
            $error = $this->validateField($key, $value, $meta);
            if ($error !== null) {
                $errors[$key][] = $error;

                continue;
            }

            if ($meta['secret']) {
                $secret = is_string($value) ? trim($value) : '';
                if ($secret !== '') {
                    $out[$key] = $secret;
                }
                continue;
            }

            if ($value === null || $value === '') {
                if (in_array($meta['type'], ['nullable_int', 'nullable_string', 'string', 'url', 'uuid'], true)) {
                    $out[$key] = $value;
                }
                continue;
            }

            if ($meta['type'] === 'csv_array' && is_string($value)) {
                $out[$key] = PlatformSettingsSchema::coerce($key, $value);

                continue;
            }

            $out[$key] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * @param  array{env: string|null, group: string, type: string, secret: bool}  $meta
     */
    private function validateField(string $key, mixed $value, array $meta): ?string
    {
        if ($meta['secret']) {
            if ($value === null || $value === '') {
                return null;
            }
            if (! is_string($value) || strlen($value) > 2048) {
                return 'Invalid secret value.';
            }

            return null;
        }

        $type = $meta['type'];

        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'bool' => is_bool($value) || in_array($value, [0, 1, '0', '1', true, false], true)
                ? null
                : 'Must be a boolean.',
            'int' => is_numeric($value) && (int) $value >= 0
                ? null
                : 'Must be a non-negative integer.',
            'nullable_int' => is_numeric($value) && (int) $value >= 1
                ? null
                : 'Must be a positive integer or empty.',
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL)
                ? null
                : 'Must be a valid URL.',
            'uuid' => is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)
                ? null
                : 'Must be a valid UUID.',
            default => is_string($value) && strlen($value) <= 2048
                ? null
                : 'Invalid value.',
        };
    }
}
