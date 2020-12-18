<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        Event::listen('laravels.received_request', function (Request $request, $app) {
            $request->query->set('get_key', 'swoole-get-param');// 修改 GET 请求参数
            $request->request->set('post_key', 'swoole-post-param'); // 修改 POST 请求参数
        });
        Event::listen('laravels.generated_response', function (Request $request, Response $response, $app) {
            $response->headers->set('header-key', 'swoole-header');
        });
        //
    }
}
