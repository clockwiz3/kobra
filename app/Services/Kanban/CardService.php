<?php

namespace App\Services\Kanban;

use App\Enums\CardAssignee;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Couple;
use App\Models\User;
use App\Services\Gamification\GamificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Карточки. Работает в текущем CoupleContext. Завершение карточки → событие геймификации.
 */
class CardService
{
    public function __construct(private readonly GamificationService $gamification)
    {
    }

    /**
     * Создать карточку. $attrs — опциональные параметры (description, due_at, due_end_at);
     * attrs-подход выбран намеренно: новые параметры карточки добавляются без смены сигнатуры.
     *
     * @param  array<string,mixed>  $attrs
     */
    public function create(BoardColumn $column, string $title, CardAssignee $assignee = CardAssignee::Together, array $attrs = [], ?User $actor = null): Card
    {
        $this->assertValidRange(null, $attrs); // бэкстоп к FormRequest

        // Карточка, созданная сразу в терминальной колонке, рождается «выполненной» (страховка:
        // UI прямое создание в «Готово» отключает, но контракт сервиса держим консистентным).
        $bornDone = $column->is_terminal;

        $card = Card::create([
            'board_column_id' => $column->id,
            'title' => $title,
            'assignee' => $assignee,
            'description' => $attrs['description'] ?? null,
            'due_at' => $attrs['due_at'] ?? null,
            'due_end_at' => $attrs['due_end_at'] ?? null,
            'done' => $bornDone, // явно: иначе in-memory done=null → CardData(bool $done) падает (DB-default не подтягивается без refresh)
            'completed_at' => $bornDone ? now() : null,
            'position' => $column->cards()->count(),
        ]);
        if ($bornDone) {
            $this->awardCompletion($card, $actor);
        }
        Log::info('[CardService.create]', [
            'card_id' => $card->id,
            'column_id' => $column->id,
            'has_due' => isset($attrs['due_at']),
            'born_done' => $bornDone,
        ]);

        return $card;
    }

    /** @param array<string,mixed> $attrs */
    public function update(Card $card, array $attrs): Card
    {
        $this->assertValidRange($card, $attrs); // бэкстоп к FormRequest (end >= start)

        $card->update($attrs);
        Log::info('[CardService.update]', ['card_id' => $card->id, 'keys' => array_keys($attrs)]);

        return $card;
    }

    /**
     * Инвариант дедлайна (бэкстоп к FormRequest): due_end_at допустим только вместе с due_at
     * и не может быть раньше него. Эффективные значения берём из $attrs, иначе из карточки
     * (на update меняется лишь часть полей). На create $card === null.
     *
     * @param  array<string,mixed>  $attrs
     */
    private function assertValidRange(?Card $card, array $attrs): void
    {
        $start = array_key_exists('due_at', $attrs) ? $attrs['due_at'] : $card?->due_at;
        $end = array_key_exists('due_end_at', $attrs) ? $attrs['due_end_at'] : $card?->due_end_at;

        if ($end === null) {
            return; // одиночная дата или без дедлайна — валидно
        }
        if ($start === null) {
            $this->failRange($card, 'due_end_at без due_at');
        }
        if (Carbon::parse($end)->lt(Carbon::parse($start))) {
            $this->failRange($card, 'due_end_at раньше due_at');
        }
    }

    private function failRange(?Card $card, string $why): never
    {
        Log::warning('[CardService.update] invalid range', ['card_id' => $card?->id, 'why' => $why]);

        throw ValidationException::withMessages([
            'due_end_at' => ['Конец диапазона дедлайна не может быть раньше начала.'],
        ]);
    }

