<?php

namespace App\Tests\Unit\Search\Product\Infrastructure\Elasticsearch\Bulk;

use App\Search\Product\Infrastructure\Elasticsearch\Bulk\BulkResponse;
use App\Search\Product\Infrastructure\Elasticsearch\Bulk\BulkResponseParser;
use PHPUnit\Framework\TestCase;

final class BulkResponseParserTest extends TestCase
{
    public function testReportsEveryFailedOrMissingDocument(): void
    {
        $response = BulkResponse::fromArray([
            "errors" => true,
            "items" => [
                ["index" => ["_id" => "10", "status" => 201]],
                ["index" => [
                    "_id" => "11",
                    "status" => 400,
                    "error" => ["type" => "mapper_parsing_exception", "reason" => "bad field"],
                ]],
            ],
        ]);

        $result = (new BulkResponseParser())->parse($response, [10, 11, 12]);

        self::assertSame(1, $result->successful);
        self::assertSame(2, $result->getFailedCount());
        self::assertSame(11, $result->failures[0]->productId);
        self::assertStringContainsString("mapper_parsing_exception", $result->failures[0]->error);
        self::assertSame(12, $result->failures[1]->productId);
        self::assertStringContainsString("Missing", $result->failures[1]->error);
    }
}
