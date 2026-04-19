<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:sync-admin',
    description: 'Creates the bootstrap admin user or resets its password from environment variables.',
)]
class SyncAdminUserCommand extends Command
{
    private const DEFAULT_ADMIN_ROLES = [
        'ROLE_ADMIN',
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%env(string:APP_ADMIN_LOGIN)%')]
        private readonly string $adminLogin,
        #[Autowire('%env(string:APP_ADMIN_PASSWORD)%')]
        private readonly string $adminPassword,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $adminLogin = trim($this->adminLogin);
        $adminPassword = trim($this->adminPassword);

        if ($adminLogin === '') {
            $io->error('Environment variable APP_ADMIN_LOGIN must not be empty.');

            return Command::FAILURE;
        }

        if ($adminPassword === '') {
            $io->error('Environment variable APP_ADMIN_PASSWORD must not be empty.');

            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy(['email' => $adminLogin]);
        $isNewUser = $user === null;

        if ($isNewUser) {
            $user = (new User())
                ->setEmail($adminLogin);
        }

        $user
            ->setRoles(self::DEFAULT_ADMIN_ROLES)
            ->setPassword($this->passwordHasher->hashPassword($user, $adminPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        if ($isNewUser) {
            $io->success(sprintf('Admin user "%s" has been created.', $adminLogin));

            return Command::SUCCESS;
        }

        $io->success(sprintf('Admin user "%s" already existed. Password has been updated.', $adminLogin));

        return Command::SUCCESS;
    }
}
