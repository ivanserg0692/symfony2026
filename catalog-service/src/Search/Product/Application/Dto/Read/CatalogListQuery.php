<?php

namespace App\Search\Product\Application\Dto\Read;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'CatalogListQuery',
    type: 'object',
    description: 'Query parameters for the catalog element list.',
)]
final readonly class CatalogListQuery
{
    public function __construct(
        #[OA\Property(description: 'Legacy direct-section filter. Combined with sectionIds using OR.', type: 'integer', minimum: 1, deprecated: true)]
        #[Assert\Positive]
        public ?int $sectionId = null,
        #[OA\Property(description: 'Filter by active state.', type: 'boolean')]
        public ?string $active = null,
        #[OA\Property(description: 'Page number starting from 1.', type: 'integer', minimum: 1, default: 1)]
        #[Assert\Positive]
        public int $page = 1,
        #[OA\Property(description: 'Number of items per page.', type: 'integer', minimum: 1, maximum: 100, default: 20)]
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 20,
        #[OA\Property(description: 'Full-text search in name (boost 3) and description.', type: 'string')]
        public ?string $query = null,
        /** @var list<int|string> */
        #[OA\Property(
            description: 'Direct section identifiers passed as sectionIds[]. A product may belong to any listed section (OR). Combined with legacy sectionId.',
            type: 'array',
            items: new OA\Items(type: 'integer', minimum: 1),
        )]
        #[Assert\All([new Assert\AtLeastOneOf([
            new Assert\Blank,
            new Assert\Positive,
        ])])]
        public array $sectionIds = [],
        #[OA\Property(description: 'Minimum price amount, inclusive.', type: 'integer', minimum: 0)]
        #[Assert\PositiveOrZero]
        public ?int $priceFrom = null,
        #[OA\Property(description: 'Maximum price amount, inclusive.', type: 'integer', minimum: 0)]
        #[Assert\PositiveOrZero]
        #[Assert\GreaterThanOrEqual(
            propertyPath: 'priceFrom',
            message: 'priceTo must be greater than or equal to priceFrom.',
        )]
        public ?int $priceTo = null,
        /** @var list<string> */
        #[OA\Property(
            description: 'Price type codes passed as priceTypeCodes[] (OR). Combined with the price range within the same nested price object.',
            type: 'array',
            items: new OA\Items(type: 'string'),
        )]
        #[Assert\All([new Assert\Type('string')])]
        public array $priceTypeCodes = [],
        #[OA\Property(description: 'true requires total stock above zero; false requires total stock at or below zero.', type: 'boolean')]
        #[Assert\Choice(choices: ['true', 'false', '1', '0'])]
        public ?string $inStock = null,
    ) {
    }

    public function toCriteria(bool $lookAhead): CatalogListCriteria
    {
        return new CatalogListCriteria(
            sectionId: $this->sectionId,
            active: $this->normalizeBoolean($this->active),
            page: $this->page,
            limit: $this->limit,
            lookAhead: $lookAhead,
            query: $this->normalizeQuery(),
            sectionIds: $this->normalizeSectionIds(),
            priceFrom: $this->priceFrom,
            priceTo: $this->priceTo,
            priceTypeCodes: $this->normalizePriceTypeCodes(),
            inStock: $this->normalizeBoolean($this->inStock),
        );
    }

    private function normalizeBoolean(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($normalized) ? $normalized : null;
    }

    private function normalizeQuery(): ?string
    {
        $query = trim((string) $this->query);

        return $query === '' ? null : $query;
    }

    /** @return list<int> */
    private function normalizeSectionIds(): array
    {
        $sectionIds = [];
        foreach ($this->sectionIds as $sectionId) {
            if ($sectionId === '') {
                continue;
            }

            $sectionIds[] = (int) $sectionId;
        }

        return array_values(array_unique($sectionIds));
    }

    /** @return list<string> */
    private function normalizePriceTypeCodes(): array
    {
        $codes = [];
        foreach ($this->priceTypeCodes as $priceTypeCode) {
            $priceTypeCode = trim($priceTypeCode);
            if ($priceTypeCode !== '') {
                $codes[] = $priceTypeCode;
            }
        }

        return array_values(array_unique($codes));
    }
}
