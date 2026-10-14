<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Încălcarea unei reguli de business (stand ocupat, recompensă deja ridicată…).
 * Mesajul este deja tradus și sigur de afișat utilizatorului.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public string $reason = 'rule')
    {
        parent::__construct($message);
    }
}
