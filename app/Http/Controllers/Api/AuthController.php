<?php

namespace App\Http\Controllers\Api;

use App\Data\AuthSessionData;
use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\Identity\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Тонкий контроллер аутентификации. Доменная логика — в AuthService (модуль Identity).
 * Единый 401 при провале (анти-enumeration). Stateless Bearer-токен (Sanctum).
 */
class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        Log::debug('[AuthController.login] attempt', ['ip' => $request->ip()]);

        $result = $this->auth->attempt($data['email'], $data['password']);
        abort_if($result === null, 401, 'Неверный email или пароль.');

        // Явный 200 (spatie Data на POST по умолчанию отдаёт 201; для логина конвенция — 200).
        return response()->json(new AuthSessionData($result['token'], UserData::fromModel($result['user'])));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return response()->json(['ok' => true]);
    }
}
