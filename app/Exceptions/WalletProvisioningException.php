<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

class WalletProvisioningException extends ValidationException
{
    public bool $remoteRejected = false;
}
