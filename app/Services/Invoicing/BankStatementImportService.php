<?php

namespace App\Services\Invoicing;

use App\Enums\BankImportSource;
use App\Enums\BankTransactionDirection;
use App\Enums\BankTransactionMatchStatus;
use App\Models\BankImportBatch;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\User;
use App\Services\Invoicing\BankImport\BankStatementParser;
use App\Services\Invoicing\BankImport\Camt053Parser;
use App\Services\Invoicing\BankImport\CsvBankParser;
use App\Support\Invoicing\BankTransactionDedupe;
use App\Support\Invoicing\ParsedBankTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BankStatementImportService
{
    /** @var list<BankStatementParser> */
    protected array $parsers;

    public function __construct(
        protected BusinessDocumentPaymentMatcher $matcher,
        ?array $parsers = null,
    ) {
        $this->parsers = $parsers ?? [
            new Camt053Parser,
            new CsvBankParser,
        ];
    }

    /**
     * @return array{batch: BankImportBatch, imported: int, skipped_duplicates: int, auto_matched: int}
     */
    public function importFile(Company $company, UploadedFile $file, User $user, ?string $format = null): array
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages(['file' => ['Could not read uploaded file.']]);
        }

        $parser = $this->resolveParser($file->getClientOriginalName(), $contents, $format);
        $rows = $parser->parse($contents);

        $source = $parser instanceof Camt053Parser
            ? BankImportSource::Camt053
            : BankImportSource::Csv;

        $disk = config('bank_import.storage_disk', 'local');
        $dir = trim(config('bank_import.storage_directory', 'bank-imports'), '/');
        $path = $file->storeAs(
            $dir.'/'.$company->id,
            now()->format('Ymd_His').'_'.$file->hashName(),
            $disk,
        );

        return $this->persistRows($company, $user, $rows, $source, $file->getClientOriginalName(), $path);
    }

    /**
     * @param  list<ParsedBankTransaction>  $rows
     * @return array{batch: BankImportBatch, imported: int, skipped_duplicates: int, auto_matched: int}
     */
    public function persistRows(
        Company $company,
        ?User $user,
        array $rows,
        BankImportSource $source,
        ?string $filename = null,
        ?string $storagePath = null,
    ): array {
        return DB::transaction(function () use ($company, $user, $rows, $source, $filename, $storagePath) {
            $batch = BankImportBatch::create([
                'company_id' => $company->id,
                'user_id' => $user?->id,
                'source' => $source,
                'filename' => $filename,
                'storage_path' => $storagePath,
                'row_count' => count($rows),
            ]);

            $imported = 0;
            $skipped = 0;
            $created = [];

            foreach ($rows as $parsed) {
                $row = $parsed->toRow();
                $row['company_id'] = $company->id;
                $row['bank_import_batch_id'] = $batch->id;
                $row['match_status'] = BankTransactionMatchStatus::Unmatched;
                $row['source'] = $source->value;
                $row['dedupe_hash'] = BankTransactionDedupe::hash($company, $row);

                if (BankTransaction::query()->where('company_id', $company->id)->where('dedupe_hash', $row['dedupe_hash'])->exists()) {
                    $skipped++;

                    continue;
                }

                $created[] = BankTransaction::create($row);
                $imported++;
            }

            $autoMatched = 0;
            if ($created !== []) {
                $result = $this->matcher->autoMatchBatch(collect($created), $user?->id);
                $autoMatched = $result['auto_matched'];
            }

            $batch->update([
                'imported_count' => $imported,
                'skipped_duplicates' => $skipped,
                'auto_matched_count' => $autoMatched,
            ]);

            return [
                'batch' => $batch->fresh(),
                'imported' => $imported,
                'skipped_duplicates' => $skipped,
                'auto_matched' => $autoMatched,
            ];
        });
    }

    protected function resolveParser(string $filename, string $contents, ?string $format): BankStatementParser
    {
        if ($format === 'camt053') {
            return new Camt053Parser;
        }
        if ($format === 'csv') {
            return new CsvBankParser;
        }

        foreach ($this->parsers as $parser) {
            if ($parser->supports($filename, $contents)) {
                return $parser;
            }
        }

        throw ValidationException::withMessages([
            'file' => ['Unsupported bank statement format. Upload CAMT.053 XML or CSV.'],
        ]);
    }

    public function purgeOldImportFiles(): int
    {
        $days = max(1, (int) config('bank_import.file_retention_days', 30));
        $cutoff = now()->subDays($days);
        $deleted = 0;
        $disk = Storage::disk(config('bank_import.storage_disk', 'local'));

        BankImportBatch::query()
            ->whereNotNull('storage_path')
            ->where('created_at', '<', $cutoff)
            ->chunkById(100, function ($batches) use ($disk, &$deleted) {
                foreach ($batches as $batch) {
                    if ($batch->storage_path && $disk->exists($batch->storage_path)) {
                        $disk->delete($batch->storage_path);
                        $deleted++;
                    }
                    $batch->update(['storage_path' => null]);
                }
            });

        return $deleted;
    }
}
