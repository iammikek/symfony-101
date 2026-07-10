<?php

namespace App\Exception;

class CategoryNotFoundException extends \RuntimeException
{
    public function __construct(public readonly int $categoryId)
    {
        parent::__construct(sprintf('Category %d not found', $categoryId));
    }
}
