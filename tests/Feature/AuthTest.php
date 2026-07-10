<?php

namespace App\Tests\Feature;

use App\Tests\ApiTestCase;

class AuthTest extends ApiTestCase
{
    public function testRegisterUser(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request(
            'POST',
            '/auth/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'alice@example.com', 'password' => 'password123'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('alice@example.com', $data['email']);
        self::assertArrayNotHasKey('password', $data);
    }

    public function testRegisterDuplicateEmail(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $payload = json_encode(['email' => 'test@example.com', 'password' => 'secret123'], JSON_THROW_ON_ERROR);
        $client->request('POST', '/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
        $client->request('POST', '/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('USER_EMAIL_EXISTS', $data['code']);
    }

    public function testLoginSuccess(): void
    {
        $client = $this->createAuthenticatedClient();

        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('bearer', $data['token_type']);
        self::assertNotEmpty($data['access_token']);
    }

    public function testLoginInvalidPassword(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request(
            'POST',
            '/auth/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com', 'password' => 'secret123'], JSON_THROW_ON_ERROR),
        );
        $client->request('POST', '/auth/login', parameters: ['username' => 'test@example.com', 'password' => 'wrong-password']);

        self::assertResponseStatusCodeSame(401);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Incorrect email or password', $data['detail']);
    }

    public function testReadCurrentUser(): void
    {
        $client = $this->createAuthenticatedClient();
        $headers = $this->bearerHeaders($client);

        $client->request('GET', '/auth/me', server: $headers);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('test@example.com', $data['email']);
    }

    public function testReadCurrentUserWithoutToken(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request('GET', '/auth/me');

        self::assertResponseStatusCodeSame(401);
    }
}
