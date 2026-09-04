<?php

namespace App\Search\Product\Port\Output;

use App\Search\Product\Application\Dto\Indexing\BulkIndexResult;
use App\Search\Product\Port\Output\Document\ProductSearchIndexDocumentInterface;

interface ProductSearchIndexGatewayInterface
{
    public function createRebuildIndex(): string;

    /**
     * @param ProductSearchIndexDocumentInterface[] $documents
     */
    public function bulkIndex(string $indexName, array $documents): BulkIndexResult;

    public function indexInCurrentIndex(ProductSearchIndexDocumentInterface $document): void;

    public function deleteFromCurrentIndex(int $catalogElementId): void;

    public function refresh(string $indexName): void;

    public function count(string $indexName): int;

    public function switchReadAlias(string $targetIndexName): void;
}
