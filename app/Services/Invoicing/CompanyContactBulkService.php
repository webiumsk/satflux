<?php

namespace App\Services\Invoicing;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class CompanyContactBulkService
{
    public function __construct(
        protected CompanyContactAnonymizationService $anonymization,
    ) {}

    /**
     * @return Builder<CompanyContact>
     */
    public function filteredQuery(Company $company, Request $request): Builder
    {
        $query = CompanyContact::query()
            ->where('company_id', $company->id)
            ->orderBy('name');

        if ($search = trim((string) $request->input('q', ''))) {
            $pattern = '%'.$search.'%';
            $query->where(function ($q) use ($pattern) {
                foreach (['name', 'email', 'registration_number', 'tax_id'] as $column) {
                    $q->orWhere(function ($inner) use ($pattern, $column) {
                        $this->whereLikeInsensitive($inner, $column, $pattern);
                    });
                }
            });
        }

        $letter = $request->input('letter');
        if ($letter && $letter !== 'all') {
            if ($letter === '#') {
                $this->whereNameNotStartingWithLetter($query);
            } else {
                $letter = mb_strtoupper(mb_substr($letter, 0, 1));
                $query->where(function ($q) use ($letter) {
                    $this->whereLikeInsensitive($q, 'name', $letter.'%');
                    $q->orWhere(function ($inner) use ($letter) {
                        $this->whereLikeInsensitive($inner, 'name', mb_strtolower($letter).'%');
                    });
                });
            }
        }

        return $query;
    }

    /**
     * @return Collection<int, CompanyContact>
     */
    public function resolveContacts(Company $company, Request $request): Collection
    {
        $query = $this->filteredQuery($company, $request);

        if ($request->boolean('select_all')) {
            return $query->get();
        }

        $ids = $request->input('contact_ids', []);

        return $query->whereIn('id', $ids)->get();
    }

    /**
     * @param  Collection<int, CompanyContact>  $contacts
     */
    public function downloadXlsx(Company $company, Collection $contacts): Response
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([CompanyContactImportService::EXAMPLE_HEADERS], null, 'A1');

        $row = 2;
        foreach ($contacts as $contact) {
            $sheet->fromArray([$this->contactToRow($contact)], null, "A{$row}");
            $row++;
        }

        $path = Storage::disk('local')->path('temp/contacts-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        (new Xlsx($spreadsheet))->save($path);

        AuditLog::log('company_contact.bulk_xlsx', 'company', $company->id, [
            'count' => $contacts->count(),
        ]);

        return response()->download($path, 'contacts.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * @param  Collection<int, CompanyContact>  $contacts
     * @return array{processed: int, deleted: int, anonymized: int, skipped: int}
     */
    public function deleteContacts(Company $company, Collection $contacts): array
    {
        $deleted = 0;
        $anonymized = 0;
        $skipped = 0;

        foreach ($contacts as $contact) {
            if ($contact->company_id !== $company->id) {
                $skipped++;

                continue;
            }

            if ($this->anonymization->anonymize($company, $contact)) {
                $anonymized++;
            } else {
                $contact->delete();
                $deleted++;
            }
        }

        AuditLog::log('company_contact.bulk_delete', 'company', $company->id, [
            'deleted' => $deleted,
            'anonymized' => $anonymized,
            'skipped' => $skipped,
        ]);

        return [
            'processed' => $deleted + $anonymized,
            'deleted' => $deleted,
            'anonymized' => $anonymized,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return list<string|int|null>
     */
    protected function contactToRow(CompanyContact $contact): array
    {
        return [
            $contact->name,
            $contact->street,
            $contact->postal_code,
            $contact->city,
            $this->countryAbbrev($contact->country),
            $contact->registration_number,
            $contact->tax_id,
            $contact->vat_id,
            $contact->email,
            $contact->phone,
            $contact->fax,
            '',
            $contact->delivery_street,
            $contact->delivery_postal_code,
            $contact->delivery_city,
            $this->countryAbbrev($contact->delivery_country),
            '',
            $contact->notes,
            $contact->default_payment_terms_days,
            '',
            '',
            $contact->iban,
            $contact->swift,
        ];
    }

    protected function countryAbbrev(?string $country): string
    {
        if ($country === null || $country === '') {
            return '';
        }

        return match ($country) {
            'Slovensko' => 'SK',
            'Česko' => 'CZ',
            'Rakúsko' => 'AT',
            'Maďarsko' => 'HU',
            'Poľsko' => 'PL',
            'Nemecko' => 'DE',
            default => $country,
        };
    }

    protected function whereLikeInsensitive($query, string $column, string $pattern): void
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->where($column, 'ilike', $pattern);
        } else {
            $query->whereRaw('LOWER('.$column.') LIKE ?', [mb_strtolower($pattern)]);
        }
    }

    protected function whereNameNotStartingWithLetter($query): void
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->whereRaw("name !~* '^[a-záäčďéíĺľňóôŕšťúýž]'");

            return;
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZÁÄČĎÉÍĹĽŇÓÔŘŠŤÚÝŽ';
        foreach (mb_str_split($alphabet) as $char) {
            $query->where('name', 'not like', $char.'%');
            $query->where('name', 'not like', mb_strtolower($char).'%');
        }
    }
}
