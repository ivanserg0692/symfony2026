<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const USER_REFERENCE_PREFIX = 'user_';

    private const USERS_COUNT = 500;
    private const DEFAULT_PASSWORD = 'password123';

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

            $manager->persist($user);
            $this->addReference(self::USER_REFERENCE_PREFIX.$i, $user);
        }

        $manager->flush();
    }

    public static function getUsersCount(): int
    {
        return self::USERS_COUNT;
    }

    private function generateRoles(Generator $faker): array
    {
        return match ($faker->numberBetween(1, 100)) {
            default => [],
            1, 2, 3, 4, 5 => ['ROLE_ADMIN'],
            6, 7, 8, 9, 10, 11, 12, 13, 14, 15 => ['ROLE_EDITOR'],
        };
    }
}
