<?php

namespace App\Services\BtcPay\Exceptions;

use Exception;

class BtcPayException extends Exception
{
    protected $statusCode;

    /** Greenfield error `code` (e.g. "apikey-not-found"), when the response carried one. */
    protected ?string $errorCode;

    public function __construct(string $message = '', int $statusCode = 0, ?Exception $previous = null, ?string $errorCode = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
