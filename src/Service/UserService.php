<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\UserEmailExistsException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function getByEmail(string $email): ?User
    {
        return $this->userRepository->findOneByEmail($email);
    }

    public function create(string $email, string $password): User
    {
        if ($this->getByEmail($email) !== null) {
            throw new UserEmailExistsException($email);
        }

        $user = (new User())
            ->setEmail($email);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function authenticate(string $email, string $password): ?User
    {
        $user = $this->getByEmail($email);
        if ($user === null || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return null;
        }

        return $user;
    }
}
