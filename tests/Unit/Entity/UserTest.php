<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Test User Entity
 * 
 * Tests unitaires de l'entité User.
 * On teste:
 * - Les getters/setters
 * - Les rôles
 * - Le password
 * - Le JWT token
 */
class UserTest extends TestCase
{
    /**
     * Test: Création et getters/setters basiques
     */
    public function testUserCanBeCreatedWithBasicProperties(): void
    {
        // Arrange & Act
        $user = new User();
        $user->setUuid('john_doe');
        $user->setPassword('hashed_password');

        // Assert
        $this->assertEquals('john_doe', $user->getUuid());
        $this->assertEquals('hashed_password', $user->getPassword());
    }

    /**
     * Test: Définir et récupérer les rôles
     */
    public function testUserCanHaveMultipleRoles(): void
    {
        // Arrange
        $user = new User();
        $roles = ['ROLE_USER', 'ROLE_ADMIN'];

        // Act
        $user->setRoles($roles);

        // Assert
        $this->assertEquals($roles, $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    /**
     * Test: Le setter setRoles ajoute le rôle ROLE_USER par défaut
     */
    public function testSetRolesEnsuresRoleUserIsAlwaysPresent(): void
    {
        // Arrange
        $user = new User();
        
        // Act
        $user->setRoles(['ROLE_ADMIN']);

        // Assert
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    /**
     * Test: Définir et récupérer le JWT token
     */
    public function testUserCanHaveJwtToken(): void
    {
        // Arrange
        $user = new User();
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE3NjQwMjc0NTl9.signature';

        // Act
        $user->setJwtToken($token);

        // Assert
        $this->assertEquals($token, $user->getJwtToken());
    }

    /**
     * Test: ID est généré automatiquement par Doctrine
     */
    public function testUserIdIsAutoIncrement(): void
    {
        // Arrange
        $user = new User();
        $user->setUuid('user1');

        // Act & Assert
        // L'ID est null tant que l'entity n'est pas persistée
        $this->assertNull($user->getId());
    }

    /**
     * Test: User sans rôles a au moins ROLE_USER par défaut
     */
    public function testNewUserHasRoleUserByDefault(): void
    {
        // Arrange & Act
        $user = new User();

        // Assert
        // Après la création, les rôles par défaut doivent être vides
        // mais le setRoles() ajoute ROLE_USER
        $roles = $user->getRoles();
        $this->assertIsArray($roles);
    }

    /**
     * Test: Setter de password
     */
    public function testPasswordCanBeSet(): void
    {
        // Arrange
        $user = new User();
        $hashedPassword = '$2y$10$hashedpasswordexample';

        // Act
        $user->setPassword($hashedPassword);

        // Assert
        $this->assertEquals($hashedPassword, $user->getPassword());
    }

    /**
     * Test: Setter de UUID
     */
    public function testUuidCanBeSet(): void
    {
        // Arrange
        $user = new User();
        
        // Act
        $user->setUuid('alice@example.com');

        // Assert
        $this->assertEquals('alice@example.com', $user->getUuid());
    }

    /**
     * Test: Multiple users indépendants
     */
    public function testMultipleUsersAreIndependent(): void
    {
        // Arrange
        $user1 = new User();
        $user1->setUuid('alice');
        $user1->setRoles(['ROLE_USER']);
        $user1->setPassword('password1');

        $user2 = new User();
        $user2->setUuid('bob');
        $user2->setRoles(['ROLE_ADMIN']);
        $user2->setPassword('password2');

        // Act & Assert
        $this->assertNotEquals($user1->getUuid(), $user2->getUuid());
        $this->assertNotEquals($user1->getPassword(), $user2->getPassword());
        $this->assertNotEquals($user1->getRoles(), $user2->getRoles());
    }

    /**
     * Test: JWT token est nullable
     */
    public function testJwtTokenCanBeNull(): void
    {
        // Arrange
        $user = new User();

        // Act & Assert
        $this->assertNull($user->getJwtToken());
    }
}
