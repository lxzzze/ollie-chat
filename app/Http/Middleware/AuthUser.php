<?php

namespace App\Http\Middleware;

use App\User;
use Closure;

class AuthUser
{
    /**
     * 获取当前用户id
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $api_token = $request->input('api_token');
        $user = User::query()->select('id')->where('api_token', $api_token)->first();
        if ($user){
            request()->attributes->add(['user_id'=>$user->id]);
        }

        return $next($request);
    }
}
