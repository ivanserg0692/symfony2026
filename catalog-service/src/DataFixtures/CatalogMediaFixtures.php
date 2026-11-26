<?php

namespace App\DataFixtures;

use App\DataFixtures\Media\FixtureMediaImporter;
use App\DataFixtures\Media\FixtureMediaLibrary;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireInline;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

final class CatalogMediaFixtures extends Fixture implements OrderedFixtureInterface
{
    /** @param \Closure(): FixtureMediaImporter $importerProvider */
    public function __construct(
        private readonly FixtureMediaLibrary $library,
        #[AutowireServiceClosure(FixtureMediaImporter::class)] private readonly \Closure $importerProvider,
        #[AutowireInline(ArgvInput::class)] private readonly InputInterface $input,
    ) {
    }

    public function getOrder(): int
    {
        return 5;
    }

    public function load(ObjectManager $manager): void
    {
        foreach (AppFixtures::mediaCategorySlugs() as $slug) {
            $this->library->forCategory($slug, 1);
        }
        // Doctrine's dry-run rolls back SQL, but cannot roll back S3 writes.
        if ($this->input->hasParameterOption('--dry-run', true)) {
            $this->library->validateFiles();
            return;
        }
        ($this->importerProvider)()->import();
    }
}
