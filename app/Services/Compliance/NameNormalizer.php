<?php

namespace App\Services\Compliance;

class NameNormalizer
{
    public function normalize(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value) !== '' ? trim($value) : null;
    }

    public function normalizeEmailLocalPart(string $email): ?string
    {
        $at = strpos($email, '@');

        if ($at === false) {
            return $this->normalize($email);
        }

        return $this->normalize(substr($email, 0, $at));
    }
}
