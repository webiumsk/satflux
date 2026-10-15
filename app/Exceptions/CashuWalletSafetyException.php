<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CashuWalletSafetyException extends ValidationException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        $validator = Validator::make([], []);
        $validator->errors()->add('cashu', $message);
        parent::__construct($validator);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'errors' => $this->errors(),
        ], 422);
    }
}
