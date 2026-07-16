<?php

namespace App\Enums;

enum CompanyStockMovementSource: string
{
    case Manual = 'manual';
    case Import = 'import';
    case DocumentIssue = 'document_issue';
    case DocumentCancel = 'document_cancel';
    case DocumentAdjustment = 'document_adjustment';
    case Transfer = 'transfer';
    case PurchaseReceipt = 'purchase_receipt';
}
