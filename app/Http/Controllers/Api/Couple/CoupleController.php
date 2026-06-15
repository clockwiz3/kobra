<?php

namespace App\Http\Controllers\Api\Couple;

use App\Http\Controllers\Controller;
use App\Support\CoupleContext;

/**
 * База для couple-scoped эндпоинтов. Структурный backstop: если CoupleContext не установлен
 * (юзер без активной пары проскочил мимо middleware) → 403. Изоляция самих данных — CoupleScoped.
 */
abstract class CoupleController extends Controller
{
    public function __construct()
    {
        if (! app(CoupleContext::class)->has()) {
            abort(403, 'Требуется активная пара.');
        }
    }
}
