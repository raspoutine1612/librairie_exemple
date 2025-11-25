<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthenticationTest extends WebTestCase
{
    private string $testUserUuid = 'testuser@example.com';
    private string $testAdminUuid = 'admin@example.com';
    private string $testPassword = 'password123';

    public function testCreateAndLoginStandardUser(): void
    {
        [$client, $adminToken] = $this->createAdminAndLogin();
        
        $client->request('POST', '/api/user/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken
        ], json_encode([
            'uuid' => $this->testUserUuid,
            'password' => $this->testPassword,
            'roles' => ['ROLE_USER']
        ]));

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $response);
        
        $client->request('POST', '/api/user/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'uuid' => $this->testUserUuid,
            'password' => $this->testPassword
        ]));

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $response);
    }

    public function testCreateAndLoginAdmin(): void
    {
        [$client, $adminToken] = $this->createAdminAndLogin();
        $this->assertNotNull($adminToken);
        
        $client->request('GET', '/api/user/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken
        ]);

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertContains('ROLE_ADMIN', $response['roles']);
    }

    public function testCreateUserWithoutAdminRoleFails(): void
    {
        [$client, $adminToken] = $this->createAdminAndLogin();
        
        // Créer un utilisateur standard d'abord
        $client->request('POST', '/api/user/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken
        ], json_encode([
            'uuid' => $this->testUserUuid,
            'password' => $this->testPassword,
            'roles' => ['ROLE_USER']
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        $standardToken = $response['token'];
        
        // Maintenant essayer de créer un nouvel utilisateur en tant qu'utilisateur standard
        $client->request('POST', '/api/user/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $standardToken
        ], json_encode([
            'uuid' => 'newuser@example.com',
            'password' => 'password123'
        ]));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAccessProtectedEndpointWithoutToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/user/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginWithWrongPassword(): void
    {
        [$client, $token] = $this->createAdminAndLogin();
        
        $client->request('POST', '/api/user/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'uuid' => $this->testAdminUuid,
            'password' => 'wrongpassword'
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginWithNonExistentUser(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/user/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'uuid' => 'nonexistent@example.com',
            'password' => 'anypassword'
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    private function createAdminAndLogin()
    {
        $client = static::createClient();
        
        // Récupérer les services après createClient() qui boot le kernel
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        
        // Nettoyer
        $connection = $entityManager->getConnection();
        $connection->executeStatement('DELETE FROM user');
        
        // Créer l'admin
        $admin = new User();
        $admin->setUuid($this->testAdminUuid);
        $admin->setPassword($passwordHasher->hashPassword($admin, $this->testPassword));
        $admin->setRoles(['ROLE_ADMIN']);
        
        $entityManager->persist($admin);
        $entityManager->flush();

        // Login
        $client->request('POST', '/api/user/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'uuid' => $this->testAdminUuid,
            'password' => $this->testPassword
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        return [$client, $response['token']];
    }

    private function createStandardUserAndLogin(): string
    {
        [$client, $adminToken] = $this->createAdminAndLogin();

        $client->request('POST', '/api/user/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken
        ], json_encode([
            'uuid' => $this->testUserUuid,
            'password' => $this->testPassword,
            'roles' => ['ROLE_USER']
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        return $response['token'];
    }
}
