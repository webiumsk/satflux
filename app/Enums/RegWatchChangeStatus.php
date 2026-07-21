<?php

namespace App\Enums;

/**
 * Review workflow of a detected legislative change (docs/LEGAL.md):
 * the cron inserts New rows; a human moves them to Reviewed and then
 * Applied (rule updated) or Dismissed (not relevant).
 */
enum RegWatchChangeStatus: string
{
    case New = 'new';
    case Reviewed = 'reviewed';
    case Applied = 'applied';
    case Dismissed = 'dismissed';
}
