<?php

namespace App\Cart;

enum AdvisoryLockKey: int
{
    // First part of the lock key. The second part is the cart owner's ID.
    case GuestCartTransfer = 46129;
}
