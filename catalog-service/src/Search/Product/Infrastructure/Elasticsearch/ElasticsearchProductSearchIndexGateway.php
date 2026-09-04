<?php

namespace App\Search\Product\Infrastructure\Elasticsearch;

use App\Search\Product\Application\Dto\Indexing\BulkIndexResult;
use App\Search\Product\Infrastructure\Elasticsearch\Bulk\BulkResponse;
use App\Search\Product\Infrastructure\Elasticsearch\Bulk\BulkResponseParser;
use App\Search\Product\Port\Output\Document\ProductSearchIndexDocumentInterface;
use App\Search\Product\Port\Output\ProductSearchIndexGatewayInterface;
use Elastic\Elasticsearch\Client;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\AutowireInline;

#[AsAlias(ProductSearchIndexGatewayInterface::class)]
final readonly class ElasticsearchProductSearchIndexGateway implements ProductSearchIndexGatewayInterface
{
    public function __construct(
        #[AutowireInline(
            class: ["@".ElasticsearchClientFactory::class, "create"],
        )]
        private Client $client,
        private BulkResponseParser $bulkResponseParser,
        private ProductSearchIndexDefinition $indexDefinition,
        private string $productSearchIndexPrefix,
        private string $productSearchIndexAlias,
    ) {
    }

    public function createRebuildIndex(): string
    {
        $indexName = sprintf(
            "%s-v%d-%s-%s",
            $this->productSearchIndexPrefix,
            ProductSearchIndexDefinition::SCHEMA_VERSION,
            gmdate("YmdHis"),
            bin2hex(random_bytes(3)),
        );

        $this->client->indices()->create([
            "index" => $indexName,
            "body" => $this->indexDefinition->getConfiguration(),
        ]);

        return $indexName;
    }

    /**
     * @param ProductSearchIndexDocumentInterface[] $documents
     */
    public function bulkIndex(string $indexName, array $documents): BulkIndexResult
    {
        $body = [];
        $productIds = [];

        foreach ($documents as $document) {
            $productIds[] = $document->getId();
            $body[] = ["index" => ["_index" => $indexName, "_id" => (string) $document->getId()]];
            $body[] = ["schema_version" => ProductSearchIndexDefinition::SCHEMA_VERSION] + $document->toArray();
        }

        if ($body === []) {
            return new BulkIndexResult(0, []);
        }

        $response = BulkResponse::fromArray(
            $this->client->bulk(["body" => $body])->asArray(),
        );

        return $this->bulkResponseParser->parse($response, $productIds);
    }

    public function indexInCurrentIndex(ProductSearchIndexDocumentInterface $document): void
    {
        $result = $this->bulkIndex($this->productSearchIndexAlias, [$document]);

        if ($result->getFailedCount() > 0) {
            throw new \RuntimeException(sprintf(
                "Elasticsearch incremental indexing failed for catalog element %d: %s",
                $document->getId(),
                $result->failures[0]->error,
            ));
        }
    }

    public function deleteFromCurrentIndex(int $catalogElementId): void
    {
        $response = $this->client->bulk([
            "body" => [[
                "delete" => [
                    "_index" => $this->productSearchIndexAlias,
                    "_id" => (string) $catalogElementId,
                ],
            ]],
        ])->asArray();

        $deleteResult = $response["items"][0]["delete"] ?? null;
        $status = is_array($deleteResult) ? (int) ($deleteResult["status"] ?? 500) : 500;
        $documentWasAlreadyMissing = $status === 404 && ($deleteResult["result"] ?? null) === "not_found";

        if ($status >= 300 && !$documentWasAlreadyMissing) {
            $error = is_array($deleteResult) ? $deleteResult["error"] ?? "unknown error" : "malformed Bulk API response";

            throw new \RuntimeException(sprintf(
                "Elasticsearch incremental deletion failed for catalog element %d: %s",
                $catalogElementId,
                is_string($error) ? $error : json_encode($error, JSON_THROW_ON_ERROR),
            ));
        }
    }

    public function refresh(string $indexName): void
    {
        $this->client->indices()->refresh(["index" => $indexName]);
    }

    public function count(string $indexName): int
    {
        return (int) $this->client->count(["index" => $indexName])["count"];
    }

    public function switchReadAlias(string $targetIndexName): void
    {
        $actions = [];

        if ($this->client->indices()->existsAlias(["name" => $this->productSearchIndexAlias])->asBool()) {
            $aliases = $this->client->indices()->getAlias(["name" => $this->productSearchIndexAlias])->asArray();
            foreach (array_keys($aliases) as $indexName) {
                $actions[] = ["remove" => ["index" => $indexName, "alias" => $this->productSearchIndexAlias]];
            }
        }

        $actions[] = [
            "add" => [
                "index" => $targetIndexName,
                "alias" => $this->productSearchIndexAlias,
                "is_write_index" => true,
            ],
        ];

        $this->client->indices()->updateAliases(["body" => ["actions" => $actions]]);
    }
}
