<?php

namespace App\Services\Invoicing;

use App\Enums\CompanyWarehouseType;
use App\Models\Company;
use App\Models\CompanyWarehouse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CompanyWarehouseService
{
    public function list(Company $company, bool $activeOnly = true): Collection
    {
        $query = $company->warehouses()->with('contact:id,name')->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function ensureDefaultForCompany(Company $company): CompanyWarehouse
    {
        $existing = $company->warehouses()->where('is_default', true)->first();
        if ($existing) {
            return $existing;
        }

        return $this->create($company, [
            'name' => 'Hlavný sklad',
            'type' => CompanyWarehouseType::Own->value,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function defaultWarehouse(Company $company): CompanyWarehouse
    {
        $warehouse = $company->warehouses()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if ($warehouse) {
            return $warehouse;
        }

        return $this->ensureDefaultForCompany($company);
    }

    public function create(Company $company, array $data): CompanyWarehouse
    {
        $type = CompanyWarehouseType::from($data['type'] ?? CompanyWarehouseType::Own->value);
        $data['type'] = $type->value;
        $data['deduct_on_issue'] = $data['deduct_on_issue'] ?? $type->deductOnIssue();

        if (! empty($data['is_default'])) {
            $this->clearDefaultFlag($company);
        }

        if (! $company->warehouses()->exists()) {
            $data['is_default'] = true;
        }

        $this->assertContactBelongsToCompany($company, $data['company_contact_id'] ?? null);

        return $company->warehouses()->create($data);
    }

    public function update(Company $company, CompanyWarehouse $warehouse, array $data): CompanyWarehouse
    {
        $this->assertBelongsToCompany($warehouse, $company);

        if (isset($data['type'])) {
            $type = CompanyWarehouseType::from($data['type']);
            $data['type'] = $type->value;
            if (! array_key_exists('deduct_on_issue', $data)) {
                $data['deduct_on_issue'] = $type->deductOnIssue();
            }
        }

        if (! empty($data['is_default'])) {
            $this->clearDefaultFlag($company, $warehouse->id);
        }

        if (array_key_exists('company_contact_id', $data)) {
            $this->assertContactBelongsToCompany($company, $data['company_contact_id']);
        }

        $warehouse->fill($data);
        $warehouse->save();

        return $warehouse->fresh(['contact:id,name']);
    }

    public function delete(Company $company, CompanyWarehouse $warehouse): void
    {
        $this->assertBelongsToCompany($warehouse, $company);

        $hasBalances = $warehouse->balances()
            ->where('quantity_on_hand', '!=', 0)
            ->exists();

        if ($hasBalances) {
            throw ValidationException::withMessages([
                'warehouse' => ['Warehouse has non-zero stock and cannot be deleted. Transfer stock first.'],
            ]);
        }

        if ($warehouse->movements()->exists()) {
            throw ValidationException::withMessages([
                'warehouse' => ['Warehouse has stock movement history and cannot be deleted. Deactivate it instead.'],
            ]);
        }

        $wasDefault = $warehouse->is_default;
        $warehouse->delete();

        if ($wasDefault) {
            $next = $company->warehouses()->where('is_active', true)->orderBy('name')->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(CompanyWarehouse $warehouse): array
    {
        $warehouse->loadMissing('contact:id,name');

        return [
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'type' => $warehouse->type->value,
            'deduct_on_issue' => $warehouse->deduct_on_issue,
            'is_default' => $warehouse->is_default,
            'is_active' => $warehouse->is_active,
            'company_contact_id' => $warehouse->company_contact_id,
            'contact_name' => $warehouse->contact?->name,
            'street' => $warehouse->street,
            'city' => $warehouse->city,
            'postal_code' => $warehouse->postal_code,
            'country' => $warehouse->country,
            'notes' => $warehouse->notes,
        ];
    }

    protected function clearDefaultFlag(Company $company, ?string $exceptId = null): void
    {
        $query = $company->warehouses()->where('is_default', true);
        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }
        $query->update(['is_default' => false]);
    }

    protected function assertBelongsToCompany(CompanyWarehouse $warehouse, Company $company): void
    {
        if ($warehouse->company_id !== $company->id) {
            abort(404);
        }
    }

    protected function assertContactBelongsToCompany(Company $company, ?string $contactId): void
    {
        if (! $contactId) {
            return;
        }

        if (! $company->contacts()->where('id', $contactId)->exists()) {
            throw ValidationException::withMessages([
                'company_contact_id' => ['Invalid contact for this company.'],
            ]);
        }
    }
}
