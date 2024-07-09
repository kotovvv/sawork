<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
//use Laravel\Sanctum\HasApiTokens;

// app/Http/Controllers/AuthController.php
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('login', 'password');

        $passwordHash = $this->getPasswordHash($request->password);

        // Load user from Uzytkownik table
        $user = User::where('Login', $request->login)->first();

        // Check that user exists and hashed passwords are the same
        if ($user && $user->Haslo === $passwordHash) {
            $user = Auth::user();

            $token = $user->createToken('Personal Access Token')->accessToken;

            return response()->json([
                'token' => $token,
                'user' => $user,
            ], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function getPasswordHash($password): string
    {
        $md5 = md5($password);
        $unHex = hex2bin($md5);
        return base64_encode($unHex);
    }
}