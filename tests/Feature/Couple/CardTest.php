<?php

namespace Tests\Feature\Couple;

use App\Models\BoardColumn;
use App\Models\User;
use App\Services\Couples\CoupleInvitationService;
use App\Services\Kanban\BoardService;
use App\Support\CoupleContext;
use Database\Seeders\CoupleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CardTest extends TestCase
{
    use RefreshDatabase;

    private function login(): string
    {
        $this->seed(CoupleSeeder::class);

        return $this->postJson('/api/login', [
            'email' => 'lesya@ix.app',
            'password' => 'kobra',
        ])->assertOk()->json('token');
    }

    /** Регрессия: POST /cards раньше падал 500 — CardData(bool $done) получал null (done не выставлялся при create). */
    public function test_create_card_returns_201_with_done_false(): void
    {
        $token = $this->login();

        $columnId = $this->withToken($token)->getJson('/api/couple/board')
            ->assertOk()->json('columns.0.id');

        $res = $this->withToken($token)->postJson('/api/couple/cards', [
            'column_id' => $columnId,
            'title' => 'Свидание у моря',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('title', 'Свидание у моря')
            ->assertJsonPath('done', false)
            ->assertJsonPath('assignee', 'together');

        $this->assertDatabaseHas('cards', ['title' => 'Свидание у моря', 'done' => false]);
    }

    /** @return int id созданной карточки */
    private function createCard(string $token, int $columnId, string $title): int
    {
        return $this->withToken($token)->postJson('/api/couple/cards', [
            'column_id' => $columnId,
            'title' => $title,
        ])->assertStatus(201)->json('id');
    }

    /** @return array<int,int> id карточек колонки в сохранённом (position) порядке */
    private function columnCardIds(string $token, int $columnIndex): array
    {
        return collect(
            $this->withToken($token)->getJson('/api/couple/board')
                ->assertOk()->json("columns.{$columnIndex}.cards")
        )->pluck('id')->all();
    }

    /** @return array<int,int> position карточек колонки в порядке выдачи */
    private function columnPositions(string $token, int $columnIndex): array
    {
        return collect(
            $this->withToken($token)->getJson('/api/couple/board')
                ->assertOk()->json("columns.{$columnIndex}.cards")
        )->pluck('position')->all();
    }

    /**
     * Регрессия: порядок карточек внутри колонки должен переживать reload.
     * Раньше move писал position только перемещаемой карточки → коллизии → порядок сбивался.
     */
    public function test_move_persists_reorder_within_same_column(): void
    {
        $token = $this->login();
        $col = (int) $this->withToken($token)->getJson('/api/couple/board')->assertOk()->json('columns.0.id');

        $a = $this->createCard($token, $col, 'A'); // position 0
        $b = $this->createCard($token, $col, 'B'); // position 1
        $c = $this->createCard($token, $col, 'C'); // position 2

        // A (поз 0) → в конец (поз 2): ожидаемый порядок B, C, A
        $this->withToken($token)->patchJson("/api/couple/cards/{$a}/move", [
            'column_id' => $col,
            'position' => 2,
        ])->assertOk();

        $this->assertSame([$b, $c, $a], $this->columnCardIds($token, 0));
        $this->assertSame([0, 1, 2], $this->columnPositions($token, 0)); // контигуально, без коллизий
    }

    /**
     * Поднять ВТОРОЙ shard (пара из двух новых юзеров) штатным lifecycle и вернуть id
     * одной из его колонок — заведомо ЧУЖОЙ для пары Леся&Серёжа.
     */
    private function foreignColumnId(): int
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();

        $invitations = app(CoupleInvitationService::class);
        $couple = $invitations->accept($invitations->invite($owner, $partner->email), $partner);

        return app(CoupleContext::class)->withCouple($couple->id, function () {
            app(BoardService::class)->ensureDefaultBoard();

            return (int) BoardColumn::query()->orderBy('id')->firstOrFail()->id; // scoped к чужой паре
        });
    }

    /**
     * Tenancy-инвариант: move в колонку ЧУЖОЙ пары даёт 404 (CoupleScoped fail-closed),
     * карточка не «уносится» с доски своей пары. Регрессия: column_id не проверялся на принадлежность.
     */
    public function test_move_to_foreign_couple_column_returns_404_and_keeps_card_in_place(): void
    {
        $token = $this->login();
        $col = (int) $this->withToken($token)->getJson('/api/couple/board')->assertOk()->json('columns.0.id');
        $card = $this->createCard($token, $col, 'A');

        $foreignColumn = $this->foreignColumnId();

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}/move", [
            'column_id' => $foreignColumn,
            'position' => 0,
        ])->assertNotFound();

        // Карточка осталась в своей колонке — не «улетела» в чужую и не пропала с доски.
        $this->assertSame([$card], $this->columnCardIds($token, 0));
        $this->assertDatabaseHas('cards', ['id' => $card, 'board_column_id' => $col]);
    }

    /**
     * Регрессия: перенос между колонками вставляет по индексу в целевой колонке
     * и компактит исходную (без «дыр» в нумерации). Порядок переживает reload.
     */
    public function test_move_between_columns_inserts_at_index_and_compacts_source(): void
    {
        $token = $this->login();
        $board = $this->withToken($token)->getJson('/api/couple/board')->assertOk()->json();
        $col0 = (int) $board['columns'][0]['id'];
        $col1 = (int) $board['columns'][1]['id'];

        $a = $this->createCard($token, $col0, 'A'); // col0: A,B,C
        $b = $this->createCard($token, $col0, 'B');
        $c = $this->createCard($token, $col0, 'C');
        $x = $this->createCard($token, $col1, 'X'); // col1: X,Y
        $y = $this->createCard($token, $col1, 'Y');

        // B из col0 → в col1 на индекс 1: col1 = X, B, Y; col0 = A, C
        $this->withToken($token)->patchJson("/api/couple/cards/{$b}/move", [
            'column_id' => $col1,
            'position' => 1,
        ])->assertOk();

        $this->assertSame([$a, $c], $this->columnCardIds($token, 0));
        $this->assertSame([0, 1], $this->columnPositions($token, 0)); // исходная компактна
        $this->assertSame([$x, $b, $y], $this->columnCardIds($token, 1));
        $this->assertSame([0, 1, 2], $this->columnPositions($token, 1)); // целевая контигуальна
    }

    /** id терминальной («Готово») колонки из выдачи доски. */
    private function terminalColumnId(string $token): int
    {
        $columns = $this->withToken($token)->getJson('/api/couple/board')->assertOk()->json('columns');

        return (int) collect($columns)->firstWhere('isTerminal', true)['id'];
    }

    /** @return int|null число событий task_completed по конкретной карточке */
    private function completionEvents(int $cardId): int
    {
        return DB::table('activity_events')->where('idempotency_key', 'card_done_'.$cardId)->count();
    }

    /**
     * Завершение через колонку: перенос в терминальную «Готово» закрывает карточку
     * (done + completed_at) и начисляет ОДИН раз — повторное закрытие идемпотентно (анти-фарм).
     */
    public function test_move_into_terminal_column_closes_card_and_awards_once(): void
    {
        $token = $this->login();
        $col0 = (int) $this->withToken($token)->getJson('/api/couple/board')->assertOk()->json('columns.0.id');
        $terminal = $this->terminalColumnId($token);

        $card = $this->createCard($token, $col0, 'Свидание у моря');

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}/move", [
            'column_id' => $terminal,
            'position' => 0,
        ])->assertOk()->assertJsonPath('done', true);

        $this->assertDatabaseHas('cards', ['id' => $card, 'done' => true]);
        $this->assertNotNull(DB::table('cards')->where('id', $card)->value('completed_at'));
        $this->assertSame(1, $this->completionEvents($card));

        // Туда → обратно → туда: повторное закрытие той же карточки не начисляет заново.
        $this->withToken($token)->patchJson("/api/couple/cards/{$card}/move", ['column_id' => $col0, 'position' => 0])->assertOk();
        $this->withToken($token)->patchJson("/api/couple/cards/{$card}/move", ['column_id' => $terminal, 'position' => 0])->assertOk()->assertJsonPath('done', true);

        $this->assertSame(1, $this->completionEvents($card)); // всё ещё одно событие
    }

    /**
     * Возврат в работу: вынос из терминальной колонки снимает done и completed_at,
     * БЕЗ отрицательных начислений (upside-only) — событие закрытия остаётся одно.
     */
    public function test_move_out_of_terminal_column_reopens_without_negative(): void
    {
        $token = $this->login();
        $col0 = (int) $this->withToken($token)->getJson('/api/couple/board')->assertOk()->json('columns.0.id');
        $terminal = $this->terminalColumnId($token);

        $card = $this->createCard($token, $col0, 'Купить цветы');

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}/move", ['column_id' => $terminal, 'position' => 0])->assertOk()->assertJsonPath('done', true);
        $this->withToken($token)->patchJson("/api/couple/cards/{$card}/move", ['column_id' => $col0, 'position' => 0])->assertOk()->assertJsonPath('done', false);

        $this->assertDatabaseHas('cards', ['id' => $card, 'done' => false]);
        $this->assertNull(DB::table('cards')->where('id', $card)->value('completed_at'));
        $this->assertSame(1, $this->completionEvents($card)); // ровно одно (за закрытие), без отрицательных
    }
}
