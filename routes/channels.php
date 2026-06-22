<?php

use App\Broadcasting\CoupleChannel;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| presence-канал пары — единственная точка real-time для shard'а: на нём
| вещаются доменные события (карточки/колонки/профиль) И ведётся онлайн-
| присутствие партнёра. Авторизация — в CoupleChannel::join (fail-closed
| по членству; тестируется напрямую leak-харнессом двух пар).
|
*/

Broadcast::channel('couple.{coupleId}', CoupleChannel::class);
