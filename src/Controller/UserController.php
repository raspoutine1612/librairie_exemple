<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use OpenApi\Attributes as OA;

/**
 * UserController
 * 
 * Contrôleur gérant les opérations d'authentification et de gestion des utilisateurs.
 * 
 * Endpoints:
 * - POST /api/user/register - Créer un nouvel utilisateur (ROLE_ADMIN seulement)
 * - POST /api/user/login - Se connecter avec uuid/password, reçoit un JWT
 * - GET /api/user/me - Obtenir les infos de l'utilisateur connecté
 * - GET /api/user/{userId} - Obtenir les infos d'un utilisateur (ROLE_ADMIN seulement)
 */
#[Route('/api/user')]
final class UserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private JwtService $jwtService,
        private UserPasswordHasherInterface $passwordHasher
    )
    {
    }

    /**
     * register() - Créer un nouvel utilisateur
     * 
     * Endpoint protégé réservé aux administrateurs.
     * L'utilisateur doit avoir le rôle ROLE_ADMIN pour accéder à cet endpoint.
     * 
     * Données requises dans le JSON:
     * - uuid: identifiant unique de l'utilisateur (string)
     * - password: mot de passe en clair (sera hasher avec bcrypt)
     * - roles (optionnel): tableau de rôles (ex: ["ROLE_USER", "ROLE_ADMIN"])
     * 
     * Retour:
     * - 201 (CREATED): utilisateur créé avec succès
     * - 400 (BAD_REQUEST): données invalides ou manquantes
     * - 403 (FORBIDDEN): utilisateur connecté n'a pas ROLE_ADMIN
     * - 409 (CONFLICT): UUID déjà existant
     */
    #[OA\Post(
        path: '/api/user/register',
        summary: 'Créer un nouvel utilisateur',
        description: 'Crée un nouvel utilisateur. Nécessite ROLE_ADMIN.',
        tags: ['Utilisateur'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    'uuid' => new OA\Property(property: 'uuid', type: 'string', example: 'user@example.com'),
                    'password' => new OA\Property(property: 'password', type: 'string', example: 'password123'),
                    'roles' => new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'))
                ],
                required: ['uuid', 'password'],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Utilisateur créé avec succès',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(property: 'message', type: 'string', example: 'Utilisateur créé avec succès'),
                        'token' => new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGc...'),
                        'expiresIn' => new OA\Property(property: 'expiresIn', type: 'integer', example: 3600)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 409, description: 'UUID déjà existant')
        ]
    )]
    #[Route('/register', name: 'register_user', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur connecté a le rôle ROLE_ADMIN
        // Nous faisons cette vérification manuellement ici plutôt qu'avec #[IsGranted]
        // pour avoir plus de contrôle sur le message d'erreur
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        try {
            // Décoder le JSON de la requête
            $data = json_decode($request->getContent(), true);

            // Vérifier que les champs requis sont présents
            if (!isset($data['uuid']) || !isset($data['password'])) {
                return new JsonResponse(
                    ['error' => 'UUID et mot de passe sont requis'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Vérifier que l'utilisateur n'existe pas déjà
            $existingUser = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);
            if ($existingUser) {
                return new JsonResponse(
                    ['error' => 'Cet UUID existe déjà'],
                    Response::HTTP_CONFLICT
                );
            }

            // Créer une nouvelle instance d'User
            $user = new User();
            $user->setUuid($data['uuid']);

            // Hasher le mot de passe avec bcrypt (algorithme sécurisé)
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);

            // Ajouter les rôles si fournis dans la requête
            if (isset($data['roles']) && is_array($data['roles'])) {
                $user->setRoles($data['roles']);
            }

            // Sauvegarder l'utilisateur en base de données
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Générer un JWT token pour l'utilisateur
            $token = $this->jwtService->generateToken($user);

            return new JsonResponse([
                'message' => 'Utilisateur créé avec succès',
                'token' => $token,
                'expiresIn' => $this->jwtService->getExpirationTime()
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * login() - Authentifier un utilisateur et retourner un JWT
     * 
     * Endpoint public (sans authentification requise).
     * L'utilisateur envoie son uuid et password, et reçoit un JWT valide pour 1 heure.
     * 
     * Données requises dans le JSON:
     * - uuid: identifiant unique
     * - password: mot de passe en clair
     * 
     * Retour:
     * - 200 (OK): authentification réussie avec JWT
     * - 400 (BAD_REQUEST): données invalides ou manquantes
     * - 401 (UNAUTHORIZED): utilisateur introuvable ou mot de passe incorrect
     */
    #[OA\Post(
        path: '/api/user/login',
        summary: 'Authentifier un utilisateur',
        description: 'Authentifie un utilisateur et retourne un JWT',
        tags: ['Utilisateur'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    'uuid' => new OA\Property(property: 'uuid', type: 'string', example: 'user@example.com'),
                    'password' => new OA\Property(property: 'password', type: 'string', example: 'password123')
                ],
                required: ['uuid', 'password'],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connexion réussie',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(property: 'message', type: 'string', example: 'Connexion réussie'),
                        'token' => new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGc...'),
                        'expiresIn' => new OA\Property(property: 'expiresIn', type: 'integer', example: 3600)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 401, description: 'Identifiants invalides')
        ]
    )]
    #[Route('/login', name: 'login_user', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
            // Décoder le JSON de la requête
            $data = json_decode($request->getContent(), true);

            // Vérifier que les données requises sont présentes
            if (!isset($data['uuid']) || !isset($data['password'])) {
                return new JsonResponse(
                    ['error' => 'UUID et mot de passe sont requis'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Chercher l'utilisateur par son UUID
            $user = $this->userRepository->findOneBy(['uuid' => $data['uuid']]);
            if (!$user) {
                return new JsonResponse(
                    ['error' => 'Utilisateur non trouvé'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Vérifier que le mot de passe est correct
            // isPasswordValid compare le mot de passe en clair avec le hash stocké
            if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
                return new JsonResponse(
                    ['error' => 'Mot de passe incorrect'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Mot de passe correct: générer un JWT
            $token = $this->jwtService->generateToken($user);

            return new JsonResponse([
                'message' => 'Connexion réussie',
                'token' => $token,
                'expiresIn' => $this->jwtService->getExpirationTime()
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * getCurrentUser() - Obtenir les informations de l'utilisateur connecté
     * 
     * Endpoint protégé: nécessite un JWT valide avec au minimum ROLE_USER.
     * 
     * Retour:
     * - 200 (OK): objet utilisateur avec id, uuid, roles
     * - 401 (UNAUTHORIZED): pas de JWT ou JWT invalide
     */
    #[OA\Get(
        path: '/api/user/me',
        summary: 'Obtenir l\'utilisateur connecté',
        description: 'Récupère les informations de l\'utilisateur authentifié',
        tags: ['Utilisateur'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Informations utilisateur',
                content: new OA\JsonContent(
                    properties: [
                        'id' => new OA\Property(property: 'id', type: 'integer', example: 1),
                        'uuid' => new OA\Property(property: 'uuid', type: 'string', example: 'user@example.com'),
                        'roles' => new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'))
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    #[Route('/me', name: 'get_current_user', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCurrentUser(): JsonResponse
    {
        // getUser() retourne l'utilisateur actuellement authentifié
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(
                ['error' => 'Utilisateur non authentifié'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'uuid' => $user->getUuid(),
            'roles' => $user->getRoles()
        ]);
    }

    /**
     * getUserById() - Obtenir les informations d'un utilisateur spécifique
     * 
     * Endpoint protégé: réservé aux administrateurs (ROLE_ADMIN).
     * Permet de chercher un utilisateur par son ID de base de données.
     * 
     * Paramètres:
     * - userId: ID de la base de données
     * 
     * Retour:
     * - 200 (OK): objet utilisateur
     * - 403 (FORBIDDEN): utilisateur ne pas ROLE_ADMIN
     * - 404 (NOT_FOUND): utilisateur inexistant
     */
    #[OA\Get(
        path: '/api/user/{userId}',
        summary: 'Obtenir un utilisateur par ID',
        description: 'Récupère les informations d\'un utilisateur. Nécessite ROLE_ADMIN.',
        tags: ['Utilisateur'],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                description: 'ID utilisateur',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Informations utilisateur',
                content: new OA\JsonContent(
                    properties: [
                        'id' => new OA\Property(property: 'id', type: 'integer', example: 1),
                        'uuid' => new OA\Property(property: 'uuid', type: 'string', example: 'user@example.com'),
                        'roles' => new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'))
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé')
        ]
    )]
    #[Route('/{userId}', name: 'get_user_by_id', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getUserById(int $userId): JsonResponse
    {
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            return new JsonResponse(
                ['error' => 'Utilisateur non trouvé'],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'uuid' => $user->getUuid(),
            'roles' => $user->getRoles()
        ]);
    }
}
