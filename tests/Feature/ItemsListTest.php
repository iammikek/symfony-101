<?php

namespace App\Tests\Feature;

use App\Tests\ApiTestCase;

class ItemsListTest extends ApiTestCase
{
    public function testListItemsEmpty(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request('GET', '/items');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"items":[],"total":0,"skip":0,"limit":10}',
            $client->getResponse()->getContent(),
        );
    }

    public function testListItemsWithPagination(): void
    {
        $client = $this->createAuthenticatedClient();
        $headers = array_merge($this->bearerHeaders($client), ['CONTENT_TYPE' => 'application/json']);

        foreach ([['A', 1.0], ['B', 2.0], ['C', 3.0]] as [$name, $price]) {
            $client->request(
                'POST',
                '/items',
                server: $headers,
                content: json_encode(['name' => $name, 'price' => $price], JSON_THROW_ON_ERROR),
            );
            self::assertResponseStatusCodeSame(201);
        }

        $client->request('GET', '/items?skip=1&limit=2');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(3, $data['total']);
        self::assertSame(1, $data['skip']);
        self::assertSame(2, $data['limit']);
        self::assertCount(2, $data['items']);
        self::assertSame('B', $data['items'][0]['name']);
        self::assertSame('C', $data['items'][1]['name']);
    }

    public function testListItemsValidationErrors(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request('GET', '/items?limit=101');
        self::assertResponseStatusCodeSame(422);
    }
}
