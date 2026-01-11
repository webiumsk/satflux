<?php

namespace App\Services\BtcPay\Exceptions;

use Exception;

class BtcPayException extends Exception
{
    protected $statusCode;

    public function __construct(string $message = '', int $statusCode = 0, Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

