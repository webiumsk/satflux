<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class UpdateComplianceGeoIp extends Command
{
    protected $signature = 'compliance:update-geoip';

    protected $description = 'Download MaxMind GeoLite2 Country database for compliance geo-blocking';

    public function handle(): int
    {
        $accountId = config('compliance.maxmind_account_id');
        $licenseKey = config('compliance.maxmind_license_key');
        $targetPath = config('compliance.maxmind_database_path');

        if (! is_string($accountId) || $accountId === '' || ! is_string($licenseKey) || $licenseKey === '') {
            $this->error('Set MAXMIND_ACCOUNT_ID and MAXMIND_LICENSE_KEY in .env (free MaxMind account).');

            return self::FAILURE;
        }

        if (! is_string($targetPath) || $targetPath === '') {
            $this->error('Invalid COMPLIANCE_MAXMIND_DATABASE_PATH.');

            return self::FAILURE;
        }

        $url = sprintf(
            'https://download.maxmind.com/geoip/databases/GeoLite2-Country/download?suffix=tar.gz&edition_id=GeoLite2-Country'
        );

        $this->info('Downloading GeoLite2-Country from MaxMind...');

        $response = Http::withBasicAuth($accountId, $licenseKey)
            ->timeout(120)
            ->get($url);

        if (! $response->successful()) {
            $this->error('Download failed: HTTP '.$response->status());

            return self::FAILURE;
        }

        $tmpDir = storage_path('app/compliance/tmp-geoip-'.uniqid());
        File::ensureDirectoryExists($tmpDir);

        $archivePath = $tmpDir.'/GeoLite2-Country.tar.gz';
        File::put($archivePath, $response->body());

        try {
            $phar = new \PharData($archivePath);
            $phar->decompress();

            $tarPath = str_replace('.gz', '', $archivePath);
            $tar = new \PharData($tarPath);
            $tar->extractTo($tmpDir, null, true);

            $mmdbPath = null;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.mmdb')) {
                    $mmdbPath = $file->getPathname();
                    break;
                }
            }

            if ($mmdbPath === null) {
                $this->error('Could not find .mmdb file in MaxMind archive.');

                return self::FAILURE;
            }

            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($mmdbPath, $targetPath);
        } finally {
            if (File::isDirectory($tmpDir)) {
                File::deleteDirectory($tmpDir);
            }
        }

        $this->info('GeoLite2 database saved to: '.$targetPath);

        return self::SUCCESS;
    }
}
