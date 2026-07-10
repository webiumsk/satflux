<?php

namespace App\Services;

/**
 * Detect wallet connection kind from pasted merchant input (single textarea UX).
 */
class WalletConnectionDetector
{
    public function __construct(
        protected WalletConnectionValidator $validator,
    ) {}

    /**
     * @return array{
     *     kind: string,
     *     connection_type: string|null,
     *     store_wallet_type: string|null,
     *     brand: string|null,
     *     normalized_secret: string|null,
     *     cashu_mint_url: string|null,
     *     cashu_lightning_address: string|null,
     *     confidence: string
     * }
     */
    public function detect(string $input): array
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return $this->unknown();
        }

        if ($this->looksLikeNwc($trimmed)) {
            if ($this->validator->isCashuWalletNwcUri($trimmed)) {
                return [
                    'kind' => 'cashu_wallet_nwc',
                    'connection_type' => null,
                    'store_wallet_type' => null,
                    'brand' => null,
                    'normalized_secret' => null,
                    'cashu_mint_url' => null,
                    'cashu_lightning_address' => $this->validator->extractNwcLud16($trimmed),
                    'confidence' => 'high',
                ];
            }

            return [
                'kind' => 'nwc',
                'connection_type' => 'nwc',
                'store_wallet_type' => 'nwc',
                'brand' => null,
                'normalized_secret' => $this->validator->normalizeNwcUri($trimmed),
                'cashu_mint_url' => null,
                'cashu_lightning_address' => null,
                'confidence' => 'high',
            ];
        }

        if ($this->looksLikeBlink($trimmed)) {
            return [
                'kind' => 'blink',
                'connection_type' => 'blink',
                'store_wallet_type' => 'blink',
                'brand' => null,
                'normalized_secret' => $trimmed,
                'cashu_mint_url' => null,
                'cashu_lightning_address' => null,
                'confidence' => 'high',
            ];
        }

        if ($this->looksLikeDescriptor($trimmed)) {
            $body = $this->validator->stripDescriptorChecksum($trimmed);

            return [
                'kind' => 'aqua_descriptor',
                'connection_type' => 'aqua_descriptor',
                'store_wallet_type' => 'aqua_boltz',
                'brand' => $this->detectDescriptorBrand($body),
                'normalized_secret' => $trimmed,
                'cashu_mint_url' => null,
                'cashu_lightning_address' => null,
                'confidence' => 'high',
            ];
        }

        $cashu = $this->detectCashuInput($trimmed);
        if ($cashu !== null) {
            return $cashu;
        }

        return $this->unknown();
    }

    protected function unknown(): array
    {
        return [
            'kind' => 'unknown',
            'connection_type' => null,
            'store_wallet_type' => null,
            'brand' => null,
            'normalized_secret' => null,
            'cashu_mint_url' => null,
            'cashu_lightning_address' => null,
            'confidence' => 'low',
        ];
    }

    protected function looksLikeNwc(string $value): bool
    {
        $lower = strtolower($value);

        return str_starts_with($lower, 'nostr+walletconnect:')
            || str_starts_with($lower, 'nostr+walletconnect://')
            || str_starts_with($lower, 'type=nwc;');
    }

    protected function looksLikeBlink(string $value): bool
    {
        if (str_contains(strtolower($value), 'type=blink;')) {
            return true;
        }

        $parsed = $this->validator->parseBlinkConnectionString($value);

        return empty($parsed['errors']) && ($parsed['type'] ?? null) === 'blink';
    }

    protected function looksLikeDescriptor(string $value): bool
    {
        return $this->validator->validateAquaDescriptor($value);
    }

    protected function detectDescriptorBrand(string $descriptorBody): ?string
    {
        if (preg_match('/,\s*elwpkh\s*\(/i', $descriptorBody)) {
            return 'bull';
        }
        if (preg_match('/,\s*elsh\s*\(\s*wpkh\s*\(/i', $descriptorBody)) {
            return 'aqua';
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function detectCashuInput(string $value): ?array
    {
        $lines = preg_split('/\R/', $value) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($line) => $line !== ''));

        if ($lines === []) {
            return null;
        }

        $mintUrl = null;
        $lightningAddress = null;

        foreach ($lines as $line) {
            if ($this->looksLikeMintUrl($line)) {
                $mintUrl = $line;

                continue;
            }
            if ($this->looksLikeLightningAddress($line)) {
                $lightningAddress = $line;
            }
        }

        if ($mintUrl === null && count($lines) === 1 && $this->looksLikeMintUrl($lines[0])) {
            $mintUrl = $lines[0];
        }

        if ($lightningAddress === null && count($lines) === 1 && $this->looksLikeLightningAddress($lines[0])) {
            $lightningAddress = $lines[0];
        }

        if ($mintUrl === null && $lightningAddress === null) {
            return null;
        }

        return [
            'kind' => 'cashu',
            'connection_type' => null,
            'store_wallet_type' => 'cashu',
            'brand' => null,
            'normalized_secret' => null,
            'cashu_mint_url' => $mintUrl,
            'cashu_lightning_address' => $lightningAddress,
            'confidence' => 'high',
        ];
    }

    protected function looksLikeMintUrl(string $value): bool
    {
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $lower = strtolower($value);

        return str_starts_with($lower, 'https://')
            && ! str_contains($lower, 'nostr+walletconnect')
            && ! str_contains($lower, 'type=blink');
    }

    protected function looksLikeLightningAddress(string $value): bool
    {
        return $this->validator->validateCashuLightningAddress($value);
    }
}
