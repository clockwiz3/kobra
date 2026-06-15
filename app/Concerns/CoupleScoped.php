<?php

namespace App\Concerns;

use App\Exceptions\CrossShardWriteException;
use App\Exceptions\MissingCoupleContextException;
use App\Scopes\CoupleScope;
use App\Support\CoupleContext;

/**
 * Трейт изоляции couple-owned моделей:
 *   1) global scope CoupleScope (fail-closed чтение);
 *   2) на create — жёстко проставляет couple_id из CoupleContext (перетирает клиента, throw если null);
 *   3) на update — запрещает смену couple_id на чужой.
 *
 * couple_id ОБЯЗАН быть в $guarded модели.
 */
trait CoupleScoped
{
    public static function bootCoupleScoped(): void
    {
        static::addGlobalScope(new CoupleScope());

        static::creating(function ($model): void {
            $contextId = app(CoupleContext::class)->idOrNull();
            if ($contextId === null) {
                throw new MissingCoupleContextException(static::class.' (creating)');
            }
            $model->couple_id = $contextId; // direct set — обходит $guarded намеренно
        });

        static::saving(function ($model): void {
            if ($model->exists && $model->isDirty('couple_id')) {
                $contextId = app(CoupleContext::class)->idOrNull();
                if ($contextId !== null && (int) $model->couple_id !== (int) $contextId) {
                    throw new CrossShardWriteException(static::class);
                }
            }
        });
    }
}
