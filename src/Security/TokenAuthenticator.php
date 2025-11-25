<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use App\Entity\User;
use App\Repository\UserRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

/**
 * TokenAuthenticator
 * 
 * Authenticateur personnalisé pour gérer l'authentification basée sur JWT (JSON Web Tokens).
 * Cet authenticateur intercepte les requêtes avec un header Authorization contenant un Bearer token
 * et valide le JWT pour authentifier l'utilisateur.
 * 
 * Flux d'authentification:
 * 1. supports() - Vérifie si la requête a un header Authorization
 * 2. authenticate() - Décode et valide le JWT, puis charge l'utilisateur
 * 3. onAuthenticationSuccess() - Laisse la requête continuer (null)
 * 4. onAuthenticationFailure() - Retourne une erreur JSON si l'authentification échoue
 * 5. start() - Point d'entrée appelé quand l'accès est refusé (entry_point)
 */
class TokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private UserRepository $userRepository;
    private string $jwtSecret;

    /**
     * Constructeur
     * 
     * @param UserRepository $userRepository - Repository pour charger les utilisateurs
     * @param string $appSecret - Secret utilisé pour signer/vérifier les JWT (provient de .env APP_SECRET)
     */
    public function __construct(UserRepository $userRepository, string $appSecret)
    {
        $this->userRepository = $userRepository;
        $this->jwtSecret = $appSecret;
    }

    /**
     * supports() - Détermine si cet authenticateur doit être utilisé
     * 
     * Cette méthode est appelée pour chaque requête. Si elle retourne true,
     * la méthode authenticate() sera appelée. Si elle retourne false,
     * Symfony essaiera les autres authenticateurs.
     * 
     * Ici, on retourne true seulement si la requête contient un header Authorization,
     * ce qui signale la présence d'un Bearer token JWT.
     * 
     * @param Request $request - La requête HTTP actuelle
     * @return bool|null - true si la requête a Authorization, false sinon
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    /**
     * authenticate() - Valide le JWT et charge l'utilisateur correspondant
     * 
     * Cette méthode:
     * 1. Extrait le Bearer token du header Authorization
     * 2. Décode le JWT avec la clé secrète
     * 3. Charge l'utilisateur depuis la base de données
     * 4. Retourne un Passport valide qui authentifie l'utilisateur
     * 
     * @param Request $request - La requête HTTP
     * @return Passport - Objet représentant l'authentification réussie
     * @throws AuthenticationException - Si le token est manquant, invalide ou expiré
     */
    public function authenticate(Request $request): Passport
    {
        // Récupérer le header Authorization (format: "Bearer <token>")
        $authHeader = $request->headers->get('Authorization');
        
        // Extraire le token JWT après "Bearer "
        if (!$authHeader || !preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            throw new AuthenticationException('Token manquant ou invalide');
        }

        $token = $matches[1];

        try {
            // Décoder le JWT avec la clé secrète
            // Si le token est expiré ou la signature invalide, une exception sera levée
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

            // Charger l'utilisateur depuis la base de données en utilisant le UUID du token
            $user = $this->userRepository->findOneBy(['uuid' => $decoded->uuid]);
            if (!$user) {
                throw new AuthenticationException('Utilisateur non trouvé');
            }

            // Créer un UserBadge qui chargera l'utilisateur via la callback
            // Cette callback permet de recharger l'utilisateur si nécessaire
            $userBadge = new UserBadge(
                (string) $user->getUuid(),
                function($uuid) {
                    return $this->userRepository->findOneBy(['uuid' => $uuid]);
                }
            );
            
            // Retourner un SelfValidatingPassport
            // Ce type de passport indique que le token lui-même est suffisant pour l'authentification
            // (nous ne nécessitons pas de données supplémentaires ou de vérifications)
            return new SelfValidatingPassport($userBadge);
        } catch (ExpiredException $e) {
            // Le token JWT a expiré
            throw new AuthenticationException('Token expiré. Veuillez vous reconnecter.');
        } catch (\Exception $e) {
            // Toute autre erreur (mauvaise signature, format invalide, etc.)
            throw new AuthenticationException('Token JWT invalide: ' . $e->getMessage());
        }
    }

    /**
     * onAuthenticationSuccess() - Appelé après une authentification réussie
     * 
     * Nous retournons null ici, ce qui signifie que la requête est autorisée à continuer.
     * Si vous vouliez faire quelque chose après une authentification réussie (comme logger,
     * mettre à jour un timestamp, etc.), vous le feriez ici.
     * 
     * @param Request $request - La requête HTTP
     * @param TokenInterface $token - Le token d'authentification généré
     * @param string $firewallName - Le nom du firewall utilisé
     * @return Response|null - null pour continuer, ou une réponse personnalisée
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    /**
     * onAuthenticationFailure() - Appelé si l'authentification échoue
     * 
     * Cette méthode retourne une réponse JSON avec l'erreur d'authentification.
     * C'est important pour les APIs REST qui s'attendent à du JSON plutôt qu'à une redirection.
     * 
     * @param Request $request - La requête HTTP
     * @param AuthenticationException $exception - L'exception contenant le message d'erreur
     * @return Response - La réponse d'erreur JSON
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * start() - Implémente AuthenticationEntryPointInterface
     * 
     * Cette méthode est appelée lorsqu'une ressource protégée est accédée sans authentification.
     * Par exemple, si on essaie d'accéder à /api/user/me sans Authorization header.
     * 
     * Elle retourne une réponse JSON au lieu de rediriger vers une page de connexion,
     * ce qui est idéal pour une API REST.
     * 
     * @param Request $request - La requête HTTP
     * @param AuthenticationException|null $authException - L'exception d'authentification (peut être null)
     * @return Response - La réponse d'erreur JSON
     */
    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            ['error' => 'Authentication required'],
            Response::HTTP_UNAUTHORIZED
        );
    }
}

