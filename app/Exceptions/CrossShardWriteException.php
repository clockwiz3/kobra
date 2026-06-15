<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Бросается при попытке записать/сменить couple_id, не равный текущему CoupleContext.
 * Защита от кросс-шардовой записи (изоляция tenant).
 */
class CrossShardWriteException extends RuntimeException
{
    public function __construct(string $model = '')
    {
        parent::__construct(
            'Cross-shard write blocked: couple_id mismatch'.($model !== '' ? " on {$model}" : '').'.'
        );
    }
}
