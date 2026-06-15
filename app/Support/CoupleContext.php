<?php

namespace App\Support;

use App\Exceptions\MissingCoupleContextException;

/**
 * Request-fresh носитель текущего couple_id (активный shard).
 * Биндится синглтоном в контейнере, наполняется middleware ResolveCoupleContext.
 * НИКОГДА не кэшируется на токене/сессии. В очередях — явный set/clear per job (см. #11).
 */
final class CoupleContext
{
    private ?int $coupleId = null;

    public function set(int $coupleId): void
    {
        $this->coupleId = $coupleId;
    }

    public function clear(): void
    {
        $this->coupleId = null;
    }

    public function has(): bool
    {
        return $this->coupleId !== null;
    }

    public function idOrNull(): ?int
    {
        return $this->coupleId;
    }

    /** @throws MissingCoupleContextException когда контекст не установлен (fail-closed). */
    public function id(): int
    {
        return $this->coupleId ?? throw new MissingCoupleContextException();
    }

    /** Выполнить замыкание в контексте указанного couple, затем восстановить прежний. */
    public function withCouple(int $coupleId, callable $callback): mixed
    {
        $previous = $this->coupleId;
        $this->coupleId = $coupleId;
        try {
            return $callback();
        } finally {
            $this->coupleId = $previous;
        }
    }
}
