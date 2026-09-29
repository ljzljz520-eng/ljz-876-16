<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * 草稿纸照片通过 <img src> 展示时无法携带 Authorization 头，
 * 因此除标准 Bearer Token 外，额外允许使用 ?token= 访问。
 */
class ScratchPhotoTokenAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        $token = $request->query('token');

        if (is_string($token) && $token !== '') {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken && $accessToken->tokenable) {
                $request->setUserResolver(fn () => $accessToken->tokenable);
                return $next($request);
            }
        }

        return response()->json(['message' => '未认证'], 401);
    }
}
