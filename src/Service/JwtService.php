<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;

/**
 * JwtService
 * 
 * Service responsable de la génération et gestion des tokens JWT (JSON Web Tokens).
 * 
 * JWT est un standard ouvert (RFC 7519) pour créer des tokens d'authentification.
 * Un JWT contient trois parties séparées par des points:
 * 1. Header (en-tête): contient le type de token et l'algorithme de chiffrement
 * 2. Payload (charge utile): contient les données de l'utilisateur et les claims
 * 3. Signature: garantit que le token n'a pas été modifié
 * 
 * Format: header.payload.signature
 * Exemple: eyJhbGc...OjE3NjQwMjc0NTl...btZnts-yCwL...
 */
class JwtService
{
    private string $jwtSecret;
    private int $expirationTime;

    /**
     * Constructeur
     * 
     * @param string $appSecret - Secret utilisé pour signer les JWT (provient de .env APP_SECRET)
     * @param EntityManagerInterface $entityManager - Manager Doctrine pour persister les données
     * @param int $expirationTime - Durée de vie du token en secondes (par défaut 1 heure = 3600s)
     */
    public function __construct(
        string $appSecret,
        private EntityManagerInterface $entityManager,
        int $expirationTime = 3600
    )
    {
        $this->jwtSecret = $appSecret;
        $this->expirationTime = $expirationTime;
    }

    /**
     * generateToken() - Génère un JWT pour un utilisateur
     * 
     * Cette méthode:
     * 1. Crée un payload contenant les données de l'utilisateur
     * 2. Encode le payload avec la clé secrète en HS256
     * 3. Persiste le token dans la base de données (optionnel mais recommandé)
     * 4. Retourne le token pour l'envoyer au client
     * 
     * Les claims (données) inclus dans le token:
     * - iat (issued at): timestamp de création du token
     * - exp (expiration): timestamp d'expiration du token
     * - uuid: identifiant unique de l'utilisateur (utilisé pour le charger)
     * - id: ID de la base de données (redondant mais utile)
     * - roles: tableau des rôles de l'utilisateur (pour les autorisations)
     * 
     * @param User $user - L'utilisateur pour lequel générer le token
     * @return string - Le JWT complet au format string
     */
    public function generateToken(User $user): string
    {
        // Timestamp actuel (seconds depuis 1970)
        $issuedAt = time();
        
        // Calculer le timestamp d'expiration
        $expire = $issuedAt + $this->expirationTime;

        // Construire le payload (les données du token)
        $payload = [
            'iat' => $issuedAt,           // Moment de création
            'exp' => $expire,             // Moment d'expiration
            'uuid' => $user->getUuid(),   // ID unique de l'utilisateur
            'id' => $user->getId(),       // ID de la base de données
            'roles' => $user->getRoles(), // Rôles pour les autorisations
        ];

        // Encoder le payload en JWT avec l'algorithme HS256
        // HS256 = HMAC using SHA-256 (algorithme symétrique: même clé pour encoder et décoder)
        $token = JWT::encode($payload, $this->jwtSecret, 'HS256');

        // Persister le token dans la base de données pour pouvoir le vérifier plus tard
        // (optionnel mais recommandé pour plus de contrôle et pour pouvoir révoquer les tokens)
        $user->setJwtToken($token);
        
        // Sauvegarder l'utilisateur avec le nouveau token
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Retourner le token pour l'envoyer au client
        return $token;
    }

    /**
     * getExpirationTime() - Retourne le temps d'expiration des tokens
     * 
     * @return int - Temps d'expiration en secondes
     */
    public function getExpirationTime(): int
    {
        return $this->expirationTime;
    }
}
