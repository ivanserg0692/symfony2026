<?php

namespace App\Tests\Unit\Search\Product\Infrastructure\Elasticsearch\Bulk;

use App\Search\Product\Infrastructure\Elasticsearch\Bulk\BulkResponse;
use PHPUnit\Framework\TestCase;

final class BulkResponseTest extends TestCase
{
    public function testCreatesTypedItemsFromBulkApiResponse(): void
    {
        $response = BulkResponse::fromArray([
            "items" => [
                ["index" => ["status" => 201]],
                ["index" => [
                    "status" => 400,
                    "error" => ["type" => "mapper_parsing_exception", "reason" => "bad field"],
                ]],
                "malformed item",
            ],
        ]);

        self::assertCount(3, $response->items);
        self::assertSame(201, $response->items[0]->status);
        self::assertTrue($response->items[0]->successful);
        self::assertNull($response->items[0]->error);
        self::assertSame(400, $response->items[1]->status);
        self::assertFalse($response->items[1]->successful);
        self::assertStringContainsString("mapper_parsing_exception", $response->items[1]->error);
        self::assertSame(0, $response->items[2]->status);
        self::assertFalse($response->items[2]->successful);
        self::assertSame("Missing or malformed Bulk API item response.", $response->items[2]->error);
    }

    public function testTreatsMissingItemsAsEmptyResponse(): void
    {
        $response = BulkResponse::fromArray([]);

        self::assertSame([], $response->items);
    }
}
