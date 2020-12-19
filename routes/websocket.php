<?php

use Swoole\Http\Request;
use App\Services\WebSocket\WebSocket;
use App\Services\Websocket\Facades\Websocket as WebsocketProxy;

/*
|--------------------------------------------------------------------------
| Websocket Routes
|--------------------------------------------------------------------------
|
| Here is where you can register websocket events for your application.
|
*/

WebsocketProxy::on('connect', function (WebSocket $websocket, Request $request) {
    // 发送欢迎信息
    $websocket->setSender($request->fd);
    $websocket->emit('connect', '欢迎访问聊天室');

});

WebsocketProxy::on('disconnect', function (WebSocket $websocket) {
    // called while socket on disconnect
});

WebsocketProxy::on('login', function (WebSocket $websocket, $data) {
    if (!empty($data['api_token']) && ($user = \App\User::where('api_token', $data['api_token'])->first())) {
        $websocket->loginUsing($user);
        // 获取未读消息
        $rooms = [];
        foreach (\App\Count::$ROOMLIST as $roomid) {
            // 循环所有房间
            $result = \App\Count::where('user_id', $user->id)->where('room_id', $roomid)->first();
            $roomid = 'room' . $roomid;
            if ($result) {
                $rooms[$roomid] = $result->count;
            } else {
                $rooms[$roomid] = 0;
            }
        }
        $websocket->toUser($user)->emit('count', $rooms);
    } else {
        $websocket->emit('login', '登录后才能进入聊天室');
    }
});
