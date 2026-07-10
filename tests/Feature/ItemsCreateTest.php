<?php

namespace App\Tests\Feature;

use App\Tests\ApiTestCase;

class ItemsCreateTest extends ApiTestCase
{
    public function testCreateItem(): void
    {
        $client = $this->createAuthenticatedClient();
        $headers = array_merge($this->bearerHeaders($client), ['CONTENT_TYPE' => 'application/json']);

        $client->request(
            'POST',
            '/items',
            server: $headers,
            content: json_encode([
                'name' => 'Widget',
                'description' => 'A nice widget',
                'price' => 9.99,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertGreaterThanOrEqual(1, $data['id']);
        self::assertSame('Widget', $data['name']);
        self::assertSame('A nice widget', $data['description']);
        self::assertSame(9.99, $data['price']);
    }

    public function testCreateItemWithoutAuth(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request(
            'POST',
            '/items',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Widget', 'price' => 9.99], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetItemNotFound(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request('GET', '/items/99');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ITEM_NOT_FOUND', $data['code']);
    }

    public function testDeleteItem(): void
    {
        $client = $this->createAuthenticatedClient();
        $headers = array_merge($this->bearerHeaders($client), ['CONTENT_TYPE' => 'application/json']);

        $client->request(
            'POST',
            '/items',
            server: $headers,
            content: json_encode(['name' => 'To Delete', 'price' => 1.0], JSON_THROW_ON_ERROR),
        );
        $itemId = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $client->request('DELETE', '/items/' . $itemId, server: $headers);
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/items/' . $itemId);
        self::assertResponseStatusCodeSame(404);
    }
}
