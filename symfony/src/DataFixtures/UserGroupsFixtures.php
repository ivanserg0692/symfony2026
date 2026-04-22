<?php

namespace App\DataFixtures;

use App\Entity\UserGroups;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserGroupsFixtures extends Fixture
{
    public const GROUP_REFERENCE_PREFIX = 'group_';

    /**
     * @var array<int, array{name: string, isAdmin: bool}>
     */
    public const GROUPS = [
        ['name' => 'admin', 'isAdmin' => true],
        ['name' => 'editors', 'isAdmin' => false],
        ['name' => 'authors', 'isAdmin' => false],
        ['name' => 'managers', 'isAdmin' => false],
        ['name' => 'guests', 'isAdmin' => false],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::GROUPS as $index => $groupData) {
            $group = new UserGroups();
            $group->setName($groupData['name']);
            $group->setIsAdmin($groupData['isAdmin']);

            $manager->persist($group);
            $this->addReference(self::GROUP_REFERENCE_PREFIX.$index, $group);
        }

        $manager->flush();
    }
}
