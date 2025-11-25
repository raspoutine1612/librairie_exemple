<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\TokenAuthenticator;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Test TokenAuthenticator
 * 
 * Tests unitaires de l'authentificateur JWT.
 * On teste:
 * - La vérification de la présence du header Authorization
 * - La validation du JWT
 * - Le chargement de l'utilisateur
 * - La gestion des erreurs
 */
class TokenAuthenticatorTest extends TestCase
{
    private TokenAuthenticator $authenticator;
    private UserRepository $userRepository;
    private string $appSecret = 'test-secret-key-very-secure-1234567890';

    protected function setUp(): void
    {
        // Créer un mock du repository
        $this->userRepository = $this->createMock(UserRepository::class);
        
        // Créer l'authenticateur avec les dépendances mockées
        $this->authenticator = new TokenAuthenticator(
            $this->userRepository,
            $this->appSecret
        );
    }

    /**
     * Test: supports() retourne true si Authorization header est présent
     */
    public function testSupportsReturnsTrueWithAuthorizationHeader(): void
    {
        // Arrange
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer some_token');

        // Act & Assert
        $this->assertTrue($this->authenticator->supports($request));
    }

    /**
     * Test: supports() retourne false sans Authorization header
     */
    public function testSupportsReturnsFalseWithoutAuthorizationHeader(): void
    {
        // Arrange
        $request = new Request();

        // Act & Assert
        $this->assertFalse($this->authenticator->supports($request));
    }

    /**
     * Test: Authentification réussie avec un token valide
     */
    public function testAuthenticateWithValidToken(): void
    {
        // Arrange
        $user = new User();
        $user->setUuid('test_user');
        $user->setPassword('hashed_password');
        $user->setRoles(['ROLE_USER']);

        // Créer un JWT valide
        $now = time();
        $payload = [
            'iat' => $now,
            'exp' => $now + 3600,
            'uuid' => 'test_user',
            'roles' => ['ROLE_USER']
        ];
        $token = JWT::encode($payload, $this->appSecret, 'HS256');

        // Configurer le mock pour retourner l'utilisateur
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['uuid' => 'test_user'])
            ->willReturn($user);

        // Créer la requête avec le token
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        // Act
        $passport = $this->authenticator->authenticate($request);

        // Assert
        $this->assertNotNull($passport);
    }

    /**
     * Test: Erreur si le header Authorization est mal formé
     */
    public function testAuthenticateThrowsExceptionWithMalformedHeader(): void
    {
        // Arrange
        $request = new Request();
        $request->headers->set('Authorization', 'InvalidFormat');

        // Act & Assert
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Token manquant ou invalide');
        
        $this->authenticator->authenticate($request);
    }

    /**
     * Test: Erreur si le token est expiré
     */
    public function testAuthenticateThrowsExceptionWithExpiredToken(): void
    {
        // Arrange
        $now = time();
        $payload = [
            'iat' => $now - 7200,  // Créé il y a 2 heures
            'exp' => $now - 3600,  // Expiré il y a 1 heure
            'uuid' => 'test_user',
            'roles' => ['ROLE_USER']
        ];
        $token = JWT::encode($payload, $this->appSecret, 'HS256');

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        // Act & Assert
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Token expiré');
        
        $this->authenticator->authenticate($request);
    }

    /**
     * Test: Erreur si l'utilisateur n'existe pas en base de données
     */
    public function testAuthenticateThrowsExceptionWithUnknownUser(): void
    {
        // Arrange
        $now = time();
        $payload = [
            'iat' => $now,
            'exp' => $now + 3600,
            'uuid' => 'unknown_user',
            'roles' => ['ROLE_USER']
        ];
        $token = JWT::encode($payload, $this->appSecret, 'HS256');

        // Configurer le mock pour retourner null (utilisateur introuvable)
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['uuid' => 'unknown_user'])
            ->willReturn(null);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        // Act & Assert
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Utilisateur non trouvé');
        
        $this->authenticator->authenticate($request);
    }

    /**
     * Test: Erreur avec une mauvaise signature
     */
    public function testAuthenticateThrowsExceptionWithInvalidSignature(): void
    {
        // Arrange
        $now = time();
        $payload = [
            'iat' => $now,
            'exp' => $now + 3600,
            'uuid' => 'test_user',
            'id' => 1,
            'roles' => ['ROLE_USER']
        ];
        // Encoder avec une mauvaise clé secrète
        $token = JWT::encode($payload, 'wrong-secret-key', 'HS256');

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        // Act & Assert
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Token JWT invalide');
        
        $this->authenticator->authenticate($request);
    }

    /**
     * Test: onAuthenticationSuccess retourne null (requête continue)
     */
    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        // Arrange
        $request = new Request();
        $token = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);

        // Act
        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        // Assert
        $this->assertNull($response);
    }

    /**
     * Test: onAuthenticationFailure retourne JSON avec erreur
     */
    public function testOnAuthenticationFailureReturnsJsonResponse(): void
    {
        // Arrange
        $request = new Request();
        $exception = new AuthenticationException('Test error message');

        // Act
        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Test error message', $response->getContent());
    }

    /**
     * Test: start() retourne JSON avec erreur 401
     */
    public function testStartReturnsUnauthorizedJsonResponse(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $response = $this->authenticator->start($request);

        // Assert
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Authentication required', $response->getContent());
    }

    /**
     * Test: Token avec des rôles ROLE_ADMIN
     */
    public function testAuthenticateWithAdminRole(): void
    {
        // Arrange
        $user = new User();
        $user->setUuid('admin_user');
        $user->setPassword('hashed_password');
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        $now = time();
        $payload = [
            'iat' => $now,
            'exp' => $now + 3600,
            'uuid' => 'admin_user',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER']
        ];
        $token = JWT::encode($payload, $this->appSecret, 'HS256');

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['uuid' => 'admin_user'])
            ->willReturn($user);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        // Act
        $passport = $this->authenticator->authenticate($request);

        // Assert
        $this->assertNotNull($passport);
    }
}
