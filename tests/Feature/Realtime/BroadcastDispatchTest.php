<?php

namespace Tests\Feature\Realtime;

use App\Data\CardData;
use App\Data\ColumnData;
use App\Events\CardBroadcast;
use App\Events\CardRemoved;
use App\Events\ColumnBroadcast;
use App\Events\ColumnRemoved;
use App\Events\ColumnsReordered;
use App\Events\MemberAppearanceUpdated;
use Database\Seeders\CoupleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Каждая мутация couple-данных вещает доменное событие на presence-канал couple.{id}
 * с DTO-payload (не сырой Eloquent). Event::fake перехватывает broadcast() (он диспатчит
 * через event-диспетчер), реального соединения не требуется.
 */
class BroadcastDispatchTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private int $coupleId;

    private int $col;      // нетерминальная колонка

    private int $terminal; // терминальная колонка («Готово»)

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoupleSeeder::class);
        $this->token = $this->postJson('/api/login', ['email' => 'lesya@ix.app', 'password' => 'kobra'])->json('token');

        $board = $this->withToken($this->token)->getJson('/api/couple/board')->json();
        $this->coupleId = (int) $board['coupleId'];
        $this->col = (int) collect($board['columns'])->firstWhere('isTerminal', false)['id'];
        $this->terminal = (int) collect($board['columns'])->firstWhere('isTerminal', true)['id'];
    }

    private function channel(): string
    {
        return "presence-couple.{$this->coupleId}";
    }

    private function createCard(string $title = 'Тест'): int
    {
        return (int) $this->withToken($this->token)
            ->postJson('/api/couple/cards', ['column_id' => $this->col, 'title' => $title])
            ->json('id');
    }

    public function test_card_create_broadcasts_on_couple_channel_with_dto(): void
    {
        Event::fake([CardBroadcast::class]);

        $id = $this->createCard('Новая');

        Event::assertDispatched(CardBroadcast::class, fn ($e) => $e->action === 'created'
            && $e->coupleId === $this->coupleId
            && $e->card instanceof CardData
            && $e->card->id === $id
            && $e->broadcastOn()->name === $this->channel());
    }

    public function test_card_move_to_terminal_broadcasts_moved_done(): void
    {
        $id = $this->createCard();
        Event::fake([CardBroadcast::class]);

        $this->withToken($this->token)
            ->patchJson("/api/couple/cards/{$id}/move", ['column_id' => $this->terminal, 'position' => 0])
            ->assertOk();

        Event::assertDispatched(CardBroadcast::class, fn ($e) => $e->action === 'moved' && $e->card->id === $id && $e->card->done === true);
    }

    public function test_card_update_broadcasts_updated(): void
    {
        $id = $this->createCard();
        Event::fake([CardBroadcast::class]);

        $this->withToken($this->token)->patchJson("/api/couple/cards/{$id}", ['title' => 'Изменено'])->assertOk();

        Event::assertDispatched(CardBroadcast::class, fn ($e) => $e->action === 'updated' && $e->card->id === $id);
    }

    public function test_backlog_create_broadcasts_backlog_created_off_board(): void
    {
        Event::fake([CardBroadcast::class]);

        $this->withToken($this->token)->postJson('/api/couple/backlog', ['title' => 'В бэклог'])->assertSuccessful();

        Event::assertDispatched(CardBroadcast::class, fn ($e) => $e->action === 'backlogCreated' && $e->card->onBoard === false);
    }

    public function test_return_broadcasts_returned_on_board(): void
    {
        $id = (int) $this->withToken($this->token)->postJson('/api/couple/backlog', ['title' => 'Вернуть'])->json('id');
        Event::fake([CardBroadcast::class]);

        $this->withToken($this->token)
            ->patchJson("/api/couple/cards/{$id}/return", ['column_id' => $this->col])
            ->assertOk();

        Event::assertDispatched(CardBroadcast::class, fn ($e) => $e->action === 'returned' && $e->card->id === $id && $e->card->onBoard === true);
    }

    public function test_card_delete_broadcasts_card_removed(): void
    {
        $id = $this->createCard();
        Event::fake([CardRemoved::class]);

        $this->withToken($this->token)->deleteJson("/api/couple/cards/{$id}")->assertNoContent();

        Event::assertDispatched(CardRemoved::class, fn ($e) => $e->cardId === $id
            && $e->coupleId === $this->coupleId
            && $e->broadcastOn()->name === $this->channel());
    }

    public function test_column_create_broadcasts_with_dto(): void
    {
        $boardId = (int) $this->withToken($this->token)->getJson('/api/couple/board')->json('id');
        Event::fake([ColumnBroadcast::class]);

        $this->withToken($this->token)->postJson('/api/couple/columns', ['board_id' => $boardId, 'title' => 'Новая'])->assertSuccessful();

        Event::assertDispatched(ColumnBroadcast::class, fn ($e) => $e->action === 'created'
            && $e->column instanceof ColumnData
            && $e->broadcastOn()->name === $this->channel());
    }

    public function test_column_rename_broadcasts_updated(): void
    {
        Event::fake([ColumnBroadcast::class]);

        $this->withToken($this->token)->patchJson("/api/couple/columns/{$this->col}", ['title' => 'Переименована'])->assertOk();

        Event::assertDispatched(ColumnBroadcast::class, fn ($e) => $e->action === 'updated' && $e->column->id === $this->col);
    }

    public function test_column_reorder_broadcasts_ordered_ids(): void
    {
        Event::fake([ColumnsReordered::class]);

        $this->withToken($this->token)->patchJson('/api/couple/columns/reorder', ['ids' => [$this->col]])->assertNoContent();

        Event::assertDispatched(ColumnsReordered::class, fn ($e) => $e->coupleId === $this->coupleId
            && is_array($e->orderedIds)
            && $e->broadcastOn()->name === $this->channel());
    }

    public function test_column_delete_broadcasts_column_removed(): void
    {
        Event::fake([ColumnRemoved::class]);

        $this->withToken($this->token)->deleteJson("/api/couple/columns/{$this->col}")->assertNoContent();

        Event::assertDispatched(ColumnRemoved::class, fn ($e) => $e->columnId === $this->col && $e->coupleId === $this->coupleId);
    }

    /** Профиль вне couple.context: coupleId/slot резолвятся через couple_user. */
    public function test_profile_appearance_broadcasts_member_appearance(): void
    {
        Event::fake([MemberAppearanceUpdated::class]);

        $this->withToken($this->token)->patchJson('/api/user', [
            'appearance' => ['card' => [
                'paint' => ['kind' => 'solid', 'color' => '#e64ab4'],
                'style' => 'solid', 'width' => 2, 'animated' => false,
            ]],
        ])->assertOk();

        Event::assertDispatched(MemberAppearanceUpdated::class, fn ($e) => $e->slot === 'her'
            && $e->coupleId === $this->coupleId
            && $e->broadcastOn()->name === $this->channel());
    }
}
