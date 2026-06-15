<?php

namespace App\Services\Couples;

use App\Enums\CoupleRole;
use App\Enums\CoupleStatus;
use App\Events\CoupleActivated;
use App\Models\Couple;
use App\Models\CoupleInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Владелец жизненного цикла ШАРДА: создание (invite), активация (accept).
 */
class CoupleInvitationService
{
    /**
     * Создать pending-шард и инвайт партнёра. Инициатор добавляется как owner (is_active=false).
     */
    public function invite(User $inviter, ?string $email = null, int $ttlHours = 168): CoupleInvitation
    {
        return DB::transaction(function () use ($inviter, $email, $ttlHours) {
            $couple = Couple::create([
                'status' => CoupleStatus::Pending,
                'created_by' => $inviter->id,
            ]);

            $couple->members()->attach($inviter->id, [
                'role' => CoupleRole::Owner->value,
                'is_active' => false,
                'joined_at' => now(),
            ]);

            $invitation = $couple->invitations()->create([
                'inviter_id' => $inviter->id,
                'email' => $email,
                'invite_code' => Str::lower(Str::random(24)),
                'status' => 'pending',
                'expires_at' => now()->addHours($ttlHours),
            ]);

            Log::info('[CoupleInvitationService.invite] invite created', [
                'couple_id' => $couple->id,
                'inviter_id' => $inviter->id,
            ]);

            return $invitation;
        });
    }

    /**
     * Принять инвайт: привязать партнёра, активировать shard (триггер геймификации).
     * Инвариант «одна активная пара на юзера» обеспечивает partial unique index (fail-closed).
     *
     * @throws RuntimeException если инвайт нельзя принять (просрочен/использован/самопринятие).
     */
    public function accept(CoupleInvitation $invitation, User $accepter): Couple
    {
        if (! $invitation->isPending() || $invitation->isExpired()) {
            Log::warning('[CoupleInvitationService.accept] rejected: not acceptable', [
                'invitation_id' => $invitation->id,
                'status' => $invitation->status,
                'expired' => $invitation->isExpired(),
            ]);
            throw new RuntimeException('Invitation is expired or already used.');
        }

        if ((int) $invitation->inviter_id === (int) $accepter->id) {
            Log::warning('[CoupleInvitationService.accept] rejected: self-accept', [
                'invitation_id' => $invitation->id,
                'user_id' => $accepter->id,
            ]);
            throw new RuntimeException('Cannot accept your own invitation.');
        }

        return DB::transaction(function () use ($invitation, $accepter) {
            // Сериализуем конкурентные accept'ы: лочим активные членства принимающего.
            DB::table('couple_user')
                ->where('user_id', $accepter->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            $couple = $invitation->couple()->lockForUpdate()->firstOrFail();

            $couple->members()->syncWithoutDetaching([
                $accepter->id => [
                    'role' => CoupleRole::Partner->value,
                    'is_active' => true,
                    'joined_at' => now(),
                ],
            ]);

            // Активировать обоих участников (partial unique отклонит, если кто-то уже в активной паре).
            DB::table('couple_user')
                ->where('couple_id', $couple->id)
                ->update(['is_active' => true, 'updated_at' => now()]);

            $couple->update(['status' => CoupleStatus::Active]);
            $invitation->update(['status' => 'accepted', 'accepted_by' => $accepter->id]);

            // Триггер создания gamification_profiles (слушатель из #5), в этой же транзакции.
            CoupleActivated::dispatch($couple);

            Log::info('[CoupleInvitationService.accept] shard activated', [
                'couple_id' => $couple->id,
                'members' => [(int) $invitation->inviter_id, (int) $accepter->id],
            ]);

            return $couple->refresh();
        });
    }
}
