<?php

namespace App\Exceptions;

use RuntimeException;

class ProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable)
    {
        parent::__construct($message);
    }
}