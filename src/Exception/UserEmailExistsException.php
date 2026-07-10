<?php

namespace App\Exception;

class UserEmailExistsException extends \RuntimeException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct(sprintf("User email '%s' already exists", $email));
    }
}
