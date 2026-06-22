<?php

namespace Tests\Feature\Couple;

use App\Models\BoardColumn;
use App\Models\User;
use App\Services\Couples\CoupleInvitationService;
use App\Services\Kanban\BoardService;
use App\Services\Kanban\CardService;
use App\Support\CoupleContext;
use Database\Seeders\CoupleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Бэклог («Задачи»): список карточек, убранных с доски (on_board=false), возврат на доску
 * с выбором колонки (позиция в конец, done-переход по терминальности), tenancy-изоляция и удаление.
 */
class BacklogTest extends TestCase
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

    /** @return array{0:int,1:int} [первая не-терминальная колонка, терминальная «Готово»] */
    private function columnIds(string $token): array
    {
        $cols = collect($this->withToken($token)->getJson('/api/couple/board')->assertOk()->json('columns'));

        return [
            (int) $cols->firstWhere('isTerminal', false)['id'],
            (int) $cols->firstWhere('isTerminal', true)['id'],
        ];
    }

    private function createCard(string $token, int $columnId, string $title = 'Свидание'): int
    {
        return (int) $this->withToken($token)->postJson('/api/couple/cards', [
            'column_id' => $columnId,
            'title' => $title,
        ])->assertStatus(201)->json('id');
    }

    /** Убрать карточку с доски (on_board=false) — установившийся путь через generic update. */
    private function removeFromBoard(string $token, int $cardId): void
    {
        $this->withToken($token)->patchJson("/api/couple/cards/{$cardId}", ['on_board' => false])->assertOk();
    }

    public function test_backlog_lists_only_off_board_cards(): void
    {
        $token = $this->login();
        [$col] = $this->columnIds($token);
        $onBoard = $this->createCard($token, $col, 'На доске');
        $removed = $this->createCard($token, $col, 'Убрана');
        $this->removeFromBoard($token, $removed);

        $ids = collect($this->withToken($token)->getJson('/api/couple/backlog')->assertOk()->json())->pluck('id');

        $this->assertTrue($ids->contains($removed), 'убранная карточка в бэклоге');
        $this->assertFalse($ids->contains($onBoard), 'карточка на доске НЕ в бэклоге');
    }

    public function test_create_in_backlog_is_off_board_and_absent_from_board(): void
    {
        $token = $this->login();

        $id = (int) $this->withToken($token)->postJson('/api/couple/backlog', ['title' => 'Идея на потом'])
            ->assertStatus(201)
            ->assertJsonPath('title', 'Идея на потом')
            ->json('id');

        // В бэклоге есть, на доске — нет.
        $backlogIds = collect($this->withToken($token)->getJson('/api/couple/backlog')->json())->pluck('id');
        $this->assertTrue($backlogIds->contains($id));

        $boardCardIds = collect($this->withToken($token)->getJson('/api/couple/board')->json('columns'))
            ->flatMap(fn ($c) => collect($c['cards'])->pluck('id'));
        $this->assertFalse($boardCardIds->contains($id), 'созданная в бэклоге карточка не на доске');

        $this->assertDatabaseHas('cards', ['id' => $id, 'on_board' => false, 'done' => false]);
    }

    public function test_create_in_backlog_requires_title(): void
    {
        $token = $this->login();

        $this->withToken($token)->postJson('/api/couple/backlog', ['title' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['title']);
    }

    public function test_created_backlog_card_can_be_returned_to_board(): void
    {
        $token = $this->login();
        [$col] = $this->columnIds($token);
        $id = (int) $this->withToken($token)->postJson('/api/couple/backlog', ['title' => 'Вернём позже'])
            ->assertStatus(201)->json('id');

        $this->withToken($token)->patchJson("/api/couple/cards/{$id}/return", ['column_id' => $col])
            ->assertOk()->assertJsonPath('columnId', $col);

        $this->assertDatabaseHas('cards', ['id' => $id, 'on_board' => true]);
    }

    public function test_return_puts_card_back_on_board_at_end_of_column(): void
    {
        $token = $this->login();
        [$col] = $this->columnIds($token);
        $keep = $this->createCard($token, $col, 'Остаётся'); // position 0
        $returned = $this->createCard($token, $col, 'Вернётся'); // position 1
        $this->removeFromBoard($token, $returned);

        $this->withToken($token)->patchJson("/api/couple/cards/{$returned}/return", ['column_id' => $col])
            ->assertOk()
            ->assertJsonPath('columnId', $col)
            ->assertJsonPath('done', false);

        // Снова на доске и в КОНЦЕ колонки (после оставшейся карточки).
        $cards = collect($this->withToken($token)->getJson('/api/couple/board')->json('columns'))
            ->firstWhere('id', $col)['cards'];
        $orderedIds = collect($cards)->pluck('id')->all();
        $this->assertSame([$keep, $returned], $orderedIds, 'возвращённая карточка — в конце колонки');

        // И исчезла из бэклога.
        $backlogIds = collect($this->withToken($token)->getJson('/api/couple/backlog')->json())->pluck('id');
        $this->assertFalse($backlogIds->contains($returned));
        $this->assertDatabaseHas('cards', ['id' => $returned, 'on_board' => true]);
    }

    public function test_return_into_terminal_column_marks_done(): void
    {
        $token = $this->login();
        [$col, $terminal] = $this->columnIds($token);
        $card = $this->createCard($token, $col, 'В готово');
        $this->removeFromBoard($token, $card);

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}/return", ['column_id' => $terminal])
            ->assertOk()
            ->assertJsonPath('done', true);

        $this->assertDatabaseHas('cards', ['id' => $card, 'on_board' => true, 'done' => true]);
    }

    public function test_delete_removes_backlog_card_permanently(): void
    {
        $token = $this->login();
        [$col] = $this->columnIds($token);
        $card = $this->createCard($token, $col, 'Удалить');
        $this->removeFromBoard($token, $card);

        $this->withToken($token)->deleteJson("/api/couple/cards/{$card}")->assertNoContent();

        $backlogIds = collect($this->withToken($token)->getJson('/api/couple/backlog')->json())->pluck('id');
        $this->assertFalse($backlogIds->contains($card));
        $this->assertDatabaseMissing('cards', ['id' => $card]);
    }

    /** Tenancy: бэклог второй пары не содержит карточек первой. */
    public function test_backlog_is_couple_scoped(): void
    {
        $token = $this->login();
        [$col] = $this->columnIds($token);
        $mine = $this->createCard($token, $col, 'Моя убранная');
        $this->removeFromBoard($token, $mine);

        // Чужой backlog-карточки быть в выдаче не должно (создаём её во втором shard).
        $foreign = $this->foreignBacklogCardId();

        $ids = collect($this->withToken($token)->getJson('/api/couple/backlog')->assertOk()->json())->pluck('id');
        $this->assertTrue($ids->contains($mine));
        $this->assertFalse($ids->contains($foreign), 'чужая карточка не попадает в бэклог');
    }

    /** Tenancy: возврат чужой карточки → 404 (CoupleScoped fail-closed), данные не меняются. */
    public function test_return_foreign_card_returns_404(): void
    {
        $token = $this->login();
        [$col] = $this->columnIds($token);
        $foreign = $this->foreignBacklogCardId();

        $this->withToken($token)->patchJson("/api/couple/cards/{$foreign}/return", ['column_id' => $col])
            ->assertNotFound();

        $this->assertDatabaseHas('cards', ['id' => $foreign, 'on_board' => false]); // осталась в чужом бэклоге
    }

    /** Поднять второй shard штатным lifecycle и создать в нём убранную с доски карточку. */
    private function foreignBacklogCardId(): int
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();

        $invitations = app(CoupleInvitationService::class);
        $couple = $invitations->accept($invitations->invite($owner, $partner->email), $partner);

        return app(CoupleContext::class)->withCouple($couple->id, function () {
            app(BoardService::class)->ensureDefaultBoard();
            $column = BoardColumn::query()->orderBy('id')->firstOrFail();
            $card = app(CardService::class)->create($column, 'Чужая убранная');
            $card->update(['on_board' => false]);

            return (int) $card->id;
        });
    }
}
