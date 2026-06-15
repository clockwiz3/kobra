<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JoinWaitlistRequest;
use App\Services\Waitlist\WaitlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class WaitlistController extends Controller
{
    public function __construct(private readonly WaitlistService $service)
    {
    }

    /** Запись в вайтлист (double-opt-in). Generic-ответ — без enumeration. */
    public function join(JoinWaitlistRequest $request): JsonResponse
    {
        $this->service->join([
            'email' => Str::lower(trim((string) $request->validated('email'))),
            'name' => $request->validated('name'),
            'utm_source' => $request->validated('utm_source'),
            'utm_medium' => $request->validated('utm_medium'),
            'utm_campaign' => $request->validated('utm_campaign'),
            'locale' => $request->validated('locale'),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Проверьте почту — мы отправили письмо для подтверждения.',
        ], 202);
    }

    /** Подтверждение по токену. Идемпотентно; неверный/просроченный → дружелюбный 410. */
    public function confirm(string $token): JsonResponse
    {
        $entry = $this->service->confirm($token);

        if ($entry === null) {
            return response()->json([
                'message' => 'Ссылка недействительна или устарела. Запросите подтверждение заново.',
            ], 410);
        }

        return response()->json([
            'confirmed' => true,
            'message' => 'Спасибо! Подписка подтверждена.',
        ]);
    }

    /** Статус + статичная позиция (если подтверждён). */
    public function status(string $token): JsonResponse
    {
        $status = $this->service->status($token);

        if ($status === null) {
            return response()->json(['message' => 'Не найдено.'], 404);
        }

        return response()->json($status);
    }
}
