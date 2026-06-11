<?php

namespace App\Enums;

enum CompanyWarehouseType: string
{
    case Own = 'own';
    case OwnedExternal = 'owned_external';
    case SupplierAvailability = 'supplier_availability';

    public function deductOnIssue(): bool
    {
        return match ($this) {
            self::Own, self::OwnedExternal => true,
            self::SupplierAvailability => false,
        };
    }

    public function labelKey(): string
    {
        return match ($this) {
            self::Own => 'invoicing.warehouse_type_own',
            self::OwnedExternal => 'invoicing.warehouse_type_owned_external',
            self::SupplierAvailability => 'invoicing.warehouse_type_supplier_availability',
        };
    }
}
