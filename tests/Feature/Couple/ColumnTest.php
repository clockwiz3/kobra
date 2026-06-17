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
}
