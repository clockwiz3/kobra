<?php

namespace App\Enums;

enum CoupleStatus: string
{
    case Pending = 'pending';   // инвайт создан, второй партнёр не принял
    case Active = 'active';     // shard живой, геймификация начисляется
    case Archived = 'archived'; // роспуск/выход — заморожен, retain
}
