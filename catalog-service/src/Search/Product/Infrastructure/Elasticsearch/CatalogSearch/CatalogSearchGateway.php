<?php

namespace App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch;

use Elastic\Elasticsearch\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Owns the Elasticsearch read transport for the catalog.
 *
 * Query construction remains in CatalogSearchQueryBuilder, while this class
 * hides client calls and transport-specific response normalization from the
 * CatalogRead adapter.
 */
final readonly class CatalogSearchGateway
{
    public function __construct(
        #[Autowire(service: "app.catalog_read.elasticsearch_client")]
        private Client $client,
        private string $productSearchIndexAlias,
    ) {
    }

    /** @param array<string, mixed> $body */
    public function search(array $body): CatalogSearchResponse
    {
        return $this->executeSearch($body, $this->productSearchIndexAlias);
    }

    /** @param array<string, mixed> $body */
    public function searchPointInTime(array $body): CatalogSearchResponse
    {
        return $this->executeSearch($body);
    }

    /** @param array<string, mixed> $body */
    private function executeSearch(array $body, ?string $index = null): CatalogSearchResponse
    {
        $parameters = ["body" => $body];
        if ($index !== null) {
            $parameters["index"] = $index;
        }

        return CatalogSearchResponse::fromArray(
            $this->client->search($parameters + ["allow_partial_search_results" => false])->asArray(),
        );
    }

    public function findById(int $id): ?CatalogSearchHit
    {
        $response = $this->client->mget([
            "index" => $this->productSearchIndexAlias,
            "body" => ["ids" => [(string) $id]],
        ])->asArray();
        $document = $response["docs"][0] ?? [];
        if (isset($document["error"])) {
            throw new \RuntimeException("Elasticsearch catalog document could not be read.");
        }

        return ($document["found"] ?? false) ? CatalogSearchHit::fromArray($document) : null;
    }

    public function openPointInTime(): string
    {
        return (string) $this->client->openPointInTime([
            "index" => $this->productSearchIndexAlias,
            "keep_alive" => "1m",
            "allow_partial_search_results" => false,
        ])->asArray()["id"];
    }

    public function closePointInTime(string $pit): void
    {
        $this->client->closePointInTime(["body" => ["id" => $pit]]);
    }
}
