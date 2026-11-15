<?php

namespace App\Command;

use App\DataFixtures\Media\FixtureMediaLibrary;
use App\DataFixtures\Media\FixtureMediaImporter;
use App\DataFixtures\AppFixtures;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'catalog:media:import', description: 'Validate and import the shared fixture photo library into private MinIO buckets.')]
final class CatalogMediaImportCommand extends Command
{
    public function __construct(private readonly FixtureMediaLibrary $library, private readonly FixtureMediaImporter $importer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (AppFixtures::mediaCategorySlugs() as $slug) {
            $this->library->forCategory($slug, 1);
        }
        $output->writeln(sprintf('Catalog media ready: %d objects uploaded.', $this->importer->import()));
        return Command::SUCCESS;
    }
}
