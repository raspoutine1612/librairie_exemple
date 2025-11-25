<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

/**
 * Test JwtService
 * 
 * Tests unitaires du service JwtService qui gère la génération des JWT.
 * On teste:
 * - La génération d'un token valide
 * - La présence des claims corrects
 * - L'expiration du token
 * - La persistance en base de données
 */
class JwtServiceTest extends TestCase
{
    private JwtService $jwtService;
    private EntityManagerInterface $entityManager;
    private string $appSecret = 'test-secret-key-very-secure-1234567890';

    protected function setUp(): void
    {
        // Créer un mock du EntityManager
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        
        // Créer l'instance du service avec les dépendances mockées
        $this->jwtService = new JwtService(
            $this->appSecret,
            $this->entityManager,
            3600
        );
    }

    /**
     * Test: Génération d'un token valide
     * 
     * Vérifie que generateToken() retourne un JWT bien formé
     */
    public function testGenerateTokenReturnsValidJwt(): void
    {
        // Arrange: Créer un utilisateur test
        $user = new User();
        $user->setUuid('test_user');
        $user->setRoles(['ROLE_USER']);

        // Act: Générer le token
        $token = $this->jwtService->generateToken($user);

        // Assert: Vérifier que le token est un string et contient 3 parties (JWT format)
        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token);
        
        // Compter les points: un JWT a exactement 2 points (3 parties)
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    /**
     * Test: Le token peut être décodé avec la clé secrète
     * 
     * Vérifie que le token généré peut être validé et décodé
     */
    public function testGeneratedTokenCanBeDecoded(): void
    {
        // Arrange
        $user = new User();
        $user->setUuid('john_doe');
        $user->setRoles(['ROLE_ADMIN']);

        // Act
        $token = $this->jwtService->generateToken($user);

        // Assert: Décoder le token et vérifier les données
        $decoded = JWT::decode($token, new Key($this->appSecret, 'HS256'));
        
        $this->assertEquals('john_doe', $decoded->uuid);
        $this->assertContains('ROLE_ADMIN', $decoded->roles);
    }

    /**
     * Test: Les claims ont les bonnes valeurs
     * 
     * Vérifie que tous les claims (données) du JWT sont corrects
     */
    public function testGeneratedTokenContainsCorrectClaims(): void
    {
        // Arrange
        $user = new User();
        $user->setUuid('alice');
        $user->setRoles(['ROLE_USER']);

        $beforeTime = time();

        // Act
        $token = $this->jwtService->generateToken($user);

        $afterTime = time();

        // Assert: Décoder et vérifier tous les claims
        $decoded = JWT::decode($token, new Key($this->appSecret, 'HS256'));
        
        // iat (issued at) doit être entre before et after
        $this->assertGreaterThanOrEqual($beforeTime, $decoded->iat);
        $this->assertLessThanOrEqual($afterTime, $decoded->iat);
        
        // exp (expiration) doit être iat + 3600
        $this->assertEquals($decoded->iat + 3600, $decoded->exp);
        
        // uuid, roles doivent correspondre
        $this->assertEquals('alice', $decoded->uuid);
        $this->assertEquals(['ROLE_USER'], $decoded->roles);
    }

    /**
     * Test: La persistance en base de données
     * 
     * Vérifie que le token est sauvegardé dans la base de données
     */
    public function testGenerateTokenPersistsToDatabase(): void
    {
        // Arrange
        $user = new User();
        $user->setUuid('test_user');
        $user->setRoles(['ROLE_USER']);

        // Configurer les mocks pour vérifier les appels
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($user);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $token = $this->jwtService->generateToken($user);

        // Assert: Vérifier que le token a été défini sur l'utilisateur
        $this->assertEquals($token, $user->getJwtToken());
    }

    /**
     * Test: La durée d'expiration est correcte
     * 
     * Vérifie que getExpirationTime() retourne la bonne valeur
     */
    public function testGetExpirationTimeReturnsCorrectValue(): void
    {
        // Act & Assert
        $this->assertEquals(3600, $this->jwtService->getExpirationTime());
    }

    /**
     * Test: Plusieurs utilisateurs reçoivent des tokens différents
     * 
     * Vérifie que chaque utilisateur a son propre token
     */
    public function testDifferentUsersHaveDifferentTokens(): void
    {
        // Arrange
        $user1 = new User();
        $user1->setUuid('alice');
        $user1->setRoles(['ROLE_USER']);

        $user2 = new User();
        $user2->setUuid('bob');
        $user2->setRoles(['ROLE_USER']);

        // Act
        $token1 = $this->jwtService->generateToken($user1);
        $token2 = $this->jwtService->generateToken($user2);

        // Assert
        $this->assertNotEquals($token1, $token2);
        
        // Vérifier que les tokens contiennent les bonnes identités
        $decoded1 = JWT::decode($token1, new Key($this->appSecret, 'HS256'));
        $decoded2 = JWT::decode($token2, new Key($this->appSecret, 'HS256'));
        
        $this->assertEquals('alice', $decoded1->uuid);
        $this->assertEquals('bob', $decoded2->uuid);
    }

    /**
     * Test: Le token avec une mauvaise clé secrète ne peut pas être décodé
     * 
     * Vérifie que la sécurité du token fonctionne (pas de signature spoofée)
     */
    public function testTokenWithWrongSecretCannotBeDecoded(): void
    {
        // Arrange
        $user = new User();
        $user->setUuid('test_user');
        $user->setRoles(['ROLE_USER']);

        $token = $this->jwtService->generateToken($user);
        
        // Act & Assert: Essayer de décoder avec une mauvaise clé secrète
        $this->expectException(\Exception::class);
        JWT::decode($token, new Key('wrong-secret-key', 'HS256'));
    }
}
