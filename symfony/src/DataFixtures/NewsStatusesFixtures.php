<?php

namespace App\DataFixtures;

use App\Entity\NewsStatuses;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class NewsStatusesFixtures extends Fixture
{
    public const STATUS_REFERENCE_PREFIX = 'news_status_';

    private const STATUSES = [
        'public',
        'internal',
        'on moderation',
        'drafted',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::STATUSES as $index => $name) {
            $status = new NewsStatuses();
            $status->setName($name);

            $manager->persist($status);
            $this->addReference(self::STATUS_REFERENCE_PREFIX.$index, $status);
        }

        $manager->flush();
    }

    public static function getStatusesCount(): int
    {
        return \count(self::STATUSES);
    }
}
