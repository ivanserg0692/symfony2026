<?php

namespace App\MessengerBatch;

enum MessengerBatchStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case FINISHED = 'finished';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
