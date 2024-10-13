<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class JWTMiddleware
{
    public function handle($request, Closure $next)
    {

        try {
            // Пытаемся получить пользователя по токену
            $user = JWTAuth::parseToken()->authenticate();
            // Сохраняем пользователя в запросе, чтобы получить его в контроллерах
            $request->user = $user;
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token wygasł'], 401)->header('Refresh', '1;url=' . $request->fullUrl());
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token jest nieprawidłowy'], 401)->header('Refresh', '1;url=' . $request->fullUrl());
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token nie został dostarczony'], 401)->header('Refresh', '1;url=' . $request->fullUrl());
        }

        return $next($request);
    }
}
