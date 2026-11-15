<?php

namespace App\Tests\Unit\Search\Product\Infrastructure\Elasticsearch;

use App\Search\Product\Infrastructure\Elasticsearch\ProductSearchIndexDefinition;
use PHPUnit\Framework\TestCase;

final class ProductSearchIndexDefinitionTest extends TestCase
{
    public function testMapsAdditionalRelationFieldsWithoutChangingNestedStructure(): void
    {
        $configuration = (new ProductSearchIndexDefinition(10000))->getConfiguration();
        $mapping = $configuration['mappings'];
        $properties = $mapping['properties'];

        self::assertSame(3, ProductSearchIndexDefinition::SCHEMA_VERSION);
        self::assertSame(10000, $configuration['settings']['index']['max_result_window']);
        self::assertSame('strict', $mapping['dynamic']);
        self::assertSame('nested', $properties['sections']['type']);
        self::assertSame('nested', $properties['prices']['type']);
        self::assertSame(['type' => 'text'], $properties['sections']['properties']['description']);
        self::assertSame(['type' => 'object', 'enabled' => false], $properties['picture']);
        self::assertSame(['type' => 'object', 'enabled' => false], $properties['sections']['properties']['picture']);
        self::assertArrayNotHasKey('picture_id', $properties);
        self::assertArrayNotHasKey('picture_id', $properties['sections']['properties']);
        self::assertSame(['type' => 'integer'], $properties['prices']['properties']['type_sort']);
        self::assertSame(['type' => 'text'], $properties['prices']['properties']['type_description']);
    }
}
