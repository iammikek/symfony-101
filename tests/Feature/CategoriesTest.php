<?php

namespace App\Tests\Feature;

use App\Tests\ApiTestCase;

class CategoriesTest extends ApiTestCase
{
    public function testCreateCategory(): void
    {
        $client = $this->createAuthenticatedClient();
        $headers = array_merge($this->bearerHeaders($client), ['CONTENT_TYPE' => 'application/json']);

        $client->request(
            'POST',
            '/categories',
            server: $headers,
            content: json_encode(['name' => 'Tools', 'description' => 'Hand tools'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertGreaterThanOrEqual(1, $data['id']);
        self::assertSame('Tools', $data['name']);
        self::assertSame('Hand tools', $data['description']);
    }

    public function testCreateCategoryWithoutAuth(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request(
            'POST',
            '/categories',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Tools'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateCategoryDuplicateName(): void
    {
        $client = $this->createAuthenticatedClient();
        $headers = array_merge($this->bearerHeaders($client), ['CONTENT_TYPE' => 'application/json']);

        $client->request(
            'POST',
            '/categories',
            server: $headers,
            content: json_encode(['name' => 'foo'], JSON_THROW_ON_ERROR),
        );
        $client->request(
            'POST',
            '/categories',
            server: $headers,
            content: json_encode(['name' => 'foo', 'description' => 'duplicate'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('CATEGORY_NAME_EXISTS', $data['code']);
    }

    public function testListCategories(): void
    {
        $client = $this->createAuthenticatedClient();
        $headers = array_merge($this->bearerHeaders($client), ['CONTENT_TYPE' => 'application/json']);

        $client->request(
            'POST',
            '/categories',
            server: $headers,
            content: json_encode(['name' => 'Books'], JSON_THROW_ON_ERROR),
        );

        $client->request('GET', '/categories');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['total']);
        self::assertSame('Books', $data['items'][0]['name']);
    }
}
