<?php

namespace App\Enums;

enum CoupleRole: string
{
    case Owner = 'owner';     // создатель пары (инициатор инвайта)
    case Partner = 'partner'; // принявший инвайт
}
