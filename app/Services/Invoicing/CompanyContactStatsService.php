<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyContact;
use Illuminate\Support\Collection;

class CompanyContactStatsService
{
    /**
     * @return array<string, array{
     *   invoiced_total: float,
     *   invoiced_count: int,
     *   overdue_total: float,
     *   overdue_count: int,
     *   avg_payment_days: int|null
     * }>
     */
    public function statsForCompany(Company $company): array
    {
        $documents = BusinessDocument::query()
            ->where('company_id', $company->id)
            ->where('type', BusinessDocumentType::Invoice)
            ->whereNotNull('company_contact_id')
            ->whereIn('status', [
                BusinessDocumentStatus::Issued,
                BusinessDocumentStatus::Paid,
            ])
            ->get(['company_contact_id', 'status', 'total', 'due_date', 'issue_date', 'paid_at']);

        $stats = [];

        foreach ($documents as $doc) {
            $contactId = $doc->company_contact_id;
            if (! isset($stats[$contactId])) {
                $stats[$contactId] = $this->emptyStats();
            }

            $stats[$contactId]['invoiced_total'] += (float) $doc->total;
            $stats[$contactId]['invoiced_count']++;

            if ($this->isOverdue($doc)) {
                $stats[$contactId]['overdue_total'] += (float) $doc->total;
                $stats[$contactId]['overdue_count']++;
            }

            if ($doc->status === BusinessDocumentStatus::Paid && $doc->paid_at && $doc->issue_date) {
                $days = $doc->issue_date->diffInDays($doc->paid_at);
                $stats[$contactId]['payment_days_sum'] = ($stats[$contactId]['payment_days_sum'] ?? 0) + $days;
                $stats[$contactId]['payment_days_count'] = ($stats[$contactId]['payment_days_count'] ?? 0) + 1;
            }
        }

        foreach ($stats as $id => $row) {
            if (($row['payment_days_count'] ?? 0) > 0) {
                $stats[$id]['avg_payment_days'] = (int) round(
                    $row['payment_days_sum'] / $row['payment_days_count']
                );
            }
            unset($stats[$id]['payment_days_sum'], $stats[$id]['payment_days_count']);
        }

        return $stats;
    }

    public function statsForContact(Company $company, CompanyContact $contact): array
    {
        $all = $this->statsForCompany($company);

        return $all[$contact->id] ?? $this->emptyStats();
    }

    /**
     * @return array<int, string> Letters A-Z and '#' that have at least one contact
     */
    public function availableLetters(Company $company, Collection $contacts): array
    {
        $letters = [];
        foreach ($contacts as $contact) {
            $first = mb_strtoupper(mb_substr(trim($contact->name), 0, 1));
            if ($first === '' || ! preg_match('/^[A-ZÁÄČĎÉÍĹĽŇÓÔŘŠŤÚÝŽ]$/u', $first)) {
                $letters['#'] = '#';
            } else {
                $letters[$first] = $first;
            }
        }

        ksort($letters);

        return array_values($letters);
    }

    protected function isOverdue(BusinessDocument $doc): bool
    {
        if ($doc->status !== BusinessDocumentStatus::Issued || ! $doc->due_date) {
            return false;
        }

        return $doc->due_date->endOfDay()->isPast();
    }

    /**
     * @return array{
     *   invoiced_total: float,
     *   invoiced_count: int,
     *   overdue_total: float,
     *   overdue_count: int,
     *   avg_payment_days: int|null
     * }
     */
    protected function emptyStats(): array
    {
        return [
            'invoiced_total' => 0.0,
            'invoiced_count' => 0,
            'overdue_total' => 0.0,
            'overdue_count' => 0,
            'avg_payment_days' => null,
        ];
    }
}
