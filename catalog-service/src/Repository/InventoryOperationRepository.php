<?php

namespace App\Repository;

use App\Entity\InventoryOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryOperation>
 */
class InventoryOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryOperation::class);
    }

    public function findOneByOperationId(string $operationId): ?InventoryOperation
    {
        return $this->findOneBy(["operationId" => $operationId]);
    }

    /**
     * @param array<string, mixed> $responsePayload
     */
    public function addDeductionOperation(string $operationId, string $requestHash, array $responsePayload): void
    {
        $this->getEntityManager()->persist(new InventoryOperation(
            $operationId,
            InventoryOperation::TYPE_STOCK_DEDUCTION,
            $requestHash,
            $responsePayload,
        ));
    }
}
