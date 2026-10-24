<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class LightningAddressCapabilityException extends RuntimeException
{
    public function __construct(private array $decision)
    {
        parent::__construct(match ($decision['outcome']) {
            'unsupported' => 'This Lightning address does not support direct payment verification. Review the available alternatives.',
            'unavailable' => 'The Lightning address could not be checked. Retry before connecting the wallet.',
            default => 'The Lightning address did not return valid payment verification. The wallet was not changed.',
        });
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            ...$this->decision,
            'message' => $this->getMessage(),
            'errors' => ['secret' => [$this->getMessage()]],
        ], 422);
    }
}
