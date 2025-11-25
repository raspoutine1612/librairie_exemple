<?php

namespace App\Controller;

use App\Repository\LivreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Model\Livre;
use App\Model\Auteur;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\AuteurRepository;
use Symfony\Component\Serializer\SerializerInterface;
use App\Service\LivreCreationService;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

/**
 * LivreController
 * 
 * Contrôleur gérant les opérations sur les livres.
 * 
 * Endpoints:
 * - GET /api/livre - Lister tous les livres
 * - GET /api/livre/{id} - Obtenir un livre par ID
 * - POST /api/livre - Créer un ou plusieurs livres
 * - PUT /api/livre/{id} - Modifier un livre
 * - DELETE /api/livre/{id} - Supprimer un livre
 * - GET /api/livre/count - Compter le nombre total de livres
 */
#[Route('/api/livre')]
final class LivreController extends AbstractController
{
    public function __construct(
        private LivreRepository $livreRepository,
        private EntityManagerInterface $entityManager,
        private AuteurRepository $auteurRepository,
        private SerializerInterface $serializer,
        private LivreCreationService $livreCreationService
    )
    {
    }

    /**
     * Lister tous les livres
     * 
     * Endpoint protégé: nécessite au minimum ROLE_USER
     * 
     * Retour:
     * - 200 (OK): Tableau de livres
     * - 401 (UNAUTHORIZED): Pas de JWT ou JWT invalide
     */
    #[OA\Get(
        path: '/api/livre',
        summary: 'Lister tous les livres',
        description: 'Récupère la liste de tous les livres disponibles',
        tags: ['Livre'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des livres',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            'id' => new OA\Property(property: 'id', type: 'integer', example: 1),
                            'titre' => new OA\Property(property: 'titre', type: 'string', example: 'Le Seigneur des Anneaux'),
                            'annee' => new OA\Property(property: 'annee', type: 'integer', example: 1954),
                            'auteur' => new OA\Property(property: 'auteur', type: 'object')
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    #[Route('', name: 'get_all_livres', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get_all_livres(): JsonResponse
    {
        $livres = $this->livreRepository->findAll();
        $data = [];

        foreach ($livres as $livre) {
            $data[] = [
                'id' => $livre->getId(),
                'titre' => $livre->getTitre(),
                'annee' => $livre->getAnnee(),
                'auteur' => $livre->getAuteur(),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Compter le nombre total de livres
     * 
     * Endpoint protégé: nécessite au minimum ROLE_USER
     * 
     * Retour:
     * - 200 (OK): Nombre de livres
     * - 401 (UNAUTHORIZED): Pas de JWT ou JWT invalide
     */
    #[OA\Get(
        path: '/api/livre/count',
        summary: 'Compter les livres',
        description: 'Retourne le nombre total de livres',
        tags: ['Livre'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Nombre de livres',
                content: new OA\JsonContent(
                    properties: [
                        'count' => new OA\Property(property: 'count', type: 'integer', example: 42)
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    #[Route('/count', name: 'count_livres', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function count_livres(): JsonResponse
    {
        $count = $this->livreRepository->count([]);
        return new JsonResponse(['count' => $count]);
    }

    /**
     * Obtenir un livre par ID
     * 
     * Endpoint protégé: nécessite au minimum ROLE_USER
     * 
     * Paramètres:
     * - id: ID du livre dans la base de données
     * 
     * Retour:
     * - 200 (OK): Objet livre
     * - 401 (UNAUTHORIZED): Pas de JWT ou JWT invalide
     * - 404 (NOT_FOUND): Livre introuvable
     */
    #[OA\Get(
        path: '/api/livre/{id}',
        summary: 'Obtenir un livre par ID',
        description: 'Récupère les détails d\'un livre spécifique',
        tags: ['Livre'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du livre',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détails du livre',
                content: new OA\JsonContent(
                    properties: [
                        'id' => new OA\Property(property: 'id', type: 'integer', example: 1),
                        'titre' => new OA\Property(property: 'titre', type: 'string', example: 'Le Seigneur des Anneaux'),
                        'annee' => new OA\Property(property: 'annee', type: 'integer', example: 1954),
                        'auteur' => new OA\Property(property: 'auteur', type: 'object')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Livre non trouvé')
        ]
    )]
    #[Route('/{id}', name: 'get_a_single_livre', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function get_a_single_livre(int $id): JsonResponse
    {
        $livre = $this->livreRepository->find($id);
        if (!$livre) {
            return new JsonResponse(['message' => 'Livre non trouvé'], 404);
        }
        $data = [
            'id' => $livre->getId(),
            'titre' => $livre->getTitre(),
            'annee' => $livre->getAnnee(),
            'auteur' => $livre->getAuteur(),
        ];

        return new JsonResponse($data);
    }

    /**
     * Créer un ou plusieurs livres
     * 
     * Endpoint protégé: nécessite au minimum ROLE_USER
     * Peut accepter un seul objet ou un tableau d'objets
     * 
     * Données requises:
     * - titre: Titre du livre (string)
     * - annee: Année de publication (integer)
     * - auteur: Objet auteur avec id, nom et prénom
     * 
     * Retour:
     * - 201 (CREATED): Livres créés avec succès
     * - 400 (BAD_REQUEST): Données invalides
     * - 401 (UNAUTHORIZED): Pas de JWT ou JWT invalide
     */
    #[OA\Post(
        path: '/api/livre',
        summary: 'Créer un ou plusieurs livres',
        description: 'Crée un nouveau livre ou plusieurs livres à la fois',
        tags: ['Livre'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                oneOf: [
                    new OA\Schema(
                        properties: [
                            'titre' => new OA\Property(property: 'titre', type: 'string', example: 'Harry Potter'),
                            'annee' => new OA\Property(property: 'annee', type: 'integer', example: 1997),
                            'auteur' => new OA\Property(property: 'auteur', type: 'object')
                        ],
                        type: 'object'
                    ),
                    new OA\Schema(
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                'titre' => new OA\Property(property: 'titre', type: 'string'),
                                'annee' => new OA\Property(property: 'annee', type: 'integer'),
                                'auteur' => new OA\Property(property: 'auteur', type: 'object')
                            ]
                        )
                    )
                ]
            )
        ),
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Livres créés avec succès',
                content: new OA\JsonContent(
                    properties: [
                        'response' => new OA\Property(property: 'response', type: 'string', example: '2 Livre(s) créé(s)'),
                        'skipped' => new OA\Property(property: 'skipped', type: 'array', items: new OA\Items(type: 'string'))
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    #[Route('', name: 'push_a_new_livre', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function push_a_new_livre(Request $request): Response
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Gérer à la fois un seul objet et un tableau d'objets
            $livres = is_array($data) && isset($data[0]) ? $data : [$data];
            $createdLivres = [];
            $skippedMessages = [];

            foreach ($livres as $livreData) {
                // Désérialiser vers le modèle (DTO)
                $livreModel = $this->serializer->deserialize(
                    json_encode($livreData),
                    Livre::class,
                    'json'
                );
                
                // Utiliser le service pour créer le livre
                $result = $this->livreCreationService->createFromModel($livreModel);
                
                if ($result['livre']) {
                    $createdLivres[] = $result['livre'];
                } else {
                    $skippedMessages[] = $result['message'];
                }
            }

            $this->entityManager->flush();
            
            $retour = [
                'response' => count($createdLivres) . ' Livre(s) créé(s)'
            ];
            
            if (!empty($skippedMessages)) {
                $retour['skipped'] = $skippedMessages;
            }

            return new JsonResponse($retour, 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
    /**
     * Supprimer un livre
     * 
     * Endpoint protégé: nécessite au minimum ROLE_USER
     * 
     * Paramètres:
     * - id: ID du livre à supprimer
     * 
     * Retour:
     * - 200 (OK): Livre supprimé avec succès
     * - 401 (UNAUTHORIZED): Pas de JWT ou JWT invalide
     * - 404 (NOT_FOUND): Livre non trouvé
     */
    #[OA\Delete(
        path: '/api/livre/{id}',
        summary: 'Supprimer un livre',
        description: 'Supprime un livre existant de la base de données',
        tags: ['Livre'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du livre à supprimer',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Livre supprimé avec succès',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(property: 'message', type: 'string', example: 'Livre Le Seigneur des Anneaux supprimé')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Livre non trouvé')
        ]
    )]
    #[Route('/{id}', name: 'delete_livre', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete_livre(int $id): JsonResponse
    {
        $livre = $this->livreRepository->find($id);
        if (!$livre) {
            return new JsonResponse(['message' => 'Livre not found'], 404);
        }

        $this->entityManager->remove($livre);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Livre ' . $livre->getTitre() . ' supprimé']);
    }

    /**
     * Modifier un livre
     * 
     * Endpoint protégé: nécessite au minimum ROLE_USER
     * 
     * Paramètres:
     * - id: ID du livre à modifier
     * 
     * Données:
     * - titre: Nouveau titre (string)
     * - annee: Nouvelle année (integer)
     * - auteur: Nouvel auteur (object, optionnel)
     * 
     * Retour:
     * - 200 (OK): Livre modifié avec succès
     * - 401 (UNAUTHORIZED): Pas de JWT ou JWT invalide
     * - 404 (NOT_FOUND): Livre non trouvé
     */
    #[OA\Put(
        path: '/api/livre/{id}',
        summary: 'Modifier un livre',
        description: 'Met à jour les informations d\'un livre existant',
        tags: ['Livre'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du livre à modifier',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    'titre' => new OA\Property(property: 'titre', type: 'string', example: 'Titre modifié'),
                    'annee' => new OA\Property(property: 'annee', type: 'integer', example: 2024),
                    'auteur' => new OA\Property(property: 'auteur', type: 'object')
                ],
                type: 'object'
            )
        ),
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Livre modifié avec succès',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(property: 'message', type: 'string', example: 'Livre Titre modifié mis à jour')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Livre non trouvé')
        ]
    )]
    #[Route('/{id}', name: 'update_livre', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update_livre(int $id, Request $request): JsonResponse
    {
        $livre = $this->livreRepository->find($id);
        if (!$livre) {
            return new JsonResponse(['message' => 'Livre non trouvé'], 404);
        }
        $livreModel = $this->serializer->deserialize(
            $request->getContent(),
            Livre::class,
            'json'
        );
        $livre->setTitre($livreModel->getTitre());
        $livre->setAnnee($livreModel->getAnnee());
        // Gérer la mise à jour de l'auteur s'il est fourni
        if ($livreModel->getAuteur()) {
            $auteurModel = $livreModel->getAuteur();
            $auteurEntity = null;
            if ($auteurModel->getId()) {
                $auteurEntity = $this->auteurRepository->find($auteurModel->getId());
            } else {
                $auteurEntity = $this->auteurRepository->findOneBy([
                    'nom' => $auteurModel->getNom(),
                    'prenom' => $auteurModel->getPrenom()
                ]);
            }
            if ($auteurEntity) {
                $livre->setAuteur($auteurEntity);
            }
        }
        $this->entityManager->flush();
        return new JsonResponse(['message' => 'Livre ' . $livre->getTitre() . ' mis à jour']);
    }

 


}