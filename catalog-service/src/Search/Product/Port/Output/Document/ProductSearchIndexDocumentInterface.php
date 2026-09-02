<?php

namespace App\Search\Product\Port\Output\Document;

interface ProductSearchIndexDocumentInterface
{
    public function getId(): int;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
