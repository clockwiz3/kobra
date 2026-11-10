<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SetUserPassword extends Command
{
    protected $signature = 'user:password {email}';

    protected $description = 'Сменить пароль пользователя (спросит новый пароль) и разлогинить его везде';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->error('Нет такого пользователя.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Новый пароль');
        if (mb_strlen($password) < 8) {
            $this->error('Нужно хотя бы 8 символов.');

            return self::FAILURE;
        }
        if ($password !== (string) $this->secret('Ещё раз')) {
            $this->error('Пароли не совпали.');

            return self::FAILURE;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();
        $user->tokens()->delete();

        Log::info('[SetUserPassword.handle] password changed', ['user_id' => $user->id]);
        $this->info("Пароль для {$user->email} обновлён, старые сессии сброшены.");

        return self::SUCCESS;
    }
}
