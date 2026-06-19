<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\PlatformSettingsSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class PlatformSettingsRepository
{
    private const CACHE_KEY = 'platform_settings:all';

    private const CACHE_TTL_SECONDS = 60;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $stored = PlatformSetting::query()->pluck('value', 'key')->all();
            $resolved = [];

            foreach (PlatformSettingsSchema::fieldMap() as $key => $meta) {
                if (! array_key_exists($key, $stored)) {
                    continue;
                }

                $resolved[$key] = $this->decodeStoredValue($key, $stored[$key]);
            }

            return $resolved;
        });
    }

    public function applyToConfig(): void
    {
        foreach ($this->all() as $key => $value) {
            Config::set($key, $value);
        }
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *     groups: list<string>,
     *     fields: list<array{key: string, group: string, type: string, secret: bool}>,
     *     values: array<string, mixed>
     * }
     */
    public function adminPayload(): array
    {
        $stored = $this->all();
        $values = [];

        foreach (PlatformSettingsSchema::fields() as $field) {
            $key = $field['key'];
            $secret = (bool) ($field['secret'] ?? false);
            $effective = array_key_exists($key, $stored)
                ? $stored[$key]
                : PlatformSettingsSchema::configDefault($key);

            if ($secret) {
                $isSet = is_string($effective) && trim($effective) !== '';
                $values[PlatformSettingsSchema::setFlagKey($key)] = $isSet;
                continue;
            }

            if ($field['type'] === 'csv_array' && is_array($effective)) {
                $values[$key] = implode(',', $effective);
                continue;
            }

            $values[$key] = $effective;
        }

        $fields = array_map(static function (array $field): array {
            return [
                'key' => $field['key'],
                'group' => $field['group'],
                'type' => $field['type'],
                'secret' => (bool) ($field['secret'] ?? false),
            ];
        }, PlatformSettingsSchema::fields());

        return [
            'groups' => PlatformSettingsSchema::groups(),
            'fields' => $fields,
            'values' => $values,
        ];
    }

    /**
     * @param  array<string, mixed>  $incoming  Flat keys (config paths or *_set flags ignored)
     */
    public function updateMany(array $incoming, ?User $user = null): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $fieldMap = PlatformSettingsSchema::fieldMap();

        foreach ($incoming as $key => $raw) {
            if (! isset($fieldMap[$key])) {
                continue;
            }

            $meta = $fieldMap[$key];

            if ($meta['secret']) {
                $secret = is_string($raw) ? trim($raw) : '';
                if ($secret === '') {
                    continue;
                }
                $encoded = Crypt::encryptString($secret);
            } else {
                if ($raw === null || $raw === '') {
                    if (in_array($meta['type'], ['nullable_int', 'nullable_string', 'string', 'url', 'uuid'], true)) {
                        PlatformSetting::query()->where('key', $key)->delete();
                    }
                    continue;
                }
                $coerced = PlatformSettingsSchema::coerce($key, $raw);
                if ($coerced === null && in_array($meta['type'], ['nullable_int', 'nullable_string'], true)) {
                    PlatformSetting::query()->where('key', $key)->delete();
                    continue;
                }
                $encoded = json_encode($coerced, JSON_THROW_ON_ERROR);
            }

            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $encoded,
                    'updated_by' => $user?->id,
                ],
            );
        }

        $this->flushCache();
        $this->applyToConfig();
    }

    /**
     * @param  array<string, string>  $envPairs  KEY => value from .env file
     * @return array{imported: int, skipped: int}
     */
    public function importFromEnvPairs(array $envPairs): array
    {
        $imported = 0;
        $skipped = 0;
        $batch = [];

        foreach ($envPairs as $envKey => $rawValue) {
            $configKey = PlatformSettingsSchema::configKeyFromEnv($envKey);
            if ($configKey === null) {
                continue;
            }

            if ($rawValue === null || $rawValue === '') {
                $skipped++;

                continue;
            }

            $batch[$configKey] = PlatformSettingsSchema::coerce($configKey, $rawValue);
            $imported++;
        }

        if ($batch !== []) {
            $this->updateMany($this->secretsAsPlainForImport($batch));
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed>  $batch
     * @return array<string, mixed>
     */
    private function secretsAsPlainForImport(array $batch): array
    {
        foreach ($batch as $key => $value) {
            if (PlatformSettingsSchema::isSecret($key) && is_string($value)) {
                $batch[$key] = $value;
            }
        }

        return $batch;
    }

    private function decodeStoredValue(string $key, ?string $stored): mixed
    {
        if ($stored === null || $stored === '') {
            return PlatformSettingsSchema::configDefault($key);
        }

        if (PlatformSettingsSchema::isSecret($key)) {
            try {
                return Crypt::decryptString($stored);
            } catch (\Throwable) {
                return '';
            }
        }

        return json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
