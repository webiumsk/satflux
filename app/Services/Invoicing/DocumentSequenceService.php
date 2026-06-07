<?php

namespace App\Services\Invoicing;

use App\Models\BusinessDocument;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\CompanyDocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentSequenceService
{
    public function __construct(
        protected DocumentNumberFormatter $formatter,
    ) {}

    public function nextNumber(Company $company, string $documentType): string
    {
        return DB::transaction(function () use ($company, $documentType) {
            $series = $this->resolveSeriesForIssue($company, $documentType);

            $series = CompanyDocumentSequence::query()
                ->where('id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->syncPeriod($series);
            $this->ensureCounterSynced($series);

            $series->last_number = (int) $series->last_number + 1;
            $series->save();

            return $this->formatter->format(
                $series->format,
                (int) $series->last_number,
            );
        });
    }

    public function previewNext(CompanyDocumentSequence $series, ?int $counterOverride = null): string
    {
        $counter = $counterOverride ?? ($this->effectiveLastNumber($series) + 1);

        return $this->formatter->format($series->format, $counter);
    }

    public function previewNextNumber(Company $company, string $documentType): string
    {
        $series = CompanyDocumentSequence::query()
            ->where('company_id', $company->id)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->first();

        if (! $series) {
            $this->seedDefaultsForCompany($company);

            $series = CompanyDocumentSequence::query()
                ->where('company_id', $company->id)
                ->where('document_type', $documentType)
                ->where('is_default', true)
                ->first();
        }

        if (! $series) {
            throw ValidationException::withMessages([
                'number_series' => ['No default number series found for this document type.'],
            ]);
        }

        $this->ensureCounterSynced($series);

        return $this->previewNext($series->fresh());
    }

    protected function ensureCounterSynced(CompanyDocumentSequence $series): void
    {
        $this->syncPeriod($series);

        $fromDocuments = $series->document_type === 'expense'
            ? $this->highestUsedExpenseCounter($series->company_id, $series->format)
            : $this->highestUsedCounter(
                $series->company_id,
                $series->document_type,
                $series->format,
            );

        if ($fromDocuments !== (int) $series->last_number) {
            $series->last_number = $fromDocuments;
            $series->save();
        }
    }

    /**
     * Recalculate default series counter after a document is deleted.
     */
    public function syncSeriesAfterDocumentChange(Company $company, string $documentType): void
    {
        $series = CompanyDocumentSequence::query()
            ->where('company_id', $company->id)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->first();

        if ($series) {
            $this->ensureCounterSynced($series);
        }
    }

    protected function highestUsedCounter(string $companyId, string $documentType, string $format): int
    {
        $digitLen = $this->counterDigitsInFormat($format);
        $max = 0;

        BusinessDocument::query()
            ->where('company_id', $companyId)
            ->where('type', $documentType)
            ->whereNotNull('number')
            ->pluck('number')
            ->each(function (string $number) use ($digitLen, &$max) {
                if (strlen($number) < $digitLen) {
                    return;
                }

                $suffix = substr($number, -$digitLen);
                if (ctype_digit($suffix)) {
                    $max = max($max, (int) $suffix);
                }
            });

        return $max;
    }

    protected function highestUsedExpenseCounter(string $companyId, string $format): int
    {
        $digitLen = $this->counterDigitsInFormat($format);
        $max = 0;

        BusinessExpense::query()
            ->where('company_id', $companyId)
            ->pluck('internal_number')
            ->each(function (string $number) use ($digitLen, &$max) {
                if (strlen($number) < $digitLen) {
                    return;
                }

                $suffix = substr($number, -$digitLen);
                if (ctype_digit($suffix)) {
                    $max = max($max, (int) $suffix);
                }
            });

        return $max;
    }

    protected function counterDigitsInFormat(string $format): int
    {
        $format = strtoupper(trim($format));

        if (preg_match('/C+$/', $format, $matches)) {
            return strlen($matches[0]);
        }

        return max(1, substr_count($format, 'C'));
    }

    protected function effectiveLastNumber(CompanyDocumentSequence $series): int
    {
        $periodKey = $this->currentPeriodKey($series->reset_period);

        if ($series->period_key !== $periodKey) {
            return 0;
        }

        return (int) $series->last_number;
    }

    public function currentPeriodKey(string $resetPeriod): string
    {
        return match ($resetPeriod) {
            'monthly' => now()->format('Y-m'),
            'never' => 'all',
            default => now()->format('Y'),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function defaultSeriesDefinitions(): array
    {
        return [
            ['document_type' => 'invoice', 'name' => 'Faktúra', 'format' => 'RRRRCCCC', 'is_default' => true],
            ['document_type' => 'credit_note', 'name' => 'Dobropis', 'format' => 'RRRRCCCC', 'is_default' => true],
            ['document_type' => 'proforma', 'name' => 'Zálohová faktúra', 'format' => 'ZALRRRRCCCC', 'is_default' => true],
            ['document_type' => 'delivery_note', 'name' => 'Dodací list', 'format' => 'DODRRCCC', 'is_default' => true],
            ['document_type' => 'quote', 'name' => 'Cenová ponuka', 'format' => 'PONRRRRCCC', 'is_default' => true],
            ['document_type' => 'order_received', 'name' => 'Prijatá objednávka', 'format' => 'OBJRRRRMMCCC', 'is_default' => true],
            ['document_type' => 'order_issued', 'name' => 'Vydaná objednávka', 'format' => 'VOBJRRRRCCC', 'is_default' => true],
            ['document_type' => 'expense', 'name' => 'Náklad', 'format' => 'NRRRRCCCC', 'is_default' => true],
        ];
    }

    public function seedDefaultsForCompany(Company $company): void
    {
        foreach ($this->defaultSeriesDefinitions() as $def) {
            $exists = CompanyDocumentSequence::query()
                ->where('company_id', $company->id)
                ->where('document_type', $def['document_type'])
                ->where('is_default', true)
                ->exists();

            if ($exists) {
                continue;
            }

            CompanyDocumentSequence::create([
                'company_id' => $company->id,
                'document_type' => $def['document_type'],
                'name' => $def['name'],
                'format' => $def['format'],
                'reset_period' => 'yearly',
                'is_default' => $def['is_default'],
                'period_key' => $this->currentPeriodKey('yearly'),
                'last_number' => 0,
            ]);
        }
    }

    protected function resolveSeriesForIssue(Company $company, string $documentType): CompanyDocumentSequence
    {
        $series = CompanyDocumentSequence::query()
            ->where('company_id', $company->id)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->first();

        if ($series) {
            return $series;
        }

        $this->seedDefaultsForCompany($company);

        $series = CompanyDocumentSequence::query()
            ->where('company_id', $company->id)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->first();

        if (! $series) {
            throw ValidationException::withMessages([
                'number_series' => ['No default number series found for this document type.'],
            ]);
        }

        return $series;
    }

    protected function syncPeriod(CompanyDocumentSequence $series): void
    {
        $key = $this->currentPeriodKey($series->reset_period);
        if ($series->period_key !== $key) {
            $series->period_key = $key;
            $series->last_number = 0;
        }
    }
}
