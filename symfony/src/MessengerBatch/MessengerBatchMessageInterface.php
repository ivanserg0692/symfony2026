<?php

namespace App\MessengerBatch;

interface MessengerBatchMessageInterface
{
    public function getBatchId(): int;
}
