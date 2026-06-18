<?php

namespace Tests\Feature\Couple;

use Database\Seeders\CoupleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColumnTest extends TestCase
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

    /** @return array<string,mixed> выдача доски */
    private function board(string $token): array
    {
        return $this->withToken($token)->getJson('/api/couple/board')->assertOk()->json();
    }

    /** Терминальную («Готово») колонку удалить нельзя — она выполняет роль галочки «выполнено». */
    public function test_terminal_column_cannot_be_deleted(): void
    {
        $token = $this->login();
        $terminal = (int) collect($this->board($token)['columns'])->firstWhere('isTerminal', true)['id'];

        $this->withToken($token)->deleteJson("/api/couple/columns/{$terminal}")->assertStatus(422);

        $this->assertDatabaseHas('board_columns', ['id' => $terminal]); // не удалена
    }

    /** Обычную (нетерминальную) колонку удалять можно как прежде. */
    public function test_non_terminal_column_can_be_deleted(): void
    {
        $token = $this->login();
        $normal = (int) $this->board($token)['columns'][0]['id']; // «Идеи», isTerminal=false

        $this->withToken($token)->deleteJson("/api/couple/columns/{$normal}")->assertNoContent();

        $this->assertDatabaseMissing('board_columns', ['id' => $normal]);
    }

    /** Новая колонка добавляется ПЕРЕД терминальной — «Готово» остаётся последней. */
    public function test_added_column_is_inserted_before_terminal(): void
    {
        $token = $this->login();
        $board = $this->board($token);

        $this->withToken($token)->postJson('/api/couple/columns', [
            'board_id' => $board['id'],
            'title' => 'Новая',
        ])->assertSuccessful()->assertJsonPath('title', 'Новая')->assertJsonPath('isTerminal', false);

        $cols = collect($this->board($token)['columns'])->sortBy('position')->values();
        $this->assertTrue($cols->last()['isTerminal']);                 // «Готово» — последняя
        $this->assertSame('Новая', $cols[$cols->count() - 2]['title']); // новая — непосредственно перед ней
    }

    /** Reorder-бэкстоп: терминальная колонка принудительно последняя, даже если фронт прислал иначе. */
    public function test_reorder_forces_terminal_last(): void
    {
        $token = $this->login();
        $board = $this->board($token);
        $ids = collect($board['columns'])->sortBy('position')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $terminalId = (int) collect($board['columns'])->firstWhere('isTerminal', true)['id'];

        // «битый» порядок: терминальную ставим первой
        $bad = array_merge([$terminalId], array_values(array_filter($ids, fn ($id) => $id !== $terminalId)));
        $this->withToken($token)->patchJson('/api/couple/columns/reorder', ['ids' => $bad])->assertNoContent();

        $cols = collect($this->board($token)['columns'])->sortBy('position')->values();
        $this->assertTrue($cols->last()['isTerminal']); // всё равно последняя
        $this->assertSame($terminalId, (int) $cols->last()['id']);
    }
}
