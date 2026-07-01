<?php

namespace App\Services\Invoicing\Wise;

use App\Enums\BankImportSource;
use App\Models\Company;
use App\Models\User;
use App\Services\Invoicing\BankStatementImportService;
use App\Support\Invoicing\CompanyAppSettings;
use App\Support\Invoicing\CompanyWiseSettings;
use App\Support\Invoicing\ParsedBankTransaction;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;

class WiseBankSyncService
{
    public function __construct(
        protected WiseApiClient $client,
        protected WiseStatementMapper $mapper,
        protected BankStatementImportService $importService,
    ) {}

    /**
     * @return array{
     *     connected: bool,
     *     profile_id: ?int,
     *     balance_id: ?int,
     *     balance_currency: ?string,
     *     last_sync_at: ?string,
     *     api_available: bool,
     * }
     */
    public function status(Company $company): array
    {
        $settings = CompanyWiseSettings::fromCompany($company);

        return [
            'connected' => $settings->configured(),
            'profile_id' => $settings->profileId(),
            'balance_id' => $settings->balanceId(),
            'balance_currency' => $company->default_currency,
            'last_sync_at' => $settings->lastSyncAt(),
            'api_available' => $settings->configured(),
        ];
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array{settings: CompanyWiseSettings, profile_id: int, balance_id: int}
     */
    public function connect(Company $company, array $incoming): array
    {
        $token = trim((string) ($incoming['wise_api_token'] ?? ''));
        if ($token === '') {
            throw ValidationException::withMessages([
                'wise_api_token' => ['Wise API token is required.'],
            ]);
        }

        $profiles = $this->client->profiles($token);
        $profile = $this->resolveProfile($profiles, $incoming['wise_profile_id'] ?? null);
        if ($profile === null) {
            throw ValidationException::withMessages([
                'wise_api_token' => ['No Wise business profile found for this token.'],
            ]);
        }

        $profileId = (int) $profile['id'];
        $balances = $this->client->balances($token, $profileId);
        $balance = $this->resolveBalance($balances, $company->default_currency, $incoming['wise_balance_id'] ?? null);
        if ($balance === null) {
            throw ValidationException::withMessages([
                'wise_balance_id' => ['No Wise balance found for currency '.$company->default_currency.'.'],
            ]);
        }

        $balanceId = (int) $balance['id'];
        $current = CompanyAppSettings::from($company->app_settings)->toArray();
        $merged = CompanyWiseSettings::mergeIncoming($current, [
            'wise_api_token' => $token,
            'wise_profile_id' => $profileId,
            'wise_balance_id' => $balanceId,
        ]);
        $company->update(['app_settings' => $merged]);

        return [
            'settings' => CompanyWiseSettings::fromCompany($company->fresh()),
            'profile_id' => $profileId,
            'balance_id' => $balanceId,
        ];
    }

    /**
     * @return array{
     *     imported: int,
     *     skipped_duplicates: int,
     *     auto_matched: int,
     *     rows?: list<array<string, mixed>>,
     * }
     */
    public function sync(
        Company $company,
        ?User $user,
        ?string $from = null,
        ?string $to = null,
        bool $rowsOnly = false,
    ): array {
        $settings = CompanyWiseSettings::fromCompany($company);
        $token = $settings->apiToken();
        if ($token === null) {
            throw ValidationException::withMessages([
                'wise' => ['Wise is not connected for this company.'],
            ]);
        }

        $profileId = $settings->profileId();
        $balanceId = $settings->balanceId();
        if ($profileId === null || $balanceId === null) {
            $connected = $this->connect($company, ['wise_api_token' => $token]);
            $settings = $connected['settings'];
            $profileId = $connected['profile_id'];
            $balanceId = $connected['balance_id'];
            $token = $settings->apiToken();
        }

        $end = $to ? Carbon::parse($to)->endOfDay() : now();
        $start = $from
            ? Carbon::parse($from)->startOfDay()
            : now()->subDays((int) config('wise.default_sync_days', 30))->startOfDay();

        try {
            $statement = $this->client->statement(
                $token,
                $profileId,
                $balanceId,
                $start->toIso8601String(),
                $end->toIso8601String(),
            );
        } catch (RequestException $e) {
            $message = $e->response?->json('message') ?? $e->getMessage();
            throw ValidationException::withMessages([
                'wise' => ['Wise API error: '.$message],
            ]);
        }

        /** @var list<ParsedBankTransaction> $rows */
        $rows = $this->mapper->map($statement);

        if ($rowsOnly) {
            return [
                'imported' => count($rows),
                'skipped_duplicates' => 0,
                'auto_matched' => 0,
                'rows' => $this->mapper->serializeRows($rows),
            ];
        }

        $result = $this->importService->persistRows(
            $company,
            $user,
            $rows,
            BankImportSource::Wise,
            'wise-sync.json',
        );

        $current = CompanyAppSettings::from($company->app_settings)->toArray();
        $company->update([
            'app_settings' => CompanyWiseSettings::mergeIncoming($current, [
                'wise_last_sync_at' => now()->toIso8601String(),
            ]),
        ]);

        return [
            'imported' => $result['imported'],
            'skipped_duplicates' => $result['skipped_duplicates'],
            'auto_matched' => $result['auto_matched'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $profiles
     * @return array<string, mixed>|null
     */
    protected function resolveProfile(array $profiles, mixed $preferredId): ?array
    {
        if (is_numeric($preferredId)) {
            foreach ($profiles as $profile) {
                if ((int) ($profile['id'] ?? 0) === (int) $preferredId) {
                    return $profile;
                }
            }
        }

        foreach ($profiles as $profile) {
            if (strtoupper((string) ($profile['type'] ?? '')) === 'BUSINESS') {
                return $profile;
            }
        }

        return $profiles[0] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $balances
     * @return array<string, mixed>|null
     */
    protected function resolveBalance(array $balances, string $currency, mixed $preferredId): ?array
    {
        $currency = strtoupper($currency);

        if (is_numeric($preferredId)) {
            foreach ($balances as $balance) {
                if ((int) ($balance['id'] ?? 0) === (int) $preferredId) {
                    return $balance;
                }
            }
        }

        foreach ($balances as $balance) {
            $balanceCurrency = strtoupper((string) ($balance['currency'] ?? $balance['amount']['currency'] ?? ''));
            if ($balanceCurrency === $currency) {
                return $balance;
            }
        }

        return $balances[0] ?? null;
    }
}
