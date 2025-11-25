<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:jwt:generate',
    description: 'Générer un token JWT pour un utilisateur ou créer un nouvel utilisateur et générer un token'
)]
class JwtGenerateCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private JwtService $jwtService,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('uuid', InputArgument::OPTIONAL, 'UUID de l\'utilisateur')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe de l\'utilisateur (requis si création)')
            ->addOption('create', 'c', InputOption::VALUE_NONE, 'Créer l\'utilisateur s\'il n\'existe pas')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED, 'Rôle de l\'utilisateur (ROLE_USER ou ROLE_ADMIN)', 'ROLE_USER');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $uuid = $input->getArgument('uuid');
        $password = $input->getArgument('password');
        $shouldCreate = $input->getOption('create');
        $role = $input->getOption('role');

        // Valider le rôle
        if (!in_array($role, ['ROLE_USER', 'ROLE_ADMIN'])) {
            $io->error('Le rôle doit être ROLE_USER ou ROLE_ADMIN');
            return Command::FAILURE;
        }

        if (!$uuid) {
            $io->error('L\'UUID est requis');
            return Command::FAILURE;
        }

        // Trouver l'utilisateur existant
        $user = $this->userRepository->findOneBy(['uuid' => $uuid]);

        if (!$user && !$shouldCreate) {
            $io->error("L'utilisateur avec l'UUID '$uuid' n'existe pas.");
            $io->text('Utilisez l\'option --create pour créer un nouvel utilisateur');
            $io->text('Exemple: <fg=cyan>php bin/console app:jwt:generate john secret123 --create --role=ROLE_ADMIN</>');
            return Command::FAILURE;
        }

        // Créer l'utilisateur s'il n'existe pas
        if (!$user) {
            if (!$password) {
                $io->error('Le mot de passe est requis pour créer un nouvel utilisateur');
                return Command::FAILURE;
            }

            $user = new User();
            $user->setUuid($uuid);
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            $user->setRoles([$role]);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success("Utilisateur créé avec succès!");
            $io->text("UUID: <fg=green>$uuid</>");
            $io->text("Rôle: <fg=green>$role</>");
        }

        // Générer le token JWT
        $token = $this->jwtService->generateToken($user);
        $expirationTime = $this->jwtService->getExpirationTime();

        // Afficher le résultat
        $io->success('Token JWT généré avec succès!');
        $io->newLine();

        $io->section('Détails du Token');
        $io->table(
            ['Propriété', 'Valeur'],
            [
                ['UUID', $user->getUuid()],
                ['ID', $user->getId()],
                ['Rôles', implode(', ', $user->getRoles())],
                ['Expire dans (secondes)', $expirationTime],
                ['Expire dans (minutes)', round($expirationTime / 60)],
            ]
        );

        $io->newLine();
        $io->section('Token JWT');
        $io->text($token);

        $io->newLine();
        $io->section('Utilisation');
        $io->text('Utilisez ce token dans l\'en-tête Authorization de vos requêtes:');
        $io->text('');
        $io->text('<fg=cyan>Authorization: Bearer ' . $token . '</>');
        $io->text('');
        $io->text('Exemple avec curl:');
        $io->text('<fg=cyan>curl -H "Authorization: Bearer ' . $token . '" http://localhost/api/livre</>');

        return Command::SUCCESS;
    }
}

