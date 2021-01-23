<?php

use Swoole\Http\Request;
use App\Services\WebSocket\WebSocket;
use App\Services\Websocket\Facades\Websocket as WebsocketProxy;
use Illuminate\Support\Facades\Cache;
use App\Count;
use App\User;
use App\Message;
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
    $websocket->emit('connect', '欢迎访问聊天室');

});

WebsocketProxy::on('disconnect', function (WebSocket $websocket, $data) {
    roomout($websocket, $data,true);
});

WebsocketProxy::on('login', function (WebSocket $websocket, $data) {
    if (!empty($data['api_token']) && ($user = \App\User::where('api_token', $data['api_token'])->first())) {
        //fd--->user_id
        Redis::set('chat_fd_'.$websocket->getSender(),$user->id);
        //user_id--->fd
        Redis::set('chat_userId_'.$user->id,$websocket->getSender());

        //用户登录
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
        $websocket->to($websocket->getSender())->emit('count',$rooms);
        $websocket->toUser($user)->emit('count', $rooms);
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

        //房间id关联user_id和fd,由user_id和fd字符串拼接

        if (!Redis::sismember('chat_room_'.$roomId.'_user',$user->id)){
            Redis::sadd('chat_room_'.$roomId.'_user',$user->id);
//            Redis::sadd('chat_room_'.$roomId,$user->id.'_'.$websocket->getSender());
        }

//        Redis::sadd('chat_room_'.$roomId,$user->id.'_'.$websocket->getSender());

        // 重置用户与fd关联
        Redis::command('hset', ['socket_id', $user->id, $websocket->getSender()]);
        // 将该房间下用户未读消息清零
        $count = Count::where('user_id', $user->id)->where('room_id', $roomId)->first();
        if ($count){
            $count->count = 0;
            $count->save();
        }
        // 将用户加入指定房间
        $room = Count::$ROOMLIST[$roomId];
//        $websocket->join($room);
        // 打印日志
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
//        $websocket->to($room)->emit('room', $onlineUsers);

        $all = Redis::smembers('chat_room_'.$roomId.'_user');
        if ($all){
            foreach ($all as $user_id){
                $fd = Redis::get('chat_userId_'.$user_id);
                if ($fd){
                    $websocket->to($fd)->emit('room',$onlineUsers);
                }
//                $explode = explode('_',$item);
//                if (count($explode) > 1){
//                    $fd = $explode[1];
//                    $websocket->to($fd)->emit('room',$onlineUsers);
//                }
            }
        }
    } else {
        $websocket->emit('login', '登录后才能进入聊天室');
    }
});

WebsocketProxy::on('roomout', function (WebSocket $websocket, $data) {
    roomout($websocket, $data);
});

function roomout(WebSocket $websocket, $data, $disconnect = false) {
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

        $all = Redis::smembers('chat_room_'.$roomId.'_user');
        //给房间中所有人发送,当前在线人数
        if ($all){
            foreach ($all as $user_id){
                $fd = Redis::get('chat_userId_'.$user_id);
                if ($fd){
                    $websocket->to($fd)->emit('room',$onlineUsers);
                }
            }
        }
        //断开连接,删除缓存
        if ($disconnect){
            $fd = Redis::get('chat_userId_'.$user->id);
            Redis::del('chat_userId_'.$user->id);
            if ($fd){
                Redis::del('chat_fd_'.$fd);
            }
        }

//        //判断数值是否在集合中
//        if (Redis::sismember('chat_room_'.$roomId,$user->id.'_'.$websocket->getSender())){
//            //删除集合
//            Redis::srem('chat_room_'.$roomId,$user->id.'_'.$websocket->getSender());
//            $all = Redis::smembers('chat_room_'.$roomId);
//            //给房间中所有人发送
//            if ($all){
//                foreach ($all as $item){
//                    $explode = explode('_',$item);
//                    if (count($explode) > 1){
//                        $fd = $explode[1];
//                        $websocket->to($fd)->emit('room',$onlineUsers);
//                    }
//                }
//            }
//        }

//        $websocket->to($room)->emit('roomout', $onlineUsers);
//        $websocket->leave([$room]);
    } else {
        $websocket->emit('login', '登录后才能进入聊天室');
    }
}

WebsocketProxy::on('message', function (WebSocket $websocket, $data) {
    if (!empty($data['api_token']) && ($user = User::where('api_token', $data['api_token'])->first())) {
        // 获取消息内容
        $msg = $data['msg'];
        $roomId = intval($data['roomid']);
        $time = $data['time'];
        // 消息内容或房间号不能为空
        if(empty($msg) || empty($roomId)) {
            return;
        }
        // 记录日志
        // 将消息保存到数据库
        $message = new Message();
        $message->user_id = $user->id;
        $message->room_id = $roomId;
        $message->msg = $msg;
        $message->img = ''; // 图片字段暂时留空
        $message->created_at = Carbon::now();
        $message->save();
        // 将消息广播给房间内所有用户
//        $room = Count::$ROOMLIST[$roomId];
        $messageData = [
            'userid' => $user->email,
            'username' => $user->name,
            'src' => $user->avatar,
            'msg' => $msg,
            'img' => '',
            'roomid' => $roomId,
            'time' => $time
        ];
        //广播给所有在房间的用户
        $all = Redis::smembers('chat_room_'.$roomId.'_user');
        if ($all){
            foreach ($all as $user_id){
                $fd = Redis::get('chat_userId_'.$user_id);
                if ($fd){
                    $websocket->to($fd)->emit('message',$messageData);
                }
            }
        }

//        $websocket->to($roomId)->emit('message', $messageData);
//        $all = Redis::smembers('chat_room_'.$roomId);
//        if ($all){
//            foreach ($all as $item){
//                $explode = explode('_',$item);
//                if (count($explode) > 1 && $explode[0] != $user->id){
//                    $fd = $explode[1];
//                    $websocket->to($fd)->emit('message',$messageData);
//                }
//            }
//        }

//        // 更新所有用户本房间未读消息数
//        $userIds = Redis::hgetall('socket_id');
//        foreach ($userIds as $userId => $socketId) {
//            // 更新每个用户未读消息数并将其发送给对应在线用户
//            $result = Count::where('user_id', $userId)->where('room_id', $roomId)->first();
//            if ($result) {
//                $result->count += 1;
//                $result->save();
//                $rooms[$roomId] = $result->count;
//            } else {
//                // 如果某个用户未读消息数记录不存在，则初始化它
//                $count = new Count();
//                $count->user_id = $user->id;
//                $count->room_id = $roomId;
//                $count->count = 1;
//                $count->save();
//                $rooms[$roomId] = 1;
//            }
//            $websocket->to($socketId)->emit('count', $rooms);
//        }
    } else {
        $websocket->emit('login', '登录后才能进入聊天室');
    }
});
