<?php

namespace App\Serializer;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\User;

final class ApiSerializer
{
    /** @return array<string, mixed> */
    public static function category(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
        ];
    }

    /** @return array<string, mixed> */
    public static function item(Item $item, bool $includeCategory = true): array
    {
        $data = [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'description' => $item->getDescription(),
            'price' => (float) $item->getPrice(),
            'category_id' => $item->getCategoryId(),
        ];

        if ($includeCategory && $item->getCategory() !== null) {
            $data['category'] = self::category($item->getCategory());
        } else {
            $data['category'] = null;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function user(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
        ];
    }
}
