<?php

namespace App\Service;

use App\Entity\Livre as LivreEntity;
use App\Entity\Auteur as AuteurEntity;
use App\Model\Livre;
use App\Repository\LivreRepository;
use App\Repository\AuteurRepository;
use Doctrine\ORM\EntityManagerInterface;

class LivreCreationService
{
    public function __construct(
        private LivreRepository $livreRepository,
        private AuteurRepository $auteurRepository,
        private EntityManagerInterface $entityManager
    )
    {
    }

    /**
     * Créer un livre à partir d'un modèle, en gérant la résolution de l'auteur et la détection des doublons
     * 
     * @return array{livre: LivreEntity|null, message: string}
     */
    public function createFromModel(Livre $livreModel): array
    {
        // Résoudre l'auteur DABORD pour obtenir l'entité auteur réelle et l'ID
        $auteurEntity = null;
        $auteurId = null;
        
        if ($livreModel->getAuteur()) {
            $auteurModel = $livreModel->getAuteur();
            
            // Si l'auteur a un ID, essayer de le récupérer de la base de données
            if ($auteurModel->getId()) {
                $auteurEntity = $this->auteurRepository->find($auteurModel->getId());
                if (!$auteurEntity) {
                    return [
                        'livre' => null,
                        'message' => 'Auteur avec l\'id ' . $auteurModel->getId() . ' non trouvé'
                    ];
                }
                $auteurId = $auteurEntity->getId();
            } else {
                // Sinon, rechercher par nom et prénom
                $auteurEntity = $this->auteurRepository->findOneBy([
                    'nom' => $auteurModel->getNom(),
                    'prenom' => $auteurModel->getPrenom()
                ]);
                if ($auteurEntity) {
                    $auteurId = $auteurEntity->getId();
                }
                // S'il n'est pas trouvé, on le créera plus tard, mais auteurId reste null pour l'instant
            }
        }
        
        // Vérifier les doublons : même titre, année ET auteur
        $existingLivre = $this->livreRepository->findOneBy([
            'titre' => $livreModel->getTitre(),
            'annee' => $livreModel->getAnnee(),
            'auteur' => $auteurId
        ]);
        if ($existingLivre) {
            return [
                'livre' => null,
                'message' => 'Le livre ' . $livreModel->getTitre() . ' (' . $livreModel->getAnnee() . ') par cet auteur existait déjà'
            ];
        }
        
        // Convertir le modèle en entité
        $livreEntity = new LivreEntity();
        $livreEntity->setTitre($livreModel->getTitre());
        $livreEntity->setAnnee($livreModel->getAnnee());

        // Si l'auteur n'existe pas encore, le créer maintenant
        if ($livreModel->getAuteur() && !$auteurEntity) {
            $auteurModel = $livreModel->getAuteur();
            $auteurEntity = new AuteurEntity();
            $auteurEntity->setNom($auteurModel->getNom());
            $auteurEntity->setPrenom($auteurModel->getPrenom());
            $this->entityManager->persist($auteurEntity);
        }
        
        if ($auteurEntity) {
            $livreEntity->setAuteur($auteurEntity);
        }

        $this->entityManager->persist($livreEntity);

        return [
            'livre' => $livreEntity,
            'message' => ''
        ];
    }
}
