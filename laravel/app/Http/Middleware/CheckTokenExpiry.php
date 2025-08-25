<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Carbon\Carbon;

class CheckTokenExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Token không tồn tại'], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            return response()->json(['message' => 'Token không hợp lệ'], 401);
        }

        $expireHours = config('sanctum.token_expire_hours', env('TOKEN_EXPIRE_HOURS', 2));
        $expireDays  = config('sanctum.token_remember_expire_days', env('TOKEN_REMEMBER_EXPIRE_DAYS', 30));
        
        $createdAt = Carbon::parse($accessToken->created_at);

        if (str_contains($accessToken->name, 'remember') && $createdAt->lt(now()->subDays($expireDays))) {
            $accessToken->delete();
            return response()->json(['message' => 'Token đã hết hạn'], 401);
        }

        // Token bình thường hết hạn sau X giờ
        if (!str_contains($accessToken->name, 'remember') && $createdAt->lt(now()->subHours($expireHours))) {
            $accessToken->delete();
            return response()->json(['message' => 'Token đã hết hạn'], 401);
        }

        return $next($request);
    }
}
