<?php

namespace Tests\Feature\Identity;

use Database\Seeders\CoupleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PATCH /api/user — профиль текущего юзера (имя + appearance). Identity-слой, вне couple-context.
 * Леся = owner → слот her (см. BoardMemberData): appearance отражается в board.members[her].
 */
class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email = 'lesya@ix.app'): string
    {
        $this->seed(CoupleSeeder::class);

        return $this->postJson('/api/login', ['email' => $email, 'password' => 'kobra'])
            ->assertOk()->json('token');
    }

    /** @return array<string, mixed> валидный appearance.card */
    private function frame(string $color = '#ff0000', string $style = 'dashed'): array
    {
        return ['card' => ['paint' => ['kind' => 'solid', 'color' => $color], 'style' => $style, 'width' => 2, 'animated' => false]];
    }

    public function test_requires_auth(): void
    {
        $this->patchJson('/api/user', ['name' => 'Аноним'])->assertUnauthorized();
    }

    public function test_can_update_name(): void
    {
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/user', ['name' => 'Леся2'])
            ->assertOk()
            ->assertJsonPath('name', 'Леся2');

        $this->assertDatabaseHas('users', ['email' => 'lesya@ix.app', 'name' => 'Леся2']);
    }

    public function test_can_update_appearance(): void
    {
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/user', ['appearance' => $this->frame('#00ff88')])
            ->assertOk()
            ->assertJsonPath('appearance.card.paint.color', '#00ff88')
            ->assertJsonPath('appearance.card.style', 'dashed');
    }

    public function test_appearance_reflected_in_board_members(): void
    {
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/user', ['appearance' => $this->frame('#ff0000')])->assertOk();

        $members = collect($this->withToken($token)->getJson('/api/couple/board')->assertOk()->json('members'));
        $her = $members->firstWhere('slot', 'her');

        $this->assertSame('#ff0000', $her['appearance']['card']['paint']['color']);
    }

    public function test_updating_name_does_not_wipe_existing_appearance(): void
    {
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/user', ['appearance' => $this->frame('#123456')])->assertOk();
        // отдельный апдейт только имени не должен затирать appearance в settings
        $this->withToken($token)->patchJson('/api/user', ['name' => 'Леся3'])
            ->assertOk()
            ->assertJsonPath('appearance.card.paint.color', '#123456');
    }

    public function test_validation_rejects_bad_hex(): void
    {
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/user', ['appearance' => $this->frame('red')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('appearance.card.paint.color');
    }

    public function test_validation_rejects_bad_style(): void
    {
        $token = $this->login();

        $payload = $this->frame('#ff0000', 'wavy');
        $this->withToken($token)->patchJson('/api/user', ['appearance' => $payload])
            ->assertStatus(422)
            ->assertJsonValidationErrors('appearance.card.style');
    }

    public function test_validation_rejects_long_name(): void
    {
        $token = $this->login();

        $this->withToken($token)->patchJson('/api/user', ['name' => str_repeat('я', 51)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
