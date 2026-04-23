<?php

namespace App\Enum;

enum NewsStatusCode: string
{
    case PUBLIC = 'public';
    case INTERNAL = 'internal';
    case ON_MODERATION = 'on_moderation';
    case MODERATION_REJECTED = 'moderation_rejected';
    case DRAFTED = 'drafted';
}
