<?php

namespace App\Scopes;

use App\Exceptions\MissingCoupleContextException;
use App\Support\CoupleContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope изоляции couple-owned моделей. FAILS CLOSED:
 *   - нет CoupleContext + non-prod/testing → throw (ловим в CI);
 *   - нет CoupleContext + production → always-false предикат (ничего не отдаём).
 * No-op-on-null ЗАПРЕЩЁН (это была бы fail-open утечка).
 */
final class CoupleScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $coupleId = app(CoupleContext::class)->idOrNull();

        if ($coupleId === null) {
            if (! app()->isProduction()) {
                throw new MissingCoupleContextException($model::class);
            }
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable().'.couple_id', $coupleId);
    }
}
