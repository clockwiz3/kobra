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
 * Параметры карточки: description, assignee, дедлайн (одиночная дата / диапазон due_at+due_end_at),
 * валидация диапазона (FormRequest + бэкстоп сервиса) и tenancy-изоляция апдейта.
 */
class CardUpdateTest extends TestCase
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

    private function firstColumnId(string $token): int
    {
        return (int) $this->withToken($token)->getJson('/api/couple/board')
            ->assertOk()->json('columns.0.id');
    }

    private function createCard(string $token, int $columnId, string $title = 'Свидание'): int
    {
        return (int) $this->withToken($token)->postJson('/api/couple/cards', [
            'column_id' => $columnId,
            'title' => $title,
        ])->assertStatus(201)->json('id');
    }

    public function test_update_sets_description_and_assignee(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'description' => 'Ужин при свечах',
            'assignee' => 'him',
        ])->assertOk()
            ->assertJsonPath('description', 'Ужин при свечах')
            ->assertJsonPath('assignee', 'him');

        $this->assertDatabaseHas('cards', ['id' => $card, 'assignee' => 'him']);
    }

    /** Лимит названия: апдейт title > 100 символов → 422 (валидация title). */
    public function test_update_rejects_title_over_100_chars(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'title' => str_repeat('я', 101),
        ])->assertStatus(422)->assertJsonValidationErrors(['title']);
    }

    public function test_update_sets_due_time(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'due_time' => '08:05',
        ])->assertOk()->assertJsonPath('dueTime', '08:05');

        $this->assertDatabaseHas('cards', ['id' => $card, 'due_time' => '08:05']);
    }

    public function test_update_rejects_invalid_time_format(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'due_time' => '25:00',
        ])->assertStatus(422)->assertJsonValidationErrors(['due_time']);
    }

    public function test_update_clears_due_time_with_null(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", ['due_time' => '08:05'])->assertOk();
        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", ['due_time' => null])
            ->assertOk()->assertJsonPath('dueTime', null);
    }

    public function test_update_sets_priority(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'priority' => 'high',
        ])->assertOk()->assertJsonPath('priority', 'high');

        $this->assertDatabaseHas('cards', ['id' => $card, 'priority' => 'high']);
    }

    public function test_update_rejects_invalid_priority(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'priority' => 'urgent',
        ])->assertStatus(422)->assertJsonValidationErrors(['priority']);
    }

    /** «Убрать с доски» (on_board=false): карточка пропадает из выдачи доски, но остаётся в БД (НЕ удаление). */
    public function test_remove_from_board_hides_card_but_keeps_it(): void
    {
        $token = $this->login();
        $col = $this->firstColumnId($token);
        $card = $this->createCard($token, $col);

        $onBoard = collect($this->withToken($token)->getJson('/api/couple/board')->json('columns.0.cards'))->pluck('id');
        $this->assertTrue($onBoard->contains($card));

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", ['on_board' => false])->assertOk();

        $afterIds = collect($this->withToken($token)->getJson('/api/couple/board')->json('columns.0.cards'))->pluck('id');
        $this->assertFalse($afterIds->contains($card), 'карточка убрана с доски');
        $this->assertDatabaseHas('cards', ['id' => $card, 'on_board' => false]); // но осталась в БД
    }

    public function test_update_sets_single_due_date_and_leaves_end_null(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $res = $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'due_at' => '2026-06-20',
        ])->assertOk()->assertJsonPath('dueEndAt', null);

        $this->assertStringStartsWith('2026-06-20', (string) $res->json('dueAt'));
    }

    public function test_update_sets_date_range(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $res = $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'due_at' => '2026-06-17',
            'due_end_at' => '2026-06-24',
        ])->assertOk();

        $this->assertStringStartsWith('2026-06-17', (string) $res->json('dueAt'));
        $this->assertStringStartsWith('2026-06-24', (string) $res->json('dueEndAt'));
    }

    public function test_update_rejects_end_before_start(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'due_at' => '2026-06-24',
            'due_end_at' => '2026-06-17',
        ])->assertStatus(422)->assertJsonValidationErrors(['due_end_at']);
    }

    public function test_clearing_deadline_with_null(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'due_at' => '2026-06-17',
            'due_end_at' => '2026-06-24',
        ])->assertOk();

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'due_at' => null,
            'due_end_at' => null,
        ])->assertOk()
            ->assertJsonPath('dueAt', null)
            ->assertJsonPath('dueEndAt', null);
    }

    /**
     * Бэкстоп сервиса: due_end_at приходит БЕЗ due_at (начало остаётся из карточки),
     * и оказывается раньше сохранённого начала → 422 (FormRequest этот случай пропускает).
     */
    public function test_service_backstop_rejects_end_before_existing_start(): void
    {
        $token = $this->login();
        $card = $this->createCard($token, $this->firstColumnId($token));

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'due_at' => '2026-06-20',
        ])->assertOk();

        $this->withToken($token)->patchJson("/api/couple/cards/{$card}", [
            'due_end_at' => '2026-06-10',
        ])->assertStatus(422)->assertJsonValidationErrors(['due_end_at']);
    }

    /**
     * Tenancy: PATCH чужой карточки (другой shard) → 404 (CoupleScoped fail-closed),
     * данные чужой пары не меняются.
     */
    public function test_update_foreign_couple_card_returns_404(): void
    {
        $token = $this->login();
        $foreignCard = $this->foreignCardId();

        $this->withToken($token)->patchJson("/api/couple/cards/{$foreignCard}", [
            'description' => 'взлом',
        ])->assertNotFound();

        $this->assertDatabaseMissing('cards', ['id' => $foreignCard, 'description' => 'взлом']);
    }

    /** Поднять второй shard штатным lifecycle и создать в нём карточку — заведомо чужую. */
    private function foreignCardId(): int
    {
        $owner = User::factory()->create();
        $partner = User::factory()->create();

        $invitations = app(CoupleInvitationService::class);
        $couple = $invitations->accept($invitations->invite($owner, $partner->email), $partner);

        return app(CoupleContext::class)->withCouple($couple->id, function () {
            app(BoardService::class)->ensureDefaultBoard();
            $column = BoardColumn::query()->orderBy('id')->firstOrFail();

            return (int) app(CardService::class)->create($column, 'Чужая карточка')->id;
        });
    }
}
