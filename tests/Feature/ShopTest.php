<?php

namespace App\Tests\Feature;

use App\Entity\User;
use App\Tests\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ShopTest extends ApiTestCase
{
    public function testShopHomeRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/shop');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Catalog Shop', $client->getResponse()->getContent());
        self::assertStringContainsString('Full-stack Symfony', $client->getResponse()->getContent());
    }

    public function testShopItemListEmpty(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request('GET', '/shop/items');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('No items match', $client->getResponse()->getContent());
    }

    public function testShopItemDetail(): void
    {
        $client = $this->createAuthenticatedClient();
        $headers = array_merge($this->bearerHeaders($client), ['CONTENT_TYPE' => 'application/json']);

        $client->request(
            'POST',
            '/items',
            server: $headers,
            content: json_encode(['name' => 'Shop Widget', 'price' => 9.99], JSON_THROW_ON_ERROR),
        );
        $itemId = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $client->request('GET', '/shop/items/' . $itemId);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Shop Widget', $client->getResponse()->getContent());
    }

    public function testShopCreateRequiresLogin(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request('GET', '/shop/items/new');

        self::assertResponseRedirects('/shop/login');
    }

    public function testShopCreateItem(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->createShopUser($client);

        $client->request('GET', '/shop/items/new');
        self::assertResponseIsSuccessful();

        $client->submitForm('Save item', [
            'item_form[name]' => 'Browser Widget',
            'item_form[description]' => 'Added via HTML form',
            'item_form[price]' => '12.50',
        ]);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertStringContainsString('Browser Widget', $client->getResponse()->getContent());
    }

    public function testShopLoginPage(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $user = $this->createShopUserEntity();

        $client->request('GET', '/shop/login');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Browser session auth', $client->getResponse()->getContent());

        $client->request('POST', '/shop/login', [
            'email' => $user->getEmail(),
            'password' => 'secret123',
        ]);
        self::assertResponseRedirects('/shop');
    }

    public function testShopRegisterPage(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $client->request('GET', '/shop/register');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Create account', $client->getResponse()->getContent());
    }

    public function testShopRegisterCreatesUserAndLogsIn(): void
    {
        $client = static::createClient();
        $this->resetDatabase();

        $crawler = $client->request('GET', '/shop/register');
        $form = $crawler->selectButton('Create account')->form([
            'registration_form[email]' => 'newshopper@example.com',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/shop');
        $client->followRedirect();
        self::assertStringContainsString('newshopper@example.com', $client->getResponse()->getContent());

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNotNull($em->getRepository(User::class)->findOneBy(['email' => 'newshopper@example.com']));
    }

    public function testShopRegisterDuplicateEmail(): void
    {
        $client = static::createClient();
        $this->resetDatabase();
        $this->createShopUserEntity();

        $crawler = $client->request('GET', '/shop/register');
        $form = $crawler->selectButton('Create account')->form([
            'registration_form[email]' => 'shopper@example.com',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);
        $client->submit($form);

        self::assertStringContainsString('already exists', $client->getResponse()->getContent());
    }

    private function createShopUserEntity(): User
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())
            ->setEmail('shopper@example.com');
        $user->setPassword(
            static::getContainer()->get('security.user_password_hasher')->hashPassword($user, 'secret123'),
        );
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createShopUser(KernelBrowser $client): void
    {
        $this->createShopUserEntity();
        $client->request('POST', '/shop/login', [
            'email' => 'shopper@example.com',
            'password' => 'secret123',
        ]);
        self::assertResponseRedirects();
    }
}
