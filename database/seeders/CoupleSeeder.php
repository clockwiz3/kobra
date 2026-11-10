<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Couples\CoupleInvitationService;
use App\Services\Kanban\BoardService;
use App\Support\CoupleContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Наша пара: Леся + Серёжа. Пароль берётся из COUPLE_SEED_PASSWORD (config app.seed_password),
 * если пусто — генерируется случайный и печатается в консоль один раз.
 *
 * Идемпотентно: повторный seed не плодит пользователей и пары. Shard поднимается
 * штатным lifecycle (invite → accept), поэтому gamification-профиль создаётся через
 * CoupleActivated и DB-инвариант «одна активная пара на юзера» соблюдается как в проде.
 * Дефолтная доска создаётся в couple-context (couple_id проставит CoupleScoped).
 */
class CoupleSeeder extends Seeder
{
    public function run(): void
    {
        $configured = (string) config('app.seed_password');
        $password = $configured !== '' ? $configured : Str::password(16, symbols: false);

        $lesya = User::firstOrCreate(
            ['email' => 'lesya@ix.app'],
            [
                'name' => 'Леся',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'locale' => 'ru',
            ],
        );

        $seryozha = User::firstOrCreate(
            ['email' => 'seryozha@ix.app'],
            [
                'name' => 'Серёжа',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'locale' => 'ru',
            ],
        );

        // Уже в активной паре — ничего не делаем (идемпотентность повторного seed).
        if ($lesya->activeCouple() !== null) {
            $this->command?->info('CoupleSeeder: пара уже активна — пропуск.');

            return;
        }

        // Поднимаем shard штатным путём: Леся приглашает → Серёжа принимает.
        // accept() диспатчит CoupleActivated → синхронно создаётся gamification_profiles.
        $invitations = app(CoupleInvitationService::class);
        $invitation = $invitations->invite($lesya, $seryozha->email);
        $couple = $invitations->accept($invitation, $seryozha);
        $couple->update(['name' => 'Леся & Серёжа']);

        // Дефолтная доска пары — внутри её couple-context.
        app(CoupleContext::class)->withCouple($couple->id, function () {
            app(BoardService::class)->ensureDefaultBoard();
        });

        $this->command?->info("CoupleSeeder: пара #{$couple->id} «Леся & Серёжа» активирована.");
        if ($configured === '' && $lesya->wasRecentlyCreated) {
            $this->command?->warn("  Сгенерирован пароль для обоих аккаунтов: {$password}");
        }
    }
}
