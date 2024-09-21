<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Cache;

class AuthUserController extends Controller
{
    protected $maxAttempts = 3; // Максимальное количество попыток
    protected $decayMinutes = 6; // Время блокировки в минутах

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string|max:105',
            'password' => 'required|string',
        ]);

        $ipAddress = $request->ip();
        $loginKey = 'login.attempts.' . $ipAddress;

        if (Cache::has($loginKey) && Cache::get($loginKey) >= $this->maxAttempts) {
            $myLogInfo = date('Y-m-d H:i:s') . ', ' . $ipAddress . ', ' . $request->login . ', ' . $request->password;
            file_put_contents(
                storage_path() . '/logs/logins.log',
                $myLogInfo . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
            return response()->json(['error' => 'Zbyt wiele prób logowania. '], 429); //Please try again in ' . $this->decayMinutes . ' minutes.
        }

        // Поиск пользователя по логину
        $user = User::select('IDUzytkownika', 'IDRoli', 'NazwaUzytkownika', 'IDDefaultWarehouse', 'Login')->where('login', $request->login)->where('Aktywny', 1)->first();
        // Проверка существования пользователя и совпадения хэшированных паролей
        if ($user && $this->checkPassword($request->password, User::where('IDUzytkownika', $user->IDUzytkownika)->value('Haslo'))) {
            if ($user->IDRoli == 4) {
                $myLogInfo = date('Y-m-d H:i:s') . ', ' . $ipAddress . ', ' . $request->login;
                file_put_contents(
                    storage_path() . '/logs/Users.log',
                    $myLogInfo . PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );
            }
            // Сброс попыток после успешного входа
            Cache::forget($loginKey);

            // Создание токена JWT
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'token' => $token,
                'user' => $user,
            ], 200);
        } else {
            // Увеличение количества попыток
            Cache::increment($loginKey, 1);

            // Установка времени жизни кеша при первой ошибочной попытке
            if (Cache::get($loginKey) == 1) {
                Cache::put($loginKey, 1, now()->addMinutes($this->decayMinutes));
            }

            return response()->json(['error' => 'Nieautoryzowany'], 401);
        }
    }

    private function checkPassword($inputPassword, $storedHash)
    {
        // Проверка пароля с использованием вашего метода хеширования
        $inputHash = $this->getPasswordHash($inputPassword);
        return $inputHash === $storedHash;
    }

    public function getPasswordHash($password): string
    {
        $md5 = md5($password);
        $unHex = hex2bin($md5);
        return base64_encode($unHex);
    }
}
