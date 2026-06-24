<?php

namespace App\Services\Integrations;

use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class IntegrationInboxDeepLinkService
{
    /**
     * Resolve a WooCommerce integration inbox deep link for the SPA.
     *
     * Accepts a Satflux store UUID, BTCPay store id, or server company UUID.
     *
     * @return array{
     *     store_id: string,
     *     btcpay_store_id: string,
     *     company_id: string|null,
     *     invoices_path: string,
     *     integration_inbox_path: string,
     *     resolved_via: string
     * }
     */
    public function resolve(User $user, ?string $storeRef, ?string $companyRef): array
    {
        $storeRef = $storeRef !== null ? trim($storeRef) : '';
        $companyRef = $companyRef !== null ? trim($companyRef) : '';

        if ($storeRef === '' && $companyRef === '') {
            throw ValidationException::withMessages([
                'store' => ['Provide store or company.'],
            ]);
        }

        $store = null;
        $resolvedVia = 'store';

        if ($storeRef !== '') {
            $store = $this->findStoreForUser($user, $storeRef);
            if (! $store) {
                throw ValidationException::withMessages([
                    'store' => ['Store not found for this account.'],
                ]);
            }
        } else {
            $company = Company::query()
                ->where('user_id', $user->id)
                ->where('id', $companyRef)
                ->first();
            if (! $company) {
                throw ValidationException::withMessages([
                    'company' => ['Company not found for this account.'],
                ]);
            }
            $store = $company->stores()->orderBy('name')->first();
            $resolvedVia = 'company';
            if (! $store) {
                throw ValidationException::withMessages([
                    'company' => ['No BTCPay store is linked to this company.'],
                ]);
            }
        }

        $company = $store->company;

        $invoicesPath = $company
            ? '/invoicing/companies/'.$company->id.'/invoices'
            : '/invoicing';

        return [
            'store_id' => $store->id,
            'btcpay_store_id' => $store->btcpay_store_id,
            'company_id' => $company?->id,
            'invoices_path' => $invoicesPath,
            'integration_inbox_path' => '/invoicing/stores/'.$store->id.'/integration-inbox',
            'resolved_via' => $resolvedVia,
        ];
    }

    protected function findStoreForUser(User $user, string $storeRef): ?Store
    {
        return Store::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($storeRef) {
                $query->where('id', $storeRef)
                    ->orWhere('btcpay_store_id', $storeRef);
            })
            ->first();
    }
}
