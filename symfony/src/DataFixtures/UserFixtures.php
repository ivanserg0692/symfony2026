<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public const USER_REFERENCE_PREFIX = 'user_';

    private const USERS_COUNT = 500;
    private const DEFAULT_PASSWORD = 'password123';
    private const MAX_GROUPS_PER_USER = 3;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('en_US');

        for ($i = 0; $i < self::USERS_COUNT; ++$i) {
            $user = new User();
            $user->setEmail($faker->unique()->safeEmail());
            $user->setFirstName($faker->firstName());
            $user->setSecondName($faker->lastName());
            $user->setRoles($this->generateRoles($faker));
            $user->setPassword($this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD));
            $this->assignRandomGroups($user, $faker);

            $manager->persist($user);
            $this->addReference(self::USER_REFERENCE_PREFIX.$i, $user);
        }

        $manager->flush();
    }

    public static function getUsersCount(): int
    {
        return self::USERS_COUNT;
    }

    public function getDependencies(): array
    {
        return [
            UserGroupsFixtures::class,
        ];
    }

    private function generateRoles(Generator $faker): array
    {
        return match ($faker->numberBetween(1, 100)) {
            default => [],
            1, 2, 3, 4, 5 => ['ROLE_ADMIN'],
            6, 7, 8, 9, 10, 11, 12, 13, 14, 15 => ['ROLE_EDITOR'],
        };
    }

    private function assignRandomGroups(User $user, Generator $faker): void
    {
        $groupIndexes = range(0, count(UserGroupsFixtures::GROUPS) - 1);
        shuffle($groupIndexes);

        $groupsCount = $faker->numberBetween(1, min(self::MAX_GROUPS_PER_USER, count($groupIndexes)));

        foreach (array_slice($groupIndexes, 0, $groupsCount) as $groupIndex) {
            /** @var \App\Entity\UserGroups $group */
            $group = $this->getReference(UserGroupsFixtures::GROUP_REFERENCE_PREFIX.$groupIndex, \App\Entity\UserGroups::class);
            $user->addGroup($group);
        }
    }
}
