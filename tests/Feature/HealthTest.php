<?php

namespace App\Tests\Feature;

use App\Tests\ApiTestCase;

class HealthTest extends ApiTestCase
{
    public function testRoot(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"message":"Hello from symfony-101"}',
            $client->getResponse()->getContent(),
        );
    }

    public function testHealth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', $client->getResponse()->getContent());
    }
}
