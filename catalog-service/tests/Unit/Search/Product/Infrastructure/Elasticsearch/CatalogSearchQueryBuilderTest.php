<?php

namespace AppTests\Unit\Search\Product\Infrastructure\Elasticsearch;

use App\Search\Product\Application\Dto\Read\CatalogListCriteria;
use App\Search\Product\Infrastructure\Elasticsearch\CatalogSearch\CatalogSearchQueryBuilder;
use PHPUnit\Framework\TestCase;

final class CatalogSearchQueryBuilderTest extends TestCase
{
    public function testUsesConfiguredResultWindowForDeepPaginationDecision(): void
    {
        $builder = new CatalogSearchQueryBuilder(100);

        self::assertFalse($builder->requiresDeepPagination(new CatalogListCriteria(null, null, 10, 10, false)));
        self::assertTrue($builder->requiresDeepPagination(new CatalogListCriteria(null, null, 11, 10, false)));
    }
}
