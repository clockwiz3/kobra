<?php

namespace App\Http\Middleware;

use App\Support\CoupleContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Резолвит активный shard аутентифицированного юзера и кладёт его в CoupleContext.
 * Ставится за auth:sanctum на couple-scoped маршрутах. Контекст request-fresh,
 * НЕ кэшируется на токене/сессии.
 */
class ResolveCoupleContext
{
    public function __construct(private readonly CoupleContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            // couple_user НЕ в closed-list couple-owned таблиц — прямой запрос допустим.
            $coupleId = DB::table('couple_user')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->value('couple_id');

            if ($coupleId !== null) {
                $this->context->set((int) $coupleId);
                Log::debug('[ResolveCoupleContext.handle] context resolved', [
                    'user_id' => $user->id,
                    'couple_id' => $coupleId,
                ]);
            }
        }

        return $next($request);
    }
}
