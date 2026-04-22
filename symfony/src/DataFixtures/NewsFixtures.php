<?php

namespace App\DataFixtures;

use App\Entity\News;
use App\Entity\NewsStatuses;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class NewsFixtures extends Fixture implements DependentFixtureInterface
{
    private const NEWS_COUNT = 1020;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('en_US');

        for ($i = 0; $i < self::NEWS_COUNT; ++$i) {
            $title = ucfirst($faker->unique()->words($faker->numberBetween(3, 6), true));
            $createdAt = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-90 days', '-1 day'));
            $updatedAt = $createdAt->modify(sprintf('+%d hours', $faker->numberBetween(1, 72)));

            /** @var User $author */
            $author = $this->getReference(
                UserFixtures::USER_REFERENCE_PREFIX.$faker->numberBetween(0, UserFixtures::getUsersCount() - 1),
                User::class,
            );
            $status = $this->getReference(
                NewsStatusesFixtures::STATUS_REFERENCE_PREFIX.$faker->numberBetween(0, NewsStatusesFixtures::getStatusesCount() - 1),
                NewsStatuses::class,
            );

            $news = new News();
            $news->setName($title);
            $news->setSlug($faker->unique()->slug(4));
            $news->setCreatedAt($createdAt);
            $news->setUpdatedAt($updatedAt);
            $news->setCreatedBy($author);
            $news->setStatus($status);
            $news->setBrief($faker->sentence($faker->numberBetween(12, 20)));
            $news->setDescription($faker->paragraphs($faker->numberBetween(2, 4), true));

            $manager->persist($news);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            NewsStatusesFixtures::class,
            UserFixtures::class,
        ];
    }
}
