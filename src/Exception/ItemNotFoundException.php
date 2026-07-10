<?php

namespace App\Exception;

class ItemNotFoundException extends \RuntimeException
{
    public function __construct(public readonly int $itemId)
    {
        parent::__construct(sprintf('Item %d not found', $itemId));
    }
}
