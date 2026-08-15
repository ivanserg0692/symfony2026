<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserGroups;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Эти пользователи используются исключительно для нагрузочного тестирования (k6).
 *
 * Соответствие Virtual Users:
 *
 * VU 1   → load-user-001@test.local
 * VU 2   → load-user-002@test.local
 * ...
 * VU 100 → load-user-100@test.local
 *
 * Это позволяет каждому Virtual User использовать собственную корзину, собственные заказы
 * и собственную пользовательскую сессию без конфликтов.
 */
class LoadTestUsersFixture extends Fixture implements DependentFixtureInterface
{
    private const USER_COUNT = 100;
    private const EMAIL_TEMPLATE = 'load-user-%03d@test.local';
    private const MAX_GROUPS_PER_USER = 3;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%env(string:LOAD_TEST_USER_PASSWORD)%')]
        private readonly string $loadTestUserPassword,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $plainPassword = trim($this->loadTestUserPassword);

        if ($plainPassword === '') {
            throw new \RuntimeException('Environment variable LOAD_TEST_USER_PASSWORD must not be empty.');
        }

        $faker = Factory::create('en_US');
        $faker->seed(1000);

        $groups = $this->getUserGroups();

        for ($i = 1; $i <= self::USER_COUNT; ++$i) {
            $email = sprintf(self::EMAIL_TEMPLATE, $i);
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if ($user === null) {
                $user = new User();
                $user->setEmail($email);
            }

            $user
                ->setFirstName($faker->firstName())
                ->setSecondName($faker->lastName())
                ->setRoles($this->generateRoles($i))
                ->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

            $this->syncGroups($user, $this->getGroupsForUser($i, $groups));

            $manager->persist($user);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserGroupsFixtures::class,
        ];
    }

    private function generateRoles(int $userNumber): array
    {
        return match ((($userNumber - 1) % 100) + 1) {
            default => [],
            1, 2, 3, 4, 5 => ['ROLE_ADMIN'],
            6, 7, 8, 9, 10, 11, 12, 13, 14, 15 => ['ROLE_EDITOR'],
        };
    }

    /**
     * @param list<UserGroups> $groups
     *
     * @return list<UserGroups>
     */
    private function getGroupsForUser(int $userNumber, array $groups): array
    {
        $groupsCount = (($userNumber - 1) % self::MAX_GROUPS_PER_USER) + 1;
        $startIndex = ($userNumber - 1) % count($groups);
        $userGroups = [];

        for ($offset = 0; $offset < $groupsCount; ++$offset) {
            $userGroups[] = $groups[($startIndex + $offset) % count($groups)];
        }

        return $userGroups;
    }

    /**
     * @return list<UserGroups>
     */
    private function getUserGroups(): array
    {
        $groups = [];

        foreach (array_keys(UserGroupsFixtures::GROUPS) as $index) {
            $groups[] = $this->getReference(
                UserGroupsFixtures::GROUP_REFERENCE_PREFIX.$index,
                UserGroups::class,
            );
        }

        return $groups;
    }

    /**
     * @param list<UserGroups> $defaultGroups
     */
    private function syncGroups(User $user, array $defaultGroups): void
    {
        foreach ($user->getGroups() as $group) {
            if (!in_array($group, $defaultGroups, true)) {
                $user->removeGroup($group);
            }
        }

        foreach ($defaultGroups as $group) {
            $user->addGroup($group);
        }
    }
}
