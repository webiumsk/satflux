<?php

namespace App\Enums;

enum IntegrationDocumentInboxStatus: string
{
    case Pending = 'pending';
    case Imported = 'imported';
    case Dismissed = 'dismissed';
}
