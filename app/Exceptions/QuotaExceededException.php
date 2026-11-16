<?php

namespace App\Exceptions;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(public readonly string $limit, string $message)
    {
        parent::__construct($message);
    }
}
