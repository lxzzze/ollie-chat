<?php

use Swoole\Http\Request;
use App\Services\WebSocket\WebSocket;
use App\Services\Websocket\Facades\Websocket as WebsocketProxy;
use Illuminate\Support\Facades\Cache;
use App\Count;
use App\User;
use App\Message;
use App\UserChatRecord;
use App\UserMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
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
});

WebsocketProxy::on('disconnect', function (WebSocket $websocket, $data) {
    //连接断开,删除关联redis数据
    $user_id = Redis::get('chat_fd_'.$websocket->getSender());
    if ($user_id){
        Redis::del('chat_fd_'.$websocket->getSender());
        Redis::del('chat_userId_'.$user_id);
        //循环公共聊天室
        $rooms = [1,2];
        foreach ($rooms as $roomId){
            if (Redis::sismember('chat_room_'.$roomId.'_user',$user_id)){
                Redis::srem('chat_room_'.$roomId.'_user',$user_id);
            }
            //删除在线数据
            $roomUsersKey = 'online_users_' . $roomId;
            $onlineUsers = Cache::get($roomUsersKey);
            if (!empty($onlineUsers[$user_id])) {
                //用户在线
                unset($onlineUsers[$user_id]);
                Cache::forever($roomUsersKey, $onlineUsers);
                //广播给房间内的用户
                emitToRoom($websocket,$roomId,'room',$onlineUsers);
            }
        }

    }
});

WebsocketProxy::on('login', function (WebSocket $websocket, $data) {
    if (!empty($data['api_token']) && ($user = \App\User::where('api_token', $data['api_token'])->first())) {
        //fd--->user_id
        Redis::set('chat_fd_'.$websocket->getSender(),$user->id);
        //user_id--->fd
        Redis::set('chat_userId_'.$user->id,$websocket->getSender());
    } else {
        $websocket->emit('login', '登录后才能进入聊天室');
    }
});

WebsocketProxy::on('room', function (WebSocket $websocket, $data) {
    if (!empty($data['api_token']) && ($user = User::where('api_token', $data['api_token'])->first())) {
        // 从请求数据中获取房间ID
        if (empty($data['roomid'])) {
            return;
        }
        $roomId = $data['roomid'];

        if (!Redis::sismember('chat_room_'.$roomId.'_user',$user->id)){
            Redis::sadd('chat_room_'.$roomId.'_user',$user->id);
        }
        // 将用户加入指定房间
        $room = Count::$ROOMLIST[$roomId];
        // 更新在线用户信息
        $roomUsersKey = 'online_users_' . $room;
        $onlineUsers = Cache::get($roomUsersKey);
        $user->src = $user->avatar;
        if ($onlineUsers) {
            $onlineUsers[$user->id] = $user;
            Cache::forever($roomUsersKey, $onlineUsers);
        } else {
            $onlineUsers = [
                $user->id => $user
            ];
            Cache::forever($roomUsersKey, $onlineUsers);
        }

        // 广播消息给房间内所有用户
        emitToRoom($websocket,$roomId,'room',$onlineUsers);

    } else {
        $websocket->emit('login', '登录后才能进入聊天室');
    }
});

WebsocketProxy::on('roomout', function (WebSocket $websocket, $data) {
    roomout($websocket, $data);
});

function roomout(WebSocket $websocket, $data) {
    if (!empty($data['api_token']) && ($user = User::where('api_token', $data['api_token'])->first())) {
        if (empty($data['roomid'])) {
            return;
        }
        $roomId = $data['roomid'];
        $room = Count::$ROOMLIST[$roomId];
        // 更新在线用户信息
        $roomUsersKey = 'online_users_' . $room;
        $onlineUsers = Cache::get($roomUsersKey);
        if (!empty($onlineUsers[$user->id])) {
            unset($onlineUsers[$user->id]);
            Cache::forever($roomUsersKey, $onlineUsers);
        }
        if (Redis::sismember('chat_room_'.$roomId.'_user',$user->id)){
            Redis::srem('chat_room_'.$roomId.'_user',$user->id);
        }
        //给房间中所有人发送,当前在线人数
        emitToRoom($websocket,$roomId,'room',$onlineUsers);
    } else {
        $websocket->emit('login', '登录后才能进入聊天室');
    }
}

WebsocketProxy::on('message', function (WebSocket $websocket, $data) {
    if (!empty($data['api_token']) && ($user = User::where('api_token', $data['api_token'])->first())) {
        // 获取消息内容
        $msg = $data['msg'];
        $img = $data['img'];
        $roomId = intval($data['roomid']);
        $time = $data['time'];
        // 消息内容（含图片）或房间号不能为空
        if((empty($msg)  && empty($img))|| empty($roomId)) {
            return;
        }
        if (empty($img)){
            // 将消息保存到数据库
            $message = new Message();
            $message->user_id = $user->id;
            $message->room_id = $roomId;
            $message->msg = $msg;
            $message->img = ''; // 图片字段暂时留空
            $message->created_at = Carbon::now();
            $message->save();
        }

        $messageData = [
            'userid' => $user->email,
            'username' => $user->name,
            'src' => $user->avatar,
            'msg' => $msg,
            'img' => $img,
            'roomid' => $roomId,
            'time' => $time
        ];
        //广播给所有在房间的用户
        emitToRoom($websocket,$roomId,'message',$messageData);
    } else {
        $websocket->emit('login', '登录后才能进入聊天室');
    }
});


WebsocketProxy::on('friendMessage',function (Websocket $websocket,$data) {
    if (!empty($data['api_token']) && ($user = User::where('api_token', $data['api_token'])->first())) {
        // 获取消息内容
        $msg = $data['msg'];
        $img = $data['img'];
        $time = $data['time'];
        // 消息内容（含图片）或好友id不能为空
        if((empty($msg)  && empty($img))|| empty($data['friendId'])) {
            return;
        }
        if (empty($img)){
            // 将消息保存到数据库
            $message = new UserMessage();
            $message->from_user_id = intval($user->id);
            $message->to_user_id = $data['friendId'];
            $message->msg = $msg;
            $message->img = ''; // 图片字段暂时留空
            $message->created_at = Carbon::now();
            $message->time = time();
            $message->save();
        }
        $messageData = [
            'userid' => $user->email,
            'username' => $user->name,
            'src' => $user->avatar,
            'msg' => $msg,
            'img' => $img,
            'friend_id' => $data['friendId'],
            'created_at' => $time
        ];
        //添加聊天记录
        UserChatRecord::query()->updateOrCreate(
            ['user_id'=>$user->id,'friend_id'=>$data['friendId']],
            ['time'=>time()]
        );
        UserChatRecord::query()->updateOrCreate(
            ['user_id'=>$data['friendId'],'friend_id'=>$user->id],
            ['time'=>time()]
        );
        //发送给自己
        $websocket->to($websocket->getSender())->emit('friendMessage',$messageData);
        //判断用户是否在线,若在线则发送消息
        $user_fd = Redis::get('chat_userId_'.$data['friendId']);
        if ($user_fd){
            $websocket->to($user_fd)->emit('friendMessage',$messageData);
        }
    } else {
        $websocket->emit('login', '登录后才能进入聊天室');
    };
});

//给房间内在线的人发送广播
function emitToRoom(WebSocket $websocket,$roomId,$event,$data)
{
    $all = Redis::smembers('chat_room_'.$roomId.'_user');
    //给房间中所有人发送,当前在线人数
    if ($all){
        foreach ($all as $user_id){
            $fd = Redis::get('chat_userId_'.$user_id);
            if ($fd){
                $websocket->to($fd)->emit($event,$data);
            }
        }
    }
}
