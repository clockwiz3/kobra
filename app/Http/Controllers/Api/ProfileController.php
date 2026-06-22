<?php

namespace App\Http\Controllers\Api;

use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\Identity\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Тонкий контроллер профиля текущего юзера (Identity). За auth:sanctum, ВНЕ couple.context.
 * Доменная логика — в ProfileService. Возвращает UserData (с appearance) для синхрона стора.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        Log::debug('[ProfileController.update] request', ['user_id' => $user->id, 'keys' => array_keys($request->validated())]);

        $user = $this->profiles->updateProfile($user, $request->validated());

        return response()->json(UserData::fromModel($user));
    }
}
