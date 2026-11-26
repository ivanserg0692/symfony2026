<?php

namespace App;

enum AdvisoryLockKey: int
{
    // Seed used to derive a transaction lock key from the operation ID.
    case InventoryDeductionHashSeed = 0;

    // Default session lock key for product search indexing.
    case ProductSearchReindex = 1633906547;
}
