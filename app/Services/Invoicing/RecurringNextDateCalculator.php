<?php

namespace App\Services\Invoicing;

use App\Enums\RecurringInterval;
use App\Models\BusinessRecurringProfile;
use Carbon\Carbon;

class RecurringNextDateCalculator
{
    public function initialNextDate(BusinessRecurringProfile $profile): string
    {
        return $profile->first_issue_date->toDateString();
    }

    public function advance(BusinessRecurringProfile $profile, ?Carbon $from = null): string
    {
        $from = $from ?? Carbon::parse($profile->next_issue_date);

        $next = match ($profile->recurrence_interval) {
            RecurringInterval::Monthly => $from->copy()->addMonth(),
            RecurringInterval::Yearly => $from->copy()->addYear(),
        };

        if ($profile->issue_last_day_of_month) {
            $next = $next->copy()->endOfMonth();
        }

        return $next->toDateString();
    }

    public function isDue(BusinessRecurringProfile $profile, ?Carbon $today = null): bool
    {
        if (! $profile->is_active) {
            return false;
        }

        $today = $today ?? Carbon::today();

        if ($profile->next_issue_date->gt($today)) {
            return false;
        }

        if (! $profile->repeat_indefinitely && $profile->ends_at && $profile->ends_at->lt($today)) {
            return false;
        }

        return true;
    }
}
