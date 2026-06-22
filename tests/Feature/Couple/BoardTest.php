<?php

namespace Tests\Feature\Couple;

use Database\Seeders\CoupleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email = 'lesya@ix.app'): string
    {
        $this->seed(CoupleSeeder::class);

        return $this->postJson('/api/login', ['email' => $email, 'password' => 'kobra'])
            ->assertOk()->json('token');
    }

    /** Доска отдаёт участников пары в слотах исполнителя: owner→her, partner→him — с именами аккаунтов. */
    public function test_board_returns_couple_members_mapped_to_assignee_slots(): void
    {
        $token = $this->login();

        $members = collect($this->withToken($token)->getJson('/api/couple/board')
            ->assertOk()
            ->json('members'));

        $this->assertCount(2, $members);

        $bySlot = $members->keyBy('slot');
        $this->assertSame('Леся', $bySlot['her']['name']);   // owner (пригласила)
        $this->assertSame('Серёжа', $bySlot['him']['name']); // partner (принял)

        // appearance присутствует в контракте (null по умолчанию — фронт красит рамку по нему)
        $this->assertArrayHasKey('appearance', $bySlot['her']);
        $this->assertNull($bySlot['her']['appearance']);
    }
}
