<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected function resetDatabase(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $connection->executeStatement('DELETE FROM items');
        $connection->executeStatement('DELETE FROM categories');
        $connection->executeStatement('DELETE FROM users');
    }

    protected function createAuthenticatedClient(): KernelBrowser
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request(
            'POST',
            '/auth/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com', 'password' => 'secret123'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);

        $client->request(
            'POST',
            '/auth/login',
            parameters: ['username' => 'test@example.com', 'password' => 'secret123'],
        );
        self::assertResponseStatusCodeSame(200);

        return $client;
    }

    /** @return array<string, string> */
    protected function bearerHeaders(KernelBrowser $client): array
    {
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $data['access_token']];
    }
}
