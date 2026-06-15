<?php

namespace App\Services\Invoicing;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CompanyBrandingService
{
    public const DISK = 'local';

    public function storeLogo(Company $company, UploadedFile $file): Company
    {
        return $this->storeImage($company, $file, 'logo_path', 'logo');
    }

    public function storeSignatureStamp(Company $company, UploadedFile $file): Company
    {
        return $this->storeImage($company, $file, 'signature_stamp_path', 'signature_stamp');
    }

    public function deleteLogo(Company $company): Company
    {
        $this->deletePath($company->logo_path);
        $company->update(['logo_path' => null]);

        return $company->fresh();
    }

    public function deleteSignatureStamp(Company $company): Company
    {
        $this->deletePath($company->signature_stamp_path);
        $company->update(['signature_stamp_path' => null]);

        return $company->fresh();
    }

    /**
     * Data URI safe for Dompdf (PNG or JPEG only).
     */
    public function imageDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $binary = Storage::disk(self::DISK)->get($path);
        $mime = Storage::disk(self::DISK)->mimeType($path) ?: '';

        if ($this->isDompdfSafeMime($mime)) {
            return 'data:'.$mime.';base64,'.base64_encode($binary);
        }

        $png = $this->convertToPngBytes($binary);
        if ($png === null) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * Use an inline data URL from the client snapshot, or resolve a stored path.
     */
    public function resolveBrandingDataUri(?string $inlineDataUrl, ?string $storedPath): ?string
    {
        if ($inlineDataUrl && str_starts_with($inlineDataUrl, 'data:image/')) {
            return $inlineDataUrl;
        }

        return $this->imageDataUri($storedPath);
    }

    /**
     * @return array{logo_url: string|null, signature_stamp_url: string|null, has_logo: bool, has_signature_stamp: bool}
     */
    public function brandingMeta(Company $company): array
    {
        return [
            'has_logo' => (bool) $company->logo_path,
            'has_signature_stamp' => (bool) $company->signature_stamp_path,
            'logo_url' => $company->logo_path
                ? url("/api/invoicing/companies/{$company->id}/branding/logo")
                : null,
            'signature_stamp_url' => $company->signature_stamp_path
                ? url("/api/invoicing/companies/{$company->id}/branding/signature-stamp")
                : null,
        ];
    }

    protected function storeImage(Company $company, UploadedFile $file, string $column, string $basename): Company
    {
        $contents = file_get_contents($file->getRealPath() ?: '');
        if ($contents === false || $contents === '') {
            throw ValidationException::withMessages([
                'image' => ['Invalid image file.'],
            ]);
        }

        $png = $this->convertToPngBytes($contents);
        if ($png === null) {
            throw ValidationException::withMessages([
                'image' => ['Could not process image. Use PNG or JPEG, or ensure PHP GD supports WebP.'],
            ]);
        }

        $dir = 'companies/'.$company->id;
        $path = $dir.'/'.$basename.'.png';

        $this->deletePath($company->{$column});
        Storage::disk(self::DISK)->put($path, $png);
        $company->update([$column => $path]);

        return $company->fresh();
    }

    protected function deletePath(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    protected function isDompdfSafeMime(string $mime): bool
    {
        return in_array($mime, ['image/png', 'image/jpeg', 'image/jpg'], true);
    }

    /**
     * Convert arbitrary raster image bytes to PNG (Dompdf-compatible).
     */
    protected function convertToPngBytes(string $contents): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('PHP GD extension is required for company branding images.');
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return null;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagepng($image);
        imagedestroy($image);
        $png = ob_get_clean();

        return ($ok && $png !== false && $png !== '') ? $png : null;
    }
}
