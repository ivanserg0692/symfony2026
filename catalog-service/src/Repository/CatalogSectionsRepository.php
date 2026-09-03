<?php

namespace App\Repository;

use App\Entity\CatalogSections;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CatalogSections>
 */
class CatalogSectionsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogSections::class);
    }

    /**
     * @return CatalogSections[]
     */
    public function findActiveForPublicList(): array
    {
        return $this->createQueryBuilder("section")
            ->leftJoin("section.parent", "parent")
            ->addSelect("parent")
            ->andWhere("section.active = :active")
            ->setParameter("active", true)
            ->orderBy("section.sort", "DESC")
            ->addOrderBy("section.id", "ASC")
            ->getQuery()
            ->getResult();
    }

    public function findOneForPublicApi(int $id): ?CatalogSections
    {
        return $this->createQueryBuilder("section")
            ->leftJoin("section.parent", "parent")
            ->addSelect("parent")
            ->andWhere("section.id = :id")
            ->setParameter("id", $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Collects products from the current ORM object graph rather than querying
     * the database, because callers may run during onFlush before pending
     * hierarchy and association changes have been written.
     *
     * @return Product[]
     */
    public function collectProducts(CatalogSections $section, bool $includeDescendants): array
    {
        $products = [];
        $visitedSections = [];
        $sections = [$section];

        while ($sections !== []) {
            $currentSection = array_pop($sections);
            $objectId = spl_object_id($currentSection);

            if (isset($visitedSections[$objectId])) {
                continue;
            }

            $visitedSections[$objectId] = true;

            foreach ($currentSection->getProducts() as $product) {
                $productId = $product->getId();
                if ($productId !== null) {
                    $products[$productId] = $product;
                }
            }

            if ($includeDescendants) {
                foreach ($currentSection->getCatalogSections() as $childSection) {
                    $sections[] = $childSection;
                }
            }
        }

        return array_values($products);
    }
}
