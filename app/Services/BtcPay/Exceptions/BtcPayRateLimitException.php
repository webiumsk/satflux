<?php

namespace App\Services\BtcPay\Exceptions;

class BtcPayRateLimitException extends BtcPayException
{
    protected $retryAfter;

    public function __construct(string $message = 'Rate limit exceeded', int $retryAfter = 60, Exception $previous = null)
    {
        parent::__construct($message, 429, $previous);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}








