<?php

namespace App\Service;

use App\Entity\Item;
use App\Exception\ItemNotFoundException;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class ItemService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ItemRepository $itemRepository,
        private readonly CategoryService $categoryService,
    ) {
    }

    /** @param array<string, mixed> $filters
     * @return array{0: list<Item>, 1: int}
     */
    public function listItems(int $skip, int $limit, array $filters = []): array
    {
        $qb = $this->itemRepository->createQueryBuilder('i')
            ->leftJoin('i.category', 'c')
            ->addSelect('c')
            ->orderBy('i.id', 'ASC');

        if (isset($filters['min_price'])) {
            $qb->andWhere('i.price >= :min_price')
                ->setParameter('min_price', (string) $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $qb->andWhere('i.price <= :max_price')
                ->setParameter('max_price', (string) $filters['max_price']);
        }

        if (isset($filters['category_id'])) {
            $qb->andWhere('i.category = :category_id')
                ->setParameter('category_id', (int) $filters['category_id']);
        }

        if (isset($filters['name_contains'])) {
            $qb->andWhere('LOWER(i.name) LIKE :name_contains')
                ->setParameter('name_contains', '%' . strtolower((string) $filters['name_contains']) . '%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(i.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $rows = $qb
            ->setFirstResult($skip)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [$rows, $total];
    }

    public function getById(int $itemId): Item
    {
        $item = $this->itemRepository->createQueryBuilder('i')
            ->leftJoin('i.category', 'c')
            ->addSelect('c')
            ->where('i.id = :id')
            ->setParameter('id', $itemId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($item === null) {
            throw new ItemNotFoundException($itemId);
        }

        return $item;
    }

    public function create(string $name, ?string $description, string $price, ?int $categoryId): Item
    {
        $category = null;
        if ($categoryId !== null) {
            $category = $this->categoryService->getById($categoryId);
        }

        $item = (new Item())
            ->setName($name)
            ->setDescription($description)
            ->setPrice($price)
            ->setCategory($category);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $this->getById((int) $item->getId());
    }

    /** @param array<string, mixed> $data */
    public function update(int $itemId, array $data): Item
    {
        $item = $this->getById($itemId);

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $item->setName((string) $data['name']);
        }

        if (array_key_exists('description', $data)) {
            $item->setDescription($data['description']);
        }

        if (array_key_exists('price', $data) && $data['price'] !== null) {
            $item->setPrice((string) $data['price']);
        }

        if (array_key_exists('category_id', $data)) {
            if ($data['category_id'] === null) {
                $item->setCategory(null);
            } else {
                $item->setCategory($this->categoryService->getById((int) $data['category_id']));
            }
        }

        $this->entityManager->flush();

        return $this->getById($itemId);
    }

    public function delete(int $itemId): void
    {
        $item = $this->getById($itemId);
        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }

    /** @return array<string, mixed> */
    public function getStats(): array
    {
        $total = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Item::class, 'i')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return [
                'total_items' => 0,
                'average_price' => 0.0,
                'min_price' => null,
                'max_price' => null,
                'uncategorized_count' => 0,
                'by_category' => [],
            ];
        }

        $aggregate = $this->entityManager->createQueryBuilder()
            ->select('AVG(i.price) AS avg_price, MIN(i.price) AS min_price, MAX(i.price) AS max_price')
            ->from(Item::class, 'i')
            ->getQuery()
            ->getSingleResult();

        $uncategorizedCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Item::class, 'i')
            ->where('i.category IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $categoryRows = $this->entityManager->createQueryBuilder()
            ->select('c.id AS category_id, c.name AS category_name, COUNT(i.id) AS item_count, AVG(i.price) AS average_price')
            ->from('App\Entity\Category', 'c')
            ->innerJoin('c.items', 'i')
            ->groupBy('c.id, c.name')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $byCategory = array_map(static function (array $row): array {
            return [
                'category_id' => (int) $row['category_id'],
                'category_name' => $row['category_name'],
                'item_count' => (int) $row['item_count'],
                'average_price' => round((float) $row['average_price'], 2),
            ];
        }, $categoryRows);

        return [
            'total_items' => $total,
            'average_price' => round((float) $aggregate['avg_price'], 2),
            'min_price' => round((float) $aggregate['min_price'], 2),
            'max_price' => round((float) $aggregate['max_price'], 2),
            'uncategorized_count' => $uncategorizedCount,
            'by_category' => $byCategory,
        ];
    }
}
