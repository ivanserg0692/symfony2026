<?php

namespace App\MessengerBatch;

interface MessengerBatchFinalizableMessageInterface extends MessengerBatchMessageInterface
{
    public function createFinalizeMessage(): object;
}
