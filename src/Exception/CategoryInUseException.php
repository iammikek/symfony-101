<?php

namespace App\Exception;

class CategoryInUseException extends \RuntimeException
{
    public function __construct(public readonly int $categoryId)
    {
        parent::__construct(sprintf('Category %d has items and cannot be deleted', $categoryId));
    }
}
