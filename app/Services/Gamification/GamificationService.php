<?php

namespace App\Services\Gamification;

use App\Enums\CoupleStatus;
use App\Models\Achievement;
use App\Models\AchievementUnlock;
use App\Models\ActivityEvent;
use App\Models\Couple;
use App\Models\GamificationProfile;
use App\Models\User;
use App\Support\CoupleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Геймификация шарда. Берёт ЯВНЫЙ Couple (никогда не ambient), мостит его в CoupleContext
 * для записи scoped-моделей. Начисляет ТОЛЬКО для active-шарда (archived = freeze).
 * Стрик upside-only: растёт от активности хотя бы одного партнёра, не ломается за бездействие.
 */
class GamificationService
{
    public function __construct(private readonly CoupleContext $context)
    {
    }

    /** Эагерно создать единственный профиль пары (идемпотентно). Хук CoupleActivated. */
    public function ensureProfile(Couple $couple): GamificationProfile
    {
        return $this->context->withCouple($couple->id, function () use ($couple) {
            $profile = GamificationProfile::firstOrCreate([]); // couple_id проставит CoupleScoped
            Log::info('[GamificationService.ensureProfile] profile ready', [
                'couple_id' => $couple->id,
                'profile_id' => $profile->id,
            ]);

            return $profile;
        });
    }

    /**
     * Записать событие пары + XP + стрик + ачивки. Возвращает событие (или существующее при idem-hit).
     */
    public function recordEvent(
        Couple $couple,
        string $type,
        ?User $actor = null,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): ?ActivityEvent {
        if ($couple->status !== CoupleStatus::Active) {
            Log::warning('[GamificationService.recordEvent] skipped: couple not active', [
                'couple_id' => $couple->id,
                'status' => $couple->status->value,
            ]);

            return null;
        }

        return $this->context->withCouple($couple->id, fn () => DB::transaction(function () use ($couple, $type, $actor, $payload, $idempotencyKey) {
            if ($idempotencyKey !== null) {
                $existing = ActivityEvent::where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    Log::debug('[GamificationService.recordEvent] idempotent hit', ['couple_id' => $couple->id, 'key' => $idempotencyKey]);

                    return $existing;
                }
            }

            $event = ActivityEvent::create([
                'actor_user_id' => $actor?->id,
                'type' => $type,
                'payload' => $payload !== [] ? $payload : null,
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => now(),
            ]);

            $profile = GamificationProfile::firstOrCreate([]);
            $this->applyXp($profile, $type, $event);
            $this->advanceStreak($profile);
            $profile->save();

            $this->evaluateAchievements($profile, $actor);

            Log::debug('[GamificationService.recordEvent] recorded', [
                'couple_id' => $couple->id,
                'type' => $type,
                'xp' => $profile->xp,
                'level' => $profile->level,
                'streak' => $profile->current_streak,
            ]);

            return $event;
        }));
    }

    private function applyXp(GamificationProfile $profile, string $type, ActivityEvent $event): void
    {
        // exactly-once: не начислять за уже обработанное событие
        if ($profile->last_processed_event_id !== null && $event->id <= $profile->last_processed_event_id) {
            return;
        }
        $map = config('gamification.xp_per_event', []);
        $profile->xp += $map[$type] ?? $map['default'] ?? 0;
        $profile->last_processed_event_id = $event->id;
        $profile->level = $this->levelForXp($profile->xp);
    }

    private function levelForXp(int $xp): int
    {
        $level = 1;
        foreach (config('gamification.level_thresholds', [0]) as $i => $threshold) {
            if ($xp >= $threshold) {
                $level = $i + 1;
            }
        }

        return $level;
    }

    /** Инвертированный стрик: +1 если был вчера, =1 иначе; сегодня — no-op. Никогда не штраф. */
    private function advanceStreak(GamificationProfile $profile): void
    {
        $today = now()->toDateString();
        $last = $profile->streak_last_qualified_on?->toDateString();
        if ($last === $today) {
            return;
        }
        $profile->current_streak = ($last === now()->subDay()->toDateString())
            ? $profile->current_streak + 1
            : 1;
        $profile->streak_last_qualified_on = $today;
        $profile->longest_streak = max($profile->longest_streak, $profile->current_streak);
    }

    private function evaluateAchievements(GamificationProfile $profile, ?User $actor): void
    {
        $unlocked = AchievementUnlock::pluck('achievement_id')->all();
        $candidates = Achievement::whereNotIn('id', $unlocked !== [] ? $unlocked : [0])->get();

        foreach ($candidates as $achievement) {
            if ($this->criteriaMet($achievement->criteria ?? [], $profile)) {
                AchievementUnlock::create([
                    'achievement_id' => $achievement->id,
                    'unlocked_by_user_id' => $actor?->id,
                    'unlocked_at' => now(),
                ]);
                Log::info('[GamificationService.unlock] achievement unlocked', [
                    'couple_id' => $profile->couple_id,
                    'achievement' => $achievement->code,
                ]);
            }
        }
    }

    private function criteriaMet(array $criteria, GamificationProfile $profile): bool
    {
        return match ($criteria['type'] ?? null) {
            'streak_gte' => $profile->current_streak >= ($criteria['value'] ?? PHP_INT_MAX),
            'xp_gte' => $profile->xp >= ($criteria['value'] ?? PHP_INT_MAX),
            'level_gte' => $profile->level >= ($criteria['value'] ?? PHP_INT_MAX),
            'event_type' => ActivityEvent::where('type', $criteria['value'] ?? '')->exists(),
            default => false,
        };
    }
}
