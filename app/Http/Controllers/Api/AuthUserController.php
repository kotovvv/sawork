<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthUserController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string|max:105',
            'password' => 'required|string',
        ]);

        // Поиск пользователя по логину
        $user = User::where('login', $request->login)->first();
        // Проверка существования пользователя и совпадения хэшированных паролей
        if ($user && $this->checkPassword($request->password, $user->Haslo)) {
            // Создание токена JWT
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'token' => $token,
                'user' => $user,
            ], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
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