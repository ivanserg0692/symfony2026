<?php

namespace App\DataFixtures;

use App\Entity\NewsStatus;
use App\Enum\NewsStatusCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class NewsStatusFixtures extends Fixture
{
    public const STATUS_REFERENCE_PREFIX = 'news_status_';

    private const STATUSES = [
        ['code' => NewsStatusCode::PUBLIC, 'name' => 'Public'],
        ['code' => NewsStatusCode::INTERNAL, 'name' => 'Internal'],
        ['code' => NewsStatusCode::ON_MODERATION, 'name' => 'On moderation'],
        ['code' => NewsStatusCode::MODERATION_REJECTED, 'name' => 'Moderation rejected'],
        ['code' => NewsStatusCode::DRAFTED, 'name' => 'Drafted'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::STATUSES as $index => $statusData) {
            $status = new NewsStatus();
            $status
                ->setCode($statusData['code'])
                ->setName($statusData['name']);

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
