<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\CoupleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'test@cuploid.app',
            'password' => Hash::make('secret123'),
        ]);

        $res = $this->postJson('/api/login', [
            'email' => 'test@cuploid.app',
            'password' => 'secret123',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'avatar']])
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_wrong_password_returns_generic_401(): void
    {
        User::factory()->create([
            'email' => 'test@cuploid.app',
            'password' => Hash::make('secret123'),
        ]);

        $res = $this->postJson('/api/login', [
            'email' => 'test@cuploid.app',
            'password' => 'wrong-password',
        ]);

        $res->assertStatus(401)->assertJson(['message' => 'Неверный email или пароль.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_unknown_email_returns_identical_generic_401(): void
    {
        // Анти-enumeration: ответ обязан совпадать с кейсом «неверный пароль».
        $res = $this->postJson('/api/login', [
            'email' => 'nobody@cuploid.app',
            'password' => 'whatever',
        ]);

        $res->assertStatus(401)->assertJson(['message' => 'Неверный email или пароль.']);
    }

    public function test_passwordless_account_cannot_login_with_password(): void
    {
        // У Telegram-/passwordless-аккаунтов password = null — вход по паролю невозможен.
        User::factory()->create([
            'email' => 'tg@cuploid.app',
            'password' => null,
        ]);

        $res = $this->postJson('/api/login', [
            'email' => 'tg@cuploid.app',
            'password' => 'anything',
        ]);

        $res->assertStatus(401)->assertJson(['message' => 'Неверный email или пароль.']);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create([
            'email' => 'test@cuploid.app',
            'password' => Hash::make('secret123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'test@cuploid.app', 'password' => 'wrong-password'])
                ->assertStatus(401);
        }

        $this->postJson('/api/login', ['email' => 'test@cuploid.app', 'password' => 'wrong-password'])
            ->assertStatus(429);
    }

    public function test_logout_revokes_token(): void
    {
        $this->seed(CoupleSeeder::class);

        $token = $this->postJson('/api/login', [
            'email' => 'lesya@ix.app',
            'password' => 'kobra',
        ])->assertOk()->json('token');

        // Токен работает — доступ к couple-API есть.
        $this->withToken($token)->getJson('/api/couple/board')->assertOk();

        // Логаут отзывает токен.
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Сбросить закэшированный в рамках теста guard, чтобы следующий запрос
        // заново резолвил юзера из (уже удалённого) токена — как в реальном запросе.
        $this->app['auth']->forgetGuards();

        // Отозванным токеном доступа больше нет.
        $this->withToken($token)->getJson('/api/couple/board')->assertStatus(401);
    }

    public function test_authenticated_user_with_active_couple_reaches_board(): void
    {
        $this->seed(CoupleSeeder::class);

        $token = $this->postJson('/api/login', [
            'email' => 'seryozha@ix.app',
            'password' => 'kobra',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->getJson('/api/couple/board')
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'columns']);
    }
}
