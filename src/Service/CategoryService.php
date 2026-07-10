<?php

namespace App\Service;

use App\Entity\Category;
use App\Exception\CategoryInUseException;
use App\Exception\CategoryNameExistsException;
use App\Exception\CategoryNotFoundException;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class CategoryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    /** @return array{0: list<Category>, 1: int} */
    public function listCategories(int $skip, int $limit): array
    {
        $qb = $this->categoryRepository->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC');

        $total = (int) (clone $qb)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $rows = $qb
            ->setFirstResult($skip)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [$rows, $total];
    }

    public function getById(int $categoryId): Category
    {
        $category = $this->categoryRepository->find($categoryId);
        if ($category === null) {
            throw new CategoryNotFoundException($categoryId);
        }

        return $category;
    }

    public function create(string $name, ?string $description): Category
    {
        $this->ensureUniqueName($name);

        $category = (new Category())
            ->setName($name)
            ->setDescription($description);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /** @param array<string, mixed> $data */
    public function update(int $categoryId, array $data): Category
    {
        $category = $this->getById($categoryId);

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $this->ensureUniqueName((string) $data['name'], $categoryId);
            $category->setName((string) $data['name']);
        }

        if (array_key_exists('description', $data)) {
            $category->setDescription($data['description']);
        }

        $this->entityManager->flush();

        return $category;
    }

    public function delete(int $categoryId): void
    {
        $category = $this->getById($categoryId);

        $itemCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from('App\Entity\Item', 'i')
            ->where('i.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();

        if ($itemCount > 0) {
            throw new CategoryInUseException($categoryId);
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    private function ensureUniqueName(string $name, ?int $excludeId = null): void
    {
        $existing = $this->categoryRepository->findOneByName($name);
        if ($existing !== null && $existing->getId() !== $excludeId) {
            throw new CategoryNameExistsException($name);
        }
    }
}
