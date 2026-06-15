<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Бросается, когда couple-scoped доступ запрашивается без установленного CoupleContext.
 * Это намеренный fail-closed сигнал — НИКОГДА не подавлять «тихим» возвратом.
 */
class MissingCoupleContextException extends RuntimeException
{
    public function __construct(string $context = '')
    {
        parent::__construct(
            'CoupleContext is not set'.($context !== '' ? " for {$context}" : '')
            .'. Couple-scoped access requires a resolved active couple.'
        );
    }
}
