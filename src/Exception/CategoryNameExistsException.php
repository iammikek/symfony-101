<?php

namespace App\Exception;

class CategoryNameExistsException extends \RuntimeException
{
    public function __construct(public readonly string $name)
    {
        parent::__construct(sprintf("Category name '%s' already exists", $name));
    }
}
