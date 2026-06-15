<?php

namespace App\Enums;

enum WaitlistStatus: string
{
    case Pending = 'pending';     // записан, не подтвердил email
    case Confirmed = 'confirmed'; // double-opt-in подтверждён
    case Invited = 'invited';     // приглашён в продукт (на запуске)
}
