<?php

namespace App\Search\Product\Infrastructure\Input\Console;

use App\Search\Product\Application\Dto\Rebuild\ProductSearchReindexProgress;
use App\Search\Product\Port\Input\ProductSearchRebuildInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: "app:elasticsearch:reindex",
    description: "Rebuilds the Elasticsearch product catalog read-model from PostgreSQL.",
)]
final class ElasticsearchReindexCommand extends Command
{
    public function __construct(
        private readonly ProductSearchRebuildInterface $rebuilder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("Elasticsearch product catalog rebuild");

        $progressBar = new ProgressBar($output, $this->rebuilder->countProducts());
        $progressBar->setFormat(
            " %current%/%max% [%bar%] %percent:3s%% | indexed: %indexed% | failed: %failed% | rate: %rate% docs/s | elapsed: %elapsed:6s%",
        );
        $progressBar->setMessage("0", "indexed");
        $progressBar->setMessage("0", "failed");
        $progressBar->setMessage("0", "rate");
        $progressBar->start();

        try {
            $result = $this->rebuilder->rebuild(
                static function (ProductSearchReindexProgress $progress) use ($progressBar): void {
                    $progressBar->setMessage((string) $progress->indexed, "indexed");
                    $progressBar->setMessage((string) $progress->failed, "failed");
                    $progressBar->setMessage(number_format($progress->getRate(), 0, ".", ""), "rate");
                    $progressBar->setProgress($progress->processed);
                },
            );
        } catch (\Throwable $exception) {
            $progressBar->finish();
            $io->newLine(2);
            $io->error($exception->getMessage());
            $io->note("No successful alias switch was confirmed. Inspect the alias and retained versioned index before retrying.");

            return Command::FAILURE;
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->table(
            ["Index", "Processed", "Indexed", "Failed", "Elapsed", "Alias switched"],
            [[
                $result->indexName,
                $result->processed,
                $result->indexed,
                $result->failed,
                $this->formatDuration($result->elapsedSeconds),
                $result->aliasSwitched ? "yes" : "no",
            ]],
        );

        if (!$result->aliasSwitched) {
            $io->error("Rebuild contains failed documents. The existing alias was left unchanged.");

            return Command::FAILURE;
        }

        $io->success("Product catalog index rebuilt successfully.");

        return Command::SUCCESS;
    }

    private function formatDuration(float $seconds): string
    {
        $seconds = (int) round($seconds);

        return sprintf("%02d:%02d:%02d", intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
