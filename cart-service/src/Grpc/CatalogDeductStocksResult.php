<?php

namespace App\Grpc;

final readonly class CatalogDeductStocksResult
{
    /**
     * @param list<CatalogProductDeduction> $products
     */
    public function __construct(
        public string $operationId,
        public array $products,
    ) {
    }
}
