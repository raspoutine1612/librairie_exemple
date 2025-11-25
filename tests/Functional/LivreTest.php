<?php

namespace App\Tests\Functional;

use App\Entity\Auteur;
use App\Entity\Livre;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LivreTest extends WebTestCase
{
    private string $userToken;
    private ?Auteur $testAuteur = null;

    public function testCreateSingleLivre(): void
    {
        [$client, $userToken, $auteur] = $this->setupTestData();

        $client->request('POST', '/api/livre', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userToken
        ], json_encode([
            'titre' => 'Le Seigneur des Anneaux',
            'annee' => 1954,
            'auteur' => [
                'id' => $auteur->getId(),
                'nom' => $auteur->getNom(),
                'prenom' => $auteur->getPrenom()
            ]
        ]));

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('1 Livre(s) créé(s)', $response['response']);
    }

    public function testCreateMultipleLivres(): void
    {
        [$client, $userToken, $auteur] = $this->setupTestData();

        $client->request('POST', '/api/livre', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userToken
        ], json_encode([
            [
                'titre' => 'Harry Potter à l\'école des sorciers',
                'annee' => 1997,
                'auteur' => [
                    'id' => $auteur->getId(),
                    'nom' => $auteur->getNom(),
                    'prenom' => $auteur->getPrenom()
                ]
            ],
            [
                'titre' => 'Fondation',
                'annee' => 1951,
                'auteur' => [
                    'id' => $auteur->getId(),
                    'nom' => $auteur->getNom(),
                    'prenom' => $auteur->getPrenom()
                ]
            ]
        ]));

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('2 Livre(s) créé(s)', $response['response']);
    }

    public function testGetAllLivres(): void
    {
        [$client, $userToken, $auteur] = $this->setupTestData();
        $this->createTestLivres(3, $auteur);

        $client->request('GET', '/api/livre', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userToken
        ]);

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertCount(3, $response);
    }

    public function testGetLivreById(): void
    {
        [$client, $userToken, $auteur] = $this->setupTestData();
        $livre = $this->createTestLivre('Le Hobbit', 1937, $auteur);
        $client->request('GET', '/api/livre/' . $livre->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userToken
        ]);

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Le Hobbit', $response['titre']);
    }

    public function testGetNonExistentLivre(): void
    {
        [$client, $userToken, $auteur] = $this->setupTestData();
        $client->request('GET', '/api/livre/99999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userToken
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdateLivre(): void
    {
        [$client, $userToken, $auteur] = $this->setupTestData();
        $livre = $this->createTestLivre('Titre original', 2000, $auteur);
        $client->request('PUT', '/api/livre/' . $livre->getId(), [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userToken
        ], json_encode([
            'titre' => 'Titre modifié',
            'annee' => 2024,
            'auteur' => [
                'id' => $auteur->getId(),
                'nom' => $auteur->getNom(),
                'prenom' => $auteur->getPrenom()
            ]
        ]));

        $this->assertResponseStatusCodeSame(200);
    }

    public function testUpdateNonExistentLivre(): void
    {
        [$client, $userToken, $auteur] = $this->setupTestData();
        $client->request('PUT', '/api/livre/99999', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userToken
        ], json_encode([
            'titre' => 'Nouveau titre',
            'annee' => 2024
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteLivre(): void
    {
        [$client, $userToken, $auteur] = $this->setupTestData();
        $livre = $this->createTestLivre('À supprimer', 2000, $auteur);
        $livreId = $livre->getId();
        $client->request('DELETE', '/api/livre/' . $livreId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userToken
        ]);

        $this->assertResponseStatusCodeSame(200);
    }

    public function testDeleteNonExistentLivre(): void
    {
        [$client, $userToken, $auteur] = $this->setupTestData();
        $client->request('DELETE', '/api/livre/99999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userToken
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCountLivres(): void
    {
        [$client, $userToken, $auteur] = $this->setupTestData();
        $this->createTestLivres(5, $auteur);
        $client->request('GET', '/api/livre/count', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $userToken
        ]);

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(5, $response['count']);
    }

    public function testAccessLivreEndpointWithoutToken(): void
    {
        [$client, , ] = $this->setupTestData();
        $client->request('GET', '/api/livre');
        $this->assertResponseStatusCodeSame(401);
    }

    private function setupTestData(): array
    {
        $client = static::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        
        // Nettoyer
        $connection = $entityManager->getConnection();
        $connection->executeStatement('DELETE FROM livre');
        $connection->executeStatement('DELETE FROM auteur');
        $connection->executeStatement('DELETE FROM user');
        
        // Créer un admin
        $admin = new User();
        $admin->setUuid('admin@test.com');
        $admin->setPassword($passwordHasher->hashPassword($admin, 'password'));
        $admin->setRoles(['ROLE_ADMIN']);
        $entityManager->persist($admin);
        $entityManager->flush();

        // Login
        $client->request('POST', '/api/user/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'uuid' => 'admin@test.com',
            'password' => 'password'
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        $token = $response['token'];
        
        // Créer un auteur
        $auteur = new Auteur();
        $auteur->setNom('Tolkien');
        $auteur->setPrenom('J.R.R.');
        $entityManager->persist($auteur);
        $entityManager->flush();
        
        return [$client, $token, $auteur];
    }

    private function createTestLivre(string $titre, int $annee, Auteur $auteur): Livre
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        
        $livre = new Livre();
        $livre->setTitre($titre);
        $livre->setAnnee($annee);
        $livre->setAuteur($auteur);
        
        $entityManager->persist($livre);
        $entityManager->flush();
        
        return $livre;
    }

    private function createTestLivres(int $count, Auteur $auteur): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->createTestLivre('Livre ' . $i, 2000 + $i, $auteur);
        }
    }
}
