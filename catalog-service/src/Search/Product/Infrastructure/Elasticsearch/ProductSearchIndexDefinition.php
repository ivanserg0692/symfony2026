<?php

namespace App\Search\Product\Infrastructure\Elasticsearch;

final class ProductSearchIndexDefinition
{
    public const int SCHEMA_VERSION = 2;

    public function __construct(
        private readonly int $productSearchResultWindow,
    ) {
        if ($this->productSearchResultWindow < 1) {
            throw new \InvalidArgumentException("PRODUCT_SEARCH_RESULT_WINDOW must be greater than zero.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return [
            "settings" => [
                "index" => [
                    "max_result_window" => $this->productSearchResultWindow,
                ],
            ],
            "mappings" => [
                "dynamic" => "strict",
                "properties" => [
                    "schema_version" => ["type" => "integer"],
                    "id" => ["type" => "long"],
                    "product_id" => ["type" => "long"],
                    "name" => [
                        "type" => "text",
                        "fields" => ["keyword" => ["type" => "keyword", "ignore_above" => 256]],
                    ],
                    "slug" => ["type" => "keyword"],
                    "description" => ["type" => "text"],
                    "picture_id" => ["type" => "keyword"],
                    "active" => ["type" => "boolean"],
                    "sort" => ["type" => "integer"],
                    "created_at" => ["type" => "date"],
                    "section_ids" => ["type" => "long"],
                    "sections" => [
                        "type" => "nested",
                        "properties" => [
                            "id" => ["type" => "long"],
                            "name" => ["type" => "text"],
                            "slug" => ["type" => "keyword"],
                            "active" => ["type" => "boolean"],
                            "parent_id" => ["type" => "long"],
                            "description" => ["type" => "text"],
                            "picture_id" => ["type" => "keyword"],
                            "level" => ["type" => "integer"],
                            "sort" => ["type" => "integer"],
                        ],
                    ],
                    "prices" => [
                        "type" => "nested",
                        "properties" => [
                            "id" => ["type" => "long"],
                            "type_id" => ["type" => "long"],
                            "type_code" => ["type" => "keyword"],
                            "type_name" => ["type" => "keyword"],
                            "type_active" => ["type" => "boolean"],
                            "type_sort" => ["type" => "integer"],
                            "type_description" => ["type" => "text"],
                            "amount" => ["type" => "long"],
                            "currency" => ["type" => "keyword"],
                            "active" => ["type" => "boolean"],
                            "valid_from" => ["type" => "date"],
                            "valid_to" => ["type" => "date"],
                        ],
                    ],
                    "total_stock" => ["type" => "long"],
                    "available" => ["type" => "boolean"],
                    "stocks" => [
                        "type" => "nested",
                        "properties" => [
                            "store_id" => ["type" => "long"],
                            "store_name" => ["type" => "text"],
                            "store_slug" => ["type" => "keyword"],
                            "store_active" => ["type" => "boolean"],
                            "quantity" => ["type" => "long"],
                        ],
                    ],
                ],
            ],
        ];
    }
}
