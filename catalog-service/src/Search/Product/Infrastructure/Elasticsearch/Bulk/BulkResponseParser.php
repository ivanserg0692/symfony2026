<?php

namespace App\Search\Product\Infrastructure\Elasticsearch\Bulk;

use App\Search\Product\Application\Dto\Indexing\BulkIndexFailure;
use App\Search\Product\Application\Dto\Indexing\BulkIndexResult;

final class BulkResponseParser
{
    /**
     * @param int[] $productIds
     */
    public function parse(BulkResponse $response, array $productIds): BulkIndexResult
    {
        $successful = 0;
        $failures = [];

        foreach ($productIds as $offset => $productId) {
            $item = $response->items[$offset] ?? null;

            if ($item === null) {
                $failures[] = new BulkIndexFailure($productId, "Missing or malformed Bulk API item response.");
                continue;
            }

            if ($item->successful) {
                ++$successful;
                continue;
            }

            $failures[] = new BulkIndexFailure(
                $productId,
                $item->error ?? "Bulk operation returned an unknown error.",
            );
        }

        return new BulkIndexResult($successful, $failures);
    }
}