    /**
     * Перенести карточку в колонку $toColumnId на индекс $toPosition и
     * пересчитать порядок так, чтобы позиции внутри колонок были контигуальными
     * (0..n-1). Без пересчёта одна запись position не сдвигала соседей → позиции
     * коллизировали и порядок внутри колонки не сохранялся надёжно (после reload
     * сбивался, т.к. orderBy('position') разруливал ничью на усмотрение БД).
     * Всё в транзакции: промежуточные коллизии position не должны быть видимы.
     */
    public function move(Card $card, BoardColumn $toColumn, int $toPosition, ?User $actor = null): Card
    {
        $toColumnId = $toColumn->id;

        return DB::transaction(function () use ($card, $toColumn, $toColumnId, $toPosition, $actor) {
            $fromColumnId = $card->board_column_id;

            // Относим карточку к целевой колонке; итоговую позицию выставит renumber ниже.
            $card->update(['board_column_id' => $toColumnId]);

            // Целевая колонка: текущий порядок БЕЗ перемещаемой карточки.
            $targetIds = Card::query()
                ->where('board_column_id', $toColumnId)
                ->where('id', '!=', $card->id)
                ->orderBy('position')->orderBy('id')
                ->pluck('id')->all();

            // Вставляем карточку на запрошенный индекс (кламп в границы списка).
            $index = max(0, min($toPosition, count($targetIds)));
            array_splice($targetIds, $index, 0, [$card->id]);
            $this->renumber($targetIds);

            // Переезд между колонками — компактим исходную (убираем «дыру» от ушедшей карточки).
            if ($fromColumnId !== $toColumnId) {
                $sourceIds = Card::query()
                    ->where('board_column_id', $fromColumnId)
                    ->orderBy('position')->orderBy('id')
                    ->pluck('id')->all();
                $this->renumber($sourceIds);
            }

            $card->refresh(); // position/column обновлены отдельными апдейтами — подтянуть в модель
            // Завершение/возврат задачи выводится из попадания в терминальную колонку (роль галочки).
            $this->applyDoneTransition($card, $toColumn, $actor);
            Log::info('[FIX][CardService.move] reindexed', [
                'card_id' => $card->id,
                'from_column' => $fromColumnId,
                'to_column' => $toColumnId,
                'requested_pos' => $toPosition,
                'stored_pos' => $card->position,
                'done' => $card->done,
            ]);

            return $card;
        });
    }

    /**
     * Готовность карточки как функция терминальной колонки:
     *  - вход в терминал (и ещё не done) → done=true + completed_at + событие геймификации (идемпотентно);
     *  - выход из терминала (был done) → done=false, completed_at=null, БЕЗ отрицательных начислений (upside-only).
     */
    private function applyDoneTransition(Card $card, BoardColumn $target, ?User $actor): void
    {
        if ($target->is_terminal && ! $card->done) {
            $card->update(['done' => true, 'completed_at' => now()]);
            $this->awardCompletion($card, $actor);
            Log::info('[CardService.applyDoneTransition]', ['card_id' => $card->id, 'to_terminal' => true, 'done' => true]);

            return;
        }

        if (! $target->is_terminal && $card->done) {
            $card->update(['done' => false, 'completed_at' => null]);
            Log::info('[CardService.applyDoneTransition]', ['card_id' => $card->id, 'to_terminal' => false, 'done' => false]);
        }
    }

    /**
     * Событие 'task_completed' (XP/стрик/ачивка), идемпотентно по карточке (анти-фарм:
     * повторное закрытие той же карточки не начисляет заново — ключ card_done_<id>).
     */
    private function awardCompletion(Card $card, ?User $actor): void
    {
        $couple = Couple::find($card->couple_id);
        if ($couple !== null) {
            $this->gamification->recordEvent(
                $couple,
                'task_completed',
                $actor,
                ['card_id' => $card->id],
                'card_done_'.$card->id, // idempotency key — одна награда за карточку
            );
        }
    }

    /**
     * Записать контигуальные позиции 0..n-1 в порядке переданных id — это и есть
     * «хранимый порядок» карточек в колонке.
     *
     * @param  array<int,int>  $orderedIds
     */
    private function renumber(array $orderedIds): void
    {
        foreach ($orderedIds as $i => $id) {
            Card::whereKey($id)->update(['position' => $i]); // scoped: только карточки текущей пары
        }
    }

}
